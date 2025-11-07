<?php
/**
 * SEO分析功能测试脚本
 * 用于验证SEO分析功能是否正常工作
 */

// 模拟WordPress环境
if (!defined('ABSPATH')) {
    $base_dir = dirname(__FILE__);
    require_once($base_dir . '/../../../wp-config.php');
}

// 加载必要的文件
require_once 'modules/auto-excerpt/includes/class-seo-analyzer-database.php';
require_once 'modules/auto-excerpt/includes/class-seo-analyzer.php';

echo "=== SEO分析功能测试 ===\n\n";

// 1. 测试数据库连接和表创建
echo "1. 测试数据库连接和表创建...\n";
try {
    $db = new Auto_Excerpt_SEO_Analyzer_Database();
    $result = $db->create_tables();
    echo $result ? "✓ 数据库表创建成功\n" : "✗ 数据库表创建失败\n";
} catch (Exception $e) {
    echo "✗ 数据库测试失败: " . $e->getMessage() . "\n";
}

// 2. 测试获取文章列表
echo "\n2. 测试获取文章列表...\n";
try {
    $db = new Auto_Excerpt_SEO_Analyzer_Database();
    $posts = $db->get_posts_for_analysis(5);
    if ($posts) {
        echo "✓ 获取到 " . count($posts) . " 篇文章\n";
        foreach ($posts as $post) {
            echo "  - ID: {$post->ID}, 标题: {$post->post_title}\n";
        }
    } else {
        echo "✗ 未获取到文章\n";
    }
} catch (Exception $e) {
    echo "✗ 获取文章列表失败: " . $e->getMessage() . "\n";
}

// 3. 测试SEO统计信息
echo "\n3. 测试SEO统计信息...\n";
try {
    $db = new Auto_Excerpt_SEO_Analyzer_Database();
    $stats = $db->get_seo_statistics();
    if ($stats) {
        echo "✓ 获取统计信息成功\n";
        echo "  - 总分析数: " . ($stats['total_analyses'] ?? 0) . "\n";
        echo "  - 平均得分: " . round(($stats['average_score'] ?? 0), 2) . "\n";
        echo "  - 最近7天分析数: " . ($stats['recent_analyses'] ?? 0) . "\n";
    } else {
        echo "✗ 获取统计信息失败\n";
    }
} catch (Exception $e) {
    echo "✗ 统计信息测试失败: " . $e->getMessage() . "\n";
}

// 4. 测试SEO分析器初始化
echo "\n4. 测试SEO分析器初始化...\n";
try {
    // 获取设置
    $settings = get_option('wordpress_toolkit_auto_excerpt_settings', array());
    $seo_settings = array(
        'ai_provider' => $settings['ai_provider'] ?? 'deepseek',
        'ai_model' => $settings['deepseek_model'] ?? 'deepseek-chat',
        'api_key' => $settings['deepseek_api_key'] ?? '',
        'api_base' => $settings['deepseek_api_base'] ?? 'https://api.deepseek.com',
        'max_tokens' => $settings['ai_max_tokens'] ?? 2000,
        'temperature' => $settings['ai_temperature'] ?? 0.3
    );

    $analyzer = new Auto_Excerpt_SEO_Analyzer($seo_settings);
    echo "✓ SEO分析器初始化成功\n";
    echo "  - AI提供商: " . $seo_settings['ai_provider'] . "\n";
    echo "  - AI模型: " . $seo_settings['ai_model'] . "\n";
    echo "  - API密钥状态: " . (empty($seo_settings['api_key']) ? '未配置' : '已配置') . "\n";
} catch (Exception $e) {
    echo "✗ SEO分析器初始化失败: " . $e->getMessage() . "\n";
}

// 5. 测试单篇文章SEO分析（如果有API密钥）
echo "\n5. 测试单篇文章SEO分析...\n";
try {
    if (!empty($seo_settings['api_key'])) {
        $db = new Auto_Excerpt_SEO_Analyzer_Database();
        $posts = $db->get_posts_for_analysis(1);

        if ($posts) {
            $post_id = $posts[0]->ID;
            echo "正在分析文章 ID: {$post_id}...\n";

            $analyzer = new Auto_Excerpt_SEO_Analyzer($seo_settings);
            $result = $analyzer->analyze_post_seo($post_id);

            if ($result) {
                echo "✓ SEO分析成功\n";
                echo "  - 整体得分: " . $result['overall_score'] . "\n";
                echo "  - 标题得分: " . $result['title_score'] . "\n";
                echo "  - 内容得分: " . $result['content_score'] . "\n";
                echo "  - 关键词得分: " . $result['keyword_score'] . "\n";
                echo "  - 可读性得分: " . $result['readability_score'] . "\n";
                echo "  - 字数统计: " . $result['word_count'] . "\n";
                echo "  - 分析耗时: " . $result['analysis_time'] . " 秒\n";
            } else {
                echo "✗ SEO分析失败\n";
            }
        } else {
            echo "✗ 没有找到可用于测试的文章\n";
        }
    } else {
        echo "⚠ 跳过测试：未配置AI API密钥\n";
        echo "  请在WordPress后台的 文章优化 > 基本设置 中配置DeepSeek API密钥\n";
    }
} catch (Exception $e) {
    echo "✗ SEO分析测试失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
echo "\n使用说明:\n";
echo "1. 确保已安装并激活WordPress Toolkit插件\n";
echo "2. 在WordPress后台进入 工具 > WordPress Toolkit > 文章优化 > SEO分析\n";
echo "3. 配置AI API密钥（DeepSeek）\n";
echo "4. 使用批量分析或单篇文章分析功能\n";
echo "5. 查看分析报告和优化建议\n";