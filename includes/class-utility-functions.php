<?php
/**
 * WordPress Toolkit 通用工具类
 * 提供常用的工具函数，减少代码重复
 *
 * @since 1.1.0
 * @author WordPress Toolkit Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Utilities {

    /**
     * 获取当前插件版本
     */
    public static function get_plugin_version() {
        return defined('WORDPRESS_TOOLKIT_VERSION') ? WORDPRESS_TOOLKIT_VERSION : '1.0.0';
    }

    /**
     * 获取插件路径
     */
    public static function get_plugin_path() {
        return defined('WORDPRESS_TOOLKIT_PLUGIN_PATH') ? WORDPRESS_TOOLKIT_PLUGIN_PATH : plugin_dir_path(__FILE__);
    }

    /**
     * 获取插件URL
     */
    public static function get_plugin_url() {
        return defined('WORDPRESS_TOOLKIT_PLUGIN_URL') ? WORDPRESS_TOOLKIT_PLUGIN_URL : plugin_dir_url(__FILE__);
    }

    /**
     * 安全的HTTP GET请求
     *
     * @param string $url 请求URL
     * @param array $args 请求参数
     * @return array|WP_Error
     */
    public static function safe_remote_get($url, $args = array()) {
        $default_args = array(
            'timeout' => 15,
            'user-agent' => 'WordPress Toolkit/' . self::get_plugin_version(),
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate'
            )
        );

        $args = wp_parse_args($args, $default_args);

        // 验证URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', '无效的URL');
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        // 检查响应状态码
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('http_error', "HTTP请求失败，状态码：{$status_code}");
        }

        return $response;
    }

    /**
     * 安全的HTTP POST请求
     *
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $args 请求参数
     * @return array|WP_Error
     */
    public static function safe_remote_post($url, $data = array(), $args = array()) {
        $default_args = array(
            'timeout' => 30,
            'user-agent' => 'WordPress Toolkit/' . self::get_plugin_version(),
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8'
            )
        );

        $args = wp_parse_args($args, $default_args);

        // 如果数据是数组，转换为JSON
        if (is_array($data) && !isset($args['body'])) {
            $args['body'] = wp_json_encode($data);
            $args['headers']['Content-Length'] = strlen($args['body']);
        }

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (!in_array($status_code, array(200, 201, 202))) {
            return new WP_Error('http_error', "HTTP请求失败，状态码：{$status_code}");
        }

        return $response;
    }

    /**
     * 安全的文件读取
     *
     * @param string $file_path 文件路径
     * @param int $max_size 最大文件大小（字节）
     * @return string|WP_Error
     */
    public static function safe_file_get_contents($file_path, $max_size = 1048576) { // 1MB默认限制
        // 验证文件路径
        $real_path = realpath($file_path);
        $plugin_path = realpath(self::get_plugin_path());

        if ($real_path === false || strpos($real_path, $plugin_path) !== 0) {
            return new WP_Error('invalid_path', '无效的文件路径');
        }

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return new WP_Error('file_not_found', '文件不存在或不可读');
        }

        // 检查文件大小
        $file_size = filesize($file_path);
        if ($file_size > $max_size) {
            return new WP_Error('file_too_large', '文件过大');
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return new WP_Error('read_failed', '文件读取失败');
        }

        return $content;
    }

    /**
     * 安全的文件写入
     *
     * @param string $file_path 文件路径
     * @param string $content 文件内容
     * @param int $max_size 最大文件大小（字节）
     * @return bool|WP_Error
     */
    public static function safe_file_put_contents($file_path, $content, $max_size = 1048576) {
        // 验证文件路径
        $real_path = realpath(dirname($file_path));
        $plugin_path = realpath(self::get_plugin_path());

        if ($real_path === false || strpos($real_path, $plugin_path) !== 0) {
            return new WP_Error('invalid_path', '无效的文件路径');
        }

        // 检查内容大小
        if (strlen($content) > $max_size) {
            return new WP_Error('content_too_large', '内容过大');
        }

        $result = file_put_contents($file_path, $content);
        if ($result === false) {
            return new WP_Error('write_failed', '文件写入失败');
        }

        return true;
    }

    /**
     * 生成安全的随机字符串
     *
     * @param int $length 字符串长度
     * @param string $charset 字符集
     * @return string
     */
    public static function generate_random_string($length = 32, $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $random_string = '';
        $charset_length = strlen($charset);

        for ($i = 0; $i < $length; $i++) {
            $random_string .= $charset[random_int(0, $charset_length - 1)];
        }

        return $random_string;
    }

    /**
     * 创建安全的数据哈希
     *
     * @param mixed $data 要哈希的数据
     * @param string $salt 盐值
     * @return string
     */
    public static function create_data_hash($data, $salt = '') {
        $serialized = is_string($data) ? $data : serialize($data);
        return hash_hmac('sha256', $serialized, $salt . wp_salt());
    }

    /**
     * 验证数据哈希
     *
     * @param mixed $data 原始数据
     * @param string $hash 哈希值
     * @param string $salt 盐值
     * @return bool
     */
    public static function verify_data_hash($data, $hash, $salt = '') {
        $expected_hash = self::create_data_hash($data, $salt);
        return hash_equals($expected_hash, $hash);
    }

    /**
     * 格式化文件大小
     *
     * @param int $bytes 字节数
     * @param int $precision 精度
     * @return string
     */
    public static function format_file_size($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * 格式化时间间隔
     *
     * @param int $seconds 秒数
     * @return string
     */
    public static function format_time_interval($seconds) {
        if ($seconds < 60) {
            return $seconds . ' 秒';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . ' 分钟';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' 小时';
        } elseif ($seconds < 2592000) {
            return round($seconds / 86400, 1) . ' 天';
        } else {
            return round($seconds / 2592000, 1) . ' 个月';
        }
    }

    /**
     * 安全的日期格式化
     *
     * @param string $date 日期字符串
     * @param string $format 格式
     * @return string
     */
    public static function safe_date_format($date, $format = 'Y-m-d H:i:s') {
        if (empty($date)) {
            return '';
        }

        $timestamp = is_numeric($date) ? $date : strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date($format, $timestamp);
    }

    /**
     * 计算两个日期之间的间隔
     *
     * @param string $date1 日期1
     * @param string $date2 日期2
     * @return array
     */
    public static function date_diff($date1, $date2 = null) {
        $date2 = $date2 ?: current_time('mysql');

        $datetime1 = new DateTime($date1);
        $datetime2 = new DateTime($date2);
        $interval = $datetime1->diff($datetime2);

        return array(
            'years' => $interval->y,
            'months' => $interval->m,
            'days' => $interval->d,
            'total_days' => $interval->days,
            'invert' => $interval->invert,
            'formatted' => $interval->format('%y年 %m个月 %d天')
        );
    }

    /**
     * 验证URL是否安全
     *
     * @param string $url URL
     * @param bool $allow_local 是否允许本地URL
     * @return bool
     */
    public static function is_safe_url($url, $allow_local = false) {
        if (empty($url)) {
            return false;
        }

        // 基本URL验证
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed_url = parse_url($url);

        // 检查协议
        $allowed_schemes = array('http', 'https');
        if (!$allow_local) {
            $allowed_schemes = array('https');
        }

        if (!in_array($parsed_url['scheme'] ?? '', $allowed_schemes)) {
            return false;
        }

        // 检查主机名
        $host = $parsed_url['host'] ?? '';
        if (empty($host)) {
            return false;
        }

        // 检查是否为本地地址（如果不允许）
        if (!$allow_local) {
            $local_patterns = array(
                '/^localhost$/',
                '/^127\.0\.0\.1$/',
                '/^192\.168\./',
                '/^10\./',
                '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
                '/^::1$/'
            );

            foreach ($local_patterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 清理HTML内容
     *
     * @param string $html HTML内容
     * @param array $allowed_tags 允许的标签
     * @return string
     */
    public static function clean_html($html, $allowed_tags = null) {
        if ($allowed_tags === null) {
            // 默认允许的标签
            $allowed_tags = array(
                'a' => array(
                    'href' => array(),
                    'title' => array(),
                    'target' => array()
                ),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'p' => array(),
                'ul' => array(),
                'ol' => array(),
                'li' => array(),
                'h1' => array(), 'h2' => array(), 'h3' => array(),
                'h4' => array(), 'h5' => array(), 'h6' => array(),
                'blockquote' => array(),
                'code' => array(),
                'pre' => array()
            );
        }

        return wp_kses($html, $allowed_tags);
    }

    /**
     * 生成唯一的ID
     *
     * @param string $prefix 前缀
     * @return string
     */
    public static function generate_unique_id($prefix = '') {
        return $prefix . uniqid() . '_' . self::generate_random_string(8);
    }

    /**
     * 压缩CSS内容
     *
     * @param string $css CSS内容
     * @return string
     */
    public static function minify_css($css) {
        // 移除注释
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // 移除多余空白
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);

        // 移除多余的空格和分号
        $css = preg_replace(array('/ ;/', '/ :/', '/ ;/'), array(';', ':', ';'), $css);

        return trim($css);
    }

    /**
     * 压缩JavaScript内容
     *
     * @param string $js JavaScript内容
     * @return string
     */
    public static function minify_js($js) {
        // 移除单行注释
        $js = preg_replace('/\/\/.*$/m', '', $js);

        // 移除多行注释
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);

        // 移除多余空白（简单版本）
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }

    /**
     * 检查是否为移动设备
     *
     * @return bool
     */
    public static function is_mobile() {
        return wp_is_mobile();
    }

    /**
     * 获取用户IP地址
     *
     * @return string
     */
    public static function get_user_ip() {
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

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 发送邮件（使用WordPress邮件系统）
     *
     * @param string $to 收件人
     * @param string $subject 主题
     * @param string $content 内容
     * @param array $headers 邮件头
     * @param array $attachments 附件
     * @return bool
     */
    public static function send_email($to, $subject, $content, $headers = array(), $attachments = array()) {
        $default_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $headers = array_merge($default_headers, $headers);

        return wp_mail($to, $subject, $content, $headers, $attachments);
    }

    /**
     * 记录调试信息
     *
     * @param string $message 消息
     * @param string $context 上下文
     * @param array $data 数据
     */
    public static function debug_log($message, $context = 'debug', $data = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
            'data' => $data
        );

        error_log('WordPress Toolkit Debug: ' . wp_json_encode($log_entry));
    }
}