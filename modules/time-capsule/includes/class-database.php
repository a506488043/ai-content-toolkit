<?php
/**
 * 数据库操作类
 */

if (!defined('ABSPATH')) {
    exit;
}

class TimeCapsule_Database {

    private $db_manager;
    private $table_items;
    private $table_categories;

    public function __construct() {
        $this->db_manager = new WordPress_Toolkit_Database_Manager();
        $this->table_items = 'time_capsule_items';
        $this->table_categories = 'time_capsule_categories';
    }

    /**
     * 验证表名是否在允许的白名单中
     */
    private function validate_table_name($table_name) {
        $allowed_tables = array(
            $this->table_items,
            $this->table_categories
        );
        return in_array($table_name, $allowed_tables);
    }

    /**
     * 获取完整的表名
     */
    private function get_table_name($table) {
        return $this->db_manager->get_table_name($table);
    }

    /**
     * 更新数据库表结构
     */
    public function update_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 更新items表结构
        $this->update_items_table();

        // 更新categories表结构
        $this->update_categories_table();

        return true;
    }

    /**
     * 更新items表结构
     */
    private function update_items_table() {
        global $wpdb;

        // 检查是否需要添加证书资质相关字段
        $table_name = $this->get_table_name($this->table_items);

        // 验证表名安全性
        if (!$this->validate_table_name($this->table_items)) {
            wt_log_error('Invalid table name in update_tables', 'time-capsule');
            return false;
        }

        // 检查字段是否存在
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}");

        $certificate_fields = array(
            'issue_date' => "ADD COLUMN issue_date date DEFAULT NULL COMMENT '发证时间'",
            'issuing_authority' => "ADD COLUMN issuing_authority varchar(255) DEFAULT NULL COMMENT '发证机构'",
            'renewal_period' => "ADD COLUMN renewal_period int(11) DEFAULT 0 COMMENT '续证周期(月)'",
            'holding_duration' => "ADD COLUMN holding_duration int(11) DEFAULT 0 COMMENT '持证时长(月)'",
            'renewal_date' => "ADD COLUMN renewal_date date DEFAULT NULL COMMENT '续证时间'",
            'training_fee' => "ADD COLUMN training_fee decimal(10,2) DEFAULT 0.00 COMMENT '培训费用'",
            'renewal_fee' => "ADD COLUMN renewal_fee decimal(10,2) DEFAULT 0.00 COMMENT '续证费用'",
            'total_mileage' => "ADD COLUMN total_mileage decimal(10,1) DEFAULT 0.0 COMMENT '总里程(公里)'",
            'used_time_hours' => "ADD COLUMN used_time_hours decimal(10,1) DEFAULT 0.0 COMMENT '年龄(岁)'",
            // 新增的证书资质字段
            'certificate_number' => "ADD COLUMN certificate_number varchar(255) DEFAULT NULL COMMENT '证书编号'",
            'renewal_unit' => "ADD COLUMN renewal_unit varchar(10) DEFAULT 'months' COMMENT '续证周期单位'",
            'certificate_level' => "ADD COLUMN certificate_level varchar(20) DEFAULT NULL COMMENT '证书等级'",
            'reminder_days' => "ADD COLUMN reminder_days int(11) DEFAULT 30 COMMENT '续证提醒天数'",
            'certificate_status' => "ADD COLUMN certificate_status varchar(20) DEFAULT 'valid' COMMENT '证书状态'"
        );

        $alter_sqls = array();
        foreach ($certificate_fields as $field => $alter_sql) {
            if (!in_array($field, $existing_columns)) {
                $alter_sqls[] = $alter_sql;
            }
        }

        if (!empty($alter_sqls)) {
            // 逐个执行ALTER操作，避免重复字段错误
            foreach ($alter_sqls as $alter_sql) {
                $full_sql = "ALTER TABLE {$table_name} {$alter_sql}";
                $result = $wpdb->query($full_sql);
                if ($result === false) {
                    // 如果是重复字段错误，记录但不停止
                    if (strpos($wpdb->last_error, 'Duplicate column name') !== false) {
                        wt_log_error('Column already exists, skipping: ' . $wpdb->last_error, 'time-capsule');
                        continue;
                    } else {
                        wt_log_error('Failed to alter table structure: ' . $wpdb->last_error, 'time-capsule');
                        return false;
                    }
                }
            }

            // 添加索引，并处理错误
            $index_operations = array();
            if (!in_array('issue_date', $existing_columns)) {
                $index_operations[] = "ALTER TABLE {$table_name} ADD INDEX issue_date (issue_date)";
            }
            if (!in_array('renewal_date', $existing_columns)) {
                $index_operations[] = "ALTER TABLE {$table_name} ADD INDEX renewal_date (renewal_date)";
            }
            if (!in_array('certificate_status', $existing_columns)) {
                $index_operations[] = "ALTER TABLE {$table_name} ADD INDEX certificate_status (certificate_status)";
            }

            // 执行索引操作
            foreach ($index_operations as $index_sql) {
                $result = $wpdb->query($index_sql);
                if ($result === false) {
                    wt_log_error('Failed to create index: ' . $wpdb->last_error, 'time-capsule');
                    // 继续执行，不返回false，因为索引创建失败不会影响主要功能
                }
            }
        }

        return true;
    }

    /**
     * 更新categories表结构
     */
    private function update_categories_table() {
        global $wpdb;

        $table_name = $this->get_table_name($this->table_categories);

        // 验证表名安全性
        if (!$this->validate_table_name($this->table_categories)) {
            wt_log_error('Invalid table name in update_categories_table', 'time-capsule');
            return false;
        }

        // 检查字段是否存在
        $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_name}");

        // 检查并添加updated_at字段
        if (!in_array('updated_at', $existing_columns)) {
            $alter_sql = "ALTER TABLE {$table_name} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'";
            $result = $wpdb->query($alter_sql);
            if ($result === false) {
                wt_log_error('Failed to add updated_at column to categories table: ' . $wpdb->last_error, 'time-capsule');
                return false;
            }
        }

        return true;
    }

    /**
     * 创建数据库表
     */
    public function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // 创建物品表
        $table_items_sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name($this->table_items)} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            category varchar(100) NOT NULL,
            description text,
            purchase_date date NOT NULL,
            purchase_source varchar(255),
            warranty_period date DEFAULT NULL COMMENT '出生日期',
            shelf_life int(11) DEFAULT 0,
            price decimal(10,2) DEFAULT 0.00,
            brand varchar(255),
            model varchar(255),
            serial_number varchar(255),
            notes text,
            status varchar(20) DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            -- 证书资质特有字段
            issue_date date DEFAULT NULL COMMENT '发证时间',
            issuing_authority varchar(255) DEFAULT NULL COMMENT '发证机构',
            renewal_period int(11) DEFAULT 0 COMMENT '续证周期(月)',
            holding_duration int(11) DEFAULT 0 COMMENT '持证时长(月)',
            renewal_date date DEFAULT NULL COMMENT '续证时间',
            training_fee decimal(10,2) DEFAULT 0.00 COMMENT '培训费用',
            renewal_fee decimal(10,2) DEFAULT 0.00 COMMENT '续证费用',
            -- 新增证书资质字段
            certificate_number varchar(255) DEFAULT NULL COMMENT '证书编号',
            renewal_unit varchar(10) DEFAULT 'months' COMMENT '续证周期单位',
            certificate_level varchar(20) DEFAULT NULL COMMENT '证书等级',
            reminder_days int(11) DEFAULT 30 COMMENT '续证提醒天数',
            certificate_status varchar(20) DEFAULT 'valid' COMMENT '证书状态',
            -- 其他特有字段
            total_mileage decimal(10,1) DEFAULT 0.0 COMMENT '总里程(公里)',
            used_time_hours decimal(10,1) DEFAULT 0.0 COMMENT '年龄(岁)',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY category (category),
            KEY status (status),
            KEY purchase_date (purchase_date),
            KEY issue_date (issue_date),
            KEY renewal_date (renewal_date),
            KEY certificate_status (certificate_status)
        ) $charset_collate;";

        // 创建类别表
        $table_categories_sql = "CREATE TABLE IF NOT EXISTS {$this->get_table_name($this->table_categories)} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            display_name varchar(100) NOT NULL,
            description text,
            icon varchar(50),
            color varchar(7) DEFAULT '#007bff',
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        dbDelta($table_items_sql);
        dbDelta($table_categories_sql);

        // 更新表结构以添加新字段
        $this->update_tables();

        // 检查表是否创建成功
        $items_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->get_table_name($this->table_items)}'") === $this->get_table_name($this->table_items);
        $categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->get_table_name($this->table_categories)}'") === $this->get_table_name($this->table_categories);

        if (!$items_table_exists || !$categories_table_exists) {
            wt_log_database_error('Failed to create database tables', 'time-capsule-db', $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * 获取物品列表
     */
    public function get_items($args = array()) {
        $defaults = array(
            'user_id' => current_user_can('manage_options') ? null : get_current_user_id(),
            'category' => '',
            'status' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = array();
        if ($args['user_id'] !== null && $args['user_id'] > 0) {
            $where['user_id'] = $args['user_id'];
        }

        if (!empty($args['category'])) {
            $where['category'] = $args['category'];
        }

        if (!empty($args['status'])) {
            $where['status'] = $args['status'];
        }

        // 搜索条件需要特殊处理
        if (!empty($args['search'])) {
            // 使用自定义SQL处理搜索
            global $wpdb;
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $table_name = $this->get_table_name($this->table_items);

            $where_clause = '';
            if (!empty($where)) {
                $where_clause = 'WHERE ' . $this->build_where_clause($where);
            }

            $search_where = "(name LIKE %s OR description LIKE %s OR brand LIKE %s)";
            if ($where_clause) {
                $search_where = $where_clause . ' AND ' . $search_where;
            } else {
                $search_where = 'WHERE ' . $search_where;
            }

            $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
            if (!$orderby) {
                $orderby = 'created_at DESC';
            }

            $limit = '';
            if ($args['limit'] > 0) {
                $limit = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
            }

            $sql = "SELECT * FROM {$table_name} {$search_where} ORDER BY {$orderby} {$limit}";
            $sql = $wpdb->prepare($sql, $search, $search, $search);

            return $wpdb->get_results($sql);
        }

        return $this->db_manager->get_results(
            $this->table_items,
            $where,
            '*',
            $args['orderby'] . ' ' . $args['order'],
            $args['limit'],
            $args['offset']
        );
    }

    /**
     * 获取单个物品
     */
    public function get_item($id, $user_id = null) {
        if ($user_id === null && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
        }

        $where = ['id' => $id];
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        return $this->db_manager->get_results($this->table_items, $where, '*', 'id DESC', 1)[0] ?? null;
    }

    /**
     * 插入物品
     */
    public function insert_item($data) {
        $data['user_id'] = get_current_user_id();

        return $this->db_manager->insert($this->table_items, $data);
    }

    /**
     * 更新物品
     */
    public function update_item($id, $data, $user_id = null) {
        if ($user_id === null && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
        }

        $where = ['id' => $id];
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        return $this->db_manager->update($this->table_items, $data, $where);
    }

    /**
     * 删除物品
     */
    public function delete_item($id, $user_id = null) {
        if ($user_id === null && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
        }

        // 验证参数
        $id = intval($id);

        if ($id <= 0) {
            return false;
        }

        $where = ['id' => $id];
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        // 检查物品是否存在
        $item = $this->get_item($id, $user_id);
        if (!$item) {
            return false;
        }

        // 执行删除
        $result = $this->db_manager->delete($this->table_items, $where);

        // 记录删除操作日志
        if ($result !== false) {
            wt_log_info('Item deleted', 'time-capsule-db', array('item_id' => $id, 'user_id' => $user_id));
        } else {
            wt_log_database_error('Failed to delete item', 'time-capsule-db', $this->db_manager->get_last_error());
        }

        return $result;
    }

    /**
     * 获取类别列表
     */
    public function get_categories($active_only = true) {
        $where = [];
        if ($active_only) {
            $where['is_active'] = 1;
        }

        return $this->db_manager->get_results($this->table_categories, $where, '*', 'sort_order ASC');
    }

    /**
     * 获取单个类别
     */
    public function get_category($name) {
        return $this->db_manager->get_by_field($this->table_categories, 'name', $name);
    }

    /**
     * 获取物品统计
     */
    public function get_stats($user_id = null) {
        if ($user_id === null && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
        }

        $stats = array();

        // 总物品数
        $where = ['status' => 'active'];
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }
        $stats['total_items'] = $this->db_manager->count($this->table_items, $where);

        // 按类别统计
        global $wpdb;
        $table_name = $this->get_table_name($this->table_items);

        if ($user_id !== null) {
            $category_stats = $wpdb->get_results($wpdb->prepare(
                "SELECT category, COUNT(*) as count FROM {$table_name} WHERE user_id = %d AND status = 'active' GROUP BY category",
                $user_id
            ));
        } else {
            // 管理员查看所有物品
            $category_stats = $wpdb->get_results(
                "SELECT category, COUNT(*) as count FROM {$table_name} WHERE status = 'active' GROUP BY category"
            );
        }

        $stats['by_category'] = array();
        foreach ($category_stats as $stat) {
            $stats['by_category'][$stat->category] = $stat->count;
        }

        return $stats;
    }

    /**
     * 计算年龄
     */
    public function calculate_age($birth_date) {
        if (empty($birth_date)) {
            return null;
        }

        $birth_timestamp = strtotime($birth_date);
        $now = time();

        // 计算年龄（年）
        $age_years = floor(($now - $birth_timestamp) / (365 * 24 * 60 * 60));

        return $age_years;
    }

    /**
     * 插入类别
     */
    public function insert_category($data) {
        return $this->db_manager->insert($this->table_categories, $data);
    }

    /**
     * 更新类别
     */
    public function update_category($name, $data) {
        return $this->db_manager->update($this->table_categories, $data, ['name' => $name]);
    }

    /**
     * 删除类别
     */
    public function delete_category($name) {
        return $this->db_manager->delete($this->table_categories, ['name' => $name]);
    }

    /**
     * 重置数据库表（用于修复表结构问题）
     */
    public function reset_tables() {
        global $wpdb;

        wt_log_info('Resetting database tables', 'time-capsule-db');

        // 安全地删除现有表
        $tables_to_drop = array($this->table_items, $this->table_categories);
        foreach ($tables_to_drop as $table) {
            if ($this->validate_table_name($table)) {
                $table_name = $this->get_table_name($table);
                $result = $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
                if ($result === false) {
                    wt_log_error("Failed to drop table {$table_name}: " . $wpdb->last_error, 'time-capsule-db');
                    return false;
                }
            } else {
                wt_log_error('Invalid table name in reset_tables: ' . $table, 'time-capsule-db');
                return false;
            }
        }

        // 重新创建表
        $result = $this->create_tables();

        if ($result) {
            wt_log_info('Database tables reset successfully', 'time-capsule-db');
        } else {
            wt_log_error('Failed to reset database tables', 'time-capsule-db', array(
                'last_error' => $wpdb->last_error
            ));
        }

        return $result;
    }

    /**
     * 强制重置数据库表（公开方法）
     */
    public function force_reset_tables() {
        return $this->reset_tables();
    }

    /**
     * 构建WHERE子句（用于搜索）
     */
    private function build_where_clause($where) {
        if (empty($where)) {
            return '';
        }

        $conditions = [];
        foreach ($where as $field => $value) {
            $conditions[] = "{$field} = '{$value}'";
        }

        return implode(' AND ', $conditions);
    }
}