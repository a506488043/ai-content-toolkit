<?php
/**
 * SEO分析数据库操作类
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Excerpt_SEO_Analyzer_Database {

    private $db_manager;
    private $table_seo_analysis;
    private $cache_manager;

    public function __construct() {
        $this->db_manager = new WordPress_Toolkit_Database_Manager();
        $this->cache_manager = new WordPress_Toolkit_Cache_Manager();
        $this->table_seo_analysis = 'auto_excerpt_seo_analysis';
    }

    /**
     * 创建SEO分析数据表
     */
    public function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // 创建SEO分析表
        $table_seo_analysis_sql = "CREATE TABLE IF NOT EXISTS {$this->table_seo_analysis} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            analysis_type varchar(20) NOT NULL DEFAULT 'seo',
            title_score decimal(5,2) DEFAULT 0.00 COMMENT '标题得分',
            content_score decimal(5,2) DEFAULT 0.00 COMMENT '内容得分',
            keyword_score decimal(5,2) DEFAULT 0.00 COMMENT '关键词得分',
            readability_score decimal(5,2) DEFAULT 0.00 COMMENT '可读性得分',
            overall_score decimal(5,2) DEFAULT 0.00 COMMENT '整体得分',
            analysis_data longtext DEFAULT NULL COMMENT '详细分析数据(JSON)',
            recommendations longtext DEFAULT NULL COMMENT '优化建议(JSON)',
            primary_keywords text DEFAULT NULL COMMENT '主要关键词',
            secondary_keywords text DEFAULT NULL COMMENT '次要关键词',
            raw_ai_analysis longtext DEFAULT NULL COMMENT 'AI原始完整分析文本',
            parsed_analysis longtext DEFAULT NULL COMMENT '解析后的AI分析数据(JSON)',
            word_count int(11) DEFAULT 0 COMMENT '字数统计',
            title_length int(11) DEFAULT 0 COMMENT '标题长度',
            meta_description_length int(11) DEFAULT 0 COMMENT '描述长度',
            image_count int(11) DEFAULT 0 COMMENT '图片数量',
            heading_counts text DEFAULT NULL COMMENT '标题标签统计(JSON)',
            internal_links int(11) DEFAULT 0 COMMENT '内部链接数',
            external_links int(11) DEFAULT 0 COMMENT '外部链接数',
            ai_provider varchar(50) DEFAULT 'deepseek' COMMENT 'AI提供商',
            ai_model varchar(100) DEFAULT NULL COMMENT 'AI模型',
            analysis_version varchar(20) DEFAULT '1.0' COMMENT '分析版本',
            analysis_time decimal(10,3) DEFAULT 0.000 COMMENT '分析耗时(秒)',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY post_id_analysis_type (post_id, analysis_type),
            KEY post_id (post_id),
            KEY analysis_type (analysis_type),
            KEY overall_score (overall_score),
            KEY created_at (created_at),
            KEY ai_provider (ai_provider)
        ) $charset_collate;";

        dbDelta($table_seo_analysis_sql);

        return true;
    }

    /**
     * 保存SEO分析结果
     */
    public function save_seo_analysis($post_id, $analysis_data) {
        global $wpdb;

        $data = array(
            'post_id' => intval($post_id),
            'analysis_type' => 'seo',
            'title_score' => floatval($analysis_data['title_score'] ?? 0),
            'content_score' => floatval($analysis_data['content_score'] ?? 0),
            'keyword_score' => floatval($analysis_data['keyword_score'] ?? 0),
            'readability_score' => floatval($analysis_data['readability_score'] ?? 0),
            'overall_score' => floatval($analysis_data['overall_score'] ?? 0),
            'analysis_data' => wp_json_encode($analysis_data),
            'recommendations' => wp_json_encode($analysis_data['ai_recommendations'] ?? array()),
            'primary_keywords' => maybe_serialize($analysis_data['ai_keywords'] ?? array()),
            'secondary_keywords' => maybe_serialize($analysis_data['ai_meta_info']['focus_keywords'] ?? array()),
            'word_count' => intval($analysis_data['word_count'] ?? 0),
            'title_length' => intval($analysis_data['title_length'] ?? 0),
            'meta_description_length' => intval($analysis_data['title_length'] ?? 0),
            'image_count' => intval($analysis_data['image_count'] ?? 0),
            'heading_counts' => wp_json_encode($analysis_data['heading_counts'] ?? array()),
            'internal_links' => intval($analysis_data['internal_links'] ?? 0),
            'external_links' => intval($analysis_data['external_links'] ?? 0),
            'raw_ai_analysis' => $analysis_data['raw_ai_analysis'] ?? '',
            'parsed_analysis' => wp_json_encode($analysis_data['ai_analysis'] ?? array()),
            'ai_provider' => sanitize_text_field($analysis_data['ai_provider'] ?? 'deepseek'),
            'ai_model' => sanitize_text_field($analysis_data['ai_model'] ?? ''),
            'analysis_version' => sanitize_text_field($analysis_data['analysis_version'] ?? '2.0'),
            'analysis_time' => floatval($analysis_data['analysis_time'] ?? 0),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $format = array(
            '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s',
            '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%f',
            '%s', '%s'
        );

        // 检查是否已存在分析记录
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_seo_analysis} WHERE post_id = %d AND analysis_type = %s",
            $post_id, 'seo'
        ));

        if ($existing) {
            // 更新现有记录
            $result = $wpdb->update(
                $this->table_seo_analysis,
                $data,
                array('post_id' => $post_id, 'analysis_type' => 'seo'),
                $format,
                array('%d', '%s')
            );
        } else {
            // 插入新记录
            $result = $wpdb->insert($this->table_seo_analysis, $data, $format);
        }

        return $result !== false;
    }

    /**
     * 获取SEO分析结果
     */
    public function get_seo_analysis($post_id, $analysis_type = 'seo') {
        // 使用缓存
        $cache_key = "seo_analysis_{$post_id}_{$analysis_type}";
        $cached_result = $this->cache_manager->get($cache_key, 'seo_analysis');

        if ($cached_result !== false) {
            return $cached_result;
        }

        // 使用自定义查询（多个条件）
        $table_name = $this->db_manager->get_table_name($this->table_seo_analysis);
        $result = $this->db_manager->get_row_sql(
            $this->db_manager->wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE post_id = %d AND analysis_type = %s",
                $post_id, $analysis_type
            )
        );

        if ($result) {
            // 解码JSON字段
            $result->analysis_data = json_decode($result->analysis_data, true);
            $result->recommendations = json_decode($result->recommendations, true);
            $result->heading_counts = json_decode($result->heading_counts, true);
            $result->primary_keywords = maybe_unserialize($result->primary_keywords);
            $result->secondary_keywords = maybe_unserialize($result->secondary_keywords);

            // 缓存结果
            $this->cache_manager->set($cache_key, $result, 3600, 'seo_analysis');
        }

        return $result;
    }

    /**
     * 获取所有SEO分析记录
     */
    public function get_all_seo_analyses($limit = 50, $offset = 0, $order_by = 'updated_at', $order = 'DESC') {
        global $wpdb;

        $allowed_orders = array('updated_at', 'created_at', 'overall_score', 'post_id', 'title_score');
        $allowed_directions = array('ASC', 'DESC');

        $order_by = in_array($order_by, $allowed_orders) ? $order_by : 'updated_at';
        $order = in_array(strtoupper($order), $allowed_directions) ? strtoupper($order) : 'DESC';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.post_title, p.post_status, p.post_type
             FROM {$this->table_seo_analysis} a
             LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
             WHERE a.analysis_type = %s
             ORDER BY a.{$order_by} {$order}
             LIMIT %d OFFSET %d",
            'seo', $limit, $offset
        ));

        // 解码JSON字段
        foreach ($results as $result) {
            $result->analysis_data = json_decode($result->analysis_data, true);
            $result->recommendations = json_decode($result->recommendations, true);
            $result->primary_keywords = maybe_unserialize($result->primary_keywords);
            $result->secondary_keywords = maybe_unserialize($result->secondary_keywords);
        }

        return $results;
    }

    /**
     * 获取统计信息
     */
    public function get_seo_statistics() {
        global $wpdb;

        $stats = array();

        // 总分析数
        $stats['total_analyses'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_seo_analysis} WHERE analysis_type = %s",
            'seo'
        ));

        // 平均得分
        $stats['average_score'] = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(overall_score) FROM {$this->table_seo_analysis} WHERE analysis_type = %s",
            'seo'
        ));

        // 得分分布
        $score_ranges = array(
            'excellent' => array(80, 100),
            'good' => array(60, 79),
            'average' => array(40, 59),
            'poor' => array(0, 39)
        );

        foreach ($score_ranges as $key => $range) {
            $stats['score_distribution'][$key] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_seo_analysis}
                 WHERE analysis_type = %s AND overall_score >= %f AND overall_score <= %f",
                'seo', $range[0], $range[1]
            ));
        }

        // 最近的分析
        $stats['recent_analyses'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_seo_analysis}
             WHERE analysis_type = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'seo'
        ));

        return $stats;
    }

    /**
     * 删除SEO分析记录
     */
    public function delete_seo_analysis($post_id, $analysis_type = 'seo') {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_seo_analysis} WHERE post_id = %d AND analysis_type = %s",
            $post_id, $analysis_type
        )) !== false;
    }

    /**
     * 清空所有SEO分析数据
     */
    public function clear_all_seo_analyses() {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_seo_analysis} WHERE analysis_type = %s",
            'seo'
        )) !== false;
    }

    /**
     * 获取需要分析的文章列表
     */
    public function get_posts_for_analysis($limit = 100, $post_type = 'post') {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, p.post_status, p.post_modified,
                    IFNULL(a.overall_score, -1) as last_score,
                    IFNULL(a.updated_at, '1970-01-01') as last_analysis_date
             FROM {$wpdb->posts} p
             LEFT JOIN {$this->table_seo_analysis} a ON p.ID = a.post_id AND a.analysis_type = %s
             WHERE p.post_type = %s AND p.post_status = 'publish'
             ORDER BY last_analysis_date ASC, p.post_modified DESC
             LIMIT %d",
            'seo', $post_type, $limit
        ));
    }

    /**
     * 更新分析记录
     */
    public function update_analysis_timestamp($post_id, $analysis_type = 'seo') {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_seo_analysis}
             SET updated_at = %s
             WHERE post_id = %d AND analysis_type = %s",
            current_time('mysql'), $post_id, $analysis_type
        )) !== false;
    }
}