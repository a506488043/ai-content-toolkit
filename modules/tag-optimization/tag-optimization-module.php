<?php
/**
 * Tag Optimization Module - 标签优化模块
 *
 * 通过AI分析标签下的文章，为标签生成描述
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag Optimization Module 主类
 */
class Tag_Optimization_Module {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 模块设置
     */
    private $settings = array();

    /**
     * 数据库管理器实例
     */
    private $db_manager = null;

    /**
     * 缓存管理器实例
     */
    private $cache_manager = null;

    /**
     * 构造函数
     */
    private function __construct() {
        $this->db_manager = new WordPress_Toolkit_Database_Manager();
        $this->cache_manager = new WordPress_Toolkit_Cache_Manager();
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * 加载设置
     */
    private function load_settings() {
        $default_settings = array(
            'auto_generate' => true,
            'description_length' => 100,
            'analyze_articles_count' => 10,
            'min_articles_count' => 3
        );

        $saved_settings = get_option('wordpress_toolkit_tag_optimization_settings', array());
        $this->settings = wp_parse_args($saved_settings, $default_settings);
    }

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // WordPress后台脚本和样式（仅在管理页面加载）
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // AJAX处理
        add_action('wp_ajax_tag_optimization_generate_description', array($this, 'ajax_generate_description'));
        add_action('wp_ajax_tag_optimization_batch_generate', array($this, 'ajax_batch_generate'));
        add_action('wp_ajax_tag_optimization_get_tags_list', array($this, 'ajax_get_tags_list'));
        add_action('wp_ajax_tag_optimization_get_statistics', array($this, 'ajax_get_statistics'));

        // 前端脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * 激活模块
     */
    public function activate() {
        error_log('Tag Optimization: Starting module activation');

        try {
            // 创建默认设置（仅在不存在时）
            if (!get_option('wordpress_toolkit_tag_optimization_settings')) {
                add_option('wordpress_toolkit_tag_optimization_settings', $this->settings);
                error_log('Tag Optimization: Default settings created');
            } else {
                error_log('Tag Optimization: Settings already exist, skipping creation');
            }

            error_log('Tag Optimization: Module activated successfully');

        } catch (Exception $e) {
            error_log('Tag Optimization: Activation error: ' . $e->getMessage());
        }
    }

    /**
     * 停用模块
     */
    public function deactivate() {
        // 清理缓存
        wp_cache_flush();
        error_log('Tag Optimization: Module deactivated');
    }

    /**
     * 初始化模块
     */
    public function init() {
        // 模块初始化逻辑
    }

    /**
     * 加载管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 只在相关页面加载统一脚本和样式
        $valid_pages = [
            'settings_page_wordpress-toolkit-tag-optimization-settings',
            'admin_page_wordpress-toolkit-tag-optimization',
            'toplevel_page_wordpress-toolkit'
        ];

        if (in_array($hook, $valid_pages)) {
            // 使用统一的模块CSS
            wp_enqueue_style(
                'wordpress-toolkit-modules-admin',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/modules-admin.css',
                array('wordpress-toolkit-admin'),
                WORDPRESS_TOOLKIT_VERSION
            );

            // 加载统一的模块JavaScript
            wp_enqueue_script(
                'wordpress-toolkit-modules-admin',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/js/modules-admin.js',
                array('jquery', 'wordpress-toolkit-core'),
                '1.0.0',
                true
            );

            // 传递配置到JavaScript
            wp_localize_script('wordpress-toolkit-modules-admin', 'TagOptimizationConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tag_optimization_batch'),
                'strings' => array(
                    'generating' => __('正在生成描述...', 'wordpress-toolkit'),
                    'generated' => __('描述已生成', 'wordpress-toolkit'),
                    'error' => __('生成失败，请重试', 'wordpress-toolkit'),
                    'noApiKey' => __('请先配置DeepSeek API密钥', 'wordpress-toolkit'),
                    'confirmApply' => __('是否要应用生成的描述？', 'wordpress-toolkit')
                ),
                'settings' => $this->settings
            ));
        }
    }

    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 前端功能脚本（如果需要）
    }

    /**
     * 获取设置
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * 更新设置
     */
    public function update_settings($new_settings) {
        $this->settings = wp_parse_args($new_settings, $this->settings);
        update_option('wordpress_toolkit_tag_optimization_settings', $this->settings);
    }

    /**
     * 获取标签列表
     */
    public function get_tags_list($page = 1, $per_page = 20, $status = 'all') {
        error_log("Tag Optimization: get_tags_list called with page=$page, per_page=$per_page, status=$status");

        // 获取所有标签
        $args = array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'count',
            'order' => 'DESC'
        );

        $tags = get_terms($args);
        $total_tags = wp_count_terms('post_tag', array('hide_empty' => false));

        $filtered_tags = array();

        foreach ($tags as $tag) {
            $has_description = !empty($tag->description);

            // 根据状态筛选
            if ($status === 'with_description' && !$has_description) {
                continue;
            } elseif ($status === 'without_description' && $has_description) {
                continue;
            }

            // 获取标签下的文章数量
            $post_count = $tag->count;

            $filtered_tags[] = array(
                'ID' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description,
                'description_length' => mb_strlen($tag->description),
                'post_count' => $post_count,
                'has_description' => $has_description,
                'edit_url' => get_edit_term_link($tag->term_id, 'post_tag'),
                'view_url' => get_term_link($tag->term_id, 'post_tag')
            );
        }

        $total_filtered = count($filtered_tags);
        $max_pages = ceil($total_tags / $per_page);

        error_log("Tag Optimization: Found $total_filtered tags matching status='$status'");

        return array(
            'tags' => $filtered_tags,
            'total' => $total_tags,
            'pages' => $max_pages,
            'current_page' => $page,
            'per_page' => $per_page
        );
    }

    /**
     * 获取统计信息
     */
    public function get_statistics() {
        error_log("Tag Optimization: get_statistics called");

        $total_tags = wp_count_terms('post_tag', array('hide_empty' => false));

        // 获取有描述和无描述的标签数量
        $tags_with_description = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => 'description',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));

        $tags_with_description_count = is_wp_error($tags_with_description) ? 0 : count($tags_with_description);
        $tags_without_description_count = $total_tags - $tags_with_description_count;
        $coverage_rate = $total_tags > 0 ? round(($tags_with_description_count / $total_tags) * 100, 2) : 0;

        error_log("Tag Optimization: Stats - Total: $total_tags, With: $tags_with_description_count, Without: $tags_without_description_count");

        return array(
            'total_tags' => $total_tags,
            'tags_with_description' => $tags_with_description_count,
            'tags_without_description' => $tags_without_description_count,
            'coverage_rate' => $coverage_rate
        );
    }

    /**
     * 使用AI为标签生成描述
     */
    public function generate_tag_description($tag_id) {
        // 检查AI功能是否可用
        if (!function_exists('wordpress_toolkit_is_ai_available') || !wordpress_toolkit_is_ai_available()) {
            return array('success' => false, 'message' => __('AI功能未配置，请先配置AI服务', 'wordpress-toolkit'));
        }

        try {
            $tag = get_term($tag_id, 'post_tag');
            if (!$tag) {
                return array('success' => false, 'message' => __('标签不存在', 'wordpress-toolkit'));
            }

            // 获取使用该标签的文章
            $posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $this->settings['analyze_articles_count'],
                'tag' => $tag->slug,
                'orderby' => 'date',
                'order' => 'DESC'
            ));

            if (empty($posts)) {
                return array('success' => false, 'message' => __('该标签下没有文章', 'wordpress-toolkit'));
            }

            // 分析文章内容
            $articles_content = '';
            $keywords = array();

            foreach ($posts as $post) {
                $articles_content .= "文章标题：{$post->post_title}\n";
                $articles_content .= "文章内容：" . mb_substr(strip_tags($post->post_content), 0, 300) . "\n\n";

                // 提取关键词
                $content = $post->post_title . ' ' . $post->post_content;
                $words = preg_split('/[\s，。！？；：""\'\'（）【】]/u', $content);
                foreach ($words as $word) {
                    $word = trim($word);
                    if (mb_strlen($word) >= 2 && mb_strlen($word) <= 6 && !preg_match('/[0-9]/', $word)) {
                        if (isset($keywords[$word])) {
                            $keywords[$word]++;
                        } else {
                            $keywords[$word] = 1;
                        }
                    }
                }
            }

            // 获取高频关键词（排除标签本身）
            unset($keywords[$tag->name]);
            arsort($keywords);
            $top_keywords = array_slice(array_keys($keywords), 0, 8);
            $keywords_text = implode('、', $top_keywords);

            // 构建AI提示词
            $prompt = "请为以下标签生成一个简洁准确的描述：

标签名称：{$tag->name}

使用该标签的文章主要内容：
{$articles_content}

相关关键词：{$keywords_text}

请返回一个1-2句话的标签描述，要求：
1. 准确概括该标签的用途和含义
2. 语言简洁明了，适合用户理解
3. 30-60字之间
4. 只返回描述内容，不要包含其他解释";

            // 调用AI服务
            $response = wordpress_toolkit_call_deepseek_api(
                $prompt,
                array(
                    'max_tokens' => 100,
                    'temperature' => 0.3
                )
            );

            if (!is_wp_error($response) && !empty($response)) {
                $description = trim($response);

                // 清理描述
                $description = preg_replace('/[""\'\']/', '', $description);
                $description = preg_replace('/[\r\n]+/', ' ', $description);
                $description = trim($description);

                if (!empty($description)) {
                    return array(
                        'success' => true,
                        'message' => sprintf(__('成功为标签"%s"生成描述', 'wordpress-toolkit'), $tag->name),
                        'description' => $description,
                        'tag_id' => $tag_id,
                        'tag_name' => $tag->name
                    );
                } else {
                    return array('success' => false, 'message' => __('AI未能生成有效描述', 'wordpress-toolkit'));
                }

            } else {
                return array('success' => false, 'message' => __('AI服务响应异常', 'wordpress-toolkit'));
            }

        } catch (Exception $e) {
            error_log("Tag Optimization: AI tag description error: " . $e->getMessage());
            return array('error' => __('AI生成标签描述失败：', 'wordpress-toolkit') . $e->getMessage());
        }
    }

    /**
     * 应用标签描述
     */
    public function apply_tag_description($tag_id, $description) {
        if (!$tag_id || empty($description)) {
            return array('success' => false, 'message' => __('参数无效', 'wordpress-toolkit'));
        }

        $tag = get_term($tag_id, 'post_tag');
        if (!$tag) {
            return array('success' => false, 'message' => __('标签不存在', 'wordpress-toolkit'));
        }

        try {
            // 更新标签描述
            wp_update_term($tag_id, 'post_tag', array(
                'description' => $description
            ));

            return array(
                'success' => true,
                'message' => __('标签描述更新成功', 'wordpress-toolkit'),
                'tag_id' => $tag_id,
                'tag_name' => $tag->name
            );

        } catch (Exception $e) {
            error_log("Tag Optimization: Apply tag description error: " . $e->getMessage());
            return array('success' => false, 'message' => __('标签描述更新失败：', 'wordpress-toolkit') . $e->getMessage());
        }
    }

    /**
     * 批量生成标签描述
     */
    public function batch_generate_descriptions() {
        error_log('Tag Optimization: Starting batch description generation');

        // 检查是否启用AI生成
        if (!wordpress_toolkit_is_ai_available()) {
            return array(
                'success' => false,
                'message' => __('AI生成功能未启用或未配置API密钥', 'wordpress-toolkit')
            );
        }

        try {
            $max_execution_time = ini_get('max_execution_time');
            // 增加执行时间限制到600秒（10分钟），如果允许的话
            if ($max_execution_time < 600) {
                @set_time_limit(600);
                $max_execution_time = 600;
            }
            $start_time = time();
            $processed_count = 0;
            $success_count = 0;
            $error_count = 0;

            // 获取所有无描述的标签
            $tags = get_terms(array(
                'taxonomy' => 'post_tag',
                'hide_empty' => false
            ));

            // 过滤出无描述的标签
            $tags_without_description = array();
            foreach ($tags as $tag) {
                if (empty($tag->description)) {
                    $tags_without_description[] = $tag;
                }
            }
            $tags = $tags_without_description;

            if (is_wp_error($tags)) {
                return array(
                    'success' => false,
                    'message' => __('获取标签列表失败：', 'wordpress-toolkit') . $tags->get_error_message()
                );
            }

            // 过滤掉文章数量太少的标签
            $valid_tags = array();
            foreach ($tags as $tag) {
                if ($tag->count >= $this->settings['min_articles_count']) {
                    $valid_tags[] = $tag;
                }
            }

            if (empty($valid_tags)) {
                return array(
                    'success' => true,
                    'message' => __('没有符合条件的标签需要处理', 'wordpress-toolkit'),
                    'processed_count' => 0,
                    'success_count' => 0,
                    'error_count' => 0
                );
            }

            foreach ($valid_tags as $tag) {
                if ((time() - $start_time) >= ($max_execution_time - 10)) {
                    break; // 避免超时
                }

                $processed_count++;

                try {
                    // 生成描述
                    $result = $this->generate_tag_description($tag->term_id);

                    if ($result && $result['success']) {
                        // 应用描述
                        $apply_result = $this->apply_tag_description($tag->term_id, $result['description']);

                        if ($apply_result && $apply_result['success']) {
                            $success_count++;
                            error_log("Tag Optimization: Generated description for tag ID: {$tag->term_id}");
                        } else {
                            $error_count++;
                            error_log("Tag Optimization: Failed to apply description for tag ID: {$tag->term_id}");
                        }
                    } else {
                        $error_count++;
                        error_log("Tag Optimization: No description generated for tag ID: {$tag->term_id}");
                    }
                } catch (Exception $e) {
                    $error_count++;
                    error_log("Tag Optimization: Error processing tag ID {$tag->term_id}: " . $e->getMessage());
                }
            }

            return array(
                'success' => true,
                'processed_count' => $processed_count,
                'success_count' => $success_count,
                'error_count' => $error_count,
                'message' => sprintf(
                    __('批量生成标签描述完成！处理：%d个，成功：%d个，失败：%d个', 'wordpress-toolkit'),
                    $processed_count,
                    $success_count,
                    $error_count
                )
            );

        } catch (Exception $e) {
            error_log('Tag Optimization: Batch description generation error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('批量生成标签描述失败：', 'wordpress-toolkit') . $e->getMessage()
            );
        }
    }

    /**
     * AJAX处理生成标签描述
     */
    public function ajax_generate_description() {
        // 使用统一的安全验证
        if (!WordPress_Toolkit_Security_Validator::verify_admin_ajax('tag_optimization_nonce')) {
            return;
        }

        // 清理输入数据
        $sanitized_data = WordPress_Toolkit_Security_Validator::sanitize_post_data([
            'tag_id' => 'int'
        ]);
        $tag_id = $sanitized_data['tag_id'];

        // 验证必填字段
        $validation = WordPress_Toolkit_Security_Validator::validate_required_fields(
            ['tag_id' => $tag_id],
            ['tag_id']
        );

        if (!$validation['valid']) {
            wp_send_json_error(array('message' => $validation['errors'][0]));
            return;
        }

        try {
            error_log("Tag Optimization: Processing single tag ID: {$tag_id}");

            // 生成描述
            $result = $this->generate_tag_description($tag_id);

            if ($result['success']) {
                // 自动应用生成的描述
                $apply_result = $this->apply_tag_description($tag_id, $result['description']);

                if ($apply_result['success']) {
                    wp_send_json_success(array(
                        'tag_id' => $tag_id,
                        'tag_name' => $result['tag_name'],
                        'description' => $result['description'],
                        'message' => $apply_result['message']
                    ));
                } else {
                    wp_send_json_error(array('message' => $apply_result['message']));
                }
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }

        } catch (Exception $e) {
            error_log("Tag Optimization: Single tag generation error for ID {$tag_id}: " . $e->getMessage());
            wp_send_json_error(array('message' => __('生成失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX处理批量生成
     */
    public function ajax_batch_generate() {
        // 使用统一的安全验证
        if (!WordPress_Toolkit_Security_Validator::verify_admin_ajax('tag_optimization_batch')) {
            return;
        }

        try {
            error_log('Tag Optimization: Starting batch generation AJAX request');
            $result = $this->batch_generate_descriptions();

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }

        } catch (Exception $e) {
            error_log('Tag Optimization: Batch generation AJAX error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('批量生成失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取标签列表
     */
    public function ajax_get_tags_list() {
        // 使用统一的安全验证
        if (!WordPress_Toolkit_Security_Validator::verify_admin_ajax('tag_optimization_nonce')) {
            return;
        }

        try {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';

            $tags_list = $this->get_tags_list($page, $per_page, $status);

            wp_send_json_success($tags_list);

        } catch (Exception $e) {
            error_log('Tag Optimization: Get tags list AJAX error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('获取标签列表失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取统计信息
     */
    public function ajax_get_statistics() {
        // 使用统一的安全验证
        if (!WordPress_Toolkit_Security_Validator::verify_admin_ajax('tag_optimization_nonce')) {
            return;
        }

        try {
            $statistics = $this->get_statistics();
            wp_send_json_success($statistics);

        } catch (Exception $e) {
            error_log('Tag Optimization: Get statistics AJAX error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('获取统计信息失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * 显示管理页面
     */
    public function admin_page() {
        // 加载管理页面模板
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/tag-optimization/admin/admin-page.php';
        $admin_page = Tag_Optimization_Admin_Page::get_instance();
        $admin_page->admin_page();
    }
}

// 注册插件激活和停用钩子
register_activation_hook(__FILE__, array('Tag_Optimization_Module', 'activate'));
register_deactivation_hook(__FILE__, array('Tag_Optimization_Module', 'deactivate'));