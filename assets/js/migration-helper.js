/**
 * WordPress Toolkit - 迁移助手
 * 帮助从旧版本平滑过渡到新的共享库
 */

(function($) {
    'use strict';

    var MigrationHelper = {

        init: function() {
            this.checkCompatibility();
            this.setupPolyfills();
            this.bindMigrationEvents();
        },

        // 检查兼容性
        checkCompatibility: function() {
            // 确保ToolkitCore已加载
            if (typeof ToolkitCore === 'undefined') {
                console.warn('ToolkitCore not loaded. Some features may not work correctly.');
                return false;
            }

            // 检查jQuery版本
            if (!$ || typeof $ === 'undefined') {
                console.error('jQuery is required but not loaded.');
                return false;
            }

            // console.log('WordPress Toolkit Core initialized successfully');
            return true;
        },

        // 设置Polyfills
        setupPolyfills: function() {
            // 为旧浏览器添加缺失的功能
            if (!String.prototype.startsWith) {
                String.prototype.startsWith = function(searchString, position) {
                    position = position || 0;
                    return this.indexOf(searchString, position) === position;
                };
            }

            if (!String.prototype.includes) {
                String.prototype.includes = function(search, start) {
                    if (typeof start !== 'number') {
                        start = 0;
                    }
                    if (start + search.length > this.length) {
                        return false;
                    } else {
                        return this.indexOf(search, start) !== -1;
                    }
                };
            }
        },

        // 绑定迁移事件
        bindMigrationEvents: function() {
            // 自动迁移旧的CSS类名到新的类名
            this.migrateCSSClasses();

            // 迁移旧的事件绑定
            this.migrateEventBindings();

            // 迁移旧的AJAX调用
            this.migrateAJAXCalls();
        },

        // 迁移CSS类名
        migrateCSSClasses: function() {
            var migrations = {
                'wordpress-toolkit-admin-section': 'tc-card',
                'gdpr-admin-wrap': 'tc-admin-container',
                'gdpr-admin-header': 'tc-flex tc-flex-between tc-flex-center',
                'gdpr-form-section': 'tc-mb-2xl',
                'gdpr-form-row': 'tc-form-group',
                'gdpr-form-label': 'tc-form-label',
                'gdpr-form-input': 'tc-form-control',
                'gdpr-form-textarea': 'tc-form-control',
                'gdpr-form-select': 'tc-form-control',
                'gdpr-submit-btn': 'tc-btn tc-btn-primary',
                'gdpr-preview-btn': 'tc-btn tc-btn-success',
                'notice-success': 'tc-notice tc-notice-success',
                'notice-error': 'tc-notice tc-notice-error',
                'notice-warning': 'tc-notice tc-notice-warning',
                'notice-info': 'tc-notice tc-notice-info'
            };

            var self = this;
            $.each(migrations, function(oldClass, newClass) {
                $('.' + oldClass).removeClass(oldClass).addClass(newClass);
            });
        },

        // 迁移事件绑定
        migrateEventBindings: function() {
            // 如果发现旧的事件绑定，使用新的统一方式
            if (typeof CookieGuardAdmin !== 'undefined' && CookieGuardAdmin.init) {
                // console.log('Migrating CookieGuardAdmin to new framework...');
                // CookieGuardAdmin已经重新定义为基础ToolkitCore的扩展
            }

            if (typeof TimeCapsuleAdmin !== 'undefined' && TimeCapsuleAdmin.init) {
                // console.log('Migrating TimeCapsuleAdmin to new framework...');
                // TimeCapsuleAdmin已经重新定义为基础ToolkitCore的扩展
            }
        },

        // 迁移AJAX调用
        migrateAJAXCalls: function() {
            // 拦截$.ajax调用，确保使用正确的URL和nonce
            var originalAjax = $.ajax;
            $.ajax = function(options) {
                if (options.data && options.data.action &&
                    (options.data.action.indexOf('wordpress_toolkit') === 0 ||
                     options.data.action.indexOf('time_capsule') === 0 ||
                     options.data.action.indexOf('custom_card') === 0 ||
                     options.data.action.indexOf('age_calculator') === 0)) {

                    // 确保使用正确的AJAX URL
                    if (!options.url) {
                        options.url = ToolkitCore.config.ajaxUrl;
                    }

                    // 确保包含nonce
                    if (options.data && !options.data.nonce && ToolkitCore.config.nonce) {
                        options.data.nonce = ToolkitCore.config.nonce;
                    }
                }
                return originalAjax.call(this, options);
            };
        },

        // 显示迁移完成通知
        showMigrationComplete: function() {
            if (typeof ToolkitCore !== 'undefined' && ToolkitCore.showNotice) {
                ToolkitCore.showNotice('info', 'WordPress Toolkit框架已更新到最新版本', {
                    autoHide: true,
                    duration: 3000
                });
            }
        }
    };

    // 文档加载完成后初始化
    $(document).ready(function() {
        MigrationHelper.init();

        // 延迟显示迁移完成通知 - 已禁用
        // setTimeout(function() {
        //     MigrationHelper.showMigrationComplete();
        // }, 1000);
    });

    // 暴露到全局作用域
    window.MigrationHelper = MigrationHelper;

})(jQuery);