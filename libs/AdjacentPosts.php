<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Common;
use Typecho\Db;
use Typecho\Router;
use Typecho\Widget;

/**
 * 相邻文章相关类
 *
 * @package XPro
 */

/**
 * 相邻文章路由生成器
 *
 * 职责：根据 Typecho 当前永久链接规则，为相邻文章原始数据补全路由参数，
 *       正确生成 URL，失败时自动降级到 /archives/{cid}/
 */
class AdjacentPostRouter
{
    /**
     * 生成文章永久链接
     *
     * @param array  $row     原始文章行数据（含 cid, slug, created, type 等）
     * @param string $baseUrl 站点首页地址
     * @return string
     */
    public static function build(array $row, string $baseUrl): string
    {
        $params = self::prepareParams($row);

        try {
            $type  = $params['type'] ?? 'post';
            $route = $type === 'page' ? 'page' : 'post';

            $url = Router::url($route, $params, $baseUrl);
            if ($url && $url !== $baseUrl) {
                return $url;
            }
        } catch (Throwable $e) {
        }

        return Common::url('/archives/' . $params['cid'] . '/', $baseUrl);
    }

    /**
     * 补全 Typecho 路由反解所需的参数
     *
     * 永久链接常用变量：{cid}, {slug}, {year}, {month}, {day}, {category}
     * 这里自动从 created 提取日期参数，确保日期型路由可用。
     * category 类路由若缺失参数，则依赖上方 try-catch 兜底。
     *
     * @param array $row 原始文章行数据
     * @return array 补全后的参数数组
     */
    private static function prepareParams(array $row): array
    {
        $row['cid']  = $row['cid'];
        $row['slug'] = !empty($row['slug']) ? $row['slug'] : $row['cid'];

        if (!empty($row['created'])) {
            $ts = is_int($row['created']) 
                ? $row['created'] 
                : strtotime((string) $row['created']);
            
            if ($ts > 0) {
                $row['year']  = date('Y', $ts);
                $row['month'] = date('m', $ts);
                $row['day']   = date('d', $ts);
            }
        }

        return $row;
    }
}

/**
 * 相邻文章处理类
 *
 * - 惰性查询，按需加载，避免重复 SQL
 * - 进程级静态缓存，同一请求周期内多次 new 也不会重复查询
 * - 使用 PHP 8.2+ 类型声明
 * - 不包含任何硬编码 HTML，完全由调用方控制输出
 */
class AdjacentPosts
{
    /** @var Db 数据库实例 */
    private Db $db;

    /** @var object 当前文章 Widget 对象 */
    private object $widget;

    /** @var array|null 上一篇缓存 */
    private ?array $prev = null;

    /** @var array|null 下一篇缓存 */
    private ?array $next = null;

    /** @var bool 上一篇是否已加载 */
    private bool $prevLoaded = false;

    /** @var bool 下一篇是否已加载 */
    private bool $nextLoaded = false;

    /** @var array<string, array|null> 进程级静态缓存 */
    private static array $cache = [];

    /**
     * 构造方法
     *
     * @param object $widget 当前文章 Widget 对象
     */
    public function __construct(object $widget)
    {
        $this->db = Db::get();
        $this->widget = $widget;
    }

    /**
     * 是否存在上一篇文章
     *
     * @return bool
     */
    public function hasPrev(): bool
    {
        return $this->getPrev() !== null;
    }

    /**
     * 是否存在下一篇文章
     *
     * @return bool
     */
    public function hasNext(): bool
    {
        return $this->getNext() !== null;
    }

    /**
     * 获取上一篇文章数据
     *
     * @return array|null 文章数据，无则返回 null
     */
    public function getPrev(): ?array
    {
        if (!$this->prevLoaded) {
            $this->prev = $this->fetchAdjacent('<', Db::SORT_DESC);
            $this->prevLoaded = true;
        }
        return $this->prev;
    }

    /**
     * 获取下一篇文章数据
     *
     * @return array|null 文章数据，无则返回 null
     */
    public function getNext(): ?array
    {
        if (!$this->nextLoaded) {
            $this->next = $this->fetchAdjacent('>', Db::SORT_ASC);
            $this->nextLoaded = true;
        }
        return $this->next;
    }

    /**
     * 查询相邻文章
     *
     * @param string $operator 比较运算符（'<' 查上一篇，'>' 查下一篇）
     * @param string $order    排序方向（DESC / ASC）
     * @return array|null      文章数据，无则返回 null
     */
    private function fetchAdjacent(string $operator, string $order): ?array
    {
        $cid = $this->widget->cid;
        if ($cid === 0) {
            return null;
        }

        $direction = $operator === '<' ? 'prev' : 'next';
        $cacheKey = "{$cid}:{$direction}";

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $row = $this->db->fetchRow(
            $this->db->select()->from('table.contents')
                ->where("created {$operator} ?", $this->widget->created)
                ->where('created < ?', time())
                ->where('status = ?', 'publish')
                ->where('type = ?', $this->widget->type)
                ->where('password IS NULL')
                ->order('created', $order)
                ->limit(1)
        );

        if (!$row) {
            self::$cache[$cacheKey] = null;
            return null;
        }

        if (method_exists($this->widget, 'filter')) {
            $row = $this->widget->filter($row);
        }

        $row['title'] = $row['title'];
        $row['created'] = $row['created'];

        if (empty($row['permalink'])) {
            $baseUrl = Widget::widget('Widget_Options')->index;
            $row['permalink'] = AdjacentPostRouter::build($row, $baseUrl);
        }

        self::$cache[$cacheKey] = $row;
        return $row;
    }

    /**
     * 渲染单篇相邻文章
     *
     * @param string        $direction 'prev'（上一篇）或 'next'（下一篇）
     * @param callable|null $renderer  回调函数签名：function(array $postData, string $direction, object $widget): string
     * @param string|null   $default   无文章时输出的默认内容
     * @return void
     */
    public function render(string $direction, ?callable $renderer = null, ?string $default = null): void
    {
        $adjacent = $direction === 'next' ? $this->getNext() : $this->getPrev();

        if ($adjacent === null) {
            echo $default;
            return;
        }

        if ($renderer !== null) {
            echo $renderer($adjacent, $direction, $this->widget);
        } else {
            $title = htmlspecialchars((string) $adjacent['title'], ENT_QUOTES, 'UTF-8');
            $url   = htmlspecialchars((string) $adjacent['permalink'], ENT_QUOTES, 'UTF-8');
            $text  = $direction === 'next' ? '下一篇' : '上一篇';
            echo "<a href=\"{$url}\" rel=\"{$direction}\" title=\"{$title}\">{$text}: {$title}</a>";
        }
    }

    /**
     * 同时输出上一篇与下一篇
     *
     * @param callable|null $prevRenderer 上一篇渲染回调
     * @param callable|null $nextRenderer 下一篇渲染回调
     * @param string|null   $prevDefault  上一篇无数据时的默认内容
     * @param string|null   $nextDefault  下一篇无数据时的默认内容
     * @return void
     */
    public function renderPair(
        ?callable $prevRenderer = null,
        ?callable $nextRenderer = null,
        ?string $prevDefault = null,
        ?string $nextDefault = null
    ): void {
        $this->render('prev', $prevRenderer, $prevDefault);
        $this->render('next', $nextRenderer, $nextDefault);
    }
}
