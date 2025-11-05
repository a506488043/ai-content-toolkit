<?php
/**
 * WordPress Toolkit 模块基础抽象类
 * 提供模块通用功能，减少代码重复
 *
 * @since 1.1.0
 * @author WordPress Toolkit Team
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WordPress_Toolkit_Module_Base {

    /**
     * 模块名称
     */
    protected $module_name;

    /**
     * 模块版本
     */
    protected $module_version;

    /**
     * 选项名称
     */
    protected $option_name;

    /**
     * 模块能力要求
     */
    protected $required_capability = 'manage_options';

    /**
     * 构造函数
     */
    public function __construct() {
        $this->init_module_properties();
        $this->init();
    }

    /**
     * 初始化模块属性（子类必须实现）
     */
    abstract protected function init_module_properties();

    /**
     * 获取模块信息（子类必须实现）
     */
    abstract public function get_module_info();

    /**
     * 获取默认设置（子类必须实现）
     */
    abstract public function get_default_settings();

    /**
     * 注册短代码（子类实现）
     */
    abstract public function register_shortcodes();

    /**
     * 注册AJAX处理器（子类实现）
     */
    abstract public function register_ajax_handlers();

    /**
     * 初始化模块
     */
    protected function init() {
        // 注册短代码
        $this->register_shortcodes();

        // 注册AJAX处理器
        $this->register_ajax_handlers();

        // 注册WordPress钩子
        add_action('init', array($this, 'register_hooks'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));

        // 注册激活和停用钩子
        register_activation_hook(WORDPRESS_TOOLKIT_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WORDPRESS_TOOLKIT_PLUGIN_FILE, array($this, 'deactivate'));
    }

    /**
     * 注册WordPress钩子
     */
    public function register_hooks() {
        // 子类可以重写此方法添加特定钩子
    }

    /**
     * 模块激活
     */
    public function activate() {
        // 记录激活时间
        update_option("{$this->option_name}_activated_time", current_time('mysql'));

        // 创建数据表（如果需要）
        $this->create_tables();

        // 设置默认选项
        $default_settings = $this->get_default_settings();
        if (!get_option($this->option_name)) {
            update_option($this->option_name, $default_settings);
        }

        // 记录激活日志
        if (function_exists('wt_log_info')) {
            wt_log_info("Module {$this->module_name} activated", 'module-activation');
        }
    }

    /**
     * 模块停用
     */
    public function deactivate() {
        // 清理定时任务
        wp_clear_scheduled_hook("{$this->module_name}_cron");

        // 清理缓存
        $this->clear_cache();

        // 记录停用日志
        if (function_exists('wt_log_info')) {
            wt_log_info("Module {$this->module_name} deactivated", 'module-deactivation');
        }
    }

    /**
     * 创建数据表（子类可重写）
     */
    protected function create_tables() {
        // 子类可以重写此方法创建特定数据表
    }

    /**
     * 清理缓存（子类可重写）
     */
    protected function clear_cache() {
        // 清理模块相关的transients
        $transients = $this->get_module_transients();
        foreach ($transients as $transient) {
            delete_transient($transient);
        }

        // 清理对象缓存
        wp_cache_flush();
    }

    /**
     * 获取模块相关的transients（子类可重写）
     */
    protected function get_module_transients() {
        return array(
            "{$this->module_name}_cache",
            "{$this->module_name}_data",
            "{$this->module_name}_stats"
        );
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-toolkit',
            $this->get_module_info()['name'],
            $this->get_module_info()['menu_name'],
            $this->required_capability,
            "wordpress-toolkit-{$this->module_name}",
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        $this->verify_admin_access();

        // 处理表单提交
        if (isset($_POST['save_settings'])) {
            $this->handle_settings_save();
        }

        // 渲染页面
        $this->render_page_header();
        $this->render_page_content();
        $this->render_page_footer();
    }

    /**
     * 验证管理访问权限
     */
    protected function verify_admin_access() {
        if (!current_user_can($this->required_capability)) {
            wp_die('权限不足');
        }

        // 验证nonce
        if (isset($_POST['save_settings']) && !wp_verify_nonce($_POST['_wpnonce'], $this->option_name)) {
            wp_die('安全验证失败');
        }
    }

    /**
     * 处理设置保存
     */
    protected function handle_settings_save() {
        // 使用安全工具类验证数据
        if (class_exists('WordPress_Toolkit_Security')) {
            $validation_rules = $this->get_validation_rules();
            $result = WordPress_Toolkit_Security::validate_and_sanitize_input($_POST[$this->option_name], $validation_rules);

            if (!empty($result['errors'])) {
                $this->add_admin_notice('设置保存失败：' . implode(', ', $result['errors']), 'error');
                return;
            }

            $sanitized_data = $result['data'];
        } else {
            // 回退方法
            $sanitized_data = $this->sanitize_settings($_POST[$this->option_name] ?? array());
        }

        update_option($this->option_name, $sanitized_data);
        $this->add_admin_notice('设置保存成功！', 'success');
    }

    /**
     * 获取验证规则（子类可重写）
     */
    protected function get_validation_rules() {
        return array();
    }

    /**
     * 清理设置数据（子类可重写）
     */
    protected function sanitize_settings($raw_settings) {
        return array_map('sanitize_text_field', $raw_settings);
    }

    /**
     * 添加管理通知
     */
    protected function add_admin_notice($message, $type = 'success') {
        add_action('admin_notices', function() use ($message, $type) {
            echo "<div class='notice notice-{$type} is-dismissible'><p>{$message}</p></div>";
        });
    }

    /**
     * 渲染页面头部
     */
    protected function render_page_header() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($this->get_module_info()['name']) . '</h1>';
    }

    /**
     * 渲染页面内容（子类必须实现）
     */
    abstract protected function render_page_content();

    /**
     * 渲染页面底部
     */
    protected function render_page_footer() {
        echo '</div>';
    }

    /**
     * 注册WordPress设置
     */
    public function register_settings() {
        register_setting(
            $this->option_name,
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->get_default_settings()
            )
        );
    }

    /**
     * 管理后台资源加载
     */
    public function admin_enqueue_scripts($hook) {
        if (!$this->is_module_page($hook)) {
            return;
        }

        // 加载通用样式
        wp_enqueue_style(
            'wordpress-toolkit-admin',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WORDPRESS_TOOLKIT_VERSION
        );

        // 加载模块特定样式
        $this->enqueue_admin_styles();

        // 加载通用脚本
        wp_enqueue_script(
            'wordpress-toolkit-core',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/js/toolkit-core.js',
            array('jquery'),
            WORDPRESS_TOOLKIT_VERSION,
            true
        );

        // 加载模块特定脚本
        $this->enqueue_admin_scripts();

        // 本地化脚本
        $this->localize_admin_scripts();
    }

    /**
     * 前端资源加载
     */
    public function frontend_enqueue_scripts() {
        // 子类可重写加载前端资源
    }

    /**
     * 检查是否为模块页面
     */
    protected function is_module_page($hook) {
        return strpos($hook, "wordpress-toolkit-{$this->module_name}") !== false;
    }

    /**
     * 加载管理后台样式（子类可重写）
     */
    protected function enqueue_admin_styles() {
        // 子类可以加载特定样式
    }

    /**
     * 加载管理后台脚本（子类可重写）
     */
    protected function enqueue_admin_scripts() {
        // 子类可以加载特定脚本
    }

    /**
     * 本地化管理脚本
     */
    protected function localize_admin_scripts() {
        $data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce("{$this->module_name}_nonce"),
            'moduleName' => $this->module_name,
            'strings' => $this->get_localized_strings()
        );

        wp_localize_script('wordpress-toolkit-core', 'WordPressToolkitData', $data);
    }

    /**
     * 获取本地化字符串（子类可重写）
     */
    protected function get_localized_strings() {
        return array(
            'confirmDelete' => __('确定要删除吗？', 'wordpress-toolkit'),
            'saving' => __('保存中...', 'wordpress-toolkit'),
            'saved' => __('保存成功', 'wordpress-toolkit'),
            'error' => __('操作失败', 'wordpress-toolkit')
        );
    }

    /**
     * 安全的AJAX处理器包装
     */
    protected function handle_ajax_request($action, $callback, $capability = null) {
        $capability = $capability ?: $this->required_capability;

        try {
            // 验证nonce
            WordPress_Toolkit_Security::verify_ajax_nonce($_POST['nonce'] ?? '', "{$this->module_name}_{$action}");

            // 验证权限
            WordPress_Toolkit_Security::verify_user_capability($capability);

            // 执行回调
            call_user_func($callback);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("{$this->module_name} AJAX Error: " . $e->getMessage());
            }
            wp_send_json_error(__('操作失败，请稍后重试', 'wordpress-toolkit'));
        }
    }

    /**
     * 获取模块设置
     */
    public function get_settings() {
        return wp_parse_args(
            get_option($this->option_name, array()),
            $this->get_default_settings()
        );
    }

    /**
     * 更新模块设置
     */
    public function update_settings($settings) {
        return update_option($this->option_name, $settings);
    }

    /**
     * 获取单个设置项
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * 缓存管理
     */
    protected function get_cached_data($key, $callback, $expiration = 3600) {
        $cache_key = "{$this->module_name}_{$key}";
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $data = call_user_func($callback);
        set_transient($cache_key, $data, $expiration);
        return $data;
    }

    /**
     * 清理模块缓存
     */
    protected function clear_module_cache($key = null) {
        if ($key) {
            delete_transient("{$this->module_name}_{$key}");
        } else {
            $transients = $this->get_module_transients();
            foreach ($transients as $transient) {
                delete_transient($transient);
            }
        }
    }
}