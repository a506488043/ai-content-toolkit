/**
 * Website Optimization Admin JavaScript
 *
 * 处理网站SEO分析功能的AJAX交互
 */

(function($) {
    'use strict';

    /**
     * 网站优化模块主对象
     */
    var WebsiteOptimization = {

        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            // 分析网站SEO
            $('#analyze-website-seo').on('click', this.analyzeWebsiteSEO.bind(this));
        },

        /**
         * 分析网站SEO
         */
        analyzeWebsiteSEO: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $spinner = $('#analysis-spinner');
            var $progress = $('#analysis-progress');
            var $result = $('#analysis-result');

            // 显示进度条
            $progress.show();
            $result.hide();
            $button.prop('disabled', true);
            $spinner.show();

            // 初始化进度显示
            this.updateProgress(
                WebsiteOptimizationConfig.i18n.analyzing,
                0, 0, 0, 0,
                WebsiteOptimizationConfig.i18n.preparing,
                5
            );

            // 发送AJAX请求
            $.ajax({
                url: WebsiteOptimizationConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'website_optimization_analyze',
                    nonce: WebsiteOptimizationConfig.analyzeNonce,
                    timestamp: new Date().getTime() // 添加时间戳避免缓存
                },
                beforeSend: function() {
                    WebsiteOptimization.updateProgress(
                        WebsiteOptimizationConfig.i18n.analyzing,
                        10, 0, 0, 0,
                        WebsiteOptimizationConfig.i18n.sendingRequest,
                        5
                    );
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        WebsiteOptimization.updateProgress(
                            WebsiteOptimizationConfig.i18n.analyzing,
                            100, 5, 5, 0,
                            WebsiteOptimizationConfig.i18n.completed,
                            5
                        );

                        // 显示SEO分析报告
                        WebsiteOptimization.displaySEOAnalysisReport(data.seo_report);

                        // 显示成功消息
                        var message = '<div class="notice notice-success is-dismissible"><p>' +
                            '<strong>' + WebsiteOptimizationConfig.i18n.analysisComplete + '</strong><br>' +
                            WebsiteOptimizationConfig.i18n.analysisTime + ': ' + data.analysis_date +
                            '</p></div>';
                        $result.html(message).show();

                        // 5秒后隐藏进度条
                        setTimeout(function() {
                            $progress.hide();
                        }, 5000);

                    } else {
                        WebsiteOptimization.updateProgress(
                            WebsiteOptimizationConfig.i18n.analyzing,
                            100, 0, 0, 0,
                            WebsiteOptimizationConfig.i18n.analysisFailed + ': ' + response.data.message,
                            5
                        );
                        $result.html('<div class="notice notice-error"><p><strong>' + WebsiteOptimizationConfig.i18n.analysisFailed + '</strong><br>' + response.data.message + '</p></div>').show();
                        setTimeout(function() {
                            $progress.hide();
                        }, 5000);
                    }

                    $button.prop('disabled', false);
                    $spinner.hide();
                },
                error: function(xhr, status, error) {
                    var errorMessage = '';
                    if (status === 'timeout') {
                        errorMessage = WebsiteOptimizationConfig.i18n.requestTimeout;
                    } else {
                        errorMessage = WebsiteOptimizationConfig.i18n.networkError + ': ' + (error || WebsiteOptimizationConfig.i18n.unknownError);
                    }

                    WebsiteOptimization.updateProgress(
                        WebsiteOptimizationConfig.i18n.analyzing,
                        100, 0, 0, 0,
                        errorMessage,
                        5
                    );
                    $result.html('<div class="notice notice-error"><p><strong>' + WebsiteOptimizationConfig.i18n.analysisFailed + '</strong><br>' + errorMessage + '</p></div>').show();
                    setTimeout(function() {
                        $progress.hide();
                    }, 5000);
                    $button.prop('disabled', false);
                    $spinner.hide();
                }
            });
        },

        /**
         * 更新进度显示
         */
        updateProgress: function(title, percentage, processed, success, errors, currentAnalysis, totalCount) {
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
        },

        /**
         * 显示SEO分析报告
         */
        displaySEOAnalysisReport: function(seoReport) {
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

                // 显示优化结果
                if (titleReport.optimization_results && titleReport.optimization_results.length > 0) {
                    var $titleOptimizationResults = $('#title-optimization-results');
                    if ($titleOptimizationResults.length === 0) {
                        $titleAnalysis.after('<div class="optimization-results"><h5>优化结果：</h5><ul id="title-optimization-results"></ul></div>');
                        $titleOptimizationResults = $('#title-optimization-results');
                    } else {
                        $titleOptimizationResults.empty();
                    }
                    titleReport.optimization_results.forEach(function(item) {
                        $titleOptimizationResults.append('<li style="color: #0073aa; font-weight: 600;">' + item + '</li>');
                    });
                }

                // 显示具体答案
                if (titleReport.specific_answers && titleReport.specific_answers.length > 0) {
                    var $titleSpecificAnswers = $('#title-specific-answers');
                    if ($titleSpecificAnswers.length === 0) {
                        $('#title-optimization-results').parent().after('<div class="specific-answers"><h5>具体答案：</h5><ul id="title-specific-answers"></ul></div>');
                        $titleSpecificAnswers = $('#title-specific-answers');
                    } else {
                        $titleSpecificAnswers.empty();
                    }
                    titleReport.specific_answers.forEach(function(item) {
                        $titleSpecificAnswers.append('<li style="color: #00a32a;">' + item + '</li>');
                    });
                }

                // 显示优化建议
                var $titleRecommendations = $('#title-recommendations');
                $titleRecommendations.empty();
                titleReport.recommendations.forEach(function(item) {
                    $titleRecommendations.append('<li>' + item + '</li>');
                });

                // 显示具体标题建议
                if (titleReport.suggested_titles && titleReport.suggested_titles.length > 0) {
                    var $titleSuggestions = $('#title-suggestions');
                    $titleSuggestions.empty();
                    titleReport.suggested_titles.forEach(function(item) {
                        $titleSuggestions.append('<li>' + item + '</li>');
                    });
                    $('#title-suggestions-section').show();
                }

                // 显示AI标题建议
                if (titleReport.ai_suggested_titles && titleReport.ai_suggested_titles.length > 0) {
                    var $aiTitleSuggestions = $('#ai-title-suggestions');
                    $aiTitleSuggestions.empty();
                    titleReport.ai_suggested_titles.forEach(function(item) {
                        $aiTitleSuggestions.append('<li class="ai-suggestion">🤖 ' + item + '</li>');
                    });
                    $('#ai-title-suggestions-section').show();
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

                // 显示优化结果
                if (descriptionReport.optimization_results && descriptionReport.optimization_results.length > 0) {
                    var $descriptionOptimizationResults = $('#description-optimization-results');
                    if ($descriptionOptimizationResults.length === 0) {
                        $descriptionAnalysis.after('<div class="optimization-results"><h5>优化结果：</h5><ul id="description-optimization-results"></ul></div>');
                        $descriptionOptimizationResults = $('#description-optimization-results');
                    } else {
                        $descriptionOptimizationResults.empty();
                    }
                    descriptionReport.optimization_results.forEach(function(item) {
                        $descriptionOptimizationResults.append('<li style="color: #0073aa; font-weight: 600;">' + item + '</li>');
                    });
                }

                // 显示具体答案
                if (descriptionReport.specific_answers && descriptionReport.specific_answers.length > 0) {
                    var $descriptionSpecificAnswers = $('#description-specific-answers');
                    if ($descriptionSpecificAnswers.length === 0) {
                        $('#description-optimization-results').parent().after('<div class="specific-answers"><h5>具体答案：</h5><ul id="description-specific-answers"></ul></div>');
                        $descriptionSpecificAnswers = $('#description-specific-answers');
                    } else {
                        $descriptionSpecificAnswers.empty();
                    }
                    descriptionReport.specific_answers.forEach(function(item) {
                        $descriptionSpecificAnswers.append('<li style="color: #00a32a;">' + item + '</li>');
                    });
                }

                // 显示优化建议
                var $descriptionRecommendations = $('#description-recommendations');
                $descriptionRecommendations.empty();
                descriptionReport.recommendations.forEach(function(item) {
                    $descriptionRecommendations.append('<li>' + item + '</li>');
                });

                // 显示具体描述建议
                if (descriptionReport.suggested_descriptions && descriptionReport.suggested_descriptions.length > 0) {
                    var $descriptionSuggestions = $('#description-suggestions');
                    $descriptionSuggestions.empty();
                    descriptionReport.suggested_descriptions.forEach(function(item) {
                        $descriptionSuggestions.append('<li>' + item + '</li>');
                    });
                    $('#description-suggestions-section').show();
                }

                // 显示AI描述建议
                if (descriptionReport.ai_suggested_descriptions && descriptionReport.ai_suggested_descriptions.length > 0) {
                    var $aiDescriptionSuggestions = $('#ai-description-suggestions');
                    $aiDescriptionSuggestions.empty();
                    descriptionReport.ai_suggested_descriptions.forEach(function(item) {
                        $aiDescriptionSuggestions.append('<li class="ai-suggestion">🤖 ' + item + '</li>');
                    });
                    $('#ai-description-suggestions-section').show();
                }

                $('#description-report-section').show();
            }

            // 显示关键词SEO报告
            if (seoReport.keyword_report) {
                var keywordReport = seoReport.keyword_report;
                $('#total-tags').text(keywordReport.total_tags);
                $('#total-categories').text(keywordReport.total_categories);

                // 显示分析结果
                var $keywordAnalysis = $('#keyword-analysis');
                $keywordAnalysis.empty();
                keywordReport.analysis.forEach(function(item) {
                    $keywordAnalysis.append('<li>' + item + '</li>');
                });

                // 显示优化结果
                if (keywordReport.optimization_results && keywordReport.optimization_results.length > 0) {
                    var $keywordOptimizationResults = $('#keyword-optimization-results');
                    if ($keywordOptimizationResults.length === 0) {
                        $keywordAnalysis.after('<div class="optimization-results"><h5>优化结果：</h5><ul id="keyword-optimization-results"></ul></div>');
                        $keywordOptimizationResults = $('#keyword-optimization-results');
                    } else {
                        $keywordOptimizationResults.empty();
                    }
                    keywordReport.optimization_results.forEach(function(item) {
                        $keywordOptimizationResults.append('<li style="color: #0073aa; font-weight: 600;">' + item + '</li>');
                    });
                }

                // 显示具体答案
                if (keywordReport.specific_answers && keywordReport.specific_answers.length > 0) {
                    var $keywordSpecificAnswers = $('#keyword-specific-answers');
                    if ($keywordSpecificAnswers.length === 0) {
                        $('#keyword-optimization-results').parent().after('<div class="specific-answers"><h5>具体答案：</h5><ul id="keyword-specific-answers"></ul></div>');
                        $keywordSpecificAnswers = $('#keyword-specific-answers');
                    } else {
                        $keywordSpecificAnswers.empty();
                    }
                    keywordReport.specific_answers.forEach(function(item) {
                        $keywordSpecificAnswers.append('<li style="color: #00a32a;">' + item + '</li>');
                    });
                }

                // 显示优化建议
                var $keywordRecommendations = $('#keyword-recommendations');
                $keywordRecommendations.empty();
                keywordReport.recommendations.forEach(function(item) {
                    $keywordRecommendations.append('<li>' + item + '</li>');
                });

                // 显示具体关键词建议
                if (keywordReport.suggested_keywords && keywordReport.suggested_keywords.length > 0) {
                    var $keywordSuggestions = $('#keyword-suggestions');
                    $keywordSuggestions.empty();
                    keywordReport.suggested_keywords.forEach(function(item) {
                        $keywordSuggestions.append('<li>' + item + '</li>');
                    });
                    $('#keyword-suggestions-section').show();
                }

                // 显示AI关键词建议
                if (keywordReport.ai_suggested_keywords && keywordReport.ai_suggested_keywords.length > 0) {
                    var $aiKeywordSuggestions = $('#ai-keyword-suggestions');
                    $aiKeywordSuggestions.empty();
                    keywordReport.ai_suggested_keywords.forEach(function(item) {
                        $aiKeywordSuggestions.append('<li class="ai-suggestion">🤖 ' + item + '</li>');
                    });
                    $('#ai-keyword-suggestions-section').show();
                }

                // 显示AI长尾关键词建议
                if (keywordReport.ai_suggested_longtail_keywords && keywordReport.ai_suggested_longtail_keywords.length > 0) {
                    var $aiLongtailSuggestions = $('#ai-longtail-suggestions');
                    $aiLongtailSuggestions.empty();
                    keywordReport.ai_suggested_longtail_keywords.forEach(function(item) {
                        $aiLongtailSuggestions.append('<li class="ai-suggestion">🤖 ' + item + '</li>');
                    });
                    $('#ai-longtail-suggestions-section').show();
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
    };

    // 初始化
    $(document).ready(function() {
        WebsiteOptimization.init();
    });

})(jQuery);