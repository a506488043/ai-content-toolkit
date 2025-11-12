<?php
/**
 * Tag Optimization Page Template
 * 标签优化页面模板 - 重定向到主管理页面
 *
 * @package WordPressToolkit
 * @subpackage TagOptimization
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 重定向到主管理页面
$admin_url = admin_url('admin.php?page=wordpress-toolkit-tag-optimization');
wp_redirect($admin_url);
exit;