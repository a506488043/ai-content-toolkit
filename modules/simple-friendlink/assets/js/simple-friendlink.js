/**
 * Simple FriendLink Module JavaScript
 * 简洁友情链接模块脚本
 */

jQuery(document).ready(function($) {
    'use strict';

    // 表单提交处理
    $('#simple-add-link-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $('#submit-btn');
        const $loadingIndicator = $('#loading-indicator');
        const $formMessage = $('#form-message');

        // 显示加载状态
        $submitBtn.prop('disabled', true);
        $loadingIndicator.show();
        $formMessage.hide().removeClass('success error');

        // 收集表单数据
        const formData = {
            action: 'simple_friendlink_add',
            nonce: SimpleFriendLinkConfig.nonce,
            link_name: $('#simple-link-name').val().trim(),
            link_url: $('#simple-link-url').val().trim(),
            link_description: $('#simple-link-description').val().trim(),
            link_email: $('#simple-link-email').val().trim()
        };

        // 验证必填字段
        if (!formData.link_name || !formData.link_url) {
            showFormMessage(__('网站名称和地址为必填项', 'wordpress-toolkit'), 'error');
            $submitBtn.prop('disabled', false);
            $loadingIndicator.hide();
            return;
        }

        // 验证URL格式
        if (!isValidUrl(formData.link_url)) {
            showFormMessage(__('请输入有效的网站地址', 'wordpress-toolkit'), 'error');
            $submitBtn.prop('disabled', false);
            $loadingIndicator.hide();
            return;
        }

        // 发送AJAX请求
        $.ajax({
            url: SimpleFriendLinkConfig.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showFormMessage(response.data.message || SimpleFriendLinkConfig.strings.submitSuccess, 'success');
                    // 重置表单
                    $form[0].reset();
                } else {
                    showFormMessage(response.data.message || SimpleFriendLinkConfig.strings.submitError, 'error');
                }
            },
            error: function(xhr, status, error) {
                showFormMessage(SimpleFriendLinkConfig.strings.submitError, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
                $loadingIndicator.hide();
            }
        });
    });

    // 显示表单消息函数
    function showFormMessage(message, type) {
        $formMessage.text(message).removeClass('success error').addClass(type).show();

        // 3秒后自动隐藏成功消息
        if (type === 'success') {
            setTimeout(function() {
                $formMessage.fadeOut(500);
            }, 3000);
        }
    }

    // URL验证函数
    function isValidUrl(string) {
        try {
            const url = new URL(string);
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    // 表单字段实时验证
    $('#simple-link-url').on('input', function() {
        const $input = $(this);
        const url = $input.val().trim();

        if (url && !isValidUrl(url)) {
            $input.addClass('error');
        } else {
            $input.removeClass('error');
        }
    });

    // 添加URL输入框错误样式
    $('<style>')
        .text('#simple-link-url.error { border-color: #ef4444 !important; }')
        .appendTo('head');

    // 处理图片加载失败，显示首字母占位符
    $('.friend-icon img').each(function() {
        this.onerror = function() {
            this.style.display = 'none';
            const fallback = this.nextElementSibling;
            if (fallback && fallback.classList.contains('friend-icon')) {
                fallback.style.display = 'flex';
            }
        };
    });
});