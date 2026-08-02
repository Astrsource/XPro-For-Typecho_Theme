<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * XPro 主题内容处理类 — 短代码解析器
 *
 * @package XPro
 */


/**
 * 内容过滤与短代码解析器
 */
class ContentFilter
{
    private static int $counter = 0;
    private static array $alertIcons = [
        'info'    => '<path d="M12 22C6.47715 22 2 17.5228 2 12 2 6.47715 6.47715 2 12 2 17.5228 2 22 6.47715 22 12 22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12 20 7.58172 16.4183 4 12 4 7.58172 4 4 7.58172 4 12 4 16.4183 7.58172 20 12 20ZM13 10.5V15H14V17H10V15H11V12.5H10V10.5H13ZM13.5 8C13.5 8.82843 12.8284 9.5 12 9.5 11.1716 9.5 10.5 8.82843 10.5 8 10.5 7.17157 11.1716 6.5 12 6.5 12.8284 6.5 13.5 7.17157 13.5 8Z"/>',
        'success' => '<path d="M10 15.172L19.192 5.979L20.607 7.393L10 18L3.393 11.393L4.807 9.979L10 15.172Z"/>',
        'warning' => '<path d="M12.866 3L21.392 18H4.359L12.866 3ZM12.866 5.5L6.428 16H19.179L12.866 5.5ZM11 10H13V14H11V10ZM11 15H13V17H11V15Z"/>',
        'danger'  => '<path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z"/>',
    ];
    /** @var array<string, array> 文章/页面引用卡片静态缓存 */
    private static array $contentCache = [];
    /** @var bool 音乐解析器是否已初始化 */
    private static bool $musicParserReady = false;
    /** @var array<string,string> 站点 URL 缓存（siteUrl / themeUrl） */
    private static array $musicUrlCache = [];
    private const CONTENT_PLACEHOLDER_ICON = '<path d="M5 8V20H19V8H5ZM5 6H19V4H5V6ZM20 22H4C3.44772 22 3 21.5523 3 21V3C3 2.44772 3.44772 2 4 2H20C20.5523 2 21 2.44772 21 3V21C21 21.5523 20.5523 22 20 22ZM7 10H11V14H7V10ZM7 16H17V18H7V16ZM13 11H17V13H13V11Z"></path>';

    /** 主解析入口：修复HyperDown、处理短代码、添加标题ID、包裹图片 */
    public static function parseContent($content, $widget, $lastResult = null): string
    {
        $html = empty($lastResult) ? $content : $lastResult;
        $html = preg_replace_callback(
            '/<img\b([^>]*?)\balt="\s*title="([^"]*)""\s+title="\s*title="([^"]*)""/i',
            function ($m) { $title = $m[2] !== '' ? $m[2] : $m[3]; return '<img' . $m[1] . 'alt="' . $title . '" title="' . $title . '"'; },
            $html
        );
        $html = self::handleShortcodes($html);
        $html = self::addHeadingIds($html);
        $html = self::wrapImages($html);
        return $html;
    }

    private static function handleShortcodes($html)
    {
        $html = self::parseCodeBlock($html); $html = self::parseButton($html);
        $html = self::parseProgress($html); $html = self::parseDownload($html);
        $html = self::parseGallery($html); $html = self::parseGithub($html);
        $html = self::parseBilibili($html);
        $html = self::parseMusic($html);
        $html = self::parsePostCard($html); $html = self::parsePageCard($html);
        $html = self::parseTabs($html); $html = self::parseCollapse($html);
        $html = self::parseTimeline($html); $html = self::parseAlert($html);
        return $html;
    }

    /** 代码块增强：为<pre><code>添加语言标签和复制按钮 */
    private static function parseCodeBlock(string $html): string
    {
        if (stripos($html, '<pre>') === false) return $html;
        return preg_replace_callback('#<pre><code([^>]*)>(.*?)</code></pre>#is', function ($matches) {
            $attrs = $matches[1]; $code = $matches[2]; $lang = '';
            if (preg_match('/\bclass=["\'](?:lang-)?([a-z0-9+#._-]+)["\']/i', $attrs, $langMatch)) $lang = strtolower($langMatch[1]);
            $langLabel = $lang !== '' ? htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') : 'text';
            return '<div class="code-block"><div class="code-block-header"><span class="code-block-lang">' . $langLabel . '</span>'
                . '<button class="code-block-copy" type="button" aria-label="复制代码">'
                . '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M7 6V3C7 2.44772 7.44772 2 8 2H20C20.5523 2 21 2.44772 21 3V17C21 17.5523 20.5523 18 20 18H17V21C17 21.5523 16.5523 22 16 22H4C3.44772 22 3 21.5523 3 21V7C3 6.44772 3.44772 6 4 6H7ZM5 8V20H15V8H5ZM17 16H19V4H9V6H16C16.5523 6 17 6.44772 17 7V16Z"></path></svg>'
                . '<span>复制</span></button></div><pre><code' . $attrs . '>' . $code . '</code></pre></div>';
        }, $html);
    }

    /** [abutton]/[button] 短代码 */
    private static function parseButton($html): string
    {
        $html = preg_replace_callback('#(<p>)?\[abutton\b([^\]]*)\](.*?)\[/abutton\](</p>)?#is', function ($matches) {
            $attrs = self::parseAttributes($matches[2]); $inner = self::trimBr($matches[3]);
            $cls = self::buildButtonClass($attrs); $url = isset($attrs['url']) ? trim($attrs['url'], '`') : '#';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="' . $cls . '" role="button">' . $inner . '</a>';
        }, $html);
        $html = preg_replace_callback('#(<p>)?\[button\b([^\]]*)\](.*?)\[/button\](</p>)?#is', function ($matches) {
            $attrs = self::parseAttributes($matches[2]); $inner = self::trimBr($matches[3]);
            $cls = self::buildButtonClass($attrs);
            $onclick = isset($attrs['onclick']) ? htmlspecialchars($attrs['onclick'], ENT_QUOTES, 'UTF-8') : '';
            $onclickAttr = $onclick !== '' ? ' onclick="' . $onclick . '"' : '';
            return '<button type="button" class="' . $cls . '"' . $onclickAttr . '>' . $inner . '</button>';
        }, $html);
        return $html;
    }

    private static function buildButtonClass(array $attrs): string
    {
        $theme = in_array($attrs['theme'] ?? '', ['primary', 'secondary', 'ghost', 'danger'], true) ? $attrs['theme'] : 'primary';
        $cls = 'btn btn-' . $theme;
        if (isset($attrs['size']) && in_array($attrs['size'], ['sm', 'lg'], true)) $cls .= ' btn-' . $attrs['size'];
        return $cls;
    }

    /** [collapse] 折叠框 */
    private static function parseCollapse(string $html): string
    {
        while (true) {
            if (!preg_match('#(<p>)?\[collapse\b([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/collapse\](<\/p>)?#i', $html, $matches)) break;
            $attrs = self::parseAttributes($matches[2]); $inner = self::trimBr($matches[4]);
            $title = htmlspecialchars($attrs['title'] ?? '折叠', ENT_QUOTES, 'UTF-8');
            $isOpen = isset($attrs['open']); $openClass = $isOpen ? ' is-open' : ''; $expanded = $isOpen ? 'true' : 'false';
            $replacement = '<div class="collapse' . $openClass . '" data-collapse><div class="collapse-header" role="button" tabindex="0" aria-expanded="' . $expanded . '"><span class="collapse-title">' . $title . '</span><span class="collapse-chevron" aria-hidden="true"></span></div><div class="collapse-body">' . $inner . '</div></div>';
            $html = self::replaceOnce($html, $matches[0], $replacement);
        }
        return $html;
    }

    /** [tabs] 选项卡 */
    private static function parseTabs(string $html): string
    {
        while (true) {
            if (!preg_match('#(<p>)?\[tabs\b([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/tabs\](<\/p>)?#i', $html, $matches)) break;
            $inner = $matches[4]; $id = 'tabs-' . (self::$counter++);
            preg_match_all('#(<p>)?\[tab\s+([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/tab\](<\/p>)?#i', $inner, $tabMatches, PREG_SET_ORDER);
            if (empty($tabMatches)) { $html = self::replaceOnce($html, $matches[0], ''); continue; }
            $navItems = []; $panels = []; $index = 0;
            foreach ($tabMatches as $tab) {
                $tAttrs = self::parseAttributes($tab[2]); $tContent = self::trimBr($tab[4]);
                $tTitle = htmlspecialchars($tAttrs['title'] ?? 'Tab', ENT_QUOTES, 'UTF-8');
                $active = isset($tAttrs['active']); $tabId = $id . '-tab' . $index;
                $navClass = $active ? 'tab is-active' : 'tab'; $ariaSel = $active ? 'true' : 'false'; $tabIdx = $active ? '0' : '-1';
                $navItems[] = '<button type="button" role="tab" class="' . $navClass . '" data-tab="' . $tabId . '" aria-selected="' . $ariaSel . '" tabindex="' . $tabIdx . '" aria-controls="panel-' . $tabId . '">' . $tTitle . '</button>';
                $panelClass = $active ? 'tabs-panel is-active' : 'tabs-panel';
                $panels[] = '<div class="' . $panelClass . '" data-panel="' . $tabId . '" id="panel-' . $tabId . '" role="tabpanel" aria-labelledby="tab-' . $tabId . '">' . $tContent . '</div>';
                $index++;
            }
            $replacement = '<div class="tabs" data-tabs><div class="tabs-nav" role="tablist" aria-label="选项卡">' . implode('', $navItems) . '</div>' . implode('', $panels) . '</div>';
            $html = self::replaceOnce($html, $matches[0], $replacement);
        }
        return $html;
    }

    /** [timeline] 时间线 */
    private static function parseTimeline(string $html): string
    {
        while (true) {
            if (!preg_match('#(<p>)?\[timeline\b([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/timeline\](<\/p>)?#i', $html, $matches)) break;
            $inner = $matches[4];
            preg_match_all('#(<p>)?\[event\s+([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/event\](<\/p>)?#i', $inner, $evMatches, PREG_SET_ORDER);
            if (empty($evMatches)) { $html = self::replaceOnce($html, $matches[0], ''); continue; }
            $items = [];
            foreach ($evMatches as $ev) {
                $eAttrs = self::parseAttributes($ev[2]); $eContent = self::trimBr($ev[4]);
                $date = htmlspecialchars($eAttrs['date'] ?? '', ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars($eAttrs['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $items[] = '<li class="timeline-item"><span class="timeline-dot" aria-hidden="true"></span><div class="timeline-content">'
                    . ($date ? '<time class="timeline-time" datetime="' . $date . '">' . $date . '</time>' : '')
                    . ($title ? '<h4 class="timeline-title">' . $title . '</h4>' : '') . $eContent . '</div></li>';
            }
            $replacement = '<ol class="timeline">' . implode('', $items) . '</ol>';
            $html = self::replaceOnce($html, $matches[0], $replacement);
        }
        return $html;
    }

    /** [alert] 提示框 */
    private static function parseAlert(string $html): string
    {
        while (true) {
            if (!preg_match('#(<p>)?\[alert\b([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/alert\](<\/p>)?#i', $html, $matches)) break;
            $attrs = self::parseAttributes($matches[2]); $content = self::trimBr($matches[4]);
            $type = in_array($attrs['type'] ?? '', ['info', 'success', 'warning', 'danger'], true) ? $attrs['type'] : 'info';
            $title = htmlspecialchars($attrs['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $icon = self::$alertIcons[$type]; $role = ($type === 'warning' || $type === 'danger') ? 'alert' : 'status';
            $titleHtml = $title !== '' ? '<p class="alert-title">' . $title . '</p>' : '';
            $replacement = '<div class="alert alert-' . $type . '" role="' . $role . '"><div class="alert-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24">' . $icon . '</svg></div><div class="alert-body">' . $titleHtml . '<p class="alert-text">' . $content . '</p></div></div>';
            $html = self::replaceOnce($html, $matches[0], $replacement);
        }
        return $html;
    }

    /** [progress] 进度条 */
    private static function parseProgress(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[progress\s+([^\]]*)\](<\/p>)?#i', function ($matches) {
            $attrs = self::parseAttributes($matches[2]); $label = htmlspecialchars($attrs['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $value = (int) ($attrs['value'] ?? 0); $value = max(0, min(100, $value));
            $color = in_array($attrs['color'] ?? '', ['success', 'warning', 'danger'], true) ? ' is-' . $attrs['color'] : '';
            return '<div class="progress-row"><span class="progress-row-label">' . $label . '</span><progress class="progress' . $color . '" value="' . $value . '" max="100"></progress><span class="progress-row-value">' . $value . '%</span></div>';
        }, $html);
    }

    /** [gallery] 图片网格/相册 */
    private static function parseGallery(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[gallery\b([^\]]*)\](<br\s*/?>)?([\s\S]*?)(<br\s*/?>)?\[\/gallery\](<\/p>)?#i', function ($matches) {
            $attrs = self::parseAttributes($matches[2]); $content = $matches[4];
            $cols = in_array($attrs['cols'] ?? '3', ['2', '3', '4'], true) ? $attrs['cols'] : '3';
            $visible = isset($attrs['visible']) ? (int) $attrs['visible'] : null; $gid = 'gallery-' . (self::$counter++);
            $arStyle = '';
            if (isset($attrs['ar']) && preg_match('#^\s*(\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)\s*$#', (string) $attrs['ar'], $arM))
                $arStyle = ' style="aspect-ratio:' . $arM[1] . ' / ' . $arM[2] . ';"';
            preg_match_all('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $content, $srcMatches);
            preg_match_all('/<img\b[^>]*\balt=["\']([^"\']*)["\']/i', $content, $altMatches);
            preg_match_all('/<img\b[^>]*\btitle=["\']([^"\']*)["\']/i', $content, $titleMatches);
            $total = count($srcMatches[1]); if ($total === 0) return '';
            $images = [];
            for ($i = 0; $i < $total; $i++) {
                $alt = $altMatches[1][$i] ?? ''; if ($alt === '') $alt = $titleMatches[1][$i] ?? '';
                $images[] = ['src' => $srcMatches[1][$i], 'alt' => $alt];
            }
            $isAlbum = ($visible !== null && $visible > 0 && $visible < $total); $items = []; $remaining = $isAlbum ? ($total - $visible) : 0;
            foreach ($images as $i => $img) {
                $escUrl = htmlspecialchars($img['src'], ENT_QUOTES, 'UTF-8');
                $caption = $img['alt'] !== '' ? $img['alt'] : ('图片 ' . ($i + 1));
                $escCap = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); $extraAttrs = '';
                if ($isAlbum) {
                    if ($i < $visible - 1) { } elseif ($i === $visible - 1) {
                        $extraAttrs = ' class="is-overlay" data-count="+' . $remaining . '" aria-label="共 ' . $total . ' 张图片，点击查看全部"';
                    } else { $extraAttrs = ' class="is-hidden" aria-hidden="true"'; }
                }
                $items[] = '<a href="' . $escUrl . '" data-fancybox="' . $gid . '" data-type="image" data-caption="' . $escCap . '"' . $extraAttrs . $arStyle . '><figure><img src="' . $escUrl . '" alt="' . $escCap . '" loading="lazy"><figcaption>' . $escCap . '</figcaption></figure></a>';
            }
            $label = $isAlbum ? ' aria-label="相册，共 ' . $total . ' 张"' : ''; $visibleAttr = ($isAlbum) ? ' data-visible="' . $visible . '"' : '';
            return '<div class="gallery cols-' . $cols . '"' . $visibleAttr . $label . '>' . implode('', $items) . '</div>';
        }, $html);
    }

    /** [download] 下载卡片 */
    private static function parseDownload(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[download\s+([^\]]*)\](<\/p>)?#i', function ($matches) {
            $attrs = self::parseAttributes($matches[2]);
            $name = htmlspecialchars($attrs['name'] ?? '文件', ENT_QUOTES, 'UTF-8');
            $size = htmlspecialchars($attrs['size'] ?? '', ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($attrs['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $code = htmlspecialchars($attrs['code'] ?? '', ENT_QUOTES, 'UTF-8');
            $source = htmlspecialchars($attrs['source'] ?? '网盘', ENT_QUOTES, 'UTF-8');
            $codeHtml = $code !== '' ? '<span class="download-card-tag">提取码: ' . $code . '</span>' : '';
            $copyBtnHtml = $code !== '' ? '<button class="download-card-btn copy-btn" type="button" data-copy="' . $code . '" aria-label="复制提取码 ' . $code . '"><svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M7 6V3C7 2.44772 7.44772 2 8 2H20C20.5523 2 21 2.44772 21 3V17C21 17.5523 20.5523 18 20 18H17V21C17 21.5523 16.5523 22 16 22H4C3.44772 22 3 21.5523 3 21V7C3 6.44772 3.44772 6 4 6H7ZM5 8V20H15V8H5ZM17 16H19V4H9V6H16C16.5523 6 17 6.44772 17 7V16Z"/></svg></button>' : '';
            return '<div class="download-card"><div class="download-card-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24"><path d="M3 19H21V21H3V19ZM13 13.1716L17.9497 8.22168L19.3639 9.63589L12 17L4.63614 9.63589L6.05025 8.22168L11 13.1716V1H13V13.1716Z"/></svg></div>'
                . '<div class="download-card-body"><div class="download-card-meta"><span class="download-card-name">' . $name . '</span>'
                . ($size !== '' ? '<span class="download-card-size">' . $size . '</span>' : '') . '</div>'
                . '<div class="download-card-source"><span class="download-card-tag">' . $source . '</span>' . $codeHtml . '</div></div>'
                . '<div class="download-card-actions">' . $copyBtnHtml
                . '<a href="' . $url . '" class="download-card-btn primary" target="_blank" rel="noopener noreferrer" aria-label="下载">'
                . '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M3 19H21V21H3V19ZM13 13.1716L17.9497 8.22168L19.3639 9.63589L12 17L4.63614 9.63589L6.05025 8.22168L11 13.1716V1H13V13.1716Z"/></svg></a></div></div>';
        }, $html);
    }

    /** [github] 项目卡片 */
    private static function parseGithub(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[github\s+repo\s*=\s*"([^"]+)"\](<\/p>)?#i', function ($matches) {
            $repo = htmlspecialchars(trim($matches[2]), ENT_QUOTES, 'UTF-8');
            if (!str_contains($repo, '/')) return '<div class="github-card" style="border-color:var(--error);"><p class="github-card-desc">GitHub 仓库格式错误，应为 owner/repo</p></div>';
            $cacheData = self::getGithubCache($repo);
            if ($cacheData !== null) return self::buildGithubCardHtml($repo, $cacheData, true);
            return self::buildGithubCardSkeleton($repo);
        }, $html);
    }

    /** 读取 GitHub 缓存 */
    private static function getGithubCache(string $repo): ?array
    {
        $cacheDir = __DIR__ . '/cache'; $key = 'github_repo_' . $repo;
        $file = $cacheDir . '/' . md5($key) . '.json'; $metaFile = $cacheDir . '/' . md5($key) . '.meta';
        if (!is_dir($cacheDir) || !is_file($file) || !is_file($metaFile)) return null;
        $meta = json_decode(file_get_contents($metaFile), true);
        if (!is_array($meta) || ($meta['expires_at'] ?? 0) <= time()) return null;
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /** 构建 GitHub 完整卡片 */
    private static function buildGithubCardHtml(string $repo, array $data, bool $cached): string
    {
        $url = 'https://github.com/' . $repo;
        $name = htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $owner = htmlspecialchars($data['owner'] ?? '', ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $stars = self::formatGithubNumber((int) ($data['stargazers_count'] ?? 0));
        $forks = self::formatGithubNumber((int) ($data['forks_count'] ?? 0));
        $lang = htmlspecialchars($data['language'] ?? '', ENT_QUOTES, 'UTF-8');
        $langColor = self::getLangColor($data['language'] ?? '');
        $cachedAttr = $cached ? ' data-cached="true"' : '';
        $langHtml = $lang !== '' ? '<div class="github-card-lang"><span class="github-card-lang-dot" style="--lang-color:' . $langColor . '"></span><span class="github-card-lang-name">' . $lang . '</span></div>' : '';
        return '<a class="github-card"' . $cachedAttr . ' data-repo="' . $repo . '" href="' . $url . '" target="_blank" rel="noopener noreferrer">'
            . '<div class="github-card-header"><svg class="github-card-logo" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.87 8.17 6.84 9.5.5.08.66-.23.66-.5v-1.69c-2.77.6-3.36-1.34-3.36-1.34-.46-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.87 1.52 2.34 1.07 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.92 0-1.11.38-2 1.03-2.71-.1-.25-.45-1.29.1-2.64 0 0 .84-.27 2.75 1.02.79-.22 1.65-.33 2.5-.33.85 0 1.71.11 2.5.33 1.91-1.29 2.75-1.02 2.75-1.02.55 1.35.2 2.39.1 2.64.65.71 1.03 1.6 1.03 2.71 0 3.82-2.34 4.66-4.57 4.91.36.31.69.92.69 1.85V21c0 .27.16.59.67.5C19.14 20.16 22 16.42 22 12A10 10 0 0012 2z"/></svg>'
            . '<div class="github-card-repo"><span class="github-card-owner">' . $owner . '</span><span class="github-card-sep">/</span><span class="github-card-name">' . $name . '</span></div></div>'
            . ($desc !== '' ? '<p class="github-card-desc">' . $desc . '</p>' : '<p class="github-card-desc"></p>') . $langHtml
            . '<div class="github-card-stats">'
            . '<span class="github-card-stat" aria-label="Stars"><svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span class="github-card-stat-num" data-stat="stars">' . $stars . '</span></span>'
            . '<span class="github-card-stat" aria-label="Forks"><svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M9 6C9 7.30622 8.16519 8.41746 7 8.82929V9C7 10.1046 7.89543 11 9 11H15C16.1046 11 17 10.1046 17 9V8.82929C15.8348 8.41746 15 7.30622 15 6C15 4.34315 16.3431 3 18 3C19.6569 3 21 4.34315 21 6C21 7.30622 20.1652 8.41746 19 8.82929V9C19 11.2091 17.2091 13 15 13H13V15.1707C14.1652 15.5825 15 16.6938 15 18C15 19.6569 13.6569 21 12 21C10.3431 21 9 19.6569 9 18C9 16.6938 9.83481 15.5825 11 15.1707V13H9C6.79086 13 5 11.2091 5 9V8.82929C3.83481 8.41746 3 7.30622 3 6C3 4.34315 4.34315 3 6 3C7.65685 3 9 4.34315 9 6Z"/></svg><span class="github-card-stat-num" data-stat="forks">' . $forks . '</span></span></div></a>';
    }

    /** 构建 GitHub 骨架卡片 */
    private static function buildGithubCardSkeleton(string $repo): string
    {
        $url = 'https://github.com/' . $repo;
        return '<a class="github-card" data-repo="' . $repo . '" href="' . $url . '" target="_blank" rel="noopener noreferrer">'
            . '<div class="github-card-header"><svg class="github-card-logo" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.87 8.17 6.84 9.5.5.08.66-.23.66-.5v-1.69c-2.77.6-3.36-1.34-3.36-1.34-.46-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.87 1.52 2.34 1.07 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.92 0-1.11.38-2 1.03-2.71-.1-.25-.45-1.29.1-2.64 0 0 .84-.27 2.75 1.02.79-.22 1.65-.33 2.5-.33.85 0 1.71.11 2.5.33 1.91-1.29 2.75-1.02 2.75-1.02.55 1.35.2 2.39.1 2.64.65.71 1.03 1.6 1.03 2.71 0 3.82-2.34 4.66-4.57 4.91.36.31.69.92.69 1.85V21c0 .27.16.59.67.5C19.14 20.16 22 16.42 22 12A10 10 0 0012 2z"/></svg>'
            . '<div class="github-card-repo"><span class="github-card-owner"></span><span class="github-card-sep">/</span><span class="github-card-name"></span></div></div>'
            . '<p class="github-card-desc"></p>'
            . '<div class="github-card-lang"><span class="github-card-lang-dot" style="--lang-color:#4F5D95;"></span><span class="github-card-lang-name"></span></div>'
            . '<div class="github-card-stats">'
            . '<span class="github-card-stat" aria-label="Stars"><svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span class="github-card-stat-num" data-stat="stars">--</span></span>'
            . '<span class="github-card-stat" aria-label="Forks"><svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M9 6C9 7.30622 8.16519 8.41746 7 8.82929V9C7 10.1046 7.89543 11 9 11H15C16.1046 11 17 10.1046 17 9V8.82929C15.8348 8.41746 15 7.30622 15 6C15 4.34315 16.3431 3 18 3C19.6569 3 21 4.34315 21 6C21 7.30622 20.1652 8.41746 19 8.82929V9C19 11.2091 17.2091 13 15 13H13V15.1707C14.1652 15.5825 15 16.6938 15 18C15 19.6569 13.6569 21 12 21C10.3431 21 9 19.6569 9 18C9 16.6938 9.83481 15.5825 11 15.1707V13H9C6.79086 13 5 11.2091 5 9V8.82929C3.83481 8.41746 3 7.30622 3 6C3 4.34315 4.34315 3 6 3C7.65685 3 9 4.34315 9 6Z"/></svg><span class="github-card-stat-num" data-stat="forks">--</span></span></div></a>';
    }

    /** 格式化 GitHub 数字 */
    private static function formatGithubNumber(int $n): string { if ($n >= 1000) return round($n / 1000, 1) . 'k'; return (string) $n; }

    /** 语言颜色映射 */
    private static function getLangColor(string $lang): string
    {
        $map = ['JavaScript'=>'#f1e05a','TypeScript'=>'#3178c6','HTML'=>'#e34c26','CSS'=>'#563d7c','PHP'=>'#4F5D95','Python'=>'#3572A5','Java'=>'#b07219','Go'=>'#00ADD8','Rust'=>'#dea584','Ruby'=>'#701516','C'=>'#555555','C++'=>'#f34b7d','C#'=>'#178600','Swift'=>'#ffac45','Kotlin'=>'#A97BFF','Vue'=>'#41b883','Shell'=>'#89e051'];
        return $map[$lang] ?? '#4F5D95';
    }

    /** [bilibili] 卡片 */
    private static function parseBilibili(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[bilibili\s+([^\]]*)\](<\/p>)?#i', function ($matches) {
            $attrs = self::parseAttributes($matches[2]);
            if (!empty($attrs['bv'])) {
                $bvid = htmlspecialchars(trim($attrs['bv']), ENT_QUOTES, 'UTF-8');
                $href = 'https://www.bilibili.com/video/' . $bvid; $attrStr = ' data-bvid="' . $bvid . '"';
                $cacheData = self::getBiliCache('bili_video_bv_' . $bvid);
                if ($cacheData !== null) return self::buildBiliCardHtml($bvid, $href, $attrStr, $cacheData, true);
                return self::buildBiliCardSkeleton($bvid, $href, $attrStr);
            } elseif (!empty($attrs['aid'])) {
                $aid = htmlspecialchars(trim($attrs['aid']), ENT_QUOTES, 'UTF-8');
                $href = 'https://www.bilibili.com/video/av' . $aid; $attrStr = ' data-aid="' . $aid . '"';
                $cacheData = self::getBiliCache('bili_video_av_' . $aid);
                if ($cacheData !== null) return self::buildBiliCardHtml('', $href, $attrStr, $cacheData, true);
                return self::buildBiliCardSkeleton('', $href, $attrStr);
            }
            return '<div class="bili-card" style="border-color:var(--error);"><p class="bili-card-desc">B站卡片需要指定 bv 或 aid 参数</p></div>';
        }, $html);
    }

    /** B站缓存 */
    private static function getBiliCache(string $cacheKey): ?array
    {
        $cacheDir = __DIR__ . '/cache';
        $file = $cacheDir . '/' . md5($cacheKey) . '.json'; $metaFile = $cacheDir . '/' . md5($cacheKey) . '.meta';
        if (!is_dir($cacheDir) || !is_file($file) || !is_file($metaFile)) return null;
        $meta = json_decode(file_get_contents($metaFile), true);
        if (!is_array($meta) || ($meta['expires_at'] ?? 0) <= time()) return null;
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /** B站完整卡片 */
    private static function buildBiliCardHtml(string $bvid, string $href, string $attrStr, array $data, bool $cached): string
    {
        $cachedAttr = $cached ? ' data-cached="true"' : '';
        $title = htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($data['desc'] ?? '', ENT_QUOTES, 'UTF-8');
        $pic = htmlspecialchars(str_replace('http://', 'https://', $data['pic'] ?? ''), ENT_QUOTES, 'UTF-8');
        $duration = isset($data['duration']) ? self::formatBiliDuration((int) $data['duration']) : '';
        $upName = htmlspecialchars($data['up_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $upFace = htmlspecialchars(str_replace('http://', 'https://', $data['up_face'] ?? ''), ENT_QUOTES, 'UTF-8');
        $pubdate = isset($data['pubdate']) ? self::formatBiliDate((int) $data['pubdate']) : '';
        $view = self::formatBiliCount((int) ($data['view'] ?? 0)); $danmaku = self::formatBiliCount((int) ($data['danmaku'] ?? 0));
        $like = self::formatBiliCount((int) ($data['like'] ?? 0)); $coin = self::formatBiliCount((int) ($data['coin'] ?? 0));
        $coverImg = $pic ? '<img class="no-fancybox" alt="' . $title . '" loading="lazy" referrerpolicy="no-referrer" src="' . $pic . '">' : '<img alt="" loading="lazy" referrerpolicy="no-referrer">';
        $upAvatar = $upFace ? '<img class="no-fancybox" alt="' . $upName . '" loading="lazy" referrerpolicy="no-referrer" src="' . $upFace . '">' : '<img alt="" loading="lazy" referrerpolicy="no-referrer">';
        $bs = '<svg class="icon" viewBox="0 0 24 24"><path d="M7.17157 2.75737L10.414 5.99948H13.585L16.8284 2.75737C17.219 2.36685 17.8521 2.36685 18.2426 2.75737C18.6332 3.1479 18.6332 3.78106 18.2426 4.17158L16.414 5.99948L18.5 6.00001C20.433 6.00001 22 7.56701 22 9.50001V17.5C22 19.433 20.433 21 18.5 21H5.5C3.567 21 2 19.433 2 17.5V9.50001C2 7.56701 3.567 6.00001 5.5 6.00001L7.585 5.99948L5.75736 4.17158C5.36684 3.78106 5.36684 3.1479 5.75736 2.75737C6.14788 2.36685 6.78105 2.36685 7.17157 2.75737ZM18.5 8.00001H5.5C4.7203 8.00001 4.07955 8.5949 4.00687 9.35555L4 9.50001V17.5C4 18.2797 4.59489 18.9205 5.35554 18.9931L5.5 19H18.5C19.2797 19 19.9204 18.4051 19.9931 17.6445L20 17.5V9.50001C20 8.67158 19.3284 8.00001 18.5 8.00001ZM8 11C8.55228 11 9 11.4477 9 12V14C9 14.5523 8.55228 15 8 15C7.44772 15 7 14.5523 7 14V12C7 11.4477 7.44772 11 8 11ZM16 11C16.5523 11 17 11.4477 17 12V14C17 14.5523 16.5523 15 16 15C15.4477 15 15 14.5523 15 14V12C15 11.4477 15.4477 11 16 11Z" /></svg>';
        return '<a class="bili-card"' . $cachedAttr . $attrStr . ' href="' . $href . '" target="_blank" rel="noopener noreferrer" aria-label="哔哩哔哩视频：' . $title . '">'
            . '<div class="bili-card-cover">' . $coverImg
            . '<span class="bili-card-cover-play" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 100-16 8 8 0 000 16zM10 8L16 12L10 16V8z"/></svg></span>'
            . '<span class="bili-card-source" aria-hidden="true">' . $bs . '哔哩哔哩</span>'
            . '<span class="bili-card-duration">' . $duration . '</span></div>'
            . '<div class="bili-card-body"><h4 class="bili-card-title">' . $title . '</h4>'
            . ($desc ? '<p class="bili-card-desc">' . $desc . '</p>' : '<p class="bili-card-desc"></p>')
            . '<div class="bili-card-up"><span class="bili-card-up-avatar">' . $upAvatar . '</span>'
            . '<span class="bili-card-up-name">' . $upName . '</span><span class="bili-card-up-time">' . $pubdate . '</span></div>'
            . '<div class="bili-card-stats">'
            . '<span class="bili-card-stat" aria-label="播放量"><svg class="icon" viewBox="0 0 24 24"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM10.6219 8.41459L15.5008 11.6672C15.6846 11.7897 15.7343 12.0381 15.6117 12.2219C15.5824 12.2658 15.5447 12.3035 15.5008 12.3328L10.6219 15.5854C10.4381 15.708 10.1897 15.6583 10.0672 15.4745C10.0234 15.4088 10 15.3316 10 15.2526V8.74741C10 8.52649 10.1791 8.34741 10.4 8.34741C10.479 8.34741 10.5562 8.37078 10.6219 8.41459Z" /></svg><span class="bili-card-stat-num">' . $view . '</span></span>'
            . '<span class="bili-card-stat" aria-label="弹幕数"><svg class="icon" viewBox="0 0 24 24"><path d="M2 4h20v12H2V4zm2 2v8h16V6H4zm2 2h4v2H6V8zm6 0h4v2h-4V8zm-6 4h8v2H6v-2zm10 0h2v2h-2v-2zM2 18h20v2H2v-2z"/></svg><span class="bili-card-stat-num">' . $danmaku . '</span></span>'
            . '<span class="bili-card-stat" aria-label="点赞数"><svg class="icon" viewBox="0 0 24 24"><path d="M2 8.99997H5V21H2C1.44772 21 1 20.5523 1 20V9.99997C1 9.44769 1.44772 8.99997 2 8.99997ZM7.29289 7.70708L13.6934 1.30661C13.8693 1.13066 14.1479 1.11087 14.3469 1.26016L15.1995 1.8996C15.6842 2.26312 15.9026 2.88253 15.7531 3.46966L14.5998 7.99997H21C22.1046 7.99997 23 8.8954 23 9.99997V12.1043C23 12.3656 22.9488 12.6243 22.8494 12.8658L19.755 20.3807C19.6007 20.7554 19.2355 21 18.8303 21H8C7.44772 21 7 20.5523 7 20V8.41419C7 8.14897 7.10536 7.89462 7.29289 7.70708Z"/></svg><span class="bili-card-stat-num">' . $like . '</span></span>'
            . '<span class="bili-card-stat" aria-label="投币数"><svg class="icon" viewBox="0 0 26 26"><path fill-rule="evenodd" clip-rule="evenodd" d="M14.045 25.5454C7.69377 25.5454 2.54504 20.3967 2.54504 14.0454C2.54504 7.69413 7.69377 2.54541 14.045 2.54541C20.3963 2.54541 25.545 7.69413 25.545 14.0454C25.545 17.0954 24.3334 20.0205 22.1768 22.1771C20.0201 24.3338 17.095 25.5454 14.045 25.5454ZM9.66202 6.81624H18.2761C18.825 6.81624 19.27 7.22183 19.27 7.72216C19.27 8.22248 18.825 8.62807 18.2761 8.62807H14.95V10.2903C17.989 10.4444 20.3766 12.9487 20.3855 15.9916V17.1995C20.3854 17.6997 19.9799 18.1052 19.4796 18.1052C18.9793 18.1052 18.5738 17.6997 18.5737 17.1995V15.9916C18.5667 13.9478 16.9882 12.2535 14.95 12.1022V20.5574C14.95 21.0577 14.5444 21.4633 14.0441 21.4633C13.5437 21.4633 13.1382 21.0577 13.1382 20.5574V12.1022C11.1 12.2535 9.52148 13.9478 9.51448 15.9916V17.1995C9.5144 17.6997 9.10883 18.1052 8.60856 18.1052C8.1083 18.1052 7.70273 17.6997 7.70265 17.1995V15.9916C7.71158 12.9487 10.0992 10.4444 13.1382 10.2903V8.62807H9.66202C9.11309 8.62807 8.66809 8.22248 8.66809 7.72216C8.66809 7.22183 9.11309 6.81624 9.66202 6.81624Z" fill="currentColor"/></svg><span class="bili-card-stat-num">' . $coin . '</span></span>'
            . '</div></div></a>';
    }

    /** B站骨架卡片 */
    private static function buildBiliCardSkeleton(string $bvid, string $href, string $attrStr): string
    {
        $bs = '<svg class="icon" viewBox="0 0 24 24"><path d="M7.17157 2.75737L10.414 5.99948H13.585L16.8284 2.75737C17.219 2.36685 17.8521 2.36685 18.2426 2.75737C18.6332 3.1479 18.6332 3.78106 18.2426 4.17158L16.414 5.99948L18.5 6.00001C20.433 6.00001 22 7.56701 22 9.50001V17.5C22 19.433 20.433 21 18.5 21H5.5C3.567 21 2 19.433 2 17.5V9.50001C2 7.56701 3.567 6.00001 5.5 6.00001L7.585 5.99948L5.75736 4.17158C5.36684 3.78106 5.36684 3.1479 5.75736 2.75737C6.14788 2.36685 6.78105 2.36685 7.17157 2.75737ZM18.5 8.00001H5.5C4.7203 8.00001 4.07955 8.5949 4.00687 9.35555L4 9.50001V17.5C4 18.2797 4.59489 18.9205 5.35554 18.9931L5.5 19H18.5C19.2797 19 19.9204 18.4051 19.9931 17.6445L20 17.5V9.50001C20 8.67158 19.3284 8.00001 18.5 8.00001ZM8 11C8.55228 11 9 11.4477 9 12V14C9 14.5523 8.55228 15 8 15C7.44772 15 7 14.5523 7 14V12C7 11.4477 7.44772 11 8 11ZM16 11C16.5523 11 17 11.4477 17 12V14C17 14.5523 16.5523 15 16 15C15.4477 15 15 14.5523 15 14V12C15 11.4477 15.4477 11 16 11Z" /></svg>';
        return '<a class="bili-card"' . $attrStr . ' href="' . $href . '" target="_blank" rel="noopener noreferrer" aria-label="哔哩哔哩视频">'
            . '<div class="bili-card-cover"><img class="no-fancybox" alt="" loading="lazy" referrerpolicy="no-referrer">'
            . '<span class="bili-card-cover-play" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 100-16 8 8 0 000 16zM10 8L16 12L10 16V8z"/></svg></span>'
            . '<span class="bili-card-source" aria-hidden="true">' . $bs . '哔哩哔哩</span>'
            . '<span class="bili-card-duration"></span></div>'
            . '<div class="bili-card-body"><h4 class="bili-card-title"></h4><p class="bili-card-desc"></p>'
            . '<div class="bili-card-up"><span class="bili-card-up-avatar"><img class="no-fancybox" alt="" loading="lazy" referrerpolicy="no-referrer"></span>'
            . '<span class="bili-card-up-name"></span><span class="bili-card-up-time"></span></div>'
            . '<div class="bili-card-stats">'
            . '<span class="bili-card-stat" aria-label="播放量"><svg class="icon" viewBox="0 0 24 24"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM10.6219 8.41459L15.5008 11.6672C15.6846 11.7897 15.7343 12.0381 15.6117 12.2219C15.5824 12.2658 15.5447 12.3035 15.5008 12.3328L10.6219 15.5854C10.4381 15.708 10.1897 15.6583 10.0672 15.4745C10.0234 15.4088 10 15.3316 10 15.2526V8.74741C10 8.52649 10.1791 8.34741 10.4 8.34741C10.479 8.34741 10.5562 8.37078 10.6219 8.41459Z" /></svg><span class="bili-card-stat-num"></span></span>'
            . '<span class="bili-card-stat" aria-label="弹幕数"><svg class="icon" viewBox="0 0 24 24"><path d="M2 4h20v12H2V4zm2 2v8h16V6H4zm2 2h4v2H6V8zm6 0h4v2h-4V8zm-6 4h8v2H6v-2zm10 0h2v2h-2v-2zM2 18h20v2H2v-2z"/></svg><span class="bili-card-stat-num"></span></span>'
            . '<span class="bili-card-stat" aria-label="点赞数"><svg class="icon" viewBox="0 0 24 24"><path d="M2 8.99997H5V21H2C1.44772 21 1 20.5523 1 20V9.99997C1 9.44769 1.44772 8.99997 2 8.99997ZM7.29289 7.70708L13.6934 1.30661C13.8693 1.13066 14.1479 1.11087 14.3469 1.26016L15.1995 1.8996C15.6842 2.26312 15.9026 2.88253 15.7531 3.46966L14.5998 7.99997H21C22.1046 7.99997 23 8.8954 23 9.99997V12.1043C23 12.3656 22.9488 12.6243 22.8494 12.8658L19.755 20.3807C19.6007 20.7554 19.2355 21 18.8303 21H8C7.44772 21 7 20.5523 7 20V8.41419C7 8.14897 7.10536 7.89462 7.29289 7.70708Z"/></svg><span class="bili-card-stat-num"></span></span>'
            . '<span class="bili-card-stat" aria-label="投币数"><svg class="icon" viewBox="0 0 26 26"><path fill-rule="evenodd" clip-rule="evenodd" d="M14.045 25.5454C7.69377 25.5454 2.54504 20.3967 2.54504 14.0454C2.54504 7.69413 7.69377 2.54541 14.045 2.54541C20.3963 2.54541 25.545 7.69413 25.545 14.0454C25.545 17.0954 24.3334 20.0205 22.1768 22.1771C20.0201 24.3338 17.095 25.5454 14.045 25.5454ZM9.66202 6.81624H18.2761C18.825 6.81624 19.27 7.22183 19.27 7.72216C19.27 8.22248 18.825 8.62807 18.2761 8.62807H14.95V10.2903C17.989 10.4444 20.3766 12.9487 20.3855 15.9916V17.1995C20.3854 17.6997 19.9799 18.1052 19.4796 18.1052C18.9793 18.1052 18.5738 17.6997 18.5737 17.1995V15.9916C18.5667 13.9478 16.9882 12.2535 14.95 12.1022V20.5574C14.95 21.0577 14.5444 21.4633 14.0441 21.4633C13.5437 21.4633 13.1382 21.0577 13.1382 20.5574V12.1022C11.1 12.2535 9.52148 13.9478 9.51448 15.9916V17.1995C9.5144 17.6997 9.10883 18.1052 8.60856 18.1052C8.1083 18.1052 7.70273 17.6997 7.70265 17.1995V15.9916C7.71158 12.9487 10.0992 10.4444 13.1382 10.2903V8.62807H9.66202C9.11309 8.62807 8.66809 8.22248 8.66809 7.72216C8.66809 7.22183 9.11309 6.81624 9.66202 6.81624Z" fill="currentColor"/></svg><span class="bili-card-stat-num"></span></span>'
            . '</div></div></a>';
    }

    private static function formatBiliDuration(int $seconds): string { $m = floor($seconds / 60); $s = $seconds % 60; return $m . ':' . str_pad((string) $s, 2, '0', STR_PAD_LEFT); }
    private static function formatBiliDate(int $timestamp): string { return date('Y-m-d', $timestamp); }
    private static function formatBiliCount(int $n): string { if ($n >= 10000) return round($n / 10000, 1) . '万'; return (string) $n; }

    /**
     * [music] 音乐卡片短代码
     *
     * 支持两种写法：
     *   1. 平台歌曲：[music wy="歌曲ID"] / [music tx="歌曲ID"] / [music kg="歌曲ID"]
     *   2. 手动音频：[music title="歌名" artist="歌手" url="/歌曲.mp3" pic="封面图.png"]
     */
    private static function parseMusic(string $html): string
    {
        if (stripos($html, '[music') === false) {
            return $html;
        }
        if (!preg_match_all('#(<p>)?\[music\b([^\]]*)\](<br\s*/?>)?(</p>)?#i', $html, $matches, PREG_SET_ORDER)) {
            return $html;
        }

        $lines = [];
        $lineIdx = [];
        foreach ($matches as $idx => $match) {
            $line = self::buildMusicLine(self::parseAttributes($match[2]));
            if ($line === null) {
                $lineIdx[$idx] = null;
                continue;
            }
            $lineIdx[$idx] = count($lines);
            $lines[] = $line;
        }

        $byKey = [];
        $manualItems = [];
        if (!empty($lines)) {
            self::ensureMusicParser();
            try {
                $parsed = MusicParserFactory::batchParseFromText($lines);
            } catch (\Throwable $e) {
                $parsed = [];
            }
            foreach ($parsed as $item) {
                if (($item['source'] ?? '') === 'manual') {
                    $manualItems[] = $item;
                } else {
                    $byKey[($item['source'] ?? '') . ':' . ($item['raw_id'] ?? '')] = $item;
                }
            }
        }

        $manualIdx = 0;
        foreach ($matches as $idx => $match) {
            $item = null;
            $li = $lineIdx[$idx] ?? null;
            if ($li !== null) {
                $info = MusicParserFactory::parseLine($lines[$li]);
                if ($info !== null && ($info['source'] ?? '') === 'manual') {
                    $item = $manualItems[$manualIdx++] ?? null;
                } elseif ($info !== null) {
                    $item = $byKey[($info['source'] ?? '') . ':' . ($info['raw_id'] ?? '')] ?? null;
                }
            }

            $card = ($item !== null && !empty($item['name']) && !empty($item['url']))
                ? self::buildMusicCard($item)
                : '<div class="music-card" style="border-color:var(--error);"><p class="music-card-desc">音乐卡片解析失败，请检查短代码参数或稍后重试</p></div>';
            $html = self::replaceOnce($html, $match[0], $card);
        }
        return $html;
    }

    /** 根据属性构建 MusicParserFactory::parseLine 可识别的单行指令 */
    private static function buildMusicLine(array $attrs): ?string
    {
        $clean = static function ($value): string {
            return str_replace(['"', "'"], '', trim((string) $value));
        };

        if (isset($attrs['wy']) && $attrs['wy'] !== '') {
            return '[wy="' . $clean($attrs['wy']) . '"]';
        }
        if (isset($attrs['tx']) && $attrs['tx'] !== '') {
            return '[tx="' . $clean($attrs['tx']) . '"]';
        }
        if (isset($attrs['kg']) && $attrs['kg'] !== '') {
            return '[kg="' . $clean($attrs['kg']) . '"]';
        }

        $title = $clean($attrs['title'] ?? '');
        $url   = $clean($attrs['url'] ?? '');
        if ($title === '' || $url === '') {
            return null;
        }

        $parts = ['[title="' . $title . '"', 'url="' . $url . '"'];
        if (($attrs['artist'] ?? '') !== '') {
            $parts[] = 'artist="' . $clean($attrs['artist']) . '"';
        }
        if (($attrs['pic'] ?? '') !== '') {
            $parts[] = 'pic="' . $clean($attrs['pic']) . '"';
        }
        return implode(' ', $parts) . ']';
    }

    /** 加载并初始化音乐解析器（懒加载，仅当正文出现 [music] 时执行） */
    private static function ensureMusicParser(): void
    {
        if (self::$musicParserReady) {
            return;
        }
        if (!function_exists('curl_init')) {
            return;
        }
        require_once __DIR__ . '/MusicParser.php';
        if (!class_exists('MusicParserFactory', false)) {
            return;
        }
        MusicParserFactory::init(new FileCache(), new HttpClient());
        self::$musicParserReady = true;
    }

    /** 构建音乐卡片 */
    private static function buildMusicCard(array $item): string
    {
        $title  = htmlspecialchars((string) ($item['name'] ?? '未知歌曲'), ENT_QUOTES, 'UTF-8');
        $artist = htmlspecialchars((string) ($item['artist'] ?? '未知歌手'), ENT_QUOTES, 'UTF-8');
        $audio  = htmlspecialchars(self::resolveMusicUrl((string) ($item['url'] ?? '')), ENT_QUOTES, 'UTF-8');
        $pic    = htmlspecialchars(self::resolveMusicUrl((string) ($item['pic'] ?? '')), ENT_QUOTES, 'UTF-8');
        if ($pic === '') {
            $pic = htmlspecialchars(self::musicFallbackPic(), ENT_QUOTES, 'UTF-8');
        }

        return '<div class="music-card" data-music-player data-audio="' . $audio . '">'
            . '<div class="music-card-cover">'
            . '<img class="no-fancybox" src="' . $pic . '" alt="' . $title . ' - ' . $artist . '" loading="lazy">'
            . '</div>'
            . '<div class="music-card-info">'
            . '<div class="music-card-head">'
            . '<div class="music-card-meta">'
            . '<p class="music-card-title">' . $title . '</p>'
            . '<p class="music-card-artist">' . $artist . '</p>'
            . '</div>'
            . '<button class="music-card-play" type="button" aria-label="播放">'
            . '<svg class="music-card-icon icon-play" aria-hidden="true" viewBox="0 0 24 24"><path d="M8 5.5V18.5L19 12L8 5.5ZM6 3.5V20.5L21 12L6 3.5Z"></path></svg>'
            . '<svg class="music-card-icon icon-pause" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 4H10V20H6V4ZM14 4H18V20H14V4Z"></path></svg>'
            . '</button>'
            . '</div>'
            . '<div class="music-card-progress">'
            . '<span class="music-card-time music-card-current">00:00</span>'
            . '<div class="music-card-track" role="slider" aria-label="播放进度" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">'
            . '<div class="music-card-fill"></div>'
            . '<div class="music-card-thumb" aria-hidden="true"></div>'
            . '</div>'
            . '<span class="music-card-time music-card-duration">0:00</span>'
            . '</div>'
            . '</div>'
            . '<audio preload="metadata" crossorigin="anonymous"></audio>'
            . '</div>';
    }

    /** 手动音频相对路径补全为站点绝对地址 */
    private static function resolveMusicUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^(?:[a-z][a-z0-9+.\-]*:)?//#i', $url)) {
            return $url;
        }
        $siteUrl = self::musicSiteUrl();
        return $siteUrl !== '' ? rtrim($siteUrl, '/') . '/' . ltrim($url, '/') : $url;
    }

    /** 站点地址（懒获取并缓存） */
    private static function musicSiteUrl(): string
    {
        if (!array_key_exists('site', self::$musicUrlCache)) {
            $site = '';
            try {
                $options = \Typecho\Widget::widget('Widget_Options');
                $site = (string) ($options->siteUrl ?? '');
            } catch (\Throwable $e) {
                $site = '';
            }
            self::$musicUrlCache['site'] = $site;
        }
        return self::$musicUrlCache['site'];
    }

    /** 封面缺省图（主题 noscreen.png） */
    private static function musicFallbackPic(): string
    {
        if (!array_key_exists('theme', self::$musicUrlCache)) {
            $theme = '';
            try {
                $options = \Typecho\Widget::widget('Widget_Options');
                $theme = (string) ($options->themeUrl ?? '');
            } catch (\Throwable $e) {
                $theme = '';
            }
            self::$musicUrlCache['theme'] = $theme;
        }
        $theme = self::$musicUrlCache['theme'];
        return $theme !== '' ? rtrim($theme, '/') . '/assets/images/noscreen.png' : '';
    }

    /** [post] 文章引用卡片 */
    private static function parsePostCard(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[post\s+cid=["\']?(\d+)["\']?\s*\](</p>)?#i', function ($matches) {
            return self::buildContentCard((int) $matches[2], 'post');
        }, $html);
    }

    /** [page] 页面引用卡片 */
    private static function parsePageCard(string $html): string
    {
        return preg_replace_callback('#(<p>)?\[page\s+cid=["\']?(\d+)["\']?\s*\](</p>)?#i', function ($matches) {
            return self::buildContentCard((int) $matches[2], 'page');
        }, $html);
    }

    /** 构建文章/页面引用卡片 */
    private static function buildContentCard(int $cid, string $type): string
    {
        $cacheKey = $type . ':' . $cid;
        if (isset(self::$contentCache[$cacheKey])) return self::buildQuoteCardHtml(self::$contentCache[$cacheKey]);
        try {
            $widget = \Helper::widgetById('Contents', $cid);
            if (!$widget || empty($widget->title)) throw new \Exception('Content not found');
            if ($widget->type !== $type) throw new \Exception('Type mismatch');
            $thumb = ThumbnailHelper::showThumbnail($widget, true) ?? '';
            $time = date('Y-m-d', (int) $widget->created);
            $excerpt = XPro::excerpt((string) $widget->content, 120);
            $data = ['title' => (string) $widget->title, 'url' => (string) $widget->permalink, 'thumb' => $thumb, 'type' => $type, 'excerpt' => $excerpt, 'time' => $time];
            self::$contentCache[$cacheKey] = $data;
            return self::buildQuoteCardHtml($data);
        } catch (\Throwable $e) {
            return '<div class="quote-card" style="border-color:var(--error);"><div class="quote-cover"><svg viewBox="0 0 24 24" aria-hidden="true">' . self::CONTENT_PLACEHOLDER_ICON . '</svg></div><div class="quote-body"><p class="quote-date" style="color:var(--error);">' . "\u{26A0}\u{FE0F}" . ' 引用失败</p><a class="quote-title" href="#">' . ($type === 'post' ? '文章' : '页面') . '不存在或已被删除</a><p class="quote-desc">CID: ' . $cid . '</p></div></div>';
        }
    }

    /** 引用卡片 HTML */
    private static function buildQuoteCardHtml(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($data['url'] ?? '#', ENT_QUOTES, 'UTF-8');
        $thumb = htmlspecialchars($data['thumb'] ?? '', ENT_QUOTES, 'UTF-8');
        $time = htmlspecialchars($data['time'] ?? '', ENT_QUOTES, 'UTF-8');
        $excerpt = htmlspecialchars($data['excerpt'] ?? '', ENT_QUOTES, 'UTF-8');
        $type = $data['type'] ?? 'post'; $dateText = $type === 'page' ? '独立页面' : $time;
        $cover = $thumb !== '' ? '<img class="no-fancybox" src="' . $thumb . '" alt="' . $title . '" loading="lazy">' : '<svg viewBox="0 0 24 24" aria-hidden="true">' . self::CONTENT_PLACEHOLDER_ICON . '</svg>';
        return '<div class="quote-card"><div class="quote-cover">' . $cover . '</div><div class="quote-body"><p class="quote-date">' . $dateText . '</p><a class="quote-title" href="' . $url . '">' . $title . '</a><p class="quote-desc">' . $excerpt . '</p></div></div>';
    }

    /** 单次字符串替换 */
    private static function replaceOnce(string $html, string $search, string $replace): string
    { $pos = strpos($html, $search); if ($pos === false) return $html; return substr_replace($html, $replace, $pos, strlen($search)); }

    /** 解析短代码属性 */
    private static function parseAttributes(string $attrStr): array
    {
        $attrs = [];
        if (preg_match_all('/(\w+)\s*=\s*(["\'])(.*?)\2/s', $attrStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) $attrs[$m[1]] = $m[3];
        }
        $cleaned = preg_replace('/\w+\s*=\s*["\'][^"\']*["\']/s', '', $attrStr);
        if ($cleaned !== null && $cleaned !== '') {
            if (preg_match_all('/(\w+)/s', $cleaned, $flagMatches)) {
                foreach ($flagMatches[1] as $flag) { if (!isset($attrs[$flag])) $attrs[$flag] = ''; }
            }
        }
        return $attrs;
    }

    /** 去除首尾 <br> */
    private static function trimBr(string $content): string
    { return preg_replace('#^(\s*<br\s*/?>\s*)+|(\s*<br\s*/?>\s*)+$#i', '', trim($content)); }

    /** 为正文图片包裹 <a data-fancybox> */
    private static function wrapImages($html)
    {
        if (empty($html) || stripos($html, '<img') === false) return $html;
        $protected = [];
        $html = preg_replace_callback('#<div class="gallery\b[^>]*>.*?</div>\s*#is', function ($m) use (&$protected) {
            $id = count($protected); $protected[$id] = $m[0]; return '<!--GALLERY_PROTECTED_' . $id . '-->';
        }, $html);
        $html = preg_replace_callback('#<p\b([^>]*)>(.*?)</p>#is', function ($matches) {
            $inner = $matches[2]; if (stripos($inner, '<img') !== false) return $inner; return $matches[0];
        }, $html);
        $html = preg_replace_callback('#<a\b[^>]*>.*?</a>|<img\b[^>]*>#is', function ($matches) {
            $tag = $matches[0]; if (stripos($tag, '<img') !== 0) return $tag;
            if (preg_match('/\bclass=["\'][^"\']*no-fancybox/i', $tag)) return $tag;
            if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $srcMatch)) return $tag;
            $src = $srcMatch[1]; $alt = '';
            if (preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $altMatch)) $alt = $altMatch[1];
            if ($alt === '' && preg_match('/\btitle=["\']([^"\']*)["\']/i', $tag, $titleMatch)) $alt = $titleMatch[1];
            $href = htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); $caption = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
            return '<a href="' . $href . '" data-fancybox="post-gallery" data-type="image" data-caption="' . $caption . '"><figure>' . $tag . '<figcaption>' . $caption . '</figcaption></figure></a>';
        }, $html);
        foreach ($protected as $id => $block) $html = str_replace('<!--GALLERY_PROTECTED_' . $id . '-->', $block, $html);
        return $html;
    }

    /** 为 h1-h6 添加 id（短代码卡片内部的标题除外） */
    private static function addHeadingIds($html)
    {
        $protected = [];
        /* 保护 B站卡片：卡片内的 h4 标题不应参与标题锚点 */
        $html = preg_replace_callback('#<a class="bili-card"[^>]*>.*?</a>#is', function ($m) use (&$protected) {
            $id = count($protected);
            $protected[$id] = $m[0];
            return '<!--XPRO_PROTECT_' . $id . '-->';
        }, $html);
        /* 保护时间线卡片：事件标题 h4 不应参与标题锚点 */
        $html = preg_replace_callback('#<ol class="timeline">.*?</ol>#is', function ($m) use (&$protected) {
            $id = count($protected);
            $protected[$id] = $m[0];
            return '<!--XPRO_PROTECT_' . $id . '-->';
        }, $html);
        $html = preg_replace_callback('/<h([1-6])([^>]*)>(.*?)<\/h\1>/is', function ($matches) {
            $level = $matches[1]; $attrs = $matches[2]; $inner = $matches[3]; $text = strip_tags($inner);
            $id = empty($text) ? 'heading-' . uniqid() : self::slugify($text);
            if (preg_match('/\bid\s*=\s*(["\'])/i', $attrs)) return "<h{$level}{$attrs}>{$inner}</h{$level}>";
            return "<h{$level} id=\"{$id}\"{$attrs}>{$inner}</h{$level}>";
        }, $html);
        foreach ($protected as $id => $block) {
            $html = str_replace('<!--XPRO_PROTECT_' . $id . '-->', $block, $html);
        }
        return $html;
    }

    private static function slugify($text)
    { if (empty($text)) return 'heading-' . uniqid(); return Typecho_Common::slugName($text); }
}
