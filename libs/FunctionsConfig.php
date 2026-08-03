<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * XPro 主题配置面板
 *
 * 提供主题外观设置（基础设置、首页设置、侧边栏）和文章自定义字段。
 *
 * @package XPro
 */


/**
 * 主题外观配置面板
 *
 * 输出备份面板、Tab导航、CSS样式和表单字段。
 *
 * @param \Typecho\Widget\Helper\Form $form 表单对象
 * @return void
 */
function themeConfig($form) {
    echo '<div class="xpro-admin-card">';
    \Backup::echoBackup();
    echo '</div>';

    echo <<<'XPRO_CSS'
<style>
        /* ============ 面板整体 ============ */
        .xpro-admin-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px 22px;
            margin-bottom: 18px;
            border: 1px solid #e8edf3;
        }

        /* ============ 备份面板 ============ */
        .backup-panel { background: transparent !important; border: none !important; border-radius: 0 !important; padding: 0 !important; margin-bottom: 0 !important; }
        .backup-panel h3 { font-size: 15px !important; color: #1f2937 !important; margin: 0 0 12px !important; font-weight: 700 !important; }
        .backup-panel p { color: #6b7280 !important; font-size: 12.5px !important; }
        .backup-btn { border-radius: 8px !important; padding: 7px 18px !important; font-size: 13px !important; font-weight: 500 !important; cursor: pointer !important; transition: all 0.2s ease !important; }
        .backup-btn:not(.primary):not(.danger) { background: #fff !important; border-color: #d8dee8 !important; color: #4b5563 !important; }
        .backup-btn:not(.primary):not(.danger):hover { border-color: #1890ff !important; color: #1890ff !important; background: #f4f8ff !important; }
        .backup-btn.primary { background: #1890ff !important; border-color: #1890ff !important; }
        .backup-btn.danger { background: #fff !important; border-color: #ff4d4f !important; color: #ff4d4f !important; }
        .backup-btn.danger:hover { background: #fff2f0 !important; }

        /* ============ Tab 导航 ============ */
        .xpro-tabs-nav-outer {
            display: flex;
            gap: 6px;
            padding: 10px 12px 0;
            flex-wrap: wrap;
            background: #fff;
            border: 1px solid #e8edf3;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
        }
        .xpro-tab-btn {
            padding: 8px 18px;
            border: none;
            background: transparent;
            color: #5b6472;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: color 0.2s, background 0.2s;
            font-weight: 500;
            font-family: inherit;
        }
        .xpro-tab-btn:hover { color: #1890ff; background: rgba(24, 144, 255, 0.06); }
        .xpro-tab-btn:focus-visible { outline: 2px solid rgba(24, 144, 255, 0.45); outline-offset: -2px; }
        .xpro-tab-btn.active {
            color: #1890ff;
            border-bottom-color: #1890ff;
            font-weight: 600;
        }

        /* ============ Tab 面板 ============ */
        .xpro-tabs-content {
            background: #fff;
            border: 1px solid #e8edf3;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 16px 18px 24px;
        }
        .xpro-tab-panel { display: none; }
        .xpro-tab-panel.active { display: block; animation: xproFadeIn 0.28s ease; }
        @keyframes xproFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }

        /* ============ 字段卡片 ============ */
        .xpro-tab-panel .typecho-option {
            margin: 0 0 14px;
            background: #fafbfc;
            padding: 14px 16px;
            border: 1px solid #edf1f6;
            border-radius: 8px;
            transition: border-color 0.2s;
        }
        .xpro-tab-panel .typecho-option:hover { border-color: #d0d9e6; }
        .xpro-tab-panel .typecho-option label.typecho-label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .xpro-tab-panel .typecho-option-content { font-size: 13px; color: #4b5563; }

        /* ============ 输入控件 ============ */
        .xpro-tab-panel .typecho-option-content input[type="text"],
        .xpro-tab-panel .typecho-option-content textarea,
        .xpro-tab-panel .typecho-option-content select {
            width: 100%;
            max-width: 520px;
            box-sizing: border-box;
            padding: 9px 12px;
            border: 1px solid #d8dee8;
            border-radius: 8px;
            font-size: 13px;
            color: #1f2937;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        .xpro-tab-panel .typecho-option-content textarea { min-height: 96px; line-height: 1.7; resize: vertical; }
        .xpro-tab-panel .typecho-option-content input[type="text"]:focus,
        .xpro-tab-panel .typecho-option-content textarea:focus,
        .xpro-tab-panel .typecho-option-content select:focus {
            outline: none;
            border-color: #1890ff;
            box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.14);
        }
        .xpro-tab-panel .typecho-option-content .description {
            color: #8a94a6;
            font-size: 12px;
            margin-top: 7px;
            line-height: 1.7;
        }
        .xpro-tab-panel .typecho-option-content pre {
            background: #f6f8fa;
            color: #4b5563;
            border: 1px solid #e8edf3;
            border-radius: 6px;
            padding: 12px 14px;
            overflow: auto;
            font-size: 12px;
            line-height: 1.6;
        }
        .xpro-tab-panel .typecho-option-content code {
            font-family: SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 12px;
            color: #c7254e;
        }
        .xpro-tab-panel .typecho-option-content pre code { color: inherit; }

        /* ============ 单选按钮（胶囊） ============ */
        .xpro-tab-panel .typecho-option-content input[type="radio"] { display: none; }
        .xpro-tab-panel .typecho-option-content input[type="radio"] + label {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            margin: 2px 8px 2px 0;
            border: 1px solid #d8dee8;
            border-radius: 999px;
            background: #fff;
            color: #4b5563;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .xpro-tab-panel .typecho-option-content input[type="radio"] + label:hover {
            border-color: #1890ff;
            color: #1890ff;
        }
        .xpro-tab-panel .typecho-option-content input[type="radio"]:checked + label {
            background: #e8f3ff;
            border-color: #1890ff;
            color: #1890ff;
            font-weight: 600;
            box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.1);
        }

        /* ============ 保存按钮 ============ */
        .typecho-option-submit .btn.primary {
            background: #1890ff;
            border: 1px solid #1890ff;
            color: #fff;
            padding: 8px 24px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .typecho-option-submit .btn.primary:hover { background: #40a9ff; border-color: #40a9ff; }

        /* ============ 移动端适配 ============ */
        @media (max-width: 720px) {
            .xpro-tabs-content { padding: 12px 10px 18px; }
            .xpro-tab-btn { padding: 8px 12px; font-size: 13px; }
        }
</style>
XPRO_CSS;

    echo <<<'XPRO_TABS'
<div class="xpro-tabs-nav-outer" role="tablist" aria-label="主题设置分类">
    <button type="button" class="xpro-tab-btn active" data-target="xpro-tab-basic" role="tab" aria-selected="true" tabindex="0">基础设置</button>
    <button type="button" class="xpro-tab-btn" data-target="xpro-tab-home" role="tab" aria-selected="false" tabindex="-1">首页设置</button>
    <button type="button" class="xpro-tab-btn" data-target="xpro-tab-func" role="tab" aria-selected="false" tabindex="-1">功能设置</button>
    <button type="button" class="xpro-tab-btn" data-target="xpro-tab-sidebar" role="tab" aria-selected="false" tabindex="-1">侧边栏设置</button>
</div>
XPRO_TABS;

    echo <<<'XPRO_JS'
<script>
    document.addEventListener("DOMContentLoaded", function () {
        var form = document.querySelector('form[action*="themes-edit"]');
        if (!form) {
            var firstOption = document.querySelector(".typecho-option");
            if (firstOption) form = firstOption.closest("form");
        }
        if (!form) {
            console.error("Theme config form not found");
            return;
        }

        var content = document.createElement("div");
        content.className = "xpro-tabs-content";

        var panelIds = ["xpro-tab-basic", "xpro-tab-home", "xpro-tab-func", "xpro-tab-sidebar"];
        var panels = {};
        panelIds.forEach(function (id) {
            var div = document.createElement("div");
            div.id = id;
            div.className = "xpro-tab-panel";
            content.appendChild(div);
            panels[id] = div;
        });

        var firstOption = form.querySelector(".typecho-option");
        if (firstOption) {
            form.insertBefore(content, firstOption);
        } else {
            form.appendChild(content);
        }

        function moveField(name, tabId) {
            var input = document.querySelector('input[name="' + name + '"], select[name="' + name + '"], textarea[name="' + name + '"]');
            if (input) {
                var option = input.closest(".typecho-option");
                if (option && panels[tabId]) {
                    panels[tabId].appendChild(option);
                }
            }
        }

        var map = {
            "xpro-tab-basic": ["logoUrl", "logoDarkUrl", "themecolor", "themelayout"],
            "xpro-tab-home": ["notice", "sticky", "carouselBanner"],
            "xpro-tab-func": ["gravatars", "hotSearchTimeRange", "showCopyright", "sitefooter", "add_head", "add_body"],
            "xpro-tab-sidebar": ["sidebarIcons", "sidebarMenu"]
        };

        for (var tabId in map) {
            map[tabId].forEach(function (name) {
                moveField(name, tabId);
            });
        }

        var buttons = Array.prototype.slice.call(document.querySelectorAll(".xpro-tab-btn"));
        var STORAGE_KEY = "xpro_theme_active_tab";

        function switchTab(target) {
            if (!panels[target]) return;
            buttons.forEach(function (b) {
                var active = b.getAttribute("data-target") === target;
                b.classList.toggle("active", active);
                b.setAttribute("aria-selected", active ? "true" : "false");
                b.setAttribute("tabindex", active ? "0" : "-1");
            });
            panelIds.forEach(function (id) {
                panels[id].classList.toggle("active", id === target);
            });
            try {
                localStorage.setItem(STORAGE_KEY, target);
            } catch (e) { /* 隐私模式下忽略 */ }
        }

        buttons.forEach(function (btn, idx) {
            btn.addEventListener("click", function () {
                switchTab(this.getAttribute("data-target"));
            });
            btn.addEventListener("keydown", function (e) {
                if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
                e.preventDefault();
                var next = e.key === "ArrowRight"
                    ? (idx + 1) % buttons.length
                    : (idx - 1 + buttons.length) % buttons.length;
                buttons[next].focus();
                switchTab(buttons[next].getAttribute("data-target"));
            });
        });

        var saved = null;
        try {
            saved = localStorage.getItem(STORAGE_KEY);
        } catch (e) { /* 忽略 */ }
        switchTab(saved && panels[saved] ? saved : "xpro-tab-basic");
    });
</script>
XPRO_JS;

    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text('logoUrl', null, null, _t('站点 LOGO 地址'), _t('填入图片 URL'));
    $form->addInput($logoUrl->addRule('url', _t('请填写合法 URL')));

    $logoDarkUrl = new \Typecho\Widget\Helper\Form\Element\Text('logoDarkUrl', null, null, _t('站点 LOGO 地址（暗色模式）'), _t('填入图片 URL'));
    $form->addInput($logoDarkUrl->addRule('url', _t('请填写合法 URL')));

    $notice = new \Typecho\Widget\Helper\Form\Element\Textarea('notice', null, null, _t('站点公告'), _t('填入公告内容'));
    $form->addInput($notice);

    $themecolor = new \Typecho\Widget\Helper\Form\Element\Select('themecolor', [
        'blue' => _t('蓝色'),
        'pink' => _t('粉色'),
        'green' => _t('绿色'),
        'purple' => _t('紫色'),
        'orange' => _t('橙色'),
        'yellow' => _t('黄色'),
    ], 'blue', _t('主题颜色'), _t('选择主题颜色'));
    $form->addInput($themecolor);

    $themelayout = new \Typecho\Widget\Helper\Form\Element\Select('themelayout', [
        'boxed' => _t('盒子布局'),
        'boxed-float' => _t('盒子布局（浮动）'),
        'full' => _t('全宽布局'),
    ], 'boxed', _t('主题布局'), _t('选择主题布局'));
    $form->addInput($themelayout);

    $gravatars = new \Typecho\Widget\Helper\Form\Element\Select('gravatars', [
        'https://www.gravatar.com/avatar/' => _t('gravatar的www源'),
        'https://cn.gravatar.com/avatar/' => _t('gravatar的cn源'),
        'https://secure.gravatar.com/avatar/' => _t('gravatar的secure源'),
        'https://cravatar.cn/avatar/' => _t('Cravatar'),
        'https://gravatar.helingqi.com/wavatar/' => _t('禾令奇源[建议]'),
        'https://gravatar.loli.net/avatar/' => _t('loli.net源[建议]'),
    ], 'https://gravatar.loli.net/avatar/',_t('gravatar头像源'), _t('替换Gravatar头像的默认地址。替换后可提升加载速度，默认使用<b>loli.net源[建议]</b>。'));
    $form->addInput($gravatars->multiMode());

    $sticky = new \Typecho\Widget\Helper\Form\Element\Text(
        'sticky', null, '',
        _t('置顶文章'),
        _t('多个cid用 , 分隔，如：1,2,3')
    );
    $form->addInput($sticky);

    $carouselBanner = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'carouselBanner',
        null,
        null,
        _t('首页轮播解析'),
        _t('每行一个，格式：<br>[title="轮播标题" excerpt="这是轮播图的摘要" url="site.com" pic="banner.png" badge="广告"] <br> [post="文章cid" pic="banner.png"] <br> [page="独立页面cid"]')
    );
    $form->addInput($carouselBanner);

    $hotSearchTimeRange = new \Typecho\Widget\Helper\Form\Element\Text(
        'hotSearchTimeRange',
        null,
        '0',
        _t('热门搜索统计范围（天）'),
        _t('只统计最近 N 天内的搜索记录。填写 0 表示不限。例如：7 = 最近7天，30 = 最近30天。')
    );
    $form->addInput($hotSearchTimeRange);

    $showCopyright = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showCopyright',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '1',
        _t('版权信息'),
        _t('是否在文章底部展示 CC BY-NC-SA 4.0 版权信息')
    );
    $form->addInput($showCopyright);

    $sidebarIcons = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'sidebarIcons',
        null,
        null,
        _t('侧边栏图标'),
        _t('每行一个 SVG 图标，name 需与侧边栏菜单 JSON 中的 icon 字段对应：<br><pre><code>&lt;path name="home" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"&gt;&lt;/path&gt;
&lt;path name="home" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" /&gt;</code></pre>')
    );
    $form->addInput($sidebarIcons);

    $sidebarMenu = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'sidebarMenu',
        null,
        null,
        _t('侧边栏菜单'),
        _t('<p style="color:red;">注意：多级菜单中的父菜单为SPAN标签。对象或数组末尾不允许保留逗号。</p>填写标准 JSON 格式，icon 为可选字段，需与「侧边栏图标」中的 name 对应。<br>url 支持 <code>{siteurl}</code> 占位符，会被替换为站点地址；<code>{caturl=N}</code> 会被替换为 mid=N 的分类链接，<code>{catname=N}</code> 会被替换为分类名称；<code>{pageurl=N}</code> 会被替换为 cid=N 的独立页面链接，<code>{pagename=N}</code> 会被替换为页面标题，页面/分类不存在时输出空：<br><pre><code>
[
    {"name": "首页", "url": "{siteurl}/", "icon": "home"},
    {"name": "{catname=1}", "url": "{caturl=1}", "icon": "code"},
    {"name": "{pagename=2}", "url": "{pageurl=2}", "icon": "file"},
    {"name": "技术博客", "icon": "code", "sub": [
        {"name": "前端开发", "url": "{siteurl}/category/frontend/", "icon": "code"},
        {"name": "后端架构", "url": "{siteurl}/category/backend/", "icon": "server"}
    ]},
    {"name": "友链", "url": "{siteurl}/links/", "icon": "links"},
    {"name": "关于", "url": "{siteurl}/about.html"}
]
        </code></pre>')
    );
    $form->addInput($sidebarMenu);

    $sitefooter = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'sitefooter',
        null,
        null,
        _t('页脚内容'),
        _t('在页脚展示的内容，支持 HTML 格式。')
    );
    $form->addInput($sitefooter);

    $add_head = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'add_head',
        null,
        null,
        _t('head() 函数挂载'),
        _t('head() 函数挂载的内容，支持 HTML 格式。建议添加link、style等标签。')
    );
    $form->addInput($add_head);

    $add_body = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'add_body',
        null,
        null,
        _t('footer() 函数挂载'),
        _t('footer() 函数挂载的内容，支持 HTML 格式。建议添加script等标签。')
    );
    $form->addInput($add_body);
}

/**
 * 文章自定义字段面板
 *
 * 输出自定义字段区域的CSS样式和表单控件。
 *
 * @param \Typecho\Widget\Helper\Layout $layout 布局对象
 * @return void
 */
function themeFields($layout) {
    echo '<style>
        #custom-field {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #f0f0f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        #custom-field > summary {
            font-size: 16px;
            font-weight: 600;
            color: #262626;
            margin: -20px -20px 16px;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            list-style: none;
        }
        #custom-field > summary::-webkit-details-marker { display: none; }
        #custom-field > .fields {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #custom-field > .fields > .field {
            margin-bottom: 16px;
            background: #fafafa;
            padding: 12px 14px;
            border-radius: 6px;
            border: 1px solid #f0f0f0;
            transition: box-shadow 0.2s;
        }
        #custom-field > .fields > .field:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        #custom-field > .fields > .field:last-child {
            margin-bottom: 0;
        }
        #custom-field .field-name label.typecho-label {
            font-weight: 600;
            color: #262626;
            margin-bottom: 8px;
            display: block;
        }
        #custom-field .field-value input.text,
        #custom-field .field-value textarea,
        #custom-field .field-value select {
            width: 100%;
            max-width: 480px;
            padding: 8px 12px;
            border: 1px solid #d9d9d9;
            border-radius: 6px;
            font-size: 13px;
            color: #262626;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        #custom-field .field-value input.text:focus,
        #custom-field .field-value textarea:focus,
        #custom-field .field-value select:focus {
            outline: none;
            border-color: #1890ff;
            box-shadow: 0 0 0 3px rgba(24,144,255,0.15);
        }
        #custom-field .field-value .description {
            color: #8c8c8c;
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.6;
        }
        #custom-field > .add {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f0f0f0;
        }
    </style>';

    $k = new \Typecho\Widget\Helper\Form\Element\Text('keyword', null, null, _t('自定义关键词'), _t('在这里填入关键词，请以半角逗号 "," 分割多个关键字。'));
    $layout->addItem($k);

    $d = new \Typecho\Widget\Helper\Form\Element\Text('description', null, null, _t('自定义描述'), _t('在这里填入描述'));
    $layout->addItem($d);

    $cardStyle = new \Typecho\Widget\Helper\Form\Element\Select(
        'cardStyle',
        [
            'auto' => _t('自动推断'),
            'none' => _t('无图'),
            'single' => _t('单图'),
            'multi' => _t('多图网格'),
            'album' => _t('相册折叠'),
        ],
        'auto',
        _t('卡片风格'),
        _t('选择文章在列表中的图片展示风格，多图网格最多显示9张图片。')
    );
    $layout->addItem($cardStyle);

    $thumb = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'thumb',
        null,
        null,
        _t('自定义缩略图 URL'),
        _t('一行一个图片 URL，支持 \n 或 , 分隔。留空则自动从正文提取。')
    );
    $layout->addItem($thumb);

    $albumVisible = new \Typecho\Widget\Helper\Form\Element\Text(
        'albumVisible',
        null,
        null,
        _t('自定义网格/相册可见张数'),
        _t('网格和相册折叠风格下默认可见的图片数量，建议填写 2、3、4、6、9，留空则由系统根据图片数自动推断。自动推断为相册折叠时，可见张数会小于图片总数。')
    );
    $albumVisible->addRule('isInteger', _t('请填写整数'));
    $layout->addItem($albumVisible);
}
