<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 缩略图工具类
 *
 * 缓存策略：
 * - key = cid，生命周期为当前 HTTP 请求
 * - 无 cid 时退化为无缓存模式
 *
 * @package XPro
 */


/**
 * 缩略图工具类
 */
class ThumbnailHelper
{
    /** @var array<string, string> 页面级静态缓存 */
    private static array $cache = [];

    /** 网格风格最多展示图片数 */
    public const MAX_GRID_IMAGES = 9;

    /**
     * 输出缩略图
     *
     * 优先级：自定义字段 > 正文HTML（含懒加载）> Markdown
     *
     * @param \Widget\Archive|\Widget\Base\Contents $widget 文章对象
     * @param bool $return 是否返回（默认直接输出）
     * @return string|null
     */
    public static function showThumbnail($widget, bool $return = false): ?string
    {
        if (!is_object($widget) || empty($widget->cid) || !$widget->have()) {
            return self::dispatch(null, $return);
        }

        $cid = $widget->cid ?? 0;

        if ($cid > 0 && isset(self::$cache[$cid])) {
            return self::dispatch(self::$cache[$cid], $return);
        }

        $img = null;

        if (!empty($widget->fields->thumb)) {
            $candidates = preg_split('/[\r\n,]+/', $widget->fields->thumb, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);
                if (!empty($candidate) && !self::isBlocked($candidate)) {
                    $img = $candidate;
                    break;
                }
            }
        }

        if (empty($img) && !empty($widget->content)) {
            $img = self::extractFromHtml($widget->content);
        }

        if (empty($img) && !empty($widget->content)) {
            $img = self::extractFromMarkdown($widget->content);
        }

        if (!empty($img) && self::isBlocked($img)) {
            $img = null;
        }

        if ($cid > 0) {
            self::$cache[$cid] = $img ?? '';
        }

        return self::dispatch($img, $return);
    }

    /**
     * 获取卡片图片配置
     *
     * 根据自定义字段 cardStyle、thumb、albumVisible 返回渲染所需数据。
     * 卡片图片来源不包含附件与随机图；网格最多 9 张，相册不限制。
     *
     * @param mixed $widget 文章对象
     * @return array
     */
    public static function getCardImageConfig($widget): array
    {
        $images  = self::getCardImages($widget);
        $style   = self::resolveCardStyle($widget, $images);
        $total   = count($images);
        $visible = self::resolveVisibleCount($widget, $style, $total);

        if ($style === 'album') {
            $displayImages = $images;
            $visible       = min($visible, $total);
            $hasMore       = $total > $visible;
            $displayCount  = $visible;
        } elseif ($style === 'multi') {
            $visible       = min($visible, $total, self::MAX_GRID_IMAGES);
            $displayImages = array_slice($images, 0, $visible);
            $hasMore       = false;
            $displayCount  = $visible;
        } elseif ($style === 'single') {
            $displayImages = array_slice($images, 0, 1);
            $hasMore       = false;
            $displayCount  = 1;
        } else {
            $displayImages = [];
            $hasMore       = false;
            $displayCount  = 0;
        }

        return [
            'style'         => $style,
            'images'        => $images,
            'displayImages' => $displayImages,
            'total'         => $total,
            'visible'       => $visible,
            'colsClass'     => self::resolveColsClass($displayCount),
            'hasMore'       => $hasMore,
        ];
    }

    /**
     * 获取卡片所有可用图片
     *
     * 优先级：自定义字段 thumb > 正文 HTML > Markdown
     *
     * @param mixed $widget 文章对象
     * @return string[]
     */
    public static function getCardImages($widget): array
    {
        if (!is_object($widget) || empty($widget->cid) || !$widget->have()) {
            return [];
        }

        $images = self::parseThumbField($widget);
        if (!empty($images)) {
            return $images;
        }

        if (!empty($widget->content)) {
            $htmlImages = self::extractAllFromHtml($widget->content);
            $mdImages   = self::extractAllFromMarkdown($widget->content);
            $images     = array_values(array_unique(array_merge($htmlImages, $mdImages)));
            if (!empty($images)) {
                return $images;
            }
        }

        return [];
    }

    /**
     * 解析自定义字段 thumb
     *
     * @param mixed $widget 文章对象
     * @return string[]
     */
    private static function parseThumbField($widget): array
    {
        $images = [];
        if (!empty($widget->fields->thumb)) {
            $candidates = preg_split('/[\r\n,]+/', $widget->fields->thumb, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);
                if (!empty($candidate) && !self::isBlocked($candidate)) {
                    $images[] = $candidate;
                }
            }
        }
        return $images;
    }

    /**
     * 根据自定义字段 cardStyle 与图片数量解析最终风格
     *
     * 规则：
     * - 0 张 → none
     * - auto 时根据图片数推断，并把推断结果写入 cardStyle 字段
     *   - 1 张 → single
     *   - 2 张 → multi
     *   - >9 张 → album
     *   - 3-9 张 → 随机 multi / album
     * - 显式指定时优先使用（multi 最多显示 9 张）
     * - multi / album 只有 1 张时退化为 single
     *
     * @param mixed $widget 文章对象
     * @param string[] $images 图片 URL 列表
     * @return string
     */
    public static function resolveCardStyle($widget, array $images): string
    {
        $count = count($images);
        if ($count === 0) {
            return 'none';
        }

        $style = $widget->fields->cardStyle ?? 'auto';
        $valid = ['none', 'single', 'multi', 'album'];

        if (!in_array((string) $style, $valid, true)) {
            $style = 'auto';
        }

        if ($style === 'auto') {
            if ($count === 1) {
                $chosen = 'single';
            } elseif ($count === 2) {
                $chosen = 'multi';
            } elseif ($count > self::MAX_GRID_IMAGES) {
                $chosen = 'album';
            } else {
                $chosen = self::randomChoice(['multi', 'album']);
            }

            $cid = (int) ($widget->cid ?? 0);
            if ($cid > 0) {
                self::persistField($cid, 'cardStyle', 'str', $chosen);
            }
            return $chosen;
        }

        if (($style === 'multi' || $style === 'album') && $count === 1) {
            return 'single';
        }

        return $style;
    }

    /**
     * 解析可见张数
     *
     * - none / single：0
     * - 显式指定 albumVisible：在 1-9 之间裁剪并不超过总数
     * - 未指定时自动推断，结果从 {2,3,4,6,9} 中选取并写入自定义字段；
     *   相册折叠风格下可见张数一定小于图片总数
     *
     * @param mixed $widget 文章对象
     * @param string $style 卡片风格
     * @param int $total 图片总数
     * @return int
     */
    public static function resolveVisibleCount($widget, string $style, int $total): int
    {
        if ($style === 'none' || $style === 'single') {
            return 0;
        }

        $visible = $widget->fields->albumVisible ?? null;

        if ($visible !== null && $visible !== '') {
            $visible = (int) $visible;
            if ($visible < 1) {
                $visible = 1;
            }
            if ($visible > self::MAX_GRID_IMAGES) {
                $visible = self::MAX_GRID_IMAGES;
            }
            return min($visible, $total);
        }

        $visible = self::resolveAutoVisibleCount($total, $style);

        $cid = (int) ($widget->cid ?? 0);
        if ($cid > 0) {
            self::persistField($cid, 'albumVisible', 'int', $visible);
        }
        return $visible;
    }

    /**
     * 自动推断可见张数
     *
     * 网格风格取不超过总数的最大支持值（2、3、4、6、9）；
     * 相册折叠风格随机取小于总数的支持值，确保存在折叠蒙层。
     *
     * @param int $total 图片总数
     * @param string $style 卡片风格
     * @return int
     */
    private static function resolveAutoVisibleCount(int $total, string $style): int
    {
        $supported = [2, 3, 4, 6, 9];

        if ($style === 'album') {
            $candidates = array_values(array_filter($supported, static fn (int $value): bool => $value < $total));
            if (!empty($candidates)) {
                return self::randomChoice($candidates);
            }
            return max(1, $total - 1);
        }

        foreach (array_reverse($supported) as $value) {
            if ($value <= $total) {
                return $value;
            }
        }

        return $total;
    }

    /**
     * 根据展示数量解析 gallery 列数类名
     *
     * 规则：1 → cols-1；2、4 → cols-2；3、6、9 → cols-3；其余默认 cols-3
     *
     * @param int $displayCount 实际展示数量
     * @return string
     */
    public static function resolveColsClass(int $displayCount): string
    {
        if ($displayCount === 1) {
            return 'cols-1';
        }
        if (in_array($displayCount, [2, 4], true)) {
            return 'cols-2';
        }
        if (in_array($displayCount, [3, 6, 9], true)) {
            return 'cols-3';
        }
        return 'cols-3';
    }

    /**
     * 持久化自定义字段值到数据库
     *
     * @param int $cid 文章 CID
     * @param string $name 字段名
     * @param string $type 字段类型：str|int|float|json
     * @param mixed $value 字段值
     * @return void
     */
    private static function persistField(int $cid, string $name, string $type, $value): void
    {
        if (!in_array($type, ['str', 'int', 'float', 'json'], true)) {
            return;
        }

        try {
            $db = \Typecho\Db::get();
            $exist = $db->fetchRow(
                $db->select('cid')->from('table.fields')
                    ->where('cid = ? AND name = ?', $cid, $name)
            );

            $rows = [
                'type'        => $type,
                'str_value'   => in_array($type, ['str', 'json'], true) ? $value : null,
                'int_value'   => $type === 'int' ? (int) $value : 0,
                'float_value' => $type === 'float' ? (float) $value : 0,
            ];

            if (empty($exist)) {
                $rows['cid']  = $cid;
                $rows['name'] = $name;
                $db->query($db->insert('table.fields')->rows($rows));
            } else {
                $db->query($db->update('table.fields')
                    ->rows($rows)
                    ->where('cid = ? AND name = ?', $cid, $name));
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 从数组中随机选取一项
     *
     * @param array $choices 候选项
     * @return mixed
     */
    private static function randomChoice(array $choices)
    {
        if (empty($choices)) {
            return '';
        }
        return $choices[self::randomInt(0, count($choices) - 1)];
    }

    /**
     * 生成指定范围随机整数（带降级容错）
     *
     * @param int $min 最小值
     * @param int $max 最大值
     * @return int
     */
    private static function randomInt(int $min, int $max): int
    {
        try {
            return random_int($min, $max);
        } catch (\Throwable $e) {
            return mt_rand($min, $max);
        }
    }

    /**
     * 从 HTML 提取首张有效图片
     *
     * @param string $content 正文内容
     * @return string|null
     */
    private static function extractFromHtml(string $content): ?string
    {
        $ext = 'jpg|jpeg|gif|png|webp|bmp|avif';

        if (preg_match('/<img[^>]*?\sdata-src=["\']([^"\']+?\.(?:' . $ext . '))(?:\?[^"\']*)?["\'][^>]*>/i', $content, $match)) {
            return $match[1];
        }

        if (preg_match('/<img[^>]*?\ssrc=["\']([^"\']+?\.(?:' . $ext . '))(?:\?[^"\']*)?["\'][^>]*>/i', $content, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * 从 HTML 提取所有有效图片
     *
     * @param string $content 正文内容
     * @return string[]
     */
    private static function extractAllFromHtml(string $content): array
    {
        $ext = 'jpg|jpeg|gif|png|webp|bmp|avif';
        $images = [];

        if (preg_match_all('/<img[^>]*>/i', $content, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\sclass=["\'][^"\']*\bno-fancybox\b[^"\']*["\']/i', $tag)) {
                    continue;
                }

                $url = null;
                if (preg_match('/\sdata-src=["\']([^"\']+?\.(?:' . $ext . '))(?:\?[^"\']*)?["\']/i', $tag, $match)) {
                    $url = $match[1];
                } elseif (preg_match('/\ssrc=["\']([^"\']+?\.(?:' . $ext . '))(?:\?[^"\']*)?["\']/i', $tag, $match)) {
                    $url = $match[1];
                }

                if (!empty($url) && !self::isBlocked($url)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * 从 Markdown 提取首张有效图片
     *
     * @param string $content 正文内容
     * @return string|null
     */
    private static function extractFromMarkdown(string $content): ?string
    {
        $ext = 'jpg|jpeg|gif|png|webp|bmp|avif';

        if (preg_match('/!\[.*?\]\((https?:\/\/[^)\s]+?\.(?:' . $ext . '))(?:\?[^)\s]*)?(?:\s+["\'].*?["\'])?\)/i', $content, $match)) {
            return $match[1];
        }

        if (preg_match('/\[.*?\]:\s*(https?:\/\/[^\s]+?\.(?:' . $ext . '))(?:\?[^\s]*)?/i', $content, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * 从 Markdown 提取所有有效图片
     *
     * @param string $content 正文内容
     * @return string[]
     */
    private static function extractAllFromMarkdown(string $content): array
    {
        $ext = 'jpg|jpeg|gif|png|webp|bmp|avif';
        $images = [];

        if (preg_match_all('/!\[.*?\]\((https?:\/\/[^)\s]+?\.(?:' . $ext . '))(?:\?[^)\s]*)?(?:\s+["\'].*?["\'])?\)/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (!self::isBlocked($url)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * 黑名单过滤
     *
     * 过滤插件图标、base64 内嵌等不应展示的图片。
     *
     * @param string $url 图片 URL
     * @return bool
     */
    private static function isBlocked(string $url): bool
    {
        if (strpos($url, __TYPECHO_PLUGIN_DIR__ . '/TePass') !== false) {
            return true;
        }

        if (strpos($url, 'data:image') === 0) {
            return true;
        }

        return false;
    }

    /**
     * 输出或返回结果
     *
     * @param string|null $img 图片 URL
     * @param bool $return 是否返回而非输出
     * @return string|null
     */
    private static function dispatch(?string $img, bool $return): ?string
    {
        if ($return) {
            return $img ?? '';
        }

        echo $img ?? '';
        return null;
    }
}
