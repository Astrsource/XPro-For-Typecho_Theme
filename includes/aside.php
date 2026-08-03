<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 边栏小部件组件
 *
 * @package XPro
 */

use Typecho\Widget;

$themeUrl = rtrim((string) $this->options->themeUrl, '/');
$noscreen = $themeUrl . '/assets/images/noscreen.png';

$recentComments = Widget::widget('Widget\Comments\Recent');

$hotPosts = Widget::widget('Widget\Post\Hot', 'pageSize=5');

$tags = Widget::widget('Widget\Metas\Tag\Cloud', 'sort=count&desc=1&limit=15');

$formatHeat = static function (int $num): string {
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return (string) $num;
};

$isPostPage = $this->is('post');
$relatedPosts = null;
$tocItems     = [];

if ($isPostPage) {
    /* 相关文章：Typecho 官方按标签关联（文章无标签时无结果，回退热门文章） */
    $this->related(6)->to($relatedPosts);

    /* 文章目录：解析已渲染正文中的 h1-h6 */
    if (!$this->hidden) {
        $contentHtml = (string) $this->content;
        /* 排除短代码卡片内部的标题（B站卡片、时间线卡片） */
        $contentHtml = preg_replace('#<a class="bili-card"[^>]*>.*?</a>#is', '', $contentHtml) ?? $contentHtml;
        $contentHtml = preg_replace('#<ol class="timeline">.*?</ol>#is', '', $contentHtml) ?? $contentHtml;
        if (preg_match_all('/<h([1-6])([^>]*)>(.*?)<\/h\1>/is', $contentHtml, $headingMatches, PREG_SET_ORDER)) {
            foreach ($headingMatches as $heading) {
                $level = (int) $heading[1];
                $text  = trim(strip_tags($heading[3]));
                if ($text === '') {
                    continue;
                }
                if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $heading[2], $idMatch)) {
                    $id = $idMatch[1];
                } else {
                    $id = \Typecho\Common::slugName($text);
                }
                if ($id === '') {
                    continue;
                }
                $tocItems[] = ['level' => $level, 'id' => $id, 'text' => $text];
            }
        }
    }
}

/* 边栏文章卡片渲染（热门文章 / 相关文章共用） */
$renderBasePost = static function ($post, int $index, string $heatLabel = '热度', ?int $heatValue = null) use ($noscreen, $formatHeat): void {
    $cid   = (int) $post->cid;
    $title = (string) $post->title;
    $views = $heatValue ?? (int) ($post->views ?? 0);
    $cat   = XPro::getPostCategory($cid, $post);
    $cfg   = ThumbnailHelper::getCardImageConfig($post);
    $pic   = $cfg['displayImages'][0] ?? $noscreen;
    ?>
    <a href="<?php XPro::esc($post->permalink); ?>" class="base-post">
        <div class="base-post-bg" style="background-image:url(<?php XPro::esc($pic); ?>)" aria-hidden="true"></div>
        <div class="base-post-main">
            <div class="base-post-top">
                <span class="hots-post-num"><?= $index; ?></span>
                <span class="hots-post-dot">·</span>
                <span class="hots-post-cat"><?php XPro::esc($cat['name'] ?? '未分类'); ?></span>
            </div>
            <p class="base-post-title"><?php XPro::esc($title); ?></p>
        </div>
        <div class="hots-post-heat">
            <span class="hots-post-heat-label"><?= $heatLabel; ?></span>
            <span class="hots-post-heat-num"><?= $formatHeat($views); ?></span>
        </div>
    </a>
    <?php
};
?>
<aside class="side-panel" role="complementary" aria-label="侧边栏">
    <div class="side-panel-header mobile-only">
        <span class="mobile-nav-label">侧边栏</span>
        <button id="sidepanel-close" class="icon-btn" aria-label="关闭侧边栏">
            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path>
            </svg>
        </button>
    </div>

    <div class="search-field search-field-block">
        <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
            <path d="M18.031 16.6168L22.3137 20.8995L20.8995 22.3137L16.6168 18.031C15.0769 19.263 13.124 20 11 20C6.032 20 2 15.968 2 11C2 6.032 6.032 2 11 2C15.968 2 20 6.032 20 11C20 13.124 19.263 15.0769 18.031 16.6168ZM16.0247 15.8748C17.2475 14.6146 18 12.8956 18 11C18 7.1325 14.8675 4 11 4C7.1325 4 4 7.1325 4 11C4 14.8675 7.1325 18 11 18C12.8956 18 14.6146 17.2475 15.8748 16.0247L16.0247 15.8748Z"></path>
        </svg>
        <input type="search" placeholder="搜索文章..." class="search-input" aria-label="搜索文章">
    </div>

    <!-- 文章目录（仅文章页） -->
    <?php if (!empty($tocItems)) { ?>
    <section class="side-section toc" aria-label="文章目录">
        <div class="side-section-header">
            <h2 class="side-section-title">文章目录</h2>
        </div>
        <div class="side-section-body">
            <nav class="toc-nav" aria-label="目录导航">
                <?php foreach ($tocItems as $tocItem) { ?>
                <a href="#<?php XPro::esc($tocItem['id']); ?>" class="toc-link toc-h<?= $tocItem['level']; ?>"><?php XPro::esc($tocItem['text']); ?></a>
                <?php } ?>
            </nav>
        </div>
    </section>
    <?php } ?>

    <!-- 最近评论 -->
    <section class="side-section" aria-label="最近评论">
        <div class="side-section-header">
            <h2 class="side-section-title">最近评论</h2>
        </div>
        <div class="side-section-body">
            <?php if ($recentComments->have()) { ?>
                <?php while ($recentComments->next()) { ?>
                    <?php
                    $commentAuthor = (string) $recentComments->author;
                    $commentMail   = (string) $recentComments->mail;
                    $commentTime   = (int) $recentComments->created;
                    $commentText   = XPro::excerpt((string) $recentComments->content, 80);
                    $commentLink   = (string) $recentComments->permalink;
                    /* quote 链接截断评论分页段（/comment-page-N）并去掉锚点，点击仅进入文章 */
                    $commentQuoteLink = preg_replace('#/comment-page-\d+#i', '', $commentLink) ?? $commentLink;
                    $commentQuoteLink = preg_replace('/#comment-\d+$/i', '', $commentQuoteLink) ?? $commentQuoteLink;
                    $commentTitle  = (string) $recentComments->title;
                    $isAuthor      = $recentComments->authorId > 0 && $recentComments->authorId === $recentComments->ownerId;
                    ?>
                    <article class="comment-item" data-href="<?= XPro::esc($commentLink, true) ?>" aria-label="<?php XPro::esc($commentAuthor . '的评论'); ?>">
                        <img src="<?php XPro::avatar($commentMail, 100, false, (int) $recentComments->authorId); ?>" alt="<?php XPro::esc($commentAuthor . '的头像'); ?>" class="avatar" loading="lazy">
                        <div class="comment-item-body">
                            <div class="comment-item-meta">
                                <a href="<?php $recentComments->url(); ?>" class="comment-item-author"><?php XPro::esc($commentAuthor); ?></a>
                                <?php if ($isAuthor) { ?>
                                <span class="comment-item-badge">作者</span>
                                <?php } ?>
                                <time class="comment-item-date" datetime="<?php XPro::esc(XPro::formatIsoDate($commentTime)); ?>"><?php XPro::esc(XPro::relativeTime($commentTime)); ?></time>
                            </div>
                            <p class="comment-item-text"><?php XPro::esc($commentText); ?></p>
                            <a class="comment-item-quote" href="<?php XPro::esc($commentQuoteLink); ?>"><?php XPro::esc($commentTitle); ?></a>
                        </div>
                    </article>
                <?php } ?>
            <?php } else { ?>
                <p class="comment-item-text">暂无评论</p>
            <?php } ?>
        </div>
    </section>

    <!-- 相关文章 / 热门文章 -->
    <?php if ($isPostPage && $relatedPosts !== null && $relatedPosts->have()) { ?>
    <section class="side-section" aria-label="相关文章">
        <div class="side-section-header">
            <h2 class="side-section-title">相关文章</h2>
        </div>
        <div class="side-section-body">
            <?php $relatedIndex = 0; ?>
            <?php while ($relatedPosts->next()) { ?>
                <?php $relatedIndex++; ?>
                <?php $renderBasePost($relatedPosts, $relatedIndex, '评论', (int) $relatedPosts->commentsNum); ?>
            <?php } ?>
        </div>
    </section>
    <?php } else { ?>
    <section class="side-section" aria-label="热门文章">
        <div class="side-section-header">
            <h2 class="side-section-title">热门文章</h2>
        </div>
        <div class="side-section-body">
            <?php if ($hotPosts->have()) { ?>
                <?php $hotIndex = 0; ?>
                <?php while ($hotPosts->next()) { ?>
                    <?php $hotIndex++; ?>
                    <?php $renderBasePost($hotPosts, $hotIndex); ?>
                <?php } ?>
            <?php } else { ?>
                <p class="comment-item-text">暂无热门文章</p>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <!-- 热门标签 -->
    <section class="side-section" aria-label="热门标签">
        <div class="side-section-header">
            <h2 class="side-section-title">热门标签</h2>
        </div>
        <div class="side-section-body">
            <?php if ($tags->have()) { ?>
                <div class="tag-flow">
                    <?php while ($tags->next()) { ?>
                        <a href="<?php XPro::esc((string) $tags->permalink); ?>" class="tag-pill"><?php XPro::esc((string) $tags->name); ?></a>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="comment-item-text">暂无标签</p>
            <?php } ?>
        </div>
    </section>
</aside>
