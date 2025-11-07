<?php
/**
 * AI SEO分析器核心类
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Excerpt_SEO_Analyzer extends WordPress_Toolkit_Module_Base {

    private $database;
    private $settings;

    public function __construct($settings = array()) {
        $this->database = new Auto_Excerpt_SEO_Analyzer_Database();
        $this->settings = wp_parse_args($settings, array(
            'ai_provider' => 'deepseek',
            'ai_model' => 'deepseek-chat',
            'api_key' => '',
            'api_base' => 'https://api.deepseek.com',
            'max_tokens' => 2000,
            'temperature' => 0.3,
            'analysis_timeout' => 30
        ));
    }

    /**
     * 分析单篇文章的SEO
     */
    public function analyze_post_seo($post_id) {
        $start_time = microtime(true);

        try {
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found: {$post_id}");
            }

            // 准备分析数据
            $content_data = $this->prepare_content_data($post);

            // 执行AI分析
            $ai_analysis = $this->perform_ai_analysis($content_data);

            // 计算各项得分
            $scores = $this->calculate_seo_scores($content_data, $ai_analysis);

            // 生成优化建议
            $recommendations = $this->generate_recommendations($content_data, $scores, $ai_analysis);

            // 构建完整分析结果
            $analysis_result = array_merge($content_data, $scores, array(
                'detailed_analysis' => $ai_analysis,
                'recommendations' => $recommendations,
                'ai_provider' => $this->settings['ai_provider'],
                'ai_model' => $this->settings['ai_model'],
                'analysis_version' => '1.0',
                'analysis_time' => round(microtime(true) - $start_time, 3)
            ));

            // 保存到数据库
            $save_result = $this->database->save_seo_analysis($post_id, $analysis_result);

            if (!$save_result) {
                throw new Exception("Failed to save SEO analysis result");
            }

            $this->log_module_action("SEO analysis completed for post: {$post_id}", 'info', array(
                'post_title' => $post->post_title,
                'overall_score' => $analysis_result['overall_score']
            ));

            return $analysis_result;

        } catch (Exception $e) {
            $this->log_module_action("SEO analysis failed for post: {$post_id} - " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 批量分析文章SEO
     */
    public function batch_analyze_posts($post_ids = array(), $batch_size = 5) {
        if (empty($post_ids)) {
            // 获取需要分析的文章
            $posts = $this->database->get_posts_for_analysis($batch_size * 2);
            $post_ids = wp_list_pluck($posts, 'ID');
        }

        if (empty($post_ids)) {
            return array(
                'success' => true,
                'message' => '没有需要分析的文章',
                'analyzed' => 0,
                'failed' => 0,
                'results' => array()
            );
        }

        return $this->process_in_batches($post_ids, array($this, 'analyze_post_seo'), $batch_size, 'seo-analyzer');
    }

    /**
     * 准备内容分析数据
     */
    private function prepare_content_data($post) {
        $content = $post->post_content;
        $title = $post->post_title;
        $excerpt = $post->post_excerpt;

        // 清理内容
        $content = $this->clean_content($content);

        // 提取纯文本
        $plain_text = $this->extract_plain_text($content);

        // 分析内容结构
        $structure_analysis = $this->analyze_content_structure($content);

        // 分析链接
        $link_analysis = $this->analyze_links($content);

        // 提取图片信息
        $image_analysis = $this->analyze_images($content);

        return array(
            'post_id' => $post->ID,
            'title' => $title,
            'content' => $content,
            'plain_text' => $plain_text,
            'excerpt' => $excerpt,
            'word_count' => $this->count_words($plain_text),
            'title_length' => mb_strlen($title),
            'meta_description_length' => mb_strlen($excerpt),
            'image_count' => count($image_analysis['images']),
            'heading_counts' => $structure_analysis['headings'],
            'internal_links' => $link_analysis['internal_count'],
            'external_links' => $link_analysis['external_count'],
            'structure_analysis' => $structure_analysis,
            'link_analysis' => $link_analysis,
            'image_analysis' => $image_analysis
        );
    }

    /**
     * 执行AI分析
     */
    private function perform_ai_analysis($content_data) {
        if (empty($this->settings['api_key'])) {
            throw new Exception('AI API key not configured');
        }

        $prompt = $this->build_analysis_prompt($content_data);

        $response = $this->call_ai_api($prompt);

        if (!$response) {
            throw new Exception('AI analysis request failed');
        }

        return $this->parse_ai_response($response);
    }

    /**
     * 构建AI分析提示词
     */
    private function build_analysis_prompt($content_data) {
        $max_content_length = 3000;
        $content = mb_substr($content_data['plain_text'], 0, $max_content_length);

        if (mb_strlen($content_data['plain_text']) > $max_content_length) {
            $content .= '...(内容已截断)';
        }

        return <<<PROMPT
作为一名专业的SEO分析师，请对以下文章进行全面深入的SEO分析，提供具体、可执行的优化建议。

文章标题：{$content_data['title']}
文章摘要：{$content_data['excerpt']}
文章字数：{$content_data['word_count']} 字
标题长度：{$content_data['title_length']} 字符

文章内容：
{$content}

请从以下8个维度进行详细分析，并提供具体改进建议：

1. **标题优化分析**
   - 标题长度是否合适（最佳50-60字符）
   - 是否包含目标关键词
   - 标题吸引力和点击率潜力
   - 数字、疑问词、情感词使用情况
   - 与内容相关性分析

2. **内容质量评估**
   - 内容深度和完整性
   - 信息价值和原创性
   - 可读性和易懂性
   - 段落结构和逻辑性
   - 字数是否充足（建议1500+字）

3. **关键词策略**
   - 识别主要目标关键词（3-5个）
   - 长尾关键词机会（5-8个）
   - 关键词密度分析（建议2-3%）
   - 关键词分布合理性
   - LSI语义相关词建议

4. **内容结构优化**
   - H1-H6标签使用情况
   - 段落长度控制（建议100-200字）
   - 列表和格式化使用
   - 内部链接建设建议
   - 信息的层次结构

5. **技术SEO检查**
   - 图片alt属性优化
   - 元描述优化建议
   - URL结构优化
   - 页面加载速度因素
   - 移动端适配

6. **用户体验优化**
   - 内容可扫读性
   - 重点信息突出显示
   - 视觉元素使用
   - 阅读时间预估
   - 互动元素建议

7. **竞争对手分析**
   - 同类文章常见做法
   - 差异化机会
   - 内容覆盖度比较
   - 独特价值主张

8. **具体执行计划**
   - 优先级排序的改进行动
   - 预期效果预估
   - 实施时间建议
   - 成功指标定义

请返回JSON格式分析报告：
{
    "keywords": ["关键词1", "关键词2", "关键词3", "关键词4", "关键词5"],
    "recommendations": [
        {
            "title": "优化建议标题",
            "description": "问题的具体说明",
            "action": "详细的操作步骤（分步骤说明，每步具体怎么做）",
            "priority": "high"
        }
    ]
}

要求：
- keywords: 提供5个最相关的关键词
- recommendations: 提供6-8条具体的、可执行的优化建议
- 每条建议都要包含"标题-说明-行动步骤"
- action字段必须提供详细的分步骤操作指南，而不是简单的描述
- priority: 用high/medium/low表示优先级
- 确保建议实用性强、可操作
- action字段内容要详尽，包含具体的实施步骤
- 请只返回JSON格式，不要添加任何其他文字说明

PROMPT;
    }

    /**
     * 调用AI API
     */
    private function call_ai_api($prompt) {
        $api_url = rtrim($this->settings['api_base'], '/') . '/v1/chat/completions';

        $request_data = array(
            'model' => $this->settings['ai_model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => '你是一个专业的SEO分析师，擅长内容优化和搜索引擎优化。请提供准确、实用的分析建议。'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $this->settings['max_tokens'],
            'temperature' => $this->settings['temperature'],
            'stream' => false
        );

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->settings['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($request_data),
            'timeout' => $this->settings['analysis_timeout'],
            'method' => 'POST'
        ));

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            throw new Exception("API request failed with status code: {$response_code}");
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API response parsing failed');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response format');
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * 解析AI响应
     */
    private function parse_ai_response($response) {
        // 清理响应内容
        $response = trim($response);

        // 尝试多种方式提取JSON
        $json_content = null;

        // 方法1: 提取```json代码块
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $json_content = trim($matches[1]);
        }
        // 方法2: 提取大括号内容
        elseif (preg_match('/\{.*\}/s', $response, $matches)) {
            $json_content = trim($matches[0]);
        }
        // 方法3: 直接使用响应
        else {
            $json_content = $response;
        }

        // 记录基本信息（避免中文字符编码问题）
        error_log('[SEO ANALYZER] Original AI response length: ' . strlen($response));
        error_log('[SEO ANALYZER] Extracted JSON content length: ' . strlen($json_content));

        // 尝试解析JSON
        $analysis_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果解析失败，生成一个默认的分析结果
            error_log('[SEO ANALYZER] JSON parsing failed, using default analysis: ' . json_last_error_msg());
            return $this->generate_default_analysis($json_content);
        }

        // 添加成功解析的调试信息（完全避免中文字符编码问题）
        if (isset($analysis_data['recommendations']) && is_array($analysis_data['recommendations'])) {
            $recommendation_count = count($analysis_data['recommendations']);
            error_log('[SEO ANALYZER] Successfully parsed ' . $recommendation_count . ' recommendations');

            $action_count = 0;
            $total_action_length = 0;
            foreach ($analysis_data['recommendations'] as $index => $rec) {
                if (isset($rec['action']) && !empty($rec['action'])) {
                    $action_count++;
                    $total_action_length += strlen($rec['action']);
                }
            }

            error_log("[SEO ANALYZER] Recommendations with actions: {$action_count}/{$recommendation_count}");
            error_log("[SEO ANALYZER] Average action length: " . ($action_count > 0 ? round($total_action_length / $action_count) : 0) . " characters");

            // 检查keywords
            if (isset($analysis_data['keywords']) && is_array($analysis_data['keywords'])) {
                error_log('[SEO ANALYZER] Keywords found: ' . count($analysis_data['keywords']));
            }
        } else {
            error_log('[SEO ANALYZER] WARNING: No recommendations found in parsed data');
        }

        return $analysis_data;
    }

    /**
     * 生成默认分析结果（当AI响应解析失败时）
     */
    private function generate_default_analysis($ai_response) {
        // 尝试从响应中提取一些有用信息
        $keywords = array();
        $recommendations = array();

        // 简单的关键词提取
        if (preg_match_all('/关键词[：:]\s*([^。\n]+)/', $ai_response, $matches)) {
            foreach ($matches[1] as $match) {
                $keywords = array_merge($keywords, explode('、', trim($match)));
            }
        }

        // 生成通用建议
        $recommendations = array(
            array(
                'title' => '优化文章标题',
                'description' => '确保标题包含目标关键词且长度适中（建议30-60字符）',
                'action' => '检查标题是否准确反映文章内容',
                'priority' => 'medium'
            ),
            array(
                'title' => '增加内容深度',
                'description' => '文章内容应该更加详细和有价值',
                'action' => '添加更多细节、案例和实用信息',
                'priority' => 'high'
            )
        );

        return array(
            'keywords' => array_slice($keywords, 0, 5),
            'recommendations' => $recommendations,
            'content_analysis' => 'AI响应解析失败，使用基础分析'
        );
    }

    /**
     * 计算SEO得分
     */
    private function calculate_seo_scores($content_data, $ai_analysis) {
        $scores = array();

        // 标题得分
        $title_score = $this->calculate_title_score($content_data, $ai_analysis);
        $scores['title_score'] = $title_score;

        // 内容得分
        $content_score = $this->calculate_content_score($content_data, $ai_analysis);
        $scores['content_score'] = $content_score;

        // 关键词得分
        $keyword_score = $this->calculate_keyword_score($content_data, $ai_analysis);
        $scores['keyword_score'] = $keyword_score;

        // 可读性得分
        $readability_score = $this->calculate_readability_score($content_data, $ai_analysis);
        $scores['readability_score'] = $readability_score;

        // 整体得分 (加权平均)
        $weights = array(
            'title_score' => 0.25,
            'content_score' => 0.35,
            'keyword_score' => 0.25,
            'readability_score' => 0.15
        );

        $overall_score = 0;
        foreach ($weights as $score_type => $weight) {
            $overall_score += $scores[$score_type] * $weight;
        }
        $scores['overall_score'] = round($overall_score, 2);

        // 提取关键词信息
        if (isset($ai_analysis['keyword_analysis']['primary_keywords'])) {
            $scores['primary_keywords'] = $ai_analysis['keyword_analysis']['primary_keywords'];
        }
        if (isset($ai_analysis['keyword_analysis']['secondary_keywords'])) {
            $scores['secondary_keywords'] = $ai_analysis['keyword_analysis']['secondary_keywords'];
        }

        return $scores;
    }

    /**
     * 计算标题得分
     */
    private function calculate_title_score($content_data, $ai_analysis) {
        $score = 0;
        $title = $content_data['title'];
        $length = $content_data['title_length'];

        // 长度评分 (40%)
        if ($length >= 30 && $length <= 60) {
            $score += 40;
        } elseif ($length >= 20 && $length <= 70) {
            $score += 30;
        } else {
            $score += 10;
        }

        // AI分析评分 (60%)
        if (isset($ai_analysis['title_analysis']['score'])) {
            $score += $ai_analysis['title_analysis']['score'] * 0.6;
        } else {
            $score += 30;
        }

        return min(100, round($score, 2));
    }

    /**
     * 计算内容得分
     */
    private function calculate_content_score($content_data, $ai_analysis) {
        $score = 0;
        $word_count = $content_data['word_count'];

        // 字数评分 (30%)
        if ($word_count >= 1000) {
            $score += 30;
        } elseif ($word_count >= 500) {
            $score += 25;
        } elseif ($word_count >= 300) {
            $score += 20;
        } else {
            $score += 10;
        }

        // 结构评分 (20%)
        $headings = $content_data['heading_counts'];
        if (isset($headings['h2']) && $headings['h2'] > 0) {
            $score += 20;
        } elseif (isset($headings['h3']) && $headings['h3'] > 0) {
            $score += 15;
        } else {
            $score += 5;
        }

        // AI分析评分 (50%)
        if (isset($ai_analysis['content_analysis']['score'])) {
            $score += $ai_analysis['content_analysis']['score'] * 0.5;
        } else {
            $score += 25;
        }

        return min(100, round($score, 2));
    }

    /**
     * 计算关键词得分
     */
    private function calculate_keyword_score($content_data, $ai_analysis) {
        $score = 0;

        // AI分析评分 (80%)
        if (isset($ai_analysis['keyword_analysis']['score'])) {
            $score += $ai_analysis['keyword_analysis']['score'] * 0.8;
        } else {
            $score += 40;
        }

        // 关键词数量评分 (20%)
        $primary_count = isset($ai_analysis['keyword_analysis']['primary_keywords']) ?
                        count($ai_analysis['keyword_analysis']['primary_keywords']) : 0;
        $secondary_count = isset($ai_analysis['keyword_analysis']['secondary_keywords']) ?
                          count($ai_analysis['keyword_analysis']['secondary_keywords']) : 0;

        if ($primary_count >= 3 && $secondary_count >= 5) {
            $score += 20;
        } elseif ($primary_count >= 2 && $secondary_count >= 3) {
            $score += 15;
        } elseif ($primary_count >= 1) {
            $score += 10;
        } else {
            $score += 5;
        }

        return min(100, round($score, 2));
    }

    /**
     * 计算可读性得分
     */
    private function calculate_readability_score($content_data, $ai_analysis) {
        $score = 0;
        $content = $content_data['plain_text'];

        // 句子长度分析 (30%)
        $sentences = preg_split('/[.!?。！？]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $avg_sentence_length = 0;
        if (!empty($sentences)) {
            $total_chars = 0;
            foreach ($sentences as $sentence) {
                $total_chars += mb_strlen(trim($sentence));
            }
            $avg_sentence_length = $total_chars / count($sentences);
        }

        if ($avg_sentence_length <= 20) {
            $score += 30;
        } elseif ($avg_sentence_length <= 30) {
            $score += 25;
        } elseif ($avg_sentence_length <= 40) {
            $score += 15;
        } else {
            $score += 5;
        }

        // AI分析评分 (70%)
        if (isset($ai_analysis['content_analysis']['readability_score'])) {
            $score += $ai_analysis['content_analysis']['readability_score'] * 0.7;
        } else {
            $score += 35;
        }

        return min(100, round($score, 2));
    }

    /**
     * 生成优化建议
     */
    private function generate_recommendations($content_data, $scores, $ai_analysis) {
        $recommendations = array();
        $overall_score = $scores['overall_score'];

        // 高优先级建议 (得分 < 60)
        if ($overall_score < 60) {
            if ($scores['title_score'] < 70) {
                $recommendations[] = array(
                    'priority' => 'high',
                    'category' => 'title',
                    'title' => '优化标题',
                    'description' => '标题需要进一步优化以提高SEO效果',
                    'action' => '调整标题长度，添加关键词，增强吸引力'
                );
            }

            if ($scores['content_score'] < 70) {
                $recommendations[] = array(
                    'priority' => 'high',
                    'category' => 'content',
                    'title' => '丰富内容质量',
                    'description' => '内容质量需要提升',
                    'action' => '增加内容长度，改善结构，提供更多价值'
                );
            }
        }

        // 中优先级建议 (得分 60-80)
        if ($overall_score >= 60 && $overall_score < 80) {
            if ($scores['keyword_score'] < 70) {
                $recommendations[] = array(
                    'priority' => 'medium',
                    'category' => 'keywords',
                    'title' => '优化关键词',
                    'description' => '关键词使用可以进一步优化',
                    'action' => '调整关键词密度，添加长尾关键词'
                );
            }

            if ($scores['readability_score'] < 70) {
                $recommendations[] = array(
                    'priority' => 'medium',
                    'category' => 'readability',
                    'title' => '改善可读性',
                    'description' => '文章可读性有待提升',
                    'action' => '缩短句子长度，使用更多小标题，增加段落分隔'
                );
            }
        }

        // 低优先级建议 (得分 >= 80)
        if ($overall_score >= 80) {
            $recommendations[] = array(
                'priority' => 'low',
                'category' => 'optimization',
                'title' => '进一步优化',
                'description' => 'SEO表现良好，可以考虑进一步优化',
                'action' => '添加更多内部链接，优化图片alt属性，丰富元描述'
            );
        }

        // 整合AI分析建议
        if (isset($ai_analysis['overall_assessment']['priority_improvements'])) {
            foreach ($ai_analysis['overall_assessment']['priority_improvements'] as $improvement) {
                $recommendations[] = array(
                    'priority' => 'medium',
                    'category' => 'ai_suggestion',
                    'title' => 'AI建议',
                    'description' => $improvement,
                    'action' => $improvement
                );
            }
        }

        return $recommendations;
    }

    /**
     * 清理内容
     */
    private function clean_content($content) {
        // 移除短代码
        $content = strip_shortcodes($content);

        // 移除HTML标签但保留基本结构
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);

        return $content;
    }

    /**
     * 提取纯文本
     */
    private function extract_plain_text($content) {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * 分析内容结构
     */
    private function analyze_content_structure($content) {
        $headings = array(
            'h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0
        );

        preg_match_all('/<h([1-6])[^>]*>/i', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $level) {
                $key = 'h' . $level;
                if (isset($headings[$key])) {
                    $headings[$key]++;
                }
            }
        }

        return array('headings' => $headings);
    }

    /**
     * 分析链接
     */
    private function analyze_links($content) {
        $internal_count = 0;
        $external_count = 0;

        preg_match_all('/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches);
        if (isset($matches[1])) {
            $home_url = home_url();
            foreach ($matches[1] as $url) {
                if (strpos($url, $home_url) !== false || preg_match('/^\/[^\/]/', $url)) {
                    $internal_count++;
                } elseif (preg_match('/^https?:\/\//', $url)) {
                    $external_count++;
                }
            }
        }

        return array(
            'internal_count' => $internal_count,
            'external_count' => $external_count
        );
    }

    /**
     * 分析图片
     */
    private function analyze_images($content) {
        $images = array();
        preg_match_all('/<img[^>]+>/i', $content, $img_tags);

        if (isset($img_tags[0])) {
            foreach ($img_tags[0] as $img_tag) {
                $has_alt = preg_match('/alt=[\'"]([^\'"]*)[\'"]/i', $img_tag);
                $images[] = array(
                    'tag' => $img_tag,
                    'has_alt' => (bool)$has_alt
                );
            }
        }

        return array('images' => $images);
    }

    /**
     * 计算字数
     */
    private function count_words($text) {
        // 移除多余的空格
        $text = preg_replace('/\s+/', ' ', trim($text));

        // 中文字符计数
        $chinese_chars = mb_strlen(preg_replace('/[^\x{4e00}-\x{9fa5}]/u', '', $text));

        // 英文单词计数
        $english_words = str_word_count($text);

        return $chinese_chars + $english_words;
    }

    /**
     * 获取SEO分析报告
     */
    public function get_seo_report($post_id) {
        return $this->database->get_seo_analysis($post_id);
    }

    /**
     * 获取所有SEO分析
     */
    public function get_all_seo_reports($limit = 50, $offset = 0) {
        return $this->database->get_all_seo_analyses($limit, $offset);
    }

    /**
     * 获取SEO统计信息
     */
    public function get_seo_statistics() {
        return $this->database->get_seo_statistics();
    }
}