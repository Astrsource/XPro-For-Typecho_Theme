<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * XPro Theme For Typecho
 *
 * @package XPro
 * @author Astrsource
 * @version 1.0
 * @link https://astrsource.com
 */

$this->need('header.php');
?>
<!-- ==================== 中间主内容 ==================== -->
<main id="main-content" class="main-content">
<?php
$noticeText = $this->options->notice;
if (/*$this->is('index') && $this->getCurrentPage() == 1 &&*/ $noticeText) {
    $noticeId  = md5($noticeText);
    $cookieKey = 'chirp_notice_closed_id';
    $isClosed  = isset($_COOKIE[$cookieKey]) && $_COOKIE[$cookieKey] === $noticeId;
    if (!$isClosed) {
?>
    <!-- ==================== 站点公告 ==================== -->
    <section class="notice" data-notice-id="<?= $noticeId; ?>" aria-label="站点公告">
        <div class="notice-icon" aria-hidden="true">
            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                <path d="M6.60282 10.0001L10 7.22056V16.7796L6.60282 14.0001H3V10.0001H6.60282ZM2 16.0001H5.88889L11.1834 20.3319C11.2727 20.405 11.3846 20.4449 11.5 20.4449C11.7761 20.4449 12 20.2211 12 19.9449V4.05519C12 3.93977 11.9601 3.8279 11.887 3.73857C11.7121 3.52485 11.3971 3.49335 11.1834 3.66821L5.88889 8.00007H2C1.44772 8.00007 1 8.44778 1 9.00007V15.0001C1 15.5524 1.44772 16.0001 2 16.0001ZM23 12C23 15.292 21.5539 18.2463 19.2622 20.2622L17.8445 18.8444C19.7758 17.1937 21 14.7398 21 12C21 9.26016 19.7758 6.80629 17.8445 5.15557L19.2622 3.73779C21.5539 5.75368 23 8.70795 23 12ZM18 12C18 10.0883 17.106 8.38548 15.7133 7.28673L14.2842 8.71584C15.3213 9.43855 16 10.64 16 12C16 13.36 15.3213 14.5614 14.2842 15.2841L15.7133 16.7132C17.106 15.6145 18 13.9116 18 12Z"></path>
            </svg>
        </div>
        <div class="notice-body">
            <p class="notice-title">公告</p>
            <p class="notice-text"><?php $this->options->notice(); ?></p>
        </div>
        <button class="notice-close" aria-label="关闭公告">
            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path>
            </svg>
        </button>
    </section>
<?php
    }
}
?>
<?php
$carouselItems = XPro::parseCarousel();
if (!empty($carouselItems)) {
    $carouselCount = count($carouselItems);
?>
<!-- ==================== 首页轮播 ==================== -->
<section class="carousel" aria-label="精选轮播">
    <div class="carousel-viewport">
        <?php foreach ($carouselItems as $idx => $item) {
            $isActive  = $idx === 0 ? ' active' : '';
            $slideAttr = $idx === 0 ? ' fetchpriority="high"' : ' loading="lazy"';
            $noscreen  = rtrim((string) $this->options->themeUrl, '/')  .'/assets/images/noscreen.png';
            $pic       = $item['pic'] !== '' ? $item['pic'] : $noscreen;
            $url       = $item['url'] !== '' ? $item['url'] : '#';
            $badge     = $item['badge'] ?? '';
        ?>
        <div class="carousel-slide<?= $isActive; ?>">
            <img src="<?php XPro::esc($pic); ?>" alt="轮播图：<?php XPro::esc($item['title']); ?>"<?= $slideAttr; ?>>
            <div class="carousel-caption">
                <?php if ($badge !== '') { ?>
                <span class="carousel-tag"><?php XPro::esc($badge); ?></span>
                <?php } ?>
                <a href="<?php XPro::esc($url); ?>" class="carousel-title"><?php XPro::esc($item['title']); ?></a>
                <?php if (!empty($item['excerpt'])) { ?>
                <p class="carousel-description"><?php XPro::esc($item['excerpt']); ?></p>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
    <button class="carousel-arrow prev" aria-label="上一张">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
            <path d="M10.8284 12.0007L15.7782 16.9504L14.364 18.3646L8 12.0007L14.364 5.63672L15.7782 7.05093L10.8284 12.0007Z"></path>
        </svg>
    </button>
    <button class="carousel-arrow next" aria-label="下一张">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
            <path d="M13.1717 12.0007L8.22192 7.05093L9.63614 5.63672L16.0001 12.0007L9.63614 18.3646L8.22192 16.9504L13.1717 12.0007Z"></path>
        </svg>
    </button>
    <div class="carousel-dots" role="tablist" aria-label="轮播指示器">
        <?php for ($i = 0; $i < $carouselCount; $i++) {
            $dotActive = $i === 0 ? ' active' : '';
            $selected  = $i === 0 ? 'true' : 'false';
        ?>
        <button class="carousel-dot<?= $dotActive; ?>" role="tab" aria-label="第<?= $i + 1; ?>张" aria-selected="<?= $selected; ?>"></button>
        <?php } ?>
    </div>
</section>
<?php } ?>
<!-- ==================== 文章列表 ==================== -->
<?php $this->need('includes/article.php'); ?>
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
<!-- footer -->
<?php $this->need('footer.php'); ?>