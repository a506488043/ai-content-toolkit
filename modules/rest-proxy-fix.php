<?php
/**
 * REST代理修复模块
 * 解决 public-api.wordpress.com 连接失败的问题
 *
 * @package WordPressToolkit
 * @subpackage Modules
 * @since 1.0.5
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Toolkit_REST_Proxy_Fix {

    /**
     * 构造函数
     */
    public function __construct() {
        add_action('init', [$this, 'init_module']);
    }

    /**
     * 获取默认设置
     */
    private function get_default_settings() {
        return [
            'enabled' => true,
            'blocked_domains' => [
                'public-api.wordpress.com',
                'rest-proxy.com',
                'wp-proxy.com'
            ],
            'protected_domains' => [
                'api.wordpress.org',
                'wordpress.org',
                'download.wordpress.org',
                's.w.org',
                'saiita.com.cn',
                'www.saiita.com.cn',
                'localhost',
                '127.0.0.1',
                'api.weixin.qq.com',
                'pay.weixin.qq.com',
                'rss.com',
                'feedburner.com',
                'feeds.feedburner.com',
                'feedly.com',
                'feedspot.com',
                'inoreader.com',
                'feedvalidator.org'
            ],
            'allowed_paths' => [
                '/feed/',
                '/rss/',
                '/atom/',
                '/rdf/',
                'feed=rss',
                'feed=atom',
                'feed=rdf',
                'wp-json/wp/v2/',
                'wp-json/watch-life-net/v1/',
                '/wp-json/',
                '/wp-admin/',
                '/wp-cron.php',
                '/wp-login.php',
                '/wp-admin/admin-ajax.php',
                '/wp-admin/admin-post.php'
            ]
        ];
    }

    /**
     * 获取模块设置
     */
    public function get_settings() {
        $default_settings = $this->get_default_settings();
        $saved_settings = get_option('wp_toolkit_rest_proxy_settings', $default_settings);

        // 合并默认设置和保存的设置，确保所有必需的键都存在
        return wp_parse_args($saved_settings, $default_settings);
    }

    /**
     * 保存模块设置
     */
    public function save_settings($settings) {
        $default_settings = $this->get_default_settings();
        $sanitized_settings = wp_parse_args($settings, $default_settings);

        // 验证和清理设置
        $sanitized_settings['enabled'] = isset($sanitized_settings['enabled']) ? (bool) $sanitized_settings['enabled'] : true;
        $sanitized_settings['blocked_domains'] = $this->sanitize_domain_list($sanitized_settings['blocked_domains']);
        $sanitized_settings['protected_domains'] = $this->sanitize_domain_list($sanitized_settings['protected_domains']);
        $sanitized_settings['allowed_paths'] = $this->sanitize_path_list($sanitized_settings['allowed_paths']);

        return update_option('wp_toolkit_rest_proxy_settings', $sanitized_settings);
    }

    /**
     * 清理域名列表
     */
    private function sanitize_domain_list($domains) {
        if (!is_array($domains)) {
            return [];
        }

        $sanitized = [];
        foreach ($domains as $domain) {
            $domain = sanitize_text_field($domain);
            if (!empty($domain)) {
                $sanitized[] = $domain;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * 清理路径列表
     */
    private function sanitize_path_list($paths) {
        if (!is_array($paths)) {
            return [];
        }

        $sanitized = [];
        foreach ($paths as $path) {
            $path = sanitize_text_field($path);
            if (!empty($path)) {
                $sanitized[] = $path;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * 初始化模块
     */
    public function init_module() {
        // 初始化钩子
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        $settings = $this->get_settings();

        // 只有在启用时才应用修复
        if ($settings['enabled']) {
            // 移除导致REST代理问题的脚本
            add_action('wp_enqueue_scripts', [$this, 'remove_problematic_scripts'], 999);

            // 禁用WordPress.com连接
            add_filter('pre_http_request', [$this, 'block_wordpress_dotcom_requests'], 10, 3);

  
            // 清理相关的transient缓存
            add_action('init', [$this, 'clear_related_cache']);
        }

        // 添加管理菜单
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // 处理表单提交
        add_action('admin_init', [$this, 'handle_form_submission']);

        // 插件激活时清理缓存
        register_activation_hook(AI_CONTENT_TOOLKIT_PLUGIN_BASENAME, [$this, 'plugin_activation']);
    }

    /**
     * 移除有问题的脚本
     */
    public function remove_problematic_scripts() {
        global $wp_scripts;

        // 查找并移除REST代理相关的脚本
        if (isset($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (strpos($script->src, 'rest-proxy') !== false ||
                    strpos($script->src, 'public-api.wordpress.com') !== false) {
                    wp_dequeue_script($handle);
                    wp_deregister_script($handle);
                }
            }
        }

        // 清理可能的问题脚本
        $problematic_handles = ['rest-proxy', 'wordpress-api-proxy', 'wp-api-proxy'];
        foreach ($problematic_handles as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
    }

    /**
     * 阻止向WordPress.com的请求
     * 只阻止有问题的WordPress.com域名，不影响本地API和小程序功能
     */
    public function block_wordpress_dotcom_requests($preempt, $r, $url) {
        $settings = $this->get_settings();

        // 获取配置的域名列表
        $blocked_domains = $settings['blocked_domains'];
        $protected_domains = $settings['protected_domains'];
        $allowed_paths = $settings['allowed_paths'];

        $parsed_url = parse_url($url);
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

        // 优先检查保护域名
        foreach ($protected_domains as $protected_domain) {
            if (strpos($host, $protected_domain) !== false) {
                return $preempt; // 允许访问保护域名
            }
        }

        // 检查允许的路径
        foreach ($allowed_paths as $allowed_path) {
            if (strpos($path, $allowed_path) !== false) {
                return $preempt; // 允许访问允许的路径
            }
        }

        // 检查是否是阻止的域名
        foreach ($blocked_domains as $blocked_domain) {
            if (strpos($host, $blocked_domain) !== false) {
                // 记录被阻止的请求

                return new WP_Error('rest_proxy_blocked', 'REST API connection blocked for security reasons.');
            }
        }

        return $preempt;
    }

  
    /**
     * 清理相关的缓存
     */
    public function clear_related_cache() {
        // 清理可能包含REST代理错误的缓存
        $transient_keys = [
            'rest_proxy_*',
            'public_api_wordpress_com_*',
            'wordpress_com_api_*'
        ];

        foreach ($transient_keys as $key_pattern) {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $key_pattern
                )
            );
        }
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        // 添加到工具箱设置菜单下的子菜单
        add_submenu_page(
            'wordpress-ai-toolkit-settings',
            'REST代理修复设置',
            'REST代理修复',
            'manage_options',
            'wp-toolkit-rest-proxy-fix',
            [$this, 'admin_page']
        );
    }

    /**
     * 处理表单提交
     */
    public function handle_form_submission() {
        if (!isset($_POST['wp_toolkit_rest_proxy_nonce']) || !wp_verify_nonce($_POST['wp_toolkit_rest_proxy_nonce'], 'wp_toolkit_rest_proxy_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();

        // 处理启用/禁用
        if (isset($_POST['save_settings'])) {
            $settings['enabled'] = isset($_POST['enabled']);
            $this->save_settings($settings);
            add_settings_error('wp_toolkit_rest_proxy', 'settings_saved', '设置已保存', 'updated');
        }

        // 处理添加阻止域名
        if (isset($_POST['add_blocked_domain']) && !empty($_POST['new_blocked_domain'])) {
            $new_domain = sanitize_text_field($_POST['new_blocked_domain']);
            if (!in_array($new_domain, $settings['blocked_domains'])) {
                $settings['blocked_domains'][] = $new_domain;
                $this->save_settings($settings);
                add_settings_error('wp_toolkit_rest_proxy', 'blocked_added', '已添加阻止域名: ' . $new_domain, 'updated');
            } else {
                add_settings_error('wp_toolkit_rest_proxy', 'blocked_exists', '该域名已在阻止列表中', 'error');
            }
        }

        // 处理添加保护域名
        if (isset($_POST['add_protected_domain']) && !empty($_POST['new_protected_domain'])) {
            $new_domain = sanitize_text_field($_POST['new_protected_domain']);
            if (!in_array($new_domain, $settings['protected_domains'])) {
                $settings['protected_domains'][] = $new_domain;
                $this->save_settings($settings);
                add_settings_error('wp_toolkit_rest_proxy', 'protected_added', '已添加保护域名: ' . $new_domain, 'updated');
            } else {
                add_settings_error('wp_toolkit_rest_proxy', 'protected_exists', '该域名已在保护列表中', 'error');
            }
        }

        // 处理添加允许路径
        if (isset($_POST['add_allowed_path']) && !empty($_POST['new_allowed_path'])) {
            $new_path = sanitize_text_field($_POST['new_allowed_path']);
            if (!in_array($new_path, $settings['allowed_paths'])) {
                $settings['allowed_paths'][] = $new_path;
                $this->save_settings($settings);
                add_settings_error('wp_toolkit_rest_proxy', 'path_added', '已添加允许路径: ' . $new_path, 'updated');
            } else {
                add_settings_error('wp_toolkit_rest_proxy', 'path_exists', '该路径已在允许列表中', 'error');
            }
        }

        // 处理删除操作
        if (isset($_POST['action']) && isset($_POST['type']) && isset($_POST['index'])) {
            $action = sanitize_text_field($_POST['action']);
            $type = sanitize_text_field($_POST['type']);
            $index = intval($_POST['index']);

            if ($action === 'delete' && isset($settings[$type]) && isset($settings[$type][$index])) {
                $removed = $settings[$type][$index];
                unset($settings[$type][$index]);
                $settings[$type] = array_values($settings[$type]); // 重新索引数组
                $this->save_settings($settings);

                $type_names = [
                    'blocked_domains' => '阻止域名',
                    'protected_domains' => '保护域名',
                    'allowed_paths' => '允许路径'
                ];

                add_settings_error('wp_toolkit_rest_proxy', 'item_deleted', '已删除' . ($type_names[$type] ?? '项目') . ': ' . $removed, 'updated');
            }
        }

        set_transient('settings_errors', get_settings_errors(), 30);
    }

    /**
     * 管理页面
     */
    public function admin_page() {
        $settings = $this->get_settings();

        // 显示设置消息
        if (get_transient('settings_errors')) {
            settings_errors('wp_toolkit_rest_proxy');
            delete_transient('settings_errors');
        }
        ?>
        <div class="wrap">
            <h1>REST代理修复设置</h1>

            <form method="post" action="">
                <?php wp_nonce_field('wp_toolkit_rest_proxy_settings', 'wp_toolkit_rest_proxy_nonce'); ?>

                <div class="notice notice-<?php echo $settings['enabled'] ? 'success' : 'warning'; ?>">
                    <p><strong><?php echo $settings['enabled'] ? '✅ 修复已启用' : '⚠️ 修复已禁用'; ?></strong></p>
                    <p><?php echo $settings['enabled'] ? 'REST代理连接问题已成功修复，插件正在运行中。' : 'REST代理修复功能已禁用，不会阻止任何请求。'; ?></p>
                </div>

                <div class="toolkit-settings-form">
                    <h2>⚙️ 基本设置</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enabled">启用REST代理修复</label>
                            </th>
                            <td>
                                <input type="checkbox" name="enabled" id="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                <p class="description">启用后，插件将阻止配置的阻止域名，并保护配置的保护域名和路径。</p>
                            </td>
                        </tr>
                    </table>

                    <div class="submit">
                        <?php submit_button('保存设置', 'primary', 'save_settings'); ?>
                    </div>
                </div>

                <div class="toolkit-settings-form">
                    <h2>🚫 阻止域名管理</h2>
                    <p>这些域名的请求将被阻止。</p>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>域名</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings['blocked_domains'] as $index => $domain): ?>
                            <tr>
                                <td data-label="域名"><?php echo esc_html($domain); ?></td>
                                <td data-label="操作">
                                    <form method="post" style="display:inline-block;">
                                        <?php wp_nonce_field('wp_toolkit_rest_proxy_settings', 'wp_toolkit_rest_proxy_nonce'); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="blocked_domains">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <input type="submit" class="button button-small" value="删除" onclick="return confirm('确定要删除这个域名吗？');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($settings['blocked_domains'])): ?>
                            <tr>
                                <td colspan="2" data-label="状态">暂无阻止域名</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h3>添加新的阻止域名</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_blocked_domain">域名</label>
                            </th>
                            <td>
                                <input type="text" name="new_blocked_domain" id="new_blocked_domain" class="regular-text" placeholder="example.com">
                                <?php submit_button('添加阻止域名', 'secondary', 'add_blocked_domain'); ?>
                                <p class="description">输入要阻止的域名（不包括协议）。</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="toolkit-settings-form">
                    <h2>✅ 保护域名管理</h2>
                    <p>这些域名的请求将被允许访问。</p>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>域名</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings['protected_domains'] as $index => $domain): ?>
                            <tr>
                                <td data-label="域名"><?php echo esc_html($domain); ?></td>
                                <td data-label="操作">
                                    <form method="post" style="display:inline-block;">
                                        <?php wp_nonce_field('wp_toolkit_rest_proxy_settings', 'wp_toolkit_rest_proxy_nonce'); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="protected_domains">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <input type="submit" class="button button-small" value="删除" onclick="return confirm('确定要删除这个域名吗？');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($settings['protected_domains'])): ?>
                            <tr>
                                <td colspan="2" data-label="状态">暂无保护域名</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h3>添加新的保护域名</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_protected_domain">域名</label>
                            </th>
                            <td>
                                <input type="text" name="new_protected_domain" id="new_protected_domain" class="regular-text" placeholder="example.com">
                                <?php submit_button('添加保护域名', 'secondary', 'add_protected_domain'); ?>
                                <p class="description">输入要保护的域名（不包括协议）。</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card">
                    <h2>允许路径管理</h2>
                    <p>包含这些路径的请求将被允许访问。</p>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>路径</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings['allowed_paths'] as $index => $path): ?>
                            <tr>
                                <td data-label="路径"><?php echo esc_html($path); ?></td>
                                <td data-label="操作">
                                    <form method="post" style="display:inline-block;">
                                        <?php wp_nonce_field('wp_toolkit_rest_proxy_settings', 'wp_toolkit_rest_proxy_nonce'); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="allowed_paths">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <input type="submit" class="button button-small" value="删除" onclick="return confirm('确定要删除这个路径吗？');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($settings['allowed_paths'])): ?>
                            <tr>
                                <td colspan="2" data-label="状态">暂无允许路径</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h3>添加新的允许路径</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_allowed_path">路径</label>
                            </th>
                            <td>
                                <input type="text" name="new_allowed_path" id="new_allowed_path" class="regular-text" placeholder="/path/">
                                <?php submit_button('添加允许路径', 'secondary', 'add_allowed_path'); ?>
                                <p class="description">输入允许的路径片段（如：/wp-json/）。</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card">
                    <h2>修复状态</h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>功能</th>
                                <th>状态</th>
                                <th>说明</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="功能">阻止问题请求</td>
                                <td data-label="状态"><?php echo $settings['enabled'] ? '✅ 已启用' : '❌ 已禁用'; ?></td>
                                <td data-label="说明">阻止配置的阻止域名</td>
                            </tr>
                            <tr>
                                <td data-label="功能">移除问题脚本</td>
                                <td data-label="状态"><?php echo $settings['enabled'] ? '✅ 已启用' : '❌ 已禁用'; ?></td>
                                <td data-label="说明">移除导致REST代理错误的脚本</td>
                            </tr>
                            <tr>
                                <td data-label="功能">域名保护</td>
                                <td data-label="状态"><?php echo $settings['enabled'] ? '✅ 已启用' : '❌ 已禁用'; ?></td>
                                <td data-label="说明">保护配置的保护域名</td>
                            </tr>
                            <tr>
                                <td data-label="功能">路径保护</td>
                                <td data-label="状态"><?php echo $settings['enabled'] ? '✅ 已启用' : '❌ 已禁用'; ?></td>
                                <td data-label="说明">允许配置的允许路径</td>
                            </tr>
                            <tr>
                                <td data-label="功能">缓存清理</td>
                                <td data-label="状态"><?php echo $settings['enabled'] ? '✅ 已启用' : '❌ 已禁用'; ?></td>
                                <td data-label="说明">清理相关的transient缓存</td>
                            </tr>
                            <tr>
                                <td data-label="功能">错误处理</td>
                                <td data-label="状态"><?php echo $settings['enabled'] ? '✅ 已启用' : '❌ 已禁用'; ?></td>
                                <td data-label="说明">提供安全的错误处理机制</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <style>
                /* 强制覆盖WordPress默认样式 */
                body.wp-admin #wpwrap .wrap {
                    max-width: none !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 20px !important;
                    box-sizing: border-box !important;
                }

                .card {
                    background: #fff !important;
                    border: 1px solid #ccd0d4 !important;
                    border-radius: 4px !important;
                    margin: 20px 0 !important;
                    padding: 20px !important;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04) !important;
                    width: 100% !important;
                    max-width: none !important;
                    box-sizing: border-box !important;
                    overflow: hidden !important;
                    float: none !important;
                }

                .card h2 {
                    margin-top: 0 !important;
                    border-bottom: 1px solid #eee !important;
                    padding-bottom: 10px !important;
                }

                .card h3 {
                    margin-top: 20px !important;
                }

                /* 表格样式强制覆盖 */
                .widefat,
                .form-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse !important;
                    margin: 0 !important;
                    table-layout: fixed !important;
                }

                .widefat th,
                .widefat td,
                .form-table th,
                .form-table td {
                    word-wrap: break-word !important;
                    overflow-wrap: break-word !important;
                }

                .form-table th {
                    width: 200px !important;
                    min-width: 150px !important;
                    max-width: 200px !important;
                    padding: 15px 10px !important;
                }

                .form-table td {
                    padding: 15px 10px !important;
                    vertical-align: top !important;
                    width: auto !important;
                }

                .regular-text {
                    width: 100% !important;
                    max-width: 400px !important;
                    min-width: 200px !important;
                }

                .button {
                    margin: 2px 0 !important;
                }

                .button-small {
                    font-size: 13px !important;
                    line-height: 2.15384615 !important;
                    height: 30px !important;
                    padding: 0 10px !important;
                }

                /* 表单提交按钮样式 */
                .submit {
                    padding: 10px 0 !important;
                    text-align: left !important;
                }

                .submit .button {
                    margin-right: 10px !important;
                }

                /* 响应式设计 */
                @media screen and (max-width: 1200px) {
                    body.wp-admin #wpwrap .wrap {
                        padding: 0 15px !important;
                    }

                    .form-table th {
                        width: 180px !important;
                        min-width: 120px !important;
                        max-width: 180px !important;
                    }
                }

                @media screen and (max-width: 782px) {
                    body.wp-admin #wpwrap .wrap {
                        padding: 0 10px !important;
                    }

                    .card {
                        margin: 10px 0 !important;
                        padding: 15px !important;
                    }

                    .form-table th,
                    .form-table td {
                        display: block !important;
                        width: 100% !important;
                        max-width: 100% !important;
                        padding: 10px 5px !important;
                    }

                    .form-table th {
                        padding-bottom: 0 !important;
                        max-width: none !important;
                        min-width: auto !important;
                    }

                    .regular-text {
                        max-width: 100% !important;
                        min-width: 150px !important;
                    }

                    .widefat {
                        font-size: 14px !important;
                    }

                    .widefat th,
                    .widefat td {
                        padding: 8px 5px !important;
                    }

                    .submit {
                        text-align: center !important;
                    }
                }

                @media screen and (max-width: 480px) {
                    body.wp-admin #wpwrap .wrap {
                        padding: 0 5px !important;
                    }

                    .card {
                        padding: 10px !important;
                    }

                    .widefat th,
                    .widefat td {
                        padding: 5px 2px !important;
                        font-size: 13px !important;
                    }

                    .button-small {
                        font-size: 12px !important;
                        height: 28px !important;
                        padding: 0 8px !important;
                    }

                    .regular-text {
                        min-width: 100px !important;
                    }
                }

                /* 确保表格在小屏幕上的可读性 */
                @media screen and (max-width: 600px) {
                    .widefat,
                    .widefat thead,
                    .widefat tbody,
                    .widefat th,
                    .widefat td,
                    .widefat tr {
                        display: block !important;
                        width: 100% !important;
                    }

                    .widefat thead tr {
                        position: absolute !important;
                        top: -9999px !important;
                        left: -9999px !important;
                    }

                    .widefat tr {
                        border: 1px solid #ccc !important;
                        margin-bottom: 10px !important;
                    }

                    .widefat td {
                        border: none !important;
                        border-bottom: 1px solid #eee !important;
                        position: relative !important;
                        padding-left: 50% !important;
                    }

                    .widefat td:before {
                        position: absolute !important;
                        top: 8px !important;
                        left: 10px !important;
                        width: 45% !important;
                        padding-right: 10px !important;
                        white-space: nowrap !important;
                        font-weight: bold !important;
                        content: attr(data-label) !important;
                    }
                }
                </style>
            </form>
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
        </style>
        <?php
    }

    /**
     * 插件激活时清理缓存
     */
    public function plugin_activation() {
        $this->clear_related_cache();

        // 添加激活日志

    }

    /**
     * 获取修复状态
     */
    public function get_status() {
        return [
            'enabled' => true,
            'blocked_domains' => [
                'public-api.wordpress.com',
                'rest-proxy.com',
                'wp-proxy.com'
            ],
            'protected_domains' => [
                'api.wordpress.org',
                'wordpress.org',
                'download.wordpress.org',
                's.w.org'
            ],
            'protected_features' => [
                '微信小程序API',
                'WordPress REST API',
                'RSS/Feed订阅',
                '本地域名访问',
                '微信支付功能',
                'WordPress官方服务(主题/插件更新)'
            ]
        ];
    }
}

// 初始化模块
new WordPress_Toolkit_REST_Proxy_Fix();
?>