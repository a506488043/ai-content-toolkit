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

        // 处理批量操作
        add_action('admin_init', array($this, 'handle_batch_operations'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-toolkit',
            __('自动摘要管理', 'wordpress-toolkit'),
            __('自动摘要', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-auto-excerpt',
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

        // 处理表单提交
        $this->handle_form_submission();

        // 获取统计数据
        $stats = $this->get_statistics();

        // 获取设置
        $settings = $this->module->get_settings();
        ?>
        <div class="wrap auto-excerpt-admin">
            <h1><?php _e('自动摘要管理', 'wordpress-toolkit'); ?></h1>

            <!-- 统计卡片 -->
            <div class="auto-excerpt-stats-grid">
                <div class="stat-card">
                    <h3><?php _e('总文章数', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['total_posts']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('有摘要的文章', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['posts_with_excerpt']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('无摘要的文章', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['posts_without_excerpt']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('摘要覆盖率', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['coverage_rate']; ?>%</span>
                </div>
            </div>

            <!-- 标签页导航 -->
            <div class="auto-excerpt-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#settings" class="nav-tab nav-tab-active"><?php _e('基本设置', 'wordpress-toolkit'); ?></a>
                    <a href="#batch" class="nav-tab"><?php _e('批量操作', 'wordpress-toolkit'); ?></a>
                    <a href="#analytics" class="nav-tab"><?php _e('数据分析', 'wordpress-toolkit'); ?></a>
                    <a href="#advanced" class="nav-tab"><?php _e('高级选项', 'wordpress-toolkit'); ?></a>
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
        </style>

        <script>
        jQuery(document).ready(function($) {
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
                    nonce: '<?php echo wp_create_nonce('auto_excerpt_batch'); ?>',
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
                        <label for="auto_generate"><?php _e('自动生成摘要', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="auto_generate" name="auto_generate" value="1"
                               <?php checked($settings['auto_generate']); ?>>
                        <span class="description"><?php _e('保存文章时自动为没有摘要的文章生成摘要', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="excerpt_length"><?php _e('摘要长度', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="excerpt_length" name="excerpt_length"
                               value="<?php echo $settings['excerpt_length']; ?>"
                               min="50" max="500" step="10">
                        <span class="description"><?php _e('字符（建议100-200字符）', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="smart_extraction"><?php _e('智能提取', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="smart_extraction" name="smart_extraction" value="1"
                               <?php checked($settings['smart_extraction']); ?>>
                        <span class="description"><?php _e('优先提取文章关键句子，保持语义完整', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="preserve_formatting"><?php _e('保留格式', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="preserve_formatting" name="preserve_formatting" value="1"
                               <?php checked($settings['preserve_formatting']); ?>>
                        <span class="description"><?php _e('在摘要中保留基本的HTML格式标签', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="min_content_length"><?php _e('最小内容长度', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="min_content_length" name="min_content_length"
                               value="<?php echo $settings['min_content_length']; ?>"
                               min="50" max="1000" step="10">
                        <span class="description"><?php _e('字符（内容少于此长度时不生成摘要）', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="save_settings" class="button button-primary"
                       value="<?php _e('保存设置', 'wordpress-toolkit'); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * 渲染批量操作标签页
     */
    private function render_batch_tab() {
        ?>
        <h3><?php _e('批量生成摘要', 'wordpress-toolkit'); ?></h3>
        <p><?php _e('为现有的文章批量生成摘要。您可以选择文章类型、数量限制，以及是否覆盖已有摘要。', 'wordpress-toolkit'); ?></p>

        <div class="batch-progress">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <div class="progress-text"><?php _e('准备开始...', 'wordpress-toolkit'); ?></div>
        </div>

        <form id="batch-generate-form" method="post" action="">
            <?php wp_nonce_field('auto_excerpt_batch'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batch_post_type"><?php _e('文章类型', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <select id="batch_post_type" name="batch_post_type">
                            <option value="post"><?php _e('文章', 'wordpress-toolkit'); ?></option>
                            <option value="page"><?php _e('页面', 'wordpress-toolkit'); ?></option>
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
                        <label for="batch_limit"><?php _e('处理数量', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="batch_limit" name="batch_limit" value="50" min="1" max="1000" step="10">
                        <span class="description"><?php _e('一次最多处理的文章数量', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="batch_overwrite"><?php _e('覆盖已有摘要', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="batch_overwrite" name="batch_overwrite" value="1">
                        <span class="description"><?php _e('勾选此项将覆盖已有的摘要内容', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="batch_generate" class="button button-primary"
                       value="<?php _e('开始批量生成', 'wordpress-toolkit'); ?>">
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
        <h3><?php _e('摘要数据统计', 'wordpress-toolkit'); ?></h3>

        <div class="analytics-chart">
            <h4><?php _e('摘要长度分布', 'wordpress-toolkit'); ?></h4>
            <div class="chart-container">
                <?php
                // 生成摘要长度分布图表数据
                $length_distribution = $this->get_excerpt_length_distribution();

                if (!empty($length_distribution)) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>' . __('长度范围', 'wordpress-toolkit') . '</th><th>' . __('文章数量', 'wordpress-toolkit') . '</th><th>' . __('百分比', 'wordpress-toolkit') . '</th></tr></thead>';
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
                    echo '<p>' . __('暂无数据', 'wordpress-toolkit') . '</p>';
                }
                ?>
            </div>
        </div>

        <div class="analytics-chart">
            <h4><?php _e('最近生成的摘要', 'wordpress-toolkit'); ?></h4>
            <?php
            $recent_excerpts = $this->get_recent_generated_excerpts(10);

            if (!empty($recent_excerpts)) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>' . __('文章标题', 'wordpress-toolkit') . '</th><th>' . __('摘要长度', 'wordpress-toolkit') . '</th><th>' . __('生成时间', 'wordpress-toolkit') . '</th></tr></thead>';
                echo '<tbody>';

                foreach ($recent_excerpts as $post) {
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link($post->ID) . '" target="_blank">' . get_the_title($post->ID) . '</a></td>';
                    echo '<td>' . mb_strlen($post->post_excerpt) . ' ' . __('字符', 'wordpress-toolkit') . '</td>';
                    echo '<td>' . get_the_modified_date('Y-m-d H:i:s', $post->ID) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            } else {
                echo '<p>' . __('暂无数据', 'wordpress-toolkit') . '</p>';
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
        <h3><?php _e('高级设置', 'wordpress-toolkit'); ?></h3>

        <form method="post" action="">
            <?php wp_nonce_field('auto_excerpt_advanced'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e('排除的短代码', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <textarea name="exclude_shortcodes" rows="4" class="large-text"
                                  placeholder="gallery&#10;video&#10;audio&#10;caption"><?php
                            echo implode("\n", $this->module->get_settings()['exclude_shortcodes'] ?? array());
                        ?></textarea>
                        <span class="description"><?php _e('每行一个短代码名称，这些短代码的内容将在生成摘要时被忽略', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="excluded_tags"><?php _e('保留的HTML标签', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="excluded_tags" name="excluded_tags"
                               value="p,br,strong,em" class="regular-text">
                        <span class="description"><?php _e('逗号分隔的HTML标签列表，这些标签在清理内容时将被保留', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="custom_prompt"><?php _e('自定义提示词', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <textarea id="custom_prompt" name="custom_prompt" rows="4" class="large-text"
                                  placeholder="请为以下内容生成一个简洁的摘要，突出重点信息..."></textarea>
                        <span class="description"><?php _e('用于指导摘要生成的提示词，留空使用默认提示词', 'wordpress-toolkit'); ?></span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="save_advanced" class="button button-primary"
                       value="<?php _e('保存高级设置', 'wordpress-toolkit'); ?>">
            </p>
        </form>

        <div class="card">
            <h4><?php _e('危险操作', 'wordpress-toolkit'); ?></h4>
            <p><strong><?php _e('清除所有摘要', 'wordpress-toolkit'); ?></strong></p>
            <p><?php _e('此操作将删除所有文章的摘要内容，无法撤销。请谨慎操作。', 'wordpress-toolkit'); ?></p>
            <form method="post" action="" onsubmit="return confirm('<?php _e('确定要清除所有摘要吗？此操作无法撤销！', 'wordpress-toolkit'); ?>')">
                <?php wp_nonce_field('auto_excerpt_clear_all'); ?>
                <input type="submit" name="clear_all_excerpts" class="button"
                       value="<?php _e('清除所有摘要', 'wordpress-toolkit'); ?>">
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
                wp_die(__('安全验证失败', 'wordpress-toolkit'));
            }

            $settings = array(
                'auto_generate' => isset($_POST['auto_generate']),
                'excerpt_length' => intval($_POST['excerpt_length']),
                'smart_extraction' => isset($_POST['smart_extraction']),
                'preserve_formatting' => isset($_POST['preserve_formatting']),
                'min_content_length' => intval($_POST['min_content_length'])
            );

            $this->module->update_settings($settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('设置保存成功！', 'wordpress-toolkit') . '</p></div>';
        }

        if (isset($_POST['save_advanced'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'auto_excerpt_advanced')) {
                wp_die(__('安全验证失败', 'wordpress-toolkit'));
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
            echo '<div class="notice notice-success is-dismissible"><p>' . __('高级设置保存成功！', 'wordpress-toolkit') . '</p></div>';
        }

        if (isset($_POST['clear_all_excerpts'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'auto_excerpt_clear_all')) {
                wp_die(__('安全验证失败', 'wordpress-toolkit'));
            }

            global $wpdb;
            $wpdb->query("
                UPDATE {$wpdb->posts}
                SET post_excerpt = ''
                WHERE post_type = 'post'
            ");

            echo '<div class="notice notice-success is-dismissible"><p>' . __('所有摘要已清除！', 'wordpress-toolkit') . '</p></div>';
        }
    }

    /**
     * 处理批量操作
     */
    public function handle_batch_operations() {
        if (isset($_POST['action']) && $_POST['action'] === 'auto_excerpt_batch_generate') {
            if (!wp_verify_nonce($_POST['nonce'], 'auto_excerpt_batch')) {
                wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
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
                'message' => sprintf(__('成功处理了 %d 篇文章', 'wordpress-toolkit'), $processed),
                'processed' => $processed
            ));
        }
    }
}

// 初始化管理页面
Auto_Excerpt_Admin_Page::get_instance();