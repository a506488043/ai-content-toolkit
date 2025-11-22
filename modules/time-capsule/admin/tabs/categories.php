<?php
/**
 * 类别管理选项卡
 */

if (!defined('ABSPATH')) {
    exit;
}

$categories = $category_manager->get_categories(false);

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_options')) {

    if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
        // 验证nonce
        if (!wp_verify_nonce($_POST['tc_category_nonce'], 'time_capsule_add_category')) {
            $message = '安全验证失败，请重试。';
            $message_type = 'error';
        } else {
            // 收集表单数据
            $category_data = array(
                'name' => sanitize_text_field($_POST['category_name']),
                'display_name' => sanitize_text_field($_POST['display_name']),
                'description' => sanitize_textarea_field($_POST['description']),
                'icon' => sanitize_text_field($_POST['icon']),
                'color' => sanitize_hex_color($_POST['color'])
            );

            // 添加类别
            $result = $category_manager->add_category($category_data);

            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '类别添加成功！';
                $message_type = 'success';
                // 刷新页面以显示新类别
                echo '<script>window.location.href = window.location.href;</script>';
                exit;
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
        // 验证nonce
        $category_name = sanitize_text_field($_POST['category_name']);
        if (!wp_verify_nonce($_POST['tc_delete_category_nonce'], 'time_capsule_delete_category_' . $category_name)) {
            $message = '安全验证失败，请重试。';
            $message_type = 'error';
        } else {
            // 删除类别
            $result = $category_manager->delete_category($category_name);

            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '类别删除成功！';
                $message_type = 'success';
                // 刷新页面以更新列表
                echo '<script>window.location.href = window.location.href;</script>';
                exit;
            }
        }
    }

    // 处理启用/禁用类别
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_category') {
        // 验证nonce
        $category_name = sanitize_text_field($_POST['category_name']);
        $new_status = intval($_POST['new_status']);
        if (!wp_verify_nonce($_POST['tc_toggle_category_nonce'], 'time_capsule_toggle_category_' . $category_name)) {
            $message = '安全验证失败，请重试。';
            $message_type = 'error';
        } else {
            // 更新类别状态
            $result = $category_manager->db->update_category($category_name, array('is_active' => $new_status));

            if ($result) {
                $message = $new_status ? '类别启用成功！' : '类别禁用成功！';
                $message_type = 'success';
            } else {
                $message = '操作失败，请重试。';
                $message_type = 'error';
            }
            // 刷新页面以更新列表
            echo '<script>window.location.href = window.location.href;</script>';
            exit;
        }
    }

    // 处理强制更新类别
    if (isset($_POST['action']) && $_POST['action'] === 'force_update_categories') {
        // 验证nonce
        if (!wp_verify_nonce($_POST['tc_force_update_nonce'], 'time_capsule_force_update_categories')) {
            $message = '安全验证失败，请重试。';
            $message_type = 'error';
        } else {
            // 强制更新类别
            if (method_exists($category_manager, 'force_update_categories')) {
                $result = $category_manager->force_update_categories();
                if ($result) {
                    $message = '类别强制更新成功！';
                    $message_type = 'success';
                } else {
                    $message = '类别更新失败，请重试。';
                    $message_type = 'error';
                }
            } else {
                $message = '类别更新方法不存在，请检查插件版本。';
                $message_type = 'error';
            }
            // 刷新页面以更新列表
            echo '<script>window.location.href = window.location.href;</script>';
            exit;
        }
    }
}
?>

<div class="tc-categories-management">
    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="tc-section-header">
        <h3><?php _e('类别管理', 'time-capsule'); ?></h3>
        <p><?php _e('管理物品类别和字段配置', 'time-capsule'); ?></p>
    </div>
    
    <div class="tc-categories-grid">
        <?php foreach ($categories as $category): ?>
            <div class="tc-category-card">
                <div class="tc-category-header">
                    <div class="tc-category-icon">
                        <span class="dashicons <?php echo esc_attr($category->icon); ?>"></span>
                    </div>
                    <div class="tc-category-info">
                        <h4><?php echo esc_html($category->display_name); ?></h4>
                        <div class="tc-category-name"><?php echo esc_html($category->name); ?></div>
                    </div>
                    <div class="tc-category-status">
                        <?php if ($category->is_active): ?>
                            <span class="tc-status-active"><?php _e('启用', 'time-capsule'); ?></span>
                            <form method="post" action="" class="tc-toggle-category-form" style="display: inline-block; margin-left: 10px;">
                                <?php wp_nonce_field('wordpress_ai_toolkit_time_capsule'); ?>
                                <?php wp_nonce_field('time_capsule_toggle_category_' . $category->name, 'tc_toggle_category_nonce'); ?>
                                <input type="hidden" name="action" value="toggle_category">
                                <input type="hidden" name="category_name" value="<?php echo esc_attr($category->name); ?>">
                                <input type="hidden" name="new_status" value="0">
                                <button type="submit" class="button-link button-link-secondary" onclick="return confirm('确定要禁用类别 \'<?php echo esc_js($category->display_name); ?>\' 吗？')">
                                    <?php _e('禁用', 'time-capsule'); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="tc-status-inactive"><?php _e('禁用', 'time-capsule'); ?></span>
                            <form method="post" action="" class="tc-toggle-category-form" style="display: inline-block; margin-left: 10px;">
                                <?php wp_nonce_field('wordpress_ai_toolkit_time_capsule'); ?>
                                <?php wp_nonce_field('time_capsule_toggle_category_' . $category->name, 'tc_toggle_category_nonce'); ?>
                                <input type="hidden" name="action" value="toggle_category">
                                <input type="hidden" name="category_name" value="<?php echo esc_attr($category->name); ?>">
                                <input type="hidden" name="new_status" value="1">
                                <button type="submit" class="button-link button-link-primary" onclick="return confirm('确定要启用类别 \'<?php echo esc_js($category->display_name); ?>\' 吗？')">
                                    <?php _e('启用', 'time-capsule'); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php
                        // 检查类别中是否有物品
                        $item_count = $category_manager->get_category_item_count($category->name);
                        if ($item_count === 0): ?>
                            <form method="post" action="" class="tc-delete-category-form" style="display: inline-block; margin-left: 10px;">
                                <?php wp_nonce_field('wordpress_ai_toolkit_time_capsule'); ?>
                                <?php wp_nonce_field('time_capsule_delete_category_' . $category->name, 'tc_delete_category_nonce'); ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_name" value="<?php echo esc_attr($category->name); ?>">
                                <button type="submit" class="button-link button-link-delete" onclick="return confirm('确定要删除类别 \'<?php echo esc_js($category->display_name); ?>\' 吗？此操作不可恢复。')">
                                    <?php _e('删除', 'time-capsule'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="tc-category-fields">
                    <h5><?php _e('字段配置', 'time-capsule'); ?></h5>
                    <?php 
                    // 检查fields字段类型，如果已经是数组则直接使用，否则解码JSON
                    if (is_array($category->fields)) {
                        $fields = $category->fields;
                    } else {
                        $fields = json_decode($category->fields, true);
                    }
                    if ($fields): 
                    ?>
                        <ul class="tc-fields-list">
                            <?php foreach ($fields as $field_name => $field_config): ?>
                                <li>
                                    <strong><?php echo esc_html($field_config['label']); ?></strong>
                                    <span class="tc-field-type">(<?php echo esc_html($field_config['type']); ?>)</span>
                                    <?php if (isset($field_config['unit'])): ?>
                                        <span class="tc-field-unit"><?php echo esc_html($field_config['unit']); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="tc-no-fields"><?php _e('暂无字段配置', 'time-capsule'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="tc-category-stats">
                    <?php 
                    $count = isset($stats['by_category'][$category->name]) ? $stats['by_category'][$category->name] : 0;
                    ?>
                    <div class="tc-stat-item">
                        <span class="tc-stat-number"><?php echo esc_html($count); ?></span>
                        <span class="tc-stat-label"><?php _e('个物品', 'time-capsule'); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="tc-category-actions">
        <div class="tc-add-category-form">
            <h4><?php _e('添加新类别', 'time-capsule'); ?></h4>
            <form method="post" action="" class="tc-category-form">
                <?php wp_nonce_field('wordpress_ai_toolkit_time_capsule'); ?>
                <?php wp_nonce_field('time_capsule_add_category', 'tc_category_nonce'); ?>
                <input type="hidden" name="action" value="add_category">

                <div class="tc-form-row">
                    <div class="tc-form-group">
                        <label for="category_name"><?php _e('类别名称*', 'time-capsule'); ?></label>
                        <input type="text" id="category_name" name="category_name" required
                               placeholder="例如：electronics"
                               pattern="[a-z0-9_-]+"
                               title="只能包含小写字母、数字、下划线和连字符">
                        <p class="description"><?php _e('只能包含小写字母、数字、下划线和连字符', 'time-capsule'); ?></p>
                    </div>

                    <div class="tc-form-group">
                        <label for="display_name"><?php _e('显示名称*', 'time-capsule'); ?></label>
                        <input type="text" id="display_name" name="display_name" required
                               placeholder="例如：电子产品">
                    </div>
                </div>

                <div class="tc-form-row">
                    <div class="tc-form-group">
                        <label for="description"><?php _e('描述', 'time-capsule'); ?></label>
                        <textarea id="description" name="description" rows="2"
                                  placeholder="例如：手机、电脑、平板等电子设备"></textarea>
                    </div>
                </div>

                <div class="tc-form-row">
                    <div class="tc-form-group">
                        <label for="icon"><?php _e('图标', 'time-capsule'); ?></label>
                        <select id="icon" name="icon">
                            <option value="tag"><?php _e('标签', 'time-capsule'); ?></option>
                            <option value="devices"><?php _e('设备', 'time-capsule'); ?></option>
                            <option value="home"><?php _e('家居', 'time-capsule'); ?></option>
                            <option value="chair"><?php _e('椅子', 'time-capsule'); ?></option>
                            <option value="shirt"><?php _e('衣服', 'time-capsule'); ?></option>
                            <option value="book"><?php _e('书籍', 'time-capsule'); ?></option>
                            <option value="bicycle"><?php _e('自行车', 'time-capsule'); ?></option>
                            <option value="tools"><?php _e('工具', 'time-capsule'); ?></option>
                            <option value="car"><?php _e('汽车', 'time-capsule'); ?></option>
                            <option value="smartphone"><?php _e('手机', 'time-capsule'); ?></option>
                            <option value="food"><?php _e('食物', 'time-capsule'); ?></option>
                            <option value="admin-post"><?php _e('文章', 'time-capsule'); ?></option>
                        </select>
                    </div>

                    <div class="tc-form-group">
                        <label for="color"><?php _e('颜色', 'time-capsule'); ?></label>
                        <input type="color" id="color" name="color" value="#007bff">
                    </div>
                </div>

                <div class="tc-form-actions">
                    <button type="submit" class="button button-primary"><?php _e('添加类别', 'time-capsule'); ?></button>
                </div>
            </form>
        </div>

        <!-- 强制更新类别按钮 -->
        <div class="tc-force-update-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4><?php _e('类别更新工具', 'time-capsule'); ?></h4>
            <p class="tc-note"><?php _e('如果发现类别显示问题（如存在重复的"其他"类别但没有"宠物"类别），请点击下面的按钮强制更新类别。', 'time-capsule'); ?></p>

            <form method="post" action="" class="tc-force-update-form">
                <?php wp_nonce_field('wordpress_ai_toolkit_time_capsule'); ?>
                <?php wp_nonce_field('time_capsule_force_update_categories', 'tc_force_update_nonce'); ?>
                <input type="hidden" name="action" value="force_update_categories">
                <button type="submit" class="button button-secondary" onclick="return confirm('确定要强制更新类别吗？这会将现有的\"其他\"类别更新为\"宠物\"类别。')">
                    <?php _e('强制更新类别', 'time-capsule'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.tc-categories-management {
    max-width: 1000px;
}

.tc-section-header {
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.tc-section-header h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.tc-section-header p {
    margin: 0;
    color: #666;
}

.tc-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* 确保网格不会产生空白区域 */
.tc-categories-grid::after {
    content: "";
    grid-column: 1 / -1;
    height: 0;
}

.tc-category-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tc-category-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.tc-category-icon {
    font-size: 24px;
    color: #2271b1;
}

.tc-category-info {
    flex: 1;
}

.tc-category-info h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.tc-category-name {
    font-size: 12px;
    color: #666;
    font-family: monospace;
}

.tc-category-status .tc-status-active {
    background: #d4edda;
    color: #155724;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.tc-category-status .tc-status-inactive {
    background: #f8d7da;
    color: #721c24;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.tc-category-fields h5 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.tc-fields-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.tc-fields-list li {
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}

.tc-fields-list li:last-child {
    border-bottom: none;
}

.tc-field-type {
    color: #666;
    font-size: 11px;
}

.tc-field-unit {
    color: #2271b1;
    font-size: 11px;
    margin-left: 5px;
}

.tc-no-fields {
    color: #666;
    font-style: italic;
    margin: 0;
    font-size: 13px;
}

.tc-category-stats {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.tc-category-stats .tc-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.tc-category-stats .tc-stat-number {
    font-size: 18px;
    font-weight: bold;
    color: #2271b1;
}

.tc-category-stats .tc-stat-label {
    font-size: 12px;
    color: #666;
}

.tc-category-actions {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e5e5e5;
}

.tc-note {
    margin: 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

/* 添加类别表单样式 */
.tc-add-category-form {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tc-add-category-form h4 {
    margin: 0 0 20px 0;
    color: #333;
    font-size: 16px;
}

.tc-category-form {
    max-width: 100%;
}

.tc-form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.tc-form-group {
    flex: 1;
}

.tc-form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.tc-form-group input,
.tc-form-group select,
.tc-form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.tc-form-group input:focus,
.tc-form-group select:focus,
.tc-form-group textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.tc-form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.tc-form-group .description {
    margin: 5px 0 0 0;
    font-size: 12px;
    color: #666;
}

.tc-form-actions {
    margin-top: 20px;
    text-align: right;
}

.tc-delete-category-form {
    display: inline-block;
}

.button-link-delete {
    color: #d63638;
    text-decoration: none;
}

.button-link-delete:hover {
    color: #b32d2e;
    text-decoration: underline;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .tc-form-row {
        flex-direction: column;
        gap: 15px;
    }

    .tc-add-category-form {
        padding: 15px;
    }
}
</style>

