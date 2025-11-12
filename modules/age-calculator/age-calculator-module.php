<?php
/**
 * Age Calculator 模块
 * 集成原Age Calculator插件的所有功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Age Calculator 模块类
 */
class Age_Calculator_Module {
    
    /**
     * 模块版本
     */
    const MODULE_VERSION = '1.0.3';
    
    /**
     * 选项键名
     */
    private $option_name = 'wordpress_toolkit_age_calculator_options';
    
    /**
     * 构造函数
     */
    public function __construct() {
        // 初始化钩子将在init()中设置
    }
    
    /**
     * 激活模块
     */
    public function activate() {
        // 设置默认选项
        $default_options = array(
            'default_format' => 'years',
            'show_for_logged_in' => true,
            'show_for_logged_out' => true,
            'module_version' => self::MODULE_VERSION
        );
        
        add_option($this->option_name, $default_options);
    }
    
    /**
     * 停用模块
     */
    public function deactivate() {
        // 清理临时数据
        wp_cache_flush();
    }
    
    /**
     * 初始化模块
     */
    public function init() {
        // 注册短代码
        add_shortcode('manus_age_calculator', array($this, 'shortcode_display'));
        add_shortcode('age_calculator', array($this, 'shortcode_display'));
        add_shortcode('manus_age_calculator_form', array($this, 'shortcode_form'));

        // 注册页面模板
        add_filter('theme_page_templates', array($this, 'add_page_template'));
        add_filter('template_include', array($this, 'load_page_template'));

        // 注册前端脚本和样式
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // 注册AJAX处理器
        add_action('wp_ajax_age_calculator_calculate', array($this, 'ajax_calculate'));
        add_action('wp_ajax_nopriv_age_calculator_calculate', array($this, 'ajax_calculate'));

        // 注册设置API
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * 加载管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 使用统一样式，不需要加载额外的CSS和JS
        return;
    }
    
    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 加载原Age Calculator的前端样式
        wp_enqueue_style(
            'wordpress-toolkit-age-calculator',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/age-calculator/assets/style.css',
            array(),
            self::MODULE_VERSION
        );
        
        // 加载原Age Calculator的前端脚本
        wp_enqueue_script(
            'wordpress-toolkit-age-calculator-script',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/age-calculator/assets/script.js',
            array('jquery'),
            self::MODULE_VERSION,
            true
        );
        
        // 传递AJAX URL
        wp_localize_script('wordpress-toolkit-age-calculator-script', 'age_calculator_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('age_calculator_nonce'),
            'plugin_url' => WORDPRESS_TOOLKIT_PLUGIN_URL
        ));
    }
    
    /**
     * 注册设置API
     */
    public function register_settings() {
        register_setting(
            'wordpress_toolkit_age_calculator_options',
            'wordpress_toolkit_age_calculator_options',
            array(
                'sanitize_callback' => array($this, 'sanitize_options')
            )
        );
    }

    /**
     * 清理和验证选项
     */
    public function sanitize_options($input) {
        $sanitized = array();

        // 验证默认格式
        if (isset($input['default_format']) && in_array($input['default_format'], array('years', 'months', 'days', 'detailed'))) {
            $sanitized['default_format'] = sanitize_text_field($input['default_format']);
        } else {
            $sanitized['default_format'] = 'years';
        }

        // 验证登录用户显示选项
        $sanitized['show_for_logged_in'] = isset($input['show_for_logged_in']) ? true : false;

        // 验证未登录用户显示选项
        $sanitized['show_for_logged_out'] = isset($input['show_for_logged_out']) ? true : false;

        // 保留模块版本
        $current_options = get_option('wordpress_toolkit_age_calculator_options', array());
        if (isset($current_options['module_version'])) {
            $sanitized['module_version'] = $current_options['module_version'];
        }

        return $sanitized;
    }

    /**
     * 设置页面 - 只显示基本设置
     */
    public function settings_page() {
        // 包含原Age Calculator的管理页面，强制显示设置选项卡
        include WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/age-calculator/admin/admin-page.php';
    }

    /**
     * 管理页面 - 保持向后兼容
     */
    public function admin_page() {
        // 包含原Age Calculator的管理页面
        include WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/age-calculator/admin/admin-page.php';
    }
    
    /**
     * 短代码显示
     */
    public function shortcode_display($atts) {
        // 获取默认设置
        $options = get_option('wordpress_toolkit_age_calculator_options', array());
        $default_format = isset($options['default_format']) ? $options['default_format'] : 'years';

        $atts = shortcode_atts(array(
            'birthdate' => '',
            'reference_date' => '',
            'format' => $default_format,
            'show_for_logged_in' => 'true',
            'show_for_logged_out' => 'true',
            'show_form' => 'false'
        ), $atts, 'manus_age_calculator');
        
        // 检查显示权限
        $show_for_logged_in = filter_var($atts['show_for_logged_in'], FILTER_VALIDATE_BOOLEAN);
        $show_for_logged_out = filter_var($atts['show_for_logged_out'], FILTER_VALIDATE_BOOLEAN);
        
        if (is_user_logged_in() && !$show_for_logged_in) {
            return '';
        }
        
        if (!is_user_logged_in() && !$show_for_logged_out) {
            return '';
        }
        
        // 如果显示表单
        if (filter_var($atts['show_form'], FILTER_VALIDATE_BOOLEAN)) {
            return $this->render_form();
        }
        
        // 如果没有生日，返回空
        if (empty($atts['birthdate'])) {
            return '';
        }
        
        // 计算年龄
        try {
            $age = $this->calculate_age($atts['birthdate'], $atts['reference_date'], $atts['format']);
            return $this->render_result($age, $atts['format']);
        } catch (Exception $e) {
            return '<div class="age-calculator-error">' . esc_html($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * 表单短代码
     */
    public function shortcode_form($atts) {
        $atts = shortcode_atts(array(
            'show_for_logged_in' => 'true',
            'show_for_logged_out' => 'true'
        ), $atts, 'manus_age_calculator_form');
        
        // 检查显示权限
        $show_for_logged_in = filter_var($atts['show_for_logged_in'], FILTER_VALIDATE_BOOLEAN);
        $show_for_logged_out = filter_var($atts['show_for_logged_out'], FILTER_VALIDATE_BOOLEAN);
        
        if (is_user_logged_in() && !$show_for_logged_in) {
            return '';
        }
        
        if (!is_user_logged_in() && !$show_for_logged_out) {
            return '';
        }
        
        return $this->render_form();
    }
    
    /**
     * AJAX计算处理器
     */
    public function ajax_calculate() {
        check_ajax_referer('age_calculator_nonce', 'nonce');
        
        $birthdate = sanitize_text_field($_POST['birthdate']);
        $reference_date = sanitize_text_field($_POST['reference_date']);

        // 使用默认格式设置
        $options = get_option('wordpress_toolkit_age_calculator_options', array());
        $format = isset($options['default_format']) ? $options['default_format'] : 'years';
        
        if (empty($birthdate)) {
            wp_send_json_error('请提供出生日期');
        }
        
        try {
            $age = $this->calculate_age($birthdate, $reference_date, $format);
            $result = $this->render_result($age, $format);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * 计算年龄
     */
    private function calculate_age($birthdate_str, $reference_date_str, $format) {
        try {
            $birthdate = new DateTime($birthdate_str);
            
            if (empty($reference_date_str)) {
                $reference_date = new DateTime();
            } else {
                $reference_date = new DateTime($reference_date_str);
            }
            
            // 确保出生日期不大于参考日期
            if ($birthdate > $reference_date) {
                throw new Exception('出生日期不能晚于参考日期');
            }
            
            $interval = $birthdate->diff($reference_date);
            
            switch ($format) {
                case 'years':
                    // 使用精确的周岁计算，考虑闰年
                    return $this->calculate_exact_years($birthdate, $reference_date);
                case 'months':
                    return $interval->y * 12 + $interval->m;
                case 'days':
                    // 使用精确的天数计算，考虑闰年
                    return $this->calculate_exact_days($birthdate, $reference_date);
                case 'detailed':
                    return array(
                        'years' => $this->calculate_exact_years($birthdate, $reference_date),
                        'months' => $interval->m,
                        'days' => $interval->d
                    );
                default:
                    return $this->calculate_exact_years($birthdate, $reference_date);
            }
        } catch (Exception $e) {
            throw new Exception('无效的日期格式: ' . $e->getMessage());
        }
    }
    
    /**
     * 精确计算周岁（考虑闰年）
     */
    private function calculate_exact_years($birthdate, $reference_date) {
        $years = $reference_date->format('Y') - $birthdate->format('Y');
        
        // 检查是否已经过了生日
        $birth_month_day = $birthdate->format('md');
        $current_month_day = $reference_date->format('md');
        
        if ($current_month_day < $birth_month_day) {
            $years--;
        }
        
        return $years;
    }
    
    /**
     * 精确计算天数（考虑闰年）
     */
    private function calculate_exact_days($birthdate, $reference_date) {
        $interval = $birthdate->diff($reference_date);
        return $interval->days;
    }
    
    /**
     * 渲染结果
     */
    private function render_result($age, $format) {
        $calculation_note = '<div class="age-calculation-note" style="font-size: 12px; color: #666; margin-top: 10px;">
            <strong>计算说明：</strong>使用周岁计算（实岁），已考虑闰年2月天数情况
        </div>';
        
        switch ($format) {
            case 'years':
                return '<div class="age-calculator-result">年龄: <span class="age-value">' . esc_html($age) . '</span> 岁</div>' . $calculation_note;
            case 'months':
                return '<div class="age-calculator-result">年龄: <span class="age-value">' . esc_html($age) . '</span> 个月</div>' . $calculation_note;
            case 'days':
                return '<div class="age-calculator-result">年龄: <span class="age-value">' . esc_html($age) . '</span> 天</div>' . $calculation_note;
            case 'detailed':
                return '<div class="age-calculator-result detailed">
                    <span class="age-label">年龄:</span> <span class="age-value">' . esc_html($age['years']) . '</span><span class="age-unit">岁</span>
                    <span class="age-label">月份:</span> <span class="age-value">' . esc_html($age['months']) . '</span><span class="age-unit">个月</span>
                    <span class="age-label">天数:</span> <span class="age-value">' . esc_html($age['days']) . '</span><span class="age-unit">天</span>
                </div>' . $calculation_note;
            default:
                return '<div class="age-calculator-result">年龄: <span class="age-value">' . esc_html($age) . '</span> 岁</div>' . $calculation_note;
        }
    }
    
    /**
     * 渲染表单
     */
    private function render_form() {
        // 获取默认设置
        $options = get_option('wordpress_toolkit_age_calculator_options', array());
        $default_format = isset($options['default_format']) ? $options['default_format'] : 'years';

        // 调试：确保默认格式被正确设置
        if (empty($default_format)) {
            $default_format = 'years';
        }

        ob_start();
        ?>
        <div class="age-calculator-form">
            <form class="manus-age-calculator-form" method="post">
                <style>
                    /* 内联样式确保下拉框文字可见 */
                    .age-calculator-form select {
                        color: #333 !important;
                        background-color: #fff !important;
                        text-shadow: none !important;
                        opacity: 1 !important;
                    }
                    .age-calculator-form select option {
                        color: #333 !important;
                        background-color: #fff !important;
                        text-shadow: none !important;
                        opacity: 1 !important;
                    }
                    /* 确保选中的选项文字可见 */
                    .age-calculator-form select:invalid {
                        color: #333 !important;
                    }
                </style>
                <div class="form-group">
                    <label for="birthdate">出生日期:</label>
                    <input type="date" id="birthdate" name="birthdate" value="1990-07-25" required>
                </div>

                
                <button type="submit" class="button button-primary">计算年龄</button>
            </form>
            
            <div class="age-calculator-result-container"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 页面加载时自动计算默认年龄
            function calculateDefaultAge() {
                var birthdate = $('#birthdate').val();

                if (birthdate) {
                    $.ajax({
                        url: age_calculator_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'age_calculator_calculate',
                            nonce: age_calculator_ajax.nonce,
                            birthdate: birthdate
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.age-calculator-result-container').html(response.data).show();
                            }
                        }
                    });
                }
            }

            // 页面加载时立即计算
            calculateDefaultAge();

            $('.manus-age-calculator-form').on('submit', function(e) {
                e.preventDefault();

                var birthdate = $('#birthdate').val();

                if (!birthdate) {
                    alert('请选择出生日期');
                    return;
                }

                $.ajax({
                    url: age_calculator_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'age_calculator_calculate',
                        nonce: age_calculator_ajax.nonce,
                        birthdate: birthdate
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.age-calculator-result-container').html(response.data).show();
                        } else {
                            $('.age-calculator-result-container').html('<div class="error">' + response.data + '</div>').show();
                        }
                    },
                    error: function() {
                        $('.age-calculator-result-container').html('<div class="error">计算失败，请重试</div>').show();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 添加页面模板
     */
    public function add_page_template($templates) {
        $templates['age-calculator-page.php'] = '年龄计算器页面';
        return $templates;
    }
    
    /**
     * 加载页面模板
     */
    public function load_page_template($template) {
        if (get_page_template_slug() === 'age-calculator-page.php') {
            $template = WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/age-calculator/templates/age-calculator-page.php';
        }
        return $template;
    }
}
