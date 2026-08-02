<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 404 错误页模板
 *
 * 由 404.html 静态演示页改造而来。
 *
 * @package XPro
 */

$this->need('header.php');

$siteUrl    = rtrim((string) $this->options->siteUrl, '/');
?>
<!-- ==================== 中间主内容：404 ==================== -->
<main id="main-content" class="main-content">
    <style>
        .page-404 {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 64px);
            padding: 3rem 1.5rem;
            text-align: center;
            position: relative;
        }

        /* 404 错误代码（背景大字） */
        .page-404-code {
            font-family: var(--font-display);
            font-size: clamp(6rem, 15vw, 12rem);
            font-weight: 800;
            line-height: 1;
            color: var(--primary);
            opacity: 0.08;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            user-select: none;
            pointer-events: none;
            z-index: 0;
            letter-spacing: -0.05em;
        }

        /* 404 图标 */
        .page-404-icon {
            width: 5rem;
            height: 5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
            position: relative;
            z-index: 1;
            animation: icon-bounce 2s ease-in-out infinite;
        }

        @keyframes icon-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .page-404-icon .icon {
            width: 100%;
            height: 100%;
        }

        /* 404 标题 */
        .page-404-title {
            font-family: var(--font-display);
            font-size: clamp(1.5rem, 4vw, 2.25rem);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
            line-height: 1.3;
        }

        /* 404 描述 */
        .page-404-desc {
            font-size: 0.9375rem;
            line-height: 1.7;
            color: var(--text-secondary);
            max-width: 28rem;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        /* 搜索框（404页面专用） */
        .page-404-search {
            position: relative;
            width: 100%;
            max-width: 28rem;
            margin-bottom: 2rem;
            z-index: 1;
        }

        .page-404-search-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border-radius: var(--radius-pill);
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.9375rem;
            font-family: inherit;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        [data-theme="dark"] .page-404-search-input {
            background: rgba(20, 20, 20, 0.65);
        }

        .page-404-search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .page-404-search-input::placeholder {
            color: var(--text-muted);
        }

        .page-404-search .icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.125rem;
            height: 1.125rem;
            color: var(--text-muted);
            pointer-events: none;
            z-index: 1;
        }

        /* 操作按钮组 */
        .page-404-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            margin-bottom: 3rem;
            position: relative;
            z-index: 1;
        }

        .page-404-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-pill);
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: inherit;
        }

        .page-404-btn:hover {
            background: var(--surface-3);
            border-color: var(--primary-light);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .page-404-btn.primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .page-404-btn.primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--primary-shadow);
        }

        .page-404-btn .icon {
            width: 1.125rem;
            height: 1.125rem;
            flex-shrink: 0;
        }

        /* 推荐链接区域 */
        .page-404-suggest {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 32rem;
        }

        .page-404-suggest-title {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .page-404-suggest-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .page-404-suggest-link {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-pill);
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--surface-2);
            border: 1px solid var(--border-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-404-suggest-link:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }

        /* 响应式 */
        @media (max-width: 768px) {
            .page-404 {
                padding: 2rem 1rem;
                min-height: calc(100vh - 64px);
            }

            .page-404-icon {
                width: 4rem;
                height: 4rem;
                margin-bottom: 1rem;
            }

            .page-404-actions {
                flex-direction: column;
                width: 100%;
                max-width: 20rem;
            }

            .page-404-btn {
                justify-content: center;
                width: 100%;
            }

            .page-404-suggest-list {
                gap: 0.375rem;
            }

            .page-404-suggest-link {
                padding: 0.375rem 0.75rem;
                font-size: 0.8125rem;
            }
        }
    </style>

    <nav class="post-breadcrumb error-breadcrumb" aria-label="面包屑导航">
        <a href="<?= $siteUrl; ?>">首页</a>
        <span class="post-breadcrumb-current" aria-current="page">404 · 页面未找到</span>
    </nav>

    <article class="page-404" aria-label="404 错误页">
        <!-- 背景大字 -->
        <div class="page-404-code" aria-hidden="true">404</div>

        <!-- 幽灵图标 -->
        <div class="page-404-icon" aria-hidden="true">
            <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C7.58172 2 4 5.58172 4 10V22H20V10C20 5.58172 16.4183 2 12 2ZM12 4C15.3137 4 18 6.68629 18 10V20H6V10C6 6.68629 8.68629 4 12 4ZM9 9C9.55228 9 10 9.44772 10 10C10 10.5523 9.55228 11 9 11C8.44772 11 8 10.5523 8 10C8 9.44772 8.44772 9 9 9ZM15 9C15.5523 9 16 9.44772 16 10C16 10.5523 15.5523 11 15 11C14.4477 11 14 10.5523 14 10C14 9.44772 14.4477 9 15 9ZM12 14C10.8954 14 10 14.8954 10 16H14C14 14.8954 13.1046 14 12 14Z"/>
            </svg>
        </div>

        <h1 class="page-404-title">这里什么都没有</h1>
        <p class="page-404-desc">你访问的页面可能已经搬家、被删除，或者从未存在过。<br>别担心，你可以搜索一下，或者去其他地方看看。</p>

        <!-- 搜索框 -->
        <div class="page-404-search">
            <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                <path d="M18.031 16.6168L22.3137 20.8995L20.8995 22.3137L16.6168 18.031C15.0769 19.263 13.124 20 11 20C6.032 20 2 15.968 2 11C2 6.032 6.032 2 11 2C15.968 2 20 6.032 20 11C20 13.124 19.263 15.0769 18.031 16.6168ZM16.0247 15.8748C17.2475 14.6146 18 12.8956 18 11C18 7.1325 14.8675 4 11 4C7.1325 4 4 7.1325 4 11C4 14.8675 7.1325 18 11 18C12.8956 18 14.6146 17.2475 15.8748 16.0247L16.0247 15.8748Z"></path>
            </svg>
            <input type="search" class="page-404-search-input" placeholder="搜索文章..." aria-label="搜索文章" id="search-404-input">
        </div>

        <!-- 操作按钮 -->
        <div class="page-404-actions">
            <a href="<?= $siteUrl; ?>" class="page-404-btn primary">
                <svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M13 19H19V9.97815L12 4.53371L5 9.97815V19H11V13H13V19ZM21 20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V9.48907C3 9.18048 3.14247 8.88917 3.38606 8.69972L11.3861 2.47749C11.7472 2.19663 12.2528 2.19663 12.6139 2.47749L20.6139 8.69972C20.8575 8.88917 21 9.18048 21 9.48907V20Z"/></svg>
                回到首页
            </a>
        </div>

        <!-- 推荐链接 -->
        <div class="page-404-suggest">
            <p class="page-404-suggest-title">热搜标签</p>
            <div class="tag-flow">
                <?php HotSearch::render(6, [
                    'wrapper' => '{items}',
                    'item'    => '<a href="{url}" class="tag-pill">{keyword}</a>',
                    'empty'   => '<span>暂无热搜标签</span>',
                ]); ?>
            </div>
        </div>
    </article>

</main>
<!-- ==================== 侧边栏 ==================== -->
<?php $this->need('includes/aside.php'); ?>
<!-- ==================== 页脚 ==================== -->
<?php $this->need('footer.php'); ?>
