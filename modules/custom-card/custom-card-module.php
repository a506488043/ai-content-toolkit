<?php
/**
 * Custom Card 模块
 * 集成原Custom Card插件的所有功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Card 模块类
 */
class Custom_Card_Module {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 模块版本
     */
    const MODULE_VERSION = '1.0.3';
    
    /**
     * 选项键名
     */
    private $option_name = 'wordpress_ai_toolkit_custom_card_options';
    
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
        // 初始化钩子将在init()中设置
    }
    
    /**
     * 激活模块
     */
    public function activate() {
        // 创建数据库表
        $this->create_tables();
        
        // 设置默认选项
        $default_options = array(
            'cache_expire_hours' => 72,
            'enable_memcached' => false,
            'enable_opcache' => true,
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
        // 检查数据库表是否存在，如果不存在则创建
        $this->check_and_create_tables();
        
        // 注册短代码
        add_shortcode('custom_card', array($this, 'shortcode_display'));
        add_shortcode('chf_card', array($this, 'shortcode_display'));
        add_shortcode('custom_card_lazy', array($this, 'shortcode_lazy_display'));
        
        // 注册AJAX处理钩子
        add_action('wp_ajax_wordpress_ai_toolkit_load_custom_card', array($this, 'handle_ajax_load_card'));
        add_action('wp_ajax_nopriv_wordpress_ai_toolkit_load_custom_card', array($this, 'handle_ajax_load_card'));
        
        // 注册点击统计AJAX处理钩子
        add_action('wp_ajax_wordpress_ai_toolkit_record_card_click', array($this, 'handle_ajax_record_click'));
        add_action('wp_ajax_nopriv_wordpress_ai_toolkit_record_card_click', array($this, 'handle_ajax_record_click'));
        
        // 注册清除缓存AJAX处理钩子
        add_action('wp_ajax_clear_custom_card_cache', array($this, 'handle_ajax_clear_cache'));

        // 注册删除卡片AJAX处理钩子
        add_action('wp_ajax_delete_custom_card', array($this, 'handle_ajax_delete_card'));

        // 注册切换卡片状态AJAX处理钩子
        add_action('wp_ajax_toggle_custom_card_status', array($this, 'handle_ajax_toggle_status'));
        
        // 注册前端脚本和样式
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));
          
        // 注册Gutenberg区块
        if (function_exists('register_block_type')) {
            $this->register_gutenberg_block();
        }
    }
    
    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting(
            'wordpress_ai_toolkit_custom_card_options',
            'wordpress_ai_toolkit_custom_card_options',
            array(
                'type' => 'array',
                'description' => 'Custom Card 模块设置',
                'sanitize_callback' => array($this, 'sanitize_options'),
                'default' => array(
                    'cache_expire_hours' => 72,
                    'enable_memcached' => false,
                    'enable_opcache' => true
                )
            )
        );
    }
    
    /**
     * 清理选项数据
     */
    public function sanitize_options($input) {
        $sanitized = array();
        
        if (isset($input['cache_expire_hours'])) {
            $sanitized['cache_expire_hours'] = absint($input['cache_expire_hours']);
            if ($sanitized['cache_expire_hours'] < 1) {
                $sanitized['cache_expire_hours'] = 1;
            }
            if ($sanitized['cache_expire_hours'] > 720) {
                $sanitized['cache_expire_hours'] = 720;
            }
        }
        
        $sanitized['enable_memcached'] = isset($input['enable_memcached']) && $input['enable_memcached'] ? true : false;
        $sanitized['enable_opcache'] = isset($input['enable_opcache']) && $input['enable_opcache'] ? true : false;
        
        return $sanitized;
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
        // 加载原Custom Card的前端样式
        wp_enqueue_style(
            'wordpress-ai-toolkit-custom-card',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/custom-card/assets/chf-card.css',
            array(),
            self::MODULE_VERSION
        );
        
        // 加载原Custom Card的前端脚本
        wp_enqueue_script(
            'wordpress-ai-toolkit-custom-card-script',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/custom-card/assets/chf-card.js',
            array('jquery'),
            self::MODULE_VERSION,
            true
        );
        
        // 传递AJAX URL
        wp_localize_script('wordpress-ai-toolkit-custom-card-script', 'chf_card_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_card_nonce'),
            'plugin_url' => AI_CONTENT_TOOLKIT_PLUGIN_URL,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
    
    /**
     * 设置页面 - 只显示基本设置（无选项卡）
     */
    public function settings_page() {
        // 获取选项
        $options = get_option('wordpress_ai_toolkit_custom_card_options');
        $cache_expire_hours = isset($options['cache_expire_hours']) ? intval($options['cache_expire_hours']) : 72;
        $enable_memcached = isset($options['enable_memcached']) ? $options['enable_memcached'] : false;
        $enable_opcache = isset($options['enable_opcache']) ? $options['enable_opcache'] : true;
        ?>

        <div class="wrap">
            <h1>网站卡片设置</h1>

            <div class="wordpress-ai-toolkit-admin-section">
                <form method="post" action="options.php">
                    <?php settings_fields('wordpress_ai_toolkit_custom_card_options'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cache_expire_hours">缓存时间（小时）</label>
                            </th>
                            <td>
                                <input type="number" id="cache_expire_hours" name="wordpress_ai_toolkit_custom_card_options[cache_expire_hours]"
                                       value="<?php echo esc_attr($cache_expire_hours); ?>" min="1" max="720" class="small-text">
                                <p class="description">设置卡片数据的缓存时间，默认为72小时。</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="enable_memcached">启用Memcached缓存</label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_memcached" name="wordpress_ai_toolkit_custom_card_options[enable_memcached]"
                                       value="1" <?php checked($enable_memcached); ?>>
                                <p class="description">如果服务器支持Memcached，可以启用此选项提高性能。</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="enable_opcache">启用OPcache缓存</label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_opcache" name="wordpress_ai_toolkit_custom_card_options[enable_opcache]"
                                       value="1" <?php checked($enable_opcache); ?>>
                                <p class="description">如果服务器支持OPcache，可以启用此选项提高性能。</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('保存设置'); ?>
                </form>
            </div>

            
            <div class="wordpress-ai-toolkit-admin-section">
                <h2>缓存管理</h2>
                <p>当前缓存设置：</p>
                <ul>
                    <li>数据库缓存：<?php echo $cache_expire_hours; ?> 小时</li>
                    <li>Memcached：<?php echo $enable_memcached ? '已启用' : '已禁用'; ?></li>
                    <li>OPcache：<?php echo $enable_opcache ? '已启用' : '已禁用'; ?></li>
                </ul>

                <button type="button" class="button button-secondary" id="clear-card-cache">清除所有缓存</button>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#clear-card-cache').on('click', function(e) {
                e.preventDefault();

                if (confirm('确定要清除所有网站卡片缓存吗？')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'clear_custom_card_cache',
                            nonce: '<?php echo wp_create_nonce('clear_custom_card_cache'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('缓存已清除');
                            } else {
                                alert('清除缓存失败：' + response.data);
                            }
                        },
                        error: function() {
                            alert('网络错误，请重试');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * 卡片列表页面 - 使用新的管理页面
     */
    public function cards_list_page() {
        // 调试日志
        if (defined('WP_DEBUG') && WP_DEBUG) {

        }

        // 包含新的管理页面
        include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/custom-card/admin/cards-admin-page.php';

        // 显示管理页面
        $admin_page = Custom_Cards_Admin_Page::get_instance();
        $admin_page->admin_page();
    }

    /**
     * 管理页面 - 保持向后兼容
     */
    public function admin_page() {
        // 调试日志
        if (defined('WP_DEBUG') && WP_DEBUG) {



        }

        // 包含原Custom Card的管理页面
        if (defined('WP_DEBUG') && WP_DEBUG) {

        }
        include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/custom-card/admin/admin-page.php';

        if (defined('WP_DEBUG') && WP_DEBUG) {

        }
    }
    
    
    /**
     * 短代码显示 - 立即加载版本
     */
    public function shortcode_display($atts) {
        $atts = shortcode_atts(array(
            'url' => '',
            'title' => '',
            'description' => '',
            'image' => '',
            'cache' => 'true'
        ), $atts, 'custom_card');
        
        // 如果没有URL，返回空
        if (empty($atts['url'])) {
            return '';
        }
        
        // 验证和清理URL
        $url = esc_url_raw($atts['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '<div class="custom-card-error">无效的URL格式</div>';
        }
        
        // 获取卡片数据
        $data = $this->get_card_data($url, $atts);
        
        // 渲染卡片
        ob_start();
        include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/custom-card/template/card.php';
        return ob_get_clean();
    }
    
    /**
     * 短代码显示 - 懒加载版本
     */
    public function shortcode_lazy_display($atts) {
        $atts = shortcode_atts(array(
            'url' => '',
            'title' => '',
            'description' => '',
            'image' => '',
            'cache' => 'true'
        ), $atts, 'custom_card_lazy');
        
        // 如果没有URL，返回空
        if (empty($atts['url'])) {
            return '';
        }
        
        // 验证和清理URL
        $url = esc_url_raw($atts['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '<div class="custom-card-error">无效的URL格式</div>';
        }
        
        // 创建懒加载占位符
        return sprintf(
            '<div class="strict-card" data-url="%s" style="background: #f5f5f5; min-height: 60px; display: flex; align-items: center; justify-content: center; color: #666; border-radius: 8px; margin: 10px 0;">
            <div class="custom-card-placeholder">网站卡片将在滚动到此处时加载...</div>
        </div>',
            esc_attr($url)
        );
    }
    
    /**
     * 获取卡片数据
     */
    private function get_card_data($url, $atts) {
        // 检查缓存
        $cache_key = 'chf_card_' . md5($url);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false && is_array($cached_data)) {
            return $cached_data;
        }
        
        // 获取选项
        $options = get_option($this->option_name);
        $cache_expire_hours = isset($options['cache_expire_hours']) ? intval($options['cache_expire_hours']) : 72;
        
        // 抓取数据
        $card_data = $this->fetch_card_data($url);
        
        // 验证数据格式
        if (!is_array($card_data)) {
            $card_data = array(
                'title' => '无法加载',
                'description' => '数据格式错误',
                'image' => '',
                'url' => $url
            );
        }
        
        // 确保必要的字段存在
        $card_data = array_merge(array(
            'title' => '无标题',
            'description' => '无描述',
            'image' => '',
            'url' => $url
        ), $card_data);
        
        // 缓存数据
        set_transient($cache_key, $card_data, $cache_expire_hours * HOUR_IN_SECONDS);
        
        // 创建或更新卡片记录
        $this->create_or_update_card($card_data);
        
        return $card_data;
    }
    
    /**
     * 创建或更新卡片记录
     */
    private function create_or_update_card($card_data) {
        global $wpdb;
        $cards_table = $wpdb->prefix . 'chf_cards';
        
        // 检查卡片是否已存在
        $existing_card = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $cards_table WHERE url = %s",
            $card_data['url']
        ));
        
        $card_record = array(
            'url' => $card_data['url'],
            'title' => $card_data['title'],
            'description' => $card_data['description'],
            'image' => isset($card_data['image']) ? $card_data['image'] : '',
            'icon' => isset($card_data['icon']) ? $card_data['icon'] : '',
            'status' => 'active',
            'updated_at' => current_time('mysql')
        );
        
        if ($existing_card) {
            // 更新现有卡片
            $wpdb->update($cards_table, $card_record, array('id' => $existing_card->id));
            return $existing_card->id;
        } else {
            // 创建新卡片
            $card_record['created_at'] = current_time('mysql');
            $wpdb->insert($cards_table, $card_record);
            return $wpdb->insert_id;
        }
    }
    
    /**
     * 记录卡片点击
     */
    public function record_card_click($card_id) {
        global $wpdb;
        $clicks_table = $wpdb->prefix . 'chf_card_clicks';
        
        // 获取用户信息
        $user_ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : '';
        
        $click_data = array(
            'card_id' => $card_id,
            'ip_address' => $user_ip,
            'user_agent' => $user_agent,
            'referer' => $referer,
            'clicked_at' => current_time('mysql')
        );
        
        $wpdb->insert($clicks_table, $click_data);
        return $wpdb->insert_id;
    }
    
    /**
     * 获取客户端IP地址
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 处理多个IP的情况（如X-Forwarded-For）
                if (strpos($ip, ',') !== false) {
                    $ip_list = explode(',', $ip);
                    $ip = trim($ip_list[0]);
                }
                
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * 获取卡片ID
     */
    private function get_card_id_by_url($url) {
        global $wpdb;
        $cards_table = $wpdb->prefix . 'chf_cards';
        
        $card_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $cards_table WHERE url = %s",
            $url
        ));
        
        return $card_id ? intval($card_id) : null;
    }
    
    /**
     * 抓取卡片数据
     */
    private function fetch_card_data($url) {
        // 使用WordPress的HTTP API
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
            )
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return array(
                'title' => '无法加载',
                'description' => '无法获取网站信息: ' . $error_message,
                'image' => '',
                'url' => $url
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return array(
                'title' => '无法加载',
                'description' => 'HTTP错误: ' . $response_code,
                'image' => '',
                'url' => $url
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return array(
                'title' => '无法加载',
                'description' => '网站内容为空',
                'image' => '',
                'url' => $url
            );
        }
        
        // 解析HTML获取元数据
        $card_data = array(
            'url' => $url,
            'title' => $this->extract_title($body),
            'description' => $this->extract_description($body),
            'image' => $this->extract_image($body),
            'icon' => $this->extract_icon($body, $url)
        );
        
        return $card_data;
    }
    
    /**
     * 提取标题
     */
    private function extract_title($html) {
        // 尝试从<title>标签提取
        if (preg_match('/<title[^>]*>(.*?)<\\/title>/is', $html, $matches)) {
            $title = trim($matches[1]);
            if (!empty($title)) {
                return $title;
            }
        }
        
        // 尝试从og:title提取
        if (preg_match('/<meta[^>]*property=[\'"]og:title[\'"][^>]*content=[\'"]([^\'"]*)[\'"][^>]*>/is', $html, $matches)) {
            $title = trim($matches[1]);
            if (!empty($title)) {
                return $title;
            }
        }
        
        // 尝试从h1标签提取
        if (preg_match('/<h1[^>]*>(.*?)<\\/h1>/is', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
            if (!empty($title)) {
                return $title;
            }
        }
        
        return '无标题';
    }
    
    /**
     * 提取描述
     */
    private function extract_description($html) {
        // 尝试Open Graph描述
        if (preg_match('/<meta[^>]*property=[\'"]og:description[\'"][^>]*content=[\'"]([^\'"]*)[\'"][^>]*>/is', $html, $matches)) {
            $desc = trim($matches[1]);
            if (!empty($desc)) {
                return $desc;
            }
        }
        
        // 尝试meta描述
        if (preg_match('/<meta[^>]*name=[\'"]description[\'"][^>]*content=[\'"]([^\'"]*)[\'"][^>]*>/is', $html, $matches)) {
            $desc = trim($matches[1]);
            if (!empty($desc)) {
                return $desc;
            }
        }
        
        // 尝试从第一个p标签提取
        if (preg_match('/<p[^>]*>(.*?)<\\/p>/is', $html, $matches)) {
            $desc = trim(strip_tags($matches[1]));
            if (!empty($desc) && strlen($desc) > 10) {
                return substr($desc, 0, 200);
            }
        }
        
        return '无描述';
    }
    
    /**
     * 提取图片
     */
    private function extract_image($html) {
        // 尝试Open Graph图片
        if (preg_match('/<meta[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]*)[\'"][^>]*>/is', $html, $matches)) {
            $image = trim($matches[1]);
            if (!empty($image)) {
                return $image;
            }
        }
        
        // 尝试Twitter Card图片
        if (preg_match('/<meta[^>]*name=[\'"]twitter:image[\'"][^>]*content=[\'"]([^\'"]*)[\'"][^>]*>/is', $html, $matches)) {
            $image = trim($matches[1]);
            if (!empty($image)) {
                return $image;
            }
        }
        
        // 尝试从第一个img标签提取
        if (preg_match('/<img[^>]*src=[\'"]([^\'"]*)[\'"][^>]*>/is', $html, $matches)) {
            $image = trim($matches[1]);
            if (!empty($image) && filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }
        }
        
        return '';
    }
    
    /**
     * 提取网站图标
     */
    private function extract_icon($html, $url) {
        // 尝试从link标签提取favicon
        $patterns = array(
            '/<link[^>]*rel=[\'"](?:shortcut )?icon[\'"][^>]*href=[\'"]([^\'"]*)[\'"][^>]*>/is',
            '/<link[^>]*href=[\'"]([^\'"]*)[\'"][^>]*rel=[\'"](?:shortcut )?icon[\'"][^>]*>/is'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $icon = trim($matches[1]);
                if (!empty($icon)) {
                    // 处理相对路径
                    if (!filter_var($icon, FILTER_VALIDATE_URL)) {
                        $url_parts = parse_url($url);
                        $base_url = $url_parts['scheme'] . '://' . $url_parts['host'];
                        if (substr($icon, 0, 1) === '/') {
                            $icon = $base_url . $icon;
                        } else {
                            $icon = $base_url . '/' . $icon;
                        }
                    }
                    
                    if (filter_var($icon, FILTER_VALIDATE_URL)) {
                        return $icon;
                    }
                }
            }
        }
        
        // 尝试默认favicon位置
        $url_parts = parse_url($url);
        if (isset($url_parts['host'])) {
            $default_icon = $url_parts['scheme'] . '://' . $url_parts['host'] . '/favicon.ico';
            return $default_icon;
        }
        
        return '';
    }
    
    /**
     * 检查并创建数据库表
     */
    private function check_and_create_tables() {
        global $wpdb;
        
        $cards_table = $wpdb->prefix . 'chf_cards';
        $clicks_table = $wpdb->prefix . 'chf_card_clicks';
        
        // 检查表是否存在
        $cards_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cards_table'") == $cards_table;
        $clicks_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clicks_table'") == $clicks_table;
        
        // 如果表不存在，则创建
        if (!$cards_table_exists || !$clicks_table_exists) {
            $this->create_tables();
        }
    }
    
    /**
     * 创建数据库表
     */
    private function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $cache_table = $wpdb->prefix . 'chf_card_cache';
        $cards_table = $wpdb->prefix . 'chf_cards';
        $clicks_table = $wpdb->prefix . 'chf_card_clicks';
        $charset_collate = $wpdb->get_charset_collate();
        
        // 缓存表
        $sql = "CREATE TABLE IF NOT EXISTS $cache_table (
            url_hash CHAR(32) NOT NULL COMMENT 'URL的MD5哈希',
            url VARCHAR(512) NOT NULL COMMENT '原始URL',
            title VARCHAR(255) NOT NULL DEFAULT '' COMMENT '卡片标题',
            image VARCHAR(512) NOT NULL DEFAULT '' COMMENT '图片URL',
            description TEXT NOT NULL COMMENT '描述内容',
            expires_at DATETIME NOT NULL COMMENT '缓存失效时间',
            PRIMARY KEY (url_hash),
            INDEX url_index (url(191))
        ) $charset_collate";
        
        dbDelta($sql);
        
        // 卡片管理表
        $sql = "CREATE TABLE IF NOT EXISTS $cards_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(512) NOT NULL COMMENT '网站URL',
            title VARCHAR(255) NOT NULL DEFAULT '' COMMENT '卡片标题',
            description TEXT NOT NULL COMMENT '描述内容',
            image VARCHAR(512) NOT NULL DEFAULT '' COMMENT '图片URL',
            icon VARCHAR(512) NOT NULL DEFAULT '' COMMENT '网站图标',
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
            PRIMARY KEY (id),
            UNIQUE KEY url_unique (url(191)),
            INDEX status_index (status),
            INDEX created_at_index (created_at)
        ) $charset_collate";
        
        dbDelta($sql);
        
        // 点击统计表
        $sql = "CREATE TABLE IF NOT EXISTS $clicks_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id BIGINT(20) UNSIGNED NOT NULL COMMENT '卡片ID',
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点击时间',
            ip_address VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'IP地址',
            user_agent TEXT NOT NULL COMMENT '用户代理',
            referer VARCHAR(512) NOT NULL DEFAULT '' COMMENT '来源页面',
            PRIMARY KEY (id),
            INDEX card_id_index (card_id),
            INDEX clicked_at_index (clicked_at)
        ) $charset_collate";
        
        dbDelta($sql);
    }
    
    /**
     * 注册Gutenberg区块
     */
    private function register_gutenberg_block() {
        // 使用block.json文件注册Gutenberg区块
        register_block_type(
            AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/custom-card/blocks/custom-card',
            array(
                'render_callback' => array($this, 'shortcode_display')
            )
        );
    }
    
    /**
     * 处理AJAX加载卡片
     */
    public function handle_ajax_load_card() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'custom_card_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // 获取URL
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error(array('message' => 'URL不能为空'));
        }
        
        // 验证URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => '无效的URL格式'));
        }
        
        // 获取卡片数据
        $atts = array(
            'url' => $url,
            'title' => '',
            'description' => '',
            'image' => '',
            'cache' => 'true'
        );
        
        try {
            $data = $this->get_card_data($url, $atts);
            
            // 验证数据
            if (!is_array($data) || !isset($data['url'])) {
                wp_send_json_error(array('message' => '获取卡片数据失败'));
            }
            
            // 渲染卡片HTML
            ob_start();
            include AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/custom-card/template/card.php';
            $html = ob_get_clean();
            
            if (empty($html)) {
                wp_send_json_error(array('message' => '渲染卡片失败'));
            }
            
            wp_send_json_success(array('html' => $html));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '处理卡片时发生错误: ' . $e->getMessage()));
        }
    }
    
    /**
     * 处理AJAX记录点击
     */
    public function handle_ajax_record_click() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'custom_card_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // 获取卡片ID
        $card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;
        
        if (empty($card_id)) {
            wp_send_json_error(array('message' => '卡片ID不能为空'));
        }
        
        try {
            // 记录点击
            $click_id = $this->record_card_click($card_id);
            
            if ($click_id) {
                wp_send_json_success(array('message' => '点击已记录', 'click_id' => $click_id));
            } else {
                wp_send_json_error(array('message' => '记录点击失败'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '记录点击时发生错误: ' . $e->getMessage()));
        }
    }
    
    /**
     * 处理AJAX清除缓存
     */
    public function handle_ajax_clear_cache() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'clear_custom_card_cache')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        try {
            // 清除所有卡片缓存
            global $wpdb;

            // 清除transient缓存
            $cache_keys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_chf_card_%'");
            foreach ($cache_keys as $cache_key) {
                $transient_name = str_replace('_transient_', '', $cache_key);
                delete_transient($transient_name);
            }

            // 清除缓存表（如果有）
            $cache_table = $wpdb->prefix . 'chf_card_cache';
            if ($wpdb->get_var("SHOW TABLES LIKE '$cache_table'") == $cache_table) {
                $wpdb->query("TRUNCATE TABLE $cache_table");
            }

            // 清除WordPress对象缓存
            wp_cache_flush();

            wp_send_json_success(array('message' => '缓存已成功清除'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '清除缓存时发生错误: ' . $e->getMessage()));
        }
    }

    /**
     * 处理AJAX删除卡片
     */
    public function handle_ajax_delete_card() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'delete_custom_card')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        // 获取卡片ID
        $card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;

        if (empty($card_id)) {
            wp_send_json_error(array('message' => '卡片ID不能为空'));
        }

        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '您没有权限删除卡片'));
        }

        global $wpdb;

        try {
            // 开始事务
            $wpdb->query('START TRANSACTION');

            $cards_table = $wpdb->prefix . 'chf_cards';
            $clicks_table = $wpdb->prefix . 'chf_card_clicks';

            // 检查卡片是否存在
            $card = $wpdb->get_row($wpdb->prepare(
                "SELECT id, url FROM $cards_table WHERE id = %d",
                $card_id
            ));

            if (!$card) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => '卡片不存在'));
            }

            // 删除相关的点击记录
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $clicks_table WHERE card_id = %d",
                $card_id
            ));

            // 删除卡片
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $cards_table WHERE id = %d",
                $card_id
            ));

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => '删除卡片失败'));
            }

            // 删除相关的缓存
            $cache_key = 'chf_card_' . md5($card->url);
            delete_transient($cache_key);

            // 提交事务
            $wpdb->query('COMMIT');

            wp_send_json_success(array('message' => '卡片删除成功'));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => '删除卡片时发生错误: ' . $e->getMessage()));
        }
    }

    /**
     * 处理AJAX切换卡片状态
     */
    public function handle_ajax_toggle_status() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'toggle_custom_card_status')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        // 获取卡片ID和新状态
        $card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

        if (empty($card_id)) {
            wp_send_json_error(array('message' => '卡片ID不能为空'));
        }

        if (!in_array($new_status, array('active', 'inactive'))) {
            wp_send_json_error(array('message' => '无效的状态值'));
        }

        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '您没有权限修改卡片状态'));
        }

        global $wpdb;

        try {
            $cards_table = $wpdb->prefix . 'chf_cards';

            // 检查卡片是否存在
            $card = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $cards_table WHERE id = %d",
                $card_id
            ));

            if (!$card) {
                wp_send_json_error(array('message' => '卡片不存在'));
            }

            // 更新卡片状态
            $result = $wpdb->update(
                $cards_table,
                array(
                    'status' => $new_status,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $card_id),
                array('%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                wp_send_json_error(array('message' => '更新卡片状态失败'));
            }

            wp_send_json_success(array('message' => '卡片状态更新成功'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '切换卡片状态时发生错误: ' . $e->getMessage()));
        }
    }

    /**
     * 记录日志
     */
    private function log_message($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_level = strtoupper($level);
            if ($log_level === 'ERROR') {
                wt_log_error($message, 'custom-card', array('level' => $level));
            } else {
                wt_log_debug($message, 'custom-card', array('level' => $level));
            }
        }
    }
}
