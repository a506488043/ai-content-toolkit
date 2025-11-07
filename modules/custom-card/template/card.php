<?php
/**
 * 卡片模板 - 安全增强版
 */
// 安全检查：防止直接访问PHP文件
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 安全处理显示逻辑
$show_title = !empty($data['title']) && $data['title'] !== '无法加载' && $data['title'] !== '无标题';
$show_image = !empty($data['image']);
$show_icon = !empty($data['icon']);
$show_desc = !empty($data['description']) && $data['description'] !== '无法获取网站信息' && $data['description'] !== '无描述';

// 获取卡片ID用于点击统计
global $wpdb;
$card_id = null;
if (isset($data['url'])) {
    $cards_table = $wpdb->prefix . 'chf_cards';
    $card_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $cards_table WHERE url = %s",
        $data['url']
    ));
}

// 检查是否为无法加载状态
$is_failed = (!$show_title && !$show_desc && !$show_image && !$show_icon) || 
             (isset($data['title']) && ($data['title'] === '无法加载' || $data['title'] === '无标题')) ||
             (isset($data['description']) && ($data['description'] === '无法获取网站信息' || $data['description'] === '无描述'));

// 提取域名用于备用显示
$domain = '';
if (isset($data['url'])) {
    $url_parts = parse_url($data['url']);
    $domain = isset($url_parts['host']) ? $url_parts['host'] : $data['url'];
}

// 生成首字母图标
$first_letter = '';
if (!empty($domain)) {
    $first_letter = strtoupper(substr($domain, 0, 1));
}

// 确定显示的图标类型
$icon_type = 'none';
if ($show_icon) {
    $icon_type = 'favicon';
} elseif ($is_failed && !empty($domain)) {
    $icon_type = 'fallback';
} elseif ($show_image) {
    $icon_type = 'image';
}
?>
<a href="<?php echo esc_url($data['url']); ?>" 
   class="strict-card <?php echo $is_failed ? 'strict-card-fallback' : ''; ?>" 
   target="_blank"
   rel="noopener noreferrer"
   data-card-id="<?php echo $card_id ? esc_attr($card_id) : ''; ?>"
   aria-label="<?php echo $show_title ? esc_attr($data['title']) : esc_attr($data['url']); ?>">

    <div class="strict-inner">
        <?php if ($icon_type === 'image') : ?>
            <!-- 显示网站图片 -->
            <div class="strict-media">
                <img src="<?php echo esc_url($data['image']); ?>" 
                     class="strict-img" 
                     alt="<?php echo $show_title ? esc_attr($data['title']) : esc_attr('内容卡片'); ?>"
                     loading="lazy"
                     onerror="this.style.display='none'">
            </div>
        <?php elseif ($icon_type === 'favicon') : ?>
            <!-- 显示网站图标 -->
            <div class="strict-media strict-media-favicon">
                <img src="<?php echo esc_url($data['icon']); ?>" 
                     class="strict-favicon" 
                     alt="<?php echo esc_attr($domain); ?> 图标"
                     loading="lazy"
                     onerror="this.style.display='none'">
            </div>
        <?php elseif ($icon_type === 'fallback') : ?>
            <!-- 备用显示：域名首字母图标 -->
            <div class="strict-media strict-media-fallback">
                <div class="strict-fallback-icon">
                    <?php echo esc_html($first_letter); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="strict-content <?php echo (!$show_image && !$is_failed) ? 'strict-content-full' : ''; ?>">
            <?php if ($show_title && !$is_failed) : ?>
                <h3 class="strict-title">
                    <?php echo esc_html($data['title']); ?>
                </h3>
            <?php elseif ($is_failed && !empty($domain)) : ?>
                <!-- 备用显示：域名 -->
                <h3 class="strict-title strict-title-fallback">
                    <?php echo esc_html($domain); ?>
                </h3>
            <?php elseif (!$is_failed) : ?>
                <h3 class="strict-title">
                    <?php echo esc_url($data['url']); ?>
                </h3>
            <?php endif; ?>

            <?php if ($show_desc && !$is_failed) : ?>
                <div class="strict-desc"><?php echo esc_html($data['description']); ?></div>
            <?php endif; ?>
        </div>

        <span class="strict-overlay"></span>
    </div>
</a>
