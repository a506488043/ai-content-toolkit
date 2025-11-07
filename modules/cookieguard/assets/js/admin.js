/**
 * CookieGuard - 管理后台JavaScript
 * 处理管理页面的交互功能
 */

(function($) {
    'use strict';

    // CookieGuard管理对象
    var CookieGuardAdmin = {
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.initColorPickers();
            this.initGeoTesting();
            this.initFormValidation();
        },
        
        // 绑定事件
        bindEvents: function() {
            var self = this;
            
            // 表单提交事件
            $('#cookieguard-settings-form').on('submit', function(e) {
                self.handleFormSubmit(e);
            });
            
            // 地理位置检测开关
            $('#enable_geo_detection').on('change', function() {
                self.toggleGeoSettings($(this).is(':checked'));
            });
            
            // 预览按钮事件
            $('#preview-notice').on('click', function(e) {
                e.preventDefault();
                self.previewCookieNotice();
            });
            
            // 清除缓存按钮事件
            $('#clear-geo-cache').on('click', function(e) {
                e.preventDefault();
                self.clearGeoCache();
            });
            
            // 重置Cookie按钮事件
            $('#reset-cookie-consent').on('click', function(e) {
                e.preventDefault();
                self.resetCookieConsent();
            });
            
            // 测试地理位置检测
            $('#test-geo-detection').on('click', function(e) {
                e.preventDefault();
                self.testGeoDetection();
            });
        },
        
        // 初始化颜色选择器
        initColorPickers: function() {
            // WordPress颜色选择器初始化
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    var color = ui.color.toString();
                    $(this).val(color);
                    CookieGuardAdmin.updatePreview();
                },
                clear: function() {
                    $(this).val('');
                    CookieGuardAdmin.updatePreview();
                }
            });
        },
        
        // 初始化地理位置测试
        initGeoTesting: function() {
            var self = this;
            
            // 页面加载时自动测试
            if ($('#enable_geo_detection').is(':checked')) {
                setTimeout(function() {
                    self.testGeoDetection();
                }, 1000);
            }
        },
        
        // 初始化表单验证
        initFormValidation: function() {
            var self = this;
            
            // Cookie有效期验证
            $('#cookie_expiry').on('input', function() {
                var value = parseInt($(this).val());
                if (value < 1 || value > 3650) {
                    $(this).addClass('error');
                    $('#cookie-expiry-error').show();
                } else {
                    $(this).removeClass('error');
                    $('#cookie-expiry-error').hide();
                }
            });
            
            // URL验证
            $('#learn_more_url').on('input', function() {
                var url = $(this).val();
                if (url && !self.isValidUrl(url)) {
                    $(this).addClass('error');
                    $('#url-error').show();
                } else {
                    $(this).removeClass('error');
                    $('#url-error').hide();
                }
            });
        },
        
        // 处理表单提交
        handleFormSubmit: function(e) {
            var self = this;
            var $form = $(e.target);
            
            // 验证表单
            if (!self.validateForm($form)) {
                e.preventDefault();
                return false;
            }
            
            // 显示加载状态
            self.setLoadingState(true);
            
            // 表单验证通过，允许正常提交
            return true;
        },
        
        // 验证表单
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // 验证通知文本
            var noticeText = $('#notice_text').val().trim();
            if (!noticeText) {
                errors.push('通知文本不能为空');
                $('#notice_text').addClass('error');
            } else {
                $('#notice_text').removeClass('error');
            }
            
            // 验证按钮文本
            var acceptText = $('#accept_button_text').val().trim();
            if (!acceptText) {
                errors.push('接受按钮文本不能为空');
                $('#accept_button_text').addClass('error');
            } else {
                $('#accept_button_text').removeClass('error');
            }
            
            // 验证Cookie有效期
            var expiry = parseInt($('#cookie_expiry').val());
            if (isNaN(expiry) || expiry < 1 || expiry > 3650) {
                errors.push('Cookie有效期必须是1-3650之间的数字');
                $('#cookie_expiry').addClass('error');
            } else {
                $('#cookie_expiry').removeClass('error');
            }
            
            // 验证URL
            var learnMoreUrl = $('#learn_more_url').val().trim();
            if (learnMoreUrl && !this.isValidUrl(learnMoreUrl)) {
                errors.push('了解更多链接必须是有效的URL');
                $('#learn_more_url').addClass('error');
            } else {
                $('#learn_more_url').removeClass('error');
            }
            
            // 显示错误信息
            if (errors.length > 0) {
                this.showErrors(errors);
                isValid = false;
            } else {
                this.hideErrors();
            }
            
            return isValid;
        },
        
        // 验证URL
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },
        
        // 显示错误信息
        showErrors: function(errors) {
            var $errorContainer = $('#form-errors');
            $errorContainer.empty();
            
            errors.forEach(function(error) {
                $errorContainer.append('<div class="notice notice-error"><p>' + error + '</p></div>');
            });
            
            $errorContainer.show();
        },
        
        // 隐藏错误信息
        hideErrors: function() {
            $('#form-errors').empty().hide();
        },
        
        // 切换地理位置设置
        toggleGeoSettings: function(enabled) {
            var $geoSettings = $('#geo-settings');
            var $localIpSetting = $('#local-ip-setting');
            
            if (enabled) {
                $geoSettings.slideDown();
                $localIpSetting.slideDown();
            } else {
                $geoSettings.slideUp();
                $localIpSetting.slideUp();
            }
        },
        
        // 预览Cookie通知
        previewCookieNotice: function() {
            // 在新窗口中打开首页并强制显示通知
            var previewUrl = window.location.origin + '/?force_show_notice=1';
            window.open(previewUrl, '_blank');
        },
        
        // 清除地理位置缓存
        clearGeoCache: function() {
            var self = this;
            
            if (!confirm('确定要清除所有地理位置缓存吗？这将重新检测所有用户的地理位置。')) {
                return;
            }
            
            self.setLoadingState(true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wordpress_toolkit_clear_geo_cache',
                    nonce: wordpress_toolkit_cookieguard_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('地理位置缓存已清除');
                        // 重新测试检测
                        setTimeout(function() {
                            self.testGeoDetection();
                        }, 500);
                    } else {
                        self.showError('清除缓存失败：' + (response.data || '未知错误'));
                    }
                },
                error: function() {
                    self.showError('清除缓存失败，请稍后重试');
                },
                complete: function() {
                    self.setLoadingState(false);
                }
            });
        },
        
        // 重置Cookie同意
        resetCookieConsent: function() {
            if (!confirm('确定要重置所有访问者的Cookie同意状态吗？这将使通知重新显示给所有用户。')) {
                return;
            }
            
            // 删除当前用户的Cookie
            document.cookie = 'wordpress_toolkit_cookieguard_consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            
            this.showSuccess('Cookie同意状态已重置');
        },
        
        // 测试地理位置检测
        testGeoDetection: function() {
            var self = this;
            var $testButton = $('#test-geo-detection');
            var $geoInfo = $('#geo-info');
            
            $testButton.prop('disabled', true).text('检测中...');
            $geoInfo.addClass('loading');
            
            // 获取当前IP和地理位置信息
            $.get('https://api.ipify.org?format=json')
                .done(function(ipData) {
                    $('#current-ip').text(ipData.ip);
                    
                    return $.get('https://ipapi.co/' + ipData.ip + '/json/');
                })
                .done(function(geoData) {
                    var country = geoData.country_name || '未知';
                    var countryCode = geoData.country_code || 'UNKNOWN';
                    
                    // 台湾是中国的一个省份，不是国家
                    if (country === 'Taiwan' || countryCode === 'TW') {
                        country = '中国台湾省';
                    }
                    
                    $('#current-country').text(country + ' (' + countryCode + ')');
                    
                    // 判断是否显示通知
                    var enableGeo = $('#enable_geo_detection').is(':checked');
                    var localAsChina = $('#local_ip_as_china').is(':checked');
                    
                    var showNotice = true;
                    if (enableGeo) {
                        if (countryCode === 'CN') {
                            showNotice = false;
                        } else if (self.isLocalIP(ipData.ip) && localAsChina) {
                            showNotice = false;
                        }
                    }
                    
                    var $showNotice = $('#show-notice');
                    $showNotice.text(showNotice ? '是（显示Cookie通知）' : '否（不显示通知）');
                    $showNotice.css('color', showNotice ? '#ff9500' : '#34c759');
                })
                .fail(function() {
                    $('#current-country').text('检测失败');
                    $('#show-notice').text('是（安全默认）');
                })
                .always(function() {
                    $testButton.prop('disabled', false).text('测试检测');
                    $geoInfo.removeClass('loading');
                });
        },
        
        // 检查是否为本地IP
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
        
        // 更新预览
        updatePreview: function() {
            // 这里可以添加实时预览功能
        },
        
        // 设置加载状态
        setLoadingState: function(loading) {
            var $form = $('#cookieguard-settings-form');
            
            if (loading) {
                $form.addClass('loading');
                $('input[type="submit"]', $form).prop('disabled', true).val('保存中...');
            } else {
                $form.removeClass('loading');
                $('input[type="submit"]', $form).prop('disabled', false).val('保存设置');
            }
        },
        
        // 显示成功消息
        showSuccess: function(message) {
            var $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            this.showNotice($notice);
        },
        
        // 显示错误消息
        showError: function(message) {
            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            this.showNotice($notice);
        },
        
        // 显示通知
        showNotice: function($notice) {
            var $container = $('#notices');
            $container.empty().append($notice);
            
            // 自动关闭
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
            
            // 点击关闭
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            });
        }
    };
    
    // 文档加载完成后初始化
    $(document).ready(function() {
        CookieGuardAdmin.init();
    });
    
})(jQuery);