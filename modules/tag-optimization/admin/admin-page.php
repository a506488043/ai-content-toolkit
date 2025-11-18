<?php
/**
 * Tag Optimization Admin Page
 *
 * 标签优化管理页面 - 使用与文章优化页面相同的样式和布局
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag Optimization Admin Page 类
 */
class Tag_Optimization_Admin_Page {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 标签优化模块实例
     */
    private $tag_optimization = null;

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
        $this->tag_optimization = Tag_Optimization_Module::get_instance();
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 不需要注册菜单，菜单已在主插件中注册
    }

    /**
     * 管理页面
     */
    public function admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 验证nonce（防止CSRF攻击）
        if (isset($_POST['action']) && !wp_verify_nonce($_POST['_wpnonce'], 'wordpress_toolkit_tag_optimization')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 显示管理页面
        ?>
        <div class="wrap">
            <?php
            error_log("Tag Optimization: Loading admin page");
            $stats = $this->tag_optimization->get_statistics();
            error_log("Tag Optimization: Stats loaded - " . print_r($stats, true));
            ?>

            <div class="postbox" style="margin-top: 15px; margin-bottom: 10px;">
                <div class="inside" style="padding: 12px 15px;">
                    <div style="display: flex; align-items: center; gap: 30px; padding: 0; flex-wrap: wrap; justify-content: space-between;">
                        <div>
                            <strong><?php _e('标签总数', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-tag" style="color: #0073aa;"></span>
                                <?php echo number_format($stats['total_tags']); ?>
                            </div>
                        </div>
                        <div>
                            <strong><?php _e('有描述标签', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php echo number_format($stats['tags_with_description']); ?>
                            </div>
                        </div>
                        <div>
                            <strong><?php _e('无描述标签数量', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-no-alt" style="color: #d63638;"></span>
                                <?php echo number_format($stats['tags_without_description']); ?>
                            </div>
                        </div>
                        <div>
                            <strong><?php _e('描述覆盖率', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px; display: flex; align-items: center; gap: 10px;">
                                <span class="dashicons dashicons-chart-bar" style="color: #0073aa;"></span>
                                <span><?php echo $stats['coverage_rate']; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="postbox" style="margin-top: 10px;">
                <div class="inside" style="padding: 15px;">
                    <?php
                    // 获取分页数据
                    $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
                    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

                    error_log("Tag Optimization: Loading tag list - page: $current_page, status: $status");
                    $tags_list = $this->tag_optimization->get_tags_list($current_page, 15, $status);
                    error_log("Tag Optimization: Tag list loaded - " . print_r($tags_list, true));
                    ?>

                    <!-- 筛选器、批量操作和分页放在同一行 -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                        <!-- 左侧：筛选器和批量操作 -->
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <form method="get" action="" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                                <input type="hidden" name="page" value="wordpress-toolkit-tag-optimization">
                                <select name="status" id="tag-status-filter">
                                    <option value="all" <?php selected(isset($_GET['status']) ? $_GET['status'] : 'all', 'all'); ?>><?php _e('全部标签', 'wordpress-toolkit'); ?></option>
                                    <option value="with_description" <?php selected(isset($_GET['status']) ? $_GET['status'] : 'all', 'with_description'); ?>><?php _e('有描述标签', 'wordpress-toolkit'); ?></option>
                                    <option value="without_description" <?php selected(isset($_GET['status']) ? $_GET['status'] : 'all', 'without_description'); ?>><?php _e('无描述标签', 'wordpress-toolkit'); ?></option>
                                </select>
                                <button type="submit" class="button"><?php _e('筛选', 'wordpress-toolkit'); ?></button>

                                <span style="margin: 0 5px; color: #666;">|</span>

                                <button type="button" id="batch-generate-descriptions" class="button button-primary">
                                    <?php _e('为无描述标签生成描述', 'wordpress-toolkit'); ?>
                                </button>
                                <span class="spinner" id="batch-generate-spinner" style="display: none; margin-left: 5px;"></span>
                            </form>
                        </div>

                        <!-- 右侧：分页 -->
                        <?php if (!empty($tags_list) && isset($tags_list['pages']) && $tags_list['pages'] > 1): ?>
                        <div class="tablenav-pages" style="margin: 0;">
                            <?php
                            $current_url = admin_url('admin.php?page=wordpress-toolkit-tag-optimization');
                            if (isset($_GET['status'])) {
                                $current_url .= '&status=' . urlencode($_GET['status']);
                            }
                            ?>
                            <span class="displaying-num">
                                <?php printf(__('共 %d 个项目', 'wordpress-toolkit'), $tags_list['total']); ?>
                            </span>
                            <?php
                            // 使用WordPress标准的paginate_links函数
                            echo paginate_links(array(
                                'base' => $current_url . '&paged=%#%',
                                'format' => '',
                                'prev_text' => __('&laquo; 上一页'),
                                'next_text' => __('下一页 &raquo;'),
                                'total' => $tags_list['pages'],
                                'current' => $current_page
                            ));
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- 批量操作进度 -->
                    <div id="batch-generate-progress" style="display: none; margin: 15px 0;">
                        <div class="progress-container">
                            <h4 id="progress-title">处理中...</h4>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill"></div>
                                </div>
                                <span class="progress-text" id="progress-text">0%</span>
                            </div>
                            <div class="progress-details" id="progress-details">
                                <span>当前处理：<span id="current-tag">准备中...</span></span>
                                <span>已处理：<span id="processed-count">0</span> / <span id="total-count">0</span></span>
                                <span>成功：<span id="success-count">0</span></span>
                                <span>失败：<span id="error-count">0</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- 批量操作结果 -->
                    <div id="batch-generate-result" style="display: none; margin: 15px 0;"></div>

                    <!-- 标签列表 -->
                    <?php
                    // 添加调试信息和错误处理
                    if (empty($tags_list) || !isset($tags_list['tags'])) {
                        echo '<div class="notice notice-warning"><p>标签列表数据加载失败，请检查错误日志。</p></div>';
                        error_log("Tag Optimization: Tag list data is invalid");
                    } elseif (empty($tags_list['tags'])) {
                        // 显示空状态
                        ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" width="30%"><?php _e('标签名称', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="10%"><?php _e('描述状态', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="10%"><?php _e('描述长度', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="10%"><?php _e('文章数量', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="20%"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <?php
                                        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
                                        if ($current_status !== 'all'):
                                        ?>
                                        <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                            <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                            没有找到匹配的<?php echo $current_status === 'with_description' ? '有描述' : '无描述'; ?>标签
                                        </div>
                                        <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-tag-optimization'); ?>" class="button button-primary">
                                            清除筛选条件
                                        </a>
                                        <?php else: ?>
                                        <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                            <span class="dashicons dashicons-tag" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                            暂无标签数据
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php
                        error_log("Tag Optimization: No tags found matching criteria");
                    } else {
                        error_log("Tag Optimization: Displaying " . count($tags_list['tags']) . " tags");
                    ?>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" width="30%"><?php _e('标签名称', 'wordpress-toolkit'); ?></th>
                                <th scope="col" width="10%"><?php _e('描述状态', 'wordpress-toolkit'); ?></th>
                                <th scope="col" width="10%"><?php _e('描述长度', 'wordpress-toolkit'); ?></th>
                                <th scope="col" width="10%"><?php _e('文章数量', 'wordpress-toolkit'); ?></th>
                                <th scope="col" width="20%"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tags_list['tags'] as $tag): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?php echo esc_url($tag['edit_url']); ?>" target="_blank"><?php echo esc_html($tag['name']); ?></a></strong>
                                    <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                        <?php echo esc_html($tag['slug']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($tag['has_description']): ?>
                                        <span class="status-active"><?php _e('有描述', 'wordpress-toolkit'); ?></span>
                                    <?php else: ?>
                                        <span class="status-inactive"><?php _e('无描述', 'wordpress-toolkit'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $tag['description_length']; ?> <?php _e('字符', 'wordpress-toolkit'); ?></td>
                                <td><?php echo $tag['post_count']; ?> <?php _e('篇', 'wordpress-toolkit'); ?></td>
                                <td>
                                    <div class="action-buttons-container">
                                        <a href="<?php echo esc_url($tag['edit_url']); ?>" class="button button-small" target="_blank" style="background: #646970; color: white; border-color: #646970; margin: 0; text-decoration: none;"><?php _e('编辑', 'wordpress-toolkit'); ?></a>
                                        <a href="<?php echo esc_url($tag['view_url']); ?>" class="button button-small" target="_blank" style="background: #646970; color: white; border-color: #646970; margin: 0; text-decoration: none;"><?php _e('查看', 'wordpress-toolkit'); ?></a>
                                        <?php if (!$tag['has_description']): ?>
                                        <button type="button" class="button button-small generate-description-single" data-tag-id="<?php echo $tag['ID']; ?>" data-tag-name="<?php echo esc_attr($tag['name']); ?>" title="为这个标签生成AI描述" style="background: #46b450; color: white; border-color: #46b450; margin: 0;">
                                            生成描述
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                        <?php } // End of else from tags check ?>
                </div>
            </div>
        </div>

        <style>
        /* 使用与文章优化页面相同的样式 */
        .status-active {
            color: #00a32a;
            font-weight: bold;
        }
        .status-inactive {
            color: #d63638;
            font-weight: bold;
        }

        /* 使用WordPress标准分页样式 */
        .tablenav-pages {
            margin-top: 0;
            background: #f8f9f9;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #e5e5e5;
            font-size: 13px;
        }

        .tablenav-pages .displaying-num {
            margin-right: 10px;
            color: #50575e;
        }

        .tablenav-pages .page-numbers {
            display: inline-block;
            padding: 4px 8px;
            margin: 0 2px;
            border: 1px solid #ccc;
            text-decoration: none;
            border-radius: 3px;
        }

        .tablenav-pages .page-numbers.current {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }

        .tablenav-pages .page-numbers:hover {
            background: #f1f1f1;
        }

        .tablenav-pages .page-numbers.current:hover {
            background: #0073aa;
        }

        /* 批量操作进度条样式 */
        .progress-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .progress-container h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
        }

        .progress-bar-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .progress-bar {
            flex: 1;
            height: 24px;
            background: #f1f1f1;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa 0%, #005a87 100%);
            border-radius: 12px;
            width: 0%;
            transition: width 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-text {
            font-weight: 600;
            color: #0073aa;
            font-size: 14px;
            min-width: 50px;
            text-align: center;
        }

        .progress-details {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
            color: #555;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #0073aa;
        }

        .progress-details span {
            display: inline-block;
            min-width: 100px;
        }

        .progress-details span span {
            font-weight: 600;
            color: #0073aa;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // 统计信息
            var stats = {
                total_tags: <?php echo $stats['total_tags']; ?>,
                without_description: <?php echo $stats['tags_without_description']; ?>
            };

            // 进度更新函数
            function updateProgress(title, percentage, processed, success, errors, currentTag, totalCount) {
                // 更新标题和进度条
                if (percentage === 100) {
                    $('#progress-title').text(title + ' - ' + currentTag);
                } else {
                    $('#progress-title').text(title + ' - 处理中...');
                }

                // 确保数据有效性
                processed = Math.max(0, processed || 0);
                success = Math.max(0, success || 0);
                errors = Math.max(0, errors || 0);

                $('#progress-fill').css('width', percentage + '%');
                $('#progress-text').text(percentage + '%');
                $('#current-tag').text(currentTag);
                $('#processed-count').text(processed);
                $('#success-count').text(success);
                $('#error-count').text(errors);

                // 更新总数显示
                if (totalCount !== undefined && totalCount !== null) {
                    $('#total-count').text(totalCount);
                } else {
                    // 智能更新总数显示
                    var $totalCount = $('#total-count');
                    if (percentage === 100 && processed > 0) {
                        $totalCount.text(processed);
                    } else if (processed > 0 && percentage < 100) {
                        if ($totalCount.text() === '0' || $totalCount.text() === '?') {
                            var estimated = Math.round(processed * 100 / percentage);
                            $totalCount.text(estimated);
                        }
                    }
                }

                // 完成时自动隐藏进度条
                if (percentage === 100) {
                    setTimeout(function() {
                        $('#batch-generate-progress').fadeOut(500);
                    }, 3000);
                }
            }

            // 批量生成描述
            $('#batch-generate-descriptions').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $spinner = $('#batch-generate-spinner');
                var $progress = $('#batch-generate-progress');
                var $result = $('#batch-generate-result');

                var estimatedTime = '30秒-2分钟';
                var showBatchOption = false;

                if (stats.without_description > 2000) {
                    estimatedTime = '15-30分钟';
                    showBatchOption = true;
                } else if (stats.without_description > 1000) {
                    estimatedTime = '8-15分钟';
                    showBatchOption = true;
                } else if (stats.without_description > 500) {
                    estimatedTime = '5-10分钟';
                } else if (stats.without_description > 100) {
                    estimatedTime = '2-5分钟';
                }

                var confirmMessage = '确定要为所有无描述标签批量生成描述吗？\n\n' +
                    '• 需要处理的标签数量：' + stats.without_description + ' 个\n' +
                    '• 预计处理时间：' + estimatedTime + '\n' +
                    '• 处理期间请勿关闭页面\n' +
                    '• 大量标签可能需要更长时间处理';

                if (showBatchOption) {
                    confirmMessage += '\n\n💡 **建议：对于' + stats.without_description + '个标签**\n' +
                        '考虑分批处理以获得更好的稳定性：\n' +
                        '• 分3-5批处理，每批300-500个\n' +
                        '• 每批处理间隔2-3分钟\n' +
                        '• 可以降低服务器压力和超时风险\n\n' +
                        '点击"确定"继续处理全部标签，\n点击"取消"可以考虑分批处理。';
                } else {
                    confirmMessage += '\n\n点击"确定"开始处理，或"取消"退出。';
                }

                if (!confirm(confirmMessage)) {
                    return;
                }

                // 显示进度条
                $progress.show();
                $result.hide();
                $button.prop('disabled', true);

                // 初始化进度显示
                var initMessage = 'Processing ' + stats.without_description + ' tags without descriptions...';
                if (stats.without_description > 1000) {
                    initMessage += '\nWarning: Large number of tags, please be patient';
                }
                updateProgress('生成描述', 0, 0, 0, 0, initMessage, stats.without_description);

                // 发送实际的批量生成请求
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 600000, // 10分钟超时时间
                    data: {
                        action: 'tag_optimization_batch_generate',
                        nonce: '<?php echo wp_create_nonce('tag_optimization_batch'); ?>'
                    },
                    beforeSend: function() {
                        updateProgress('生成描述', 10, 0, 0, 0, '正在发送请求到服务器...', stats.without_description);
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            // 确保显示真实的处理结果
                            var actualProcessed = data.success_count + data.error_count;
                            updateProgress('生成描述', 100, actualProcessed, data.success_count, data.error_count, '处理完成', stats.without_description);

                            var message = '<div class="notice notice-success is-dismissible"><p>' +
                                '<strong>批量生成描述完成！</strong><br>' +
                                '✅ 成功处理：' + data.success_count + ' 个标签<br>' +
                                (data.error_count > 0 ? '❌ 处理失败：' + data.error_count + ' 个标签<br>' : '') +
                                '📊 总计处理：' + (data.success_count + data.error_count) + ' 个标签';

                            if (data.error_count > 0) {
                                message += '<br><small>详细信息请查看错误日志</small>';
                            }

                            message += '</p></div>';
                            $result.html(message).show();

                            // 5秒后隐藏进度条
                            setTimeout(function() {
                                $progress.hide();
                            }, 5000);

                        } else {
                            updateProgress('生成描述', 100, 0, 0, 0, '处理失败：' + response.data.message, stats.without_description);
                            $result.html('<div class="notice notice-error"><p><strong>描述生成失败：</strong><br>' + response.data.message + '</p></div>').show();
                            setTimeout(function() {
                                $progress.hide();
                            }, 5000);
                        }

                        $button.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '';
                        if (status === 'timeout') {
                            var partialMessage = '\n\n⚠️ **处理可能仍在继续**\n\n' +
                                '对于大量标签（' + stats.without_description + ' 个）的处理：\n' +
                                '• 服务器可能仍在后台继续处理\n' +
                                '• 建议等待5-10分钟后刷新页面查看结果\n' +
                                '• 如果仍有大量标签未处理，可以再次运行\n' +
                                '• 考虑分批次处理（每次处理200-300个）';

                            errorMessage = '请求超时：处理时间过长，服务器响应超时。' + partialMessage;
                            updateProgress('生成描述', 100, 0, 0, 0, '请求超时，但处理可能仍在继续', stats.without_description);
                        } else if (status === 'abort') {
                            errorMessage = '请求被取消';
                            updateProgress('生成描述', 100, 0, 0, 0, '请求被取消', stats.without_description);
                        } else if (xhr.status === 0) {
                            errorMessage = '网络连接失败：无法连接到服务器，请检查网络连接';
                            updateProgress('生成描述', 100, 0, 0, 0, '网络连接失败', stats.without_description);
                        } else if (xhr.status === 500) {
                            errorMessage = '服务器内部错误：服务器处理请求时发生错误 (HTTP 500)';
                            updateProgress('生成描述', 100, 0, 0, 0, '服务器错误', stats.without_description);
                        } else {
                            errorMessage = '网络错误：' + (error || '未知错误') + ' (HTTP ' + xhr.status + ')';
                            updateProgress('生成描述', 100, 0, 0, 0, '网络错误', stats.without_description);
                        }

                        $result.html('<div class="notice notice-error"><p><strong>处理失败：</strong><br>' + errorMessage + '</p>' +
                            '<p><strong>建议：</strong></p>' +
                            '<ul>' +
                            '<li>检查网络连接是否正常</li>' +
                            '<li>刷新页面后重试</li>' +
                            '<li>如果是大量标签处理，建议分批处理</li>' +
                            '<li>如果问题持续，请联系服务器管理员</li>' +
                            '</ul></div>').show();

                        setTimeout(function() {
                            $progress.hide();
                        }, 8000);
                        $button.prop('disabled', false);
                    }
                });
            });

            // 单个标签生成描述
            $('.generate-description-single').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                var tagId = $button.data('tag-id');
                var tagName = $button.data('tag-name');
                var originalText = $button.html();

                // 确认对话框
                if (!confirm('确定要为标签 "' + tagName + '" 生成AI描述吗？\n\n描述生成后将自动保存到标签中。')) {
                    return;
                }

                // 显示加载状态
                $button.prop('disabled', true).html('<span class="dashicons dashicons-spinner"></span><span>生成中...</span>');

                // 发送AJAX请求
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tag_optimization_generate_description',
                        tag_id: tagId,
                        nonce: '<?php echo wp_create_nonce('tag_optimization_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var message = '<div class="notice notice-success is-dismissible"><p>' +
                                '✅ 描述生成并保存成功！<br>' +
                                '标签：' + data.tag_name + '<br>' +
                                '描述：' + data.description +
                                '</p></div>';

                            // 显示成功消息
                            $('#batch-generate-result').html(message).show();

                            // 更新按钮状态
                            $button.removeClass('button-primary').addClass('button-secondary')
                                   .html('<span class="dashicons dashicons-yes"></span><span>已生成</span>')
                                   .prop('disabled', true);

                            // 更新表格中的状态显示
                            var $row = $button.closest('tr');
                            var statusHtml = '<span class="status-active">有描述</span>';
                            $row.find('td:nth-child(2)').html(statusHtml);
                            $row.find('td:nth-child(3)').text(data.description.length + ' 字符');

                        } else {
                            // 显示错误消息
                            $('#batch-generate-result').html('<div class="notice notice-error"><p>描述生成失败：' + response.data.message + '</p></div>').show();
                            $button.html(originalText).prop('disabled', false);
                        }
                    },
                    error: function() {
                        $('#batch-generate-result').html('<div class="notice notice-error"><p>网络错误，请重试</p></div>').show();
                        $button.html(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// 初始化管理页面
Tag_Optimization_Admin_Page::get_instance();