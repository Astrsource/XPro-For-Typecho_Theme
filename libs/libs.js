/**
 * XPro 主题 - 短代码插入助手
 *
 * 根据 libs/ContentFilter.php 的短代码定义生成对应代码，
 * 以模态框形式在 Typecho 后台文章/页面编辑器中使用。
 *
 * 使用前提：页面存在 <textarea id="text">（Typecho 原生编辑器）。
 * 加载本脚本后会自动创建右下角浮动按钮与模态框。
 */
(function () {
  'use strict';

  if (window.XProShortcodeHelper) {
    return;
  }

  var STYLE_ID = 'xpro-sc-style';
  if (!document.getElementById(STYLE_ID)) {
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent =
      '#wmd-button-row #xpro-sc-fab{' +
      'width:auto;height:auto;overflow:visible;' +
      'display:inline-flex;align-items:center;padding:3px 10px;margin:0 4px 0 0;' +
      'border:1px solid #b9c6d2;border-radius:4px;background:#fff;' +
      'color:#467B96;font-size:12px;font-weight:600;line-height:1.6;cursor:pointer;' +
      'font-family:inherit;box-sizing:border-box;text-indent:0;text-transform:none;}' +
      '#wmd-button-row #xpro-sc-fab:hover{background:#e8f1f8;border-color:#467B96;}' +
      '.xpro-sc-overlay{' +
      'position:fixed;inset:0;z-index:99991;display:none;align-items:center;justify-content:center;' +
      'background:rgba(15,23,42,.45);backdrop-filter:blur(3px);padding:20px;}' +
      '.xpro-sc-overlay.open{display:flex;}' +
      '.xpro-sc-modal{' +
      'width:min(720px,100%);max-height:88vh;display:flex;flex-direction:column;' +
      'background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.28);' +
      'overflow:hidden;color:#1f2937;font-size:14px;}' +
      '.xpro-sc-head{display:flex;align-items:center;justify-content:space-between;' +
      'padding:14px 18px;border-bottom:1px solid #eef1f5;background:#fafbfc;}' +
      '.xpro-sc-head h3{margin:0;font-size:16px;font-weight:700;color:#1f2937;}' +
      '.xpro-sc-close{border:none;background:transparent;cursor:pointer;font-size:20px;' +
      'color:#8a94a6;line-height:1;padding:4px 8px;border-radius:6px;}' +
      '.xpro-sc-close:hover{background:#eef1f5;color:#1f2937;}' +
      '.xpro-sc-types{display:flex;flex-wrap:wrap;gap:6px;padding:12px 16px;' +
      'border-bottom:1px solid #eef1f5;background:#fff;}' +
      '.xpro-sc-type-btn{border:1px solid #d8dee8;background:#fff;color:#4b5563;' +
      'padding:5px 12px;border-radius:999px;cursor:pointer;font-size:13px;transition:all .18s;}' +
      '.xpro-sc-type-btn:hover{border-color:#1890ff;color:#1890ff;}' +
      '.xpro-sc-type-btn.active{background:#1890ff;border-color:#1890ff;color:#fff;font-weight:600;}' +
      '.xpro-sc-body{flex:1;overflow-y:auto;padding:16px 18px;}' +
      '.xpro-sc-field{margin-bottom:14px;}' +
      '.xpro-sc-field>label{display:block;margin-bottom:5px;font-size:13px;font-weight:600;color:#374151;}' +
      '.xpro-sc-field .xpro-sc-help{display:block;margin-top:4px;font-size:12px;color:#8a94a6;line-height:1.5;}' +
      '.xpro-sc-input{width:100%;box-sizing:border-box;padding:8px 11px;border:1px solid #d8dee8;height:36px;' +
      'border-radius:8px;font-size:13px;color:#1f2937;background:#fff;font-family:inherit;}' +
      '.xpro-sc-input:focus{outline:none;border-color:#1890ff;box-shadow:0 0 0 3px rgba(24,144,255,.14);}' +
      'textarea.xpro-sc-input{min-height:72px;line-height:1.6;resize:vertical;}' +
      'select.xpro-sc-input{padding-right:28px;}' +
      '.xpro-sc-check{display:inline-flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;color:#374151;}' +
      '.xpro-sc-check input{width:16px;height:16px;accent-color:#1890ff;}' +
      '.xpro-sc-repeat-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}' +
      '.xpro-sc-repeat-head>label{font-size:13px;font-weight:600;color:#374151;margin:0;}' +
      '.xpro-sc-add-btn{border:1px dashed #1890ff;color:#1890ff;background:#f4f8ff;' +
      'padding:4px 12px;border-radius:999px;cursor:pointer;font-size:12px;}' +
      '.xpro-sc-add-btn:hover{background:#e8f3ff;}' +
      '.xpro-sc-repeat-item{border:1px solid #eef1f5;border-radius:10px;padding:12px 14px;margin-bottom:10px;' +
      'background:#fafbfc;position:relative;}' +
      '.xpro-sc-repeat-item .xpro-sc-del{position:absolute;top:8px;right:8px;border:none;background:transparent;' +
      'color:#b0b8c4;cursor:pointer;font-size:16px;line-height:1;padding:2px 6px;border-radius:6px;}' +
      '.xpro-sc-repeat-item .xpro-sc-del:hover{background:#fee2e2;color:#ef4444;}' +
      '.xpro-sc-repeat-item .xpro-sc-field{margin-bottom:10px;}' +
      '.xpro-sc-repeat-item .xpro-sc-field:last-child{margin-bottom:0;}' +
      '.xpro-sc-repeat-item .xpro-sc-field>label{font-size:12.5px;}' +
      '.xpro-sc-preview{padding:12px 18px;border-top:1px solid #eef1f5;background:#fafbfc;}' +
      '.xpro-sc-preview label{display:block;font-size:12px;color:#8a94a6;margin-bottom:6px;}' +
      '.xpro-sc-preview textarea{width:100%;box-sizing:border-box;min-height:84px;' +
      'background:#0f172a;color:#dbeafe;border:none;border-radius:8px;padding:10px 12px;' +
      'font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.6;resize:vertical;}' +
      '.xpro-sc-foot{display:flex;justify-content:flex-end;gap:10px;padding:12px 18px;' +
      'border-top:1px solid #eef1f5;background:#fff;}' +
      '.xpro-sc-btn{padding:8px 20px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;' +
      'border:1px solid #d8dee8;background:#fff;color:#4b5563;transition:all .18s;font-family:inherit;}' +
      '.xpro-sc-btn:hover{border-color:#1890ff;color:#1890ff;}' +
      '.xpro-sc-btn.primary{background:#1890ff;border-color:#1890ff;color:#fff;}' +
      '.xpro-sc-btn.primary:hover{background:#40a9ff;border-color:#40a9ff;}' +
      '.xpro-sc-empty{color:#8a94a6;font-size:13px;text-align:center;padding:10px 0;}';
    document.head.appendChild(style);
  }

  /* ==================== 短代码定义 ==================== */
  function flagAttrs(flag) {
    return flag ? ' ' + flag : '';
  }

  function buildAttrs(pairs) {
    return pairs
      .filter(function (p) {
        var v = p[1];
        return v !== undefined && v !== null && v !== '' && v !== false;
      })
      .map(function (p) {
        return p[0] + '="' + String(p[1]).replace(/["']/g, '') + '"';
      })
      .join(' ');
  }

  function contentValue(v, sel) {
    var c = (v.content || '').trim();
    return c !== '' ? c : (sel && sel.trim() !== '' ? sel.trim() : '内容');
  }

  var SHORTCODES = {
    button: {
      label: '按钮',
      desc: '普通按钮（可带点击脚本）',
      fields: [
        { name: 'theme', label: '主题', type: 'select', def: 'primary',
          options: [['primary', '主色'], ['secondary', '次要'], ['ghost', '幽灵'], ['danger', '危险']] },
        { name: 'size', label: '尺寸', type: 'select',
          options: [['', '默认'], ['sm', '小'], ['lg', '大']] },
        { name: 'onclick', label: '点击脚本（可选）', type: 'text', ph: '如 alert(1)' },
        { name: 'content', label: '按钮文字', type: 'textarea', def: '点击这里' }
      ],
      build: function (v, sel) {
        var a = buildAttrs([['theme', v.theme], ['size', v.size], ['onclick', v.onclick]]);
        return '[button' + (a ? ' ' + a : '') + ']' + contentValue(v, sel) + '[/button]';
      }
    },
    abutton: {
      label: '外链按钮',
      desc: '按钮形式的链接',
      fields: [
        { name: 'url', label: '链接地址', type: 'text', def: 'https://' },
        { name: 'theme', label: '主题', type: 'select', def: 'primary',
          options: [['primary', '主色'], ['secondary', '次要'], ['ghost', '幽灵'], ['danger', '危险']] },
        { name: 'size', label: '尺寸', type: 'select',
          options: [['', '默认'], ['sm', '小'], ['lg', '大']] },
        { name: 'content', label: '按钮文字', type: 'textarea', def: '前往查看' }
      ],
      build: function (v, sel) {
        var a = buildAttrs([['url', v.url], ['theme', v.theme], ['size', v.size]]);
        return '[abutton' + (a ? ' ' + a : '') + ']' + contentValue(v, sel) + '[/abutton]';
      }
    },
    collapse: {
      label: '折叠框',
      desc: '可展开/收起的内容块',
      fields: [
        { name: 'title', label: '标题', type: 'text', def: '展开查看' },
        { name: 'open', label: '默认展开', type: 'checkbox', flag: true },
        { name: 'content', label: '内容', type: 'textarea', def: '折叠内容' }
      ],
      build: function (v, sel) {
        var a = buildAttrs([['title', v.title]]) + flagAttrs(v.open ? 'open' : '');
        return '[collapse' + (a ? ' ' + a : '') + ']' + contentValue(v, sel) + '[/collapse]';
      }
    },
    tabs: {
      label: '选项卡',
      desc: '多个标签页切换',
      repeat: {
        addText: '添加选项卡',
        fields: [
          { name: 'title', label: '标签名', type: 'text', def: '标签' },
          { name: 'active', label: '默认激活', type: 'checkbox', flag: true },
          { name: 'content', label: '内容', type: 'textarea', def: '选项卡内容' }
        ]
      },
      build: function (v, sel) {
        var items = (v.items || []).filter(function (it) { return it.title !== '' || it.content !== ''; });
        if (!items.length) {
          items = [{ title: '标签一', active: true, content: '内容一' }, { title: '标签二', active: false, content: '内容二' }];
        }
        var lines = items.map(function (it) {
          var a = buildAttrs([['title', it.title]]) + flagAttrs(it.active ? 'active' : '');
          return '[tab' + (a ? ' ' + a : '') + ']' + (it.content || '内容') + '[/tab]';
        });
        return '[tabs]\n' + lines.join('\n') + '\n[/tabs]';
      }
    },
    timeline: {
      label: '时间线',
      desc: '带日期的事件列表',
      repeat: {
        addText: '添加事件',
        fields: [
          { name: 'date', label: '日期', type: 'text', ph: '如 2026-01-01' },
          { name: 'title', label: '标题', type: 'text', def: '事件标题' },
          { name: 'content', label: '描述', type: 'textarea', def: '事件描述' }
        ]
      },
      build: function (v) {
        var items = (v.items || []).filter(function (it) { return it.title !== '' || it.content !== ''; });
        if (!items.length) {
          items = [{ date: '2026-01-01', title: '事件一', content: '描述一' }];
        }
        var lines = items.map(function (it) {
          var a = buildAttrs([['date', it.date], ['title', it.title]]);
          return '[event' + (a ? ' ' + a : '') + ']' + (it.content || '描述') + '[/event]';
        });
        return '[timeline]\n' + lines.join('\n') + '\n[/timeline]';
      }
    },
    alert: {
      label: '提示框',
      desc: '信息/成功/警告/危险提示',
      fields: [
        { name: 'type', label: '类型', type: 'select', def: 'info',
          options: [['info', '信息'], ['success', '成功'], ['warning', '警告'], ['danger', '危险']] },
        { name: 'title', label: '标题（可选）', type: 'text' },
        { name: 'content', label: '内容', type: 'textarea', def: '提示内容' }
      ],
      build: function (v, sel) {
        var a = buildAttrs([['type', v.type], ['title', v.title]]);
        return '[alert' + (a ? ' ' + a : '') + ']' + contentValue(v, sel) + '[/alert]';
      }
    },
    progress: {
      label: '进度条',
      desc: '技能/进度展示',
      fields: [
        { name: 'label', label: '名称', type: 'text', def: '技能' },
        { name: 'value', label: '数值（0-100）', type: 'number', def: '80' },
        { name: 'color', label: '颜色', type: 'select',
          options: [['', '默认'], ['success', '绿色'], ['warning', '橙色'], ['danger', '红色']] }
      ],
      build: function (v) {
        var n = Math.max(0, Math.min(100, parseInt(v.value, 10) || 0));
        var a = buildAttrs([['label', v.label], ['value', String(n)], ['color', v.color]]);
        return '[progress' + (a ? ' ' + a : '') + ']';
      }
    },
    gallery: {
      label: '图片网格',
      desc: '多图网格/相册',
      fields: [
        { name: 'cols', label: '列数', type: 'select', def: '3',
          options: [['2', '2 列'], ['3', '3 列'], ['4', '4 列']] },
        { name: 'visible', label: '可见张数（可选，小于总数时折叠为相册）', type: 'number' },
        { name: 'ar', label: '宽高比（可选，如 16/9）', type: 'text' },
        { name: 'content', label: '图片地址（每行一个 URL）', type: 'textarea', def: 'https://example.com/1.jpg\nhttps://example.com/2.jpg',
          help: '生成时为 Markdown 图片语法，如 ![](https://example.com/1.jpg)' }
      ],
      build: function (v) {
        var a = buildAttrs([['cols', v.cols], ['visible', v.visible], ['ar', v.ar]]);
        var imgs = String(v.content || '').split(/\r?\n/).map(function (u) { return u.trim(); })
          .filter(Boolean)
          .map(function (u) { return '![](' + u.replace(/["'()]/g, '') + ')'; });
        if (!imgs.length) {
          imgs = ['![](https://example.com/1.jpg)'];
        }
        return '[gallery' + (a ? ' ' + a : '') + ']\n' + imgs.join('\n') + '\n[/gallery]';
      }
    },
    download: {
      label: '下载卡片',
      desc: '文件下载入口 + 提取码',
      fields: [
        { name: 'name', label: '文件名', type: 'text', def: '文件.zip' },
        { name: 'size', label: '大小（可选）', type: 'text', ph: '如 100MB' },
        { name: 'url', label: '下载地址', type: 'text', def: 'https://' },
        { name: 'code', label: '提取码（可选）', type: 'text' },
        { name: 'source', label: '来源（可选）', type: 'text', def: '网盘' }
      ],
      build: function (v) {
        var a = buildAttrs([['name', v.name], ['size', v.size], ['url', v.url], ['code', v.code], ['source', v.source]]);
        return '[download' + (a ? ' ' + a : '') + ']';
      }
    },
    github: {
      label: 'GitHub 卡片',
      desc: '仓库信息卡片',
      fields: [
        { name: 'repo', label: '仓库（owner/repo）', type: 'text', def: 'owner/repo' }
      ],
      build: function (v) {
        return '[github repo="' + (v.repo || 'owner/repo') + '"]';
      }
    },
    bilibili: {
      label: 'B站卡片',
      desc: '视频信息卡片',
      fields: [
        { name: 'bv', label: 'BV 号', type: 'text', ph: '如 BV1xx411c7mD' },
        { name: 'av', label: 'AV 号（与 BV 二选一）', type: 'text', ph: '如 170001' }
      ],
      build: function (v) {
        if (v.bv) {
          return '[bilibili bv="' + v.bv + '"]';
        }
        return '[bilibili av="' + (v.av || '') + '"]';
      }
    },
    music: {
      label: '音乐卡片',
      desc: '平台歌曲或手动音频',
      fields: [
        { name: 'platform', label: '来源', type: 'select', def: 'wy',
          options: [['wy', '网易云'], ['tx', 'QQ音乐'], ['kg', '酷狗'], ['manual', '手动音频']] },
        { name: 'wy', label: '网易云歌曲 ID', type: 'text', showIf: function (v) { return v.platform === 'wy'; } },
        { name: 'tx', label: 'QQ音乐歌曲 ID', type: 'text', showIf: function (v) { return v.platform === 'tx'; } },
        { name: 'kg', label: '酷狗歌曲 Hash', type: 'text', showIf: function (v) { return v.platform === 'kg'; } },
        { name: 'title', label: '歌名', type: 'text', def: '歌名', showIf: function (v) { return v.platform === 'manual'; } },
        { name: 'artist', label: '歌手', type: 'text', def: '歌手', showIf: function (v) { return v.platform === 'manual'; } },
        { name: 'url', label: '音频地址', type: 'text', def: '/歌曲.mp3', showIf: function (v) { return v.platform === 'manual'; } },
        { name: 'pic', label: '封面图（可选）', type: 'text', showIf: function (v) { return v.platform === 'manual'; } }
      ],
      build: function (v) {
        if (v.platform === 'manual') {
          var a = buildAttrs([['title', v.title], ['artist', v.artist], ['url', v.url], ['pic', v.pic]]);
          return '[music' + (a ? ' ' + a : '') + ']';
        }
        var id = String(v[v.platform] || '').trim();
        if (!id) {
          return '[music ' + v.platform + '="ID"]';
        }
        return '[music ' + v.platform + '="' + id + '"]';
      }
    },
    post: {
      label: '文章引用',
      desc: '引用站内文章卡片',
      fields: [
        { name: 'cid', label: '文章 CID', type: 'number', def: '' }
      ],
      build: function (v) {
        return '[post cid="' + (v.cid || '') + '"]';
      }
    },
    page: {
      label: '页面引用',
      desc: '引用站内独立页面卡片',
      fields: [
        { name: 'cid', label: '页面 CID', type: 'number', def: '' }
      ],
      build: function (v) {
        return '[page cid="' + (v.cid || '') + '"]';
      }
    }
  };

  /* ==================== 模态框逻辑 ==================== */
  var state = {
    type: 'button',
    textarea: null,
    modal: null,
    form: null,
    preview: null
  };

  function getSelection() {
    var ta = state.textarea;
    if (!ta) return '';
    return ta.value.substring(ta.selectionStart, ta.selectionEnd);
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'class') node.className = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else node.setAttribute(k, attrs[k]);
      });
    }
    (children || []).forEach(function (c) {
      node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return node;
  }

  function fieldEl(field, value) {
    var wrap = el('div', { class: 'xpro-sc-field' });
    var label = el('label', {}, [field.label || field.name]);
    wrap.appendChild(label);

    var input;
    if (field.type === 'select') {
      input = el('select', { class: 'xpro-sc-input', name: field.name });
      (field.options || []).forEach(function (opt) {
        var o = el('option', { value: opt[0] }, [opt[1]]);
        if (String(value) === String(opt[0])) o.setAttribute('selected', 'selected');
        input.appendChild(o);
      });
    } else if (field.type === 'checkbox') {
      input = el('input', { class: 'xpro-sc-input', type: 'checkbox', name: field.name });
      if (value) input.setAttribute('checked', 'checked');
      wrap.appendChild(el('label', { class: 'xpro-sc-check' }, [input, field.label || field.name]));
      wrap.removeChild(label);
      return wrap;
    } else if (field.type === 'textarea') {
      input = el('textarea', { class: 'xpro-sc-input', name: field.name });
      input.value = value || '';
    } else if (field.type === 'number') {
      input = el('input', { class: 'xpro-sc-input', type: 'number', name: field.name });
      if (value !== undefined && value !== null) input.value = value;
    } else {
      input = el('input', { class: 'xpro-sc-input', type: 'text', name: field.name });
      if (value !== undefined && value !== null) input.value = value;
    }
    if (field.ph) input.setAttribute('placeholder', field.ph);
    wrap.appendChild(input);
    if (field.help) wrap.appendChild(el('span', { class: 'xpro-sc-help' }, [field.help]));
    return wrap;
  }

  function renderRepeatItem(container, repeat, values, idx) {
    var item = el('div', { class: 'xpro-sc-repeat-item', 'data-idx': String(idx) });
    repeat.fields.forEach(function (f) {
      item.appendChild(fieldEl(f, values[f.name] !== undefined ? values[f.name] : f.def));
    });
    var del = el('button', { class: 'xpro-sc-del', type: 'button', title: '删除该项', html: '&times;' });
    del.addEventListener('click', function () {
      item.remove();
      state.form.dispatchEvent(new Event('input', { bubbles: true }));
    });
    item.appendChild(del);
    container.appendChild(item);
  }

  function renderForm() {
    var def = SHORTCODES[state.type];
    state.form.innerHTML = '';
    if (!def) return;

    var defs = def.fields || [];
    var repeat = def.repeat || null;

    defs.forEach(function (f) {
      var wrap = fieldEl(f, f.def);
      if (f.showIf) wrap.setAttribute('data-showif', '1');
      state.form.appendChild(wrap);
    });

    if (repeat) {
      var head = el('div', { class: 'xpro-sc-repeat-head' });
      head.appendChild(el('label', {}, [repeat.addText || '添加条目']));
      var addBtn = el('button', { class: 'xpro-sc-add-btn', type: 'button' }, ['+ 添加']);
      addBtn.addEventListener('click', function () {
        renderRepeatItem(list, repeat, {}, list.children.length);
        state.form.dispatchEvent(new Event('input', { bubbles: true }));
      });
      head.appendChild(addBtn);
      state.form.appendChild(head);

      var list = el('div', { class: 'xpro-sc-repeat-list' });
      state.form.appendChild(list);
      renderRepeatItem(list, repeat, {}, 0);
    }

    state.form.addEventListener('input', function () {
      applyShowIf();
      renderPreview();
    });
    state.form.addEventListener('change', function () {
      applyShowIf();
      renderPreview();
    });
    applyShowIf();
  }

  function applyShowIf() {
    var values = collectValues();
    Array.prototype.forEach.call(state.form.querySelectorAll('[data-showif]'), function (wrap) {
      var input = wrap.querySelector('.xpro-sc-input');
      var name = input ? input.getAttribute('name') : '';
      var field = null;
      (SHORTCODES[state.type].fields || []).forEach(function (f) {
        if (f.name === name) field = f;
      });
      wrap.style.display = field && field.showIf && !field.showIf(values) ? 'none' : '';
    });
  }

  function collectValues() {
    var def = SHORTCODES[state.type];
    var values = {};
    (def.fields || []).forEach(function (f) {
      var input = state.form.querySelector('[name="' + f.name + '"]');
      if (!input) return;
      if (f.type === 'checkbox') values[f.name] = input.checked;
      else values[f.name] = input.value.trim();
    });
    if (def.repeat) {
      var items = [];
      Array.prototype.forEach.call(state.form.querySelectorAll('.xpro-sc-repeat-item'), function (item) {
        var it = {};
        def.repeat.fields.forEach(function (f) {
          var input = item.querySelector('[name="' + f.name + '"]');
          if (!input) return;
          if (f.type === 'checkbox') it[f.name] = input.checked;
          else it[f.name] = input.value.trim();
        });
        items.push(it);
      });
      values.items = items;
    }
    return values;
  }

  function renderPreview() {
    var def = SHORTCODES[state.type];
    var values = collectValues();
    var code = def.build(values, getSelection());
    state.preview.value = code;
  }

  function insertCode() {
    var ta = state.textarea;
    if (!ta) return;
    var code = state.preview.value;
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + code + ta.value.substring(end);
    var pos = start + code.length;
    ta.setSelectionRange(pos, pos);
    ta.focus();
    try {
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    } catch (e) { /* 忽略 */ }
    close();
  }

  function close() {
    state.modal.classList.remove('open');
    document.body.style.overflow = '';
  }

  function open() {
    if (!state.textarea) return;
    renderForm();
    renderPreview();
    state.modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function buildModal() {
    var overlay = el('div', { class: 'xpro-sc-overlay', id: 'xpro-sc-overlay' });
    var modal = el('div', { class: 'xpro-sc-modal' });

    var head = el('div', { class: 'xpro-sc-head' });
    head.appendChild(el('h3', {}, ['插入短代码']));
    var closeBtn = el('button', { class: 'xpro-sc-close', type: 'button', title: '关闭', html: '&times;' });
    closeBtn.addEventListener('click', close);
    head.appendChild(closeBtn);
    modal.appendChild(head);

    var types = el('div', { class: 'xpro-sc-types' });
    Object.keys(SHORTCODES).forEach(function (key) {
      var btn = el('button', {
        class: 'xpro-sc-type-btn' + (key === state.type ? ' active' : ''),
        type: 'button',
        'data-type': key
      }, [SHORTCODES[key].label]);
      btn.addEventListener('click', function () {
        state.type = key;
        Array.prototype.forEach.call(types.querySelectorAll('.xpro-sc-type-btn'), function (b) {
          b.classList.toggle('active', b.getAttribute('data-type') === key);
        });
        renderForm();
        renderPreview();
      });
      types.appendChild(btn);
    });
    modal.appendChild(types);

    var body = el('div', { class: 'xpro-sc-body' });
    var form = el('div', { class: 'xpro-sc-form' });
    body.appendChild(form);
    modal.appendChild(body);

    var previewWrap = el('div', { class: 'xpro-sc-preview' });
    previewWrap.appendChild(el('label', {}, ['预览']));
    var preview = el('textarea', { class: 'xpro-sc-preview-input', readonly: 'readonly', spellcheck: 'false' });
    previewWrap.appendChild(preview);
    modal.appendChild(previewWrap);

    var foot = el('div', { class: 'xpro-sc-foot' });
    var cancelBtn = el('button', { class: 'xpro-sc-btn', type: 'button' }, ['取消']);
    cancelBtn.addEventListener('click', close);
    var insertBtn = el('button', { class: 'xpro-sc-btn primary', type: 'button' }, ['插入']);
    insertBtn.addEventListener('click', insertCode);
    foot.appendChild(cancelBtn);
    foot.appendChild(insertBtn);
    modal.appendChild(foot);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('open')) close();
    });

    state.modal = overlay;
    state.form = form;
    state.preview = preview;
  }

  function init() {
    state.textarea = document.querySelector('textarea#text');
    if (!state.textarea) {
      return;
    }
    buildModal();

    /* 在 WMD 编辑器工具栏插入文字按钮 */
    var row = document.getElementById('wmd-button-row') ||
      document.querySelector('#wmd-button-bar .wmd-button-row');
    if (row) {
      var fab = el('li', { class: 'wmd-button', id: 'xpro-sc-fab', title: '插入主题短代码' }, ['插入主题短代码']);
      fab.addEventListener('click', function (e) {
        e.preventDefault();
        open();
      });
      row.appendChild(fab);
    }

    window.XProShortcodeHelper = {
      open: open,
      close: close,
      insert: insertCode,
      types: Object.keys(SHORTCODES)
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
