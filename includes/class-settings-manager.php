<?php
/**
 * WordPress Toolkit 设置管理器
 * 提供统一的设置保存、获取和验证功能
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Settings_Manager extends WordPress_Toolkit_Module_Base {

    /**
     * 默认设置
     */
    private $default_settings = array();

    /**
     * 设置键名
     */
    private $option_key;

    /**
     * 构造函数
     *
     * @param string $option_key 设置键名
     * @param array $default_settings 默认设置
     */
    public function __construct($option_key, $default_settings = array()) {
        $this->option_key = $option_key;
        $this->default_settings = array_merge($this->get_default_settings(), $default_settings);
    }

    /**
     * 获取默认设置
     *
     * @return array 默认设置
     */
    protected function get_default_settings() {
        return array(
            'enabled' => true,
            'version' => AI_CONTENT_TOOLKIT_VERSION,
            'last_updated' => current_time('mysql')
        );
    }

    /**
     * 获取设置
     *
     * @param string $key 设置键名，为空时返回所有设置
     * @param mixed $default 默认值
     * @return mixed 设置值
     */
    public function get_setting($key = '', $default = null) {
        $settings = get_option($this->option_key, $this->default_settings);

        if (empty($key)) {
            return wp_parse_args($settings, $this->default_settings);
        }

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * 更新设置
     *
     * @param array $new_settings 新设置
     * @return bool 是否成功
     */
    public function update_settings($new_settings) {
        if (!is_array($new_settings)) {
            $this->log_module_action('Invalid settings data provided', 'error');
            return false;
        }

        // 获取当前设置
        $current_settings = $this->get_setting();

        // 验证和清理设置
        $validated_settings = $this->validate_settings($new_settings);

        if ($validated_settings === false) {
            $this->log_module_action('Settings validation failed', 'error');
            return false;
        }

        // 合并设置
        $updated_settings = array_merge($current_settings, $validated_settings);
        $updated_settings['last_updated'] = current_time('mysql');

        // 保存设置
        $result = update_option($this->option_key, $updated_settings);

        if ($result) {
            $this->log_module_action('Settings updated successfully', 'info', array(
                'changed_keys' => array_keys($validated_settings)
            ));
        } else {
            $this->log_module_action('Failed to update settings', 'error');
        }

        return $result;
    }

    /**
     * 更新单个设置
     *
     * @param string $key 设置键名
     * @param mixed $value 设置值
     * @return bool 是否成功
     */
    public function update_setting($key, $value) {
        return $this->update_settings(array($key => $value));
    }

    /**
     * 删除设置
     *
     * @return bool 是否成功
     */
    public function delete_settings() {
        $result = delete_option($this->option_key);

        if ($result) {
            $this->log_module_action('Settings deleted', 'info');
        } else {
            $this->log_module_action('Failed to delete settings', 'error');
        }

        return $result;
    }

    /**
     * 重置设置为默认值
     *
     * @return bool 是否成功
     */
    public function reset_settings() {
        $result = update_option($this->option_key, $this->default_settings);

        if ($result) {
            $this->log_module_action('Settings reset to defaults', 'info');
        } else {
            $this->log_module_action('Failed to reset settings', 'error');
        }

        return $result;
    }

    /**
     * 验证设置
     *
     * @param array $settings 要验证的设置
     * @return array|false 验证后的设置或false
     */
    protected function validate_settings($settings) {
        $validated = array();

        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'enabled':
                    $validated[$key] = $this->validate_input($value, 'bool');
                    break;

                case 'version':
                    $validated[$key] = $this->validate_input($value, 'text');
                    break;

                case 'last_updated':
                    $validated[$key] = $this->validate_input($value, 'text');
                    break;

                default:
                    // 允许子类扩展验证规则
                    $validated[$key] = apply_filters(
                        'wordpress_ai_toolkit_validate_setting_' . $key,
                        $value,
                        $this->option_key
                    );
                    break;
            }

            // 如果验证失败，记录错误并返回false
            if ($validated[$key] === false && $value !== false) {
                $this->log_module_action("Invalid value for setting '{$key}'", 'error', array(
                    'value' => $value,
                    'type' => gettype($value)
                ));
                return false;
            }
        }

        return $validated;
    }

    /**
     * 处理AJAX设置更新请求
     *
     * @param string $nonce_action Nonce动作
     * @param array $field_mapping 字段映射（输入字段名 => 设置键名）
     * @param array $validation_rules 验证规则
     */
    public function handle_ajax_settings_update($nonce_action, $field_mapping = array(), $validation_rules = array()) {
        // 验证AJAX请求
        $this->verify_ajax_request($nonce_action);

        $new_settings = array();
        $errors = array();

        // 处理每个字段
        foreach ($field_mapping as $field_name => $setting_key) {
            if (!isset($_POST[$field_name])) {
                continue;
            }

            $value = $_POST[$field_name];
            $validation_rule = $validation_rules[$field_name] ?? 'text';
            $validation_options = $validation_rules[$field_name . '_options'] ?? array();

            // 验证输入
            $validated_value = $this->validate_input($value, $validation_rule, $validation_options);

            if ($validated_value !== false || $value === false) {
                $new_settings[$setting_key] = $validated_value;
            } else {
                $errors[] = sprintf(
                    __('字段 %s 的值无效', 'wordpress-ai-toolkit'),
                    esc_html($field_name)
                );
            }
        }

        // 如果有错误，返回错误信息
        if (!empty($errors)) {
            $this->send_ajax_response(false, implode('; ', $errors));
            return;
        }

        // 更新设置
        $result = $this->update_settings($new_settings);

        if ($result) {
            $this->send_ajax_response(true, __('设置保存成功', 'wordpress-ai-toolkit'), array(
                'updated_settings' => $new_settings
            ));
        } else {
            $this->send_ajax_response(false, __('设置保存失败', 'wordpress-ai-toolkit'));
        }
    }

    /**
     * 获取设置表单字段
     *
     * @param array $fields 字段配置
     * @return string HTML表单字段
     */
    public function render_settings_fields($fields) {
        $settings = $this->get_setting();
        $html = '';

        foreach ($fields as $field_name => $field_config) {
            $type = $field_config['type'] ?? 'text';
            $label = $field_config['label'] ?? ucfirst($field_name);
            $description = $field_config['description'] ?? '';
            $value = $settings[$field_name] ?? $field_config['default'] ?? '';
            $options = $field_config['options'] ?? array();

            $html .= '<div class="form-field">';
            $html .= '<label for="' . esc_attr($field_name) . '">' . esc_html($label) . '</label>';

            switch ($type) {
                case 'text':
                case 'email':
                case 'url':
                    $html .= '<input type="' . esc_attr($type) . '" id="' . esc_attr($field_name) .
                             '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text">';
                    break;

                case 'textarea':
                    $html .= '<textarea id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) .
                             '" class="large-text" rows="5">' . esc_textarea($value) . '</textarea>';
                    break;

                case 'checkbox':
                    $checked = checked($value, true, false);
                    $html .= '<input type="checkbox" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) .
                             '" value="1" ' . $checked . '>';
                    break;

                case 'select':
                    $html .= '<select id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) . '">';
                    foreach ($options as $option_value => $option_label) {
                        $selected = selected($value, $option_value, false);
                        $html .= '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' .
                                 esc_html($option_label) . '</option>';
                    }
                    $html .= '</select>';
                    break;

                case 'number':
                    $min = isset($field_config['min']) ? 'min="' . esc_attr($field_config['min']) . '"' : '';
                    $max = isset($field_config['max']) ? 'max="' . esc_attr($field_config['max']) . '"' : '';
                    $step = isset($field_config['step']) ? 'step="' . esc_attr($field_config['step']) . '"' : '';
                    $html .= '<input type="number" id="' . esc_attr($field_name) . '" name="' . esc_attr($field_name) .
                             '" value="' . esc_attr($value) . '" class="small-text" ' . $min . ' ' . $max . ' ' . $step . '>';
                    break;

                default:
                    // 允许自定义字段类型
                    $html .= apply_filters('wordpress_ai_toolkit_render_field_' . $type, '', $field_name, $field_config, $value);
                    break;
            }

            if (!empty($description)) {
                $html .= '<p class="description">' . esc_html($description) . '</p>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * 导出设置
     *
     * @return array 设置数据
     */
    public function export_settings() {
        $settings = $this->get_setting();

        $this->log_module_action('Settings exported', 'info');

        return array(
            'plugin' => 'WordPress Toolkit',
            'version' => AI_CONTENT_TOOLKIT_VERSION,
            'export_time' => current_time('mysql'),
            'settings' => $settings
        );
    }

    /**
     * 导入设置
     *
     * @param array $import_data 导入数据
     * @return bool 是否成功
     */
    public function import_settings($import_data) {
        if (!is_array($import_data) || !isset($import_data['settings'])) {
            $this->log_module_action('Invalid import data format', 'error');
            return false;
        }

        $result = $this->update_settings($import_data['settings']);

        if ($result) {
            $this->log_module_action('Settings imported successfully', 'info', array(
                'source_version' => $import_data['version'] ?? 'unknown',
                'export_time' => $import_data['export_time'] ?? 'unknown'
            ));
        }

        return $result;
    }
}