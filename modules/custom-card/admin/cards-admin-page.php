<?php
/**
 * Custom Cards Admin Page
 * 网站卡片管理页面
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Cards Admin Page 类
 */
class Custom_Cards_Admin_Page {

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
        $this->module = Custom_Card_Module::get_instance();
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 不需要注册菜单，菜单已在主插件中注册
        // 加载脚本和样式
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * 加载脚本和样式
     */
    public function enqueue_scripts($hook) {
        // 只在网站卡片管理页面加载
        if (strpos($hook, 'wordpress-ai-toolkit-cards-list') === false) {
            return;
        }

        // 加载统一样式
        wp_enqueue_style(
            'wordpress-ai-toolkit-modules-admin',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'assets/css/modules-admin.css',
            array(),
            AI_CONTENT_TOOLKIT_VERSION
        );

        // 加载网站卡片管理脚本
        wp_enqueue_script(
            'wordpress-ai-toolkit-cards-admin',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/custom-card/assets/admin-script.js',
            array('jquery'),
            AI_CONTENT_TOOLKIT_VERSION,
            true
        );

        // 传递AJAX参数
        wp_localize_script('wordpress-ai-toolkit-cards-admin', 'custom_cards_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_cards_nonce'),
            'plugin_url' => AI_CONTENT_TOOLKIT_PLUGIN_URL
        ));
    }

    /**
     * 渲染管理页面
     */
    public function admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-ai-toolkit'));
        }

        // 获取统计数据
        $stats = $this->get_statistics();

        // 获取卡片列表
        $cards_data = $this->get_cards_data();
        ?>
        <div class="wrap custom-cards-admin">
            <h1><?php _e('网站卡片管理', 'wordpress-ai-toolkit'); ?></h1>

            <!-- 统计卡片 -->
            <div class="custom-cards-stats-grid">
                <div class="stat-card">
                    <h3><?php _e('总卡片数', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats['total_cards']); ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('激活卡片', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats['active_cards']); ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('今日点击量', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats['today_clicks']); ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('总点击量', 'wordpress-ai-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats['total_clicks']); ?></span>
                </div>
            </div>

            
            <!-- 卡片列表 -->
            <div class="custom-cards-list-section">
                <?php if ($cards_data['cards'] && !empty($cards_data['cards'])): ?>
                    <!-- 分页导航 -->
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(__('共 %d 个卡片', 'wordpress-ai-toolkit'), $cards_data['total_filtered']); ?>
                        </span>
                        <?php echo $cards_data['pagination']; ?>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('网站标题', 'wordpress-ai-toolkit'); ?></th>
                                <th scope="col"><?php _e('URL', 'wordpress-ai-toolkit'); ?></th>
                                <th scope="col"><?php _e('状态', 'wordpress-ai-toolkit'); ?></th>
                                <th scope="col"><?php _e('点击次数', 'wordpress-ai-toolkit'); ?></th>
                                <th scope="col"><?php _e('创建时间', 'wordpress-ai-toolkit'); ?></th>
                                <th scope="col"><?php _e('操作', 'wordpress-ai-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cards_data['cards'] as $card): ?>
                                <?php
                                $status_class = $card->status === 'active' ? 'active' : 'inactive';
                                $status_text = $card->status === 'active' ? '激活' : '未激活';
                                ?>
                                <tr data-card-id="<?php echo $card->id; ?>">
                                    <td class="column-title">
                                        <strong>
                                            <a href="<?php echo esc_url($card->url); ?>" target="_blank">
                                                <?php echo esc_html($card->title ?: '未知标题'); ?>
                                            </a>
                                        </strong>
                                        <?php if ($card->description): ?>
                                            <p class="card-description"><?php echo esc_html(wp_trim_words($card->description, 20)); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($card->url); ?>" target="_blank" class="card-url">
                                            <?php echo esc_html($card->url); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo number_format($card->click_count); ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($card->created_at)); ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <span class="visit">
                                                <button type="button" class="button button-small" onclick="window.open('<?php echo esc_url($card->url); ?>', '_blank')">
                                                    🌐 访问
                                                </button>
                                            </span>
                                            <span class="toggle-status">
                                                <button type="button" class="button button-small toggle-card-status" data-card-id="<?php echo $card->id; ?>" data-current-status="<?php echo $card->status; ?>">
                                                    <?php echo $card->status === 'active' ? '🚫 停用' : '✅ 激活'; ?>
                                                </button>
                                            </span>
                                            <span class="delete">
                                                <button type="button" class="button button-small delete-card" data-card-id="<?php echo $card->id; ?>">
                                                    🗑️ 删除
                                                </button>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- 底部分页导航 -->
                    <div class="tablenav-pages" style="margin-top: 15px;">
                        <?php echo $cards_data['pagination']; ?>
                    </div>
                <?php else: ?>
                    <div class="custom-cards-no-cards">
                        <h3>📭 暂无网站卡片</h3>
                        <p>还没有创建任何网站卡片。您可以：</p>
                        <ul>
                            <li>在文章或页面中使用短代码 <code>[custom_card url="https://example.com"]</code></li>
                            <li>访问包含网站卡片的页面时会自动创建卡片</li>
                            <li>或前往<a href="<?php echo admin_url('admin.php?page=wordpress-ai-toolkit-custom-card-settings'); ?>">设置页面</a>进行配置</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        /* 网站卡片统计网格 - 与文章优化保持一致 */
        .custom-cards-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .custom-cards-stats-grid .stat-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .custom-cards-stats-grid .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .custom-cards-stats-grid .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #0073aa;
            display: block;
        }

        
        /* 卡片列表区域 - 与文章优化保持一致 */
        .custom-cards-list-section {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* 状态徽章 - 与文章优化保持一致 */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active {
            background: #f0f6fc;
            color: #0073aa;
            border: 1px solid #c3d9ea;
        }

        .status-badge.inactive {
            background: #fef7f7;
            color: #d63638;
            border: 1px solid #ffabaf;
        }

        /* 卡片描述样式 */
        .card-description {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 12px;
            line-height: 1.4;
        }

        .card-url {
            font-size: 12px;
            color: #0073aa;
            text-decoration: none;
            word-break: break-all;
        }

        .card-url:hover {
            color: #005a87;
            text-decoration: underline;
        }

        /* WordPress标准表格样式 - 与文章优化保持一致 */
        .wp-list-table {
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            background: #fff;
            clear: both;
            margin: 0;
            width: 100%;
        }

        .wp-list-table th {
            font-weight: 600;
            text-align: left;
            padding: 8px 10px;
            line-height: 1.3em;
        }

        .wp-list-table td {
            padding: 9px 10px;
            line-height: 1.3em;
            vertical-align: top;
        }

        .wp-list-table .column-title {
            width: 25%;
        }

        .wp-list-table .column-title strong {
            font-size: 14px;
            line-height: 1.4;
            font-weight: 600;
        }

        .wp-list-table .row-actions {
            visibility: hidden;
            padding: 2px 0 0;
        }

        .wp-list-table tr:hover .row-actions {
            visibility: visible;
        }

        .wp-list-table .row-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .wp-list-table .row-actions span {
            margin-right: 0;
        }

        /* 操作按钮样式 - 与文章优化保持一致 */
        .row-actions .button {
            margin: 2px 0;
            font-size: 12px;
            line-height: 1.4;
            height: auto;
            padding: 6px 12px;
            white-space: nowrap;
        }

        .row-actions .button:hover {
            opacity: 0.9;
        }

        /* 分页样式 - 与文章优化保持一致 */
        .tablenav-pages {
            float: right;
            height: auto;
            margin: 0 0 15px 0;
            padding: 0;
            vertical-align: middle;
            text-align: right;
        }

        .tablenav-pages .displaying-num {
            margin-right: 15px;
            font-size: 13px;
            color: #666;
        }

        .tablenav-pages .page-numbers {
            display: inline-block;
            min-width: 20px;
            text-align: center;
            padding: 2px 6px;
            margin: 0 2px;
            border: 1px solid #ccc;
            border-radius: 3px;
            color: #5b9dd9;
            text-decoration: none;
            font-size: 12px;
        }

        .tablenav-pages .page-numbers.current {
            background: #e5e5e5;
            border-color: #999;
            color: #32373c;
        }

        .tablenav-pages .page-numbers:hover {
            background: #0073aa;
            color: #fff;
            border-color: #0073aa;
        }

        /* 空状态 - 与文章优化保持一致 */
        .custom-cards-no-cards {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }

        .custom-cards-no-cards h3 {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 18px;
        }

        .custom-cards-no-cards p {
            margin: 0 0 10px 0;
            color: #666;
        }

        .custom-cards-no-cards ul {
            list-style: none;
            padding: 0;
            margin: 15px 0 0 0;
            text-align: left;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .custom-cards-no-cards li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .custom-cards-no-cards li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #0073aa;
            font-weight: bold;
        }

        /* 响应式设计 - 与文章优化保持一致 */
        @media screen and (max-width: 768px) {
            .custom-cards-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            
            .tablenav-pages {
                float: none;
                text-align: center;
                margin: 15px 0;
            }
        }

        @media screen and (max-width: 480px) {
            .custom-cards-stats-grid {
                grid-template-columns: 1fr;
            }

            .wp-list-table th,
            .wp-list-table td {
                padding: 8px 6px;
                font-size: 12px;
            }

            .row-actions {
                visibility: visible;
                display: block;
                text-align: center;
            }

            .row-actions span {
                display: block;
                margin: 5px 0;
            }

                    }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {

            // 切换卡片状态
            $(document).on('click', '.toggle-card-status', function(e) {
                e.preventDefault();

                var cardId = $(this).data('card-id');
                var currentStatus = $(this).data('current-status');
                var newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                var button = $(this);
                var originalText = button.text();

                if (!confirm('确定要' + (newStatus === 'active' ? '激活' : '停用') + '这个网站卡片吗？')) {
                    return;
                }

                button.prop('disabled', true).text('处理中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'toggle_custom_card_status',
                        nonce: '<?php echo wp_create_nonce('toggle_custom_card_status'); ?>',
                        card_id: cardId,
                        new_status: newStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            // 更新按钮状态
                            button.data('current-status', newStatus);
                            button.text(newStatus === 'active' ? '🚫 停用' : '✅ 激活');

                            // 更新状态徽章
                            var statusBadge = button.closest('tr').find('.status-badge');
                            statusBadge.removeClass('active inactive').addClass(newStatus);
                            statusBadge.text(newStatus === 'active' ? '激活' : '未激活');

                            alert('状态更新成功');
                        } else {
                            alert('状态更新失败：' + response.data);
                        }
                    },
                    error: function() {
                        alert('网络错误，请重试');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });

            // 删除卡片功能
            $(document).on('click', '.delete-card', function(e) {
                e.preventDefault();

                var cardId = $(this).data('card-id');
                var cardRow = $(this).closest('tr');

                if (!confirm('确定要删除这个网站卡片吗？此操作不可撤销。')) {
                    return;
                }

                var deleteButton = $(this);
                var originalText = deleteButton.text();
                deleteButton.prop('disabled', true).text('删除中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_custom_card',
                        nonce: '<?php echo wp_create_nonce('delete_custom_card'); ?>',
                        card_id: cardId
                    },
                    success: function(response) {
                        if (response.success) {
                            cardRow.fadeOut(300, function() {
                                $(this).remove();
                            });
                            alert('卡片删除成功');
                        } else {
                            alert('删除失败：' + response.data);
                        }
                    },
                    error: function() {
                        alert('网络错误，请重试');
                    },
                    complete: function() {
                        deleteButton.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 获取统计数据
     */
    private function get_statistics() {
        global $wpdb;
        $cards_table = $wpdb->prefix . 'chf_cards';
        $clicks_table = $wpdb->prefix . 'chf_card_clicks';

        // 计算今日点击量（今天0点到现在）
        $today_start = date('Y-m-d 00:00:00');
        $today_clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $clicks_table WHERE clicked_at >= %s",
            $today_start
        ));

        $stats = array(
            'total_cards' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $cards_table"),
            'active_cards' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $cards_table WHERE status = 'active'"),
            'today_clicks' => $today_clicks,
            'total_clicks' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $clicks_table")
        );

        return $stats;
    }

    /**
     * 获取卡片数据
     */
    private function get_cards_data() {
        global $wpdb;
        $cards_table = $wpdb->prefix . 'chf_cards';
        $clicks_table = $wpdb->prefix . 'chf_card_clicks';

        // 分页参数
        $page = isset($_GET['card_page']) ? max(1, intval($_GET['card_page'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // 获取总数
        $total_filtered = $wpdb->get_var("SELECT COUNT(*) FROM $cards_table");
        $total_pages = ceil($total_filtered / $per_page);

        // 获取卡片列表
        $cards = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM $clicks_table WHERE card_id = c.id) as click_count
             FROM $cards_table c
             ORDER BY click_count DESC, c.updated_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        // 生成分页链接
        $current_url = admin_url('admin.php?page=wordpress-ai-toolkit-cards-list');

        $pagination = paginate_links(array(
            'base' => $current_url . '&card_page=%#%',
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $page
        ));

        return array(
            'cards' => $cards,
            'total_filtered' => $total_filtered,
            'pagination' => $pagination
        );
    }
}

// 初始化管理页面
Custom_Cards_Admin_Page::get_instance();