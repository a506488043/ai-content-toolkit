<?php
/**
 * Category Optimization Page Template
 * 分类优化页面模板 - 重定向到主管理页面
 *
 * @package WordPressToolkit
 * @subpackage CategoryOptimization
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 重定向到主管理页面
$admin_url = admin_url('admin.php?page=wordpress-toolkit-category-optimization');
wp_redirect($admin_url);
exit;