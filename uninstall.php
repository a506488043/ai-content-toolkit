<?php
/**
 * WordPress Toolkit 卸载脚本
 * 清理插件创建的所有数据和选项
 */

// 防止直接访问
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 加载日志管理类
require_once plugin_dir_path(__FILE__) . 'includes/class-logger.php';

// 检查用户权限
if (!current_user_can('activate_plugins')) {
    return;
}

// 全局变量
global $wpdb;

// 删除插件选项
delete_option('wordpress_toolkit_custom_card_options');
delete_option('wordpress_toolkit_age_calculator_options');
delete_option('wordpress_toolkit_time_capsule_options');
delete_option('wordpress_toolkit_cookieguard_options');
delete_option('wordpress_toolkit_activated_time');
delete_option('wordpress_toolkit_custom_card_activated_time');
delete_option('wordpress_toolkit_time_capsule_activated_time');
delete_option('wordpress_toolkit_cookieguard_activated_time');

// 安全删除数据库表 - 使用白名单验证
$allowed_tables = ['chf_card_cache', 'time_capsule_items', 'time_capsule_categories'];
foreach ($allowed_tables as $table) {
    $table_name = $wpdb->prefix . $table;
    // 使用转义确保表名安全
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prepare("%i", $table_name));
}

// 安全删除用户元数据 - 使用转义和占位符
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        'wordpress_toolkit_%'
    )
);

// 安全删除transients缓存 - 使用占位符
$transient_patterns = [
    '_transient_wordpress_toolkit_%',
    '_transient_timeout_wordpress_toolkit_%',
    '_transient_wordpress_toolkit_cookieguard_geo_%',
    '_transient_timeout_wordpress_toolkit_cookieguard_geo_%',
    '_transient_chf_card_%',
    '_transient_timeout_chf_card_%'
];

foreach ($transient_patterns as $pattern) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        )
    );
}

// 安全清理post meta数据
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        'wordpress_toolkit_%'
    )
);

// 记录卸载日志
if (defined('WP_DEBUG') && WP_DEBUG) {
    wt_log_info('Plugin uninstalled successfully', 'uninstall');
}