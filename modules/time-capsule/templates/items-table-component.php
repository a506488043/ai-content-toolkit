<?php
/**
 * 物品表格组件 - 合并筛选表单和物品表格
 *
 * @param array $items 物品列表
 * @param array $categories 类别列表
 * @param array $stats 统计信息
 * @param string $category_filter 当前类别筛选
 * @param string $status_filter 当前状态筛选
 * @param string $search 当前搜索关键词
 * @param int $page 当前页码
 * @param int $total_pages 总页数
 * @param int $total_items 总物品数
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wordpress-toolkit-admin-section">
    <form method="get" action="">
        <input type="hidden" name="page" value="wordpress-toolkit-time-capsule">
        <input type="hidden" name="tab" value="items">

        <div class="tablenav top">
            <div class="alignleft actions">
                <!-- 筛选控件 -->
                <select name="category">
                    <option value=""><?php _e('所有类别', 'time-capsule'); ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category->name); ?>"
                                <?php selected($category_filter, $category->name); ?>>
                            <?php echo esc_html($category->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value=""><?php _e('所有状态', 'time-capsule'); ?></option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('使用中', 'time-capsule'); ?></option>
                    <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php _e('闲置', 'time-capsule'); ?></option>
                    <option value="disposed" <?php selected($status_filter, 'disposed'); ?>><?php _e('已处置', 'time-capsule'); ?></option>
                </select>

                <!-- 移除了保修状态筛选器 -->

                <?php
                // 只有管理员才能看到用户筛选器
                $current_user = wp_get_current_user();
                if (current_user_can('manage_options')):
                ?>
                <select name="user" id="tc-user-filter">
                    <?php
                    // 获取所有有物品的用户，并按角色分组
                    global $wpdb;
                    $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM " . $wpdb->prefix . "time_capsule_items WHERE user_id > 0");
                    $administrators = array();
                    $subscribers = array();
                    $processed_users = array();

                    foreach ($user_ids as $user_id):
                        $user = get_userdata($user_id);
                        if ($user):
                            $user_role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
                            if ($user_role === 'administrator') {
                                $administrators[] = $user;
                            } else {
                                $subscribers[] = $user;
                            }
                            $processed_users[] = $user_id;
                        endif;
                    endforeach;

                    // 确保当前登录的管理员总是出现在下拉框中，即使他没有物品
                    $current_user = wp_get_current_user();
                    if (current_user_can('manage_options') && !in_array($current_user->ID, $processed_users)) {
                        $administrators[] = $current_user;
                    }

                    // 显示管理员选项
                    if (!empty($administrators)):
                    ?>
                    <optgroup label="管理员">
                        <?php foreach ($administrators as $user): ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($user_filter === $user->ID); ?>>
                            <?php echo esc_html($user->display_name . ' (管理员)'); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>

                    <!-- 显示订阅者选项 -->
                    <?php if (!empty($subscribers)): ?>
                    <optgroup label="订阅者">
                        <?php foreach ($subscribers as $user): ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($user_filter === $user->ID); ?>>
                            <?php echo esc_html($user->display_name . ' (订阅者)'); ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
                <?php endif; ?>

                <input type="text" name="search" value="<?php echo esc_attr($search); ?>"
                       placeholder="搜索物品名称或品牌..." style="width: 300px;">

                <input type="submit" class="button" value="筛选">

                <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items'); ?>" class="button">重置</a>

                <!-- 操作按钮 -->
                <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=add'); ?>"
                   class="button-primary">添加物品</a>

                <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=categories'); ?>"
                   class="button">类别管理</a>

                <!-- 统计信息 -->
                <span class="custom-card-stats" style="margin-left: 20px; color: #666; font-size: 14px;">
                    <?php
                    $total_items = isset($stats['total_items']) ? intval($stats['total_items']) : 0;
                    ?>
                    <?php if (current_user_can('manage_options')): ?>
                        总物品数：<strong><?php echo number_format($total_items); ?></strong>
                    <?php else: ?>
                        我的物品：<strong><?php echo number_format($total_items); ?></strong>
                    <?php endif; ?>
                </span>
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf('共 %d 个项目', $total_items); ?></span>
                <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <?php
                        $base_url = admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items');
                        if ($category_filter) {
                            $base_url = add_query_arg('category', $category_filter, $base_url);
                        }
                        if ($status_filter) {
                            $base_url = add_query_arg('status', $status_filter, $base_url);
                        }
                        // 移除了保修状态参数
                        if (isset($_GET['user']) && $_GET['user']) {
                            $base_url = add_query_arg('user', $_GET['user'], $base_url);
                        }
                        if ($search) {
                            $base_url = add_query_arg('search', $search, $base_url);
                        }

                        echo paginate_links(array(
                            'base' => $base_url . '&paged=%#%',
                            'format' => '',
                            'prev_text' => '&laquo; 上一页',
                            'next_text' => '下一页 &raquo;',
                            'total' => $total_pages,
                            'current' => $page
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
                <th scope="col" width="20%">物品名称</th>
                <th scope="col" width="12%">类别</th>
                <th scope="col" width="12%">购买日期</th>
                <th scope="col" width="12%">保修期</th>
                <th scope="col" width="10%">已用时间</th>
                <th scope="col" width="12%">状态</th>
                <th scope="col" width="12%">用户</th>
                <th scope="col" width="10%">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <?php if (!empty($search) || !empty($category_filter) || !empty($status_filter)): ?>
                            <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                没有找到匹配的物品
                            </div>
                            <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items'); ?>" class="button button-primary">
                                清除筛选条件
                            </a>
                        <?php else: ?>
                            <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                <span class="dashicons dashicons-archive" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                还没有添加任何物品
                            </div>
                            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; text-align: left;">
                                <h3 style="margin-top: 0;">如何添加物品？</h3>
                                <p>物品管理可以帮助您记录和追踪个人物品的购买信息、保修状态等。请按照以下步骤操作：</p>
                                <ol>
                                    <li>点击上方的"添加物品"按钮</li>
                                    <li>填写物品的基本信息（名称、品牌、类别等）</li>
                                    <li>设置购买日期和保修信息</li>
                                    <li>保存后即可在列表中查看</li>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <div>
                                    <strong><?php echo esc_html($item->name); ?></strong>
                                    <?php if ($item->brand): ?>
                                        <div style="color: #666; font-size: 12px;"><?php echo esc_html($item->brand); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="badge <?php echo tc_get_category_badge_class($item->category); ?>">
                                <span class="dashicons <?php echo esc_attr(!empty($item->category_icon) ? $item->category_icon : 'dashicons-admin-post'); ?>" style="font-size: 14px; margin-right: 4px;"></span>
                                <?php echo esc_html(!empty($item->category_display_name) ? $item->category_display_name : $item->category); ?>
                            </span>
                        </td>

                        <td>
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->purchase_date))); ?>
                            <div style="color: #666; font-size: 12px;">
                                <?php echo tc_calculate_item_age($item->purchase_date); ?>
                            </div>
                        </td>

                        <td>
                            <?php if ($item->category === 'pets'): ?>
                                <?php if (!empty($item->warranty_period)): ?>
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->warranty_period))); ?>
                                <?php else: ?>
                                    <span style="color: #999;">未设置</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($item->warranty_period)): ?>
                                    <span style="font-weight: bold;"><?php echo esc_html($item->warranty_period); ?> 天</span>
                                <?php else: ?>
                                    <span style="color: #999;">未设置</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($item->category === 'pets'): ?>
                                <?php if (!empty($item->used_time_hours)): ?>
                                    <span style="font-weight: bold;"><?php echo esc_html($item->used_time_hours); ?> 岁</span>
                                <?php elseif (!empty($item->warranty_period) && isset($item->age_years)): ?>
                                    <span style="font-weight: bold;"><?php echo esc_html($item->age_years); ?> 岁</span>
                                <?php else: ?>
                                    <span style="color: #999;">未知</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($item->used_time_hours)): ?>
                                    <span style="font-weight: bold;"><?php echo esc_html($item->used_time_hours); ?> 小时</span>
                                <?php else: ?>
                                    <span style="color: #999;">未设置</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge <?php echo tc_get_status_badge_class($item->status); ?>">
                                <?php echo tc_get_status_text($item->status, $item->category); ?>
                            </span>
                        </td>

                        <td>
                            <?php
                            $user = get_userdata($item->user_id);
                            if ($user) {
                                $user_role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
                                $role_text = $user_role === 'administrator' ? '管理员' : '订阅者';
                                echo '<div>';
                                echo '<div style="font-weight: 500;">' . esc_html($user->display_name) . '</div>';
                                echo '<div style="color: #666; font-size: 12px;">' . esc_html($role_text) . '</div>';
                                echo '</div>';
                            } else {
                                echo '<span style="color: #999;">未知用户</span>';
                            }
                            ?>
                        </td>

                        <td>
                            <div class="tc-actions-group">
                                <button type="button" class="button tc-edit-item"
                                        data-item-id="<?php echo esc_attr($item->id); ?>">
                                    <?php _e('编辑', 'time-capsule'); ?>
                                </button>

                                <button type="button" class="button tc-view-item"
                                        data-item-id="<?php echo esc_attr($item->id); ?>">
                                    <?php _e('查看', 'time-capsule'); ?>
                                </button>

                                <button type="button" class="button tc-delete-item"
                                        data-item-id="<?php echo esc_attr($item->id); ?>">
                                    <?php _e('删除', 'time-capsule'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

  </div>

<style>
/* 统一样式 - 与网站卡片页面保持一致 */
.wordpress-toolkit-admin-section .tablenav {
    margin-bottom: 15px;
}

.wordpress-toolkit-admin-section .wp-list-table {
    margin-top: 0;
}

/* 徽章样式 */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    line-height: 1.4;
}

/* 类别徽章 */
.badge-transportation {
    background: #e3f2fd;
    color: #1565c0;
}

.badge-electronics {
    background: #f3e5f5;
    color: #7b1fa2;
}

.badge-furniture {
    background: #e8f5e8;
    color: #2e7d32;
}

.badge-clothing {
    background: #fff3e0;
    color: #ef6c00;
}

.badge-food {
    background: #fce4ec;
    color: #c2185b;
}

.badge-books {
    background: #e0f2f1;
    color: #00695c;
}

.badge-sports {
    background: #e1f5fe;
    color: #0277bd;
}

.badge-other {
    background: #f5f5f5;
    color: #616161;
}

/* 保修状态徽章 */
.badge-valid {
    background: #e8f5e8;
    color: #2e7d32;
}

.badge-expiring {
    background: #fff8e1;
    color: #f57f17;
}

.badge-expired {
    background: #ffebee;
    color: #c62828;
}

.badge-no-warranty {
    background: #f5f5f5;
    color: #616161;
}

/* 状态徽章 */
.badge-active {
    background: #e8f5e8;
    color: #2e7d32;
}

.badge-inactive {
    background: #fff3e0;
    color: #ef6c00;
}

.badge-disposed {
    background: #f5f5f5;
    color: #616161;
}

/* 响应式设计 */
@media screen and (max-width: 1200px) {
    .wordpress-toolkit-admin-section .tablenav .alignleft {
        display: block;
        width: 100%;
        margin-bottom: 10px;
    }

    .wordpress-toolkit-admin-section .tablenav .alignleft > * {
        display: inline-block;
        margin-bottom: 5px;
    }
}

@media screen and (max-width: 782px) {
    .wordpress-toolkit-admin-section .wp-list-table th,
    .wordpress-toolkit-admin-section .wp-list-table td {
        padding: 8px 10px;
    }

    .wordpress-toolkit-admin-section .button {
        margin-bottom: 5px;
    }
}
</style>