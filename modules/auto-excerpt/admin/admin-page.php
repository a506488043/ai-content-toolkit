<?php
/**
 * Article Optimization Admin Page
 * 文章优化管理页面
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Article Optimization Admin Page 类
 */
class Auto_Excerpt_Admin_Page {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 模块实例
     */
    private $module;

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
        $this->module = Auto_Excerpt_Module::get_instance();
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // 加载SEO分析相关脚本和样式
        add_action('admin_enqueue_scripts', array($this, 'enqueue_seo_scripts'));

        // 处理批量操作
        add_action('admin_init', array($this, 'handle_batch_operations'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-ai-toolkit',
            __('自动摘要管理', 'wordpress-ai-toolkit'),
            __('自动摘要', 'wordpress-ai-toolkit'),
            'manage_options',
            'wordpress-ai-toolkit-auto-excerpt',
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-ai-toolkit'));
        }

        // 处理表单提交
        $this->handle_form_submission();

        // 获取统计数据
        $stats = $this->get_statistics();

        // 获取设置
        $settings = $this->module->get_settings();
        ?>
        <div class="wrap auto-excerpt-admin">
            <h1><?php _e('自动摘要管理', 'wordpress-ai-toolkit'); ?></h1>

            <!-- 统计卡片 -->
            <div class="auto-excerpt-stats-grid">
                <div class="stat-card">
                    <h3><?php _e('总文章数', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['total_posts']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('有摘要的文章', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['posts_with_excerpt']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('无摘要的文章', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['posts_without_excerpt']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('摘要覆盖率', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['coverage_rate']; ?>%</span>
                </div>
            </div>

            <!-- 文章列表和SEO分析 -->
            <div class="posts-list-section">
                <h3><?php _e('文章列表与SEO分析', 'wordpress-ai-toolkit'); ?></h3>
                <?php if (!function_exists('wordpress_ai_toolkit_is_ai_available') || !wordpress_ai_toolkit_is_ai_available()): ?>
                <div class="notice notice-warning inline" style="margin-bottom: 20px;">
                    <p>
                        <strong>⚠️ <?php _e('AI功能未配置', 'wordpress-ai-toolkit'); ?></strong><br>
                        <?php _e('SEO分析功能需要配置AI服务。请前往', 'wordpress-ai-toolkit'); ?>
                        <a href="<?php echo admin_url('admin.php?page=wordpress-ai-toolkit-ai-settings'); ?>" class="button button-primary">
                            <?php _e('工具箱设置 → AI设置', 'wordpress-ai-toolkit'); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                <div id="posts-list-container">
                    <?php
                    // 获取文章列表数据
                    $per_page = 20;
                    $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
                    $offset = ($current_page - 1) * $per_page;

                    $args = array(
                        'post_type' => 'post',
                        'post_status' => 'publish',
                        'posts_per_page' => $per_page,
                        'offset' => $offset,
                        'orderby' => 'modified',
                        'order' => 'DESC'
                    );

                    // 处理筛选
                    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
                        if ($_GET['status'] === 'with_excerpt') {
                            $args['meta_query'] = array(
                                array(
                                    'key' => 'post_excerpt',
                                    'value' => '',
                                    'compare' => '!='
                                )
                            );
                        } elseif ($_GET['status'] === 'without_excerpt') {
                            $args['meta_query'] = array(
                                array(
                                    'key' => 'post_excerpt',
                                    'value' => '',
                                    'compare' => '='
                                )
                            );
                        }
                    }

                    $posts_query = new WP_Query($args);
                    $total_posts = $posts_query->found_posts;
                    $total_pages = ceil($total_posts / $per_page);

                    if ($posts_query->have_posts()) {
                        ?>
                        <div class="tablenav top">
                            <div class="alignleft actions bulkactions">
                                <button type="button" class="button action" id="batch-seo-analyze">
                                    <span class="dashicons dashicons-search"></span>
                                    <?php _e('批量SEO分析', 'wordpress-ai-toolkit'); ?>
                                </button>
                            </div>
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(__('共 %d 篇文章', 'wordpress-ai-toolkit'), $total_posts); ?>
                                </span>
                                <?php
                                $current_url = admin_url('admin.php?page=wordpress-ai-toolkit-auto-excerpt');
                                if (isset($_GET['status'])) {
                                    $current_url .= '&status=' . urlencode($_GET['status']);
                                }
                                echo paginate_links(array(
                                    'base' => $current_url . '&paged=%#%',
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page
                                ));
                                ?>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-cb check-column">
                                        <input type="checkbox" id="cb-select-all-1">
                                    </th>
                                    <th scope="col"><?php _e('文章标题', 'wordpress-ai-toolkit'); ?></th>
                                    <th scope="col"><?php _e('摘要状态', 'wordpress-ai-toolkit'); ?></th>
                                    <th scope="col"><?php _e('SEO得分', 'wordpress-ai-toolkit'); ?></th>
                                    <th scope="col"><?php _e('修改时间', 'wordpress-ai-toolkit'); ?></th>
                                    <th scope="col"><?php _e('操作', 'wordpress-ai-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                while ($posts_query->have_posts()) {
                                    $posts_query->the_post();
                                    $post_id = get_the_ID();
                                    $post_title = get_the_title();
                                    $direct_excerpt = get_post_field('post_excerpt', $post_id);
                                    $has_excerpt = !empty($direct_excerpt);
                                    $excerpt_length = mb_strlen($direct_excerpt);

                                    // 获取SEO分数
                                    $seo_db = new Auto_Excerpt_SEO_Analyzer_Database();
                                    $seo_score = $seo_db->get_seo_score($post_id);

                                    if ($seo_score !== null) {
                                        $score_class = '';
                                        if ($seo_score >= 90) {
                                            $score_class = 'excellent';
                                        } elseif ($seo_score >= 80) {
                                            $score_class = 'good';
                                        } elseif ($seo_score >= 70) {
                                            $score_class = 'average';
                                        } elseif ($seo_score >= 60) {
                                            $score_class = 'poor';
                                        } else {
                                            $score_class = 'bad';
                                        }
                                    } else {
                                        $seo_score = '-';
                                        $score_class = '';
                                    }
                                    ?>
                                    <tr>
                                        <th scope="row" class="check-column">
                                            <input type="checkbox" name="post_ids[]" value="<?php echo $post_id; ?>" class="post-checkbox">
                                        </th>
                                        <td class="column-title">
                                            <strong>
                                                <a href="<?php echo get_edit_post_link($post_id); ?>" target="_blank">
                                                    <?php echo esc_html($post_title); ?>
                                                </a>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($has_excerpt): ?>
                                                <span class="status-badge has-excerpt">
                                                    <?php _e('有摘要', 'wordpress-ai-toolkit'); ?>
                                                    <small>(<?php echo $excerpt_length; ?> 字符)</small>
                                                    <?php
                                                    // 检查是否为AI生成的摘要
                                                    $is_ai_generated = get_post_meta($post_id, '_ai_generated_excerpt', true) ||
                                                                     get_post_meta($post_id, '_auto_excerpt_ai_generated', true);
                                                    if ($is_ai_generated) {
                                                        echo ' <span class="ai-badge">AI</span>';
                                                    }
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge no-excerpt">
                                                    <?php _e('无摘要', 'wordpress-ai-toolkit'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($seo_score !== '-'): ?>
                                                <div class="seo-score-display">
                                                    <span class="seo-score-badge <?php echo $score_class; ?>">
                                                        <?php echo number_format($seo_score, 1); ?>
                                                    </span>
                                                    <div class="seo-score-bar">
                                                        <div class="seo-score-fill" style="width: <?php echo $seo_score; ?>%; background: <?php
                                                            echo $seo_score >= 90 ? '#22c55e' : (
                                                                $seo_score >= 80 ? '#3b82f6' : (
                                                                    $seo_score >= 70 ? '#f59e0b' : (
                                                                        $seo_score >= 60 ? '#f97316' : '#ef4444'
                                                                    )
                                                                )
                                                            );
                                                        ?>;"></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="seo-score-badge none">
                                                    <?php _e('未分析', 'wordpress-ai-toolkit'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo get_the_modified_date('Y-m-d H:i'); ?></td>
                                        <td>
                                            <!-- 调试信息 -->
                                            <div style="font-size: 10px; color: #666;">
                                                DEBUG: Post <?php echo $post_id; ?> -
                                                Has Excerpt: <?php echo $has_excerpt ? 'YES' : 'NO'; ?> -
                                                Length: <?php echo $excerpt_length; ?> -
                                                AI Mark: <?php echo ($ai_meta_1 || $ai_meta_2) ? 'YES' : 'NO'; ?>
                                            </div>
                                            <div class="row-actions">
                                                <!-- 强制显示生成摘要按钮 -->
                                                <span class="generate-excerpt">
                                                    <button type="button" class="button button-small generate-excerpt-btn" data-post-id="<?php echo $post_id; ?>" style="background: #46b450; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        📝 生成摘要
                                                    </button>
                                                </span>

                                                <span class="generate-tags">
                                                    <button type="button" class="button button-small generate-tags-btn" data-post-id="<?php echo $post_id; ?>" style="background: #ff6900; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        🏷️ 生成标签
                                                    </button>
                                                </span>
                                                <span class="ai-categorize">
                                                    <button type="button" class="button button-small ai-categorize-btn" data-post-id="<?php echo $post_id; ?>" style="background: #22c55e; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        📁 AI分类描述
                                                    </button>
                                                </span>
                                                <span class="ai-optimize-tags">
                                                    <button type="button" class="button button-small ai-optimize-tags-btn" data-post-id="<?php echo $post_id; ?>" style="background: #8b5cf6; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        ✨ AI标签描述
                                                    </button>
                                                </span>
                                                <span class="seo-analyze">
                                                    <button type="button" class="button button-small seo-analyze-btn" data-post-id="<?php echo $post_id; ?>" style="background: #0073aa; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        📊 SEO分析
                                                    </button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                wp_reset_postdata();
                                ?>
                            </tbody>
                        </table>

                        <div class="tablenav bottom">
                            <div class="alignleft actions bulkactions">
                                <button type="button" class="button action" id="batch-seo-analyze-bottom">
                                    <span class="dashicons dashicons-search"></span>
                                    <?php _e('批量SEO分析', 'wordpress-ai-toolkit'); ?>
                                </button>
                            </div>
                            <div class="tablenav-pages">
                                <?php
                                echo paginate_links(array(
                                    'base' => $current_url . '&paged=%#%',
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page
                                ));
                                ?>
                            </div>
                        </div>
                        <?php
                    } else {
                        echo '<p>' . __('没有找到文章', 'wordpress-ai-toolkit') . '</p>';
                    }
                    ?>
                </div>

                <!-- SEO分析结果模态框 -->
                <div id="seo-result-modal" class="seo-modal" style="display: none;">
                    <div class="modal-backdrop"></div>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><?php _e('SEO分析结果', 'wordpress-ai-toolkit'); ?></h3>
                            <button type="button" class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body" id="seo-result-content">
                            <div class="loading"><?php _e('正在分析...', 'wordpress-ai-toolkit'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 标签页导航 -->
            <div class="auto-excerpt-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#settings" class="nav-tab nav-tab-active"><?php _e('基本设置', 'wordpress-ai-toolkit'); ?></a>
                    <a href="#batch" class="nav-tab"><?php _e('批量操作', 'wordpress-ai-toolkit'); ?></a>
                    <a href="#analytics" class="nav-tab"><?php _e('数据分析', 'wordpress-ai-toolkit'); ?></a>
                    <a href="#advanced" class="nav-tab"><?php _e('高级选项', 'wordpress-ai-toolkit'); ?></a>
                </h2>

                <!-- 基本设置标签页 -->
                <div id="settings" class="tab-content active">
                    <?php $this->render_settings_tab(); ?>
                </div>

                <!-- 批量操作标签页 -->
                <div id="batch" class="tab-content">
                    <?php $this->render_batch_tab(); ?>
                </div>

                <!-- 数据分析标签页 -->
                <div id="analytics" class="tab-content">
                    <?php $this->render_analytics_tab($stats); ?>
                </div>

                <!-- 高级选项标签页 -->
                <div id="advanced" class="tab-content">
                    <?php $this->render_advanced_tab(); ?>
                </div>
            </div>
        </div>

        <!-- 页面样式和脚本 -->
        <style>
        .auto-excerpt-admin .stat-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .auto-excerpt-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .auto-excerpt-stats-grid .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .auto-excerpt-stats-grid .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #0073aa;
            display: block;
        }

        .auto-excerpt-tabs {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .auto-excerpt-tabs .tab-content {
            display: none;
            margin-top: 20px;
        }

        .auto-excerpt-tabs .tab-content.active {
            display: block;
        }

        .batch-progress {
            margin: 20px 0;
            padding: 15px;
            background: #f0f6fc;
            border: 1px solid #2271b1;
            border-radius: 6px;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e5e5e5;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: #2271b1;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .analytics-chart {
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }

        @media (max-width: 782px) {
            .auto-excerpt-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* 文章列表和SEO分析样式 */
        .posts-list-section {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.has-excerpt {
            background: #f0f6fc;
            color: #0073aa;
            border: 1px solid #c3d9ea;
        }

        .status-badge.no-excerpt {
            background: #fef7f7;
            color: #d63638;
            border: 1px solid #ffabaf;
        }

        .seo-score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            min-width: 45px;
            text-align: center;
        }

        .seo-score-badge.excellent {
            background: #46b450;
            color: #fff;
        }

        .seo-score-badge.good {
            background: #00a0d2;
            color: #fff;
        }

        .seo-score-badge.average {
            background: #ffb900;
            color: #000;
        }

        .seo-score-badge.poor {
            background: #dc3232;
            color: #fff;
        }

        .seo-score-badge.none {
            background: #f0f0f1;
            color: #666;
        }

        /* SEO分数显示增强样式 */
        .seo-score-display {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .seo-score-bar {
            width: 60px;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .seo-score-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
            min-width: 2px;
        }

        /* SEO模态框样式 */
        .seo-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 100000;
            display: none;
        }

        .seo-modal .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
        }

        .seo-modal .modal-content {
            position: relative;
            max-width: 800px;
            max-height: 90vh;
            margin: 5vh auto;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .seo-modal .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9f9;
        }

        .seo-modal .modal-header h3 {
            margin: 0;
            color: #23282d;
            font-size: 1.3em;
        }

        .seo-modal .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
        }

        .seo-modal .modal-close:hover {
            background: #e1e1e1;
            color: #23282d;
        }

        .seo-modal .modal-body {
            padding: 20px;
            max-height: calc(90vh - 100px);
            overflow-y: auto;
        }

        .seo-modal .loading {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }

        @media screen and (max-width: 768px) {
            .seo-modal .modal-content {
                margin: 0;
                max-height: 100vh;
                border-radius: 0;
            }

            .row-actions {
                display: block;
                text-align: center;
            }

            .row-actions span {
                display: block;
                margin: 5px 0;
            }
        }

        /* 桌面版操作按钮样式 */
        .row-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .row-actions span {
            margin-right: 0;
        }

        /* 按钮样式 */
        .row-actions .button {
            margin: 2px 0;
            font-size: 12px;
            line-height: 1.4;
            height: auto;
            padding: 6px 12px;
            white-space: nowrap;
        }

        .row-actions .seo-analyze-btn {
            background: #0073aa;
            color: #fff;
            border-color: #0073aa;
        }

        .row-actions .seo-analyze-btn:hover {
            background: #005a87;
            border-color: #005a87;
        }

        .row-actions .generate-excerpt-btn {
            background: #46b450;
            color: #fff;
            border-color: #46b450;
        }

        .row-actions .generate-excerpt-btn:hover {
            background: #3d8b40;
            border-color: #3d8b40;
        }

        .row-actions .generate-tags-btn {
            background: #ff6900;
            color: #fff;
            border-color: #ff6900;
        }

        .row-actions .generate-tags-btn:hover {
            background: #e85d00;
            border-color: #e85d00;
        }

        .row-actions .view-seo-report-btn {
            background: #826eb4;
            color: #fff;
            border-color: #826eb4;
        }

        .row-actions .view-seo-report-btn:hover {
            background: #6d5aa0;
            border-color: #6d5aa0;
        }

        .row-actions .ai-categorize-btn {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }

        .row-actions .ai-categorize-btn:hover {
            background: #16a34a;
            border-color: #16a34a;
        }

        .row-actions .ai-optimize-tags-btn {
            background: #8b5cf6;
            color: #fff;
            border-color: #8b5cf6;
        }

        .row-actions .ai-optimize-tags-btn:hover {
            background: #7c3aed;
            border-color: #7c3aed;
        }
        </style>

        <!-- 调试信息：检查脚本加载状态 -->
        <div style="background: #f0f0f1; border: 1px solid #ccd0d4; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px;">
            <h4>🔧 调试信息 - 脚本加载状态</h4>
            <?php
            echo "ToolkitConfig 可用: " . (wp_script_is('toolkit-core', 'enqueued') ? '✅ 已加载' : '❌ 未加载') . "<br>";
            echo "modules-admin.js 可用: " . (wp_script_is('wordpress-ai-toolkit-modules-admin', 'enqueued') ? '✅ 已加载' : '❌ 未加载') . "<br>";
            echo "当前 nonce: " . wp_create_nonce('toolkit_nonce') . "<br>";
            echo "用户权限: " . (current_user_can('edit_posts') ? '✅ 有编辑权限' : '❌ 无编辑权限') . "<br>";
            echo "AI 功能可用: " . (function_exists('wordpress_ai_toolkit_is_ai_available') && wordpress_ai_toolkit_is_ai_available() ? '✅ 可用' : '❌ 不可用');
            ?>
        </div>

        <script>
        // 调试信息：检查全局配置
        console.log('=== AUTO_EXCERPT_DEBUG: Page loaded ===');
        console.log('ToolkitConfig:', typeof ToolkitConfig !== 'undefined' ? ToolkitConfig : 'UNDEFINED');
        console.log('ToolkitCore.config:', typeof ToolkitCore !== 'undefined' ? ToolkitCore.config : 'UNDEFINED');
        console.log('modules-admin.js loaded:', typeof ToolkitModules !== 'undefined');
        console.log('toolkit-core.js loaded:', typeof ToolkitCore !== 'undefined');

        // 全局配置对象，供SEO分析器使用
        var AutoExcerptConfig = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            generateNonce: '<?php echo wp_create_nonce('auto_excerpt_generate'); ?>',
            tagsNonce: '<?php echo wp_create_nonce('auto_excerpt_generate_tags'); ?>',
            categorizeNonce: '<?php echo wp_create_nonce('auto_excerpt_ai_categorize'); ?>',
            optimizeTagsNonce: '<?php echo wp_create_nonce('auto_excerpt_ai_optimize_tags'); ?>',
            seoAnalyzeNonce: '<?php echo wp_create_nonce('auto_excerpt_seo_analyze'); ?>',
            getSeoReportNonce: '<?php echo wp_create_nonce('auto_excerpt_get_seo_report'); ?>',
            batchNonce: '<?php echo wp_create_nonce('auto_excerpt_batch'); ?>'
        };

        jQuery(document).ready(function($) {
            // 调试信息：检查组件是否正确加载
            console.log('SEO Components loaded:', {
                SEOAnalyzer: typeof window.SEOAnalyzer,
                SEOReportDisplay: typeof window.SEOReportDisplay,
                AutoExcerptConfig: typeof window.AutoExcerptConfig
            });
            // 标签页切换
            $('.auto-excerpt-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();

                var targetId = $(this).attr('href').substring(1);

                // 更新标签状态
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // 显示对应内容
                $('.tab-content').removeClass('active');
                $('#' + targetId).addClass('active');
            });

            // 批量操作AJAX
            $('#batch-generate-form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var progressDiv = $('.batch-progress');
                var progressBar = $('.progress-fill');
                var progressText = $('.progress-text');
                var resultsDiv = $('#batch-results');

                progressDiv.show();
                form.find('input[type="submit"]').prop('disabled', true);

                var data = {
                    action: 'auto_excerpt_batch_generate',
                    nonce: AutoExcerptConfig.batchNonce,
                    post_type: $('#batch_post_type').val(),
                    limit: parseInt($('#batch_limit').val()),
                    overwrite: $('#batch_overwrite').is(':checked')
                };

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = evt.loaded / evt.total;
                                progressBar.css('width', (percentComplete * 100) + '%');
                                progressText.text('处理中... ' + Math.round(percentComplete * 100) + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        progressDiv.hide();
                        form.find('input[type="submit"]').prop('disabled', false);

                        if (response.success) {
                            resultsDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            resultsDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        progressDiv.hide();
                        form.find('input[type="submit"]').prop('disabled', false);
                        resultsDiv.html('<div class="notice notice-error"><p>操作失败，请重试</p></div>');
                    }
                });
            });

            // 生成摘要按钮
            $(document).on('click', '.generate-excerpt-btn', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var originalText = button.text();

                button.prop('disabled', true).text('生成中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_generate',
                        nonce: AutoExcerptConfig.generateNonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('已生成').addClass('success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            button.prop('disabled', false).text(originalText);
                            alert('生成失败：' + response.data.message);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text(originalText);
                        alert('生成失败，请重试');
                    }
                });
            });

            // 生成标签按钮
            $(document).on('click', '.generate-tags-btn', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var originalText = button.text();

                button.prop('disabled', true).text('生成中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_generate_tags',
                        nonce: AutoExcerptConfig.tagsNonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('已生成').addClass('success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            button.prop('disabled', false).text(originalText);
                            alert('生成失败：' + response.data.message);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text(originalText);
                        alert('生成失败，请重试');
                    }
                });
            });

            // AI分类按钮
            $(document).on('click', '.ai-categorize-btn', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var originalText = button.text();

                button.prop('disabled', true).text('生成分类描述中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_ai_categorize',
                        nonce: AutoExcerptConfig.categorizeNonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('已分类').addClass('success');
                            alert('分类描述生成成功：' + response.data.message);
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            button.prop('disabled', false).text(originalText);
                            alert('分类描述生成失败：' + response.data.message);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text(originalText);
                        alert('分类描述生成失败，请重试');
                    }
                });
            });

            // AI优化标签按钮
            $(document).on('click', '.ai-optimize-tags-btn', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var originalText = button.text();

                button.prop('disabled', true).text('生成标签描述中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_ai_optimize_tags',
                        nonce: AutoExcerptConfig.optimizeTagsNonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('已优化').addClass('success');
                            alert('标签描述生成成功：' + response.data.message);
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            button.prop('disabled', false).text(originalText);
                            alert('标签描述生成失败：' + response.data.message);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text(originalText);
                        alert('标签描述生成失败，请重试');
                    }
                });
            });

            // SEO分析按钮
            $(document).on('click', '.seo-analyze-btn', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var originalText = button.html();

                button.prop('disabled', true).html('<span class="dashicons dashicons-spinner"></span> 分析中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_seo_analyze',
                        nonce: AutoExcerptConfig.seoAnalyzeNonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            showSEOReport(response.data);
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            button.prop('disabled', false).html(originalText);
                            alert('分析失败：' + response.data.message);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).html(originalText);
                        alert('分析失败，请重试');
                    }
                });
            });

            // 查看SEO报告按钮
            $(document).on('click', '.view-seo-report-btn', function() {
                var postId = $(this).data('post-id');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_get_seo_report',
                        nonce: AutoExcerptConfig.getSeoReportNonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('=== 获取SEO报告数据结构 ===');
                            console.log('完整数据:', response.data);
                            console.log('SEOAnalyzer可用:', typeof window.SEOAnalyzer);
                            console.log('SEOReportDisplay可用:', typeof window.SEOReportDisplay);
                            console.log('=== 数据结构结束 ===');
                            showSEOReport(response.data);
                        } else {
                            alert('获取报告失败：' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('获取报告失败，请重试');
                    }
                });
            });

            // 批量SEO分析
            $('#batch-seo-analyze, #batch-seo-analyze-bottom').on('click', function() {
                var selectedPosts = $('.post-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedPosts.length === 0) {
                    alert('请先选择要分析的文章');
                    return;
                }

                if (!confirm('确定要对选中的 ' + selectedPosts.length + ' 篇文章进行SEO分析吗？')) {
                    return;
                }

                var button = $(this);
                button.prop('disabled', true).html('<span class="dashicons dashicons-spinner"></span> 批量分析中...');

                var currentIndex = 0;
                var results = [];

                function analyzeNextPost() {
                    if (currentIndex >= selectedPosts.length) {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> <?php _e('批量SEO分析', 'wordpress-ai-toolkit'); ?>');
                        alert('批量分析完成！共分析了 ' + results.length + ' 篇文章。');
                        location.reload();
                        return;
                    }

                    var postId = selectedPosts[currentIndex];

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'auto_excerpt_seo_analyze',
                            nonce: AutoExcerptConfig.seoAnalyzeNonce,
                            post_id: postId
                        },
                        success: function(response) {
                            results.push({
                                post_id: postId,
                                success: response.success,
                                message: response.success ? '分析成功' : response.data.message
                            });
                        },
                        complete: function() {
                            currentIndex++;
                            setTimeout(analyzeNextPost, 500); // 延迟500ms避免API限制
                        }
                    });
                }

                analyzeNextPost();
            });

            // 显示SEO报告 - 使用完整AI分析逻辑
            function showSEOReport(data) {
                var modal = $('#seo-result-modal');
                var content = $('#seo-result-content');

                console.log('=== showSEOReport 调试信息 ===');
                console.log('SEOReportDisplay:', typeof window.SEOReportDisplay);
                console.log('SEOAnalyzer:', typeof window.SEOAnalyzer);
                console.log('数据结构:', data);

                // 清空内容容器，确保没有重复标题
                content.empty();

                // 强制使用新的SEOReportDisplay组件
                try {
                    if (typeof window.SEOReportDisplay !== 'undefined') {
                        console.log('使用新的SEOReportDisplay组件');
                        var reportDisplay = new SEOReportDisplay();
                        reportDisplay.displayCompleteReport(data, '#seo-result-content');
                    } else if (typeof window.SEOAnalyzer !== 'undefined') {
                        console.log('降级使用旧的SEO分析器');
                        // 使用新的完整显示逻辑，而不是旧的displaySimpleReport
                        var html = '<div class="seo-ai-report-container">';

                        // 手动构建完整的AI分析报告
                        html += '<div class="report-header">';
                        html += '<h2>🤖 AI SEO 完整分析报告</h2>';
                        html += '<div class="report-meta">';
                        html += '<span class="report-date">分析时间: ' + new Date().toLocaleString('zh-CN') + '</span>';
                        html += '<span class="ai-provider">AI引擎: ' + (data.ai_provider || 'DeepSeek') + '</span>';
                        html += '</div>';
                        html += '</div>';

                        // 显示AI分析数据
                        if (data.raw_ai_analysis) {
                            html += '<div class="ai-full-analysis">';
                            html += '<h3>🧠 AI 完整分析</h3>';
                            try {
                                var aiData = JSON.parse(data.raw_ai_analysis);
                                if (aiData.keywords && aiData.keywords.length > 0) {
                                    html += '<div class="keyword-section">';
                                    html += '<h4>🎯 关键词</h4>';
                                    aiData.keywords.forEach(function(keyword) {
                                        html += '<span class="keyword-tag">' + keyword + '</span>';
                                    });
                                    html += '</div>';
                                }
                                if (aiData.recommendations && aiData.recommendations.length > 0) {
                                    html += '<div class="recommendations-section">';
                                    html += '<h4>💡 优化建议</h4>';
                                    aiData.recommendations.forEach(function(rec, index) {
                                        html += '<div class="recommendation-item">';
                                        html += '<h5>' + (index + 1) + '. ' + (rec.title || '建议') + '</h5>';
                                        if (rec.description) {
                                            html += '<p><strong>问题描述:</strong> ' + rec.description + '</p>';
                                        }
                                        if (rec.action) {
                                            html += '<p><strong>操作步骤:</strong> ' + rec.action + '</p>';
                                        }
                                        if (rec.impact) {
                                            html += '<p><strong>预期效果:</strong> ' + rec.impact + '</p>';
                                        }
                                        html += '</div>';
                                    });
                                    html += '</div>';
                                }
                            } catch (e) {
                                html += '<div class="raw-analysis">';
                                html += '<pre>' + data.raw_ai_analysis + '</pre>';
                                html += '</div>';
                            }
                            html += '</div>';
                        }

                        // 显示基础得分信息
                        html += '<div class="score-details">';
                        html += '<h3>📈 SEO 得分详情</h3>';
                        html += '<p><strong>整体得分:</strong> ' + (data.overall_score || 0) + '</p>';
                        html += '<p><strong>标题得分:</strong> ' + (data.title_score || 0) + '</p>';
                        html += '<p><strong>内容得分:</strong> ' + (data.content_score || 0) + '</p>';
                        html += '<p><strong>关键词得分:</strong> ' + (data.keyword_score || 0) + '</p>';
                        html += '<p><strong>可读性得分:</strong> ' + (data.readability_score || 0) + '</p>';
                        html += '</div>';

                        html += '</div>';
                        content.html(html);
                    } else {
                        // 完全降级方案
                        console.log('使用完全降级方案');
                        var html = '<div class="seo-analysis-result">';
                        html += '<h2>🤖 AI SEO 分析报告</h2>';
                        html += '<p><strong>文章：</strong>' + (data.post_title || '未知') + '</p>';
                        html += '<p><strong>整体得分：</strong>' + (data.overall_score || 0) + '</p>';

                        // 显示原始AI分析数据
                        if (data.raw_ai_analysis) {
                            html += '<div class="ai-analysis-section">';
                            html += '<h3>🧠 AI 分析内容</h3>';
                            html += '<div class="ai-content">';
                            html += '<pre>' + data.raw_ai_analysis + '</pre>';
                            html += '</div>';
                            html += '</div>';
                        }
                        html += '</div>';
                        content.html(html);
                    }
                } catch (error) {
                    console.error('显示报告时出错:', error);
                    content.html('<div class="notice notice-error"><p>显示报告时出错: ' + error.message + '</p></div>');
                }

                modal.show();
                console.log('=== showSEOReport 结束 ===');
            }

            // 模态框关闭
            $('.modal-close, .modal-backdrop').on('click', function() {
                $('#seo-result-modal').hide();
            });

            // 全选/取消全选
            $('#cb-select-all-1').on('change', function() {
                $('.post-checkbox').prop('checked', $(this).prop('checked'));
            });

            // 生成摘要按钮点击事件
            $('.generate-excerpt-btn').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var postId = $button.data('post-id');

                if (!postId) {
                    alert('文章ID无效');
                    return;
                }

                // 禁用按钮，显示加载状态
                var originalText = $button.html();
                $button.prop('disabled', true)
                       .html('🔄 生成中...')
                       .css('opacity', '0.6');

                // 发送AJAX请求
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_generate',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("auto_excerpt_generate"); ?>'
                    },
                    success: function(response) {
                        // 恢复按钮状态
                        $button.prop('disabled', false)
                               .html(originalText)
                               .css('opacity', '1');

                        if (response.success) {
                            // 显示成功消息
                            $('<div class="notice notice-success is-dismissible"><p>' +
                              response.data.message + '</p></div>')
                                .insertAfter('.wrap h1')
                                .delay(3000)
                                .fadeOut(500, function() { $(this).remove(); });

                            // 刷新页面以更新摘要状态
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            // 显示错误消息
                            $('<div class="notice notice-error is-dismissible"><p>' +
                              (response.data.message || '生成摘要失败') + '</p></div>')
                                .insertAfter('.wrap h1')
                                .delay(5000)
                                .fadeOut(500, function() { $(this).remove(); });
                        }
                    },
                    error: function(xhr, status, error) {
                        // 恢复按钮状态
                        $button.prop('disabled', false)
                               .html(originalText)
                               .css('opacity', '1');

                        // 显示错误消息
                        $('<div class="notice notice-error is-dismissible"><p>' +
                          '网络错误：' + error + '</p></div>')
                            .insertAfter('.wrap h1')
                            .delay(5000)
                            .fadeOut(500, function() { $(this).remove(); });
                    }
                });
            });

            // 生成标签按钮点击事件
            $('.generate-tags-btn').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var postId = $button.data('post-id');

                if (!postId) {
                    alert('文章ID无效');
                    return;
                }

                // 禁用按钮，显示加载状态
                var originalText = $button.html();
                $button.prop('disabled', true)
                       .html('🔄 生成中...')
                       .css('opacity', '0.6');

                // 发送AJAX请求
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'auto_excerpt_generate_tags',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("auto_excerpt_generate_tags"); ?>'
                    },
                    success: function(response) {
                        // 恢复按钮状态
                        $button.prop('disabled', false)
                               .html(originalText)
                               .css('opacity', '1');

                        if (response.success) {
                            // 显示成功消息
                            $('<div class="notice notice-success is-dismissible"><p>' +
                              response.data.message + '</p></div>')
                                .insertAfter('.wrap h1')
                                .delay(3000)
                                .fadeOut(500, function() { $(this).remove(); });
                        } else {
                            // 显示错误消息
                            $('<div class="notice notice-error is-dismissible"><p>' +
                              (response.data.message || '生成标签失败') + '</p></div>')
                                .insertAfter('.wrap h1')
                                .delay(5000)
                                .fadeOut(500, function() { $(this).remove(); });
                        }
                    },
                    error: function(xhr, status, error) {
                        // 恢复按钮状态
                        $button.prop('disabled', false)
                               .html(originalText)
                               .css('opacity', '1');

                        // 显示错误消息
                        $('<div class="notice notice-error is-dismissible"><p>' +
                          '网络错误：' + error + '</p></div>')
                            .insertAfter('.wrap h1')
                            .delay(5000)
                            .fadeOut(500, function() { $(this).remove(); });
                    }
                });
            });

            // SEO分析按钮点击事件
            $('.seo-analyze-btn').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var postId = $button.data('post-id');

                if (!postId) {
                    alert('文章ID无效');
                    return;
                }

                // 禁用按钮，显示加载状态
                var originalText = $button.html();
                $button.prop('disabled', true)
                       .html('🔄 分析中...')
                       .css('opacity', '0.6');

                // 发送AJAX请求
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'analyze_post_seo',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("analyze_post_seo"); ?>'
                    },
                    success: function(response) {
                        // 恢复按钮状态
                        $button.prop('disabled', false)
                               .html(originalText)
                               .css('opacity', '1');

                        if (response.success) {
                            // 显示成功消息
                            $('<div class="notice notice-success is-dismissible"><p>' +
                              response.data.message + '</p></div>')
                                .insertAfter('.wrap h1')
                                .delay(3000)
                                .fadeOut(500, function() { $(this).remove(); });

                            // 刷新页面以更新SEO分数
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            // 显示错误消息
                            $('<div class="notice notice-error is-dismissible"><p>' +
                              (response.data.message || 'SEO分析失败') + '</p></div>')
                                .insertAfter('.wrap h1')
                                .delay(5000)
                                .fadeOut(500, function() { $(this).remove(); });
                        }
                    },
                    error: function(xhr, status, error) {
                        // 恢复按钮状态
                        $button.prop('disabled', false)
                               .html(originalText)
                               .css('opacity', '1');

                        // 显示错误消息
                        $('<div class="notice notice-error is-dismissible"><p>' +
                          '网络错误：' + error + '</p></div>')
                            .insertAfter('.wrap h1')
                            .delay(5000)
                            .fadeOut(500, function() { $(this).remove(); });
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 渲染设置标签页
     */
    private function render_settings_tab() {
        $settings = $this->module->get_settings();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('auto_excerpt_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_generate"><?php _e('自动生成摘要', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="auto_generate" name="auto_generate" value="1"
                               <?php checked($settings['auto_generate']); ?>>
                        <span class="description"><?php _e('保存文章时自动为没有摘要的文章生成摘要', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="excerpt_length"><?php _e('摘要长度', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="excerpt_length" name="excerpt_length"
                               value="<?php echo $settings['excerpt_length']; ?>"
                               min="50" max="500" step="10">
                        <span class="description"><?php _e('字符（建议100-200字符）', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="smart_extraction"><?php _e('智能提取', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="smart_extraction" name="smart_extraction" value="1"
                               <?php checked($settings['smart_extraction']); ?>>
                        <span class="description"><?php _e('优先提取文章关键句子，保持语义完整', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="preserve_formatting"><?php _e('保留格式', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="preserve_formatting" name="preserve_formatting" value="1"
                               <?php checked($settings['preserve_formatting']); ?>>
                        <span class="description"><?php _e('在摘要中保留基本的HTML格式标签', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="min_content_length"><?php _e('最小内容长度', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="min_content_length" name="min_content_length"
                               value="<?php echo $settings['min_content_length']; ?>"
                               min="50" max="1000" step="10">
                        <span class="description"><?php _e('字符（内容少于此长度时不生成摘要）', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="save_settings" class="button button-primary"
                       value="<?php _e('保存设置', 'wordpress-ai-toolkit'); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * 渲染批量操作标签页
     */
    private function render_batch_tab() {
        ?>
        <h3><?php _e('批量生成摘要', 'wordpress-ai-toolkit'); ?></h3>
        <p><?php _e('为现有的文章批量生成摘要。您可以选择文章类型、数量限制，以及是否覆盖已有摘要。', 'wordpress-ai-toolkit'); ?></p>

        <div class="batch-progress">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <div class="progress-text"><?php _e('准备开始...', 'wordpress-ai-toolkit'); ?></div>
        </div>

        <form id="batch-generate-form" method="post" action="">
            <?php wp_nonce_field('auto_excerpt_batch'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batch_post_type"><?php _e('文章类型', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <select id="batch_post_type" name="batch_post_type">
                            <option value="post"><?php _e('文章', 'wordpress-ai-toolkit'); ?></option>
                            <option value="page"><?php _e('页面', 'wordpress-ai-toolkit'); ?></option>
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            foreach ($post_types as $post_type) {
                                if (!in_array($post_type->name, array('post', 'page', 'attachment'))) {
                                    ?>
                                    <option value="<?php echo $post_type->name; ?>">
                                        <?php echo $post_type->labels->singular_name; ?>
                                    </option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="batch_limit"><?php _e('处理数量', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="batch_limit" name="batch_limit" value="50" min="1" max="1000" step="10">
                        <span class="description"><?php _e('一次最多处理的文章数量', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="batch_overwrite"><?php _e('覆盖已有摘要', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="batch_overwrite" name="batch_overwrite" value="1">
                        <span class="description"><?php _e('勾选此项将覆盖已有的摘要内容', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="batch_generate" class="button button-primary"
                       value="<?php _e('开始批量生成', 'wordpress-ai-toolkit'); ?>">
            </p>
        </form>

        <div id="batch-results"></div>
        <?php
    }

    /**
     * 渲染数据分析标签页
     */
    private function render_analytics_tab($stats) {
        ?>
        <h3><?php _e('摘要数据统计', 'wordpress-ai-toolkit'); ?></h3>

        <div class="analytics-chart">
            <h4><?php _e('摘要长度分布', 'wordpress-ai-toolkit'); ?></h4>
            <div class="chart-container">
                <?php
                // 生成摘要长度分布图表数据
                $length_distribution = $this->get_excerpt_length_distribution();

                if (!empty($length_distribution)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>' . __('长度范围', 'wordpress-ai-toolkit') . '</th><th>' . __('文章数量', 'wordpress-ai-toolkit') . '</th><th>' . __('百分比', 'wordpress-ai-toolkit') . '</th></tr></thead>';
                    echo '<tbody>';

                    foreach ($length_distribution as $range => $count) {
                        $percentage = $stats['total_posts'] > 0 ? round(($count / $stats['total_posts']) * 100, 1) : 0;
                        echo '<tr>';
                        echo '<td>' . $range . '</td>';
                        echo '<td>' . $count . '</td>';
                        echo '<td>' . $percentage . '%</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<p>' . __('暂无数据', 'wordpress-ai-toolkit') . '</p>';
                }
                ?>
            </div>
        </div>

        <div class="analytics-chart">
            <h4><?php _e('最近生成的摘要', 'wordpress-ai-toolkit'); ?></h4>
            <?php
            $recent_excerpts = $this->get_recent_generated_excerpts(10);

            if (!empty($recent_excerpts)) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>' . __('文章标题', 'wordpress-ai-toolkit') . '</th><th>' . __('摘要长度', 'wordpress-ai-toolkit') . '</th><th>' . __('生成时间', 'wordpress-ai-toolkit') . '</th></tr></thead>';
                echo '<tbody>';

                foreach ($recent_excerpts as $post) {
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link($post->ID) . '" target="_blank">' . get_the_title($post->ID) . '</a></td>';
                    echo '<td>' . mb_strlen($post->post_excerpt) . ' ' . __('字符', 'wordpress-ai-toolkit') . '</td>';
                    echo '<td>' . get_the_modified_date('Y-m-d H:i:s', $post->ID) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            } else {
                echo '<p>' . __('暂无数据', 'wordpress-ai-toolkit') . '</p>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * 渲染高级选项标签页
     */
    private function render_advanced_tab() {
        ?>
        <h3><?php _e('高级设置', 'wordpress-ai-toolkit'); ?></h3>

        <form method="post" action="">
            <?php wp_nonce_field('auto_excerpt_advanced'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('排除的短代码', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <textarea name="exclude_shortcodes" rows="4" class="large-text"
                                  placeholder="gallery&#10;video&#10;audio&#10;caption"><?php
                            echo implode("\n", $this->module->get_settings()['exclude_shortcodes'] ?? array());
                        ?></textarea>
                        <span class="description"><?php _e('每行一个短代码名称，这些短代码的内容将在生成摘要时被忽略', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="excluded_tags"><?php _e('保留的HTML标签', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="excluded_tags" name="excluded_tags"
                               value="p,br,strong,em" class="regular-text">
                        <span class="description"><?php _e('逗号分隔的HTML标签列表，这些标签在清理内容时将被保留', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="custom_prompt"><?php _e('自定义提示词', 'wordpress-ai-toolkit'); ?></label>
                    </th>
                    <td>
                        <textarea id="custom_prompt" name="custom_prompt" rows="4" class="large-text"
                                  placeholder="请为以下内容生成一个简洁的摘要，突出重点信息..."></textarea>
                        <span class="description"><?php _e('用于指导摘要生成的提示词，留空使用默认提示词', 'wordpress-ai-toolkit'); ?></span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="save_advanced" class="button button-primary"
                       value="<?php _e('保存高级设置', 'wordpress-ai-toolkit'); ?>">
            </p>
        </form>

        <div class="card">
            <h4><?php _e('危险操作', 'wordpress-ai-toolkit'); ?></h4>
            <p><strong><?php _e('清除所有摘要', 'wordpress-ai-toolkit'); ?></strong></p>
            <p><?php _e('此操作将删除所有文章的摘要内容，无法撤销。请谨慎操作。', 'wordpress-ai-toolkit'); ?></p>
            <form method="post" action="" onsubmit="return confirm('<?php _e('确定要清除所有摘要吗？此操作无法撤销！', 'wordpress-ai-toolkit'); ?>')">
                <?php wp_nonce_field('auto_excerpt_clear_all'); ?>
                <input type="submit" name="clear_all_excerpts" class="button"
                       value="<?php _e('清除所有摘要', 'wordpress-ai-toolkit'); ?>">
            </form>
        </div>
        <?php
    }

    /**
     * 获取统计数据
     */
    private function get_statistics() {
        global $wpdb;

        $total_posts = wp_count_posts('post');
        $total_posts = $total_posts->publish;

        $posts_with_excerpt = (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_status = 'publish'
            AND post_excerpt != ''
        ");

        $posts_without_excerpt = $total_posts - $posts_with_excerpt;
        $coverage_rate = $total_posts > 0 ? round(($posts_with_excerpt / $total_posts) * 100, 1) : 0;

        return array(
            'total_posts' => $total_posts,
            'posts_with_excerpt' => $posts_with_excerpt,
            'posts_without_excerpt' => $posts_without_excerpt,
            'coverage_rate' => $coverage_rate
        );
    }

    /**
     * 获取摘要长度分布
     */
    private function get_excerpt_length_distribution() {
        global $wpdb;

        $results = $wpdb->get_results("
            SELECT
                CASE
                    WHEN LENGTH(post_excerpt) <= 50 THEN '0-50字符'
                    WHEN LENGTH(post_excerpt) <= 100 THEN '51-100字符'
                    WHEN LENGTH(post_excerpt) <= 150 THEN '101-150字符'
                    WHEN LENGTH(post_excerpt) <= 200 THEN '151-200字符'
                    WHEN LENGTH(post_excerpt) <= 300 THEN '201-300字符'
                    ELSE '300+字符'
                END as length_range,
                COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_status = 'publish'
            AND post_excerpt != ''
            GROUP BY length_range
            ORDER BY LENGTH(post_excerpt)
        ");

        $distribution = array();
        foreach ($results as $result) {
            $distribution[$result->length_range] = (int) $result->count;
        }

        return $distribution;
    }

    /**
     * 获取最近生成的摘要
     */
    private function get_recent_generated_excerpts($limit = 10) {
        return get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'post_excerpt',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));
    }

    /**
     * 处理表单提交
     */
    private function handle_form_submission() {
        if (isset($_POST['save_settings'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'auto_excerpt_settings')) {
                wp_die(__('安全验证失败', 'wordpress-ai-toolkit'));
            }

            $settings = array(
                'auto_generate' => isset($_POST['auto_generate']),
                'excerpt_length' => intval($_POST['excerpt_length']),
                'smart_extraction' => isset($_POST['smart_extraction']),
                'preserve_formatting' => isset($_POST['preserve_formatting']),
                'min_content_length' => intval($_POST['min_content_length'])
            );

            $this->module->update_settings($settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('设置保存成功！', 'wordpress-ai-toolkit') . '</p></div>';
        }

        if (isset($_POST['save_advanced'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'auto_excerpt_advanced')) {
                wp_die(__('安全验证失败', 'wordpress-ai-toolkit'));
            }

            $settings = $this->module->get_settings();

            // 处理排除的短代码
            if (!empty($_POST['exclude_shortcodes'])) {
                $exclude_shortcodes = array_filter(array_map('trim', explode("\n", $_POST['exclude_shortcodes'])));
                $settings['exclude_shortcodes'] = $exclude_shortcodes;
            }

            // 处理其他高级设置
            $settings['excluded_tags'] = sanitize_text_field($_POST['excluded_tags']);
            $settings['custom_prompt'] = sanitize_textarea_field($_POST['custom_prompt']);

            $this->module->update_settings($settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('高级设置保存成功！', 'wordpress-ai-toolkit') . '</p></div>';
        }

        if (isset($_POST['clear_all_excerpts'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'auto_excerpt_clear_all')) {
                wp_die(__('安全验证失败', 'wordpress-ai-toolkit'));
            }

            global $wpdb;
            $wpdb->query("
                UPDATE {$wpdb->posts}
                SET post_excerpt = ''
                WHERE post_type = 'post'
            ");

            echo '<div class="notice notice-success is-dismissible"><p>' . __('所有摘要已清除！', 'wordpress-ai-toolkit') . '</p></div>';
        }
    }

    /**
     * 处理批量操作
     */
    public function handle_batch_operations() {
        if (isset($_POST['action']) && $_POST['action'] === 'auto_excerpt_batch_generate') {
            if (!wp_verify_nonce($_POST['nonce'], 'auto_excerpt_batch')) {
                wp_send_json_error(__('安全验证失败', 'wordpress-ai-toolkit'));
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('权限不足', 'wordpress-ai-toolkit'));
            }

            $post_type = sanitize_text_field($_POST['post_type']);
            $limit = intval($_POST['limit']);
            $overwrite = isset($_POST['overwrite']);

            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'modified',
                'order' => 'DESC'
            );

            if (!$overwrite) {
                $args['meta_query'] = array(
                    array(
                        'key' => 'post_excerpt',
                        'value' => '',
                        'compare' => '='
                    )
                );
            }

            $posts = get_posts($args);
            $processed = 0;

            foreach ($posts as $post) {
                $excerpt = $this->module->generate_excerpt($post->post_content);
                if ($excerpt) {
                    wp_update_post(array(
                        'ID' => $post->ID,
                        'post_excerpt' => $excerpt
                    ));
                    $processed++;
                }
            }

            wp_send_json_success(array(
                'message' => sprintf(__('成功处理了 %d 篇文章', 'wordpress-ai-toolkit'), $processed),
                'processed' => $processed
            ));
        }
    }

    
    /**
     * 加载SEO分析相关脚本和样式
     */
    public function enqueue_seo_scripts($hook) {
        // 只在自动摘要管理页面加载
        if (strpos($hook, 'wordpress-ai-toolkit-auto-excerpt') === false) {
            return;
        }

        // 加载SEO分析器样式
        wp_enqueue_style(
            'seo-analyzer-css',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/auto-excerpt/assets/css/seo-analyzer.css',
            array(),
            '1.0.0'
        );

        // 加载新的SEO报告显示样式
        wp_enqueue_style(
            'seo-report-display-css',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/auto-excerpt/assets/css/seo-report-display.css',
            array(),
            '1.0.0'
        );

        // 加载SEO分析器脚本
        wp_enqueue_script(
            'seo-analyzer-js',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/auto-excerpt/assets/js/seo-analyzer.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // 加载新的SEO报告显示组件
        wp_enqueue_script(
            'seo-report-display-js',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/auto-excerpt/assets/js/seo-report-display.js',
            array('seo-analyzer-js'),
            '1.0.0',
            true
        );
    }
}

// 初始化管理页面
Auto_Excerpt_Admin_Page::get_instance();