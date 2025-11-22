<?php
/**
 * Website Optimization Module - 网站优化模块
 *
 * 获取WordPress的标题、关键词、描述，对比现有博客标题、关键字、描述进行SEO分析和优化
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Website Optimization Module 主类
 */
class Website_Optimization_Module {

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
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * 加载设置
     */
    private function load_settings() {
        $default_settings = array(
            'enable_ai_analysis' => true,
            'auto_optimize_suggestions' => true,
            'compare_with_competitors' => false,
            'suggestion_confidence_threshold' => 80,
            'max_keywords' => 5,
            'title_length_limit' => 60,
            'description_length_limit' => 160
        );

        $saved_settings = get_option('wordpress_ai_toolkit_website_optimization_settings', array());
        $this->settings = wp_parse_args($saved_settings, $default_settings);
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 注意：菜单项在主插件文件中定义，这里不需要重复定义

        // 注册AJAX处理
        add_action('wp_ajax_website_optimization_analyze', array($this, 'handle_ajax_analyze'));
        add_action('wp_ajax_website_optimization_get_saved_analysis', array($this, 'handle_ajax_get_saved_analysis'));
        add_action('wp_ajax_website_optimization_save_settings', array($this, 'handle_ajax_save_settings'));

        // 加载脚本和样式
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    /**
     * 模块激活
     */
    public function activate() {
        // 设置默认选项
        $default_options = array(
            'auto_analysis' => false,
            'analysis_interval' => 7, // 天
            'enable_notifications' => false,
            'last_analysis_date' => null
        );

        // 如果设置不存在，则添加默认设置
        if (!get_option('wordpress_ai_toolkit_website_optimization_settings')) {
            add_option('wordpress_ai_toolkit_website_optimization_settings', $default_options);
        }
    }

    /**
     * 模块停用
     */
    public function deactivate() {
        // 清理定时任务（如果有）
        wp_clear_scheduled_hook('wordpress_ai_toolkit_website_seo_analysis');

        // 清理临时缓存和选项（可选）
        // delete_option('wordpress_ai_toolkit_last_website_analysis');
    }

    /**
     * 渲染管理页面 - 兼容主插件调用
     */
    public function admin_page() {
        $this->render_admin_page();
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page() {
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'wordpress-ai-toolkit'));
        }

        // 获取网站基本信息
        $site_info = $this->get_site_info();

        // 获取统计数据
        $stats = $this->get_statistics();

        // 获取设置
        $settings = $this->get_settings();

        // 加载管理页面模板
        require_once AI_CONTENT_TOOLKIT_PLUGIN_PATH . 'modules/website-optimization/admin/admin-page.php';
        Website_Optimization_Admin_Page::get_instance()->render_page($site_info, $stats, $settings);
    }

    /**
     * 获取网站基本信息
     */
    public function get_site_info() {
        global $wpdb;

        // 优先从wpjam-basic获取SEO设置
        $wpjam_seo = $this->get_wpjam_seo_settings();

        $site_info = array(
            'site_title' => !empty($wpjam_seo['home_title']) ? $wpjam_seo['home_title'] : get_bloginfo('name'),
            'site_description' => !empty($wpjam_seo['home_description']) ? $wpjam_seo['home_description'] : get_bloginfo('description'),
            'site_url' => get_site_url(),
            'total_posts' => wp_count_posts('post')->publish,
            'total_pages' => wp_count_posts('page')->publish,
            'total_categories' => wp_count_terms('category'),
            'total_tags' => wp_count_terms('post_tag'),
            'last_analysis_date' => get_option('wordpress_ai_toolkit_last_website_analysis', __('从未分析', 'wordpress-ai-toolkit'))
        );

        // 获取主题信息
        $theme = wp_get_theme();
        $site_info['theme_name'] = $theme->get('Name');
        $site_info['theme_version'] = $theme->get('Version');

        return $site_info;
    }


    /**
     * 获取统计数据
     */
    public function get_statistics() {
        global $wpdb;

        $stats = array(
            'total_posts' => wp_count_posts('post')->publish,
            'posts_with_seo_title' => 0,
            'posts_with_seo_description' => 0,
            'posts_with_seo_keywords' => 0,
            'posts_with_featured_image' => 0,
            'posts_without_seo_data' => 0,
            'average_seo_score' => 0
        );

        // 计算有SEO数据的文章数量
        $posts_with_seo = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_status = 'publish'
            AND (
                post_title != ''
                OR post_excerpt != ''
            )
        ");

        $stats['posts_with_seo_data'] = $posts_with_seo;
        $stats['posts_without_seo_data'] = $stats['total_posts'] - $posts_with_seo;

        // 计算有特色图片的文章数量
        $posts_with_featured_image = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
        ");

        $stats['posts_with_featured_image'] = $posts_with_featured_image;

        return $stats;
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
        update_option('wordpress_ai_toolkit_website_optimization_settings', $this->settings);
    }

    /**
     * 分析网站SEO
     */
    public function analyze_website_seo() {
        $site_info = $this->get_site_info();

        // 执行AI分析
        $ai_analysis = $this->perform_ai_analysis($site_info);

        // 生成基础SEO报告
        $seo_report = array(
            'title_report' => $this->generate_title_seo_report(),
            'description_report' => $this->generate_description_seo_report(),
            'keyword_report' => $this->generate_keyword_seo_report(),
            'overall_recommendations' => $this->generate_overall_recommendations()
        );

        // 如果AI分析可用，将AI建议整合到报告中
        if ($ai_analysis['available'] && !empty($ai_analysis['suggestions'])) {
            $ai_suggestions = $ai_analysis['suggestions'];

            // 整合AI标题建议
            if (isset($ai_suggestions['suggested_titles'])) {
                $seo_report['title_report']['ai_suggested_titles'] = $ai_suggestions['suggested_titles'];
            }

            // 整合AI描述建议
            if (isset($ai_suggestions['suggested_descriptions'])) {
                $seo_report['description_report']['ai_suggested_descriptions'] = $ai_suggestions['suggested_descriptions'];
            }

            // 整合AI关键词建议
            if (isset($ai_suggestions['suggested_keywords'])) {
                $seo_report['keyword_report']['ai_suggested_keywords'] = $ai_suggestions['suggested_keywords'];
            }

            // 整合AI长尾关键词建议
            if (isset($ai_suggestions['suggested_longtail_keywords'])) {
                $seo_report['keyword_report']['ai_suggested_longtail_keywords'] = $ai_suggestions['suggested_longtail_keywords'];
            }

            // 添加AI分析摘要
            if (isset($ai_suggestions['analysis_summary'])) {
                $seo_report['ai_analysis_summary'] = $ai_suggestions['analysis_summary'];
            }
        }

        $analysis = array(
            'site_info' => $site_info,
            'seo_report' => $seo_report,
            'ai_analysis' => $ai_analysis,
            'analysis_date' => current_time('mysql')
        );

        // 保存分析结果
        update_option('wordpress_ai_toolkit_website_seo_analysis', $analysis);
        update_option('wordpress_ai_toolkit_last_website_analysis', current_time('mysql'));

        return $analysis;
    }


    /**
     * 生成标题SEO分析报告
     */
    private function generate_title_seo_report() {
        // 优先从wpjam-basic获取SEO设置
        $wpjam_seo = $this->get_wpjam_seo_settings();
        $site_title = !empty($wpjam_seo['home_title']) ? $wpjam_seo['home_title'] : get_bloginfo('name');
        $title_length = mb_strlen($site_title);

        $report = array(
            'current_title' => $site_title,
            'title_length' => $title_length,
            'analysis' => array(),
            'recommendations' => array(),
            'suggested_titles' => array()
        );

        // 标题长度分析 - 具体结果
        if ($title_length < 30) {
            $report['analysis'][] = sprintf(__('标题长度分析：当前标题过短（%d字符），建议扩展到30-60字符', 'wordpress-ai-toolkit'), $title_length);
            $report['recommendations'][] = __('具体优化：在标题中添加更多描述性词语，如"专业"、"优质"、"最新"等', 'wordpress-ai-toolkit');
        } elseif ($title_length > 60) {
            $report['analysis'][] = sprintf(__('标题长度分析：当前标题过长（%d字符），可能被搜索引擎截断', 'wordpress-ai-toolkit'), $title_length);
            $report['recommendations'][] = __('具体优化：精简标题内容，删除不必要的词语，保持在60字符以内', 'wordpress-ai-toolkit');
        } else {
            $report['analysis'][] = __('标题长度分析：标题长度适中，符合搜索引擎要求', 'wordpress-ai-toolkit');
        }

        // 标题内容分析 - 具体结果
        if (empty($site_title)) {
            $report['analysis'][] = __('标题内容分析：未设置网站标题', 'wordpress-ai-toolkit');
            $report['recommendations'][] = __('具体优化：请立即设置一个包含关键词和品牌名称的网站标题', 'wordpress-ai-toolkit');
        } else {
            $report['analysis'][] = sprintf(__('标题内容分析：当前标题为"%s"', 'wordpress-ai-toolkit'), $site_title);

            // 检查是否包含关键词
            $keywords = $this->extract_keywords_from_content();
            $contains_keywords = false;
            foreach ($keywords as $keyword) {
                if (strpos($site_title, $keyword) !== false) {
                    $contains_keywords = true;
                    break;
                }
            }

            if (!$contains_keywords) {
                $report['recommendations'][] = __('具体优化：确保标题包含主要关键词，格式建议："[关键词] - [品牌名称]" 或 "[品牌名称] | [核心业务]"', 'wordpress-ai-toolkit');
            }
        }

        // 生成具体的标题建议
        $keywords = $this->extract_keywords_from_content();
        $top_keywords = array_slice($keywords, 0, 3);

        if (!empty($top_keywords)) {
            $report['suggested_titles'] = array(
                sprintf(__('建议标题1：%s - %s', 'wordpress-ai-toolkit'), $top_keywords[0], $site_title),
                sprintf(__('建议标题2：%s | %s服务', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0]),
                sprintf(__('建议标题3：专业%s - %s官方网站', 'wordpress-ai-toolkit'), $top_keywords[0], $site_title),
                sprintf(__('建议标题4：%s %s - %s', 'wordpress-ai-toolkit'), $top_keywords[0], $top_keywords[1], $site_title),
                sprintf(__('建议标题5：%s - 专注%s和%s领域', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0], $top_keywords[1])
            );
        } else {
            $report['suggested_titles'] = array(
                sprintf(__('建议标题1：%s - 官方网站', 'wordpress-ai-toolkit'), $site_title),
                sprintf(__('建议标题2：%s | 专业服务提供商', 'wordpress-ai-toolkit'), $site_title),
                sprintf(__('建议标题3：欢迎访问%s - 优质内容分享', 'wordpress-ai-toolkit'), $site_title)
            );
        }

        return $report;
    }

    /**
     * 生成描述SEO分析报告
     */
    private function generate_description_seo_report() {
        // 优先从wpjam-basic获取SEO设置
        $wpjam_seo = $this->get_wpjam_seo_settings();
        $site_description = !empty($wpjam_seo['home_description']) ? $wpjam_seo['home_description'] : get_bloginfo('description');
        $description_length = mb_strlen($site_description);

        $report = array(
            'current_description' => $site_description,
            'description_length' => $description_length,
            'analysis' => array(),
            'recommendations' => array(),
            'suggested_descriptions' => array()
        );

        // 描述长度分析 - 具体结果
        if ($description_length < 50) {
            $report['analysis'][] = sprintf(__('描述长度分析：当前描述过短（%d字符），无法有效吸引用户点击', 'wordpress-ai-toolkit'), $description_length);
            $report['recommendations'][] = __('具体优化：将描述扩展到50-160字符，添加更多有价值的信息和关键词', 'wordpress-ai-toolkit');
        } elseif ($description_length > 160) {
            $report['analysis'][] = sprintf(__('描述长度分析：当前描述过长（%d字符），可能被搜索引擎截断', 'wordpress-ai-toolkit'), $description_length);
            $report['recommendations'][] = __('具体优化：精简描述内容，删除冗余信息，保持在160字符以内', 'wordpress-ai-toolkit');
        } else {
            $report['analysis'][] = __('描述长度分析：描述长度适中，符合搜索引擎要求', 'wordpress-ai-toolkit');
        }

        // 描述内容分析 - 具体结果
        if (empty($site_description)) {
            $report['analysis'][] = __('描述内容分析：未设置网站描述', 'wordpress-ai-toolkit');
            $report['recommendations'][] = __('具体优化：请立即设置一个包含关键词、核心价值和行动号召的网站描述', 'wordpress-ai-toolkit');
        } else {
            $report['analysis'][] = sprintf(__('描述内容分析：当前描述为"%s"', 'wordpress-ai-toolkit'), $site_description);

            // 描述内容质量分析 - 不应该检查关键词，应该分析描述本身的质量
            $description_quality = $this->analyze_description_quality($site_description);

            if (!$description_quality['is_good']) {
                $report['recommendations'][] = __('具体优化：确保描述具有吸引力、包含核心价值主张和行动号召，格式建议："[品牌名称]提供[核心服务]，帮助用户[解决问题]。了解更多信息请访问我们的网站。"', 'wordpress-ai-toolkit');
            }
        }

        // 生成具体的描述建议
        $keywords = $this->extract_keywords_from_content();
        $top_keywords = array_slice($keywords, 0, 3);
        $site_title = get_bloginfo('name');

        if (!empty($top_keywords)) {
            $report['suggested_descriptions'] = array(
                sprintf(__('建议描述1：%1$s专注于%2$s和%3$s领域，提供专业的%2$s服务和解决方案。我们致力于帮助用户解决%2$s相关问题，提供高质量的内容和资源。', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0], $top_keywords[1]),
                sprintf(__('建议描述2：欢迎访问%1$s - 您的%2$s专家。我们提供最新的%2$s资讯、实用技巧和深度分析，帮助您更好地理解和应用%2$s知识。', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0]),
                sprintf(__('建议描述3：%1$s是专业的%2$s平台，涵盖%3$s、%4$s等多个领域。我们为读者提供有价值的%2$s内容，帮助您提升技能和知识水平。', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0], $top_keywords[1], $top_keywords[2]),
                sprintf(__('建议描述4：探索%1$s的%2$s世界 - 从基础入门到高级应用，我们为您提供全面的%2$s指南和教程。加入我们的社区，与其他%2$s爱好者交流学习。', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0]),
                sprintf(__('建议描述5：%1$s - 您的%2$s资源中心。我们收集整理了大量关于%2$s和%3$s的优质内容，包括教程、案例分析和最佳实践，助您成为%2$s专家。', 'wordpress-ai-toolkit'), $site_title, $top_keywords[0], $top_keywords[1])
            );
        } else {
            $report['suggested_descriptions'] = array(
                sprintf(__('建议描述1：%s是一个专业的网站，致力于为用户提供有价值的内容和服务。我们关注用户体验，持续优化网站内容，确保为访客提供最佳的浏览体验。', 'wordpress-ai-toolkit'), $site_title),
                sprintf(__('建议描述2：欢迎访问%s，这里汇集了丰富的资源和信息。我们的目标是创建高质量的内容，帮助用户解决问题、获取知识和提升技能。', 'wordpress-ai-toolkit'), $site_title),
                sprintf(__('建议描述3：%s为您提供专业的服务和内容支持。我们注重内容质量和用户体验，致力于成为您信赖的信息来源和问题解决平台。', 'wordpress-ai-toolkit'), $site_title)
            );
        }

        return $report;
    }

    /**
     * 生成关键词SEO分析报告
     */
    private function generate_keyword_seo_report() {
        global $wpdb;

        $report = array(
            'current_keywords' => '',
            'keyword_count' => 0,
            'analysis' => array(),
            'recommendations' => array(),
            'suggested_keywords' => array()
        );

        // 尝试从不同来源获取网站关键字
        $site_keywords = $this->get_site_keywords();
        $report['current_keywords'] = $site_keywords;
        $report['keyword_count'] = !empty($site_keywords) ? count(explode(',', $site_keywords)) : 0;

        // 关键词存在性分析 - 具体结果
        if (empty($site_keywords)) {
            $report['analysis'][] = __('关键词分析：未设置网站关键词', 'wordpress-ai-toolkit');
            $report['recommendations'][] = __('具体优化：请立即设置3-5个核心关键词，用逗号分隔', 'wordpress-ai-toolkit');
        } else {
            $report['analysis'][] = sprintf(__('关键词分析：当前关键词为"%s"', 'wordpress-ai-toolkit'), $site_keywords);

            // 关键词数量分析
            $keyword_array = array_map('trim', explode(',', $site_keywords));
            $keyword_count = count($keyword_array);

            if ($keyword_count < 3) {
                $report['analysis'][] = sprintf(__('关键词数量分析：关键词数量过少（%d个），建议设置3-5个核心关键词', 'wordpress-ai-toolkit'), $keyword_count);
            } elseif ($keyword_count > 10) {
                $report['analysis'][] = sprintf(__('关键词数量分析：关键词数量过多（%d个），建议精简到3-5个核心关键词', 'wordpress-ai-toolkit'), $keyword_count);
            } else {
                $report['analysis'][] = sprintf(__('关键词数量分析：关键词数量适中（%d个），符合SEO最佳实践', 'wordpress-ai-toolkit'), $keyword_count);
            }

            // 关键词质量分析 - 分析关键词本身的质量，而不是与标签/分类的相关性
            $keyword_quality = $this->analyze_keyword_quality($keyword_array);

            if ($keyword_quality['is_good']) {
                $report['analysis'][] = __('关键词质量分析：关键词质量良好，具有商业价值和搜索潜力', 'wordpress-ai-toolkit');
            } else {
                $report['analysis'][] = __('关键词质量分析：关键词质量需要优化，建议选择更具商业价值的关键词', 'wordpress-ai-toolkit');
            }

            $report['recommendations'][] = __('具体优化：确保关键词在标题、描述和内容中自然分布', 'wordpress-ai-toolkit');
            $report['recommendations'][] = __('具体优化：创建长尾关键词，如"[核心关键词] 使用方法"', 'wordpress-ai-toolkit');
            $report['recommendations'][] = __('具体优化：确保关键词自然融入内容，避免堆砌', 'wordpress-ai-toolkit');
        }

        // 生成具体的关键词建议
        $keywords = $this->extract_keywords_from_content();
        $top_keywords = array_slice($keywords, 0, 5);

        if (!empty($top_keywords)) {
            $report['suggested_keywords'] = array(
                sprintf(__('核心关键词：%s', 'wordpress-ai-toolkit'), implode(', ', $top_keywords)),
                sprintf(__('长尾关键词：%s 使用方法', 'wordpress-ai-toolkit'), $top_keywords[0]),
                sprintf(__('长尾关键词：%s 教程', 'wordpress-ai-toolkit'), $top_keywords[0]),
                sprintf(__('长尾关键词：%s 技巧', 'wordpress-ai-toolkit'), $top_keywords[0]),
                sprintf(__('长尾关键词：%s %s', 'wordpress-ai-toolkit'), $top_keywords[0], $top_keywords[1]),
                sprintf(__('长尾关键词：%s 入门指南', 'wordpress-ai-toolkit'), $top_keywords[0]),
                sprintf(__('长尾关键词：%s 常见问题', 'wordpress-ai-toolkit'), $top_keywords[0]),
                sprintf(__('长尾关键词：%s 最佳实践', 'wordpress-ai-toolkit'), $top_keywords[0])
            );
        } else {
            $report['suggested_keywords'] = array(
                __('核心关键词：网站优化, SEO, 内容策略', 'wordpress-ai-toolkit'),
                __('长尾关键词：网站优化 方法', 'wordpress-ai-toolkit'),
                __('长尾关键词：SEO 优化技巧', 'wordpress-ai-toolkit'),
                __('长尾关键词：内容策略 指南', 'wordpress-ai-toolkit'),
                __('长尾关键词：网站SEO 最佳实践', 'wordpress-ai-toolkit')
            );
        }

        return $report;
    }

    /**
     * 分析描述内容质量
     */
    private function analyze_description_quality($description) {
        $quality = array(
            'is_good' => false,
            'reasons' => array()
        );

        // 检查描述是否包含价值主张
        $has_value_proposition = (strlen($description) > 30 &&
                                 (strpos($description, '提供') !== false ||
                                  strpos($description, '帮助') !== false ||
                                  strpos($description, '服务') !== false ||
                                  strpos($description, '解决') !== false));

        // 检查描述是否具有吸引力
        $has_attractive_elements = (strpos($description, '欢迎') !== false ||
                                   strpos($description, '专业') !== false ||
                                   strpos($description, '优质') !== false ||
                                   strpos($description, '最新') !== false);

        if ($has_value_proposition && $has_attractive_elements) {
            $quality['is_good'] = true;
            $quality['reasons'][] = __('描述包含明确的价值主张和吸引力元素', 'wordpress-ai-toolkit');
        } else {
            $quality['reasons'][] = __('描述缺乏明确的价值主张或吸引力元素', 'wordpress-ai-toolkit');
        }

        return $quality;
    }

    /**
     * 分析关键词质量
     */
    private function analyze_keyword_quality($keywords) {
        $quality = array(
            'is_good' => false,
            'reasons' => array()
        );

        // 检查关键词是否具有商业价值
        $has_commercial_value = false;
        $has_search_potential = false;

        foreach ($keywords as $keyword) {
            // 检查是否包含商业意图
            if (strpos($keyword, '服务') !== false ||
                strpos($keyword, '产品') !== false ||
                strpos($keyword, '购买') !== false ||
                strpos($keyword, '价格') !== false ||
                strpos($keyword, '咨询') !== false) {
                $has_commercial_value = true;
            }

            // 检查是否具有搜索潜力
            if (strlen($keyword) >= 2 && strlen($keyword) <= 20) {
                $has_search_potential = true;
            }
        }

        if ($has_commercial_value && $has_search_potential) {
            $quality['is_good'] = true;
            $quality['reasons'][] = __('关键词具有明确的商业价值和搜索潜力', 'wordpress-ai-toolkit');
        } else {
            $quality['reasons'][] = __('关键词缺乏明确的商业价值或搜索潜力', 'wordpress-ai-toolkit');
        }

        return $quality;
    }

    /**
     * 从内容中提取关键词
     */
    private function extract_keywords_from_content() {
        global $wpdb;

        $keywords = array();

        // 从标签中提取关键词
        $tags = $wpdb->get_results("
            SELECT t.name, COUNT(tr.object_id) as count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            WHERE tt.taxonomy = 'post_tag'
            AND p.post_status = 'publish'
            GROUP BY t.term_id
            ORDER BY count DESC
            LIMIT 10
        ");

        foreach ($tags as $tag) {
            $keywords[] = $tag->name;
        }

        // 从分类中提取关键词
        $categories = $wpdb->get_results("
            SELECT t.name, COUNT(tr.object_id) as count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            WHERE tt.taxonomy = 'category'
            AND p.post_status = 'publish'
            GROUP BY t.term_id
            ORDER BY count DESC
            LIMIT 10
        ");

        foreach ($categories as $category) {
            $keywords[] = $category->name;
        }

        // 去重并返回
        $keywords = array_unique($keywords);
        return array_slice($keywords, 0, 5);
    }


    /**
     * 生成总体优化建议
     */
    private function generate_overall_recommendations() {
        $recommendations = array();

        $recommendations[] = __('SEO优化总体建议：', 'wordpress-ai-toolkit');
        $recommendations[] = __('1. 标题优化：确保标题包含核心关键词，长度在30-60字符之间', 'wordpress-ai-toolkit');
        $recommendations[] = __('2. 描述优化：描述应包含关键词和行动号召，长度在50-160字符之间', 'wordpress-ai-toolkit');
        $recommendations[] = __('3. 关键词优化：选择3-5个核心关键词，在内容中自然分布', 'wordpress-ai-toolkit');
        $recommendations[] = __('4. 内容质量：定期发布高质量、原创的内容', 'wordpress-ai-toolkit');
        $recommendations[] = __('5. 用户体验：确保网站加载速度快，移动端友好', 'wordpress-ai-toolkit');

        return $recommendations;
    }

    /**
     * 执行竞争对手分析
     */
    private function perform_competitor_analysis() {
        // 这里可以实现竞争对手分析逻辑
        // 暂时返回空数组
        return array();
    }

    /**
     * 执行AI分析
     */
    private function perform_ai_analysis($site_info) {
        // 检查AI功能是否可用
        if (!function_exists('wordpress_ai_toolkit_is_ai_available') || !wordpress_ai_toolkit_is_ai_available()) {
            return array(
                'available' => false,
                'message' => __('AI功能未配置', 'wordpress-ai-toolkit')
            );
        }

        try {
            // 获取网站内容摘要用于AI分析
            $content_summary = $this->get_content_summary_for_ai();
            $keywords = $this->extract_keywords_from_content();

            // 优先从wpjam-basic获取SEO设置
            $wpjam_seo = $this->get_wpjam_seo_settings();
            $site_title = !empty($wpjam_seo['home_title']) ? $wpjam_seo['home_title'] : get_bloginfo('name');
            $site_description = !empty($wpjam_seo['home_description']) ? $wpjam_seo['home_description'] : get_bloginfo('description');

            // 构建AI分析提示
            $prompt = $this->build_ai_prompt($site_title, $site_description, $keywords, $content_summary);

            // 调用AI服务进行分析
            $ai_response = $this->call_ai_service($prompt);

            // 解析AI响应
            $ai_suggestions = $this->parse_ai_response($ai_response);

            return array(
                'available' => true,
                'analysis' => __('AI分析完成，已生成智能优化建议', 'wordpress-ai-toolkit'),
                'suggestions' => $ai_suggestions
            );
        } catch (Exception $e) {
            // AI分析失败时返回基础建议
            return array(
                'available' => false,
                'message' => sprintf(__('AI分析失败，使用基础建议: %s', 'wordpress-ai-toolkit'), $e->getMessage()),
                'suggestions' => $this->generate_fallback_suggestions()
            );
        }
    }

    /**
     * 获取内容摘要用于AI分析
     */
    private function get_content_summary_for_ai() {
        global $wpdb;

        $summary = array(
            'total_posts' => 0,
            'total_pages' => 0,
            'categories' => array(),
            'tags' => array(),
            'recent_titles' => array()
        );

        // 获取文章和页面数量
        $summary['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
        $summary['total_pages'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'");

        // 获取分类信息
        $categories = $wpdb->get_results("SELECT name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'category' LIMIT 10");
        foreach ($categories as $category) {
            $summary['categories'][] = $category->name;
        }

        // 获取标签信息
        $tags = $wpdb->get_results("SELECT name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'post_tag' LIMIT 15");
        foreach ($tags as $tag) {
            $summary['tags'][] = $tag->name;
        }

        // 获取最近文章标题
        $recent_posts = $wpdb->get_results("SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 10");
        foreach ($recent_posts as $post) {
            $summary['recent_titles'][] = $post->post_title;
        }

        return $summary;
    }

    /**
     * 构建AI分析提示
     */
    private function build_ai_prompt($site_title, $site_description, $keywords, $content_summary) {
        // 使用动态提示词设置
        if (function_exists('wordpress_ai_toolkit_get_prompt')) {
            $prompt = wordpress_ai_toolkit_get_prompt('website_optimization', array(
                'site_title' => $site_title,
                'site_description' => $site_description,
                'total_posts' => $content_summary['total_posts'],
                'total_pages' => $content_summary['total_pages'],
                'categories' => implode(', ', $content_summary['categories']),
                'tags' => implode(', ', $content_summary['tags']),
                'recent_titles' => implode(' | ', $content_summary['recent_titles'])
            ));
        } else {
            // 降级到默认提示词
            $prompt = "请为以下WordPress网站提供SEO优化建议：\n\n";
            $prompt .= "当前网站标题：{$site_title}\n";
            $prompt .= "当前网站描述：{$site_description}\n\n";

            $prompt .= "网站内容概况：\n";
            $prompt .= "- 文章数量：{$content_summary['total_posts']}\n";
            $prompt .= "- 页面数量：{$content_summary['total_pages']}\n";
            $prompt .= "- 主要分类：" . implode(', ', $content_summary['categories']) . "\n";
            $prompt .= "- 主要标签：" . implode(', ', $content_summary['tags']) . "\n";
            $prompt .= "- 最近文章标题：" . implode(' | ', $content_summary['recent_titles']) . "\n\n";

            $prompt .= "请基于以上信息，提供以下具体建议：\n";
            $prompt .= "1. 提供3个优化的网站标题建议（每个30-60字符）\n";
            $prompt .= "2. 提供3个优化的网站描述建议（每个50-160字符）\n";
            $prompt .= "3. 提供5个核心关键词和5个长尾关键词建议\n";
            $prompt .= "4. 简要说明每个建议的SEO优势\n\n";
            $prompt .= "请用JSON格式返回结果，包含以下字段：\n";
            $prompt .= "- suggested_titles: 数组，包含3个标题建议\n";
            $prompt .= "- suggested_descriptions: 数组，包含3个描述建议\n";
            $prompt .= "- suggested_keywords: 数组，包含5个核心关键词\n";
            $prompt .= "- suggested_longtail_keywords: 数组，包含5个长尾关键词\n";
            $prompt .= "- analysis_summary: 字符串，简要分析说明\n";
        }

        return $prompt;
    }

    /**
     * 调用AI服务
     */
    private function call_ai_service($prompt) {
        // 这里调用WordPress Toolkit的AI服务
        if (function_exists('wordpress_ai_toolkit_ai_request')) {
            return wordpress_ai_toolkit_ai_request($prompt);
        }

        // 如果AI服务不可用，抛出异常
        throw new Exception(__('AI服务不可用', 'wordpress-ai-toolkit'));
    }

    /**
     * 解析AI响应
     */
    private function parse_ai_response($ai_response) {
        // 尝试解析JSON响应
        $parsed_response = json_decode($ai_response, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_response)) {
            return $parsed_response;
        }

        // 如果JSON解析失败，返回基础建议
        return $this->generate_fallback_suggestions();
    }

    /**
     * 从wpjam-basic获取首页SEO设置
     * 优先获取wpjam-basic插件中的首页SEO设置
     */
    private function get_wpjam_seo_settings() {
        $seo_settings = array(
            'home_title' => '',
            'home_description' => '',
            'home_keywords' => ''
        );

        // 尝试从wpjam-basic获取SEO设置
        if (function_exists('get_option')) {
            $wpjam_seo_options = get_option('wpjam-seo');
            if (!empty($wpjam_seo_options)) {
                $seo_settings['home_title'] = isset($wpjam_seo_options['home_title']) ? $wpjam_seo_options['home_title'] : '';
                $seo_settings['home_description'] = isset($wpjam_seo_options['home_description']) ? $wpjam_seo_options['home_description'] : '';
                $seo_settings['home_keywords'] = isset($wpjam_seo_options['home_keywords']) ? $wpjam_seo_options['home_keywords'] : '';
            }
        }

        return $seo_settings;
    }

    /**
     * 获取网站关键词
     * 尝试从不同来源获取网站关键词，优先从wpjam-basic获取
     */
    private function get_site_keywords() {
        $keywords = '';

        // 1. 优先从wpjam-basic获取
        $wpjam_seo = $this->get_wpjam_seo_settings();
        if (!empty($wpjam_seo['home_keywords'])) {
            $keywords = $wpjam_seo['home_keywords'];
        }

        // 2. 尝试从主题设置中获取
        if (empty($keywords) && function_exists('get_theme_mod')) {
            $keywords = get_theme_mod('site_keywords', '');
        }

        // 3. 尝试从SEO插件中获取
        if (empty($keywords)) {
            // Yoast SEO
            if (function_exists('get_option')) {
                $yoast_options = get_option('wpseo_titles');
                if (!empty($yoast_options) && isset($yoast_options['metakey-home'])) {
                    $keywords = $yoast_options['metakey-home'];
                }
            }

            // All in One SEO
            if (empty($keywords) && function_exists('aioseo')) {
                $aioseo_options = aioseo()->options->searchAppearance->global->keywords;
                if (!empty($aioseo_options)) {
                    $keywords = $aioseo_options;
                }
            }

            // Rank Math
            if (empty($keywords) && function_exists('get_option')) {
                $rankmath_options = get_option('rank-math-options-titles');
                if (!empty($rankmath_options) && isset($rankmath_options['homepage_keywords'])) {
                    $keywords = $rankmath_options['homepage_keywords'];
                }
            }
        }

        // 4. 尝试从WordPress设置中获取
        if (empty($keywords)) {
            $keywords = get_option('site_keywords', '');
        }

        // 5. 如果仍然没有关键词，从内容中提取
        if (empty($keywords)) {
            $extracted_keywords = $this->extract_keywords_from_content();
            if (!empty($extracted_keywords)) {
                $keywords = implode(', ', array_slice($extracted_keywords, 0, 5));
            }
        }

        return $keywords;
    }

    /**
     * 生成备用建议（当AI分析失败时使用）
     */
    private function generate_fallback_suggestions() {
        $site_title = get_bloginfo('name');
        $keywords = $this->extract_keywords_from_content();
        $top_keywords = array_slice($keywords, 0, 3);

        $suggestions = array(
            'suggested_titles' => array(
                $site_title . ' - 官方网站',
                $site_title . ' | 专业内容分享平台',
                '欢迎访问' . $site_title . ' - 优质资源中心'
            ),
            'suggested_descriptions' => array(
                $site_title . '为您提供有价值的内容和服务。我们致力于创建高质量的内容，帮助用户解决问题和获取知识。',
                '探索' . $site_title . '的精彩世界。这里汇集了丰富的资源和信息，满足您的各种需求和兴趣。',
                $site_title . '是一个专业的平台，专注于提供优质的内容和服务。我们关注用户体验，持续优化网站功能。'
            ),
            'suggested_keywords' => array_slice($keywords, 0, 5),
            'suggested_longtail_keywords' => array(
                '网站优化方法',
                'SEO最佳实践',
                '内容策略指南',
                '用户体验优化',
                '网站性能提升'
            ),
            'analysis_summary' => '基于网站内容分析生成的优化建议。建议根据实际业务需求进一步调整。'
        );

        // 如果有关键词，生成更相关的建议
        if (!empty($top_keywords)) {
            $suggestions['suggested_titles'] = array(
                $top_keywords[0] . ' - ' . $site_title,
                $site_title . ' | ' . $top_keywords[0] . '专家',
                '专业' . $top_keywords[0] . ' - ' . $site_title
            );

            $suggestions['suggested_descriptions'] = array(
                $site_title . '专注于' . $top_keywords[0] . '领域，提供专业的服务和解决方案。我们致力于帮助用户解决相关问题。',
                '欢迎访问' . $site_title . ' - 您的' . $top_keywords[0] . '资源中心。我们提供最新的资讯和实用的技巧。',
                $site_title . '是专业的' . $top_keywords[0] . '平台，涵盖' . implode('、', $top_keywords) . '等多个领域。'
            );
        }

        return $suggestions;
    }

    /**
     * 处理AJAX获取保存的分析报告请求
     */
    public function handle_ajax_get_saved_analysis() {
        // 验证权限和nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-ai-toolkit'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'website_optimization_analyze')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-ai-toolkit'));
        }

        try {
            // 获取保存的分析报告
            $saved_analysis = get_option('wordpress_ai_toolkit_website_seo_analysis', false);

            if ($saved_analysis) {
                wp_send_json_success($saved_analysis);
            } else {
                wp_send_json_error(__('没有保存的分析报告', 'wordpress-ai-toolkit'));
            }
        } catch (Exception $e) {
            wp_send_json_error(sprintf(__('获取保存的分析报告失败: %s', 'wordpress-ai-toolkit'), $e->getMessage()));
        }
    }

    /**
     * 处理AJAX分析请求
     */
    public function handle_ajax_analyze() {
        // 验证权限和nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-ai-toolkit'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'website_optimization_analyze')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-ai-toolkit'));
        }

        try {
            $analysis = $this->analyze_website_seo();
            wp_send_json_success($analysis);
        } catch (Exception $e) {
            wp_send_json_error(sprintf(__('分析失败: %s', 'wordpress-ai-toolkit'), $e->getMessage()));
        }
    }


    /**
     * 处理AJAX保存设置请求
     */
    public function handle_ajax_save_settings() {
        // 验证权限和nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'wordpress-ai-toolkit'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'website_optimization_save_settings')) {
            wp_send_json_error(__('安全验证失败', 'wordpress-ai-toolkit'));
        }

        try {
            // 获取设置数据
            $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

            // 更新设置
            $this->update_settings($settings);

            wp_send_json_success(array(
                'message' => __('设置已保存', 'wordpress-ai-toolkit')
            ));
        } catch (Exception $e) {
            wp_send_json_error(sprintf(__('保存设置失败: %s', 'wordpress-ai-toolkit'), $e->getMessage()));
        }
    }

    /**
     * 初始化模块
     */
    public function init() {
        // 模块初始化逻辑
    }

    /**
     * 加载前端脚本和样式
     */
    public function enqueue_scripts() {
        // 前端功能脚本（如果需要）
    }

    /**
     * 加载管理脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
        // 只在网站优化管理页面加载
        if (strpos($hook, 'wordpress-ai-toolkit-website-optimization') === false) {
            return;
        }

        // 加载核心样式
        wp_enqueue_style(
            'website-optimization-css',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/website-optimization/assets/css/admin.css',
            array(),
            '1.0.0'
        );

        // 加载核心脚本
        wp_enqueue_script(
            'website-optimization-js',
            AI_CONTENT_TOOLKIT_PLUGIN_URL . 'modules/website-optimization/assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // 传递配置到前端
        wp_localize_script('website-optimization-js', 'WebsiteOptimizationConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'analyzeNonce' => wp_create_nonce('website_optimization_analyze'),
            'settingsNonce' => wp_create_nonce('website_optimization_save_settings'),
            'i18n' => array(
                'analyzing' => __('分析中...', 'wordpress-ai-toolkit'),
                'preparing' => __('正在准备...', 'wordpress-ai-toolkit'),
                'sendingRequest' => __('正在发送请求到服务器...', 'wordpress-ai-toolkit'),
                'completed' => __('完成', 'wordpress-ai-toolkit'),
                'analysisComplete' => __('网站SEO分析完成！', 'wordpress-ai-toolkit'),
                'analysisFailed' => __('SEO分析失败：', 'wordpress-ai-toolkit'),
                'overallScore' => __('整体得分：', 'wordpress-ai-toolkit'),
                'analysisTime' => __('分析时间：', 'wordpress-ai-toolkit'),
                'requestTimeout' => __('请求超时：处理时间过长，请稍后重试。', 'wordpress-ai-toolkit'),
                'networkError' => __('网络错误：', 'wordpress-ai-toolkit'),
                'unknownError' => __('未知错误', 'wordpress-ai-toolkit'),
                'settingsSaved' => __('设置已保存', 'wordpress-ai-toolkit'),
                'settingsSaveFailed' => __('保存设置失败', 'wordpress-ai-toolkit'),
                'excellent' => __('优秀', 'wordpress-ai-toolkit'),
                'good' => __('良好', 'wordpress-ai-toolkit'),
                'fair' => __('一般', 'wordpress-ai-toolkit'),
                'needsImprovement' => __('需要改进', 'wordpress-ai-toolkit'),
                'highPriority' => __('高优先级', 'wordpress-ai-toolkit'),
                'mediumPriority' => __('中优先级', 'wordpress-ai-toolkit'),
                'lowPriority' => __('低优先级', 'wordpress-ai-toolkit'),
                'normal' => __('一般', 'wordpress-ai-toolkit'),
                'action' => __('操作：', 'wordpress-ai-toolkit')
            )
        ));
    }
}

// 初始化模块
Website_Optimization_Module::get_instance();