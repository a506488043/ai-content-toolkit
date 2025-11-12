<?php
/**
 * 时间胶囊页面模板 - 重新设计版本
 * Template Name: 时间胶囊
 */

// 加载时间胶囊页面模板样式
wp_enqueue_style('time-capsule-page-template', TIME_CAPSULE_PLUGIN_URL . 'assets/css/page-template.css', array(), TIME_CAPSULE_VERSION);

get_header(); ?>

<div class="time-capsule-page-wrapper">
    <?php while (have_posts()) : the_post(); ?>

        <div class="tc-page-main">
            <div class="container">
                <?php
                // 获取页面设置
                $display_mode = get_post_meta(get_the_ID(), '_tc_display_mode', true) ?: 'grid';
                $show_categories = get_post_meta(get_the_ID(), '_tc_show_categories', true) ?: '1';
                $items_per_page = get_post_meta(get_the_ID(), '_tc_items_per_page', true) ?: '12';
                $category_filter = get_post_meta(get_the_ID(), '_tc_category_filter', true) ?: '';

                // 检查用户权限
                if (!is_user_logged_in()) {
                    echo '<div class="tc-login-notice">';
                    echo '<h3>请登录查看您的时间胶囊</h3>';
                    echo '<p>您需要登录后才能查看和管理您的个人物品记录。</p>';
                    echo '<a href="' . wp_login_url(get_permalink()) . '" class="tc-login-btn">立即登录</a>';
                    echo '</div>';
                    echo '</div></div>';
                    get_footer();
                    return;
                }

                // 获取用户数据
                $item_manager = new TimeCapsule_Item();
                $category_manager = new TimeCapsule_Category();

                $args = array(
                    'user_id' => get_current_user_id(),
                    'category' => $category_filter,
                    'limit' => intval($items_per_page),
                    'status' => 'active'
                );

                $items = $item_manager->get_items($args);
                $categories = $category_manager->get_categories();
                $stats = $item_manager->get_stats(get_current_user_id());
                ?>

                <!-- 类别筛选区域 -->
                <div class="tc-category-filter-section">
                    <div class="tc-filter-header">
                        <h2 class="tc-filter-title">物品分类</h2>
                        <div class="tc-filter-actions">
                            <button class="tc-filter-reset active" data-category="">全部</button>
                            <?php if (is_user_logged_in()): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=items')); ?>"
                               class="tc-admin-manage-btn" title="后台管理">
                                管理
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tc-category-grid">
                        <?php
                        $category_icons = array(
                            'electronics' => array('icon' => '📱', 'name' => '电子产品'),
                            'appliances' => array('icon' => '🏠', 'name' => '家用电器'),
                            'furniture' => array('icon' => '🪑', 'name' => '家具'),
                            'clothing' => array('icon' => '👔', 'name' => '服装'),
                            'books' => array('icon' => '📚', 'name' => '图书'),
                            'sports' => array('icon' => '⚽', 'name' => '运动器材'),
                            'tools' => array('icon' => '🔧', 'name' => '工具'),
                            'certificate' => array('icon' => '🏆', 'name' => '证书资质'),
                            'vehicle' => array('icon' => '🚗', 'name' => '交通工具'),
                            'pets' => array('icon' => '🐾', 'name' => '宠物'),
                            'other' => array('icon' => '📦', 'name' => '其他')
                        );
                        ?>
                        <div class="tc-category-card active" data-category="">
                            <div class="tc-category-icon">🔍</div>
                            <div class="tc-category-name">全部</div>
                            <div class="tc-category-count"><?php echo count($items); ?></div>
                        </div>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $category_info = $category_icons[$category->name] ?? $category_icons['other'];
                            $count = isset($stats['by_category'][$category->name]) ? $stats['by_category'][$category->name] : 0;
                            ?>
                            <div class="tc-category-card" data-category="<?php echo esc_attr($category->name); ?>">
                                <div class="tc-category-icon"><?php echo $category_info['icon']; ?></div>
                                <div class="tc-category-name"><?php echo esc_html($category_info['name']); ?></div>
                                <div class="tc-category-count"><?php echo $count; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 物品展示区域 -->
                <div class="tc-items-section">
                    <div class="tc-items-header">
                        <h2 class="tc-items-title">我的物品</h2>
                        <div class="tc-items-count">共 <?php echo count($items); ?> 件</div>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="tc-empty-state">
                            <div class="tc-empty-icon">📦</div>
                            <h3>暂无物品记录</h3>
                            <p>您还没有添加任何物品记录。</p>
                            <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-time-capsule&tab=add'); ?>" class="tc-btn tc-btn-primary">添加第一个物品</a>
                        </div>
                    <?php else: ?>
                        <div class="tc-items-grid" id="tc-items-container">
                            <?php foreach ($items as $item): ?>
                                <?php
                                // 统一处理数组和对象格式
                                $item_category = is_array($item) ? $item['category'] : $item->category;
                                $item_status = is_array($item) ? $item['status'] : $item->status;
                                $item_name = is_array($item) ? $item['name'] : $item->name;
                                $item_brand = is_array($item) ? ($item['brand'] ?? '') : ($item->brand ?? '');
                                $item_description = is_array($item) ? ($item['description'] ?? '') : ($item->description ?? '');
                                $item_purchase_date = is_array($item) ? $item['purchase_date'] : $item->purchase_date;
                                $item_price = is_array($item) ? ($item['price'] ?? '') : ($item->price ?? '');
                                $item_warranty = is_array($item) ? ($item['warranty_period'] ?? '') : ($item->warranty_period ?? '');
                                $item_id = is_array($item) ? $item['id'] : $item->id;

                                $category_info = $category_icons[$item_category] ?? $category_icons['other'];
                                ?>

                                <div class="tc-item-new" data-category="<?php echo esc_attr($item_category); ?>">
                                    <div class="tc-item-header">
                                        <div class="tc-item-category-icon"><?php echo $category_info['icon']; ?></div>
                                        <div class="tc-item-info">
                                            <h3 class="tc-item-name"><?php echo esc_html($item_name); ?></h3>
                                            <?php if (!empty($item_brand)): ?>
                                                <div class="tc-item-brand">
                                                    <?php if ($item_category === 'certificate'): ?>
                                                        <?php echo esc_html($item_brand); ?>
                                                    <?php else: ?>
                                                        品牌：<?php echo esc_html($item_brand); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="tc-item-status <?php echo esc_attr($item_status); ?>">
                                                <?php
                                                if ($item_category === 'pets') {
                                                    // 宠物类别使用不同的状态文本
                                                    $pet_status_texts = array(
                                                        'active' => '在养',
                                                        'inactive' => '寄养',
                                                        'disposed' => '已送养'
                                                    );
                                                    echo $pet_status_texts[$item_status] ?? $item_status;
                                                } else {
                                                    // 其他类别使用原有状态文本
                                                    $status_texts = array(
                                                        'active' => '使用中',
                                                        'inactive' => '闲置',
                                                        'disposed' => '已处置'
                                                    );
                                                    echo $status_texts[$item_status] ?? $item_status;
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($item_description)): ?>
                                        <div class="tc-item-description">描述：<?php echo esc_html($item_description); ?></div>
                                    <?php endif; ?>

                                    <div class="tc-item-details">
                                        <?php if ($item_category === 'certificate'): ?>
                                            <!-- 证书资质特殊字段 -->
                                            <div class="tc-detail-item">
                                                <span class="tc-detail-label">发证时间</span>
                                                <span class="tc-detail-value"><?php echo esc_html(date('Y-m-d', strtotime($item_purchase_date))); ?></span>
                                            </div>
                                            <?php if (!empty($item_price)): ?>
                                                <div class="tc-detail-item">
                                                    <span class="tc-detail-label">培训费用</span>
                                                    <span class="tc-detail-value">¥<?php echo number_format(floatval($item_price)); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($item_warranty)): ?>
                                                <div class="tc-detail-item">
                                                    <span class="tc-detail-label">续证周期</span>
                                                    <span class="tc-detail-value"><?php echo esc_html($item_warranty); ?>个月</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="tc-detail-item">
                                                <span class="tc-detail-label">持证时间</span>
                                                <span class="tc-detail-value">
                                                    <?php
                                                    $purchase_date = new DateTime($item_purchase_date);
                                                    $current_date = new DateTime();
                                                    $usage_interval = $purchase_date->diff($current_date);

                                                    $years = $usage_interval->y;
                                                    $months = $usage_interval->m;
                                                    $days = $usage_interval->d;

                                                    $usage_text = '';

                                                    if ($years > 0) {
                                                        $usage_text .= $years . '年';
                                                        if ($months > 0) {
                                                            $usage_text .= $months . '个月';
                                                        }
                                                        if ($days > 0) {
                                                            $usage_text .= $days . '天';
                                                        }
                                                    } elseif ($months > 0) {
                                                        $usage_text .= $months . '个月';
                                                        if ($days > 0) {
                                                            $usage_text .= $days . '天';
                                                        }
                                                    } elseif ($days > 0) {
                                                        $usage_text .= $days . '天';
                                                    } else {
                                                        $usage_text = '不满一天';
                                                    }

                                                    echo $usage_text;
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="tc-detail-item">
                                            <span class="tc-detail-label">续证时间</span>
                                            <span class="tc-detail-value">
                                                <?php
                                                // 获取续证时间字段
                                                $item_renewal_date = is_array($item) ? ($item['renewal_date'] ?? '') : ($item->renewal_date ?? '');
                                                if (!empty($item_renewal_date)) {
                                                    echo esc_html(date('Y-m-d', strtotime($item_renewal_date)));
                                                } elseif (!empty($item_warranty) && !empty($item_purchase_date)) {
                                                    $next_renewal_date = date('Y-m-d', strtotime($item_purchase_date . ' + ' . $item_warranty . ' months'));
                                                    $renewal_date_obj = new DateTime($next_renewal_date);
                                                    $current_date_obj = new DateTime();

                                                    // 如果已经过期，计算下一个续证周期
                                                    if ($renewal_date_obj < $current_date_obj) {
                                                        $interval = $current_date_obj->diff($renewal_date_obj);
                                                        $months_passed = ($interval->y * 12) + $interval->m;
                                                        $additional_cycles = ceil(($months_passed + 1) / intval($item_warranty));
                                                        $next_renewal_date = date('Y-m-d', strtotime($item_purchase_date . ' + ' . ($additional_cycles * intval($item_warranty)) . ' months'));
                                                    }

                                                    echo $next_renewal_date;
                                                } else {
                                                    echo '未设置';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php else: ?>
                                            <!-- 普通物品字段 -->
                                            <div class="tc-detail-item">
                                                <span class="tc-detail-label">购买时间</span>
                                                <span class="tc-detail-value"><?php echo esc_html(date('Y-m-d', strtotime($item_purchase_date))); ?></span>
                                            </div>
                                            <?php if (!empty($item_price)): ?>
                                                <div class="tc-detail-item">
                                                    <span class="tc-detail-label">价格</span>
                                                    <span class="tc-detail-value">¥<?php echo number_format(floatval($item_price)); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($item_warranty)): ?>
                                                <div class="tc-detail-item">
                                                    <span class="tc-detail-label">
                                                        <?php if ($item_category === 'pets'): ?>
                                                            出生日期
                                                        <?php else: ?>
                                                            保修期
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="tc-detail-value">
                                                        <?php if ($item_category === 'pets'): ?>
                                                            <?php echo esc_html(date('Y-m-d', strtotime($item_warranty))); ?>
                                                        <?php else: ?>
                                                            <?php echo esc_html($item_warranty); ?>个月
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="tc-detail-item">
                                                <span class="tc-detail-label">
                                                    <?php if ($item_category === 'pets'): ?>
                                                        年龄
                                                    <?php else: ?>
                                                        已用时间
                                                    <?php endif; ?>
                                                </span>
                                                <span class="tc-detail-value">
                                                    <?php
                                                    $purchase_date = new DateTime($item_purchase_date);
                                                    $current_date = new DateTime();
                                                    $usage_interval = $purchase_date->diff($current_date);

                                                    $years = $usage_interval->y;
                                                    $months = $usage_interval->m;
                                                    $days = $usage_interval->d;

                                                    $usage_text = '';

                                                    if ($item_category === 'pets') {
                                                        // 宠物类别显示年龄
                                                        if ($years > 0) {
                                                            $usage_text .= $years . '岁';
                                                            if ($months > 0) {
                                                                $usage_text .= $months . '个月';
                                                            }
                                                        } elseif ($months > 0) {
                                                            $usage_text .= $months . '个月';
                                                        } elseif ($days > 0) {
                                                            $usage_text .= $days . '天';
                                                        } else {
                                                            $usage_text = '不满一天';
                                                        }
                                                    } else {
                                                        // 其他类别显示已用时间
                                                        if ($years > 0) {
                                                            $usage_text .= $years . '年';
                                                            if ($months > 0) {
                                                                $usage_text .= $months . '个月';
                                                            }
                                                            if ($days > 0) {
                                                                $usage_text .= $days . '天';
                                                            }
                                                        } elseif ($months > 0) {
                                                            $usage_text .= $months . '个月';
                                                            if ($days > 0) {
                                                                $usage_text .= $days . '天';
                                                            }
                                                        } elseif ($days > 0) {
                                                            $usage_text .= $days . '天';
                                                        } else {
                                                            $usage_text = '不满一天';
                                                        }
                                                    }

                                                    echo $usage_text;
                                                    ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($items) >= $items_per_page): ?>
                            <div class="tc-load-more">
                                <button class="tc-load-more-btn" id="tc-load-more">
                                    加载更多
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    <?php endwhile; ?>
</div>


<script>
// 新版页面模板JavaScript
jQuery(document).ready(function($) {
    // console.log('新版时间胶囊页面JavaScript已加载');

    // 类别卡片筛选功能
    $('.tc-category-card, .tc-filter-reset').on('click', function(e) {
        e.preventDefault();
        var category = $(this).data('category');
        // console.log('点击类别卡片筛选:', category);

        $('.tc-category-card, .tc-filter-reset').removeClass('active');
        if ($(this).hasClass('tc-filter-reset')) {
            $(this).addClass('active');
        } else {
            $(this).addClass('active');
        }

        if (category === '' || category === undefined) {
            $('.tc-item-new').show();
            // console.log('显示所有物品');
        } else {
            $('.tc-item-new').hide();
            var matchingItems = $('.tc-item-new[data-category="' + category + '"]');
            matchingItems.show();
            // console.log('筛选类别:', category, '找到物品数量:', matchingItems.length);
        }

        // 更新URL参数
        try {
            var url = new URL(window.location);
            if (category === '' || category === undefined) {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', category);
            }
            window.history.replaceState({}, '', url);
        } catch (e) {
            // console.log('URL更新失败:', e);
        }
    });

    // 页面加载时应用筛选
    var urlParams = new URLSearchParams(window.location.search);
    var categoryParam = urlParams.get('category');
    if (categoryParam) {
        // console.log('从URL应用筛选:', categoryParam);
        $('.tc-category-card[data-category="' + categoryParam + '"]').addClass('active');
        $('.tc-filter-reset').removeClass('active');
        $('.tc-item-new').hide();
        $('.tc-item-new[data-category="' + categoryParam + '"]').show();
    } else {
        $('.tc-category-card[data-category=""]').addClass('active');
        $('.tc-filter-reset').addClass('active');
    }
});
</script>

<?php get_footer(); ?>