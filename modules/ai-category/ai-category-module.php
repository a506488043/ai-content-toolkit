<?php
/**
 * AI Auto Category Module - AI自动分类模块
 *
 * 根据文章内容自动选择合适的分类
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Auto Category Module 主类
 */
class AI_Category_Module {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 模块设置
     */
    private $settings = array();

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
     * 构造函数
     */
    private function __construct() {
        error_log('=== AI_CATEGORY_MODULE: Constructor started ===');
        $this->init();
        error_log('=== AI_CATEGORY_MODULE: Constructor completed ===');
    }

    /**
     * 初始化模块
     */
    private function init() {
        error_log('=== AI_CATEGORY_MODULE: init_hooks started ===');

        // 加载默认设置
        $this->settings = $this->get_default_settings();

        // 注册管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));

        // 加载前端脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // 注册AJAX处理
        add_action('wp_ajax_ai_categorize_post', array($this, 'ajax_categorize_post'));
        add_action('wp_ajax_ai_category_get_settings', array($this, 'ajax_get_settings'));
        error_log('=== AI_CATEGORY_MODULE: AJAX actions registered ===');

        // 自动分类钩子
        add_action('save_post', array($this, 'auto_categorize_post'), 10, 2);
        add_action('draft_to_publish', array($this, 'auto_categorize_on_publish'));
        error_log('=== AI_CATEGORY_MODULE: Auto categorize hooks registered ===');

        // 在文章编辑页面添加AI分类按钮
        add_action('post_submitbox_misc_actions', array($this, 'add_ai_category_button'));
        error_log('=== AI_CATEGORY_MODULE: init_hooks completed ===');
    }

    /**
     * 获取默认设置
     */
    private function get_default_settings() {
        return array(
            'enabled' => false,
            'auto_remove_note_category' => true,
            'confidence_threshold' => 0.7,
            'max_categories' => 3,
            'custom_prompt' => '',
            'auto_mode' => 'draft_always' // 新增：草稿总是分类
        );
    }

    /**
     * 获取模块设置
     */
    public function get_settings() {
        $saved_settings = get_option('ai_category_settings', array());
        return wp_parse_args($saved_settings, $this->settings);
    }

    /**
     * 更新模块设置
     */
    public function update_settings($new_settings) {
        $settings = wp_parse_args($new_settings, $this->get_settings());
        update_option('ai_category_settings', $settings);
        $this->settings = $settings;
    }

    /**
     * 注册管理菜单
     */
    public function add_admin_menu() {
        // 不添加单独的菜单，集成到工具箱设置页面
    }

    /**
     * 设置页面
     */
    public function settings_page() {
        // 处理表单提交
        if (isset($_POST['save_settings'])) {
            $this->handle_settings_save();
        }

        $settings = $this->get_settings();
        $stats = $this->get_category_stats();
        ?>
        <div class="wrap ai-category-admin">
            <h1><?php _e('AI自动分类设置', 'wordpress-ai-toolkit'); ?></h1>

            <!-- 设置表单 -->
            <div class="ai-category-settings">
                <form method="post" action="">
                    <?php wp_nonce_field('ai_category_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai_category_enabled"><?php _e('启用AI自动分类', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="ai_category_enabled" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                <span class="description"><?php _e('启用后，保存文章时会自动调用AI选择合适的分类', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="auto_mode"><?php _e('自动分类模式', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <select id="auto_mode" name="auto_mode">
                                    <option value="draft_always" <?php selected($settings['auto_mode'], 'draft_always'); ?>>
                                        <?php _e('草稿总是分类', 'wordpress-ai-toolkit'); ?>
                                    </option>
                                    <option value="smart_first" <?php selected($settings['auto_mode'], 'smart_first'); ?>>
                                        <?php _e('智能首次分类', 'wordpress-ai-toolkit'); ?>
                                    </option>
                                    <option value="draft_once" <?php selected($settings['auto_mode'], 'draft_once'); ?>>
                                        <?php _e('草稿首次分类', 'wordpress-ai-toolkit'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('草稿总是分类：草稿状态每次保存都AI分类；智能首次分类：只有默认文章分类时才AI分类；草稿首次分类：只对草稿第一次保存时AI分类', 'wordpress-ai-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="auto_remove_note_category"><?php _e('自动移除笔记分类', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="auto_remove_note_category" name="auto_remove_note_category" value="1" <?php checked($settings['auto_remove_note_category']); ?>>
                                <span class="description"><?php _e('自动分类时移除默认选择的"笔记"分类', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="confidence_threshold"><?php _e('置信度阈值', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="confidence_threshold" name="confidence_threshold"
                                       value="<?php echo esc_attr($settings['confidence_threshold']); ?>"
                                       min="0.1" max="1.0" step="0.1">
                                <span class="description"><?php _e('AI分类的最低置信度（0.1-1.0）', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="max_categories"><?php _e('最大分类数量', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="max_categories" name="max_categories"
                                       value="<?php echo intval($settings['max_categories']); ?>"
                                       min="1" max="10" step="1">
                                <span class="description"><?php _e('一篇文章最多分配的分类数量', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="save_settings" class="button button-primary"
                               value="<?php _e('保存设置', 'wordpress-ai-toolkit'); ?>">
                    </p>
                </form>
            </div>

            <!-- 统计信息 -->
            <div class="ai-category-stats">
                <h2><?php _e('分类统计', 'wordpress-ai-toolkit'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php _e('总文章数', 'wordpress-ai-toolkit'); ?></h3>
                        <span class="stat-number"><?php echo $stats['total_posts']; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('已分类文章', 'wordpress-ai-toolkit'); ?></h3>
                        <span class="stat-number"><?php echo $stats['categorized_posts']; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('AI分类文章', 'wordpress-ai-toolkit'); ?></h3>
                        <span class="stat-number"><?php echo $stats['ai_categorized_posts']; ?></span>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('分类覆盖率', 'wordpress-ai-toolkit'); ?></h3>
                        <span class="stat-number"><?php echo $stats['coverage_rate']; ?>%</span>
                    </div>
                </div>
            </div>

                    </div>

        <!-- 页面样式 -->
        <style>
        .ai-category-admin .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .ai-category-admin .stat-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .ai-category-admin .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .ai-category-admin .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #0073aa;
            display: block;
        }

        .ai-category-admin .ai-category-stats {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        </style>
        </div>
        <?php
    }

    /**
     * 处理设置保存
     */
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_category_settings')) {
            wp_die(__('安全验证失败', 'wordpress-ai-toolkit'));
        }

        $settings = array(
            'enabled' => isset($_POST['enabled']),
            'auto_mode' => sanitize_text_field($_POST['auto_mode']),
            'auto_remove_note_category' => isset($_POST['auto_remove_note_category']),
            'confidence_threshold' => floatval($_POST['confidence_threshold']),
            'max_categories' => intval($_POST['max_categories'])
        );

        $this->update_settings($settings);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('设置保存成功！', 'wordpress-ai-toolkit') . '</p></div>';
    }

    /**
     * 获取分类统计信息
     */
    private function get_category_stats() {
        global $wpdb;

        $total_posts = wp_count_posts('post');
        $total_posts = $total_posts->publish;

        $categorized_posts = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
        ");

        $ai_categorized_posts = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_ai_categorized'
            AND pm.meta_value = '1'
        ");

        $coverage_rate = $total_posts > 0 ? round(($categorized_posts / $total_posts) * 100, 1) : 0;

        return array(
            'total_posts' => $total_posts,
            'categorized_posts' => $categorized_posts,
            'ai_categorized_posts' => $ai_categorized_posts,
            'coverage_rate' => $coverage_rate
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('ai_category_settings', 'ai_category_settings');
    }

    /**
     * 加载管理页面脚本
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wordpress-ai-toolkit-ai-category') !== false ||
            strpos($hook, 'post.php') !== false ||
            strpos($hook, 'post-new.php') !== false) {

            // 加载AI分类专用脚本
            wp_enqueue_script(
                'ai-category-js',
                plugin_dir_url(__FILE__) . 'assets/ai-category.js',
                array('jquery'),
                '1.0.0',
                true
            );

            wp_localize_script('ai-category-js', 'aiCategory', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_category_nonce'),
                'strings' => array(
                    'confirm' => __('确定要对这些文章进行AI分类吗？', 'wordpress-ai-toolkit'),
                    'processing' => __('正在处理...', 'wordpress-ai-toolkit'),
                    'complete' => __('处理完成', 'wordpress-ai-toolkit'),
                    'error' => __('处理失败，请重试', 'wordpress-ai-toolkit')
                )
            ));
        }
    }

    /**
     * 在文章编辑页面添加AI分类按钮
     */
    public function add_ai_category_button() {
        global $post;

        if ($post && $post->post_type === 'post') {
            ?>
            <div class="misc-pub-section misc-pub-ai-category">
                <label for="ai-categorize-btn" class="ai-categorize-label">
                    🤖 <strong><?php _e('AI分类', 'wordpress-ai-toolkit'); ?></strong>
                </label>
                <button type="button" id="ai-categorize-btn" class="button" style="margin-top: 5px;">
                    <?php _e('智能选择分类', 'wordpress-ai-toolkit'); ?>
                </button>
                <span id="ai-categorize-status" style="display: block; margin-top: 5px; font-size: 12px;"></span>
            </div>
            <?php
        }
    }

    /**
     * AJAX处理文章分类
     */
    public function ajax_categorize_post() {
        error_log('=== AJAX DEBUG: AI Category request received ===');
        error_log('Action: ai_categorize_post');
        error_log('POST data: ' . print_r($_POST, true));

        check_ajax_referer('ai_category_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            error_log('=== AJAX DEBUG: Permission denied ===');
            wp_send_json_error(__('权限不足', 'wordpress-ai-toolkit'));
        }

        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            error_log('=== AJAX DEBUG: Invalid post ID ===');
            wp_send_json_error(__('文章ID无效', 'wordpress-ai-toolkit'));
        }

        error_log('=== AJAX DEBUG: Processing post ID: ' . $post_id . ' ===');
        $result = $this->categorize_post($post_id);

        if ($result['success']) {
            // 获取分类名称用于前端显示
            if (isset($result['categories'])) {
                $category_names = array();
                foreach ($result['categories'] as $cat_id) {
                    $category = get_category($cat_id);
                    if ($category) {
                        $category_names[] = $category->name;
                    }
                }
                $result['category_names'] = $category_names;
            }

            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX获取设置
     */
    public function ajax_get_settings() {
        check_ajax_referer('ai_category_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-ai-toolkit'));
        }

        $settings = $this->get_settings();
        wp_send_json_success($settings);
    }

    
    /**
     * 自动分类文章
     */
    public function auto_categorize_post($post_id, $post) {
        // 只处理文章类型
        if ($post->post_type !== 'post') {
            return;
        }

        // 检查是否启用AI分类
        $settings = $this->get_settings();
        if (!$settings['enabled']) {
            return;
        }

        // 避免自动保存和修订版本触发
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 根据自动分类模式决定是否AI分类
        $auto_mode = $settings['auto_mode'];
        $existing_categories = wp_get_post_categories($post_id);

        switch ($auto_mode) {
            case 'draft_always':
                // 草稿总是分类：草稿状态每次保存都AI分类（推荐模式）
                if ($post->post_status !== 'draft') {
                    return; // 非草稿状态不执行
                }
                // 草稿状态下不检查现有分类，直接执行AI分类
                break;

            case 'smart_first':
                // 智能首次分类：只有默认文章分类时才AI分类
                if ($this->has_only_default_category($existing_categories)) {
                    // 只有默认分类，执行AI分类
                    break;
                } else {
                    return; // 已有用户手动选择的分类，跳过AI分类
                }

            case 'draft_once':
                // 草稿首次分类：只对草稿第一次保存时AI分类
                if ($post->post_status !== 'draft' || !$this->has_only_default_category($existing_categories)) {
                    return;
                }
                break;

            default:
                // 默认使用草稿总是分类逻辑
                if ($post->post_status !== 'draft') {
                    return;
                }
                break;
        }

        // 执行AI分类（仅第一次保存且无分类时）
        $this->categorize_post($post_id, true);
    }

    /**
     * 发布时自动分类
     */
    public function auto_categorize_on_publish($post) {
        if ($post->post_type !== 'post') {
            return;
        }

        $settings = $this->get_settings();
        if (!$settings['enabled']) {
            return;
        }

        // 根据自动分类模式决定是否AI分类
        $auto_mode = $settings['auto_mode'];
        $existing_categories = wp_get_post_categories($post->ID);

        switch ($auto_mode) {
            case 'draft_always':
                // 草稿总是分类：发布时不执行AI分类（因为已发布的文章分类已确定）
                return; // 发布时总是跳过

            case 'smart_first':
                // 智能首次分类：只有默认文章分类时才AI分类
                if ($this->has_only_default_category($existing_categories)) {
                    // 只有默认分类，执行AI分类
                    break;
                } else {
                    return; // 已有用户手动选择的分类，跳过AI分类
                }

            case 'draft_once':
                // 草稿首次分类：发布时不执行AI分类
                return; // 发布时总是跳过

            default:
                // 默认使用草稿总是分类逻辑：发布时不执行
                return; // 发布时总是跳过
        }

        $this->categorize_post($post->ID, true);
    }

    /**
     * 分类文章
     */
    public function categorize_post($post_id, $is_auto = false) {
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return array('success' => false, 'message' => __('文章内容为空', 'wordpress-ai-toolkit'));
        }

        // 检查AI是否可用
        if (!function_exists('wordpress_ai_toolkit_is_ai_available') || !wordpress_ai_toolkit_is_ai_available()) {
            return array('success' => false, 'message' => __('AI功能不可用', 'wordpress-ai-toolkit'));
        }

        $settings = $this->get_settings();

        // 获取所有分类
        $categories = get_categories(array('hide_empty' => false));
        if (empty($categories)) {
            return array('success' => false, 'message' => __('没有可用的分类', 'wordpress-ai-toolkit'));
        }

        // 构建分类选项
        $category_options = array();
        foreach ($categories as $category) {
            $category_options[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent' => $category->parent,
                'count' => $category->count
            );
        }

        // 准备AI请求内容
        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;

        // 限制内容长度
        if (mb_strlen($content) > 2000) {
            $content = mb_substr($content, 0, 2000) . '...';
        }

        // 构建分类提示词
        $prompt = $this->build_category_prompt($title, $content, $category_options, $settings);

        // 调用AI分类
        try {
            $ai_response = $this->call_ai_category_api($prompt, $title, $content);

            if ($ai_response && isset($ai_response['categories'])) {
                $selected_categories = $ai_response['categories'];

                // 移除笔记分类（如果启用）
                if ($settings['auto_remove_note_category']) {
                    $note_category = get_category_by_slug('note');
                    if ($note_category) {
                        $selected_categories = array_diff($selected_categories, array($note_category->term_id));
                    }
                }

                // 应用分类
                if (!empty($selected_categories)) {
                    wp_set_post_categories($post_id, $selected_categories);

                    // 标记为AI分类
                    update_post_meta($post_id, '_ai_categorized', '1');
                    update_post_meta($post_id, '_ai_categorized_time', current_time('mysql'));

                    return array(
                        'success' => true,
                        'message' => sprintf(__('已选择 %d 个分类', 'wordpress-ai-toolkit'), count($selected_categories)),
                        'categories' => $selected_categories
                    );
                } else {
                    return array('success' => false, 'message' => __('AI未能选择合适的分类', 'wordpress-ai-toolkit'));
                }
            } else {
                return array('success' => false, 'message' => __('AI分类失败', 'wordpress-ai-toolkit'));
            }
        } catch (Exception $e) {
            error_log('AI Category Error: ' . $e->getMessage());
            return array('success' => false, 'message' => __('分类时发生错误', 'wordpress-ai-toolkit'));
        }
    }

    /**
     * 构建分类提示词
     */
    private function build_category_prompt($title, $content, $category_options, $settings) {
        // 从提示词设置获取自定义提示词
        $prompt_settings = get_option('wordpress_toolkit_prompt_settings', array());
        $custom_prompt = isset($prompt_settings['ai_category']) ? $prompt_settings['ai_category']['prompt'] : '';

        if (!empty($custom_prompt)) {
            // 使用自定义提示词，替换变量
            $prompt = $custom_prompt;

            // 构建分类列表字符串
            $category_list = '';
            foreach ($category_options as $category) {
                $category_list .= "- [{$category['id']}] {$category['name']} ({$category['slug']})";
                if (!empty($category['description'])) {
                    $category_list .= ": {$category['description']}";
                }
                $category_list .= "\n";
            }

            // 替换变量
            $prompt = str_replace('{title}', $title, $prompt);
            $prompt = str_replace('{content}', $content, $prompt);
            $prompt = str_replace('{max_categories}', $settings['max_categories'], $prompt);
            $prompt = str_replace('{confidence_threshold}', $settings['confidence_threshold'], $prompt);
            $prompt = str_replace('{category_list}', $category_list, $prompt);
        } else {
            // 使用默认提示词
            $prompt = "你是一个专业的内容分类助手。请根据文章标题和内容，从给定的分类列表中选择最合适的分类。\n\n";
            $prompt .= "要求：\n";
            $prompt .= "1. 选择 {$settings['max_categories']} 个以内的分类\n";
            $prompt .= "2. 置信度需要达到 {$settings['confidence_threshold']} 以上\n";
            $prompt .= "3. 优先选择最具体、最相关的分类\n";
            $prompt .= "4. 返回JSON格式：{\"categories\": [分类ID数组]}\n\n";

            $prompt .= "文章标题：{$title}\n\n";
            $prompt .= "文章内容：{$content}\n\n";

            $prompt .= "可选分类：\n";
            foreach ($category_options as $category) {
                $prompt .= "- [{$category['id']}] {$category['name']} ({$category['slug']})";
                if (!empty($category['description'])) {
                    $prompt .= ": {$category['description']}";
                }
                $prompt .= "\n";
            }

            $prompt .= "\n请返回JSON格式的分类结果。";
        }

        return $prompt;
    }

    /**
     * 检查是否只有默认的文章分类
     */
    private function has_only_default_category($categories) {
        if (empty($categories)) {
            return true; // 没有分类也算作需要AI分类
        }

        // 获取WordPress默认文章分类
        $default_category_id = get_option('default_category', 1);

        // 检查是否只有默认分类
        if (count($categories) === 1 && in_array($default_category_id, $categories)) {
            return true; // 只有默认分类
        }

        return false; // 有其他用户选择的分类
    }

    /**
     * 调用AI分类API
     */
    private function call_ai_category_api($prompt, $title, $content) {
        // 这里需要根据实际的AI API接口来实现
        // 可以调用现有的AI服务或者集成第三方API

        if (function_exists('wordpress_ai_toolkit_call_ai_api')) {
            $response = wordpress_ai_toolkit_call_ai_api($prompt, array(
                'max_tokens' => 500,
                'temperature' => 0.3 // 较低温度以获得更一致的结果
            ));

            if (!is_wp_error($response) && !empty($response)) {
                // 解析AI返回的JSON
                $result = json_decode($response, true);
                if ($result && isset($result['categories'])) {
                    return $result;
                }
            }
        }

        // 临时回退：基于关键词的简单分类
        return $this->fallback_category_selection($title, $content);
    }

    /**
     * 回退分类选择（基于关键词）
     */
    private function fallback_category_selection($title, $content) {
        $keywords_map = array(
            'computer' => array('computer', 'technology', 'programming'),
            'wordpress' => array('wordpress', 'web-development', 'cms'),
            'php' => array('programming', 'web-development', 'computer'),
            'javascript' => array('programming', 'web-development', 'frontend'),
            'css' => array('web-development', 'design', 'frontend'),
            'mysql' => array('database', 'web-development', 'backend'),
            'linux' => array('linux', 'server', 'operating-system'),
            'docker' => array('docker', 'container', 'devops'),
            'git' => array('git', 'version-control', 'development-tools'),
            'tutorial' => array('tutorial', 'guide', 'learning'),
            'note' => array('note', 'personal', 'diary')
        );

        $text = strtolower($title . ' ' . $content);
        $matched_categories = array();

        foreach ($keywords_map as $keywords => $possible_slugs) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    foreach ($possible_slugs as $slug) {
                        $category = get_category_by_slug($slug);
                        if ($category && !in_array($category->term_id, $matched_categories)) {
                            $matched_categories[] = $category->term_id;
                        }
                    }
                }
            }
        }

        if (!empty($matched_categories)) {
            return array('categories' => array_slice($matched_categories, 0, 3));
        }

        return null;
    }
}

// 初始化模块
AI_Category_Module::get_instance();