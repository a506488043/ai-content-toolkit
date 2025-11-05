<?php
/**
 * WordPress Toolkit AJAX处理器基类
 * 提供统一的AJAX处理功能，减少重复代码
 *
 * @since 1.1.0
 * @author WordPress Toolkit Team
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WordPress_Toolkit_AJAX_Handler {

    /**
     * 模块名称
     */
    protected $module_name;

    /**
     * Nonce动作前缀
     */
    protected $nonce_prefix;

    /**
     * 权限要求
     */
    protected $required_capability = 'manage_options';

    /**
     * 支持的动作列表
     */
    protected $actions = array();

    /**
     * 构造函数
     *
     * @param string $module_name 模块名称
     */
    public function __construct($module_name) {
        $this->module_name = $module_name;
        $this->nonce_prefix = $module_name . '_';
        $this->init();
    }

    /**
     * 初始化AJAX处理器
     */
    protected function init() {
        $this->actions = $this->get_actions();
        $this->register_ajax_actions();
    }

    /**
     * 获取支持的动作列表（子类必须实现）
     */
    abstract protected function get_actions();

    /**
     * 注册AJAX动作
     */
    protected function register_ajax_actions() {
        foreach ($this->actions as $action => $config) {
            $callback = $config['callback'] ?? $action;
            $capability = $config['capability'] ?? $this->required_capability;
            $nopriv = $config['nopriv'] ?? false;

            // 注册AJAX动作
            add_action("wp_ajax_{$action}", array($this, 'handle_ajax_request'));

            if ($nopriv) {
                add_action("wp_ajax_nopriv_{$action}", array($this, 'handle_ajax_request'));
            }

            // 存储配置供后续使用
            $this->actions[$action] = array_merge($config, array(
                'callback' => $callback,
                'capability' => $capability
            ));
        }
    }

    /**
     * 处理AJAX请求
     */
    public function handle_ajax_request() {
        $action = $_REQUEST['action'] ?? '';

        if (!isset($this->actions[$action])) {
            wp_send_json_error(array(
                'message' => '无效的动作',
                'code' => 'invalid_action'
            ), 400);
        }

        $config = $this->actions[$action];
        $callback = $config['callback'];
        $capability = $config['capability'];

        try {
            // 验证请求
            $this->verify_request($action, $capability);

            // 执行回调方法
            if (method_exists($this, $callback)) {
                call_user_func(array($this, $callback));
            } else {
                throw new Exception("回调方法 {$callback} 不存在");
            }

        } catch (Exception $e) {
            $this->handle_ajax_error($e, $action);
        }
    }

    /**
     * 验证AJAX请求
     *
     * @param string $action 动作名称
     * @param string $capability 所需权限
     */
    protected function verify_request($action, $capability) {
        // 验证nonce
        $this->verify_nonce($action);

        // 验证权限
        $this->verify_capability($capability);

        // 验证请求频率（可选）
        $this->verify_rate_limit($action);
    }

    /**
     * 验证nonce
     *
     * @param string $action 动作名称
     */
    protected function verify_nonce($action) {
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        $nonce_action = $this->nonce_prefix . $action;

        if (!wp_verify_nonce($nonce, $nonce_action)) {
            // 记录安全事件
            if (class_exists('WordPress_Toolkit_Security')) {
                WordPress_Toolkit_Security::log_security_event('ajax_nonce_failed', array(
                    'action' => $action,
                    'nonce_action' => $nonce_action,
                    'user_id' => get_current_user_id(),
                    'ip' => $this->get_client_ip()
                ));
            }

            wp_send_json_error(array(
                'message' => '安全验证失败',
                'code' => 'invalid_nonce'
            ), 403);
        }
    }

    /**
     * 验证用户权限
     *
     * @param string $capability 所需权限
     */
    protected function verify_capability($capability) {
        // 如果不需要登录，直接返回
        if ($capability === 'public') {
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => '请先登录',
                'code' => 'not_logged_in'
            ), 401);
        }

        if (!current_user_can($capability)) {
            // 记录权限违规
            if (class_exists('WordPress_Toolkit_Security')) {
                WordPress_Toolkit_Security::log_security_event('ajax_insufficient_permissions', array(
                    'required_capability' => $capability,
                    'user_roles' => wp_get_current_user()->roles,
                    'user_id' => get_current_user_id(),
                    'ip' => $this->get_client_ip()
                ));
            }

            wp_send_json_error(array(
                'message' => '权限不足',
                'code' => 'insufficient_permissions'
            ), 403);
        }
    }

    /**
     * 验证请求频率限制
     *
     * @param string $action 动作名称
     */
    protected function verify_rate_limit($action) {
        // 获取限制配置
        $limits = $this->get_rate_limits();
        $limit = $limits[$action] ?? array('requests' => 60, 'window' => 60);

        if ($limit['requests'] <= 0) {
            return; // 无限制
        }

        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        $cache_key = "ajax_rate_limit_{$action}_{$user_id}_{$ip}";
        $current_requests = get_transient($cache_key) ?: 0;

        if ($current_requests >= $limit['requests']) {
            wp_send_json_error(array(
                'message' => '请求过于频繁，请稍后再试',
                'code' => 'rate_limit_exceeded',
                'retry_after' => $limit['window']
            ), 429);
        }

        // 增加请求计数
        set_transient($cache_key, $current_requests + 1, $limit['window']);
    }

    /**
     * 获取频率限制配置（子类可重写）
     */
    protected function get_rate_limits() {
        return array(
            // 默认限制：每分钟60次请求
            'default' => array('requests' => 60, 'window' => 60),

            // 保存操作：每分钟10次请求
            'save' => array('requests' => 10, 'window' => 60),

            // 删除操作：每分钟5次请求
            'delete' => array('requests' => 5, 'window' => 60),

            // 查询操作：每分钟120次请求
            'get' => array('requests' => 120, 'window' => 60)
        );
    }

    /**
     * 处理AJAX错误
     *
     * @param Exception $e 异常对象
     * @param string $action 动作名称
     */
    protected function handle_ajax_error($e, $action) {
        $error_message = $e->getMessage();

        // 记录错误日志
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("{$this->module_name} AJAX Error [{$action}]: {$error_message}");
        }

        if (class_exists('WordPress_Toolkit_Security')) {
            WordPress_Toolkit_Security::log_security_event('ajax_error', array(
                'action' => $action,
                'error' => $error_message,
                'user_id' => get_current_user_id(),
                'ip' => $this->get_client_ip()
            ));
        }

        // 根据错误类型返回不同的响应
        if (strpos($error_message, '权限') !== false) {
            wp_send_json_error(array(
                'message' => '权限不足',
                'code' => 'permission_denied'
            ), 403);
        } elseif (strpos($error_message, '验证') !== false) {
            wp_send_json_error(array(
                'message' => '数据验证失败',
                'code' => 'validation_failed'
            ), 400);
        } else {
            wp_send_json_error(array(
                'message' => defined('WP_DEBUG') && WP_DEBUG ? $error_message : '操作失败，请稍后重试',
                'code' => 'operation_failed'
            ), 500);
        }
    }

    /**
     * 验证和清理输入数据
     *
     * @param array $data 输入数据
     * @param array $rules 验证规则
     * @return array 清理后的数据
     */
    protected function validate_input($data, $rules) {
        if (class_exists('WordPress_Toolkit_Security')) {
            $result = WordPress_Toolkit_Security::validate_and_sanitize_input($data, $rules);

            if (!empty($result['errors'])) {
                wp_send_json_error(array(
                    'message' => '数据验证失败',
                    'errors' => $result['errors'],
                    'code' => 'validation_failed'
                ), 400);
            }

            return $result['data'];
        }

        // 回退方法
        return array_map('sanitize_text_field', $data);
    }

    /**
     * 发送成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     */
    protected function send_success($data = null, $message = '') {
        $response = array(
            'success' => true,
            'message' => $message
        );

        if ($data !== null) {
            $response['data'] = $data;
        }

        wp_send_json_success($response);
    }

    /**
     * 发送错误响应
     *
     * @param string $message 错误消息
     * @param string $code 错误代码
     * @param int $status_code HTTP状态码
     */
    protected function send_error($message, $code = 'error', $status_code = 400) {
        wp_send_json_error(array(
            'success' => false,
            'message' => $message,
            'code' => $code
        ), $status_code);
    }

    /**
     * 获取客户端IP地址
     */
    protected function get_client_ip() {
        $ip_keys = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * 获取当前用户ID（如果已登录）
     */
    protected function get_current_user_id() {
        return is_user_logged_in() ? get_current_user_id() : 0;
    }

    /**
     * 记录操作日志
     *
     * @param string $operation 操作类型
     * @param array $details 操作详情
     */
    protected function log_operation($operation, $details = array()) {
        if (!function_exists('wt_log_info')) {
            return;
        }

        $log_data = array_merge($details, array(
            'operation' => $operation,
            'module' => $this->module_name,
            'user_id' => $this->get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'timestamp' => current_time('mysql')
        ));

        wt_log_info("AJAX Operation: {$operation}", "{$this->module_name}-ajax", $log_data);
    }

    /**
     * 清理模块缓存
     *
     * @param string $cache_key 缓存键名
     */
    protected function clear_cache($cache_key = null) {
        if ($cache_key) {
            delete_transient("{$this->module_name}_{$cache_key}");
        } else {
            // 清理所有模块相关的transients
            global $wpdb;
            $prefix = '_transient_' . $this->module_name . '_';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $prefix . '%'
                )
            );
        }

        // 清理对象缓存
        wp_cache_flush();
    }

    /**
     * 检查用户是否可以管理特定资源
     *
     * @param int $resource_id 资源ID
     * @param string $resource_type 资源类型
     * @return bool
     */
    protected function can_manage_resource($resource_id, $resource_type = 'item') {
        // 管理员可以管理所有资源
        if (current_user_can('manage_options')) {
            return true;
        }

        // 检查资源所有权
        $current_user_id = $this->get_current_user_id();
        if (!$current_user_id) {
            return false;
        }

        // 子类可以重写此方法实现特定的权限检查逻辑
        return $this->check_resource_ownership($resource_id, $resource_type, $current_user_id);
    }

    /**
     * 检查资源所有权（子类可重写）
     *
     * @param int $resource_id 资源ID
     * @param string $resource_type 资源类型
     * @param int $user_id 用户ID
     * @return bool
     */
    protected function check_resource_ownership($resource_id, $resource_type, $user_id) {
        // 默认实现：检查时间胶囊物品的所有权
        if ($resource_type === 'time_capsule_item') {
            global $wpdb;
            $table = $wpdb->prefix . 'time_capsule_items';

            $owner_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM " . $wpdb->prepare("%i", $table) . " WHERE id = %d",
                $resource_id
            ));

            return $owner_id == $user_id;
        }

        return false;
    }
}