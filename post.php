<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 文章页模板
 *
 * @package XPro
 */

$this->need('header.php');

$archive     = $this;
$author      = $archive->author;
$authorName  = $author->screenName;
$authorMail  = $author->mail;
$authorUrl   = $author->permalink;
$date        = XPro::formatDate((int) $archive->created);
$readingMin  = XPro::readingTime($archive, 300, true);
$viewCount   = XPro::getPostView($archive, 1);
$likeCount   = XPro::getPostLikes((int) $archive->cid);
$hasLiked    = XPro::hasUserLiked((int) $archive->cid);
$permalink   = $archive->permalink;
$categories  = $archive->categories ?? [];
$tags        = $archive->tags ?? [];

$adjacent    = new AdjacentPosts($archive);
$prevPost    = $adjacent->getPrev();
$nextPost    = $adjacent->getNext();

$adjacentWidget = static function (?array $row): ?\Widget\Contents\From {
    if ($row === null) {
        return null;
    }
    $widget = \Widget\Contents\From::allocWithAlias('adjacent:' . $row['cid'], ['cid' => $row['cid']]);
    if ($widget->have()) {
        $widget->next();
        return $widget;
    }
    return null;
};

$prevWidget = $adjacentWidget($prevPost);
$nextWidget = $adjacentWidget($nextPost);

$adjacentBg = static function (?\Widget\Contents\From $post): string {
    if ($post === null) {
        return '';
    }
    $cfg = ThumbnailHelper::getCardImageConfig($post);
    $url = $cfg['displayImages'][0] ?? '';
    return $url !== '' ? 'background-image:url(' . XPro::esc($url, true) . ')' : '';
};
?>
<!-- ==================== 中间主内容 ==================== -->
<main id="main-content" class="main-content">
<!-- 面包屑导航 -->
<nav class="post-breadcrumb" aria-label="面包屑导航">
    <a href="<?= $archive->options->siteUrl; ?>">首页</a>
    <?php foreach ($categories as $category) { ?>
    <a href="<?= $category['permalink']; ?>"><?php XPro::esc($category['name']); ?></a>
    <?php } ?>
    <span class="post-breadcrumb-current">正文</span>
</nav>
<article class="post-article" aria-label="文章正文">
    <!-- 文章头部 -->
    <header class="post-header">
        <h1 class="post-title"><?php XPro::esc($archive->title); ?></h1>
        <div class="comment-item post-meta-bar">
            <img src="<?php XPro::avatar($authorMail, 100, false, (int) $archive->authorId); ?>" alt="<?php XPro::esc($authorName . '的头像'); ?>" class="avatar" loading="lazy">
            <div class="comment-item-body">
                <div class="comment-item-meta">
                    <a href="<?= $authorUrl; ?>" class="comment-item-author"><?php XPro::esc($authorName); ?></a>
                    <time class="comment-item-date" datetime="<?= $date; ?>"><?= $date; ?></time>
                </div>
                <div class="comment-item-row">
                    <span><?= $readingMin; ?> 分钟</span>
                    <span class="dot" aria-hidden="true">·</span>
                    <span><?= $viewCount; ?> 浏览</span>
                    <span class="dot" aria-hidden="true">·</span>
                    <a href="#comments"><?= (int) $archive->commentsNum; ?> 评论</a>
                </div>
            </div>
        </div>
    </header>
    <!-- 文章内容 -->
    <div class="post-content">
        <?php if ($this->hidden) { ?>
        <!-- 密码保护状态 -->
        <div class="post-password-protect" aria-label="密码保护区域">
            <div class="post-password-icon">
                <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M19 10H20C20.5523 10 21 10.4477 21 11V21C21 21.5523 20.5523 22 20 22H4C3.44772 22 3 21.5523 3 21V11C3 10.4477 3.44772 10 4 10H5V9C5 5.13401 8.13401 2 12 2C15.866 2 19 5.13401 19 9V10ZM17 10V9C17 6.23858 14.7614 4 12 4C9.23858 4 7 6.23858 7 9V10H17ZM11 14V18H13V14H11Z"></path>
                </svg>
            </div>
            <h2 class="post-password-title">本文受密码保护</h2>
            <p class="post-password-desc">请输入访问密码以查看完整内容</p>
            <form class="post-password-form" action="<?php echo Helper::Security()->getTokenUrl($this->permalink); ?>" method="post" aria-label="密码验证表单">
                <input type="hidden" name="protectCID" value="<?php $this->cid(); ?>">
                <div class="post-password-field">
                    <label for="post-password-input" class="post-password-label">访问密码</label>
                    <div class="post-password-input-wrap">
                        <input type="password" id="post-password-input" name="protectPassword" class="post-password-input" placeholder="请输入密码" aria-label="输入访问密码" autocomplete="off">
                        <button type="button" class="post-password-toggle" aria-label="显示密码" aria-pressed="false">
                            <svg class="icon icon-eye" aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M12 3C17.392 3 21.878 6.88 22.819 12C21.879 17.12 17.392 21 12 21C6.608 21 2.122 17.12 1.181 12C2.122 6.88 6.608 3 12 3ZM12 19C16.236 19 19.86 16.052 20.778 12C19.86 7.948 16.236 5 12 5C7.764 5 4.14 7.948 3.222 12C4.14 16.052 7.764 19 12 19ZM12 16.5C9.515 16.5 7.5 14.485 7.5 12C7.5 9.515 9.515 7.5 12 7.5C14.485 7.5 16.5 9.515 16.5 12C16.5 14.485 14.485 16.5 12 16.5ZM12 14.5C13.381 14.5 14.5 13.381 14.5 12C14.5 10.619 13.381 9.5 12 9.5C10.619 9.5 9.5 10.619 9.5 12C9.5 13.381 10.619 14.5 12 14.5Z"></path>
                            </svg>
                            <svg class="icon icon-eye-off hidden" aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M17.8827 19.2968C16.1814 20.3755 14.1638 21.0002 12.0003 21.0002C6.60812 21.0002 2.12215 17.1204 1.18164 12.0002C1.61832 9.62282 2.81932 7.5129 4.52047 5.93457L1.39366 2.80777L2.80788 1.39355L22.6069 21.1925L21.1927 22.6068L17.8827 19.2968ZM5.9356 7.3497C4.60673 8.56015 3.6378 10.1672 3.22278 12.0002C4.14022 16.0521 7.7646 19.0002 12.0003 19.0002C13.5997 19.0002 15.112 18.5798 16.4243 17.8384L14.396 15.8101C13.7023 16.2472 12.8808 16.5002 12.0003 16.5002C9.51498 16.5002 7.50026 14.4854 7.50026 12.0002C7.50026 11.1196 7.75317 10.2981 8.19031 9.60442L5.9356 7.3497ZM12.9139 14.328L9.67246 11.0866C9.5613 11.3696 9.50026 11.6777 9.50026 12.0002C9.50026 13.3809 10.6196 14.5002 12.0003 14.5002C12.3227 14.5002 12.6309 14.4391 12.9139 14.328ZM20.8068 16.5925L19.376 15.1617C20.0319 14.2268 20.5154 13.1586 20.7777 12.0002C19.8603 7.94818 16.2359 5.00016 12.0003 5.00016C11.1544 5.00016 10.3329 5.11773 9.55249 5.33818L7.97446 3.76015C9.22127 3.26959 10.5793 3.00016 12.0003 3.00016C17.3924 3.00016 21.8784 6.87992 22.8189 12.0002C22.5067 13.6998 21.8038 15.2628 20.8068 16.5925ZM11.7229 7.50857C11.8146 7.50299 11.9071 7.50016 12.0003 7.50016C14.4855 7.50016 16.5003 9.51488 16.5003 12.0002C16.5003 12.0933 16.4974 12.1858 16.4919 12.2775L11.7229 7.50857Z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="post-password-submit">验证密码</button>
            </form>
        </div>
        <script>
            (function () {
                const toggleBtn = document.querySelector('.post-password-toggle');
                if (!toggleBtn) return;
                const eyeIcon = toggleBtn.querySelector('.icon-eye');
                const eyeOffIcon = toggleBtn.querySelector('.icon-eye-off');
                const pwdInput = document.getElementById('post-password-input');

                toggleBtn.addEventListener('click', function () {
                    const isShow = this.getAttribute('aria-pressed') === 'true';

                    if (!isShow) {
                        pwdInput.type = 'text';
                        eyeIcon.classList.add('hidden');
                        eyeOffIcon.classList.remove('hidden');
                        this.setAttribute('aria-label', '隐藏密码');
                        this.setAttribute('aria-pressed', 'true');
                    } else {
                        pwdInput.type = 'password';
                        eyeIcon.classList.remove('hidden');
                        eyeOffIcon.classList.add('hidden');
                        this.setAttribute('aria-label', '显示密码');
                        this.setAttribute('aria-pressed', 'false');
                    }
                });
            })();
        </script>
        <?php } else { ?>
            <?php $archive->content(); ?>
        <?php } ?>
    </div>
    <!-- 文章标签 -->
    <?php if (!empty($tags)) { ?>
    <div class="post-tags">
        <?php foreach ($tags as $tag) { ?>
        <a href="<?= $tag['permalink']; ?>" class="tag-pill"><?php XPro::esc($tag['name']); ?></a>
        <?php } ?>
    </div>
    <?php } ?>
    <?php if ($this->options->showCopyright == '1') { ?>
    <!-- 版权信息 -->
    <div class="post-copyright">
        <p class="post-copyright-title"><svg class="icon" aria-hidden="true" viewbox="0 0 24 24" style="width:1.125rem;height:1.125rem;color:var(--primary);"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM13 10.5V15H14V17H10V15H11V12.5H10V10.5H13ZM12 8.5C12.5523 8.5 13 8.94772 13 9.5C13 10.0523 12.5523 10.5 12 10.5C11.4477 10.5 11 10.0523 11 9.5C11 8.94772 11.4477 8.5 12 8.5Z"></path></svg>版权声明</p>
        <p>本文采用 <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank" rel="noopener">CC BY-NC-SA 4.0</a> 协议进行许可，转载请注明出处。</p>
        <p>原文链接：<a href="<?= $permalink; ?>"><?php XPro::esc($permalink); ?></a></p>
    </div>
    <?php } ?>
    <!-- 文章底部操作栏 -->
    <div class="post-actions">
        <button class="post-action-btn like<?= $hasLiked ? ' liked' : ''; ?>" aria-label="<?= $likeCount; ?> 个赞" data-cid="<?= (int) $archive->cid; ?>">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                <path d="<?= $hasLiked ? 'M16.5 3C19.5376 3 22 5.5 22 9C22 16 14.5 20 12 21.5C9.5 20 2 16 2 9C2 5.5 4.5 3 7.5 3C9.35997 3 11 4 12 5C13 4 14.64 3 16.5 3Z' : 'M16.5 3C19.5376 3 22 5.5 22 9C22 16 14.5 20 12 21.5C9.5 20 2 16 2 9C2 5.5 4.5 3 7.5 3C9.35997 3 11 4 12 5C13 4 14.64 3 16.5 3ZM12.9339 18.6038C13.8155 18.0485 14.61 17.4955 15.3549 16.9029C18.3337 14.533 20 11.9435 20 9C20 6.64076 18.463 5 16.5 5C15.4241 5 14.2593 5.56911 13.4142 6.41421L12 7.82843L10.5858 6.41421C9.74068 5.56911 8.5759 5 7.5 5C5.55906 5 4 6.6565 4 9C4 11.9435 5.66627 14.533 8.64514 16.9029C9.39 17.4955 10.1845 18.0485 11.0661 18.6038C11.3646 18.7919 11.6611 18.9729 12 19.1752C12.3389 18.9729 12.6354 18.7919 12.9339 18.6038Z'; ?>"></path>
            </svg>
            <span class="count"><?= $likeCount ?></span>
        </button>
        <button class="post-action-btn" aria-label="分享" onclick="if(navigator.share){navigator.share({title:document.title,url:location.href});}else{navigator.clipboard.writeText(location.href).then(()=>alert('链接已复制到剪贴板'))}">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24"><path d="M13.1202 17.0228L8.92129 14.7324C8.19135 15.5125 7.15261 16 6 16C3.79086 16 2 14.2091 2 12C2 9.79086 3.79086 8 6 8C7.15255 8 8.19125 8.48746 8.92118 9.26746L13.1202 6.97713C13.0417 6.66441 13 6.33707 13 6C13 3.79086 14.7909 2 17 2C19.2091 2 21 3.79086 21 6C21 8.20914 19.2091 10 17 10C15.8474 10 14.8087 9.51251 14.0787 8.73246L9.87977 11.0228C9.9583 11.3355 10 11.6629 10 12C10 12.3371 9.95831 12.6644 9.87981 12.9771L14.0788 15.2675C14.8087 14.4875 15.8474 14 17 14C19.2091 14 21 15.7909 21 18C21 20.2091 19.2091 22 17 22C14.7909 22 13 20.2091 13 18C13 17.6629 13.0417 17.3355 13.1202 17.0228ZM6 14C7.10457 14 8 13.1046 8 12C8 10.8954 7.10457 10 6 10C4.89543 10 4 10.8954 4 12C4 13.1046 4.89543 14 6 14ZM17 8C18.1046 8 19 7.10457 19 6C19 4.89543 18.1046 4 17 4C15.8954 4 15 4.89543 15 6C15 7.10457 15.8954 8 17 8ZM17 20C18.1046 20 19 19.1046 19 18C19 16.8954 18.1046 16 17 16C15.8954 16 15 16.8954 15 18C15 19.1046 15.8954 20 17 20Z"></path></svg>
            <span>分享</span>
        </button>
        <button class="post-action-btn" aria-label="复制链接" onclick="navigator.clipboard.writeText(location.href).then(()=>alert('链接已复制到剪贴板'))">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24"><path d="M7 6V3C7 2.44772 7.44772 2 8 2H20C20.5523 2 21 2.44772 21 3V17C21 17.5523 20.5523 18 20 18H17V21C17 21.5523 16.5523 22 16 22H4C3.44772 22 3 21.5523 3 21V7C3 6.44772 3.44772 6 4 6H7ZM5 8V20H15V8H5ZM17 16H19V4H9V6H16C16.5523 6 17 6.44772 17 7V16Z"></path></svg>
            <span>复制</span>
        </button>
    </div>
    <!-- 上下篇导航 -->
    <nav class="post-nav-section" aria-label="文章导航">
        <div class="post-nav-grid">
            <?php if ($prevPost) { ?>
            <a href="<?= $prevPost['permalink']; ?>" class="base-post post-nav-prev" rel="prev">
                <?php $prevBg = $adjacentBg($prevWidget); ?>
                <?php if ($prevBg !== '') { ?>
                <div class="base-post-bg" style="<?= $prevBg; ?>" aria-hidden="true"></div>
                <?php } ?>
                <div class="base-post-main">
                    <div class="base-post-top">
                        <span class="post-nav-label">上一篇</span>
                    </div>
                    <p class="base-post-title"><?php XPro::esc($prevPost['title']); ?></p>
                </div>
            </a>
            <?php } else { ?>
            <span class="base-post post-nav-prev is-empty">
                <div class="base-post-main">
                    <div class="base-post-top"><span class="post-nav-label">上一篇</span></div>
                    <p class="base-post-title">没有更多了</p>
                </div>
            </span>
            <?php } ?>

            <?php if ($nextPost) { ?>
            <a href="<?= $nextPost['permalink']; ?>" class="base-post post-nav-next" rel="next">
                <?php $nextBg = $adjacentBg($nextWidget); ?>
                <?php if ($nextBg !== '') { ?>
                <div class="base-post-bg" style="<?= $nextBg; ?>" aria-hidden="true"></div>
                <?php } ?>
                <div class="base-post-main">
                    <div class="base-post-top">
                        <span class="post-nav-label">下一篇</span>
                    </div>
                    <p class="base-post-title"><?php XPro::esc($nextPost['title']); ?></p>
                </div>
            </a>
            <?php } else { ?>
            <span class="base-post post-nav-next is-empty">
                <div class="base-post-main">
                    <div class="base-post-top"><span class="post-nav-label">下一篇</span></div>
                    <p class="base-post-title">没有更多了</p>
                </div>
            </span>
            <?php } ?>
        </div>
    </nav>
    <!-- 评论区 -->
    <?php if (!$this->hidden) { $this->need('includes/comments.php'); } ?>
</article>
</main>
<?php $this->need('includes/aside.php'); ?>
<?php $this->need('footer.php'); ?>
