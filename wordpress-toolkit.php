<?php
/**
 * Plugin Name: WordPress Toolkit
 * Plugin URI: https://www.saiita.com.cn
 * Description: 一个集成了网站卡片、年龄计算器、物品管理、友情链接、文章优化、Cookie同意通知和REST代理修复的综合工具包。
 * Version: 1.0.5
 * Author: www.saiita.com.cn
 * Author URI: https://www.saiita.com.cn
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wordpress-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('WORDPRESS_TOOLKIT_VERSION', '1.0.5');
define('WORDPRESS_TOOLKIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WORDPRESS_TOOLKIT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WORDPRESS_TOOLKIT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 国际化支持已移除 - 直接使用WordPress原生翻译函数

// 加载日志管理
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/class-logger.php';

// 加载基础模块类
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/abstract-class-module-base.php';

// 加载管理页面模板系统
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/class-admin-page-template.php';

// 加载统一核心类
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/class-security-validator.php';
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/class-cache-manager.php';
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/class-database-manager.php';
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'includes/class-utilities.php';

// 加载REST代理修复模块
require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/rest-proxy-fix.php';

/**
 * WordPress Toolkit 主类
 */
class WordPress_Toolkit {
    
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * 子模块实例
     */
    private $custom_card = null;
    private $age_calculator = null;
    private $time_capsule = null;
    private $cookieguard = null;
    private $simple_friendlink = null;
    private $simple_friendlink_admin = null;
    private $auto_excerpt = null;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_modules();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 插件激活和停用钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // WordPress初始化钩子
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // 插件链接
        add_filter('plugin_action_links_' . WORDPRESS_TOOLKIT_PLUGIN_BASENAME, array($this, 'add_plugin_links'));
    }
    
    /**
     * 加载子模块
     */
    private function load_modules() {
        // 加载Custom Card模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/custom-card/custom-card-module.php';
        $this->custom_card = new Custom_Card_Module();
        
        // 加载Age Calculator模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/age-calculator/age-calculator-module.php';
        $this->age_calculator = new Age_Calculator_Module();
        
        // 加载Time Capsule模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/time-capsule/time-capsule-module.php';
        $this->time_capsule = new Time_Capsule_Module();
        
        // 加载CookieGuard模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/cookieguard/cookieguard-module.php';
        $this->cookieguard = CookieGuard_Module::get_instance();

        // 加载Simple FriendLink模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/simple-friendlink/simple-friendlink-module.php';
        $this->simple_friendlink = Simple_FriendLink_Module::get_instance();

        // 加载Simple FriendLink管理页面
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/simple-friendlink/admin.php';
        $this->simple_friendlink_admin = new Simple_FriendLink_Admin();

        // 加载AI Settings模块 - 必须在其他需要AI功能的模块之前加载
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/ai-settings/ai-settings-module.php';
        WordPress_Toolkit_AI_Settings::get_instance(); // 确保AI设置模块被实例化，加载helper函数

        // 加载Auto Excerpt模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/auto-excerpt/auto-excerpt-module.php';
        $this->auto_excerpt = Auto_Excerpt_Module::get_instance();

        // 加载Category Optimization模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/category-optimization/category-optimization-module.php';
        $this->category_optimization = Category_Optimization_Module::get_instance();

        // 加载Tag Optimization模块
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/tag-optimization/tag-optimization-module.php';
        $this->tag_optimization = Tag_Optimization_Module::get_instance();

        // Auto Excerpt 管理功能已整合到设置页面，无需额外加载

        // 调试日志
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WordPress Toolkit: Modules loaded - Custom Card: ' . ($this->custom_card ? 'Yes' : 'No'));
        }
    }
    
    /**
     * 插件激活
     */
    public function activate() {
        // 先加载模块
        $this->load_modules();
        
        // 激活所有子模块
        if ($this->custom_card) $this->custom_card->activate();
        if ($this->age_calculator) $this->age_calculator->activate();
        if ($this->time_capsule) $this->time_capsule->activate();
        if ($this->cookieguard) $this->cookieguard->activate();
        if ($this->simple_friendlink) $this->simple_friendlink->activate();
        if ($this->auto_excerpt) $this->auto_excerpt->activate();
        
        // 设置插件激活时间
        add_option('wordpress_toolkit_activated_time', current_time('timestamp'));
    }
    
    /**
     * 插件停用
     */
    public function deactivate() {
        // 停用所有子模块
        if ($this->custom_card) $this->custom_card->deactivate();
        if ($this->age_calculator) $this->age_calculator->deactivate();
        if ($this->time_capsule) $this->time_capsule->deactivate();
        if ($this->cookieguard) $this->cookieguard->deactivate();
        if ($this->simple_friendlink) $this->simple_friendlink->deactivate();
        if ($this->auto_excerpt) $this->auto_excerpt->deactivate();
    }
    
    /**
     * 初始化
     */
    public function init() {
        // 加载文本域
        load_plugin_textdomain('wordpress-toolkit', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 初始化所有子模块
        if ($this->custom_card) $this->custom_card->init();
        if ($this->age_calculator) $this->age_calculator->init();
        if ($this->time_capsule) $this->time_capsule->init();
        if ($this->cookieguard) $this->cookieguard->init();
        if ($this->simple_friendlink) $this->simple_friendlink->init();
        if ($this->auto_excerpt) $this->auto_excerpt->init();
    }
    
    /**
     * 添加管理菜单 - 重新组织结构
     */
    public function add_admin_menu() {
        // ======================
        // 工具箱菜单 - 数据查看和操作
        // ======================

        // 添加主菜单 - 使用较低权限让订阅者也能看到
        add_menu_page(
            'WordPress Toolkit',
            __('工具箱', 'wordpress-toolkit'),
            'read', // 使用基础阅读权限，所有登录用户都有
            'wordpress-toolkit',
            array($this, 'admin_page'),
            'dashicons-admin-tools',
            30
        );

        // 网站卡片（仅管理员可见）
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'wordpress-toolkit',
                __('网站卡片', 'wordpress-toolkit'),
                __('网站卡片', 'wordpress-toolkit'),
                'manage_options',
                'wordpress-toolkit-cards-list',
                array($this, 'custom_cards_list_page')
            );
        }

        // 物品管理（订阅者和管理员都可见）
        add_submenu_page(
            'wordpress-toolkit',
            __('物品管理', 'wordpress-toolkit'),
            __('物品管理', 'wordpress-toolkit'),
            'read', // 使用基础阅读权限
            'wordpress-toolkit-time-capsule',
            array($this, 'time_capsule_admin_page')
        );

        // 友情链接（仅管理员可见）
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'wordpress-toolkit',
                __('友情链接', 'wordpress-toolkit'),
                __('友情链接', 'wordpress-toolkit'),
                'manage_options',
                'wordpress-toolkit-friendlinks',
                array($this, 'friendlinks_admin_page')
            );
        }

        // 文章优化（仅管理员可见）
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'wordpress-toolkit',
                __('文章优化', 'wordpress-toolkit'),
                __('文章优化', 'wordpress-toolkit'),
                'manage_options',
                'wordpress-toolkit-auto-excerpt',
                array($this, 'auto_excerpt_admin_page')
            );
        }

        // 分类优化菜单已由模块自动注册


        // ======================
        // 工具箱设置菜单 - 集中管理所有模块设置
        // ======================

        // 添加工具箱设置主菜单
        add_menu_page(
            __('工具箱设置', 'wordpress-toolkit'),
            __('工具箱设置', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-settings',
            array($this, 'toolkit_settings_main_page'),
            'dashicons-admin-settings',
            31 // 位置在工具箱主菜单之后
        );

        // 网站卡片设置
        add_submenu_page(
            'wordpress-toolkit-settings',
            __('网站卡片设置', 'wordpress-toolkit'),
            __('网站卡片', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-custom-card-settings',
            array($this, 'custom_card_settings_page')
        );

        // 年龄计算器设置
        add_submenu_page(
            'wordpress-toolkit-settings',
            __('年龄计算器设置', 'wordpress-toolkit'),
            __('年龄计算器', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-age-calculator-settings',
            array($this, 'age_calculator_settings_page')
        );

        // Cookie同意设置
        add_submenu_page(
            'wordpress-toolkit-settings',
            __('Cookie同意设置', 'wordpress-toolkit'),
            __('Cookie同意', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-cookieguard-settings',
            array($this, 'cookieguard_settings_page')
        );

        // 简洁友情链接设置
        add_submenu_page(
            'wordpress-toolkit-settings',
            __('简洁友情链接设置', 'wordpress-toolkit'),
            __('简洁友情链接', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-simple-friendlink-settings',
            array($this, 'simple_friendlink_settings_page')
        );

        // 文章优化设置
        add_submenu_page(
            'wordpress-toolkit-settings',
            __('文章优化设置', 'wordpress-toolkit'),
            __('文章优化', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-auto-excerpt-settings',
            array($this, 'auto_excerpt_settings_page')
        );
    }
    
    /**
     * 加载管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 只在插件相关页面加载统一样式和脚本
        if (strpos($hook, 'wordpress-toolkit') !== false || strpos($hook, 'options-general') !== false) {
            // 加载统一CSS变量
            wp_enqueue_style(
                'toolkit-variables',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/variables.css',
                array(),
                WORDPRESS_TOOLKIT_VERSION
            );

            // 加载通用样式
            wp_enqueue_style(
                'toolkit-common',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/common.css',
                array('toolkit-variables'),
                WORDPRESS_TOOLKIT_VERSION
            );

            // 加载SEO分析报告样式
            wp_enqueue_style(
                'toolkit-seo-report',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/seo-report.css',
                array('toolkit-variables', 'toolkit-common'),
                WORDPRESS_TOOLKIT_VERSION
            );

            // 加载核心JavaScript框架
            wp_enqueue_script(
                'toolkit-core',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/js/toolkit-core.js',
                array('jquery'),
                WORDPRESS_TOOLKIT_VERSION,
                true
            );

            // 加载迁移助手
            wp_enqueue_script(
                'toolkit-migration',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/js/migration-helper.js',
                array('jquery', 'toolkit-core'),
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

        // 加载子模块的资源（已重构，主要加载模块特定资源）
        if ($this->custom_card) $this->custom_card->admin_enqueue_scripts($hook);
        if ($this->age_calculator) $this->age_calculator->admin_enqueue_scripts($hook);
        if ($this->time_capsule) $this->time_capsule->admin_enqueue_scripts($hook);
        if ($this->cookieguard) $this->cookieguard->admin_enqueue_scripts($hook);
        if ($this->auto_excerpt) $this->auto_excerpt->admin_enqueue_scripts($hook);
        if ($this->category_optimization) $this->category_optimization->admin_enqueue_scripts($hook);
        // Simple_FriendLink_Module 不需要特殊的管理页面资源加载
    }
    
    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 加载子模块的前端资源
        if ($this->custom_card) $this->custom_card->enqueue_scripts();
        if ($this->age_calculator) $this->age_calculator->enqueue_scripts();
        if ($this->time_capsule) $this->time_capsule->enqueue_scripts();
        if ($this->cookieguard) $this->cookieguard->enqueue_scripts();
        if ($this->simple_friendlink) $this->simple_friendlink->enqueue_scripts();
        if ($this->auto_excerpt) $this->auto_excerpt->enqueue_scripts();
        if ($this->category_optimization) $this->category_optimization->enqueue_scripts();
    }
    
    /**
     * 主管理页面 - 安全版本（简化版）
     */
    public function admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 显示工具箱主页面，包含功能说明
        $this->toolbox_about_page();
    }
    
    /**
     * 网站卡片设置页面 - 放在设置菜单中
     */
    public function custom_card_settings_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 验证nonce（防止CSRF攻击）
        if (isset($_POST['action']) && !wp_verify_nonce($_POST['_wpnonce'], 'wordpress_toolkit_custom_card')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 调试日志
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WordPress Toolkit: Custom Card settings page called');
        }

        if ($this->custom_card) {
            // 调用自定义卡片模块的设置页面（只显示设置选项卡）
            $this->custom_card->settings_page();
        } else {
            echo '<div class="wrap"><h1>网站卡片设置</h1><div class="error"><p>Custom Card 模块未正确加载，请检查插件设置。</p></div></div>';
        }
    }

    /**
     * 年龄计算器设置页面 - 放在设置菜单中
     */
    public function age_calculator_settings_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 验证nonce（防止CSRF攻击）
        if (isset($_POST['action']) && !wp_verify_nonce($_POST['_wpnonce'], 'wordpress_toolkit_age_calculator')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        if ($this->age_calculator) {
            // 调用年龄计算器模块的设置页面
            $this->age_calculator->settings_page();
        } else {
            echo '<div class="wrap"><h1>年龄计算器设置</h1><div class="error"><p>Age Calculator 模块未正确加载，请检查插件设置。</p></div></div>';
        }
    }

    /**
     * 物品管理设置页面 - 放在设置菜单中
     */
    public function time_capsule_settings_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 验证nonce（防止CSRF攻击）
        if (isset($_POST['action']) && !wp_verify_nonce($_POST['_wpnonce'], 'wordpress_toolkit_time_capsule')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        if ($this->time_capsule) {
            // 调用时间胶囊模块的设置页面
            $this->time_capsule->settings_page();
        } else {
            echo '<div class="wrap"><h1>物品管理设置</h1><div class="error"><p>Time Capsule 模块未正确加载，请检查插件设置。</p></div></div>';
        }
    }

    /**
     * Cookie同意设置页面 - 放在设置菜单中
     */
    public function cookieguard_settings_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 验证nonce（防止CSRF攻击）
        if (isset($_POST['action']) && !wp_verify_nonce($_POST['_wpnonce'], 'wordpress_toolkit_cookieguard')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 处理表单提交
        if (isset($_POST['action']) && $_POST['action'] === 'save_cookieguard_settings') {
            $this->save_cookieguard_settings();
        }

        // 获取设置
        $settings = get_option('wordpress_toolkit_cookieguard_settings', array(
            'cookie_types' => array(),
            'theme' => 'light',
            'position' => 'bottom',
            'learn_more_url' => '',
            'privacy_policy_url' => '',
            'consent_expiry_days' => 365
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('Cookie同意设置', 'wordpress-toolkit'); ?></h1>

            <form method="post" action="">
                <input type="hidden" name="action" value="save_cookieguard_settings">
                <?php wp_nonce_field('wordpress_toolkit_cookieguard'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Cookie类型', 'wordpress-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cookie_types[]" value="necessary" checked disabled>
                                <?php _e('必要Cookie', 'wordpress-toolkit'); ?> (<?php _e('始终启用', 'wordpress-toolkit'); ?>)
                            </label><br>
                            <label>
                                <input type="checkbox" name="cookie_types[]" value="functional" <?php echo in_array('functional', $settings['cookie_types']) ? 'checked' : ''; ?>>
                                <?php _e('功能性Cookie', 'wordpress-toolkit'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="cookie_types[]" value="analytics" <?php echo in_array('analytics', $settings['cookie_types']) ? 'checked' : ''; ?>>
                                <?php _e('分析性Cookie', 'wordpress-toolkit'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="cookie_types[]" value="marketing" <?php echo in_array('marketing', $settings['cookie_types']) ? 'checked' : ''; ?>>
                                <?php _e('营销性Cookie', 'wordpress-toolkit'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('主题', 'wordpress-toolkit'); ?></th>
                        <td>
                            <select name="theme">
                                <option value="light" <?php selected($settings['theme'], 'light'); ?>><?php _e('浅色', 'wordpress-toolkit'); ?></option>
                                <option value="dark" <?php selected($settings['theme'], 'dark'); ?>><?php _e('深色', 'wordpress-toolkit'); ?></option>
                                <option value="auto" <?php selected($settings['theme'], 'auto'); ?>><?php _e('自动', 'wordpress-toolkit'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('位置', 'wordpress-toolkit'); ?></th>
                        <td>
                            <select name="position">
                                <option value="bottom" <?php selected($settings['position'], 'bottom'); ?>><?php _e('底部', 'wordpress-toolkit'); ?></option>
                                <option value="top" <?php selected($settings['position'], 'top'); ?>><?php _e('顶部', 'wordpress-toolkit'); ?></option>
                                <option value="center" <?php selected($settings['position'], 'center'); ?>><?php _e('居中', 'wordpress-toolkit'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('了解更多链接', 'wordpress-toolkit'); ?></th>
                        <td>
                            <input type="url" name="learn_more_url" value="<?php echo esc_url($settings['learn_more_url']); ?>" class="regular-text">
                            <p class="description"><?php _e('Cookie使用说明页面链接', 'wordpress-toolkit'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('隐私政策链接', 'wordpress-toolkit'); ?></th>
                        <td>
                            <input type="url" name="privacy_policy_url" value="<?php echo esc_url($settings['privacy_policy_url']); ?>" class="regular-text">
                            <p class="description"><?php _e('隐私政策页面链接', 'wordpress-toolkit'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('同意有效期', 'wordpress-toolkit'); ?></th>
                        <td>
                            <input type="number" name="consent_expiry_days" value="<?php echo $settings['consent_expiry_days']; ?>" min="1" max="3650" step="1">
                            <p class="description"><?php _e('用户Cookie同意记录的有效天数（默认：365天）', 'wordpress-toolkit'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('保存设置', 'wordpress-toolkit'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * 保存CookieGuard设置
     */
    private function save_cookieguard_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        $cookie_types = isset($_POST['cookie_types']) ? array_map('sanitize_text_field', $_POST['cookie_types']) : array();

        $settings = array(
            'cookie_types' => $cookie_types,
            'theme' => sanitize_text_field($_POST['theme']),
            'position' => sanitize_text_field($_POST['position']),
            'learn_more_url' => esc_url_raw($_POST['learn_more_url']),
            'privacy_policy_url' => esc_url_raw($_POST['privacy_policy_url']),
            'consent_expiry_days' => intval($_POST['consent_expiry_days'])
        );

        update_option('wordpress_toolkit_cookieguard_settings', $settings);

        // 显示成功消息
        add_settings_error('wordpress_toolkit_cookieguard_settings', 'settings_saved', __('设置已保存', 'wordpress-toolkit'), 'updated');
        set_transient('settings_errors', get_settings_errors(), 30);
    }

    /**
     * 简洁友情链接设置页面
     */
    public function simple_friendlink_settings_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 保存设置
        if (isset($_POST['save_settings'])) {
            $settings = array(
                'allow_user_submit' => isset($_POST['allow_user_submit']),
                'require_login' => isset($_POST['require_login']),
                'admin_approval' => isset($_POST['admin_approval']),
                'max_links_per_page' => intval($_POST['max_links_per_page'])
            );

            if (class_exists('Simple_FriendLink_Module')) {
                $friendlink_module = Simple_FriendLink_Module::get_instance();
                $friendlink_module->save_settings($settings);
                echo '<div class="notice notice-success is-dismissible"><p>' . __('设置保存成功！', 'wordpress-toolkit') . '</p></div>';
            }
        }

        // 获取当前设置
        if (class_exists('Simple_FriendLink_Module')) {
            $friendlink_module = Simple_FriendLink_Module::get_instance();
            $settings = $friendlink_module->get_settings();
        } else {
            $settings = array(
                'allow_user_submit' => true,
                'require_login' => true,
                'admin_approval' => false,
                'max_links_per_page' => 30
            );
        }

        // 显示设置表单
        ?>
        <div class="wrap">
            <h1><?php echo __('简洁友情链接设置', 'wordpress-toolkit'); ?></h1>

            <div class="toolkit-settings-form">
                <h2>🔗 基本设置</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('wordpress_toolkit_simple_friendlink'); ?>

                    <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('用户提交', 'wordpress-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_user_submit" value="1" <?php checked($settings['allow_user_submit']); ?>>
                                <?php _e('允许用户提交友情链接', 'wordpress-toolkit'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('需要登录', 'wordpress-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_login" value="1" <?php checked($settings['require_login']); ?>>
                                <?php _e('用户必须登录才能提交', 'wordpress-toolkit'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('管理员审核', 'wordpress-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="admin_approval" value="1" <?php checked($settings['admin_approval']); ?>>
                                <?php _e('用户提交的链接需要管理员审核', 'wordpress-toolkit'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('每页显示数量', 'wordpress-toolkit'); ?></th>
                        <td>
                            <input type="number" name="max_links_per_page" value="<?php echo $settings['max_links_per_page']; ?>" min="5" max="50" step="5">
                            <p class="description"><?php _e('友情链接页面每页显示的链接数量（默认：30）', 'wordpress-toolkit'); ?></p>
                        </td>
                    </tr>

                                    </table>

                    <div class="submit">
                        <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('保存设置', 'wordpress-toolkit'); ?>">
                    </div>
                </form>
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

        /* SEO分析报告弹框样式 */
        .seo-report-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000000;
        }

        .seo-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
        }

        .seo-modal-content {
            position: relative;
            max-width: 800px;
            max-height: 90vh;
            margin: 5vh auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .seo-modal-header {
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            color: #fff;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .seo-modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            font-weight: 600;
        }

        .seo-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #fff;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }

        .seo-modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .seo-modal-body {
            padding: 32px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .seo-modal-footer {
            padding: 20px 32px;
            border-top: 1px solid #e1e1e1;
            background: #f8f9f9;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .seo-report-section {
            margin-bottom: 32px;
        }

        .seo-report-section h3 {
            margin: 0 0 16px 0;
            font-size: 1.2em;
            font-weight: 600;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .keywords-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .keyword-tag {
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .keyword-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.4);
        }

        .recommendations-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .recommendation-item {
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .recommendation-item:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .recommendation-item.priority-high {
            border-left: 4px solid #d63638;
        }

        .recommendation-item.priority-medium {
            border-left: 4px solid #dba617;
        }

        .recommendation-item.priority-low {
            border-left: 4px solid #00a32a;
        }

        .rec-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .rec-header h4 {
            margin: 0;
            font-size: 1.1em;
            font-weight: 600;
            color: #1d2327;
            flex: 1;
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-high .priority-badge {
            background: #fef7f7;
            color: #d63638;
            border: 1px solid #d63638;
        }

        .priority-medium .priority-badge {
            background: #fcf9e8;
            color: #dba617;
            border: 1px solid #dba617;
        }

        .priority-low .priority-badge {
            background: #f0f6fc;
            color: #00a32a;
            border: 1px solid #00a32a;
        }

        .rec-description {
            color: #3c434a;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .rec-action {
            background: #f8f9f9;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #2271b1;
            color: #1d2327;
        }

        .rec-action strong {
            color: #2271b1;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .seo-modal-content {
                margin: 0;
                max-height: 100vh;
                border-radius: 0;
            }

            .seo-modal-header,
            .seo-modal-body,
            .seo-modal-footer {
                padding: 20px;
            }

            .keywords-container {
                gap: 6px;
            }

            .keyword-tag {
                font-size: 13px;
                padding: 6px 12px;
            }

            .rec-header {
                flex-direction: column;
                gap: 8px;
            }

            .priority-badge {
                align-self: flex-start;
            }
        }

        /* 滚动条样式 */
        .seo-modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .seo-modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .seo-modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .seo-modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* 完整AI分析报告样式 */
        .article-info-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .article-info-section h2 {
            margin: 0 0 15px 0;
            font-size: 1.5em;
            font-weight: 700;
        }

        .article-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .meta-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .ai-full-analysis-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .ai-full-analysis-section h3 {
            margin: 0 0 20px 0;
            color: #1d2327;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .analysis-section {
            margin-bottom: 30px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .analysis-section h4 {
            margin: 0 0 15px 0;
            color: #1d2327;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .keywords-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .keyword-chip {
            background: linear-gradient(45deg, #2271b1, #135e96);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #2271b1;
            transition: all 0.3s ease;
        }

        .keyword-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(34, 113, 177, 0.3);
        }

        .recommendations-detailed {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .detailed-recommendation {
            border: 1px solid #e1e1e1;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .detailed-recommendation:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        .detailed-recommendation.priority-high {
            border-left: 5px solid #d63638;
        }

        .detailed-recommendation.priority-medium {
            border-left: 5px solid #dba617;
        }

        .detailed-recommendation.priority-low {
            border-left: 5px solid #00a32a;
        }

        .rec-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .rec-number {
            background: #2271b1;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .rec-priority {
            font-size: 20px;
        }

        .rec-title {
            margin: 0;
            color: #1d2327;
            font-size: 1.1em;
            font-weight: 600;
            flex: 1;
        }

        .rec-description,
        .rec-action {
            padding: 15px 20px;
        }

        .rec-description {
            background: white;
            border-bottom: 1px solid #f0f0f0;
        }

        .rec-action {
            background: #f8f9fa;
        }

        .action-steps {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-top: 8px;
            line-height: 1.6;
        }

        .ai-analysis-text {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            line-height: 1.6;
            color: #3c434a;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
        }

        .ai-analysis-raw-text {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            line-height: 1.8;
            color: #1d2327;
            font-size: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #2271b1;
        }

        .basic-analysis-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .basic-recommendations {
            margin-top: 20px;
        }

        .basic-recommendations h4 {
            margin: 0 0 15px 0;
            color: #1d2327;
        }

        .technical-stats-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
        }

        .technical-stats-section h3 {
            margin: 0 0 15px 0;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .score-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .score-badge.excellent {
            background: #f0f6fc;
            color: #00a32a;
            border: 1px solid #00a32a;
        }

        .score-badge.good {
            background: #fcf9e8;
            color: #dba617;
            border: 1px solid #dba617;
        }

        .score-badge.average {
            background: #fcf9e8;
            color: #dba617;
            border: 1px solid #dba617;
        }

        .score-badge.poor {
            background: #fef7f7;
            color: #d63638;
            border: 1px solid #d63638;
        }
        </style>
        <?php
    }

    /**
     * 文章优化管理页面 - 工具箱菜单中
     */
    public function auto_excerpt_admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 验证nonce（防止CSRF攻击）
        if (isset($_POST['action']) && !wp_verify_nonce($_POST['_wpnonce'], 'wordpress_toolkit_auto_excerpt')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 显示管理页面
        if ($this->auto_excerpt) {
            ?>
            <div class="wrap">
                <?php
                error_log("WordPress Toolkit: Loading auto excerpt admin page");
                $stats = $this->auto_excerpt->get_excerpt_stats();
                error_log("WordPress Toolkit: Stats loaded - " . print_r($stats, true));
                ?>

                <div class="postbox" style="margin-top: 15px; margin-bottom: 10px;">
                    <div class="inside" style="padding: 12px 15px;">
                        <div style="display: flex; align-items: center; gap: 30px; padding: 0; flex-wrap: wrap; justify-content: space-between;">
                            <div>
                                <strong><?php _e('文章总数', 'wordpress-toolkit'); ?></strong>
                                <div style="margin-top: 5px;">
                                    <span class="dashicons dashicons-post" style="color: #0073aa;"></span>
                                    <?php echo number_format($stats['total_posts']); ?>
                                </div>
                            </div>
                            <div>
                                <strong><?php _e('有摘要文章', 'wordpress-toolkit'); ?></strong>
                                <div style="margin-top: 5px;">
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                    <?php echo number_format($stats['with_excerpt']); ?>
                                </div>
                            </div>
                            <div>
                                <strong><?php _e('无摘要文章数量', 'wordpress-toolkit'); ?></strong>
                                <div style="margin-top: 5px;">
                                    <span class="dashicons dashicons-no-alt" style="color: #d63638;"></span>
                                    <?php echo number_format($stats['without_excerpt']); ?>
                                </div>
                            </div>
                            <div>
                                <strong><?php _e('摘要覆盖率', 'wordpress-toolkit'); ?></strong>
                                <div style="margin-top: 5px; display: flex; align-items: center; gap: 10px;">
                                    <span class="dashicons dashicons-chart-bar" style="color: #0073aa;"></span>
                                    <span><?php echo $stats['coverage_rate']; ?>%</span>
                                    <?php if ($stats['ai_generated'] > 0): ?>
                                        <span class="badge-ai" style="background: #f0f6fc; color: #0073aa; padding: 2px 6px; border-radius: 3px; font-size: 12px; border: 1px solid #c3d9ea;">🤖 <?php echo sprintf(__('AI生成：%d篇', 'wordpress-toolkit'), $stats['ai_generated']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="postbox" style="margin-top: 10px;">
                    <div class="inside" style="padding: 15px;">
                        <?php
                        // 获取分页数据（在这里提前获取，以便在筛选器行显示分页）
                        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
                        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

                        error_log("WordPress Toolkit: Loading excerpt list - page: $current_page, status: $status");
                        $excerpt_list = $this->auto_excerpt->get_excerpt_list($current_page, 15, $status);
                        error_log("WordPress Toolkit: Excerpt list loaded - " . print_r($excerpt_list, true));
                        ?>

                        <!-- 筛选器、批量操作和分页放在同一行 -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                            <!-- 左侧：筛选器和批量操作 -->
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <form method="get" action="" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                                    <input type="hidden" name="page" value="wordpress-toolkit-auto-excerpt">
                                    <select name="status" id="excerpt-status-filter">
                                        <option value="all" <?php selected(isset($_GET['status']) ? $_GET['status'] : 'all', 'all'); ?>><?php _e('全部文章', 'wordpress-toolkit'); ?></option>
                                        <option value="with_excerpt" <?php selected(isset($_GET['status']) ? $_GET['status'] : 'all', 'with_excerpt'); ?>><?php _e('有摘要文章', 'wordpress-toolkit'); ?></option>
                                        <option value="without_excerpt" <?php selected(isset($_GET['status']) ? $_GET['status'] : 'all', 'without_excerpt'); ?>><?php _e('无摘要文章', 'wordpress-toolkit'); ?></option>
                                    </select>
                                    <button type="submit" class="button"><?php _e('筛选', 'wordpress-toolkit'); ?></button>

                                    <span style="margin: 0 5px; color: #666;">|</span>

                                    <button type="button" id="batch-generate-excerpts" class="button button-primary">
                                        <?php _e('为无摘要文章生成摘要', 'wordpress-toolkit'); ?>
                                    </button>
                                    <button type="button" id="batch-generate-tags" class="button" style="margin-left: 10px; background: #9333ea; border-color: #7c3aed; color: white;">
                                        <?php _e('批量生成标签', 'wordpress-toolkit'); ?>
                                    </button>
                                    <span class="spinner" id="batch-generate-spinner" style="display: none; margin-left: 5px;"></span>
                                    <span class="spinner" id="batch-generate-tags-spinner" style="display: none; margin-left: 5px;"></span>
                                </form>
                            </div>

                            <!-- 右侧：分页 -->
                            <?php if (!empty($excerpt_list) && isset($excerpt_list['pages']) && $excerpt_list['pages'] > 1): ?>
                            <div class="tablenav-pages" style="margin: 0;">
                                <?php
                                $current_url = admin_url('admin.php?page=wordpress-toolkit-auto-excerpt');
                                if (isset($_GET['status'])) {
                                    $current_url .= '&status=' . urlencode($_GET['status']);
                                }
                                ?>
                                <span class="displaying-num">
                                    <?php printf(__('共 %d 个项目', 'wordpress-toolkit'), $excerpt_list['total']); ?>
                                </span>
                                <?php
                                // 使用WordPress标准的paginate_links函数，与网站卡片保持一致
                                echo paginate_links(array(
                                    'base' => $current_url . '&paged=%#%',
                                    'format' => '',
                                    'prev_text' => __('&laquo; 上一页'),
                                    'next_text' => __('下一页 &raquo;'),
                                    'total' => $excerpt_list['pages'],
                                    'current' => $current_page
                                ));
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- 批量操作进度 -->
                        <div id="batch-generate-progress" style="display: none; margin: 15px 0;">
                            <div class="progress-container">
                                <h4 id="progress-title">处理中...</h4>
                                <div class="progress-bar-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progress-fill"></div>
                                    </div>
                                    <span class="progress-text" id="progress-text">0%</span>
                                </div>
                                <div class="progress-details" id="progress-details">
                                    <span>当前处理：<span id="current-post">准备中...</span></span>
                                    <span>已处理：<span id="processed-count">0</span> / <span id="total-count">0</span></span>
                                    <span>成功：<span id="success-count">0</span></span>
                                    <span>失败：<span id="error-count">0</span></span>
                                </div>
                            </div>
                        </div>

                        <!-- 批量操作结果 -->
                        <div id="batch-generate-result" style="display: none; margin: 15px 0;"></div>

                        <!-- 文章列表 -->
                        <?php
                        // 添加调试信息和错误处理
                        if (empty($excerpt_list) || !isset($excerpt_list['posts'])) {
                            echo '<div class="notice notice-warning"><p>摘要列表数据加载失败，请检查错误日志。</p></div>';
                            error_log("WordPress Toolkit: Excerpt list data is invalid");
                        } elseif (empty($excerpt_list['posts'])) {
                            // 显示空状态，参考网站卡片样式
                            ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th scope="col" width="35%"><?php _e('标题', 'wordpress-toolkit'); ?></th>
                                        <th scope="col" width="10%"><?php _e('摘要状态', 'wordpress-toolkit'); ?></th>
                                        <th scope="col" width="10%"><?php _e('摘要长度', 'wordpress-toolkit'); ?></th>
                                        <th scope="col" width="10%"><?php _e('内容长度', 'wordpress-toolkit'); ?></th>
                                        <th scope="col" width="15%"><?php _e('发布日期', 'wordpress-toolkit'); ?></th>
                                        <th scope="col" width="20%"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px;">
                                            <?php
                                            $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
                                            if ($current_status !== 'all'):
                                            ?>
                                            <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                                <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                                没有找到匹配的<?php echo $current_status === 'with_excerpt' ? '有摘要' : '无摘要'; ?>文章
                                            </div>
                                            <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-auto-excerpt'); ?>" class="button button-primary">
                                                清除筛选条件
                                            </a>
                                            <?php else: ?>
                                            <div style="font-size: 16px; color: #666; margin-bottom: 20px;">
                                                <span class="dashicons dashicons-edit-page" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></span>
                                                暂无文章数据
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <?php
                            error_log("WordPress Toolkit: No posts found matching criteria");
                        } else {
                            error_log("WordPress Toolkit: Displaying " . count($excerpt_list['posts']) . " posts");
                        ?>
            
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" width="35%"><?php _e('标题', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="10%"><?php _e('摘要状态', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="10%"><?php _e('摘要长度', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="10%"><?php _e('内容长度', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="15%"><?php _e('发布日期', 'wordpress-toolkit'); ?></th>
                                    <th scope="col" width="20%"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($excerpt_list['posts'] as $post): ?>
                                <tr>
                                    <td>
                                        <strong><a href="<?php echo esc_url($post['edit_url']); ?>" target="_blank"><?php echo esc_html($post['title']); ?></a></strong>
                                        <?php if ($post['status'] !== 'publish'): ?>
                                        <span class="status-draft">草稿</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($post['has_excerpt']): ?>
                                            <span class="status-active"><?php _e('有摘要', 'wordpress-toolkit'); ?></span>
                                            <?php if (isset($post['is_ai_generated']) && $post['is_ai_generated']): ?>
                                            <span class="ai-badge" style="margin-left: 5px; background: #e6f3ff; color: #0073aa; padding: 2px 6px; border-radius: 3px; font-size: 11px; border: 1px solid #b3d9ff; font-weight: 500;">🤖 AI</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-inactive"><?php _e('无摘要', 'wordpress-toolkit'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $post['excerpt_length']; ?> <?php _e('字符', 'wordpress-toolkit'); ?></td>
                                    <td><?php echo $post['content_length']; ?> <?php _e('字符', 'wordpress-toolkit'); ?></td>
                                    <td><?php echo $post['date']; ?></td>
                                    <td>
                                        <div class="action-buttons-container">
                                            <a href="<?php echo esc_url($post['edit_url']); ?>" class="button button-small" target="_blank" style="background: #646970; color: white; border-color: #646970; margin: 0; text-decoration: none;"><?php _e('编辑', 'wordpress-toolkit'); ?></a>
                                            <a href="<?php echo esc_url($post['view_url']); ?>" class="button button-small" target="_blank" style="background: #646970; color: white; border-color: #646970; margin: 0; text-decoration: none;"><?php _e('查看', 'wordpress-toolkit'); ?></a>
                                            <?php if (!$post['has_excerpt']): ?>
                                            <button type="button" class="button button-small generate-excerpt-single" data-post-id="<?php echo $post['ID']; ?>" title="为这篇生成智能摘要" style="background: #46b450; color: white; border-color: #46b450; margin: 0;">
                                                生成摘要
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="button button-small generate-tags-single" data-post-id="<?php echo $post['ID']; ?>" data-title="<?php echo esc_attr($post['title']); ?>" title="AI生成文章标签" style="background: #ff6900; color: white; border-color: #ff6900; margin: 0;">
                                                生成标签
                                            </button>
                                            <button type="button" class="button button-small seo-analyze-single" data-post-id="<?php echo $post['ID']; ?>" title="AI SEO分析" style="background: #0073aa; color: white; border-color: #0073aa; margin: 0;">
                                                SEO分析
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                            <?php } // End of else from posts check ?>
                    </div>
                </div>
            </div>

            <style>
            /* 简化的状态样式 */
            .status-active {
                color: #00a32a;
                font-weight: bold;
            }
            .status-inactive {
                color: #d63638;
                font-weight: bold;
            }
            .status-draft {
                display: inline-block;
                background: #f0f0f1;
                color: #50575e;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
                margin-left: 8px;
            }
            .badge-ai {
                display: inline-block;
                background: linear-gradient(135deg, #0073aa, #005a87);
                color: #fff;
                padding: 4px 12px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 500;
            }

            /* 使用WordPress标准分页样式，保持与后台其他功能一致 */

            /* 统一所有操作按钮宽度 */
            .tablenav td .button.button-small {
                min-width: 60px !important;
                max-width: 70px !important;
                white-space: nowrap !important;
                text-align: center !important;
                padding: 0 8px !important;
                font-size: 13px !important;
                height: 30px !important;
                line-height: 28px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                display: inline-block !important;
                vertical-align: middle !important;
            }
            .generate-excerpt-single {
                min-width: 95px !important;
                max-width: 105px !important;
                white-space: nowrap !important;
                text-align: center !important;
                padding: 0 10px !important;
                font-size: 13px !important;
                height: 26px !important;
                line-height: 28px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                display: inline-flex !important;
                vertical-align: middle !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 4px !important;
                border-radius: 3px !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
                transition: all 0.2s ease !important;
            }
            .generate-excerpt-single.button-primary {
                background: #0073aa !important;
                border-color: #0073aa !important;
                color: #fff !important;
            }
            .generate-excerpt-single.button-primary:hover {
                background: #005a87 !important;
                border-color: #005a87 !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.15) !important;
            }
            .generate-excerpt-single.button-secondary {
                background: #f6f7f7 !important;
                border-color: #ddd !important;
                color: #50575e !important;
            }
            .generate-excerpt-single.button-secondary:hover {
                background: #e9e9e9 !important;
                border-color: #bbb !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.15) !important;
            }
            .generate-excerpt-single .dashicons {
                font-size: 14px !important;
                height: 14px !important;
                width: 14px !important;
                vertical-align: middle !important;
                margin: 0 !important;
                display: inline-block !important;
                flex-shrink: 0 !important;
            }

            /* 分页样式优化 - 与网站卡片保持一致 */
            .tablenav-pages {
                margin-top: 0;
                background: #f8f9f9;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #e5e5e5;
                font-size: 13px;
            }

            .tablenav-pages .displaying-num {
                margin-right: 10px;
                color: #50575e;
            }

            .tablenav-pages .page-numbers {
                display: inline-block;
                padding: 4px 8px;
                margin: 0 2px;
                border: 1px solid #ccc;
                text-decoration: none;
                border-radius: 3px;
            }

            .tablenav-pages .page-numbers.current {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }

            .tablenav-pages .page-numbers:hover {
                background: #f1f1f1;
            }

            .tablenav-pages .page-numbers.current:hover {
                background: #0073aa;
            }

            /* 标签生成按钮样式 */
            .generate-tags-single {
                min-width: 105px !important;
                max-width: 115px !important;
                background: #9333ea !important;
                border-color: #7c3aed !important;
                color: #fff !important;
                font-weight: 500 !important;
            }
            .generate-tags-single:hover {
                background: #7c3aed !important;
                border-color: #6d28d9 !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 2px 4px rgba(147, 51, 234, 0.3) !important;
            }

            /* 标签对话框样式 */
            #tag-dialog {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .tag-dialog-content {
                background: #fff;
                border-radius: 12px;
                padding: 25px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            }

            .tag-dialog-content h3 {
                margin-top: 0;
                margin-bottom: 20px;
                color: #1a1a1a;
                font-size: 20px;
                text-align: center;
                border-bottom: 2px solid #e5e5e5;
                padding-bottom: 10px;
            }

            .tag-section {
                margin-bottom: 20px;
            }

            .tag-section h4 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
                font-weight: 600;
            }

            .tag-container {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                min-height: 40px;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 6px;
                align-items: center;
            }

            .tag {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                border: 2px solid transparent;
                user-select: none;
            }

            .existing-tag {
                background: #e3f2fd;
                color: #1976d2;
                border-color: #90caf9;
                cursor: default;
            }

            .ai-tag {
                background: #f3e5f5;
                color: #7b1fa2;
                border-color: #ce93d8;
            }

            .ai-tag:hover {
                background: #e1bee7;
                border-color: #ba68c8;
                transform: translateY(-1px);
            }

            .ai-tag.selected {
                background: #4caf50;
                color: white;
                border-color: #45a049;
                box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
            }

            .ai-tag.selected:hover {
                background: #45a049;
            }

            .no-tags {
                color: #999;
                font-style: italic;
                margin: 0;
            }

            .tag-actions {
                margin: 20px 0;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 6px;
                border-left: 4px solid #0073aa;
            }

            .tag-actions h4 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
                font-weight: 600;
            }

            .tag-actions label {
                display: inline-block;
                margin: 8px 15px 8px 0;
                cursor: pointer;
                font-weight: 500;
                white-space: nowrap;
            }

            .tag-actions input[type="radio"] {
                margin-right: 8px;
            }

            .tag-dialog-buttons {
                text-align: right;
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #e5e5e5;
            }

            .tag-dialog-buttons .button {
                margin-left: 10px;
                font-weight: 500;
            }

            .tag-dialog-buttons .button-primary {
                background: #0073aa;
                border-color: #0073aa;
            }

            .tag-dialog-buttons .button-primary:hover {
                background: #005a87;
                border-color: #005a87;
            }

            /* 旋转动画 */
            .rotating {
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }

            /* 批量操作进度条样式 */
            .progress-container {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .progress-container h4 {
                margin: 0 0 15px 0;
                color: #333;
                font-size: 16px;
                font-weight: 600;
                text-align: center;
            }

            .progress-bar-container {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
            }

            .progress-bar {
                flex: 1;
                height: 24px;
                background: #f1f1f1;
                border-radius: 12px;
                overflow: hidden;
                position: relative;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #0073aa 0%, #005a87 100%);
                border-radius: 12px;
                width: 0%;
                transition: width 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .progress-fill::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.3),
                    transparent
                );
                animation: shimmer 2s infinite;
            }

            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .progress-text {
                font-weight: 600;
                color: #0073aa;
                font-size: 14px;
                min-width: 50px;
                text-align: center;
            }

            .progress-details {
                display: flex;
                justify-content: space-around;
                flex-wrap: wrap;
                gap: 10px;
                font-size: 13px;
                color: #555;
                background: #f8f9fa;
                padding: 12px;
                border-radius: 6px;
                border-left: 4px solid #0073aa;
            }

            .progress-details span {
                display: inline-block;
                min-width: 100px;
            }

            .progress-details span span {
                font-weight: 600;
                color: #0073aa;
            }
            </style>

            <script>
            jQuery(document).ready(function($) {
                // 统计信息
                var stats = {
                    total_posts: <?php echo $stats['total_posts']; ?>,
                    without_excerpt: <?php echo $stats['without_excerpt']; ?>
                };
                // 进度更新函数
                function updateProgress(title, percentage, processed, success, errors, currentPost, totalCount) {
                    // 更新标题和进度条
                    if (percentage === 100) {
                        $('#progress-title').text(title + ' - ' + currentPost);
                    } else {
                        $('#progress-title').text(title + ' - 处理中...');
                    }

                    // 确保数据有效性
                    processed = Math.max(0, processed || 0);
                    success = Math.max(0, success || 0);
                    errors = Math.max(0, errors || 0);

                    $('#progress-fill').css('width', percentage + '%');
                    $('#progress-text').text(percentage + '%');
                    $('#current-post').text(currentPost);
                    $('#processed-count').text(processed);
                    $('#success-count').text(success);
                    $('#error-count').text(errors);

                    // 更新总数显示
                    if (totalCount !== undefined && totalCount !== null) {
                        $('#total-count').text(totalCount);
                    } else {
                        // 智能更新总数显示（兼容旧代码）
                        var $totalCount = $('#total-count');
                        if (percentage === 100 && processed > 0) {
                            // 完成时，总数等于已处理数
                            $totalCount.text(processed);
                        } else if (processed > 0 && percentage < 100) {
                            // 处理中时，估算总数
                            if ($totalCount.text() === '0' || $totalCount.text() === '?') {
                                // 首次估算：假设当前进度是准确的，反推总数
                                var estimated = Math.round(processed * 100 / percentage);
                                $totalCount.text(estimated);
                            }
                        }
                    }

                    // 完成时自动隐藏进度条
                    if (percentage === 100) {
                        setTimeout(function() {
                            $('#batch-generate-progress').fadeOut(500);
                        }, 3000);
                    }
                }

                // 显示加载状态的函数
                function showProcessingStatus(title, totalPosts, operationType) {
                    var messageCount = 0;
                    var cycleCount = 0;

                    // 根据操作类型选择不同的状态消息
                    var statusMessages, processingMessages;

                    if (operationType === 'tags') {
                        // 标签生成的状态消息
                        statusMessages = [
                            '正在准备标签生成环境...',
                            '正在加载AI标签模型...',
                            '正在分析文章标题和内容...',
                            '正在获取文章列表...',
                            '正在初始化标签处理器...'
                        ];

                        processingMessages = [
                            '正在分析文章内容...',
                            '正在生成AI标签...',
                            '正在匹配现有标签...',
                            '正在保存标签结果...',
                            '正在验证标签准确性...'
                        ];
                    } else {
                        // 摘要生成的状态消息（默认）
                        statusMessages = [
                            '正在准备处理环境...',
                            '正在加载AI模型...',
                            '正在分析文章数据...',
                            '正在获取文章列表...',
                            '正在初始化处理器...'
                        ];

                        processingMessages = [
                            '正在分析文章内容...',
                            '正在生成智能摘要...',
                            '正在优化摘要长度...',
                            '正在保存处理结果...',
                            '正在验证摘要质量...'
                        ];
                    }

                    var interval = setInterval(function() {
                        if (messageCount < statusMessages.length) {
                            // 在准备阶段，显示渐进的准备进度
                            var progress = Math.round((messageCount + 1) * 8); // 8%, 16%, 24%, 32%, 40%
                            var simulatedProcessed = Math.round((progress / 100) * Math.min(totalPosts, 10)); // 最多模拟处理10篇
                            var simulatedSuccess = Math.round(simulatedProcessed * 0.9);

                            updateProgress(title, progress, simulatedProcessed, simulatedSuccess,
                                         simulatedProcessed - simulatedSuccess, statusMessages[messageCount], totalPosts);
                            messageCount++;
                        } else {
                            // 循环显示处理状态，模拟真实的处理进度
                            cycleCount++;

                            // For large numbers of articles，使用更慢的进度增长
                            var maxProgress = 95;
                            var progressIncrement = totalPosts > 1000 ? 0.5 : (totalPosts > 500 ? 1 : 2);
                            var baseProgress = 45;
                            var additionalProgress = Math.min(cycleCount * progressIncrement, maxProgress - baseProgress);
                            var progress = Math.min(baseProgress + additionalProgress, maxProgress);

                            var simulatedProcessed = Math.round((progress / 100) * totalPosts);
                            var simulatedSuccess = Math.round(simulatedProcessed * 0.85 + Math.random() * 10);
                            var simulatedErrors = simulatedProcessed - simulatedSuccess;

                            // 确保不超过总数
                            simulatedProcessed = Math.min(simulatedProcessed, totalPosts);
                            simulatedSuccess = Math.min(simulatedSuccess, simulatedProcessed);
                            simulatedErrors = Math.min(simulatedErrors, simulatedProcessed - simulatedSuccess);

                            var messageIndex = (cycleCount - 1) % processingMessages.length;
                            var currentMessage = processingMessages[messageIndex] + ' (' + simulatedProcessed + '/' + totalPosts + ')';

                            // For large numbers of articles，添加时间提示和进度检查点
                            if (totalPosts > 1000) {
                                if (cycleCount % 8 === 0) {
                                    var remainingMinutes = Math.round((100 - progress) / 10 * 1.5); // 估算剩余时间
                                    currentMessage += ' - 预计还需' + remainingMinutes + '分钟';
                                }

                                // 在特定进度点显示里程碑
                                if (progress >= 25 && progress < 27 && cycleCount % 50 === 0) {
                                    currentMessage += ' ✅ 已完成25%';
                                } else if (progress >= 50 && progress < 52 && cycleCount % 50 === 0) {
                                    currentMessage += ' 🎯 已完成50%';
                                } else if (progress >= 75 && progress < 77 && cycleCount % 50 === 0) {
                                    currentMessage += ' 🔥 已完成75%';
                                }
                            }

                            updateProgress(title, progress, simulatedProcessed, simulatedSuccess,
                                         simulatedErrors, currentMessage, totalPosts);
                        }
                    }, totalPosts > 1000 ? 3000 : 1500); // 大量文章时每3秒更新一次，减少频率

                    return interval;
                }

                // 批量生成摘要
                $('#batch-generate-excerpts').on('click', function(e) {
                    e.preventDefault();

                    var $button = $(this);
                    var $spinner = $('#batch-generate-spinner');
                    var $progress = $('#batch-generate-progress');
                    var $result = $('#batch-generate-result');

                    var estimatedTime = '30秒-2分钟';
                    var showBatchOption = false;

                    if (stats.without_excerpt > 2000) {
                        estimatedTime = '15-30分钟';
                        showBatchOption = true;
                    } else if (stats.without_excerpt > 1000) {
                        estimatedTime = '8-15分钟';
                        showBatchOption = true;
                    } else if (stats.without_excerpt > 500) {
                        estimatedTime = '5-10分钟';
                    } else if (stats.without_excerpt > 100) {
                        estimatedTime = '2-5分钟';
                    }

                    var confirmMessage = '确定要为所有无摘要文章批量生成摘要吗？\n\n' +
                        '• 需要处理的文章数量：' + stats.without_excerpt + ' 篇\n' +
                        '• 预计处理时间：' + estimatedTime + '\n' +
                        '• Do not close page during processing\n' +
                        '• Large number of articles may take longer to process';

                    if (showBatchOption) {
                        confirmMessage += '\n\n💡 **建议：对于' + stats.without_excerpt + '篇文章**\n' +
                            '考虑分批处理以获得更好的稳定性：\n' +
                            '• 分3-5批处理，每批300-500篇\n' +
                            '• 每批处理间隔2-3分钟\n' +
                            '• 可以降低服务器压力和超时风险\n\n' +
                            '点击"确定"继续处理全部文章，\n点击"取消"可以考虑分批处理。';
                    } else {
                        confirmMessage += '\n\n点击"确定"开始处理，或"取消"退出。';
                    }

                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    // 显示进度条
                    $progress.show();
                    $result.hide();
                    $button.prop('disabled', true);

                    // 初始化进度显示
                    var initMessage = 'Processing ' + stats.without_excerpt + ' articles without excerpts...';
                    if (stats.without_excerpt > 1000) {
                        initMessage += '\nWarning: Large number of articles, please be patient';
                    }
                    updateProgress('生成摘要', 0, 0, 0, 0, initMessage, stats.without_excerpt);

                    // 显示处理状态
                    var statusInterval = showProcessingStatus('生成摘要', stats.without_excerpt, 'excerpts');

                    // 发送实际的批量生成请求
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        timeout: 600000, // 10分钟超时时间（600秒）
                        data: {
                            action: 'batch_generate_excerpts',
                            nonce: '<?php echo wp_create_nonce('batch_generate_excerpts_nonce'); ?>'
                        },
                        beforeSend: function() {
                            updateProgress('生成摘要', 10, 0, 0, 0, '正在发送请求到服务器...', stats.without_excerpt);
                        },
                        success: function(response) {
                            // 立即停止状态消息显示
                            clearInterval(statusInterval);

                            if (response.success) {
                                var data = response.data;
                                // 确保显示真实的处理结果
                                var actualProcessed = data.success_count + data.error_count;
                                updateProgress('生成摘要', 100, actualProcessed, data.success_count, data.error_count, '处理完成', stats.without_excerpt);

                                var message = '<div class="notice notice-success is-dismissible"><p>' +
                                    '<strong>批量生成摘要完成！</strong><br>' +
                                    '✅ 成功处理：' + data.success_count + ' 篇文章<br>' +
                                    (data.error_count > 0 ? '❌ 处理失败：' + data.error_count + ' 篇文章<br>' : '') +
                                    '📊 总计处理：' + (data.success_count + data.error_count) + ' 篇文章';

                                if (data.error_count > 0) {
                                    message += '<br><small>详细信息请查看错误日志</small>';
                                }

                                message += '</p></div>';
                                $result.html(message).show();

                                // 5秒后隐藏进度条
                                setTimeout(function() {
                                    $progress.hide();
                                }, 5000);

                            } else {
                                updateProgress('生成摘要', 100, 0, 0, 0, '处理失败：' + response.data.message, stats.without_excerpt);
                                $result.html('<div class="notice notice-error"><p><strong>摘要生成失败：</strong><br>' + response.data.message + '</p></div>').show();
                                setTimeout(function() {
                                    $progress.hide();
                                }, 5000);
                            }

                            $button.prop('disabled', false);
                        },
                        error: function(xhr, status, error) {
                            // 停止状态消息显示
                            clearInterval(statusInterval);

                            var errorMessage = '';
                            if (status === 'timeout') {
                                var partialMessage = '\n\n⚠️ **处理可能仍在继续**\n\n' +
                                    'For large numbers of articles（' + stats.without_excerpt + ' 篇）的处理：\n' +
                                    '• 服务器可能仍在后台继续处理\n' +
                                    '• 建议等待5-10分钟后刷新页面查看结果\n' +
                                    '• 如果仍有大量文章未处理，可以再次运行\n' +
                                    '• 考虑分批次处理（每次处理200-300篇）';

                                errorMessage = '请求超时：处理时间过长，服务器响应超时。' + partialMessage;
                                updateProgress('生成摘要', 100, 0, 0, 0, '请求超时，但处理可能仍在继续', stats.without_excerpt);
                            } else if (status === 'abort') {
                                errorMessage = '请求被取消';
                                updateProgress('生成摘要', 100, 0, 0, 0, '请求被取消', stats.without_excerpt);
                            } else if (xhr.status === 0) {
                                errorMessage = '网络连接失败：无法连接到服务器，请检查网络连接';
                                updateProgress('生成摘要', 100, 0, 0, 0, '网络连接失败', stats.without_excerpt);
                            } else if (xhr.status === 500) {
                                errorMessage = '服务器内部错误：服务器处理请求时发生错误 (HTTP 500)';
                                updateProgress('生成摘要', 100, 0, 0, 0, '服务器错误', stats.without_excerpt);
                            } else if (xhr.status === 503) {
                                errorMessage = '服务不可用：服务器暂时无法处理请求 (HTTP 503)';
                                updateProgress('生成摘要', 100, 0, 0, 0, '服务不可用', stats.without_excerpt);
                            } else if (xhr.status === 504) {
                                errorMessage = '网关超时：服务器处理时间过长 (HTTP 504)';
                                updateProgress('生成摘要', 100, 0, 0, 0, '网关超时', stats.without_excerpt);
                            } else {
                                errorMessage = '网络错误：' + (error || '未知错误') + ' (HTTP ' + xhr.status + ')';
                                updateProgress('生成摘要', 100, 0, 0, 0, '网络错误', stats.without_excerpt);
                            }

                            $result.html('<div class="notice notice-error"><p><strong>处理失败：</strong><br>' + errorMessage + '</p>' +
                                '<p><strong>建议：</strong></p>' +
                                '<ul>' +
                                '<li>检查网络连接是否正常</li>' +
                                '<li>刷新页面后重试</li>' +
                                '<li>如果是大量文章处理，recommend processing in batches</li>' +
                                '<li>如果问题持续，请联系服务器管理员</li>' +
                                '</ul></div>').show();

                            setTimeout(function() {
                                $progress.hide();
                            }, 8000); // 延长显示时间到8秒
                            $button.prop('disabled', false);
                        }
                    });
                });

                // 单个文章生成摘要
                $('.generate-excerpt-single').on('click', function(e) {
                    e.preventDefault();

                    var $button = $(this);
                    var postId = $button.data('post-id');
                    var originalText = $button.html();

                    // 显示加载状态
                    $button.prop('disabled', true).html('<span class="dashicons dashicons-spinner"></span><span>生成中...</span>');

                    // 发送AJAX请求
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'generate_single_excerpt',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('generate_single_excerpt_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                var message = '<div class="notice notice-success is-dismissible"><p>' +
                                    '摘要生成成功！<br>' +
                                    '文章：' + data.post_title + '<br>' +
                                    '摘要长度：' + data.excerpt_length + ' 字符' +
                                    '</p></div>';

                                // 显示成功消息
                                $('#batch-generate-result').html(message).show();

                                // 更新按钮状态
                                $button.removeClass('button-primary').addClass('button-secondary')
                                       .html('<span class="dashicons dashicons-yes"></span><span>已生成</span>')
                                       .prop('disabled', true);

                                // 更新表格中的状态显示
                                var $row = $button.closest('tr');
                                var statusHtml = '<span class="status-active">有摘要</span>';
                                if (data.ai_generated) {
                                    statusHtml += '<span class="ai-badge" style="margin-left: 5px; background: #e6f3ff; color: #0073aa; padding: 2px 6px; border-radius: 3px; font-size: 11px; border: 1px solid #b3d9ff; font-weight: 500;">🤖 AI</span>';
                                }
                                $row.find('td:nth-child(2)').html(statusHtml);
                                $row.find('td:nth-child(3)').text(data.excerpt_length + ' 字符');

                            } else {
                                // 显示错误消息
                                $('#batch-generate-result').html('<div class="notice notice-error"><p>摘要生成失败：' + response.data.message + '</p></div>').show();
                                $button.html(originalText).prop('disabled', false);
                            }
                        },
                        error: function() {
                            $('#batch-generate-result').html('<div class="notice notice-error"><p>网络错误，请重试</p></div>').show();
                            $button.html(originalText).prop('disabled', false);
                        }
                    });
                });

                // AI生成标签功能
                $('.generate-tags-single').on('click', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var postId = $button.data('post-id');
                    var postTitle = $button.data('title');

                    console.log('Generate tags clicked - Post ID:', postId, 'Title:', postTitle);

                    if (!postId) {
                        alert('文章ID无效');
                        return;
                    }

                    // 显示加载状态
                    var originalText = $button.html();
                    $button.html('<span class="dashicons dashicons-update rotating"></span> 生成中...').prop('disabled', true);

                    // 生成标签
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'generate_ai_tags',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('generate_tags_nonce'); ?>'
                        },
                        beforeSend: function(xhr) {
                            console.log('Sending AJAX request for tags...');
                        },
                        success: function(response) {
                            console.log('AJAX response:', response);
                            $button.html(originalText).prop('disabled', false);

                            if (response.success) {
                                showTagDialog(postId, postTitle, response.data);
                            } else {
                                alert('标签生成失败：' + response.data.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('AJAX error:', status, error);
                            console.log('XHR response:', xhr.responseText);
                            $button.html(originalText).prop('disabled', false);
                            alert('网络错误，请重试');
                        }
                    });
                });

                // 显示标签选择对话框
                function showTagDialog(postId, postTitle, tagData) {
                    var existingTags = tagData.existing_tags || [];
                    var aiTags = tagData.ai_tags || [];
                    var suggestedAction = tagData.suggested_action || 'replace';

                    // 创建对话框内容
                    var dialogHtml = '<div id="tag-dialog" style="display: none;">' +
                        '<div class="tag-dialog-content">' +
                        '<h3>🏷️ AI标签生成 - ' + postTitle + '</h3>' +

                        '<div class="tag-section">' +
                        '<h4>📌 原有标签：</h4>' +
                        '<div class="tag-container" id="existing-tags">';

                    if (existingTags.length > 0) {
                        existingTags.forEach(function(tag) {
                            dialogHtml += '<span class="tag existing-tag">' + tag + '</span>';
                        });
                    } else {
                        dialogHtml += '<span class="no-tags">暂无标签</span>';
                    }

                    dialogHtml += '</div></div>' +

                        '<div class="tag-section">' +
                        '<h4>🤖 AI生成标签：</h4>' +
                        '<div class="tag-container" id="ai-tags">';

                    if (aiTags.length > 0) {
                        aiTags.forEach(function(tag) {
                            dialogHtml += '<span class="tag ai-tag" data-tag="' + tag + '">' + tag + '</span>';
                        });
                    } else {
                        dialogHtml += '<span class="no-tags">AI未生成标签</span>';
                    }

                    dialogHtml += '</div></div>' +

                        '<div class="tag-actions">' +
                        '<h4>选择操作：</h4>' +
                        '<label><input type="radio" name="tag_action" value="replace" ' + (suggestedAction === 'replace' ? 'checked' : '') + '> 替换所有标签</label>' +
                        '<label><input type="radio" name="tag_action" value="add" ' + (suggestedAction === 'add' ? 'checked' : '') + '> 添加到现有标签</label>' +
                        '<label><input type="radio" name="tag_action" value="merge"> 合并去重</label>' +
                        '</div>' +

                        '<div class="tag-dialog-buttons">' +
                        '<button type="button" class="button button-secondary" onclick="closeTagDialog()">取消</button>' +
                        '<button type="button" class="button button-primary" onclick="applyTags(' + postId + ')">应用标签</button>' +
                        '</div>' +
                        '</div></div>';

                    // 添加到页面
                    $('body').append(dialogHtml);

                    // 显示对话框
                    $('#tag-dialog').fadeIn(200);

                    // AI标签点击选择/取消
                    $('.ai-tag').on('click', function() {
                        $(this).toggleClass('selected');
                    });
                }

                // 关闭对话框
                window.closeTagDialog = function() {
                    $('#tag-dialog').fadeOut(200, function() {
                        $(this).remove();
                    });
                };

                // 应用标签
                window.applyTags = function(postId) {
                    var selectedTags = $('.ai-tag.selected').map(function() {
                        return $(this).data('tag');
                    }).get();

                    if (selectedTags.length === 0) {
                        alert('请选择要应用的标签');
                        return;
                    }

                    var actionType = $('input[name="tag_action"]:checked').val();

                    // 显示加载状态
                    $('.tag-dialog-buttons .button-primary').html('<span class="dashicons dashicons-update rotating"></span> 应用中...').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'apply_ai_tags',
                            post_id: postId,
                            new_tags: selectedTags,
                            action_type: actionType,
                            nonce: '<?php echo wp_create_nonce('apply_tags_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('标签应用成功！');
                                closeTagDialog();
                                // 刷新页面以显示更新的标签信息
                                location.reload();
                            } else {
                                alert('标签应用失败：' + response.data.message);
                                $('.tag-dialog-buttons .button-primary').html('应用标签').prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('网络错误，请重试');
                            $('.tag-dialog-buttons .button-primary').html('应用标签').prop('disabled', false);
                        }
                    });
                };

                // 批量生成标签
                $('#batch-generate-tags').on('click', function(e) {
                    e.preventDefault();

                    var $button = $(this);
                    var $spinner = $('#batch-generate-tags-spinner');
                    var $progress = $('#batch-generate-progress');
                    var $result = $('#batch-generate-result');

                    var estimatedTime = '1-3分钟';
                    var showBatchOption = false;

                    if (stats.total_posts > 2000) {
                        estimatedTime = '20-40分钟';
                        showBatchOption = true;
                    } else if (stats.total_posts > 1000) {
                        estimatedTime = '10-20分钟';
                        showBatchOption = true;
                    } else if (stats.total_posts > 500) {
                        estimatedTime = '6-12分钟';
                    } else if (stats.total_posts > 100) {
                        estimatedTime = '3-8分钟';
                    }

                    var confirmMessage = '确定要为所有文章批量生成标签吗？\n\n' +
                        '• 需要处理的文章数量：' + stats.total_posts + ' 篇\n' +
                        '• 预计处理时间：' + estimatedTime + '\n' +
                        '• 将为每篇文章生成AI标签并与现有标签合并\n' +
                        '• Do not close page during processing\n' +
                        '• Large number of articles may take longer to process';

                    if (showBatchOption) {
                        confirmMessage += '\n\n💡 **建议：对于' + stats.total_posts + '篇文章**\n' +
                            '标签生成更耗时，强烈建议分批处理：\n' +
                            '• 分4-6批处理，每批200-400篇\n' +
                            '• 每批处理间隔3-5分钟\n' +
                            '• 可以确保AI标签质量和处理稳定性\n\n' +
                            '点击"确定"继续处理全部文章，\n点击"取消"可以考虑分批处理。';
                    } else {
                        confirmMessage += '\n\n点击"确定"开始处理，或"取消"退出。';
                    }

                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    // 显示进度条
                    $progress.show();
                    $result.hide();
                    $button.prop('disabled', true);

                    // 初始化进度显示
                    var initMessage = 'Processing ' + stats.total_posts + ' articles for tag generation...';
                    if (stats.total_posts > 1000) {
                        initMessage += '\nWarning: Large number of articles, processing may take longer';
                    }
                    updateProgress('生成标签', 0, 0, 0, 0, initMessage, stats.total_posts);

                    // 显示处理状态
                    var statusInterval = showProcessingStatus('生成标签', stats.total_posts, 'tags');

                    // 发送实际的批量生成请求
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        timeout: 600000, // 10分钟超时时间（600秒）
                        data: {
                            action: 'batch_generate_tags',
                            nonce: '<?php echo wp_create_nonce('batch_generate_tags_nonce'); ?>'
                        },
                        beforeSend: function() {
                            updateProgress('生成标签', 10, 0, 0, 0, '正在发送请求到服务器...', stats.total_posts);
                        },
                        success: function(response) {
                            // 立即停止状态消息显示
                            clearInterval(statusInterval);

                            if (response.success) {
                                var data = response.data;
                                // 确保显示真实的处理结果
                                updateProgress('生成标签', 100, data.processed_count, data.success_count, data.error_count, '处理完成', stats.total_posts);

                                var message = '<div class="notice notice-success is-dismissible"><p>' +
                                    '<strong>批量生成标签完成！</strong><br>' +
                                    '✅ 成功处理：' + data.success_count + ' 篇文章<br>' +
                                    (data.error_count > 0 ? '❌ 处理失败：' + data.error_count + ' 篇文章<br>' : '') +
                                    '📊 总计处理：' + data.processed_count + ' 篇文章<br>' +
                                    '🏷️ 应用标签：' + data.total_applied_tags + ' 个';

                                if (data.error_count > 0) {
                                    message += '<br><small>详细信息请查看错误日志</small>';
                                }

                                message += '</p></div>';
                                $result.html(message).show();

                                // 5秒后隐藏进度条
                                setTimeout(function() {
                                    $progress.hide();
                                }, 5000);

                            } else {
                                updateProgress('生成标签', 100, 0, 0, 0, '处理失败：' + response.data.message, stats.total_posts);
                                $result.html('<div class="notice notice-error"><p><strong>批量生成标签失败：</strong><br>' + response.data.message + '</p></div>').show();
                                setTimeout(function() {
                                    $progress.hide();
                                }, 5000);
                            }

                            $button.prop('disabled', false);
                        },
                        error: function(xhr, status, error) {
                            // 停止状态消息显示
                            clearInterval(statusInterval);

                            var errorMessage = '';
                            if (status === 'timeout') {
                                var partialMessage = '\n\n⚠️ **处理可能仍在继续**\n\n' +
                                    'For large numbers of articles（' + stats.total_posts + ' 篇）的标签生成：\n' +
                                    '• 服务器可能仍在后台继续处理\n' +
                                    '• 建议等待10-15分钟后刷新页面查看结果\n' +
                                    '• 如果仍有大量文章未处理，可以再次运行\n' +
                                    '• 考虑分批次处理（每次处理200-300篇）';

                                errorMessage = '请求超时：处理时间过长，服务器响应超时。' + partialMessage;
                                updateProgress('生成标签', 100, 0, 0, 0, '请求超时，但处理可能仍在继续', stats.total_posts);
                            } else if (status === 'abort') {
                                errorMessage = '请求被取消';
                                updateProgress('生成标签', 100, 0, 0, 0, '请求被取消', stats.total_posts);
                            } else if (xhr.status === 0) {
                                errorMessage = '网络连接失败：无法连接到服务器，请检查网络连接';
                                updateProgress('生成标签', 100, 0, 0, 0, '网络连接失败', stats.total_posts);
                            } else if (xhr.status === 500) {
                                errorMessage = '服务器内部错误：服务器处理请求时发生错误 (HTTP 500)';
                                updateProgress('生成标签', 100, 0, 0, 0, '服务器错误', stats.total_posts);
                            } else if (xhr.status === 503) {
                                errorMessage = '服务不可用：服务器暂时无法处理请求 (HTTP 503)';
                                updateProgress('生成标签', 100, 0, 0, 0, '服务不可用', stats.total_posts);
                            } else if (xhr.status === 504) {
                                errorMessage = '网关超时：服务器处理时间过长 (HTTP 504)';
                                updateProgress('生成标签', 100, 0, 0, 0, '网关超时', stats.total_posts);
                            } else {
                                errorMessage = '网络错误：' + (error || '未知错误') + ' (HTTP ' + xhr.status + ')';
                                updateProgress('生成标签', 100, 0, 0, 0, '网络错误', stats.total_posts);
                            }

                            $result.html('<div class="notice notice-error"><p><strong>标签生成失败：</strong><br>' + errorMessage + '</p>' +
                                '<p><strong>建议：</strong></p>' +
                                '<ul>' +
                                '<li>检查网络连接是否正常</li>' +
                                '<li>刷新页面后重试</li>' +
                                '<li>如果是大量文章处理，recommend processing in batches</li>' +
                                '<li>如果问题持续，请联系服务器管理员</li>' +
                                '</ul></div>').show();

                            setTimeout(function() {
                                $progress.hide();
                            }, 8000); // 延长显示时间到8秒
                            $button.prop('disabled', false);
                        }
                    });
                });

                // SEO分析功能
                $('.seo-analyze-single').on('click', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var postId = $button.data('post-id');

                    console.log('SEO分析按钮点击 - 文章ID:', postId);

                    if (!postId) {
                        alert('文章ID无效');
                        return;
                    }

                    // 显示加载状态
                    var originalText = $button.html();
                    $button.html('<span class="dashicons dashicons-update rotating"></span> 分析中...').prop('disabled', true);

                    // 发送SEO分析请求
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'auto_excerpt_seo_analyze',
                            nonce: '<?php echo wp_create_nonce('auto_excerpt_seo_analyze'); ?>',
                            post_id: postId
                        },
                        success: function(response) {
                            if (response.success) {
                                // 恢复按钮状态
                                $button.html(originalText).prop('disabled', false);

                                // 显示美观的SEO分析弹框
                                console.log('=== AI SEO分析数据结构 ===');
                                console.log('完整数据:', response.data);

                                if (response.data.recommendations) {
                                    console.log('建议数量:', response.data.recommendations.length);
                                    response.data.recommendations.forEach(function(rec, index) {
                                        console.log(`建议${index + 1}:`, {
                                            title: rec.title,
                                            has_action: !!rec.action,
                                            action_length: rec.action ? rec.action.length : 0,
                                            has_description: !!rec.description,
                                            priority: rec.priority
                                        });
                                    });
                                }

                                if (response.data.keywords) {
                                    console.log('关键词:', response.data.keywords);
                                }

                                console.log('=== 数据结构结束 ===');

                                showSEOReportModal(postId, response.data);

                                // 不自动刷新页面，让用户有足够时间阅读报告
                            } else {
                                alert('SEO分析失败：' + response.data.message);
                                $button.html(originalText).prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('网络错误，请重试');
                            $button.html(originalText).prop('disabled', false);
                        }
                    });
                });

                // JSON修复函数
                window.fixBrokenJSON = function(jsonString) {
                    if (!jsonString || typeof jsonString !== 'string') {
                        return null;
                    }

                    let fixed = jsonString.trim();

                    // 提取JSON内容（移除```json标记）
                    if (fixed.startsWith('```json')) {
                        fixed = fixed.replace(/^```json\s*/, '').replace(/\s*```$/, '');
                    }

                    // 1. 修复花括号不匹配
                    const openBraces = (fixed.match(/\{/g) || []).length;
                    const closeBraces = (fixed.match(/\}/g) || []).length;
                    if (openBraces > closeBraces) {
                        fixed += '}'.repeat(openBraces - closeBraces);
                        console.log('添加了 ' + (openBraces - closeBraces) + ' 个闭合花括号');
                    }

                    // 2. 移除控制字符
                    fixed = fixed.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');

                    // 3. 转义未转义的换行符
                    fixed = fixed.replace(/\n/g, '\\n').replace(/\r/g, '\\r').replace(/\t/g, '\\t');

                    // 4. 修复未闭合的字符串
                    fixed = fixed.replace(/"([^"]*?)$/, '"$1"');

                    // 5. 移除多余的逗号
                    fixed = fixed.replace(/,\s*([}\]])/g, '$1');

                    console.log('JSON修复完成');
                    return fixed;
                };

                // 构建结构化AI分析部分 - 优化样式
                window.buildStructuredAISection = function(data) {
                    var html = '<div class="seo-report-section ai-analysis-container">';

                    // AI分析详情
                    if (data.ai_analysis && Object.keys(data.ai_analysis).length > 0) {
                        html += '<div class="analysis-card">';
                        html += '<h4 class="card-title">📊 AI分析详情</h4>';

                        const labels = {
                            title_analysis: '标题分析',
                            content_analysis: '内容分析',
                            keyword_analysis: '关键词分析',
                            readability_analysis: '可读性分析'
                        };

                        Object.entries(data.ai_analysis).forEach(([key, value]) => {
                            html += '<div class="analysis-row">';
                            html += '<div class="analysis-content">';
                            html += '<h4 class="analysis-label">' + (labels[key] || key) + '</h6>';
                            html += '<p class="analysis-text">' + value + '</p>';
                            html += '</div>';
                            html += '</div>';
                        });

                        html += '</div>';
                    }

                    // AI关键词
                    if (data.ai_keywords && data.ai_keywords.length > 0) {
                        html += '<div class="keywords-card">';
                        html += '<h4 class="card-title">🏷️ AI推荐关键词</h4>';
                        html += '<div class="keywords-list">';
                        data.ai_keywords.forEach(function(keyword) {
                            html += '<span class="keyword-chip">' + keyword + '</span>';
                        });
                        html += '</div></div>';
                    }

                    // AI推荐
                    if (data.ai_recommendations && data.ai_recommendations.length > 0) {
                        html += '<div class="recommendations-card">';
                        html += '<h4 class="card-title">🤖 AI优化建议</h4>';

                        data.ai_recommendations.forEach(function(rec, index) {
                            html += '<div class="recommendation-card-item">';
                            html += '<h5 class="rec-title">' + (index + 1) + '. ' + (rec.title || '优化建议') + '</h5>';
                            if (rec.description) html += '<p class="rec-desc">' + rec.description + '</p>';
                            if (rec.action) {
                                html += '<div class="rec-action">';
                                html += '<span class="action-label">✓ 操作</span>';
                                html += '<span class="action-text">' + rec.action + '</span>';
                                html += '</div>';
                            }
                            if (rec.impact) {
                                html += '<div class="rec-impact">';
                                html += '<span class="impact-label">⭐ 效果</span>';
                                html += '<span class="impact-text">' + rec.impact + '</span>';
                                html += '</div>';
                            }
                            html += '</div>';
                        });

                        html += '</div>';
                    }

                    // 元信息建议
                    if (data.ai_meta_info) {
                        html += '<div class="meta-card">';
                        html += '<h4 class="card-title">📝 元信息建议</h4>';

                        if (data.ai_meta_info.suggested_title) {
                            html += '<div class="meta-item">';
                            html += '<h4 class="meta-label">📄 建议标题</h6>';
                            html += '<p class="meta-value">' + data.ai_meta_info.suggested_title + '</p>';
                            html += '</div>';
                        }

                        if (data.ai_meta_info.meta_description) {
                            html += '<div class="meta-item">';
                            html += '<h4 class="meta-label">📋 Meta描述</h6>';
                            html += '<p class="meta-value">' + data.ai_meta_info.meta_description + '</p>';
                            html += '</div>';
                        }

                        if (data.ai_meta_info.focus_keywords && data.ai_meta_info.focus_keywords.length > 0) {
                            html += '<div class="meta-item">';
                            html += '<h4 class="meta-label">🎯 核心关键词</h6>';
                            html += '<div class="keywords-list">';
                            data.ai_meta_info.focus_keywords.forEach(function(keyword) {
                                html += '<span class="focus-keyword-chip">' + keyword + '</span>';
                            });
                            html += '</div></div>';
                        }

                        html += '</div>';
                    }

                    html += '</div>';
                    return html;
                };

                // 构建AI分析部分
                window.buildAIAnalysisSection = function(aiData) {
                    var html = '<div class="seo-report-section">';
                    html += '<h3>🤖 AI分析</h3>';

                    // AI分析详情
                    if (aiData.analysis) {
                        html += '<div class="analysis-details">';
                        html += '<h4>📊 AI分析详情</h4>';

                        const labels = {
                            title_analysis: '标题分析',
                            content_analysis: '内容分析',
                            keyword_analysis: '关键词分析',
                            readability_analysis: '可读性分析'
                        };

                        Object.entries(aiData.analysis).forEach(([key, value]) => {
                            html += '<div class="analysis-item">';
                            html += '<h5>' + (labels[key] || key) + ':</h5>';
                            html += '<p>' + value + '</p>';
                            html += '</div>';
                        });

                        html += '</div>';
                    }

                    // AI推荐
                    if (aiData.recommendations && aiData.recommendations.length > 0) {
                        html += '<div class="ai-recommendations">';
                        html += '<h4>🤖 AI优化建议</h4>';

                        aiData.recommendations.forEach(function(rec, index) {
                            html += '<div class="recommendation-item">';
                            html += '<h5>' + (index + 1) + '. ' + (rec.title || '建议') + '</h5>';
                            if (rec.description) html += '<p>' + rec.description + '</p>';
                            if (rec.action) html += '<p><strong>操作:</strong> ' + rec.action + '</p>';
                            if (rec.impact) html += '<p><strong>效果:</strong> ' + rec.impact + '</p>';
                            html += '</div>';
                        });

                        html += '</div>';
                    }

                    html += '</div>';
                    return html;
                };

                // 构建原始分析部分
                window.buildRawAnalysisSection = function(rawAnalysis) {
                    var html = '<div class="seo-report-section">';
                    html += '<h3>🤖 AI分析</h3>';
                    html += '<div class="raw-analysis">';
                    html += '<h4>📄 原始分析数据</h4>';
                    html += '<pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 300px; overflow-y: auto;">' +
                           escapeHtml(rawAnalysis) + '</pre>';
                    html += '</div></div>';
                    return html;
                };

                // HTML转义函数
                window.escapeHtml = function(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };

                // SEO报告弹框函数 - 简化版本
                window.showSEOReportModal = function(postId, data) {
                    console.log('SEO报告数据:', data); // 调试用
                    console.log('=== 详细数据检查 ===');
                    console.log('data.ai_analysis:', data.ai_analysis);
                    console.log('data.ai_recommendations:', data.ai_recommendations);
                    console.log('data.ai_keywords:', data.ai_keywords);
                    console.log('keys in data:', Object.keys(data));
                    if (data.ai_analysis) {
                        console.log('data.ai_analysis keys:', Object.keys(data.ai_analysis));
                    }

                    // 构建报告HTML - 基于实际数据结构
                    var reportHtml = '<div class="seo-report-header">';
                    reportHtml += '<p class="report-post-id">文章ID: ' + postId + '</p>';
                    reportHtml += '</div>';

                    // 基础信息
                    if (data.post_title) {
                        reportHtml += '<div class="seo-report-section">';
                        reportHtml += '<h3>📄 文章信息</h3>';
                        reportHtml += '<p><strong>标题:</strong> ' + data.post_title + '</p>';
                        reportHtml += '</div>';
                    }

                    // SEO得分展示
                    if (data.overall_score !== undefined) {
                        reportHtml += '<div class="seo-report-section">';
                        reportHtml += '<h3>📊 SEO得分</h3>';
                        reportHtml += '<div class="score-grid">';
                        reportHtml += '<div class="score-item"><strong>整体得分:</strong> <span class="score-value">' + data.overall_score + '</span></div>';
                        if (data.title_score) reportHtml += '<div class="score-item"><strong>标题得分:</strong> <span class="score-value">' + data.title_score + '</span></div>';
                        if (data.content_score) reportHtml += '<div class="score-item"><strong>内容得分:</strong> <span class="score-value">' + data.content_score + '</span></div>';
                        if (data.keyword_score) reportHtml += '<div class="score-item"><strong>关键词得分:</strong> <span class="score-value">' + data.keyword_score + '</span></div>';
                        if (data.readability_score) reportHtml += '<div class="score-item"><strong>可读性得分:</strong> <span class="score-value">' + data.readability_score + '</span></div>';
                        reportHtml += '</div></div>';
                    }

                    // 技术统计
                    reportHtml += '<div class="seo-report-section">';
                    reportHtml += '<div class="stats-grid">';
                    if (data.word_count) reportHtml += '<div class="stat-item"><strong>字数:</strong> ' + data.word_count + ' 字</div>';
                    if (data.title_length) reportHtml += '<div class="stat-item"><strong>标题长度:</strong> ' + data.title_length + ' 字符</div>';
                    if (data.image_count) reportHtml += '<div class="stat-item"><strong>图片数量:</strong> ' + data.image_count + ' 个</div>';
                    if (data.internal_links) reportHtml += '<div class="stat-item"><strong>内部链接:</strong> ' + data.internal_links + ' 个</div>';
                    if (data.external_links) reportHtml += '<div class="stat-item"><strong>外部链接:</strong> ' + data.external_links + ' 个</div>';
                    reportHtml += '</div></div>';

                    // 标题结构统计
                    if (data.heading_counts && Object.keys(data.heading_counts).length > 0) {
                        reportHtml += '<div class="seo-report-section">';
                        reportHtml += '<h3>📝 标题结构</h3>';
                        reportHtml += '<div class="heading-grid">';
                        Object.keys(data.heading_counts).forEach(function(tag) {
                            reportHtml += '<div class="heading-item"><span class="heading-tag">' + tag.toUpperCase() + '</span><span class="heading-count">' + data.heading_counts[tag] + '</span></div>';
                        });
                        reportHtml += '</div></div>';
                    }

                    // AI分析部分 - 使用analysis对象中的数据
                    if (data.analysis && (data.analysis.ai_analysis || data.analysis.ai_recommendations || data.analysis.ai_keywords)) {
                        console.log('使用analysis对象中的AI数据');
                        reportHtml += buildStructuredAISection(data.analysis);
                    } else if (data.analysis) {
                        console.log('使用analysis对象数据构建AI部分');
                        // 直接使用analysis对象
                        reportHtml += buildStructuredAISection(data.analysis);
                    } else if (data.raw_ai_analysis) {
                        // 备用：处理原始JSON数据
                        try {
                            var aiData = JSON.parse(data.raw_ai_analysis);
                            reportHtml += buildAIAnalysisSection(aiData);
                        } catch (e) {
                            console.log('JSON解析失败，尝试修复:', e);
                            reportHtml += buildRawAnalysisSection(data.raw_ai_analysis);
                        }
                    }

                    // 创建弹框 - 简化版本，无头部
                    var modalHtml = '<div id="seo-report-modal" class="seo-report-modal" style="display: none;">';
                    modalHtml += '<div class="seo-modal-backdrop"></div>';
                    modalHtml += '<div class="seo-modal-content">';
                    modalHtml += '<div class="seo-modal-body">' + reportHtml + '</div>';
                    modalHtml += '<div class="seo-modal-footer">';
                    modalHtml += '<button class="button button-secondary" onclick="closeSEOReportModal()">关闭</button>';
                    modalHtml += '</div>';
                    modalHtml += '</div></div>';

                    // 添加到页面并显示
                    $('body').append(modalHtml);

                    var modal = $('#seo-report-modal');
                    if (modal.length > 0) {
                        modal.css({
                            'position': 'fixed',
                            'top': '0',
                            'left': '0',
                            'width': '100%',
                            'height': '100%',
                            'background': 'rgba(0, 0, 0, 0.5)',
                            'display': 'flex',
                            'align-items': 'center',
                            'justify-content': 'center',
                            'z-index': '99999'
                        }).show();

                        modal.find('.seo-modal-content').css({
                            'background': 'white',
                            'border-radius': '8px',
                            'max-width': '800px',
                            'max-height': '90vh',
                            'width': '90%',
                            'overflow': 'hidden',
                            'box-shadow': '0 20px 60px rgba(0, 0, 0, 0.3)'
                        });

                        modal.find('.seo-modal-body').css({
                            'padding': '32px',
                            'max-height': '60vh',
                            'overflow-y': 'auto'
                        });

                        modal.find('.seo-modal-footer').css({
                            'padding': '20px 32px',
                            'border-top': '1px solid #eee',
                            'text-align': 'right',
                            'background': '#f8f9fa'
                        });
                    }
                };

                // 关闭SEO报告弹框
                window.closeSEOReportModal = function() {
                    $('#seo-report-modal').fadeOut(300, function() {
                        $(this).remove();
                    });
                };
            });
        </script>
        <?php
        } // End of if ($this->auto_excerpt)
    }

    /**
     * 添加插件操作链接
     */
    public function add_plugin_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wordpress-toolkit') . '">设置</a>';
        $about_link = '<a href="' . admin_url('admin.php?page=wordpress-toolkit-about') . '">功能说明</a>';
        array_unshift($links, $about_link, $settings_link);
        return $links;
    }
}

// 初始化插件
WordPress_Toolkit::get_instance();
