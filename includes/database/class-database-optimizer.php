<?php
/**
 * WordPress Toolkit 数据库优化器
 * 提供数据库查询优化和索引管理功能
 *
 * @since 1.1.0
 * @author WordPress Toolkit Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Database_Optimizer {

    /**
     * 添加推荐的数据库索引
     *
     * @param string $table_name 表名
     * @return array 执行结果
     */
    public static function add_recommended_indexes($table_name) {
        global $wpdb;

        $results = array();

        // 定义推荐的索引
        $recommended_indexes = array(
            'time_capsule_items' => array(
                'idx_category_status' => 'category, status',
                'idx_user_category' => 'user_id, category',
                'idx_user_status' => 'user_id, status',
                'idx_category_status_created' => 'category, status, created_at',
                'idx_purchase_date' => 'purchase_date',
                'idx_warranty_period' => 'warranty_period'
            ),
            'chf_card_cache' => array(
                'idx_url_hash_expires' => 'url_hash, expires_at',
                'idx_expires_at' => 'expires_at',
                'idx_created_at' => 'created_at'
            ),
            'time_capsule_categories' => array(
                'idx_name' => 'name',
                'idx_icon' => 'icon'
            )
        );

        $table_key = str_replace($wpdb->prefix, '', $table_name);

        if (isset($recommended_indexes[$table_key])) {
            foreach ($recommended_indexes[$table_key] as $index_name => $columns) {
                $result = self::add_index_if_not_exists($table_name, $index_name, $columns);
                $results[$index_name] = $result;
            }
        }

        return $results;
    }

    /**
     * 添加索引（如果不存在）
     *
     * @param string $table_name 表名
     * @param string $index_name 索引名
     * @param string $columns 列名
     * @return bool 执行结果
     */
    private static function add_index_if_not_exists($table_name, $index_name, $columns) {
        global $wpdb;

        // 检查索引是否已存在
        $existing_indexes = $wpdb->get_results(
            "SHOW INDEX FROM " . $wpdb->prepare("%i", $table_name) . " WHERE Key_name = %s",
            $index_name
        );

        if (!empty($existing_indexes)) {
            return array(
                'status' => 'exists',
                'message' => "索引 {$index_name} 已存在"
            );
        }

        // 添加索引
        $sql = "ALTER TABLE " . $wpdb->prepare("%i", $table_name) .
               " ADD INDEX " . $wpdb->prepare("%i", $index_name) .
               " (" . $wpdb->prepare("%i", $columns) . ")";

        $result = $wpdb->query($sql);

        if ($result !== false) {
            return array(
                'status' => 'success',
                'message' => "成功添加索引 {$index_name}"
            );
        } else {
            return array(
                'status' => 'error',
                'message' => "添加索引 {$index_name} 失败: " . $wpdb->last_error
            );
        }
    }

    /**
     * 优化的时间胶囊物品查询（修复N+1问题）
     *
     * @param array $args 查询参数
     * @return array 物品列表
     */
    public static function get_time_capsule_items_optimized($args = array()) {
        global $wpdb;

        $defaults = array(
            'user_id' => null,
            'category' => null,
            'status' => null,
            'search' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'time_capsule_items';

        // 构建WHERE条件
        $where_conditions = array();
        $where_values = array();

        if ($args['user_id']) {
            $where_conditions[] = "user_id = %d";
            $where_values[] = $args['user_id'];
        }

        if ($args['category']) {
            $where_conditions[] = "category = %s";
            $where_values[] = $args['category'];
        }

        if ($args['status']) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if ($args['search']) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(name LIKE %s OR description LIKE %s OR brand LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = !empty($where_conditions) ?
            "WHERE " . implode(" AND ", $where_conditions) : "";

        // 构建ORDER BY
        $allowed_orderby = array('id', 'name', 'category', 'created_at', 'purchase_date', 'warranty_period');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = "ORDER BY {$orderby} {$order}";

        // 构建LIMIT
        $limit_clause = "";
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        // 构建完整的SQL查询
        $sql = "SELECT * FROM " . $wpdb->prepare("%i", $table_name) .
               " {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        $items = $wpdb->get_results($sql);

        // 批量增强数据，避免N+1查询
        return self::batch_enhance_items_data($items);
    }

    /**
     * 批量增强物品数据（避免N+1查询）
     *
     * @param array $items 物品列表
     * @return array 增强后的物品列表
     */
    private static function batch_enhance_items_data($items) {
        if (empty($items)) {
            return $items;
        }

        $current_time = time();

        foreach ($items as &$item) {
            // 批量计算年龄相关数据
            $item = self::calculate_item_metrics($item, $current_time);
        }

        return $items;
    }

    /**
     * 计算物品指标数据
     *
     * @param object $item 物品数据
     * @param int $current_time 当前时间戳
     * @return object 增强后的物品数据
     */
    private static function calculate_item_metrics($item, $current_time) {
        // 计算使用天数
        if (!empty($item->purchase_date)) {
            $purchase_timestamp = strtotime($item->purchase_date);
            $item->days_owned = floor(($current_time - $purchase_timestamp) / (24 * 60 * 60));

            // 宠物类别计算年龄
            if ($item->category === 'pets' && !empty($item->warranty_period)) {
                $birth_timestamp = strtotime($item->warranty_period);
                $item->age_years = floor(($current_time - $birth_timestamp) / (365 * 24 * 60 * 60));
            }

            // 零食食品类别计算过期天数
            if ($item->category === 'snacks' && $item->shelf_life > 0) {
                $expiry_date = strtotime("+{$item->shelf_life} days", $purchase_timestamp);
                $item->days_expired = floor(($current_time - $expiry_date) / (24 * 60 * 60));
            }

            // 证书资质特殊处理
            if ($item->category === 'certificate') {
                $issue_date = $item->purchase_date;

                if (empty($item->holding_duration) && !empty($issue_date)) {
                    $issue_timestamp = strtotime($issue_date);
                    $item->holding_duration = floor(($current_time - $issue_timestamp) / (30 * 24 * 60 * 60));
                }

                // 计算续证提醒
                if (!empty($item->renewal_date) && !empty($item->reminder_days)) {
                    $renewal_timestamp = strtotime($item->renewal_date);
                    $reminder_timestamp = strtotime("-{$item->reminder_days} days", $renewal_timestamp);
                    $item->renewal_due = $current_time >= $reminder_timestamp;
                    $item->days_until_renewal = floor(($renewal_timestamp - $current_time) / (24 * 60 * 60));
                }
            }
        }

        return $item;
    }

    /**
     * 优化的缓存卡片查询
     *
     * @param array $args 查询参数
     * @return array 卡片列表
     */
    public static function get_custom_cards_optimized($args = array()) {
        global $wpdb;

        $defaults = array(
            'search' => null,
            'status' => 'active',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'chf_card_cache';

        // 构建WHERE条件
        $where_conditions = array();
        $where_values = array();

        // 只返回未过期的缓存
        $where_conditions[] = "expires_at > %s";
        $where_values[] = current_time('mysql');

        if ($args['search']) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(url LIKE %s OR title LIKE %s OR description LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = "WHERE " . implode(" AND ", $where_conditions);

        // 构建ORDER BY
        $allowed_orderby = array('id', 'url', 'title', 'created_at', 'expires_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = "ORDER BY {$orderby} {$order}";

        // 构建LIMIT
        $limit_clause = "";
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        // 构建完整的SQL查询
        $sql = "SELECT * FROM " . $wpdb->prepare("%i", $table_name) .
               " {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * 清理过期缓存
     *
     * @param int $batch_size 批次大小
     * @return int 清理的记录数
     */
    public static function clean_expired_cache($batch_size = 1000) {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'chf_card_cache' => 'expires_at',
            $wpdb->prefix . 'time_capsule_items' => 'created_at' // 示例，可根据需要调整
        );

        $total_cleaned = 0;

        foreach ($tables as $table => $date_column) {
            if ($table === $wpdb->prefix . 'chf_card_cache') {
                // 清理过期的卡片缓存
                $sql = "DELETE FROM " . $wpdb->prepare("%i", $table) .
                       " WHERE {$date_column} < %s LIMIT %d";
                $deleted = $wpdb->query($wpdb->prepare($sql, current_time('mysql'), $batch_size));
                $total_cleaned += $deleted;
            }
        }

        return $total_cleaned;
    }

    /**
     * 获取数据库性能统计
     *
     * @return array 性能统计信息
     */
    public static function get_performance_stats() {
        global $wpdb;

        $stats = array();

        // 获取表大小信息
        $tables = array(
            $wpdb->prefix . 'time_capsule_items',
            $wpdb->prefix . 'time_capsule_categories',
            $wpdb->prefix . 'chf_card_cache'
        );

        foreach ($tables as $table) {
            $table_stats = $wpdb->get_row(
                "SELECT
                    COUNT(*) as record_count,
                    AVG(LENGTH(id)) as avg_record_size
                FROM " . $wpdb->prepare("%i", $table)
            );

            if ($table_stats) {
                $stats[$table] = array(
                    'record_count' => intval($table_stats->record_count),
                    'avg_record_size' => floatval($table_stats->avg_record_size)
                );
            }
        }

        return $stats;
    }
}