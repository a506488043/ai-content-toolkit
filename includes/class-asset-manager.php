<?php
/**
 * WordPress Toolkit 资源管理器
 * 负责CSS和JS文件的合并、压缩和加载优化
 *
 * @since 1.1.0
 * @author WordPress Toolkit Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_Asset_Manager {

    /**
     * 资源版本号
     */
    private $version;

    /**
     * 资源缓存目录
     */
    private $cache_dir;

    /**
     * 资源缓存URL
     */
    private $cache_url;

    /**
     * 已注册的CSS文件
     */
    private $css_files = array();

    /**
     * 已注册的JS文件
     */
    private $js_files = array();

    /**
     * 构造函数
     */
    public function __construct() {
        $this->version = defined('WORDPRESS_TOOLKIT_VERSION') ? WORDPRESS_TOOLKIT_VERSION : '1.0.0';
        $this->cache_dir = WP_CONTENT_DIR . '/cache/wordpress-toolkit';
        $this->cache_url = WP_CONTENT_URL . '/cache/wordpress-toolkit';

        $this->init();
    }

    /**
     * 初始化资源管理器
     */
    private function init() {
        // 创建缓存目录
        $this->ensure_cache_directory();

        // 注册WordPress钩子
        add_action('init', array($this, 'register_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'), 999);

        // 开发环境下禁用合并
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_head', array($this, 'debug_assets_info'), 999);
        }
    }

    /**
     * 确保缓存目录存在
     */
    private function ensure_cache_directory() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);

            // 创建.htaccess文件保护缓存目录
            $htaccess_content = "# WordPress Toolkit Assets Cache\n";
            $htaccess_content .= "# Deny direct access to cache files\n";
            $htaccess_content .= "<FilesMatch \"\.(css|js)$\">\n";
            $htaccess_content .= "    <IfModule mod_headers.c>\n";
            $htaccess_content .= "        Header set Cache-Control \"public, max-age=31536000\"\n";
            $htaccess_content .= "        Header set X-Content-Type-Options nosniff\n";
            $htaccess_content .= "    </IfModule>\n";
            $htaccess_content .= "    ForceType application/javascript\n";
            $htaccess_content .= "</FilesMatch>\n";

            file_put_contents($this->cache_dir . '/.htaccess', $htaccess_content);
        }

        // 创建index.php防止目录列表
        if (!file_exists($this->cache_dir . '/index.php')) {
            file_put_contents($this->cache_dir . '/index.php', '<?php // Silence is golden ?>');
        }
    }

    /**
     * 注册资源文件
     */
    public function register_assets() {
        // 注册核心CSS文件
        $this->css_files['core'] = array(
            'variables.css' => WORDPRESS_TOOLKIT_PLUGIN_PATH . 'assets/css/variables.css',
            'common.css' => WORDPRESS_TOOLKIT_PLUGIN_PATH . 'assets/css/common.css',
            'admin.css' => WORDPRESS_TOOLKIT_PLUGIN_PATH . 'assets/css/admin.css'
        );

        // 注册模块CSS文件
        $this->register_module_css();

        // 注册核心JS文件
        $this->js_files['core'] = array(
            'toolkit-core.js' => WORDPRESS_TOOLKIT_PLUGIN_PATH . 'assets/js/toolkit-core.js',
            'migration-helper.js' => WORDPRESS_TOOLKIT_PLUGIN_PATH . 'assets/js/migration-helper.js'
        );

        // 注册模块JS文件
        $this->register_module_js();
    }

    /**
     * 注册模块CSS文件
     */
    private function register_module_css() {
        $modules = array(
            'time-capsule' => array(
                'admin.css',
                'frontend-manage.css',
                'style.css',
                'custom-page.css',
                'page-template.css'
            ),
            'custom-card' => array(
                'admin-style.css',
                'chf-card.css'
            ),
            'cookieguard' => array(
                'style.css',
                'admin.css'
            ),
            'age-calculator' => array(
                'style.css'
            ),
            'auto-excerpt' => array(
                'admin.css'
            ),
            'simple-friendlink' => array(
                'simple-friendlink.css'
            )
        );

        foreach ($modules as $module => $files) {
            $this->css_files[$module] = array();
            foreach ($files as $file) {
                $path = WORDPRESS_TOOLKIT_PLUGIN_PATH . "modules/{$module}/assets/css/{$file}";
                if (file_exists($path)) {
                    $this->css_files[$module][$file] = $path;
                }
            }
        }
    }

    /**
     * 注册模块JS文件
     */
    private function register_module_js() {
        $modules = array(
            'time-capsule' => array(
                'admin.js',
                'script.js',
                'custom-page.js',
                'frontend-manage.js'
            ),
            'custom-card' => array(
                'admin-script.js',
                'chf-card.js',
                'blocks/custom-card/edit.js',
                'blocks/custom-card/index.js'
            ),
            'cookieguard' => array(
                'admin.js',
                'admin-new.js',
                'script.js'
            ),
            'age-calculator' => array(
                'script.js'
            ),
            'auto-excerpt' => array(
                'admin.js'
            ),
            'simple-friendlink' => array(
                'simple-friendlink.js'
            )
        );

        foreach ($modules as $module => $files) {
            $this->js_files[$module] = array();
            foreach ($files as $file) {
                $path = WORDPRESS_TOOLKIT_PLUGIN_PATH . "modules/{$module}/assets/js/{$file}";
                if (file_exists($path)) {
                    $this->js_files[$module][$file] = $path;
                }
            }
        }
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets() {
        // 加载合并的核心CSS
        $this->enqueue_merged_css('core');

        // 根据当前页面加载相应模块资源
        $this->load_page_specific_assets();

        // 加载jQuery（如果没有加载）
        if (!wp_script_is('jquery', 'registered')) {
            wp_register_script('jquery', false, array(), '1.0.0', true);
        }
    }

    /**
     * 加载管理后台资源
     */
    public function enqueue_admin_assets($hook) {
        // 加载合并的核心CSS
        $this->enqueue_merged_css('core');

        // 加载合并的核心JS
        $this->enqueue_merged_js('core');

        // 根据当前页面加载相应模块资源
        if (strpos($hook, 'wordpress-toolkit') !== false) {
            $this->load_admin_page_assets($hook);
        }
    }

    /**
     * 加载页面特定资源
     */
    private function load_page_specific_assets() {
        global $post;

        // 时间胶囊页面
        if (is_page_template('page-time-capsule.php') || get_query_var('time_capsule_page')) {
            $this->enqueue_merged_css('time-capsule');
            $this->enqueue_merged_js('time-capsule');
        }

        // 包含短代码的页面
        if ($post && has_shortcode($post->post_content, 'custom_card')) {
            $this->enqueue_merged_css('custom-card');
            $this->enqueue_merged_js('custom-card');
        }

        // 检查CookieGuard是否需要显示
        $cookieguard_options = get_option('wordpress_toolkit_cookieguard_options');
        if ($cookieguard_options && !isset($_COOKIE['wordpress_toolkit_cookieguard_consent'])) {
            $this->enqueue_merged_css('cookieguard');
            $this->enqueue_merged_js('cookieguard');
        }
    }

    /**
     * 加载管理页面资源
     */
    private function load_admin_page_assets($hook) {
        // 根据不同页面加载不同模块资源
        if (strpos($hook, 'wordpress-toolkit-time-capsule') !== false) {
            $this->enqueue_merged_css('time-capsule');
            $this->enqueue_merged_js('time-capsule');
        } elseif (strpos($hook, 'wordpress-toolkit-cards') !== false) {
            $this->enqueue_merged_css('custom-card');
            $this->enqueue_merged_js('custom-card');
        } elseif (strpos($hook, 'wordpress-toolkit-friendlink') !== false) {
            $this->enqueue_merged_css('simple-friendlink');
            $this->enqueue_merged_js('simple-friendlink');
        } elseif (strpos($hook, 'wordpress-toolkit-auto-excerpt') !== false) {
            $this->enqueue_merged_css('auto-excerpt');
            $this->enqueue_merged_js('auto-excerpt');
        }
    }

    /**
     * 加载合并的CSS文件
     */
    private function enqueue_merged_css($group) {
        if (!isset($this->css_files[$group]) || empty($this->css_files[$group])) {
            return;
        }

        $handle = "wordpress-toolkit-{$group}-css";
        $merged_file = $this->get_merged_file_path('css', $group);

        if ($merged_file) {
            // 使用合并后的文件
            wp_enqueue_style(
                $handle,
                $this->cache_url . '/' . $merged_file,
                array(),
                $this->version
            );
        } else {
            // 回退到单独加载
            foreach ($this->css_files[$group] as $file => $path) {
                $url = str_replace(WORDPRESS_TOOLKIT_PLUGIN_PATH, WORDPRESS_TOOLKIT_PLUGIN_URL, $path);
                wp_enqueue_style(
                    "wordpress-toolkit-" . sanitize_title(basename($file, '.css')),
                    $url,
                    array(),
                    $this->version
                );
            }
        }
    }

    /**
     * 加载合并的JS文件
     */
    private function enqueue_merged_js($group) {
        if (!isset($this->js_files[$group]) || empty($this->js_files[$group])) {
            return;
        }

        $handle = "wordpress-toolkit-{$group}-js";
        $merged_file = $this->get_merged_file_path('js', $group);

        if ($merged_file) {
            // 使用合并后的文件
            wp_enqueue_script(
                $handle,
                $this->cache_url . '/' . $merged_file,
                array('jquery'),
                $this->version,
                true
            );
        } else {
            // 回退到单独加载
            foreach ($this->js_files[$group] as $file => $path) {
                $url = str_replace(WORDPRESS_TOOLKIT_PLUGIN_PATH, WORDPRESS_TOOLKIT_PLUGIN_URL, $path);
                wp_enqueue_script(
                    "wordpress-toolkit-" . sanitize_title(basename($file, '.js')),
                    $url,
                    array('jquery'),
                    $this->version,
                    true
                );
            }
        }
    }

    /**
     * 获取合并文件路径
     */
    private function get_merged_file_path($type, $group) {
        $files = ($type === 'css') ? $this->css_files[$group] : $this->js_files[$group];

        if (empty($files)) {
            return false;
        }

        // 生成文件哈希
        $file_hash = $this->generate_files_hash($files);
        $filename = "{$group}-{$file_hash}.{$type}";
        $filepath = $this->cache_dir . '/' . $filename;

        // 检查文件是否存在且为最新
        if (file_exists($filepath)) {
            return $filename;
        }

        // 生成合并文件
        return $this->generate_merged_file($type, $group, $files, $filepath, $filename);
    }

    /**
     * 生成文件哈希
     */
    private function generate_files_hash($files) {
        $hash_data = '';
        foreach ($files as $path) {
            if (file_exists($path)) {
                $hash_data .= filemtime($path) . filesize($path);
            }
        }
        return md5($hash_data);
    }

    /**
     * 生成合并文件
     */
    private function generate_merged_file($type, $group, $files, $filepath, $filename) {
        try {
            $merged_content = '';

            foreach ($files as $file => $path) {
                if (!file_exists($path)) {
                    continue;
                }

                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                // 添加文件注释
                $merged_content .= "\n/* File: {$file} */\n";

                // 处理CSS文件中的相对路径
                if ($type === 'css') {
                    $content = $this->process_css_urls($content, dirname($path));
                }

                // 压缩内容（如果不在调试模式）
                if (!defined('WP_DEBUG') || !WP_DEBUG) {
                    $content = ($type === 'css') ?
                        WordPress_Toolkit_Utilities::minify_css($content) :
                        WordPress_Toolkit_Utilities::minify_js($content);
                }

                $merged_content .= $content . "\n";
            }

            if (!empty($merged_content)) {
                // 写入文件
                $result = file_put_contents($filepath, $merged_content);
                if ($result !== false) {
                    return $filename;
                }
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WordPress Toolkit: Failed to generate merged {$type} file for {$group}: " . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * 处理CSS中的相对URL
     */
    private function process_css_urls($content, $base_path) {
        // 匹配url()中的相对路径
        $pattern = '/url\(\s*[\'"]?([^\'"")]+)[\'"]?\s*\)/i';

        return preg_replace_callback($pattern, function($matches) use ($base_path) {
            $url = $matches[1];

            // 跳过绝对URL、data URI等
            if (preg_match('/^(https?:\/\/|data:|\/|#)/', $url)) {
                return $matches[0];
            }

            // 转换为绝对路径
            $absolute_path = realpath($base_path . '/' . $url);
            if ($absolute_path && strpos($absolute_path, WORDPRESS_TOOLKIT_PLUGIN_PATH) === 0) {
                $relative_url = str_replace(WORDPRESS_TOOLKIT_PLUGIN_PATH, WORDPRESS_TOOLKIT_PLUGIN_URL, $absolute_path);
                return "url('{$relative_url}')";
            }

            return $matches[0];
        }, $content);
    }

    /**
     * 清理缓存
     */
    public function clear_cache() {
        $files = glob($this->cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== 'index.php' && basename($file) !== '.htaccess') {
                unlink($file);
            }
        }

        // 清理相关缓存
        wp_cache_delete('wordpress_toolkit_merged_files', 'wordpress_toolkit');
    }

    /**
     * 获取缓存统计信息
     */
    public function get_cache_stats() {
        $stats = array(
            'cache_dir' => $this->cache_dir,
            'cache_url' => $this->cache_url,
            'total_files' => 0,
            'total_size' => 0,
            'files' => array()
        );

        if (is_dir($this->cache_dir)) {
            $files = glob($this->cache_dir . '/*.{css,js}', GLOB_BRACE);
            foreach ($files as $file) {
                if (is_file($file)) {
                    $stats['total_files']++;
                    $stats['total_size'] += filesize($file);
                    $stats['files'][] = array(
                        'name' => basename($file),
                        'size' => filesize($file),
                        'modified' => filemtime($file)
                    );
                }
            }
        }

        return $stats;
    }

    /**
     * 调试模式下显示资源信息
     */
    public function debug_assets_info() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = $this->get_cache_stats();
        echo '<!-- WordPress Toolkit Assets Debug Info -->';
        echo '<!-- Cache Files: ' . $stats['total_files'] . ' -->';
        echo '<!-- Cache Size: ' . WordPress_Toolkit_Utilities::format_file_size($stats['total_size']) . ' -->';
        echo '<!-- Debug Mode: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'ON' : 'OFF') . ' -->';
        echo '<!-- End WordPress Toolkit Assets Debug Info -->';
    }

    /**
     * 获取单个资源文件的URL
     */
    public function get_asset_url($type, $group, $file) {
        $files = ($type === 'css') ? $this->css_files[$group] : $this->js_files[$group];

        if (isset($files[$file]) && file_exists($files[$file])) {
            return str_replace(WORDPRESS_TOOLKIT_PLUGIN_PATH, WORDPRESS_TOOLKIT_PLUGIN_URL, $files[$file]);
        }

        return false;
    }

    /**
     * 添加自定义CSS
     */
    public function add_custom_css($css, $group = 'custom') {
        if (empty($css)) {
            return;
        }

        $custom_file = $this->cache_dir . "/custom-{$group}.css";
        file_put_contents($custom_file, $css);

        wp_enqueue_style(
            "wordpress-toolkit-custom-{$group}",
            $this->cache_url . "/custom-{$group}.css",
            array(),
            $this->version
        );
    }

    /**
     * 添加自定义JS
     */
    public function add_custom_js($js, $group = 'custom', $deps = array('jquery')) {
        if (empty($js)) {
            return;
        }

        $custom_file = $this->cache_dir . "/custom-{$group}.js";
        file_put_contents($custom_file, $js);

        wp_enqueue_script(
            "wordpress-toolkit-custom-{$group}",
            $this->cache_url . "/custom-{$group}.js",
            $deps,
            $this->version,
            true
        );
    }
}