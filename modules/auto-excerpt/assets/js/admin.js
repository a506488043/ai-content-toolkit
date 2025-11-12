/**
 * Auto Excerpt Admin JavaScript - 简化版本
 * 自动摘要管理后台脚本
 */

jQuery(document).ready(function($) {
    'use strict';

    // 获取DOM元素
    const generateBtn = $('#generate-excerpt-btn');
    const excerptResult = $('#excerpt-result');
    const excerptTextarea = $('#excerpt');

    // 当前生成的摘要
    let currentExcerpt = '';

    // 检查必要的元素是否存在
    if (!generateBtn.length || !excerptResult.length) {
        console.log('Auto Excerpt: 必要的DOM元素未找到');
        return;
    }

    // 绑定事件
    generateBtn.on('click', function() {
        generateSimpleExcerpt();
    });

    /**
     * 简化版摘要生成
     */
    function generateSimpleExcerpt() {
        const postId = $('#post_ID').val();
        let content = '';

        // 尝试获取文章内容
        if ($('#content').length) {
            content = $('#content').val();
        } else if (window.tinyMCE && window.tinyMCE.activeEditor) {
            content = window.tinyMCE.activeEditor.getContent();
        } else if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
            const editor = wp.data.select('core/editor');
            if (editor) {
                content = editor.getEditedPostContent();
            }
        }

        if (!content || content.length < 100) {
            excerptResult.html('<p style="color: #d63638;">内容太短，无法生成摘要（需要至少100字符）</p>');
            return;
        }

        // 显示加载状态
        generateBtn.prop('disabled', true).text('生成中...');
        excerptResult.html('<p style="color: #0073aa;">正在生成摘要...</p>');

        // 调试日志
        console.log('Auto Excerpt: 开始AJAX请求');
        console.log('Auto Excerpt: Config', AutoExcerptConfig);
        console.log('Auto Excerpt: Action - generate_single_excerpt');
        console.log('Auto Excerpt: Nonce -', AutoExcerptConfig.nonce);
        console.log('Auto Excerpt: Post ID -', postId);

        // 调用AJAX生成摘要
        $.ajax({
            url: AutoExcerptConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_single_excerpt',
                nonce: AutoExcerptConfig.nonce,
                post_id: postId,
                content: content,
                append_mode: false,
                length: 150,
                smart_extraction: true
            },
            success: function(response) {
                console.log('Auto Excerpt: AJAX响应', response);
                generateBtn.prop('disabled', false).text('生成智能摘要');

                if (response.success) {
                    currentExcerpt = response.data.excerpt;
                    const isAI = response.data.ai_generated;
                    const aiIcon = isAI ? '🤖 ' : '';
                    const aiBg = isAI ? 'background: #f0f8ff; border-left-color: #0073aa;' : 'background: #f0f6fc; border-left-color: #00a32a;';

                    excerptResult.html('<div style="' + aiBg + ' padding: 10px; border-left: 4px solid; margin-bottom: 10px;"><strong>' + aiIcon + '摘要生成成功！</strong><br><br>' + response.data.excerpt + '</div><button type="button" id="apply-excerpt-btn" class="button button-primary" style="margin-top: 10px;">应用此摘要</button>');

                    // 绑定应用按钮事件
                    $('#apply-excerpt-btn').on('click', function() {
                        if (currentExcerpt) {
                            excerptTextarea.val(currentExcerpt);
                            excerptTextarea.trigger('change');
                            excerptResult.html('<p style="color: #00a32a;">✅ 摘要已应用到文章中</p>');
                        }
                    });
                } else {
                    console.log('Auto Excerpt: 生成失败', response.data);
                    excerptResult.html('<p style="color: #d63638;">生成失败：' + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log('Auto Excerpt: AJAX错误', xhr.responseText);
                generateBtn.prop('disabled', false).text('生成智能摘要');
                excerptResult.html('<p style="color: #d63638;">网络错误：' + error + '</p><pre>' + xhr.responseText + '</pre>');
            }
        });
    }

    // 初始化检查
    console.log('Auto Excerpt: JavaScript 已加载');
});