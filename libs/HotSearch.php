<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Typecho 热门搜索管理器
 *
 * 特性：
 * - 关键词忽略大小写（Typecho = typecho = TYPECHO）
 * - 存储时保留首次输入的大小写形式作为显示
 * - 支持调用处通过 $config 自定义 HTML 模板
 *
 * @package XPro
 */

final class HotSearch
{
    /** @var int 默认显示数量 */
    private const LIMIT = 10;

    /** @var string 默认外层容器模板（必须包含 {items} 占位符） */
    private const WRAPPER_TMPL = '<ul class="hot-search-list">{items}</ul>';

    /** @var string 默认单项模板（可用占位符：{url}, {keyword}, {count}, {index}, {articles}） */
    private const ITEM_TMPL = '<li><a href="{url}">{keyword}<span>({count})</span></a></li>';

    /** @var string 默认空数据模板 */
    private const EMPTY_TMPL = '<p class="hot-search-empty">暂无热门搜索</p>';

    /** @var array|null 缓存的热搜数据 */
    private static ?array $data = null;

    /** @var string|null JSON 数据文件路径 */
    private static ?string $file = null;

    /** @var bool 路径是否已初始化 */
    private static bool $init = false;

    /**
     * 初始化 JSON 数据文件路径
     *
     * @return void
     */
    private static function initPath(): void
    {
        if (self::$init) {
            return;
        }
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        self::$file = is_writable($cacheDir)
            ? $cacheDir . '/hotsearch.json'
            : sys_get_temp_dir() . '/hotsearch_' . md5(__DIR__) . '.json';
        self::$init = true;
    }

    /**
     * 从 JSON 文件加载热搜数据
     *
     * @return array 热搜数据数组
     */
    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        self::initPath();
        if (!file_exists(self::$file)) {
            self::$data = [];
            return [];
        }
        $content = @file_get_contents(self::$file);
        $raw = json_decode($content, true);
        self::$data = is_array($raw) ? $raw : [];
        return self::$data;
    }

    /**
     * 将热搜数据保存到 JSON 文件（原子写入）
     *
     * @param array $data 热搜数据数组
     * @return void
     */
    private static function save(array $data): void
    {
        if (self::$file === null) {
            self::initPath();
        }
        $tmp = self::$file . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
            rename($tmp, self::$file);
        }
        self::$data = $data;
    }

    /**
     * 获取后台配置的热搜时间范围（天）
     *
     * @return int 时间范围天数，0 表示不限
     */
    private static function timeRange(): int
    {
        $opt = \Helper::options()->hotSearchTimeRange ?? '0';
        return max(0, (int)$opt);
    }

    /**
     * 查询匹配指定关键词的文章数量
     *
     * @param string $keyword 搜索关键词
     * @return int 匹配的文章数
     */
    private static function articleCount(string $keyword): int
    {
        $db = \Typecho\Db::get();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
        try {
            $count = $db->fetchObject(
                $db->select(['COUNT(DISTINCT cid)' => 'num'])
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('title LIKE ? OR text LIKE ?', $like, $like)
            )->num ?? 0;
            return (int)$count;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 记录搜索关键词（大小写不敏感）
     *
     * @param string $keyword 搜索关键词
     * @return void
     */
    public static function log(string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '' || strlen($keyword) > 255) {
            return;
        }

        $articles = self::articleCount($keyword);
        if ($articles === 0) {
            return;
        }

        $isChineseOnly = preg_match('/^[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]+$/u', $keyword);
        $isAsciiOnly   = preg_match('/^[\x00-\x7F]+$/', $keyword);

        if ($isChineseOnly) {
            if (mb_strlen($keyword, 'UTF-8') < 2) {
                return;
            }
        } elseif ($isAsciiOnly) {
            if (strlen($keyword) < 4) {
                return;
            }
        } else {
            if (mb_strlen($keyword, 'UTF-8') < 3) {
                return;
            }
        }

        $data = self::load();
        $now = time();
        $found = false;

        foreach ($data as &$item) {
            if (strcasecmp($item['keyword'], $keyword) === 0) {
                $item['count']++;
                $item['lastsearch'] = $now;
                $item['articles'] = $articles;
                $found = true;
                break;
            }
        }

        if ($found) {
            self::save($data);
        } else {
            $data[] = [
                'keyword'    => $keyword,
                'count'      => 1,
                'lastsearch' => $now,
                'articles'   => $articles,
            ];
            self::save($data);
        }
    }

    /**
     * 获取热门搜索列表
     *
     * @param int|null $limit 显示数量，null 则使用默认值
     * @return array 按搜索次数降序排列的热搜列表
     */
    public static function getList(?int $limit = null): array
    {
        $data = self::load();
        if (empty($data)) {
            return [];
        }

        $range = self::timeRange();
        $now = time();
        $filtered = [];

        foreach ($data as $item) {
            if ($range > 0 && ($now - $item['lastsearch']) > $range * 86400) {
                continue;
            }
            $filtered[] = $item;
        }

        usort($filtered, fn($a, $b) => $b['count'] <=> $a['count'] ?: $b['lastsearch'] <=> $a['lastsearch']);

        $limit = $limit ?? self::LIMIT;
        return array_slice($filtered, 0, $limit);
    }

    /**
     * 渲染热门搜索 HTML
     *
     * @param int|null $limit  显示数量，null 则使用默认值
     * @param array    $config 自定义模板配置：
     *                         'wrapper' => 外层容器模板，必须包含 {items} 占位符，
     *                         'item'    => 单项模板，可用占位符：{url}, {keyword}, {count}, {index}, {articles}，
     *                         'empty'   => 无数据时模板
     * @return void
     */
    public static function render(?int $limit = null, array $config = []): void
    {
        $list = self::getList($limit);
        
        $wrapper = $config['wrapper'] ?? self::WRAPPER_TMPL;
        $itemTpl = $config['item'] ?? self::ITEM_TMPL;
        $empty   = $config['empty'] ?? self::EMPTY_TMPL;

        if (empty($list)) {
            echo $empty;
            return;
        }

        $items = '';
        foreach ($list as $idx => $item) {
            $url = \Typecho\Router::url('search', ['keywords' => urlencode($item['keyword'])], \Helper::options()->index);
            $items .= strtr($itemTpl, [
                '{url}'      => $url,
                '{keyword}'  => htmlspecialchars($item['keyword'], ENT_QUOTES, 'UTF-8'),
                '{count}'    => (string) $item['count'],
                '{index}'    => (string) ($idx + 1),
                '{articles}' => (string) $item['articles'],
            ]);
        }
        echo strtr($wrapper, ['{items}' => $items]);
    }

    /**
     * 清空热搜数据文件
     *
     * @return void
     */
    public static function clear(): void
    {
        if (self::$file && file_exists(self::$file)) {
            unlink(self::$file);
        }
        self::$data = null;
    }
}
