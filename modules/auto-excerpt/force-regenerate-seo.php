<?php
/**
 * 强制重新生成SEO分析
 * 用于为现有文章重新生成完整的SEO分析数据
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

// 强制重新生成特定文章的SEO分析
function force_regenerate_seo_analysis($post_id) {
    require_once __DIR__ . '/includes/class-seo-analyzer.php';
    require_once __DIR__ . '/includes/class-seo-analyzer-database.php';

    // 加载AI设置
    require_once dirname(__FILE__) . '/../ai-settings/ai-settings-helper.php';

    if (!wordpress_ai_toolkit_is_ai_available()) {
        return array('success' => false, 'message' => 'AI服务未配置');
    }

    $seo_analyzer = new Auto_Excerpt_SEO_Analyzer();
    $database = new Auto_Excerpt_SEO_Analyzer_Database();

    echo "正在分析文章 {$post_id}...\n";

    // 执行分析
    $result = $seo_analyzer->analyze_post($post_id);

    if (is_wp_error($result)) {
        return array(
            'success' => false,
            'message' => '分析失败: ' . $result->get_error_message()
        );
    }

    // 检查保存的数据
    $saved_data = $database->get_seo_analysis($post_id);

    if ($saved_data) {
        $success = array(
            'success' => true,
            'message' => 'SEO分析重新生成完成',
            'post_id' => $post_id,
            'overall_score' => $result['overall_score'],
            'has_raw_analysis' => !empty($saved_data->raw_ai_analysis),
            'raw_analysis_length' => strlen($saved_data->raw_ai_analysis ?? ''),
            'has_parsed_analysis' => !empty($saved_data->parsed_analysis)
        );

        echo "✅ 分析完成！\n";
        echo "整体得分: {$result['overall_score']}\n";
        echo "原始AI分析长度: " . strlen($saved_data->raw_ai_analysis ?? '') . "\n";
        echo "解析分析存在: " . (!empty($saved_data->parsed_analysis) ? '是' : '否') . "\n";

        return $success;
    }

    return array('success' => false, 'message' => '数据保存失败');
}

// 如果直接访问此文件并提供了post_id参数
if (isset($_GET['post_id']) && is_numeric($_GET['post_id'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $post_id = intval($_GET['post_id']);
    $result = force_regenerate_seo_analysis($post_id);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "用法: ?post_id=文章ID\n";
}
?>