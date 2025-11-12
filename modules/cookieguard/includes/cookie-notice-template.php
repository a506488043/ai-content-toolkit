<?php
/**
 * CookieGuard Pro - Cookie通知模板 v2.0
 * 精美苹果风格设计，增强可访问性和语义化
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('wordpress_toolkit_cookieguard_options');

// 获取配置选项，提供默认值
$position = isset($options['position']) ? $options['position'] : 'bottom';
$background_color = isset($options['background_color']) ? $options['background_color'] : '#FFFFFF';
$text_color = isset($options['text_color']) ? $options['text_color'] : '#000000';
$button_color = isset($options['button_color']) ? $options['button_color'] : '#007AFF';
$button_text_color = isset($options['button_text_color']) ? $options['button_text_color'] : '#FFFFFF';

// 文本内容
$notice_text = isset($options['notice_text']) ? $options['notice_text'] : '本网站使用Cookie来改善您的浏览体验。继续使用本网站即表示您同意我们使用Cookie。';
$accept_text = isset($options['accept_button_text']) ? $options['accept_button_text'] : '接受';
$decline_text = isset($options['decline_button_text']) ? $options['decline_button_text'] : '拒绝';
$learn_more_text = isset($options['learn_more_text']) ? $options['learn_more_text'] : '了解更多';
$learn_more_url = isset($options['learn_more_url']) ? $options['learn_more_url'] : '';

// 行为设置
$show_decline = isset($options['show_decline_button']) ? $options['show_decline_button'] : true;

// 设置自定义CSS变量（如果用户自定义了颜色）
$custom_css_vars = '';
if ($background_color !== '#FFFFFF' || $text_color !== '#000000' || $button_color !== '#007AFF' || $button_text_color !== '#FFFFFF') {
    $custom_css_vars = "
        --custom-notice-bg: {$background_color};
        --custom-notice-text: {$text_color};
        --custom-notice-button-bg: {$button_color};
        --custom-notice-button-text: {$button_text_color};
    ";
}

// 生成唯一ID以避免冲突
$notice_id = 'cookieguard-notice-' . wp_generate_uuid4();
?>

<!-- Cookie同意通知 - 苹果风格设计 -->
<aside id="cookieguard-notice" 
       class="cookieguard-notice-<?php echo esc_attr($position); ?>" 
       style="<?php echo esc_attr($custom_css_vars); ?>"
       role="dialog" 
       aria-labelledby="cookieguard-title" 
       aria-describedby="cookieguard-description"
       aria-modal="false"
       data-testid="cookie-consent-notice">
    
    <div class="cookieguard-notice-container">
        <!-- 图标区域 - 视觉标识 -->
        <div class="cookieguard-notice-icon" 
             role="img" 
             aria-label="Cookie图标"
             data-testid="cookie-icon">
        </div>
        
        <!-- 主要内容区域 -->
        <div class="cookieguard-notice-content">
            <!-- 标题 -->
            <h2 id="cookieguard-title" class="cookieguard-notice-title">
                🍪 Cookie 使用通知
            </h2>
            
            <!-- 描述文本 -->
            <div id="cookieguard-description" class="cookieguard-notice-text">
                <p><?php echo wp_kses_post($notice_text); ?></p>
                
                <?php if (!empty($learn_more_url) && !empty($learn_more_text)): ?>
                    <a href="<?php echo esc_url($learn_more_url); ?>" 
                       class="cookieguard-learn-more" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       aria-label="<?php echo esc_attr($learn_more_text); ?>（在新窗口中打开）"
                       data-testid="learn-more-link">
                        <?php echo esc_html($learn_more_text); ?>
                        <span aria-hidden="true">↗</span>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- 按钮操作区域 -->
            <div class="cookieguard-notice-buttons" 
                 role="group" 
                 aria-label="Cookie同意选项"
                 data-testid="consent-buttons">
                
                <?php if ($show_decline): ?>
                    <button type="button" 
                            class="cookieguard-btn cookieguard-decline-btn" 
                            data-consent="declined"
                            data-testid="decline-button"
                            aria-describedby="cookieguard-description">
                        <span aria-hidden="true">✕</span>
                        <?php echo esc_html($decline_text); ?>
                    </button>
                <?php endif; ?>
                
                <button type="button" 
                        class="cookieguard-btn cookieguard-accept-btn" 
                        data-consent="accepted"
                        data-testid="accept-button"
                        aria-describedby="cookieguard-description">
                    <span aria-hidden="true">✓</span>
                    <?php echo esc_html($accept_text); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 关闭按钮（键盘导航支持） -->
    <button type="button" 
            class="cookieguard-close-btn" 
            data-consent="declined"
            aria-label="关闭Cookie通知"
            title="关闭通知"
            data-testid="close-button"
            style="display: none;">
        <span aria-hidden="true">×</span>
    </button>
</aside>

<!-- 结构化数据 - SEO和可访问性增强 -->
<?php if (!empty($learn_more_url)): ?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "<?php echo esc_js(get_bloginfo('name')); ?>",
    "url": "<?php echo esc_url(home_url()); ?>",
    "cookiePolicy": "<?php echo esc_url($learn_more_url); ?>",
    "privacyPolicy": "<?php echo esc_url($learn_more_url); ?>",
    "publisher": {
        "@type": "Organization",
        "name": "<?php echo esc_js(get_bloginfo('name')); ?>"
    }
}
</script>
<?php endif; ?>

<!-- 无障碍访问增强脚本 -->
<script>
(function() {
    'use strict';
    
    // 确保Cookie通知在DOM加载后正确初始化
    document.addEventListener('DOMContentLoaded', function() {
        const notice = document.getElementById('cookieguard-notice');
        if (!notice) return;
        
        // 设置焦点管理
        const firstFocusable = notice.querySelector('.cookieguard-btn');
        if (firstFocusable) {
            // 延迟设置焦点，等待动画完成
            setTimeout(() => {
                firstFocusable.focus();
            }, 800);
        }
        
        // 键盘导航支持
        notice.addEventListener('keydown', function(e) {
            // ESC键关闭通知
            if (e.key === 'Escape') {
                const declineBtn = notice.querySelector('[data-consent="declined"]');
                if (declineBtn) {
                    declineBtn.click();
                }
            }
            
            // Tab键循环焦点
            if (e.key === 'Tab') {
                const focusableElements = notice.querySelectorAll(
                    'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        });
        
        // 触摸设备优化
        if ('ontouchstart' in window) {
            notice.classList.add('cookieguard-touch-device');
        }
        
        // 高对比度模式检测
        if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
            notice.classList.add('cookieguard-high-contrast');
        }
        
        // 减少动画偏好检测
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            notice.classList.add('cookieguard-reduced-motion');
        }
        
        // 屏幕阅读器公告
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.style.cssText = 'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;';
        announcement.textContent = 'Cookie使用通知已显示，请选择您的偏好设置。';
        document.body.appendChild(announcement);
        
        // 清理公告元素
        setTimeout(() => {
            if (announcement.parentNode) {
                announcement.parentNode.removeChild(announcement);
            }
        }, 3000);
    });
})();
</script>

<!-- 自定义样式覆盖（如果需要） -->
<?php if (!empty($custom_css_vars)): ?>
<style>
#cookieguard-notice {
    background: var(--custom-notice-bg, rgba(255, 255, 255, 0.85)) !important;
    color: var(--custom-notice-text, #000000) !important;
}

#cookieguard-notice .cookieguard-notice-title,
#cookieguard-notice .cookieguard-notice-text {
    color: var(--custom-notice-text, #000000) !important;
}

#cookieguard-notice .cookieguard-accept-btn {
    background: var(--custom-notice-button-bg, #007AFF) !important;
    color: var(--custom-notice-button-text, #FFFFFF) !important;
}

@media (prefers-color-scheme: dark) {
    #cookieguard-notice {
        background: color-mix(in srgb, var(--custom-notice-bg, #1C1C1E) 85%, transparent) !important;
    }
}
</style>
<?php endif; ?>

