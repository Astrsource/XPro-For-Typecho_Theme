<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 文章卡片组件
 *
 * @package XPro
 */

$renderGallery = static function (array $cfg, string $title, $cid): void {
    if ($cfg['style'] === 'none' || empty($cfg['displayImages'])) {
        return;
    }

    $style     = $cfg['style'];
    $images    = $cfg['displayImages'];
    $total     = $cfg['total'];
    $visible   = $cfg['visible'];
    $colsClass = $cfg['colsClass'];
    $group     = 'card-' . (int) $cid;

    $attrs = 'class="gallery ' . $colsClass . '"';
    if ($style === 'album') {
        $label = XPro::esc($title . ' 相册，共 ' . $total . ' 张', true);
        $attrs .= ' aria-label="' . $label . '"';
    }
?>
    <div <?= $attrs ?>>
        <?php foreach ($images as $index => $url) {
            $caption    = $title . ' - 图 ' . ($index + 1);
            $escapedUrl = XPro::esc($url, true);
            $itemClass  = '';
            $itemAttrs  = '';

            if ($style === 'album') {
                if ($index >= $visible) {
                    $itemClass = 'is-hidden';
                    $itemAttrs = ' aria-hidden="true"';
                } elseif ($index === $visible - 1 && $total > $visible) {
                    $itemClass = 'is-overlay';
                    $itemAttrs = ' data-count="+' . ($total - $visible) . '" aria-label="共 ' . $total . ' 张图片，点击查看全部"';
                }
            }

            $classAttr = $itemClass !== '' ? ' class="' . $itemClass . '"' : '';
        ?>
        <a href="<?= $escapedUrl ?>"<?= $classAttr ?><?= $itemAttrs ?> data-fancybox="<?= $group ?>" data-type="image" data-caption="<?php XPro::esc($caption); ?>">
            <figure>
                <img src="<?= $escapedUrl ?>" alt="<?php XPro::esc($caption); ?>" loading="lazy" decoding="async">
                <figcaption><?php XPro::esc($caption); ?></figcaption>
            </figure>
        </a>
        <?php } ?>
    </div>
<?php
};

$renderCard = static function ($post, array $cfg, bool $pinned = false) use ($renderGallery): void {
    $title     = (string) $post->title;
    $author    = $post->author->screenName;
    $authorMail = $post->author->mail;
    $date      = XPro::formatDate((int) $post->created);
    $excerpt   = XPro::excerpt((string) $post->content, 120);
    $comments  = (int) ($post->commentsNum ?? 0);
    $likes     = (int) ($post->likes ?? 0);
    $views     = (int) ($post->views ?? 0);
    $hasLiked  = XPro::hasUserLiked((int) ($post->cid ?? 0));
    $cardClass = $pinned ? 'pinned-card card' : 'card';
    $badge     = $pinned ? '<div class="card-badge"><svg class="icon" aria-hidden="true" viewbox="0 0 24 24"><path d="M22.3126 10.1753L20.8984 11.5895L20.1913 10.8824L15.9486 15.125L15.2415 18.6606L13.8273 20.0748L9.58466 15.8321L4.63492 20.7819L3.2207 19.3677L8.17045 14.4179L3.92781 10.1753L5.34202 8.76107L8.87756 8.05396L13.1202 3.81132L12.4131 3.10422L13.8273 1.69L22.3126 10.1753Z"></path></svg>置顶</div>' : '';
?>
<article class="<?= $cardClass ?>" data-href="<?php $post->permalink(); ?>" aria-label="<?php XPro::esc($title); ?>">
    <?= $badge ?>
    <div class="card-row">
        <img src="<?php XPro::avatar($authorMail, 100, false, (int) $post->authorId); ?>" alt="<?php XPro::esc($author . '的头像'); ?>" class="avatar" loading="lazy">
        <div class="card-content">
            <div class="card-meta">
                <a href="<?php $post->author->permalink(); ?>" class="card-author"><?php XPro::esc($author); ?></a>
                <span class="dot" aria-hidden="true">·</span>
                <time class="card-date" datetime="<?= $date ?>"><?= $date ?></time>
            </div>
            <a href="<?php $post->permalink(); ?>" class="card-heading"><?php XPro::esc($title); ?></a>
            <p class="card-excerpt"><?php XPro::esc($excerpt); ?></p>
            <?php $renderGallery($cfg, $title, $post->cid ?? 0); ?>
            <div class="card-actions">
                <a href="<?php $post->permalink(); ?>#comments" class="card-action" aria-label="评论，当前<?= $comments ?>条" role="button">
                    <svg class="icon" aria-hidden="true" viewbox="0 0 24 24"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>
                    <span class="count"><?= $comments ?></span>
                </a>
                <button class="card-action like<?= $hasLiked ? ' liked' : ''; ?>" aria-label="点赞，当前<?= $likes ?>个赞" data-cid="<?= (int) ($post->cid ?? 0) ?>">
                    <svg class="icon" aria-hidden="true" viewbox="0 0 24 24">
                        <path d="<?= $hasLiked ? 'M16.5 3C19.5376 3 22 5.5 22 9C22 16 14.5 20 12 21.5C9.5 20 2 16 2 9C2 5.5 4.5 3 7.5 3C9.35997 3 11 4 12 5C13 4 14.64 3 16.5 3Z' : 'M16.5 3C19.5376 3 22 5.5 22 9C22 16 14.5 20 12 21.5C9.5 20 2 16 2 9C2 5.5 4.5 3 7.5 3C9.35997 3 11 4 12 5C13 4 14.64 3 16.5 3ZM12.9339 18.6038C13.8155 18.0485 14.61 17.4955 15.3549 16.9029C18.3337 14.533 20 11.9435 20 9C20 6.64076 18.463 5 16.5 5C15.4241 5 14.2593 5.56911 13.4142 6.41421L12 7.82843L10.5858 6.41421C9.74068 5.56911 8.5759 5 7.5 5C5.55906 5 4 6.6565 4 9C4 11.9435 5.66627 14.533 8.64514 16.9029C9.39 17.4955 10.1845 18.0485 11.0661 18.6038C11.3646 18.7919 11.6611 18.9729 12 19.1752C12.3389 18.9729 12.6354 18.7919 12.9339 18.6038Z'; ?>"></path>
                    </svg>
                    <span class="count"><?= $likes ?></span>
                </button>
                <span class="card-action" aria-label="阅读量，当前<?= $views ?>">
                    <svg class="icon" aria-hidden="true" viewbox="0 0 24 24"><path d="M12.0003 3C17.3924 3 21.8784 6.87976 22.8189 12C21.8784 17.1202 17.3924 21 12.0003 21C6.60812 21 2.12215 17.1202 1.18164 12C2.12215 6.87976 6.60812 3 12.0003 3ZM12.0003 19C16.2359 19 19.8603 16.052 20.7777 12C19.8603 7.94803 16.2359 5 12.0003 5C7.7646 5 4.14022 7.94803 3.22278 12C4.14022 16.052 7.7646 19 12.0003 19ZM12.0003 16.5C9.51498 16.5 7.50026 14.4853 7.50026 12C7.50026 9.51472 9.51498 7.5 12.0003 7.5C14.4855 7.5 16.5003 9.51472 16.5003 12C16.5003 14.4853 14.4855 16.5 12.0003 16.5ZM12.0003 14.5C13.381 14.5 14.5003 13.3807 14.5003 12C14.5003 10.6193 13.381 9.5 12.0003 9.5C10.6196 9.5 9.50026 10.6193 9.50026 12C9.50026 13.3807 10.6196 14.5 12.0003 14.5Z"></path></svg>
                    <span class="count"><?= $views ?></span>
                </span>
            </div>
        </div>
    </div>
</article>
<?php
};

XPro::applyStickyPagination($this);

while ($this->next()) {
    $cfg = ThumbnailHelper::getCardImageConfig($this);
    $renderCard($this, $cfg, XPro::isStickyCid($this->cid) && $this->is('index'));
}
