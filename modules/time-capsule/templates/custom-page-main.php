<?php
/**
 * 时间胶囊自定义页面主模板
 * 类似考勤查询页面的独立页面展示
 */

if (!defined('ABSPATH')) {
    exit;
}

// 确保必要的常量被定义
if (!defined('WORDPRESS_TOOLKIT_PLUGIN_PATH')) {
    define('WORDPRESS_TOOLKIT_PLUGIN_PATH', plugin_dir_path(dirname(dirname(dirname(__FILE__)))));
}
if (!defined('WORDPRESS_TOOLKIT_PLUGIN_URL')) {
    define('WORDPRESS_TOOLKIT_PLUGIN_URL', plugin_dir_url(dirname(dirname(dirname(__FILE__)))));
}
if (!defined('TIME_CAPSULE_PLUGIN_URL')) {
    define('TIME_CAPSULE_PLUGIN_URL', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/');
}
if (!defined('TIME_CAPSULE_VERSION')) {
    define('TIME_CAPSULE_VERSION', '1.0.3');
}

// 检查用户权限
if (!is_user_logged_in()) {
    // 使用auth_redirect()而不是wp_redirect()，它会正确处理登录重定向
    auth_redirect();
    exit;
}

// 检查用户是否有访问权限 - 使用更严格的检查
if (!current_user_can('read')) {
    status_header(403);
    wp_die('抱歉，您不能访问此页面。');
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
// 加载通用函数
if (!function_exists('tc_get_default_avatar')) {
    require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/functions.php';
}

// 初始化类
$item_manager = new TimeCapsule_Item();
$category_manager = new TimeCapsule_Category();
$categories = $category_manager->get_categories();

// 前端所有用户都只获取自己的统计信息
$stats = $item_manager->get_stats(get_current_user_id());

// 加载插件样式和脚本
wp_enqueue_style('time-capsule-custom-page', TIME_CAPSULE_PLUGIN_URL . 'assets/css/custom-page.css', array(), TIME_CAPSULE_VERSION);
wp_enqueue_script('time-capsule-custom-page', TIME_CAPSULE_PLUGIN_URL . 'assets/js/custom-page.js', array('jquery'), TIME_CAPSULE_VERSION, true);

// 本地化脚本
wp_localize_script('time-capsule-custom-page', 'timeCapsuleCustom', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('time_capsule_nonce'),
    'isAdmin' => current_user_can('manage_options'),
    'currentUserId' => get_current_user_id(),
    'strings' => array(
        'confirmDelete' => __('确定要删除这个物品吗？', 'time-capsule'),
        'saveSuccess' => __('保存成功！', 'time-capsule'),
        'saveError' => __('保存失败，请重试。', 'time-capsule'),
        'loading' => __('加载中...', 'time-capsule')
    )
));
?>

<div class="tc-custom-page-container">


   
    <!-- 主要内容区域 -->
    <div class="tc-main-content">
        <!-- 左侧导航 -->
        <div class="tc-sidebar">
            <div class="tc-nav-menu">
                <div class="tc-nav-section">
                    <h3 class="tc-nav-title">物品管理</h3>
                    <ul class="tc-nav-list">
                        <li class="tc-nav-item active">
                            <a href="#" class="tc-nav-link" data-tab="overview">
                                <span class="tc-nav-icon">🏠</span>
                                概览
                            </a>
                        </li>
                        <li class="tc-nav-item">
                            <a href="#" class="tc-nav-link" data-tab="items">
                                <span class="tc-nav-icon">📋</span>
                                物品列表
                            </a>
                        </li>
                        <li class="tc-nav-item">
                            <a href="#" class="tc-nav-link" data-tab="add">
                                <span class="tc-nav-icon">➕</span>
                                添加物品
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="tc-nav-section">
                    <div class="tc-nav-section-header">
                        <h3 class="tc-nav-title">分类筛选</h3>
                        <?php if (current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items')); ?>"
                           class="tc-manage-btn" title="管理物品">
                            <span class="dashicons dashicons-admin-tools"></span>
                            管理
                        </a>
                        <?php endif; ?>
                    </div>
                    <ul class="tc-nav-list">
                        <li class="tc-nav-item">
                            <div class="tc-nav-item-content">
                                <a href="#" class="tc-nav-link tc-category-filter active" data-category="">
                                    <span class="tc-nav-icon">🔍</span>
                                    全部
                                </a>
                                <?php if (is_user_logged_in()): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items')); ?>"
                                   class="tc-inline-manage-btn" title="后台管理">
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    管理
                                </a>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php foreach ($categories as $category): ?>
                        <li class="tc-nav-item">
                            <a href="#" class="tc-nav-link tc-category-filter" data-category="<?php echo esc_attr($category->name); ?>">
                                <span class="tc-nav-icon"><?php echo tc_get_default_avatar($category->name); ?></span>
                                <?php echo esc_html($category->display_name); ?>
                                <span class="tc-nav-count"><?php echo isset($stats['by_category'][$category->name]) ? $stats['by_category'][$category->name] : 0; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 右侧内容区域 -->
        <div class="tc-content-area">
            <!-- 概览选项卡 -->
            <div class="tc-tab-content active" id="tc-tab-overview">
                <div class="tc-content-header">
                    <h2 class="tc-content-title">概览</h2>
                    <div class="tc-content-actions">
                        <button class="tc-btn tc-btn-primary" id="tc-add-item-btn">
                            <span class="tc-btn-icon">➕</span>
                            添加物品
                        </button>
                    </div>
                </div>
                
                <!-- 快速搜索 -->
                <div class="tc-search-section">
                    <div class="tc-search-box">
                        <input type="text" id="tc-quick-search" placeholder="搜索物品名称、品牌或描述..." class="tc-search-input">
                        <button class="tc-search-btn" type="button">🔍</button>
                    </div>
                </div>
                
                <!-- 物品网格 -->
                <div class="tc-items-overview" id="tc-items-overview">
                    <!-- 动态加载内容 -->
                </div>
            </div>

            <!-- 物品列表选项卡 -->
            <div class="tc-tab-content" id="tc-tab-items">
                <div class="tc-content-header">
                    <h2 class="tc-content-title">物品列表</h2>
                    <div class="tc-content-actions">
                        <?php if (current_user_can('manage_options')): ?>
                        <button class="tc-btn tc-btn-secondary" id="tc-export-btn">
                            <span class="tc-btn-icon">📥</span>
                            导出
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 高级筛选 -->
                <div class="tc-filters-section">
                    <div class="tc-filters-row">
                        <select id="tc-status-filter" class="tc-filter-select">
                            <option value="">所有状态</option>
                            <option value="active">使用中</option>
                            <option value="inactive">闲置</option>
                            <option value="disposed">已处置</option>
                        </select>
                        
                        <select id="tc-warranty-filter" class="tc-filter-select">
                            <option value="">所有保修状态</option>
                            <option value="valid">保修中</option>
                            <option value="expiring">即将过保</option>
                            <option value="expired">已过保</option>
                            <option value="no_warranty">无保修</option>
                        </select>
                        
                        <input type="date" id="tc-date-from" class="tc-filter-input" placeholder="购买日期从">
                        <input type="date" id="tc-date-to" class="tc-filter-input" placeholder="购买日期到">
                        
                        <button class="tc-btn tc-btn-outline" id="tc-reset-filters">重置</button>
                    </div>
                </div>
                
                <!-- 物品列表容器 -->
                <div class="tc-items-list-container" id="tc-items-list">
                    <!-- 动态加载内容 -->
                </div>
            </div>

            <!-- 添加物品选项卡 -->
            <div class="tc-tab-content" id="tc-tab-add">
                <div class="tc-content-header">
                    <h2 class="tc-content-title">添加物品</h2>
                </div>
                
                <!-- 添加物品表单 -->
                <div class="tc-add-form-container">
                    <form id="tc-add-item-form" class="tc-add-form">
                        <!-- 表单内容将通过JavaScript动态加载 -->
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- 加载指示器 -->
<div id="tc-loading" class="tc-loading" style="display: none;">
    <div class="tc-loading-spinner"></div>
    <div class="tc-loading-text">加载中...</div>
</div>

