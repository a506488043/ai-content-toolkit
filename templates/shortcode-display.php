<?php
/**
 * 短代码显示模板
 */

if (!defined('ABSPATH')) {
    exit;
}

// 检查用户权限
if (!is_user_logged_in()) {
    echo '<div class="tc-login-notice">' . __('请登录后查看您的物品管理', 'time-capsule') . '</div>';
    return;
}

// 初始化类
$item_manager = new TimeCapsule_Item();
$category_manager = new TimeCapsule_Category();

// 获取参数
$category_filter = $atts['category'];
$limit = intval($atts['limit']);
$user_id = intval($atts['user_id']) ?: get_current_user_id();

// 获取数据
$args = array(
    'user_id' => $user_id,
    'category' => $category_filter,
    'limit' => $limit,
    'status' => 'active'
);

$items = $item_manager->get_items($args);
$categories = $category_manager->get_categories();
$stats = $item_manager->get_stats($user_id);
?>

<div class="time-capsule-frontend" id="time-capsule-app">
    <!-- 统计概览 -->
    <div class="tc-stats-overview">
        <div class="tc-stat-item">
            <div class="tc-stat-number"><?php echo esc_html($stats['total_items']); ?></div>
            <div class="tc-stat-label"><?php _e('总物品', 'time-capsule'); ?></div>
        </div>
        
        <?php if ($stats['expiring_warranty'] > 0): ?>
            <div class="tc-stat-item warning">
                <div class="tc-stat-number"><?php echo esc_html($stats['expiring_warranty']); ?></div>
                <div class="tc-stat-label"><?php _e('即将过保', 'time-capsule'); ?></div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 筛选器 -->
    <div class="tc-filters-frontend">
        <div class="tc-filter-tabs">
            <button class="tc-filter-tab active" data-category="">
                <?php _e('全部', 'time-capsule'); ?>
            </button>
            <?php foreach ($categories as $category): ?>
                <button class="tc-filter-tab" data-category="<?php echo esc_attr($category->name); ?>">
                    <span class="tc-category-icon"><?php echo tc_get_default_avatar($category->name); ?></span>
                    <?php echo esc_html($category->display_name); ?>
                    <span class="tc-count"><?php echo isset($stats['by_category'][$category->name]) ? $stats['by_category'][$category->name] : 0; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="tc-search-box">
            <input type="text" id="tc-search" placeholder="<?php _e('搜索物品...', 'time-capsule'); ?>">
            <button type="button" id="tc-search-btn">🔍</button>
        </div>
        
        <div class="tc-view-toggle">
            <button class="tc-view-btn active" data-view="grid">⊞</button>
            <button class="tc-view-btn" data-view="list">☰</button>
        </div>
    </div>
    
    <!-- 物品网格 -->
    <div class="tc-items-container">
        <?php if (empty($items)): ?>
            <div class="tc-empty-state">
                <div class="tc-empty-icon">📦</div>
                <h3><?php _e('暂无物品', 'time-capsule'); ?></h3>
                <p><?php _e('您还没有添加任何物品到物品管理中', 'time-capsule'); ?></p>
            </div>
        <?php else: ?>
            <div class="tc-items-grid" id="tc-items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="tc-item-card" data-category="<?php echo esc_attr($item->category); ?>" data-item-id="<?php echo esc_attr($item->id); ?>">
                        <div class="tc-item-header">
                            <div class="tc-item-avatar">
                                <?php echo tc_get_default_avatar($item->category); ?>
                            </div>
                            <div class="tc-item-category">
                                <?php 
                                // 修复：确保显示正确的类别名称，避免重复
                                $category_display = !empty($item->category_display_name) ? $item->category_display_name : ucfirst($item->category);
                                echo esc_html($category_display); 
                                ?>
                            </div>
                        </div>
                        
                        <div class="tc-item-content">
                            <h4 class="tc-item-name"><?php echo esc_html($item->name); ?></h4>
                            
                            <?php if (!empty($item->brand) && $item->brand !== $item->name): ?>
                                <div class="tc-item-brand"><?php echo esc_html($item->brand); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item->description) && $item->description !== $item->name): ?>
                                <div class="tc-item-description"><?php echo esc_html(wp_trim_words($item->description, 15)); ?></div>
                            <?php endif; ?>
                            
                            <div class="tc-item-meta">
                                <div class="tc-meta-item">
                                    <span class="tc-meta-label"><?php _e('购买于', 'time-capsule'); ?></span>
                                    <span class="tc-meta-value"><?php echo esc_html(date_i18n('Y年n月', strtotime($item->purchase_date))); ?></span>
                                </div>
                                
                                <div class="tc-meta-item">
                                    <span class="tc-meta-label"><?php _e('已拥有', 'time-capsule'); ?></span>
                                    <span class="tc-meta-value"><?php echo tc_calculate_item_age($item->purchase_date); ?></span>
                                </div>
                                
                                <?php if ($item->warranty_period > 0): ?>
                                    <div class="tc-meta-item">
                                        <span class="tc-meta-label"><?php _e('保修', 'time-capsule'); ?></span>
                                        <span class="tc-meta-value tc-warranty-status <?php echo tc_get_warranty_status_class($item->warranty_status); ?>">
                                            <?php echo tc_get_warranty_status_text($item->warranty_status); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($item->used_time_hours > 0): ?>
                                    <div class="tc-meta-item">
                                        <span class="tc-meta-label"><?php _e('使用时间', 'time-capsule'); ?></span>
                                        <span class="tc-meta-value"><?php echo tc_format_used_time($item->used_time_hours); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($item->category === 'vehicle' && $item->total_mileage > 0): ?>
                                    <div class="tc-meta-item">
                                        <span class="tc-meta-label"><?php _e('总里程', 'time-capsule'); ?></span>
                                        <span class="tc-meta-value"><?php echo tc_format_mileage($item->total_mileage); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($item->price > 0): ?>
                                    <div class="tc-meta-item">
                                        <span class="tc-meta-label"><?php _e('价格', 'time-capsule'); ?></span>
                                        <span class="tc-meta-value"><?php echo esc_html($item->formatted_price); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="tc-item-footer">
                            <div class="tc-item-status <?php echo tc_get_status_class($item->status); ?>">
                                <?php echo tc_get_status_text($item->status); ?>
                            </div>
                            
                            <button class="tc-item-details-btn" data-item-id="<?php echo esc_attr($item->id); ?>">
                                <?php _e('查看详情', 'time-capsule'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 加载更多按钮 -->
    <?php if (count($items) >= $limit): ?>
        <div class="tc-load-more">
            <button class="tc-load-more-btn" id="tc-load-more">
                <?php _e('加载更多', 'time-capsule'); ?>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if ($show_demo): ?>
        <div class="tc-demo-notice">
            <p><strong>📝 演示模式</strong></p>
            <p>您当前查看的是演示数据。<a href="<?php echo wp_login_url(); ?>">登录</a>后可以管理您自己的物品管理。</p>
        </div>
    <?php endif; ?>
</div>

<!-- 物品详情模态框 -->
<div id="tc-item-modal" class="tc-modal" style="display: none;">
    <div class="tc-modal-content">
        <div class="tc-modal-header">
            <h3 id="tc-modal-title"><?php _e('物品详情', 'time-capsule'); ?></h3>
            <span class="tc-modal-close">&times;</span>
        </div>
        <div class="tc-modal-body" id="tc-modal-body">
            <!-- 动态加载内容 -->
        </div>
    </div>
</div>

