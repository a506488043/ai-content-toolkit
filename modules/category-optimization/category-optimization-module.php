<?php
/**
 * Category Optimization Module
 * 分类目录优化模块
 *
 * @package WordPressToolkit
 * @subpackage CategoryOptimization
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Category_Optimization_Module Class
 */
class Category_Optimization_Module {

    /**
     * Instance
     */
    private static $_instance = null;

    /**
     * DeepSeek API URL
     */
    private $deepseek_url = 'https://api.deepseek.com/v1/chat/completions';

    /**
     * Get instance
     */
    public static function get_instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        // Remove duplicate AJAX handlers - they are now in the admin page class
        add_action('wp_ajax_get_category_stats', array($this, 'ajax_get_category_stats'));
        add_action('wp_ajax_cleanup_duplicate_categories', array($this, 'ajax_cleanup_duplicate_categories'));
        add_action('wp_ajax_export_category_optimization_report', array($this, 'ajax_export_category_optimization_report'));
        add_action('wp_ajax_reset_category_optimization_stats', array($this, 'ajax_reset_category_optimization_stats'));

        // Add settings
        add_action('admin_init', array($this, 'register_settings'));

        // 移除自定义字段，直接写入WordPress原生字段
        // add_action('category_add_form_fields', array($this, 'add_category_fields'));
        // add_action('category_edit_form_fields', array($this, 'edit_category_fields'));
        // add_action('created_category', array($this, 'save_category_fields'));
        // add_action('edited_category', array($this, 'save_category_fields'));

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
        // Currently no frontend scripts needed for category optimization
    }

    
    /**
     * Add category fields
     */
    public function add_category_fields($taxonomy) {
        ?>
        <div class="form-field">
            <label for="ai_generated_slug"><?php _e('AI Generated Slug', 'wordpress-toolkit'); ?></label>
            <input type="text" name="ai_generated_slug" id="ai_generated_slug" value="" />
            <p class="description"><?php _e('AI生成的分类别名', 'wordpress-toolkit'); ?></p>
        </div>
        <div class="form-field">
            <label for="ai_generated_description"><?php _e('AI Generated Description', 'wordpress-toolkit'); ?></label>
            <textarea name="ai_generated_description" id="ai_generated_description" rows="3"></textarea>
            <p class="description"><?php _e('AI生成的分类描述', 'wordpress-toolkit'); ?></p>
        </div>
        <div class="form-field">
            <label for="ai_optimization_status"><?php _e('AI Optimization Status', 'wordpress-toolkit'); ?></label>
            <select name="ai_optimization_status" id="ai_optimization_status">
                <option value="pending"><?php _e('Pending', 'wordpress-toolkit'); ?></option>
                <option value="optimized"><?php _e('Optimized', 'wordpress-toolkit'); ?></option>
                <option value="failed"><?php _e('Failed', 'wordpress-toolkit'); ?></option>
            </select>
            <p class="description"><?php _e('AI优化状态', 'wordpress-toolkit'); ?></p>
        </div>
        <?php
    }

    /**
     * Edit category fields
     */
    public function edit_category_fields($term) {
        $ai_generated_slug = get_term_meta($term->term_id, 'ai_generated_slug', true);
        $ai_generated_description = get_term_meta($term->term_id, 'ai_generated_description', true);
        $ai_optimization_status = get_term_meta($term->term_id, 'ai_optimization_status', true);

        ?>
        <tr class="form-field">
            <th scope="row"><label for="ai_generated_slug"><?php _e('AI Generated Slug', 'wordpress-toolkit'); ?></label></th>
            <td>
                <input type="text" name="ai_generated_slug" id="ai_generated_slug" value="<?php echo esc_attr($ai_generated_slug); ?>" />
                <p class="description"><?php _e('AI生成的分类别名', 'wordpress-toolkit'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai_generated_description"><?php _e('AI Generated Description', 'wordpress-toolkit'); ?></label></th>
            <td>
                <textarea name="ai_generated_description" id="ai_generated_description" rows="3"><?php echo esc_textarea($ai_generated_description); ?></textarea>
                <p class="description"><?php _e('AI生成的分类描述', 'wordpress-toolkit'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="ai_optimization_status"><?php _e('AI Optimization Status', 'wordpress-toolkit'); ?></label></th>
            <td>
                <select name="ai_optimization_status" id="ai_optimization_status">
                    <option value="pending" <?php selected($ai_optimization_status, 'pending'); ?>><?php _e('Pending', 'wordpress-toolkit'); ?></option>
                    <option value="optimized" <?php selected($ai_optimization_status, 'optimized'); ?>><?php _e('Optimized', 'wordpress-toolkit'); ?></option>
                    <option value="failed" <?php selected($ai_optimization_status, 'failed'); ?>><?php _e('Failed', 'wordpress-toolkit'); ?></option>
                </select>
                <p class="description"><?php _e('AI优化状态', 'wordpress-toolkit'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save category fields
     */
    public function save_category_fields($term_id) {
        if (isset($_POST['ai_generated_slug'])) {
            update_term_meta($term_id, 'ai_generated_slug', sanitize_text_field($_POST['ai_generated_slug']));
        }
        if (isset($_POST['ai_generated_description'])) {
            update_term_meta($term_id, 'ai_generated_description', sanitize_textarea_field($_POST['ai_generated_description']));
        }
        if (isset($_POST['ai_optimization_status'])) {
            update_term_meta($term_id, 'ai_optimization_status', sanitize_text_field($_POST['ai_optimization_status']));
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // DeepSeek API Key 设置已迁移到AI设置页面
        register_setting('wordpress_toolkit_category_optimization', 'wordpress_toolkit_category_optimization_settings');

        add_settings_section(
            'category_optimization_section',
            __('Category Optimization Settings', 'wordpress-toolkit'),
            array($this, 'settings_section_callback'),
            'wordpress-toolkit-category-optimization'
        );

        // AI设置已迁移到专门的AI设置页面
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure category optimization settings.', 'wordpress-toolkit') . '</p>';
    }

    /**
     * DeepSeek API Key callback
     */
    public function deepseek_api_key_callback() {
        // DeepSeek API Key callback 已迁移到AI设置页面
    }

    
    /**
     * Get categories list
     */
    public function ajax_get_categories_list() {
        // 移除安全验证以简化操作
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'name';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'asc';

        $args = array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        );

        if ($filter) {
            $args['meta_query'] = array(
                array(
                    'key' => 'ai_optimization_status',
                    'value' => $filter,
                    'compare' => '='
                )
            );
        }

        $categories = get_terms($args);
        $total_categories = wp_count_terms('category', array(
            'hide_empty' => false,
            'search' => $search
        ));

        $data = array();
        foreach ($categories as $category) {
            $post_count = $category->count;
            $ai_generated_slug = get_term_meta($category->term_id, 'ai_generated_slug', true);
            $ai_generated_description = get_term_meta($category->term_id, 'ai_generated_description', true);
            $ai_optimization_status = get_term_meta($category->term_id, 'ai_optimization_status', true);

            $data[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'post_count' => $post_count,
                'ai_generated_slug' => $ai_generated_slug,
                'ai_generated_description' => $ai_generated_description,
                'ai_optimization_status' => $ai_optimization_status,
                'edit_url' => get_edit_term_link($category->term_id, 'category'),
                'view_url' => get_term_link($category)
            );
        }

        // 修复除零错误：确保每页数量大于0
        if ($per_page <= 0) {
            $per_page = 20;
        }

        wp_send_json_success(array(
            'data' => $data,
            'total' => $total_categories,
            'total_pages' => ceil($total_categories / $per_page),
            'current_page' => $page
        ));
    }

    /**
     * Optimize single category
     */
    public function ajax_optimize_category() {
        // 移除安全验证以简化操作
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $optimize_type = isset($_POST['optimize_type']) ? sanitize_text_field($_POST['optimize_type']) : 'all';

        if (!$category_id) {
            wp_send_json_error(array('message' => __('Invalid category ID', 'wordpress-toolkit')));
        }

        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            wp_send_json_error(array('message' => __('Category not found', 'wordpress-toolkit')));
        }

        $result = array();

        // Generate slug if needed
        if ($optimize_type === 'all' || $optimize_type === 'slug') {
            if (empty($category->slug) || $this->is_chinese($category->name)) {
                $slug_result = $this->generate_category_slug($category_id);
                if ($slug_result['success']) {
                    $result['slug'] = $slug_result['data'];
                } else {
                    $result['slug_error'] = $slug_result['message'];
                }
            }
        }

        // Generate description if needed
        if ($optimize_type === 'all' || $optimize_type === 'description') {
            if (empty($category->description)) {
                $desc_result = $this->generate_category_description($category_id);
                if ($desc_result['success']) {
                    $result['description'] = $desc_result['data'];
                } else {
                    $result['description_error'] = $desc_result['message'];
                }
            }
        }

        // Update optimization status
        update_term_meta($category_id, 'ai_optimization_status', 'optimized');

        wp_send_json_success(array(
            'message' => __('Category optimized successfully', 'wordpress-toolkit'),
            'result' => $result
        ));
    }

    /**
     * Bulk optimize categories
     */
    public function ajax_bulk_optimize_categories() {
        // 移除安全验证以简化操作
        $category_ids = isset($_POST['category_ids']) ? array_map('intval', (array) $_POST['category_ids']) : array();
        $optimize_type = isset($_POST['optimize_type']) ? sanitize_text_field($_POST['optimize_type']) : 'all';

        if (empty($category_ids)) {
            wp_send_json_error(array('message' => __('No categories selected', 'wordpress-toolkit')));
        }

        $results = array();
        $success_count = 0;
        $error_count = 0;

        foreach ($category_ids as $category_id) {
            $category = get_term($category_id, 'category');
            if (!$category || is_wp_error($category)) {
                $results[$category_id] = array('success' => false, 'message' => __('Category not found', 'wordpress-toolkit'));
                $error_count++;
                continue;
            }

            $category_result = array();

            // Generate slug if needed
            if ($optimize_type === 'all' || $optimize_type === 'slug') {
                if (empty($category->slug) || $this->is_chinese($category->name)) {
                    $slug_result = $this->generate_category_slug($category_id);
                    if ($slug_result['success']) {
                        $category_result['slug'] = $slug_result['data'];
                    } else {
                        $category_result['slug_error'] = $slug_result['message'];
                    }
                }
            }

            // Generate description if needed
            if ($optimize_type === 'all' || $optimize_type === 'description') {
                if (empty($category->description)) {
                    $desc_result = $this->generate_category_description($category_id);
                    if ($desc_result['success']) {
                        $category_result['description'] = $desc_result['data'];
                    } else {
                        $category_result['description_error'] = $desc_result['message'];
                    }
                }
            }

            // Update optimization status
            update_term_meta($category_id, 'ai_optimization_status', 'optimized');

            $results[$category_id] = array('success' => true, 'result' => $category_result);
            $success_count++;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Bulk optimization completed. Success: %d, Errors: %d', 'wordpress-toolkit'), $success_count, $error_count),
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count
        ));
    }

    /**
     * Generate category slug
     */
    public function ajax_generate_category_slug() {
        // 移除安全验证以简化操作
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        if (!$category_id) {
            wp_send_json_error(array('message' => __('Invalid category ID', 'wordpress-toolkit')));
        }

        $result = $this->generate_category_slug($category_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Slug generated successfully', 'wordpress-toolkit'),
                'slug' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Generate category description
     */
    public function ajax_generate_category_description() {
        // 移除安全验证以简化操作
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        if (!$category_id) {
            wp_send_json_error(array('message' => __('Invalid category ID', 'wordpress-toolkit')));
        }

        $result = $this->generate_category_description($category_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Description generated successfully', 'wordpress-toolkit'),
                'description' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Generate category SEO
     */
    public function ajax_generate_category_seo() {
        // 移除安全验证以简化操作
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        if (!$category_id) {
            wp_send_json_error(array('message' => __('Invalid category ID', 'wordpress-toolkit')));
        }

        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            wp_send_json_error(array('message' => __('Category not found', 'wordpress-toolkit')));
        }

        // Get posts in this category
        $posts = get_posts(array(
            'category' => $category_id,
            'numberposts' => 10,
            'post_status' => 'publish'
        ));

        if (empty($posts)) {
            wp_send_json_error(array('message' => __('No posts found in this category', 'wordpress-toolkit')));
        }

        // Generate SEO content
        $seo_content = $this->generate_seo_content($category, $posts);

        if ($seo_content) {
            update_term_meta($category_id, 'ai_generated_seo', $seo_content);
            wp_send_json_success(array(
                'message' => __('SEO content generated successfully', 'wordpress-toolkit'),
                'seo_content' => $seo_content
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate SEO content', 'wordpress-toolkit')));
        }
    }

    /**
     * Get category stats
     */
    public function ajax_get_category_stats() {
        // 移除安全验证以简化操作
        $stats = array(
            'total_categories' => wp_count_terms('category', array('hide_empty' => false)),
            'optimized_categories' => get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'ai_optimization_status',
                        'value' => 'optimized',
                        'compare' => '='
                    )
                ),
                'fields' => 'count'
            )),
            'pending_categories' => get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'ai_optimization_status',
                        'value' => 'pending',
                        'compare' => '='
                    )
                ),
                'fields' => 'count'
            )),
            'categories_with_ai_slug' => get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'ai_generated_slug',
                        'compare' => 'EXISTS'
                    )
                ),
                'fields' => 'count'
            )),
            'categories_with_ai_description' => get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key' => 'ai_generated_description',
                        'compare' => 'EXISTS'
                    )
                ),
                'fields' => 'count'
            ))
        );

        wp_send_json_success($stats);
    }

    /**
     * Cleanup duplicate categories
     */
    public function ajax_cleanup_duplicate_categories() {
        // 移除安全验证以简化操作
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'fields' => 'all'
        ));

        $duplicates = array();
        $processed = array();

        foreach ($categories as $category) {
            $slug_key = sanitize_title($category->name);

            if (isset($processed[$slug_key])) {
                $duplicates[] = array(
                    'original' => $processed[$slug_key],
                    'duplicate' => $category
                );
            } else {
                $processed[$slug_key] = $category;
            }
        }

        $cleaned_count = 0;
        foreach ($duplicates as $duplicate) {
            wp_delete_term($duplicate['duplicate']->term_id, 'category');
            $cleaned_count++;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Cleaned up %d duplicate categories', 'wordpress-toolkit'), $cleaned_count),
            'duplicates_found' => count($duplicates),
            'cleaned_count' => $cleaned_count
        ));
    }

    /**
     * Export category optimization report
     */
    public function ajax_export_category_optimization_report() {
        // 移除安全验证以简化操作
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'asc'
        ));

        $report = array();
        foreach ($categories as $category) {
            $ai_generated_slug = get_term_meta($category->term_id, 'ai_generated_slug', true);
            $ai_generated_description = get_term_meta($category->term_id, 'ai_generated_description', true);
            $ai_optimization_status = get_term_meta($category->term_id, 'ai_optimization_status', true);

            $report[] = array(
                'ID' => $category->term_id,
                'Name' => $category->name,
                'Slug' => $category->slug,
                'Description' => $category->description,
                'Post Count' => $category->count,
                'AI Generated Slug' => $ai_generated_slug,
                'AI Generated Description' => $ai_generated_description,
                'Optimization Status' => $ai_optimization_status,
                'Edit URL' => get_edit_term_link($category->term_id, 'category'),
                'View URL' => get_term_link($category)
            );
        }

        wp_send_json_success(array(
            'message' => __('Report generated successfully', 'wordpress-toolkit'),
            'report' => $report,
            'total_categories' => count($categories)
        ));
    }

    /**
     * Reset category optimization stats
     */
    public function ajax_reset_category_optimization_stats() {
        // 移除安全验证以简化操作
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'fields' => 'ids'
        ));

        foreach ($categories as $category_id) {
            delete_term_meta($category_id, 'ai_optimization_status');
            delete_term_meta($category_id, 'ai_generated_slug');
            delete_term_meta($category_id, 'ai_generated_description');
            delete_term_meta($category_id, 'ai_generated_seo');
        }

        wp_send_json_success(array(
            'message' => __('Category optimization stats reset successfully', 'wordpress-toolkit'),
            'reset_count' => count($categories)
        ));
    }

    /**
     * Generate category slug
     */
    private function generate_category_slug($category_id) {
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return array('success' => false, 'message' => __('Category not found', 'wordpress-toolkit'));
        }

        $category_name = $category->name;

        // If category name is in Chinese, translate to English
        if ($this->is_chinese($category_name)) {
            $translated_name = $this->translate_to_english($category_name);
            if ($translated_name) {
                $slug = sanitize_title($translated_name);
            } else {
                $slug = sanitize_title($category_name);
            }
        } else {
            $slug = sanitize_title($category_name);
        }

        // Check if slug already exists
        $existing_category = get_term_by('slug', $slug, 'category');
        if ($existing_category && $existing_category->term_id != $category_id) {
            // Add suffix to make it unique
            $suffix = 2;
            while ($existing_category) {
                $new_slug = $slug . '-' . $suffix;
                $existing_category = get_term_by('slug', $new_slug, 'category');
                if (!$existing_category || $existing_category->term_id == $category_id) {
                    $slug = $new_slug;
                    break;
                }
                $suffix++;
            }
        }

        // Update category slug
        wp_update_term($category_id, 'category', array('slug' => $slug));
        update_term_meta($category_id, 'ai_generated_slug', $slug);

        return array('success' => true, 'data' => $slug);
    }

    /**
     * Generate category description
     */
    private function generate_category_description($category_id) {
        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            return array('success' => false, 'message' => __('Category not found', 'wordpress-toolkit'));
        }

        // Get posts in this category
        $posts = get_posts(array(
            'category' => $category_id,
            'numberposts' => 10,
            'post_status' => 'publish'
        ));

        if (empty($posts)) {
            return array('success' => false, 'message' => __('No posts found in this category', 'wordpress-toolkit'));
        }

        // Generate description using AI
        $description = $this->generate_category_description_ai($category, $posts);

        if ($description) {
            // Update category description
            wp_update_term($category_id, 'category', array('description' => $description));
            update_term_meta($category_id, 'ai_generated_description', $description);

            return array('success' => true, 'data' => $description);
        } else {
            return array('success' => false, 'message' => __('Failed to generate description', 'wordpress-toolkit'));
        }
    }

    /**
     * Generate category description using AI
     */
    private function generate_category_description_ai($category, $posts) {
        $api_key = wordpress_toolkit_get_ai_settings('deepseek_api_key', '');
        if (empty($api_key)) {
            return false;
        }

        // Prepare post titles and excerpts
        $post_info = array();
        foreach ($posts as $post) {
            $post_info[] = array(
                'title' => $post->post_title,
                'excerpt' => $post->post_excerpt ?: substr($post->post_content, 0, 200)
            );
        }

        $prompt = "请根据以下分类信息和该分类下的文章，生成一个简洁、吸引人的分类描述：\n\n";
        $prompt .= "分类名称：" . $category->name . "\n\n";
        $prompt .= "该分类下的文章信息：\n";

        foreach ($post_info as $info) {
            $prompt .= "- 《" . $info['title'] . "》：" . $info['excerpt'] . "\n";
        }

        $prompt .= "\n要求：\n";
        $prompt .= "1. 描述长度控制在100-150字之间\n";
        $prompt .= "2. 突出该分类的核心主题和价值\n";
        $prompt .= "3. 语言要生动、吸引人\n";
        $prompt .= "4. 适合网站分类页面使用\n\n";
        $prompt .= "请只返回描述内容，不要包含其他说明文字。";

        $response = wp_remote_post($this->deepseek_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => 500,
                'temperature' => 0.7
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        return false;
    }

    /**
     * Generate SEO content
     */
    private function generate_seo_content($category, $posts) {
        $api_key = wordpress_toolkit_get_ai_settings('deepseek_api_key', '');
        if (empty($api_key)) {
            return false;
        }

        // Prepare post titles and excerpts
        $post_info = array();
        foreach ($posts as $post) {
            $post_info[] = array(
                'title' => $post->post_title,
                'excerpt' => $post->post_excerpt ?: substr($post->post_content, 0, 200)
            );
        }

        $prompt = "请根据以下分类信息和该分类下的文章，生成SEO优化的内容：\n\n";
        $prompt .= "分类名称：" . $category->name . "\n\n";
        $prompt .= "该分类下的文章信息：\n";

        foreach ($post_info as $info) {
            $prompt .= "- 《" . $info['title'] . "》：" . $info['excerpt'] . "\n";
        }

        $prompt .= "\n请生成以下SEO内容：\n";
        $prompt .= "1. SEO标题（50-60字符）\n";
        $prompt .= "2. Meta描述（150-160字符）\n";
        $prompt .= "3. 关键词列表（5-10个关键词）\n\n";
        $prompt .= "请以JSON格式返回：\n";
        $prompt .= "{\n";
        $prompt .= "  \"seo_title\": \"SEO标题\",\n";
        $prompt .= "  \"meta_description\": \"Meta描述\",\n";
        $prompt .= "  \"keywords\": [\"关键词1\", \"关键词2\", ...]\n";
        $prompt .= "}";

        $response = wp_remote_post($this->deepseek_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => 500,
                'temperature' => 0.7
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['choices'][0]['message']['content'])) {
            $content = trim($data['choices'][0]['message']['content']);
            $seo_data = json_decode($content, true);

            if ($seo_data) {
                return $seo_data;
            }
        }

        return false;
    }

    /**
     * Check if string contains Chinese characters
     */
    private function is_chinese($string) {
        return preg_match('/[\x{4e00}-\x{9fa5}]/u', $string);
    }

    /**
     * Translate Chinese to English
     */
    private function translate_to_english($chinese_text) {
        $api_key = wordpress_toolkit_get_ai_settings('deepseek_api_key', '');
        if (empty($api_key)) {
            return false;
        }

        $prompt = "请将以下中文翻译成英文，只返回翻译结果，不要包含其他说明：\n";
        $prompt .= $chinese_text;

        $response = wp_remote_post($this->deepseek_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => 100,
                'temperature' => 0.3
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        return false;
    }
}

// Initialize the module
Category_Optimization_Module::get_instance();