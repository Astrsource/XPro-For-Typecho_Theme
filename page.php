<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 独立页面模板
 *
 * @package XPro
 */

$this->need('header.php');
?>
<!-- ==================== 中间主内容 ==================== -->
<main id="main-content" class="main-content">
    <!-- 面包屑导航 -->
    <nav class="post-breadcrumb" aria-label="面包屑导航">
        <a href="<?php $this->options->siteUrl(); ?>">首页</a>
        <span class="post-breadcrumb-current"><?php XPro::esc($this->title); ?></span>
    </nav>
    <article class="post-article" aria-label="<?php XPro::esc($this->title); ?>">
        <!-- 独立页面头部 -->
        <header class="post-header page-header">
            <h1 class="post-title"><?php XPro::esc($this->title); ?></h1>
        </header>
        <!-- 独立页面内容 -->
        <div class="post-content page-content">
            <?php $this->content(); ?>
        </div>
        <?php if ($this->allow('comment')) { $this->need('includes/comments.php'); } ?>
    </article>
</main>
<!-- ==================== 右侧边栏 ==================== -->
<?php $this->need('includes/aside.php'); ?>
<!-- ==================== 页脚 ==================== -->
<?php $this->need('footer.php'); ?>
