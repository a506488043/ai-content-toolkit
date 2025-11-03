/**
 * 前端物品管理脚本
 */
jQuery(document).ready(function($) {
    'use strict';

    // 模态框元素
    var $itemModal = $('#tc-item-modal');
    var $deleteModal = $('#tc-delete-modal');
    var $itemForm = $('#tc-item-form');
    var $modalTitle = $('#tc-modal-title');
    var $submitText = $('#tc-submit-text');
    var $itemId = $('#tc-item-id');

    // 当前操作的物品ID
    var currentItemId = null;

    // 初始化事件监听
    function initEventListeners() {
        // 打开添加物品模态框
        $('#tc-add-item-btn, #tc-add-first-item').on('click', openAddModal);

        // 编辑物品按钮
        $(document).on('click', '.tc-edit-btn', function() {
            var itemId = $(this).data('item-id');
            openEditModal(itemId);
        });

        // 删除物品按钮
        $(document).on('click', '.tc-delete-btn', function() {
            var itemId = $(this).data('item-id');
            openDeleteModal(itemId);
        });

        // 关闭模态框
        $('.tc-modal-close').on('click', closeAllModals);

        // 点击模态框背景关闭
        $('.tc-modal-overlay').on('click', closeAllModals);

        // 类别变更事件 - 显示/隐藏特定字段
        $('#tc_category').on('change', toggleCategoryFields);

        // 表单提交
        $itemForm.on('submit', handleFormSubmit);

        // 确认删除
        $('#tc-confirm-delete').on('click', handleDeleteItem);

        // ESC键关闭模态框
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) {
                closeAllModals();
            }
        });
    }

    // 打开添加物品模态框
    function openAddModal() {
        $modalTitle.text('添加物品');
        $submitText.text('添加物品');
        $itemId.val('');
        $itemForm[0].reset();
        toggleCategoryFields(); // 初始化类别字段
        $itemModal.show();
    }

    // 打开编辑物品模态框
    function openEditModal(itemId) {
        currentItemId = itemId;

        // 显示加载状态
        $modalTitle.text('加载中...');
        $submitText.text('加载中...');
        $itemForm.addClass('loading');
        $itemModal.show();

        // 获取物品数据
        $.ajax({
            url: tc_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'time_capsule_frontend_get_item',
                item_id: itemId,
                nonce: tc_frontend_ajax.nonce
            },
            success: function(response) {
                $itemForm.removeClass('loading');

                if (response.success && response.data) {
                    var item = response.data;

                    $modalTitle.text('编辑物品');
                    $submitText.text('更新物品');
                    $itemId.val(item.id);

                    // 填充表单数据
                    $('#tc_name').val(item.name);
                    $('#tc_category').val(item.category);
                    $('#tc_description').val(item.description);
                    $('#tc_status').val(item.status);
                    $('#tc_purchase_date').val(item.purchase_date);
                    $('#tc_purchase_source').val(item.purchase_source);
                    $('#tc_price').val(item.price);
                    $('#tc_warranty_period').val(item.warranty_period);
                    $('#tc_shelf_life').val(item.shelf_life);
                    $('#tc_brand').val(item.brand);
                    $('#tc_model').val(item.model);
                    $('#tc_serial_number').val(item.serial_number);
                    $('#tc_used_time_hours').val(item.used_time_hours);
                    $('#tc_total_mileage').val(item.total_mileage);
                    $('#tc_notes').val(item.notes);

                    // 更新类别特定字段
                    toggleCategoryFields();
                } else {
                    showMessage('获取物品数据失败: ' + (response.data || '未知错误'), 'error');
                    closeAllModals();
                }
            },
            error: function(xhr, status, error) {
                $itemForm.removeClass('loading');
                showMessage('网络错误: ' + error, 'error');
                closeAllModals();
            }
        });
    }

    // 打开删除确认模态框
    function openDeleteModal(itemId) {
        currentItemId = itemId;
        $deleteModal.show();
    }

    // 关闭所有模态框
    function closeAllModals() {
        $itemModal.hide();
        $deleteModal.hide();
        currentItemId = null;
    }

    // 根据类别显示/隐藏特定字段
    function toggleCategoryFields() {
        var category = $('#tc_category').val();

        // 交通工具字段
        $('.tc-vehicle-only').toggle(category === 'vehicle');

        // 零食食品字段
        $('.tc-snacks-only').toggle(category === 'snacks');

        // 保修期/出生日期字段
        if (category === 'pets') {
            $('.tc-warranty-field').hide();
            $('.tc-birthdate-field').show();
        } else {
            $('.tc-warranty-field').show();
            $('.tc-birthdate-field').hide();
        }

        // 已用时间/年龄字段
        if (category === 'pets') {
            $('.tc-used-time-field').hide();
            $('.tc-age-field').show();
        } else {
            $('.tc-used-time-field').show();
            $('.tc-age-field').hide();
        }
    }

    // 处理表单提交
    function handleFormSubmit(e) {
        e.preventDefault();

        // 验证必填字段
        if (!validateForm()) {
            return;
        }

        // 显示加载状态
        $itemForm.addClass('loading');

        // 收集表单数据
        var formData = new FormData($itemForm[0]);
        formData.append('action', 'time_capsule_frontend_save_item');
        formData.append('nonce', tc_frontend_ajax.nonce);

        // 发送AJAX请求
        $.ajax({
            url: tc_frontend_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $itemForm.removeClass('loading');

                if (response.success) {
                    showMessage(response.data.message || '操作成功', 'success');
                    closeAllModals();
                    refreshItemsList();
                } else {
                    showMessage('操作失败: ' + (response.data || '未知错误'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $itemForm.removeClass('loading');
                showMessage('网络错误: ' + error, 'error');
            }
        });
    }

    // 处理删除物品
    function handleDeleteItem() {
        if (!currentItemId) {
            showMessage('无效的物品ID', 'error');
            return;
        }

        // 显示加载状态
        $('#tc-confirm-delete').prop('disabled', true).text('删除中...');

        $.ajax({
            url: tc_frontend_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'time_capsule_frontend_delete_item',
                item_id: currentItemId,
                nonce: tc_frontend_ajax.nonce
            },
            success: function(response) {
                $('#tc-confirm-delete').prop('disabled', false).text('确认删除');

                if (response.success) {
                    showMessage(response.data.message || '删除成功', 'success');
                    closeAllModals();
                    refreshItemsList();
                } else {
                    showMessage('删除失败: ' + (response.data || '未知错误'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $('#tc-confirm-delete').prop('disabled', false).text('确认删除');
                showMessage('网络错误: ' + error, 'error');
            }
        });
    }

    // 验证表单
    function validateForm() {
        var isValid = true;
        var errors = [];

        // 验证必填字段
        if (!$('#tc_name').val().trim()) {
            errors.push('物品名称不能为空');
            isValid = false;
        }

        if (!$('#tc_category').val()) {
            errors.push('请选择类别');
            isValid = false;
        }

        if (!$('#tc_purchase_date').val()) {
            errors.push('购买日期不能为空');
            isValid = false;
        }

        // 显示错误信息
        if (errors.length > 0) {
            showMessage('请检查以下错误:\\n' + errors.join('\\n'), 'error');
        }

        return isValid;
    }

    // 刷新物品列表
    function refreshItemsList() {
        // 简单刷新页面
        location.reload();
    }

    // 显示消息
    function showMessage(message, type) {
        // 移除现有消息
        $('.tc-message').remove();

        // 创建消息元素
        var messageClass = 'tc-message tc-message-' + type;
        var $message = $('<div class="' + messageClass + '">' + message + '</div>');

        // 添加到页面顶部
        $('.tc-manage-header').after($message);

        // 自动隐藏
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // 初始化
    initEventListeners();
});