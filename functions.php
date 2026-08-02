<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * XPro 主题函数文件
 *
 * 注册自动加载器、主题钩子、归档排序、短代码等功能。
 *
 * @package XPro
 */


spl_autoload_register(function ($className) {
    $map = [
        'XPro'              => __DIR__ . '/libs/XPro.php',
        'Backup'            => __DIR__ . '/libs/Backup.php',
        'SeoHelper'         => __DIR__ . '/libs/SeoHelper.php',
        'ThumbnailHelper'   => __DIR__ . '/libs/ThumbnailHelper.php',
        'ContentFilter'     => __DIR__ . '/libs/ContentFilter.php',
        'HotSearch'         => __DIR__ . '/libs/HotSearch.php',
        'AjaxComment'       => __DIR__ . '/libs/AjaxComment.php',
    ];

    if (isset($map[$className])) {
        require_once $map[$className];
        return;
    }

    $file = __DIR__ . '/libs/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/libs/FunctionsConfig.php';

Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = ['ContentFilter', 'parseContent'];
Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = ['ContentFilter', 'parseContent'];
Typecho_Plugin::factory('Widget_Archive')->header = ['Add_Config', 'header'];  
Typecho_Plugin::factory('Widget_Archive')->footer = ['Add_Config', 'footer'];  
Typecho_Plugin::factory('admin/write-post.php')->bottom = ['Add_Config', 'Button'];  
Typecho_Plugin::factory('admin/write-page.php')->bottom = ['Add_Config', 'Button'];  

//Typecho_Plugin::factory('Widget_Abstract_Contents')->markdown = ['Markdown', 'parse'];
//Typecho_Plugin::factory('Widget_Abstract_Comments')->markdown = ['Markdown', 'parse'];

// class Markdown {
//     private static $parser = null;

//     /**
//      * 获取单例解析器（避免重复加载文件）
//      */
//     private static function getParser()
//     {
//         if (self::$parser === null) {
//             // 加载 ParsedownExtra 类
//             require_once __DIR__ . '/libs/Parsedown.php';
//             require_once __DIR__ . '/libs/ParsedownExtra.php';
//             require_once __DIR__ . '/libs/Parser.php';

//             self::$parser = new Parser();

//             // ---------- 可选配置 ----------
//             // 1. 将普通换行转为 <br>（符合 GFM 习惯）
//             self::$parser->setBreaksEnabled(true);

//             // 2. 转义 HTML 标签，防止 XSS（推荐开启）
//             //    开启后，用户输入的 HTML 会被转义为纯文本，只解析 Markdown
//             //self::$parser->setMarkupEscaped(false);

//             // 3. 安全模式，防止解析错误（推荐开启）
//             //    开启后，解析器会严格遵守 Markdown 规范，避免解析错误
//             //self::$parser->setSafeMode(false);
//         }
//         return self::$parser;
//     }

//     /**
//      * 静态回调方法，供 Typecho 调用
//      */
//     public static function parse($text)
//     {
//         $parser = self::getParser();
//         return $parser->text($text);
//     }
// }

/**
 * 主题初始化入口
 *
 * 处理点赞、作者资料更新、热搜记录、热门搜索 HTML 等请求，并修复 Typecho 1.3.0 row 缓存问题。
 *
 * @param \Widget\Archive $archive 文章归档对象
 */
function themeInit($archive) {
    if ($archive->is('single') && $archive->request->isPost() && $archive->request->get('themeAction') === 'comment') {
        AjaxComment::submit($archive);
    }

    if ($archive->is('single') && $archive->request->get('themeAction') === 'loadMoreComments') {
        AjaxComment::loadMore($archive);
    }

    if ($archive->request->get('action') == 'like') {
        header('Content-Type: application/json; charset=utf-8');
        $cid = (int) $archive->request->get('cid', 0);
        if ($cid <= 0) {
            echo json_encode(['error' => '无效文章']);
            exit;
        }
        $result = XPro::leLike($cid);
        echo json_encode(['success' => true, 'likes' => $result['likes'], 'liked' => $result['liked']]);
        exit;
    }

    if ($archive->is('single') && $archive->hidden) {
        $archive->response->setStatus(200);
    }

    if ($archive->is('author') && $archive->request->isPost() && $archive->request->is('xpro_profile_uid')) {
        handleXProProfileUpdate($archive);
    }

    if ($archive->request->get('action') == 'record') {
        $keyword = trim($archive->request->get('keyword', ''));
        if (!empty($keyword)) {
            HotSearch::log($keyword);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($archive->request->get('action') == 'hotSearchHTML') {
        header('Content-Type: text/html; charset=utf-8');
        HotSearch::render(10, [
            'wrapper' => '{items}',
            'item'    => '<span class="chip" role="button">{keyword}</span>',
            'empty'   => '<span>暂无热门搜索</span>',
        ]);
        exit;
    }

    $ref = new \ReflectionProperty(\Typecho\Widget::class, 'row');
    //$ref->setAccessible(true); PHP8.1及以上不需要
    $row = $ref->getValue($archive);
    unset(
        $row['#content'],
        $row['#excerpt'],
        $row['#plainExcerpt'],
        $row['#summary'],
        $row['#description']
    );
    $ref->setValue($archive, $row);
}

/**
 * 处理作者资料更新提交
 *
 * @param \Widget\Archive $archive 文章归档对象
 * @return void
 */
function handleXProProfileUpdate($archive): void {
    $user = Typecho\Widget::widget('Widget_User');
    if (!$user->hasLogin()) {
        return;
    }

    Helper::security()->protect();

    $uid = (int) $archive->request->filter('int')->get('xpro_profile_uid');
    if ($uid <= 0 || $uid !== (int) $user->uid) {
        return;
    }

    $bio      = trim((string) $archive->request->get('xpro_profile_bio'));
    $cover    = trim((string) $archive->request->get('xpro_profile_cover'));
    $avatar   = trim((string) $archive->request->get('xpro_profile_avatar'));
    $nickname = trim((string) $archive->request->get('xpro_profile_nickname'));
    $homepage = trim((string) $archive->request->get('xpro_profile_homepage'));
    $email    = trim((string) $archive->request->get('xpro_profile_email'));

    XPro::updateUserProfile($uid, [
        'bio'        => $bio,
        'cover'      => $cover,
        'avatar'     => $avatar,
        'screenName' => $nickname,
        'url'        => $homepage,
        'mail'       => $email,
    ]);

    $options = Typecho\Widget::widget('Widget_Options');
    $archive->response->redirect(Typecho\Router::url('author', ['uid' => $uid], $options->index));
    exit;
}

/**
 * 允许的归档排序方式
 *
 * @return array<string, string>
 */
function getAllowedArchiveSorts(): array
{
    return [
        'newest'        => '最新发布',
        'oldest'        => '最早发布',
        'most-comments' => '最多评论',
        'most-likes'    => '最多点赞',
        'most-views'    => '最多浏览',
    ];
}

/**
 * 获取当前归档页排序参数
 *
 * @return string
 */
function getArchiveSort(): string
{
    $sort = $_GET['sort'] ?? 'newest';
    if (!is_string($sort) || !array_key_exists($sort, getAllowedArchiveSorts())) {
        return 'newest';
    }
    return $sort;
}

/**
 * 生成指定排序方式的 URL
 *
 * @param string $url 原始 URL
 * @param string $sort 排序方式
 * @return string
 */
function buildArchiveSortUrl(string $url, string $sort): string
{
    $url = preg_replace('/([?&])sort=[^&]*&?/', '$1', $url);
    $url = rtrim($url, '?&');
    if ($sort !== 'newest') {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'sort=' . urlencode($sort);
    }
    return $url;
}

/**
 * 归档页排序钩子
 *
 * @param \Widget\Archive $archive 文章归档组件
 * @param \Typecho\Db\Query $select 数据库查询对象
 * @return void
 */
\Typecho\Plugin::factory('Widget_Archive')->query = function (\Widget\Archive $archive, \Typecho\Db\Query $select): void {
    $archiveTypes = [
        'category', 'category_page',
        'tag', 'tag_page',
        'author', 'author_page',
        'archive_year', 'archive_year_page',
        'archive_month', 'archive_month_page',
        'archive_day', 'archive_day_page',
        'archive', 'archive_page',
        'search', 'search_page',
    ];

    $type = $archive->parameter->type;
    if (in_array($type, $archiveTypes, true)) {
        $sort = getArchiveSort();
        $select->cleanAttribute('order');

        switch ($sort) {
            case 'oldest':
                $select->order('table.contents.created', \Typecho\Db::SORT_ASC);
                break;
            case 'most-comments':
                $select->order('table.contents.commentsNum', \Typecho\Db::SORT_DESC);
                break;
            case 'most-views':
                $select->order('table.contents.views', \Typecho\Db::SORT_DESC);
                break;
            case 'most-likes':
                $select->order('table.contents.likes', \Typecho\Db::SORT_DESC);
                break;
            case 'newest':
            default:
                $select->order('table.contents.created', \Typecho\Db::SORT_DESC);
                break;
        }
    }

    $archive->db->fetchAll($select, [$archive, 'push']);
};

/**
 * 热门文章 Widget
 *
 * 按浏览量降序排列，排除加密和未发布文章。
 *
 * @package XPro
 */
class Widget_Post_hot extends \Widget_Abstract_Contents
{
    /**
     * 执行查询并按浏览量降序输出
     *
     * @return void
     */
    public function execute(): void
    {
        $select = $this->select()
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created <= ?', time())
            ->where('table.contents.type = ?', 'post')
            ->limit((int) ($this->parameter->pageSize))
            ->order('table.contents.views', \Typecho\Db::SORT_DESC);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}

class Add_Config  
{  
    /**  
     * 添加额外编辑器按钮  
     *   
     */  
    public static function Button()  
    {  
        echo '<script type="text/javascript" src="/libs/libs.js"></script>';  
    }  
    
    /**  
     * 加载在头部  
     *   
     * @return Widget_Archive  
     */  
    public static function header($archive)  
    {  
        Typecho_Widget::widget('Widget_Options')->add_head();  
    }  
    
    /**  
     * 加载在尾部  
     *   
     * @return Widget_Archive  
     */  
    public static function footer($archive)  
    {  
        Typecho_Widget::widget('Widget_Options')->add_body();  
    }  
}