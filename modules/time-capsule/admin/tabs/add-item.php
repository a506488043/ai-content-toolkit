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

                <!-- 证书资质特有字段 -->
                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_certificate_number"><?php _e('证书编号', 'time-capsule'); ?></label>
                    <input type="text" id="tc_certificate_number" name="certificate_number"
                           value="<?php echo $is_edit ? esc_attr($item_data->certificate_number) : ''; ?>"
                           placeholder="<?php _e('请输入证书编号', 'time-capsule'); ?>">
                </div>

                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_issuing_authority"><?php _e('发证机构', 'time-capsule'); ?></label>
                    <input type="text" id="tc_issuing_authority" name="issuing_authority"
                           value="<?php echo $is_edit ? esc_attr($item_data->issuing_authority) : ''; ?>"
                           placeholder="<?php _e('请输入发证机构名称', 'time-capsule'); ?>">
                </div>

                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_certificate_level"><?php _e('证书等级', 'time-capsule'); ?></label>
                    <select id="tc_certificate_level" name="certificate_level">
                        <option value=""><?php _e('请选择证书等级', 'time-capsule'); ?></option>
                        <option value="entry" <?php echo $is_edit ? selected($item_data->certificate_level, 'entry') : ''; ?>><?php _e('初级', 'time-capsule'); ?></option>
                        <option value="intermediate" <?php echo $is_edit ? selected($item_data->certificate_level, 'intermediate') : ''; ?>><?php _e('中级', 'time-capsule'); ?></option>
                        <option value="senior" <?php echo $is_edit ? selected($item_data->certificate_level, 'senior') : ''; ?>><?php _e('高级', 'time-capsule'); ?></option>
                        <option value="expert" <?php echo $is_edit ? selected($item_data->certificate_level, 'expert') : ''; ?>><?php _e('专家级', 'time-capsule'); ?></option>
                        <option value="other" <?php echo $is_edit ? selected($item_data->certificate_level, 'other') : ''; ?>><?php _e('其他', 'time-capsule'); ?></option>
                    </select>
                </div>

                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_certificate_status"><?php _e('证书状态', 'time-capsule'); ?></label>
                    <select id="tc_certificate_status" name="certificate_status">
                        <option value="valid" <?php echo $is_edit ? selected($item_data->certificate_status, 'valid') : 'selected'; ?>><?php _e('有效', 'time-capsule'); ?></option>
                        <option value="expired" <?php echo $is_edit ? selected($item_data->certificate_status, 'expired') : ''; ?>><?php _e('已过期', 'time-capsule'); ?></option>
                        <option value="renewing" <?php echo $is_edit ? selected($item_data->certificate_status, 'renewing') : ''; ?>><?php _e('续证中', 'time-capsule'); ?></option>
                        <option value="suspended" <?php echo $is_edit ? selected($item_data->certificate_status, 'suspended') : ''; ?>><?php _e('暂停', 'time-capsule'); ?></option>
                    </select>
                </div>
            </div>

            <!-- 购买信息/证书时间 -->
            <div class="tc-form-section">
                <h3 class="tc-purchase-info-title"><?php _e('购买信息', 'time-capsule'); ?></h3>

                <div class="tc-field-group">
                    <label for="tc_purchase_date" class="tc-purchase-date-label"><?php _e('发证时间', 'time-capsule'); ?> <span class="required">*</span></label>
                    <input type="date" id="tc_purchase_date" name="purchase_date"
                           value="<?php echo $is_edit ? esc_attr($item_data->purchase_date) : ''; ?>"
                           required>
                    <small class="tc-field-hint tc-purchase-date-hint">证书的颁发日期，如不填写则使用购买日期作为发证时间</small>
                </div>

                <!-- 证书资质特有字段：发证时间 -->
                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_issue_date"><?php _e('发证时间', 'time-capsule'); ?></label>
                    <input type="date" id="tc_issue_date" name="issue_date"
                           value="<?php echo $is_edit ? esc_attr($item_data->issue_date) : ''; ?>"
                           placeholder="<?php _e('证书的颁发日期', 'time-capsule'); ?>">
                    <small class="tc-field-hint">证书的颁发日期，如不填写则使用购买日期作为发证时间</small>
                </div>

                <div class="tc-field-group tc-non-certificate-fields">
                    <label for="tc_purchase_source"><?php _e('购买来源', 'time-capsule'); ?></label>
                    <input type="text" id="tc_purchase_source" name="purchase_source"
                           value="<?php echo $is_edit ? esc_attr($item_data->purchase_source) : ''; ?>"
                           placeholder="<?php _e('如：京东、淘宝、实体店等', 'time-capsule'); ?>">
                </div>

                <div class="tc-field-group">
                    <label class="tc-price-label"><?php _e('购买价格', 'time-capsule'); ?></label>
                    <input type="number" id="tc_price" name="price" step="0.01" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->price) : ''; ?>"
                           placeholder="0.00">
                    <span class="field-unit">元</span>
                </div>

                <!-- 证书资质特有字段：培训费用 -->
                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_training_fee"><?php _e('培训费用', 'time-capsule'); ?></label>
                    <input type="number" id="tc_training_fee" name="training_fee" step="0.01" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->training_fee) : ''; ?>"
                           placeholder="0.00">
                    <span class="field-unit">元</span>
                </div>

                <!-- 保修期/出生日期字段 - 动态显示 -->
                <div class="tc-field-group tc-non-certificate-fields tc-warranty-field" style="display: none;">
                    <label for="tc_warranty_period"><?php _e('保修期', 'time-capsule'); ?></label>
                    <input type="number" id="tc_warranty_period" name="warranty_period" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->warranty_period) : ''; ?>"
                           placeholder="0">
                    <span class="field-unit">天</span>
                </div>

                <div class="tc-field-group tc-non-certificate-fields tc-birthdate-field" style="display: none;">
                    <label for="tc_birth_date"><?php _e('出生日期', 'time-capsule'); ?></label>
                    <input type="date" id="tc_birth_date" name="warranty_period"
                           value="<?php echo $is_edit ? esc_attr($item_data->warranty_period) : ''; ?>">
                </div>

                <!-- 证书资质特有字段：持证时长 -->
                <div class="tc-field-group tc-certificate-only" style="display: none;">
                    <label for="tc_holding_duration"><?php _e('持证时长', 'time-capsule'); ?></label>
                    <input type="number" id="tc_holding_duration" name="holding_duration" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->holding_duration) : ''; ?>"
                           placeholder="0">
                    <span class="field-unit">月</span>
                    <small class="tc-field-hint">如不填写，系统将根据发证时间自动计算</small>
                </div>
            </div>

            <!-- 证书管理信息（仅证书资质显示） -->
            <div class="tc-form-section tc-certificate-only" style="display: none;">
                <h3><?php _e('证书管理', 'time-capsule'); ?></h3>

                <div class="tc-field-group">
                    <label for="tc_renewal_period"><?php _e('续证周期', 'time-capsule'); ?></label>
                    <div class="tc-input-group">
                        <input type="number" id="tc_renewal_period" name="renewal_period" min="0"
                               value="<?php echo $is_edit ? esc_attr($item_data->renewal_period) : ''; ?>"
                               placeholder="0">
                        <select id="tc_renewal_unit" name="renewal_unit" class="tc-renewal-unit">
                            <option value="months" <?php echo $is_edit ? selected($item_data->renewal_unit, 'months') : 'selected'; ?>><?php _e('月', 'time-capsule'); ?></option>
                            <option value="years" <?php echo $is_edit ? selected($item_data->renewal_unit, 'years') : ''; ?>><?php _e('年', 'time-capsule'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="tc-field-group">
                    <label for="tc_renewal_date"><?php _e('最近续证时间', 'time-capsule'); ?></label>
                    <input type="date" id="tc_renewal_date" name="renewal_date"
                           value="<?php echo $is_edit ? esc_attr($item_data->renewal_date) : ''; ?>"
                           placeholder="<?php _e('最近一次续证的日期', 'time-capsule'); ?>">
                </div>

                <div class="tc-field-group">
                    <label for="tc_renewal_fee"><?php _e('续证费用', 'time-capsule'); ?></label>
                    <input type="number" id="tc_renewal_fee" name="renewal_fee" step="0.01" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->renewal_fee) : ''; ?>"
                           placeholder="0.00">
                    <span class="field-unit">元</span>
                </div>

                <div class="tc-field-group">
                    <label for="tc_reminder_days"><?php _e('提醒天数', 'time-capsule'); ?></label>
                    <input type="number" id="tc_reminder_days" name="reminder_days" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->reminder_days) : '30'; ?>"
                           placeholder="30">
                    <span class="field-unit">天</span>
                    <small class="tc-field-hint">在证书到期前多少天开始提醒续证</small>
                </div>
            </div>

            <!-- 产品信息（非证书资质显示） -->
            <div class="tc-form-section tc-non-certificate-fields">
                <h3><?php _e('产品信息', 'time-capsule'); ?></h3>

                <div class="tc-field-group">
                    <label for="tc_brand"><?php _e('品牌', 'time-capsule'); ?></label>
                    <input type="text" id="tc_brand" name="brand"
                           value="<?php echo $is_edit ? esc_attr($item_data->brand) : ''; ?>">
                </div>

                <div class="tc-field-group">
                    <label for="tc_model"><?php _e('型号', 'time-capsule'); ?></label>
                    <input type="text" id="tc_model" name="model"
                           value="<?php echo $is_edit ? esc_attr($item_data->model) : ''; ?>">
                </div>

                <div class="tc-field-group">
                    <label for="tc_serial_number"><?php _e('序列号', 'time-capsule'); ?></label>
                    <input type="text" id="tc_serial_number" name="serial_number"
                           value="<?php echo $is_edit ? esc_attr($item_data->serial_number) : ''; ?>">
                </div>
            </div>

            <!-- 使用信息 -->
            <div class="tc-form-section">
                <h3 class="tc-usage-info-title"><?php _e('使用信息', 'time-capsule'); ?></h3>

                <div class="tc-field-group tc-non-certificate-fields">
                    <label for="tc_used_time_hours"><?php _e('年龄', 'time-capsule'); ?></label>
                    <input type="number" id="tc_used_time_hours" name="used_time_hours" step="0.1" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->used_time_hours) : ''; ?>"
                           placeholder="0">
                    <span class="field-unit">岁</span>
                </div>

                <!-- 交通工具特有字段 -->
                <div class="tc-field-group tc-vehicle-only" style="display: none;">
                    <label for="tc_total_mileage"><?php _e('总里程', 'time-capsule'); ?></label>
                    <input type="number" id="tc_total_mileage" name="total_mileage" step="0.1" min="0"
                           value="<?php echo $is_edit ? esc_attr($item_data->total_mileage) : ''; ?>"
                           placeholder="0.0">
                    <span class="field-unit">公里</span>
                </div>

                <div class="tc-field-group">
                    <label for="tc_notes"><?php _e('备注', 'time-capsule'); ?></label>
                    <textarea id="tc_notes" name="notes" rows="3"><?php echo $is_edit ? esc_textarea($item_data->notes) : ''; ?></textarea>
                </div>
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

        // 对于证书资质，隐藏不相关的字段，更新区块标题
        if (category === 'certificate') {
            $('.tc-non-certificate-fields').hide();
            // 更新区块标题和字段说明
            $('.tc-purchase-info-title').text('证书时间信息');
            $('.tc-usage-info-title').text('证书备注信息');
            $('.tc-purchase-date-label').html('<?php _e('购买日期', 'time-capsule'); ?> <span class="required">*</span>');
            $('.tc-purchase-date-hint').text('用于记录证书相关费用的购买时间');
            $('.tc-price-label').text('<?php _e('证书费用', 'time-capsule'); ?>');
        } else {
            $('.tc-non-certificate-fields').show();
            // 恢复普通物品的设置
            $('.tc-purchase-info-title').text('购买信息');
            $('.tc-usage-info-title').text('使用信息');
            $('.tc-purchase-date-label').html('<?php _e('购买日期', 'time-capsule'); ?> <span class="required">*</span>');
            $('.tc-purchase-date-hint').text('物品的购买日期');
            $('.tc-price-label').text('<?php _e('购买价格', 'time-capsule'); ?>');
        }
    }

    // 类别变化时切换字段显示
    $('#tc_category').on('change', toggleCategoryFields);
    toggleCategoryFields(); // 初始化

    // 统一使用tc_purchase_date字段，不再需要映射
    $('#tc-item-form').on('submit', function() {
        var category = $('#tc_category').val();
        if (category === 'certificate') {
            // 对于证书资质，将购买日期字段的值映射到purchase_date字段（后端会存储为发证时间）
            var purchaseDate = $('#tc_purchase_date').val();
            if (purchaseDate) {
                $('<input type="hidden" name="purchase_date">').val(purchaseDate).appendTo('#tc-item-form');
            }
        } else {
            // 对于其他类别，将购买日期字段的值映射到purchase_date字段
            var purchaseDate = $('#tc_purchase_date').val();
            if (purchaseDate) {
                $('<input type="hidden" name="purchase_date">').val(purchaseDate).appendTo('#tc-item-form');
            }
        }
    });
});
</script>