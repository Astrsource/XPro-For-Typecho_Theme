$(document).ready(function() {
    // 向页面动态添加按钮
    $('#wmd-button-row').append(
        '<li class="wmd-button" id="post-button" title="引入文章">引入文章</li>' +
        '<li class="wmd-button" id="pinyin-button" title="拼音注解">拼音注解</li>'
    );

    // 检查按钮是否成功添加
    if ($('#wmd-button-row').length) {
        // 为“引入文章”按钮绑定点击事件
        $('#post-button').click(function() {
            insertAtCursor($('#text'), '[cid="文章的cid"]');
        });

        // 为“拼音注解”按钮绑定点击事件
        $('#pinyin-button').click(function() {
            insertAtCursor($('#text'), '{{拼音注解写法:pinyin}}');
        });
    }

    // 定义在光标位置插入文本的函数
    function insertAtCursor(myField, myValue) {
        if (document.selection) { // IE浏览器
            myField.focus();
            var sel = document.selection.createRange();
            sel.text = myValue;
            sel.select();
        } else if (myField.selectionStart || myField.selectionStart == '0') { // FireFox、Chrome等
            var startPos = myField.selectionStart;
            var endPos = myField.selectionEnd;
            var restoreTop = myField.scrollTop;
            myField.value = myField.value.substring(0, startPos) +
                myValue +
                myField.value.substring(endPos, myField.value.length);
            if (restoreTop > 0) {
                myField.scrollTop = restoreTop;
            }
            myField.focus();
            myField.selectionStart = startPos + myValue.length;
            myField.selectionEnd = startPos + myValue.length;
        } else {
            myField.value += myValue;
            myField.focus();
        }
    }
});
