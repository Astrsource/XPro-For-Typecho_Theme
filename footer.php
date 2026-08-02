<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 页脚模板
 *
 * @package XPro
 */

$options   = $this->options;
$siteUrl   = rtrim((string) $options->siteUrl, '/');
?>
</div><!-- .page-layout -->
<!-- ==================== 回到顶部 ==================== -->
<button id="back-to-top" class="back-to-top visible" aria-label="返回页面顶部">
    <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
        <path d="M13.0001 7.82843V20H11.0001V7.82843L5.63614 13.1924L4.22192 11.7782L12.0001 4L19.7783 11.7782L18.3641 13.1924L13.0001 7.82843Z"></path>
    </svg>
</button>
<!-- ==================== 页脚 ==================== -->
<footer id="footer" class="site-footer">
    <?php $this->options->sitefooter(); ?>
    <p>© <?= date('Y'); ?> <?php $this->options->title(); ?></p>
    <p>RSS订阅：<a href="<?php $this->options->feedUrl(); ?>" target="_blank">全站订阅</a> | <a href="<?php $this->options->commentsFeedUrl(); ?>" target="_blank">评论订阅</a></p>
    <p>使用 <a href="https://github.com/astrsource/XPro-For-Typecho_Theme" target="_blank">XPro</a> 主题</p>
    <p>由 <a href="https://typecho.org/" target="_blank">Typecho</a> 强力驱动</p>
</footer>
<!-- ==================== Snackbar 提示容器 ==================== -->
<div class="snackbar-container" aria-live="polite" aria-atomic="true"></div>
<!-- ==================== JavaScript 脚本 ==================== -->
<script>window.XPRO_LIKE_URL = "<?php $this->options->siteUrl(); ?>?action=like";window.BILI_CARD_API = "<?php $this->options->themeUrl('libs/BiliBili.php'); ?>";window.GITHUB_CARD_API = "<?php $this->options->themeUrl('libs/Github.php'); ?>";window.__searchUrlTpl = '<?= rtrim((string)($this->options->index ?? ''), '/'); ?>' + '/search/{keyword}/';</script>
<script src="<?php $this->options->themeUrl('assets/js/Swup.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('assets/js/scroll.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('assets/js/head.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('assets/js/preload.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('assets/js/scripts.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('assets/js/fancybox.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('assets/js/script.js'); ?>"></script>
<!-- ==================== footer函数 ==================== -->
<?php $this->footer(); ?>
</body>
</html>
