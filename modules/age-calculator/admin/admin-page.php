<?php
/**
 * Age Calculator 管理页面
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取选项
$options = get_option('wordpress_toolkit_age_calculator_options');
$default_format = isset($options['default_format']) ? $options['default_format'] : 'years';
$show_for_logged_in = isset($options['show_for_logged_in']) ? $options['show_for_logged_in'] : true;
$show_for_logged_out = isset($options['show_for_logged_out']) ? $options['show_for_logged_out'] : true;
?>

<div class="wrap">
    <h1>年龄计算器设置</h1>
    
    <div class="toolkit-settings-form">
        <h2>⏰ 基本设置</h2>
        <form method="post" action="options.php">
            <?php settings_fields('wordpress_toolkit_age_calculator_options'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_format">默认显示格式</label>
                    </th>
                    <td>
                        <select id="default_format" name="wordpress_toolkit_age_calculator_options[default_format]">
                            <option value="years" <?php selected($default_format, 'years'); ?>>年</option>
                            <option value="months" <?php selected($default_format, 'months'); ?>>月</option>
                            <option value="days" <?php selected($default_format, 'days'); ?>>天</option>
                            <option value="detailed" <?php selected($default_format, 'detailed'); ?>>详细</option>
                        </select>
                        <p class="description">选择默认的年龄显示格式。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="show_for_logged_in">对登录用户显示</label>
                    </th>
                    <td>
                        <input type="checkbox" id="show_for_logged_in" name="wordpress_toolkit_age_calculator_options[show_for_logged_in]" 
                               value="1" <?php checked($show_for_logged_in); ?>>
                        <p class="description">是否对已登录用户显示年龄计算器。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="show_for_logged_out">对未登录用户显示</label>
                    </th>
                    <td>
                        <input type="checkbox" id="show_for_logged_out" name="wordpress_toolkit_age_calculator_options[show_for_logged_out]" 
                               value="1" <?php checked($show_for_logged_out); ?>>
                        <p class="description">是否对未登录用户显示年龄计算器。</p>
                    </td>
                </tr>
            </table>

            <div class="submit">
                <?php submit_button('保存设置'); ?>
            </div>
        </form>
    </div>

    <div class="toolkit-settings-form">
        <h2>🧪 测试工具</h2>
        <p>您可以使用以下工具测试年龄计算功能：</p>
        
        <div class="age-calculator-test-tool">
            <h3>年龄计算测试</h3>
            <form id="age-calculator-test-form">
                <div class="form-group">
                    <label for="test-birthdate">出生日期:</label>
                    <input type="date" id="test-birthdate" value="1990-01-01">
                </div>
                
                
                <div class="form-group">
                    <label for="test-format">显示格式:</label>
                    <select id="test-format">
                        <option value="years">年</option>
                        <option value="months">月</option>
                        <option value="days">天</option>
                        <option value="detailed">详细</option>
                    </select>
                </div>
                
                <button type="submit" class="button button-primary">测试计算</button>
            </form>
            
            <div id="age-calculator-test-result" style="margin-top: 20px;"></div>
        </div>
    </div>
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
    $('#age-calculator-test-form').on('submit', function(e) {
        e.preventDefault();

        var birthdate = $('#test-birthdate').val();
        var format = $('#test-format').val();

        if (!birthdate) {
            alert('请选择出生日期');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'age_calculator_calculate',
                nonce: '<?php echo wp_create_nonce('age_calculator_nonce'); ?>',
                birthdate: birthdate,
                format: format
            },
            success: function(response) {
                if (response.success) {
                    $('#age-calculator-test-result').html('<div class="success">计算结果: ' + response.data + '</div>');
                } else {
                    $('#age-calculator-test-result').html('<div class="error">错误: ' + response.data + '</div>');
                }
            },
            error: function() {
                $('#age-calculator-test-result').html('<div class="error">网络错误，请重试</div>');
            }
        });
    });
});
</script>

<style type="text/css">
.age-calculator-test-tool {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.age-calculator-test-tool .form-group {
    margin-bottom: 15px;
}

.age-calculator-test-tool label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.age-calculator-test-tool input,
.age-calculator-test-tool select {
    width: 100%;
    max-width: 300px;
}

.age-calculator-test-tool .success {
    color: #46b450;
    font-weight: bold;
}

.age-calculator-test-tool .error {
    color: #dc3232;
    font-weight: bold;
}
</style>
