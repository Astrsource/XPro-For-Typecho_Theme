<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 侧边栏组件
 *
 * @package XPro
 */
?>
<nav class="site-navigation" role="navigation" aria-label="主导航">
  <!-- Logo 区域 -->
  <div class="nav-header">
    <div class="nav-brand">
      <a href=" <?php $this->options->siteUrl(); ?>">
        <img src="<?php $this->options->logoUrl(); ?>" alt="<?php $this->options->title(); ?>" class="light-logo">
        <img src="<?php $this->options->logoDarkUrl(); ?>" alt="<?php $this->options->title(); ?>" class="dark-logo">
      </a>
    </div>
    <!-- 移动端关闭菜单按钮 -->
    <div class="mobile-brand">
      <span class="mobile-nav-label">导航</span>
      <button id="menu-close" class="icon-btn mobile-only" aria-label="关闭导航菜单">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
          <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path>
        </svg>
      </button>
    </div>
    <div class="nav-description"><?php $this->options->description(); ?></div>
  </div>

  <!-- 导航菜单容器 -->
  <div class="nav-wrapper" role="menubar">
    <div class="nav-menu">
      <?php $this->options->sidebarMenu ? XPro::sidebarNav() : print '请在主题设置里的「侧边栏菜单」中添加菜单。'; ?>
    </div>
  </div>

  <!-- 个人资料卡片-->
  <?php if ($this->user->hasLogin()) { ?>
  <?php
  /* 使用当前登录用户信息（空归档页无页面作者时也能正常渲染） */
  $sidebarUid       = (int) ($this->user->uid ?? 0);
  $sidebarMail      = (string) ($this->user->mail ?? '');
  $sidebarName      = (string) ($this->user->screenName ?? '');
  $sidebarAuthorUrl = \Typecho\Router::url('author', ['uid' => $sidebarUid], $this->options->index);
  ?>
  <div class="profile-card" id="profile-card" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false" aria-label="打开个人资料菜单">
    <img src="<?php XPro::avatar($sidebarMail, 100, false, $sidebarUid); ?>" alt="用户头像" class="avatar" loading="lazy">
    <div class="profile-info">
      <p class="profile-name"><?php XPro::esc($sidebarName); ?></p>
      <p class="profile-handle"><?php XPro::esc($sidebarMail); ?></p>
    </div>
    <svg class="icon profile-more" aria-hidden="true" viewbox="0 0 24 24">
      <path d="M4.5 10.5C3.675 10.5 3 11.175 3 12C3 12.825 3.675 13.5 4.5 13.5C5.325 13.5 6 12.825 6 12C6 11.175 5.325 10.5 4.5 10.5ZM19.5 10.5C18.675 10.5 18 11.175 18 12C18 12.825 18.675 13.5 19.5 13.5C20.325 13.5 21 12.825 21 12C21 11.175 20.325 10.5 19.5 10.5ZM12 10.5C11.175 10.5 10.5 11.175 10.5 12C10.5 12.825 11.175 13.5 12 13.5C12.825 13.5 13.5 12.825 13.5 12C13.5 11.175 12.825 10.5 12 10.5Z"></path>
    </svg>
    <!-- 下拉菜单：个人资料 / 登出 -->
    <div class="profile-dropdown" id="profile-dropdown" role="menu" aria-label="用户操作菜单">
      <a href="<?php XPro::esc($sidebarAuthorUrl); ?>" class="dropdown-item" role="menuitem">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
          <path d="M4 22C4 17.5817 7.58172 14 12 14C16.4183 14 20 17.5817 20 22H18C18 18.6863 15.3137 16 12 16C8.68629 16 6 18.6863 6 22H4ZM12 13C8.685 13 6 10.315 6 7C6 3.685 8.685 1 12 1C15.315 1 18 3.685 18 7C18 10.315 15.315 13 12 13ZM12 11C14.21 11 16 9.21 16 7C16 4.79 14.21 3 12 3C9.79 3 8 4.79 8 7C8 9.21 9.79 11 12 11Z"></path>
        </svg>
        <span>个人资料</span>
      </a>
      <a target="_blank" rel="noopener noreferrer" data-no-swup href="<?php $this->options->adminUrl(); ?>manage-posts.php" class="dropdown-item" role="menuitem">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
          <path d="M16.7574 2.99678L14.7574 4.99678H5V18.9968H19V9.23943L21 7.23943V19.9968C21 20.5491 20.5523 20.9968 20 20.9968H4C3.44772 20.9968 3 20.5491 3 19.9968V3.99678C3 3.4445 3.44772 2.99678 4 2.99678H16.7574ZM20.4853 2.09729L21.8995 3.5115L12.7071 12.7039L11.2954 12.7064L11.2929 11.2897L20.4853 2.09729Z"></path>
        </svg>
        <span>管理文章</span>
      </a>
      <a target="_blank" rel="noopener noreferrer" data-no-swup href="<?php $this->options->adminUrl(); ?>" class="dropdown-item" role="menuitem">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
          <path d="M7 11.5C4.51472 11.5 2.5 9.48528 2.5 7C2.5 4.51472 4.51472 2.5 7 2.5C9.48528 2.5 11.5 4.51472 11.5 7C11.5 9.48528 9.48528 11.5 7 11.5ZM7 21.5C4.51472 21.5 2.5 19.4853 2.5 17C2.5 14.5147 4.51472 12.5 7 12.5C9.48528 12.5 11.5 14.5147 11.5 17C11.5 19.4853 9.48528 21.5 7 21.5ZM17 11.5C14.5147 11.5 12.5 9.48528 12.5 7C12.5 4.51472 14.5147 2.5 17 2.5C19.4853 2.5 21.5 4.51472 21.5 7C21.5 9.48528 19.4853 11.5 17 11.5ZM17 21.5C14.5147 21.5 12.5 19.4853 12.5 17C12.5 14.5147 14.5147 12.5 17 12.5C19.4853 12.5 21.5 14.5147 21.5 17C21.5 19.4853 19.4853 21.5 17 21.5ZM7 9.5C8.38071 9.5 9.5 8.38071 9.5 7C9.5 5.61929 8.38071 4.5 7 4.5C5.61929 4.5 4.5 5.61929 4.5 7C4.5 8.38071 5.61929 9.5 7 9.5ZM7 19.5C8.38071 19.5 9.5 18.3807 9.5 17C9.5 15.6193 8.38071 14.5 7 14.5C5.61929 14.5 4.5 15.6193 4.5 17C4.5 18.3807 5.61929 19.5 7 19.5ZM17 9.5C18.3807 9.5 19.5 8.38071 19.5 7C19.5 5.61929 18.3807 4.5 17 4.5C15.6193 4.5 14.5 5.61929 14.5 7C14.5 8.38071 15.6193 9.5 17 9.5ZM17 19.5C18.3807 19.5 19.5 18.3807 19.5 17C19.5 15.6193 18.3807 14.5 17 14.5C15.6193 14.5 14.5 15.6193 14.5 17C14.5 18.3807 15.6193 19.5 17 19.5Z"></path>
          <span>进入后台</span>
      </a>
      <a href="<?php $this->options->logoutUrl(); ?>" class="dropdown-item" id="logout-btn" data-no-swup role="menuitem">
        <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
          <path d="M4 15H6V20H18V4H6V9H4V3C4 2.44772 4.44772 2 5 2H19C19.5523 2 20 2.44772 20 3V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V15ZM10 11V8L15 12L10 16V13H2V11H10Z"></path>
        </svg>
        <span>登出</span>
      </a>
    </div>
  </div>
  <?php } ?>

  <!-- 导航栏底部：主题切换 / 登录 / 搜索 -->
  <div class="nav-footer">
    <button id="theme-toggle" class="icon-btn theme-toggle" aria-label="切换主题（亮色/暗色）">
      <!-- 太阳图标（亮色模式显示） -->
      <svg id="theme-sun" class="icon" aria-hidden="true" viewbox="0 0 24 24">
        <path d="M12 18C8.68629 18 6 15.3137 6 12C6 8.68629 8.68629 6 12 6C15.3137 6 18 8.68629 18 12C18 15.3137 15.3137 18 12 18ZM12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16ZM11 1H13V4H11V1ZM11 20H13V23H11V20ZM3.51472 4.92893L4.92893 3.51472L7.05025 5.63604L5.63604 7.05025L3.51472 4.92893ZM16.9497 18.364L18.364 16.9497L20.4853 19.0711L19.0711 20.4853L16.9497 18.364ZM19.0711 3.51472L20.4853 4.92893L18.364 7.05025L16.9497 5.63604L19.0711 3.51472ZM5.63604 16.9497L7.05025 18.364L4.92893 20.4853L3.51472 19.0711L5.63604 16.9497ZM23 11V13H20V11H23ZM4 11V13H1V11H4Z"></path>
      </svg>
      <!-- 月亮图标（暗色模式显示） -->
      <svg id="theme-moon" class="icon hidden" aria-hidden="true" viewbox="0 0 24 24">
        <path d="M10 6C10 10.4183 13.5817 14 18 14C19.4386 14 20.7885 13.6203 21.9549 12.9556C21.4738 18.0302 17.2005 22 12 22C6.47715 22 2 17.5228 2 12C2 6.79948 5.9698 2.52616 11.0444 2.04507C10.3797 3.21152 10 4.56142 10 6ZM4 12C4 16.4183 7.58172 20 12 20C14.9654 20 17.5757 18.3788 18.9571 15.9546C18.6407 15.9848 18.3214 16 18 16C12.4772 16 8 11.5228 8 6C8 5.67863 8.01524 5.35933 8.04536 5.04293C5.62119 6.42426 4 9.03458 4 12ZM18.1642 2.29104L19 2.5V3.5L18.1642 3.70896C17.4476 3.8881 16.8881 4.4476 16.709 5.16417L16.5 6H15.5L15.291 5.16417C15.1119 4.4476 14.5524 3.8881 13.8358 3.70896L13 3.5V2.5L13.8358 2.29104C14.5524 2.1119 15.1119 1.5524 15.291 0.835829L15.5 0H16.5L16.709 0.835829C16.8881 1.5524 17.4476 2.1119 18.1642 2.29104ZM23.1642 7.29104L24 7.5V8.5L23.1642 8.70896C22.4476 8.8881 21.8881 9.4476 21.709 10.1642L21.5 11H20.5L20.291 10.1642C20.1119 9.4476 19.5524 8.8881 18.8358 8.70896L18 8.5V7.5L18.8358 7.29104C19.5524 7.1119 20.1119 6.5524 20.291 5.83583L20.5 5H21.5L21.709 5.83583C21.8881 6.5524 22.4476 7.1119 23.1642 7.29104Z"></path>
      </svg>
    </button>
    <a data-no-swup href="<?php $this->user->hasLogin() ? $this->options->logoutUrl() : $this->options->loginUrl(); ?>" class="icon-btn" aria-label="<?php $this->user->hasLogin() ? '登出' : '登录'; ?>" >
      <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
        <path d="M4 15H6V20H18V4H6V9H4V3C4 2.44772 4.44772 2 5 2H19C19.5523 2 20 2.44772 20 3V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V15ZM10 11V8L15 12L10 16V13H2V11H10Z"></path>
      </svg>
    </a>
    <button id="search-toggle" class="icon-btn search-toggle" aria-label="打开搜索">
      <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
        <path d="M18.031 16.6168L22.3137 20.8995L20.8995 22.3137L16.6168 18.031C15.0769 19.263 13.124 20 11 20C6.032 20 2 15.968 2 11C2 6.032 6.032 2 11 2C15.968 2 20 6.032 20 11C20 13.124 19.263 15.0769 18.031 16.6168ZM16.0247 15.8748C17.2475 14.6146 18 12.8956 18 11C18 7.1325 14.8675 4 11 4C7.1325 4 4 7.1325 4 11C4 14.8675 7.1325 18 11 18C12.8956 18 14.6146 17.2475 15.8748 16.0247L16.0247 15.8748Z"></path>
      </svg>
    </button>
  </div>
</nav>
