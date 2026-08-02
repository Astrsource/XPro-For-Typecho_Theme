<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 页头模板
 *
 * @package XPro
 */

$themeUrl   = rtrim((string) $this->options->themeUrl, '/');
$thumbCfg   = ThumbnailHelper::getCardImageConfig($this);
$meta_image = $thumbCfg['displayImages'][0] ?? $themeUrl.'/assets/images/favicon180.ico';
$favicon    = !empty($this->options->favicon) ? $this->options->favicon : $themeUrl.'/assets/images/favicon180.ico';
$seo        = new SeoHelper($this);
?>
<!DOCTYPE HTML>
<html lang="zh-CN" data-theme="light" theme-color="<?php $this->options->themecolor(); ?>" data-layout="<?php $this->options->themelayout(); ?>">
<head>
    <!-- ==================== 基本信息 ==================== -->
    <meta charset="<?= $this->options->charset(); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="renderer" content="webkit">
    <meta name="applicable-device" content="pc,mobile">
    <meta name="theme-color" content="<?php $this->options->themecolor(); ?>">

    <!-- ==================== SEO 核心 ==================== -->
    <?php $seo->robots(); ?>

    <!-- ==================== 关键词 / 描述 / 作者 ==================== -->
    <meta name="keywords" content="<?php $seo->keywords(); ?>">
    <meta name="description" content="<?php $seo->description(); ?>">
    <?php if ($this->author->have()) { ?>
    <meta name="author" content="<?php $this->author(); ?>">
    <meta name="copyright" content="© <?php XPro::esc(XPro::formatYear()); ?> <?php $this->options->title(); ?>">
    <link rel="author" href="<?php $this->author->permalink(); ?>">
    <link rel="publisher" href="<?php $this->author->permalink(); ?>">
    <link rel="me" href="<?php $this->author->permalink(); ?>">
    <?php } ?>

    <!-- ==================== 文章页专属 ==================== -->
    <?php $seo->articleMeta(); ?>

    <!-- ==================== Open Graph ==================== -->
    <?php $seo->og($meta_image, $favicon); ?>

    <!-- ==================== Twitter Card ==================== -->
    <?php $seo->twitter($meta_image, $favicon); ?>
    
    <!-- ==================== RSS XML ==================== -->
    <link rel="alternate" type="application/rss+xml" title="<?php $this->options->title(); ?> » RSS 2.0" href="<?php $this->options->rootUrl(); ?>/feed/">
    <link rel="alternate" type="application/rdf+xml" title="<?php $this->options->title(); ?> » RSS 1.0" href="<?php $this->options->rootUrl(); ?>/feed/rss/">
    <link rel="alternate" type="application/atom+xml" title="<?php $this->options->title(); ?> » ATOM 1.0" href="<?php $this->options->rootUrl(); ?>/feed/atom/">

    <!-- ==================== Favicon ==================== -->
    <link rel="icon" sizes="32x32" href="<?php $this->options->themeUrl('assets/images/favicon.ico'); ?>" type="image/x-icon">
    <link rel="icon" sizes="16x16" href="<?php $this->options->themeUrl('assets/images/favicon16.ico'); ?>" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php $this->options->themeUrl('assets/images/favicon180.ico'); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php $this->options->themeUrl('assets/images/favicon.ico'); ?>" type="image/x-icon">

    <!-- ==================== 标题 ==================== -->
    <?php $seo->title(); ?>

    <!-- ==================== 主题切换：防止闪烁 ==================== -->
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-theme', t);
                return;
            }
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <!-- ==================== 静态资源 ==================== -->
    <link href="<?php $this->options->themeUrl('assets/css/fancybox.css'); ?>" rel="stylesheet">
    <link href="<?php $this->options->themeUrl('assets/css/style.css'); ?>" rel="stylesheet">

    <!-- ==================== head 函数 ==================== -->
    <?php $this->head(); ?>
</head>
<body>
<!-- ==================== 无障碍跳转链接 ==================== -->
<a href="#main-content" class="skip-link">跳转到主要内容</a>
<!-- ==================== 加载进度条 ==================== -->
<div class="loading-progress" id="loadingProgress"></div>
<!-- ==================== 导航菜单遮罩层 ==================== -->
<div id="overlay" class="overlay"></div>
<!-- ==================== 移动端顶部栏 ==================== -->
<header class="site-header">
    <div class="brand-inline">
        <a href="<?php $this->options->rootUrl(); ?>" title="<?php $this->options->title(); ?>">
            <img src="<?php $this->options->logoUrl(); ?>" alt="<?php $this->options->title(); ?>" class="light-logo">
            <img src="<?php $this->options->logoDarkUrl(); ?>" alt="<?php $this->options->title(); ?>" class="dark-logo">
        </a>
    </div>
    <div class="header-actions">
        <button id="search-toggle-mobile" class="icon-btn" aria-label="打开搜索">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                <path d="M18.031 16.6168L22.3137 20.8995L20.8995 22.3137L16.6168 18.031C15.0769 19.263 13.124 20 11 20C6.032 20 2 15.968 2 11C2 6.032 6.032 2 11 2C15.968 2 20 6.032 20 11C20 13.124 19.263 15.0769 18.031 16.6168ZM16.0247 15.8748C17.2475 14.6146 18 12.8956 18 11C18 7.1325 14.8675 4 11 4C7.1325 4 4 7.1325 4 11C4 14.8675 7.1325 18 11 18C12.8956 18 14.6146 17.2475 15.8748 16.0247L16.0247 15.8748Z"></path>
            </svg>
        </button>
        <button id="sidepanel-toggle" class="icon-btn sidepanel-toggle" aria-label="打开侧边栏">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                <path d="M7 11.5C4.51472 11.5 2.5 9.48528 2.5 7C2.5 4.51472 4.51472 2.5 7 2.5C9.48528 2.5 11.5 4.51472 11.5 7C11.5 9.48528 9.48528 11.5 7 11.5ZM7 21.5C4.51472 21.5 2.5 19.4853 2.5 17C2.5 14.5147 4.51472 12.5 7 12.5C9.48528 12.5 11.5 14.5147 11.5 17C11.5 19.4853 9.48528 21.5 7 21.5ZM17 11.5C14.5147 11.5 12.5 9.48528 12.5 7C12.5 4.51472 14.5147 2.5 17 2.5C19.4853 2.5 21.5 4.51472 21.5 7C21.5 9.48528 19.4853 11.5 17 11.5ZM17 21.5C14.5147 21.5 12.5 19.4853 12.5 17C12.5 14.5147 14.5147 12.5 17 12.5C19.4853 12.5 21.5 14.5147 21.5 17C21.5 19.4853 19.4853 21.5 17 21.5ZM7 9.5C8.38071 9.5 9.5 8.38071 9.5 7C9.5 5.61929 8.38071 4.5 7 4.5C5.61929 4.5 4.5 5.61929 4.5 7C4.5 8.38071 5.61929 9.5 7 9.5ZM7 19.5C8.38071 19.5 9.5 18.3807 9.5 17C9.5 15.6193 8.38071 14.5 7 14.5C5.61929 14.5 4.5 15.6193 4.5 17C4.5 18.3807 5.61929 19.5 7 19.5ZM17 9.5C18.3807 9.5 19.5 8.38071 19.5 7C19.5 5.61929 18.3807 4.5 17 4.5C15.6193 4.5 14.5 5.61929 14.5 7C14.5 8.38071 15.6193 9.5 17 9.5ZM17 19.5C18.3807 19.5 19.5 18.3807 19.5 17C19.5 15.6193 18.3807 14.5 17 14.5C15.6193 14.5 14.5 15.6193 14.5 17C14.5 18.3807 15.6193 19.5 17 19.5Z"></path>
            </svg>
        </button>
        <button id="menu-toggle" class="icon-btn" aria-label="打开导航菜单">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                <path d="M16 18V20H5V18H16ZM21 11V13H3V11H21ZM19 4V6H8V4H19Z"></path>
            </svg>
        </button>
    </div>
</header>
<!-- ==================== 搜索弹窗 ==================== -->
<?php $this->need('includes/search.php'); ?>
<!-- ==================== 页面布局 ==================== -->
<div class="page-layout">
<!-- ==================== 侧边菜单栏 ==================== -->
<?php $this->need('includes/sidebar.php'); ?>
