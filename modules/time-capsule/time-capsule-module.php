<?php
/**
 * Time Capsule 模块
 * 集成原Time Capsule插件的所有功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义Time Capsule模块常量
define('TIME_CAPSULE_PLUGIN_URL', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/');
define('TIME_CAPSULE_VERSION', '1.1.3');

// 加载依赖类
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-database.php';
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-item.php';
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/class-category.php';
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/includes/functions.php';

/**
 * Time Capsule 模块类
 */
class Time_Capsule_Module {
    
    /**
     * 模块版本
     */
    const MODULE_VERSION = '1.0.6';
    
    /**
     * 选项键名
     */
    private $option_name = 'wordpresstoolkit_time_capsule_options';
    
    /**
     * 数据库管理器
     */
    private $database;
    
    /**
     * 物品管理器
     */
    private $item_manager;
    
    /**
     * 类别管理器
     */
    private $category_manager;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new TimeCapsule_Database();
        $this->item_manager = new TimeCapsule_Item();
        $this->category_manager = new TimeCapsule_Category();
    }
    
    /**
     * 激活模块
     */
    public function activate() {
        // 创建数据库表
        $this->database->create_tables();

        // 插入默认类别
        $this->category_manager->insert_default_categories();

        // 强制更新类别，确保"other"类别被更新为"pets"
        if (method_exists($this->category_manager, 'force_update_categories')) {
            $this->category_manager->force_update_categories();
        }

        // 设置默认选项
        $default_options = array(
            'items_per_page' => 10,
            'module_version' => self::MODULE_VERSION
        );

        add_option($this->option_name, $default_options);

        // 刷新重写规则
        flush_rewrite_rules();

        // 设置插件激活时间
        add_option('wordpress_toolkit_time_capsule_activated_time', current_time('timestamp'));
    }
    
    /**
     * 停用模块
     */
    public function deactivate() {
        // 清理临时数据
        wp_cache_flush();

        // 清理重写规则
        flush_rewrite_rules();
    }
    
    /**
     * 初始化模块
     */
    public function init() {
        // 短代码已删除

        // 注册页面模板
        add_filter('theme_page_templates', array($this, 'add_page_template'));
        add_filter('template_include', array($this, 'load_page_template'));

        // 注册自定义路由
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_custom_route'));

        // 注册AJAX处理程序
        add_action('wp_ajax_time_capsule_save_item', array($this, 'ajax_save_item'));
        add_action('wp_ajax_nopriv_time_capsule_save_item', array($this, 'ajax_save_item'));
        add_action('wp_ajax_time_capsule_delete_item', array($this, 'ajax_delete_item'));
        add_action('wp_ajax_time_capsule_get_item_details', array($this, 'ajax_get_item_details'));
        add_action('wp_ajax_time_capsule_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_tc_get_items', array($this, 'ajax_get_items'));
        add_action('wp_ajax_nopriv_tc_get_items', array($this, 'ajax_get_items'));

        // 注册前端管理AJAX处理程序
        add_action('wp_ajax_time_capsule_frontend_save_item', array($this, 'ajax_frontend_save_item'));
        add_action('wp_ajax_time_capsule_frontend_delete_item', array($this, 'ajax_frontend_delete_item'));
        add_action('wp_ajax_time_capsule_frontend_get_item', array($this, 'ajax_frontend_get_item'));

        // 加载文本域
        load_plugin_textdomain('wordpress-toolkit', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * 加载管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 在Time Capsule相关页面加载
        if (strpos($hook, 'wordpress-toolkit-time-capsule') === false) {
            return;
        }
        
        // 加载原Time Capsule的管理样式
        wp_enqueue_style(
            'wordpress-toolkit-time-capsule-admin',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/assets/css/admin.css',
            array(),
            self::MODULE_VERSION
        );
        
        // 加载原Time Capsule的管理脚本
        wp_enqueue_script(
            'wordpress-toolkit-time-capsule-admin',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/assets/js/admin.js',
            array('jquery'),
            self::MODULE_VERSION,
            true
        );

        // 传递AJAX URL和nonce到JavaScript
        wp_localize_script('wordpress-toolkit-time-capsule-admin', 'TimeCapsuleAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('time_capsule_nonce'),
            'strings' => array(
                'saveSuccess' => __('保存成功！', 'time-capsule'),
                'saveError' => __('保存失败，请重试。', 'time-capsule'),
                'networkError' => __('网络错误，请重试。', 'time-capsule'),
                'confirmDelete' => __('确定要删除这个物品吗？此操作不可撤销。', 'time-capsule'),
                'deleteSuccess' => __('删除成功！', 'time-capsule'),
                'deleteError' => __('删除失败，请重试。', 'time-capsule')
            )
        ));

        // 加载颜色选择器
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
    
    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 加载原Time Capsule的前端样式
        wp_enqueue_style(
            'wordpress-toolkit-time-capsule',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/assets/css/style.css',
            array(),
            self::MODULE_VERSION
        );
        
        // 加载原Time Capsule的前端脚本
        wp_enqueue_script(
            'wordpress-toolkit-time-capsule-script',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/assets/js/script.js',
            array('jquery'),
            self::MODULE_VERSION,
            true
        );
        
        // 传递AJAX URL
        wp_localize_script('wordpress-toolkit-time-capsule-script', 'time_capsule_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('time_capsule_nonce'),
            'plugin_url' => WORDPRESS_TOOLKIT_PLUGIN_URL
        ));
    }
    
    /**
     * 设置页面 - 只显示基本设置
     */
    public function settings_page() {
        // 包含原Time Capsule的管理页面，强制显示设置选项卡
        include WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/admin/admin-page.php';
    }

    /**
     * 管理页面 - 保持向后兼容
     */
    public function admin_page() {
        // 包含原Time Capsule的管理页面
        include WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/admin/admin-page.php';
    }
    


    
    /**
     * 渲染表单
     */
    private function render_form() {
        if (!is_user_logged_in()) {
            return '<div class="time-capsule-login-notice">请登录后添加物品</div>';
        }
        
        $categories = $this->category_manager->get_categories();
        
        ob_start();
        include WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/time-capsule/templates/add-item-form.php';
        return ob_get_clean();
    }
    
    /**
     * 添加页面模板
     */
    public function add_page_template($templates) {
        $templates['time-capsule-page.php'] = '物品管理页面';
        return $templates;
    }
    
    /**
     * 加载页面模板
     */
    public function load_page_template($template) {
        if (get_page_template_slug() === 'time-capsule-page.php') {
            $template = WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/templates/page-time-capsule.php';
        }
        return $template;
    }
    
    /**
     * 添加重写规则
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^time-capsule/?$',
            'index.php?time_capsule_page=1',
            'top'
        );
        
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * 添加查询变量
     */
    public function add_query_vars($vars) {
        $vars[] = 'time_capsule_page';
        return $vars;
    }
    
    /**
     * 处理自定义路由
     */
    public function handle_custom_route() {
        if (get_query_var('time_capsule_page')) {
            // 检查登录状态
            if (!is_user_logged_in()) {
                auth_redirect();
                exit;
            }

            // 检查用户权限 - 允许所有登录用户访问自己的物品管理页面
            if (!is_user_logged_in()) {
                auth_redirect();
                exit;
            }

            // 确保WordPress完全初始化
            if (!did_action('wp_loaded')) {
                return; // 让WordPress继续正常流程
            }

            // 加载自定义页面模板
            include WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/templates/custom-page-main.php';
            exit;
        }
    }
    
    /**
     * AJAX处理 - 保存物品
     */
    public function ajax_save_item() {
        // 记录AJAX请求开始
        wt_log_info('=== AJAX SAVE ITEM REQUEST START ===', 'time-capsule-ajax', array(
            'post_data' => $_POST,
            'user_id' => get_current_user_id(),
            'user_can_edit' => current_user_can('manage_options'),
            'is_user_logged_in' => is_user_logged_in(),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'http_referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
        ));

        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_nonce')) {
            wt_log_error('Nonce verification failed', 'time-capsule-ajax', array(
                'received_nonce' => $_POST['nonce'],
                'expected_nonce' => wp_create_nonce('time_capsule_nonce'),
                'nonce_action' => 'time_capsule_nonce',
                'post_keys' => array_keys($_POST)
            ));
            wp_send_json_error('安全验证失败');
        }

        // 检查权限 - 允许所有登录用户管理自己的物品，管理员可以管理所有物品
        if (!is_user_logged_in()) {
            wt_log_error('User not logged in', 'time-capsule-ajax', array(
                'current_user' => get_current_user_id(),
                'user_roles' => wp_get_current_user()->roles
            ));
            wp_send_json_error('请先登录');
        }

        try {
            // 记录原始POST数据
            wt_log_info('Raw POST data received', 'time-capsule-ajax', array(
                'post_keys' => array_keys($_POST),
                'post_values' => array_map(function($value) {
                    return is_string($value) ? substr($value, 0, 100) : $value;
                }, $_POST)
            ));

            $item_data = array(
                'name' => sanitize_text_field($_POST['name']),
                'category' => sanitize_text_field($_POST['category']),
                'description' => wp_kses_post($_POST['description']),
                'purchase_date' => sanitize_text_field($_POST['purchase_date']),
                'purchase_source' => sanitize_text_field($_POST['purchase_source']),
                'warranty_period' => $_POST['warranty_period'], // 让item类根据类别处理
                'price' => floatval($_POST['price']),
                'brand' => sanitize_text_field($_POST['brand']),
                'model' => sanitize_text_field($_POST['model']),
                'serial_number' => sanitize_text_field($_POST['serial_number']),
                'notes' => wp_kses_post($_POST['notes']),
                'status' => sanitize_text_field($_POST['status']),
                // 证书资质特有字段
                'used_time_hours' => floatval($_POST['used_time_hours']),
                'total_mileage' => floatval($_POST['total_mileage']),
                'shelf_life' => isset($_POST['shelf_life']) ? intval($_POST['shelf_life']) : 0,
                'issue_date' => sanitize_text_field($_POST['issue_date']),
                'issuing_authority' => sanitize_text_field($_POST['issuing_authority']),
                'certificate_number' => sanitize_text_field($_POST['certificate_number']),
                'certificate_level' => sanitize_text_field($_POST['certificate_level']),
                'renewal_period' => intval($_POST['renewal_period']),
                'renewal_unit' => sanitize_text_field($_POST['renewal_unit']),
                'holding_duration' => intval($_POST['holding_duration']),
                'renewal_date' => sanitize_text_field($_POST['renewal_date']),
                'training_fee' => floatval($_POST['training_fee']),
                'renewal_fee' => floatval($_POST['renewal_fee']),
                'reminder_days' => intval($_POST['reminder_days']),
                'certificate_status' => sanitize_text_field($_POST['certificate_status'])
            );

            // 检查是否有编辑ID
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $item_data['id'] = intval($_POST['id']);

                // 如果是编辑模式，检查用户是否有权限编辑该物品
                $existing_item = $this->item_manager->get_item($item_data['id']);
                if (!$existing_item) {
                    wp_send_json_error('物品不存在');
                }

                // 非管理员只能编辑自己的物品
                if (!current_user_can('manage_options') && $existing_item->user_id != get_current_user_id()) {
                    wp_send_json_error('您没有权限编辑此物品');
                }

                wt_log_info('Editing existing item', 'time-capsule-ajax', array('item_id' => $item_data['id']));
            } else {
                wt_log_info('Creating new item', 'time-capsule-ajax');

                // 新增物品时，设置user_id为当前用户（非管理员）
                if (!current_user_can('manage_options')) {
                    $item_data['user_id'] = get_current_user_id();
                }
            }

            wt_log_info('Item data prepared for saving', 'time-capsule-ajax', array(
                'item_data' => $item_data,
                'item_data_keys' => array_keys($item_data),
                'item_data_types' => array_map('gettype', $item_data)
            ));

            // 保存数据到数据库（save方法内部会验证数据）
            wt_log_info('Calling item_manager->save()', 'time-capsule-ajax');
            $save_result = $this->item_manager->save($item_data);

            wt_log_info('Save result received', 'time-capsule-ajax', array(
                'save_result' => $save_result,
                'save_success' => $save_result['success'] ?? false,
                'save_message' => $save_result['message'] ?? 'No message'
            ));

            if ($save_result['success']) {
                // 返回成功响应
                wt_log_info('=== ITEM SAVED SUCCESSFULLY ===', 'time-capsule-ajax', array(
                    'item_id' => $save_result['data']->id ?? 'unknown',
                    'message' => $save_result['message'] ?? 'Success'
                ));
                wp_send_json_success($save_result);
            } else {
                // 返回失败响应
                wt_log_error('=== ITEM SAVE FAILED ===', 'time-capsule-ajax', array(
                    'error_message' => $save_result['message'] ?? 'Unknown error',
                    'save_result' => $save_result
                ));
                wp_send_json_error($save_result);
            }

        } catch (Exception $e) {
            wt_log_error('=== EXCEPTION IN AJAX_SAVE_ITEM ===', 'time-capsule-ajax', array(
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            wp_send_json_error('保存过程中发生错误: ' . $e->getMessage());
        }
    }

    /**
     * AJAX处理 - 删除物品
     */
    public function ajax_delete_item() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_nonce')) {
            wp_send_json_error('安全验证失败');
        }

        // 检查权限 - 允许所有登录用户管理自己的物品，管理员可以管理所有物品
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }

        try {
            $item_id = intval($_POST['item_id']);

            if ($item_id <= 0) {
                wp_send_json_error('无效的物品ID');
            }

            // 检查物品是否存在以及用户权限
            $existing_item = $this->item_manager->get_item($item_id);
            if (!$existing_item) {
                wp_send_json_error('物品不存在');
            }

            // 非管理员只能删除自己的物品
            if (!current_user_can('manage_options') && $existing_item->user_id != get_current_user_id()) {
                wp_send_json_error('您没有权限删除此物品');
            }

            // 删除物品
            $delete_result = $this->item_manager->delete($item_id);

            if ($delete_result['success']) {
                wp_send_json_success($delete_result);
            } else {
                wp_send_json_error($delete_result);
            }

        } catch (Exception $e) {
            wt_log_error('Error in ajax_delete_item', 'time-capsule', array('exception' => $e->getMessage()));
            wp_send_json_error('删除过程中发生错误');
        }
    }

    /**
     * AJAX处理 - 获取物品详情
     */
    public function ajax_get_item_details() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_nonce')) {
            wp_send_json_error('安全验证失败');
        }

        // 检查权限 - 允许所有登录用户管理自己的物品，管理员可以管理所有物品
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }

        try {
            $item_id = intval($_POST['item_id']);

            if ($item_id <= 0) {
                wp_send_json_error('无效的物品ID');
            }

            // 获取物品详情
            $item = $this->item_manager->get_item($item_id);

            if (!$item) {
                wp_send_json_error('物品不存在');
            }

            // 非管理员只能查看自己的物品
            if (!current_user_can('manage_options') && $item->user_id != get_current_user_id()) {
                wp_send_json_error('您没有权限查看此物品');
            }

            wp_send_json_success($item);

        } catch (Exception $e) {
            wt_log_error('Error in ajax_get_item_details', 'time-capsule', array('exception' => $e->getMessage()));
            wp_send_json_error('获取物品详情过程中发生错误');
        }
    }

    /**
     * AJAX处理 - 导出数据
     */
    public function ajax_export_data() {
        // 验证nonce
        if (!wp_verify_nonce($_GET['nonce'], 'time_capsule_nonce')) {
            wp_die('安全验证失败');
        }

        // 检查权限 - 只有管理员可以导出数据
        if (!current_user_can('manage_options')) {
            wp_die('抱歉，您没有权限执行此操作');
        }

        try {
            $format = sanitize_text_field($_GET['format']);

            if (!in_array($format, ['csv', 'json'])) {
                wp_die('不支持的导出格式');
            }

            // 导出数据
            $export_data = $this->item_manager->export_data($format);

            // 设置响应头
            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="time-capsule-export.csv"');
                echo $export_data;
            } else {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="time-capsule-export.json"');
                echo $export_data;
            }

            exit;

        } catch (Exception $e) {
            wt_log_error('Error in ajax_export_data', 'time-capsule', array('exception' => $e->getMessage()));
            wp_die('导出过程中发生错误');
        }
    }

    /**
     * 强制重置数据库表
     */
    public function force_reset_database() {
        wt_log_info('Force resetting database tables', 'time-capsule');
        $result = $this->database->force_reset_tables();

        if ($result) {
            // 重新插入默认类别
            $this->category_manager->insert_default_categories();
            wt_log_info('Database reset completed successfully', 'time-capsule');
        } else {
            wt_log_error('Database reset failed', 'time-capsule');
        }

        return $result;
    }

    /**
     * AJAX处理 - 获取物品列表
     */
    public function ajax_get_items() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_nonce')) {
            wp_send_json_error('安全验证失败');
        }

        try {
            $args = array();

            // 处理查询参数
            if (!empty($_POST['category'])) {
                $args['category'] = sanitize_text_field($_POST['category']);
            }

            if (!empty($_POST['search'])) {
                $args['search'] = sanitize_text_field($_POST['search']);
            }

            if (!empty($_POST['page'])) {
                $args['page'] = intval($_POST['page']);
            }

            if (!empty($_POST['limit'])) {
                $args['limit'] = intval($_POST['limit']);
            }

            // 前端所有用户都只显示自己的物品
            $args['user_id'] = get_current_user_id();

            // 获取物品列表
            $items = $this->item_manager->get_items($args);

            wp_send_json_success($items);

        } catch (Exception $e) {
            wt_log_error('Error in ajax_get_items', 'time-capsule', array('exception' => $e->getMessage()));
            wp_send_json_error('获取物品列表过程中发生错误');
        }
    }

    /**
     * AJAX处理 - 前端保存物品
     */
    public function ajax_frontend_save_item() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_frontend_nonce')) {
            wp_send_json_error('安全验证失败');
        }

        // 检查权限 - 允许所有登录用户管理自己的物品
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }

        try {
            $item_data = array(
                'name' => sanitize_text_field($_POST['name']),
                'category' => sanitize_text_field($_POST['category']),
                'description' => wp_kses_post($_POST['description']),
                'purchase_date' => sanitize_text_field($_POST['purchase_date']),
                'purchase_source' => sanitize_text_field($_POST['purchase_source']),
                'warranty_period' => intval($_POST['warranty_period']),
                'price' => floatval($_POST['price']),
                'brand' => sanitize_text_field($_POST['brand']),
                'model' => sanitize_text_field($_POST['model']),
                'serial_number' => sanitize_text_field($_POST['serial_number']),
                'notes' => wp_kses_post($_POST['notes']),
                'status' => sanitize_text_field($_POST['status']),
                'used_time_hours' => intval($_POST['used_time_hours']),
                'total_mileage' => floatval($_POST['total_mileage']),
                'shelf_life' => intval($_POST['shelf_life']),
                'user_id' => get_current_user_id()
            );

            // 检查是否有编辑ID
            if (isset($_POST['item_id']) && !empty($_POST['item_id'])) {
                $item_data['id'] = intval($_POST['item_id']);
            }

            // 保存数据到数据库
            $save_result = $this->item_manager->save($item_data);

            if ($save_result['success']) {
                wp_send_json_success(array(
                    'message' => isset($item_data['id']) ? '物品更新成功' : '物品添加成功',
                    'data' => $save_result['data']
                ));
            } else {
                wp_send_json_error($save_result);
            }

        } catch (Exception $e) {
            wt_log_error('Error in ajax_frontend_save_item', 'time-capsule', array('exception' => $e->getMessage()));
            wp_send_json_error('保存过程中发生错误: ' . $e->getMessage());
        }
    }

    /**
     * AJAX处理 - 前端删除物品
     */
    public function ajax_frontend_delete_item() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_frontend_nonce')) {
            wp_send_json_error('安全验证失败');
        }

        // 检查权限 - 允许所有登录用户管理自己的物品
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }

        try {
            $item_id = intval($_POST['item_id']);

            if ($item_id <= 0) {
                wp_send_json_error('无效的物品ID');
            }

            // 检查用户权限 - 只能删除自己的物品
            $item = $this->item_manager->get_item($item_id);
            if (!$item || $item->user_id != get_current_user_id()) {
                wp_send_json_error('您没有权限删除此物品');
            }

            // 删除物品
            $delete_result = $this->item_manager->delete($item_id);

            if ($delete_result['success']) {
                wp_send_json_success(array(
                    'message' => '物品删除成功'
                ));
            } else {
                wp_send_json_error($delete_result);
            }

        } catch (Exception $e) {
            wt_log_error('Error in ajax_frontend_delete_item', 'time-capsule', array('exception' => $e->getMessage()));
            wp_send_json_error('删除过程中发生错误');
        }
    }

    /**
     * AJAX处理 - 前端获取物品详情
     */
    public function ajax_frontend_get_item() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'time_capsule_frontend_nonce')) {
            wp_send_json_error('安全验证失败');
        }

        // 检查权限 - 允许所有登录用户管理自己的物品
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }

        try {
            $item_id = intval($_POST['item_id']);

            if ($item_id <= 0) {
                wp_send_json_error('无效的物品ID');
            }

            // 获取物品详情
            $item = $this->item_manager->get_item($item_id);

            // 检查用户权限 - 只能查看自己的物品
            if (!$item || $item->user_id != get_current_user_id()) {
                wp_send_json_error('您没有权限查看此物品');
            }

            if ($item) {
                wp_send_json_success($item);
            } else {
                wp_send_json_error('物品不存在');
            }

        } catch (Exception $e) {
            wt_log_error('Error in ajax_frontend_get_item', 'time-capsule', array('exception' => $e->getMessage()));
            wp_send_json_error('获取物品详情过程中发生错误');
        }
    }
}
