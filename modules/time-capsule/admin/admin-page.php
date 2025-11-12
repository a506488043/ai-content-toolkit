<?php
/**
 * 后台管理页面
 */

if (!defined('ABSPATH')) {
    exit;
}

// 确保必要的常量被定义
if (!defined('WORDPRESS_TOOLKIT_PLUGIN_PATH')) {
    define('WORDPRESS_TOOLKIT_PLUGIN_PATH', plugin_dir_path(dirname(dirname(dirname(__FILE__)))));
}

// 确保必要的类被加载
if (!class_exists('TimeCapsule_Item')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-item.php';
}
if (!class_exists('TimeCapsule_Category')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-category.php';
}
if (!class_exists('TimeCapsule_Database')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-database.php';
}

// 确保函数文件被加载
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/functions.php';

// 确保脚本和样式被加载
if (!class_exists('Time_Capsule_Module')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/time-capsule-module.php';
}

// 手动加载脚本和样式
$time_capsule_module = new Time_Capsule_Module();
$time_capsule_module->admin_enqueue_scripts('wordpress-toolkit-time-capsule');

// 检查用户权限 - 允许管理员和订阅者访问
if (!current_user_can('manage_options') && !current_user_can('read')) {
    status_header(403);
    wp_die('抱歉，您不能访问此页面。');
}

// 获取当前选项卡 - 订阅者只能访问物品列表和添加物品
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$allowed_tabs = $is_admin ? ['items', 'add', 'categories', 'settings'] : ['items', 'add'];

$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'items';

// 如果用户试图访问不允许的选项卡，重定向到物品列表
if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'items';
}

// 初始化类
$item_manager = new TimeCapsule_Item();
$category_manager = new TimeCapsule_Category();
$categories = $category_manager->get_categories();
$stats = $item_manager->get_stats();
?>

<div class="wrap time-capsule-admin">
    <!-- 选项卡内容 -->
    <div class="tc-tab-content">
        <?php
        switch ($current_tab) {
            case 'add':
                include 'tabs/add-item.php';
                break;
            case 'categories':
                include 'tabs/categories.php';
                break;
            case 'settings':
                include 'tabs/settings.php';
                break;
            case 'items':
            default:
                include 'tabs/items-list.php';
                break;
        }
        ?>
    </div>
</div>

<!-- 隐藏的nonce字段 -->
<input type="hidden" id="tc_nonce" name="tc_nonce" value="<?php echo wp_create_nonce('time_capsule_nonce'); ?>">

<!-- 模态框 -->
<div id="tc-modal" class="tc-modal" style="display: none;">
    <div class="tc-modal-content">
        <div class="tc-modal-header">
            <h3 id="tc-modal-title"></h3>
            <span class="tc-modal-close">&times;</span>
        </div>
        <div class="tc-modal-body" id="tc-modal-body">
            <!-- 动态内容 -->
        </div>
        <div class="tc-modal-footer">
            <button type="button" class="button" id="tc-modal-cancel"><?php _e('取消', 'time-capsule'); ?></button>
            <button type="button" class="button-primary" id="tc-modal-save"><?php _e('保存', 'time-capsule'); ?></button>
        </div>
    </div>
</div>

