<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Db;
use Typecho\Router;
use Typecho\Cookie;
use Typecho\Widget;

/**
 * XPro 主题工具类
 *
 * @package XPro
 */
class XPro
{
    /**
     * 进程级摘要缓存
     *
     * 键为 content、长度等参数的 MD5，值为生成的摘要字符串。
     * 用于避免同一文章在列表页多次渲染时重复计算。
     *
     * @var array<string,string>
     */
    private static array $excerptCache = [];

    /**
     * 置顶文章 CID 缓存
     *
     * 按配置键（如 'sticky'）缓存解析后的 CID 数组，
     * 避免重复解析逗号分隔的字符串。
     *
     * @var array<string,array>
     */
    private static array $stickyCidCache = [];

    /**
     * 字段自检成功标记
     *
     * 记录哪些表的字段已经通过 ALTER 添加成功（如 views, likes），
     * 避免重复执行检查/修改语句。
     *
     * @var array<string,bool>
     */
    private static array $columnEnsured = [];

    /**
     * 数据库单例句柄
     *
     * 复用 Db 实例，减少重复获取开销。
     *
     * @var Db|null
     */
    private static ?Db $db = null;

    /**
     * 主题选项单例句柄
     *
     * 复用 Widget_Options 实例，便于快速读取主题配置。
     *
     * @var \Widget\Options|null
     */
    private static ?\Widget\Options $options = null;

    /**
     * 对字符串进行 HTML 转义（防 XSS）
     *
     * 默认直接输出，若 $return 为 true 则返回转义后的字符串。
     *
     * @param string $text   待转义的文本
     * @param bool   $return 是否返回结果（true 返回，false 直接输出）
     * @return string 当 $return 为 true 时返回转义后的字符串；否则返回空字符串（但会输出）
     */
    public static function esc($text = '', bool $return = false): string
    {
        $escaped = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($return) {
            return $escaped;
        }
        echo $escaped;
        return $escaped;
    }

    /**
     * 解析置顶文章的 CID 列表
     *
     * 从主题选项（如 'sticky'）读取逗号分隔的 CID 字符串，
     * 过滤非数字值，并缓存结果。
     *
     * @param string $optionKey 主题选项键名（默认 'sticky'）
     * @return array<int,int> 置顶文章 CID 的索引数组（值已转为整数）
     */
    public static function parseStickyCids(string $optionKey = 'sticky'): array
    {
        if (isset(self::$stickyCidCache[$optionKey])) {
            return self::$stickyCidCache[$optionKey];
        }

        $raw = (string) self::_getOptions()->{$optionKey};
        if ($raw === '') {
            return self::$stickyCidCache[$optionKey] = [];
        }

        $cids = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            'is_numeric'
        ));
        return self::$stickyCidCache[$optionKey] = $cids;
    }

    /**
     * 判断给定的 CID 是否为置顶文章
     *
     * @param int|string $cid 文章 CID
     * @return bool 若该 CID 在置顶列表中则返回 true
     */
    public static function isStickyCid($cid): bool
    {
        return in_array((string) $cid, self::parseStickyCids(), true);
    }

    /**
     * 应用置顶文章修正到归档对象
     *
     * 仅对首页生效：
     * - 第一页顶部插入置顶文章，普通文章补足到 pageSize
     * - 第二页起使用修正后的偏移量，保证每页 pageSize 条
     * - 置顶文章占用第一页容量，总页数按原始文章总数计算
     *
     * @param \Widget\Archive $archive   当前归档对象
     * @param string          $optionKey 置顶配置键名（默认 'sticky'）
     * @return void
     */
    public static function applyStickyPagination(\Widget\Archive $archive, string $optionKey = 'sticky'): void
    {
        if (!$archive->is('index')) {
            return;
        }

        $stickyCids  = self::parseStickyCids($optionKey);
        $stickyCount = count($stickyCids);
        if ($stickyCount === 0) {
            return;
        }

        $pageSize    = (int) $archive->parameter->pageSize;
        $currentPage = $archive->getCurrentPage();
        $db          = self::_getDb();
        $now         = (int) self::_getOptions()->time;
        $user        = Widget::widget('Widget_User');

        $applyStatus = static function ($select) use ($user) {
            if ($user->hasLogin()) {
                return $select->where(
                    'table.contents.status = ? OR (table.contents.status = ? AND table.contents.authorId = ?)',
                    'publish',
                    'private',
                    $user->uid
                );
            }
            return $select->where('table.contents.status = ?', 'publish');
        };

        $selectSticky = $archive->select()->where('type = ?', 'post');
        $selectSticky = $applyStatus($selectSticky);
        $selectSticky->where('table.contents.created < ?', $now);

        $selectNormal = $archive->select()->where('type = ?', 'post');
        $selectNormal = $applyStatus($selectNormal);
        $selectNormal->where('table.contents.created < ?', $now);

        foreach ($stickyCids as $i => $cid) {
            if ($i === 0) {
                $selectSticky->where('table.contents.cid = ?', $cid);
            } else {
                $selectSticky->orWhere('table.contents.cid = ?', $cid);
            }
            $selectNormal->where('table.contents.cid != ?', $cid);
        }

        self::_resetArchiveStack($archive);

        $stickyRows = $db->fetchAll($selectSticky);
        $orderMap   = array_flip(array_map('intval', $stickyCids));
        usort($stickyRows, static function ($a, $b) use ($orderMap): int {
            return ($orderMap[(int) ($a['cid'] ?? 0)] ?? PHP_INT_MAX)
                 - ($orderMap[(int) ($b['cid'] ?? 0)] ?? PHP_INT_MAX);
        });

        if ($currentPage === 1) {
            foreach ($stickyRows as $stickyPost) {
                $archive->push($stickyPost);
            }
            $normalLimit  = max(0, $pageSize - $stickyCount);
            $normalOffset = 0;
        } else {
            $normalLimit  = $pageSize;
            $normalOffset = ($currentPage - 2) * $pageSize + ($pageSize - $stickyCount);
        }

        $normalPosts = $db->fetchAll(
            $selectNormal
                ->order('table.contents.created', Db::SORT_DESC)
                ->limit($normalLimit)
                ->offset($normalOffset)
        );
        foreach ($normalPosts as $post) {
            $archive->push($post);
        }
    }

    /**
     * 解析轮播图数据（从主题选项的短代码块）
     *
     * 支持两种格式：
     * 1. 直接定义：[title="标题" url="链接" pic="图片" badge="角标" excerpt="简介"]
     * 2. 引用文章/页面：[post="123" pic="图片"] 或 [page="456" pic="图片"]，
     *    手动指定 pic 时优先使用；否则自动提取自定义字段 > 正文图片
     *
     * @param int|null $limit 最多返回的项数，null 表示不限制
     * @return array<int,array> 轮播项数组，具体字段根据类型不同
     */
    public static function parseCarousel(?int $limit = null): array
    {
        $raw = (string) (self::_getOptions()->carouselBanner ?? '');
        $items = [];
        foreach (self::_parseShortcodeBlock($raw) as $attrs) {
            $item = self::_resolveCarouselLine($attrs);
            if ($item === null || empty($item['title'])) {
                continue;
            }
            $items[] = $item;
            if ($limit !== null && count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    /**
     * 生成 Gravatar 头像 URL
     *
     * 使用主题选项中配置的 gravatars 源（默认为 https://gravatar.loli.net/avatar/），
     * 结合邮箱 MD5 和尺寸参数。
     *
     * @param string|null $mail 用户邮箱
     * @param int    $size 头像尺寸（像素）
     * @param bool   $out  若为 true 直接返回 URL，否则输出
     * @return string 头像 URL
     */
    public static function avatar(?string $mail, int $size = 100, bool $out = false, ?int $userId = null): string
    {
        if ($userId !== null && $userId > 0) {
            $customAvatar = self::getUserAvatar($userId);
            if ($customAvatar !== '') {
                if ($out) {
                    return $customAvatar;
                }
                echo $customAvatar;
                return $customAvatar;
            }
        }

        $source = rtrim((string) (self::_getOptions()->gravatars ?? 'https://gravatar.loli.net/avatar/'), '/');
        $mail   = strtolower(trim((string) ($mail ?? '')));
        $url    = $source . '/' . md5($mail) . '?s=' . $size . '&d=mp';
        if ($out) {
            return $url;
        }
        echo $url;
        return $url;
    }

    /**
     * 格式化相对时间
     *
     * @param int $timestamp Unix 时间戳
     * @return string 相对时间描述
     */
    public static function relativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return '刚刚';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' 分钟前';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' 小时前';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . ' 天前';
        }
        return self::formatDate($timestamp);
    }

    /**
     * 格式化日期
     *
     * @param int    $timestamp Unix 时间戳
     * @param string $format    日期格式
     * @return string 格式化后的日期
     */
    public static function formatDate(int $timestamp, string $format = 'Y-m-d'): string
    {
        return date($format, $timestamp);
    }

    /**
     * 格式化为 ISO 8601 日期
     *
     * @param int $timestamp Unix 时间戳
     * @return string ISO 8601 格式日期
     */
    public static function formatIsoDate(int $timestamp): string
    {
        return date('c', $timestamp);
    }

    /**
     * 格式化年份
     *
     * @param int|null $timestamp Unix 时间戳，默认当前时间
     * @return string 年份
     */
    public static function formatYear(?int $timestamp = null): string
    {
        return date('Y', $timestamp ?? time());
    }

    /**
     * 根据用户 ID 获取其指定字段的值
     *
     * @param int    $userID 用户 UID
     * @param string $field  字段名（如 'screenName', 'name', 'mail' 等）
     * @return string 字段值，若不存在则返回空字符串
     */
    public static function getUserInfo(int $userID, string $field = 'screenName'): string
    {
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select($field)->from('table.users')->where('uid = ?', $userID)
        );
        return $row ? (string) ($row[$field] ?? '') : '';
    }

    /**
     * 获取用户简介
     *
     * @param int $userID 用户 UID
     * @return string 简介内容，若不存在则返回空字符串
     */
    public static function getUserBio(int $userID): string
    {
        self::_ensureUserExtraFields();
        return self::getUserInfo($userID, 'bio');
    }

    /**
     * 获取用户封面图 URL
     *
     * @param int $userID 用户 UID
     * @return string 封面图 URL，若不存在则返回空字符串
     */
    public static function getUserCover(int $userID): string
    {
        self::_ensureUserExtraFields();
        return self::getUserInfo($userID, 'cover');
    }

    /**
     * 获取用户自定义头像 URL
     *
     * @param int $userID 用户 UID
     * @return string 头像 URL，若不存在则返回空字符串
     */
    public static function getUserAvatar(int $userID): string
    {
        self::_ensureUserExtraFields();
        return self::getUserInfo($userID, 'avatar');
    }

    /**
     * 更新用户扩展资料（简介、封面、头像）以及基础资料（昵称、主页）
     *
     * @param int   $userID 用户 UID
     * @param array $data   包含 bio / cover / avatar / screenName / url 的键值对
     * @return bool 是否更新成功
     */
    public static function updateUserProfile(int $userID, array $data): bool
    {
        self::_ensureUserExtraFields();

        $allowed = ['bio', 'cover', 'avatar', 'screenName', 'url', 'mail'];
        $rows    = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $rows[$field] = trim((string) $data[$field]);
            }
        }

        if (empty($rows) || $userID <= 0) {
            return false;
        }

        $db = self::_getDb();
        try {
            $db->query($db->update('table.users')->rows($rows)->where('uid = ?', $userID));
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 获取文章阅读量，并（在单页时）自动增加浏览量（防刷 Cookie 机制）
     *
     * @param mixed $archive 文章对象（必须包含 cid 属性）
     * @param int   $r       0 表示直接输出浏览量，非 0 表示返回浏览量数值
     * @return int|null 当 $r != 0 时返回浏览量，否则输出并返回 null
     */
    public static function getPostView($archive, $r = 0)
    {
        $cid = (int) ($archive->cid ?? 0);
        if ($cid <= 0) {
            return $r == 0 ? null : 0;
        }
        $db = self::_getDb();
        self::_ensureViewsField($db);

        $row   = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
        $views = $row ? (int) $row['views'] : 0;

        if (!empty($archive->is('single'))) {
            $cookieName = 'extend_contents_views';
            $visited = array_filter(
                array_map('trim', explode(',', (string) Cookie::get($cookieName))),
                'strlen'
            );
            if (!in_array((string) $cid, $visited, true)) {
                $db->query(
                    $db->update('table.contents')
                        ->rows(['views' => $views + 1])
                        ->where('cid = ?', $cid)
                );
                $visited[] = (string) $cid;
                Cookie::set($cookieName, implode(',', $visited));
                $views++;
            }
        }

        if ($r == 0) {
            echo $views;
            return null;
        }
        return $views;
    }

    /**
     * 获取文章的最深分类（即叶子分类）
     *
     * 若文章无分类或分类链异常，则返回 null。
     *
     * @param int         $cid    文章 CID
     * @param object|null $widget 可选的已实例化的 Archive 对象（若提供则优先使用其 categories 属性）
     * @return array|null 关联数组，包含 mid, name, slug, parent, permalink
     */
    public static function getPostCategory(int $cid, ?object $widget = null): ?array
    {
        return self::_pickDeepest(self::_resolveCategories($cid, $widget));
    }

    /**
     * 生成文章摘要
     *
     * 支持：
     * - 自动移除代码块（可开关）
     * - 智能截断：若在句号、问号等结束符附近截断，则尽可能完整截断
     * - 结果缓存（相同参数只计算一次）
     *
     * @param string $content  文章原始内容（HTML 或 Markdown 均可，但会 strip_tags）
     * @param int    $length   摘要最大长度（字符数，UTF-8）
     * @param string $suffix   截断时追加的后缀（默认 '...'）
     * @param bool   $smartCut 是否启用智能截断（在标点处截断）
     * @param bool   $skipCode 是否移除代码块（``` 或 <pre> 等）
     * @return string 生成的摘要文本
     */
    public static function excerpt(string $content = '', int $length = 160, string $suffix = '...', bool $smartCut = true, bool $skipCode = true): string
    {
        $key = md5($content . '|' . $length . '|' . (int) $smartCut . '|' . (int) $skipCode);
        if (isset(self::$excerptCache[$key])) {
            return self::$excerptCache[$key];
        }

        if ($skipCode) {
            $content = preg_replace('/```[\s\S]*?```/', '', $content) ?? $content;
            $content = preg_replace('/<pre[\s\S]*?<\/pre>/i', '', $content) ?? $content;
            $content = preg_replace('/<code[\s\S]*?<\/code>/i', '', $content) ?? $content;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return self::$excerptCache[$key] = $text;
        }

        $cut = mb_substr($text, 0, $length, 'UTF-8');
        if ($smartCut) {
            $endings = ['。', '！', '？', '；', '.', '!', '?', ';'];
            $maxPos  = 0;
            foreach ($endings as $e) {
                $pos = mb_strrpos($cut, $e, 0, 'UTF-8');
                if ($pos !== false && $pos > $maxPos) {
                    $maxPos = $pos;
                }
            }
            if ($maxPos > (int) ($length * 0.5)) {
                return self::$excerptCache[$key] = mb_substr($cut, 0, $maxPos + 1, 'UTF-8');
            }
        }
        return self::$excerptCache[$key] = $cut . $suffix;
    }

    /**
     * 估算文章阅读时间
     *
     * 中文按每个字符计 1 词，英文按 str_word_count 统计，总词数除以 WPM（默认 300）。
     *
     * @param string|object $content   文章内容或包含 content 属性的对象
     * @param int           $wpm       每分钟阅读词数（默认 300）
     * @param bool          $returnRaw 若为 true 返回分钟数（整数），否则返回本地化字符串（如 "3分钟"）
     * @return int|string 根据 $returnRaw 决定返回类型
     */
    public static function readingTime($content = '', int $wpm = 300, bool $returnRaw = false)
    {
        $text = is_object($content) ? (string) ($content->content ?? '') : (string) $content;
        $text = trim(strip_tags($text));
        if ($text === '') {
            return $returnRaw ? 1 : '1分钟';
        }
        $chinese = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text) ?: 0;
        $english = str_word_count(preg_replace('/[\x{4e00}-\x{9fff}]/u', ' ', $text) ?? '');
        $wpm     = max(60, $wpm);
        $minutes = max(1, (int) ceil(($chinese + $english) / $wpm));
        return $returnRaw ? $minutes : $minutes . '分钟';
    }

    /**
     * 获取文章点赞数
     *
     * @param int $cid 文章 CID
     * @return int 点赞数
     */
    public static function getPostLikes(int $cid): int
    {
        self::_ensureLikesFields();
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select('likes')->from('table.contents')->where('cid = ?', $cid)
        );
        return (int) ($row['likes'] ?? 0);
    }

    /**
     * 检查当前用户是否已点赞某篇文章
     *
     * 识别依据：已登录用户用 uid，未登录用户用 IP + User-Agent 的 MD5。
     *
     * @param int $cid 文章 CID
     * @return bool 若已点赞则返回 true
     */
    public static function hasUserLiked(int $cid): bool
    {
        self::_ensureLikesFields();
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select('likesData')->from('table.contents')->where('cid = ?', $cid)
        );
        if (empty($row['likesData'])) {
            return false;
        }
        $list = json_decode($row['likesData'], true);
        return is_array($list) && in_array(self::_getLikeIdentity(), $list, true);
    }

    /**
     * 点赞（已点赞则保持状态，不再取消）
     *
     * 自动更新 likes 字段和 likesData（存储用户标识列表）。
     *
     * @param int $cid 文章 CID
     * @return array{likes:int, liked:bool} 返回最新点赞数及当前是否已点赞
     */
    public static function leLike(int $cid): array
    {
        self::_ensureLikesFields();
        $db  = self::_getDb();
        $row = $db->fetchRow(
            $db->select('likes', 'likesData')->from('table.contents')->where('cid = ?', $cid)
        );
        if (!$row) {
            return ['likes' => 0, 'liked' => false];
        }

        $likes = (int) ($row['likes'] ?? 0);
        $list  = [];
        if (!empty($row['likesData'])) {
            $decoded = json_decode($row['likesData'], true);
            if (is_array($decoded)) {
                $list = $decoded;
            }
        }

        $identity = self::_getLikeIdentity();
        $liked    = in_array($identity, $list, true);
        if ($liked) {
            return ['likes' => $likes, 'liked' => true];
        }

        $likes++;
        $list[] = $identity;

        $db->query(
            $db->update('table.contents')->rows([
                'likes'     => $likes,
                'likesData' => json_encode($list, JSON_UNESCAPED_UNICODE),
            ])->where('cid = ?', $cid)
        );
        return ['likes' => $likes, 'liked' => true];
    }

    /**
     * 获取数据库单例
     *
     * @return Db
     */
    private static function _getDb(): Db
    {
        return self::$db ??= Db::get();
    }

    /**
     * 获取主题选项单例
     *
     * @return \Widget\Options
     */
    private static function _getOptions(): \Widget\Options
    {
        return self::$options ??= Widget::widget('Widget_Options');
    }

    /**
     * 清空归档对象的内部数据堆栈
     *
     * stack/length/row 为 Widget 受保护属性，从主题静态方法中直接赋值会走 __set，
     * 导致原始堆栈未被真正清空，从而出现文章重复。这里使用反射直接写入真实属性。
     *
     * @param \Widget\Archive $archive 当前归档对象
     */
    private static function _resetArchiveStack(\Widget\Archive $archive): void
    {
        $reflection = new ReflectionClass($archive);

        $rowProp = $reflection->getProperty('row');
        $rowProp->setValue($archive, []);

        $stackProp = $reflection->getProperty('stack');
        $stackProp->setValue($archive, []);

        $lengthProp = $reflection->getProperty('length');
        $lengthProp->setValue($archive, 0);
    }

    /**
     * 解析短代码块（每行一个 [key="value" ...]）
     *
     * @param string $content 多行文本
     * @return array<int,array> 每行解析出的属性数组
     */
    private static function _parseShortcodeBlock(string $content): array
    {
        $items = [];
        if ($content === '') {
            return $items;
        }
        foreach (preg_split("/\r?\n/", $content) as $line) {
            $line = trim($line);
            if ($line === '' || !preg_match('/^\[(.+)\]$/', $line, $matches)) {
                continue;
            }
            $items[] = self::_parseAttributes($matches[1]);
        }
        return $items;
    }

    /**
     * 解析键值对字符串（key="value"）
     *
     * @param string $body 如 'title="Hello" url="..."'
     * @return array<string,string> 属性名到值的映射
     */
    private static function _parseAttributes(string $body): array
    {
        $attrs = [];
        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $attrs[$m[1]] = $m[2];
            }
        }
        return $attrs;
    }

    /**
     * 解析轮播图单行短代码，尝试从文章/页面引用或直接属性生成轮播项
     *
     * @param array $attrs 已解析的属性数组
     * @return array|null 轮播项数组，若无效则返回 null
     */
    private static function _resolveCarouselLine(array $attrs): ?array
    {
        if (isset($attrs['post']) || isset($attrs['page'])) {
            $type = isset($attrs['page']) ? 'page' : 'post';
            $cid  = (int) ($attrs[$type] ?? 0);
            if ($cid <= 0) {
                return null;
            }
            $db  = self::_getDb();
            $row = $db->fetchRow(
                $db->select(['cid'])->from('table.contents')
                    ->where('cid = ?', $cid)
                    ->where('type = ?', $type)
                    ->where('status = ?', 'publish')
                    ->where('password IS NULL')
                    ->where('created <= ?', time())
            );
            if (!$row) {
                return null;
            }
            try {
                $widget = Widget::widget("Widget_Archive@carousel_{$cid}", "type={$type}", "cid={$cid}");
            } catch (Throwable) {
                return null;
            }
            if (!$widget->have()) {
                return null;
            }
            $widget->next();
            $forcedPic = !empty($attrs['pic']) ? (string) $attrs['pic'] : null;
            return self::_buildCarouselItem($widget, $type, $forcedPic);
        }

        $title = $attrs['title'] ?? '';
        if ($title === '') {
            return null;
        }
        return [
            'title'   => $title,
            'url'     => $attrs['url'] ?? '',
            'pic'     => $attrs['pic'] ?? '',
            'badge'   => $attrs['badge'] ?? '',
            'excerpt' => $attrs['excerpt'] ?? '',
        ];
    }

    /**
     * 根据已获取的文章对象生成轮播项数据（用于引用文章/页面）
     *
     * @param mixed       $widget    Archive 对象（已调用 next()）
     * @param string      $type      'post' 或 'page'
     * @param string|null $forcedPic 手动指定的图片 URL，非空时优先使用
     * @return array 轮播项数组
     */
    private static function _buildCarouselItem($widget, string $type, ?string $forcedPic = null): array
    {
        $cid     = (int) $widget->cid;
        $created = (int) $widget->created;

        $cat     = $type === 'post' ? self::getPostCategory($cid, $widget) : null;
        $excerpt = self::excerpt((string) $widget->content, 120);
        $pic     = $forcedPic !== '' && $forcedPic !== null
            ? $forcedPic
            : (ThumbnailHelper::showThumbnail($widget, true) ?? '');

        return [
            'title'   => (string) $widget->title,
            'url'     => (string) $widget->permalink,
            'pic'     => $pic,
            'badge'   => $type === 'post' ? ($cat['name'] ?? '精选') : '页面',
            'excerpt' => $excerpt,
        ];
    }

    /**
     * 解析文章的所有分类（从缓存或数据库）
     *
     * @param int         $cid    文章 CID
     * @param object|null $widget 可选的 Archive 对象（若提供则优先使用其 categories 属性）
     * @return array<int,array{mid:int,name:string,slug:string,parent:int,permalink:string}>
     */
    private static function _resolveCategories(int $cid, ?object $widget): array
    {
        $cats = [];

        if ($widget !== null && !empty($widget->categories) && is_array($widget->categories)) {
            foreach ($widget->categories as $cat) {
                if (!is_object($cat) || empty($cat->mid)) {
                    continue;
                }
                $cats[(int) $cat->mid] = self::_formatCategory($cat);
            }
            if (!empty($cats)) {
                self::_fillPermalinks($cats);
                return $cats;
            }
        }

        $db   = self::_getDb();
        $rows = $db->fetchAll($db->select('mid')->from('table.relationships')->where('cid = ?', $cid));
        if (empty($rows)) {
            return [];
        }
        $mids         = array_map('intval', array_column($rows, 'mid'));
        $placeholders = implode(',', array_fill(0, count($mids), '?'));
        $metas        = $db->fetchAll(
            $db->select('mid', 'name', 'slug', 'parent')
                ->from('table.metas')
                ->where('mid IN (' . $placeholders . ') AND type = ?', ...array_merge($mids, ['category']))
        );
        foreach ($metas as $meta) {
            $cats[(int) $meta['mid']] = [
                'mid'       => (int) $meta['mid'],
                'name'      => (string) $meta['name'],
                'slug'      => (string) $meta['slug'],
                'parent'    => (int) $meta['parent'],
                'permalink' => '',
            ];
        }
        self::_fillPermalinks($cats);
        return $cats;
    }

    /**
     * 将分类对象格式化为标准数组
     *
     * @param object $cat 分类对象（必须包含 mid, name, slug, parent, permalink）
     * @return array 标准分类数组
     */
    private static function _formatCategory(object $cat): array
    {
        return [
            'mid'       => (int) $cat->mid,
            'name'      => (string) $cat->name,
            'slug'      => (string) $cat->slug,
            'parent'    => (int) $cat->parent,
            'permalink' => (string) ($cat->permalink ?? ''),
        ];
    }

    /**
     * 为分类数组填充 permalink 字段（若缺失则根据 slug 生成）
     *
     * @param array &$cats 分类数组（引用）
     */
    private static function _fillPermalinks(array &$cats): void
    {
        $index = self::_getOptions()->index;
        foreach ($cats as &$cat) {
            if (empty($cat['permalink']) && !empty($cat['slug'])) {
                $cat['permalink'] = Router::url('category', ['slug' => $cat['slug']], $index);
            }
        }
    }

    /**
     * 从分类数组中选取最深（叶子）分类
     *
     * @param array $cats 分类数组
     * @return array|null 叶子分类，若无则返回第一个分类或 null
     */
    private static function _pickDeepest(array $cats): ?array
    {
        if (empty($cats)) {
            return null;
        }
        $children = array_filter($cats, fn ($c) => $c['parent'] !== 0 && isset($cats[$c['parent']]));
        return !empty($children) ? reset($children) : reset($cats);
    }

    /**
     * 确保 contents 表存在 views 字段（若不存在则添加）
     *
     * @param Db $db 数据库实例
     */
    private static function _ensureViewsField(Db $db): void
    {
        $key = 'contents.views';
        if (isset(self::$columnEnsured[$key])) {
            return;
        }
        try {
            $table = $db->getPrefix() . 'contents';
            $row   = $db->fetchRow($db->query("SHOW COLUMNS FROM `{$table}` LIKE 'views'"));
            if (!$row) {
                $db->query("ALTER TABLE `{$table}` ADD `views` INT(10) DEFAULT 0");
            }
            self::$columnEnsured[$key] = true;
        } catch (Throwable) {
        }
    }

    /**
     * 确保 contents 表存在 likes 和 likesData 字段（若不存在则添加）
     */
    private static function _ensureLikesFields(): void
    {
        $key = 'contents.likes';
        if (isset(self::$columnEnsured[$key])) {
            return;
        }
        try {
            $db      = self::_getDb();
            $table   = $db->getPrefix() . 'contents';
            $columns = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$table}`"));
            $names   = array_column($columns, 'Field');
            if (!in_array('likes', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `likes` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点赞数'");
            }
            if (!in_array('likesData', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `likesData` TEXT NULL COMMENT '点赞用户标识JSON'");
            }
            self::$columnEnsured[$key] = true;
        } catch (Throwable) {
        }
    }

    /**
     * 确保 users 表存在 bio、cover 和 avatar 字段（若不存在则添加）
     */
    private static function _ensureUserExtraFields(): void
    {
        $key = 'users.extras';
        if (isset(self::$columnEnsured[$key])) {
            return;
        }
        try {
            $db      = self::_getDb();
            $table   = $db->getPrefix() . 'users';
            $columns = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$table}`"));
            $names   = array_column($columns, 'Field');
            if (!in_array('bio', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `bio` TEXT NULL COMMENT '用户简介'");
            }
            if (!in_array('cover', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `cover` VARCHAR(500) NULL COMMENT '用户封面图URL'");
            }
            if (!in_array('avatar', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `avatar` VARCHAR(500) NULL COMMENT '用户自定义头像URL'");
            }
            self::$columnEnsured[$key] = true;
        } catch (Throwable) {
        }
    }

    /**
     * 获取当前用户的点赞身份标识
     *
     * 已登录用户：'user_{uid}'，未登录：'ip_{md5(ip+user_agent)}'
     *
     * @return string 唯一标识符
     */
    private static function _getLikeIdentity(): string
    {
        $user = Widget::widget('Widget_User');
        if ($user->hasLogin()) {
            return 'user_' . (int) $user->uid;
        }
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return 'ip_' . md5($ip . $agent);
    }

    /**
     * 根据主题选项 sidebarMenu（JSON）输出侧边栏自定义菜单
     */
    public static function sidebarNav(): void
    {
        $options = self::_getOptions();
        $raw     = (string) ($options->sidebarMenu ?? '');
        if ($raw === '') {
            return;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data)) {
            return;
        }

        $customIcons = self::_parseCustomIcons((string) ($options->sidebarIcons ?? ''));
        $siteUrl     = Helper::options()->siteUrl;

        $extractCatMids = static function (string $text): array {
            if (preg_match_all('/\{cat(?:url|name)?=(\d+)\}/', $text, $matches)) {
                return array_map('intval', $matches[1]);
            }
            return [];
        };
        $extractPageCids = static function (string $text): array {
            if (preg_match_all('/\{page(?:url|name)?=(\d+)\}/', $text, $matches)) {
                return array_map('intval', $matches[1]);
            }
            return [];
        };
        $allCatMids  = [];
        $allPageCids = [];
        $collectIds = static function (array $items) use (&$collectIds, &$allCatMids, &$allPageCids, $extractCatMids, $extractPageCids): void {
            foreach ($items as $item) {
                $name = (string) ($item['name'] ?? '');
                $url  = (string) ($item['url'] ?? '');
                $allCatMids  = array_merge($allCatMids, $extractCatMids($name));
                $allCatMids  = array_merge($allCatMids, $extractCatMids($url));
                $allPageCids = array_merge($allPageCids, $extractPageCids($name));
                $allPageCids = array_merge($allPageCids, $extractPageCids($url));
                if (isset($item['sub']) && is_array($item['sub'])) {
                    $collectIds($item['sub']);
                }
            }
        };
        $collectIds($data);
        $allCatMids  = array_unique(array_filter($allCatMids));
        $allPageCids = array_unique(array_filter($allPageCids));

        $db    = self::_getDb();
        $index = $options->index;

        $catMap = [];
        if (!empty($allCatMids)) {
            $placeholders = implode(',', array_fill(0, count($allCatMids), '?'));
            $metas        = $db->fetchAll(
                $db->select('mid', 'name', 'slug')
                    ->from('table.metas')
                    ->where('mid IN (' . $placeholders . ') AND type = ?', ...array_merge($allCatMids, ['category']))
            );
            foreach ($metas as $meta) {
                $mid  = (int) $meta['mid'];
                $slug = (string) $meta['slug'];
                $catMap[$mid] = [
                    'name'      => (string) $meta['name'],
                    'permalink' => $slug !== '' ? Router::url('category', ['slug' => $slug], $index) : '',
                ];
            }
        }

        $pageMap = [];
        if (!empty($allPageCids)) {
            $placeholders = implode(',', array_fill(0, count($allPageCids), '?'));
            $pages        = $db->fetchAll(
                $db->select('cid', 'title', 'slug')
                    ->from('table.contents')
                    ->where('cid IN (' . $placeholders . ') AND type = ?', ...array_merge($allPageCids, ['page']))
            );
            foreach ($pages as $page) {
                $cid  = (int) $page['cid'];
                $slug = (string) $page['slug'];
                $pageMap[$cid] = [
                    'title'     => (string) $page['title'],
                    'permalink' => $slug !== '' ? Router::url('page', ['slug' => $slug], $index) : '',
                ];
            }
        }

        $replacePlaceholders = static function (string $text) use ($catMap, $pageMap): string {
            $text = preg_replace_callback('/\{caturl=(\d+)\}/', static function ($matches) use ($catMap): string {
                $mid = (int) $matches[1];
                return $catMap[$mid]['permalink'] ?? '';
            }, $text);
            $text = preg_replace_callback('/\{catname=(\d+)\}/', static function ($matches) use ($catMap): string {
                $mid = (int) $matches[1];
                return $catMap[$mid]['name'] ?? '';
            }, $text);
            $text = preg_replace_callback('/\{pageurl=(\d+)\}/', static function ($matches) use ($pageMap): string {
                $cid = (int) $matches[1];
                return $pageMap[$cid]['permalink'] ?? '';
            }, $text);
            $text = preg_replace_callback('/\{pagename=(\d+)\}/', static function ($matches) use ($pageMap): string {
                $cid = (int) $matches[1];
                return $pageMap[$cid]['title'] ?? '';
            }, $text);
            return (string) $text;
        };

        $normalizeMenuUrl = static function (string $url) use ($siteUrl): string {
            $url = str_replace('{siteurl}', $siteUrl, $url);
            if ($siteUrl !== '' && strpos($url, $siteUrl) === 0) {
                $after = substr($url, strlen($siteUrl));
                if ($after !== '' && $after[0] !== '/' && $after[0] !== '?' && $after[0] !== '#') {
                    $url = $siteUrl . '/' . $after;
                }
            }
            return (string) preg_replace('#([^:])/+#', '$1/', $url);
        };

        $currentUrl    = $options->request->getRequestUrl();
        $normalizePath = static function (string $url): string {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $path = rtrim($path, '/');
            return $path === '' ? '/' : $path;
        };
        $currentPath   = $normalizePath($currentUrl);
        $isCurrentUrl  = static function (string $url) use ($currentPath, $normalizePath): bool {
            return $normalizePath($url) === $currentPath;
        };

        foreach ($data as $item) {
            $name   = self::esc($replacePlaceholders((string) ($item['name'] ?? '')), true);
            $url    = self::esc($normalizeMenuUrl($replacePlaceholders((string) ($item['url'] ?? '#'))), true);
            $icon   = (string) ($item['icon'] ?? '');
            $target = !empty($item['target']) ? ' target="_blank" rel="noopener noreferrer"' : '';
            $svg    = $customIcons[$icon] ?? '';

            if (isset($item['sub']) && is_array($item['sub']) && !empty($item['sub'])) {
                $subHtml  = '';
                $isActive = false;
                if (!empty($item['url']) && $item['url'] !== '#') {
                    $isActive = $isCurrentUrl($url);
                }

                foreach ($item['sub'] as $sub) {
                    $subName   = self::esc($replacePlaceholders((string) ($sub['name'] ?? '')), true);
                    $subUrl    = self::esc($normalizeMenuUrl($replacePlaceholders((string) ($sub['url'] ?? '#'))), true);
                    $subIcon   = (string) ($sub['icon'] ?? '');
                    $subTarget = !empty($sub['target']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    $subSvg    = $customIcons[$subIcon] ?? '';
                    $subActive = $isCurrentUrl($subUrl);
                    if ($subActive) {
                        $isActive = true;
                    }
                    $subClass = 'nav-link' . ($subActive ? ' active' : '');

                    $subHtml .= '<a href="' . $subUrl . '" class="' . $subClass . '"' . $subTarget . ' role="menuitem">';
                    if ($subSvg !== '') {
                        $subHtml .= '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="' . $subSvg . '"/></svg>';
                    }
                    $subHtml .= $subName;
                    $subHtml .= '</a>';
                }

                $parentClass = 'nav-link' . ($isActive ? ' active' : '');
                echo '<div class="' . $parentClass . '" data-has-submenu data-url="' . $url . '" role="button" tabindex="0" aria-expanded="false">';
                echo '<span class="nav-link-left">';
                if ($svg !== '') {
                    echo '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="' . $svg . '"/></svg>';
                }
                echo '<span>' . $name . '</span>';
                echo '</span>';
                echo '<svg class="icon nav-arrow" viewBox="0 0 24 24"><path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"/></svg>';
                echo '</div>';
                echo '<div class="nav-submenu" role="menu">';
                echo $subHtml;
                echo '</div>';
            } else {
                $linkClass = 'nav-link' . ($isCurrentUrl($url) ? ' active' : '');
                echo '<a href="' . $url . '" class="' . $linkClass . '"' . $target . ' role="menuitem">';
                if ($svg !== '') {
                    echo '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="' . $svg . '"></path></svg>';
                }
                echo '<span>' . $name . '</span>';
                echo '</a>';
            }
        }
    }

    /**
     * 解析自定义图标配置
     *
     * 每行一个 &lt;path name="图标名" d="..."&gt;&lt;/path&gt; 或自闭合标签，
     * 返回图标名到 path d 属性的映射。
     */
    private static function _parseCustomIcons(string $raw): array
    {
        $icons = [];
        if ($raw === '') {
            return $icons;
        }
        foreach (preg_split("/\r?\n/", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, '<path') === false) {
                continue;
            }
            if (!preg_match('/<path\b[^>]*>/i', $line, $tagMatch)) {
                continue;
            }
            $tag = $tagMatch[0];
            if (preg_match('/\bname="([^"]*)"/i', $tag, $nameMatch)
                && preg_match('/\bd="([^"]*)"/i', $tag, $dMatch)
            ) {
                $icons[$nameMatch[1]] = $dMatch[1];
            }
        }
        return $icons;
    }

}
