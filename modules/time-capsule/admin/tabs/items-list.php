<?php
/**
 * 物品列表选项卡
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取筛选参数
$category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// 获取当前用户信息
$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');

// 如果不是管理员，只能查看自己的物品
// 如果是管理员，默认查看自己的物品，但可以通过URL参数切换到其他用户
$user_filter = $is_admin ? (isset($_GET['user']) ? intval($_GET['user']) : get_current_user_id()) : get_current_user_id();

// 获取物品列表
$args = array(
    'category' => $category_filter,
    'status' => $status_filter,
    'user_id' => $user_filter,
    'search' => $search,
    'limit' => $per_page,
    'offset' => ($page - 1) * $per_page
);

$items = $item_manager->get_items($args);

// 获取总数用于分页
$total_args = $args;
$total_args['limit'] = 0;
$total_items = count($item_manager->get_items($total_args));
$total_pages = ceil($total_items / $per_page);

// 获取统计信息 - 订阅者只能看到自己的统计
$user_id_for_stats = $is_admin ? null : get_current_user_id();
$stats = $item_manager->get_stats($user_id_for_stats);
?>

<?php
// 使用物品表格组件，传递必要变量
$component_vars = array(
    'items' => $items,
    'categories' => $categories,
    'stats' => $stats,
    'category_filter' => $category_filter,
    'status_filter' => $status_filter,
    'search' => $search,
    'page' => $page,
    'total_pages' => $total_pages,
    'total_items' => $total_items,
    'per_page' => $per_page,
    'is_admin' => $is_admin
);
extract($component_vars);
include_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/templates/items-table-component.php';
?>

