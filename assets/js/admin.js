/**
 * WordPress Toolkit - 管理后台脚本
 * 统一的管理界面交互功能
 */

(function($) {
    'use strict';
    
    // 主对象
    const WordPressToolkitAdmin = {
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initColorPickers();
            this.initTooltips();
        },
        
        // 绑定事件
        bindEvents: function() {
            const self = this;
            
            // 页面加载完成后的初始化
            $(document).ready(function() {
                self.initPage();
            });
            
            // 表单提交事件
            $(document).on('submit', '.wordpress-toolkit-admin-form', function(e) {
                self.handleFormSubmit(e);
            });
            
            // 按钮点击事件
            $(document).on('click', '.wordpress-toolkit-button', function(e) {
                self.handleButtonClick(e);
            });
            
            // AJAX错误处理
            $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
                self.handleAjaxError(event, jqXHR, settings, thrownError);
            });
        },
        
        // 初始化页面
        initPage: function() {
            // 添加页面加载动画
            $('.wordpress-toolkit-admin-section').hide().fadeIn(500);
            
            // 初始化工具提示
            this.initTooltips();
            
            // 初始化进度条
            this.initProgressBars();
            
            // 初始化统计卡片动画
            this.initStatCards();
        },
        
        // 初始化标签页
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const $this = $(this);
                const target = $this.data('target');
                
                // 移除所有活动状态
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                
                // 添加活动状态
                $this.addClass('nav-tab-active');
                $('#' + target).addClass('active');
                
                // 触发标签切换事件
                $(document).trigger('wordpress-toolkit-tab-changed', [target]);
            });
        },
        
        // 初始化颜色选择器
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker({
                    change: function(event, ui) {
                        // 触发颜色改变事件
                        $(document).trigger('wordpress-toolkit-color-changed', [ui.color.toString()]);
                    },
                    clear: function() {
                        // 触发颜色清除事件
                        $(document).trigger('wordpress-toolkit-color-cleared');
                    }
                });
            }
        },
        
        // 初始化工具提示
        initTooltips: function() {
            $('.wordpress-toolkit-tooltip').each(function() {
                const $this = $(this);
                const title = $this.attr('title');
                
                if (title) {
                    $this.attr('data-tooltip', title).removeAttr('title');
                    
                    $this.on('mouseenter', function() {
                        const tooltip = $('<div class="wordpress-toolkit-tooltip-text"></div>')
                            .text(title)
                            .appendTo('body');
                        
                        const offset = $this.offset();
                        const tooltipWidth = tooltip.outerWidth();
                        const tooltipHeight = tooltip.outerHeight();
                        const elementWidth = $this.outerWidth();
                        const elementHeight = $this.outerHeight();
                        
                        tooltip.css({
                            top: offset.top - tooltipHeight - 10,
                            left: offset.left + (elementWidth / 2) - (tooltipWidth / 2)
                        }).fadeIn(200);
                    });
                    
                    $this.on('mouseleave', function() {
                        $('.wordpress-toolkit-tooltip-text').fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },
        
        // 初始化进度条
        initProgressBars: function() {
            $('.progress-bar-fill').each(function() {
                const $this = $(this);
                const width = $this.data('width') || '0%';
                
                setTimeout(function() {
                    $this.css('width', width);
                }, 300);
            });
        },
        
        // 初始化统计卡片动画
        initStatCards: function() {
            $('.stat-value').each(function(index) {
                const $this = $(this);
                const finalValue = parseInt($this.text().replace(/[^0-9]/g, ''));
                const duration = 1000;
                const delay = index * 100;
                
                setTimeout(function() {
                    $this.prop('counter', 0).animate({
                        counter: finalValue
                    }, {
                        duration: duration,
                        easing: 'swing',
                        step: function(now) {
                            $this.text(Math.ceil(now));
                        }
                    });
                }, delay);
            });
        },
        
        // 处理表单提交
        handleFormSubmit: function(e) {
            const $form = $(e.target);
            const submitButton = $form.find('input[type="submit"], button[type="submit"]');
            
            // 禁用提交按钮
            submitButton.prop('disabled', true);
            
            // 显示加载状态
            submitButton.data('original-text', submitButton.val() || submitButton.text());
            submitButton.val('处理中...').text('处理中...');
            
            // 触发表单提交前事件
            $(document).trigger('wordpress-toolkit-form-submitting', [$form]);
            
            // 如果是AJAX表单，阻止默认提交
            if ($form.hasClass('ajax-form')) {
                e.preventDefault();
                this.submitFormAjax($form, submitButton);
            }
        },
        
        // AJAX表单提交
        submitFormAjax: function($form, submitButton) {
            const formData = $form.serialize();
            const action = $form.data('action') || 'wordpress_toolkit_ajax';
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=' + action,
                success: function(response) {
                    if (response.success) {
                        // 显示成功消息
                        WordPressToolkitAdmin.showNotice('success', response.data.message || '操作成功');
                        
                        // 触发成功事件
                        $(document).trigger('wordpress-toolkit-ajax-success', [response]);
                        
                        // 如果有重定向URL，则重定向
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        // 显示错误消息
                        WordPressToolkitAdmin.showNotice('error', response.data || '操作失败');
                        
                        // 触发失败事件
                        $(document).trigger('wordpress-toolkit-ajax-error', [response]);
                    }
                },
                error: function() {
                    WordPressToolkitAdmin.showNotice('error', '网络错误，请重试');
                },
                complete: function() {
                    // 恢复提交按钮
                    submitButton.prop('disabled', false);
                    submitButton.val(submitButton.data('original-text')).text(submitButton.data('original-text'));
                }
            });
        },
        
        // 处理按钮点击
        handleButtonClick: function(e) {
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            const confirmMessage = $button.data('confirm');
            
            // 如果有确认消息，显示确认对话框
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
                return;
            }
            
            // 根据动作类型处理
            switch (action) {
                case 'clear-cache':
                    this.clearCache($button);
                    break;
                case 'export-data':
                    this.exportData($button);
                    break;
                case 'reset-settings':
                    this.resetSettings($button);
                    break;
                case 'test-connection':
                    this.testConnection($button);
                    break;
                default:
                    // 触发自定义按钮事件
                    $(document).trigger('wordpress-toolkit-button-clicked', [$button, action]);
            }
        },
        
        // 清除缓存
        clearCache: function($button) {
            const cacheType = $button.data('cache-type') || 'all';
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wordpress_toolkit_clear_cache',
                    nonce: $button.data('nonce'),
                    cache_type: cacheType
                },
                success: function(response) {
                    if (response.success) {
                        WordPressToolkitAdmin.showNotice('success', response.data.message || '缓存已清除');
                    } else {
                        WordPressToolkitAdmin.showNotice('error', response.data || '清除缓存失败');
                    }
                },
                error: function() {
                    WordPressToolkitAdmin.showNotice('error', '网络错误，请重试');
                }
            });
        },
        
        // 导出数据
        exportData: function($button) {
            const format = $button.data('format') || 'csv';
            const category = $button.data('category') || 'all';
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wordpress_toolkit_export_data',
                    nonce: $button.data('nonce'),
                    format: format,
                    category: category
                },
                success: function(response) {
                    if (response.success) {
                        // 创建下载链接
                        const blob = new Blob([response.data.content], { type: response.data.mime_type });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        WordPressToolkitAdmin.showNotice('success', '数据导出成功');
                    } else {
                        WordPressToolkitAdmin.showNotice('error', response.data || '数据导出失败');
                    }
                },
                error: function() {
                    WordPressToolkitAdmin.showNotice('error', '网络错误，请重试');
                }
            });
        },
        
        // 重置设置
        resetSettings: function($button) {
            const module = $button.data('module') || 'all';
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wordpress_toolkit_reset_settings',
                    nonce: $button.data('nonce'),
                    module: module
                },
                success: function(response) {
                    if (response.success) {
                        WordPressToolkitAdmin.showNotice('success', response.data.message || '设置已重置');
                        
                        // 重新加载页面
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        WordPressToolkitAdmin.showNotice('error', response.data || '重置设置失败');
                    }
                },
                error: function() {
                    WordPressToolkitAdmin.showNotice('error', '网络错误，请重试');
                }
            });
        },
        
        // 测试连接
        testConnection: function($button) {
            const service = $button.data('service') || 'api';
            
            $button.prop('disabled', true);
            $button.text('测试中...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wordpress_toolkit_test_connection',
                    nonce: $button.data('nonce'),
                    service: service
                },
                success: function(response) {
                    if (response.success) {
                        WordPressToolkitAdmin.showNotice('success', response.data.message || '连接测试成功');
                    } else {
                        WordPressToolkitAdmin.showNotice('error', response.data || '连接测试失败');
                    }
                },
                error: function() {
                    WordPressToolkitAdmin.showNotice('error', '网络错误，请重试');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.text('测试连接');
                }
            });
        },
        
        // 处理AJAX错误
        handleAjaxError: function(event, jqXHR, settings, thrownError) {
            console.error('WordPress Toolkit AJAX Error:', thrownError);
            
            // 触发AJAX错误事件
            $(document).trigger('wordpress-toolkit-ajax-error', [event, jqXHR, settings, thrownError]);
        },
        
        // 显示通知
        showNotice: function(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // 插入到页面顶部
            $('.wrap h1').after(notice);
            
            // 自动隐藏
            setTimeout(function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // 绑定关闭按钮事件
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // 初始化
    $(document).ready(function() {
        WordPressToolkitAdmin.init();
    });
    
    // 暴露到全局作用域
    window.WordPressToolkitAdmin = WordPressToolkitAdmin;
    
})(jQuery);