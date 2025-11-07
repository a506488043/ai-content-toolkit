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
    <h1>网站卡片设置</h1>

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

<script type="text/javascript">
jQuery(document).ready(function($) {
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
});
</script>

