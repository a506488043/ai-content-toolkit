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
    private $settings;

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
        $this->settings = $this->get_default_settings();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 添加管理菜单 - 作为工具箱设置的子菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * 获取默认设置
     */
    private function get_default_settings() {
        return array(
            'use_ai_generation' => true,
            'ai_provider' => 'deepseek',
            'deepseek_api_key' => '',
            'deepseek_api_base' => 'https://api.deepseek.com',
            'deepseek_model' => 'deepseek-chat',
            'ai_max_tokens' => 150,
            'ai_temperature' => 0.5,
            'fallback_to_simple' => true
        );
    }

    /**
     * 获取AI设置
     */
    public function get_ai_settings() {
        $saved_settings = get_option('wordpress_toolkit_ai_settings', array());
        return wp_parse_args($saved_settings, $this->settings);
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-toolkit-settings',  // 父菜单：工具箱设置
            __('AI设置', 'wordpress-toolkit'),
            __('AI设置', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-ai-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * 渲染使用统计
     */
    private function render_usage_stats() {
        // 统计各模块的AI使用情况
        $stats = array(
            'article_optimization' => array(
                'label' => __('文章优化', 'wordpress-toolkit'),
                'total' => wp_count_posts('post')->publish,
                'ai_generated' => $this->count_ai_generated_posts()
            ),
            'category_optimization' => array(
                'label' => __('分类优化', 'wordpress-toolkit'),
                'total' => wp_count_terms('category', array('hide_empty' => false)),
                'ai_generated' => $this->count_ai_generated_terms('category')
            ),
            'tag_optimization' => array(
                'label' => __('标签优化', 'wordpress-toolkit'),
                'total' => wp_count_terms('post_tag', array('hide_empty' => false)),
                'ai_generated' => $this->count_ai_generated_terms('post_tag')
            )
        );
        ?>

        <div class="usage-stats-grid">
            <?php foreach ($stats as $module => $data): ?>
                <div class="usage-stat-item">
                    <div class="usage-stat-number"><?php echo $data['ai_generated']; ?>/<?php echo $data['total']; ?></div>
                    <div class="usage-stat-label"><?php echo $data['label']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
    }

    /**
     * 统计AI生成的文章数量
     */
    private function count_ai_generated_posts() {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value = 1",
            'ai_generated_excerpt'
        ));
    }

    /**
     * 统计AI生成的分类/标签数量
     */
    private function count_ai_generated_terms($taxonomy) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->termmeta} tm
             JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id
             WHERE tm.meta_key = %s AND tt.taxonomy = %s",
            'ai_description',
            $taxonomy
        ));
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        if (isset($_POST['save_settings']) && check_admin_referer('ai_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('设置已保存！', 'wordpress-toolkit') . '</p></div>';
        }

        $settings = $this->get_ai_settings();
        ?>

        <div class="wrap">
            <h1><?php _e('AI设置', 'wordpress-toolkit'); ?></h1>
            <p class="description"><?php _e('配置AI服务的相关设置，这些设置将应用于所有AI功能模块。', 'wordpress-toolkit'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('ai_settings_nonce'); ?>

                <div class="toolkit-settings-form">
                    <h2>🤖 <?php _e('AI服务配置', 'wordpress-toolkit'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="use_ai_generation"><?php _e('启用AI功能', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="use_ai_generation" name="use_ai_generation" value="1" <?php checked($settings['use_ai_generation']); ?>>
                                <span class="description"><?php _e('启用后所有模块的AI功能将可用', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_provider"><?php _e('AI提供商', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <select id="ai_provider" name="ai_provider">
                                    <option value="deepseek" <?php selected($settings['ai_provider'], 'deepseek'); ?>><?php _e('DeepSeek', 'wordpress-toolkit'); ?></option>
                                </select>
                                <span class="description"><?php _e('选择AI服务提供商', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="deepseek_api_key"><?php _e('DeepSeek API密钥', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="deepseek_api_key" name="deepseek_api_key"
                                       value="<?php echo esc_attr($settings['deepseek_api_key']); ?>"
                                       class="regular-text" placeholder="sk-...">
                                <span class="description">
                                    <?php _e('从DeepSeek平台获取API密钥', 'wordpress-toolkit'); ?>
                                    <a href="https://platform.deepseek.com/api_keys" target="_blank"><?php _e('获取密钥', 'wordpress-toolkit'); ?></a><br>
                                    <?php _e('格式：sk-xxxxxxxx', 'wordpress-toolkit'); ?>
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="deepseek_api_base"><?php _e('API基础URL', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="deepseek_api_base" name="deepseek_api_base"
                                       value="<?php echo esc_attr($settings['deepseek_api_base']); ?>"
                                       class="regular-text">
                                <span class="description"><?php _e('DeepSeek API服务地址（无需修改）', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="deepseek_model"><?php _e('AI模型', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <select id="deepseek_model" name="deepseek_model">
                                    <option value="deepseek-chat" <?php selected($settings['deepseek_model'], 'deepseek-chat'); ?>>deepseek-chat</option>
                                    <option value="deepseek-reasoner" <?php selected($settings['deepseek_model'], 'deepseek-reasoner'); ?>>deepseek-reasoner</option>
                                </select>
                                <span class="description"><?php _e('选择使用的AI模型', 'wordpress-toolkit'); ?></span>
                                <p class="description">
                                    <strong>deepseek-chat:</strong> <?php _e('快速生成，支持自定义长度和创造性', 'wordpress-toolkit'); ?><br>
                                    <strong>deepseek-reasoner:</strong> <?php _e('深度思考模式，更准确但稍慢', 'wordpress-toolkit'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_max_tokens"><?php _e('最大Token数', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="ai_max_tokens" name="ai_max_tokens"
                                       value="<?php echo $settings['ai_max_tokens']; ?>"
                                       min="50" max="1000" step="10">
                                <span class="description"><?php _e('AI生成内容的最大长度', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ai_temperature"><?php _e('创造性', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="range" id="ai_temperature" name="ai_temperature"
                                       value="<?php echo $settings['ai_temperature']; ?>"
                                       min="0" max="1" step="0.1">
                                <span id="temperature-value"><?php echo $settings['ai_temperature']; ?></span>
                                <span class="description"><?php _e('值越高越有创造性，建议0.3-0.7', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="fallback_to_simple"><?php _e('降级机制', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="fallback_to_simple" name="fallback_to_simple" value="1" <?php checked($settings['fallback_to_simple']); ?>>
                                <span class="description"><?php _e('AI生成失败时使用本地算法', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- API测试功能 -->
                <div class="toolkit-settings-form">
                    <h3><?php _e('🧪 API连接测试', 'wordpress-toolkit'); ?></h3>
                    <p><?php _e('测试API连接是否正常工作，确保配置正确。', 'wordpress-toolkit'); ?></p>
                    <button type="button" id="test-api-btn" class="button"><?php _e('测试API连接', 'wordpress-toolkit'); ?></button>
                    <div id="api-test-result" style="margin-top: 15px;"></div>
                </div>

                <!-- 使用统计 -->
                <div class="toolkit-settings-form">
                    <h3><?php _e('📊 使用统计', 'wordpress-toolkit'); ?></h3>
                    <p><?php _e('查看各模块的AI功能使用情况。', 'wordpress-toolkit'); ?></p>
                    <?php $this->render_usage_stats(); ?>
                </div>

                <div class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('保存设置', 'wordpress-toolkit'); ?>">
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

        .usage-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .usage-stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }

        .usage-stat-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #1d2327;
        }

        .usage-stat-label {
            color: #50575e;
            font-size: 0.9em;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // 温度值显示更新
            $('#ai_temperature').on('input', function() {
                $('#temperature-value').text($(this).val());
            });

            // API测试功能
            $('#test-api-btn').on('click', function() {
                var $btn = $(this);
                var $result = $('#api-test-result');

                $btn.prop('disabled', true).text('<?php _e('测试中...', 'wordpress-toolkit'); ?>');
                $result.removeClass('success error').html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_ai_api',
                        nonce: '<?php echo wp_create_nonce("test_ai_api_nonce"); ?>',
                        api_key: $('#deepseek_api_key').val(),
                        api_base: $('#deepseek_api_base').val(),
                        model: $('#deepseek_model').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.addClass('success').html(response.data.message);
                        } else {
                            $result.addClass('error').html(response.data.message);
                        }
                    },
                    error: function() {
                        $result.addClass('error').html('<?php _e('请求失败，请检查网络连接', 'wordpress-toolkit'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('测试API连接', 'wordpress-toolkit'); ?>');
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
            'deepseek_api_key' => sanitize_text_field($_POST['deepseek_api_key']),
            'deepseek_api_base' => sanitize_text_field($_POST['deepseek_api_base']),
            'deepseek_model' => sanitize_text_field($_POST['deepseek_model']),
            'ai_max_tokens' => intval($_POST['ai_max_tokens']),
            'ai_temperature' => floatval($_POST['ai_temperature']),
            'fallback_to_simple' => isset($_POST['fallback_to_simple'])
        );

        update_option('wordpress_toolkit_ai_settings', $settings);
    }
}

// 初始化AI设置模块
WordPress_Toolkit_AI_Settings::get_instance();

// AJAX处理函数
add_action('wp_ajax_test_ai_api', function() {
    check_ajax_referer('test_ai_api_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('权限不足', 'wordpress-toolkit')));
    }

    $api_key = sanitize_text_field($_POST['api_key']);
    $api_base = sanitize_text_field($_POST['api_base']);
    $model = sanitize_text_field($_POST['model']);

    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('请先填写API密钥', 'wordpress-toolkit')));
    }

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
                    'content' => __('请回复"测试成功"', 'wordpress-toolkit')
                )
            ),
            'max_tokens' => 10,
            'temperature' => 0.1
        )),
        'timeout' => 15
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => __('连接失败: ', 'wordpress-toolkit') . $response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => __('响应格式错误: ', 'wordpress-toolkit') . json_last_error_msg()));
    }

    if (isset($data['error'])) {
        wp_send_json_error(array('message' => __('API错误: ', 'wordpress-toolkit') . $data['error']['message']));
    }

    wp_send_json_success(array('message' => __('✅ API连接测试成功！模型可用，配置正确。', 'wordpress-toolkit')));
});