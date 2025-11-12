<?php
/**
 * 全新SEO AI分析器
 * 提供完整的AI驱动的SEO分析报告
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
            'max_tokens' => 4000, // 增加到4000 tokens确保完整JSON响应
            'temperature' => 0.3,
            'analysis_timeout' => 30
        ));
    }

    /**
     * 分析单篇文章的SEO
     */
    public function analyze_post($post_id) {
        try {
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found: {$post_id}");
            }

            // 准备分析数据
            $content_data = $this->prepare_content_data($post);

            // 执行AI分析
            $ai_analysis = $this->perform_ai_analysis($content_data);

            // 构建完整分析结果
            $analysis_result = $this->build_complete_analysis($content_data, $ai_analysis);

            // 保存到数据库
            $this->database->save_seo_analysis($post_id, $analysis_result);

            error_log('[SEO ANALYZER] Analysis completed for post: ' . $post_id . ' | Score: ' . $analysis_result['overall_score']);

            return $analysis_result;

        } catch (Exception $e) {
            error_log('[SEO ANALYZER] Analysis failed for post: ' . $post_id . ' - ' . $e->getMessage());
            return $this->create_fallback_analysis($post_id);
        }
    }

    /**
     * 准备文章分析数据
     */
    private function prepare_content_data($post) {
        $content = $post->post_content;
        $plain_text = strip_tags($content);
        $plain_text = preg_replace('/\s+/', ' ', $plain_text);

        // 提取标题
        $title = get_the_title($post);

        // 提取描述
        $excerpt = $post->post_excerpt ?: $this->generate_excerpt($content);

        // 统计信息
        $word_count = str_word_count($plain_text);
        $title_length = mb_strlen($title, 'UTF-8');

        // 图片统计
        $image_count = substr_count($content, '<img');

        // 链接统计
        $internal_links = substr_count($content, 'href="' . home_url());
        $external_links = substr_count($content, 'href="http') - $internal_links;

        // 标题标签统计
        $heading_counts = array(
            'h1' => substr_count($content, '<h1'),
            'h2' => substr_count($content, '<h2'),
            'h3' => substr_count($content, '<h3'),
            'h4' => substr_count($content, '<h4'),
            'h5' => substr_count($content, '<h5'),
            'h6' => substr_count($content, '<h6')
        );

        return array(
            'post_id' => $post->ID,
            'title' => $title,
            'content' => $content,
            'plain_text' => $plain_text,
            'excerpt' => $excerpt,
            'word_count' => $word_count,
            'title_length' => $title_length,
            'image_count' => $image_count,
            'internal_links' => $internal_links,
            'external_links' => $external_links,
            'heading_counts' => $heading_counts
        );
    }

    /**
     * 执行AI分析
     */
    private function perform_ai_analysis($content_data) {
        error_log("WordPress Toolkit: 开始执行AI分析");
        $prompt = $this->build_ai_prompt($content_data);
        error_log("WordPress Toolkit: AI提示词构建完成，长度 = " . strlen($prompt));

        $response = $this->call_ai_api($prompt);
        error_log("WordPress Toolkit: AI API调用完成，响应长度 = " . strlen($response));

        return $this->parse_ai_response($response);
    }

    /**
     * 构建AI分析提示词
     */
    private function build_ai_prompt($content_data) {
        $title = $content_data['title'];
        $excerpt = $content_data['excerpt'];
        $word_count = $content_data['word_count'];
        $title_length = $content_data['title_length'];

        // 限制内容长度以避免token超限
        $max_content_length = 2000;
        $content = mb_substr($content_data['plain_text'], 0, $max_content_length, 'UTF-8');

        if (mb_strlen($content_data['plain_text'], 'UTF-8') > $max_content_length) {
            $content .= '...(content truncated)';
        }

        return <<<PROMPT
作为专业SEO分析师，请分析以下文章。只返回JSON格式，不要其他解释文字。

文章信息：
标题：{$title}
摘要：{$excerpt}
字数：{$word_count}字
标题长度：{$title_length}字符

内容：
{$content}

请直接返回标准JSON格式：
```json
{
    "keywords": ["关键词1", "关键词2", "关键词3", "关键词4", "关键词5"],
    "score": {
        "overall": 85,
        "title": 80,
        "content": 85,
        "readability": 90,
        "technical": 80
    },
    "analysis": {
        "title_analysis": "标题分析",
        "content_analysis": "内容分析",
        "keyword_analysis": "关键词分析",
        "readability_analysis": "可读性分析"
    },
    "recommendations": [
        {
            "title": "建议标题",
            "description": "问题描述",
            "action": "具体操作",
            "impact": "预期效果"
        }
    ],
    "meta_info": {
        "suggested_title": "优化后的标题",
        "meta_description": "meta描述",
        "focus_keywords": ["核心词1", "核心词2"]
    }
}
```

重要：确保JSON语法正确，只返回代码块
PROMPT;
    }

    /**
     * 调用AI API
     */
    private function call_ai_api($prompt) {
        error_log("WordPress Toolkit: 开始调用AI API");
        $api_url = rtrim($this->settings['api_base'], '/') . '/v1/chat/completions';
        error_log("WordPress Toolkit: API URL = " . $api_url);
        error_log("WordPress Toolkit: API Key = " . substr($this->settings['api_key'], 0, 10) . "...");

        $request_data = array(
            'model' => $this->settings['ai_model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional SEO analyst. Always return valid JSON format that can be directly parsed.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 4000, // 增加到4000 tokens确保完整JSON响应 // 增加到2000 tokens确保完整JSON响应
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
            error_log("WordPress Toolkit: API请求失败 - " . $response->get_error_message());
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log("WordPress Toolkit: API响应状态码 = " . $response_code);
        error_log("WordPress Toolkit: API响应体长度 = " . strlen($response_body));
        error_log("WordPress Toolkit: API响应内容 = " . substr($response_body, 0, 500) . "...");
        error_log("WordPress Toolkit: API完整响应数据 = " . $response_body);

        if ($response_code !== 200) {
            error_log("WordPress Toolkit: API响应错误 - 状态码 = " . $response_code);
            throw new Exception("API request failed with status code: {$response_code}");
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("WordPress Toolkit: API响应JSON解析失败 - " . json_last_error_msg());
            throw new Exception('API response parsing failed');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log("WordPress Toolkit: API响应格式无效 - 缺少choices[0][message][content]");
            error_log("WordPress Toolkit: 实际响应数据 = " . print_r($data, true));
            throw new Exception('Invalid API response format');
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * 解析AI响应 - 简化版本
     */
    private function parse_ai_response($response) {
        $response = trim($response);

        // 添加调试日志
        error_log("WordPress Toolkit: AI响应原始数据长度 = " . strlen($response));
        error_log("WordPress Toolkit: AI完整响应数据 = " . $response);

        // 简单直接提取JSON
        $json_content = '';

        // 优先提取```json代码块
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $json_content = trim($matches[1]);
            error_log("WordPress Toolkit: 通过```json代码块提取JSON成功");
        }
        // 备选：直接提取JSON对象
        elseif (preg_match('/\{.*\}/s', $response, $matches)) {
            $json_content = trim($matches[0]);
            error_log("WordPress Toolkit: 直接提取JSON对象");
        }

        if (empty($json_content)) {
            error_log("WordPress Toolkit: 无法提取JSON内容");
            return $this->create_basic_analysis($response);
        }

        error_log("WordPress Toolkit: 提取的JSON内容 = " . substr($json_content, 0, 300) . "...");
        error_log("WordPress Toolkit: 完整提取的JSON = " . $json_content);

        // 尝试解析JSON
        $analysis_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("WordPress Toolkit: JSON解析失败 - " . json_last_error_msg());
            error_log("WordPress Toolkit: JSON错误码 = " . json_last_error());
            error_log("WordPress Toolkit: 失败的JSON内容 = " . $json_content);
            error_log("WordPress Toolkit: 失败的JSON长度 = " . strlen($json_content));
            // JSON解析失败，返回基础结构
            return $this->create_basic_analysis($response);
        }

        error_log("WordPress Toolkit: JSON解析成功");
        return $analysis_data;
    }

    /**
     * 创建基础分析结构（当JSON解析失败时）
     */
    private function create_basic_analysis($raw_response) {
        return array(
            'keywords' => array(),
            'score' => array(
                'overall' => 70,
                'title' => 70,
                'content' => 70,
                'readability' => 70,
                'technical' => 70
            ),
            'analysis' => array(
                'title_analysis' => 'AI analysis parsing failed',
                'content_analysis' => 'AI analysis parsing failed',
                'keyword_analysis' => 'AI analysis parsing failed',
                'readability_analysis' => 'AI analysis parsing failed'
            ),
            'recommendations' => array(
                array(
                    'title' => '重新分析',
                    'description' => 'AI分析解析失败，建议重新生成分析',
                    'action' => '点击重新生成按钮获取完整分析',
                    'priority' => 'high',
                    'impact' => '获取完整的AI分析报告'
                )
            ),
            'meta_info' => array(
                'suggested_title' => '',
                'meta_description' => '',
                'focus_keywords' => array()
            )
        );
    }

    /**
     * 构建完整分析结果
     */
    private function build_complete_analysis($content_data, $ai_analysis) {
        // 计算基础SEO得分
        $basic_scores = $this->calculate_basic_scores($content_data);

        // 合并AI分析得分
        $ai_scores = $ai_analysis['score'] ?? array();

        // 最终得分（AI分析权重70%，基础SEO权重30%）
        $final_scores = array(
            'overall_score' => round(($ai_scores['overall'] ?? 70) * 0.7 + $basic_scores['overall_score'] * 0.3, 1),
            'title_score' => round(($ai_scores['title'] ?? 70) * 0.7 + $basic_scores['title_score'] * 0.3, 1),
            'content_score' => round(($ai_scores['content'] ?? 70) * 0.7 + $basic_scores['content_score'] * 0.3, 1),
            'keyword_score' => round(($ai_scores['technical'] ?? 70) * 0.7 + $basic_scores['keyword_score'] * 0.3, 1),
            'readability_score' => round(($ai_scores['readability'] ?? 70) * 0.7 + $basic_scores['readability_score'] * 0.3, 1)
        );

        // 检查AI分析数据结构 - 直接使用解析后的AI数据
        $raw_response = is_string($ai_analysis) ? $ai_analysis : json_encode($ai_analysis);

        // 构建完整结果
        return array(
            // 基础信息
            'post_id' => $content_data['post_id'],
            'post_title' => $content_data['title'],
            'word_count' => $content_data['word_count'],
            'title_length' => $content_data['title_length'],

            // SEO得分
            'overall_score' => $final_scores['overall_score'],
            'title_score' => $final_scores['title_score'],
            'content_score' => $final_scores['content_score'],
            'keyword_score' => $final_scores['keyword_score'],
            'readability_score' => $final_scores['readability_score'],

            // 技术统计
            'image_count' => $content_data['image_count'],
            'internal_links' => $content_data['internal_links'],
            'external_links' => $content_data['external_links'],
            'heading_counts' => $content_data['heading_counts'],

            // AI完整分析数据
            'raw_ai_analysis' => $raw_response,
            'ai_keywords' => $ai_analysis['keywords'] ?? array(),
            'ai_analysis' => $ai_analysis['analysis'] ?? array(),
            'ai_recommendations' => $ai_analysis['recommendations'] ?? array(),
            'ai_meta_info' => $ai_analysis['meta_info'] ?? array(),

            // 元数据
            'ai_provider' => $this->settings['ai_provider'],
            'ai_model' => $this->settings['ai_model'],
            'analysis_time' => microtime(true)
        );
    }

    /**
     * 计算基础SEO得分
     */
    private function calculate_basic_scores($content_data) {
        $scores = array();

        // 标题得分
        $title_length = $content_data['title_length'];
        if ($title_length >= 30 && $title_length <= 60) {
            $scores['title_score'] = 85;
        } elseif ($title_length >= 20 && $title_length <= 70) {
            $scores['title_score'] = 75;
        } else {
            $scores['title_score'] = 60;
        }

        // 内容得分（基于字数）
        $word_count = $content_data['word_count'];
        if ($word_count >= 1000) {
            $scores['content_score'] = 85;
        } elseif ($word_count >= 500) {
            $scores['content_score'] = 75;
        } else {
            $scores['content_score'] = 65;
        }

        // 关键词得分（基于内容密度）
        $scores['keyword_score'] = 70; // 基础分，由AI分析增强

        // 可读性得分（基于段落和结构）
        $scores['readability_score'] = 75; // 基础分，由AI分析增强

        // 整体得分
        $scores['overall_score'] = round((
            $scores['title_score'] * 0.25 +
            $scores['content_score'] * 0.35 +
            $scores['keyword_score'] * 0.25 +
            $scores['readability_score'] * 0.15
        ), 1);

        return $scores;
    }

    /**
     * 生成降级分析
     */
    private function create_fallback_analysis($post_id) {
        return array(
            'post_id' => $post_id,
            'overall_score' => 60,
            'title_score' => 60,
            'content_score' => 60,
            'keyword_score' => 60,
            'readability_score' => 60,
            'raw_ai_analysis' => '{"error": "AI analysis failed"}',
            'ai_keywords' => array(),
            'ai_recommendations' => array(
                array(
                    'title' => '检查AI配置',
                    'description' => 'AI分析失败，请检查AI服务配置',
                    'action' => '检查API密钥和网络连接',
                    'priority' => 'high',
                    'impact' => '恢复完整的AI分析功能'
                )
            ),
            'ai_analysis' => array(),
            'ai_meta_info' => array()
        );
    }

    /**
     * 生成文章摘要
     */
    private function generate_excerpt($content, $length = 160) {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);

        if (mb_strlen($text, 'UTF-8') > $length) {
            $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
        }

        return $text;
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

    /**
     * 检查JSON字符串是否完整
     */
    private function is_json_complete($json_string) {
        // 检查花括号是否匹配
        $open_count = substr_count($json_string, '{');
        $close_count = substr_count($json_string, '}');

        // 检查基本JSON结构
        if ($open_count !== $close_count) {
            return false;
        }

        // 尝试解析JSON
        json_decode($json_string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 修复不完整的JSON字符串
     */
    private function fix_incomplete_json($json_string) {
        error_log("WordPress Toolkit: 开始修复不完整的JSON");

        // 计算花括号差异
        $open_count = substr_count($json_string, '{');
        $close_count = substr_count($json_string, '}');
        $brace_diff = $open_count - $close_count;

        // 添加缺失的闭合花括号
        if ($brace_diff > 0) {
            $json_string .= str_repeat('}', $brace_diff);
            error_log("WordPress Toolkit: 添加了 {$brace_diff} 个闭合花括号");
        }

        // 处理未闭合的字符串和常见JSON错误
        // 移除控制字符和换行符，但保留中文
        $json_string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json_string);

        // 修复常见的JSON字符串问题
        // 1. 转义未转义的换行符
        $json_string = preg_replace('/(?<!\\\\)\\n/', '\\n', $json_string);
        $json_string = preg_replace('/(?<!\\\\)\\r/', '\\r', $json_string);
        $json_string = preg_replace('/(?<!\\\\)\\t/', '\\t', $json_string);

        // 2. 修复未闭合的字符串 - 在字符串末尾添加引号
        $json_string = preg_replace('/"([^"]*?)$/', '"$1"', $json_string);

        // 3. 移除多余的逗号（花括号前或方括号前的逗号）
        $json_string = preg_replace('/,\s*([}\]])/', '$1', $json_string);

        // 4. 确保字符串值被正确引号包围
        $json_string = preg_replace('/:\s*([^",\[\]{\s][^",\[\]{]*?)\s*([,}\]])/', ': "$1"$2', $json_string);

        error_log("WordPress Toolkit: JSON修复完成，尝试解析");

        // 最终验证
        if ($this->is_json_complete($json_string)) {
            error_log("WordPress Toolkit: JSON修复成功");
            return $json_string;
        } else {
            // 更详细的失败诊断
            json_decode($json_string);
            $json_error = json_last_error_msg();
            error_log("WordPress Toolkit: JSON修复失败 - 最终错误: " . $json_error);
            error_log("WordPress Toolkit: 修复后的JSON内容: " . substr($json_string, -200));
            return null;
        }
    }
}