/**
 * XPro Theme — 高性能原生 JavaScript 核心
 * 模块化设计 | Lighthouse 优化 | 低带宽友好
 */

// ============================================================
// 1. 工具对象
// ============================================================
const Utils = {
  $(selector, context = document) {
    return context.querySelector(selector);
  },

  $$(selector, context = document) {
    return Array.from(context.querySelectorAll(selector));
  },

  on(element, event, handler, options = false) {
    element.addEventListener(event, handler, options);
  },

  off(element, event, handler, options = false) {
    element.removeEventListener(event, handler, options);
  },

  once(element, event, handler) {
    element.addEventListener(event, handler, { once: true });
  },

  create(tag, attrs = {}, text = '') {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'className') el.className = v;
      else if (k === 'dataset') Object.assign(el.dataset, v);
      else el.setAttribute(k, v);
    });
    if (text) el.textContent = text;
    return el;
  },

  formatCount(n) {
    return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K' : String(n);
  },

  parseCount(text) {
    text = String(text).trim();
    return text.includes('K') ? Math.round(parseFloat(text) * 1000) : parseInt(text, 10) || 0;
  },

  getCurrentTheme() {
    const stored = localStorage.getItem('theme');
    if (stored === 'dark' || stored === 'light') return stored;
    if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) return 'dark';
    return 'light';
  },

  prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }
};

// ============================================================
// 2. 主题管理器（含 Fancybox 主题同步）
// ============================================================
const ThemeManager = {
  KEY: 'theme',
  html: document.documentElement,
  sunIcon: null,
  moonIcon: null,
  /**
   * 初始化：绑定事件、同步图标状态、初始化 Fancybox 主题
   */
  init() {
    this.sunIcon = Utils.$('#theme-sun');
    this.moonIcon = Utils.$('#theme-moon');
    this._bindEvents();
    this._syncFancybox();
    // 根据当前主题显示对应图标
    const current = this.html.getAttribute('data-theme') || Utils.getCurrentTheme();
    if (this.sunIcon) this.sunIcon.classList.toggle('hidden', current === 'dark');
    if (this.moonIcon) this.moonIcon.classList.toggle('hidden', current !== 'dark');
  },
  /**
   * 绑定主题切换按钮和系统主题变化监听
   */
  _bindEvents() {
    const toggle = Utils.$('#theme-toggle');
    if (toggle) {
      Utils.on(toggle, 'click', () => this.toggle(), { passive: true });
    }
    // 监听系统主题变化（仅在用户未手动设置时自动切换）
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (e) => {
      if (!localStorage.getItem(this.KEY)) {
        this.apply(e.matches ? 'dark' : 'light', false);
      }
    };
    if (mq.addEventListener) {
      mq.addEventListener('change', handler);
    } else if (mq.addListener) {
      mq.addListener(handler); // 兼容旧版 Safari
    }
    // 跨标签页主题同步：监听其他标签页的主题变更
    Utils.on(window, 'storage', (e) => {
      if (e.key === this.KEY) {
        const newTheme = e.newValue;
        if (newTheme === 'dark' || newTheme === 'light') {
          this.apply(newTheme, false);
        }
      }
    });
  },
  /**
   * 应用主题并可选保存到 localStorage
   * @param {string} theme - 'dark' | 'light'
   * @param {boolean} save - 是否写入 localStorage
   */
  apply(theme, save = true) {
    this.html.setAttribute('data-theme', theme);
    if (this.sunIcon) this.sunIcon.classList.toggle('hidden', theme === 'dark');
    if (this.moonIcon) this.moonIcon.classList.toggle('hidden', theme !== 'dark');
    if (save) localStorage.setItem(this.KEY, theme);
    this._syncFancybox();
  },
  /**
   * 切换主题（dark <-> light）
   */
  toggle() {
    const current = this.html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    this.apply(current, true);
  },
  /**
   * 同步 Fancybox 主题（销毁旧实例并重建）
   * 确保 Fancybox 弹窗与页面主题一致
   */
  _syncFancybox() {
    if (typeof window.Fancybox !== 'undefined') {
      requestAnimationFrame(() => {
        const theme = this.html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        // 销毁所有现有 Fancybox 实例
        if (window.Fancybox.getInstances) {
          window.Fancybox.getInstances().forEach(inst => inst.destroy());
        }
        this._initFancybox(theme);
      });
    }
  },
  /**
   * 初始化 Fancybox 配置
   * @param {string} theme - 'dark' | 'light'
   */
  _initFancybox(theme) {
    window.Fancybox.bind('[data-fancybox]', {
      theme,
      loop: true,
      Image: { protect: true },
      Carousel: {
        Toolbar: {
          display: {
            left: ['counter'],
            middle: ['zoomIn', 'zoomOut', 'toggle1to1', 'rotateCCW', 'rotateCW', 'flipX', 'flipY'],
            right: ['autoplay', 'download', 'slideshow', 'thumbs', 'close']
          }
        },
        Thumbs: {
          type: 'classic',
          showOnStart: false,
          Carousel: {
            vertical: true,
            center: function(ref) {
              return ref.getTotalSlideDim() > ref.getViewportDim();
            }
          }
        }
      },
      Slideshow: { timeout: 5000 }
    });
  }
};

// ============================================================
// 2.5 Fancybox 管理器（延迟初始化 + 跨标签页主题同步监听）
// ============================================================
const FancyboxManager = {
  init() {
    // 使用 requestIdleCallback 延迟初始化，避免阻塞首屏
    if (typeof window.requestIdleCallback === 'function') {
      requestIdleCallback(() => this._setup(), { timeout: 2000 });
    } else {
      setTimeout(() => this._setup(), 100);
    }
  },
  _setup() {
    if (typeof window.Fancybox === 'undefined') {
      // Fancybox 尚未加载，等待 window.load
      Utils.once(window, 'load', () => this._setup());
      return;
    }
    // 监听其他标签页的主题切换（通过 storage 事件）同步 Fancybox
    Utils.on(window, 'storage', (e) => {
      if (e.key === 'theme') {
        ThemeManager._syncFancybox();
      }
    });
    ThemeManager._syncFancybox();
  }
};

// ============================================================
// 3. 滚动管理器（返回顶部）
// ============================================================
const ScrollManager = {
  btn: null,
  sentinel: null,

  init() {
    this.btn = Utils.$('#back-to-top');
    if (!this.btn) return;
    this.sentinel = Utils.create('div', {
      style: 'position:absolute;top:400px;left:0;width:1px;height:1px;visibility:hidden;pointer-events:none;'
    });
    document.body.appendChild(this.sentinel);
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          this.btn.classList.toggle('visible', !entry.isIntersecting);
        });
      },
      { rootMargin: '0px', threshold: 0 }
    );
    observer.observe(this.sentinel);
    Utils.on(this.btn, 'click', () => {
      window.scrollTo({ top: 0, behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth' });
    }, { passive: true });
  }
};

// ============================================================
// 4. 锚点平滑跳转管理器
// ============================================================
const AnchorManager = {
  init() {
    Utils.on(document, 'click', (e) => {
      const link = e.target.closest('a[href^="#"]');
      if (!link) return;
      const href = link.getAttribute('href');
      if (!href || href === '#') return;
      const target = document.getElementById(href.slice(1));
      if (!target) return;
      e.preventDefault();
      const offset = target.getBoundingClientRect().top + window.scrollY - 80;
      window.scrollTo({
        top: offset,
        behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth'
      });
    });
  }
};

// ============================================================
// 5. 个人资料卡片管理器（下拉菜单）
// ============================================================
const ProfileCardManager = {
  init() {
    this.card = Utils.$('#profile-card');
    this.dropdown = Utils.$('#profile-dropdown');
    if (!this.card) return;
    Utils.on(this.card, 'click', (e) => {
      if (e.target.closest('.dropdown-item')) return;
      this.toggle();
    });
    Utils.on(this.card, 'keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.toggle();
      } else if (e.key === 'Escape') {
        this.close();
      }
    });
    Utils.on(this.dropdown, 'click', (e) => {
      const item = e.target.closest('.dropdown-item');
      if (!item) return;
      // 登出交给服务端处理：链接指向 logoutUrl，由 /action/logout 删除登录 Cookie
      if (item.id === 'logout-btn' && typeof window.clearAuth === 'function') {
        // 兼容第三方登录状态清理（不影响服务端注销跳转）
        window.clearAuth();
      }
    });
    Utils.on(document, 'click', (e) => {
      if (this.card.classList.contains('open') && !e.target.closest('#profile-card')) {
        this.close();
      }
    });
  },

  toggle() {
    const isOpen = this.card.classList.contains('open');
    isOpen ? this.close() : this.open();
  },

  open() {
    this.card.classList.add('open');
    this.card.setAttribute('aria-expanded', 'true');
    setTimeout(() => {
      const firstItem = this.dropdown?.querySelector('.dropdown-item');
      firstItem?.focus();
    }, 100);
  },

  close() {
    this.card.classList.remove('open');
    this.card.setAttribute('aria-expanded', 'false');
  },

};

// ============================================================
// 6. 公告管理器
// ============================================================
const NoticeManager = {
  KEY: 'chirp_notice_closed_id',
  _bound: false,

  init() {
    // 事件委托：只绑定一次，PJAX 替换 DOM 后仍生效
    if (!this._bound) {
      this._bound = true;
      Utils.on(document, 'click', (e) => {
        const closeBtn = e.target.closest('.notice-close');
        if (!closeBtn) return;
        const notice = closeBtn.closest('.notice');
        if (!notice) return;
        const noticeId = notice.dataset.noticeId;
        if (!noticeId) return;
        this.close(notice, noticeId);
      });
    }

    // 每次 PJAX 后重新检查 cookie（公告在 #main-content 内会被替换）
    const notice = Utils.$('.notice');
    if (!notice) return;
    const noticeId = notice.dataset.noticeId;
    if (!noticeId) return;
    if (this._getCookie(this.KEY) === noticeId) {
      notice.style.display = 'none';
    }
  },

  close(notice, id) {
    notice.style.maxHeight = notice.scrollHeight + 'px';
    notice.style.transition = 'opacity 0.25s ease, transform 0.25s ease, max-height 0.25s ease, margin 0.25s ease, padding 0.25s ease, border-width 0.25s ease';
    requestAnimationFrame(() => {
      notice.style.opacity = '0';
      notice.style.transform = 'translateY(-8px)';
      notice.style.maxHeight = '0';
      notice.style.margin = '0';
      notice.style.padding = '0';
      notice.style.borderWidth = '0';
      notice.style.pointerEvents = 'none';
      notice.style.overflow = 'hidden';
    });
    setTimeout(() => {
      notice.style.display = 'none';
      this._setCookie(this.KEY, id, 7); // 7 天有效期
    }, 260);
  },

  _setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
  },

  _getCookie(name) {
    const match = document.cookie.match(
      new RegExp('(?:^|; )' + name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)')
    );
    return match ? decodeURIComponent(match[1]) : null;
  }
};

// ============================================================
// 7. 移动端菜单管理器（左侧抽屉）
// ============================================================
const MobileMenuManager = {
  sidebar: null,
  openBtn: null,
  closeBtn: null,
  overlay: null,

  init() {
    this.sidebar = Utils.$('.site-navigation');
    this.openBtn = Utils.$('#menu-toggle');
    this.closeBtn = Utils.$('#menu-close');
    this.overlay = Utils.$('#overlay');
    if (!this.sidebar) return;
    if (this.openBtn) {
      Utils.on(this.openBtn, 'click', () => this.open(), { passive: true });
    }
    if (this.closeBtn) {
      Utils.on(this.closeBtn, 'click', () => this.close(), { passive: true });
    }
    if (this.overlay) {
      Utils.on(this.overlay, 'click', () => {
        if (this.sidebar.classList.contains('open')) this.close();
        if (Utils.$('.side-panel')?.classList.contains('open')) {
          SidePanelManager.close();
        }
      });
    }
    Utils.on(this.sidebar, 'click', (e) => {
      if (e.target.closest('a.nav-link')) this.close();
    });
  },

  open() {
    this.sidebar.classList.add('open');
    document.body.style.overflow = 'hidden';
    if (this.overlay) this.overlay.classList.add('active');
  },

  close() {
    this.sidebar.classList.remove('open');
    document.body.style.overflow = '';
    if (this.overlay) this.overlay.classList.remove('active');
  }
};

// ============================================================
// 8. 右侧边栏管理器
// ============================================================
const SidePanelManager = {
  init() {
    // 事件委托：PJAX 替换 .side-panel 后仍生效，避免旧节点引用失效
    Utils.on(document, 'click', (e) => {
      if (e.target.closest('#sidepanel-toggle')) {
        this.toggle();
        return;
      }
      if (e.target.closest('#sidepanel-close')) {
        this.close();
        return;
      }
      if (e.target.closest('.side-panel a')) {
        this.close();
      }
    });
  },

  panel() {
    return Utils.$('.side-panel');
  },

  toggle() {
    const panel = this.panel();
    if (!panel) return;
    panel.classList.contains('open') ? this.close() : this.open();
  },

  open() {
    const panel = this.panel();
    if (!panel) return;
    panel.classList.add('open');
    document.body.style.overflow = 'hidden';
    const overlay = Utils.$('#overlay');
    if (overlay) overlay.classList.add('active');
  },

  close() {
    const panel = this.panel();
    if (!panel) return;
    panel.classList.remove('open');
    document.body.style.overflow = '';
    const overlay = Utils.$('#overlay');
    if (overlay) overlay.classList.remove('active');
  }
};

// ============================================================
// 9. 搜索管理器
// ============================================================
const SearchManager = {
  KEY: 'chirp_search_history',
  MAX_HISTORY: 5,
  overlay: null,
  input: null,
  historyContainer: null,
  ghostTrigger: null,

  init() {
    // 侧边栏搜索框：事件委托，PJAX 替换侧边栏后依然生效
    Utils.on(document, 'keydown', (e) => {
      if (e.key !== 'Enter') return;
      const input = e.target.closest('.search-field-block .search-input');
      if (!input) return;
      e.preventDefault();
      const term = input.value.trim();
      if (!term) return;
      this.addHistory(term);
      this.doSearch(term);
    });

    this.overlay = Utils.$('#search-overlay');
    this.input = this.overlay?.querySelector('.search-input');
    this.historyContainer = Utils.$('.search-history');
    if (!this.overlay) return;
    Utils.on(document, 'click', (e) => {
      const target = e.target.closest('#search-toggle, #search-toggle-mobile');
      if (target) this.open();
    });
    const cancel = Utils.$('#search-cancel');
    if (cancel) {
      Utils.on(cancel, 'click', () => this.close(), { passive: true });
    }
    Utils.on(document, 'click', (e) => {
      const chip = e.target.closest('.search-chips .chip');
      if (chip) {
        const term = chip.textContent.trim();
        if (!term) return;
        this.input.value = term;
        this.addHistory(term);
        this.doSearch(term);
      }
    });
    Utils.on(document, 'click', (e) => {
      const chip = e.target.closest('.search-history .chip');
      if (!chip || e.target.closest('.history-delete')) return;
      const term = chip.dataset.term;
      if (term) {
        this.input.value = term;
        this.input.focus();
      }
    });
    Utils.on(document, 'click', (e) => {
      const delBtn = e.target.closest('.history-delete');
      if (!delBtn) return;
      e.stopPropagation();
      const term = delBtn.closest('.chip')?.dataset.term;
      if (term) this.removeHistory(term);
    });
    Utils.on(this.input, 'keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const term = this.input.value.trim();
        if (!term) return;
        this.addHistory(term);
        this.doSearch(term);
      }
    });
  },

  /**
   * 获取/创建隐藏的 <a> 元素，用于触发 Swup PJAX 导航
   */
  ensureGhostTrigger() {
    if (this.ghostTrigger && document.body.contains(this.ghostTrigger)) {
      return this.ghostTrigger;
    }
    const ghost = Utils.create('a', {
      id: '__pjax_search_trigger',
      href: '/',
      'aria-hidden': 'true',
      tabindex: '-1',
      style: 'display:none;'
    });
    document.body.appendChild(ghost);
    this.ghostTrigger = ghost;
    return ghost;
  },

  /**
   * 执行搜索：
   * 1. 通过 sendBeacon 记录热搜值（失败不影响跳转）
   * 2. 通过隐藏 <a> 触发 Swup PJAX 导航到搜索结果页
   */
  doSearch(keyword) {
    keyword = (keyword || '').trim();
    if (!keyword) return;
    // 记录热搜值
    try {
      const recordUrl = '/?action=record&keyword=' + encodeURIComponent(keyword);
      if (navigator.sendBeacon) {
        navigator.sendBeacon(recordUrl);
      } else {
        fetch(recordUrl, { keepalive: true, method: 'GET' }).catch(() => {});
      }
    } catch (err) { /* 静默失败 */ }
    // 标记本次提交了搜索，关闭弹窗时预刷一次热搜列表
    window.__searchPendingRecord = true;
    // 走 Typecho 规范 search URL，避免 ?s=xxx 被 302 跳转丢 keyword
    const tpl = window.__searchUrlTpl || '/index.php/search/{keyword}/';
    const ghost = this.ensureGhostTrigger();
    ghost.href = tpl.replace('{keyword}', encodeURIComponent(keyword));
    ghost.click();
    // 关闭搜索弹窗
    this.close();
  },

  /**
   * 刷新热门搜索列表（PJAX 后或关闭弹窗时调用）
   */
  async refreshHotSearch() {
    const container = Utils.$('.search-chips');
    if (!container) return;
    try {
      const response = await fetch('/?action=hotSearchHTML&t=' + Date.now(), { cache: 'no-store' });
      if (!response.ok) return;
      const html = await response.text();
      container.innerHTML = html;
    } catch (err) {
      console.warn('[SearchManager] 刷新热门搜索失败:', err);
    }
  },

  open() {
    this.overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    this.renderHistory();
    setTimeout(() => this.input?.focus(), 300);
  },

  close() {
    this.overlay.classList.remove('open');
    document.body.style.overflow = '';
    // 若本次会话提交过搜索，延迟刷新热搜列表，保证 record 已落盘
    if (window.__searchPendingRecord) {
      window.__searchPendingRecord = false;
      setTimeout(() => this.refreshHotSearch(), 200);
    }
  },

  getHistory() {
    try {
      return JSON.parse(localStorage.getItem(this.KEY)) || [];
    } catch {
      return [];
    }
  },

  saveHistory(terms) {
    localStorage.setItem(this.KEY, JSON.stringify(terms.slice(0, this.MAX_HISTORY)));
  },

  addHistory(term) {
    term = term.trim();
    if (!term) return;
    const history = this.getHistory().filter(t => t.toLowerCase() !== term.toLowerCase());
    history.unshift(term);
    this.saveHistory(history);
    this.renderHistory();
  },

  removeHistory(term) {
    const history = this.getHistory().filter(t => t !== term);
    this.saveHistory(history);
    this.renderHistory();
  },

  renderHistory() {
    if (!this.historyContainer) return;
    const history = this.getHistory();
    this.historyContainer.innerHTML = '';
    if (history.length === 0) {
      this.historyContainer.appendChild(Utils.create('span', { className: 'chip' }, '暂无搜索记录'));
      return;
    }
    const frag = document.createDocumentFragment();
    history.forEach(term => {
      const chip = Utils.create('div', { className: 'chip', dataset: { term } });
      const span = Utils.create('span', {}, term);
      const delBtn = Utils.create('button', {
        className: 'history-delete',
        'aria-label': '删除该搜索记录'
      });
      delBtn.innerHTML = '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path></svg>';
      chip.appendChild(span);
      chip.appendChild(delBtn);
      frag.appendChild(chip);
    });
    this.historyContainer.appendChild(frag);
  }
};

// ============================================================
// 10. 手风琴管理器（导航折叠）
// ============================================================
const AccordionManager = {
  init() {
    Utils.on(document, 'click', (e) => {
      const item = e.target.closest('[data-has-submenu]');
      if (!item) return;
      e.stopPropagation();
      this.toggle(item);
    });
    Utils.on(document, 'keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const item = e.target.closest('[data-has-submenu]');
      if (!item) return;
      e.preventDefault();
      this.toggle(item);
    });
    Utils.on(document, 'click', (e) => {
      if (!e.target.closest('[data-has-submenu]') && !e.target.closest('.nav-submenu')) {
        Utils.$$('[data-has-submenu]').forEach(item => this.close(item));
      }
    });
  },

  toggle(item) {
    const isOpen = item.classList.contains('open');
    Utils.$$('[data-has-submenu]').forEach(el => this.close(el));
    if (!isOpen) this.open(item);
  },

  open(item) {
    item.classList.add('open');
    item.setAttribute('aria-expanded', 'true');
  },

  close(item) {
    item.classList.remove('open');
    item.setAttribute('aria-expanded', 'false');
  }
};

// ============================================================
// 11. 轮播管理器
// ============================================================
const CarouselManager = {
  slides: [],
  dots: [],
  current: 0,
  total: 0,
  timer: null,
  INTERVAL: 5000,
  SWIPE_THRESHOLD: 50,
  _visibilityHandler: null,

  init() {
    this._stopAutoPlay();
    if (this._visibilityHandler) {
      Utils.off(document, 'visibilitychange', this._visibilityHandler);
      this._visibilityHandler = null;
    }
    this.slides = Utils.$$('.carousel-slide');
    this.dots = Utils.$$('.carousel-dot');
    this.total = this.slides.length;
    this.current = 0;
    if (this.total === 0) return;
    this._bindEvents();
    this._startAutoPlay();
    this._visibilityHandler = () => {
      document.hidden ? this._stopAutoPlay() : this._startAutoPlay();
    };
    Utils.on(document, 'visibilitychange', this._visibilityHandler);
  },

  _bindEvents() {
    const nextBtn = Utils.$('.carousel-arrow.next');
    const prevBtn = Utils.$('.carousel-arrow.prev');
    if (nextBtn) {
      Utils.on(nextBtn, 'click', () => {
        this._restart();
        this.next();
      }, { passive: true });
    }
    if (prevBtn) {
      Utils.on(prevBtn, 'click', () => {
        this._restart();
        this.prev();
      }, { passive: true });
    }
    this.dots.forEach((dot, idx) => {
      Utils.on(dot, 'click', () => {
        this._restart();
        this.show(idx);
      }, { passive: true });
    });
    const viewport = Utils.$('.carousel-viewport');
    if (viewport) {
      let startX = 0;
      Utils.on(viewport, 'touchstart', (e) => {
        startX = e.changedTouches[0].screenX;
      }, { passive: true });
      Utils.on(viewport, 'touchend', (e) => {
        const endX = e.changedTouches[0].screenX;
        if (endX < startX - this.SWIPE_THRESHOLD) {
          this._restart();
          this.next();
        } else if (endX > startX + this.SWIPE_THRESHOLD) {
          this._restart();
          this.prev();
        }
      }, { passive: true });
    }
  },

  show(i) {
    if (!this.slides.length || i < 0 || i >= this.total || i === this.current) return;
    this.slides[this.current]?.classList.remove('active');
    this.dots[this.current]?.classList.remove('active');
    this.dots[this.current]?.setAttribute('aria-selected', 'false');
    this.slides[i]?.classList.add('active');
    this.dots[i]?.classList.add('active');
    this.dots[i]?.setAttribute('aria-selected', 'true');
    this.current = i;
  },

  next() {
    this.show((this.current + 1) % this.total);
  },

  prev() {
    this.show((this.current - 1 + this.total) % this.total);
  },

  _startAutoPlay() {
    if (this.timer || this.total === 0) return;
    this.timer = setInterval(() => this.next(), this.INTERVAL);
  },

  _stopAutoPlay() {
    clearInterval(this.timer);
    this.timer = null;
  },

  _restart() {
    this._stopAutoPlay();
    this._startAutoPlay();
  }
};

// ============================================================
// 14. 目录管理器（TOC）
// ============================================================
const TOCManager = {
  NAV_OFFSET: 80,
  tocLinks: [],
  headings: [],
  ticking: false,
  scrollBound: false,

  init() {
    this.tocLinks = Utils.$$('nav.toc-nav .toc-link');
    if (this.tocLinks.length === 0) return;
    this.headings = [];
    this.tocLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (!href || !href.startsWith('#')) return;
      const target = document.getElementById(href.slice(1));
      if (target) this.headings.push({ link, target });
    });
    if (this.headings.length === 0) return;
    this._bindClick();
    if (!this.scrollBound) {
      this._bindScroll();
      this.scrollBound = true;
    }
    this._updateActive();
  },

  _bindClick() {
    this.tocLinks.forEach(link => {
      Utils.on(link, 'click', (e) => {
        e.preventDefault();
        const href = link.getAttribute('href');
        const target = document.getElementById(href.slice(1));
        if (!target) return;
        this._setActive(link);
        const offset = target.getBoundingClientRect().top + window.scrollY - this.NAV_OFFSET;
        window.scrollTo({
          top: offset,
          behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth'
        });
      });
    });
  },

  _bindScroll() {
    Utils.on(window, 'scroll', () => {
      if (this.ticking) return;
      this.ticking = true;
      requestAnimationFrame(() => {
        this._updateActive();
        this.ticking = false;
      });
    });
  },

  _updateActive() {
    const scrollY = window.scrollY + this.NAV_OFFSET + 1;
    let activeIndex = -1;
    if (this.headings.length > 0) {
      const firstTop = this.headings[0].target.offsetTop;
      if (firstTop > scrollY) {
        this._clearActive();
        return;
      }
    }
    for (let i = 0; i < this.headings.length; i++) {
      const top = this.headings[i].target.offsetTop;
      if (top > scrollY) {
        activeIndex = i - 1;
        break;
      }
    }
    if (activeIndex === -1 && this.headings.length > 0) {
      const last = this.headings[this.headings.length - 1];
      if (last.target.offsetTop + last.target.offsetHeight > scrollY - this.NAV_OFFSET) {
        activeIndex = this.headings.length - 1;
      }
    }
    if (activeIndex >= 0) {
      this._setActive(this.headings[activeIndex].link);
    } else {
      this._clearActive();
    }
  },

  _setActive(activeLink) {
    this.tocLinks.forEach(link => {
      link.classList.toggle('active', link === activeLink);
    });
  },

  _clearActive() {
    this.tocLinks.forEach(link => link.classList.remove('active'));
  }
};

// ============================================================
// 15. 选项卡管理器
// ============================================================
const TabsManager = {
  init() {
    const groups = Utils.$$('[data-tabs]');
    if (groups.length === 0) return;
    groups.forEach(group => {
      const tabs = Utils.$$(':scope > .tabs-nav > .tab', group);
      const panels = Utils.$$(':scope > .tabs-panel', group);
      if (tabs.length === 0 || panels.length === 0) return;
      this._applyDefault(group, tabs, panels);
      tabs.forEach((tab) => {
        Utils.on(tab, 'click', () => this._activate(group, tab, panels));
        Utils.on(tab, 'keydown', (e) => this._onKeydown(e, group, tab, tabs, panels));
      });
    });
  },

  _applyDefault(group, tabs, panels) {
    const raw = group.dataset.default;
    if (raw === undefined || raw === '') return;
    const idx = parseInt(raw, 10);
    if (!Number.isFinite(idx) || idx < 1 || idx > tabs.length) {
      console.warn('[TabsManager] data-default 越界或非法:', raw, '（应为 1~' + tabs.length + '）');
      return;
    }
    this._activate(group, tabs[idx - 1], panels);
  },

  _activate(group, activeTab, panels) {
    const target = activeTab.dataset.tab;
    Utils.$$(':scope > .tabs-nav > .tab', group).forEach(t => {
      const isActive = t === activeTab;
      t.classList.toggle('is-active', isActive);
      t.setAttribute('aria-selected', isActive ? 'true' : 'false');
      t.setAttribute('tabindex', isActive ? '0' : '-1');
    });
    panels.forEach(p => {
      p.classList.toggle('is-active', p.dataset.panel === target);
    });
  },

  _onKeydown(e, group, tab, tabs, panels) {
    const total = tabs.length;
    const idx = tabs.indexOf(tab);
    let next = null;
    switch (e.key) {
      case 'ArrowRight':
      case 'ArrowDown':
        next = tabs[(idx + 1) % total];
        break;
      case 'ArrowLeft':
      case 'ArrowUp':
        next = tabs[(idx - 1 + total) % total];
        break;
      case 'Home':
        next = tabs[0];
        break;
      case 'End':
        next = tabs[total - 1];
        break;
      default:
        return;
    }
    e.preventDefault();
    next.focus();
    this._activate(group, next, panels);
  }
};

// ============================================================
// 16. 折叠框管理器
// ============================================================
const CollapseManager = {
  _bound: false,

  init() {
    if (this._bound) { return; }
    this._bound = true;

    Utils.on(document, 'click', (e) => {
      const header = e.target.closest('.collapse-header');
      if (!header) return;
      const collapse = header.closest('.collapse');
      if (!collapse) return;
      this._toggle(collapse);
    });
    Utils.on(document, 'keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const header = e.target.closest('.collapse-header');
      if (!header) return;
      const collapse = header.closest('.collapse');
      if (!collapse) return;
      e.preventDefault();
      this._toggle(collapse);
    });
  },

  _toggle(collapse) {
    const willOpen = !collapse.classList.contains('is-open');
    collapse.classList.toggle('is-open', willOpen);
    collapse.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }
};

// ============================================================
// 17. 点赞管理器
// ============================================================
const LikeManager = {
  KEY: 'xpro_liked_cids',
  _bound: false,

  init() {
    this._syncButtonsFromStorage();

    if (!this._bound) {
      this._bound = true;
      Utils.on(document, 'click', (e) => {
        const btn = e.target.closest('.card-action.like, .post-action-btn.like');
        if (!btn) return;
        e.preventDefault();
        this.toggle(btn);
      });
    }
  },

  _getLikedCids() {
    try {
      const raw = localStorage.getItem(this.KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  },

  _addLikedCid(cid) {
    const cids = this._getLikedCids();
    const id = String(cid);
    if (!cids.includes(id)) {
      cids.push(id);
      localStorage.setItem(this.KEY, JSON.stringify(cids));
    }
  },

  _syncButtonsFromStorage() {
    const likedCids = new Set(this._getLikedCids());

    Utils.$$('.card-action.like, .post-action-btn.like').forEach(btn => {
      const cid = btn.dataset.cid;
      if (!cid) return;

      if (btn.classList.contains('liked')) {
        likedCids.add(cid);
        this._setHeartFilled(btn, true);
      } else if (likedCids.has(cid)) {
        btn.classList.add('liked');
        this._setHeartFilled(btn, true);
      }
    });

    localStorage.setItem(this.KEY, JSON.stringify(Array.from(likedCids)));
  },

  async toggle(btn) {
    const countEl = btn.querySelector('.count');
    if (!countEl) return;

    const cid = btn.dataset.cid;
    if (!cid || btn.disabled) return;

    if (btn.classList.contains('liked')) {
      if (typeof SnackbarManager !== 'undefined') {
        SnackbarManager.show('您已点赞', 'info');
      }
      return;
    }

    btn.disabled = true;
    btn.style.transform = 'scale(1.2)';

    try {
      const res = await fetch(window.XPRO_LIKE_URL || '/?action=like', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cid=${encodeURIComponent(cid)}`,
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'API error');

      btn.classList.add('liked');
      countEl.textContent = Utils.formatCount(json.likes);
      this._setHeartFilled(btn, true);
      this._addLikedCid(cid);
      this._updateOtherButtons(cid, json.likes);
      this._clearSwupCache();

      if (typeof SnackbarManager !== 'undefined') {
        SnackbarManager.show('感谢认可，已点赞', 'success');
      }
    } catch (err) {
      console.warn('[LikeManager] toggle failed:', err.message);
    } finally {
      setTimeout(() => {
        btn.style.transform = '';
        btn.disabled = false;
      }, 200);
    }
  },

  _updateOtherButtons(cid, likes) {
    const id = String(cid);
    Utils.$$(`.card-action.like[data-cid="${id}"], .post-action-btn.like[data-cid="${id}"]`).forEach(btn => {
      if (btn.classList.contains('liked')) return;
      btn.classList.add('liked');
      const countEl = btn.querySelector('.count');
      if (countEl) countEl.textContent = Utils.formatCount(likes);
      this._setHeartFilled(btn, true);
    });
  },

  _clearSwupCache() {
    if (window.xproSwup?.cache?.clear) {
      window.xproSwup.cache.clear();
    }
  },

  _setHeartFilled(btn, filled) {
    const path = btn.querySelector('.icon path');
    if (!path) return;
    const outline = 'M16.5 3C19.5376 3 22 5.5 22 9C22 16 14.5 20 12 21.5C9.5 20 2 16 2 9C2 5.5 4.5 3 7.5 3C9.35997 3 11 4 12 5C13 4 14.64 3 16.5 3ZM12.9339 18.6038C13.8155 18.0485 14.61 17.4955 15.3549 16.9029C18.3337 14.533 20 11.9435 20 9C20 6.64076 18.463 5 16.5 5C15.4241 5 14.2593 5.56911 13.4142 6.41421L12 7.82843L10.5858 6.41421C9.74068 5.56911 8.5759 5 7.5 5C5.55906 5 4 6.6565 4 9C4 11.9435 5.66627 14.533 8.64514 16.9029C9.39 17.4955 10.1845 18.0485 11.0661 18.6038C11.3646 18.7919 11.6611 18.9729 12 19.1752C12.3389 18.9729 12.6354 18.7919 12.9339 18.6038Z';
    const filledPath = 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z';
    path.setAttribute('d', filled ? filledPath : outline);
  }
};

// ============================================================
// 18. 评论管理器（回复模式）
// ============================================================
const CommentManager = {
  form: null,
  textarea: null,
  cancelBtn: null,
  privateCheckbox: null,
  currentReplyTo: null,
  listenersBound: false,

  init() {
    this.form = Utils.$('#comment-form');
    this.textarea = Utils.$('#comment-form-textarea');
    this.cancelBtn = Utils.$('#comment-form-cancel');
    this.privateCheckbox = Utils.$('#comment-form-private');
    if (!this.form) return;
    if (this.cancelBtn) this.cancelBtn.hidden = true;
    if (!this.listenersBound) {
      this._bindReplyButtons();
      this._bindMentionClicks();
      this._bindEscapeKey();
      this._bindLoadMore();
      this.listenersBound = true;
    }
    this._bindCancelButton();
    this._bindPrivateToggle();
    this._bindFormSubmit();
  },

  _bindReplyButtons() {
    Utils.on(document, 'click', (e) => {
      if (!e.target || !e.target.closest) return;
      const btn = e.target.closest('.comment-item-action[aria-label*="回复"]');
      if (!btn) return;
      const commentItem = btn.closest('.comment-item');
      if (!commentItem) return;
      e.preventDefault();
      const author = (commentItem.querySelector('.comment-item-author') || {}).textContent;
      if (!author || !author.trim()) return;
      if (this.currentReplyTo === commentItem.id) {
        this.clearReply();
        return;
      }
      this.setReply(commentItem.id, author.trim());
    });
  },

  _bindMentionClicks() {
    Utils.on(document, 'click', (e) => {
      if (!e.target || !e.target.closest) return;
      const mention = e.target.closest('.comment-reply-mention');
      if (!mention) return;
      e.preventDefault();
      const targetId = (mention.getAttribute('href') || '').slice(1);
      if (!targetId) return;
      const target = Utils.$('#' + (window.CSS && CSS.escape ? CSS.escape(targetId) : targetId));
      if (!target) return;
      target.classList.remove('highlight');
      target.style.animation = 'none';
      var top = target.getBoundingClientRect().top + window.pageYOffset - 80;
      window.scrollTo({ top: top, behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth' });
      setTimeout(function () {
        target.classList.add('highlight');
        target.style.animation = 'commentHighlight 2s ease';
        setTimeout(function () {
          target.classList.remove('highlight');
          // 保持内联 none，避免 animation-name 回退触发 fadeInUp 重播
          target.style.animation = 'none';
        }, 2100);
      }, 50);
    });
  },

  _bindCancelButton() {
    if (!this.cancelBtn) return;
    var oldHandler = this.cancelBtn._cancelHandler;
    if (oldHandler) {
      Utils.off(this.cancelBtn, 'click', oldHandler);
    }
    var handler = function () { this.clearReply(); }.bind(this);
    this.cancelBtn._cancelHandler = handler;
    Utils.on(this.cancelBtn, 'click', handler);
  },

  _bindEscapeKey() {
    Utils.on(document, 'keydown', (e) => {
      if (e.key === 'Escape' && this.currentReplyTo) {
        this.clearReply();
      }
    });
  },

  _bindPrivateToggle() {
    if (!this.privateCheckbox || !this.textarea) return;
    var oldHandler = this.privateCheckbox._toggleHandler;
    if (oldHandler) {
      Utils.off(this.privateCheckbox, 'change', oldHandler);
    }
    var handler = function () {
      this.textarea.classList.toggle('is-private', this.privateCheckbox.checked);
      if (this.privateCheckbox.checked) {
        this.textarea.placeholder = '已开启私密评论/回复...';
      } else if (this.currentReplyTo) {
        this.textarea.placeholder = '回复 ' + (this.replyAuthorName || '');
      } else {
        this.textarea.placeholder = '写下你的想法...';
      }
    }.bind(this);
    this.privateCheckbox._toggleHandler = handler;
    Utils.on(this.privateCheckbox, 'change', handler);
  },

_bindFormSubmit() {
    if (!this.form) return;
    var self = this;
    
    // 移除旧的 submit 监听器（防止重复绑定）
    var oldHandler = this.form._submitHandler;
    if (oldHandler) {
        Utils.off(this.form, 'submit', oldHandler);
    }
    
    var submitHandler = function (e) {
        e.preventDefault();
        
        // 评论内容不能为空
        var textValue = self.textarea ? self.textarea.value.trim() : '';
        if (!textValue) {
            SnackbarManager.show('评论内容不能为空', 'danger', 3000);
            if (self.textarea) self.textarea.focus();
            return;
        }
        
        // 防重锁
        if (self.form.dataset.submitting === 'true') {
            console.log('[CommentManager] 阻止重复提交');
            return;
        }
        self.form.dataset.submitting = 'true';
        
        var btn = self.form.querySelector('.comment-form-btn.primary');
        if (btn) {
            btn.disabled = true;
            btn.textContent = '发送中...';
        }
        
        var formData = new FormData(self.form);
        formData.append('themeAction', 'comment');
        formData.append('cid', self.form.dataset.cid || window.XPRO_COMMENT_CID || '0');
        if (self.currentReplyTo) {
            var id = self.currentReplyTo.replace('comment-', '');
            formData.append('parent', id);
        }
        if (self.privateCheckbox && self.privateCheckbox.checked) {
            formData.append('isPrivate', '1');
        }
        
        var searchParams = new URLSearchParams();
        formData.forEach(function (v, k) { searchParams.append(k, v); });
        var url = '?themeAction=comment';
        
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: searchParams.toString(),
            credentials: 'same-origin'
        })
        .then(function (res) { 
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json(); 
        })
        .then(function (data) {
            // 恢复按钮
            if (btn) {
                btn.disabled = false;
                btn.textContent = '发送';
            }
            self.form.dataset.submitting = 'false';
            
            if (data.status === 1) {
                if (data.comment && data.comment.status !== 'waiting') {
                    self._insertComment(data.comment);
                }
                self.clearReply();
                self.textarea.value = '';
                self.textarea.placeholder = '写下你的想法...';
                self.textarea.classList.remove('is-private');
                if (self.privateCheckbox) self.privateCheckbox.checked = false;
                if (data.msg) {
                    SnackbarManager.show(data.msg, data.comment && data.comment.status === 'waiting' ? 'warning' : 'success', 3000);
                }
            } else {
                SnackbarManager.show(data.msg || '提交失败', 'danger', 4000);
            }
        })
        .catch(function (err) {
            console.error('[CommentManager] 提交异常:', err);
            if (btn) {
                btn.disabled = false;
                btn.textContent = '发送';
            }
            self.form.dataset.submitting = 'false';
            SnackbarManager.show('网络错误，请稍后重试', 'danger', 4000);
        });
    };
    
    this.form._submitHandler = submitHandler;
    Utils.on(this.form, 'submit', submitHandler);
},

_insertComment(comment) {
    if (!comment || !comment.coid) return;
    var list = Utils.$('.comment-list');
    if (!list) return;
    
    var html = '';
    html += '<article class="comment-item';
    if (comment.parent > 0) html += ' is-reply';
    html += '" id="comment-' + comment.coid + '">';
    html += '<img src="' + (comment.avatar || '') + '" alt="' + comment.author + '的头像" class="avatar" loading="lazy">';
    html += '<div class="comment-item-body">';
    html += '<div class="comment-item-meta">';
    if (comment.url) {
        html += '<a href="' + comment.url + '" class="comment-item-author" rel="external nofollow" target="_blank">' + comment.author + '</a>';
    } else {
        html += '<span class="comment-item-author">' + comment.author + '</span>';
    }
    if (comment.isAuthor) html += '<span class="comment-item-badge">作者</span>';
    html += '<time class="comment-item-date" datetime="' + comment.datetime + '">' + comment.datetime + '</time>';
    html += '</div>';
    if (comment.parent > 0 && comment.parentAuthor) {
        html += '<blockquote class="comment-quote">';
        html += '<a href="#comment-' + comment.parent + '" class="comment-reply-mention">@' + comment.parentAuthor + '</a>';
        if (comment.parentText) {
            html += '<p class="comment-quote-text">' + comment.parentText + '</p>';
        }
        html += '</blockquote>';
    }
    html += '<div class="comment-item-text">' + (comment.content || '') + '</div>';
    html += '<div class="comment-item-actions">';
    html += '<button class="comment-item-action" aria-label="回复这条评论">';
    html += '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>';
    html += '回复';
    html += '</button>';
    html += '</div>';
    html += '</div>';
    html += '</article>';
    
    var wrapper = Utils.create('div', { className: '' });
    wrapper.innerHTML = html;
    var el = wrapper.firstElementChild;
    el.style.animation = 'none';
    
    if (comment.parent > 0 && this.currentReplyTo) {
        // 回复：插入到父评论后面
        var parentEl = Utils.$('#' + this.currentReplyTo);
        if (parentEl) {
            parentEl.insertAdjacentElement('afterend', el);
        } else {
            list.appendChild(el);
        }
    } else {
        // 顶级评论：插入到列表最前面
        if (list.firstElementChild) {
            list.insertBefore(el, list.firstElementChild);
        } else {
            list.appendChild(el);
        }
    }
    
    // 更新评论计数
    var countEl = Utils.$('.post-comments-title');
    if (countEl) {
        var match = countEl.textContent.match(/\d+/);
        if (match) {
            countEl.textContent = '评论（' + (parseInt(match[0], 10) + 1) + '）';
        }
    }
  },

  _bindLoadMore() {
    var self = this;
    Utils.on(document, 'click', function (e) {
      var btn = e.target.closest('.post-comments .author-comments-more');
      if (!btn) return;
      e.preventDefault();
      self._loadMoreComments(btn);
    });
  },

  _loadMoreComments(btn) {
    if (btn.disabled || btn.classList.contains('is-loading')) return;
    var self = this;
    var cid = parseInt(btn.dataset.cid, 10) || 0;
    var page = parseInt(btn.dataset.nextPage, 10) || 2;
    var pageSize = parseInt(btn.dataset.pageSize, 10) || 10;
    var order = btn.dataset.order || 'ASC';
    if (!cid) return;

    btn.disabled = true;
    btn.classList.add('is-loading');

    var url = '?themeAction=loadMoreComments&cid=' + cid + '&page=' + page + '&pageSize=' + pageSize + '&order=' + order;

    fetch(url, { method: 'GET', credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        if (data.status !== 1 || !data.comments || data.comments.length === 0) {
          btn.style.display = 'none';
          return;
        }
        var list = Utils.$('.comment-list');
        if (!list) return;
        var frag = document.createDocumentFragment();
        data.comments.forEach(function (top) {
          self._appendCommentHtml(top, frag);
          if (top.descendants && top.descendants.length) {
            top.descendants.forEach(function (desc) {
              self._appendCommentHtml(desc, frag);
            });
          }
        });
        list.appendChild(frag);
        if (data.hasMore) {
          btn.dataset.nextPage = String(page + 1);
        } else {
          btn.style.display = 'none';
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        SnackbarManager.show('加载失败，请稍后重试', 'danger');
      });
  },

  _appendCommentHtml(comment, container) {
    if (!comment || !comment.coid) return;
    var html = '';
    html += '<article class="comment-item';
    if (comment.parent > 0) html += ' is-reply';
    html += '" id="comment-' + comment.coid + '" style="animation:none;">';
    html += '<img src="' + (comment.avatar || '') + '" alt="' + comment.author + '的头像" class="avatar" loading="lazy">';
    html += '<div class="comment-item-body">';
    html += '<div class="comment-item-meta">';
    if (comment.url) {
      html += '<a href="' + comment.url + '" class="comment-item-author" rel="external nofollow" target="_blank">' + comment.author + '</a>';
    } else {
      html += '<span class="comment-item-author">' + comment.author + '</span>';
    }
    if (comment.isAuthor) html += '<span class="comment-item-badge">作者</span>';
    html += '<time class="comment-item-date" datetime="' + comment.datetime + '">' + comment.datetime + '</time>';
    html += '</div>';
    if (comment.parent > 0 && comment.parentAuthor) {
      html += '<blockquote class="comment-quote">';
      html += '<a href="#comment-' + comment.parent + '" class="comment-reply-mention">@' + comment.parentAuthor + '</a>';
      if (comment.parentText) {
        html += '<p class="comment-quote-text">' + comment.parentText + '</p>';
      }
      html += '</blockquote>';
    }
    html += '<div class="comment-item-text">' + (comment.content || '') + '</div>';
    html += '<div class="comment-item-actions">';
    html += '<button class="comment-item-action" aria-label="回复这条评论">';
    html += '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>';
    html += '回复';
    html += '</button>';
    html += '</div>';
    html += '</div>';
    html += '</article>';
    var div = document.createElement('div');
    div.innerHTML = html;
    container.appendChild(div.firstElementChild);
  },

  setReply(commentId, authorName) {
    const targetComment = Utils.$('#' + commentId);
    if (!targetComment) return;
    targetComment.insertAdjacentElement('afterend', this.form);
    if (this.textarea) {
      this.textarea.placeholder = `回复 ${authorName}`;
      this.textarea.value = '';
      this.textarea.focus();
    }
    if (this.cancelBtn) this.cancelBtn.hidden = false;
    const top = this.form.getBoundingClientRect().top + window.scrollY - 80;
    window.scrollTo({
      top,
      behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth'
    });
    this.currentReplyTo = commentId;
    this.replyAuthorName = authorName;
  },

  clearReply() {
    this.currentReplyTo = null;
    this.replyAuthorName = null;
    if (this.textarea) {
      this.textarea.value = '';
      this.textarea.placeholder = '写下你的想法...';
    }
    if (this.cancelBtn) this.cancelBtn.hidden = true;
    const commentList = Utils.$('.comment-list');
    if (commentList && this.form) {
      commentList.insertBefore(this.form, commentList.firstChild);
    }
  }
};

// ============================================================
// 19. Snackbar 提示管理器
// ============================================================
const SnackbarManager = {
  container: null,

  init() {
    this.container = document.querySelector('.snackbar-container');
  },

  show(message, type = 'info', duration = 2500) {
    if (!this.container) this.init();
    const iconMap = {
      info: 'M12 22C6.47715 22 2 17.5228 2 12 2 6.47715 6.47715 2 12 2 17.5228 2 22 6.47715 22 12 22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12 20 7.58172 16.4183 4 12 4 7.58172 4 4 7.58172 4 12 4 16.4183 7.58172 20 12 20ZM13 10.5V15H14V17H10V15H11V12.5H10V10.5H13ZM13.5 8C13.5 8.82843 12.8284 9.5 12 9.5 11.1716 9.5 10.5 8.82843 10.5 8 10.5 7.17157 11.1716 6.5 12 6.5 12.8284 6.5 13.5 7.17157 13.5 8Z',
      success: 'M10 15.172L19.192 5.979L20.607 7.393L10 18L3.393 11.393L4.807 9.979L10 15.172Z',
      warning: 'M12.866 3L21.392 18H4.359L12.866 3ZM12.866 5.5L6.428 16H19.179L12.866 5.5ZM11 10H13V14H11V10ZM11 15H13V17H11V15Z',
      danger: 'M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z'
    };
    const iconId = iconMap[type] || iconMap.info;
    const snackbar = Utils.create('div', { className: `snackbar ${type}` });
    snackbar.innerHTML = `<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="${iconId}"></path></svg><span>${message}</span>`;
    this.container.appendChild(snackbar);
    requestAnimationFrame(() => snackbar.classList.add('show'));
    const remove = () => {
      snackbar.classList.remove('show');
      snackbar.classList.add('is-hiding');
      const cleanup = () => snackbar.remove();
      Utils.once(snackbar, 'animationend', cleanup);
      setTimeout(cleanup, 350);
    };
    setTimeout(remove, duration);
  }
};

// ============================================================
// 20. 下载管理器（复制提取码）
// ============================================================
const DownloadManager = {
  init() {
    Utils.on(document, 'click', (e) => {
      const btn = e.target.closest('.download-card-btn.copy-btn');
      if (!btn) return;
      const text = btn.dataset.copy;
      if (!text) return;
      e.preventDefault();
      this._copy(btn, text);
    });
  },

  _copy(btn, text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => this._feedback(btn), () => this._fallback(btn, text));
    } else {
      this._fallback(btn, text);
    }
  },

  _fallback(btn, text) {
    const ta = Utils.create('textarea', {}, text);
    ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0;';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      this._feedback(btn);
    } catch (e) {
      console.error('[DownloadManager] copy failed:', e);
    }
    document.body.removeChild(ta);
  },

  _feedback(btn) {
    if (btn._copyTimeout) clearTimeout(btn._copyTimeout);
    btn.classList.add('is-copied');
    btn.innerHTML = '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M10 15.172L19.192 5.979L20.607 7.393L10 18L3.393 11.393L4.807 9.979L10 15.172Z"></path></svg>';
    SnackbarManager.show('复制成功', 'info');
    btn._copyTimeout = setTimeout(() => {
      btn.classList.remove('is-copied');
      btn.innerHTML = '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M7 6V3C7 2.44772 7.44772 2 8 2H20C20.5523 2 21 2.44772 21 3V17C21 17.5523 20.5523 18 20 18H17V21C17 21.5523 16.5523 22 16 22H4C3.44772 22 3 21.5523 3 21V7C3 6.44772 3.44772 6 4 6H7ZM5 8V20H15V8H5ZM17 16H19V4H9V6H16C16.5523 6 17 6.44772 17 7V16Z"></path></svg>';
    }, 2000);
  }
};

// ============================================================
// 21. 代码块复制管理器
// ============================================================
const CodeBlockManager = {
  _bound: false,

  init() {
    if (!this._bound) {
      this._bound = true;
      Utils.on(document, 'click', (e) => {
        const btn = e.target.closest('.code-block-copy');
        if (!btn) return;
        const block = btn.closest('.code-block');
        if (!block) return;
        const code = block.querySelector('pre code');
        if (!code) return;
        this._copy(btn, code.textContent);
      });
    }
  },

  _copy(btn, text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(
        () => this._feedback(btn),
        () => this._fallback(btn, text)
      );
    } else {
      this._fallback(btn, text);
    }
  },

  _fallback(btn, text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); this._feedback(btn); }
    catch (e) { SnackbarManager.show('复制失败', 'warning'); }
    document.body.removeChild(ta);
  },

  _feedback(btn) {
    if (btn._copyTimeout) clearTimeout(btn._copyTimeout);
    btn.classList.add('is-copied');
    btn.innerHTML = '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M10 15.172L19.192 5.979L20.607 7.393L10 18L3.393 11.393L4.807 9.979L10 15.172Z"></path></svg><span>已复制</span>';
    SnackbarManager.show('复制成功', 'info');
    btn._copyTimeout = setTimeout(() => {
      btn.classList.remove('is-copied');
      btn.innerHTML = '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M7 6V3C7 2.44772 7.44772 2 8 2H20C20.5523 2 21 2.44772 21 3V17C21 17.5523 20.5523 18 20 18H17V21C17 21.5523 16.5523 22 16 22H4C3.44772 22 3 21.5523 3 21V7C3 6.44772 3.44772 6 4 6H7ZM5 8V20H15V8H5ZM17 16H19V4H9V6H16C16.5523 6 17 6.44772 17 7V16Z"></path></svg><span>复制</span>';
    }, 2000);
  }
};

// ============================================================
// 22. 投票管理器
// ============================================================
const VoteManager = {
  KEY_PREFIX: 'chirp_vote_',

  init() {
    const cards = Utils.$$('.vote-card[data-vote]');
    if (cards.length === 0) return;
    cards.forEach((card) => this._setup(card));
  },

  _setup(card) {
    const voteId = card.dataset.vote;
    const type = card.dataset.type || 'single';
    const max = parseInt(card.dataset.max, 10) || (card.dataset.type === 'single' ? 1 : 0);
    const options = Utils.$$('.vote-card-option[data-option]', card);
    const totalEl = Utils.$('.vote-card-join-num strong', card);
    const deadlineEl = Utils.$('.vote-card-deadline', card);
    const footer = Utils.$('.vote-card-footer', card);
    if (!voteId || options.length === 0) return;

    const deadline = card.dataset.deadline;
    const deadlineDate = deadline ? new Date(deadline) : null;
    const isExpired = deadlineDate && !isNaN(deadlineDate.getTime()) && Date.now() > deadlineDate.getTime();

    this._renderDeadline(deadlineEl, deadlineDate, isExpired);

    const optionsContainer = Utils.$('.vote-card-options', card);
    if (optionsContainer) {
      this._createOptionsDesc(card, optionsContainer, max);
    }

    const stored = this._getStored(voteId);

    if (type === 'multiple' && options.length > 5) {
      if (optionsContainer) {
        optionsContainer.classList.add('collapsed');
        this._createExpandBtn(card, optionsContainer, !!stored || isExpired);
      }
    }

    if (stored) {
      card.classList.add('is-voted');
      if (isExpired) card.classList.add('is-expired', 'ended');
      card._voteData = stored;
      this._lockResults(card, options, stored.selected || []);
      this._renderResults(card, stored, options, totalEl);
      return;
    }

    if (isExpired) {
      card.classList.add('is-expired', 'ended');
      const data = this._generateMockResults(options);
      card._voteData = data;
      this._lockResults(card, options, []);
      this._renderResults(card, data, options, totalEl);
      return;
    }

    const baseline = this._generateMockResults(options);
    card._voteData = baseline;
    this._renderResults(card, baseline, options, totalEl);

    let submitBtn = null;
    if (footer) {
      submitBtn = this._createSubmitBtn(footer);
    }

    options.forEach((opt) => {
      Utils.on(opt, 'click', () => {
        if (card.classList.contains('is-voted') || card.classList.contains('is-expired')) return;
        this._handleOptionClick(card, opt, type, max, options, submitBtn);
      });
      const indicator = opt.querySelector('.vote-card-indicator');
      if (indicator) {
        Utils.on(indicator, 'keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            indicator.click();
          }
        });
      }
    });

    if (submitBtn) {
      Utils.on(submitBtn, 'click', () => this._submit(card, voteId, options, totalEl, submitBtn));
    }
  },

  _createSubmitBtn(footer) {
    const btn = Utils.create('button', {
      type: 'button',
      className: 'vote-card-submit'
    }, '投票');

    footer.appendChild(btn);
    return btn;
  },

  _createExpandBtn(card, optionsContainer, isLocked) {
    const wrapper = document.createElement('div');
    wrapper.className = 'vote-card-expand';
    wrapper.innerHTML = `
      <button class="vote-card-expand-btn" type="button" aria-expanded="false">
        <span class="vote-card-expand-text">展开全部选项</span>
        <svg class="vote-card-expand-arrow" aria-hidden="true" viewBox="0 0 24 24">
          <path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"></path>
        </svg>
      </button>
    `;
    const btn = wrapper.querySelector('.vote-card-expand-btn');
    const text = wrapper.querySelector('.vote-card-expand-text');
    optionsContainer.parentNode.insertBefore(wrapper, optionsContainer.nextSibling);

    Utils.on(btn, 'click', () => {
      const isCollapsed = optionsContainer.classList.contains('collapsed');
      if (isCollapsed) {
        optionsContainer.classList.remove('collapsed');
        btn.setAttribute('aria-expanded', 'true');
        text.textContent = '收起';
      } else {
        optionsContainer.classList.add('collapsed');
        btn.setAttribute('aria-expanded', 'false');
        text.textContent = '展开全部选项';
      }
    });

    return wrapper;
  },

  _createOptionsDesc(card, optionsContainer, max) {
    if (Utils.$('.vote-card-options-desc', optionsContainer)) return;
    const desc = document.createElement('div');
    desc.className = 'vote-card-options-desc';
    desc.innerHTML = '<span class="vote-card-options-label">投票选项</span><span class="vote-card-options-limit"></span>';
    optionsContainer.prepend(desc);
    const limitEl = desc.querySelector('.vote-card-options-limit');
    if (limitEl) {
      limitEl.textContent = max > 0 ? `最多选${max}项` : '可多选';
    }
  },

  _renderDeadline(deadlineEl, deadlineDate, isExpired) {
    if (!deadlineEl) return;
    if (isExpired) {
      deadlineEl.textContent = '投票已结束';
    } else if (deadlineDate && !isNaN(deadlineDate.getTime())) {
      deadlineEl.textContent = '截止日期 ' + this._formatDate(deadlineDate);
    }
  },

  _handleOptionClick(card, opt, type, max, options, submitBtn) {
    const isSelected = opt.classList.contains('is-selected');
    const ind = opt.querySelector('.vote-card-indicator');
    if (isSelected) {
      opt.classList.remove('is-selected');
      if (ind) ind.setAttribute('aria-checked', 'false');
    } else {
      const selectedCount = options.filter((o) => o.classList.contains('is-selected')).length;
      if (max > 0 && selectedCount >= max) {
        // For max=1, deselect others and select this one
        if (max === 1) {
          options.forEach((o) => {
            o.classList.remove('is-selected');
            const otherInd = o.querySelector('.vote-card-indicator');
            if (otherInd) otherInd.setAttribute('aria-checked', 'false');
          });
        } else {
          SnackbarManager.show(`最多选择 ${max} 项`, 'warning');
          return;
        }
      }
      opt.classList.add('is-selected');
      if (ind) ind.setAttribute('aria-checked', 'true');
    }
    if (submitBtn) {
      const hasSelection = options.some((o) => o.classList.contains('is-selected'));
      submitBtn.classList.toggle('visible', hasSelection);
    }
  },

  _submit(card, voteId, options, totalEl, submitBtn) {
    if (card.classList.contains('is-voted') || card.classList.contains('is-expired')) {
      SnackbarManager.show('投票失败', 'danger');
      return;
    }

    const selectedKeys = options.filter((o) => o.classList.contains('is-selected')).map((o) => o.dataset.option).filter(Boolean);
    if (selectedKeys.length === 0) return;

    const current = card._voteData || this._generateMockResults(options);
    selectedKeys.forEach((key) => {
      current.results[key] = (current.results[key] || 0) + 1;
      current.total += 1;
    });

    try {
      this._store(voteId, { ...current, selected: selectedKeys });
      card.classList.add('is-voted');
      card._voteData = current;
      this._lockResults(card, options, selectedKeys);
      this._renderResults(card, current, options, totalEl);
      SnackbarManager.show('投票成功', 'success');
      if (submitBtn) submitBtn.classList.remove('visible');
    } catch (e) {
      SnackbarManager.show('投票失败', 'danger');
    }
  },

  _lockResults(card, options, selectedKeys) {
    options.forEach((opt) => {
      opt.classList.remove('is-selected');
      const ind = opt.querySelector('.vote-card-indicator');
      if (ind) ind.setAttribute('aria-checked', 'false');
      const key = opt.dataset.option;
      const isUserChoice = selectedKeys.includes(key);
      opt.classList.toggle('is-user-choice', isUserChoice);
    });
  },

  _renderResults(card, data, options, totalEl) {
    if (totalEl) totalEl.textContent = this._formatCount(data.total);
    const results = data.results || {};
    let maxCount = 0;
    const counts = options.map((opt) => {
      const count = results[opt.dataset.option] || 0;
      if (count > maxCount) maxCount = count;
      return { opt, count };
    });
    counts.forEach(({ opt, count }) => {
      const percent = data.total > 0 ? ((count / data.total) * 100).toFixed(1) : '0.0';
      const progressBg = opt.querySelector('.vote-card-progress');
      const percentage = opt.querySelector('.vote-card-percent');
      if (progressBg) progressBg.style.width = percent + '%';
      if (percentage) percentage.textContent = percent + '%';
      const isHighest = count > 0 && count === maxCount;
      opt.classList.toggle('highest-vote', isHighest);
    });
  },

  _generateMockResults(options) {
    const results = {};
    let total = 0;
    options.forEach((opt) => {
      const key = opt.dataset.option;
      const n = Math.floor(Math.random() * 190) + 10;
      results[key] = n;
      total += n;
    });
    return { results, total };
  },

  _formatCount(n) {
    if (n >= 10000) return (n / 10000).toFixed(1).replace(/\.0$/, '') + '万';
    return String(n);
  },

  _formatDate(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  },

  _getStored(voteId) {
    try {
      return JSON.parse(localStorage.getItem(this.KEY_PREFIX + voteId));
    } catch {
      return null;
    }
  },

  _store(voteId, data) {
    localStorage.setItem(this.KEY_PREFIX + voteId, JSON.stringify(data));
  }
};

// ============================================================
// 22. GitHub 卡片管理器
// ============================================================
const GitHubCardManager = {
  init() {
    const cards = Utils.$$('[data-repo]');
    if (cards.length === 0) return;
    cards.forEach((card) => {
      const repo = card.dataset.repo;
      if (!repo) return;
      if (card.tagName === 'A') {
        if (!card.getAttribute('href')) {
          card.href = `https://github.com/${repo}`;
        }
        if (!card.target) card.target = '_blank';
        if (!card.rel) card.rel = 'noopener noreferrer';
      }
      // 后端已通过缓存生成完整 HTML，跳过前端请求
      if (card.dataset.cached === 'true') return;
      this._fetch(card, repo);
    });
  },

  async _fetch(card, repo) {
    card.classList.add('is-loading');
    try {
      var api = window.GITHUB_CARD_API || './libs/Github.php';
      var url = api + '?repo=' + encodeURIComponent(repo);
      const res = await fetch(url);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      if (json.code !== 0) throw new Error(json.error || 'API error');
      this._render(card, json.data);
    } catch (err) {
      console.warn(`[GitHubCardManager] failed to fetch ${repo}:`, err.message);
    } finally {
      card.classList.remove('is-loading');
    }
  },

  _render(card, data) {
    const starsEl = card.querySelector('[data-stat="stars"]');
    const forksEl = card.querySelector('[data-stat="forks"]');
    const langDot = card.querySelector('.github-card-lang-dot');
    const langName = card.querySelector('.github-card-lang-name');
    const descEl = card.querySelector('.github-card-desc');
    const ownerEl = card.querySelector('.github-card-owner');
    const nameEl = card.querySelector('.github-card-name');
    if (starsEl) starsEl.textContent = this._format(data.stargazers_count || 0);
    if (forksEl) forksEl.textContent = this._format(data.forks_count || 0);
    if (data.language) {
      if (langDot) langDot.style.setProperty('--lang-color', this._langColor(data.language));
      if (langName) langName.textContent = data.language;
    }
    if (descEl) descEl.textContent = data.description || '';
    if (ownerEl) ownerEl.textContent = data.owner || '';
    if (nameEl) nameEl.textContent = data.name || '';
  },

  _format(n) {
    return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : String(n);
  },

  _langColor(lang) {
    const map = {
      'JavaScript': '#f1e05a', 'TypeScript': '#3178c6', 'HTML': '#e34c26',
      'CSS': '#563d7c', 'PHP': '#4F5D95', 'Python': '#3572A5',
      'Java': '#b07219', 'Go': '#00ADD8', 'Rust': '#dea584',
      'Ruby': '#701516', 'C': '#555555', 'C++': '#f34b7d',
      'C#': '#178600', 'Swift': '#ffac45', 'Kotlin': '#A97BFF',
      'Vue': '#41b883', 'React': '#61dafb', 'Shell': '#89e051'
    };
    return map[lang] || '#8b949e';
  }
};

// ============================================================
// 23. B站卡片管理器
// ============================================================
const BiliCardManager = {
  API_ENDPOINT: window.BILI_CARD_API || './libs/BiliBili.php',

  init() {
    const cards = Utils.$$('[data-bvid], [data-aid]');
    cards.forEach(card => {
      // 后端已通过缓存生成完整 HTML，跳过前端请求
      if (card.dataset.cached === 'true') return;
      const bvid = card.dataset.bvid;
      const aid = card.dataset.aid;
      if (bvid) this._fetch(card, { bvid });
      else if (aid) this._fetch(card, { aid });
    });
  },

  async _fetch(card, params) {
    card.classList.add('is-loading');
    try {
      const base = card.dataset.api || this.API_ENDPOINT;
      const url = new URL(base, window.location.origin);
      if (params.bvid) url.searchParams.set('bv', params.bvid);
      else if (params.aid) url.searchParams.set('av', params.aid);
      const res = await fetch(url);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      if (json.code !== 0) throw new Error(json.message || 'API error');
      this._render(card, json.data);
    } catch (err) {
      console.warn('[BiliCardManager] fetch failed:', err.message);
      this._renderError(card, params);
    } finally {
      card.classList.remove('is-loading');
    }
  },

  _renderError(card, params) {
    const id = params.bvid ? params.bvid : 'av' + params.aid;
    card.outerHTML = '<div class="bili-card" style="border-color:var(--danger);">'
      + '<p class="bili-card-desc">哔哩哔哩错误：啊叻？视频不见了？（' + id + '）</p>'
      + '</div>';
  },

  _render(card, data) {
    const coverImg = card.querySelector('.bili-card-cover img');
    if (coverImg && data.pic) {
      coverImg.src = data.pic;
    }
    const dur = card.querySelector('.bili-card-duration');
    if (dur && data.duration) {
      dur.textContent = this._formatDuration(data.duration);
    }
    const title = card.querySelector('.bili-card-title');
    if (title && data.title) title.textContent = data.title;
    const desc = card.querySelector('.bili-card-desc');
    if (desc && data.desc) desc.textContent = data.desc;
    const upName = card.querySelector('.bili-card-up-name');
    if (upName && data.up_name) upName.textContent = data.up_name;
    const upAvatar = card.querySelector('.bili-card-up-avatar img');
    if (upAvatar && data.up_face) upAvatar.src = data.up_face;
    const upTime = card.querySelector('.bili-card-up-time');
    if (upTime && data.pubdate) {
      const date = new Date(data.pubdate * 1000);
      upTime.textContent = this._formatDate(date);
    }
    const statNums = card.querySelectorAll('.bili-card-stat-num');
    if (statNums.length >= 4) {
      const view = data.view ?? 0;
      const danmaku = data.danmaku ?? 0;
      const like = data.like ?? 0;
      const coin = data.coin ?? 0;
      statNums[0].textContent = this._formatCount(view);
      statNums[1].textContent = this._formatCount(danmaku);
      statNums[2].textContent = this._formatCount(like);
      statNums[3].textContent = this._formatCount(coin);
    }
  },

  _formatDuration(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
  },

  _formatDate(date) {
    var y = date.getFullYear();
    var m = (date.getMonth() + 1).toString().padStart(2, '0');
    var d = date.getDate().toString().padStart(2, '0');
    return y + '-' + m + '-' + d;
  },

  _formatCount(n) {
    if (n >= 10000) return (n / 10000).toFixed(1) + '万';
    if (n >= 1000) return (n / 1000).toFixed(1) + '千';
    return String(n);
  }
};

// ============================================================
// 25. 音乐播放器管理器
// ============================================================
const MusicPlayerManager = {
  _current: null,
  _registry: new Set(),

  _formatTime(sec) {
    if (!isFinite(sec) || sec < 0) return '--:--';
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return m.toString().padStart(2, '0') + ':' + s.toString().padStart(2, '0');
  },

  init() {
    // PJAX 重新初始化：销毁旧实例以防止内存泄漏
    this._destroyAll();
    const cards = Utils.$$('[data-music-player]');
    if (cards.length === 0) return;
    cards.forEach((card) => this._setup(card));
    Utils.on(window, 'beforeunload', () => {
      this._registry.forEach((card) => this._destroy(card));
    });
  },

  _setup(card) {
    const audio    = Utils.$('audio', card);
    const button   = Utils.$('.music-card-play', card);
    const track    = Utils.$('.music-card-track', card);
    const fill     = Utils.$('.music-card-fill', card);
    const curEl    = Utils.$('.music-card-current', card);
    const durEl    = Utils.$('.music-card-duration', card);
    const src      = card.dataset.audio;
    if (!audio || !button || !track || !fill || !src) return;
    audio.src = src;
    audio.preload = audio.preload || 'metadata';
    if (durEl) durEl.textContent = '-:--';
    let dragging = false;
    let dragPercent = 0;
    Utils.on(button, 'click', () => this._toggle(card));
    Utils.on(audio, 'play',           () => {
      this._setState(card, 'is-playing');
      this._updateAria(card, true);
      if (card._musicPlayer) {
        card._musicPlayer.playErrorHandled = false;
        SnackbarManager.show(`开始播放 ${this._getTitle(card)}`, 'info');
      }
    });
    Utils.on(audio, 'pause',          () => {
      this._clearState(card, 'is-playing');
      this._updateAria(card, false);
    });
    Utils.on(audio, 'ended',          () => {
      this._clearState(card, 'is-playing');
      this._setProgress(card, 0);
      if (curEl) curEl.textContent = '00:00';
    });
    Utils.on(audio, 'waiting',        () => this._setState(card, 'is-loading'));
    Utils.on(audio, 'canplay',        () => this._clearState(card, 'is-loading'));
    Utils.on(audio, 'error',          () => {
      this._handleError(card);
      const ref = card._musicPlayer;
      if (ref?.triedPlay && !ref.playErrorHandled) {
        ref.playErrorHandled = true;
        SnackbarManager.show('链接失效', 'warning');
      }
    });
    Utils.on(audio, 'loadedmetadata', () => {
      if (durEl) durEl.textContent = this._formatTime(audio.duration);
    });
    Utils.on(audio, 'timeupdate',     () => {
      if (dragging) return;
      this._setProgress(card, this._safeRatio(audio.currentTime, audio.duration));
      if (curEl) curEl.textContent = this._formatTime(audio.currentTime);
    });
    const updateProgressUI = (ratio) => {
      const pct = (ratio * 100).toFixed(2) + '%';
      fill.style.width = pct;
      track.style.setProperty('--progress', pct);
      track.setAttribute('aria-valuenow', Math.round(ratio * 100));
      const dur = isFinite(audio.duration) ? audio.duration : 0;
      if (curEl) curEl.textContent = this._formatTime(ratio * dur);
    };
    const updateProgressFromEvent = (e) => {
      const rect = track.getBoundingClientRect();
      const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
      dragPercent = x / rect.width;
      updateProgressUI(dragPercent);
    };
    const applySeek = () => {
      if (isFinite(audio.duration) && audio.duration > 0) {
        audio.currentTime = dragPercent * audio.duration;
      }
    };
    Utils.on(track, 'mousedown', (e) => {
      e.preventDefault();
      dragging = true;
      track.style.cursor = 'pointer';
      track.style.userSelect = 'none';
      updateProgressFromEvent(e);
    });
    Utils.on(document, 'mousemove', (e) => {
      if (!dragging) return;
      track.style.cursor = 'pointer';
      updateProgressFromEvent(e);
    });
    Utils.on(document, 'mouseup', () => {
      if (!dragging) return;
      dragging = false;
      applySeek();
      track.style.cursor = '';
      track.style.userSelect = '';
    });
    Utils.on(track, 'click', (e) => {
      if (dragging) return;
      this._seek(card, this._ratioFromEvent(track, e));
    });
    Utils.on(button, 'keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      this._toggle(card);
    });
    Utils.on(track, 'keydown', (e) => {
      if (!isFinite(audio.duration)) return;
      const step = e.shiftKey ? 30 : 5;
      if (e.key === 'ArrowRight') { e.preventDefault(); audio.currentTime = Math.min(audio.duration, audio.currentTime + step); }
      if (e.key === 'ArrowLeft')  { e.preventDefault(); audio.currentTime = Math.max(0, audio.currentTime - step); }
      if (e.key === ' ')          { e.preventDefault(); this._toggle(card); }
      if (e.key === 'Home')       { e.preventDefault(); audio.currentTime = 0; }
      if (e.key === 'End')        { e.preventDefault(); audio.currentTime = audio.duration; }
    });
    this._registry.add(card);
    card._musicPlayer = { audio, button, track, fill, curEl, durEl, triedPlay: false, playErrorHandled: false };
  },

  _safeRatio(cur, dur) {
    if (!isFinite(dur) || dur <= 0) return 0;
    return Math.max(0, Math.min(1, cur / dur));
  },

  _ratioFromEvent(track, e) {
    const rect = track.getBoundingClientRect();
    const x = (e.clientX - rect.left);
    return Math.max(0, Math.min(1, x / rect.width));
  },

  _setProgress(card, ratio) {
    const ref = card._musicPlayer;
    if (!ref) return;
    const pct = (ratio * 100).toFixed(2) + '%';
    ref.fill.style.width = pct;
    ref.track.style.setProperty('--progress', pct);
    ref.track.setAttribute('aria-valuenow', Math.round(ratio * 100));
    if (ref.curEl) ref.curEl.textContent = this._formatTime(ratio * (ref.audio.duration || 0));
  },

  _seek(card, ratio) {
    const ref = card._musicPlayer;
    if (!ref || !isFinite(ref.audio.duration)) return;
    ref.audio.currentTime = ratio * ref.audio.duration;
    this._setProgress(card, ratio);
  },

  _toggle(card) {
    const ref = card._musicPlayer;
    if (!ref) return;
    if (ref.audio.paused) this._play(card);
    else                  this._pause(card);
  },

  _play(card) {
    if (this._current && this._current !== card) this._pause(this._current, true);
    const ref = card._musicPlayer;
    if (!ref) return;
    ref.triedPlay = true;
    ref.playErrorHandled = false;
    const p = ref.audio.play();
    if (p && typeof p.catch === 'function') {
      p.catch(() => {
        this._handleError(card);
        if (!ref.playErrorHandled) {
          ref.playErrorHandled = true;
          SnackbarManager.show('歌曲加载失败', 'danger');
        }
      });
    }
    this._current = card;
  },

  _pause(card, silent = false) {
    const ref = card._musicPlayer;
    if (!ref) return;
    ref.audio.pause();
    if (this._current === card) this._current = null;
    if (!silent) SnackbarManager.show('已暂停播放歌曲', 'info');
  },

  _getTitle(card) {
    const titleEl = Utils.$('.music-card-title', card);
    return titleEl ? titleEl.textContent.trim() : '歌曲';
  },

  _setState(card, cls)    { card.classList.add(cls); },
  _clearState(card, cls)  { card.classList.remove(cls); },

  _handleError(card) {
    this._setState(card, 'is-error');
    this._clearState(card, 'is-playing');
    this._clearState(card, 'is-loading');
  },

  _updateAria(card, isPlaying) {
    const ref = card._musicPlayer;
    if (!ref) return;
    ref.button.setAttribute('aria-label', isPlaying ? '暂停' : '播放');
  },

  _destroy(card) {
    const ref = card._musicPlayer;
    if (!ref) return;
    try { ref.audio.pause(); ref.audio.src = ''; } catch (e) { /* ignore */ }
    this._registry.delete(card);
    delete card._musicPlayer;
  },

  _destroyAll() {
    this._current = null;
    this._registry.forEach((card) => this._destroy(card));
    this._registry.clear();
  }
};

// ============================================================
// 26. 归档排序管理器
// ============================================================
const ArchiveSortManager = {
  btn: null,
  dropdown: null,
  toolbar: null,
  _clickHandler: null,
  _docClickHandler: null,
  _docKeyHandler: null,

  init() {
    this._unbindEvents();
    this.btn = Utils.$('#archive-sort-btn');
    this.dropdown = Utils.$('#archive-sort-dropdown');
    this.toolbar = Utils.$('.archive-toolbar');
    if (!this.btn || !this.dropdown) return;
    this._bindEvents();
    this._syncPagination();
  },

  _bindEvents() {
    this._clickHandler = (e) => {
      e.stopPropagation();
      this._toggle();
    };
    this._docClickHandler = () => this._close();
    this._docKeyHandler = (e) => {
      if (e.key === 'Escape' && this._isOpen()) this._close();
    };

    Utils.on(this.btn, 'click', this._clickHandler);
    Utils.on(document, 'click', this._docClickHandler);
    Utils.on(document, 'keydown', this._docKeyHandler);
  },

  _unbindEvents() {
    if (this._clickHandler) {
      if (this.btn) Utils.off(this.btn, 'click', this._clickHandler);
      this._clickHandler = null;
    }
    if (this._docClickHandler) {
      Utils.off(document, 'click', this._docClickHandler);
      this._docClickHandler = null;
    }
    if (this._docKeyHandler) {
      Utils.off(document, 'keydown', this._docKeyHandler);
      this._docKeyHandler = null;
    }
  },

  _toggle() {
    if (!this.dropdown || !this.btn) return;
    const willOpen = !this._isOpen();
    this.dropdown.classList.toggle('open', willOpen);
    this.btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  },

  _close() {
    if (!this.dropdown || !this.btn) return;
    this.dropdown.classList.remove('open');
    this.btn.setAttribute('aria-expanded', 'false');
  },

  _isOpen() {
    return this.dropdown ? this.dropdown.classList.contains('open') : false;
  },

  _syncPagination() {
    if (!this.toolbar) return;
    const sort = this.toolbar.dataset.sort;
    if (!sort || sort === 'newest') return;

    const pagination = Utils.$('.pagination');
    if (!pagination) return;

    Utils.$$('a', pagination).forEach(link => {
      try {
        const url = new URL(link.href, window.location.href);
        url.searchParams.set('sort', sort);
        link.href = url.toString();
      } catch (e) {
        // ignore malformed URLs
      }
    });
  }
};

// ============================================================
// 27. 作者页编辑资料模态框管理器
// ============================================================
const ProfileModalManager = {
  init() {
    this._bindEvents();
    this._updateCount();
  },

  _bindEvents() {
    if (this._eventsBound) return;
    this._eventsBound = true;

    // 使用事件委托，避免 PJAX 替换 DOM 后事件失效
    Utils.on(document, 'click', (e) => {
      const openBtn = e.target.closest('.author-actions .btn-outline');
      if (openBtn) {
        e.preventDefault();
        this.open();
        return;
      }

      const modal = Utils.$('#profile-modal');
      if (!modal || !modal.classList.contains('is-open')) return;

      if (e.target.closest('.profile-modal-backdrop') || e.target.closest('.profile-modal-close')) {
        e.preventDefault();
        this.close();
      }
    });

    Utils.on(document, 'input', (e) => {
      if (e.target.id === 'profile-bio-input') {
        this._updateCount();
      } else if (e.target.id === 'profile-avatar-input') {
        this._updatePreview(e.target, Utils.$('.profile-modal-avatar'));
      } else if (e.target.id === 'profile-cover-input') {
        this._updatePreview(e.target, Utils.$('.profile-modal-cover img'));
      }
    });

    Utils.on(document, 'keydown', (e) => {
      if (e.key === 'Escape') {
        const modal = Utils.$('#profile-modal');
        if (modal && modal.classList.contains('is-open')) {
          this.close();
        }
      }
    });
  },

  open() {
    const modal = Utils.$('#profile-modal');
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setTimeout(() => Utils.$('#profile-name-input')?.focus(), 50);
  },

  close() {
    const modal = Utils.$('#profile-modal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  },

  _updateCount() {
    const bioInput = Utils.$('#profile-bio-input');
    const charCount = Utils.$('.profile-modal-char-count');
    if (!bioInput || !charCount) return;
    const len = bioInput.value.length;
    charCount.textContent = len + ' / 300';
    charCount.style.color = len > 290 ? 'var(--danger)' : 'var(--text-muted)';
  },

  _updatePreview(input, preview) {
    if (!input || !preview) return;
    const url = input.value.trim();
    if (url) {
      preview.src = url;
    }
  }
};

// ============================================================
// 作者页评论加载更多
// ============================================================
const AuthorCommentsManager = {
  init() {
    this._bindEvents();
  },

  _bindEvents() {
    if (this._eventsBound) return;
    this._eventsBound = true;

    Utils.on(document, 'click', (e) => {
      const btn = e.target.closest('.author-comments-more');
      if (!btn || !btn.closest('.author-tabs-panel')) return;
      e.preventDefault();
      this._loadMore(btn);
    });
  },

  _loadMore(btn) {
    if (btn.disabled || btn.classList.contains('is-loading')) return;

    const url = btn.dataset.url || '';
    const nextPage = parseInt(btn.dataset.nextPage, 10) || 1;
    const totalPages = parseInt(btn.dataset.totalPages, 10) || 1;

    if (!url || nextPage > totalPages) {
      btn.style.display = 'none';
      return;
    }

    btn.disabled = true;
    btn.classList.add('is-loading');

    const fetchUrl = url + (url.includes('?') ? '&' : '?') + 'xpro_ajax=comments';

    fetch(fetchUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then((response) => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
      })
      .then((html) => {
        const list = btn.previousElementSibling;
        if (!list || !list.classList.contains('author-comments-list')) {
          throw new Error('Comments list container not found');
        }

        const temp = document.createElement('div');
        temp.innerHTML = html;
        const items = temp.querySelectorAll('.comment-item');
        if (items.length === 0) {
          btn.style.display = 'none';
          return;
        }
        items.forEach((item) => list.appendChild(item));

        const newNextPage = nextPage + 1;
        if (newNextPage > totalPages) {
          btn.style.display = 'none';
        } else {
          btn.dataset.nextPage = String(newNextPage);
          const newUrl = url.replace(/([?&])cpage=\d+/, '$1cpage=' + newNextPage);
          btn.dataset.url = newUrl;
          btn.disabled = false;
          btn.classList.remove('is-loading');
        }
      })
      .catch((err) => {
        console.error('[AuthorCommentsManager] Load more failed:', err);
        btn.disabled = false;
        btn.classList.remove('is-loading');
        if (typeof SnackbarManager !== 'undefined') {
          SnackbarManager.show('加载失败，请稍后重试', 'danger');
        }
      });
  }
};

// ============================================================
// 文章卡片：点击空白处通过 PJAX 加载进入文章
// ============================================================
const ArticleCardManager = {
  _eventsBound: false,
  ghost: null,

  init() {
    if (this._eventsBound) return;
    this._eventsBound = true;
    Utils.on(document, 'click', (e) => {
      if (!e.target || !e.target.closest) return;
      // 交互元素（链接/按钮/图库）不拦截，保持原有行为
      if (e.target.closest('a, button')) return;
      const card = e.target.closest('.card[data-href], .comment-item[data-href]');
      if (!card) return;
      // 修饰键或非左键点击不处理
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      // 拖拽选择文本时不触发跳转
      const selection = window.getSelection ? window.getSelection().toString() : '';
      if (selection && selection.trim()) return;
      const href = card.getAttribute('data-href');
      if (!href || href === '#') return;
      e.preventDefault();
      this._navigate(href);
    });
  },

  _navigate(href) {
    if (window.xproSwup) {
      if (!this.ghost || !document.body.contains(this.ghost)) {
        this.ghost = Utils.create('a', {
          id: '__pjax_card_trigger',
          href: '/',
          'aria-hidden': 'true',
          tabindex: '-1',
          style: 'display:none;'
        });
        document.body.appendChild(this.ghost);
      }
      this.ghost.href = href;
      this.ghost.click();
    } else {
      window.location.href = href;
    }
  }
};

// ============================================================
// 模块分组（适配 Swup PJAX）
// ============================================================
const AppModules = {
  // 全局常驻模块：仅页面首次加载执行，Swup 切换不重复初始化
  global: [
    ThemeManager,
    ScrollManager,
    MobileMenuManager,
    SidePanelManager,
    SearchManager,
    ProfileCardManager,
    AccordionManager,
    SnackbarManager,
    DownloadManager,
    ArticleCardManager
  ],
  // 内容区模块：每次 Swup 局部刷新后必须重新初始化
  content: [
    ArchiveSortManager,
    CarouselManager,
    LikeManager,
    CommentManager,
    TOCManager,
    CollapseManager,
    TabsManager,
    GitHubCardManager,
    VoteManager,
    FancyboxManager,
    BiliCardManager,
    ProfileModalManager,
    NoticeManager,
    MusicPlayerManager,
    AuthorCommentsManager,
    CodeBlockManager
  ],
  // 执行一组管理器 init，捕获异常防止单个模块崩溃阻塞全部
  runGroup(list) {
    list.forEach(mod => {
      try {
        mod.init();
      } catch (err) {
        console.error(`[Swup Module Error] init:`, err);
      }
    });
  }
};

// ============================================================
// 28. 应用启动器
// ============================================================
const App = {
  start() {
    // 首屏一次性执行全部模块
    AppModules.runGroup(AppModules.global);
    AppModules.runGroup(AppModules.content);
  }
};

// ============================================================
// Swup 初始化入口
// ============================================================
function initSwup() {
  const swup = new Swup({
    containers: ['#main-content', '.side-panel'],
    animateHistoryBrowsing: true,
    animationSelector: false,
    // 排除带 data-fancybox / data-no-swup / target="_blank" 的链接，避免 PJAX 抢占导致直接跳转
    linkSelector: 'a[href]:not([data-fancybox]):not([data-no-swup]):not([target="_blank"])',
    plugins: [
      new SwupPreloadPlugin(),
      new SwupHeadPlugin(),
      new SwupScrollPlugin({ animateScroll: true })
    ]
  });
  window.xproSwup = swup;

  // ============== PJAX 进度条 ==============
  const bar = Utils.$('#loadingProgress');
  if (bar) {
    let progressTimer = null;
    let finishTimer = null;
    let safetyTimer = null;

    function startProgress() {
      clearTimeout(progressTimer);
      clearTimeout(finishTimer);
      clearTimeout(safetyTimer);
      bar.style.transition = 'none';
      bar.style.width = '0%';
      bar.style.opacity = '';
      bar.classList.remove('is-loading');
      void bar.offsetHeight;
      bar.classList.add('is-loading');
      requestAnimationFrame(() => {
        bar.style.transition = 'width 0.2s ease-out';
        bar.style.width = '60%';
      });
      progressTimer = setTimeout(() => {
        bar.style.transition = 'width 0.15s ease-out';
        bar.style.width = '85%';
        progressTimer = setTimeout(() => {
          bar.style.transition = 'width 2.5s linear';
          bar.style.width = '95%';
        }, 300);
      }, 400);
      safetyTimer = setTimeout(() => {
        finishProgressToFull().then(() => fadeOutProgress());
      }, 6000);
    }

    function finishProgressToFull() {
      return new Promise((resolve) => {
        if (!bar) { resolve(); return; }
        clearTimeout(progressTimer);
        clearTimeout(safetyTimer);
        bar.style.transition = 'width 0.15s ease-out';
        bar.style.width = '100%';
        const onEnd = (e) => {
          if (e && e.propertyName !== 'width') return;
          bar.removeEventListener('transitionend', onEnd);
          resolve();
        };
        bar.addEventListener('transitionend', onEnd);
        finishTimer = setTimeout(() => {
          bar.removeEventListener('transitionend', onEnd);
          resolve();
        }, 200);
      });
    }

    function fadeOutProgress() {
      if (!bar) return;
      clearTimeout(finishTimer);
      clearTimeout(safetyTimer);
      if (parseFloat(bar.style.width) < 99.5) {
        bar.style.transition = 'none';
        bar.style.width = '100%';
        void bar.offsetHeight;
      }
      finishTimer = setTimeout(() => {
        bar.classList.remove('is-loading');
        bar.style.transition = 'width 0.3s ease, opacity 0.3s ease';
        bar.style.width = '0%';
        const onEnd = () => {
          bar.removeEventListener('transitionend', onEnd);
          bar.style.transition = '';
          bar.style.width = '';
          bar.style.opacity = '';
        };
        bar.addEventListener('transitionend', onEnd);
        setTimeout(() => {
          bar.removeEventListener('transitionend', onEnd);
          bar.style.transition = '';
          bar.style.width = '';
          bar.style.opacity = '';
        }, 350);
      }, 250);
    }

    swup.hooks.on('visit:start', () => startProgress());
    swup.hooks.on('page:load', async () => { await finishProgressToFull(); });
    swup.hooks.on('page:view', () => fadeOutProgress());
    swup.hooks.on('visit:end', () => fadeOutProgress());
  }

  // ============== 密码保护文章：显示/隐藏密码切换（事件委托，PJAX 安全） ==============
  if (!window._xproPasswordToggleBound) {
    window._xproPasswordToggleBound = true;
    Utils.on(document, 'click', function (e) {
      var toggleBtn = e.target.closest('.post-password-toggle');
      if (!toggleBtn) { return; }
      var form = toggleBtn.closest('.post-password-form');
      if (!form) { return; }
      var pwdInput = form.querySelector('.post-password-input');
      if (!pwdInput) { return; }
      var eyeIcon = toggleBtn.querySelector('.icon-eye');
      var eyeOffIcon = toggleBtn.querySelector('.icon-eye-off');
      var isShow = toggleBtn.getAttribute('aria-pressed') === 'true';
      if (!isShow) {
        pwdInput.type = 'text';
        if (eyeIcon) { eyeIcon.classList.add('hidden'); }
        if (eyeOffIcon) { eyeOffIcon.classList.remove('hidden'); }
        toggleBtn.setAttribute('aria-label', '隐藏密码');
        toggleBtn.setAttribute('aria-pressed', 'true');
      } else {
        pwdInput.type = 'password';
        if (eyeIcon) { eyeIcon.classList.remove('hidden'); }
        if (eyeOffIcon) { eyeOffIcon.classList.add('hidden'); }
        toggleBtn.setAttribute('aria-label', '显示密码');
        toggleBtn.setAttribute('aria-pressed', 'false');
      }
    });
  }

  // ============== 404 页：搜索框回车（PJAX 搜索） ==============
  Utils.on(document, 'keydown', (e) => {
    if (e.key !== 'Enter') return;
    const input = e.target.closest('#search-404-input');
    if (!input) return;
    e.preventDefault();
    const term = input.value.trim();
    if (!term) return;
    SearchManager.doSearch(term);
  });

  // ============== 密码保护文章表单 PJAX 支持 ==============
  if (!window._xproPasswordFormBound) {
    window._xproPasswordFormBound = true;
    Utils.on(document, 'submit', function (e) {
      var form = e.target.closest('.post-password-form');
      if (!form) { return; }

      e.preventDefault();

      // 空密码校验
      var passwordInput = form.querySelector('.post-password-input');
      var password = passwordInput ? passwordInput.value.trim() : '';
      if (!password) {
        if (typeof SnackbarManager !== 'undefined') {
          SnackbarManager.show('请输入密码', 'warning');
        }
        return;
      }

      startProgress();

      var submitBtn = form.querySelector('.post-password-submit');
      if (submitBtn) {
        submitBtn.textContent = '验证中…';
        submitBtn.disabled = true;
      }

      var formData = new FormData(form);
      fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        redirect: 'follow'
      })
        .then(function (response) { return response.text().then(function (html) { return { html: html, response: response }; }); })
        .then(function (result) {
          var html = result.html;
          var parser = new DOMParser();
          var newDoc = parser.parseFromString(html, 'text/html');
          var newMain = newDoc.querySelector('#main-content');

          // 响应中没有 #main-content（密码错误时 Typecho 抛出异常，返回错误页）
          if (!newMain) {
            if (typeof SnackbarManager !== 'undefined') {
              SnackbarManager.show('密码错误，请重试', 'danger');
            }
            if (submitBtn) {
              submitBtn.textContent = '验证密码';
              submitBtn.disabled = false;
            }
            fadeOutProgress();
            return;
          }

          // 密码错误兜底（响应中包含密码表单，说明验证未通过）
          if (newMain.querySelector('.post-password-form')) {
            if (typeof SnackbarManager !== 'undefined') {
              SnackbarManager.show('密码错误，请重试', 'danger');
            }
            if (submitBtn) {
              submitBtn.textContent = '验证密码';
              submitBtn.disabled = false;
            }
            fadeOutProgress();
            return;
          }

          return finishProgressToFull().then(function () {
            var currentMain = Utils.$('#main-content');
            if (currentMain) {
              currentMain.innerHTML = newMain.innerHTML;
              var currentSide = Utils.$('.side-panel');
              var newSide = newDoc.querySelector('.side-panel');
              if (currentSide && newSide) {
                currentSide.innerHTML = newSide.innerHTML;
              }
              document.title = newDoc.title;
              AppModules.runGroup(AppModules.content);
              fadeOutProgress();
              window.scrollTo({ top: 0, behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth' });
            }
          });
        })
        .catch(function (err) {
          console.error('[Swup] Password form submission failed:', err);
          if (typeof SnackbarManager !== 'undefined') {
            SnackbarManager.show('验证失败，请稍后重试', 'danger');
          }
          if (submitBtn) {
            submitBtn.textContent = '验证密码';
            submitBtn.disabled = false;
          }
          fadeOutProgress();
        });
    });
  }

  // 页面切换前销毁所有 Fancybox 实例，防止多实例冲突
  swup.hooks.on('visit:start', () => {
    if (window.Fancybox?.getInstances) {
      window.Fancybox.getInstances().forEach(inst => inst.destroy());
    }
  });

  // 更新侧边栏菜单高亮（PJAX 后 sidebar 未重新渲染）
  function updateSidebarActiveState() {
    const normalizePath = (url) => {
      try {
        const path = new URL(url, window.location.origin).pathname.replace(/\/$/, '');
        return path || '/';
      } catch {
        return '';
      }
    };

    const currentPath = normalizePath(window.location.href);
    const isActiveUrl = (href) => {
      if (!href || href === '#' || href.toLowerCase().startsWith('javascript:')) {
        return false;
      }
      return normalizePath(href) === currentPath;
    };

    document.querySelectorAll('.nav-link').forEach((link) => {
      if (link.tagName === 'A') {
        link.classList.toggle('active', isActiveUrl(link.getAttribute('href')));
        return;
      }

      if (!link.hasAttribute('data-has-submenu')) {
        return;
      }

      let isActive = isActiveUrl(link.getAttribute('data-url'));
      const submenu = link.nextElementSibling;
      if (submenu) {
        submenu.querySelectorAll('a.nav-link').forEach((subLink) => {
          const subActive = isActiveUrl(subLink.getAttribute('href'));
          subLink.classList.toggle('active', subActive);
          if (subActive) {
            isActive = true;
          }
        });
      }
      link.classList.toggle('active', isActive);
    });
  }

  // ============== 评论锚点：等布局稳定后滚动到位并触发高亮 ==============
  // 文章较长时，图片懒加载会使页面高度持续变化，Swup 在 page:view 时
  // 立即按当前高度滚动锚点，导致只滚到一半。这里轮询目标元素位置，
  // 连续两次稳定（或超时 2s）后再滚动，并触发 commentHighlight 动画。
  function triggerCommentHighlight(el) {
    el.classList.remove('highlight');
    el.style.animation = 'none';
    void el.offsetWidth;
    el.classList.add('highlight');
    el.style.animation = 'commentHighlight 2s ease';
    setTimeout(function () {
      el.classList.remove('highlight');
      // 保持内联 none，避免 animation-name 回退触发 fadeInUp 重播
      el.style.animation = 'none';
    }, 2100);
  }

  function handleCommentHash() {
    const hash = window.location.hash;
    if (!hash || !/^#comment-/.test(hash)) return;
    const target = document.getElementById(hash.slice(1));
    if (!target) return;
    let attempts = 20;
    let lastTop = -1;
    let stableCount = 0;
    const check = function () {
      const absTop = target.getBoundingClientRect().top + window.pageYOffset;
      if (absTop === lastTop) {
        stableCount++;
      } else {
        stableCount = 0;
        lastTop = absTop;
      }
      if (stableCount >= 2 || attempts <= 0) {
        window.scrollTo({
          top: Math.max(0, absTop - 80),
          behavior: Utils.prefersReducedMotion() ? 'auto' : 'smooth'
        });
        triggerCommentHighlight(target);
        return;
      }
      attempts--;
      setTimeout(check, 100);
    };
    setTimeout(check, 150);
  }

  // 新 DOM 渲染完成，重载所有内容区模块
  swup.hooks.on('page:view', () => {
    AppModules.runGroup(AppModules.content);
    updateSidebarActiveState();
    handleCommentHash();
    // 如有代码高亮可在此追加 Prism.highlightAll()
  });

  // 首次加载（非 PJAX 导航）时同样处理评论锚点
  handleCommentHash();

  return swup;
}

// ============================================================
// 全局入口执行
// ============================================================
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    App.start();
    initSwup();
  });
} else {
  App.start();
  initSwup();
}
