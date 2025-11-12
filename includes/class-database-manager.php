<?php
/**
 * WordPress Toolkit - 数据库管理类
 * 统一的数据库操作接口
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Database_Manager {

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * 获取完整的表名
     *
     * @param string $table 表名（不含前缀）
     * @return string
     */
    public function get_table_name($table) {
        return $this->wpdb->prefix . $table;
    }

    /**
     * 根据字段查询单条记录
     *
     * @param string $table 表名
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $columns 要查询的列
     * @return object|null
     */
    public function get_by_field($table, $field, $value, $columns = '*') {
        $table_name = $this->get_table_name($table);

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT {$columns} FROM {$table_name} WHERE {$field} = %s LIMIT 1",
            $value
        ));
    }

    /**
     * 根据ID查询单条记录
     *
     * @param string $table 表名
     * @param int $id ID
     * @param string $columns 要查询的列
     * @return object|null
     */
    public function get_by_id($table, $id, $columns = '*') {
        $table_name = $this->get_table_name($table);

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT {$columns} FROM {$table_name} WHERE id = %d LIMIT 1",
            $id
        ));
    }

    /**
     * 根据条件查询多条记录
     *
     * @param string $table 表名
     * @param array $where WHERE条件
     * @param string $columns 要查询的列
     * @param string $order_by 排序字段
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function get_results($table, $where = [], $columns = '*', $order_by = 'id DESC', $limit = null, $offset = 0) {
        $table_name = $this->get_table_name($table);

        $where_clause = $this->build_where_clause($where);
        $limit_clause = $limit ? "LIMIT {$offset}, {$limit}" : '';

        $sql = "SELECT {$columns} FROM {$table_name} {$where_clause} ORDER BY {$order_by} {$limit_clause}";

        return $this->wpdb->get_results($sql);
    }

    /**
     * 根据条件查询单行单列值
     *
     * @param string $table 表名
     * @param string $column 字段名
     * @param array $where WHERE条件
     * @return mixed|null
     */
    public function get_var($table, $column, $where = []) {
        $table_name = $this->get_table_name($table);
        $where_clause = $this->build_where_clause($where);

        return $this->wpdb->get_var("SELECT {$column} FROM {$table_name} {$where_clause} LIMIT 1");
    }

    /**
     * 插入记录
     *
     * @param string $table 表名
     * @param array $data 数据
     * @param string $format 数据格式
     * @return int|false 插入的ID或false
     */
    public function insert($table, $data, $format = null) {
        $table_name = $this->get_table_name($table);

        // 自动添加时间戳
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }

        $result = $this->wpdb->insert($table_name, $data, $format);

        if ($result !== false) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    /**
     * 更新记录
     *
     * @param string $table 表名
     * @param array $data 更新数据
     * @param array $where WHERE条件
     * @param string $format 数据格式
     * @param string $where_format WHERE格式
     * @return int|false 影响的行数或false
     */
    public function update($table, $data, $where, $format = null, $where_format = null) {
        $table_name = $this->get_table_name($table);

        // 自动更新时间戳
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }

        return $this->wpdb->update($table_name, $data, $where, $format, $where_format);
    }

    /**
     * 替换记录（先删除再插入）
     *
     * @param string $table 表名
     * @param array $data 数据
     * @param string $format 数据格式
     * @return int|false 影响的行数或false
     */
    public function replace($table, $data, $format = null) {
        $table_name = $this->get_table_name($table);

        // 自动添加时间戳
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }

        return $this->wpdb->replace($table_name, $data, $format);
    }

    /**
     * 删除记录
     *
     * @param string $table 表名
     * @param array $where WHERE条件
     * @param string $where_format WHERE格式
     * @return int|false 影响的行数或false
     */
    public function delete($table, $where, $where_format = null) {
        $table_name = $this->get_table_name($table);
        return $this->wpdb->delete($table_name, $where, $where_format);
    }

    /**
     * 根据ID删除记录
     *
     * @param string $table 表名
     * @param int $id ID
     * @return int|false 影响的行数或false
     */
    public function delete_by_id($table, $id) {
        return $this->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * 统计记录数量
     *
     * @param string $table 表名
     * @param array $where WHERE条件
     * @param string $column 统计列（默认为id）
     * @return int
     */
    public function count($table, $where = [], $column = 'id') {
        $table_name = $this->get_table_name($table);
        $where_clause = $this->build_where_clause($where);

        return (int) $this->wpdb->get_var("SELECT COUNT({$column}) FROM {$table_name} {$where_clause}");
    }

    /**
     * 检查记录是否存在
     *
     * @param string $table 表名
     * @param array $where WHERE条件
     * @return bool
     */
    public function exists($table, $where) {
        return $this->count($table, $where) > 0;
    }

    /**
     * 获取最大值
     *
     * @param string $table 表名
     * @param string $column 字段名
     * @param array $where WHERE条件
     * @return mixed
     */
    public function max($table, $column, $where = []) {
        $table_name = $this->get_table_name($table);
        $where_clause = $this->build_where_clause($where);

        return $this->wpdb->get_var("SELECT MAX({$column}) FROM {$table_name} {$where_clause}");
    }

    /**
     * 获取最小值
     *
     * @param string $table 表名
     * @param string $column 字段名
     * @param array $where WHERE条件
     * @return mixed
     */
    public function min($table, $column, $where = []) {
        $table_name = $this->get_table_name($table);
        $where_clause = $this->build_where_clause($where);

        return $this->wpdb->get_var("SELECT MIN({$column}) FROM {$table_name} {$where_clause}");
    }

    /**
     * 获取平均值
     *
     * @param string $table 表名
     * @param string $column 字段名
     * @param array $where WHERE条件
     * @return float
     */
    public function avg($table, $column, $where = []) {
        $table_name = $this->get_table_name($table);
        $where_clause = $this->build_where_clause($where);

        return (float) $this->wpdb->get_var("SELECT AVG({$column}) FROM {$table_name} {$where_clause}");
    }

    /**
     * 获取总和
     *
     * @param string $table 表名
     * @param string $column 字段名
     * @param array $where WHERE条件
     * @return float
     */
    public function sum($table, $column, $where = []) {
        $table_name = $this->get_table_name($table);
        $where_clause = $this->build_where_clause($where);

        return (float) $this->wpdb->get_var("SELECT SUM({$column}) FROM {$table_name} {$where_clause}");
    }

    /**
     * 执行自定义查询
     *
     * @param string $sql SQL语句
     * @return mixed
     */
    public function query($sql) {
        return $this->wpdb->query($sql);
    }

    /**
     * 获取查询结果
     *
     * @param string $sql SQL语句
     * @return array
     */
    public function get_results_sql($sql) {
        return $this->wpdb->get_results($sql);
    }

    /**
     * 获取单行结果
     *
     * @param string $sql SQL语句
     * @return object|null
     */
    public function get_row_sql($sql) {
        return $this->wpdb->get_row($sql);
    }

    /**
     * 获取单个值
     *
     * @param string $sql SQL语句
     * @return mixed|null
     */
    public function get_var_sql($sql) {
        return $this->wpdb->get_var($sql);
    }

    /**
     * 开始事务
     */
    public function start_transaction() {
        $this->wpdb->query('START TRANSACTION');
    }

    /**
     * 提交事务
     */
    public function commit() {
        $this->wpdb->query('COMMIT');
    }

    /**
     * 回滚事务
     */
    public function rollback() {
        $this->wpdb->query('ROLLBACK');
    }

    /**
     * 获取最后的插入ID
     *
     * @return int
     */
    public function get_insert_id() {
        return $this->wpdb->insert_id;
    }

    /**
     * 获取最后查询的错误信息
     *
     * @return string
     */
    public function get_last_error() {
        return $this->wpdb->last_error;
    }

    /**
     * 获取最后执行的查询
     *
     * @return string
     */
    public function get_last_query() {
        return $this->wpdb->last_query;
    }

    /**
     * 构建WHERE子句
     *
     * @param array $where WHERE条件
     * @return string
     */
    private function build_where_clause($where) {
        if (empty($where)) {
            return '';
        }

        $conditions = [];
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                // IN查询
                $placeholders = implode(',', array_fill(0, count($value), '%s'));
                $conditions[] = $this->wpdb->prepare("{$field} IN ({$placeholders})", $value);
            } else {
                // 普通查询
                $conditions[] = $this->wpdb->prepare("{$field} = %s", $value);
            }
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }
}