<?php
/**
 * WordPress Toolkit - 安全验证类
 * 统一的安全验证和输入清理功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Security_Validator {

    /**
     * 验证管理员AJAX请求
     *
     * @param string $nonce_action nonce动作名称
     * @return bool
     */
    public static function verify_admin_ajax($nonce_action) {
        // 权限检查
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('权限不足', 'wordpress-toolkit'),
                'code' => 'insufficient_permissions'
            ]);
            return false;
        }

        // Nonce验证
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            wp_send_json_error([
                'message' => __('安全验证失败', 'wordpress-toolkit'),
                'code' => 'invalid_nonce'
            ]);
            return false;
        }

        return true;
    }

    /**
     * 验证普通用户AJAX请求
     *
     * @param string $nonce_action nonce动作名称
     * @param string $capability 需要的权限
     * @return bool
     */
    public static function verify_user_ajax($nonce_action, $capability = 'read') {
        // 权限检查
        if (!current_user_can($capability)) {
            wp_send_json_error([
                'message' => __('权限不足', 'wordpress-toolkit'),
                'code' => 'insufficient_permissions'
            ]);
            return false;
        }

        // Nonce验证
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            wp_send_json_error([
                'message' => __('安全验证失败', 'wordpress-toolkit'),
                'code' => 'invalid_nonce'
            ]);
            return false;
        }

        return true;
    }

    /**
     * 清理POST数据
     *
     * @param array $fields 字段名和清理类型的映射
     * @return array 清理后的数据
     */
    public static function sanitize_post_data($fields) {
        $sanitized = [];

        foreach ($fields as $field => $sanitize_type) {
            $value = $_POST[$field] ?? '';

            switch ($sanitize_type) {
                case 'text':
                    $sanitized[$field] = sanitize_text_field($value);
                    break;
                case 'textarea':
                    $sanitized[$field] = sanitize_textarea_field($value);
                    break;
                case 'url':
                    $sanitized[$field] = esc_url_raw($value);
                    break;
                case 'email':
                    $sanitized[$field] = sanitize_email($value);
                    break;
                case 'filename':
                    $sanitized[$field] = sanitize_file_name($value);
                    break;
                case 'html':
                    $sanitized[$field] = wp_kses_post($value);
                    break;
                case 'int':
                    $sanitized[$field] = intval($value);
                    break;
                case 'float':
                    $sanitized[$field] = floatval($value);
                    break;
                case 'bool':
                    $sanitized[$field] = (bool) $value;
                    break;
                case 'alpha_num':
                    $sanitized[$field] = preg_replace('/[^a-zA-Z0-9]/', '', $value);
                    break;
                default:
                    // 自定义清理函数
                    if (is_callable($sanitize_type)) {
                        $sanitized[$field] = call_user_func($sanitize_type, $value);
                    } else {
                        $sanitized[$field] = sanitize_text_field($value);
                    }
            }
        }

        return $sanitized;
    }

    /**
     * 验证必填字段
     *
     * @param array $data 要验证的数据
     * @param array $required_fields 必填字段名
     * @return array 验证结果 ['valid' => bool, 'errors' => array]
     */
    public static function validate_required_fields($data, $required_fields) {
        $errors = [];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = sprintf(
                    __('字段 "%s" 是必填的', 'wordpress-toolkit'),
                    $field
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 验证URL格式
     *
     * @param string $url 要验证的URL
     * @return bool
     */
    public static function validate_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 验证邮箱格式
     *
     * @param string $email 要验证的邮箱
     * @return bool
     */
    public static function validate_email($email) {
        return is_email($email) !== false;
    }

    /**
     * 验证日期格式
     *
     * @param string $date 日期字符串
     * @param string $format 日期格式
     * @return bool
     */
    public static function validate_date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * 生成nonce字段HTML
     *
     * @param string $action 动作名称
     * @return string HTML
     */
    public static function nonce_field($action) {
        return wp_nonce_field($action, 'nonce', true, false);
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    public static function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 检查是否为安全的文件类型
     *
     * @param string $filename 文件名
     * @param array $allowed_types 允许的文件类型
     * @return bool
     */
    public static function is_safe_file_type($filename, $allowed_types = []) {
        if (empty($allowed_types)) {
            $allowed_types = [
                'jpg', 'jpeg', 'png', 'gif', 'webp',  // 图片
                'pdf', 'doc', 'docx', 'txt',         // 文档
                'zip', 'tar', 'gz'                   // 压缩文件
            ];
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowed_types);
    }
}