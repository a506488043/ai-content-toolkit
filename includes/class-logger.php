<?php
/**
 * WordPress Toolkit 日志管理类
 * 
 * 提供统一的日志输出功能，支持调试模式和敏感信息保护
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Logger {
    
    /**
     * 日志前缀
     */
    const LOG_PREFIX = 'WordPress Toolkit';
    
    /**
     * 是否启用调试模式
     * 
     * @return bool
     */
    private static function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * 记录错误日志（始终记录）
     *
     * @param string $message 日志消息
     * @param string $module 模块名称
     * @param array $context 上下文数据
     */
    public static function error($message, $module = 'general', $context = array()) {
        $log_message = self::format_message($message, $module, $context);
        error_log($log_message);
    }
    
    /**
     * 记录信息日志（仅在调试模式下）
     * 
     * @param string $message 日志消息
     * @param string $module 模块名称
     * @param array $context 上下文数据
     */
    public static function info($message, $module = 'general', $context = array()) {
        if (!self::is_debug_mode()) {
            return;
        }
        
        $log_message = self::format_message($message, $module, $context);
        error_log($log_message);
    }
    
    /**
     * 记录调试日志（仅在调试模式下）
     * 
     * @param string $message 日志消息
     * @param string $module 模块名称
     * @param array $context 上下文数据
     */
    public static function debug($message, $module = 'general', $context = array()) {
        if (!self::is_debug_mode()) {
            return;
        }
        
        $log_message = self::format_message($message, $module, $context);
        error_log($log_message);
    }
    
    /**
     * 记录警告日志（始终记录）
     * 
     * @param string $message 日志消息
     * @param string $module 模块名称
     * @param array $context 上下文数据
     */
    public static function warning($message, $module = 'general', $context = array()) {
        $log_message = self::format_message($message, $module, $context);
        error_log($log_message);
    }
    
    /**
     * 记录关键错误日志（始终记录）
     * 
     * @param string $message 日志消息
     * @param string $module 模块名称
     * @param array $context 上下文数据
     */
    public static function critical($message, $module = 'general', $context = array()) {
        $log_message = self::format_message($message, $module, $context);
        error_log($log_message);
    }
    
    /**
     * 格式化日志消息
     * 
     * @param string $message 原始消息
     * @param string $module 模块名称
     * @param array $context 上下文数据
     * @return string 格式化后的消息
     */
    private static function format_message($message, $module, $context) {
        // 基础格式
        $formatted = sprintf('[%s] [%s] %s', self::LOG_PREFIX, strtoupper($module), $message);
        
        // 添加上下文信息
        if (!empty($context)) {
            // 过滤敏感信息
            $filtered_context = self::filter_sensitive_data($context);
            $formatted .= ' | Context: ' . json_encode($filtered_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return $formatted;
    }
    
    /**
     * 过滤敏感数据
     * 
     * @param array $data 原始数据
     * @return array 过滤后的数据
     */
    private static function filter_sensitive_data($data) {
        $sensitive_keys = array(
            'password', 'passwd', 'pwd', 'secret', 'key', 'token', 'auth',
            'cookie', 'session', 'nonce', 'wpnonce', 'api_key', 'private_key'
        );
        
        $filtered = array();
        
        foreach ($data as $key => $value) {
            $key_lower = strtolower($key);
            
            // 检查是否为敏感键
            $is_sensitive = false;
            foreach ($sensitive_keys as $sensitive_key) {
                if (strpos($key_lower, $sensitive_key) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }
            
            if ($is_sensitive) {
                $filtered[$key] = '[FILTERED]';
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
    
    /**
     * 记录数据库错误
     * 
     * @param string $message 错误消息
     * @param string $module 模块名称
     * @param string $sql_error SQL错误信息
     */
    public static function database_error($message, $module = 'database', $sql_error = '') {
        $context = array();
        if ($sql_error) {
            $context['sql_error'] = $sql_error;
        }
        
        self::error($message, $module, $context);
    }
    
    /**
     * 记录缓存操作
     * 
     * @param string $message 操作消息
     * @param string $module 模块名称
     * @param array $cache_info 缓存信息
     */
    public static function cache_operation($message, $module = 'cache', $cache_info = array()) {
        if (!self::is_debug_mode()) {
            return;
        }
        
        self::debug($message, $module, $cache_info);
    }
    
    /**
     * 记录AJAX请求
     * 
     * @param string $message 请求消息
     * @param string $module 模块名称
     * @param array $request_info 请求信息
     */
    public static function ajax_request($message, $module = 'ajax', $request_info = array()) {
        if (!self::is_debug_mode()) {
            return;
        }
        
        self::debug($message, $module, $request_info);
    }
    
    /**
     * 记录文件操作
     * 
     * @param string $message 操作消息
     * @param string $module 模块名称
     * @param array $file_info 文件信息
     */
    public static function file_operation($message, $module = 'file', $file_info = array()) {
        if (!self::is_debug_mode()) {
            return;
        }
        
        self::debug($message, $module, $file_info);
    }
}

// 简化的日志函数别名
if (!function_exists('wt_log_error')) {
    function wt_log_error($message, $module = 'general', $context = array()) {
        WordPress_Toolkit_Logger::error($message, $module, $context);
    }
}

if (!function_exists('wt_log_info')) {
    function wt_log_info($message, $module = 'general', $context = array()) {
        WordPress_Toolkit_Logger::info($message, $module, $context);
    }
}

if (!function_exists('wt_log_debug')) {
    function wt_log_debug($message, $module = 'general', $context = array()) {
        WordPress_Toolkit_Logger::debug($message, $module, $context);
    }
}

if (!function_exists('wt_log_warning')) {
    function wt_log_warning($message, $module = 'general', $context = array()) {
        WordPress_Toolkit_Logger::warning($message, $module, $context);
    }
}

if (!function_exists('wt_log_critical')) {
    function wt_log_critical($message, $module = 'general', $context = array()) {
        WordPress_Toolkit_Logger::critical($message, $module, $context);
    }
}