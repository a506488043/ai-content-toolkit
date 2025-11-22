<?php
/**
 * CookieGuard Pro - 管理后台设置页面
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 简化的设置页面 - 不使用模板系统

// 处理表单提交 - 安全版本
if (isset($_POST['submit']) && wp_verify_nonce($_POST['cookieguard_pro_nonce'], 'cookieguard_pro_settings')) {
    // 验证用户权限
    if (!current_user_can('manage_options')) {
        wp_die('权限不足');
    }

    // 获取旧设置以比较地理位置设置是否有变化
    $old_options = get_option('wordpress_ai_toolkit_cookieguard_options', array());

    $options = array(
        'notice_text' => wp_kses_post($_POST['notice_text']),
        'accept_button_text' => sanitize_text_field($_POST['accept_button_text']),
        'decline_button_text' => sanitize_text_field($_POST['decline_button_text']),
        'show_decline_button' => isset($_POST['show_decline_button']) ? true : false,
        'position' => sanitize_text_field($_POST['position']),
        // 保持其他设置的默认值
        'learn_more_text' => isset($options['learn_more_text']) ? $options['learn_more_text'] : '了解更多',
        'learn_more_url' => isset($options['learn_more_url']) ? $options['learn_more_url'] : '',
        'background_color' => isset($options['background_color']) ? $options['background_color'] : '#FFFFFF',
        'text_color' => isset($options['text_color']) ? $options['text_color'] : '#000000',
        'button_color' => isset($options['button_color']) ? $options['button_color'] : '#007AFF',
        'button_text_color' => isset($options['button_text_color']) ? $options['button_text_color'] : '#FFFFFF',
        'cookie_expiry' => isset($options['cookie_expiry']) ? $options['cookie_expiry'] : 365,
        'enable_analytics' => isset($options['enable_analytics']) ? $options['enable_analytics'] : false,
        'enable_geo_detection' => isset($options['enable_geo_detection']) ? $options['enable_geo_detection'] : false,
        'local_ip_as_china' => isset($options['local_ip_as_china']) ? $options['local_ip_as_china'] : false,
        'module_version' => COOKIEGUARD_PRO_VERSION
    );

    // 检查地理位置设置是否有变化
    $geo_settings_changed = (
        (isset($old_options['enable_geo_detection']) ? $old_options['enable_geo_detection'] : false) !== $options['enable_geo_detection'] ||
        (isset($old_options['local_ip_as_china']) ? $old_options['local_ip_as_china'] : true) !== $options['local_ip_as_china']
    );

    // 如果地理位置设置有变化，清除所有地理位置缓存 - 修复SQL注入
    if ($geo_settings_changed) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_wordpress_ai_toolkit_cookieguard_geo_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_wordpress_ai_toolkit_cookieguard_geo_%'
        ));
    }

    update_option('wordpress_ai_toolkit_cookieguard_options', $options);
    echo '<div class="notice notice-success is-dismissible"><p>设置已保存！</p></div>';
}

// 获取当前设置
$options = get_option('wordpress_ai_toolkit_cookieguard_options');

// 确保所有选项都有默认值
$default_options = array(
    'notice_text' => '本网站使用Cookie来改善您的浏览体验。继续使用本网站即表示您同意我们使用Cookie。',
    'accept_button_text' => '接受',
    'decline_button_text' => '拒绝',
    'learn_more_text' => '了解更多',
    'learn_more_url' => '',
    'position' => 'bottom',
    'background_color' => '#FFFFFF',
    'text_color' => '#000000',
    'button_color' => '#007AFF',
    'button_text_color' => '#FFFFFF',
    'show_decline_button' => true,
    'cookie_expiry' => 365,
    'enable_analytics' => false,
    'enable_geo_detection' => false,
    'local_ip_as_china' => false,
    'module_version' => COOKIEGUARD_PRO_VERSION
);

// 合并默认值和当前设置
$options = wp_parse_args($options, $default_options);
?>

<div class="wrap">
    <h1>Cookie同意设置</h1>

    <div class="toolkit-settings-form">
        <h2>🛡️ 基本设置</h2>
        <form method="post" action="">
            <?php wp_nonce_field('cookieguard_pro_settings', 'cookieguard_pro_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="notice_text">通知文本</label>
                    </th>
                    <td>
                        <textarea name="notice_text" id="notice_text" rows="4" class="large-text"><?php echo esc_textarea($options['notice_text']); ?></textarea>
                        <p class="description">Cookie使用通知文本</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="accept_button_text">接受按钮文本</label>
                    </th>
                    <td>
                        <input type="text" name="accept_button_text" id="accept_button_text" class="regular-text" value="<?php echo esc_attr($options['accept_button_text']); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="decline_button_text">拒绝按钮文本</label>
                    </th>
                    <td>
                        <input type="text" name="decline_button_text" id="decline_button_text" class="regular-text" value="<?php echo esc_attr($options['decline_button_text']); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="show_decline_button">显示拒绝按钮</label>
                    </th>
                    <td>
                        <input type="checkbox" name="show_decline_button" id="show_decline_button" value="1" <?php checked($options['show_decline_button']); ?>>
                        <label for="show_decline_button">在通知中显示拒绝按钮</label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="position">显示位置</label>
                    </th>
                    <td>
                        <select name="position" id="position">
                            <option value="top" <?php selected($options['position'], 'top'); ?>>页面顶部</option>
                            <option value="bottom" <?php selected($options['position'], 'bottom'); ?>>页面底部</option>
                        </select>
                    </td>
                </tr>
            </table>

            <div class="submit">
                <input type="submit" name="submit" class="button button-primary" value="保存设置">
            </div>
        </form>
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
    </style>
</div>