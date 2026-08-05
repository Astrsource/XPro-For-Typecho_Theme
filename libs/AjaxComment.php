<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Ajax 评论后端处理（扁平化嵌套）
 *
 * @package XPro
 */
class AjaxComment
{
    /**
     * 提交 / 回复评论
     *
     * @param \Widget\Archive $archive
     * @return void
     */
    public static function submit($archive): void
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $user    = \Typecho\Widget::widget('Widget_User');
        $db      = \Typecho\Db::get();

        header('Content-Type: application/json; charset=utf-8');

        if (!$archive->allow('comment')) {
            echo json_encode(['status' => 0, 'msg' => '评论已关闭'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cid = (int) $archive->request->get('cid', 0);
        if ($cid <= 0) {
            echo json_encode(['status' => 0, 'msg' => '无效的文章'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $post = $db->fetchRow(
            $db->select('cid', 'type', 'status', 'authorId')
                ->from('table.contents')
                ->where('cid = ?', $cid)
        );
        if (!$post || (string) $post['status'] !== 'publish') {
            echo json_encode(['status' => 0, 'msg' => '文章不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ((bool) $options->commentsPostIntervalEnable && !$user->pass('editor', true)) {
            $latest = $db->fetchRow(
                $db->select('created')->from('table.comments')
                    ->where('cid = ?', $cid)
                    ->where('ip = ?', $archive->request->getIp())
                    ->order('created', \Typecho\Db::SORT_DESC)
                    ->limit(1)
            );
            if ($latest && (time() - (int) $latest['created']) < (int) $options->commentsPostInterval) {
                echo json_encode(['status' => 0, 'msg' => '您的发言过于频繁，请稍后再试'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $author = trim((string) $archive->request->get('author', ''));
        $mail   = trim((string) $archive->request->get('mail', ''));
        $url    = trim((string) $archive->request->get('url', ''));
        $text   = (string) $archive->request->get('text', '');
        $parent = (int) $archive->request->get('parent', 0);

        $errors = [];
        if (!$user->hasLogin()) {
            if ($author === '') {
                $errors[] = '必须填写用户名';
            } elseif (mb_strlen($author) > 200) {
                $errors[] = '用户名最多 200 字符';
            }
        }
        if ((bool) $options->commentsRequireMail && !$user->hasLogin() && $mail === '') {
            $errors[] = '必须填写邮箱';
        }
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '邮箱格式错误';
        }
        if ((bool) ($options->commentsRequireURL ?? $options->commentsRequireUrl) && !$user->hasLogin() && $url === '') {
            $errors[] = '必须填写网站';
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = '网站格式错误';
        }
        if ($text === '') {
            $errors[] = '必须填写评论内容';
        }
        if (!empty($errors)) {
            echo json_encode(['status' => 0, 'msg' => implode('；', $errors)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $comment = [
            'cid'     => $cid,
            'created' => (int) $options->gmtTime,
            'agent'   => (string) $archive->request->getAgent(),
            'ip'      => (string) $archive->request->getIp(),
            'ownerId' => (int) $post['authorId'],
            'type'    => 'comment',
            'text'    => $text,
            'status'  => (bool) $options->commentsRequireModeration ? 'waiting' : 'approved',
        ];

        if ($parent > 0) {
            if (!(bool) $options->commentsThreaded) {
                echo json_encode(['status' => 0, 'msg' => '不支持嵌套回复'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $parentRow = $db->fetchRow(
                $db->select('coid', 'cid')->from('table.comments')->where('coid = ?', $parent)
            );
            if (!$parentRow || (int) $parentRow['cid'] !== $cid) {
                echo json_encode(['status' => 0, 'msg' => '父级评论不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $comment['parent'] = $parent;
        }

        if ((bool) $options->commentsWhitelist && !(bool) $options->commentsRequireModeration) {
            $approved = $db->fetchObject(
                $db->select(['COUNT(coid)' => 'c'])->from('table.comments')
                    ->where('author = ?', $author)
                    ->where('mail = ?', $mail)
                    ->where('status = ?', 'approved')
            );
            $comment['status'] = ((int) ($approved->c ?? 0) > 0) ? 'approved' : 'waiting';
        }

        if ($user->hasLogin()) {
            $comment['author']   = (string) $user->screenName;
            $comment['mail']     = (string) $user->mail;
            $comment['url']      = (string) $user->url;
            $comment['authorId'] = (int) $user->uid;
        } else {
            $comment['author']   = $author;
            $comment['mail']     = $mail;
            $comment['url']      = $url;
            $comment['authorId'] = 0;

            $expire = (int) $options->gmtTime + (int) $options->timezone + 30 * 24 * 3600;
            \Typecho\Cookie::set('__typecho_remember_author', $author, $expire);
            \Typecho\Cookie::set('__typecho_remember_mail', $mail, $expire);
            \Typecho\Cookie::set('__typecho_remember_url', $url, $expire);
        }

        $feedback = \Typecho\Widget::widget('Widget_Feedback');
        ob_start();
        try {
            $comment = $feedback->pluginHandle()->comment($comment, $feedback->_content) ?? $comment;
        } catch (\Typecho\Exception $e) {
            ob_end_clean();
            \Typecho\Cookie::set('__typecho_remember_text', $text);
            echo json_encode(['status' => 0, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        ob_end_clean();

        try {
            $insertId = $feedback->insert($comment);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 0, 'msg' => '评论提交失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /* 补全邮件通知类插件需要的数据（评论表行中没有文章标题/链接） */
        $article = $db->fetchRow(
            $db->select('cid', 'title', 'slug', 'type', 'created')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->limit(1)
        );
        $feedbackRow = $comment;
        $feedbackRow['coid'] = $insertId;
        $feedbackRow['title'] = (string) ($article['title'] ?? '');
        $feedbackRow['permalink'] = $article
            ? (string) \Typecho\Router::url((string) ($article['type'] ?? 'post'), $article, $options->index)
            : '';
        $feedback->push($feedbackRow);

        ob_start();
        try {
            $feedback->pluginHandle()->finishComment($feedback);
        } catch (\Throwable) {
            // 忽略
        }
        ob_end_clean();

        \Typecho\Cookie::delete('__typecho_remember_text');

        $newRow = $db->fetchRow($feedback->select()->where('coid = ?', $insertId)->limit(1));
        $feedback->push($newRow);

        $isAuthor = (int) ($comment['authorId'] ?? 0) === (int) $post['authorId'];

        $parentAuthor = '';
        $parentText   = '';
        $parentCoid   = (int) ($comment['parent'] ?? 0);
        if ($parentCoid > 0) {
            $pRow = $db->fetchRow(
                $db->select('author', 'text')->from('table.comments')
                    ->where('coid = ?', $parentCoid)->limit(1)
            );
            $parentAuthor = (string) ($pRow['author'] ?? '');
            $parentText   = (string) ($pRow['text'] ?? '');
        }

        $status = (string) ($comment['status'] ?? 'approved');
        $msg    = $status === 'waiting' ? '您的评论需管理员审核后才能显示' : '';

        $commentItem = [
            'coid'         => (int) $insertId,
            'cid'          => (int) $cid,
            'parent'       => $parentCoid,
            'parentAuthor' => $parentAuthor,
            'parentText'   => $parentText,
            'author'       => (string) $comment['author'],
            'mail'         => (string) $comment['mail'],
            'url'          => (string) $comment['url'],
            'avatar'       => XPro::avatar((string) $comment['mail'], 80, true, (int) $comment['authorId']),
            'content'      => self::parseCommentText((string) $comment['text']),
            'datetime'     => date('Y-m-d H:i:s', (int) $comment['created']),
            'status'       => $status,
            'isAuthor'     => $isAuthor,
        ];

        echo json_encode([
            'status'  => 1,
            'msg'     => $msg,
            'comment' => $commentItem,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 加载更多评论（扁平化版本）
     *
     * @param \Widget\Archive $archive
     * @return void
     */
    public static function loadMore($archive): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $options  = \Typecho\Widget::widget('Widget_Options');
        $cid      = (int) $archive->request->get('cid', 0);
        $page     = max(1, (int) $archive->request->get('page', 1));
        $pageSize = max(1, min(50, (int) $archive->request->get('pageSize', max(1, (int) ($options->commentsPageSize ?? 10)))));
        $order    = ((string) $archive->request->get('order', (string) ($options->commentsOrder ?? 'ASC')) === 'DESC')
                     ? 'DESC' : 'ASC';

        if ($cid <= 0) {
            echo json_encode(['status' => 0, 'msg' => '无效参数'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = \Typecho\Db::get();

        $totalTop = (int) $db->fetchObject(
            $db->select(['COUNT(coid)' => 'c'])->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->where('parent = ?', 0)
        )->c;

        $topRows = $db->fetchAll(
            $db->select()->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->where('parent = ?', 0)
                ->order('created', $order === 'DESC' ? \Typecho\Db::SORT_DESC : \Typecho\Db::SORT_ASC)
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
        );

        $hasMore = $totalTop > $page * $pageSize;

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

        $ownerId = 0;
        $ownerRow = $db->fetchRow(
            $db->select('authorId')->from('table.contents')->where('cid = ?', $cid)->limit(1)
        );
        if ($ownerRow) {
            $ownerId = (int) $ownerRow['authorId'];
        }

        $list = [];
        foreach ($topRows as $top) {
            $topItem = self::buildCommentItem($top, $ownerId, $db, $allApproved);
            $descendants = self::getFlatDescendants((int) $top['coid'], $childrenMap);
            $topItem['descendants'] = array_map(function ($child) use ($ownerId, $db, $allApproved) {
                return self::buildCommentItem($child, $ownerId, $db, $allApproved);
            }, $descendants);
            $list[] = $topItem;
        }

        echo json_encode([
            'status'   => 1,
            'comments' => $list,
            'hasMore'  => $hasMore,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 渲染单条评论的 HTML
     *
     * @param array  $comment  评论数据行
     * @param int    $ownerId  文章作者 ID
     * @param array  $allComments 该文章所有已通过评论
     * @return string
     */
    public static function renderCommentItem(array $comment, int $ownerId, array $allComments = []): string
    {
        $coid     = (int) $comment['coid'];
        $author   = htmlspecialchars((string) $comment['author'], ENT_QUOTES, 'UTF-8');
        $mail     = (string) ($comment['mail'] ?? '');
        $url      = (string) ($comment['url'] ?? '');
        $created  = (int) $comment['created'];
        $parent   = (int) ($comment['parent'] ?? 0);
        $status   = (string) ($comment['status'] ?? 'approved');
        $text     = (string) ($comment['text'] ?? '');
        $isAuthor = ((int) ($comment['authorId'] ?? 0) === $ownerId);
        $isReply  = ($parent > 0);

        $avatar    = XPro::avatar($mail, 80, true, (int) ($comment['authorId'] ?? 0));
        $datetime  = date('Y-m-d H:i:s', $created);
        $content   = self::parseCommentText($text);
        $authorUrl = $url !== '' ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : '';

        $badgeHtml = '';
        if ($isAuthor) {
            $badgeHtml = '<span class="comment-item-badge">作者</span>';
        }

        $class = $isReply ? 'comment-item is-reply' : 'comment-item';
        $html  = '<article class="' . $class . '" id="comment-' . $coid . '">';
        $html .= '<img src="' . $avatar . '" alt="' . $author . '的头像" class="avatar" loading="lazy">';
        $html .= '<div class="comment-item-body">';
        $html .= '<div class="comment-item-meta">';

        if ($authorUrl !== '') {
            $html .= '<a href="' . $authorUrl . '" class="comment-item-author" rel="external nofollow" target="_blank">' . $author . '</a>';
        } else {
            $html .= '<span class="comment-item-author">' . $author . '</span>';
        }

        if ($badgeHtml !== '') {
            $html .= $badgeHtml;
        }

        $html .= '<time class="comment-item-date" datetime="' . $datetime . '">' . $datetime . '</time>';
        $html .= '</div>';

        if ($isReply && $parent > 0) {
            $parentAuthor = '';
            $parentText = '';
            foreach ($allComments as $c) {
                if ((int) $c['coid'] === $parent) {
                    $parentAuthor = (string) ($c['author'] ?? '');
                    $parentText   = (string) ($c['text'] ?? '');
                    break;
                }
            }
            $html .= '<blockquote class="comment-quote">';
            $html .= '<a href="#comment-' . $parent . '" class="comment-reply-mention">@' . htmlspecialchars($parentAuthor, ENT_QUOTES, 'UTF-8') . '</a>';
            $html .= '<p class="comment-quote-text">' . self::parseCommentText($parentText) . '</p>';
            $html .= '</blockquote>';
        }

        if ($status === 'waiting') {
            $html .= '<p class="comment-item-pending">您的评论正在审核中...</p>';
        } else {
            $html .= '<div class="comment-item-text">' . $content . '</div>';
        }

        $html .= '<div class="comment-item-actions">';
        $html .= '<button class="comment-item-action" aria-label="回复这条评论">';
        $html .= '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>';
        $html .= '回复';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</article>';

        return $html;
    }

    /**
     * 解析评论文本（过滤 HTML 标签，保留换行）
     *
     * @param string $text
     * @return string
     */
    private static function parseCommentText(string $text): string
    {
        $text = strip_tags($text, '<a><strong><em><code><pre><blockquote><img><br><p>');
        $text = nl2br($text);
        return $text;
    }

    /**
     * 构建评论数据项（用于 JSON 响应）
     *
     * @param array           $c        评论行数据
     * @param int             $ownerId  文章作者 ID
     * @param \Typecho\Db     $db
     * @param array           $allComments
     * @return array
     */
    private static function buildCommentItem(array $c, int $ownerId, \Typecho\Db $db, array $allComments): array
    {
        $coid   = (int) $c['coid'];
        $isAuthor = (int) ($c['authorId'] ?? 0) === $ownerId;
        $parentCoid = (int) $c['parent'];
        $parentAuthor = '';
        $parentText = '';
        if ($parentCoid > 0) {
            foreach ($allComments as $row) {
                if ((int) $row['coid'] === $parentCoid) {
                    $parentAuthor = (string) ($row['author'] ?? '');
                    $parentText   = (string) ($row['text'] ?? '');
                    break;
                }
            }
        }
        return [
            'coid'         => $coid,
            'cid'          => (int) $c['cid'],
            'parent'       => $parentCoid,
            'parentAuthor' => $parentAuthor,
            'parentText'   => $parentText,
            'author'       => (string) $c['author'],
            'mail'         => (string) $c['mail'],
            'url'          => (string) $c['url'],
            'avatar'       => XPro::avatar((string) $c['mail'], 80, true, (int) ($c['authorId'] ?? 0)),
            'content'      => self::parseCommentText((string) $c['text']),
            'datetime'     => date('Y-m-d H:i:s', (int) $c['created']),
            'status'       => (string) $c['status'],
            'isAuthor'     => $isAuthor,
        ];
    }

    /**
     * 获取顶级评论的所有后代（平铺，按 created 升序）
     *
     * @param int   $topCoid      顶级评论 coid
     * @param array $childrenMap  父子映射
     * @return array
     */
    private static function getFlatDescendants(int $topCoid, array $childrenMap): array
    {
        $descendants = [];
        $queue = $childrenMap[$topCoid] ?? [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $descendants[] = $current;
            $coid = (int) $current['coid'];
            if (!empty($childrenMap[$coid])) {
                $queue = array_merge($queue, $childrenMap[$coid]);
            }
        }
        usort($descendants, function ($a, $b) {
            return ($a['created'] ?? 0) - ($b['created'] ?? 0);
        });
        return $descendants;
    }
}
