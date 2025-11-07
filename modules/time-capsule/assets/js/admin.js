/**
 * 物品管理后台管理JavaScript
 */

(function($) {
    'use strict';

    // 确保TimeCapsuleAdmin对象存在
    if (typeof TimeCapsuleAdmin === 'undefined') {
        console.error('TimeCapsuleAdmin object is undefined! Script may not be properly loaded.');
        window.TimeCapsuleAdmin = {
            ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: '',
            strings: {
                saveSuccess: '保存成功！',
                saveError: '保存失败，请重试。',
                networkError: '网络错误，请重试。',
                confirmDelete: '确定要删除这个物品吗？此操作不可撤销。',
                deleteSuccess: '删除成功！',
                deleteError: '删除失败，请重试。'
            }
        };
    }

    // 扩展TimeCapsuleAdmin对象
    $.extend(TimeCapsuleAdmin, {

        init: function() {
            this.bindEvents();
            this.initModal();
        },
        
        bindEvents: function() {

            // 表单提交
            $(document).on('submit', '#tc-item-form', this.handleFormSubmit);

            // 编辑物品
            $(document).on('click', '.tc-edit-item', this.handleEditItem);

            // 查看物品
            $(document).on('click', '.tc-view-item', this.handleViewItem);

            // 删除物品
            $(document).on('click', '.tc-delete-item', this.handleDeleteItem);

            // 导出数据
            $(document).on('click', '#tc-export-btn', this.handleExport);

            // 模态框关闭
            $(document).on('click', '.tc-modal-close, #tc-modal-cancel', this.closeModal);

            // 模态框保存
            $(document).on('click', '#tc-modal-save', this.handleModalSave);

            // 点击模态框外部关闭
            $(document).on('click', '.tc-modal', function(e) {
                if (e.target === this) {
                    TimeCapsuleAdmin.closeModal();
                }
            });

            // 类别选择变化
            $(document).on('change', '#tc_category', this.handleCategoryChange);


        },
        
        handleFormSubmit: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();

            // 禁用提交按钮
            $submitBtn.prop('disabled', true).text('保存中...');

            // 收集表单数据
            var formData = {
                action: 'time_capsule_save_item',
                nonce: TimeCapsuleAdmin.nonce
            };

            $form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                if (name && name !== 'tc_nonce') {
                    formData[name] = value;
                    // 调试：记录关键字段（仅在开发时启用）
                    if (name.includes('fee') || name.includes('price')) {
                        // console.log('Field ' + name + ':', value, 'type:', typeof value);
                    }
                }
            });

            // 详细调试信息

            // 发送AJAX请求
            $.post(TimeCapsuleAdmin.ajaxUrl, formData)
                .done(function(response) {

                    if (response.success) {
                        var successMessage = TimeCapsuleAdmin.strings.saveSuccess || '保存成功！';
                        TimeCapsuleAdmin.showNotice('success', response.data.message || successMessage);

                        // 如果是新增，重定向到编辑页面
                        if (!formData.id && response.data && response.data.id) {
                            setTimeout(function() {
                                window.location.href = window.location.href.split('?')[0] +
                                    '?page=wordpress-toolkit-time-capsule&tab=add&edit=' + response.data.id;
                            }, 1000);
                        }
                    } else {
                        var errorMessage = TimeCapsuleAdmin.strings.saveError || '保存失败，请重试。';
                        TimeCapsuleAdmin.showNotice('error', response.data.message || errorMessage);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('=== AJAX REQUEST FAILED ===');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('XHR Status:', xhr.status);
                    console.error('XHR Status Text:', xhr.statusText);
                    console.error('XHR Response Text:', xhr.responseText);
                    console.error('==========================');

                    var networkError = TimeCapsuleAdmin.strings.networkError || '网络错误，请重试。';
                    TimeCapsuleAdmin.showNotice('error', networkError);
                })
                .always(function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                });
        },
        
        handleEditItem: function(e) {
            e.preventDefault();
            var itemId = $(this).data('item-id');
            var baseUrl = window.location.origin + window.location.pathname;
            window.location.href = baseUrl + '?page=wordpress-toolkit-time-capsule&tab=add&edit=' + itemId;
        },
        
        handleViewItem: function(e) {
            e.preventDefault();
            var itemId = $(this).data('item-id');
            TimeCapsuleAdmin.loadItemDetails(itemId);
        },
        
        handleDeleteItem: function(e) {
            e.preventDefault();
            
            var confirmMessage = TimeCapsuleAdmin.strings.confirmDelete || '确定要删除这个物品吗？此操作不可撤销。';
            if (!confirm(confirmMessage)) {
                return;
            }
            
            var $btn = $(this);
            var itemId = $btn.data('item-id');
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text('删除中...');
            
            $.post(TimeCapsuleAdmin.ajaxUrl, {
                action: 'time_capsule_delete_item',
                nonce: TimeCapsuleAdmin.nonce,
                item_id: itemId
            })
            .done(function(response) {
                if (response.success) {
                    var successMessage = TimeCapsuleAdmin.strings.deleteSuccess || '删除成功！';
                    TimeCapsuleAdmin.showNotice('success', response.data.message || successMessage);
                    
                    // 移除表格行或重新加载页面
                    var $row = $btn.closest('tr');
                    if ($row.length) {
                        $row.fadeOut(function() {
                            $row.remove();
                        });
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    var errorMessage = TimeCapsuleAdmin.strings.deleteError || '删除失败，请重试。';
                    TimeCapsuleAdmin.showNotice('error', response.data.message || errorMessage);
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function() {
                var networkError = TimeCapsuleAdmin.strings.networkError || '网络错误，请重试。';
                TimeCapsuleAdmin.showNotice('error', networkError);
                $btn.prop('disabled', false).text(originalText);
            });
        },
        
        handleExport: function(e) {
            e.preventDefault();
            
            var format = prompt('请选择导出格式：\n1. CSV\n2. JSON\n\n请输入 1 或 2：');
            
            if (format === '1' || format === '2') {
                var exportFormat = format === '1' ? 'csv' : 'json';
                var url = ajaxurl + '?action=time_capsule_export_data&format=' + exportFormat +
                         '&nonce=' + ($('#tc_nonce').val() || $('input[name="tc_nonce"]').val());
                
                // 创建隐藏的下载链接
                var $link = $('<a>').attr({
                    href: url,
                    download: 'time-capsule-export.' + exportFormat
                }).appendTo('body');
                
                $link[0].click();
                $link.remove();
            }
        },
        
        loadItemDetails: function(itemId) {
            this.openModal('物品详情', '加载中...');
            
            $.post(ajaxurl, {
                action: 'time_capsule_get_item_details',
                nonce: $('#tc_nonce').val() || $('input[name="tc_nonce"]').val(),
                item_id: itemId
            })
            .done(function(response) {
                if (response.success && response.data) {
                    TimeCapsuleAdmin.renderItemDetails(response.data);
                } else {
                    $('#tc-modal-body').html('<p>加载失败，请重试。</p>');
                }
            })
            .fail(function() {
                $('#tc-modal-body').html('<p>网络错误，请重试。</p>');
            });
        },
        
        renderItemDetails: function(item) {
            var html = '<div class="tc-item-details">';

            // 头部信息
            html += '<div class="tc-detail-header">';
            html += '<div class="tc-detail-avatar">' + this.getItemAvatar(item.category) + '</div>';
            html += '<div class="tc-detail-info">';
            html += '<h4>' + this.escapeHtml(item.name) + '</h4>';
            if (item.brand) {
                html += '<div class="tc-detail-brand">' + this.escapeHtml(item.brand) + '</div>';
            }
            html += '<div class="tc-detail-category">' + this.escapeHtml(item.category_display_name) + '</div>';
            html += '</div>';
            html += '</div>';

            // 主要内容区域
            html += '<div class="tc-detail-content">';

            // 基本信息卡片
            html += '<div class="tc-detail-card">';
            html += '<h5 class="tc-detail-card-title">基本信息</h5>';
            html += '<div class="tc-detail-grid">';
            html += '<div class="tc-detail-item"><label>购买日期</label><span>' + item.purchase_date + '</span></div>';
            if (item.purchase_source) {
                html += '<div class="tc-detail-item"><label>购买来源</label><span>' + this.escapeHtml(item.purchase_source) + '</span></div>';
            }
            if (item.price) {
                html += '<div class="tc-detail-item"><label>价格</label><span>¥' + parseFloat(item.price).toFixed(2) + '</span></div>';
            }
            html += '<div class="tc-detail-item"><label>状态</label><span class="tc-status-badge ' + this.getStatusClass(item.status) + '">' + this.getStatusText(item.status) + '</span></div>';
            html += '</div>';
            html += '</div>';

            // 描述信息
            if (item.description) {
                html += '<div class="tc-detail-card">';
                html += '<h5 class="tc-detail-card-title">物品描述</h5>';
                html += '<p class="tc-detail-description">' + this.escapeHtml(item.description) + '</p>';
                html += '</div>';
            }

            // 保修信息
            if (item.warranty_period > 0) {
                html += '<div class="tc-detail-card">';
                html += '<h5 class="tc-detail-card-title">保修信息</h5>';
                html += '<div class="tc-detail-grid">';
                html += '<div class="tc-detail-item"><label>保修期</label><span>' + item.warranty_period + ' 个月</span></div>';
                html += '<div class="tc-detail-item"><label>保修状态</label><span class="tc-warranty-status ' + this.getWarrantyStatusClass(item.warranty_status) + '">' + this.getWarrantyStatusText(item.warranty_status) + '</span></div>';
                html += '</div>';
                html += '</div>';
            }

            // 产品信息
            if (item.model || item.serial_number) {
                html += '<div class="tc-detail-card">';
                html += '<h5 class="tc-detail-card-title">产品信息</h5>';
                html += '<div class="tc-detail-grid">';
                if (item.model) {
                    html += '<div class="tc-detail-item"><label>型号</label><span>' + this.escapeHtml(item.model) + '</span></div>';
                }
                if (item.serial_number) {
                    html += '<div class="tc-detail-item"><label>序列号</label><span>' + this.escapeHtml(item.serial_number) + '</span></div>';
                }
                html += '</div>';
                html += '</div>';
            }

            // 使用信息
            if (item.used_time_hours > 0 || (item.category === 'vehicle' && item.total_mileage > 0)) {
                html += '<div class="tc-detail-card">';
                html += '<h5 class="tc-detail-card-title">使用信息</h5>';
                html += '<div class="tc-detail-grid">';
                if (item.used_time_hours > 0) {
                    html += '<div class="tc-detail-item"><label>使用时间</label><span>' + this.formatUsedTime(item.used_time_hours) + '</span></div>';
                }
                if (item.category === 'vehicle' && item.total_mileage > 0) {
                    html += '<div class="tc-detail-item"><label>总里程</label><span>' + this.formatMileage(item.total_mileage) + '</span></div>';
                }
                html += '</div>';
                html += '</div>';
            }

            // 备注信息
            if (item.notes) {
                html += '<div class="tc-detail-card">';
                html += '<h5 class="tc-detail-card-title">备注</h5>';
                html += '<p class="tc-detail-notes">' + this.escapeHtml(item.notes) + '</p>';
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            $('#tc-modal-body').html(html);
            $('#tc-modal-save').hide();
        },
        
        initModal: function() {
            // 模态框已在HTML中定义
        },
        
        openModal: function(title, content) {
            $('#tc-modal-title').text(title);
            $('#tc-modal-body').html(content);
            $('#tc-modal-save').show();
            $('#tc-modal').show();
        },
        
        closeModal: function() {
            $('#tc-modal').hide();
        },
        
        handleModalSave: function() {
            // 根据模态框内容处理保存逻辑
            TimeCapsuleAdmin.closeModal();
        },

        handleCategoryChange: function() {
            var selectedCategory = $(this).val();

            // 处理证书资质类别
            if (selectedCategory === 'certificate') {
                $('.tc-certificate-only').show();
                $('.tc-non-certificate-fields').hide();
            } else {
                $('.tc-certificate-only').hide();
                $('.tc-non-certificate-fields').show();

                // 处理保修期/出生日期字段
                if (selectedCategory === 'pets') {
                    $('.tc-warranty-field').hide();
                    $('.tc-birthdate-field').show();
                } else {
                    $('.tc-warranty-field').show();
                    $('.tc-birthdate-field').hide();
                }
            }
        },

           
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            // 尝试多个可能的选择器来找到合适的位置显示通知
            var $target = $('.time-capsule-admin h1, .time-capsule-admin h2, .tc-form-header h2');
            if ($target.length > 0) {
                $target.first().after($notice);
            } else {
                // 如果找不到标题，就放在页面顶部
                $('.time-capsule-admin').prepend($notice);
            }

            // 自动消失
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },
        
        // 辅助方法
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        getItemAvatar: function(category) {
            var avatars = {
                'vehicle': '🚗',
                'electronics': '📱',
                'food': '🍿',
                'furniture': '🪑'
            };
            return avatars[category] || '📦';
        },
        
        getStatusClass: function(status) {
            return 'status-' + status;
        },

        getStatusText: function(status) {
            var statusTexts = {
                'active': '使用中',
                'inactive': '闲置',
                'disposed': '已处置'
            };
            return statusTexts[status] || '未知';
        },
        
        getWarrantyStatusText: function(status) {
            var statusTexts = {
                'valid': '保修中',
                'expiring': '即将过保',
                'expired': '已过保',
                'no_warranty': '无保修'
            };
            return statusTexts[status] || '未知';
        },
        
        getWarrantyStatusClass: function(status) {
            return 'warranty-' + status;
        },
        
        formatUsedTime: function(hours) {
            if (hours < 24) {
                return hours + ' 小时';
            } else if (hours < 24 * 30) {
                var days = Math.floor(hours / 24);
                var remainingHours = hours % 24;
                if (remainingHours > 0) {
                    return days + ' 天 ' + remainingHours + ' 小时';
                } else {
                    return days + ' 天';
                }
            } else {
                var days = Math.floor(hours / 24);
                var months = Math.floor(days / 30);
                var remainingDays = days % 30;
                if (remainingDays > 0) {
                    return months + ' 个月 ' + remainingDays + ' 天';
                } else {
                    return months + ' 个月';
                }
            }
        },
        
        formatMileage: function(mileage) {
            if (mileage < 1000) {
                return parseFloat(mileage).toFixed(1) + ' 公里';
            } else {
                return (parseFloat(mileage) / 10000).toFixed(1) + ' 万公里';
            }
        }
    });

    // 初始化
    $(document).ready(function() {
        TimeCapsuleAdmin.init();

        // 初始化类别字段显示
        var initialCategory = $('#tc_category').val();
        if (initialCategory) {
            TimeCapsuleAdmin.handleCategoryChange.call($('#tc_category')[0]);
        }
    });

})(jQuery);

