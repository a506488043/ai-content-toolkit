<?php
/**
 * Custom Card 卡片列表管理页面
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 调试代码已禁用 - 生产环境不需要
/*
// 启用错误报告用于调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加详细调试日志
function debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {

    }
}

debug_log('=== CARDS LIST PAGE STARTED ===');
debug_log('Current user: ' . get_current_user_id());
debug_log('User can manage_options: ' . (current_user_can('manage_options') ? 'Yes' : 'No'));
debug_log('PHP memory limit: ' . ini_get('memory_limit'));
debug_log('PHP max_execution_time: ' . ini_get('max_execution_time'));
*/

global $wpdb;

// 处理操作请求
if (isset($_GET['action']) && wp_verify_nonce($_GET['_wpnonce'], 'custom_card_action')) {
    global $wpdb;
    $cards_table = $wpdb->prefix . 'chf_cards';

    switch ($_GET['action']) {
        case 'delete':
            if (isset($_GET['id'])) {
                $card_id = intval($_GET['id']);
                $wpdb->delete($cards_table, array('id' => $card_id));
                // 重定向回卡片列表页面
                wp_redirect(add_query_arg(array('page' => 'wordpress-ai-toolkit-cards-list', 'message' => 'deleted'), admin_url('admin.php')));
                exit;
            }
            break;


    }
}

// 显示操作成功消息
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'deleted':
            echo '<div class="notice notice-success is-dismissible"><p>卡片已删除！</p></div>';
            break;
    }
}

// 获取搜索参数
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// 构建查询条件
$where_conditions = array();
$query_params = array();

if (!empty($search)) {
    $where_conditions[] = "(url LIKE %s OR title LIKE %s OR description LIKE %s)";
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = '%' . $wpdb->esc_like($search) . '%';
}


// 构建WHERE子句
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// 分页设置
$per_page_options = array(10, 20, 50, 100);
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
if (!in_array($per_page, $per_page_options)) {
    $per_page = 10;
}
$offset = ($paged - 1) * $per_page;

// 检查表是否存在
$cards_table = $wpdb->prefix . 'chf_cards';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cards_table'") == $cards_table;

// 检查点击统计表是否存在
$clicks_table = $wpdb->prefix . 'chf_card_clicks';
$clicks_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'") == $clicks_table;

if (!$table_exists) {
    $total_items = 0;
    $total_pages = 0;
    $cards = array();
} else {
    // 获取卡片总数
    $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}chf_cards $where_sql";
    if (!empty($query_params)) {
        $count_query = $wpdb->prepare($count_query, $query_params);
    }
    $total_items = $wpdb->get_var($count_query);
    $total_items = $total_items ? intval($total_items) : 0;
    $total_pages = ceil($total_items / $per_page);

    // 获取卡片列表 - 按点击次数从高到低排序
    $query = "SELECT c.*, COALESCE(COUNT(click.id), 0) as click_count
              FROM {$wpdb->prefix}chf_cards c
              LEFT JOIN {$wpdb->prefix}chf_card_clicks click ON c.id = click.card_id
              $where_sql
              GROUP BY c.id
              ORDER BY click_count DESC, c.created_at DESC
              LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    if (!empty($query_params)) {
        $cards = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $cards = $wpdb->get_results($query);
    }

    if (is_wp_error($cards)) {

    }
}

// 点击统计数据现在已包含在主查询中，不需要单独查询
$click_stats = array();
?>
<div class="wrap">

    <!-- 数据库表状态检查 -->
    <?php if (!$table_exists || !$clicks_table_exists): ?>
    <div class="notice notice-warning is-dismissible">
        <h3>数据库表状态检查</h3>
        <p><strong>检测到以下数据库表不存在：</strong></p>
        <ul>
            <?php if (!$table_exists): ?>
                <li>❌ <code><?php echo $cards_table; ?></code> - 卡片数据表</li>
            <?php endif; ?>
            <?php if (!$clicks_table_exists): ?>
                <li>❌ <code><?php echo $clicks_table; ?></code> - 点击统计表</li>
            <?php endif; ?>
        </ul>
        <p><strong>解决方法：</strong></p>
        <ol>
            <li>请确保插件已正确激活</li>
            <li>尝试重新激活插件：前往 <a href="<?php echo admin_url('plugins.php'); ?>">插件页面</a> 停用并重新激活 WordPress Toolkit</li>
            <li>如果问题仍然存在，请联系技术支持</li>
        </ol>
        <p><em>注意：数据库表会在插件激活时自动创建。如果没有数据表，卡片功能将无法正常工作。</em></p>
    </div>
    <?php endif; ?>
    
        
    <!-- 搜索和卡片列表 -->
    <div class="wordpress-ai-toolkit-admin-section">
        <form method="get" action="">
            <input type="hidden" name="page" value="wordpress-ai-toolkit-cards-list">

            <div class="tablenav top">
                <div class="alignleft actions">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索URL、标题或描述..." style="width: 300px;">


                    <select name="per_page">
                        <?php foreach ($per_page_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php selected($per_page, $option); ?>><?php echo $option; ?> 条/页</option>
                        <?php endforeach; ?>
                    </select>

                    <input type="submit" class="button" value="搜索">

                    <?php if (!empty($search) || !empty($status) || $per_page != 10): ?>
                        <a href="<?php echo admin_url('admin.php?page=wordpress-ai-toolkit-cards-list'); ?>" class="button">清除筛选</a>
                    <?php endif; ?>

                    <!-- 统计信息 -->
                    <?php
                    $total_clicks = $clicks_table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chf_card_clicks") : 0;
                    $total_clicks = $total_clicks ? intval($total_clicks) : 0;
                    $today_clicks = $clicks_table_exists ? $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}chf_card_clicks WHERE DATE(clicked_at) = %s",
                        current_time('Y-m-d')
                    )) : 0;
                    $today_clicks = $today_clicks ? intval($today_clicks) : 0;
                    ?>
                    <span class="custom-card-stats" style="margin-left: 20px; color: #666; font-size: 14px;">
                        总卡片数：<strong><?php echo number_format($total_items); ?></strong> |
                        总点击数：<strong><?php echo number_format($total_clicks); ?></strong> |
                        今日点击数：<strong><?php echo number_format($today_clicks); ?></strong>
                    </span>
                </div>

                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf('共 %d 个项目', $total_items); ?></span>
                    <?php if ($total_pages > 1): ?>
                        <span class="pagination-links">
                            <?php
                            // 修复翻页链接：使用正确的页面参数
                            $base_url = admin_url('admin.php?page=wordpress-ai-toolkit-cards-list');
                            $base_url = add_query_arg(array('per_page' => $per_page), $base_url);

                            echo paginate_links(array(
                                'base' => $base_url . '&paged=%#%',
                                'format' => '',
                                'prev_text' => '&laquo; 上一页',
                                'next_text' => '下一页 &raquo;',
                                'total' => $total_pages,
                                'current' => $paged
                            ));
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" width="5%">ID</th>
                    <th scope="col" width="20%">网站信息</th>
                    <th scope="col" width="30%">描述</th>
                    <th scope="col" width="10%">点击次数</th>
                    <th scope="col" width="15%">创建时间</th>
                    <th scope="col" width="10%">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cards)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <?php if (!empty($search) || !empty($status)): ?>
                                <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                    <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                    没有找到匹配的卡片
                                </div>
                                <a href="<?php echo admin_url('admin.php?page=wordpress-ai-toolkit-cards-list'); ?>" class="button button-primary">
                                    清除筛选条件
                                </a>
                            <?php else: ?>
                                <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                    <span class="dashicons dashicons-admin-links" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                    还没有添加任何网站卡片
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cards as $card): ?>
                        <?php
                        $click_count = isset($card->click_count) ? intval($card->click_count) : 0;
                        $domain = parse_url($card->url, PHP_URL_HOST);
                        ?>
                        <tr>
                            <td><?php echo $card->id; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($card->icon)): ?>
                                        <img src="<?php echo esc_attr($card->icon); ?>" alt="网站图标" style="width: 32px; height: 32px; border-radius: 4px;">
                                    <?php else: ?>
                                        <div style="width: 32px; height: 32px; background: #007cba; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                            <?php echo strtoupper(substr($domain ?: '?', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo esc_html($card->title); ?></strong><br>
                                        <small style="color: #666;">
                                            <a href="<?php echo esc_url($card->url); ?>" target="_blank">
                                                <?php echo esc_html($domain ?: $card->url); ?>
                                            </a>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $description = wp_trim_words($card->description, 20);
                                echo esc_html($description); 
                                ?>
                            </td>
                            <td>
                                <strong style="color: #007cba;"><?php echo number_format($click_count); ?></strong>
                            </td>
                            <td>
                                <?php echo date('Y-m-d H:i', strtotime($card->created_at)); ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <span class="delete">
                                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $card->id), admin_url('admin.php?page=wordpress-ai-toolkit-cards-list')), 'custom_card_action'); ?>"
                                           onclick="return confirm('确定要删除这个卡片吗？此操作不可恢复。');"
                                           style="color: #a00;">
                                            删除
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<style>
/* 优化合并布局 */
.wordpress-ai-toolkit-admin-section .tablenav {
    margin-bottom: 15px;
}

.wordpress-ai-toolkit-admin-section .wp-list-table {
    margin-top: 0;
}

/* 优化分页样式 */
.pagination-links .page-numbers {
    display: inline-block;
    padding: 4px 8px;
    margin: 0 2px;
    border: 1px solid #ccc;
    text-decoration: none;
    border-radius: 3px;
}

.pagination-links .page-numbers.current {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.pagination-links .page-numbers:hover {
    background: #f1f1f1;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.badge-success {
    background: #46b450;
    color: white;
}

.badge-warning {
    background: #ffb900;
    color: #7c5600;
}

.row-actions {
    font-size: 12px;
}

.row-actions a {
    text-decoration: none;
}

.row-actions a:hover {
    text-decoration: underline;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.stat-card .stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.stat-card .stat-label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}
</style>