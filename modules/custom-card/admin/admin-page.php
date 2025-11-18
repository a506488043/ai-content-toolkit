<?php
/**
 * Custom Card 管理页面
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 调试日志
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Custom Card Admin Page: Started');
    error_log('Custom Card Admin Page: Current tab: ' . (isset($_GET['tab']) ? $_GET['tab'] : 'Not set'));
}

// 获取选项
$options = get_option('wordpress_toolkit_custom_card_options');
$cache_expire_hours = isset($options['cache_expire_hours']) ? intval($options['cache_expire_hours']) : 72;
$enable_memcached = isset($options['enable_memcached']) ? $options['enable_memcached'] : false;
$enable_opcache = isset($options['enable_opcache']) ? $options['enable_opcache'] : true;

// 获取当前选项卡
// 如果是通过设置菜单访问，强制显示设置选项卡
// 如果是通过工具箱菜单访问，强制显示卡片列表选项卡
if (isset($_GET['page']) && $_GET['page'] === 'wordpress-toolkit-custom-card-settings') {
    $current_tab = 'settings';
} elseif (isset($_GET['page']) && $_GET['page'] === 'wordpress-toolkit-cards-list') {
    $current_tab = 'cards';
} else {
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Custom Card Admin Page: Current tab set to: ' . $current_tab);
}
?>

<div class="wrap">
    <?php if ($current_tab === 'settings'): ?>
    <h1>网站卡片设置</h1>
    <?php endif; ?>

    <!-- 基本设置 -->
    <?php if ($current_tab === 'settings'): ?>
    <div class="toolkit-settings-form">
        <h2>📝 基本设置</h2>
        <form method="post" action="options.php">
            <?php settings_fields('wordpress_toolkit_custom_card_options'); ?>

            <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cache_expire_hours">缓存时间（小时）</label>
                        </th>
                        <td>
                            <input type="number" id="cache_expire_hours" name="wordpress_toolkit_custom_card_options[cache_expire_hours]" 
                                   value="<?php echo esc_attr($cache_expire_hours); ?>" min="1" max="720" class="small-text">
                            <p class="description">设置卡片数据的缓存时间，默认为72小时。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_memcached">启用Memcached缓存</label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_memcached" name="wordpress_toolkit_custom_card_options[enable_memcached]" 
                                   value="1" <?php checked($enable_memcached); ?>>
                            <p class="description">如果服务器支持Memcached，可以启用此选项提高性能。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_opcache">启用OPcache缓存</label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_opcache" name="wordpress_toolkit_custom_card_options[enable_opcache]" 
                                   value="1" <?php checked($enable_opcache); ?>>
                            <p class="description">如果服务器支持OPcache，可以启用此选项提高性能。</p>
                        </td>
                    </tr>
                </table>

            <div class="submit">
                <?php submit_button('保存设置'); ?>
            </div>
        </form>
    </div>

    <div class="toolkit-settings-form">
        <h2>🔄 缓存管理</h2>
            <p>当前缓存设置：</p>
            <ul>
                <li>数据库缓存：<?php echo $cache_expire_hours; ?> 小时</li>
                <li>Memcached：<?php echo $enable_memcached ? '已启用' : '已禁用'; ?></li>
                <li>OPcache：<?php echo $enable_opcache ? '已启用' : '已禁用'; ?></li>
            </ul>
            
            <button type="button" class="button button-secondary" id="clear-card-cache">清除所有缓存</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 卡片列表 -->
    <?php if ($current_tab === 'cards'): ?>
    <?php
    global $wpdb;
    $cards_table = $wpdb->prefix . 'chf_cards';
    $clicks_table = $wpdb->prefix . 'chf_card_clicks';

    // 获取统计数据
    $total_cards = (int) $wpdb->get_var("SELECT COUNT(*) FROM $cards_table");
    $active_cards = (int) $wpdb->get_var("SELECT COUNT(*) FROM $cards_table WHERE status = 'active'");
    $inactive_cards = $total_cards - $active_cards;
    $total_clicks = (int) $wpdb->get_var("SELECT SUM(click_count) FROM (SELECT COUNT(*) as click_count FROM $clicks_table GROUP BY card_id) as counts");

    // 获取卡片数据
    $page = isset($_GET['card_page']) ? max(1, intval($_GET['card_page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // 搜索条件
    $search = isset($_GET['card_search']) ? sanitize_text_field($_GET['card_search']) : '';
    $where_clause = '';
    if ($search) {
        $where_clause = $wpdb->prepare(" WHERE title LIKE %s OR url LIKE %s OR description LIKE %s",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }

    // 获取总数
    $total_filtered = $wpdb->get_var("SELECT COUNT(*) FROM $cards_table $where_clause");
    $total_pages = ceil($total_filtered / $per_page);

    // 获取卡片列表
    $cards = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*,
                (SELECT COUNT(*) FROM $clicks_table WHERE card_id = c.id) as click_count
         FROM $cards_table c
         $where_clause
         ORDER BY click_count DESC, c.updated_at DESC
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    ?>

    <!-- 卡片列表 - 简化版本 -->
    <div class="cards-list-section">
        <div id="cards-list-container">
            <?php if ($cards && !empty($cards)): ?>
                <!-- 分页导航 -->
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(__('共 %d 个卡片', 'wordpress-toolkit'), $total_filtered); ?>
                    </span>
                    <?php
                    $current_url = admin_url('admin.php?page=wordpress-toolkit-cards-list');
                    if ($search) {
                        $current_url .= '&card_search=' . urlencode($search);
                    }
                    echo paginate_links(array(
                        'base' => $current_url . '&card_page=%#%',
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('网站标题', 'wordpress-toolkit'); ?></th>
                            <th scope="col"><?php _e('状态', 'wordpress-toolkit'); ?></th>
                            <th scope="col"><?php _e('点击次数', 'wordpress-toolkit'); ?></th>
                            <th scope="col"><?php _e('创建时间', 'wordpress-toolkit'); ?></th>
                            <th scope="col"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($cards as $card):
                            $status_class = $card->status === 'active' ? 'active' : 'inactive';
                            $status_text = $card->status === 'active' ? '激活' : '未激活';
                        ?>
                        <tr>
                            <td class="column-title">
                                <strong>
                                    <a href="<?php echo esc_url($card->url); ?>" target="_blank">
                                        <?php echo esc_html($card->title ?: '未知标题'); ?>
                                    </a>
                                </strong>
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
                                        <button type="button" class="button button-small" onclick="window.open('<?php echo esc_url($card->url); ?>', '_blank')" style="background: #0073aa; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                            🌐 访问
                                        </button>
                                    </span>
                                    <span class="delete">
                                        <button type="button" class="button button-small delete-card" data-card-id="<?php echo $card->id; ?>" style="background: #dc3232; color: white; border: none; padding: 6px 12px; margin: 2px;">
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
                    <?php
                    echo paginate_links(array(
                        'base' => $current_url . '&card_page=%#%',
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            <?php else: ?>
                <div class="toolkit-no-cards">
                    <h3>📭 暂无网站卡片</h3>
                    <p>还没有创建任何网站卡片。您可以：</p>
                    <ul>
                        <li>在文章或页面中使用短代码 <code>[custom_card url="https://example.com"]</code></li>
                        <li>访问包含网站卡片的页面时会自动创建卡片</li>
                        <li>或前往<a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-custom-card-settings'); ?>">设置页面</a>进行配置</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* WordPress Toolkit 统一设置页面样式 */
.toolkit-settings-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.toolkit-settings-form h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.4em;
    font-weight: 600;
    color: #1d2327;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 8px;
}

.toolkit-settings-form .form-table {
    margin-top: 20px;
}

.toolkit-settings-form .form-table th {
    font-weight: 600;
    color: #1d2327;
    width: 35%;
}

.toolkit-settings-form .submit {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

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
    margin-bottom: 20px;
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
.cards-list-section {
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

/* WordPress标准按钮样式 - 与文章优化保持一致 */
.alignleft.actions bulkactions .button {
    font-size: 13px;
    line-height: 2.15384615;
    min-height: 30px;
    margin: 0;
    padding: 0 10px;
    cursor: pointer;
    border-width: 1px;
    border-style: solid;
    border-radius: 3px;
    box-sizing: border-box;
    vertical-align: top;
}

.button.action {
    background: #0073aa;
    border-color: #0073aa;
    color: #fff;
}

.button.action:hover {
    background: #005a87;
    border-color: #005a87;
    color: #fff;
}

.button:disabled,
.button.disabled {
    background: #f7f7f7 !important;
    border-color: #ddd !important;
    color: #a0a5aa !important;
    cursor: default;
    transform: none !important;
    box-shadow: none !important;
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

.wp-list-table .check-column {
    width: 2.2em;
    padding: 8px 10px 0 0;
}

.wp-list-table .column-title {
    width: 40%;
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
.tablenav {
    height: auto;
    clear: both;
    margin-top: 15px;
}

.tablenav .actions {
    overflow: hidden;
    padding: 7px 8px 6px;
    vertical-align: middle;
}

.tablenav .tablenav-pages {
    float: right;
    height: auto;
    margin: 0 0 0 12px;
    padding: 7px 0 0;
    vertical-align: middle;
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
.toolkit-no-cards {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

.toolkit-no-cards h3 {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 18px;
}

.toolkit-no-cards p {
    margin: 0 0 10px 0;
    color: #666;
}

.toolkit-no-cards ul {
    list-style: none;
    padding: 0;
    margin: 15px 0 0 0;
    text-align: left;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.toolkit-no-cards li {
    margin-bottom: 8px;
    padding-left: 20px;
    position: relative;
}

.toolkit-no-cards li:before {
    content: "•";
    position: absolute;
    left: 0;
    color: #0073aa;
    font-weight: bold;
}

/* 分页样式 - 重新定义用于简化版本 */
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

    .search-form {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .search-form input[type="text"] {
        width: 100% !important;
        margin-bottom: 10px;
    }
}

/* 隐藏WordPress底部栏 */
#wpfooter {
    display: none !important;
}

</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 清除缓存功能
    $('#clear-card-cache').on('click', function(e) {
        e.preventDefault();

        if (confirm('确定要清除所有网站卡片缓存吗？')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_custom_card_cache',
                    nonce: '<?php echo wp_create_nonce('clear_custom_card_cache'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('缓存已清除');
                    } else {
                        alert('清除缓存失败：' + response.data);
                    }
                },
                error: function() {
                    alert('网络错误，请重试');
                }
            });
        }
    });

    // 单个删除卡片功能
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

