<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 搜索弹窗组件
 *
 * @package XPro
 */
?>
  <div id="search-overlay" class="search-overlay" role="dialog" aria-modal="true" aria-label="搜索">
    <div class="search-overlay-header">
      <form id="search" class="search-field" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
          <path d="M18.031 16.6168L22.3137 20.8995L20.8995 22.3137L16.6168 18.031C15.0769 19.263 13.124 20 11 20C6.032 20 2 15.968 2 11C2 6.032 6.032 2 11 2C15.968 2 20 6.032 20 11C20 13.124 19.263 15.0769 18.031 16.6168ZM16.0247 15.8748C17.2475 14.6146 18 12.8956 18 11C18 7.1325 14.8675 4 11 4C7.1325 4 4 7.1325 4 11C4 14.8675 7.1325 18 11 18C12.8956 18 14.6146 17.2475 15.8748 16.0247L16.0247 15.8748Z"></path>
        </svg>
        <input type="search" id="s" name="s" placeholder="输入搜索关键词，按 Enter 键搜索" class="search-input" aria-label="输入关键词搜索文章">
      </form>
      <button id="search-cancel" class="search-cancel">关闭</button>
    </div>
    <div class="search-overlay-body">
      <p class="search-overlay-title">热门搜索</p>
      <div class="search-chips">
        <?php HotSearch::render(10, [
            'wrapper' => '{items}',
            'item'    => '<span class="chip" role="button">{keyword}</span>',
            'empty'   => '<span>暂无热门搜索</span>',
        ]); ?>
      </div>
      <p class="search-overlay-title">最近搜索</p>
      <div class="search-history" aria-live="polite"></div>
    </div>
  </div>