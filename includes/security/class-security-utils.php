<?php
/**
 * WordPress Toolkit 安全工具类
 * 提供统一的安全验证和防护功能
 *
 * @since 1.1.0
 * @author WordPress Toolkit Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Security {

    /**
     * 验证AJAX请求的nonce
     *
     * @param string $nonce POST中的nonce值
     * @param string $action nonce动作名称
     * @param bool $die_on_fail 验证失败时是否终止执行
     * @return bool
     */
    public static function verify_ajax_nonce($nonce, $action = null, $die_on_fail = true) {
        if (!isset($nonce) || empty($nonce)) {
            if ($die_on_fail) {
                wp_send_json_error(array(
                    'message' => '缺少安全令牌',
                    'code' => 'missing_nonce'
                ), 403);
            }
            return false;
        }

        $nonce_action = $action ? $action : 'wordpress_toolkit_nonce';

        if (!wp_verify_nonce($nonce, $nonce_action)) {
            if ($die_on_fail) {
                // 记录安全事件
                self::log_security_event('nonce_verification_failed', array(
                    'nonce_action' => $nonce_action,
                    'received_nonce' => substr($nonce, 0, 10) . '...',
                    'user_ip' => self::get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ));

                wp_send_json_error(array(
                    'message' => '安全验证失败',
                    'code' => 'invalid_nonce'
                ), 403);
            }
            return false;
        }

        return true;
    }

    /**
     * 验证用户权限
     *
     * @param string $capability 需要的权限
     * @param bool $die_on_fail 权限不足时是否终止执行
     * @return bool
     */
    public static function verify_user_capability($capability = 'manage_options', $die_on_fail = true) {
        if (!is_user_logged_in()) {
            if ($die_on_fail) {
                wp_send_json_error(array(
                    'message' => '请先登录',
                    'code' => 'not_logged_in'
                ), 401);
            }
            return false;
        }

        if (!current_user_can($capability)) {
            if ($die_on_fail) {
                // 记录权限违规尝试
                self::log_security_event('insufficient_permissions', array(
                    'required_capability' => $capability,
                    'user_id' => get_current_user_id(),
                    'user_roles' => wp_get_current_user()->roles,
                    'user_ip' => self::get_client_ip()
                ));

                wp_send_json_error(array(
                    'message' => '权限不足',
                    'code' => 'insufficient_permissions'
                ), 403);
            }
            return false;
        }

        return true;
    }

    /**
     * 验证时间胶囊物品权限（用户只能管理自己的物品）
     *
     * @param int $item_id 物品ID
     * @param bool $die_on_fail 权限不足时是否终止执行
     * @return bool
     */
    public static function verify_time_capsule_item_permission($item_id, $die_on_fail = true) {
        if (!is_user_logged_in()) {
            if ($die_on_fail) {
                wp_send_json_error('请先登录', 401);
            }
            return false;
        }

        $current_user_id = get_current_user_id();

        // 管理员可以管理所有物品
        if (current_user_can('manage_options')) {
            return true;
        }

        // 检查物品是否属于当前用户
        global $wpdb;
        $table_name = $wpdb->prefix . 'time_capsule_items';

        $item_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM " . $wpdb->prepare("%i", $table_name) . " WHERE id = %d",
            $item_id
        ));

        if ($item_user_id != $current_user_id) {
            if ($die_on_fail) {
                self::log_security_event('unauthorized_item_access', array(
                    'item_id' => $item_id,
                    'item_owner' => $item_user_id,
                    'current_user' => $current_user_id,
                    'user_ip' => self::get_client_ip()
                ));

                wp_send_json_error('无权限访问此物品', 403);
            }
            return false;
        }

        return true;
    }

    /**
     * 安全地获取和验证输入数据
     *
     * @param array $data 输入数据（通常是$_POST）
     * @param array $rules 验证规则
     * @return array 验证后的数据
     */
    public static function validate_and_sanitize_input($data, $rules) {
        $sanitized = array();
        $errors = array();

        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : '';

            // 检查必填字段
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ($rule['label'] ?? $field) . '不能为空';
                continue;
            }

            // 如果字段为空且不是必填，跳过验证
            if (empty($value)) {
                $sanitized[$field] = '';
                continue;
            }

            // 根据类型清理数据
            switch ($rule['type'] ?? 'text') {
                case 'text':
                    $sanitized[$field] = sanitize_text_field($value);
                    break;

                case 'textarea':
                    $sanitized[$field] = wp_kses_post($value);
                    break;

                case 'url':
                    $sanitized[$field] = esc_url_raw($value);
                    if (!empty($sanitized[$field]) && !filter_var($sanitized[$field], FILTER_VALIDATE_URL)) {
                        $errors[$field] = ($rule['label'] ?? $field) . '格式无效';
                    }
                    break;

                case 'email':
                    $sanitized[$field] = sanitize_email($value);
                    if (!empty($sanitized[$field]) && !is_email($sanitized[$field])) {
                        $errors[$field] = ($rule['label'] ?? $field) . '格式无效';
                    }
                    break;

                case 'int':
                    $sanitized[$field] = intval($value);
                    if (isset($rule['min']) && $sanitized[$field] < $rule['min']) {
                        $errors[$field] = ($rule['label'] ?? $field) . '不能小于' . $rule['min'];
                    }
                    if (isset($rule['max']) && $sanitized[$field] > $rule['max']) {
                        $errors[$field] = ($rule['label'] ?? $field) . '不能大于' . $rule['max'];
                    }
                    break;

                case 'float':
                    $sanitized[$field] = floatval($value);
                    if (isset($rule['min']) && $sanitized[$field] < $rule['min']) {
                        $errors[$field] = ($rule['label'] ?? $field) . '不能小于' . $rule['min'];
                    }
                    if (isset($rule['max']) && $sanitized[$field] > $rule['max']) {
                        $errors[$field] = ($rule['label'] ?? $field) . '不能大于' . $rule['max'];
                    }
                    break;

                case 'date':
                    $sanitized[$field] = sanitize_text_field($value);
                    if (!DateTime::createFromFormat('Y-m-d', $sanitized[$field])) {
                        $errors[$field] = ($rule['label'] ?? $field) . '日期格式无效';
                    }
                    break;

                default:
                    $sanitized[$field] = sanitize_text_field($value);
            }
        }

        return array(
            'data' => $sanitized,
            'errors' => $errors
        );
    }

    /**
     * 记录安全事件
     *
     * @param string $event_type 事件类型
     * @param array $details 事件详情
     */
    public static function log_security_event($event_type, $details = array()) {
        if (!function_exists('wt_log_error') && !function_exists('wt_log_info')) {
            return;
        }

        $sanitized_details = self::sanitize_log_data($details);

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $sanitized_details
        );

        if (function_exists('wt_log_error')) {
            wt_log_error('Security Event: ' . $event_type, 'wordpress-toolkit-security', $log_entry);
        } else {
            error_log('WordPress Toolkit Security: ' . wp_json_encode($log_entry));
        }
    }

    /**
     * 清理日志数据中的敏感信息
     *
     * @param array $data 原始数据
     * @return array 清理后的数据
     */
    private static function sanitize_log_data($data) {
        $sensitive_keys = array(
            'password', 'token', 'secret', 'key', 'nonce',
            'api_key', 'authorization', 'cookie'
        );

        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::sanitize_log_data($value);
            } elseif (is_string($value) && strlen($value) > 100) {
                $data[$key] = substr($value, 0, 100) . '...';
            }
        }

        return $data;
    }

    /**
     * 安全地获取客户端IP地址
     *
     * @return string IP地址
     */
    private static function get_client_ip() {
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

                // 验证IP地址有效性
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * 验证文件路径安全性
     *
     * @param string $path 要验证的路径
     * @param string $base_path 基础路径
     * @return bool
     */
    public static function validate_file_path($path, $base_path) {
        $real_path = realpath($path);
        $real_base = realpath($base_path);

        if ($real_path === false || $real_base === false) {
            return false;
        }

        return strpos($real_path, $real_base) === 0;
    }

    /**
     * 生成安全的CSRF令牌
     *
     * @param string $action 动作名称
     * @param int $user_id 用户ID（可选）
     * @return string
     */
    public static function generate_secure_token($action, $user_id = null) {
        $user_id = $user_id ?: get_current_user_id();
        $timestamp = time();
        $token = wp_hash($action . $user_id . $timestamp . wp_salt());

        return base64_encode($timestamp . '|' . $token);
    }

    /**
     * 验证安全令牌
     *
     * @param string $token 令牌
     * @param string $action 动作名称
     * @param int $ttl 生存时间（秒）
     * @return bool
     */
    public static function verify_secure_token($token, $action, $ttl = 3600) {
        if (empty($token)) {
            return false;
        }

        $decoded = base64_decode($token);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $timestamp = intval($parts[0]);
        $hash = $parts[1];

        // 检查时间有效性
        if (time() - $timestamp > $ttl) {
            return false;
        }

        $user_id = get_current_user_id();
        $expected_hash = wp_hash($action . $user_id . $timestamp . wp_salt());

        return hash_equals($expected_hash, $hash);
    }
}