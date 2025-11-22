<?php
/**
 * AI Settings Helper
 *
 * 提供全局访问AI设置的辅助函数
 *
 * @version 1.0.0
 * @author www.saiita.com.cn
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取AI设置
 *
 * @param string|null $key 设置键名，为null时返回所有设置
 * @param mixed $default 默认值
 * @return mixed
 */
function wordpress_ai_toolkit_get_ai_settings($key = null, $default = null) {
    static $ai_settings = null;

    if ($ai_settings === null) {
        $ai_settings = get_option('wordpress_ai_toolkit_ai_settings', array());

        // 合并默认设置 - 使用统一的默认设置
        if (class_exists('WordPress_Toolkit_AI_Settings')) {
            $default_settings = WordPress_Toolkit_AI_Settings::get_ai_default_settings();
        } else {
            // 备用默认设置（如果主类不可用）
            $default_settings = array(
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

        $ai_settings = wp_parse_args($ai_settings, $default_settings);
    }

    if ($key === null) {
        return $ai_settings;
    }

    return isset($ai_settings[$key]) ? $ai_settings[$key] : $default;
}

/**
 * 检查AI功能是否可用
 *
 * @return bool
 */
function wordpress_ai_toolkit_is_ai_available() {
    $settings = wordpress_ai_toolkit_get_ai_settings();
    $provider = $settings['ai_provider'] ?? 'deepseek';

    if ($provider === 'siliconflow') {
        return $settings['use_ai_generation'] &&
               !empty($settings['siliconflow_api_key']) &&
               class_exists('WP_Http');
    } else {
        return $settings['use_ai_generation'] &&
               !empty($settings['deepseek_api_key']) &&
               class_exists('WP_Http');
    }
}

/**
 * 获取AI API配置
 *
 * @return array
 */
function wordpress_ai_toolkit_get_ai_config() {
    $provider = wordpress_ai_toolkit_get_ai_settings('ai_provider', 'deepseek');

    if ($provider === 'siliconflow') {
        return array(
            'api_key' => wordpress_ai_toolkit_get_ai_settings('siliconflow_api_key'),
            'api_base' => wordpress_ai_toolkit_get_ai_settings('siliconflow_api_base', 'https://api.siliconflow.cn/v1'),
            'model' => wordpress_ai_toolkit_get_ai_settings('siliconflow_model', 'deepseek-ai/DeepSeek-V3'),
            'max_tokens' => wordpress_ai_toolkit_get_ai_settings('ai_max_tokens', 150),
            'temperature' => wordpress_ai_toolkit_get_ai_settings('ai_temperature', 0.5)
        );
    } else {
        return array(
            'api_key' => wordpress_ai_toolkit_get_ai_settings('deepseek_api_key'),
            'api_base' => wordpress_ai_toolkit_get_ai_settings('deepseek_api_base', 'https://api.deepseek.com'),
            'model' => wordpress_ai_toolkit_get_ai_settings('deepseek_model', 'deepseek-chat'),
            'max_tokens' => wordpress_ai_toolkit_get_ai_settings('ai_max_tokens', 150),
            'temperature' => wordpress_ai_toolkit_get_ai_settings('ai_temperature', 0.5)
        );
    }
}

/**
 * 调用AI API的通用函数
 *
 * @param string $prompt 提示词
 * @param array $options 额外选项
 * @return array|string
 */
function wordpress_ai_toolkit_call_ai_api($prompt, $options = array()) {
    if (!wordpress_ai_toolkit_is_ai_available()) {
        return new WP_Error('ai_unavailable', __('AI功能不可用', 'wordpress-ai-toolkit'));
    }

    $config = wordpress_ai_toolkit_get_ai_config();
    $options = wp_parse_args($options, array(
        'max_tokens' => $config['max_tokens'],
        'temperature' => $config['temperature'],
        'timeout' => 30
    ));

    $response = wp_remote_post($config['api_base'] . '/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode(array(
            'model' => $config['model'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature']
        )),
        'timeout' => $options['timeout']
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', __('响应格式错误', 'wordpress-ai-toolkit'));
    }

    if (isset($data['error'])) {
        return new WP_Error('api_error', $data['error']['message']);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        return new WP_Error('invalid_response', __('无效的API响应', 'wordpress-ai-toolkit'));
    }

    return $data['choices'][0]['message']['content'];
}