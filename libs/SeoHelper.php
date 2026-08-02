<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * SEO 辅助类
 *
 * 封装所有 SEO 元标签生成逻辑，零输出缓冲，直接流式输出。
 *
 * @package XPro
 */
final class SeoHelper
{
    /** @var string 允许索引的 robots 值 */
    private const ROBOTS_INDEX = 'index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large';

    /** @var string 禁止索引但允许跟踪的 robots 值 */
    private const ROBOTS_NOINDEX = 'noindex, follow';

    /** @var string 完全禁止的 robots 值 */
    private const ROBOTS_NONE = 'noindex, nofollow';

    /** @var \Widget\Options 缓存的站点配置 */
    private readonly \Widget\Options $options;

    /**
     * 构造方法
     *
     * @param \Widget\Archive $archive 当前页面归档对象
     */
    public function __construct(
        private readonly \Widget\Archive $archive
    )
    {
        $this->options = $this->archive->options ?? \Helper::options();
    }

    /**
     * 输出 robots 元标签与 canonical 链接
     *
     * @return void
     */
    public function robots(): void
    {
        [$robots, $canonical] = $this->resolveRobotsAndCanonical();

        printf('<meta name="robots" content="%s">' . "\n", $robots);
        if ($canonical !== null) {
            printf('<link rel="canonical" href="%s">' . "\n", $canonical);
        }
    }

    /**
     * 根据当前页面类型解析 robots 策略与 canonical URL
     *
     * @return array{string, string|null}
     */
    private function resolveRobotsAndCanonical(): array
    {
        return match (true) {
            $this->archive->is('search'), $this->archive->is('404') =>
                [self::ROBOTS_NONE, null],

            $this->archive->is('archive') =>
                [self::ROBOTS_NOINDEX, $this->canonicalUrl()],

            $this->archive->is('index') && $this->archive->getCurrentPage() > 1 =>
                [self::ROBOTS_NOINDEX, $this->canonicalUrl()],

            $this->archive->is('category'), $this->archive->is('tag') =>
                [$this->archive->getCurrentPage() > 1 ? self::ROBOTS_NOINDEX : self::ROBOTS_INDEX, $this->canonicalUrl()],

            $this->archive->is('post') =>
                [($this->archive->_commentsCurrentPage ?? 1) > 1 ? self::ROBOTS_NOINDEX : self::ROBOTS_INDEX, $this->archive->permalink],

            $this->archive->is('page') =>
                [self::ROBOTS_INDEX, $this->archive->permalink],

            default => [self::ROBOTS_INDEX, $this->canonicalUrl()],
        };
    }

    /**
     * 生成当前页面的 canonical URL
     *
     * @return string
     */
    private function canonicalUrl(): string
    {
        $url = $this->archive->archiveUrl ?? '';
        if (empty($url)) {
            $url = $this->options->siteUrl ?? '';
        }
        return rtrim((string) $url, '/') . '/';
    }

    /**
     * 输出 keywords 元数据
     *
     * 单页优先读取自定义字段 k，否则走 Typecho 默认逻辑；
     * 非单页直接读取后台站点 keywords。
     *
     * @return void
     */
    public function keywords(): void
    {
        if ($this->archive->is('single')) {
            $k = $this->archive->fields?->k ?? '';
            if ($k !== '') {
                echo $k;
                return;
            }
            $this->archive->keywords();
            return;
        }

        echo htmlspecialchars(
            (string) ($this->options->keywords ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /**
     * 输出 description 元数据
     *
     * 单页优先读取自定义字段 d，否则生成智能摘要；
     * 非单页直接读取后台站点 description。
     *
     * @return void
     */
    public function description(): void
    {
        if ($this->archive->is('single')) {
            $d = $this->archive->fields?->d ?? '';
            if ($d !== '') {
                echo htmlspecialchars($d, ENT_QUOTES, 'UTF-8');
                return;
            }
            echo XPro::excerpt(
                (string) ($this->archive->content ?? ''),
                160,
                '...',
                true,
                true
            );
            return;
        }

        echo htmlspecialchars(
            (string) ($this->options->description ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /**
     * 输出文章页专属的 article 系列元标签
     *
     * 仅在 post 类型时输出，包括发布时间、修改时间、作者、
     * 头条时间标签及文章标签。
     *
     * @return void
     */
    public function articleMeta(): void
    {
        if (!$this->archive->is('post')) {
            return;
        }

        $created  = XPro::formatIsoDate((int) $this->archive->created);
        $modified = XPro::formatIsoDate((int) $this->archive->modified);

        echo "    <meta property=\"article:published_time\" content=\"{$created}\">\n";
        echo "    <meta property=\"article:modified_time\" content=\"{$modified}\">\n";
        echo "    <meta property=\"bytedance:published_time\" content=\"{$created}\">\n";
        echo "    <meta property=\"bytedance:updated_time\" content=\"{$modified}\">\n";
        echo "    <meta property=\"article:author\" content=\"";
        $this->archive->author();
        echo "\">";

        if (!empty($this->archive->tags)) {
            foreach ($this->archive->tags as $tag) {
                $name = htmlspecialchars($tag['name'] ?? '');
                echo "\n    <meta property=\"article:tag\" content=\"{$name}\">";
            }
        }
    }

    /**
     * 输出 Open Graph 元标签
     *
     * @param string $metaImage 文章缩略图 URL
     * @param string $favicon   站点 favicon URL
     * @return void
     */
    public function og(string $metaImage, string $favicon): void
    {
        $siteName = htmlspecialchars((string) ($this->options->title ?? ''));
        $type     = $this->archive->is('post') || $this->archive->is('page') ? 'article' : 'website';
        $url      = $this->archive->is('single') ? $this->archive->permalink : ($this->options->rootUrl ?? '');
        $image    = $this->archive->is('single') ? ($metaImage ?: $favicon) : $favicon;

        echo "    <meta property=\"og:locale\" content=\"zh_CN\">\n";
        echo "    <meta property=\"og:site_name\" content=\"{$siteName}\">\n";
        echo "    <meta property=\"og:type\" content=\"{$type}\">\n";
        echo "    <meta property=\"og:url\" content=\"{$url}\">\n";
        echo "    <meta property=\"og:title\" content=\"";
        $this->baseTitle();
        echo "\">\n";
        echo "    <meta property=\"og:description\" content=\"";
        $this->description();
        echo "\">\n";
        echo "    <meta property=\"og:image\" content=\"{$image}\">\n";
        echo "    <meta property=\"og:image:secure_url\" content=\"{$image}\">\n"; 
        echo "    <meta property=\"og:image:alt\" content=\"";
        $this->baseTitle();
        echo "\">";
    }

    /**
     * 输出 Twitter Card 元标签
     *
     * @param string $metaImage 文章缩略图 URL
     * @param string $favicon   站点 favicon URL
     * @return void
     */
    public function twitter(string $metaImage, string $favicon): void
    {
        $url   = $this->archive->is('single') ? $this->archive->permalink : ($this->options->rootUrl ?? '');
        $image = $this->archive->is('single') ? $metaImage : ($this->options->ogImage ?? $favicon);

        echo "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        echo "    <meta property=\"twitter:creator\" content=\"";
        $this->archive->author();
        echo "\">";
        echo "    <meta name=\"twitter:title\" content=\"";
        $this->baseTitle();
        echo "\">\n";
        echo "    <meta name=\"twitter:description\" content=\"";
        $this->description();
        echo "\">\n";
        echo "    <meta name=\"twitter:image\" content=\"{$image}\">\n";
        echo "    <meta name=\"twitter:image:alt\" content=\"";
        $this->baseTitle();
        echo "\">\n";
        echo "    <meta name=\"twitter:url\" content=\"{$url}\">";
    }

    /**
     * 输出基础标题（用于 OG / Twitter / 面包屑等）
     *
     * @return void
     */
    public function baseTitle(): void
    {
        if ($this->archive->_currentPage > 1) {
            echo '第 ', $this->archive->_currentPage, ' 页 - ';
        }
        $this->archive->archiveTitle([
            'category' => '%s',
            'search'   => '包含"%s"的搜索结果',
            'tag'      => '%s',
            'author'   => '%s 发布的文章'
        ], '', ' - ');
        $this->options->title();
    }

    /**
     * 输出 &lt;title&gt; 标签（含首页 SEO 后缀）
     *
     * @return void
     */
    public function title(): void
    {
        echo '<title>';
        $this->baseTitle();
        if ($this->archive->is('index') && $this->archive->_currentPage == 1) {
            echo ' - ', htmlspecialchars((string) ($this->options->description ?? '')), ' | ', htmlspecialchars((string) ($this->options->keywords ?? ''));
        }
        echo '</title>';
    }
}
