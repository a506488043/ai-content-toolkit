<?php
/**
 * 数据库操作类
 */

if (!defined('ABSPATH')) {
    exit;
}

class TimeCapsule_Database {
    
    private $wpdb;
    private $table_items;
    private $table_categories;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_items = $wpdb->prefix . 'time_capsule_items';
        $this->table_categories = $wpdb->prefix . 'time_capsule_categories';
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
     * 更新数据库表结构
     */
    public function update_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 检查是否需要添加证书资质相关字段
        $table_name = $this->table_items;

        // 验证表名安全性
        if (!$this->validate_table_name($table_name)) {
            wt_log_error('Invalid table name in update_tables', 'time-capsule');
            return false;
        }

        // 检查字段是否存在
        $existing_columns = $wpdb->get_col($wpdb->prepare("SHOW COLUMNS FROM %s", $table_name));

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
            $alter_sql = "ALTER TABLE {$table_name} " . implode(', ', $alter_sqls);

            // 执行ALTER TABLE操作并添加错误处理
            $result = $wpdb->query($alter_sql);
            if ($result === false) {
                wt_log_error('Failed to alter table structure: ' . $wpdb->last_error, 'time-capsule');
                return false;
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
     * 创建数据库表
     */
    public function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 创建物品表
        $table_items_sql = "CREATE TABLE IF NOT EXISTS {$this->table_items} (
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
        $table_categories_sql = "CREATE TABLE IF NOT EXISTS {$this->table_categories} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            display_name varchar(100) NOT NULL,
            description text,
            icon varchar(50),
            color varchar(7) DEFAULT '#007bff',
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime NOT NULL,
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
        $items_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_items}'") === $this->table_items;
        $categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_categories}'") === $this->table_categories;
        
        if (!$items_table_exists || !$categories_table_exists) {
            wt_log_database_error('Failed to create database tables', 'time-capsule-db', $this->wpdb->last_error);
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
            $where[] = $this->wpdb->prepare("user_id = %d", $args['user_id']);
        }

        if (!empty($args['category'])) {
            $where[] = $this->wpdb->prepare("category = %s", $args['category']);
        }

        if (!empty($args['status'])) {
            $where[] = $this->wpdb->prepare("status = %s", $args['status']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = $this->wpdb->prepare("(name LIKE %s OR description LIKE %s OR brand LIKE %s)", $search, $search, $search);
        }

        // 移除了保修状态筛选逻辑
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $this->wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        $sql = "SELECT * FROM {$this->table_items} {$where_clause} ORDER BY {$orderby} {$limit}";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * 获取单个物品
     */
    public function get_item($id, $user_id = null) {
        if ($user_id === null && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
        }

        if ($user_id !== null) {
            return $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_items} WHERE id = %d AND user_id = %d",
                    $id,
                    $user_id
                )
            );
        } else {
            // 管理员可以查看所有物品
            return $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_items} WHERE id = %d",
                    $id
                )
            );
        }
    }
    
    /**
     * 插入物品
     */
    public function insert_item($data) {
        $data['user_id'] = get_current_user_id();
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->insert($this->table_items, $data);
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * 更新物品
     */
    public function update_item($id, $data, $user_id = null) {
        if ($user_id === null && !current_user_can('manage_options')) {
            $user_id = get_current_user_id();
        }

        $data['updated_at'] = current_time('mysql');

        $where = array('id' => $id);
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        return $this->wpdb->update(
            $this->table_items,
            $data,
            $where
        );
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

        $where = array('id' => $id);
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        // 检查物品是否存在
        $item = $this->get_item($id, $user_id);
        if (!$item) {
            return false;
        }

        // 执行删除
        $result = $this->wpdb->delete(
            $this->table_items,
            $where,
            array('%d', '%d')
        );

        // 记录删除操作日志
        if ($result !== false) {
            wt_log_info('Item deleted', 'time-capsule-db', array('item_id' => $id, 'user_id' => $user_id));
        } else {
            wt_log_database_error('Failed to delete item', 'time-capsule-db', $this->wpdb->last_error);
        }

        return $result;
    }
    
    /**
     * 获取类别列表
     */
    public function get_categories($active_only = true) {
        $where = '';
        if ($active_only) {
            $where = 'WHERE is_active = 1';
        }
        
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_categories} {$where} ORDER BY sort_order ASC"
        );
    }
    
    /**
     * 获取单个类别
     */
    public function get_category($name) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_categories} WHERE name = %s",
                $name
            )
        );
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
        if ($user_id !== null) {
            $stats['total_items'] = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_items} WHERE user_id = %d AND status = 'active'",
                    $user_id
                )
            );
        } else {
            // 管理员查看所有物品
            $stats['total_items'] = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_items} WHERE status = 'active'"
            );
        }

        // 按类别统计
        if ($user_id !== null) {
            $category_stats = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT category, COUNT(*) as count FROM {$this->table_items} WHERE user_id = %d AND status = 'active' GROUP BY category",
                    $user_id
                )
            );
        } else {
            // 管理员查看所有物品
            $category_stats = $this->wpdb->get_results(
                "SELECT category, COUNT(*) as count FROM {$this->table_items} WHERE status = 'active' GROUP BY category"
            );
        }
        
        $stats['by_category'] = array();
        foreach ($category_stats as $stat) {
            $stats['by_category'][$stat->category] = $stat->count;
        }
        
        // 移除了即将过保统计
        
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
        $data['created_at'] = current_time('mysql');

        $result = $this->wpdb->insert($this->table_categories, $data);

        if ($result === false) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * 更新类别
     */
    public function update_category($name, $data) {
        $where = array('name' => $name);

        $result = $this->wpdb->update($this->table_categories, $data, $where);

        return $result !== false;
    }

    /**
     * 删除类别
     */
    public function delete_category($name) {
        $result = $this->wpdb->delete($this->table_categories, array('name' => $name));

        return $result !== false;
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
                $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");
                if ($result === false) {
                    wt_log_error("Failed to drop table {$table}: " . $wpdb->last_error, 'time-capsule-db');
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
}

