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
         * 构建完整的AI分析报告
         */
        buildCompleteReport: function(data) {
            var html = '';
            var analysisData = data.analysis_data;

            // 基础信息
            html += '<div class="report-basic-info">';
            html += '<p><strong>文章：</strong>' + (data.post_title || '未知') + '</p>';
            html += '<p><strong>分析时间：</strong>' + (data.updated_at || '未知') + '</p>';
            html += '</div>';

            // 评分部分
            html += '<div class="report-scores">';
            html += '<h3>📊 评分详情</h3>';
            html += '<div class="scores-grid">';

            if (data.title_score !== undefined) {
                html += '<div class="score-item"><span class="score-label">标题得分:</span><span class="score-value">' + data.title_score + '</span></div>';
            }
            if (data.content_score !== undefined) {
                html += '<div class="score-item"><span class="score-label">内容得分:</span><span class="score-value">' + data.content_score + '</span></div>';
            }
            if (data.keyword_score !== undefined) {
                html += '<div class="score-item"><span class="score-label">关键词得分:</span><span class="score-value">' + data.keyword_score + '</span></div>';
            }
            if (data.readability_score !== undefined) {
                html += '<div class="score-item"><span class="score-label">可读性得分:</span><span class="score-value">' + data.readability_score + '</span></div>';
            }

            html += '<div class="score-item overall"><span class="score-label">整体得分:</span><span class="score-value">' + (data.overall_score || 0) + '</span></div>';
            html += '</div>';
            html += '</div>';

            // AI推荐
            if (data.recommendations && data.recommendations.length > 0) {
                html += '<div class="report-recommendations">';
                html += '<h3>🤖 AI推荐</h3>';
                html += '<div class="recommendations-list">';
                if (typeof data.recommendations === 'string') {
                    try {
                        var recs = JSON.parse(data.recommendations);
                        recs.forEach(function(rec) {
                            html += '<div class="recommendation-item"><p>' + rec + '</p></div>';
                        });
                    } catch (e) {
                        html += '<div class="recommendation-item"><p>' + data.recommendations + '</p></div>';
                    }
                } else if (Array.isArray(data.recommendations)) {
                    data.recommendations.forEach(function(rec) {
                        html += '<div class="recommendation-item"><p>' + rec + '</p></div>';
                    });
                }
                html += '</div>';
                html += '</div>';
            }

            // 关键词分析
            if (data.primary_keywords && data.primary_keywords.length > 0) {
                html += '<div class="report-keywords">';
                html += '<h3>🎯 主要关键词</h3>';
                html += '<div class="keywords-container">';
                if (typeof data.primary_keywords === 'string') {
                    try {
                        var keywords = JSON.parse(data.primary_keywords);
                        keywords.forEach(function(keyword) {
                            html += '<span class="keyword-chip">' + keyword + '</span>';
                        });
                    } catch (e) {
                        html += '<span class="keyword-chip">' + data.primary_keywords + '</span>';
                    }
                } else if (Array.isArray(data.primary_keywords)) {
                    data.primary_keywords.forEach(function(keyword) {
                        html += '<span class="keyword-chip">' + keyword + '</span>';
                    });
                }
                html += '</div>';
                html += '</div>';
            }

            // 内容分析
            if (analysisData && typeof analysisData === 'object') {
                html += '<div class="report-content-analysis">';
                html += '<h3>📝 内容分析</h3>';

                if (analysisData.word_count) {
                    html += '<p><strong>字数统计：</strong>' + analysisData.word_count + ' 字</p>';
                }

                if (analysisData.sentiment_analysis) {
                    html += '<p><strong>情感分析：</strong>' + analysisData.sentiment_analysis + '</p>';
                }

                if (analysisData.reading_time) {
                    html += '<p><strong>预计阅读时间：</strong>' + analysisData.reading_time + '</p>';
                }

                html += '</div>';
            }

            // 长度与结构分析
            if (data.word_count) {
                html += '<div class="report-structure">';
                html += '<h3>📏 结构分析</h3>';
                html += '<p><strong>总字数：</strong>' + data.word_count + ' 字</p>';

                if (data.paragraph_count) {
                    html += '<p><strong>段落数：</strong>' + data.paragraph_count + ' 段</p>';
                }

                if (data.heading_counts) {
                    html += '<p><strong>标题结构：</strong></p>';
                    Object.keys(data.heading_counts).forEach(function(heading) {
                        html += '<p class="heading-count"> ' + heading + '：' + data.heading_counts[heading] + ' 个</p>';
                    });
                }

                html += '</div>';
            }

            // 元信息建议
            if (data.ai_meta_info && (data.ai_meta_info.suggested_title || data.ai_meta_info.meta_description)) {
                html += '<div class="meta-info">';
                html += '<h3>📝 元信息建议</h3>';
                html += '<div class="meta-suggestions">';

                if (data.ai_meta_info.suggested_title) {
                    html += '<div class="meta-item">';
                    html += '<label>建议标题:</label>';
                    html += '<div class="meta-value selectable-text" data-type="suggested-title">' + this.escapeHtml(data.ai_meta_info.suggested_title) + '</div>';
                    html += '<button class="copy-meta-btn" data-text="' + this.escapeHtml(data.ai_meta_info.suggested_title) + '" title="复制建议标题">📋 复制</button>';
                    html += '</div>';
                }

                if (data.ai_meta_info.meta_description) {
                    html += '<div class="meta-item">';
                    html += '<label>Meta描述:</label>';
                    html += '<div class="meta-value selectable-text" data-type="meta-description">' + this.escapeHtml(data.ai_meta_info.meta_description) + '</div>';
                    html += '<button class="copy-meta-btn" data-text="' + this.escapeHtml(data.ai_meta_info.meta_description) + '" title="复制Meta描述">📋 复制</button>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';
            }

            return html;
        },

        /**
         * 显示SEO分析结果 - 全新完整AI分析报告
         */
        displayAnalysisResult: function(response, container) {
            var $container = $(container);
            $container.empty();

            const analysisData = response.report || response;

            // 构建完整的SEO分析报告，移除重复的"📊 SEO分析报告"标题
            var html = '<div class="seo-analysis-result">';
            html += '<div class="seo-report-header">';
            html += '<p class="report-post-id">文章ID: ' + (analysisData.post_id || '未知') + '</p>';
            html += '</div>';

            // 使用新的完整报告构建方法
            html += this.buildCompleteReport(analysisData);

            html += '</div>';

            $container.html(html);

            // 添加控制面板
            this.addControlPanel(container, analysisData);
        },

        /**
         * 简单报告显示（降级方案）
         */
        displaySimpleReport: function(data, container) {
            var $container = $(container);
            $container.empty();

            var html = '<div class="seo-analysis-result">';
            html += '<div class="seo-report-header">';
            html += '<p class="report-post-id">文章ID: ' + (data.post_id || '未知') + '</p>';
            html += '</div>';

            // 如果有完整的分析数据，显示详细报告
            if (data.analysis_data && typeof data.analysis_data === 'object') {
                html += this.buildCompleteReport(data);
            } else {
                html += '<p><strong>文章：</strong>' + (data.post_title || '未知') + '</p>';
                html += '<p><strong>整体得分：</strong>' + (data.overall_score || 0) + '</p>';
                html += '<div class="notice notice-info">';
                html += '<p>完整AI分析报告功能正在加载中...</p>';
                html += '</div>';
            }

            html += '</div>';

            $container.html(html);
        },

                // 然后尝试解析并显示结构化数据
                if (typeof parsedAnalysis === 'object' && Object.keys(parsedAnalysis).length > 0) {
                    // 关键词分析
                    if (parsedAnalysis.keywords && parsedAnalysis.keywords.length > 0) {
                        html += '<div class="analysis-section">';
                        html += '<h4>🎯 关键词分析</h4>';
                        html += '<div class="keywords-container">';
                        parsedAnalysis.keywords.forEach(function(keyword) {
                            html += '<span class="keyword-chip">' + keyword + '</span>';
                        });
                        html += '</div>';
                        html += '</div>';
                    }

                    // 详细优化建议
                    if (parsedAnalysis.recommendations && parsedAnalysis.recommendations.length > 0) {
                        html += '<div class="analysis-section">';
                        html += '<h4>💡 结构化优化建议</h4>';
                        html += '<div class="recommendations-detailed">';

                        parsedAnalysis.recommendations.forEach(function(rec, index) {
                            var priorityIcon = rec.priority === 'high' ? '🔴' : rec.priority === 'medium' ? '🟡' : '🟢';
                            html += '<div class="detailed-recommendation priority-' + (rec.priority || 'medium') + '">';
                            html += '<div class="rec-header">';
                            html += '<span class="rec-number">' + (index + 1) + '</span>';
                            html += '<span class="rec-priority">' + priorityIcon + '</span>';
                            html += '<h5 class="rec-title">' + (rec.title || '优化建议') + '</h5>';
                            html += '</div>';

                            if (rec.description) {
                                html += '<div class="rec-description">';
                                html += '<strong>问题描述：</strong><br>' + rec.description;
                                html += '</div>';
                            }

                            if (rec.action) {
                                html += '<div class="rec-action">';
                                html += '<strong>📋 具体操作步骤：</strong><br>';
                                html += '<div class="action-steps">' + rec.action.replace(/\n/g, '<br>') + '</div>';
                                html += '</div>';
                            }

                            html += '</div>';
                        });

                        html += '</div>';
                        html += '</div>';
                    }
                } else if (typeof aiFullAnalysis === 'string') {
                    // 尝试从原始文本解析JSON数据
                    try {
                        var aiData = JSON.parse(aiFullAnalysis);

                        // 关键词分析
                        if (aiData.keywords && aiData.keywords.length > 0) {
                            html += '<div class="analysis-section">';
                            html += '<h4>🎯 关键词分析</h4>';
                            html += '<div class="keywords-container">';
                            aiData.keywords.forEach(function(keyword) {
                                html += '<span class="keyword-chip">' + keyword + '</span>';
                            });
                            html += '</div>';
                            html += '</div>';
                        }

                        // 详细优化建议
                        if (aiData.recommendations && aiData.recommendations.length > 0) {
                            html += '<div class="analysis-section">';
                            html += '<h4>💡 详细优化建议</h4>';
                            html += '<div class="recommendations-detailed">';

                            aiData.recommendations.forEach(function(rec, index) {
                                var priorityIcon = rec.priority === 'high' ? '🔴' : rec.priority === 'medium' ? '🟡' : '🟢';
                                html += '<div class="detailed-recommendation priority-' + (rec.priority || 'medium') + '">';
                                html += '<div class="rec-header">';
                                html += '<span class="rec-number">' + (index + 1) + '</span>';
                                html += '<span class="rec-priority">' + priorityIcon + '</span>';
                                html += '<h5 class="rec-title">' + (rec.title || '优化建议') + '</h5>';
                                html += '</div>';

                                if (rec.description) {
                                    html += '<div class="rec-description">';
                                    html += '<strong>问题描述：</strong><br>' + rec.description;
                                    html += '</div>';
                                }

                                if (rec.action) {
                                    html += '<div class="rec-action">';
                                    html += '<strong>📋 具体操作步骤：</strong><br>';
                                    html += '<div class="action-steps">' + rec.action.replace(/\n/g, '<br>') + '</div>';
                                    html += '</div>';
                                }

                                html += '</div>';
                            });

                            html += '</div>';
                            html += '</div>';
                        }

                    } catch (e) {
                        // 如果无法解析JSON，已经在上面的原始文本中显示了
                        console.log('无法解析AI分析JSON:', e);
                    }
                }
            } else {
                // 如果没有完整AI分析，显示基本得分和建议
                html += '<div class="basic-analysis-section">';
                html += '<h3>📈 基本分析结果</h3>';

                // 各项得分
                var scores = [
                    {name: '标题得分', score: analysis.title_score || 0, key: 'title_score'},
                    {name: '内容得分', score: analysis.content_score || 0, key: 'content_score'},
                    {name: '关键词得分', score: analysis.keyword_score || 0, key: 'keyword_score'},
                    {name: '可读性得分', score: analysis.readability_score || 0, key: 'readability_score'}
                ];

                html += '<div class="scores-grid">';
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

                // 基本优化建议
                if (analysis.recommendations && analysis.recommendations.length > 0) {
                    html += '<div class="basic-recommendations">';
                    html += '<h4>优化建议</h4>';
                    analysis.recommendations.forEach(function(rec) {
                        var priorityClass = rec.priority || 'medium';
                        html += '<div class="recommendation-item priority-' + priorityClass + '">';
                        html += '<strong>' + rec.title + '</strong>';
                        if (rec.description) {
                            html += '<p>' + rec.description + '</p>';
                        }
                        html += '</div>';
                    });
                    html += '</div>';
                }

                html += '</div>';
            }

            // 技术统计信息
            html += '<div class="technical-stats-section">';
            html += '<h3>🔧 技术统计</h3>';
            html += '<div class="stats-grid">';
            html += '<div class="stat-item"><strong>图片数量：</strong>' + (analysis.image_count || 0) + '</div>';
            html += '<div class="stat-item"><strong>内部链接：</strong>' + (analysis.internal_links || 0) + '</div>';
            html += '<div class="stat-item"><strong>外部链接：</strong>' + (analysis.external_links || 0) + '</div>';
            html += '<div class="stat-item"><strong>分析时间：</strong>' + (analysis.analysis_time || 0) + 's</div>';
            html += '</div>';
            html += '</div>';

            html += '</div>';

            $container.html(html);

            // 动画效果
            setTimeout(function() {
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
                        // 为分析结果添加完整的AI数据
                        var enhancedData = {
                            report: data.analysis,
                            ai_full_analysis: data.analysis.raw_ai_analysis || data.analysis.detailed_analysis,
                            raw_analysis_data: data.analysis.analysis_data
                        };
                        $this.displayAnalysisResult(enhancedData, '#report-modal-content');
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
                modalHtml += '<h2>📊 详细SEO分析报告</h2>';
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
                    // 传递完整的数据对象，而不仅仅是report
                    $this.displayAnalysisResult(data, '#report-modal-content');
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
         * 重新生成SEO分析
         */
        regenerateAnalysis: function(postId) {
            if (!postId) {
                this.showMessage('无效的文章ID', 'error');
                return;
            }

            var $this = this;
            this.showLoading('正在重新生成完整SEO分析...');

            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'analyze_post_seo',
                    post_id: postId,
                    nonce: AutoExcerptConfig.seoNonce
                },
                success: function(response) {
                    $this.hideLoading();
                    if (response.success) {
                        $this.showMessage('SEO分析重新生成成功！', 'success');

                        // 等待一下然后刷新报告
                        setTimeout(function() {
                            $.ajax({
                                url: AutoExcerptConfig.ajaxUrl,
                                type: 'POST',
                                data: {
                                    action: 'get_seo_report',
                                    post_id: postId,
                                    nonce: AutoExcerptConfig.seoNonce
                                },
                                success: function(reportResponse) {
                                    if (reportResponse.success) {
                                        $this.displayAnalysisResult(reportResponse.data, '#report-modal-content');
                                    }
                                }
                            });
                        }, 1000);
                    } else {
                        $this.showMessage('重新生成失败：' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $this.hideLoading();
                    $this.showMessage('网络错误，请重试', 'error');
                }
            });
        },

        /**
         * 更新数据库架构
         */
        updateDatabaseSchema: function(postId) {
            var $this = this;
            this.showLoading('正在修复数据库架构...');

            $.ajax({
                url: AutoExcerptConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'update_seo_analysis_schema',
                    nonce: AutoExcerptConfig.seoNonce
                },
                success: function(response) {
                    $this.hideLoading();
                    if (response.success) {
                        $this.showMessage('数据库架构修复成功！现在可以重新生成完整的SEO分析了。', 'success');

                        // 如果提供了文章ID，自动重新生成分析
                        if (postId && postId > 0) {
                            setTimeout(function() {
                                $this.regenerateAnalysis(postId);
                            }, 1000);
                        }
                    } else {
                        $this.showMessage('数据库架构修复失败：' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $this.hideLoading();
                    $this.showMessage('网络错误，请重试', 'error');
                }
            });
        },

        /**
         * 显示消息
         */
        showMessage: function(message, type) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = '<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>';

            // 移除现有通知
            $('.wordpress-ai-toolkit-notice').remove();

            // 添加新通知
            $('body').prepend('<div class="wordpress-ai-toolkit-notice">' + notice + '</div>');

            // 自动移除
            setTimeout(function() {
                $('.wordpress-ai-toolkit-notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * 添加控制面板
         */
        addControlPanel: function(container, analysisData) {
            const $container = $(container);
            const hasIncompleteData = !analysisData.raw_ai_analysis ||
                                     analysisData.raw_ai_analysis.length < 100;

            if (hasIncompleteData) {
                const controlHTML = `
                    <div class="report-controls" style="margin-bottom: 20px;">
                        <div class="notice notice-info" style="padding: 15px;">
                            <p><strong>💡 提示：</strong>当前显示的是基础分析。点击按钮获取完整的AI分析报告。</p>
                            <div class="control-buttons" style="margin-top: 10px;">
                                <button type="button" class="button button-primary"
                                        onclick="SEOAnalyzer.regenerateAnalysis(${analysisData.post_id || 0})">
                                    🔄 重新生成完整分析
                                </button>
                                <button type="button" class="button"
                                        onclick="SEOAnalyzer.updateDatabaseSchema(${analysisData.post_id || 0})"
                                        style="margin-left: 8px;">
                                    🔧 修复数据库架构
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                $container.find('.seo-ai-report-container').prepend(controlHTML);
            }
        },

        /**
         * HTML转义
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
                    // 为分析结果添加完整的AI数据
                    var enhancedData = {
                        report: data.analysis,
                        ai_full_analysis: data.analysis.raw_ai_analysis || data.analysis.detailed_analysis,
                        raw_analysis_data: data.analysis.analysis_data
                    };
                    SEOAnalyzer.displayAnalysisResult(enhancedData, '#seo-analysis-result');
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

        // 绑定复制按钮事件
        $(document).on('click', '.copy-meta-btn', function() {
            var textToCopy = $(this).data('text');
            if (textToCopy) {
                // 创建临时textarea元素用于复制
                var tempTextArea = $('<textarea>');
                $('body').append(tempTextArea);
                tempTextArea.val(textToCopy).select();

                try {
                    var successful = document.execCommand('copy');
                    if (successful) {
                        // 显示复制成功提示
                        var originalText = $(this).text();
                        $(this).text('✅ 已复制');

                        // 2秒后恢复原文本
                        setTimeout(function() {
                            $(this).text(originalText);
                        }.bind(this), 2000);
                    } else {
                        alert('复制失败，请手动选择文本复制');
                    }
                } catch (err) {
                    alert('复制失败，请手动选择文本复制');
                }

                tempTextArea.remove();
            }
        });

        // 为现有的meta-card元素添加复制按钮
        function addCopyButtonsToMetaCards() {
            $('.meta-card .meta-item').each(function() {
                var $metaItem = $(this);
                var $metaValue = $metaItem.find('.meta-value');
                var $metaLabel = $metaItem.find('.meta-label');

                // 如果还没有复制按钮，并且有文本内容
                if ($metaValue.length && !$metaItem.find('.copy-meta-btn').length) {
                    var textToCopy = $metaValue.text().trim();
                    if (textToCopy) {
                        var labelText = $metaLabel.text().trim();
                        var title = '复制' + (labelText || '文本');

                        var $copyButton = $('<button class="copy-meta-btn" data-text="' + textToCopy + '" title="' + title + '">📋 复制</button>');
                        $metaItem.append($copyButton);
                    }
                }
            });
        }

        // 页面加载后为meta-card添加复制按钮
        setTimeout(addCopyButtonsToMetaCards, 100);

        // 监听DOM变化，为动态加载的meta-card添加复制按钮
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    setTimeout(addCopyButtonsToMetaCards, 50);
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // 允许文本选择
        $(document).on('click', '.selectable-text, .meta-value', function(e) {
            e.stopPropagation();
        });
    });
});