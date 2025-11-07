/**
 * WordPress Toolkit - 核心JavaScript框架
 * 统一的AJAX、表单验证、通知系统
 */

(function(window, $) {
    'use strict';

    // 工具包核心对象
    window.ToolkitCore = {

        // 配置选项
        config: {
            ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: '',
            strings: {
                saveSuccess: '保存成功！',
                saveError: '保存失败，请重试。',
                networkError: '网络错误，请重试。',
                confirmDelete: '确定要删除这个项目吗？此操作不可撤销。',
                deleteSuccess: '删除成功！',
                deleteError: '删除失败，请重试。',
                loading: '加载中...',
                processing: '处理中...',
                confirm: '确定',
                cancel: '取消'
            }
        },

        // 初始化核心功能
        init: function(options) {
            if (options) {
                $.extend(this.config, options);
            }

            this.bindGlobalEvents();
            this.initNotices();
        },

        // 绑定全局事件
        bindGlobalEvents: function() {
            var self = this;

            // 自动绑定带有data属性的元素
            $(document).on('click', '[data-ajax-action]', function(e) {
                e.preventDefault();
                self.handleAjaxAction($(this));
            });

            // 自动绑定表单提交
            $(document).on('submit', '[data-ajax-form]', function(e) {
                e.preventDefault();
                self.handleAjaxForm($(this));
            });

            // 自动绑定模态框关闭
            $(document).on('click', '[data-modal-close]', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // 点击模态框外部关闭
            $(document).on('click', '.tc-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
        },

        // AJAX请求封装
        ajax: function(options) {
            var defaults = {
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 30000
            };

            var settings = $.extend({}, defaults, options);

            // 添加默认的nonce
            if (!settings.data || !settings.data.nonce) {
                settings.data = settings.data || {};
                settings.data.nonce = this.config.nonce;
            }

            return $.ajax(settings)
                .fail(function(xhr, status, error) {
                    console.error('ToolkitCore AJAX Error:', {
                        status: status,
                        error: error,
                        xhrStatus: xhr.status,
                        responseText: xhr.responseText
                    });
                });
        },

        // 处理AJAX动作
        handleAjaxAction: function($element) {
            var action = $element.data('ajax-action');
            var confirmMessage = $element.data('confirm');
            var loadingText = $element.data('loading') || this.config.strings.processing;
            var successMessage = $element.data('success') || this.config.strings.saveSuccess;
            var errorMessage = $element.data('error') || this.config.strings.saveError;

            var self = this;

            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            var originalText = $element.text();
            var originalDisabled = $element.prop('disabled');

            // 设置加载状态
            $element.prop('disabled', true).text(loadingText);

            // 构建请求数据
            var data = {
                action: action
            };

            // 添加data属性中的所有数据
            $.each($element.data(), function(key, value) {
                if (key.indexOf('ajax') !== 0 && key !== 'confirm' && key !== 'loading' && key !== 'success' && key !== 'error') {
                    data[key] = value;
                }
            });

            this.ajax({
                data: data
            }).done(function(response) {
                if (response.success) {
                    self.showNotice('success', response.data && response.data.message ? response.data.message : successMessage);

                    // 触发自定义事件
                    $element.trigger('ajax.success', [response]);

                    // 如果有回调函数
                    var callback = $element.data('callback');
                    if (callback && typeof self[callback] === 'function') {
                        self[callback](response, $element);
                    }
                } else {
                    self.showNotice('error', response.data && response.data.message ? response.data.message : errorMessage);
                }
            }).fail(function() {
                self.showNotice('error', self.config.strings.networkError);
            }).always(function() {
                $element.prop('disabled', originalDisabled).text(originalText);
            });
        },

        // 处理AJAX表单
        handleAjaxForm: function($form) {
            var action = $form.data('ajax-form');
            var confirmMessage = $form.data('confirm');
            var loadingText = $form.data('loading') || this.config.strings.processing;
            var successMessage = $form.data('success') || this.config.strings.saveSuccess;
            var errorMessage = $form.data('error') || this.config.strings.saveError;
            var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');

            var self = this;

            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            // 表单验证
            if (!this.validateForm($form)) {
                return false;
            }

            var originalText = $submitBtn.text();
            var originalDisabled = $submitBtn.prop('disabled');

            // 设置加载状态
            $submitBtn.prop('disabled', true).text(loadingText);
            $form.addClass('loading');

            // 收集表单数据
            var formData = {
                action: action
            };

            $form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                if (name && name !== 'nonce') {
                    formData[name] = value;
                }
            });

            this.ajax({
                data: formData
            }).done(function(response) {
                if (response.success) {
                    self.showNotice('success', response.data && response.data.message ? response.data.message : successMessage);

                    // 触发表单成功事件
                    $form.trigger('form.success', [response]);

                    // 重置表单（如果需要）
                    if ($form.data('reset')) {
                        $form[0].reset();
                    }

                    // 如果有回调函数
                    var callback = $form.data('callback');
                    if (callback && typeof self[callback] === 'function') {
                        self[callback](response, $form);
                    }
                } else {
                    self.showNotice('error', response.data && response.data.message ? response.data.message : errorMessage);
                }
            }).fail(function() {
                self.showNotice('error', self.config.strings.networkError);
            }).always(function() {
                $submitBtn.prop('disabled', originalDisabled).text(originalText);
                $form.removeClass('loading');
            });
        },

        // 表单验证
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            var self = this;

            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                var label = $field.prev('label').text() || $field.attr('name') || '字段';

                if (!value) {
                    errors.push(label + '不能为空');
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });

            // 邮箱验证
            $form.find('[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                if (value && !self.isValidEmail(value)) {
                    errors.push('请输入有效的邮箱地址');
                    $field.addClass('error');
                    isValid = false;
                }
            });

            // URL验证
            $form.find('[type="url"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                if (value && !self.isValidUrl(value)) {
                    errors.push('请输入有效的URL地址');
                    $field.addClass('error');
                    isValid = false;
                }
            });

            // 数字范围验证
            $form.find('[type="number"]').each(function() {
                var $field = $(this);
                var value = parseFloat($field.val());
                var min = parseFloat($field.attr('min'));
                var max = parseFloat($field.attr('max'));

                if (!isNaN(value)) {
                    if (!isNaN(min) && value < min) {
                        errors.push('数值不能小于 ' + min);
                        $field.addClass('error');
                        isValid = false;
                    }
                    if (!isNaN(max) && value > max) {
                        errors.push('数值不能大于 ' + max);
                        $field.addClass('error');
                        isValid = false;
                    }
                }
            });

            if (errors.length > 0) {
                this.showErrors(errors);
            } else {
                this.hideErrors();
            }

            return isValid;
        },

        // 显示错误信息
        showErrors: function(errors) {
            var $errorContainer = $('#tc-form-errors');
            if ($errorContainer.length === 0) {
                $errorContainer = $('<div id="tc-form-errors"></div>');
                $('.wrap h1, .time-capsule-admin h1, .gdpr-admin-header h1').first().after($errorContainer);
            }

            $errorContainer.empty();
            errors.forEach(function(error) {
                $errorContainer.append('<div class="tc-notice tc-notice-error"><p>' + error + '</p></div>');
            });
        },

        // 隐藏错误信息
        hideErrors: function() {
            $('#tc-form-errors').empty();
        },

        // 通知系统初始化
        initNotices: function() {
            var $noticeContainer = $('#tc-notices');
            if ($noticeContainer.length === 0) {
                $noticeContainer = $('<div id="tc-notices"></div>');
                $('.wrap h1, .time-capsule-admin h1, .gdpr-admin-header h1').first().after($noticeContainer);
            }
        },

        // 显示通知
        showNotice: function(type, message, options) {
            var opts = $.extend({
                autoHide: true,
                duration: 5000,
                dismissible: true
            }, options);

            var $notice = $('<div class="tc-notice tc-notice-' + type + (opts.dismissible ? ' is-dismissible' : '') + '"><p>' + message + '</p></div>');
            var $container = $('#tc-notices');

            $container.empty().append($notice);

            if (opts.autoHide) {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $notice.remove();
                    });
                }, opts.duration);
            }

            if (opts.dismissible) {
                $notice.on('click', '.notice-dismiss', function(e) {
                    e.preventDefault();
                    $notice.fadeOut(function() {
                        $notice.remove();
                    });
                });
            }
        },

        // 模态框系统
        openModal: function(title, content, options) {
            var opts = $.extend({
                showFooter: true,
                saveText: this.config.strings.save,
                cancelText: this.config.strings.cancel,
                onSave: null
            }, options);

            var $modal = this.getModalElement();

            $('#tc-modal-title').text(title);
            $('#tc-modal-body').html(content);

            if (opts.showFooter) {
                $('#tc-modal-save').text(opts.saveText).show();
                $('#tc-modal-cancel').text(opts.cancelText).show();
            } else {
                $('#tc-modal-save').hide();
                $('#tc-modal-cancel').hide();
            }

            $modal.show();

            // 绑定保存按钮事件
            if (opts.onSave && typeof opts.onSave === 'function') {
                $('#tc-modal-save').off('click').on('click', opts.onSave);
            }
        },

        closeModal: function() {
            $('#tc-modal').hide();
        },

        getModalElement: function() {
            var $modal = $('#tc-modal');
            if ($modal.length === 0) {
                $modal = $(this.getModalTemplate());
                $('body').append($modal);
            }
            return $modal;
        },

        getModalTemplate: function() {
            return '<div id="tc-modal" class="tc-modal" style="display: none;">' +
                '<div class="tc-modal-overlay"></div>' +
                '<div class="tc-modal-content">' +
                '<div class="tc-modal-header">' +
                '<h3 id="tc-modal-title"></h3>' +
                '<button type="button" class="tc-modal-close" data-modal-close>&times;</button>' +
                '</div>' +
                '<div class="tc-modal-body" id="tc-modal-body"></div>' +
                '<div class="tc-modal-footer">' +
                '<button type="button" class="tc-btn tc-btn-secondary" id="tc-modal-cancel" data-modal-close></button>' +
                '<button type="button" class="tc-btn tc-btn-primary" id="tc-modal-save"></button>' +
                '</div>' +
                '</div>' +
                '</div>';
        },

        // 工具函数
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        isLocalIP: function(ip) {
            var localRanges = [
                /^127\./,
                /^10\./,
                /^172\.(1[6-9]|2[0-9]|3[01])\./,
                /^192\.168\./,
                /^169\.254\./,
                /^::1$/,
                /^fc00:/,
                /^fe80:/
            ];

            return localRanges.some(function(range) {
                return range.test(ip);
            });
        },

        formatNumber: function(num, decimals) {
            decimals = decimals || 2;
            return parseFloat(num).toFixed(decimals);
        },

        formatCurrency: function(amount) {
            return '¥' + this.formatNumber(amount, 2);
        },

        // 加载状态管理
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.data('original-text', $button.text());
                $button.prop('disabled', true).text(this.config.strings.processing);
                $button.addClass('loading');
            } else {
                var originalText = $button.data('original-text') || $button.text();
                $button.prop('disabled', false).text(originalText);
                $button.removeClass('loading');
            }
        },

        // 文件下载
        downloadFile: function(url, filename) {
            var $link = $('<a>')
                .attr({
                    href: url,
                    download: filename || 'download'
                })
                .appendTo('body');

            $link[0].click();
            $link.remove();
        },

        // 延迟执行
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // 节流执行
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        }
    };

    // 自动初始化
    $(document).ready(function() {
        // 检查是否有全局配置
        var globalConfig = window.ToolkitConfig || {};
        ToolkitCore.init(globalConfig);
    });

})(window, jQuery);