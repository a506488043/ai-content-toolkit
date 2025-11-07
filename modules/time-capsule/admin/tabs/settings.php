<?php
/**
 * 设置选项卡
 */

if (!defined('ABSPATH')) {
    exit;
}

// 处理设置保存
if (isset($_POST['tc_save_settings']) && wp_verify_nonce($_POST['tc_settings_nonce'], 'tc_settings')) {
    // 保存设置逻辑
    $settings = array(
        'items_per_page' => intval($_POST['items_per_page']),
        'default_status' => sanitize_text_field($_POST['default_status']),
        'enable_notifications' => isset($_POST['enable_notifications']) ? 1 : 0,
        'notification_days' => intval($_POST['notification_days'])
    );
    
    foreach ($settings as $key => $value) {
        update_option('tc_' . $key, $value);
    }
    
    echo '<div class="notice notice-success"><p>' . __('设置已保存', 'time-capsule') . '</p></div>';
}

// 获取当前设置
$items_per_page = get_option('tc_items_per_page', 20);
$default_status = get_option('tc_default_status', 'active');
$enable_notifications = get_option('tc_enable_notifications', 1);
$notification_days = get_option('tc_notification_days', 30);
?>

<div class="tc-settings">
    <form method="post" action="">
        <?php wp_nonce_field('tc_settings', 'tc_settings_nonce'); ?>
        
        <div class="tc-settings-sections">
            <!-- 显示设置 -->
            <div class="tc-settings-section">
                <h3><?php _e('显示设置', 'time-capsule'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="items_per_page"><?php _e('每页显示物品数', 'time-capsule'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="items_per_page" name="items_per_page" 
                                   value="<?php echo esc_attr($items_per_page); ?>" 
                                   min="5" max="100" class="small-text">
                            <p class="description"><?php _e('设置后台物品列表每页显示的物品数量', 'time-capsule'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_status"><?php _e('默认物品状态', 'time-capsule'); ?></label>
                        </th>
                        <td>
                            <select id="default_status" name="default_status">
                                <option value="active" <?php selected($default_status, 'active'); ?>><?php _e('使用中', 'time-capsule'); ?></option>
                                <option value="inactive" <?php selected($default_status, 'inactive'); ?>><?php _e('闲置', 'time-capsule'); ?></option>
                            </select>
                            <p class="description"><?php _e('新添加物品时的默认状态', 'time-capsule'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 通知设置 -->
            <div class="tc-settings-section">
                <h3><?php _e('通知设置', 'time-capsule'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_notifications"><?php _e('启用保修提醒', 'time-capsule'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_notifications" name="enable_notifications" 
                                   value="1" <?php checked($enable_notifications, 1); ?>>
                            <label for="enable_notifications"><?php _e('启用保修到期提醒功能', 'time-capsule'); ?></label>
                            <p class="description"><?php _e('开启后会在物品即将过保时显示提醒', 'time-capsule'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="notification_days"><?php _e('提醒天数', 'time-capsule'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="notification_days" name="notification_days" 
                                   value="<?php echo esc_attr($notification_days); ?>" 
                                   min="1" max="365" class="small-text">
                            <span><?php _e('天', 'time-capsule'); ?></span>
                            <p class="description"><?php _e('在保修到期前多少天开始提醒', 'time-capsule'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 数据管理 -->
            <div class="tc-settings-section">
                <h3><?php _e('数据管理', 'time-capsule'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('数据导出', 'time-capsule'); ?></th>
                        <td>
                            <button type="button" class="button" id="tc-export-csv">
                                <?php _e('导出为 CSV', 'time-capsule'); ?>
                            </button>
                            <button type="button" class="button" id="tc-export-json">
                                <?php _e('导出为 JSON', 'time-capsule'); ?>
                            </button>
                            <p class="description"><?php _e('导出所有物品数据到文件', 'time-capsule'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('数据统计', 'time-capsule'); ?></th>
                        <td>
                            <div class="tc-data-stats">
                                <div class="tc-stat-row">
                                    <span class="tc-stat-label"><?php _e('总物品数：', 'time-capsule'); ?></span>
                                    <span class="tc-stat-value"><?php echo esc_html($stats['total_items']); ?></span>
                                </div>
                                <div class="tc-stat-row">
                                    <span class="tc-stat-label"><?php _e('即将过保：', 'time-capsule'); ?></span>
                                    <span class="tc-stat-value"><?php echo esc_html($stats['expiring_warranty']); ?></span>
                                </div>
                                <?php foreach ($categories as $category): ?>
                                    <div class="tc-stat-row">
                                        <span class="tc-stat-label"><?php echo esc_html($category->display_name); ?>：</span>
                                        <span class="tc-stat-value"><?php echo esc_html(isset($stats['by_category'][$category->name]) ? $stats['by_category'][$category->name] : 0); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 短代码使用 -->
            <div class="tc-settings-section">
                <h3><?php _e('短代码使用', 'time-capsule'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('基本短代码', 'time-capsule'); ?></th>
                        <td>
                            <code>[time_capsule]</code>
                            <p class="description"><?php _e('在页面或文章中显示时间胶囊', 'time-capsule'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('带参数的短代码', 'time-capsule'); ?></th>
                        <td>
                            <code>[time_capsule category="electronics" limit="6"]</code>
                            <p class="description"><?php _e('只显示电子产品类别，限制6个物品', 'time-capsule'); ?></p>
                            
                            <h4><?php _e('可用参数：', 'time-capsule'); ?></h4>
                            <ul>
                                <li><strong>category</strong>: <?php _e('指定类别 (vehicle, electronics, furniture)', 'time-capsule'); ?></li>
                                <li><strong>limit</strong>: <?php _e('限制显示数量', 'time-capsule'); ?></li>
                                <li><strong>user_id</strong>: <?php _e('指定用户ID（默认为当前用户）', 'time-capsule'); ?></li>
                            </ul>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="tc_save_settings" class="button-primary" 
                   value="<?php _e('保存设置', 'time-capsule'); ?>">
        </p>
    </form>
</div>

<style>
.tc-settings {
    max-width: 800px;
}

.tc-settings-sections {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.tc-settings-section {
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.tc-settings-section:last-child {
    border-bottom: none;
}

.tc-settings-section h3 {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
    color: #2271b1;
}

.tc-data-stats {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}

.tc-stat-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #e5e5e5;
}

.tc-stat-row:last-child {
    border-bottom: none;
}

.tc-stat-label {
    font-weight: 500;
    color: #666;
}

.tc-stat-value {
    font-weight: bold;
    color: #2271b1;
}

code {
    background: #f1f1f1;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
}

.tc-settings h4 {
    margin: 15px 0 10px 0;
    color: #333;
}

.tc-settings ul {
    margin: 10px 0;
    padding-left: 20px;
}

.tc-settings li {
    margin-bottom: 5px;
    line-height: 1.5;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#tc-export-csv').on('click', function() {
        window.location.href = ajaxurl + '?action=tc_export_data&format=csv&nonce=' + 
            $('input[name="tc_settings_nonce"]').val();
    });
    
    $('#tc-export-json').on('click', function() {
        window.location.href = ajaxurl + '?action=tc_export_data&format=json&nonce=' + 
            $('input[name="tc_settings_nonce"]').val();
    });
});
</script>
