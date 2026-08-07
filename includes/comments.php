<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 评论组件
 *
 * @package XPro
 */

$db       = \Typecho\Db::get();
$cid      = (int) $this->cid;
$commentsCount = (int) $this->commentsNum;

$user = \Typecho\Widget::widget('Widget_User');
$options = \Typecho\Widget::widget('Widget_Options');

$hasLogin     = $user->hasLogin();
$commentAllowed = $this->allow('comment');

$pageSize  = max(1, (int) ($options->commentsPageSize ?? 10));
$order     = ((string) ($options->commentsOrder ?? 'ASC') === 'DESC') ? 'DESC' : 'ASC';
$threaded  = (bool) $options->commentsThreaded;

$ownerId = 0;
$ownerRow = $db->fetchRow(
    $db->select('authorId')->from('table.contents')->where('cid = ?', $cid)->limit(1)
);
if ($ownerRow) {
    $ownerId = (int) $ownerRow['authorId'];
}

$allApproved = $db->fetchAll(
    $db->select()->from('table.comments')
        ->where('cid = ?', $cid)
        ->where('status = ?', 'approved')
        ->order('created', \Typecho\Db::SORT_ASC)
);

$childrenMap = [];
foreach ($allApproved as $row) {
    $parentCoid = (int) $row['parent'];
    if ($parentCoid !== 0) {
        $childrenMap[$parentCoid][] = $row;
    }
}

$topRows = [];
foreach ($allApproved as $row) {
    if ((int) $row['parent'] === 0) {
        $topRows[] = $row;
    }
}

if ($order === 'DESC') {
    $topRows = array_reverse($topRows);
}

$totalTop = count($topRows);
$hasMore  = $totalTop > $pageSize;
if ($hasMore) {
    $topRows = array_slice($topRows, 0, $pageSize);
}

$rememberAuthor = \Typecho\Cookie::get('__typecho_remember_author');
$rememberMail   = \Typecho\Cookie::get('__typecho_remember_mail');
$rememberUrl    = \Typecho\Cookie::get('__typecho_remember_url');

$formAvatar = XPro::avatar($hasLogin ? (string) $user->mail : '', 80, true, $hasLogin ? (int) $user->uid : null);
$formAuthor = $hasLogin
    ? htmlspecialchars((string) $user->screenName, ENT_QUOTES, 'UTF-8')
    : htmlspecialchars((string) $rememberAuthor, ENT_QUOTES, 'UTF-8');
$formMail   = $hasLogin
    ? htmlspecialchars((string) $user->mail, ENT_QUOTES, 'UTF-8')
    : htmlspecialchars((string) $rememberMail, ENT_QUOTES, 'UTF-8');
$formUrl    = $hasLogin
    ? htmlspecialchars((string) $user->url, ENT_QUOTES, 'UTF-8')
    : htmlspecialchars((string) $rememberUrl, ENT_QUOTES, 'UTF-8');
?>

<script>window.XPRO_COMMENT_CID = "<?= $cid; ?>";</script>

<section class="post-comments" id="comments" aria-label="评论区">
    <h2 class="post-comments-title">评论（<?= $commentsCount; ?>）</h2>

    <?php if ($commentAllowed) { ?>
    <form class="comment-form" id="comment-form" aria-label="发表评论" data-cid="<?= $cid; ?>">
        <img src="<?= $formAvatar; ?>" alt="当前用户头像" class="avatar" loading="lazy">
        <div class="comment-form-body">
            <?php if (!$hasLogin) { ?>
            <div class="comment-form-fields">
                <input type="text" class="comment-form-input text" name="author" value="<?= $formAuthor; ?>" placeholder="称呼" aria-label="评论者名称">
                <input type="email" class="comment-form-input text" name="mail" value="<?= $formMail; ?>" placeholder="邮箱" aria-label="评论者邮箱">
                <input type="url" class="comment-form-input text" name="url" value="<?= $formUrl; ?>" placeholder="网址（选填）" aria-label="评论者网址">
            </div>
            <?php } ?>
            <textarea class="comment-form-input" id="comment-form-textarea" name="text" placeholder="写下你的想法..." aria-label="评论内容"></textarea>
            <div class="comment-form-actions">
                <!-- <label class="comment-form-private">
                    <input type="checkbox" class="comment-form-private-input" id="comment-form-private" aria-label="私密评论">
                    <span class="comment-form-private-switch" aria-hidden="true"></span>
                    <span class="comment-form-private-label">私密评论</span>
                </label> -->
                <div></div>
                <div class="comment-form-actions-right">
                    <button type="button" class="comment-form-btn ghost icon-only" id="comment-form-cancel" hidden aria-label="取消回复">
                        <svg class="icon" aria-hidden="true" viewBox="0 0 24 24">
                            <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path>
                        </svg>
                    </button>
                    <button type="submit" class="comment-form-btn primary">发送</button>
                </div>
            </div>
        </div>
    </form>
    <?php } else { ?>
    <div class="comment-closed">
        <p>评论已关闭</p>
    </div>
    <?php } ?>

    <div class="comment-list">
        <?php foreach ($topRows as $row) { ?>
            <?= AjaxComment::renderCommentItem($row, $ownerId, $allApproved); ?>
            <?php if ($threaded) {
                $descendants = [];
                $queue = $childrenMap[(int) $row['coid']] ?? [];
                while (!empty($queue)) {
                    $current = array_shift($queue);
                    $descendants[] = $current;
                    $childCoid = (int) $current['coid'];
                    if (!empty($childrenMap[$childCoid])) {
                        $queue = array_merge($queue, $childrenMap[$childCoid]);
                    }
                }
                usort($descendants, function ($a, $b) {
                    return ($a['created'] ?? 0) - ($b['created'] ?? 0);
                });
                foreach ($descendants as $desc) {
                    echo AjaxComment::renderCommentItem($desc, $ownerId, $allApproved);
                }
            } ?>
        <?php } ?>
    </div>

    <?php if ($hasMore) { ?>
    <button type="button" class="author-comments-more"
            data-cid="<?= $cid; ?>"
            data-next-page="2"
            data-page-size="<?= $pageSize; ?>"
            data-order="<?= $order; ?>"
            aria-label="加载更多评论">
        <span class="load-more-text">加载更多评论</span>
        <span class="load-more-spinner" aria-hidden="true"></span>
    </button>
    <?php } ?>
</section>
