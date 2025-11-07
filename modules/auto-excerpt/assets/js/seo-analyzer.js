/**
 * SEO分析器前端JavaScript
 */

jQuery(document).ready(function($) {

    // SEO分析器对象
    var SEOAnalyzer = {

        /**
         * 分析单篇文章SEO
         */
        analyzePost: function(postId, callback) {
            var $this = this;

            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'analyze_post_seo',
                    post_id: postId,
                    nonce: AutoExcerptConfig.seoNonce
                },
                beforeSend: function() {
                    if (callback && callback.onBefore) {
                        callback.onBefore();
                    }
                    $this.showLoading('正在分析文章SEO...');
                },
                success: function(response) {
                    if (response.success) {
                        $this.hideLoading();
                        if (callback && callback.onSuccess) {
                            callback.onSuccess(response.data);
                        }
                        $this.showSuccess(response.data.message);
                    } else {
                        $this.hideLoading();
                        if (callback && callback.onError) {
                            callback.onError(response.data);
                        }
                        $this.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $this.hideLoading();
                    if (callback && callback.onError) {
                        callback.onError({message: error});
                    }
                    $this.showError('网络错误，请重试');
                }
            });
        },

        /**
         * 批量分析文章SEO
         */
        batchAnalyze: function(batchSize, callback) {
            var $this = this;

            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'batch_analyze_seo',
                    batch_size: batchSize || 5,
                    nonce: AutoExcerptConfig.seoNonce
                },
                beforeSend: function() {
                    if (callback && callback.onBefore) {
                        callback.onBefore();
                    }
                    $this.showLoading('正在批量分析文章SEO...');
                },
                success: function(response) {
                    if (response.success) {
                        $this.hideLoading();
                        if (callback && callback.onSuccess) {
                            callback.onSuccess(response.data);
                        }
                        $this.showSuccess(response.data.message);
                    } else {
                        $this.hideLoading();
                        if (callback && callback.onError) {
                            callback.onError(response.data);
                        }
                        $this.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $this.hideLoading();
                    if (callback && callback.onError) {
                        callback.onError({message: error});
                    }
                    $this.showError('网络错误，请重试');
                }
            });
        },

        /**
         * 获取SEO分析报告
         */
        getReport: function(postId, callback) {
            var $this = this;

            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_seo_report',
                    post_id: postId,
                    nonce: AutoExcerptConfig.seoNonce
                },
                beforeSend: function() {
                    if (callback && callback.onBefore) {
                        callback.onBefore();
                    }
                    $this.showLoading('正在获取SEO报告...');
                },
                success: function(response) {
                    if (response.success) {
                        $this.hideLoading();
                        if (callback && callback.onSuccess) {
                            callback.onSuccess(response.data);
                        }
                    } else {
                        $this.hideLoading();
                        if (callback && callback.onError) {
                            callback.onError(response.data);
                        }
                        $this.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $this.hideLoading();
                    if (callback && callback.onError) {
                        callback.onError({message: error});
                    }
                    $this.showError('网络错误，请重试');
                }
            });
        },

        /**
         * 获取SEO统计信息
         */
        getStatistics: function(callback) {
            var $this = this;

            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_seo_statistics',
                    nonce: AutoExcerptConfig.seoNonce
                },
                beforeSend: function() {
                    if (callback && callback.onBefore) {
                        callback.onBefore();
                    }
                },
                success: function(response) {
                    if (response.success) {
                        if (callback && callback.onSuccess) {
                            callback.onSuccess(response.data);
                        }
                    } else {
                        if (callback && callback.onError) {
                            callback.onError(response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (callback && callback.onError) {
                        callback.onError({message: error});
                    }
                }
            });
        },

        /**
         * 显示SEO分析结果
         */
        displayAnalysisResult: function(analysis, container) {
            var $container = $(container);
            $container.empty();

            var html = '<div class="seo-analysis-result">';

            // 整体得分
            html += '<div class="seo-score-section">';
            html += '<h3>整体SEO得分</h3>';
            html += '<div class="score-display">';
            html += '<div class="score-circle" data-score="' + analysis.overall_score + '">';
            html += '<span class="score-number">' + analysis.overall_score + '</span>';
            html += '</div>';
            html += '<div class="score-label">' + this.getScoreLabel(analysis.overall_score) + '</div>';
            html += '</div>';
            html += '</div>';

            // 各项得分
            html += '<div class="seo-scores-breakdown">';
            html += '<h3>详细得分</h3>';
            html += '<div class="scores-grid">';

            var scores = [
                {name: '标题得分', score: analysis.title_score, key: 'title_score'},
                {name: '内容得分', score: analysis.content_score, key: 'content_score'},
                {name: '关键词得分', score: analysis.keyword_score, key: 'keyword_score'},
                {name: '可读性得分', score: analysis.readability_score, key: 'readability_score'}
            ];

            scores.forEach(function(item) {
                html += '<div class="score-item">';
                html += '<div class="score-bar">';
                html += '<div class="score-fill" style="width: ' + item.score + '%"></div>';
                html += '</div>';
                html += '<span class="score-name">' + item.name + '</span>';
                html += '<span class="score-value">' + item.score + '</span>';
                html += '</div>';
            });

            html += '</div>';
            html += '</div>';

            // 基本统计
            html += '<div class="seo-stats-section">';
            html += '<h3>内容统计</h3>';
            html += '<div class="stats-grid">';
            html += '<div class="stat-item"><span class="stat-label">字数：</span><span class="stat-value">' + analysis.word_count + '</span></div>';
            html += '<div class="stat-item"><span class="stat-label">标题长度：</span><span class="stat-value">' + analysis.title_length + '</span></div>';
            html += '<div class="stat-item"><span class="stat-label">图片数量：</span><span class="stat-value">' + analysis.image_count + '</span></div>';
            html += '<div class="stat-item"><span class="stat-label">内部链接：</span><span class="stat-value">' + analysis.internal_links + '</span></div>';
            html += '<div class="stat-item"><span class="stat-label">外部链接：</span><span class="stat-value">' + analysis.external_links + '</span></div>';
            html += '</div>';
            html += '</div>';

            // 关键词
            if (analysis.primary_keywords && analysis.primary_keywords.length > 0) {
                html += '<div class="seo-keywords-section">';
                html += '<h3>主要关键词</h3>';
                html += '<div class="keywords-list">';
                analysis.primary_keywords.forEach(function(keyword) {
                    html += '<span class="keyword-tag primary">' + keyword + '</span>';
                });
                html += '</div>';
                html += '</div>';
            }

            if (analysis.secondary_keywords && analysis.secondary_keywords.length > 0) {
                html += '<div class="seo-keywords-section">';
                html += '<h3>次要关键词</h3>';
                html += '<div class="keywords-list">';
                analysis.secondary_keywords.forEach(function(keyword) {
                    html += '<span class="keyword-tag secondary">' + keyword + '</span>';
                });
                html += '</div>';
                html += '</div>';
            }

            // 优化建议
            if (analysis.recommendations && analysis.recommendations.length > 0) {
                html += '<div class="seo-recommendations-section">';
                html += '<h3>优化建议</h3>';
                html += '<div class="recommendations-list">';

                analysis.recommendations.forEach(function(rec, index) {
                    var priorityClass = rec.priority || 'medium';
                    html += '<div class="recommendation-item priority-' + priorityClass + '">';
                    html += '<div class="recommendation-header">';
                    html += '<span class="recommendation-title">' + rec.title + '</span>';
                    html += '<span class="recommendation-priority">' + this.getPriorityLabel(rec.priority) + '</span>';
                    html += '</div>';
                    html += '<div class="recommendation-description">' + rec.description + '</div>';
                    if (rec.action) {
                        html += '<div class="recommendation-action"><strong>建议操作：</strong>' + rec.action + '</div>';
                    }
                    html += '</div>';
                }.bind(this));

                html += '</div>';
                html += '</div>';
            }

            html += '</div>';

            $container.html(html);

            // 动画效果
            setTimeout(function() {
                $container.find('.score-circle').addClass('animate');
                $container.find('.score-fill').each(function(index) {
                    var $this = $(this);
                    setTimeout(function() {
                        $this.addClass('animate');
                    }, index * 100);
                });
            }, 100);
        },

        /**
         * 显示SEO报告列表
         */
        displayReportsList: function(reports, container) {
            var $container = $(container);
            $container.empty();

            if (!reports || reports.length === 0) {
                $container.html('<p class="no-data">暂无SEO分析报告</p>');
                return;
            }

            var html = '<div class="seo-reports-list">';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>文章标题</th>';
            html += '<th>整体得分</th>';
            html += '<th>分析时间</th>';
            html += '<th>操作</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            reports.forEach(function(report) {
                var scoreClass = this.getScoreClass(report.overall_score);
                html += '<tr>';
                html += '<td><strong>' + report.post_title + '</strong></td>';
                html += '<td><span class="score-badge ' + scoreClass + '">' + report.overall_score + '</span></td>';
                html += '<td>' + report.updated_at + '</td>';
                html += '<td>';
                html += '<button type="button" class="button button-small view-report" data-post-id="' + report.post_id + '">查看报告</button>';
                html += '<button type="button" class="button button-small re-analyze" data-post-id="' + report.post_id + '">重新分析</button>';
                html += '</td>';
                html += '</tr>';
            }.bind(this));

            html += '</tbody>';
            html += '</table>';
            html += '</div>';

            $container.html(html);

            // 绑定事件
            $container.find('.view-report').on('click', function() {
                var postId = $(this).data('post-id');
                $this.showReportModal(postId);
            });

            $container.find('.re-analyze').on('click', function() {
                var postId = $(this).data('post-id');
                $this.analyzePost(postId, {
                    onSuccess: function(data) {
                        $this.displayAnalysisResult(data.analysis, '#report-modal-content');
                        // 刷新列表
                        location.reload();
                    }
                });
            });
        },

        /**
         * 显示SEO统计信息
         */
        displayStatistics: function(stats, container) {
            var $container = $(container);
            $container.empty();

            var html = '<div class="seo-statistics">';
            html += '<div class="stats-overview">';

            // 总分析数
            html += '<div class="stat-card">';
            html += '<div class="stat-number">' + (stats.total_analyses || 0) + '</div>';
            html += '<div class="stat-label">总分析数</div>';
            html += '</div>';

            // 平均得分
            html += '<div class="stat-card">';
            html += '<div class="stat-number">' + (stats.average_score ? parseFloat(stats.average_score).toFixed(1) : '0') + '</div>';
            html += '<div class="stat-label">平均得分</div>';
            html += '</div>';

            // 最近分析
            html += '<div class="stat-card">';
            html += '<div class="stat-number">' + (stats.recent_analyses || 0) + '</div>';
            html += '<div class="stat-label">最近7天</div>';
            html += '</div>';

            html += '</div>';

            // 得分分布
            if (stats.score_distribution) {
                html += '<div class="score-distribution">';
                html += '<h3>得分分布</h3>';
                html += '<div class="distribution-bars">';

                var distribution = [
                    {label: '优秀(80-100)', key: 'excellent', color: '#46b450'},
                    {label: '良好(60-79)', key: 'good', color: '#00a0d2'},
                    {label: '一般(40-59)', key: 'average', color: '#ffb900'},
                    {label: '较差(0-39)', key: 'poor', color: '#dc3232'}
                ];

                distribution.forEach(function(item) {
                    var count = stats.score_distribution[item.key] || 0;
                    var percentage = stats.total_analyses > 0 ? (count / stats.total_analyses * 100) : 0;
                    html += '<div class="distribution-item">';
                    html += '<div class="distribution-label">' + item.label + '</div>';
                    html += '<div class="distribution-bar">';
                    html += '<div class="distribution-fill" style="width: ' + percentage + '%; background-color: ' + item.color + '"></div>';
                    html += '</div>';
                    html += '<div class="distribution-count">' + count + '</div>';
                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';
            }

            html += '</div>';

            $container.html(html);
        },

        /**
         * 显示报告模态框
         */
        showReportModal: function(postId) {
            var $this = this;

            // 创建模态框
            if (!$('#seo-report-modal').length) {
                var modalHtml = '<div id="seo-report-modal" class="seo-report-modal" style="display: none;">';
                modalHtml += '<div class="modal-backdrop"></div>';
                modalHtml += '<div class="modal-content">';
                modalHtml += '<div class="modal-header">';
                modalHtml += '<h2>SEO分析报告</h2>';
                modalHtml += '<button type="button" class="modal-close">&times;</button>';
                modalHtml += '</div>';
                modalHtml += '<div class="modal-body" id="report-modal-content">';
                modalHtml += '<div class="loading">加载中...</div>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                $('body').append(modalHtml);
            }

            // 显示模态框
            $('#seo-report-modal').show();

            // 获取报告内容
            this.getReport(postId, {
                onSuccess: function(data) {
                    $this.displayAnalysisResult(data.report, '#report-modal-content');
                },
                onError: function(data) {
                    $('#report-modal-content').html('<p class="error">' + data.message + '</p>');
                }
            });

            // 绑定关闭事件
            $('#seo-report-modal .modal-close, #seo-report-modal .modal-backdrop').on('click', function() {
                $('#seo-report-modal').hide();
            });
        },

        /**
         * 获取得分标签
         */
        getScoreLabel: function(score) {
            if (score >= 80) return '优秀';
            if (score >= 60) return '良好';
            if (score >= 40) return '一般';
            return '较差';
        },

        /**
         * 获取得分样式类
         */
        getScoreClass: function(score) {
            if (score >= 80) return 'excellent';
            if (score >= 60) return 'good';
            if (score >= 40) return 'average';
            return 'poor';
        },

        /**
         * 获取优先级标签
         */
        getPriorityLabel: function(priority) {
            var labels = {
                'high': '高',
                'medium': '中',
                'low': '低'
            };
            return labels[priority] || '中';
        },

        /**
         * 显示加载状态
         */
        showLoading: function(message) {
            if (!$('#seo-loading').length) {
                var loadingHtml = '<div id="seo-loading" class="seo-loading" style="display: none;">';
                loadingHtml += '<div class="loading-backdrop"></div>';
                loadingHtml += '<div class="loading-content">';
                loadingHtml += '<div class="loading-spinner"></div>';
                loadingHtml += '<div class="loading-message">' + (message || '加载中...') + '</div>';
                loadingHtml += '</div>';
                loadingHtml += '</div>';
                $('body').append(loadingHtml);
            } else {
                $('#seo-loading .loading-message').text(message || '加载中...');
            }
            $('#seo-loading').show();
        },

        /**
         * 隐藏加载状态
         */
        hideLoading: function() {
            $('#seo-loading').hide();
        },

        /**
         * 显示成功消息
         */
        showSuccess: function(message) {
            this.showMessage(message, 'success');
        },

        /**
         * 显示错误消息
         */
        showError: function(message) {
            this.showMessage(message, 'error');
        },

        /**
         * 显示消息
         */
        showMessage: function(message, type) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = '<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>';

            // 移除现有通知
            $('.wordpress-toolkit-notice').remove();

            // 添加新通知
            $('body').prepend('<div class="wordpress-toolkit-notice">' + notice + '</div>');

            // 自动移除
            setTimeout(function() {
                $('.wordpress-toolkit-notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // 将SEO分析器暴露到全局
    window.SEOAnalyzer = SEOAnalyzer;

    // 页面加载完成后的初始化
    $(document).ready(function() {
        // 绑定单篇分析按钮
        $(document).on('click', '.analyze-seo-btn', function() {
            var postId = $(this).data('post-id');
            SEOAnalyzer.analyzePost(postId, {
                onSuccess: function(data) {
                    SEOAnalyzer.displayAnalysisResult(data.analysis, '#seo-analysis-result');
                }
            });
        });

        // 绑定批量分析按钮
        $(document).on('click', '.batch-analyze-seo-btn', function() {
            var batchSize = parseInt($(this).data('batch-size')) || 5;
            SEOAnalyzer.batchAnalyze(batchSize, {
                onSuccess: function(data) {
                    // 刷新页面或更新列表
                    if (data.result && data.result.analyzed > 0) {
                        location.reload();
                    }
                }
            });
        });

        // 加载统计信息
        if ($('#seo-statistics-container').length) {
            SEOAnalyzer.getStatistics({
                onSuccess: function(data) {
                    SEOAnalyzer.displayStatistics(data.statistics, '#seo-statistics-container');
                }
            });
        }

        // 加载报告列表
        if ($('#seo-reports-container').length) {
            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_seo_reports_list',
                    nonce: AutoExcerptConfig.seoNonce
                },
                success: function(response) {
                    if (response.success && response.data.reports) {
                        SEOAnalyzer.displayReportsList(response.data.reports, '#seo-reports-container');
                    }
                }
            });
        }
    });
});