<?php
/**
 * Simple FriendLink Admin Page - WordPress Built-in Links Version
 * 简洁友情链接管理页面 - WordPress内置链接版本
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class Simple_FriendLink_Admin {

    /**
     * 构造函数
     */
    public function __construct() {
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    /**
     * 友情链接管理页面 - 仅显示待审核申请
     */
    public function unified_admin_page() {
        // 显示消息
        if (isset($_GET['message'])) {
            $this->show_admin_message();
        }
        ?>
        <div class="wrap">
            <p class="description">
                <?php _e('已发布的友情链接请使用WordPress内置的"链接"管理功能。此页面仅显示用户提交的待审核申请。', 'wordpress-toolkit'); ?>
                <a href="<?php echo admin_url('link-manager.php'); ?>" class="button">
                    <?php _e('管理已发布链接', 'wordpress-toolkit'); ?>
                </a>
            </p>

            <?php $this->render_pending_links_tab(); ?>
        </div>
        <?php
    }

    /**
     * 获取待审核链接数量
     */
    private function get_pending_links_count() {
        $links = get_bookmarks(array(
            'hide_invisible' => false,
            'orderby' => 'updated',
            'order' => 'DESC'
        ));

        $count = 0;
        foreach ($links as $link) {
            if ($link->link_visible === 'N') {
                $count++;
            }
        }
        return $count;
    }

    
    /**
     * 渲染待审核链接标签页
     */
    private function render_pending_links_tab() {
        $all_links = get_bookmarks(array(
            'hide_invisible' => false,
            'orderby' => 'updated',
            'order' => 'DESC'
        ));

        // 筛选出不可见的链接（待审核）
        $pending_links = array();
        foreach ($all_links as $link) {
            if ($link->link_visible === 'N') {
                $pending_links[] = $link;
            }
        }

        $total_pending = count($pending_links);
        ?>
        <div class="tab-content">
            <h2><?php _e('待审核申请', 'wordpress-toolkit'); ?>
                <span style="font-size: 12px; color: #666; margin-left: 10px;">
                    (<?php echo sprintf(__('共 %d 条记录', 'wordpress-toolkit'), $total_pending); ?>)
                </span>
            </h2>

            <!-- 统计信息 -->
            <div class="pending-stats">
                <div class="stat-box">
                    <h3><?php _e('待审核申请', 'wordpress-toolkit'); ?></h3>
                    <div class="stat-number"><?php echo $total_pending; ?></div>
                    <p><?php _e('个友情链接申请等待审核', 'wordpress-toolkit'); ?></p>
                </div>
            </div>

            <?php if (!empty($pending_links)): ?>
                <form method="post" id="bulk-actions-form">
                    <?php wp_nonce_field('wordpress_toolkit_friendlinks'); ?>
                    <input type="hidden" name="page" value="wordpress-toolkit-friendlinks">
                    <input type="hidden" name="action" value="bulk_approve">

                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <label for="cb-select-all-1" class="select-all-label">
                                <input type="checkbox" id="cb-select-all-1">
                                <?php _e('全选', 'wordpress-toolkit'); ?>
                            </label>
                            <button type="submit" class="button action">
                                <?php _e('批准选中', 'wordpress-toolkit'); ?>
                            </button>
                            <button type="button" class="button" onclick="document.getElementById('bulk-actions-form').action.value='delete_link'; document.getElementById('bulk-actions-form').submit();">
                                <?php _e('删除选中', 'wordpress-toolkit'); ?>
                            </button>
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="manage-column column-cb check-column"></th>
                                <th width="20%"><?php _e('网站名称', 'wordpress-toolkit'); ?></th>
                                <th width="30%"><?php _e('网站地址', 'wordpress-toolkit'); ?></th>
                                <th width="20%"><?php _e('描述', 'wordpress-toolkit'); ?></th>
                                <th width="15%"><?php _e('申请时间', 'wordpress-toolkit'); ?></th>
                                <th width="15%"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_links as $link): ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="link_ids[]" value="<?php echo $link->link_id; ?>">
                                    </th>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($link->link_url); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html($link->link_name); ?>
                                            </a>
                                        </strong>
                                        <?php if (!empty($link->link_description)): ?>
                                            <br><small><?php echo esc_html($link->link_description); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo parse_url($link->link_url, PHP_URL_HOST); ?></td>
                                    <td><?php echo esc_html($link->link_description ?: '-'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($link->link_updated)); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('wordpress_toolkit_friendlinks'); ?>
                                            <input type="hidden" name="page" value="wordpress-toolkit-friendlinks">
                                            <input type="hidden" name="action" value="approve_link">
                                            <input type="hidden" name="link_id" value="<?php echo $link->link_id; ?>">
                                            <button type="submit" class="button button-small button-primary">
                                                <?php _e('批准', 'wordpress-toolkit'); ?>
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('wordpress_toolkit_friendlinks'); ?>
                                            <input type="hidden" name="page" value="wordpress-toolkit-friendlinks">
                                            <input type="hidden" name="action" value="delete_link">
                                            <input type="hidden" name="link_id" value="<?php echo $link->link_id; ?>">
                                            <button type="submit" class="button button-small" onclick="return confirm('<?php _e('确定要删除这个申请吗？', 'wordpress-toolkit'); ?>');">
                                                <?php _e('删除', 'wordpress-toolkit'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('暂无待审核的友情链接申请', 'wordpress-toolkit'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .pending-stats {
            margin: 20px 0;
        }

        .stat-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            display: inline-block;
            margin-right: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #d63638;
            margin: 10px 0;
        }

        .select-all-label {
            font-weight: normal;
            margin-right: 10px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // 全选/取消全选
            $('#cb-select-all-1').on('change', function() {
                $('input[name="link_ids[]"]').prop('checked', $(this).prop('checked'));
            });

            // 如果子复选框状态变化，更新主复选框
            $('input[name="link_ids[]"]').on('change', function() {
                var allChecked = $('input[name="link_ids[]"]').length === $('input[name="link_ids[]"]:checked').length;
                $('#cb-select-all-1').prop('checked', allChecked);
            });
        });
        </script>
        <?php
    }

    
    /**
     * 处理管理操作 - 仅处理待审核链接操作
     */
    public function handle_admin_actions() {
        if (isset($_POST['action']) && !empty($_POST['action']) &&
            in_array($_POST['action'], array('approve_link', 'bulk_approve', 'delete_link')) &&
            isset($_POST['page']) && $_POST['page'] === 'wordpress-toolkit-friendlinks') {

            if (!current_user_can('manage_options')) {
                wp_die(__('权限不足', 'wordpress-toolkit'));
            }

            $action = sanitize_text_field($_POST['action']);

            switch ($action) {
                case 'approve_link':
                    $this->approve_single_link();
                    break;
                case 'bulk_approve':
                    $this->bulk_approve_links();
                    break;
                case 'delete_link':
                    // 如果有link_ids数组，说明是批量删除
                    if (isset($_POST['link_ids']) && is_array($_POST['link_ids'])) {
                        $this->delete_selected_links();
                    } else {
                        $this->delete_single_link();
                    }
                    break;
            }
        }
    }

    /**
     * 批准单个链接
     */
    private function approve_single_link() {
        $link_id = intval($_POST['link_id']);

        if ($link_id > 0) {
            wp_update_link(array(
                'link_id' => $link_id,
                'link_visible' => 'Y'
            ));
        }

        wp_safe_redirect(admin_url('admin.php?page=wordpress-toolkit-friendlinks&message=approved'));
        exit;
    }

    /**
     * 删除单个链接
     */
    private function delete_single_link() {
        $link_id = intval($_POST['link_id']);

        if ($link_id > 0) {
            wp_delete_link($link_id);
        }

        wp_safe_redirect(admin_url('admin.php?page=wordpress-toolkit-friendlinks&message=deleted'));
        exit;
    }

    /**
     * 批量批准链接
     */
    private function bulk_approve_links() {
        if (isset($_POST['link_ids']) && is_array($_POST['link_ids'])) {
            foreach ($_POST['link_ids'] as $link_id) {
                $link_id = intval($link_id);
                if ($link_id > 0) {
                    wp_update_link(array(
                        'link_id' => $link_id,
                        'link_visible' => 'Y'
                    ));
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wordpress-toolkit-friendlinks&message=approved'));
        exit;
    }

    /**
     * 批量删除选中链接
     */
    private function delete_selected_links() {
        if (isset($_POST['link_ids']) && is_array($_POST['link_ids'])) {
            foreach ($_POST['link_ids'] as $link_id) {
                $link_id = intval($link_id);
                if ($link_id > 0) {
                    wp_delete_link($link_id);
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=wordpress-toolkit-friendlinks&message=deleted'));
        exit;
    }

    /**
     * 显示管理消息
     */
    private function show_admin_message() {
        $message = sanitize_text_field($_GET['message']);

        switch ($message) {
            case 'approved':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('链接已批准', 'wordpress-toolkit') . '</p></div>';
                break;
            case 'deleted':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('链接已删除', 'wordpress-toolkit') . '</p></div>';
                break;
            case 'error':
                echo '<div class="notice notice-error is-dismissible"><p>' . __('操作失败', 'wordpress-toolkit') . '</p></div>';
                break;
        }
    }
}

// 初始化管理页面
new Simple_FriendLink_Admin();