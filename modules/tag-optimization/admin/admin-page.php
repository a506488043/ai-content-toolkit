<?php
/**
 * Tag Optimization Admin Page
 * 标签优化管理页面
 *
 * @package WordPressToolkit
 * @subpackage TagOptimization
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Tag_Optimization_Admin_Page {

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // 处理AJAX操作
        add_action('wp_ajax_optimize_tag', array($this, 'ajax_optimize_tag'));
        add_action('wp_ajax_bulk_optimize_all_tags', array($this, 'ajax_bulk_optimize_all_tags'));
        add_action('wp_ajax_get_tag_stats', array($this, 'ajax_get_tag_stats'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-toolkit',
            __('标签优化管理', 'wordpress-toolkit'),
            __('标签优化', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-tag-optimization',
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 处理分页
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // 获取标签数据
        $args = array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'name',
            'order' => 'ASC'
        );

        $tags_query = get_terms($args);
        $total_tags = wp_count_terms('post_tag', array('hide_empty' => false));
        $total_pages = ceil($total_tags / $per_page);

        // 加载样式和脚本
        wp_enqueue_style('tag-optimization-admin', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/tag-optimization/assets/css/admin.css', array(), '1.0.0');
        wp_enqueue_script('tag-optimization-admin', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/tag-optimization/assets/js/admin.js', array('jquery'), '1.0.0', true);

        // 传递数据到JavaScript
        $nonce = wp_create_nonce('tag_optimization_nonce');
        wp_localize_script('tag-optimization-admin', 'tagOptimizationData', array(
            'texts' => array(
                'confirmOptimize' => __('确定要优化这个标签吗？', 'wordpress-toolkit'),
                'optimizing' => __('优化中...', 'wordpress-toolkit'),
                'optimizeSuccess' => __('优化成功', 'wordpress-toolkit'),
                'optimizeFailed' => __('优化失败', 'wordpress-toolkit'),
                'selectTags' => __('请先选择要优化的标签', 'wordpress-toolkit'),
                'confirmBulkOptimize' => __('确定要批量生成选中标签的内容吗？此操作可能需要一些时间。', 'wordpress-toolkit'),
                'bulkOptimizeSuccess' => __('批量优化完成', 'wordpress-toolkit'),
                'bulkOptimizeFailed' => __('批量优化失败', 'wordpress-toolkit')
            ),
            'nonces' => array(
                'optimizeTag' => $nonce,
                'bulkOptimizeTags' => $nonce,
                'getStats' => $nonce
            )
        ));

        ?>
        <div class="wrap auto-excerpt-admin">
            <h1><?php _e('标签优化管理', 'wordpress-toolkit'); ?></h1>

            <!-- 统计卡片 -->
            <div class="auto-excerpt-stats-grid">
                <div class="stat-card">
                    <h3><?php _e('总标签数', 'wordpress-toolkit'); ?></h3>
                    <div class="stat-number"><?php echo $total_tags; ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('已优化', 'wordpress-toolkit'); ?></h3>
                    <div class="stat-number" id="optimized-count">0</div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('待优化', 'wordpress-toolkit'); ?></h3>
                    <div class="stat-number" id="pending-count">0</div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('优化失败', 'wordpress-toolkit'); ?></h3>
                    <div class="stat-number" id="failed-count">0</div>
                </div>
            </div>

            <!-- 标签列表和管理 -->
            <div class="posts-list-section">
                <div id="tags-list-container">
                    <?php if (!empty($tags_query) && !is_wp_error($tags_query)): ?>
                        <div class="tablenav top">
                            <div class="alignleft actions bulkactions">
                                <button type="button" class="button action" id="batch-optimize-tags">
                                    🤖 <?php _e('批量生成标签描述', 'wordpress-toolkit'); ?>
                                </button>
                                <span class="spinner" id="batch-spinner" style="display: none;"></span>
                            </div>
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(__('共 %d 个标签', 'wordpress-toolkit'), $total_tags); ?>
                                </span>
                                <?php
                                $current_url = admin_url('admin.php?page=wordpress-toolkit-tag-optimization');
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%', $current_url),
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'show_all' => false,
                                    'end_size' => 1,
                                    'mid_size' => 2,
                                ));
                                ?>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped tags">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-cb check-column">
                                        <input type="checkbox" id="select-all-tags">
                                    </th>
                                    <th scope="col"><?php _e('标签名称', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('别名', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('描述', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('文章数', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('状态', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tags_query as $tag): ?>
                                    <?php
                                    $status = get_term_meta($tag->term_id, 'ai_optimization_status', true);
                                    $ai_slug = get_term_meta($tag->term_id, 'ai_slug', true);
                                    $ai_description = get_term_meta($tag->term_id, 'ai_description', true);

                                    // 检查是否有AI生成的内容
                                    $has_ai_slug = !empty($ai_slug);
                                    $has_ai_description = !empty($ai_description);
                                    ?>
                                    <tr>
                                        <td class="check-column">
                                            <input type="checkbox" class="tag-checkbox" value="<?php echo $tag->term_id; ?>" data-name="<?php echo esc_attr($tag->name); ?>">
                                        </td>
                                        <td><strong><a href="<?php echo admin_url('term.php?taxonomy=post_tag&tag_ID=' . $tag->term_id); ?>" target="_blank"><?php echo $tag->name; ?></a></strong></td>
                                        <td>
                                            <code><?php echo $tag->slug; ?></code>
                                            <?php if ($has_ai_slug): ?>
                                                <span class="ai-generated-mark">AI</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo wp_trim_words($tag->description, 10); ?>
                                            <?php if ($has_ai_description): ?>
                                                <span class="ai-generated-mark">AI</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="post-count"><?php echo $tag->count; ?></span></td>
                                        <td>
                                            <?php if ($status === 'optimized'): ?>
                                                <span class="status-badge status-success"><?php _e('已优化', 'wordpress-toolkit'); ?></span>
                                            <?php elseif ($status === 'failed'): ?>
                                                <span class="status-badge status-error"><?php _e('优化失败', 'wordpress-toolkit'); ?></span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending"><?php _e('待优化', 'wordpress-toolkit'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="row-actions">
                                                <span class="generate-description">
                                                    <button type="button" class="button button-small generate-description-btn" data-id="<?php echo $tag->term_id; ?>" style="background: #0073aa; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        📝 <?php _e('AI生成描述', 'wordpress-toolkit'); ?>
                                                    </button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                      <?php else: ?>
                        <p><?php _e('没有找到标签', 'wordpress-toolkit'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX获取标签统计数据
     */
    public function ajax_get_tag_stats() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tag_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $stats = array(
            'total_tags' => wp_count_terms('post_tag', array('hide_empty' => false)),
            'optimized_tags' => 0,
            'failed_tags' => 0,
            'pending_tags' => 0
        );

        // 获取所有标签并统计优化状态
        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0
        ));

        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $status = get_term_meta($tag->term_id, 'ai_optimization_status', true);
                if ($status === 'optimized') {
                    $stats['optimized_tags']++;
                } elseif ($status === 'failed') {
                    $stats['failed_tags']++;
                } else {
                    $stats['pending_tags']++;
                }
            }
        }

        wp_send_json_success($stats);
    }

    /**
     * 获取统计数据
     */
    private function get_statistics() {
        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0
        ));

        $stats = array(
            'total' => 0,
            'optimized' => 0,
            'failed' => 0,
            'pending' => 0
        );

        if (!is_wp_error($tags)) {
            $stats['total'] = count($tags);
            foreach ($tags as $tag) {
                $status = get_term_meta($tag->term_id, 'ai_optimization_status', true);
                if ($status === 'optimized') {
                    $stats['optimized']++;
                } elseif ($status === 'failed') {
                    $stats['failed']++;
                } else {
                    $stats['pending']++;
                }
            }
        }

        return $stats;
    }

    /**
     * AJAX优化单个标签
     */
    public function ajax_optimize_tag() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tag_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $tag_id = intval($_POST['tag_id']);
        $optimize_type = sanitize_text_field($_POST['optimize_type']);

        if (!$tag_id) {
            wp_send_json_error(__('无效的标签ID', 'wordpress-toolkit'));
        }

        $tag = get_term($tag_id, 'post_tag');
        if (!$tag || is_wp_error($tag)) {
            wp_send_json_error(__('标签不存在', 'wordpress-toolkit'));
        }

        switch ($optimize_type) {
            case 'description':
                // 基于标签下的文章生成描述并直接写入WordPress的description字段
                $ai_description = $this->generate_ai_description_by_articles($tag);

                // 更新WordPress原生的description字段
                $update_result = wp_update_term($tag_id, 'post_tag', array('description' => $ai_description));

                // 存储AI生成的描述信息到meta字段，用于标记AI生成
                update_term_meta($tag_id, 'ai_description', $ai_description);

                // 更新优化状态
                if (!is_wp_error($update_result)) {
                    update_term_meta($tag_id, 'ai_optimization_status', 'optimized', true);
                }

                $message = sprintf(__('标签 "%s" 的AI描述生成成功！', 'wordpress-toolkit'), $tag->name);
                break;

            default:
                wp_send_json_error(__('无效的优化类型', 'wordpress-toolkit'));
                break;
        }

        wp_send_json_success(array(
            'message' => $message
        ));
    }

    /**
     * 基于标签下的文章生成AI描述
     */
    private function generate_ai_description_by_articles($tag) {
        // 获取标签下的文章
        $posts = get_posts(array(
            'tag_id' => $tag->term_id,
            'numberposts' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($posts)) {
            return sprintf(
                '%s是我专门用来标记%s相关内容的标签。虽然目前还没有发布的文章使用这个标签，' .
                '但我计划在未来的文章中使用它来标记我在学习%s过程中的点点滴滴，包括遇到的问题、解决方案和心得体会。',
                $tag->name,
                $tag->name,
                $tag->name
            );
        }

        // 收集文章信息，用于生成更具体的描述
        $post_count = count($posts);
        $recent_titles = array();

        foreach ($posts as $post) {
            $recent_titles[] = $post->post_title;
            if (count($recent_titles) >= 3) break; // 取最近3篇文章标题
        }

        // 根据文章数量和内容生成更自然的描述
        if ($post_count == 1) {
            $description = sprintf(
                '在这个%s标签下，我标记了一篇关于%s的文章。这篇文章记录了我在探索%s过程中的一些真实想法和经历，' .
                '希望这些个人经验能够给同样走在%s路上的朋友带来一些启发和帮助。',
                $tag->name,
                $tag->name,
                $tag->name,
                $tag->name
            );
        } elseif ($post_count <= 3) {
            $recent_work = implode('、', array_slice($recent_titles, 0, 2));
            $description = sprintf(
                '%s标签标记了几篇我写的关于%s的文章。我在这里分享了最近在%s方面的一些学习心得和实践体会，' .
                '比如关于%s等内容。这些文章记录了我的真实经历，希望能帮助到同样对这些话题感兴趣的朋友。',
                $tag->name,
                $tag->name,
                $tag->name,
                $recent_work
            );
        } else {
            $recent_work = implode('、', array_slice($recent_titles, 0, 3));
            $description = sprintf(
                '%s标签整理了我在%s方面的多篇学习笔记。随着对%s的理解不断加深，' .
                '我在这里记录了从零基础到逐渐熟练的学习轨迹，分享了像%s这样的具体实践内容。' .
                '每一篇文章都是我真实学习过程中的沉淀，希望能够为同样想要学习%s的朋友提供一些参考。',
                $tag->name,
                $tag->name,
                $tag->name,
                $recent_work,
                $tag->name
            );
        }

        return $description;
    }

    /**
     * AJAX批量优化所有标签
     */
    public function ajax_bulk_optimize_all_tags() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tag_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $optimize_type = sanitize_text_field($_POST['optimize_type']);

        // 获取所有标签
        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 0, // 获取所有标签
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (empty($tags) || is_wp_error($tags)) {
            wp_send_json_error(__('没有找到标签', 'wordpress-toolkit'));
        }

        $processed = 0;
        $failed = 0;

        foreach ($tags as $tag) {
            try {
                if ($optimize_type === 'description') {
                    // 批量AI生成描述并直接写入WordPress原生字段
                    $ai_description = $this->generate_ai_description_by_articles($tag);
                    $update_result = wp_update_term($tag->term_id, 'post_tag', array('description' => $ai_description));

                    // 存储AI生成的描述信息到meta字段，用于标记AI生成
                    update_term_meta($tag->term_id, 'ai_description', $ai_description);

                    if (!is_wp_error($update_result)) {
                        // 更新优化状态
                        update_term_meta($tag->term_id, 'ai_optimization_status', 'optimized', true);
                        $processed++;
                    } else {
                        $failed++;
                    }
                }
            } catch (Exception $e) {
                $failed++;
            }
        }

        $total = count($tags);
        if ($failed > 0) {
            $message = sprintf(__('批量生成完成！成功生成 %d 个标签描述，失败 %d 个。', 'wordpress-toolkit'), $processed, $failed);
        } else {
            $message = sprintf(__('成功为所有 %d 个标签生成了描述！', 'wordpress-toolkit'), $processed);
        }

        wp_send_json_success(array(
            'message' => $message,
            'processed' => $processed,
            'failed' => $failed,
            'total' => $total
        ));
    }
}

// 初始化管理页面
Tag_Optimization_Admin_Page::get_instance();