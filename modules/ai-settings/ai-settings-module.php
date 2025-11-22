<?php
/**
 * AI Settings Module
 *
 * 统一的AI设置管理模块
 *
 * @version 1.0.0
 * @author www.saiita.com.cn
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_AI_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 加载辅助函数
        require_once dirname(__FILE__) . '/ai-settings-helper.php';
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 添加管理菜单 - 作为工具箱设置的子菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * 获取AI默认设置（静态方法，供其他模块使用）
     */
    public static function get_ai_default_settings() {
        return array(
            'use_ai_generation' => true,
            'ai_provider' => 'deepseek',
            'deepseek_api_key' => '',
            'deepseek_api_base' => 'https://api.deepseek.com',
            'deepseek_model' => 'deepseek-chat',
            'siliconflow_api_key' => '',
            'siliconflow_api_base' => 'https://api.siliconflow.cn/v1',
            'siliconflow_model' => 'deepseek-ai/DeepSeek-V3',
            'ai_max_tokens' => 150,
            'ai_temperature' => 0.5,
            'fallback_to_simple' => true
        );
    }

    /**
     * 获取AI设置
     */
    public function get_ai_settings() {
        // 使用辅助函数获取设置，确保数据一致性
        return wordpress_ai_toolkit_get_ai_settings();
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-ai-toolkit-settings',  // 父菜单：工具箱设置
            __('AI设置', 'wordpress-ai-toolkit'),
            __('AI设置', 'wordpress-ai-toolkit'),
            'manage_options',
            'wordpress-ai-toolkit-ai-settings',
            array($this, 'render_settings_page')
        );
    }


    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        if (isset($_POST['save_settings']) && check_admin_referer('ai_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('设置已保存！', 'wordpress-ai-toolkit') . '</p></div>';
        }

        $settings = $this->get_ai_settings();
        ?>

        <div class="wrap">

            <form method="post" action="">
                <?php wp_nonce_field('ai_settings_nonce'); ?>

                <div class="toolkit-settings-form">
                    <h2>🤖 <?php _e('AI服务配置', 'wordpress-ai-toolkit'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="use_ai_generation"><?php _e('启用AI功能', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="use_ai_generation" name="use_ai_generation" value="1" <?php checked($settings['use_ai_generation']); ?>>
                                <span class="description"><?php _e('启用后所有模块的AI功能将可用', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_provider"><?php _e('AI提供商', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <select id="ai_provider" name="ai_provider">
                                    <option value="deepseek" <?php selected($settings['ai_provider'], 'deepseek'); ?>><?php _e('DeepSeek', 'wordpress-ai-toolkit'); ?></option>
                                    <option value="siliconflow" <?php selected($settings['ai_provider'], 'siliconflow'); ?>><?php _e('硅基流动', 'wordpress-ai-toolkit'); ?></option>
                                </select>
                                <span class="description"><?php _e('选择AI服务提供商', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <!-- DeepSeek 配置 -->
                        <tr class="provider-config deepseek-config" style="<?php echo ($settings['ai_provider'] !== 'deepseek') ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="deepseek_api_key"><?php _e('DeepSeek API密钥', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="deepseek_api_key" name="deepseek_api_key"
                                       value="<?php echo esc_attr($settings['deepseek_api_key']); ?>"
                                       class="regular-text" placeholder="sk-...">
                                <span class="description">
                                    <?php _e('从DeepSeek平台获取API密钥', 'wordpress-ai-toolkit'); ?>
                                    <a href="https://platform.deepseek.com/api_keys" target="_blank"><?php _e('获取密钥', 'wordpress-ai-toolkit'); ?></a><br>
                                    <?php _e('格式：sk-xxxxxxxx', 'wordpress-ai-toolkit'); ?>
                                </span>
                            </td>
                        </tr>

                        <tr class="provider-config deepseek-config" style="<?php echo ($settings['ai_provider'] !== 'deepseek') ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="deepseek_api_base"><?php _e('API基础URL', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="deepseek_api_base" name="deepseek_api_base"
                                       value="<?php echo esc_attr($settings['deepseek_api_base']); ?>"
                                       class="regular-text">
                                <span class="description"><?php _e('DeepSeek API服务地址（无需修改）', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr class="provider-config deepseek-config" style="<?php echo ($settings['ai_provider'] !== 'deepseek') ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="deepseek_model"><?php _e('AI模型', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <select id="deepseek_model" name="deepseek_model">
                                    <option value="deepseek-chat" <?php selected($settings['deepseek_model'], 'deepseek-chat'); ?>>deepseek-chat</option>
                                    <option value="deepseek-reasoner" <?php selected($settings['deepseek_model'], 'deepseek-reasoner'); ?>>deepseek-reasoner</option>
                                </select>
                                <span class="description"><?php _e('选择使用的AI模型', 'wordpress-ai-toolkit'); ?></span>
                                <p class="description">
                                    <strong>deepseek-chat:</strong> <?php _e('快速生成，支持自定义长度和创造性', 'wordpress-ai-toolkit'); ?><br>
                                    <strong>deepseek-reasoner:</strong> <?php _e('深度思考模式，更准确但稍慢', 'wordpress-ai-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- 硅基流动 配置 -->
                        <tr class="provider-config siliconflow-config" style="<?php echo ($settings['ai_provider'] !== 'siliconflow') ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="siliconflow_api_key"><?php _e('硅基流动 API密钥', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="siliconflow_api_key" name="siliconflow_api_key"
                                       value="<?php echo esc_attr($settings['siliconflow_api_key'] ?? ''); ?>"
                                       class="regular-text" placeholder="sk-...">
                                <span class="description">
                                    <?php _e('从硅基流动平台获取API密钥', 'wordpress-ai-toolkit'); ?>
                                    <a href="https://cloud.siliconflow.cn/" target="_blank"><?php _e('获取密钥', 'wordpress-ai-toolkit'); ?></a><br>
                                    <?php _e('格式：sk-xxxxxxxx', 'wordpress-ai-toolkit'); ?>
                                </span>
                            </td>
                        </tr>

                        <tr class="provider-config siliconflow-config" style="<?php echo ($settings['ai_provider'] !== 'siliconflow') ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="siliconflow_api_base"><?php _e('API基础URL', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="siliconflow_api_base" name="siliconflow_api_base"
                                       value="<?php echo esc_attr($settings['siliconflow_api_base'] ?? 'https://api.siliconflow.cn/v1'); ?>"
                                       class="regular-text">
                                <span class="description"><?php _e('硅基流动 API服务地址（无需修改）', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr class="provider-config siliconflow-config" style="<?php echo ($settings['ai_provider'] !== 'siliconflow') ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label for="siliconflow_model"><?php _e('AI模型', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="siliconflow_model" name="siliconflow_model"
                                       value="<?php echo esc_attr($settings['siliconflow_model'] ?? 'deepseek-ai/DeepSeek-V3'); ?>"
                                       class="regular-text" placeholder="deepseek-ai/DeepSeek-V3">
                                <span class="description"><?php _e('输入硅基流动支持的模型名称', 'wordpress-ai-toolkit'); ?></span>
                                <p class="description">
                                    <?php _e('常用模型：deepseek-ai/DeepSeek-V3、Qwen/Qwen2.5-72B-Instruct、THUDM/glm-4-9b-chat 等', 'wordpress-ai-toolkit'); ?><br>
                                    <a href="https://cloud.siliconflow.cn/models" target="_blank"><?php _e('查看所有可用模型', 'wordpress-ai-toolkit'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_max_tokens"><?php _e('最大Token数', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="ai_max_tokens" name="ai_max_tokens"
                                       value="<?php echo $settings['ai_max_tokens']; ?>"
                                       min="50" max="1000" step="10">
                                <span class="description"><?php _e('AI生成内容的最大长度', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_temperature"><?php _e('创造性', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="range" id="ai_temperature" name="ai_temperature"
                                       value="<?php echo $settings['ai_temperature']; ?>"
                                       min="0" max="1" step="0.1">
                                <span id="temperature-value"><?php echo $settings['ai_temperature']; ?></span>
                                <span class="description"><?php _e('值越高越有创造性，建议0.3-0.7', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="fallback_to_simple"><?php _e('降级机制', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="fallback_to_simple" name="fallback_to_simple" value="1" <?php checked($settings['fallback_to_simple']); ?>>
                                <span class="description"><?php _e('AI生成失败时使用本地算法', 'wordpress-ai-toolkit'); ?></span>
                            </td>
                        </tr>

                        <!-- API连接测试 -->
                        <tr>
                            <th scope="row">
                                <label><?php _e('API连接测试', 'wordpress-ai-toolkit'); ?></label>
                            </th>
                            <td>
                                <button type="button" id="test-api-btn" class="button"><?php _e('🧪 测试API连接', 'wordpress-ai-toolkit'); ?></button>
                                <span class="description"><?php _e('测试API连接是否正常工作', 'wordpress-ai-toolkit'); ?></span>
                                <div id="api-test-result" style="margin-top: 15px;"></div>
                            </td>
                        </tr>
                    </table>
                </div>


                <div class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('保存设置', 'wordpress-ai-toolkit'); ?>">
                </div>
            </form>
        </div>

        <style>
        /* WordPress Toolkit AI设置页面样式 */
        .toolkit-settings-form {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        .toolkit-settings-form h2 {
            margin: 0 0 20px 0;
            padding: 0 0 12px 0;
            border-bottom: 1px solid #ddd;
            font-size: 1.3em;
            color: #1d2327;
        }

        .toolkit-settings-form h3 {
            margin: 0 0 15px 0;
            color: #1d2327;
        }

        .form-table th {
            font-weight: 600;
            color: #1d2327;
        }

        #temperature-value {
            background: #f0f0f1;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        #api-test-result {
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
        }

        #api-test-result.success {
            background: #d7edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        #api-test-result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        </style>

        <script>
        jQuery(document).ready(function($) {
            // 温度值显示更新
            $('#ai_temperature').on('input', function() {
                $('#temperature-value').text($(this).val());
            });

            // AI提供商切换功能
            function toggleProviderConfig() {
                var provider = $('#ai_provider').val();

                // 隐藏所有提供商配置
                $('.provider-config').hide();

                // 显示当前选中的提供商配置
                $('.' + provider + '-config').show();

                // 更新API测试按钮的字段
                updateApiTestFields(provider);
            }

            // 更新API测试按钮使用的字段
            function updateApiTestFields(provider) {
                var apiKeyField = provider + '_api_key';
                var apiBaseField = provider + '_api_base';
                var modelField = provider + '_model';

                // 更新API测试按钮的数据源
                $('#test-api-btn').data('api-key-field', apiKeyField);
                $('#test-api-btn').data('api-base-field', apiBaseField);
                $('#test-api-btn').data('model-field', modelField);
            }

            // 初始化提供商配置显示
            toggleProviderConfig();

            // 监听提供商切换
            $('#ai_provider').on('change', toggleProviderConfig);

            // 确保表单提交时所有字段都被包含
            $('form').on('submit', function() {
                // 为所有隐藏的提供商配置字段创建隐藏副本以确保提交
                $('.provider-config:hidden input, .provider-config:hidden select').each(function() {
                    var $hiddenCopy = $('<input type="hidden" name="' + $(this).attr('name') + '" value="' + $(this).val() + '">');
                    $(this).closest('form').append($hiddenCopy);
                });
            });

            // API测试功能
            $('#test-api-btn').on('click', function() {
                var $btn = $(this);
                var $result = $('#api-test-result');
                var provider = $('#ai_provider').val();

                // 获取当前提供商的字段
                var apiKeyField = $btn.data('api-key-field') || 'deepseek_api_key';
                var apiBaseField = $btn.data('api-base-field') || 'deepseek_api_base';
                var modelField = $btn.data('model-field') || 'deepseek_model';

                $btn.prop('disabled', true).text('<?php _e('测试中...', 'wordpress-ai-toolkit'); ?>');
                $result.removeClass('success error').html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_ai_api',
                        nonce: '<?php echo wp_create_nonce("test_ai_api_nonce"); ?>',
                        api_key: $('#' + apiKeyField).val(),
                        api_base: $('#' + apiBaseField).val(),
                        model: $('#' + modelField).val(),
                        provider: provider
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.addClass('success').html(response.data.message);
                        } else {
                            $result.addClass('error').html(response.data.message);
                        }
                    },
                    error: function() {
                        $result.addClass('error').html('<?php _e('请求失败，请检查网络连接', 'wordpress-ai-toolkit'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('测试API连接', 'wordpress-ai-toolkit'); ?>');
                    }
                });
            });
        });
        </script>

        <?php
    }

    /**
     * 保存设置
     */
    public function save_settings() {
        $settings = array(
            'use_ai_generation' => isset($_POST['use_ai_generation']),
            'ai_provider' => sanitize_text_field($_POST['ai_provider']),
            'deepseek_api_key' => sanitize_text_field($_POST['deepseek_api_key'] ?? ''),
            'deepseek_api_base' => sanitize_text_field($_POST['deepseek_api_base'] ?? ''),
            'deepseek_model' => sanitize_text_field($_POST['deepseek_model'] ?? ''),
            'siliconflow_api_key' => sanitize_text_field($_POST['siliconflow_api_key'] ?? ''),
            'siliconflow_api_base' => sanitize_text_field($_POST['siliconflow_api_base'] ?? ''),
            'siliconflow_model' => sanitize_text_field($_POST['siliconflow_model'] ?? ''),
            'ai_max_tokens' => intval($_POST['ai_max_tokens']),
            'ai_temperature' => floatval($_POST['ai_temperature']),
            'fallback_to_simple' => isset($_POST['fallback_to_simple'])
        );

        update_option('wordpress_ai_toolkit_ai_settings', $settings);
    }
}

// 初始化AI设置模块
WordPress_Toolkit_AI_Settings::get_instance();

// AJAX处理函数
add_action('wp_ajax_test_ai_api', function() {
    check_ajax_referer('test_ai_api_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('权限不足', 'wordpress-ai-toolkit')));
    }

    $api_key = sanitize_text_field($_POST['api_key']);
    $api_base = sanitize_text_field($_POST['api_base']);
    $model = sanitize_text_field($_POST['model']);
    $provider = sanitize_text_field($_POST['provider'] ?? 'deepseek');

    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('请先填写API密钥', 'wordpress-ai-toolkit')));
    }

    // 根据提供商设置合适的测试提示词
    $test_prompt = __('请回复"测试成功"', 'wordpress-ai-toolkit');

    // 对于硅基流动，使用更简单的测试
    if ($provider === 'siliconflow') {
        $test_prompt = 'test';
    }

    // 使用辅助函数中的API调用逻辑，避免重复代码
    $response = wp_remote_post($api_base . '/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode(array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $test_prompt
                )
            ),
            'max_tokens' => 10,
            'temperature' => 0.1
        )),
        'timeout' => 15
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => __('连接失败: ', 'wordpress-ai-toolkit') . $response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => __('响应格式错误: ', 'wordpress-ai-toolkit') . json_last_error_msg()));
    }

    if (isset($data['error'])) {
        wp_send_json_error(array('message' => __('API错误: ', 'wordpress-ai-toolkit') . $data['error']['message']));
    }

    wp_send_json_success(array('message' => __('✅ API连接测试成功！模型可用，配置正确。', 'wordpress-ai-toolkit')));
});