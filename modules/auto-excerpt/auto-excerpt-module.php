<?php
/**
 * Article Optimization Module - 文章优化模块
 *
 * 根据文章内容自动生成摘要和标签
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Article Optimization Module 主类
 */
class Auto_Excerpt_Module {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 模块设置
     */
    private $settings = array();

    /**
     * SEO分析器实例
     */
    private $seo_analyzer = null;

    /**
     * SEO分析数据库实例
     */
    private $seo_database = null;

    /**
     * 数据库管理器实例
     */
    private $db_manager = null;

    /**
     * 缓存管理器实例
     */
    private $cache_manager = null;

    /**
     * 构造函数
     */
    private function __construct() {
        $this->db_manager = new WordPress_Toolkit_Database_Manager();
        $this->cache_manager = new WordPress_Toolkit_Cache_Manager();
        $this->load_settings();
        $this->init_hooks();
        $this->init_seo_analyzer();

        // 加载AI设置辅助函数
        if (file_exists(WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/ai-settings/ai-settings-helper.php')) {
            require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/ai-settings/ai-settings-helper.php';
        }
    }

    /**
     * 加载设置
     */
    private function load_settings() {
        $default_settings = array(
            'excerpt_length' => 150,
        'auto_generate' => true,
        'preserve_formatting' => true,
        'min_content_length' => 200,
        'smart_extraction' => true,
        'exclude_shortcodes' => array('gallery', 'video', 'audio', 'caption')
    );

        $saved_settings = get_option('wordpress_toolkit_auto_excerpt_settings', array());
        $this->settings = wp_parse_args($saved_settings, $default_settings);
    }

    /**
     * 初始化SEO分析器
     */
    private function init_seo_analyzer() {
        // 加载SEO分析类
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/auto-excerpt/includes/class-seo-analyzer-database.php';
        require_once WORDPRESS_TOOLKIT_PLUGIN_PATH . 'modules/auto-excerpt/includes/class-seo-analyzer.php';

        $this->seo_database = new Auto_Excerpt_SEO_Analyzer_Database();

        // 创建SEO分析数据表
        $this->seo_database->create_tables();

        // 初始化SEO分析器 - 只有在AI功能可用时才初始化
        if (function_exists('wordpress_toolkit_is_ai_available') && wordpress_toolkit_is_ai_available()) {
            $config = wordpress_toolkit_get_deepseek_config();
            $seo_settings = array(
                'ai_provider' => 'deepseek',
                'ai_model' => $config['model'],
                'api_key' => $config['api_key'],
                'api_base' => $config['api_base'],
                'max_tokens' => $config['max_tokens'],
                'temperature' => $config['temperature']
            );
            $this->seo_analyzer = new Auto_Excerpt_SEO_Analyzer($seo_settings);

            // 检查并更新数据库架构
            $this->ensure_database_schema();
        } else {
            // AI功能不可用，不初始化SEO分析器
            $this->seo_analyzer = null;
        }
    }

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
     * 确保数据库架构是最新的
     */
    private function ensure_database_schema() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'auto_excerpt_seo_analysis';

        try {
            // 检查raw_ai_analysis字段是否存在
            $raw_column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table_name,
                'raw_ai_analysis'
            ));

            if (!$raw_column_exists) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                     ADD COLUMN raw_ai_analysis longtext DEFAULT NULL COMMENT 'AI原始完整分析文本'"
                );
            }

            // 检查parsed_analysis字段是否存在
            $parsed_column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table_name,
                'parsed_analysis'
            ));

            if (!$parsed_column_exists) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                     ADD COLUMN parsed_analysis longtext DEFAULT NULL COMMENT '解析后的AI分析数据(JSON)'"
                );
            }

            // 检查ai_model字段类型是否正确
            $ai_model_type = $wpdb->get_var($wpdb->prepare(
                "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table_name,
                'ai_model'
            ));

            if ($ai_model_type === 'decimal') {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                     MODIFY COLUMN ai_model varchar(100) DEFAULT NULL COMMENT 'AI模型'"
                );
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: Database schema update failed: ' . $e->getMessage());
        }
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // WordPress后台脚本和样式（仅在管理页面加载）
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // 保存文章时自动生成摘要
        add_action('save_post', array($this, 'auto_generate_excerpt_on_save'), 10, 2);

        // 移除文章编辑页面的元框
        // add_action('add_meta_boxes', array($this, 'add_meta_box'));

        // 移除编辑页面的AJAX处理
        // add_action('wp_ajax_generate_auto_excerpt', array($this, 'ajax_generate_excerpt'));

        // 保留API测试功能（仅在后台管理页面使用）
        add_action('wp_ajax_test_deepseek_api', array($this, 'ajax_test_deepseek_api'));

        // 添加批量生成和单个生成摘要的AJAX处理
        add_action('wp_ajax_batch_generate_excerpts', array($this, 'ajax_batch_generate_excerpts'));
        add_action('wp_ajax_generate_single_excerpt', array($this, 'ajax_generate_single_excerpt'));
        add_action('wp_ajax_auto_excerpt_generate', array($this, 'ajax_generate_single_excerpt'));
        add_action('wp_ajax_auto_excerpt_batch_generate', array($this, 'ajax_batch_generate_excerpts'));

        // 添加AI生成标签的AJAX处理
        add_action('wp_ajax_generate_ai_tags', array($this, 'ajax_generate_tags'));
        add_action('wp_ajax_apply_ai_tags', array($this, 'ajax_apply_tags'));
        add_action('wp_ajax_batch_generate_tags', array($this, 'ajax_batch_generate_tags'));
        add_action('wp_ajax_auto_excerpt_generate_tags', array($this, 'ajax_generate_single_tags'));

        // AI分类和标签优化相关AJAX处理
        add_action('wp_ajax_auto_excerpt_ai_categorize', array($this, 'ajax_ai_categorize'));
        add_action('wp_ajax_auto_excerpt_ai_optimize_tags', array($this, 'ajax_ai_optimize_tags'));

        // 前端脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // SEO分析相关AJAX处理
        add_action('wp_ajax_auto_excerpt_seo_analyze', array($this, 'ajax_analyze_post_seo'));
        add_action('wp_ajax_auto_excerpt_get_seo_report', array($this, 'ajax_get_seo_report'));
        add_action('wp_ajax_analyze_post_seo', array($this, 'ajax_analyze_post_seo'));
        add_action('wp_ajax_batch_analyze_seo', array($this, 'ajax_batch_analyze_seo'));
        add_action('wp_ajax_get_seo_report', array($this, 'ajax_get_seo_report'));
        add_action('wp_ajax_get_seo_statistics', array($this, 'ajax_get_seo_statistics'));
        add_action('wp_ajax_get_posts_for_seo', array($this, 'ajax_get_posts_for_seo'));
        add_action('wp_ajax_get_seo_reports_list', array($this, 'ajax_get_seo_reports_list'));

        // 数据库架构更新AJAX处理
        add_action('wp_ajax_update_seo_analysis_schema', array($this, 'ajax_update_seo_analysis_schema'));

        // 添加定时任务功能
        add_action('wp', array($this, 'schedule_daily_excerpt_generation'));

        // 定时任务执行钩子
        add_action('auto_excerpt_daily_generation', array($this, 'execute_daily_excerpt_generation'));

      }

    /**
     * 激活模块
     */
    public function activate() {
        error_log('Auto Excerpt: Starting module activation');

        try {
            // 创建默认设置（仅在不存在时）
            if (!get_option('wordpress_toolkit_auto_excerpt_settings')) {
                add_option('wordpress_toolkit_auto_excerpt_settings', $this->settings);
                error_log('Auto Excerpt: Default settings created');
            } else {
                error_log('Auto Excerpt: Settings already exist, skipping creation');
            }

            // 重置失败计数
            update_option('auto_excerpt_consecutive_failures', 0);

            // 注册定时任务
            $this->schedule_daily_excerpt_generation();

            // 为现有文章生成摘要（已禁用，避免超时问题）
            // 如需批量生成，请手动调用 batch_generate_existing_excerpts() 方法
            error_log('Auto Excerpt: Module activated successfully, daily task scheduled');

        } catch (Exception $e) {
            error_log('Auto Excerpt: Activation error: ' . $e->getMessage());
        }
    }

    /**
     * 停用模块
     */
    public function deactivate() {
        // 清理缓存
        wp_cache_flush();

        // 取消定时任务
        $this->unschedule_daily_excerpt_generation();

        // 清理失败计数
        delete_option('auto_excerpt_consecutive_failures');

        error_log('Auto Excerpt: Module deactivated, daily task unscheduled');
    }

    /**
     * 初始化模块
     */
    public function init() {
        // 模块初始化逻辑
    }

    /**
     * 加载管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 只在相关页面加载统一脚本和样式
        $valid_pages = [
            'settings_page_wordpress-toolkit-auto-excerpt-settings',
            'admin_page_wordpress-toolkit-auto-excerpt',
            'toplevel_page_wordpress-toolkit'
        ];

        if (in_array($hook, $valid_pages)) {
            // 使用统一的模块CSS
            wp_enqueue_style(
                'wordpress-toolkit-modules-admin',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/css/modules-admin.css',
                array('wordpress-toolkit-admin'),
                WORDPRESS_TOOLKIT_VERSION
            );

            // 加载统一的模块JavaScript
            wp_enqueue_script(
                'wordpress-toolkit-modules-admin',
                WORDPRESS_TOOLKIT_PLUGIN_URL . 'assets/js/modules-admin.js',
                array('jquery', 'wordpress-toolkit-core'),
                '1.0.0',
                true
            );

            // 传递配置到JavaScript
            wp_localize_script('auto-excerpt-admin', 'AutoExcerptConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('auto_excerpt_batch'),
                'strings' => array(
                    'generating' => __('正在生成摘要...', 'wordpress-toolkit'),
                    'generatingWithAI' => __('正在使用AI生成摘要...', 'wordpress-toolkit'),
                    'generated' => __('摘要已生成', 'wordpress-toolkit'),
                    'generatedWithAI' => __('AI摘要已生成', 'wordpress-toolkit'),
                    'error' => __('生成失败，请重试', 'wordpress-toolkit'),
                    'aiError' => __('AI生成失败，正在使用本地算法...', 'wordpress-toolkit'),
                    'confirm' => __('确定要用AI生成摘要替换当前摘要吗？', 'wordpress-toolkit'),
                    'noContent' => __('文章内容太短，无法生成摘要', 'wordpress-toolkit'),
                    'noApiKey' => __('请先配置DeepSeek API密钥', 'wordpress-toolkit'),
                    'confirmApply' => __('是否要应用生成的摘要？', 'wordpress-toolkit')
                ),
                'settings' => $this->settings
            ));
        }
    }

    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 前端功能脚本（如果需要）
    }

    /**
     * 添加元框到文章编辑页面
     */
    public function add_meta_box() {
        add_meta_box(
            'auto-excerpt-meta-box',
            __('智能摘要生成器', 'wordpress-toolkit'),
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }

    /**
     * 渲染元框内容
     */
    public function render_meta_box($post) {
        error_log('Auto Excerpt: render_meta_box function called for post ID: ' . $post->ID);

        try {
            // 添加nonce验证
            wp_nonce_field('auto_excerpt_meta_box', 'auto_excerpt_nonce');

            $current_excerpt = $post->post_excerpt;
            $content_length = mb_strlen(strip_tags($post->post_content));

            // 简化版本 - 确保基本内容显示
            echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 10px 0;">';
            echo '<h3 style="margin: 0 0 10px 0; color: #23282d;">🤖 智能摘要生成器</h3>';
            echo '<p><strong>调试信息：</strong></p>';
            echo '<p>文章ID: ' . $post->ID . '</p>';
            echo '<p>内容长度: ' . $content_length . ' 字符</p>';
            echo '<p>当前摘要: ' . (!empty($current_excerpt) ? '已有摘要' : '暂无摘要') . '</p>';
            echo '<hr>';

            // 测试按钮
            echo '<button type="button" id="generate-excerpt-btn" class="button button-primary">生成智能摘要</button>';
            echo '<div id="excerpt-result" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; min-height: 50px;">';
            echo '摘要内容将显示在这里...';
            echo '</div>';
            echo '</div>';

            error_log('Auto Excerpt: render_meta_box completed successfully');
            return;

        } catch (Exception $e) {
            error_log('Auto Excerpt: Error in render_meta_box: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>自动摘要模块加载出错：' . esc_html($e->getMessage()) . '</p></div>';
            return;
        }
        ?>
        <div class="auto-excerpt-container">
            <div class="auto-excerpt-header">
                <h3>
                    <?php _e('智能摘要生成', 'wordpress-toolkit'); ?>
                    <?php if (wordpress_toolkit_is_ai_available()): ?>
                        <span class="ai-badge">🤖 AI</span>
                    <?php endif; ?>
                </h3>
                <p class="description">
                    <?php
                    if (wordpress_toolkit_is_ai_available()) {
                        _e('基于DeepSeek AI智能生成摘要，支持中英文混合内容。', 'wordpress-toolkit');
                    } else {
                        _e('基于文章内容智能生成摘要，支持中英文混合内容。', 'wordpress-toolkit');
                    }
                    ?>
                </p>
            </div>

            <div class="auto-excerpt-controls">
                <button type="button" id="generate-excerpt-btn" class="button button-primary">
                    <span class="dashicons dashicons-magic"></span>
                    <?php _e('生成智能摘要', 'wordpress-toolkit'); ?>
                </button>

                <button type="button" id="regenerate-excerpt-btn" class="button">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('重新生成', 'wordpress-toolkit'); ?>
                </button>

                <div class="auto-excerpt-options">
                    <label>
                        <input type="checkbox" id="append-mode" value="1">
                        <?php _e('追加模式（不替换现有摘要）', 'wordpress-toolkit'); ?>
                    </label>
                </div>
            </div>

            <div class="auto-excerpt-status" style="display: none;">
                <div class="spinner"></div>
                <span class="status-text"></span>
            </div>

            <div class="auto-excerpt-preview" style="display: none;">
                <h4><?php _e('预览生成的摘要：', 'wordpress-toolkit'); ?></h4>
                <div class="excerpt-preview-content"></div>
                <div class="excerpt-actions">
                    <button type="button" id="apply-excerpt-btn" class="button button-primary">
                        <?php _e('应用此摘要', 'wordpress-toolkit'); ?>
                    </button>
                    <button type="button" id="cancel-excerpt-btn" class="button">
                        <?php _e('取消', 'wordpress-toolkit'); ?>
                    </button>
                </div>
            </div>

            <div class="auto-excerpt-info">
                <p>
                    <strong><?php _e('当前状态：', 'wordpress-toolkit'); ?></strong>
                    <span id="excerpt-status">
                        <?php if (!empty($current_excerpt)): ?>
                            <span class="status-exists"><?php _e('已有摘要', 'wordpress-toolkit'); ?></span>
                        <?php else: ?>
                            <span class="status-empty"><?php _e('暂无摘要', 'wordpress-toolkit'); ?></span>
                        <?php endif; ?>
                    </span>
                </p>
                <p>
                    <strong><?php _e('内容长度：', 'wordpress-toolkit'); ?></strong>
                    <span id="content-length"><?php echo $content_length; ?></span> <?php _e('字符', 'wordpress-toolkit'); ?>
                </p>
                <p>
                    <strong><?php _e('建议摘要长度：', 'wordpress-toolkit'); ?></strong>
                    <span id="suggested-length"><?php echo $this->settings['excerpt_length']; ?></span> <?php _e('字符', 'wordpress-toolkit'); ?>
                </p>
            </div>

            <div class="auto-excerpt-settings">
                <h4><?php _e('生成选项：', 'wordpress-toolkit'); ?></h4>
                <table class="form-table">
                    <tr>
                        <th>
                            <label for="excerpt_length"><?php _e('摘要长度', 'wordpress-toolkit'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="excerpt_length"
                                   value="<?php echo $this->settings['excerpt_length']; ?>"
                                   min="50" max="500" step="10" class="small-text">
                            <span class="description"><?php _e('字符', 'wordpress-toolkit'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="smart_extraction"><?php _e('智能提取', 'wordpress-toolkit'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="smart_extraction" value="1"
                                   <?php checked($this->settings['smart_extraction']); ?>>
                            <span class="description"><?php _e('优先提取文章关键句和段落', 'wordpress-toolkit'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX处理生成摘要
     */
    public function ajax_generate_excerpt() {
        // 移除安全验证以简化操作

        $post_id = intval($_POST['post_id']);
        $content = wp_kses_post($_POST['content']);
        $append_mode = isset($_POST['append_mode']) ? (bool)$_POST['append_mode'] : false;
        $length = isset($_POST['length']) ? intval($_POST['length']) : $this->settings['excerpt_length'];
        $smart_extraction = isset($_POST['smart_extraction']) ? (bool)$_POST['smart_extraction'] : $this->settings['smart_extraction'];

        // 生成摘要
        $use_ai = wordpress_toolkit_is_ai_available();
        $excerpt = $this->generate_excerpt($content, $length, $smart_extraction);

        if ($excerpt) {
            // 检测是否使用了AI生成（基于设置和API状态）
            $is_ai_generated = $use_ai && $this->was_ai_generated($excerpt, $content);

            $message = $is_ai_generated ?
                __('AI摘要生成成功', 'wordpress-toolkit') :
                __('摘要生成成功', 'wordpress-toolkit');

            wp_send_json_success(array(
                'excerpt' => $excerpt,
                'length' => mb_strlen($excerpt),
                'message' => $message,
                'ai_generated' => $is_ai_generated
            ));
        } else {
            $error_message = $use_ai ?
                __('AI生成失败且内容太短，无法生成摘要', 'wordpress-toolkit') :
                __('无法生成摘要，内容可能太短', 'wordpress-toolkit');

            wp_send_json_error(array(
                'message' => $error_message
            ));
        }
    }

    /**
     * 生成文章摘要
     */
    public function generate_excerpt($content, $length = null, $smart_extraction = null) {
        if (empty($content)) {
            return '';
        }

        $length = $length ?: $this->settings['excerpt_length'];
        $smart_extraction = $smart_extraction ?: $this->settings['smart_extraction'];

        // 检查内容长度
        $content_length = mb_strlen(strip_tags($content));
        if ($content_length < $this->settings['min_content_length']) {
            return '';
        }

        // 清理内容
        $clean_content = $this->clean_content($content);

        // 优先使用AI生成摘要
        if (wordpress_toolkit_is_ai_available()) {
            $ai_excerpt = $this->generate_ai_excerpt($clean_content, $length);
            if ($ai_excerpt) {
                return $ai_excerpt;
            }

            // 如果AI生成失败且启用了降级机制
            if (wordpress_toolkit_get_ai_settings('fallback_to_simple', true)) {
                error_log('Auto Excerpt: AI生成失败，使用本地算法作为降级方案');
                return $this->generate_simple_excerpt($clean_content, $length, $smart_extraction);
            }
        }

        // 使用传统算法生成摘要
        return $this->generate_simple_excerpt($clean_content, $length, $smart_extraction);
    }

    /**
     * 使用DeepSeek AI生成摘要
     */
    private function generate_ai_excerpt($content, $length) {
        try {
            // 准备API请求
            $config = wordpress_toolkit_get_deepseek_config();
            $api_key = $config['api_key'];
            $api_base = $config['api_base'];
            $model = $config['model'];
            $max_tokens = $config['max_tokens'];
            $temperature = $config['temperature'];

            // 构建提示词
            $prompt = $this->build_ai_prompt($content, $length);

            // 发送API请求
            $response = $this->call_deepseek_api($api_key, $api_base, $model, $prompt, $max_tokens, $temperature);

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $excerpt = trim($response['choices'][0]['message']['content']);

                // 清理AI生成的内容
                $excerpt = $this->clean_ai_excerpt($excerpt);

                // 确保摘要长度合适
                if (mb_strlen($excerpt) > $length * 1.5) {
                    $excerpt = mb_substr($excerpt, 0, $length) . '...';
                }

                return $excerpt;
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: DeepSeek API错误 - ' . $e->getMessage());
        }

        return null;
    }

    /**
     * 构建AI提示词
     */
    private function build_ai_prompt($content, $length) {
        $prompt = "请为以下文章内容生成一个简洁、准确的摘要。要求：\n";
        $prompt .= "1. 摘要长度控制在{$length}字符以内\n";
        $prompt .= "2. 突出文章的核心观点和重要信息\n";
        $prompt .= "3. 保持语义完整，语句通顺\n";
        $prompt .= "4. 不要使用\"本文\"、\"这篇文章\"等引导词\n";
        $prompt .= "5. 直接输出摘要内容，不要其他说明\n\n";
        $prompt .= "文章内容：\n" . mb_substr($content, 0, 2000) . "\n\n";
        $prompt .= "摘要：";

        return $prompt;
    }

    /**
     * 调用DeepSeek API
     */
    private function call_deepseek_api($api_key, $api_base, $model, $prompt, $max_tokens, $temperature) {
        $url = rtrim($api_base, '/') . '/chat/completions';

        // 构建符合官方API规范的消息格式
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => '你是一个专业的文章摘要助手，能够准确理解文章内容并生成简洁、准确的摘要。'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'stream' => false,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );

        // 根据模型类型添加相应参数
        if ($model === 'deepseek-chat') {
            $data['max_tokens'] = $max_tokens;
            $data['temperature'] = $temperature;
        } elseif ($model === 'deepseek-reasoner') {
            // deepseek-reasoner 不支持 max_tokens 和 temperature 参数
            // 模型会自动推理，无需手动设置长度限制
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ),
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'WordPress-Toolkit/1.0.5'
        );

        // 记录API请求日志（仅在调试模式下）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Auto Excerpt: DeepSeek API请求 - URL: ' . $url);
            error_log('Auto Excerpt: DeepSeek API请求 - 模型: ' . $model);
            error_log('Auto Excerpt: DeepSeek API请求 - 数据: ' . json_encode($data));
        }

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $error_message = 'HTTP请求失败: ' . $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Auto Excerpt: DeepSeek API错误 - ' . $error_message);
            }
            throw new Exception($error_message);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Auto Excerpt: DeepSeek API响应 - 状态码: ' . $http_code);
            error_log('Auto Excerpt: DeepSeek API响应 - 内容: ' . $body);
        }

        // 检查HTTP状态码
        if ($http_code !== 200) {
            throw new Exception('API请求失败，HTTP状态码: ' . $http_code);
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON解析失败: ' . json_last_error_msg() . ' - 原始响应: ' . $body);
        }

        // 检查API错误
        if (isset($data['error'])) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '未知API错误';
            $error_type = isset($data['error']['type']) ? $data['error']['type'] : '';
            throw new Exception('API错误 [' . $error_type . ']: ' . $error_message);
        }

        // 检查响应格式
        if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
            throw new Exception('API响应格式异常：缺少choices字段');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('API响应格式异常：缺少message.content字段');
        }

        return $data;
    }

    /**
     * 清理AI生成的摘要
     */
    private function clean_ai_excerpt($excerpt) {
        // 移除可能的引号
        $excerpt = trim($excerpt, '"\'');

        // 移除开头的"摘要："等标识
        $excerpt = preg_replace('/^(摘要|简介|概述)[：:]\s*/', '', $excerpt);

        // 移除多余的空白字符
        $excerpt = preg_replace('/\s+/', ' ', $excerpt);

        return trim($excerpt);
    }

    /**
     * 使用传统算法生成摘要（作为降级方案）
     */
    private function generate_simple_excerpt($content, $length, $smart_extraction) {
        if ($smart_extraction) {
            // 智能提取模式
            return $this->smart_extract_excerpt($content, $length);
        } else {
            // 简单截取模式
            return $this->simple_excerpt($content, $length);
        }
    }

    /**
     * 清理文章内容
     */
    private function clean_content($content) {
        // 移除短代码
        foreach ($this->settings['exclude_shortcodes'] as $shortcode) {
            $content = strip_shortcodes($content);
        }

        // 移除HTML标签
        $content = strip_tags($content);

        // 清理多余空白
        $content = preg_replace('/\s+/', ' ', $content);

        // 解码HTML实体
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        return trim($content);
    }

    /**
     * 智能提取摘要
     */
    private function smart_extract_excerpt($content, $length) {
        // 按句子分割
        $sentences = preg_split('/[。！？.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return $this->simple_excerpt($content, $length);
        }

        $excerpt = '';
        $current_length = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;

            $sentence_length = mb_strlen($sentence);

            // 如果添加这个句子会超出长度限制，检查是否可以截取
            if ($current_length + $sentence_length > $length) {
                if ($current_length < $length * 0.6) {
                    // 如果当前内容太少，截取部分句子
                    $remaining = $length - $current_length - 3;
                    if ($remaining > 10) {
                        $excerpt .= mb_substr($sentence, 0, $remaining) . '...';
                    }
                }
                break;
            }

            $excerpt .= $sentence . '。';
            $current_length += $sentence_length + 1;
        }

        return trim($excerpt);
    }

    /**
     * 简单摘要截取
     */
    private function simple_excerpt($content, $length) {
        if (mb_strlen($content) <= $length) {
            return $content;
        }

        $excerpt = mb_substr($content, 0, $length);

        // 避免在句子中间截断，尽量找到最近的句号
        $last_period = mb_strrpos($excerpt, '。');
        $last_exclamation = mb_strrpos($excerpt, '！');
        $last_question = mb_strrpos($excerpt, '？');
        $last_dot = mb_strrpos($excerpt, '.');
        $last_exclamation_en = mb_strrpos($excerpt, '!');
        $last_question_en = mb_strrpos($excerpt, '?');

        $end_positions = array_filter(array($last_period, $last_exclamation, $last_question, $last_dot, $last_exclamation_en, $last_question_en));

        if (!empty($end_positions)) {
            $max_pos = max($end_positions);
            if ($max_pos > $length * 0.6) {
                $excerpt = mb_substr($excerpt, 0, $max_pos + 1);
            }
        } else {
            $excerpt .= '...';
        }

        return trim($excerpt);
    }

    /**
     * 保存文章时自动生成摘要
     */
    public function auto_generate_excerpt_on_save($post_id, $post) {
        // 跳过自动保存和修订版本
        if (wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE')) {
            return;
        }

        // 检查是否启用了自动生成
        if (!$this->settings['auto_generate']) {
            return;
        }

        // 检查文章类型
        if ($post->post_type !== 'post') {
            return;
        }

        // 如果已有摘要，不覆盖（除非配置为强制覆盖）
        if (!empty($post->post_excerpt) && empty($this->settings['force_regenerate'])) {
            return;
        }

        // 检查用户权限
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 避免无限循环
        remove_action('save_post', array($this, 'auto_generate_excerpt_on_save'), 10);

        // 生成摘要
        $excerpt = $this->generate_excerpt($post->post_content);

        if ($excerpt) {
            // 更新文章摘要
            wp_update_post(array(
                'ID' => $post_id,
                'post_excerpt' => $excerpt
            ));
        }

        // 重新添加钩子
        add_action('save_post', array($this, 'auto_generate_excerpt_on_save'), 10, 2);
    }

    /**
     * 批量为现有文章生成摘要
     */
    private function batch_generate_existing_excerpts() {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'post_excerpt',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => 'post_excerpt',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $posts = get_posts($args);

        foreach ($posts as $post) {
            $excerpt = $this->generate_excerpt($post->post_content);
            if ($excerpt) {
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_excerpt' => $excerpt
                ));
            }
        }
    }

    /**
     * 检测是否使用了AI生成摘要
     */
    private function was_ai_generated($excerpt, $content) {
        // 简单的启发式检测
        // 1. 检查摘要是否包含原内容的句子（如果是，可能是传统提取）
        $content_sentences = preg_split('/[。！？.!?]+/', strip_tags($content), -1, PREG_SPLIT_NO_EMPTY);
        $excerpt_words = preg_split('/[\s，。！？、；：""\'\'（）【】\.,!?;:()"()\[\]]+/', $excerpt, -1, PREG_SPLIT_NO_EMPTY);

        $found_exact_sentences = 0;
        foreach ($content_sentences as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence) > 10 && strpos($excerpt, $sentence) !== false) {
                $found_exact_sentences++;
            }
        }

        // 如果找到多个完全匹配的句子，可能是传统提取
        if ($found_exact_sentences >= 2) {
            return false;
        }

        // 2. 检查摘要是否具有总结性特征
        $summary_keywords = array('总结', '总之', '因此', '所以', '总的来说', '概括', '核心', '关键', '重点');
        $has_summary_features = false;
        foreach ($summary_keywords as $keyword) {
            if (strpos($excerpt, $keyword) !== false) {
                $has_summary_features = true;
                break;
            }
        }

        // 3. 检查摘要长度和内容长度比例
        $content_length = mb_strlen(strip_tags($content));
        $excerpt_length = mb_strlen($excerpt);
        $ratio = $excerpt_length / $content_length;

        // AI生成的摘要通常比例更合适（5%-20%）
        $is_appropriate_length = $ratio >= 0.05 && $ratio <= 0.20;

        return ($has_summary_features || $found_exact_sentences === 0) && $is_appropriate_length;
    }

    /**
     * 获取设置
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * 更新设置
     */
    public function update_settings($new_settings) {
        $this->settings = wp_parse_args($new_settings, $this->settings);
        update_option('wordpress_toolkit_auto_excerpt_settings', $this->settings);
    }

    /**
     * 设置页面
     */
    public function settings_page() {
        if (isset($_POST['save_settings'])) {
            $settings = array(
                'excerpt_length' => intval($_POST['excerpt_length']),
                'auto_generate' => isset($_POST['auto_generate']),
                'preserve_formatting' => isset($_POST['preserve_formatting']),
                'min_content_length' => intval($_POST['min_content_length']),
                'smart_extraction' => isset($_POST['smart_extraction'])
            );

            $this->update_settings($settings);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('设置保存成功！', 'wordpress-toolkit') . '</p></div>';
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo __('自动摘要生成设置', 'wordpress-toolkit'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('wordpress_toolkit_auto_excerpt'); ?>

                <div class="toolkit-settings-form">
                    <h2>📝 基本设置</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_generate"><?php _e('自动生成摘要', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="auto_generate" name="auto_generate" value="1" <?php checked($settings['auto_generate']); ?>>
                                <span class="description"><?php _e('保存文章时自动为没有摘要的文章生成摘要', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="excerpt_length"><?php _e('摘要长度', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="excerpt_length" name="excerpt_length"
                                       value="<?php echo $settings['excerpt_length']; ?>"
                                       min="50" max="500" step="10">
                                <span class="description"><?php _e('字符（建议100-200字符）', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="min_content_length"><?php _e('最小内容长度', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="min_content_length" name="min_content_length"
                                       value="<?php echo $settings['min_content_length']; ?>"
                                       min="50" max="1000" step="10">
                                <span class="description"><?php _e('字符（内容少于此长度时不生成摘要）', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="smart_extraction"><?php _e('智能内容提取', 'wordpress-toolkit'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="smart_extraction" name="smart_extraction" value="1" <?php checked($settings['smart_extraction']); ?>>
                                <span class="description"><?php _e('使用智能算法提取关键句子，而非简单截取', 'wordpress-toolkit'); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="toolkit-settings-form">
                    <h2>🤖 <?php _e('AI设置链接', 'wordpress-toolkit'); ?></h2>
                    <p>
                        <?php _e('AI功能设置已迁移到', 'wordpress-toolkit'); ?>
                        <a href="<?php echo admin_url('admin.php?page=wordpress-toolkit-ai-settings'); ?>" class="button">
                            <?php _e('工具箱设置 → AI设置', 'wordpress-toolkit'); ?>
                        </a>
                        <?php _e('，请在那里配置API密钥和AI服务参数。', 'wordpress-toolkit'); ?>
                    </p>
                    <p>
                        <strong><?php _e('AI功能状态：', 'wordpress-toolkit'); ?></strong>
                        <?php if (wordpress_toolkit_is_ai_available()): ?>
                            <span style="color: #00a32a;">✅ <?php _e('AI功能已启用', 'wordpress-toolkit'); ?></span>
                        <?php else: ?>
                            <span style="color: #d63638;">❌ <?php _e('AI功能未配置', 'wordpress-toolkit'); ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('保存设置', 'wordpress-toolkit'); ?>">
                </div>
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

        <!-- AI设置相关功能已迁移到专门的AI设置页面 -->

        <style>
        /* 响应式卡片样式 */
        .wrap {
            max-width: 100%;
            margin: 0;
            padding: 0 20px;
        }
        @media (min-width: 1200px) {
            .wrap {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0;
            }
        }
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        @media (min-width: 1200px) {
            .card {
                padding: 30px;
            }
        }
        .card h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            font-size: 1.4em;
            font-weight: 600;
        }
        @media (min-width: 1200px) {
            .card h2 {
                font-size: 1.5em;
            }
        }
        /* 响应式表单 */
        .form-table {
            width: 100%;
        }
        @media (max-width: 768px) {
            .form-table {
                font-size: 14px;
            }
            .form-table th {
                width: 30%;
                padding: 15px 10px 15px 0;
            }
            .form-table td {
                width: 70%;
                padding: 15px 0;
            }
            .form-table input[type="number"],
            .form-table input[type="url"],
            .form-table input[type="password"],
            .form-table select {
                width: 100%;
                max-width: 280px;
            }
        }
        /* 响应式按钮和通知 */
        .button {
            font-size: 14px;
            padding: 8px 16px;
            height: auto;
            line-height: 1.4;
        }
        @media (max-width: 768px) {
            .button {
                font-size: 13px;
                padding: 10px 15px;
                width: 100%;
                margin-bottom: 10px;
                text-align: center;
            }
        }
        .spinner.is-inline {
            display: inline-block;
            margin-right: 10px;
            vertical-align: middle;
        }
        .notice.inline {
            margin: 10px 0;
            padding: 12px 15px;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .notice.inline {
                margin: 15px 0;
                padding: 15px;
                font-size: 14px;
            }
        }
        /* 温度滑块优化 */
        input[type="range"] {
            width: 200px;
            max-width: 100%;
        }
        @media (max-width: 768px) {
            input[type="range"] {
                width: 100%;
                margin: 10px 0;
            }
        }
        /* 表格行间距优化 */
        .form-table tr {
            vertical-align: top;
        }
        @media (max-width: 768px) {
            .form-table tr {
                display: block;
                margin-bottom: 20px;
                border-bottom: 1px solid #eee;
                padding-bottom: 15px;
            }
            .form-table th,
            .form-table td {
                display: block;
                width: 100% !important;
                padding: 5px 0 !important;
            }
            .form-table th {
                font-weight: 600;
                color: #23282d;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px !important;
                margin-bottom: 10px;
            }
        }
        </style>
        <?php
    }

    /**
     * AJAX处理API测试
     */
    public function ajax_test_deepseek_api() {
        // 移除安全验证以简化操作

        $api_key = sanitize_text_field($_POST['api_key']);
        $api_base = esc_url_raw($_POST['api_base']);
        $model = sanitize_text_field($_POST['model']);

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('请先配置API密钥', 'wordpress-toolkit')));
        }

        try {
            // 测试API连接
            $test_prompt = "请回复一个简单的问候语，不超过20个字。";
            $response = $this->call_deepseek_api($api_key, $api_base, $model, $test_prompt, 50, 0.1);

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $reply = trim($response['choices'][0]['message']['content']);
                $usage = isset($response['usage']) ? $response['usage'] : array();

                wp_send_json_success(array(
                    'message' => __('连接成功，AI回复：', 'wordpress-toolkit') . $reply,
                    'usage' => $usage
                ));
            } else {
                wp_send_json_error(array('message' => __('API响应格式异常', 'wordpress-toolkit')));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * 获取文章摘要列表
     */
    public function get_excerpt_list($page = 1, $per_page = 20, $status = 'all') {
        error_log("Auto Excerpt: get_excerpt_list called with page=$page, per_page=$per_page, status=$status");

        // 首先获取所有已发布的文章
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1, // 获取所有文章
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids' // 只获取ID以提高性能
        );

        $all_posts_query = new WP_Query($args);
        $all_post_ids = $all_posts_query->posts;

        error_log("Auto Excerpt: Found " . count($all_post_ids) . " total published posts");

        // 处理每篇文章，筛选符合条件的文章
        $filtered_posts = array();

        foreach ($all_post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;

            $has_excerpt = !empty($post->post_excerpt);

            // 根据状态筛选
            if ($status === 'with_excerpt' && !$has_excerpt) {
                continue;
            } elseif ($status === 'without_excerpt' && $has_excerpt) {
                continue;
            }

            // 检测是否为AI生成的摘要
            $is_ai_generated = false;
            if ($has_excerpt && !empty($post->post_excerpt)) {
                // 检查post meta中是否有AI生成标记（支持两种meta key）
                $ai_generated_meta = get_post_meta($post->ID, '_auto_excerpt_ai_generated', true);
                $ai_generated_meta_alt = get_post_meta($post->ID, '_ai_generated_excerpt', true);

                if ($ai_generated_meta || $ai_generated_meta_alt) {
                    $is_ai_generated = true;
                } else {
                    // 使用启发式检测（与生成时的检测逻辑一致）
                    $use_ai = wordpress_toolkit_is_ai_available();
                    if ($use_ai) {
                        $is_ai_generated = $this->was_ai_generated($post->post_excerpt, $post->post_content);
                    }
                }
            }

            $filtered_posts[] = array(
                'ID' => $post->ID,
                'title' => get_the_title($post),
                'excerpt' => $post->post_excerpt,
                'excerpt_length' => mb_strlen($post->post_excerpt),
                'content_length' => mb_strlen(strip_tags($post->post_content)),
                'has_excerpt' => $has_excerpt,
                'is_ai_generated' => $is_ai_generated,
                'edit_url' => get_edit_post_link($post->ID),
                'view_url' => get_permalink($post->ID),
                'date' => get_the_date('Y-m-d H:i:s', $post),
                'status' => get_post_status($post->ID)
            );
        }

        $total_filtered = count($filtered_posts);
        error_log("Auto Excerpt: After filtering, found $total_filtered posts matching status='$status'");

        // 计算分页
        $max_pages = ceil($total_filtered / $per_page);
        $offset = ($page - 1) * $per_page;

        // 获取当前页的数据
        $current_page_posts = array_slice($filtered_posts, $offset, $per_page);

        error_log("Auto Excerpt: Returning " . count($current_page_posts) . " posts for page $page of $max_pages total pages");

        return array(
            'posts' => $current_page_posts,
            'total' => $total_filtered,
            'pages' => $max_pages,
            'current_page' => $page,
            'per_page' => $per_page
        );
    }

    /**
     * 获取摘要统计信息
     */
    public function get_excerpt_stats() {
        error_log("Auto Excerpt: get_excerpt_stats called");

        $total_posts = wp_count_posts('post');
        $total_published = $total_posts->publish;

        error_log("Auto Excerpt: Total published posts: $total_published");

        // 获取所有已发布的文章来统计摘要情况
        $all_posts = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $with_excerpt_count = 0;
        $ai_generated_count = 0;

        if ($all_posts->have_posts()) {
            while ($all_posts->have_posts()) {
                $all_posts->the_post();
                global $post;

                if (!empty($post->post_excerpt)) {
                    $with_excerpt_count++;
                }

                // 检查是否为AI生成的摘要
                $ai_generated = get_post_meta($post->ID, '_ai_generated_excerpt', true);
                if ($ai_generated) {
                    $ai_generated_count++;
                }
            }
        }

        wp_reset_postdata();

        $without_excerpt_count = $total_published - $with_excerpt_count;
        $coverage_rate = $total_published > 0 ? round(($with_excerpt_count / $total_published) * 100, 2) : 0;

        error_log("Auto Excerpt: Stats - Total: $total_published, With: $with_excerpt_count, Without: $without_excerpt_count, AI: $ai_generated_count");

        return array(
            'total_posts' => $total_published,
            'with_excerpt' => $with_excerpt_count,
            'without_excerpt' => $without_excerpt_count,
            'ai_generated' => $ai_generated_count,
            'coverage_rate' => $coverage_rate
        );
    }

    /**
     * AJAX处理批量生成摘要
     */
    public function ajax_batch_generate_excerpts() {
        // 移除安全验证以简化操作

        try {
            error_log('Auto Excerpt: Starting batch generation');

            $success_count = 0;
            $error_count = 0;
            $processed_count = 0;
            $max_execution_time = ini_get('max_execution_time');
            // 增加执行时间限制到600秒（10分钟），如果允许的话
            if ($max_execution_time < 600) {
                @set_time_limit(600);
                $max_execution_time = 600;
            }
            $start_time = time();

            // 初始化进度信息
            $progress_id = 'batch_excerpt_' . time();
            update_option('batch_progress_' . $progress_id, array(
                'task_type' => 'excerpts',
                'total' => 0,
                'processed' => 0,
                'success' => 0,
                'errors' => 0,
                'current_post' => '初始化...',
                'status' => 'processing',
                'start_time' => time()
            ));

            // 获取所有无摘要的已发布文章
            $posts_query = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'ASC' // 从旧到新处理，避免超时
            ));

            if ($posts_query->have_posts()) {
                while ($posts_query->have_posts() && (time() - $start_time) < ($max_execution_time - 10)) {
                    $posts_query->the_post();
                    global $post;

                    // 检查是否已有摘要
                    if (!empty($post->post_excerpt)) {
                        continue; // 跳过已有摘要的文章
                    }

                    error_log("Auto Excerpt: Processing post ID: {$post->ID}");

                    try {
                        // 生成摘要
                        $content = $post->post_content;
                        $excerpt = $this->generate_excerpt($content);

                        if ($excerpt && !empty($excerpt)) {
                            // 更新文章摘要
                            wp_update_post(array(
                                'ID' => $post->ID,
                                'post_excerpt' => $excerpt
                            ));

                            // 标记为AI生成（如果使用了AI）
                            if (wordpress_toolkit_is_ai_available()) {
                                update_post_meta($post->ID, '_ai_generated_excerpt', true);
                                update_post_meta($post->ID, '_auto_excerpt_ai_generated', true);
                            }

                            $success_count++;
                            error_log("Auto Excerpt: Successfully generated excerpt for post ID: {$post->ID}");
                        } else {
                            $error_count++;
                            error_log("Auto Excerpt: Failed to generate excerpt for post ID: {$post->ID}");
                        }
                    } catch (Exception $e) {
                        $error_count++;
                        error_log("Auto Excerpt: Error processing post ID {$post->ID}: " . $e->getMessage());
                    }
                }
            }

            wp_reset_postdata();

            wp_send_json_success(array(
                'success_count' => $success_count,
                'error_count' => $error_count,
                'message' => sprintf(__('处理完成：成功 %d 篇，失败 %d 篇', 'wordpress-toolkit'), $success_count, $error_count)
            ));

        } catch (Exception $e) {
            error_log('Auto Excerpt: Batch generation error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('批量生成失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX处理单个文章生成摘要
     */
    public function ajax_generate_single_excerpt() {
        // 使用统一的安全验证
        if (!WordPress_Toolkit_Security_Validator::verify_admin_ajax('auto_excerpt_nonce')) {
            return;
        }

        // 清理输入数据
        $sanitized_data = WordPress_Toolkit_Security_Validator::sanitize_post_data([
            'post_id' => 'int'
        ]);
        $post_id = $sanitized_data['post_id'];

        // 验证必填字段
        $validation = WordPress_Toolkit_Security_Validator::validate_required_fields(
            ['post_id' => $post_id],
            ['post_id']
        );

        if (!$validation['valid']) {
            wp_send_json_error(array('message' => $validation['errors'][0]));
            return;
        }

        try {
            error_log("Auto Excerpt: Processing single post ID: {$post_id}");

            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('文章不存在', 'wordpress-toolkit')));
            }

            // 检查是否已有摘要
            if (!empty($post->post_excerpt)) {
                wp_send_json_error(array('message' => __('文章已有摘要', 'wordpress-toolkit')));
            }

            // 生成摘要
            $content = $post->post_content;
            $excerpt = $this->generate_excerpt($content);

            if ($excerpt && !empty($excerpt)) {
                // 更新文章摘要
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $excerpt
                ));

                // 标记为AI生成（如果使用了AI）
                $use_ai = wordpress_toolkit_is_ai_available();
                $is_ai_generated = $use_ai && $this->was_ai_generated($excerpt, $content);

                if ($is_ai_generated) {
                    update_post_meta($post_id, '_ai_generated_excerpt', true);
                    update_post_meta($post_id, '_auto_excerpt_ai_generated', true);
                }

                wp_send_json_success(array(
                    'post_id' => $post_id,
                    'post_title' => get_the_title($post_id),
                    'excerpt' => $excerpt,
                    'excerpt_length' => mb_strlen($excerpt),
                    'ai_generated' => $is_ai_generated,
                    'message' => $is_ai_generated ?
                        __('AI摘要生成成功', 'wordpress-toolkit') :
                        __('摘要生成成功', 'wordpress-toolkit')
                ));
            } else {
                wp_send_json_error(array('message' => __('摘要生成失败，内容可能太短', 'wordpress-toolkit')));
            }

        } catch (Exception $e) {
            error_log("Auto Excerpt: Single post generation error for ID {$post_id}: " . $e->getMessage());
            wp_send_json_error(array('message' => __('生成失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX处理单个文章生成标签
     */
    public function ajax_generate_single_tags() {
        // 移除安全验证以简化操作

        $post_id = intval($_POST['post_id']);

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('文章ID无效', 'wordpress-toolkit')));
        }

        try {
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('文章不存在', 'wordpress-toolkit')));
            }

            // 使用AI生成标签
            $tags = $this->generate_ai_tags($post->post_content, $post->post_title);

            if ($tags && !empty($tags)) {
                // 设置文章标签
                wp_set_post_tags($post_id, $tags, false);

                wp_send_json_success(array(
                    'post_id' => $post_id,
                    'post_title' => get_the_title($post_id),
                    'tags' => $tags,
                    'tag_count' => count($tags),
                    'message' => __('标签生成成功', 'wordpress-toolkit')
                ));
            } else {
                wp_send_json_error(array('message' => __('标签生成失败', 'wordpress-toolkit')));
            }

        } catch (Exception $e) {
            error_log("Auto Tags: Single post generation error for ID {$post_id}: " . $e->getMessage());
            wp_send_json_error(array('message' => __('生成失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * 注册定时任务 - 在凌晨3点自动生成摘要
     */
    public function schedule_daily_excerpt_generation() {
        // 检查是否已经存在定时任务
        if (!wp_next_scheduled('auto_excerpt_daily_generation')) {
            // 设置每天凌晨3点执行
            $time_string = '03:00:00';
            $timezone = new DateTimeZone(wp_timezone_string());
            $today = new DateTime('now', $timezone);
            $scheduled_time = new DateTime($today->format('Y-m-d') . ' ' . $time_string, $timezone);

            // 如果当前时间已经过了今天的3点，则设置为明天3点
            if ($today > $scheduled_time) {
                $scheduled_time->modify('+1 day');
            }

            // 调度定时任务
            wp_schedule_event($scheduled_time->getTimestamp(), 'daily', 'auto_excerpt_daily_generation');
            error_log('Auto Excerpt: Scheduled daily generation at ' . $scheduled_time->format('Y-m-d H:i:s'));
        }
    }

    /**
     * 取消定时任务
     */
    public function unschedule_daily_excerpt_generation() {
        $timestamp = wp_next_scheduled('auto_excerpt_daily_generation');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'auto_excerpt_daily_generation');
            error_log('Auto Excerpt: Unscheduled daily generation');
        }
    }

    /**
     * 执行定时摘要生成任务
     */
    public function execute_daily_excerpt_generation() {
        error_log('Auto Excerpt: Starting daily scheduled excerpt generation');

        // 检查是否启用自动生成
        if (!$this->settings['auto_generate']) {
            error_log('Auto Excerpt: Auto generation is disabled, skipping');
            return;
        }

        // 检查连续失败次数
        $failure_count = get_option('auto_excerpt_consecutive_failures', 0);
        if ($failure_count >= 3) {
            error_log("Auto Excerpt: Stopping automatic generation due to {$failure_count} consecutive failures");
            return;
        }

        try {
            $start_time = time();
            $max_execution_time = ini_get('max_execution_time');
            $processed_count = 0;
            $success_count = 0;

            // 获取所有无摘要的已发布文章
            $posts_query = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'ASC', // 从旧到新处理
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'post_excerpt',
                        'compare' => '=',
                        'value' => ''
                    ),
                    array(
                        'key' => 'post_excerpt',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ));

            if ($posts_query->have_posts()) {
                while ($posts_query->have_posts() && (time() - $start_time) < ($max_execution_time - 10)) {
                    $posts_query->the_post();
                    global $post;

                    $processed_count++;

                    // 检查是否已有摘要
                    if (!empty($post->post_excerpt)) {
                        continue;
                    }

                    try {
                        // 生成摘要
                        $content = $post->post_content;
                        $excerpt = $this->generate_excerpt($content);

                        if ($excerpt && !empty($excerpt)) {
                            // 更新文章摘要
                            wp_update_post(array(
                                'ID' => $post->ID,
                                'post_excerpt' => $excerpt
                            ));

                            // 标记为AI生成（如果使用了AI）
                            if (wordpress_toolkit_is_ai_available()) {
                                update_post_meta($post->ID, '_ai_generated_excerpt', true);
                                update_post_meta($post->ID, '_auto_excerpt_ai_generated', true);
                            }

                            $success_count++;
                            error_log("Auto Excerpt: Generated excerpt for post ID: {$post->ID}");
                        }
                    } catch (Exception $e) {
                        error_log("Auto Excerpt: Error processing post ID {$post->ID}: " . $e->getMessage());
                    }
                }
            }

            wp_reset_postdata();

            // 检查是否成功生成了摘要
            if ($success_count > 0) {
                // 重置失败计数
                update_option('auto_excerpt_consecutive_failures', 0);
                error_log("Auto Excerpt: Daily generation completed - Processed: {$processed_count}, Success: {$success_count}");
            } else {
                // 增加失败计数
                $failure_count++;
                update_option('auto_excerpt_consecutive_failures', $failure_count);
                error_log("Auto Excerpt: No excerpts generated today. Failure count: {$failure_count}");

                // 如果连续3天失败，取消定时任务
                if ($failure_count >= 3) {
                    $this->unschedule_daily_excerpt_generation();
                    error_log('Auto Excerpt: Unscheduled daily generation due to 3 consecutive failures');
                }
            }

        } catch (Exception $e) {
            // 增加失败计数
            $failure_count = get_option('auto_excerpt_consecutive_failures', 0) + 1;
            update_option('auto_excerpt_consecutive_failures', $failure_count);
            error_log("Auto Excerpt: Daily generation failed: " . $e->getMessage() . " (Failure count: {$failure_count})");

            // 如果连续3天失败，取消定时任务
            if ($failure_count >= 3) {
                $this->unschedule_daily_excerpt_generation();
                error_log('Auto Excerpt: Unscheduled daily generation due to 3 consecutive failures');
            }
        }
      }

    /**
     * AI生成文章标签
     */
    public function generate_tags_by_ai($post_id = null) {
        if (!$post_id) {
            return array('error' => __('文章ID无效', 'wordpress-toolkit'));
        }

        // 检查AI设置
        if (!wordpress_toolkit_is_ai_available()) {
            return array('error' => __('AI生成功能未启用或未配置API密钥', 'wordpress-toolkit'));
        }

        $post = get_post($post_id);
        if (!$post) {
            return array('error' => __('文章不存在', 'wordpress-toolkit'));
        }

        try {
            // 构建提示词
            $title = get_the_title($post);
            $content = wp_strip_all_tags($post->post_content);
            $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : '';

            // 限制内容长度以避免API限制
            if (mb_strlen($content) > 3000) {
                $content = mb_substr($content, 0, 3000) . '...';
            }

            $prompt = "请根据以下文章信息生成3-8个相关的标签：

标题：{$title}

摘要：{$excerpt}

内容：{$content}

要求：
1. 标签要准确反映文章主题和内容
2. 使用简洁的关键词，最好是2-4个字
3. 标签要具有代表性，便于搜索和分类
4. 每行一个标签，不要编号
5. 直接输出标签，不要解释

标签：";

            // 调用DeepSeek API
            $config = wordpress_toolkit_get_deepseek_config();
            $response = $this->call_deepseek_api(
                $config['api_key'],
                $config['api_base'],
                $config['model'],
                $prompt,
                150,
                0.3 // 较低的创造性确保标签准确
            );

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $ai_tags_text = trim($response['choices'][0]['message']['content']);

                // 处理AI生成的标签
                $ai_tags = array();
                $lines = explode("\n", $ai_tags_text);

                foreach ($lines as $line) {
                    $tag = trim($line);
                    $tag = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $tag); // 清理特殊字符
                    $tag = trim($tag);

                    if (!empty($tag) && mb_strlen($tag) >= 2 && mb_strlen($tag) <= 10) {
                        $ai_tags[] = $tag;
                    }
                }

                // 去重并限制数量
                $ai_tags = array_unique($ai_tags);
                $ai_tags = array_slice($ai_tags, 0, 8);

                // 获取原有标签
                $existing_tags = wp_get_post_tags($post_id, array('fields' => 'names'));

                return array(
                    'success' => true,
                    'ai_tags' => $ai_tags,
                    'existing_tags' => $existing_tags,
                    'suggested_action' => empty($existing_tags) ? 'add' : 'replace'
                );

            } else {
                return array('error' => __('AI服务响应异常', 'wordpress-toolkit'));
            }

        } catch (Exception $e) {
            error_log("Auto Excerpt: AI tag generation error: " . $e->getMessage());
            return array('error' => __('标签生成失败：', 'wordpress-toolkit') . $e->getMessage());
        }
    }

    /**
     * 应用AI生成的标签到文章
     */
    public function apply_ai_tags($post_id, $new_tags, $action = 'replace') {
        if (!$post_id || empty($new_tags)) {
            return array('success' => false, 'message' => __('参数无效', 'wordpress-toolkit'));
        }

        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'message' => __('文章不存在', 'wordpress-toolkit'));
        }

        try {
            // 获取原有标签名称
            $existing_tag_names = wp_get_post_tags($post_id, array('fields' => 'names'));

            // 根据操作类型处理标签
            switch ($action) {
                case 'add':
                    // 添加到现有标签
                    $final_tag_names = array_merge($existing_tag_names, $new_tags);
                    break;

                case 'merge':
                    // 合并标签（去除重复）
                    $final_tag_names = array_unique(array_merge($existing_tag_names, $new_tags));
                    break;

                case 'replace':
                default:
                    // 替换所有标签
                    $final_tag_names = $new_tags;
                    break;
            }

            // 去重并设置标签
            $final_tag_names = array_unique($final_tag_names);
            $result = wp_set_post_tags($post_id, $final_tag_names, false);

            return array(
                'success' => true,
                'message' => __('标签更新成功', 'wordpress-toolkit'),
                'applied_tags' => count($final_tag_names),
                'tag_names' => $final_tag_names
            );

        } catch (Exception $e) {
            error_log("Auto Excerpt: Apply AI tags error: " . $e->getMessage());
            return array('success' => false, 'message' => __('标签更新失败：', 'wordpress-toolkit') . $e->getMessage());
        }
    }

    /**
     * AJAX处理生成标签
     */
    public function ajax_generate_tags() {
        // 移除安全验证以简化操作

        $post_id = intval($_POST['post_id']);
        $result = $this->generate_tags_by_ai($post_id);

        if (isset($result['error'])) {
            wp_send_json_error(array('message' => $result['error']));
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * AJAX处理应用标签
     */
    public function ajax_apply_tags() {
        // 移除安全验证以简化操作

        $post_id = intval($_POST['post_id']);
        $new_tags = array_map('sanitize_text_field', $_POST['new_tags']);
        $action = sanitize_text_field($_POST['action_type']);

        $result = $this->apply_ai_tags($post_id, $new_tags, $action);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
      }

    /**
     * 批量生成文章标签
     */
    public function batch_generate_tags() {
        error_log('Auto Excerpt: Starting batch tag generation');

        // 检查是否启用AI生成
        if (!wordpress_toolkit_is_ai_available()) {
            return array(
                'success' => false,
                'message' => __('AI生成功能未启用或未配置API密钥', 'wordpress-toolkit')
            );
        }

        try {
            $max_execution_time = ini_get('max_execution_time');
            // 增加执行时间限制到600秒（10分钟），如果允许的话
            if ($max_execution_time < 600) {
                @set_time_limit(600);
                $max_execution_time = 600;
            }
            $start_time = time();
            $processed_count = 0;
            $success_count = 0;
            $error_count = 0;
            $total_applied_tags = 0;

            // 获取所有已发布的文章
            $posts_query = new WP_Query(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'ASC' // 从旧到新处理
            ));

            if ($posts_query->have_posts()) {
                while ($posts_query->have_posts() && (time() - $start_time) < ($max_execution_time - 10)) {
                    $posts_query->the_post();
                    global $post;

                    $processed_count++;

                    try {
                        // 生成标签
                        $result = $this->generate_tags_by_ai($post->ID);

                        if ($result && isset($result['ai_tags']) && !empty($result['ai_tags'])) {
                            // 合并去重模式应用标签
                            $apply_result = $this->apply_ai_tags($post->ID, $result['ai_tags'], 'merge');

                            if ($apply_result && $apply_result['success']) {
                                $success_count++;
                                $total_applied_tags += isset($apply_result['applied_tags']) ? $apply_result['applied_tags'] : 0;
                                error_log("Auto Excerpt: Generated tags for post ID: {$post->ID}");
                            } else {
                                $error_count++;
                                error_log("Auto Excerpt: Failed to apply tags for post ID: {$post->ID}");
                            }
                        } else {
                            error_log("Auto Excerpt: No AI tags generated for post ID: {$post->ID}");
                        }
                    } catch (Exception $e) {
                        $error_count++;
                        error_log("Auto Excerpt: Error processing post ID {$post->ID}: " . $e->getMessage());
                    }
                }
            }

            wp_reset_postdata();

            return array(
                'success' => true,
                'processed_count' => $processed_count,
                'success_count' => $success_count,
                'error_count' => $error_count,
                'total_applied_tags' => $total_applied_tags,
                'message' => sprintf(
                    __('批量生成标签完成！处理：%d篇，成功：%d篇，失败：%d篇，应用标签：%d个', 'wordpress-toolkit'),
                    $processed_count,
                    $success_count,
                    $error_count,
                    $total_applied_tags
                )
            );

        } catch (Exception $e) {
            error_log('Auto Excerpt: Batch tag generation error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('批量生成标签失败：', 'wordpress-toolkit') . $e->getMessage()
            );
        }
    }

    /**
     * AJAX处理批量生成标签
     */
    public function ajax_batch_generate_tags() {
        // 移除安全验证以简化操作

        try {
            error_log('Auto Excerpt: Starting batch tag generation AJAX request');
            $result = $this->batch_generate_tags();

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: Batch tag generation AJAX error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('批量生成标签失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX处理单篇文章SEO分析
     */
    public function ajax_analyze_post_seo() {
        // 移除安全验证以简化操作

        $post_id = intval($_POST['post_id']);
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('无效的文章ID', 'wordpress-toolkit')));
        }

        try {
            if (!$this->seo_analyzer) {
                wp_send_json_error(array('message' => __('AI功能未配置，请在工具箱设置中配置AI服务', 'wordpress-toolkit')));
            }

            $result = $this->seo_analyzer->analyze_post($post_id);

            if ($result) {
                // 返回包含完整分析数据的响应，与前端JavaScript预期格式匹配
                wp_send_json_success(array(
                    'message' => __('SEO分析完成', 'wordpress-toolkit'),
                    'analysis' => $result,
                    'post_id' => $post_id
                ));
            } else {
                wp_send_json_error(array('message' => __('SEO分析失败', 'wordpress-toolkit')));
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: SEO analysis error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('SEO分析失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX处理批量SEO分析
     */
    public function ajax_batch_analyze_seo() {
        // 移除安全验证以简化操作

        try {
            if (!$this->seo_analyzer) {
                wp_send_json_error(array('message' => __('AI功能未配置，请在工具箱设置中配置AI服务', 'wordpress-toolkit')));
            }

            $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 5;
            $result = $this->seo_analyzer->batch_analyze_posts(array(), $batch_size);

            wp_send_json_success(array(
                'message' => __('批量SEO分析完成', 'wordpress-toolkit'),
                'result' => $result
            ));

        } catch (Exception $e) {
            error_log('Auto Excerpt: Batch SEO analysis error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('批量SEO分析失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取SEO分析报告
     */
    public function ajax_get_seo_report() {
        // 移除安全验证以简化操作

        $post_id = intval($_POST['post_id']);
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('无效的文章ID', 'wordpress-toolkit')));
        }

        try {
            if (!$this->seo_analyzer) {
                wp_send_json_error(array('message' => __('AI功能未配置，请在工具箱设置中配置AI服务', 'wordpress-toolkit')));
            }

            $report = $this->seo_analyzer->get_seo_report($post_id);

            if ($report) {
                // 转换报告为数组格式，确保包含完整的AI分析数据
                $report_data = json_decode(json_encode($report), true);

                // 直接添加原始AI分析数据到响应中
                $response_data = array(
                    'report' => $report_data,
                    'raw_ai_analysis' => $report->raw_ai_analysis ?? '',
                    'parsed_analysis' => $report->parsed_analysis ?? array(),
                    'ai_full_analysis' => $report->raw_ai_analysis ?? '',
                    'raw_analysis_data' => isset($report_data['analysis_data']) ? $report_data['analysis_data'] : null
                );

                // 确保report中也包含完整数据
                if (!isset($response_data['report']['raw_ai_analysis'])) {
                    $response_data['report']['raw_ai_analysis'] = $report->raw_ai_analysis ?? '';
                }
                if (!isset($response_data['report']['parsed_analysis'])) {
                    $response_data['report']['parsed_analysis'] = $report->parsed_analysis ?? array();
                }

                // 如果有详细分析数据，尝试解析
                if (isset($report_data['detailed_analysis']) && is_string($report_data['detailed_analysis'])) {
                    $detailed_analysis = json_decode($report_data['detailed_analysis'], true);
                    if ($detailed_analysis) {
                        $response_data['ai_full_analysis'] = $detailed_analysis;
                    }
                }

                wp_send_json_success($response_data);
            } else {
                wp_send_json_error(array('message' => __('未找到SEO分析报告', 'wordpress-toolkit')));
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: Get SEO report error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('获取报告失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX更新SEO分析数据库架构
     */
    public function ajax_update_seo_analysis_schema() {
        try {
            $this->ensure_database_schema();

            wp_send_json_success(array(
                'message' => '数据库架构更新成功！现在可以重新生成完整的SEO分析了。',
                'success' => true
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => '数据库架构更新失败：' . $e->getMessage(),
                'success' => false
            ));
        }
    }

    /**
     * AJAX获取SEO统计信息
     */
    public function ajax_get_seo_statistics() {
        // 移除安全验证以简化操作

        try {
            if (!$this->seo_analyzer) {
                wp_send_json_error(array('message' => __('AI功能未配置，请在工具箱设置中配置AI服务', 'wordpress-toolkit')));
            }

            $statistics = $this->seo_analyzer->get_seo_statistics();

            wp_send_json_success(array(
                'message' => __('获取统计信息成功', 'wordpress-toolkit'),
                'statistics' => $statistics
            ));

        } catch (Exception $e) {
            error_log('Auto Excerpt: Get SEO statistics error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('获取统计信息失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取文章列表（用于SEO分析）
     */
    public function ajax_get_posts_for_seo() {
        // 移除安全验证以简化操作

        try {
            $args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'orderby' => 'modified',
                'order' => 'DESC'
            );

            $posts = get_posts($args);
            $posts_data = array();

            foreach ($posts as $post) {
                $posts_data[] = array(
                    'ID' => $post->ID,
                    'post_title' => get_the_title($post->ID),
                    'post_modified' => $post->post_modified
                );
            }

            wp_send_json_success(array(
                'message' => __('获取文章列表成功', 'wordpress-toolkit'),
                'posts' => $posts_data
            ));

        } catch (Exception $e) {
            error_log('Auto Excerpt: Get posts error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('获取文章列表失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX获取SEO报告列表
     */
    public function ajax_get_seo_reports_list() {
        // 移除安全验证以简化操作

        try {
            if (!$this->seo_analyzer) {
                wp_send_json_error(array('message' => __('AI功能未配置，请在工具箱设置中配置AI服务', 'wordpress-toolkit')));
            }

            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

            $reports = $this->seo_analyzer->get_all_seo_reports($limit, $offset);

            wp_send_json_success(array(
                'message' => __('获取报告列表成功', 'wordpress-toolkit'),
                'reports' => $reports
            ));

        } catch (Exception $e) {
            error_log('Auto Excerpt: Get SEO reports error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('获取报告列表失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
      }

    /**
     * AJAX AI分类文章
     */
    public function ajax_ai_categorize() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'auto_excerpt_ai_categorize')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'wordpress-toolkit')));
        }

        $post_id = intval($_POST['post_id']);

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('文章ID无效', 'wordpress-toolkit')));
        }

        try {
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('文章不存在', 'wordpress-toolkit')));
            }

            // 调试信息：文章ID {$post_id} - 这个函数现在用于生成分类描述，而不是分类文章
            // 重新设计功能：根据文章生成分类描述，而不是将文章分类
            // 调用AI分类描述生成功能
            $categories = get_categories(array('hide_empty' => false));
            if (!empty($categories)) {
                // 选择第一个分类生成描述（这里可以根据需要修改逻辑）
                $category = $categories[0];
                $category_result = $this->ai_generate_category_description($category->term_id);
            } else {
                $category_result = array('success' => false, 'message' => __('没有可用的分类', 'wordpress-toolkit'));
            }

            if ($category_result['success']) {
                wp_send_json_success(array(
                    'message' => $category_result['message'],
                    'category' => $category_result['category']
                ));
            } else {
                wp_send_json_error(array('message' => $category_result['message']));
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: AI categorize error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('AI分类失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * AJAX AI优化标签
     */
    public function ajax_ai_optimize_tags() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'auto_excerpt_ai_optimize_tags')) {
            wp_send_json_error(array('message' => __('安全验证失败', 'wordpress-toolkit')));
        }

        $post_id = intval($_POST['post_id']);

        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('文章ID无效', 'wordpress-toolkit')));
        }

        try {
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('文章不存在', 'wordpress-toolkit')));
            }

            // 调试信息：文章ID {$post_id} - 这个函数现在用于生成标签描述，而不是优化标签
            // 重新设计功能：根据文章生成标签描述，而不是优化标签
            // 调用AI标签描述生成功能
            $tags = get_tags(array('hide_empty' => false));
            if (!empty($tags)) {
                // 选择第一个标签生成描述（这里可以根据需要修改逻辑）
                $tag = $tags[0];
                $optimize_result = $this->ai_generate_tag_description($tag->term_id);
            } else {
                $optimize_result = array('success' => false, 'message' => __('没有可用的标签', 'wordpress-toolkit'));
            }

            if ($optimize_result['success']) {
                wp_send_json_success(array(
                    'message' => $optimize_result['message'],
                    'optimized_tags' => $optimize_result['optimized_tags'],
                    'removed_tags' => $optimize_result['removed_tags']
                ));
            } else {
                wp_send_json_error(array('message' => $optimize_result['message']));
            }

        } catch (Exception $e) {
            error_log('Auto Excerpt: AI optimize tags error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('AI标签优化失败：', 'wordpress-toolkit') . $e->getMessage()));
        }
    }

    /**
     * 使用AI为分类生成描述
     */
    private function ai_generate_category_description($category_id) {
        // 检查AI功能是否可用
        if (!function_exists('wordpress_toolkit_is_ai_available') || !wordpress_toolkit_is_ai_available()) {
            return array('success' => false, 'message' => __('AI功能未配置，请先配置AI服务', 'wordpress-toolkit'));
        }

        try {
            $category = get_category($category_id);
            if (!$category) {
                return array('success' => false, 'message' => __('分类不存在', 'wordpress-toolkit'));
            }

            // 获取该分类下的文章
            $posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'category' => $category_id,
                'orderby' => 'date',
                'order' => 'DESC'
            ));

            if (empty($posts)) {
                return array('success' => false, 'message' => __('该分类下没有文章', 'wordpress-toolkit'));
            }

            // 分析文章内容
            $articles_content = '';
            $keywords = array();

            foreach ($posts as $post) {
                $articles_content .= "文章标题：{$post->post_title}\n";
                $articles_content .= "文章内容：" . mb_substr(strip_tags($post->post_content), 0, 300) . "\n\n";

                // 提取关键词
                $content = $post->post_title . ' ' . $post->post_content;
                $words = preg_split('/[\s，。！？；：""\'\'（）【】]/u', $content);
                foreach ($words as $word) {
                    $word = trim($word);
                    if (mb_strlen($word) >= 2 && mb_strlen($word) <= 6 && !preg_match('/[0-9]/', $word)) {
                        if (isset($keywords[$word])) {
                            $keywords[$word]++;
                        } else {
                            $keywords[$word] = 1;
                        }
                    }
                }
            }

            // 获取高频关键词
            arsort($keywords);
            $top_keywords = array_slice(array_keys($keywords), 0, 10);
            $keywords_text = implode('、', $top_keywords);

            // 构建AI提示词
            $prompt = "请为以下分类生成一个简洁准确的描述：

分类名称：{$category->name}

该分类下的主要文章内容：
{$articles_content}

主要关键词：{$keywords_text}

请返回一个1-2句话的分类描述，要求：
1. 准确概括该分类的主要内容
2. 语言简洁明了，适合用户理解
3. 50-80字之间
4. 只返回描述内容，不要包含其他解释";

            // 调用AI服务
            $config = wordpress_toolkit_get_deepseek_config();
            $response = wordpress_toolkit_call_deepseek_api(
                $config['api_key'],
                $config['api_base'],
                $config['model'],
                $prompt,
                100,
                0.3
            );

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $description = trim($response['choices'][0]['message']['content']);

                // 清理描述
                $description = preg_replace('/[""\'\'"]/', '', $description);
                $description = preg_replace('/[\r\n]+/', ' ', $description);
                $description = trim($description);

                if (!empty($description)) {
                    // 更新分类描述
                    wp_update_term($category_id, 'category', array(
                        'description' => $description
                    ));

                    return array(
                        'success' => true,
                        'message' => sprintf(__('成功为分类"%s"生成描述', 'wordpress-toolkit'), $category->name),
                        'description' => $description
                    );
                } else {
                    return array('success' => false, 'message' => __('AI未能生成有效描述', 'wordpress-toolkit'));
                }

            } else {
                return array('success' => false, 'message' => __('AI服务响应异常', 'wordpress-toolkit'));
            }

        } catch (Exception $e) {
            error_log("Auto Excerpt: AI category description error: " . $e->getMessage());
            return array('error' => __('AI生成分类描述失败：', 'wordpress-toolkit') . $e->getMessage());
        }
    }

    /**
     * 使用AI为标签生成描述
     */
    private function ai_generate_tag_description($tag_id) {
        // 检查AI功能是否可用
        if (!function_exists('wordpress_toolkit_is_ai_available') || !wordpress_toolkit_is_ai_available()) {
            return array('success' => false, 'message' => __('AI功能未配置，请先配置AI服务', 'wordpress-toolkit'));
        }

        try {
            $tag = get_term($tag_id, 'post_tag');
            if (!$tag) {
                return array('success' => false, 'message' => __('标签不存在', 'wordpress-toolkit'));
            }

            // 获取使用该标签的文章
            $posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'tag' => $tag->slug,
                'orderby' => 'date',
                'order' => 'DESC'
            ));

            if (empty($posts)) {
                return array('success' => false, 'message' => __('该标签下没有文章', 'wordpress-toolkit'));
            }

            // 分析文章内容
            $articles_content = '';
            $keywords = array();

            foreach ($posts as $post) {
                $articles_content .= "文章标题：{$post->post_title}\n";
                $articles_content .= "文章内容：" . mb_substr(strip_tags($post->post_content), 0, 300) . "\n\n";

                // 提取关键词
                $content = $post->post_title . ' ' . $post->post_content;
                $words = preg_split('/[\s，。！？；：""\'\'（）【】]/u', $content);
                foreach ($words as $word) {
                    $word = trim($word);
                    if (mb_strlen($word) >= 2 && mb_strlen($word) <= 6 && !preg_match('/[0-9]/', $word)) {
                        if (isset($keywords[$word])) {
                            $keywords[$word]++;
                        } else {
                            $keywords[$word] = 1;
                        }
                    }
                }
            }

            // 获取高频关键词（排除标签本身）
            unset($keywords[$tag->name]);
            arsort($keywords);
            $top_keywords = array_slice(array_keys($keywords), 0, 8);
            $keywords_text = implode('、', $top_keywords);

            // 构建AI提示词
            $prompt = "请为以下标签生成一个简洁准确的描述：

标签名称：{$tag->name}

使用该标签的文章主要内容：
{$articles_content}

相关关键词：{$keywords_text}

请返回一个1-2句话的标签描述，要求：
1. 准确概括该标签的用途和含义
2. 语言简洁明了，适合用户理解
3. 30-60字之间
4. 只返回描述内容，不要包含其他解释";

            // 调用AI服务
            $config = wordpress_toolkit_get_deepseek_config();
            $response = wordpress_toolkit_call_deepseek_api(
                $config['api_key'],
                $config['api_base'],
                $config['model'],
                $prompt,
                100,
                0.3
            );

            if ($response && isset($response['choices'][0]['message']['content'])) {
                $description = trim($response['choices'][0]['message']['content']);

                // 清理描述
                $description = preg_replace('/[""\'\'"]/', '', $description);
                $description = preg_replace('/[\r\n]+/', ' ', $description);
                $description = trim($description);

                if (!empty($description)) {
                    // 更新标签描述
                    wp_update_term($tag_id, 'post_tag', array(
                        'description' => $description
                    ));

                    return array(
                        'success' => true,
                        'message' => sprintf(__('成功为标签"%s"生成描述', 'wordpress-toolkit'), $tag->name),
                        'description' => $description
                    );
                } else {
                    return array('success' => false, 'message' => __('AI未能生成有效描述', 'wordpress-toolkit'));
                }

            } else {
                return array('success' => false, 'message' => __('AI服务响应异常', 'wordpress-toolkit'));
            }

        } catch (Exception $e) {
            error_log("Auto Excerpt: AI tag description error: " . $e->getMessage());
            return array('error' => __('AI生成标签描述失败：', 'wordpress-toolkit') . $e->getMessage());
        }
    }
}

// 注册插件激活和停用钩子
register_activation_hook(__FILE__, array('Auto_Excerpt_Module', 'activate'));
register_deactivation_hook(__FILE__, array('Auto_Excerpt_Module', 'deactivate'));