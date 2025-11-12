<?php
/**
 * 数据库架构更新脚本
 * 用于添加缺失的SEO分析字段
 */

if (!defined('ABSPATH')) {
    // 如果直接访问，尝试加载WordPress
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        exit('WordPress not found');
    }
}

// 添加缺失的数据库字段
function update_seo_analysis_table_schema() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'auto_excerpt_seo_analysis';

    echo "正在更新SEO分析数据表架构...\n";

    // 检查表是否存在
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ));

    if (!$table_exists) {
        echo "错误: 表 {$table_name} 不存在\n";
        return false;
    }

    echo "表 {$table_name} 存在，检查字段...\n";

    // 检查raw_ai_analysis字段
    $raw_column_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME,
        $table_name,
        'raw_ai_analysis'
    ));

    if (!$raw_column_exists) {
        echo "添加 raw_ai_analysis 字段...\n";
        $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD COLUMN raw_ai_analysis longtext DEFAULT NULL COMMENT 'AI原始完整分析文本'"
        );
        echo "✅ raw_ai_analysis 字段添加成功\n";
    } else {
        echo "✅ raw_ai_analysis 字段已存在\n";
    }

    // 检查parsed_analysis字段
    $parsed_column_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME,
        $table_name,
        'parsed_analysis'
    ));

    if (!$parsed_column_exists) {
        echo "添加 parsed_analysis 字段...\n";
        $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD COLUMN parsed_analysis longtext DEFAULT NULL COMMENT '解析后的AI分析数据(JSON)'"
        );
        echo "✅ parsed_analysis 字段添加成功\n";
    } else {
        echo "✅ parsed_analysis 字段已存在\n";
    }

    // 检查ai_model字段是否为decimal类型（需要修复）
    $ai_model_type = $wpdb->get_var($wpdb->prepare(
        "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        DB_NAME,
        $table_name,
        'ai_model'
    ));

    if ($ai_model_type === 'decimal') {
        echo "修复 ai_model 字段类型...\n";
        $wpdb->query(
            "ALTER TABLE {$table_name}
             MODIFY COLUMN ai_model varchar(100) DEFAULT NULL COMMENT 'AI模型'"
        );
        echo "✅ ai_model 字段类型修复成功\n";
    } else {
        echo "✅ ai_model 字段类型正确\n";
    }

    echo "🎉 数据库架构更新完成！\n";
    return true;
}

// 验证更新结果
function verify_schema_update() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'auto_excerpt_seo_analysis';

    echo "\n验证数据库架构...\n";

    $columns = $wpdb->get_results(
        "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
        DB_NAME,
        $table_name
    );

    $required_columns = ['raw_ai_analysis', 'parsed_analysis', 'ai_model'];
    $missing_columns = [];

    foreach ($required_columns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column->COLUMN_NAME === $col) {
                $found = true;
                echo "✅ {$col}: {$column->DATA_TYPE} - {$column->COLUMN_COMMENT}\n";
                break;
            }
        }
        if (!$found) {
            $missing_columns[] = $col;
        }
    }

    if (empty($missing_columns)) {
        echo "🎉 所有必需字段都存在！\n";
        return true;
    } else {
        echo "❌ 缺失字段: " . implode(', ', $missing_columns) . "\n";
        return false;
    }
}

// 如果直接访问此文件，执行更新
if (defined('DOING_AJAX') && DOING_AJAX) {
    // 通过AJAX调用
    header('Content-Type: application/json');

    $success = update_seo_analysis_table_schema();
    $verified = $success ? verify_schema_update() : false;

    wp_send_json_success(array(
        'updated' => $success,
        'verified' => $verified,
        'message' => $success && $verified ? '数据库架构更新成功！' : '数据库架构更新失败'
    ));
} else if (basename($_SERVER['PHP_SELF']) === 'update-database-schema.php') {
    // 直接访问
    header('Content-Type: text/plain; charset=utf-8');

    update_seo_analysis_table_schema();
    verify_schema_update();
}
?>