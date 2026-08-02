<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 归档页模板
 *
 * @package XPro
 */

$this->need('header.php');

$archiveTitle      = $this->getArchiveTitle();
$archiveDesc       = $this->getArchiveDescription();
$archiveTotal      = (int) $this->getTotal();
$currentSort       = getArchiveSort();
$allowedSorts      = getAllowedArchiveSorts();
$currentSortLabel  = $allowedSorts[$currentSort] ?? $allowedSorts['newest'];
$currentRequestUrl = $this->request->getRequestUrl();
?>
<!-- ==================== 中间主内容 ==================== -->
<main id="main-content" class="main-content archive-page">
    <!-- ==================== 面包屑导航 ==================== -->
    <nav class="post-breadcrumb" aria-label="面包屑导航">
        <a href="<?php $this->options->siteUrl(); ?>">首页</a>
        <span class="post-breadcrumb-current"><?php XPro::esc($archiveTitle); ?></span>
    </nav>
    <!-- ==================== 归档信息工具栏 ==================== -->
    <div class="archive-toolbar" data-sort="<?php XPro::esc($currentSort); ?>">
        <div class="archive-toolbar-top">
            <div class="archive-context">
                <div class="archive-context-title" id="archive-ctx-title">
                    <span><?php XPro::esc($archiveTitle); ?></span>
                    <span class="archive-ctx-badge" id="archive-ctx-badge"><?php XPro::esc($archiveTotal . ' 篇文章'); ?></span>
                </div>
            </div>
            <div class="archive-toolbar-actions">
                <div class="archive-sort">
                    <button class="archive-sort-btn" id="archive-sort-btn" type="button" aria-haspopup="listbox" aria-expanded="false">
                        <span id="archive-sort-label"><?php XPro::esc($currentSortLabel); ?></span>
                        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24"><path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"></path></svg>
                    </button>
                    <div class="archive-sort-dropdown" id="archive-sort-dropdown" role="listbox" aria-label="排序选项">
                        <?php foreach ($allowedSorts as $sortKey => $sortLabel) {
                            $sortUrl   = buildArchiveSortUrl($currentRequestUrl, $sortKey);
                            $isCurrent = $sortKey === $currentSort;
                        ?>
                        <a href="<?php XPro::esc($sortUrl); ?>" class="archive-sort-option<?= $isCurrent ? ' active' : ''; ?>" data-sort="<?php XPro::esc($sortKey); ?>" role="option"<?= $isCurrent ? ' aria-selected="true"' : ''; ?>><?php XPro::esc($sortLabel); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($archiveDesc !== null && $archiveDesc !== '' && $this->is('category')) { ?>
        <p class="archive-category-desc"><?php XPro::esc($archiveDesc); ?></p>
        <?php } ?>
    </div>
    <!-- ==================== 文章列表 ==================== -->
    <?php if ($this->have()) { ?>
        <?php $this->need('includes/article.php'); ?>
    <?php } else { ?>
        <div class="list-empty" role="status">
            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                <path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17H8v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7ZM9 20h6v2H9v-2Z"></path>
            </svg>
            <p class="list-empty-title">暂无数据</p>
            <p class="list-empty-desc">这里还没有内容，稍后再来看看吧。</p>
            <a class="list-empty-btn" href="<?= rtrim((string) $this->options->siteUrl, '/'); ?>">返回首页</a>
        </div>
    <?php } ?>
    <!-- 分页导航 -->
    <?php
    $this->pageNav(
        '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M10.8284 12.0007L15.7782 16.9504L14.364 18.3646L8 12.0007L14.364 5.63672L15.7782 7.05093L10.8284 12.0007Z"></path></svg>',
        '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M13.1717 12.0007L8.22192 7.05093L9.63614 5.63672L16.0001 12.0007L9.63614 18.3646L8.22192 16.9504L13.1717 12.0007Z"></path></svg>',
        2,
        '...',
        [
            'wrapTag'      => 'div',
            'wrapClass'    => 'pagination',
            'itemTag'      => '',
            'currentClass' => 'page-btn active',
            'prevClass'    => 'page-btn',
            'nextClass'    => 'page-btn',
            'textClass'    => 'page-btn'
        ]
    );
    ?>
</main>
<!-- ==================== 侧边栏 ==================== -->
<?php $this->need('includes/aside.php'); ?>
<!-- ==================== 页脚 ==================== -->
<?php $this->need('footer.php'); ?>
