<?php
/**
 * Simple FriendLink Module for WordPress Toolkit
 * 简洁友情链接模块
 *
 * @since 1.0.5
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class Simple_FriendLink_Module {

    /**
     * 单例实例
     */
    private static $instance = null;

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
        // 模块将在主类中初始化
    }

    /**
     * 初始化模块
     */
    public function init() {
        // 注册页面模板
        add_filter('theme_page_templates', array($this, 'add_page_template'));
        add_filter('template_include', array($this, 'load_page_template'));

        // 前端资源加载 - 仅在友情链接页面加载
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX处理
        add_action('wp_ajax_simple_friendlink_add', array($this, 'ajax_add_link'));
        add_action('wp_ajax_simple_friendlink_get_rss', array($this, 'ajax_get_rss'));

        // 定时任务
        add_action('wp', array($this, 'setup_cron_jobs'));
        add_action('friendlink_update_cache', array($this, 'update_all_links_cache'));

        // 管理员手动刷新缓存
        add_action('wp_ajax_friendlink_refresh_cache', array($this, 'ajax_refresh_cache'));

        // 添加自定义定时间隔
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
    }

    /**
     * 添加页面模板
     */
    public function add_page_template($templates) {
        $templates['simple-friendlink.php'] = __('友情链接页面', 'wordpress-toolkit');
        return $templates;
    }

    /**
     * 加载页面模板
     */
    public function load_page_template($template) {
        global $post;
        if ($post && get_post_meta($post->ID, '_wp_page_template', true) == 'simple-friendlink.php') {
            return WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/simple-friendlink/templates/page-simple-friendlink.php';
        }
        return $template;
    }

    /**
     * 检测是否为友情链接页面
     */
    private function is_friendlink_page() {
        global $post;
        if (!$post) {
            return false;
        }
        return get_post_meta($post->ID, '_wp_page_template', true) === 'simple-friendlink.php';
    }

    /**
     * 前端资源加载 - 仅在友情链接页面加载
     */
    public function enqueue_scripts() {
        // 只在友情链接页面加载资源
        if (!$this->is_friendlink_page()) {
            return;
        }

        wp_enqueue_style(
            'simple-friendlink-style',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/simple-friendlink/assets/css/simple-friendlink.css',
            array(),
            WORDPRESS_TOOLKIT_VERSION
        );

        wp_enqueue_script(
            'simple-friendlink-script',
            WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/simple-friendlink/assets/js/simple-friendlink.js',
            array('jquery'),
            WORDPRESS_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('simple-friendlink-script', 'SimpleFriendLinkConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('simple_friendlink_nonce'),
            'strings' => array(
                'submitSuccess' => __('提交成功！', 'wordpress-toolkit'),
                'submitError' => __('提交失败，请重试。', 'wordpress-toolkit'),
                'loading' => __('提交中...', 'wordpress-toolkit')
            )
        ));
    }

    /**
     * 处理表单提交 - 用于页面表单提交
     */
    public function handle_form_submission() {
        // 验证nonce
        if (!isset($_POST['friendlink_nonce']) || !wp_verify_nonce($_POST['friendlink_nonce'], 'simple_friendlink_submit')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 权限检查
        $settings = $this->get_settings();
        $require_login = $settings['require_login'] ?? true;

        if ($require_login && !is_user_logged_in()) {
            wp_die(__('请登录后提交友情链接', 'wordpress-toolkit'));
        }

        // 获取表单数据
        $name = sanitize_text_field($_POST['friendlink_name']);
        $url = esc_url_raw($_POST['friendlink_url']);
        $description = sanitize_textarea_field($_POST['friendlink_description']);
        $email = isset($_POST['friendlink_email']) ? sanitize_email($_POST['friendlink_email']) : '';

        // 验证必填字段
        if (empty($name) || empty($url)) {
            wp_die(__('网站名称和地址为必填项', 'wordpress-toolkit'));
        }

        // 验证URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_die(__('请输入有效的网站地址', 'wordpress-toolkit'));
        }

        // 检查链接是否已存在
        global $wpdb;
        $table_name = $wpdb->prefix . 'links';
        $existing_link = $wpdb->get_var($wpdb->prepare(
            "SELECT link_id FROM $table_name WHERE link_url = %s LIMIT 1",
            $url
        ));

        if ($existing_link) {
            wp_die(__('该网站已存在于友情链接中', 'wordpress-toolkit'));
        }

        // 获取设置
        $admin_approval = isset($settings['admin_approval']) ? $settings['admin_approval'] : false;

        // 直接插入到wp_links表（因为wp_insert_link函数在较新WP版本中不可用）
        global $wpdb;
        $table_name = $wpdb->prefix . 'links';

        $result = $wpdb->insert($table_name, array(
            'link_name' => $name,
            'link_url' => $url,
            'link_description' => $description,
            'link_notes' => $email,
            'link_rating' => 0,
            'link_visible' => $admin_approval ? 'N' : 'Y', // 如果需要审核则设为不可见
            'link_owner' => get_current_user_id(),
            'link_target' => '_blank',
            'link_image' => '',
            'link_rss' => '',
            'link_updated' => current_time('mysql'),
            'link_rel' => ''
        ));

        $link_id = $result !== false ? $wpdb->insert_id : false;

        if ($link_id) {
            if ($admin_approval) {
                // 添加成功消息
                add_action('wp_footer', function() {
                    echo '<script>alert("友情链接提交成功！等待管理员审核。");</script>';
                });
            } else {
                // 添加成功消息
                add_action('wp_footer', function() {
                    echo '<script>alert("友情链接添加成功！");</script>';
                });
            }
        } else {
            $error_message = __('提交失败，请重试。', 'wordpress-toolkit');
            wp_die($error_message);
        }
    }

    /**
     * AJAX添加链接 - 直接保存到WordPress内置链接表
     */
    public function ajax_add_link() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'simple_friendlink_nonce')) {
            wp_die(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 权限检查
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('请登录后提交友情链接', 'wordpress-toolkit')));
        }

        // 获取表单数据
        $name = sanitize_text_field($_POST['link_name']);
        $url = esc_url_raw($_POST['link_url']);
        $description = sanitize_textarea_field($_POST['link_description']);
        $email = isset($_POST['link_email']) ? sanitize_email($_POST['link_email']) : '';

        // 验证必填字段
        if (empty($name) || empty($url)) {
            wp_send_json_error(array('message' => __('网站名称和地址为必填项', 'wordpress-toolkit')));
        }

        // 验证URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => __('请输入有效的网站地址', 'wordpress-toolkit')));
        }

        // 检查链接是否已存在
        global $wpdb;
        $table_name = $wpdb->prefix . 'links';
        $existing_link = $wpdb->get_var($wpdb->prepare(
            "SELECT link_id FROM $table_name WHERE link_url = %s LIMIT 1",
            $url
        ));

        if ($existing_link) {
            wp_send_json_error(array('message' => __('该网站已存在于友情链接中', 'wordpress-toolkit')));
        }

        // 获取设置
        $settings = $this->get_settings();
        $admin_approval = isset($settings['admin_approval']) ? $settings['admin_approval'] : false;

        // 插入到WordPress内置链接表
        $link_data = array(
            'link_name' => $name,
            'link_url' => $url,
            'link_description' => $description,
            'link_notes' => $email,
            'link_rating' => 0,
            'link_visible' => $admin_approval ? 'N' : 'Y', // 如果需要审核则设为不可见
            'link_owner' => get_current_user_id(),
            'link_category' => array(2), // 默认分类ID
            'link_target' => '_blank',
            'link_image' => '',
            'link_rss' => ''
        );

        // 直接插入到wp_links表（因为wp_insert_link函数在较新WP版本中不可用）
        global $wpdb;
        $table_name = $wpdb->prefix . 'links';

        $result = $wpdb->insert($table_name, $link_data + array(
            'link_updated' => current_time('mysql'),
            'link_rel' => ''
        ));

        $link_id = $result !== false ? $wpdb->insert_id : false;

        if ($link_id) {
            if ($admin_approval) {
                wp_send_json_success(array('message' => __('友情链接提交成功！等待管理员审核。', 'wordpress-toolkit')));
            } else {
                wp_send_json_success(array('message' => __('友情链接添加成功！', 'wordpress-toolkit')));
            }
        } else {
            $error_message = __('提交失败，请重试。', 'wordpress-toolkit');
            wp_send_json_error(array('message' => $error_message));
        }
    }

    /**
     * AJAX获取RSS最新文章和网站信息
     */
    public function ajax_get_rss() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'simple_friendlink_nonce')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'wordpress-toolkit')));
        }

        $url = esc_url_raw($_POST['url']);
        if (empty($url)) {
            wp_send_json_error(array('message' => __('URL不能为空', 'wordpress-toolkit')));
        }

        $result = array(
            'latest_post' => $this->get_latest_post_from_rss($url),
            'site_info' => $this->get_site_info($url)
        );

        // 至少有一个成功就返回成功
        if ($result['latest_post'] || $result['site_info']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => __('无法获取信息', 'wordpress-toolkit')));
        }
    }

    /**
     * AJAX手动刷新缓存
     */
    public function ajax_refresh_cache() {
        // 验证权限和nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'wordpress-toolkit')));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'friendlink_refresh_cache')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'wordpress-toolkit')));
        }

        // 清理所有缓存
        $this->clear_all_cache();

        // 立即更新所有链接的缓存
        $links = $this->get_friendlinks();
        $updated_count = 0;
        $success_count = 0;

        foreach ($links as $link) {
            $updated_count++;

            // 更新RSS缓存
            $rss_result = $this->get_latest_post_from_rss($link->url);
            if ($rss_result) $success_count++;

            // 更新网站信息缓存
            $site_result = $this->get_site_info($link->url);
            if ($site_result) $success_count++;

            // 更新Logo缓存
            $logo_result = $this->get_site_logo($link->url);
            if ($logo_result) $success_count++;

            // 短暂延迟 - 优化后减少延迟
            usleep(200000); // 0.2秒
        }

        wp_send_json_success(array(
            'message' => __('缓存刷新完成', 'wordpress-toolkit'),
            'total_links' => $updated_count,
            'success_operations' => $success_count
        ));
    }

    
    /**
     * 设置缓存（使用WordPress Transients API）
     */
    private function set_cache($key, $value, $expiration) {
        return set_transient($key, $value, $expiration);
    }

    /**
     * 获取缓存状态信息
     */
    public function get_cache_status() {
        global $wpdb;

        // 检查WordPress缓存表是否存在
        $table_name = $wpdb->prefix . 'options';
        $transient_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE option_name LIKE %s",
            'transient_friendlink_%'
        ));

        return array(
            'cache_type' => 'WordPress Transients',
            'cache_count' => intval($transient_count),
            'cache_status' => 'active',
            'description' => '使用WordPress内置缓存，无需额外依赖'
        );
    }

    /**
     * 删除缓存
     */
    private function delete_cache($key) {
        delete_transient($key);
    }

    /**
     * 获取最新文章 - 优化缓存版本
     */
    public function get_latest_post_from_rss($url) {
        $cache_key = 'friendlink_rss_' . md5($url);

        // 使用WordPress Transients缓存
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        // 构建RSS URL - 尝试常见的RSS路径
        $rss_urls = $this->guess_rss_urls($url);

        foreach ($rss_urls as $rss_url) {
            $latest_post = $this->fetch_rss_feed($rss_url);
            if ($latest_post) {
                // 缓存结果3小时
                $this->set_cache($cache_key, $latest_post, 3 * HOUR_IN_SECONDS);
                return $latest_post;
            }
        }

        return null;
    }

    /**
     * 获取网站信息 - 优化缓存版本
     */
    public function get_site_info($url) {
        $cache_key = 'friendlink_site_info_' . md5($url);

        // 使用WordPress Transients缓存
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }

        $parsed_url = parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];

        // 使用WordPress的WP_Http类进行请求
        $response = wp_remote_get($base_url, array(
            'timeout' => 8,
            'user-agent' => 'Mozilla/5.0 (compatible; FriendLink-Bot/1.0)',
            'sslverify' => false,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            )
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return null;
        }

        $site_info = array(
            'title' => $this->extract_page_title($html, $base_url),
            'description' => $this->extract_meta_description($html, $base_url)
        );

        // 缓存结果3天
        $this->set_cache($cache_key, $site_info, 3 * DAY_IN_SECONDS);

        return $site_info;
    }

    /**
     * 获取友情链接列表 - 仅使用WordPress内置链接
     */
    public function get_friendlinks() {
        $links = array();

        // 直接查询wp_links表获取友情链接（get_bookmarks函数在较新WP版本中不可用）
        global $wpdb;
        $table_name = $wpdb->prefix . 'links';

        $bookmarks = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE link_visible = 'Y' ORDER BY link_rating DESC, link_id DESC"
        );

        if ($bookmarks) {
            foreach ($bookmarks as $link) {
                $links[] = (object) array(
                    'id' => $link->link_id,
                    'name' => $link->link_name,
                    'url' => $link->link_url,
                    'description' => $link->link_description,
                    'icon_url' => $link->link_image ?? '',
                    'favicon_url' => '',
                    'email' => $link->link_notes ?? '',
                    'latest_post_title' => '',
                    'latest_post_url' => '',
                    'latest_post_date' => null,
                    'sort_order' => $link->link_rating ?? 0,
                    'status' => 'active',
                    'created_at' => $link->link_updated,
                    'updated_at' => $link->link_updated,
                    'source' => 'wp_builtin'
                );
            }
        }

        // 按评分和名称排序
        usort($links, function($a, $b) {
            if ($a->sort_order != $b->sort_order) {
                return $b->sort_order - $a->sort_order;
            }
            return strcmp($a->name, $b->name);
        });

        return $links;
    }

    /**
     * 获取友情链接分类
     */
    public function get_link_categories() {
        $categories = get_terms(array(
            'taxonomy' => 'link_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (is_wp_error($categories)) {
            return array();
        }

        return $categories;
    }

    /**
     * 添加自定义定时间隔
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['friendlink_three_hourly'] = array(
            'interval' => 3 * HOUR_IN_SECONDS,
            'display' => '每3小时一次'
        );
        return $schedules;
    }

    /**
     * 设置定时任务
     */
    public function setup_cron_jobs() {
        // 如果定时任务不存在，则创建
        if (!wp_next_scheduled('friendlink_update_cache')) {
            // 每3小时执行一次更新，匹配最新文章缓存时间
            wp_schedule_event(time(), 'friendlink_three_hourly', 'friendlink_update_cache');
        }
    }

    /**
     * 更新所有友情链接的缓存
     */
    public function update_all_links_cache() {
        $links = $this->get_friendlinks();

        foreach ($links as $link) {
            // 更新RSS缓存
            $this->get_latest_post_from_rss($link->url);

            // 更新网站信息缓存
            $this->get_site_info($link->url);

            // 更新Logo缓存
            $this->get_site_logo($link->url);

            // 减少延迟，Logo获取现在更快了
            usleep(500000); // 0.5秒
        }

        // 记录日志
        error_log('FriendLink Cache Updated: ' . count($links) . ' links processed');
    }

    /**
     * 插件激活时初始化
     */
    public function activate() {
        // 检查是否需要创建默认分类
        if (!term_exists('默认分类', 'link_category')) {
            wp_insert_term('默认分类', 'link_category');
        }

        // 添加Friends Plugin风格的选项
        add_option('friends_plugin_rss_update_interval', 24); // 默认24小时
        add_option('friends_plugin_glow_animation_enabled', 1); // 默认启用发光动画

        // 激活时立即设置定时任务
        $this->setup_cron_jobs();

        // 立即执行一次缓存更新
        wp_schedule_single_event(time() + 60, 'friendlink_update_cache');
    }

    /**
     * 插件停用时清理
     */
    public function deactivate() {
        // 清理定时任务
        wp_clear_scheduled_hook('friendlink_update_cache');

        // 清理所有友情链接相关的缓存
        $this->clear_all_cache();
    }

    /**
     * 清理所有缓存
     */
    private function clear_all_cache() {
        global $wpdb;

        // 获取所有友情链接
        $links = $this->get_friendlinks();

        foreach ($links as $link) {
            // 清理RSS缓存
            $rss_cache_key = 'friendlink_rss_' . md5($link->url);
            delete_transient($rss_cache_key);

            // 清理网站信息缓存
            $site_cache_key = 'friendlink_site_info_' . md5($link->url);
            delete_transient($site_cache_key);

            // 清理Logo缓存
            $logo_cache_key = 'friendlink_logo_' . md5($link->url);
            delete_transient($logo_cache_key);
        }
    }

    /**
     * 获取模块设置
     */
    public function get_settings() {
        return get_option('simple_friendlink_settings', array(
            'allow_user_submit' => true,
            'require_login' => true,
            'admin_approval' => false,
            'max_links_per_page' => 30
        ));
    }

    /**
     * 保存模块设置
     */
    public function save_settings($settings) {
        return update_option('simple_friendlink_settings', $settings);
    }

    /**
     * 猜测RSS URL路径
     */
    private function guess_rss_urls($url) {
        $parsed_url = parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];

        return array(
            $base_url . '/feed/',
            $base_url . '/feed/rss/',
            $base_url . '/feed/xml/',
            $base_url . '/rss/',
            $base_url . '/rss.xml',
            $base_url . '/atom.xml',
            $base_url . '/feed/atom/',
            $base_url . '/wp-rss.php',
            $base_url . '/wp-atom.php',
            $base_url . '/index.php/feed/',
            $base_url . '/index.php/rss/'
        );
    }

    /**
     * 获取RSS最新文章
     */
    private function fetch_rss_feed($rss_url) {
        $response = wp_remote_get($rss_url, array(
            'timeout' => 8,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (compatible; FriendLink-Bot/1.0)',
            'headers' => array(
                'Accept' => 'application/rss+xml, application/xml, text/xml'
            )
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $xml_string = wp_remote_retrieve_body($response);
        if (empty($xml_string)) {
            return null;
        }

        // 禁用libxml错误显示
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        libxml_clear_errors();

        if (!$xml) {
            return null;
        }

        $latest_post = null;

        // 尝试解析RSS 2.0
        if (isset($xml->channel->item)) {
            $item = $xml->channel->item[0];
            $latest_post = array(
                'title' => (string) $item->title,
                'url' => (string) $item->link,
                'date' => isset($item->pubDate) ? strtotime((string) $item->pubDate) : time()
            );
        }
        // 尝试解析Atom
        elseif (isset($xml->entry)) {
            $entry = $xml->entry[0];
            $latest_post = array(
                'title' => (string) $entry->title,
                'url' => (string) $entry->link['href'],
                'date' => isset($entry->published) ? strtotime((string) $entry->published) : time()
            );
        }

        return $latest_post;
    }

    /**
     * 提取页面标题
     */
    private function extract_page_title($html, $base_url) {
        // 使用正则表达式提取title标签
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }

        // 如果没有找到title标签，尝试提取h1标签
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }

        // 如果都没找到，返回域名
        $parsed_url = parse_url($base_url);
        return $parsed_url['host'];
    }

    /**
     * 提取meta描述
     */
    private function extract_meta_description($html, $base_url) {
        // 尝试提取meta description
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/is', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }

        // 尝试提取property="og:description"
        if (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/is', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        }

        // 如果都没有找到，尝试从第一段文字提取
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            $text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
            if (strlen($text) > 20) {
                return mb_substr($text, 0, 150, 'UTF-8') . (strlen($text) > 150 ? '...' : '');
            }
        }

        return '个人博客网站';
    }

    /**
     * 获取网站Logo
     */
    public function get_site_logo($url) {
        $cache_key = 'friendlink_logo_' . md5($url);

        // 使用WordPress Transients缓存
        $cached_logo = get_transient($cache_key);
        if ($cached_logo !== false) {
            return $cached_logo;
        }

        $parsed_url = parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];

        // 尝试获取网站Logo的常见路径
        $logo_paths = array(
            '/favicon.ico',
            '/favicon.png',
            '/apple-touch-icon.png',
            '/apple-touch-icon-precomposed.png',
            '/logo.png',
            '/wp-content/uploads/logo.png',
            '/assets/logo.png',
            '/images/logo.png'
        );

        foreach ($logo_paths as $logo_path) {
            $logo_url = $base_url . $logo_path;

            // 检查Logo是否存在
            $response = wp_remote_head($logo_url, array(
                'timeout' => 3,
                'sslverify' => false
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                // 缓存Logo URL 7天
                $this->set_cache($cache_key, $logo_url, 7 * DAY_IN_SECONDS);
                return $logo_url;
            }
        }

        // 如果没有找到Logo，返回false
        $this->set_cache($cache_key, false, 7 * DAY_IN_SECONDS);
        return false;
    }

    /**
     * 从RSS获取最新文章
     */

}
