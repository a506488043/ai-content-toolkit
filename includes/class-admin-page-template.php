<?php
/**
 * WordPress Toolkit - 管理页面模板系统
 * 标准化页面结构和通用功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class Toolkit_Admin_Page_Template {

    /**
     * 页面配置
     */
    private $config = array(
        'title' => '',
        'subtitle' => '',
        'tabs' => array(),
        'current_tab' => '',
        'has_welcome' => false,
        'welcome_message' => '',
        'page_icon' => 'dashicons-admin-generic'
    );

    /**
     * 构造函数
     */
    public function __construct($config = array()) {
        $this->config = wp_parse_args($config, $this->config);
        $this->init();
    }

    /**
     * 初始化
     */
    private function init() {
        // 加入WordPress管理页面样式
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * 加载管理页面样式
     */
    public function enqueue_admin_styles($hook) {
        // 只在工具包相关页面加载
        if (strpos($hook, 'wordpress-toolkit') === false) {
            return;
        }

        // 加载统一样式
        wp_enqueue_style(
            'toolkit-variables',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/variables.css',
            array(),
            WORDPRESS_TOOLKIT_VERSION
        );

        wp_enqueue_style(
            'toolkit-common',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/common.css',
            array('toolkit-variables'),
            WORDPRESS_TOOLKIT_VERSION
        );

        // 加载WordPress默认样式
        wp_enqueue_style('common');
        wp_enqueue_style('forms');
        wp_enqueue_style('dashboard');
    }

    /**
     * 加载管理页面脚本
     */
    public function enqueue_admin_scripts($hook) {
        // 只在工具包相关页面加载
        if (strpos($hook, 'wordpress-toolkit') === false) {
            return;
        }

        // 加载统一JavaScript框架
        wp_enqueue_script(
            'toolkit-core',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/js/toolkit-core.js',
            array('jquery'),
            WORDPRESS_TOOLKIT_VERSION,
            true
        );

        // 传递配置到JavaScript
        wp_localize_script('toolkit-core', 'ToolkitConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('toolkit_nonce'),
            'strings' => array(
                'saveSuccess' => __('保存成功！', 'wordpress-toolkit'),
                'saveError' => __('保存失败，请重试。', 'wordpress-toolkit'),
                'networkError' => __('网络错误，请重试。', 'wordpress-toolkit'),
                'confirmDelete' => __('确定要删除这个项目吗？此操作不可撤销。', 'wordpress-toolkit'),
                'deleteSuccess' => __('删除成功！', 'wordpress-toolkit'),
                'deleteError' => __('删除失败，请重试。', 'wordpress-toolkit'),
                'loading' => __('加载中...', 'wordpress-toolkit'),
                'processing' => __('处理中...', 'wordpress-toolkit'),
                'confirm' => __('确定', 'wordpress-toolkit'),
                'cancel' => __('取消', 'wordpress-toolkit')
            )
        ));
    }

    /**
     * 渲染完整页面
     */
    public function render() {
        $this->check_permissions();
        $this->handle_tab_logic();

        echo '<div class="wrap tc-admin-container">';
        $this->render_header();
        $this->render_welcome_section();
        $this->render_tabs();
        $this->render_content();
        echo '</div>';

        $this->render_modal();
    }

    /**
     * 检查用户权限
     */
    private function check_permissions() {
        if (!current_user_can('manage_options')) {
            wp_die(__('抱歉，您没有权限访问此页面。', 'wordpress-toolkit'));
        }
    }

    /**
     * 处理选项卡逻辑
     */
    private function handle_tab_logic() {
        if (empty($this->config['current_tab']) && !empty($this->config['tabs'])) {
            $tab_keys = array_keys($this->config['tabs']);
            $this->config['current_tab'] = $tab_keys[0];
        }

        // 处理GET参数中的选项卡
        if (isset($_GET['tab'])) {
            $tab = sanitize_text_field($_GET['tab']);
            if (isset($this->config['tabs'][$tab])) {
                $this->config['current_tab'] = $tab;
            }
        }
    }

    /**
     * 渲染页面头部
     */
    private function render_header() {
        echo '<div class="tc-flex tc-flex-between tc-flex-center tc-mb-2xl">';

        echo '<div>';
        echo '<h1 class="wp-heading-inline">';
        if (!empty($this->config['page_icon'])) {
            echo '<span class="dashicons ' . esc_attr($this->config['page_icon']) . '"></span> ';
        }
        echo esc_html($this->config['title']);
        echo '</h1>';

        if (!empty($this->config['subtitle'])) {
            echo '<p class="tc-text-secondary">' . esc_html($this->config['subtitle']) . '</p>';
        }
        echo '</div>';

        echo '</div>';

        echo '<hr class="wp-header-end">';
    }

    /**
     * 渲染欢迎区域
     */
    private function render_welcome_section() {
        if (!$this->config['has_welcome']) {
            return;
        }

        $welcome_message = !empty($this->config['welcome_message'])
            ? $this->config['welcome_message']
            : __('欢迎使用WordPress Toolkit！', 'wordpress-toolkit');

        echo '<div class="tc-card tc-mb-2xl" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">';
        echo '<div class="tc-flex tc-flex-center">';
        echo '<div>';
        echo '<h2 style="color: white; margin: 0 0 10px 0;">' . esc_html($welcome_message) . '</h2>';
        echo '<p style="margin: 0; opacity: 0.9;">' . __('功能强大，易于使用的WordPress管理工具集', 'wordpress-toolkit') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * 渲染选项卡导航
     */
    private function render_tabs() {
        if (empty($this->config['tabs'])) {
            return;
        }

        echo '<div class="tc-nav-tab-wrapper">';

        foreach ($this->config['tabs'] as $tab_id => $tab_data) {
            $is_active = $this->config['current_tab'] === $tab_id;
            $class = $is_active ? 'tc-nav-tab tc-nav-tab-active' : 'tc-nav-tab';

            $url = add_query_arg('tab', $tab_id, remove_query_arg('tab'));

            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">';
            if (!empty($tab_data['icon'])) {
                echo '<span class="dashicons ' . esc_attr($tab_data['icon']) . '"></span> ';
            }
            echo esc_html($tab_data['title']);
            if (!empty($tab_data['badge'])) {
                echo ' <span class="tc-badge tc-badge-primary">' . esc_html($tab_data['badge']) . '</span>';
            }
            echo '</a>';
        }

        echo '</div>';
    }

    /**
     * 渲染内容区域
     */
    private function render_content() {
        if (empty($this->config['current_tab']) || empty($this->config['tabs'][$this->config['current_tab']])) {
            $this->render_not_found();
            return;
        }

        $current_tab_data = $this->config['tabs'][$this->config['current_tab']];

        echo '<div class="tc-card">';

        // 渲染选项卡内容
        if (!empty($current_tab_data['callback']) && is_callable($current_tab_data['callback'])) {
            call_user_func($current_tab_data['callback'], $this);
        } elseif (!empty($current_tab_data['content'])) {
            echo $current_tab_data['content'];
        } else {
            $this->render_not_found();
        }

        echo '</div>';
    }

    /**
     * 渲染未找到内容
     */
    private function render_not_found() {
        echo '<div class="tc-text-center tc-py-2xl">';
        echo '<p class="tc-text-secondary">' . __('内容未找到', 'wordpress-toolkit') . '</p>';
        echo '</div>';
    }

    /**
     * 渲染模态框
     */
    private function render_modal() {
        echo '<div id="tc-modal" class="tc-modal" style="display: none;">';
        echo '<div class="tc-modal-overlay"></div>';
        echo '<div class="tc-modal-content">';
        echo '<div class="tc-modal-header">';
        echo '<h3 id="tc-modal-title"></h3>';
        echo '<button type="button" class="tc-modal-close" data-modal-close>&times;</button>';
        echo '</div>';
        echo '<div class="tc-modal-body" id="tc-modal-body"></div>';
        echo '<div class="tc-modal-footer">';
        echo '<button type="button" class="tc-btn tc-btn-secondary" id="tc-modal-cancel" data-modal-close></button>';
        echo '<button type="button" class="tc-btn tc-btn-primary" id="tc-modal-save"></button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // 模态框容器
        echo '<div id="tc-notices"></div>';
        echo '<div id="tc-form-errors"></div>';
    }

    /**
     * 渲染表单字段
     */
    public function render_form_field($type, $name, $label, $value = '', $args = array()) {
        $defaults = array(
            'id' => $name,
            'placeholder' => '',
            'required' => false,
            'disabled' => false,
            'class' => '',
            'description' => '',
            'options' => array(),
            'min' => '',
            'max' => '',
            'step' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $field_id = esc_attr($args['id']);
        $field_name = esc_attr($name);
        $field_value = esc_attr($value);
        $required = $args['required'] ? 'required' : '';
        $disabled = $args['disabled'] ? 'disabled' : '';
        $class = 'tc-form-control ' . esc_attr($args['class']);

        echo '<div class="tc-form-group">';

        if (!empty($label)) {
            $required_label = $args['required'] ? ' <span class="tc-text-danger">*</span>' : '';
            echo '<label for="' . $field_id . '" class="tc-form-label">' . esc_html($label) . $required_label . '</label>';
        }

        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                echo '<input type="' . esc_attr($type) . '" id="' . $field_id . '" name="' . $field_name . '" ';
                echo 'value="' . $field_value . '" class="' . $class . '" ';
                echo 'placeholder="' . esc_attr($args['placeholder']) . '" ' . $required . ' ' . $disabled;
                if (!empty($args['min'])) echo ' min="' . esc_attr($args['min']) . '"';
                if (!empty($args['max'])) echo ' max="' . esc_attr($args['max']) . '"';
                if (!empty($args['step'])) echo ' step="' . esc_attr($args['step']) . '"';
                echo '>';
                break;

            case 'textarea':
                echo '<textarea id="' . $field_id . '" name="' . $field_name . '" ';
                echo 'class="' . $class . '" placeholder="' . esc_attr($args['placeholder']) . '" ';
                echo $required . ' ' . $disabled . '>' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                echo '<select id="' . $field_id . '" name="' . $field_name . '" ';
                echo 'class="' . $class . '" ' . $required . ' ' . $disabled . '>';

                foreach ($args['options'] as $option_value => $option_label) {
                    $selected = ($option_value == $value) ? 'selected' : '';
                    echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                }

                echo '</select>';
                break;

            case 'checkbox':
                $checked = ($value) ? 'checked' : '';
                echo '<label class="tc-flex tc-flex-center">';
                echo '<input type="checkbox" id="' . $field_id . '" name="' . $field_name . '" value="1" ';
                echo $checked . ' ' . $disabled . ' class="tc-form-control" style="width: auto;">';
                echo '<span>' . esc_html($label) . '</span>';
                echo '</label>';
                break;

            case 'color':
                echo '<input type="color" id="' . $field_id . '" name="' . $field_name . '" ';
                echo 'value="' . $field_value . '" class="tc-form-control" style="width: 60px; height: 40px; padding: 0;" ';
                echo $required . ' ' . $disabled . '>';
                break;
        }

        if (!empty($args['description'])) {
            echo '<p class="tc-form-help">' . esc_html($args['description']) . '</p>';
        }

        echo '</div>';
    }

    /**
     * 渲染表单提交按钮
     */
    public function render_submit_button($text = '保存设置', $args = array()) {
        $defaults = array(
            'name' => 'submit',
            'class' => 'tc-btn tc-btn-primary',
            'loading' => '保存中...',
            'disabled' => false
        );

        $args = wp_parse_args($args, $defaults);

        echo '<div class="tc-form-group tc-mt-2xl">';
        echo '<button type="submit" name="' . esc_attr($args['name']) . '" ';
        echo 'class="' . esc_attr($args['class']) . '" ';
        echo 'data-loading="' . esc_attr($args['loading']) . '"';
        if ($args['disabled']) echo ' disabled';
        echo '>' . esc_html($text) . '</button>';
        echo '</div>';
    }

    /**
     * 渲染表单开始标签
     */
    public function render_form_start($action = '', $method = 'post', $args = array()) {
        $defaults = array(
            'class' => '',
            'id' => '',
            'ajax' => false,
            'confirm' => '',
            'callback' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $attributes = array();
        if (!empty($action)) $attributes['action'] = esc_url($action);
        if (!empty($method)) $attributes['method'] = esc_attr($method);
        if (!empty($args['class'])) $attributes['class'] = esc_attr($args['class']);
        if (!empty($args['id'])) $attributes['id'] = esc_attr($args['id']);

        if ($args['ajax']) {
            $attributes['data-ajax-form'] = '1';
            if (!empty($args['confirm'])) $attributes['data-confirm'] = esc_attr($args['confirm']);
            if (!empty($args['callback'])) $attributes['data-callback'] = esc_attr($args['callback']);
        }

        echo '<form';
        foreach ($attributes as $attr_name => $attr_value) {
            echo ' ' . $attr_name . '="' . $attr_value . '"';
        }
        echo '>';

        // 添加nonce字段
        wp_nonce_field('toolkit_form_nonce', 'toolkit_nonce');
    }

    /**
     * 渲染表单结束标签
     */
    public function render_form_end() {
        echo '</form>';
    }

    /**
     * 获取当前选项卡
     */
    public function get_current_tab() {
        return $this->config['current_tab'];
    }

    /**
     * 设置页面配置
     */
    public function set_config($key, $value) {
        $this->config[$key] = $value;
    }

    /**
     * 获取页面配置
     */
    public function get_config($key = null) {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
}