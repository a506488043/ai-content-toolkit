<?php
/**
 * WordPress Toolkit - 缓存管理类
 * 统一的缓存操作和管理功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Cache_Manager {

    /**
     * 默认缓存过期时间（秒）
     */
    const DEFAULT_EXPIRATION = 3600; // 1小时

    /**
     * 缓存组名前缀
     */
    const CACHE_PREFIX = 'wt_';

    /**
     * 获取缓存
     *
     * @param string $key 缓存键
     * @param string $group 缓存组名
     * @return mixed|false 缓存数据或false
     */
    public static function get($key, $group = 'default') {
        $cache_key = self::build_cache_key($key, $group);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug("获取缓存: {$cache_key}", 'cache');
        }

        $data = get_transient($cache_key);

        if ($data !== false && isset($data['data'])) {
            return $data['data'];
        }

        return false;
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $data 缓存数据
     * @param int $expiration 过期时间（秒）
     * @param string $group 缓存组名
     * @return bool
     */
    public static function set($key, $data, $expiration = null, $group = 'default') {
        $cache_key = self::build_cache_key($key, $group);
        $expiration = $expiration ?? self::DEFAULT_EXPIRATION;

        $cache_data = [
            'data' => $data,
            'timestamp' => current_time('timestamp'),
            'expiration' => $expiration
        ];

        $result = set_transient($cache_key, $cache_data, $expiration);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug("设置缓存: {$cache_key}, 过期时间: {$expiration}秒", 'cache');
        }

        return $result;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @param string $group 缓存组名
     * @return bool
     */
    public static function delete($key, $group = 'default') {
        $cache_key = self::build_cache_key($key, $group);
        $result = delete_transient($cache_key);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug("删除缓存: {$cache_key}", 'cache');
        }

        return $result;
    }

    /**
     * 通过模式删除缓存
     *
     * @param string $pattern 匹配模式
     * @param string $group 缓存组名
     * @return int 删除的数量
     */
    public static function delete_by_pattern($pattern, $group = 'default') {
        global $wpdb;

        $cache_prefix = self::CACHE_PREFIX . $group . '_';
        $like_pattern = $cache_prefix . '%' . $pattern . '%';

        // 获取匹配的transient键
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_pattern
        ));

        $deleted_count = 0;

        foreach ($transients as $transient) {
            $transient_key = str_replace('_transient_', '', $transient);
            if (delete_transient($transient_key)) {
                $deleted_count++;
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug("通过模式删除缓存: {$pattern}, 删除数量: {$deleted_count}", 'cache');
        }

        return $deleted_count;
    }

    /**
     * 清空指定组的所有缓存
     *
     * @param string $group 缓存组名
     * @return int 删除的数量
     */
    public static function clear_group($group) {
        return self::delete_by_pattern('', $group);
    }

    /**
     * 清空所有WordPress Toolkit缓存
     *
     * @return int 删除的数量
     */
    public static function clear_all() {
        global $wpdb;

        $like_pattern = '_transient_' . self::CACHE_PREFIX . '%';

        // 获取所有WordPress Toolkit的transient
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_pattern
        ));

        $deleted_count = 0;

        foreach ($transients as $transient) {
            $transient_key = str_replace('_transient_', '', $transient);
            if (delete_transient($transient_key)) {
                $deleted_count++;
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            wt_log_debug("清空所有缓存, 删除数量: {$deleted_count}", 'cache');
        }

        return $deleted_count;
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键
     * @param string $group 缓存组名
     * @return bool
     */
    public static function exists($key, $group = 'default') {
        $cache_key = self::build_cache_key($key, $group);
        $data = get_transient($cache_key);
        return $data !== false;
    }

    /**
     * 获取缓存统计信息
     *
     * @param string $group 缓存组名
     * @return array
     */
    public static function get_stats($group = null) {
        global $wpdb;

        if ($group) {
            $like_pattern = '_transient_' . self::CACHE_PREFIX . $group . '_%';
        } else {
            $like_pattern = '_transient_' . self::CACHE_PREFIX . '%';
        }

        $transients = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_pattern
        ));

        $stats = [
            'total_count' => 0,
            'total_size' => 0,
            'groups' => []
        ];

        foreach ($transients as $transient) {
            $group_name = self::extract_group_from_key($transient->option_name);
            $size = strlen($transient->option_value);

            $stats['total_count']++;
            $stats['total_size'] += $size;

            if (!isset($stats['groups'][$group_name])) {
                $stats['groups'][$group_name] = [
                    'count' => 0,
                    'size' => 0
                ];
            }

            $stats['groups'][$group_name]['count']++;
            $stats['groups'][$group_name]['size'] += $size;
        }

        return $stats;
    }

    /**
     * 缓存回调函数 - 如果缓存不存在则执行回调并缓存结果
     *
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int $expiration 过期时间
     * @param string $group 缓存组名
     * @return mixed
     */
    public static function remember($key, $callback, $expiration = null, $group = 'default') {
        $data = self::get($key, $group);

        if ($data === false) {
            $data = call_user_func($callback);
            self::set($key, $data, $expiration, $group);
        }

        return $data;
    }

    /**
     * 缓存JSON数据
     *
     * @param string $key 缓存键
     * @param array $data 数组数据
     * @param int $expiration 过期时间
     * @param string $group 缓存组名
     * @return bool
     */
    public static function set_json($key, $data, $expiration = null, $group = 'default') {
        return self::set($key, $data, $expiration, $group);
    }

    /**
     * 获取JSON缓存数据
     *
     * @param string $key 缓存键
     * @param string $group 缓存组名
     * @return array|false
     */
    public static function get_json($key, $group = 'default') {
        $data = self::get($key, $group);
        return is_array($data) ? $data : false;
    }

    /**
     * 构建缓存键
     *
     * @param string $key 原始键
     * @param string $group 组名
     * @return string
     */
    private static function build_cache_key($key, $group) {
        return self::CACHE_PREFIX . $group . '_' . md5($key);
    }

    /**
     * 从缓存键中提取组名
     *
     * @param string $option_name WordPress option名
     * @return string
     */
    private static function extract_group_from_key($option_name) {
        $prefix = '_transient_' . self::CACHE_PREFIX;
        $without_prefix = str_replace($prefix, '', $option_name);

        // 找到第一个下划线的位置（组名结束位置）
        $pos = strpos($without_prefix, '_');
        if ($pos !== false) {
            return substr($without_prefix, 0, $pos);
        }

        return 'default';
    }
}