<?php
/**
 * 添加物品选项卡
 */

if (!defined('ABSPATH')) {
    exit;
}

// 加载依赖类
if (!class_exists('TimeCapsule_Item')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-item.php';
}
if (!class_exists('TimeCapsule_Category')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-category.php';
}
if (!class_exists('TimeCapsule_Database')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-database.php';
}
// 加载通用函数
if (!function_exists('tc_calculate_item_age')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/functions.php';
}

// 加载后台管理JavaScript
wp_enqueue_script(
    'wordpress-toolkit-time-capsule-admin',
    WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/assets/js/admin.js',
    array('jquery'),
    TIME_CAPSULE_VERSION,
    true
);

// 传递必要的变量到JavaScript
wp_localize_script('wordpress-toolkit-time-capsule-admin', 'TimeCapsuleAdmin', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('time_capsule_nonce'),
    'strings' => array(
        'saveSuccess' => __('保存成功！', 'time-capsule'),
        'saveError' => __('保存失败，请重试。', 'time-capsule'),
        'networkError' => __('网络错误，请重试。', 'time-capsule'),
        'confirmDelete' => __('确定要删除这个物品吗？此操作不可撤销。', 'time-capsule'),
        'deleteSuccess' => __('删除成功！', 'time-capsule'),
        'deleteError' => __('删除失败，请重试。', 'time-capsule')
    )
));

// 获取编辑的物品ID（如果有）
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$item_data = null;

if ($edit_id > 0) {
    $item_data = $item_manager->get_item($edit_id);
    if (!$item_data) {
        echo '<div class="notice notice-error"><p>' . __('物品不存在或无权限编辑', 'time-capsule') . '</p></div>';
        return;
    }
}

$is_edit = !empty($item_data);
$page_title = $is_edit ? __('编辑物品', 'time-capsule') : __('添加物品', 'time-capsule');
?>

<div class="tc-add-item">
    <div class="tc-form-header">
        <h2><?php echo esc_html($page_title); ?></h2>
        <?php if ($is_edit): ?>
            <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=add'); ?>"
               class="button"><?php _e('添加新物品', 'time-capsule'); ?></a>
        <?php endif; ?>
    </div>

    <form id="tc-item-form" class="tc-item-form">
        <?php wp_nonce_field('time_capsule_nonce', 'tc_nonce'); ?>

        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item_data->id); ?>">
        <?php endif; ?>

        <div class="tc-form-grid">
            <!-- 基本信息 -->
            <div class="tc-form-section">
                <h3><?php _e('基本信息', 'time-capsule'); ?></h3>

                <div class="tc-field-group">
                    <label for="tc_name"><?php _e('物品名称', 'time-capsule'); ?> <span class="required">*</span></label>
                    <input type="text" id="tc_name" name="name"
                           value="<?php echo $is_edit ? esc_attr($item_data->name) : ''; ?>"
                           required>
                </div>

                <div class="tc-field-group">
                    <label for="tc_category"><?php _e('类别', 'time-capsule'); ?> <span class="required">*</span></label>
                    <select id="tc_category" name="category" required>
                        <option value=""><?php _e('请选择类别', 'time-capsule'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->name); ?>"
                                    <?php echo $is_edit ? selected($item_data->category, $category->name) : ''; ?>>
                                <?php echo esc_html($category->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tc-field-group">
                    <label for="tc_description"><?php _e('描述', 'time-capsule'); ?></label>
                    <textarea id="tc_description" name="description" rows="3"><?php echo $is_edit ? esc_textarea($item_data->description) : ''; ?></textarea>
                </div>

                <div class="tc-field-group tc-non-certificate-fields">
                    <label for="tc_status"><?php _e('状态', 'time-capsule'); ?></label>
                    <select id="tc_status" name="status">
                        <option value="active" <?php echo $is_edit ? selected($item_data->status, 'active') : 'selected'; ?>><?php _e('使用中', 'time-capsule'); ?></option>
                        <option value="inactive" <?php echo $is_edit ? selected($item_data->status, 'inactive') : ''; ?>><?php _e('闲置', 'time-capsule'); ?></option>
                        <option value="disposed" <?php echo $is_edit ? selected($item_data->status, 'disposed') : ''; ?>><?php _e('已处置', 'time-capsule'); ?></option>
                    </select>
                </div>
            </div>

            <!-- 购买信息/证书时间 -->
            <div class="tc-form-section">
                <h3 class="tc-purchase-info-title"><?php _e('购买信息', 'time-capsule'); ?></h3>

                <div class="tc-field-group">
                    <label for="tc_purchase_date" class="tc-purchase-date-label"><?php _e('购买日期', 'time-capsule'); ?> <span class="tc-required-mark">*</span></label>
                    <input type="date" id="tc_purchase_date" name="purchase_date"
                           value="<?php echo $is_edit ? esc_attr($item_data->purchase_date) : ''; ?>"
                           required>
                    <small class="tc-field-hint tc-purchase-date-hint">物品的购买日期</small>
                </div>
        </div>

        <!-- 表单按钮 -->
        <div class="tc-form-actions">
            <button type="submit" class="button-primary">
                <?php echo $is_edit ? __('更新物品', 'time-capsule') : __('添加物品', 'time-capsule'); ?>
            </button>

            <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items'); ?>"
               class="button"><?php _e('返回列表', 'time-capsule'); ?></a>

            <?php if ($is_edit): ?>
                <button type="button" class="button tc-delete-item"
                        data-item-id="<?php echo esc_attr($item_data->id); ?>">
                    <?php _e('删除物品', 'time-capsule'); ?>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // 类别变化时切换字段显示
    function toggleCategoryFields() {
        var category = $('#tc_category').val();

        // 显示/隐藏特定类别的字段
        $('.tc-vehicle-only').toggle(category === 'vehicle');
        $('.tc-certificate-only').toggle(category === 'certificate');

        // 对于证书资质，隐藏不相关的字段
        if (category === 'certificate') {
            $('.tc-non-certificate-fields').hide();
            // 更新区块标题和字段说明
            $('.tc-purchase-info-title').text('证书时间信息');
            $('.tc-purchase-date-label').html('<?php _e('发证时间', 'time-capsule'); ?> <span class="required">*</span>');
            $('.tc-purchase-date-hint').text('证书的颁发日期，将作为发证时间使用');
            $('#tc_purchase_date').prop('required', true);
            $('.tc-required-mark').show();
        } else {
            $('.tc-non-certificate-fields').show();
            // 恢复普通物品的设置
            $('.tc-purchase-info-title').text('购买信息');
            $('.tc-purchase-date-label').html('<?php _e('购买日期', 'time-capsule'); ?> <span class="required">*</span>');
            $('.tc-purchase-date-hint').text('物品的购买日期');
            $('#tc_purchase_date').prop('required', true);
            $('.tc-required-mark').show();
        }
    }

    // 类别变化时切换字段显示
    $('#tc_category').on('change', toggleCategoryFields);
    toggleCategoryFields(); // 初始化
});
</script>