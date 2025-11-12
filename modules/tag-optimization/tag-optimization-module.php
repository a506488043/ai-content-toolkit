<?php
/**
 * Tag Optimization Module
 * 标签优化模块 - AI生成标签描述
 *
 * @package WordPressToolkit
 * @subpackage TagOptimization
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Tag_Optimization_Module {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // AJAX处理函数 - 移除重复的get_tag_stats，现在在admin page中处理
        add_action('wp_ajax_cleanup_duplicate_tags', array($this, 'ajax_cleanup_duplicate_tags'));
        add_action('wp_ajax_export_tag_optimization_report', array($this, 'ajax_export_tag_optimization_report'));
        add_action('wp_ajax_reset_tag_optimization_stats', array($this, 'ajax_reset_tag_optimization_stats'));

        // Add settings
        add_action('admin_init', array($this, 'register_settings'));

        // Load admin page
        add_action('init', array($this, 'load_admin_page'));
    }

    /**
     * Initialize module
     */
    public function init() {
        $this->register_settings();
    }

    /**
     * Load admin page class
     */
    public function load_admin_page() {
        if (is_admin()) {
            // 加载AI设置辅助函数
            if (file_exists(WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/ai-settings/ai-settings-helper.php')) {
                require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/ai-settings/ai-settings-helper.php';
            }

            require_once dirname(__FILE__) . '/admin/admin-page.php';
            // Admin page class will auto-instantiate at the end of the file
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // 禁用模块级别的脚本加载，只在admin page中加载以避免重复
        return;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Currently no frontend scripts needed for tag optimization
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // DeepSeek API Key 设置已迁移到AI设置页面
        register_setting('wordpress_toolkit_tag_optimization', 'wordpress_toolkit_tag_optimization_settings');

        add_settings_section(
            'tag_optimization_section',
            __('Tag Optimization Settings', 'wordpress-toolkit'),
            array($this, 'settings_section_callback'),
            'wordpress-toolkit-tag-optimization'
        );

        add_settings_field(
            'deepseek_api_key',
            __('DeepSeek API Key', 'wordpress-toolkit'),
            array($this, 'deepseek_api_key_callback'),
            'wordpress-toolkit-tag-optimization',
            'tag_optimization_section'
        );
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure the AI optimization settings for tags.', 'wordpress-toolkit') . '</p>';
    }

    /**
     * DeepSeek API Key callback
     */
    public function deepseek_api_key_callback() {
        $api_key = wordpress_toolkit_get_ai_settings('deepseek_api_key', '');
        echo '<input type="text" name="wordpress_toolkit_deepseek_api_key" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="' . __('Enter your DeepSeek API Key', 'wordpress-toolkit') . '" />';
        echo '<p class="description">' . __('Used for AI-powered tag optimization.', 'wordpress-toolkit') . '</p>';
    }

    // 移除重复的ajax_get_tag_stats函数，现在在admin page中处理

    /**
     * AJAX清理重复标签
     */
    public function ajax_cleanup_duplicate_tags() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tag_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $cleaned = 0;
        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0
        ));

        if (!is_wp_error($tags)) {
            $tag_names = array();
            $to_delete = array();

            foreach ($tags as $tag) {
                $tag_name = strtolower(trim($tag->name));
                if (in_array($tag_name, $tag_names)) {
                    $to_delete[] = $tag->term_id;
                } else {
                    $tag_names[] = $tag_name;
                }
            }

            foreach ($to_delete as $tag_id) {
                $result = wp_delete_term($tag_id, 'post_tag');
                if (!is_wp_error($result)) {
                    $cleaned++;
                }
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('成功清理了 %d 个重复标签', 'wordpress-toolkit'), $cleaned),
            'cleaned' => $cleaned
        ));
    }

    /**
     * AJAX导出标签优化报告
     */
    public function ajax_export_tag_optimization_report() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tag_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0
        ));

        $report = array();
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $status = get_term_meta($tag->term_id, 'ai_optimization_status', true);
                $report[] = array(
                    'id' => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'description' => $tag->description,
                    'count' => $tag->count,
                    'status' => $status ? $status : 'pending'
                );
            }
        }

        wp_send_json_success(array(
            'report' => $report,
            'message' => __('报告生成成功', 'wordpress-toolkit')
        ));
    }

    /**
     * AJAX重置标签优化统计
     */
    public function ajax_reset_tag_optimization_stats() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tag_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        // 删除所有优化状态meta
        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0
        ));

        $reset = 0;
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $result = delete_term_meta($tag->term_id, 'ai_optimization_status');
                if ($result !== false) {
                    $reset++;
                }
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('成功重置了 %d 个标签的优化状态', 'wordpress-toolkit'), $reset),
            'reset' => $reset
        ));
    }
}

// 初始化模块
Tag_Optimization_Module::get_instance();