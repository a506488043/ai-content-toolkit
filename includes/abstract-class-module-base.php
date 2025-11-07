<?php
/**
 * WordPress Toolkit 基础模块类
 * 提供通用的安全验证和错误处理功能
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class WordPress_Toolkit_Module_Base {

    /**
     * 验证AJAX请求的安全性和权限
     *
     * @param string $nonce_action Nonce动作名称
     * @param string $capability 所需权限，默认为'manage_options'
     * @return bool 验证是否通过
     */
    protected function verify_ajax_request($nonce_action, $capability = 'manage_options') {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $nonce_action)) {
            wp_send_json_error(array(
                'message' => __('安全验证失败', 'wordpress-toolkit'),
                'error_code' => 'invalid_nonce'
            ));
        }

        // 验证用户权限
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('权限不足', 'wordpress-toolkit'),
                'error_code' => 'insufficient_permissions'
            ));
        }

        return true;
    }

    /**
     * 安全地执行数据库查询并处理错误
     *
     * @param string $query SQL查询语句
     * @param string $error_message 自定义错误消息
     * @param string $module_name 模块名称
     * @return mixed|false 查询结果或false
     */
    protected function safe_db_query($query, $error_message = 'Database query failed', $module_name = 'unknown') {
        global $wpdb;

        try {
            $result = $wpdb->query($query);
            if ($result === false) {
                wt_log_error($error_message . ': ' . $wpdb->last_error, $module_name, array(
                    'query' => $query,
                    'last_error' => $wpdb->last_error
                ));
            }
            return $result;
        } catch (Exception $e) {
            wt_log_error($error_message . ': ' . $e->getMessage(), $module_name, array(
                'query' => $query,
                'exception' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * 安全地执行数据库准备查询
     *
     * @param string $query SQL查询模板
     * @param array $args 查询参数
     * @param string $error_message 自定义错误消息
     * @param string $module_name 模块名称
     * @return mixed|false 查询结果或false
     */
    protected function safe_db_prepare($query, $args, $error_message = 'Database prepare query failed', $module_name = 'unknown') {
        global $wpdb;

        try {
            $prepared_query = $wpdb->prepare($query, $args);
            $result = $wpdb->query($prepared_query);

            if ($result === false) {
                wt_log_error($error_message . ': ' . $wpdb->last_error, $module_name, array(
                    'query' => $prepared_query,
                    'args' => $args,
                    'last_error' => $wpdb->last_error
                ));
            }
            return $result;
        } catch (Exception $e) {
            wt_log_error($error_message . ': ' . $e->getMessage(), $module_name, array(
                'query' => $query,
                'args' => $args,
                'exception' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * 批量处理操作，避免超时和内存问题
     *
     * @param array $items 要处理的项目数组
     * @param callable $callback 处理回调函数
     * @param int $batch_size 批次大小
     * @param string $module_name 模块名称
     * @return bool 处理是否成功
     */
    protected function process_in_batches($items, $callback, $batch_size = 50, $module_name = 'unknown') {
        if (!is_callable($callback)) {
            wt_log_error('Invalid callback provided for batch processing', $module_name);
            return false;
        }

        $batches = array_chunk($items, $batch_size);
        $total_batches = count($batches);
        $success = true;

        foreach ($batches as $index => $batch) {
            try {
                $result = call_user_func($callback, $batch, $index + 1, $total_batches);

                if ($result === false) {
                    wt_log_error("Batch {$index} processing failed", $module_name);
                    $success = false;
                }

                // 避免超时，让系统有机会处理其他任务
                if ($index < $total_batches - 1) {
                    if (function_exists('wp_cache_flush')) {
                        wp_cache_flush();
                    }

                    // 在长时间操作中，检查是否接近超时
                    if (function_exists('wp_get_max_execution_time') &&
                        time() - $_SERVER['REQUEST_TIME'] > wp_get_max_execution_time() * 0.8) {
                        wt_log_warning(' approaching execution time limit during batch processing', $module_name);
                    }
                }
            } catch (Exception $e) {
                wt_log_error("Exception in batch {$index}: " . $e->getMessage(), $module_name, array(
                    'exception' => $e->getMessage(),
                    'batch_index' => $index
                ));
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 验证和清理用户输入
     *
     * @param mixed $input 用户输入
     * @param string $type 输入类型（text, email, url, int, float, bool, array）
     * @param array $options 额外选项
     * @return mixed|false 清理后的输入或false
     */
    protected function validate_input($input, $type = 'text', $options = array()) {
        if ($input === null) {
            return null;
        }

        switch ($type) {
            case 'text':
            case 'string':
                return sanitize_text_field($input);

            case 'textarea':
                return sanitize_textarea_field($input);

            case 'email':
                return sanitize_email($input);

            case 'url':
                return esc_url_raw($input);

            case 'int':
            case 'integer':
                return intval($input);

            case 'float':
                return floatval($input);

            case 'bool':
            case 'boolean':
                return rest_sanitize_boolean($input);

            case 'array':
                if (!is_array($input)) {
                    return false;
                }

                $validated = array();
                foreach ($input as $key => $value) {
                    $item_type = $options['item_type'] ?? 'text';
                    $validated[$key] = $this->validate_input($value, $item_type, $options);
                }
                return $validated;

            case 'json':
                $decoded = json_decode($input, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : false;

            default:
                return apply_filters('wordpress_toolkit_validate_input_' . $type, $input, $options);
        }
    }

    /**
     * 发送安全的AJAX响应
     *
     * @param bool $success 是否成功
     * @param string $message 响应消息
     * @param array $data 额外数据
     * @param array $debug_data 调试数据（仅在WP_DEBUG模式下显示）
     */
    protected function send_ajax_response($success, $message, $data = array(), $debug_data = array()) {
        $response = array_merge($data, array(
            'success' => $success,
            'message' => $message
        ));

        // 在调试模式下添加调试信息
        if (defined('WP_DEBUG') && WP_DEBUG && !empty($debug_data)) {
            $response['debug'] = $debug_data;
        }

        if ($success) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }

    /**
     * 记录模块特定的操作日志
     *
     * @param string $message 日志消息
     * @param string $level 日志级别（info, warning, error）
     * @param array $context 上下文数据
     */
    protected function log_module_action($message, $level = 'info', $context = array()) {
        $module_name = strtolower(str_replace(array('WordPress_Toolkit_', '_Module'), '', get_class($this)));

        switch ($level) {
            case 'error':
                wt_log_error($message, $module_name, $context);
                break;
            case 'warning':
                wt_log_warning($message, $module_name, $context);
                break;
            case 'info':
            default:
                wt_log_info($message, $module_name, $context);
                break;
        }
    }
}