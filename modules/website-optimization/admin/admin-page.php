<?php
/**
 * Website Optimization Admin Page - 网站优化管理页面
 *
 * 基于文章优化模块的样式和布局，提供网站SEO分析和优化建议
 *
 * @version 1.0.0
 * @author WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Website Optimization Admin Page 类
 */
class Website_Optimization_Admin_Page {

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
        // 初始化操作
    }

    /**
     * 渲染管理页面
     */
    public function render_page($site_info, $stats, $settings) {
        ?>
        <div class="wrap">
            <h1><?php _e('网站SEO优化', 'wordpress-toolkit'); ?></h1>

            <!-- 统计信息面板 -->
            <div class="postbox" style="margin-top: 15px; margin-bottom: 10px;">
                <div class="inside" style="padding: 12px 15px;">
                    <div style="display: flex; align-items: center; gap: 30px; padding: 0; flex-wrap: wrap; justify-content: space-between;">
                        <div>
                            <strong><?php _e('网站标题', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-admin-site" style="color: #0073aa;"></span>
                                <?php echo esc_html($site_info['site_title']); ?>
                            </div>
                        </div>
                        <div>
                            <strong><?php _e('文章总数', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-post" style="color: #0073aa;"></span>
                                <?php echo number_format($site_info['total_posts']); ?>
                            </div>
                        </div>
                        <div>
                            <strong><?php _e('页面总数', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-admin-page" style="color: #0073aa;"></span>
                                <?php echo number_format($site_info['total_pages']); ?>
                            </div>
                        </div>
                        <div>
                            <strong><?php _e('最后分析', 'wordpress-toolkit'); ?></strong>
                            <div style="margin-top: 5px;">
                                <span class="dashicons dashicons-calendar" style="color: #0073aa;"></span>
                                <?php echo esc_html($site_info['last_analysis_date']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 操作按钮区域 -->
            <div class="postbox" style="margin-top: 10px;">
                <div class="inside" style="padding: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                        <!-- 左侧：操作按钮 -->
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <button type="button" id="analyze-website-seo" class="button button-primary">
                                <?php _e('分析网站SEO', 'wordpress-toolkit'); ?>
                            </button>
                            <span class="spinner" id="analysis-spinner" style="display: none; margin-left: 5px;"></span>
                        </div>
                    </div>

                    <!-- 分析进度 -->
                    <div id="analysis-progress" style="display: none; margin: 15px 0;">
                        <div class="progress-container">
                            <h4 id="progress-title"><?php _e('分析中...', 'wordpress-toolkit'); ?></h4>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill"></div>
                                </div>
                                <span class="progress-text" id="progress-text">0%</span>
                            </div>
                            <div class="progress-details" id="progress-details">
                                <span><?php _e('当前分析：', 'wordpress-toolkit'); ?><span id="current-analysis"><?php _e('准备中...', 'wordpress-toolkit'); ?></span></span>
                                <span><?php _e('已分析：', 'wordpress-toolkit'); ?><span id="processed-count">0</span> / <span id="total-count">0</span></span>
                                <span><?php _e('成功：', 'wordpress-toolkit'); ?><span id="success-count">0</span></span>
                                <span><?php _e('失败：', 'wordpress-toolkit'); ?><span id="error-count">0</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- 分析结果 -->
                    <div id="analysis-result" style="display: none; margin: 15px 0;"></div>

                    <!-- SEO分析报告区域 -->
                    <div id="seo-analysis-report" style="margin-top: 20px; display: none;">
                        <h3><?php _e('SEO分析报告', 'wordpress-toolkit'); ?></h3>

                        <!-- 标题SEO报告 -->
                        <div class="seo-report-section" id="title-report-section" style="display: none;">
                            <h4><?php _e('标题SEO分析', 'wordpress-toolkit'); ?></h4>
                            <div class="report-content">
                                <div class="report-item">
                                    <strong><?php _e('当前标题：', 'wordpress-toolkit'); ?></strong>
                                    <span id="current-title"></span>
                                </div>
                                <div class="report-item">
                                    <strong><?php _e('标题长度：', 'wordpress-toolkit'); ?></strong>
                                    <span id="title-length"></span> <?php _e('字符', 'wordpress-toolkit'); ?>
                                </div>
                                <div class="analysis-results">
                                    <h5><?php _e('分析结果：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="title-analysis"></ul>
                                </div>
                                <div class="recommendations">
                                    <h5><?php _e('优化建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="title-recommendations"></ul>
                                </div>
                                <div class="implementation-steps" id="title-implementation-section" style="display: none;">
                                    <h5><?php _e('📝 具体实施步骤：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="title-implementation-steps"></ul>
                                </div>
                                <div class="suggestions" id="title-suggestions-section" style="display: none;">
                                    <h5><?php _e('具体标题建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="title-suggestions"></ul>
                                </div>
                                <div class="ai-suggestions" id="ai-title-suggestions-section" style="display: none;">
                                    <h5><?php _e('🤖 AI智能标题建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="ai-title-suggestions"></ul>
                                </div>
                            </div>
                        </div>

                        <!-- 描述SEO报告 -->
                        <div class="seo-report-section" id="description-report-section" style="display: none;">
                            <h4><?php _e('描述SEO分析', 'wordpress-toolkit'); ?></h4>
                            <div class="report-content">
                                <div class="report-item">
                                    <strong><?php _e('当前描述：', 'wordpress-toolkit'); ?></strong>
                                    <span id="current-description"></span>
                                </div>
                                <div class="report-item">
                                    <strong><?php _e('描述长度：', 'wordpress-toolkit'); ?></strong>
                                    <span id="description-length"></span> <?php _e('字符', 'wordpress-toolkit'); ?>
                                </div>
                                <div class="analysis-results">
                                    <h5><?php _e('分析结果：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="description-analysis"></ul>
                                </div>
                                <div class="recommendations">
                                    <h5><?php _e('优化建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="description-recommendations"></ul>
                                </div>
                                <div class="implementation-steps" id="description-implementation-section" style="display: none;">
                                    <h5><?php _e('📝 具体实施步骤：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="description-implementation-steps"></ul>
                                </div>
                                <div class="suggestions" id="description-suggestions-section" style="display: none;">
                                    <h5><?php _e('具体描述建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="description-suggestions"></ul>
                                </div>
                                <div class="ai-suggestions" id="ai-description-suggestions-section" style="display: none;">
                                    <h5><?php _e('🤖 AI智能描述建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="ai-description-suggestions"></ul>
                                </div>
                            </div>
                        </div>

                        <!-- 关键字SEO报告 -->
                        <div class="seo-report-section" id="keyword-report-section" style="display: none;">
                            <h4><?php _e('关键字SEO分析', 'wordpress-toolkit'); ?></h4>
                            <div class="report-content">
                                <div class="report-item">
                                    <strong><?php _e('当前关键字：', 'wordpress-toolkit'); ?></strong>
                                    <span id="current-keywords"></span>
                                </div>
                                <div class="report-item">
                                    <strong><?php _e('关键字数量：', 'wordpress-toolkit'); ?></strong>
                                    <span id="keyword-count"></span> <?php _e('个', 'wordpress-toolkit'); ?>
                                </div>
                                <div class="analysis-results">
                                    <h5><?php _e('分析结果：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="keyword-analysis"></ul>
                                </div>
                                <div class="recommendations">
                                    <h5><?php _e('优化建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="keyword-recommendations"></ul>
                                </div>
                                <div class="implementation-steps" id="keyword-implementation-section" style="display: none;">
                                    <h5><?php _e('📝 具体实施步骤：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="keyword-implementation-steps"></ul>
                                </div>
                                <div class="suggestions" id="keyword-suggestions-section" style="display: none;">
                                    <h5><?php _e('具体关键字建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="keyword-suggestions"></ul>
                                </div>
                                <div class="ai-suggestions" id="ai-keyword-suggestions-section" style="display: none;">
                                    <h5><?php _e('🤖 AI智能关键字建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="ai-keyword-suggestions"></ul>
                                </div>
                                <div class="ai-suggestions" id="ai-longtail-suggestions-section" style="display: none;">
                                    <h5><?php _e('🤖 AI智能长尾关键字建议：', 'wordpress-toolkit'); ?></h5>
                                    <ul id="ai-longtail-suggestions"></ul>
                                </div>
                            </div>
                        </div>

                        <!-- 总体优化建议 -->
                        <div class="seo-report-section" id="overall-recommendations-section" style="display: none;">
                            <h4><?php _e('总体优化建议', 'wordpress-toolkit'); ?></h4>
                            <div class="report-content">
                                <ul id="overall-recommendations"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        /* SEO分析报告样式 */
        .seo-report-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .seo-report-section h4 {
            margin: 0 0 15px 0;
            color: #1d2327;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 8px;
        }

        .report-content {
            margin-top: 15px;
        }

        .report-item {
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #0073aa;
        }

        .report-item strong {
            color: #1d2327;
        }

        .analysis-results,
        .recommendations {
            margin-top: 20px;
        }

        .analysis-results h5,
        .recommendations h5 {
            margin: 0 0 10px 0;
            color: #1d2327;
            font-size: 14px;
            font-weight: 600;
        }

        .analysis-results ul,
        .recommendations ul {
            margin: 0;
            padding-left: 20px;
        }

        .analysis-results li {
            color: #666;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .recommendations li {
            color: #0073aa;
            margin-bottom: 8px;
            line-height: 1.4;
            font-weight: 500;
        }

        .implementation-steps {
            margin-top: 20px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
        }

        .implementation-steps h5 {
            margin: 0 0 10px 0;
            color: #28a745;
            font-size: 14px;
            font-weight: 600;
        }

        .implementation-steps ul {
            margin: 0;
            padding-left: 20px;
        }

        .implementation-steps li {
            color: #495057;
            margin-bottom: 10px;
            line-height: 1.5;
            background: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            border-left: 3px solid #28a745;
        }

        /* 进度条样式 */
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
            // 页面加载时检查是否有保存的分析报告
            checkSavedAnalysis();

            // 分析网站SEO
            $('#analyze-website-seo').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $spinner = $('#analysis-spinner');
                var $progress = $('#analysis-progress');
                var $result = $('#analysis-result');

                // 显示进度条
                $progress.show();
                $result.hide();
                $button.prop('disabled', true);

                // 初始化进度显示
                updateProgress('<?php _e('分析网站SEO', 'wordpress-toolkit'); ?>', 0, 0, 0, 0, '<?php _e('正在准备分析...', 'wordpress-toolkit'); ?>', 5);

                // 发送AJAX请求
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'website_optimization_analyze',
                        nonce: '<?php echo wp_create_nonce('website_optimization_analyze'); ?>',
                        timestamp: Date.now()
                    },
                    beforeSend: function() {
                        updateProgress('<?php _e('分析网站SEO', 'wordpress-toolkit'); ?>', 10, 0, 0, 0, '<?php _e('正在发送请求到服务器...', 'wordpress-toolkit'); ?>', 5);
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            updateProgress('<?php _e('分析网站SEO', 'wordpress-toolkit'); ?>', 100, 5, 5, 0, '<?php _e('分析完成', 'wordpress-toolkit'); ?>', 5);

                            // 显示SEO分析报告
                            displaySEOAnalysisReport(data.seo_report);

                            // 显示成功消息
                            var message = '<div class="notice notice-success is-dismissible"><p>' +
                                '<strong><?php _e('网站SEO分析完成！', 'wordpress-toolkit'); ?></strong><br>' +
                                '<?php _e('分析时间：', 'wordpress-toolkit'); ?>' + data.analysis_date +
                                '</p></div>';
                            $result.html(message).show();

                            // 5秒后隐藏进度条
                            setTimeout(function() {
                                $progress.hide();
                            }, 5000);

                        } else {
                            updateProgress('<?php _e('分析网站SEO', 'wordpress-toolkit'); ?>', 100, 0, 0, 0, '<?php _e('分析失败：', 'wordpress-toolkit'); ?>' + response.data.message, 5);
                            $result.html('<div class="notice notice-error"><p><strong><?php _e('SEO分析失败：', 'wordpress-toolkit'); ?></strong><br>' + response.data.message + '</p></div>').show();
                            setTimeout(function() {
                                $progress.hide();
                            }, 5000);
                        }

                        $button.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '';
                        if (status === 'timeout') {
                            errorMessage = '<?php _e('请求超时：处理时间过长，请稍后重试。', 'wordpress-toolkit'); ?>';
                        } else {
                            errorMessage = '<?php _e('网络错误：', 'wordpress-toolkit'); ?>' + (error || '<?php _e('未知错误', 'wordpress-toolkit'); ?>');
                        }

                        updateProgress('<?php _e('分析网站SEO', 'wordpress-toolkit'); ?>', 100, 0, 0, 0, errorMessage, 5);
                        $result.html('<div class="notice notice-error"><p><strong><?php _e('分析失败：', 'wordpress-toolkit'); ?></strong><br>' + errorMessage + '</p></div>').show();
                        setTimeout(function() {
                            $progress.hide();
                        }, 5000);
                        $button.prop('disabled', false);
                    }
                });
            });

            // 更新进度显示
            function updateProgress(title, percentage, processed, success, errors, currentAnalysis, totalCount) {
                $('#progress-title').text(title);
                $('#progress-fill').css('width', percentage + '%');
                $('#progress-text').text(percentage + '%');
                $('#current-analysis').text(currentAnalysis);
                $('#processed-count').text(processed);
                $('#success-count').text(success);
                $('#error-count').text(errors);
                $('#total-count').text(totalCount);

                if (percentage === 100) {
                    setTimeout(function() {
                        $('#analysis-progress').fadeOut(500);
                    }, 3000);
                }
            }

            // 显示SEO分析报告
            function displaySEOAnalysisReport(seoReport) {
                // 显示SEO分析报告区域
                $('#seo-analysis-report').show();

                // 显示标题SEO报告
                if (seoReport.title_report) {
                    var titleReport = seoReport.title_report;
                    $('#current-title').text(titleReport.current_title);
                    $('#title-length').text(titleReport.title_length);

                    // 显示分析结果
                    var $titleAnalysis = $('#title-analysis');
                    $titleAnalysis.empty();
                    titleReport.analysis.forEach(function(item) {
                        $titleAnalysis.append('<li>' + item + '</li>');
                    });

                    // 显示优化建议
                    var $titleRecommendations = $('#title-recommendations');
                    $titleRecommendations.empty();
                    titleReport.recommendations.forEach(function(item) {
                        $titleRecommendations.append('<li>' + item + '</li>');
                    });

                    // 显示实施步骤
                    if (titleReport.implementation_steps && titleReport.implementation_steps.length > 0) {
                        var $titleImplementation = $('#title-implementation-steps');
                        $titleImplementation.empty();
                        titleReport.implementation_steps.forEach(function(item) {
                            $titleImplementation.append('<li style="white-space: pre-line;">' + item + '</li>');
                        });
                        $('#title-implementation-section').show();
                    }

                    $('#title-report-section').show();
                }

                // 显示描述SEO报告
                if (seoReport.description_report) {
                    var descriptionReport = seoReport.description_report;
                    $('#current-description').text(descriptionReport.current_description);
                    $('#description-length').text(descriptionReport.description_length);

                    // 显示分析结果
                    var $descriptionAnalysis = $('#description-analysis');
                    $descriptionAnalysis.empty();
                    descriptionReport.analysis.forEach(function(item) {
                        $descriptionAnalysis.append('<li>' + item + '</li>');
                    });

                    // 显示优化建议
                    var $descriptionRecommendations = $('#description-recommendations');
                    $descriptionRecommendations.empty();
                    descriptionReport.recommendations.forEach(function(item) {
                        $descriptionRecommendations.append('<li>' + item + '</li>');
                    });

                    // 显示实施步骤
                    if (descriptionReport.implementation_steps && descriptionReport.implementation_steps.length > 0) {
                        var $descriptionImplementation = $('#description-implementation-steps');
                        $descriptionImplementation.empty();
                        descriptionReport.implementation_steps.forEach(function(item) {
                            $descriptionImplementation.append('<li style="white-space: pre-line;">' + item + '</li>');
                        });
                        $('#description-implementation-section').show();
                    }

                    $('#description-report-section').show();
                }

                // 显示关键字SEO报告
                if (seoReport.keyword_report) {
                    var keywordReport = seoReport.keyword_report;
                    $('#current-keywords').text(keywordReport.current_keywords);
                    $('#keyword-count').text(keywordReport.keyword_count);

                    // 显示分析结果
                    var $keywordAnalysis = $('#keyword-analysis');
                    $keywordAnalysis.empty();
                    keywordReport.analysis.forEach(function(item) {
                        $keywordAnalysis.append('<li>' + item + '</li>');
                    });

                    // 显示优化建议
                    var $keywordRecommendations = $('#keyword-recommendations');
                    $keywordRecommendations.empty();
                    keywordReport.recommendations.forEach(function(item) {
                        $keywordRecommendations.append('<li>' + item + '</li>');
                    });

                    // 显示实施步骤
                    if (keywordReport.implementation_steps && keywordReport.implementation_steps.length > 0) {
                        var $keywordImplementation = $('#keyword-implementation-steps');
                        $keywordImplementation.empty();
                        keywordReport.implementation_steps.forEach(function(item) {
                            $keywordImplementation.append('<li style="white-space: pre-line;">' + item + '</li>');
                        });
                        $('#keyword-implementation-section').show();
                    }

                    $('#keyword-report-section').show();
                }

                // 显示总体优化建议
                if (seoReport.overall_recommendations) {
                    var $overallRecommendations = $('#overall-recommendations');
                    $overallRecommendations.empty();
                    seoReport.overall_recommendations.forEach(function(item) {
                        $overallRecommendations.append('<li>' + item + '</li>');
                    });
                    $('#overall-recommendations-section').show();
                }
            }

            // 检查是否有保存的分析报告
            function checkSavedAnalysis() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'website_optimization_get_saved_analysis',
                        nonce: '<?php echo wp_create_nonce('website_optimization_analyze'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // 显示保存的分析报告
                            displaySEOAnalysisReport(response.data.seo_report);

                            // 显示成功消息
                            var message = '<div class="notice notice-info is-dismissible"><p>' +
                                '<strong><?php _e('已加载保存的SEO分析报告', 'wordpress-toolkit'); ?></strong><br>' +
                                '<?php _e('分析时间：', 'wordpress-toolkit'); ?>' + response.data.analysis_date +
                                '</p></div>';
                            $('#analysis-result').html(message).show();
                        }
                    },
                    error: function() {
                        // 没有保存的分析报告，静默失败
                    }
                });
            }
        });
        </script>
        <?php
    }
}

// 初始化管理页面
Website_Optimization_Admin_Page::get_instance();