/**
 * Tag Optimization Admin JavaScript
 * 标签优化管理界面脚本 - 与分类优化一致
 *
 * @package WordPressToolkit
 * @subpackage TagOptimization
 */

jQuery(document).ready(function($) {
    'use strict';

    // 美化弹框函数
    function showCustomAlert(message, title, type, callback) {
        type = type || 'info';
        title = title || (type === 'success' ? '✅ 操作成功' : type === 'error' ? '❌ 操作失败' : 'ℹ️ 确认操作');

        var alertClass = type === 'success' ? 'custom-success-alert' : type === 'error' ? 'custom-error-alert' : '';

        var overlay = $('<div class="custom-alert-overlay">' +
            '<div class="custom-alert ' + alertClass + '">' +
                '<div class="custom-alert-title">' + title + '</div>' +
                '<div class="custom-alert-message">' + message + '</div>' +
                '<div class="custom-alert-buttons">' +
                    (type === 'confirm' ?
                        '<button class="custom-alert-button custom-alert-cancel">取消</button>' : '') +
                    '<button class="custom-alert-button custom-alert-confirm">' + (type === 'confirm' ? '确定' : '好的') + '</button>' +
                '</div>' +
            '</div>' +
        '</div>');

        $('body').append(overlay);

        function closeAlert() {
            overlay.fadeOut(200, function() {
                overlay.remove();
            });
        }

        overlay.on('click', '.custom-alert-confirm', function() {
            closeAlert();
            if (callback) callback(true);
        });

        overlay.on('click', '.custom-alert-cancel', function() {
            closeAlert();
            if (callback) callback(false);
        });

        overlay.on('click', '.custom-alert-overlay', function(e) {
            if (e.target === this) {
                closeAlert();
                if (callback) callback(false);
            }
        });
    }

    // 替换原生alert和confirm
    window.customAlert = function(message, title, type) {
        showCustomAlert(message, title, type);
    };

    window.customConfirm = function(message, title, callback) {
        showCustomAlert(message, title, 'confirm', callback);
    };



    // AI分析描述
    $(document).on('click', '.generate-description-btn', function() {
        var tagId = $(this).data('id');
        optimizeTag(tagId, 'description');
    });


    // 批量优化
    $('#batch-optimize-tags').on('click', function() {
        bulkOptimizeAllTags();
    });

    // 单个标签优化
    function optimizeTag(tagId, optimizeType) {
        var $button, confirmMessage, successMessage, confirmTitle;

        if (optimizeType === 'description') {
            $button = $('.generate-description-btn[data-id="' + tagId + '"]');
            confirmMessage = '确定要AI生成这个标签的描述吗？';
            successMessage = 'AI描述生成成功';
            confirmTitle = '📝 AI生成描述确认';
        } else {
            $button = $('.optimize-tag-btn[data-id="' + tagId + '"]');
            confirmMessage = tagOptimizationData.texts.confirmOptimize;
            successMessage = tagOptimizationData.texts.optimizeSuccess;
            confirmTitle = 'ℹ️ 确认操作';
        }

        customConfirm(confirmMessage, confirmTitle, function(result) {
            if (!result) return;
            proceedWithOptimization();
        });

        function proceedWithOptimization() {

        var originalText = $button.text();
        $button.text(tagOptimizationData.texts.optimizing).prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'optimize_tag',
                tag_id: tagId,
                optimize_type: optimizeType || 'all',
                nonce: tagOptimizationData.nonces.optimizeTag
            },
            success: function(response) {
                if (response.success) {
                    customAlert(response.data.message || successMessage, '✅ 操作成功', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    customAlert(response.data.message || tagOptimizationData.texts.optimizeFailed, '❌ 操作失败', 'error');
                }
            },
            error: function() {
                customAlert(tagOptimizationData.texts.optimizeFailed, '❌ 网络错误', 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
        }
    }

    // 批量优化所有标签
    function bulkOptimizeAllTags() {
        customConfirm('确定要为所有标签生成AI描述吗？此操作可能需要一些时间。', '🤖 批量生成确认', function(result) {
            if (!result) return;
            proceedWithBulkOptimization();
        });

        function proceedWithBulkOptimization() {

        var $button = $('#batch-optimize-tags');
        var $spinner = $('#batch-spinner');

        $button.prop('disabled', true);
        $spinner.show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bulk_optimize_all_tags',
                optimize_type: 'description',
                nonce: tagOptimizationData.nonces.bulkOptimizeTags
            },
            success: function(response) {
                if (response.success) {
                    customAlert(response.data.message || '批量生成标签描述成功！', '✅ 批量操作成功', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    customAlert(response.data.message || '批量生成标签描述失败', '❌ 批量操作失败', 'error');
                }
            },
            error: function() {
                customAlert('批量生成标签描述失败', '❌ 网络错误', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.hide();
            }
        });
        }
    }

    // 加载统计数据
    function loadStatistics() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_tag_stats',
                nonce: tagOptimizationData.nonces.getStats
            },
            success: function(response) {
                if (response.success) {
                    $('#optimized-count').text(response.data.optimized_tags || 0);
                    $('#pending-count').text(response.data.pending_tags || 0);
                    $('#failed-count').text(response.data.failed_tags || 0);
                }
            }
        });
    }

    // 页面加载完成后加载统计数据
    loadStatistics();
});