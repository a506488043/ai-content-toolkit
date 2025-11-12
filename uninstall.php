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

// 定义允许删除的表名白名单
$allowed_drop_tables = array(
    $wpdb->prefix . 'chf_card_cache',
    $wpdb->prefix . 'time_capsule_items',
    $wpdb->prefix . 'time_capsule_categories',
    $wpdb->prefix . 'auto_excerpt_seo_analysis'
);

// 删除插件选项
delete_option('wordpress_toolkit_custom_card_options');
delete_option('wordpress_toolkit_age_calculator_options');
delete_option('wordpress_toolkit_time_capsule_options');
delete_option('wordpress_toolkit_cookieguard_options');
delete_option('wordpress_toolkit_activated_time');
delete_option('wordpress_toolkit_custom_card_activated_time');
delete_option('wordpress_toolkit_time_capsule_activated_time');
delete_option('wordpress_toolkit_cookieguard_activated_time');

// 安全地删除Custom Card数据库表
if (in_array($wpdb->prefix . 'chf_card_cache', $allowed_drop_tables)) {
    $result = $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}chf_card_cache");
    if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
        wt_log_error('Failed to drop chf_card_cache table: ' . $wpdb->last_error, 'uninstall');
    }
}

// 安全地删除Time Capsule数据库表
$time_capsule_tables = array(
    $wpdb->prefix . 'time_capsule_items',
    $wpdb->prefix . 'time_capsule_categories'
);

foreach ($time_capsule_tables as $table) {
    if (in_array($table, $allowed_drop_tables)) {
        $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");
        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_error("Failed to drop table {$table}: " . $wpdb->last_error, 'uninstall');
        }
    }
}

// 安全地删除SEO分析数据库表
$seo_table = $wpdb->prefix . 'auto_excerpt_seo_analysis';
if (in_array($seo_table, $allowed_drop_tables)) {
    $result = $wpdb->query("DROP TABLE IF EXISTS {$seo_table}");
    if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
        wt_log_error("Failed to drop SEO analysis table: " . $wpdb->last_error, 'uninstall');
    }
}

// 使用prepare语句安全地删除用户元数据中的相关数据
$result = $wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
    'wordpress_toolkit_%'
));
if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
    wt_log_error('Failed to delete user meta data: ' . $wpdb->last_error, 'uninstall');
}

// 使用prepare语句安全地删除transients缓存
$transient_patterns = array(
    '_transient_wordpress_toolkit_%',
    '_transient_timeout_wordpress_toolkit_%',
    '_transient_wordpress_toolkit_cookieguard_geo_%',
    '_transient_timeout_wordpress_toolkit_cookieguard_geo_%',
    '_transient_chf_card_%',
    '_transient_timeout_chf_card_%'
);

foreach ($transient_patterns as $pattern) {
    $result = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $pattern
    ));
    if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
        wt_log_error("Failed to delete transients for pattern {$pattern}: " . $wpdb->last_error, 'uninstall');
    }
}

// 安全地清理post meta中的相关数据
$result = $wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
    'wordpress_toolkit_%'
));
if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
    wt_log_error('Failed to delete post meta data: ' . $wpdb->last_error, 'uninstall');
}

// 记录卸载日志
if (defined('WP_DEBUG') && WP_DEBUG) {
    wt_log_info('Plugin uninstalled successfully', 'uninstall');
}