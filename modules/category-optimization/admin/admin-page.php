<?php
/**
 * Category Optimization Admin Page
 * 分类优化管理页面 - 与文章优化模块保持一致
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Category Optimization Admin Page 类
 */
class Category_Optimization_Admin_Page {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 模块实例
     */
    private $module;

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
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // 处理AJAX操作
        add_action('wp_ajax_optimize_category', array($this, 'ajax_optimize_category'));
        add_action('wp_ajax_bulk_optimize_categories', array($this, 'ajax_bulk_optimize_categories'));
        add_action('wp_ajax_bulk_optimize_all_categories', array($this, 'ajax_bulk_optimize_all_categories'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wordpress-toolkit',
            __('分类优化管理', 'wordpress-toolkit'),
            __('分类优化', 'wordpress-toolkit'),
            'manage_options',
            'wordpress-toolkit-category-optimization',
            array($this, 'render_admin_page')
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-toolkit'));
        }

        // 获取统计数据
        $stats = $this->get_statistics();

        // 获取分类列表数据
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;

        $args = array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        // 处理筛选
        if (isset($_GET['status']) && $_GET['status'] !== 'all') {
            if ($_GET['status'] === 'optimized') {
                $args['meta_query'] = array(
                    array(
                        'key' => 'ai_optimization_status',
                        'value' => 'optimized',
                        'compare' => '='
                    )
                );
            } elseif ($_GET['status'] === 'pending') {
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'ai_optimization_status',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => 'ai_optimization_status',
                        'value' => 'optimized',
                        'compare' => '!='
                    )
                );
            }
        }

        $categories_query = get_terms($args);
        $total_categories = wp_count_terms('category', array('hide_empty' => false));
        $total_pages = ceil($total_categories / $per_page);

        // 加载样式和脚本
        wp_enqueue_style('category-optimization-admin', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/category-optimization/assets/css/admin.css', array(), '1.0.0');
        wp_enqueue_script('category-optimization-admin', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/category-optimization/assets/js/admin.js', array('jquery'), '1.0.0', true);

        // 传递数据到JavaScript
        $nonce = wp_create_nonce('category_optimization_nonce');
        wp_localize_script('category-optimization-admin', 'categoryOptimizationData', array(
            'texts' => array(
                'confirmOptimize' => __('确定要优化这个分类吗？', 'wordpress-toolkit'),
                'optimizing' => __('优化中...', 'wordpress-toolkit'),
                'optimizeSuccess' => __('优化成功', 'wordpress-toolkit'),
                'optimizeFailed' => __('优化失败', 'wordpress-toolkit'),
                'selectCategories' => __('请先选择要优化的分类', 'wordpress-toolkit'),
                'confirmBulkOptimize' => __('确定要批量生成选中分类的内容吗？此操作可能需要一些时间。', 'wordpress-toolkit'),
                'bulkOptimizeSuccess' => __('批量优化完成', 'wordpress-toolkit'),
                'bulkOptimizeFailed' => __('批量优化失败', 'wordpress-toolkit')
            ),
            'nonces' => array(
                'optimizeCategory' => $nonce,
                'bulkOptimizeCategories' => $nonce
            )
        ));

        ?>
        <div class="wrap auto-excerpt-admin">
            <h1><?php _e('分类优化管理', 'wordpress-toolkit'); ?></h1>

            <!-- 统计卡片 -->
            <div class="auto-excerpt-stats-grid">
                <div class="stat-card">
                    <h3><?php _e('总分类数', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['total_categories']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('已优化分类', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['optimized_categories']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('待优化分类', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['pending_categories']; ?></span>
                </div>
                <div class="stat-card">
                    <h3><?php _e('优化覆盖率', 'wordpress-toolkit'); ?></h3>
                    <span class="stat-number"><?php echo $stats['coverage_rate']; ?>%</span>
                </div>
            </div>

            <!-- 分类列表和管理 -->
            <div class="posts-list-section">
                <div id="categories-list-container">
                    <?php if (!empty($categories_query) && !is_wp_error($categories_query)): ?>
                        <div class="tablenav top">
                            <div class="alignleft actions bulkactions">
                                <button type="button" class="button action" id="batch-optimize-categories">
                                    🤖 <?php _e('批量生成分类描述', 'wordpress-toolkit'); ?>
                                </button>
                                <span class="spinner" id="batch-spinner" style="display: none;"></span>
                            </div>
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(__('共 %d 个分类', 'wordpress-toolkit'), $total_categories); ?>
                                </span>
                                <?php
                                $current_url = admin_url('admin.php?page=wordpress-toolkit-category-optimization');
                                if (isset($_GET['status'])) {
                                    $current_url .= '&status=' . urlencode($_GET['status']);
                                }
                                echo paginate_links(array(
                                    'base' => $current_url . '&paged=%#%',
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page
                                ));
                                ?>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-cb check-column">
                                        <input type="checkbox" id="select-all-categories">
                                    </th>
                                    <th scope="col"><?php _e('分类名称', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('别名', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('描述', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('文章数', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('状态', 'wordpress-toolkit'); ?></th>
                                    <th scope="col"><?php _e('操作', 'wordpress-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories_query as $category): ?>
                                    <?php
                                    $status = get_term_meta($category->term_id, 'ai_optimization_status', true);
                                    $ai_slug = get_term_meta($category->term_id, 'ai_generated_slug', true);
                                    $ai_description = get_term_meta($category->term_id, 'ai_generated_description', true);

                                    // 检查是否有AI生成的内容
                                    $has_ai_slug = !empty($ai_slug);
                                    $has_ai_description = !empty($ai_description);
                                    ?>
                                    <tr>
                                        <td class="check-column">
                                            <input type="checkbox" class="category-checkbox" value="<?php echo $category->term_id; ?>" data-name="<?php echo esc_attr($category->name); ?>">
                                        </td>
                                        <td><strong><a href="<?php echo admin_url('term.php?taxonomy=category&tag_ID=' . $category->term_id); ?>" target="_blank"><?php echo $category->name; ?></a></strong></td>
                                        <td>
                                            <code><?php echo $category->slug; ?></code>
                                            <?php if ($has_ai_slug): ?>
                                                <span class="ai-generated-mark">AI</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo wp_trim_words($category->description, 10); ?>
                                            <?php if ($has_ai_description): ?>
                                                <span class="ai-generated-mark">AI</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="post-count"><?php echo $category->count; ?></span></td>
                                        <td>
                                            <?php if ($status === 'optimized'): ?>
                                                <span class="status-badge status-success"><?php _e('已优化', 'wordpress-toolkit'); ?></span>
                                            <?php elseif ($status === 'failed'): ?>
                                                <span class="status-badge status-error"><?php _e('优化失败', 'wordpress-toolkit'); ?></span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending"><?php _e('待优化', 'wordpress-toolkit'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="row-actions">
                                                <span class="generate-description">
                                                    <button type="button" class="button button-small generate-description-btn" data-id="<?php echo $category->term_id; ?>" style="background: #0073aa; color: white; border: none; padding: 6px 12px; margin: 2px;">
                                                        📝 <?php _e('AI生成描述', 'wordpress-toolkit'); ?>
                                                    </button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                      <?php else: ?>
                        <p><?php _e('没有找到分类', 'wordpress-toolkit'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 获取统计数据
     */
    private function get_statistics() {
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'fields' => 'ids'
        ));

        $total_categories = count($categories);
        $optimized_categories = 0;
        $pending_categories = 0;

        foreach ($categories as $category_id) {
            $status = get_term_meta($category_id, 'ai_optimization_status', true);
            if ($status === 'optimized') {
                $optimized_categories++;
            } else {
                $pending_categories++;
            }
        }

        $coverage_rate = $total_categories > 0 ? round(($optimized_categories / $total_categories) * 100, 1) : 0;

        return array(
            'total_categories' => $total_categories,
            'optimized_categories' => $optimized_categories,
            'pending_categories' => $pending_categories,
            'coverage_rate' => $coverage_rate
        );
    }

    /**
     * AJAX优化单个分类
     */
    public function ajax_optimize_category() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $category_id = intval($_POST['category_id']);
        $optimize_type = sanitize_text_field($_POST['optimize_type']);

        if (!$category_id) {
            wp_send_json_error(__('无效的分类ID', 'wordpress-toolkit'));
        }

        $category = get_term($category_id, 'category');
        if (!$category || is_wp_error($category)) {
            wp_send_json_error(__('分类不存在', 'wordpress-toolkit'));
        }

        switch ($optimize_type) {
            case 'description':
                // 基于分类下的文章生成描述并直接写入WordPress的description字段
                $ai_description = $this->generate_ai_description_by_articles($category);

                // 更新WordPress原生的description字段
                $update_result = wp_update_term($category_id, 'category', array('description' => $ai_description));

                // 更新优化状态
                if (!is_wp_error($update_result)) {
                    update_term_meta($category_id, 'ai_optimization_status', 'optimized', true);
                }

                $message = sprintf(__('分类 "%s" 的AI描述生成成功！', 'wordpress-toolkit'), $category->name);
                break;

            default:
                wp_send_json_error(__('无效的优化类型', 'wordpress-toolkit'));
                break;
        }

        wp_send_json_success(array(
            'message' => $message
        ));
    }

    /**
     * 生成英文别名
     */
    private function generate_english_slug($category_name, $category_id = null) {
        // 中文到英文的常见映射
        $translations = array(
            '技术' => 'technology',
            '科技' => 'tech',
            '开发' => 'development',
            '编程' => 'programming',
            '设计' => 'design',
            '教程' => 'tutorial',
            '指南' => 'guide',
            '学习' => 'learning',
            '博客' => 'blog',
            '新闻' => 'news',
            '资讯' => 'info',
            '信息' => 'information',
            '工具' => 'tools',
            '资源' => 'resources',
            '技巧' => 'tips',
            '方法' => 'methods',
            '经验' => 'experience',
            '分享' => 'sharing',
            '交流' => 'communication',
            '讨论' => 'discussion',
            '问题' => 'issues',
            '解决方案' => 'solutions',
            '最佳实践' => 'best-practices',
            '案例分析' => 'case-study',
            '深度' => 'deep',
            '专业' => 'professional',
            '企业' => 'business',
            '产品' => 'product',
            '服务' => 'service',
            '平台' => 'platform',
            '系统' => 'system',
            '架构' => 'architecture',
            '数据库' => 'database',
            '服务器' => 'server',
            '安全' => 'security',
            '性能' => 'performance',
            '优化' => 'optimization',
            '自动化' => 'automation',
            '部署' => 'deployment',
            '测试' => 'testing',
            '调试' => 'debugging',
            '产品管理' => 'product-management',
            '用户体验' => 'user-experience',
            '市场营销' => 'marketing',
            '搜索引擎优化' => 'seo',
            '内容管理' => 'content-management',
            '数据分析' => 'data-analysis',
            '项目管理' => 'project-management',
            // 常见单个字符
            '文' => 'article',
            '章' => 'chapter',
            '分' => 'category',
            '类' => 'classification',
            '网' => 'net',
            '站' => 'site',
            '论' => 'discussion',
            '坛' => 'forum',
            '社' => 'community',
            '区' => 'area',
            '应' => 'application',
            '用' => 'use',
            '软' => 'soft',
            '件' => 'ware',
            '游' => 'game',
            '戏' => 'play',
            '娱' => 'entertainment',
            '乐' => 'fun',
            '生' => 'life',
            '活' => 'live',
            '旅' => 'travel',
            '行' => 'go',
            '美' => 'beauty',
            '食' => 'food',
            '财' => 'finance',
            '经' => 'economics',
            '教' => 'education',
            '育' => 'education',
            '健' => 'health',
            '康' => 'health',
            '医' => 'medical',
            '疗' => 'therapy',
            '房' => 'house',
            '产' => 'property',
            '汽' => 'auto',
            '车' => 'car',
            '科' => 'science',
            '研' => 'research',
            '创' => 'create',
            '新' => 'new',
            '互' => 'inter',
            '联' => 'link',
            '网' => 'net'
        );

        // 优先尝试英文翻译
        $english_name = $this->translate_chinese_to_english($category_name, $translations);

        // 如果翻译失败，使用拼音转换
        if ($english_name === $category_name) {
            $english_name = $this->convert_to_pinyin($category_name);
        }

        // 清理并格式化
        $english_name = strtolower($english_name);
        $english_name = preg_replace('/[^a-z0-9]+/', '-', $english_name);
        $english_name = trim($english_name, '-');

        // 确保不为空，如果翻译和拼音都失败，尝试基本的拼音映射
        if (empty($english_name)) {
            $basic_pinyin = $this->get_basic_pinyin($category_name);
            if (!empty($basic_pinyin)) {
                $english_name = $basic_pinyin;
            } else {
                // 最后的备选方案：使用简化的数字标识
                $english_name = 'cat-' . ($category_id ? $category_id : 'unknown');
            }
        }

        return sanitize_title($english_name);
    }

    /**
     * 中文到英文翻译
     */
    private function translate_chinese_to_english($text, $translations) {
        // 先尝试完整的词汇匹配
        foreach ($translations as $chinese => $english) {
            if ($text === $chinese) {
                return $english;
            }
        }

        // 然后尝试部分匹配（优先匹配长词）
        uksort($translations, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        $translated = $text;
        foreach ($translations as $chinese => $english) {
            if (strpos($text, $chinese) !== false) {
                $translated = str_replace($chinese, ' ' . $english . ' ', $translated);
            }
        }

        // 如果有任何翻译发生，清理多余空格并返回
        if ($translated !== $text) {
            return trim(preg_replace('/\s+/', '-', $translated));
        }

        // 最后尝试逐字翻译
        $result = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            if (isset($translations[$char])) {
                $result .= $translations[$char] . '-';
            } else {
                // 对于无法翻译的字符，跳过它
                continue;
            }
        }

        if (!empty($result)) {
            return trim($result, '-');
        }

        return $text; // 无法翻译返回原文
    }

    /**
     * 获取基本的拼音转换（简化版）
     */
    private function get_basic_pinyin($text) {
        // 更全面的汉字拼音映射（常用字）
        $basic_pinyin_map = array(
            // 声母韵母组合
            '技' => 'ji', '术' => 'shu', '科' => 'ke', '开' => 'kai', '发' => 'fa',
            '设' => 'she', '计' => 'ji', '产' => 'chan', '品' => 'pin', '服' => 'fu',
            '务' => 'wu', '平' => 'ping', '台' => 'tai', '系' => 'xi', '统' => 'tong',
            '安' => 'an', '全' => 'quan', '性' => 'xing', '能' => 'neng', '优' => 'you',
            '化' => 'hua', '自' => 'zi', '动' => 'dong', '部' => 'bu', '署' => 'shu',
            '测' => 'ce', '试' => 'shi', '调' => 'diao', '教' => 'jiao', '程' => 'cheng',
            '学' => 'xue', '习' => 'xi', '博' => 'bo', '客' => 'ke', '新' => 'xin',
            '闻' => 'wen', '资' => 'zi', '讯' => 'xun', '信' => 'xin', '工' => 'gong',
            '具' => 'ju', '源' => 'yuan', '管' => 'guan', '理' => 'li', '方' => 'fang',
            '法' => 'fa', '经' => 'jing', '验' => 'yan', '分' => 'fen', '享' => 'xiang',
            '交' => 'jiao', '流' => 'liu', '讨' => 'tao', '论' => 'lun', '问' => 'wen',
            '题' => 'ti', '解' => 'jie', '决' => 'jue', '案' => 'an', '案' => 'an',
            '创' => 'chuang', '业' => 'ye', '用' => 'yong', '户' => 'hu', '体' => 'ti',
            '验' => 'yan', '市' => 'shi', '场' => 'chang', '销' => 'xiao', '售' => 'shou',
            '内' => 'nei', '容' => 'rong', '数' => 'shu', '据' => 'ju', '项' => 'xiang',
            '目' => 'mu', '研' => 'yan', '究' => 'jiu', '网' => 'wang', '站' => 'zhan',
            '游' => 'you', '戏' => 'xi', '娱' => 'yu', '乐' => 'le', '生' => 'sheng',
            '活' => 'huo', '旅' => 'lv', '行' => 'xing', '美' => 'mei', '食' => 'shi',
            '财' => 'cai', '健' => 'jian', '康' => 'kang', '医' => 'yi', '疗' => 'liao',
            '房' => 'fang', '地' => 'di', '汽' => 'qi', '车' => 'che', '教' => 'jiao',
            '育' => 'yu', '文' => 'wen', '化' => 'hua', '艺' => 'yi', '术' => 'shu',
            '体' => 'ti', '育' => 'yu', '环' => 'huan', '保' => 'bao', '农' => 'nong',
            '业' => 'ye', '军' => 'jun', '事' => 'shi', '政' => 'zheng', '治' => 'zhi',
            '法' => 'fa', '律' => 'lv', '社' => 'she', '区' => 'qu', '公' => 'gong',
            '益' => 'yi', '慈' => 'ci', '善' => 'shan', '宗' => 'zong', '教' => 'jiao',
            '历' => 'li', '史' => 'shi', '哲' => 'zhe', '学' => 'xue', '心' => 'xin',
            '理' => 'li', '语' => 'yu', '言' => 'yan', '外' => 'wai', '国' => 'guo'
        );

        $result = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            if (isset($basic_pinyin_map[$char])) {
                $result .= $basic_pinyin_map[$char];
            }
            // 对于非中文字符（英文、数字等），直接保留
            elseif (preg_match('/[a-zA-Z0-9]/', $char)) {
                $result .= $char;
            }
        }

        return !empty($result) ? $result : null;
    }

    /**
     * 转换为拼音
     */
    private function convert_to_pinyin($text) {
        // 扩展的拼音映射
        $pinyin_map = array(
            '产' => 'chan', '品' => 'pin', '技' => 'ji', '术' => 'shu',
            '开' => 'kai', '发' => 'fa', '设' => 'she', '计' => 'ji',
            '教' => 'jiao', '程' => 'cheng', '学' => 'xue', '习' => 'xi',
            '博' => 'bo', '客' => 'ke', '新' => 'xin', '闻' => 'wen',
            '工' => 'gong', '具' => 'ju', '资' => 'zi', '源' => 'yuan',
            '管' => 'guan', '理' => 'li', '法' => 'fa', '师' => 'shi',
            '销' => 'xiao', '售' => 'shou', '服' => 'fu', '务' => 'wu',
            '网' => 'wang', '站' => 'zhan', '页' => 'ye', '面' => 'mian',
            '创' => 'chuang', '新' => 'xin', '更' => 'geng', '新' => 'xin',
            '维' => 'wei', '护' => 'hu', '更新' => 'update',
            '优' => 'you', '化' => 'hua', '改' => 'gai',
            '调' => 'tiao', '试' => 'shi', '验' => 'yan'
        );

        $pinyin_name = '';
        for ($i = 0; $i < mb_strlen($text, 'UTF-8'); $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $pinyin_name .= isset($pinyin_map[$char]) ? $pinyin_map[$char] : $char;
        }

        return $pinyin_name;
    }

    /**
     * 基于分类下的文章生成AI描述
     */
    private function generate_ai_description_by_articles($category) {
        // 获取分类下的文章
        $posts = get_posts(array(
            'category' => $category->term_id,
            'numberposts' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($posts)) {
            return sprintf(
                '%s是我专门用来分享%s相关内容的分类。虽然目前还没有发布文章，' .
                '但我计划在这里记录我在学习%s过程中的点点滴滴，包括遇到的问题、解决方案和心得体会。',
                $category->name,
                $category->name,
                $category->name
            );
        }

        // 收集文章信息，用于生成更具体的描述
        $post_count = count($posts);
        $recent_titles = array();

        foreach ($posts as $post) {
            $recent_titles[] = $post->post_title;
            if (count($recent_titles) >= 3) break; // 取最近3篇文章标题
        }

        // 根据文章数量和内容生成更自然的描述
        if ($post_count == 1) {
            $description = sprintf(
                '在这个%s分类中，我分享了一篇关于%s的文章。这篇文章记录了我在探索%s过程中的一些真实想法和经历，' .
                '希望这些个人经验能够给同样走在%s路上的朋友带来一些启发和帮助。',
                $category->name,
                $category->name,
                $category->name,
                $category->name
            );
        } elseif ($post_count <= 3) {
            $recent_work = implode('、', array_slice($recent_titles, 0, 2));
            $description = sprintf(
                '%s分类收录了几篇我写的关于%s的文章。我在这里分享了最近在%s方面的一些学习心得和实践体会，' .
                '比如关于%s等内容。这些文章记录了我的真实经历，希望能帮助到同样对这些话题感兴趣的朋友。',
                $category->name,
                $category->name,
                $category->name,
                $recent_work
            );
        } else {
            $recent_work = implode('、', array_slice($recent_titles, 0, 3));
            $description = sprintf(
                '%s分类整理了我在%s方面的多篇学习笔记。随着对%s的理解不断加深，' .
                '我在这里记录了从零基础到逐渐熟练的学习轨迹，分享了像%s这样的具体实践内容。' .
                '每一篇文章都是我真实学习过程中的沉淀，希望能够为同样想要学习%s的朋友提供一些参考。',
                $category->name,
                $category->name,
                $category->name,
                $recent_work,
                $category->name
            );
        }

        return $description;
    }

    /**
     * AJAX批量优化分类
     */
    public function ajax_bulk_optimize_categories() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $category_ids = array_map('intval', $_POST['category_ids']);
        $optimize_type = sanitize_text_field($_POST['optimize_type']);

        if (empty($category_ids)) {
            wp_send_json_error(__('请选择要优化的分类', 'wordpress-toolkit'));
        }

        $processed = 0;
        foreach ($category_ids as $category_id) {
            $category = get_term($category_id, 'category');
            if ($category && !is_wp_error($category)) {

                switch ($optimize_type) {
                    case 'description':
                        // 批量AI生成描述并直接写入WordPress原生字段
                        $ai_description = $this->generate_ai_description_by_articles($category);
                        wp_update_term($category_id, 'category', array('description' => $ai_description));
                        break;
                }

                $processed++;
            }
        }

        $operation_name = 'AI生成描述';

        wp_send_json_success(array(
            'message' => sprintf(__('成功%s了 %d 个分类！', 'wordpress-toolkit'), $operation_name, $processed),
            'processed' => $processed
        ));
    }

    /**
     * AJAX批量优化所有分类
     */
    public function ajax_bulk_optimize_all_categories() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'category_optimization_nonce')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-toolkit'));
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-toolkit'));
        }

        $optimize_type = sanitize_text_field($_POST['optimize_type']);

        // 获取所有分类
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => false,
            'number' => 0, // 获取所有分类
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (empty($categories) || is_wp_error($categories)) {
            wp_send_json_error(__('没有找到分类', 'wordpress-toolkit'));
        }

        $processed = 0;
        $failed = 0;

        foreach ($categories as $category) {
            try {
                if ($optimize_type === 'description') {
                    // 批量AI生成描述并直接写入WordPress原生字段
                    $ai_description = $this->generate_ai_description_by_articles($category);
                    $update_result = wp_update_term($category->term_id, 'category', array('description' => $ai_description));

                    if (!is_wp_error($update_result)) {
                        // 更新优化状态
                        update_term_meta($category->term_id, 'ai_optimization_status', 'optimized', true);
                        $processed++;
                    } else {
                        $failed++;
                    }
                }
            } catch (Exception $e) {
                $failed++;
            }
        }

        $total = count($categories);
        if ($failed > 0) {
            $message = sprintf(__('批量生成完成！成功生成 %d 个分类描述，失败 %d 个。', 'wordpress-toolkit'), $processed, $failed);
        } else {
            $message = sprintf(__('成功为所有 %d 个分类生成了描述！', 'wordpress-toolkit'), $processed);
        }

        wp_send_json_success(array(
            'message' => $message,
            'processed' => $processed,
            'failed' => $failed,
            'total' => $total
        ));
    }
}

// 初始化管理页面
Category_Optimization_Admin_Page::get_instance();