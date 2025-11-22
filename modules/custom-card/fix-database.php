<?php
/**
 * Custom Card Database Fix Script
 * 修复Custom Card模块的数据库表结构问题
 */

// 如果直接访问此文件，阻止执行
if (!defined('ABSPATH')) {
    // 尝试加载WordPress
    $wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found');
    }
}

// 检查用户权限
if (!current_user_can('activate_plugins')) {
    die('Permission denied');
}

echo "<h2>Custom Card Database Fix</h2>";

global $wpdb;

// 表名定义
$cards_table = $wpdb->prefix . 'chf_cards';
$clicks_table = $wpdb->prefix . 'chf_card_clicks';

echo "<h3>检查表状态</h3>";

// 检查卡片表
$cards_exists = $wpdb->get_var("SHOW TABLES LIKE '$cards_table'");
if ($cards_exists) {
    echo "✅ 卡片表存在: $cards_table<br>";
} else {
    echo "❌ 卡片表不存在: $cards_table<br>";
}

// 检查点击表
$clicks_exists = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'");
if ($clicks_exists) {
    echo "✅ 点击表存在: $clicks_table<br>";

    // 检查字段
    $clicks_columns = $wpdb->get_col("SHOW COLUMNS FROM $clicks_table");
    if (in_array('clicked_at', $clicks_columns)) {
        echo "✅ clicked_at 字段存在<br>";
    } else {
        echo "❌ clicked_at 字段不存在<br>";

        // 尝试添加字段
        $add_column_sql = "ALTER TABLE $clicks_table ADD COLUMN clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点击时间'";
        $result = $wpdb->query($add_column_sql);

        if ($result !== false) {
            echo "✅ 已添加 clicked_at 字段<br>";
        } else {
            echo "❌ 添加 clicked_at 字段失败<br>";
        }
    }
} else {
    echo "❌ 点击表不存在: $clicks_table<br>";
}

echo "<h3>重新创建表结构</h3>";

// 获取字符集
$charset_collate = $wpdb->get_charset_collate();

// 创建卡片表
$cards_sql = "CREATE TABLE IF NOT EXISTS $cards_table (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    url VARCHAR(512) NOT NULL COMMENT '网站URL',
    title VARCHAR(255) NOT NULL DEFAULT '' COMMENT '卡片标题',
    description TEXT NOT NULL COMMENT '描述内容',
    image VARCHAR(512) NOT NULL DEFAULT '' COMMENT '图片URL',
    icon VARCHAR(512) NOT NULL DEFAULT '' COMMENT '网站图标',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id),
    UNIQUE KEY url_unique (url(191)),
    INDEX status_index (status),
    INDEX created_at_index (created_at)
) $charset_collate";

// 创建点击表
$clicks_sql = "CREATE TABLE IF NOT EXISTS $clicks_table (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    card_id BIGINT(20) UNSIGNED NOT NULL COMMENT '卡片ID',
    clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点击时间',
    ip_address VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'IP地址',
    user_agent TEXT NOT NULL COMMENT '用户代理',
    referer VARCHAR(512) NOT NULL DEFAULT '' COMMENT '来源页面',
    PRIMARY KEY (id),
    INDEX card_id_index (card_id),
    INDEX clicked_at_index (clicked_at)
) $charset_collate";

// 加载WordPress升级函数
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// 执行表创建
echo "创建卡片表...<br>";
dbDelta($cards_sql);
echo "创建点击表...<br>";
dbDelta($clicks_sql);

// 检查数据库错误
if (!empty($wpdb->last_error)) {
    echo "❌ 数据库错误: " . $wpdb->last_error . "<br>";
} else {
    echo "✅ 表创建成功<br>";
}

echo "<h3>验证修复结果</h3>";

// 重新检查表状态
$cards_exists = $wpdb->get_var("SHOW TABLES LIKE '$cards_table'");
$clicks_exists = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'");

if ($cards_exists) {
    echo "✅ 卡片表已修复<br>";
    $cards_count = $wpdb->get_var("SELECT COUNT(*) FROM $cards_table");
    echo "   当前卡片数量: " . $cards_count . "<br>";
} else {
    echo "❌ 卡片表修复失败<br>";
}

if ($clicks_exists) {
    echo "✅ 点击表已修复<br>";
    $clicks_count = $wpdb->get_var("SELECT COUNT(*) FROM $clicks_table");
    echo "   当前点击数量: " . $clicks_count . "<br>";
} else {
    echo "❌ 点击表修复失败<br>";
}

echo "<h3>测试查询</h3>";

// 测试今日点击查询
$today_start = date('Y-m-d 00:00:00');
$test_clicks = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $clicks_table WHERE clicked_at >= %s",
    $today_start
));

if ($test_clicks !== null) {
    echo "✅ 今日点击查询测试成功，今日点击数: " . $test_clicks . "<br>";
} else {
    echo "❌ 今日点击查询测试失败<br>";
}

echo "<br><h3>修复完成！</h3>";
echo "<p><a href='" . admin_url('admin.php?page=wordpress-ai-toolkit-cards-list') . "'>返回卡片列表</a></p>";
?>