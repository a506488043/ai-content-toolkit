<?php
/**
 * 强制更新类别脚本
 * 用于将'other'类别更新为'pets'类别
 */

// 直接加载WordPress核心文件
$wp_load_path = '/mnt/256G/docker/coder/wordpress/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    echo "无法找到WordPress环境: {$wp_load_path}\n";
    exit(1);
}

// 确保插件已激活
if (!function_exists('wp_get_current_user')) {
    echo "WordPress环境未正确加载\n";
    exit(1);
}

// 加载必要的类文件
$plugin_path = dirname(__FILE__);
$includes_path = $plugin_path . '/includes/';

if (file_exists($includes_path . 'class-category.php')) {
    require_once $includes_path . 'class-category.php';
    require_once $includes_path . 'class-database.php';
} else {
    echo "无法找到Time Capsule类文件\n";
    exit(1);
}

// 执行类别更新
echo "开始执行类别更新...\n";

try {
    $category_manager = new TimeCapsule_Category();

    // 首先检查当前类别状态
    echo "\n当前类别状态:\n";
    $categories = $category_manager->get_categories(false);
    foreach ($categories as $category) {
        echo "- {$category->name} ({$category->display_name})\n";
    }

    // 执行强制更新
    echo "\n执行类别更新...\n";
    $result = $category_manager->force_update_categories();

    if ($result) {
        echo "类别更新成功!\n";
    } else {
        echo "类别更新失败!\n";
    }

    // 再次检查更新后的类别状态
    echo "\n更新后的类别状态:\n";
    $categories = $category_manager->get_categories(false);
    foreach ($categories as $category) {
        echo "- {$category->name} ({$category->display_name})\n";
    }

    // 检查是否有重复的类别
    $category_names = array();
    foreach ($categories as $category) {
        $category_names[] = $category->name;
    }

    $duplicates = array_diff_assoc($category_names, array_unique($category_names));
    if (!empty($duplicates)) {
        echo "\n警告: 发现重复的类别: " . implode(', ', $duplicates) . "\n";
    } else {
        echo "\n类别检查完成，没有发现重复类别\n";
    }

} catch (Exception $e) {
    echo "执行过程中发生错误: " . $e->getMessage() . "\n";
}

echo "\n类别更新脚本执行完成!\n";
?>