<?php
/**
 * Category Optimization Module - 分类优化模块
 *
 * 通过AI分析分类下的文章，为分类生成描述
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Category Optimization Module 主类
 */
class Category_Optimization_Module {

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

        $saved_settings = get_option('wordpress_ai_toolkit_category_optimization_settings', array());
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
        add_action('wp_ajax_category_optimization_generate_description', array($this, 'ajax_generate_description'));
        add_action('wp_ajax_category_optimization_batch_generate', array($this, 'ajax_batch_generate'));
        add_action('wp_ajax_category_optimization_get_categories_list', array($this, 'ajax_get_categories_list'));
        add_action('wp_ajax_category_optimization_get_statistics', array($this, 'ajax_get_statistics'));

        // 前端脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * 激活模块
     */
    public function activate() {


        try {
            // 创建默认设置（仅在不存在时）
            if (!get_option('wordpress_ai_toolkit_category_optimization_settings')) {
                add_option('wordpress_ai_toolkit_category_optimization_settings', $this->settings);

            } else {

            }



        } catch (Exception $e) {

        }
    }

    /**
     * 停用模块
     */
    public function deactivate() {
        // 清理缓存
        wp_cache_flush();

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
            'settings_page_wordpress-ai-toolkit-category-optimization-settings',
            'admin_page_wordpress-ai-toolkit-category-optimization',
            'toplevel_page_wordpress-ai-toolkit'
        ];

        if (in_array($hook, $valid_pages)) {
            // 使用统一的模块CSS
            wp_enqueue_style(
                'wordpress-ai-toolkit-modules-admin',
                AI_CONTENT_TOOLKIT_PLUGIN_URL . 'assets/css/modules-admin.css',
                array('wordpress-ai-toolkit-admin'),
                AI_CONTENT_TOOLKIT_VERSION
            );

            // 加载统一的模块JavaScript
            wp_enqueue_script(
                'wordpress-ai-toolkit-modules-admin',
                AI_CONTENT_TOOLKIT_PLUGIN_URL . 'assets/js/modules-admin.js',
                array('jquery', 'toolkit-core'),
                AI_CONTENT_TOOLKIT_VERSION . '.' . time(), // 添加时间戳强制刷新缓存
                true
            );
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
        update_option('wordpress_ai_toolkit_category_optimization_settings', $this->settings);
    }

    /**
     * 获取分类列表
     */
    public function get_categories_list($page = 1, $per_page = 20, $status = 'all') {


        // 获取所有分类
        $args = array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'count',
            'order' => 'DESC'
        );

        $categories = get_terms($args);
        $total_categories = wp_count_terms('category', array('hide_empty' => false));

        $filtered_categories = array();

        foreach ($categories as $category) {
            $has_description = !empty($category->description);

            // 根据状态筛选
            if ($status === 'with_description' && !$has_description) {
                continue;
            } elseif ($status === 'without_description' && $has_description) {
                continue;
            }

            // 获取分类下的文章数量
            $post_count = $category->count;

            $filtered_categories[] = array(
                'ID' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'description_length' => mb_strlen($category->description),
                'post_count' => $post_count,
                'has_description' => $has_description,
                'edit_url' => get_edit_term_link($category->term_id, 'category'),
                'view_url' => get_term_link($category->term_id, 'category')
            );
        }

        $total_filtered = count($filtered_categories);
        $max_pages = ceil($total_categories / $per_page);



        return array(
            'categories' => $filtered_categories,
            'total' => $total_categories,
            'pages' => $max_pages,
            'current_page' => $page,
            'per_page' => $per_page
        );
    }

    /**
     * 获取统计信息
     */
    public function get_statistics() {


        $total_categories = wp_count_terms('category', array('hide_empty' => false));

        // 获取有描述和无描述的分类数量
        $categories_with_description = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => false
        ));

        $categories_with_description_count = 0;
        if (!is_wp_error($categories_with_description)) {
            foreach ($categories_with_description as $category) {
                if (!empty($category->description)) {
                    $categories_with_description_count++;
                }
            }
        }

        $categories_without_description_count = $total_categories - $categories_with_description_count;
        $coverage_rate = $total_categories > 0 ? round(($categories_with_description_count / $total_categories) * 100, 2) : 0;



        return array(
            'total_categories' => $total_categories,
            'categories_with_description' => $categories_with_description_count,
            'categories_without_description' => $categories_without_description_count,
            'coverage_rate' => $coverage_rate
        );
    }

    /**
     * 使用AI为分类生成描述
     */
    public function generate_category_description($category_id) {
        // 检查AI功能是否可用
        if (!function_exists('wordpress_ai_toolkit_is_ai_available') || !wordpress_ai_toolkit_is_ai_available()) {
            return array('success' => false, 'message' => __('AI功能未配置，请先配置AI服务', 'wordpress-ai-toolkit'));
        }

        try {
            $category = get_term($category_id, 'category');
            if (!$category) {
                return array('success' => false, 'message' => __('分类不存在', 'wordpress-ai-toolkit'));
            }

            // 获取使用该分类的文章
            $posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $this->settings['analyze_articles_count'],
                'category' => $category->term_id,
                'orderby' => 'date',
                'order' => 'DESC'
            ));

            if (empty($posts)) {
                return array('success' => false, 'message' => __('该分类下没有文章', 'wordpress-ai-toolkit'));
            }

            // 分析文章内容
            $articles_content = '';
            $keywords = array();

            foreach ($posts as $post) {
                $articles_content .= "文章标题：{$post->post_title}\n";
                $articles_content .= "文章内容：" . mb_substr(strip_tags($post->post_content), 0, 300) . "\n\n";

                // 提取关键词
                $content = ($post->post_title ?? '') . ' ' . ($post->post_content ?? '');
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

            // 获取高频关键词（排除分类本身）
            unset($keywords[$category->name]);
            arsort($keywords);
            $top_keywords = array_slice(array_keys($keywords), 0, 8);
            $keywords_text = implode('、', $top_keywords);

            // 构建AI提示词 - 使用动态提示词设置
            if (function_exists('wordpress_ai_toolkit_get_prompt')) {
                $prompt = wordpress_ai_toolkit_get_prompt('category_optimization', array(
                    'category_name' => $category->name,
                    'category_content' => $articles_content,
                    'keywords' => $keywords_text
                ));
            } else {
                // 降级到默认提示词
                $prompt = "请为以下分类生成一个简洁准确的描述：

分类名称：{$category->name}

使用该分类的文章主要内容：
{$articles_content}

相关关键词：{$keywords_text}

请返回一个1-2句话的分类描述，要求：
1. 准确概括该分类的用途和含义
2. 语言简洁明了，适合用户理解
3. 30-60字之间
4. 只返回描述内容，不要包含其他解释";
            }

            // 调用AI服务
            $response = wordpress_ai_toolkit_call_ai_api(
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
                        'message' => sprintf(__('成功为分类"%s"生成描述', 'wordpress-ai-toolkit'), $category->name),
                        'description' => $description,
                        'category_id' => $category_id,
                        'category_name' => $category->name
                    );
                } else {
                    return array('success' => false, 'message' => __('AI未能生成有效描述', 'wordpress-ai-toolkit'));
                }

            } else {
                return array('success' => false, 'message' => __('AI服务响应异常', 'wordpress-ai-toolkit'));
            }

        } catch (Exception $e) {

            return array('error' => __('AI生成分类描述失败：', 'wordpress-ai-toolkit') . $e->getMessage());
        }
    }

    /**
     * 应用分类描述
     */
    public function apply_category_description($category_id, $description) {
        if (!$category_id || empty($description)) {
            return array('success' => false, 'message' => __('参数无效', 'wordpress-ai-toolkit'));
        }

        $category = get_term($category_id, 'category');
        if (!$category) {
            return array('success' => false, 'message' => __('分类不存在', 'wordpress-ai-toolkit'));
        }

        try {
            // 更新分类描述
            wp_update_term($category_id, 'category', array(
                'description' => $description
            ));

            return array(
                'success' => true,
                'message' => __('分类描述更新成功', 'wordpress-ai-toolkit'),
                'category_id' => $category_id,
                'category_name' => $category->name
            );

        } catch (Exception $e) {

            return array('success' => false, 'message' => __('分类描述更新失败：', 'wordpress-ai-toolkit') . $e->getMessage());
        }
    }

    /**
     * 批量生成分类描述
     */
    public function batch_generate_descriptions() {


        // 检查是否启用AI生成
        if (!wordpress_ai_toolkit_is_ai_available()) {
            return array(
                'success' => false,
                'message' => __('AI生成功能未启用或未配置API密钥', 'wordpress-ai-toolkit')
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

            // 获取所有无描述的分类
            $categories = get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false
            ));

            // 过滤出无描述的分类
            $categories_without_description = array();
            foreach ($categories as $category) {
                if (empty($category->description)) {
                    $categories_without_description[] = $category;
                }
            }
            $categories = $categories_without_description;

            if (is_wp_error($categories)) {
                return array(
                    'success' => false,
                    'message' => __('获取分类列表失败：', 'wordpress-ai-toolkit') . $categories->get_error_message()
                );
            }

            // 过滤掉文章数量太少的分类
            $valid_categories = array();
            foreach ($categories as $category) {
                if ($category->count >= $this->settings['min_articles_count']) {
                    $valid_categories[] = $category;
                }
            }

            if (empty($valid_categories)) {
                return array(
                    'success' => true,
                    'message' => __('没有符合条件的分类需要处理', 'wordpress-ai-toolkit'),
                    'processed_count' => 0,
                    'success_count' => 0,
                    'error_count' => 0
                );
            }

            foreach ($valid_categories as $category) {
                if ((time() - $start_time) >= ($max_execution_time - 10)) {
                    break; // 避免超时
                }

                $processed_count++;

                try {
                    // 生成描述
                    $result = $this->generate_category_description($category->term_id);

                    if ($result && $result['success']) {
                        // 应用描述
                        $apply_result = $this->apply_category_description($category->term_id, $result['description']);

                        if ($apply_result && $apply_result['success']) {
                            $success_count++;

                        } else {
                            $error_count++;

                        }
                    } else {
                        $error_count++;

                    }
                } catch (Exception $e) {
                    $error_count++;

                }
            }

            return array(
                'success' => true,
                'processed_count' => $processed_count,
                'success_count' => $success_count,
                'error_count' => $error_count,
                'message' => sprintf(
                    __('批量生成分类描述完成！处理：%d个，成功：%d个，失败：%d个', 'wordpress-ai-toolkit'),
                    $processed_count,
                    $success_count,
                    $error_count
                )
            );

        } catch (Exception $e) {

            return array(
                'success' => false,
                'message' => __('批量生成分类描述失败：', 'wordpress-ai-toolkit') . $e->getMessage()
            );
        }
    }

    /**
     * AJAX处理生成分类描述
     */
    public function ajax_generate_description() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_nonce')) {
            wp_send_json_error(array('message' => '安全验证失败'));
            return;
        }

        // 清理输入数据
        $sanitized_data = WordPress_Toolkit_Security_Validator::sanitize_post_data([
            'category_id' => 'int'
        ]);
        $category_id = $sanitized_data['category_id'];

        // 验证必填字段
        $validation = WordPress_Toolkit_Security_Validator::validate_required_fields(
            ['category_id' => $category_id],
            ['category_id']
        );

        if (!$validation['valid']) {
            wp_send_json_error(array('message' => $validation['errors'][0]));
            return;
        }

        try {


            // 生成描述
            $result = $this->generate_category_description($category_id);

            if ($result['success']) {
                // 自动应用生成的描述
                $apply_result = $this->apply_category_description($category_id, $result['description']);

                if ($apply_result['success']) {
                    wp_send_json_success(array(
                        'category_id' => $category_id,
                        'category_name' => $result['category_name'],
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

            wp_send_json_error(array('message' => __('生成失败：', 'wordpress-ai-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX处理批量生成
     */
    public function ajax_batch_generate() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_batch')) {
            wp_send_json_error(array('message' => '安全验证失败'));
            return;
        }

        try {

            $result = $this->batch_generate_descriptions();

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }

        } catch (Exception $e) {

            wp_send_json_error(array('message' => __('批量生成失败：', 'wordpress-ai-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取分类列表
     */
    public function ajax_get_categories_list() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_nonce')) {
            wp_send_json_error(array('message' => '安全验证失败'));
            return;
        }

        try {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';

            $categories_list = $this->get_categories_list($page, $per_page, $status);

            wp_send_json_success($categories_list);

        } catch (Exception $e) {

            wp_send_json_error(array('message' => __('获取分类列表失败：', 'wordpress-ai-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取统计信息
     */
    public function ajax_get_statistics() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_nonce')) {
            wp_send_json_error(array('message' => '安全验证失败'));
            return;
        }

        try {
            $statistics = $this->get_statistics();
            wp_send_json_success($statistics);

        } catch (Exception $e) {

            wp_send_json_error(array('message' => __('获取统计信息失败：', 'wordpress-ai-toolkit') . $e->getMessage()));
        }
    }

    /**
     * 显示管理页面
     */
    public function admin_page() {
        // 加载管理页面模板
        require_once AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/category-optimization/admin/admin-page.php';
        $admin_page = Category_Optimization_Admin_Page::get_instance();
        $admin_page->admin_page();
    }
}

// 注册插件激活和停用钩子
register_activation_hook(__FILE__, array('Category_Optimization_Module', 'activate'));
register_deactivation_hook(__FILE__, array('Category_Optimization_Module', 'deactivate'));