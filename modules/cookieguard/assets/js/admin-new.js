/**
 * CookieGuard - 管理后台JavaScript (重构版本)
 * 使用统一的核心框架，功能保持不变
 */

(function($) {
    'use strict';

    // CookieGuard管理对象 - 基于ToolkitCore扩展
    var CookieGuardAdmin = $.extend({}, ToolkitCore, {

        // 初始化
        init: function() {
            this.bindCookieGuardEvents();
            this.initColorPickers();
            this.initGeoTesting();
        },

        // 绑定CookieGuard特有事件
        bindCookieGuardEvents: function() {
            var self = this;

            // 地理位置检测开关
            $(document).on('change', '#enable_geo_detection', function() {
                self.toggleGeoSettings($(this).is(':checked'));
            });

            // 预览按钮事件
            $(document).on('click', '#preview-notice', function(e) {
                e.preventDefault();
                self.previewCookieNotice();
            });

            // 清除缓存按钮事件
            $(document).on('click', '#clear-geo-cache', function(e) {
                e.preventDefault();
                self.clearGeoCache();
            });

            // 重置Cookie按钮事件
            $(document).on('click', '#reset-cookie-consent', function(e) {
                e.preventDefault();
                self.resetCookieConsent();
            });

            // 测试地理位置检测
            $(document).on('click', '#test-geo-detection', function(e) {
                e.preventDefault();
                self.testGeoDetection();
            });
        },

        // 初始化颜色选择器
        initColorPickers: function() {
            // WordPress颜色选择器初始化
            if ($('.color-picker').length && typeof wp !== 'undefined' && wp.ColorPicker) {
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
            }
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

        // 切换地理位置设置
        toggleGeoSettings: function(enabled) {
            var $geoSettings = $('#geo-settings');
            var $localIpSetting = $('#local-ip-setting');

            if (enabled) {
                $geoSettings && $geoSettings.slideDown();
                $localIpSetting && $localIpSetting.slideDown();
            } else {
                $geoSettings && $geoSettings.slideUp();
                $localIpSetting && $localIpSetting.slideUp();
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

            this.ajax({
                data: {
                    action: 'wordpress_toolkit_clear_geo_cache'
                }
            }).done(function(response) {
                if (response.success) {
                    self.showNotice('success', '地理位置缓存已清除');
                    // 重新测试检测
                    setTimeout(function() {
                        self.testGeoDetection();
                    }, 500);
                } else {
                    self.showNotice('error', '清除缓存失败：' + (response.data || '未知错误'));
                }
            }).fail(function() {
                self.showNotice('error', '清除缓存失败，请稍后重试');
            });
        },

        // 重置Cookie同意
        resetCookieConsent: function() {
            if (!confirm('确定要重置所有访问者的Cookie同意状态吗？这将使通知重新显示给所有用户。')) {
                return;
            }

            // 删除当前用户的Cookie
            document.cookie = 'wordpress_toolkit_cookieguard_consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';

            this.showNotice('success', 'Cookie同意状态已重置');
        },

        // 测试地理位置检测
        testGeoDetection: function() {
            var self = this;
            var $testButton = $('#test-geo-detection');
            var $geoInfo = $('#geo-info');

            if ($testButton.length) {
                $testButton.prop('disabled', true).text('检测中...');
            }
            if ($geoInfo) {
                $geoInfo.addClass('loading');
            }

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
                    if ($showNotice.length) {
                        $showNotice.text(showNotice ? '是（显示Cookie通知）' : '否（不显示通知）');
                        $showNotice.css('color', showNotice ? '#ff9500' : '#34c759');
                    }
                })
                .fail(function() {
                    $('#current-country').text('检测失败');
                    var $showNotice = $('#show-notice');
                    if ($showNotice.length) {
                        $showNotice.text('是（安全默认）');
                    }
                })
                .always(function() {
                    if ($testButton.length) {
                        $testButton.prop('disabled', false).text('测试检测');
                    }
                    if ($geoInfo) {
                        $geoInfo.removeClass('loading');
                    }
                });
        },

        // 更新预览
        updatePreview: function() {
            // 这里可以添加实时预览功能
            // console.log('Preview updated');
        }
    });

    // 文档加载完成后初始化
    $(document).ready(function() {
        CookieGuardAdmin.init();
    });

})(jQuery);