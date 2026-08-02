<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 作者页模板
 *
 * @package XPro
 */

$this->need('header.php');

$author       = $this->author;
$uid           = (int) $author->uid;
$name          = (string) $author->screenName;
$mail          = (string) $author->mail;
$url           = (string) $author->url;
$permalink     = (string) $author->permalink;
$customAvatar  = XPro::getUserAvatar($uid);
$avatar        = XPro::avatar($mail, 100, true, $uid);
$cover         = XPro::getUserCover($uid);
$bio           = XPro::getUserBio($uid);

$currentUser = $this->user;
$isSelf      = $currentUser->hasLogin() && (int) $currentUser->uid === $uid;

$themeUrl     = rtrim((string) $this->options->themeUrl, '/');
$defaultCover = $themeUrl . '/assets/images/noscreen.png';
$displayCover = $cover !== '' ? $cover : $defaultCover;

$postsCount = $this->getTotal();

$db = Typecho\Db::get();

$commentsPerPage = max(1, (int) $this->options->commentsPageSize);
$commentPage     = max(1, (int) $this->request->get('cpage', 1));

$commentsTotal = (int) $db->fetchObject(
    $db->select(['COUNT(coid)' => 'num'])
        ->from('table.comments')
        ->where('authorId = ?', $uid)
        ->where('status = ?', 'approved')
)->num;

$commentPages = (int) ceil($commentsTotal / $commentsPerPage);
if ($commentPage > $commentPages && $commentPages > 0) {
    $commentPage = $commentPages;
}

$isCommentsActive = $this->request->get('cpage') !== null;

$authorComments = $db->fetchAll(
    $db->select(
        'table.comments.coid',
        'table.comments.cid',
        'table.comments.author',
        'table.comments.mail',
        'table.comments.text',
        'table.comments.created',
        'table.contents.title',
        'table.contents.slug',
        'table.contents.type',
        'table.contents.created as contentCreated'
    )
        ->from('table.comments')
        ->join('table.contents', 'table.contents.cid = table.comments.cid')
        ->where('table.comments.authorId = ?', $uid)
        ->where('table.comments.status = ?', 'approved')
        ->order('table.comments.created', Typecho\Db::SORT_DESC)
        ->limit($commentsPerPage)
        ->offset(($commentPage - 1) * $commentsPerPage)
);

$buildCommentPageUrl = static function (int $page) use ($permalink): string {
    $query = $_GET;
    unset($query['xpro_ajax']);
    if ($page >= 1) {
        $query['cpage'] = $page;
    } else {
        unset($query['cpage']);
    }
    $queryString = http_build_query($query);
    return $queryString !== '' ? $permalink . '?' . $queryString : $permalink;
};

$renderCommentItem = function (array $comment) use ($avatar, $name): void {
    $coid          = (int) $comment['coid'];
    $commentText   = XPro::excerpt((string) $comment['text'], 120);
    $commentTime   = (int) $comment['created'];
    $postTitle     = (string) $comment['title'];
    $postPermalink = '';
    if (!empty($comment['type'])) {
        $postPermalink = Typecho\Common::url(
            Typecho\Router::url((string) $comment['type'], $comment),
            $this->options->index
        );
    }
    $commentLink = $postPermalink !== '' ? $postPermalink . '#comment-' . $coid : '#';
    ?>
    <article class="comment-item" aria-label="<?php XPro::esc($name . '的评论'); ?>">
        <img src="<?php XPro::esc($avatar); ?>" alt="<?php XPro::esc($name . '的头像'); ?>" class="avatar" loading="lazy">
        <div class="comment-item-body">
            <div class="comment-item-meta">
                <span class="comment-item-author"><?php XPro::esc($name); ?></span>
                <span class="comment-item-badge">作者</span>
                <time class="comment-item-date" datetime="<?php XPro::esc(XPro::formatIsoDate($commentTime)); ?>"><?php XPro::esc(XPro::relativeTime($commentTime)); ?></time>
            </div>
            <p class="comment-item-text"><?php XPro::esc($commentText); ?></p>
            <a class="comment-item-quote" href="<?php XPro::esc($commentLink); ?>"><?php XPro::esc($postTitle); ?></a>
        </div>
    </article>
    <?php
};

$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
    isset($_GET['xpro_ajax']) &&
    $_GET['xpro_ajax'] === 'comments'
);
if ($isAjax) {
    foreach ($authorComments as $comment) {
        $renderCommentItem($comment);
    }
    exit;
}

?>
<!-- ==================== 中间主内容 ==================== -->
<main id="main-content" class="main-content author-page">
    <!-- 粘性页面头部 -->
    <div class="author-page-header" role="region" aria-label="作者页面头部">
        <button class="author-back" id="author-back" type="button" aria-label="返回上一页" onclick="history.back()">
            <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                <path d="M19 12H5"></path>
                <path d="M12 19l-7-7 7-7"></path>
            </svg>
        </button>
        <nav class="breadcrumb" aria-label="面包屑导航">
            <a href="<?php $this->options->siteUrl(); ?>" class="breadcrumb-item">首页</a>
            <span class="breadcrumb-sep" aria-hidden="true">
                <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                    <path d="M9 6L15 12L9 18"></path>
                </svg>
            </span>
            <span class="post-breadcrumb-current" aria-current="page"><?php XPro::esc($name); ?></span>
        </nav>
    </div>

    <!-- 作者档案区 -->
    <section class="author-profile" aria-label="用户档案">
        <div class="author-cover">
            <a href="<?php XPro::esc($displayCover); ?>" data-fancybox="author-cover" data-type="image" data-caption="封面图">
                <img src="<?php XPro::esc($displayCover); ?>" alt="<?php XPro::esc($name . '的封面'); ?>" fetchpriority="high">
            </a>
        </div>
        <div class="author-identity">
            <div class="author-avatar-wrap">
                <img src="<?php XPro::esc($avatar); ?>" alt="<?php XPro::esc($name . '的头像'); ?>" class="author-avatar" fetchpriority="high">
            </div>
            <div class="author-actions">
                <?php if ($isSelf) { ?>
                <button class="btn-outline" type="button">编辑个人资料</button>
                <?php } ?>
            </div>
        </div>
        <div class="author-meta">
            <h1 class="author-name"><?php XPro::esc($name); ?></h1>
            <p class="author-handle"><?php XPro::esc($url !== '' ? $url : $permalink); ?></p>
        </div>
        <p class="author-bio"><?php XPro::esc($bio !== '' ? $bio : '这个人很懒，还没有填写简介。'); ?></p>
    </section>

    <!-- 文章 / 评论 选项卡 -->
    <div class="author-tabs" data-tabs>
        <div class="author-tabs-nav tabs-nav" role="tablist" aria-label="作者主页内容分类">
            <button class="tab<?php if (!$isCommentsActive) { ?> is-active<?php } ?>" role="tab" data-tab="posts" aria-selected="<?php echo $isCommentsActive ? 'false' : 'true'; ?>" tabindex="<?php echo $isCommentsActive ? '-1' : '0'; ?>">
                文章 <?php if ($postsCount > 0) { ?><span class="tab-count"><?= $postsCount; ?></span><?php } ?>
            </button>
            <button class="tab<?php if ($isCommentsActive) { ?> is-active<?php } ?>" role="tab" data-tab="comments" aria-selected="<?php echo $isCommentsActive ? 'true' : 'false'; ?>" tabindex="<?php echo $isCommentsActive ? '0' : '-1'; ?>">
                评论 <?php if ($commentsTotal > 0) { ?><span class="tab-count"><?= $commentsTotal; ?></span><?php } ?>
            </button>
        </div>

        <!-- 文章面板 -->
        <div class="author-tabs-panel tabs-panel<?php if (!$isCommentsActive) { ?> is-active<?php } ?>" role="tabpanel" data-panel="posts" aria-label="文章列表">
            <?php if ($this->have()) { ?>
                <?php $this->need('includes/article.php'); ?>
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
            <?php } else { ?>
                <p class="author-empty">暂无文章</p>
            <?php } ?>
        </div>

        <!-- 评论面板 -->
        <div class="author-tabs-panel tabs-panel<?php if ($isCommentsActive) { ?> is-active<?php } ?>" role="tabpanel" data-panel="comments" aria-label="评论列表">
            <?php if (!empty($authorComments)) { ?>
                <div class="author-comments-list">
                    <?php foreach ($authorComments as $comment) { $renderCommentItem($comment); } ?>
                </div>
                <?php if ($commentPage < $commentPages) { ?>
                    <button type="button" class="author-comments-more load-more-btn"
                            data-next-page="<?= $commentPage + 1; ?>"
                            data-total-pages="<?= $commentPages; ?>"
                            data-url="<?php XPro::esc($buildCommentPageUrl($commentPage + 1)); ?>"
                            aria-label="加载更多评论">
                        <span class="load-more-text">加载更多评论</span>
                        <span class="load-more-spinner" aria-hidden="true"></span>
                    </button>
                <?php } ?>
            <?php } else { ?>
                <p class="author-empty" style="text-align: center; margin: 5rem; color: var(--text-muted);">暂无评论</p>
            <?php } ?>
        </div>
    </div>
    
    <?php if ($isSelf) { ?>
    <!-- 编辑个人资料模态框 -->
    <div id="profile-modal" class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title" aria-hidden="true">
    <div class="profile-modal-backdrop"></div>
    <form id="profile-form" class="profile-modal-form profile-modal-panel" action="<?php echo Helper::Security()->getTokenUrl($permalink); ?>" method="post">
        <div class="profile-modal-header">
            <button class="profile-modal-close" type="button" aria-label="关闭">
                <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                    <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path>
                </svg>
            </button>
            <h2 id="profile-modal-title" class="profile-modal-title">编辑个人资料</h2>
            <button class="profile-modal-save" type="submit">保存</button>
        </div>
        <div class="profile-modal-body">
            <div class="profile-modal-cover">
                <img src="<?php XPro::esc($displayCover); ?>" alt="封面预览">
            </div>
            <div class="profile-modal-avatar-wrap">
                <img src="<?php XPro::esc($avatar); ?>" alt="头像预览" class="profile-modal-avatar">
            </div>
            <div class="profile-modal-fields">
                <input type="hidden" name="xpro_profile_uid" value="<?= $uid; ?>">
                <div class="profile-modal-field">
                    <label for="profile-name-input">昵称</label>
                    <input type="text" id="profile-name-input" name="xpro_profile_nickname" value="<?php XPro::esc($name); ?>" maxlength="20" placeholder="你的昵称">
                </div>
                <div class="profile-modal-field">
                    <label for="profile-handle-input">个人主页</label>
                    <input type="url" id="profile-handle-input" name="xpro_profile_homepage" value="<?php XPro::esc($url); ?>" maxlength="100" placeholder="https://example.com">
                </div>
                <div class="profile-modal-field">
                    <label for="profile-mail-input">邮件地址</label>
                    <input type="email" id="profile-mail-input" name="xpro_profile_email" value="<?php XPro::esc($mail); ?>" maxlength="100" placeholder="example@example.com">
                    <span style="color: var(--text-muted);font-size: 0.75rem;">电子邮箱地址将作为此用户的主要联系方式，请不要与系统中现有的电子邮箱地址重复。</span>
                </div>
                <div class="profile-modal-field">
                    <label for="profile-bio-input">简介</label>
                    <textarea id="profile-bio-input" name="xpro_profile_bio" rows="3" maxlength="300" placeholder="写一段个人简介..."><?php XPro::esc($bio); ?></textarea>
                    <span class="profile-modal-char-count">0 / 300</span>
                </div>
                <div class="profile-modal-field">
                    <label for="profile-avatar-input">头像</label>
                    <input type="url" id="profile-avatar-input" name="xpro_profile_avatar" value="<?php XPro::esc($customAvatar); ?>" maxlength="500" placeholder="https://example.com/avatar.jpg">
                    <span style="color: var(--text-muted);font-size: 0.75rem;">留空默认使用Gravatar头像</span>
                </div>
                <div class="profile-modal-field">
                    <label for="profile-cover-input">封面</label>
                    <input type="url" id="profile-cover-input" name="xpro_profile_cover" value="<?php XPro::esc($cover); ?>" maxlength="500" placeholder="https://example.com/cover.jpg">
                </div>
            </div>
        </div>
    </form>
</div>
<?php } ?>
</main>
<!-- ==================== 右侧边栏 ==================== -->
<?php $this->need('includes/aside.php'); ?>
<!-- ==================== 页脚 ==================== -->
<?php $this->need('footer.php'); ?>
