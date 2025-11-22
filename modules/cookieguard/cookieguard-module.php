<?php
/**
 * CookieGuard 模块
 * 集成原CookieGuard Pro插件的所有功能 - 修复版本
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义CookieGuard模块常量
define('COOKIEGUARD_PRO_VERSION', '1.0.3');

/**
 * CookieGuard 模块类
 */
class CookieGuard_Module {
    
    /**
     * 模块版本
     */
    const MODULE_VERSION = '1.0.3';
    
    /**
     * 选项键名
     */
    private $option_name = 'wordpress_ai_toolkit_cookieguard_options';
    
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        // 初始化钩子将在init()中设置
    }
    
    /**
     * 激活模块
     */
    public function activate() {
        // 设置默认选项
        $default_options = array(
            'notice_text' => '本网站使用Cookie来改善您的浏览体验。继续使用本网站即表示您同意我们使用Cookie。',
            'accept_button_text' => '接受',
            'decline_button_text' => '拒绝',
            'learn_more_text' => '了解更多',
            'learn_more_url' => '',
            'position' => 'bottom',
            'background_color' => '#FFFFFF',
            'text_color' => '#000000',
            'button_color' => '#007AFF',
            'button_text_color' => '#FFFFFF',
            'show_decline_button' => true,
            'cookie_expiry' => 365,
            'enable_analytics' => false,
            'enable_geo_detection' => false,
            'local_ip_as_china' => false,
            'module_version' => self::MODULE_VERSION
        );
        
        add_option($this->option_name, $default_options);
        
        // 设置插件激活时间
        add_option('wordpress_ai_toolkit_cookieguard_activated_time', current_time('timestamp'));
    }
    
    /**
     * 停用模块
     */
    public function deactivate() {
        // 清理临时数据（保留用户设置）
        wp_cache_flush();
    }
    
    /**
     * 初始化模块
     */
    public function init() {
        // 加载文本域（WordPress会自动加载托管在wordpress.org的插件）
        // load_plugin_textdomain() 在WordPress 4.6+中已不推荐使用
        
        // 检查版本更新
        $this->check_version_update();
        
        // 初始化钩子
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // WordPress初始化钩子
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_cookie_notice'));
        add_action('body_class', array($this, 'add_body_class'));
        
        // AJAX钩子
        add_action('wp_ajax_wordpress_ai_toolkit_cookie_consent', array($this, 'handle_cookie_consent'));
        add_action('wp_ajax_nopriv_wordpress_ai_toolkit_cookie_consent', array($this, 'handle_cookie_consent'));
        add_action('wp_ajax_wordpress_ai_toolkit_clear_geo_cache', array($this, 'handle_clear_geo_cache'));
        
        // GDPR合规性钩子
        $this->register_gdpr_hooks();
    }
    
    /**
     * 检查版本更新
     */
    private function check_version_update() {
        $options = get_option($this->option_name);
        $current_version = isset($options['module_version']) ? $options['module_version'] : '0.0.0';
        
        if (version_compare($current_version, self::MODULE_VERSION, '<')) {
            // 执行更新操作
            $options['module_version'] = self::MODULE_VERSION;
            update_option($this->option_name, $options);
        }
    }
    
    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 检查用户是否已经做出选择
        if (!$this->should_show_notice()) {
            return;
        }
        
        wp_enqueue_style(
            'wordpress-ai-toolkit-cookieguard-style',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/cookieguard/assets/css/style.css',
            array(),
            self::MODULE_VERSION
        );
        
        wp_enqueue_script(
            'wordpress-ai-toolkit-cookieguard-script',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/cookieguard/assets/js/script.js',
            array('jquery'),
            self::MODULE_VERSION,
            true
        );
        
        // 传递AJAX URL和nonce
        wp_localize_script('wordpress-ai-toolkit-cookieguard-script', 'wordpress_ai_toolkit_cookie_consent_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wordpress_ai_toolkit_cookieguard_nonce'),
            'plugin_url' => AI_CONTENT_TOOLKIT_PLUGIN_URL,
            'version' => self::MODULE_VERSION,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
    
    /**
     * 加载管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 使用统一样式，不需要加载额外的CSS和JS
        return;
    }
    
    /**
     * 渲染Cookie通知
     */
    public function render_cookie_notice() {
        if (!$this->should_show_notice()) {
            return;
        }
        
        $options = get_option($this->option_name);
        include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/cookieguard/includes/cookie-notice-template.php';
    }
    
    /**
     * 添加body class
     */
    public function add_body_class($classes) {
        $options = get_option($this->option_name);
        
        // 添加地理位置检测状态class
        if (isset($options['enable_geo_detection']) && $options['enable_geo_detection']) {
            $classes[] = 'wordpress-ai-toolkit-cookieguard-geo-enabled';
        } else {
            $classes[] = 'wordpress-ai-toolkit-cookieguard-geo-disabled';
        }
        
        // 添加Cookie同意状态class
        if (isset($_COOKIE['wordpress_ai_toolkit_cookieguard_consent'])) {
            $classes[] = 'wordpress-ai-toolkit-cookieguard-consent-' . sanitize_html_class($_COOKIE['wordpress_ai_toolkit_cookieguard_consent']);
        } else {
            $classes[] = 'wordpress-ai-toolkit-cookieguard-consent-none';
        }
        
        return $classes;
    }
    
    /**
     * 检查是否应该显示通知
     */
    private function should_show_notice() {
        // 检查Cookie是否已设置
        if (isset($_COOKIE['wordpress_ai_toolkit_cookieguard_consent'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wt_log_debug('Cookie found, not showing notice', 'cookieguard', array('cookie_value' => $_COOKIE['wordpress_ai_toolkit_cookieguard_consent']));
            }
            return false;
        }
        
        // 检查是否在管理后台
        if (is_admin()) {
            return false;
        }
        
        // 检查是否在登录页面
        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'))) {
            return false;
        }
        
        // 检查地理位置设置
        $options = get_option($this->option_name);
        if (isset($options['enable_geo_detection']) && $options['enable_geo_detection']) {
            // 如果启用了地理检测且用户在中国，则不显示通知
            $is_in_china = $this->is_user_in_china();
            
            // 调试信息（仅在WP_DEBUG开启时显示）
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $user_ip = $this->get_user_ip();
                $cache_key = 'wordpress_ai_toolkit_cookieguard_geo_' . md5($user_ip);
                $cached_result = get_transient($cache_key);
                
                wt_log_debug('IP地理位置检测结果', 'cookieguard', array(
                    'user_ip' => $user_ip,
                    'cached_result' => $cached_result,
                    'in_china' => $is_in_china ? 'Yes' : 'No'
                ));
            }
            
            if ($is_in_china) {
                return false;
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug('No cookie found, showing notice', 'cookieguard');
        }
        
        return true;
    }
    
    /**
     * 处理Cookie同意AJAX请求 - 修复版本
     */
    public function handle_cookie_consent() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wordpress_ai_toolkit_cookieguard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $consent = isset($_POST['consent']) ? sanitize_text_field($_POST['consent']) : '';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug('Handling consent', 'cookieguard', array('consent' => $consent));
        }
        
        if (in_array($consent, array('accept', 'decline', 'accepted', 'declined'))) {
            $options = get_option($this->option_name);
            $expiry = isset($options['cookie_expiry']) ? intval($options['cookie_expiry']) : 365;
            
            // 修复：使用安全的Cookie设置参数
            $cookie_result = setcookie(
                'wordpress_ai_toolkit_cookieguard_consent',
                $consent,
                array(
                    'expires' => time() + ($expiry * 24 * 60 * 60),
                    'path' => '/',
                    'domain' => '',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wt_log_debug('Cookie set result', 'cookieguard', array('result' => $cookie_result ? 'success' : 'failed'));
            }
            
            // 如果启用分析，记录用户选择
            if (isset($options['enable_analytics']) && $options['enable_analytics']) {
                $this->log_consent($consent);
            }
            
            // 返回正确的JSON响应
            wp_send_json_success(array(
                'consent' => $consent,
                'message' => 'Cookie consent saved successfully',
                'cookie_set' => $cookie_result,
                'debug_info' => array(
                    'cookie_value' => $consent,
                    'expiry_days' => $expiry,
                    'server_cookie' => isset($_COOKIE['wordpress_ai_toolkit_cookieguard_consent']) ? $_COOKIE['wordpress_ai_toolkit_cookieguard_consent'] : 'not set'
                )
            ));
        } else {
            wp_send_json_error(array('message' => 'Invalid consent value'));
        }
    }
    
    /**
     * 处理清除地理位置缓存AJAX请求 - 修复版本
     */
    public function handle_clear_geo_cache() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wordpress_ai_toolkit_cookieguard_nonce')) {
            wp_die('Security check failed');
        }
        
        // 使用WordPress安全的数据库API
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_wordpress_ai_toolkit_cookieguard_geo_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_wordpress_ai_toolkit_cookieguard_geo_%'
        ));
        
        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }
    
    /**
     * GDPR数据导出功能
     */
    public function export_user_data($email_address) {
        $export_data = array();
        
        // 导出Cookie同意记录
        if (isset($_COOKIE['wordpress_ai_toolkit_cookieguard_consent'])) {
            $consent_data = array(
                'group_id' => 'wordpress-ai-toolkit-cookieguard',
                'group_label' => 'Cookie同意数据',
                'item_id' => 'cookie-consent',
                'data' => array(
                    array(
                        'name' => 'Cookie同意状态',
                        'value' => sanitize_text_field($_COOKIE['wordpress_ai_toolkit_cookieguard_consent']),
                    ),
                    array(
                        'name' => '同意时间',
                        'value' => current_time('mysql'),
                    ),
                    array(
                        'name' => '数据用途',
                        'value' => '用于记录用户的Cookie使用偏好，确保符合GDPR要求',
                    ),
                )
            );
            $export_data[] = $consent_data;
        }
        
        // 导出地理位置缓存数据
        $user_ip = $this->get_user_ip();
        if (!empty($user_ip)) {
            $cache_key = 'wordpress_ai_toolkit_cookieguard_geo_' . md5($user_ip);
            $cached_result = get_transient($cache_key);
            
            if ($cached_result !== false) {
                $geo_data = array(
                    'group_id' => 'wordpress-ai-toolkit-cookieguard',
                    'group_label' => '地理位置数据',
                    'item_id' => 'geo-location',
                    'data' => array(
                        array(
                            'name' => 'IP地址',
                            'value' => $this->anonymize_ip($user_ip),
                        ),
                        array(
                            'name' => '地理位置检测结果',
                            'value' => $cached_result === 'china' ? '中国' : '其他地区',
                        ),
                        array(
                            'name' => '数据保留期限',
                            'value' => '24小时自动删除',
                        ),
                    )
                );
                $export_data[] = $geo_data;
            }
        }
        
        return $export_data;
    }
    
    /**
     * GDPR数据删除功能
     */
    public function erase_user_data($email_address) {
        $items_removed = false;
        $messages = array();
        
        // 删除Cookie同意数据
        if (isset($_COOKIE['wordpress_ai_toolkit_cookieguard_consent'])) {
            // 设置Cookie过期
            setcookie(
                'wordpress_ai_toolkit_cookieguard_consent',
                '',
                time() - 3600,
                '/',
                '',
                is_ssl(),
                true
            );
            $items_removed = true;
            $messages[] = 'Cookie同意数据已删除';
        }
        
        // 删除地理位置缓存数据
        $user_ip = $this->get_user_ip();
        if (!empty($user_ip)) {
            $cache_key = 'wordpress_ai_toolkit_cookieguard_geo_' . md5($user_ip);
            $result = delete_transient($cache_key);
            
            if ($result) {
                $items_removed = true;
                $messages[] = '地理位置缓存数据已删除';
            }
        }
        
        return array(
            'items_removed' => $items_removed,
            'items_retained' => false,
            'messages' => $messages,
            'done' => true,
        );
    }
    
    /**
     * 匿名化IP地址
     */
    private function anonymize_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: 保留前3段，最后一段设为0
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                return implode('.', $parts);
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: 保留前8段，后面设为0
            $parts = explode(':', $ip);
            if (count($parts) >= 8) {
                for ($i = 8; $i < count($parts); $i++) {
                    $parts[$i] = '0';
                }
                return implode(':', $parts);
            }
        }
        return $ip;
    }
    
    /**
     * 注册GDPR数据导出和删除钩子
     */
    public function register_gdpr_hooks() {
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_data_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_data_eraser'));
    }
    
    /**
     * 注册数据导出器
     */
    public function register_data_exporter($exporters) {
        $exporters[] = array(
            'exporter_friendly_name' => 'WordPress Toolkit CookieGuard',
            'callback' => array($this, 'export_user_data'),
        );
        return $exporters;
    }
    
    /**
     * 注册数据删除器
     */
    public function register_data_eraser($erasers) {
        $erasers[] = array(
            'eraser_friendly_name' => 'WordPress Toolkit CookieGuard',
            'callback' => array($this, 'erase_user_data'),
        );
        return $erasers;
    }
    
    /**
     * 记录用户同意选择 - 安全版本
     */
    private function log_consent($consent) {
        // 验证输入
        if (!in_array($consent, array('accept', 'decline', 'accepted', 'declined'))) {
            return;
        }
        
        // 这里可以实现分析功能，如记录到数据库或发送到分析服务
        // 为了示例，我们只是记录到WordPress日志
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_info('User consent recorded', 'cookieguard', array('consent' => $consent));
        }
    }
    
    /**
     * 获取用户IP地址 - 安全版本
     */
    private function get_user_ip() {
        $ip = '';
        
        // 优先使用REMOTE_ADDR，这是最可靠的
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        // 如果REMOTE_ADDR不可用，检查其他可能被伪造的头部
        $trusted_proxies = defined('WP_HTTP_TRUSTED_PROXIES') ? WP_HTTP_TRUSTED_PROXIES : array();
        $ip_sources = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        );
        
        foreach ($ip_sources as $source) {
            if (isset($_SERVER[$source])) {
                $potential_ip = sanitize_text_field($_SERVER[$source]);
                
                // 处理逗号分隔的IP列表（如X-Forwarded-For）
                $ips = explode(',', $potential_ip);
                $potential_ip = trim($ips[0]);
                
                // 验证IP地址格式和是否在可信代理范围内
                if (filter_var($potential_ip, FILTER_VALIDATE_IP) && 
                    !$this->is_private_ip($potential_ip) &&
                    !in_array($potential_ip, $trusted_proxies)) {
                    $ip = $potential_ip;
                    break;
                }
            }
        }
        
        return $ip;
    }
    
    /**
     * 检查是否为私有IP地址
     */
    private function is_private_ip($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        $private_ranges = array(
            '10.0.0.0/8',
            '172.16.0.0/12', 
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16'
        );
        
        foreach ($private_ranges as $range) {
            if ($this->ip_in_range($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查用户是否在中国 - 安全版本
     */
    private function is_user_in_china() {
        // 获取用户IP
        $user_ip = $this->get_user_ip();
        
        // 调试模式：如果URL参数包含force_show_notice=1，强制显示通知
        if (isset($_GET['force_show_notice']) && $_GET['force_show_notice'] == '1') {
            return false;
        }
        
        // 如果是本地IP，根据设置决定是否显示
        if ($this->is_local_ip($user_ip)) {
            $options = get_option($this->option_name);
            return isset($options['local_ip_as_china']) && $options['local_ip_as_china'];
        }
        
        // 生成缓存键
        $cache_key = 'wordpress_ai_toolkit_cookieguard_geo_' . md5($user_ip);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'china';
        }
        
        // 使用WordPress的HTTP API进行安全的地理位置查询
        $response = wp_remote_get('https://ipapi.co/' . urlencode($user_ip) . '/country/', array(
            'timeout' => 2,
            'user-agent' => 'WordPress Toolkit CookieGuard/' . self::MODULE_VERSION
        ));
        
        if (is_wp_error($response)) {
            // 如果查询失败，默认返回false（不在中国）
            set_transient($cache_key, 'other', HOUR_IN_SECONDS);
            return false;
        }
        
        $country_code = wp_remote_retrieve_body($response);
        $is_china = ($country_code === 'CN');
        
        // 缓存结果24小时
        set_transient($cache_key, $is_china ? 'china' : 'other', DAY_IN_SECONDS);
        
        return $is_china;
    }
    
    /**
     * 检查是否为本地IP地址 - 安全版本
     */
    private function is_local_ip($ip) {
        if (empty($ip)) {
            return false;
        }
        
        // 验证IP地址格式
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        // 检查私有IP范围
        $private_ranges = array(
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10'
        );
        
        foreach ($private_ranges as $range) {
            if ($this->ip_in_range($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查IP是否在指定范围内 - 安全版本
     */
    private function ip_in_range($ip, $range) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6_in_range($ip, $range);
        } else {
            return $this->ipv4_in_range($ip, $range);
        }
    }
    
    /**
     * IPv4范围检查
     */
    private function ipv4_in_range($ip, $range) {
        list($range_ip, $netmask) = explode('/', $range, 2);
        $netmask = isset($netmask) ? intval($netmask) : 32;
        
        $ip_decimal = ip2long($ip);
        $range_decimal = ip2long($range_ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~$wildcard_decimal;
        
        return ($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal);
    }
    
    /**
     * IPv6范围检查
     */
    private function ipv6_in_range($ip, $range) {
        // 简化的IPv6范围检查
        list($range_ip, $prefix) = explode('/', $range, 2);
        $prefix = isset($prefix) ? intval($prefix) : 128;
        
        $ip_bin = inet_pton($ip);
        $range_bin = inet_pton($range_ip);
        
        if ($ip_bin === false || $range_bin === false) {
            return false;
        }
        
        $mask = str_repeat(chr(0xff), floor($prefix / 8));
        if ($prefix % 8) {
            $mask .= chr(ord(str_repeat(chr(0xff), 1)) << (8 - ($prefix % 8)));
        }
        $mask = str_pad($mask, 16, chr(0x00));
        
        return ($ip_bin & $mask) === ($range_bin & $mask);
    }
    
    /**
     * 设置页面 - 只显示基本设置
     */
    public function settings_page() {
        // 包含CookieGuard的管理页面，强制显示设置选项卡
        include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/cookieguard/admin/admin-page.php';
    }

    /**
     * 管理页面 - 保持向后兼容
     */
    public function admin_page() {
        // 包含CookieGuard的管理页面
        include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/cookieguard/admin/admin-page.php';
    }
}