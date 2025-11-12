/**
 * CookieGuard Pro - 前端交互逻辑 - 修复版本
 * 苹果风格交互设计
 * 修复了第189行逻辑错误
 */

(function($) {
    'use strict';
    
    // 插件对象
    const CookieGuardPro = {
        
        // 配置选项
        config: {
            cookieName: 'wordpress_toolkit_cookieguard_consent',
            animationDuration: 600,
            hideDelay: 300,
            autoFocusDelay: 800
        },
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.checkCookieStatus();
            this.setupAccessibility();
        },
        
        // 绑定事件
        bindEvents: function() {
            const self = this;
            
            // 接受按钮点击事件
            $(document).on('click', '.cookieguard-accept-btn', function(e) {
                e.preventDefault();
                self.handleConsent('accepted', $(this));
            });
            
            // 拒绝按钮点击事件
            $(document).on('click', '.cookieguard-decline-btn', function(e) {
                e.preventDefault();
                self.handleConsent('declined', $(this));
            });
            
            // 键盘事件处理
            $(document).on('keydown', '#cookieguard-notice', function(e) {
                self.handleKeyboardNavigation(e);
            });
            
            // ESC键关闭（仅在拒绝按钮存在时）
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.cookieguard-decline-btn').length > 0) {
                    self.handleConsent('declined');
                }
            });
            
            // 触摸事件优化
            if ('ontouchstart' in window) {
                $(document).on('touchstart', '.cookieguard-btn', function() {
                    $(this).addClass('touch-active');
                });
                
                $(document).on('touchend', '.cookieguard-btn', function() {
                    const $btn = $(this);
                    setTimeout(() => $btn.removeClass('touch-active'), 150);
                });
            }
        },
        
        // 设置无障碍访问
        setupAccessibility: function() {
            const $notice = $('#cookieguard-notice');
            if ($notice.length > 0) {
                // 设置ARIA属性
                $notice.attr({
                    'role': 'dialog',
                    'aria-labelledby': 'cookieguard-title',
                    'aria-describedby': 'cookieguard-description',
                    'aria-modal': 'true'
                });
                
                // 为标题和描述添加ID
                $notice.find('.cookieguard-notice-title').attr('id', 'cookieguard-title');
                $notice.find('.cookieguard-notice-text').attr('id', 'cookieguard-description');
            }
        },
        
        // 键盘导航处理
        handleKeyboardNavigation: function(e) {
            const $notice = $('#cookieguard-notice');
            const $buttons = $notice.find('.cookieguard-btn');
            const $links = $notice.find('.cookieguard-learn-more');
            const $focusable = $buttons.add($links);
            
            if (e.key === 'Tab') {
                const currentIndex = $focusable.index(document.activeElement);
                
                if (e.shiftKey) {
                    // Shift+Tab - 向前导航
                    if (currentIndex <= 0) {
                        e.preventDefault();
                        $focusable.last().focus();
                    }
                } else {
                    // Tab - 向后导航
                    if (currentIndex >= $focusable.length - 1) {
                        e.preventDefault();
                        $focusable.first().focus();
                    }
                }
            }
        },
        
        // 检查Cookie状态
        checkCookieStatus: function() {
            const consent = this.getCookie(this.config.cookieName);
            if (consent) {
                this.hideNotice(false);
            } else {
                this.showNotice();
            }
        },
        
        // 显示通知
        showNotice: function() {
            const $notice = $('#cookieguard-notice');
            if ($notice.length === 0) return;

            // 移除隐藏类
            $notice.removeClass('cookieguard-notice-hiding');
            
            // 确保通知可见
            $notice.css('display', 'block');
            
            // 触发重排以确保动画正常
            $notice[0].offsetHeight;
            
            // 设置焦点到第一个按钮（延迟以等待动画完成）
            setTimeout(() => {
                const $firstBtn = $notice.find('.cookieguard-btn').first();
                if ($firstBtn.length > 0) {
                    $firstBtn.focus();
                }
            }, this.config.autoFocusDelay);
            
            // 触发显示事件
            $(document).trigger('cookieguard_notice_shown');
        },
        
        // 隐藏通知 - 修复版本
        hideNotice: function(animated = true) {
            const $notice = $('#cookieguard-notice');
            if ($notice.length === 0) return;
            
            if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
            }
            
            if (animated) {
                // 确保移除任何可能冲突的类
                $notice.removeClass('cookieguard-notice-loading');
                
                // 添加隐藏动画类
                $notice.addClass('cookieguard-notice-hiding');
                
                // 动画完成后隐藏元素
                setTimeout(() => {
                    $notice.css('display', 'none');
                    $notice.removeClass('cookieguard-notice-hiding');
                    $(document).trigger('cookieguard_notice_hidden');
                }, this.config.hideDelay);
            } else {
                // 直接隐藏，移除所有相关类
                $notice.removeClass('cookieguard-notice-hiding cookieguard-notice-loading');
                $notice.css('display', 'none');
                $(document).trigger('cookieguard_notice_hidden');
            }
        },
        
        // 处理用户同意 - 修复版本
        handleConsent: function(consent, $button) {
            const self = this;
            const $notice = $('#cookieguard-notice');
            
            if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
            }
            
            // 添加加载状态（在隐藏弹框之前）
            $notice.addClass('cookieguard-notice-loading');
            
            // 按钮状态处理
            if ($button) {
                const originalText = $button.text();
                $button.data('original-text', originalText);
                
                // 修复第189行逻辑错误：根据同意类型设置不同的加载文本
                const loadingText = consent === 'accepted' ? '正在接受...' : '正在拒绝...';
                $button.text(loadingText);
                $button.prop('disabled', true);
            }
            
            // 发送AJAX请求，成功后设置Cookie
            $.ajax({
                url: wordpress_toolkit_cookie_consent_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wordpress_toolkit_cookie_consent',
                    consent: consent,
                    nonce: wordpress_toolkit_cookie_consent_ajax.nonce
                },
                timeout: 10000, // 10秒超时
                success: function(response) {
                    if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
                    }
                    
                    if (response.success) {
                        // 服务器确认成功后才设置本地Cookie
                        self.setCookie(self.config.cookieName, consent, 365);
                        if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
                        }
                        
                        // 显示成功反馈（在隐藏弹框之前）
                        if ($button && $button.is(':visible')) {
                            self.showSuccessFeedback(consent, $button);
                        }
                        
                        // 延迟隐藏弹框，让用户看到成功反馈
                        setTimeout(() => {
                            self.hideNotice(true);
                            
                            // 触发自定义事件
                            $(document).trigger('cookieguard_consent_given', {
                                consent: consent,
                                timestamp: new Date().toISOString(),
                                userAgent: navigator.userAgent
                            });
                            
                            // 如果用户接受了Cookie，加载相关脚本
                            if (consent === 'accepted') {
                                self.loadAcceptedScripts();
                            }
                        }, 500);
                        
                    } else {
                        console.error('CookieGuard Debug - AJAX error:', response);
                        // AJAX失败，恢复按钮状态但不设置Cookie
                        if ($button) {
                            const originalText = $button.data('original-text');
                            if (originalText) {
                                $button.text(originalText);
                            }
                            $button.prop('disabled', false);
                        }
                        $notice.removeClass('cookieguard-notice-loading');
                        
                        // 显示错误信息
                        alert('保存Cookie设置失败，请刷新页面重试');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CookieGuard Pro AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    // 如果AJAX失败，但Cookie已设置，不需要显示错误
                    if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
                    }
                },
                complete: function() {
                    // 移除加载状态
                    $notice.removeClass('cookieguard-notice-loading');
                    
                    // 恢复按钮状态
                    if ($button) {
                        const originalText = $button.data('original-text');
                        if (originalText) {
                            $button.text(originalText);
                        }
                        $button.prop('disabled', false);
                    }
                }
            });
        },
        
        // 显示成功反馈
        showSuccessFeedback: function(consent, $button) {
            if (!$button) return;
            
            // 根据同意类型显示不同的成功反馈
            const successText = consent === 'accepted' ? '✓ 已接受' : '✓ 已拒绝';
            const originalText = $button.data('original-text');
            
            $button.text(successText);
            
            // 2秒后恢复原始文本
            setTimeout(() => {
                if (originalText) {
                    $button.text(originalText);
                }
            }, 2000);
        },
        
        // 加载接受的脚本
        loadAcceptedScripts: function() {
            // 这里可以加载用户接受Cookie后需要执行的脚本
            if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
            }
            
            // 触发自定义事件
            $(document).trigger('cookieguard_scripts_loaded');
        },
        
        // 设置Cookie
        setCookie: function(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = '; expires=' + date.toUTCString();
            document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
            if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
            }
        },
        
        // 获取Cookie
        getCookie: function(name) {
            const nameEQ = name + '=';
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }
                if (c.indexOf(nameEQ) === 0) {
                    const value = decodeURIComponent(c.substring(nameEQ.length, c.length));
                    if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
                    }
                    return value;
                }
            }
            if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
            }
            return null;
        },
        
        // 删除Cookie
        deleteCookie: function(name) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            if (typeof wordpress_toolkit_cookie_consent_ajax !== 'undefined' && wordpress_toolkit_cookie_consent_ajax.debug_mode) {
            }
        }
    };
    
    // 文档加载完成后初始化
    $(document).ready(function() {
        CookieGuardPro.init();
    });
    
})(jQuery);