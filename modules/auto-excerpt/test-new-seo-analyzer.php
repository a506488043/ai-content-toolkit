<?php
/**
 * 测试新的SEO分析器
 */

if (!defined('ABSPATH')) {
    // 如果直接访问，尝试加载WordPress
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        exit('WordPress not found');
    }
}

// 测试新的SEO分析器
function test_new_seo_analyzer() {
    echo "<h2>测试新的SEO分析器</h2>";

    try {
        // 加载AI设置
        require_once dirname(__FILE__) . '/../ai-settings/ai-settings-helper.php';

        if (!wordpress_toolkit_is_ai_available()) {
            echo "<p>❌ AI服务未配置</p>";
            echo "<p>请检查AI设置：API密钥、模型等</p>";
            return;
        }

        echo "<p>✅ AI服务已配置</p>";

        // 加载SEO分析器
        require_once dirname(__FILE__) . '/includes/class-seo-analyzer.php';

        // 获取AI配置
        $config = wordpress_toolkit_get_ai_config();

        $seo_settings = array(
            'ai_provider' => 'deepseek',
            'ai_model' => $config['model'],
            'api_key' => $config['api_key'],
            'api_base' => $config['api_base'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature']
        );

        // 创建SEO分析器实例
        $seo_analyzer = new Auto_Excerpt_SEO_Analyzer($seo_settings);

        echo "<p>✅ SEO分析器创建成功</p>";

        // 测试获取最新文章
        $latest_post = get_posts(array(
            'numberposts' => 1,
            'post_status' => 'publish',
            'post_type' => 'post'
        ));

        if (empty($latest_post)) {
            echo "<p>❌ 没有找到测试文章</p>";
            return;
        }

        $test_post = $latest_post[0];
        echo "<p>✅ 找到测试文章：{$test_post->post_title} (ID: {$test_post->ID})</p>";

        // 执行分析
        echo "<p>🚀 开始SEO分析...</p>";

        $start_time = microtime(true);
        $result = $seo_analyzer->analyze_post($test_post->ID);
        $analysis_time = microtime(true) - $start_time;

        if (is_array($result) && isset($result['overall_score'])) {
            echo "<p>✅ 分析完成！</p>";
            echo "<p>⏱️ 分析耗时：" . round($analysis_time, 2) . " 秒</p>";
            echo "<p>📊 整体得分：{$result['overall_score']}</p>";
            echo "<p>🤖 AI分析数据长度：" . strlen($result['raw_ai_analysis'] ?? '') . " 字符</p>";
            echo "<p>🎯 关键词数量：" . count($result['ai_keywords'] ?? array()) . "</p>";
            echo "<p>💡 优化建议数量：" . count($result['ai_recommendations'] ?? array()) . "</p>";

            echo "<h3>分析结果详情：</h3>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo "<p>❌ 分析失败</p>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        }

    } catch (Exception $e) {
        echo "<p>❌ 错误：" . $e->getMessage() . "</p>";
        echo "<p>文件：" . $e->getFile() . " 行：" . $e->getLine() . "</p>";
    }
}

// 如果直接访问此文件，执行测试
if (basename($_SERVER['PHP_SELF']) === 'test-new-seo-analyzer.php') {
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>";

    test_new_seo_analyzer();
}
?>