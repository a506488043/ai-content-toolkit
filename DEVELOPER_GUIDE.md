# WordPress Toolkit 开发者指南

## 📋 概述

本指南为WordPress Toolkit插件的开发者提供详细的开发规范、最佳实践和扩展指导。遵循这些指导原则可以确保代码质量、安全性和可维护性。

## 🎯 开发环境准备

### 环境要求
- **WordPress**: 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.6+
- **开发工具**: PhpStorm, VSCode 或其他支持PHP的IDE

### 开发配置
```php
// wp-config.php 开发环境配置
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### 必需工具
- **PHP CodeSniffer**: 代码规范检查
- **PHPStan**: 静态分析工具
- **PHPUnit**: 单元测试框架
- **Composer**: 依赖管理

## 📝 编码规范

### PHP编码规范

#### 1. 命名约定
```php
// 类名：大驼峰命名法
class WordPress_Toolkit_Module_Base {}

// 方法名：小驼峰命名法
public function renderAdminPage() {}
private function _helperMethod() {}

// 常量：大写字母+下划线
const MODULE_VERSION = '1.0.0';
define('WORDPRESS_TOOLKIT_VERSION', '1.0.0');

// 变量：小写字母+下划线
$module_name = 'custom_card';
$cache_key = 'wordpress_toolkit_cache';
```

#### 2. 文档注释
```php
/**
 * 处理AJAX请求保存物品
 *
 * @since 1.1.0
 * @access public
 *
 * @param array $post_data POST数据
 * @return array|WP_Error 保存结果
 */
public function ajax_save_item($post_data) {
    // 实现代码
}
```

#### 3. 代码格式
```php
// 缩进使用4个空格
if ($condition) {
    $result = $this->process_data($data);

    foreach ($result as $item) {
        if ($item->is_valid()) {
            $item->save();
        }
    }
}
```

### JavaScript编码规范

#### 1. 命名约定
```javascript
// 变量和函数：小驼峰命名法
let userName = 'admin';
const handleClick = () => {};

// 常量：大写字母+下划线
const API_URL = 'https://example.com/api';

// 类名：大驼峰命名法
class ToolkitCore {}
```

#### 2. 模块化
```javascript
// 使用模块模式
const WordPressToolkit = (function($) {
    'use strict';

    class Core {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            $(document).on('click', '.tk-button', this.handleClick.bind(this));
        }
    }

    return Core;
})(jQuery);
```

### CSS编码规范

#### 1. 命名约定
```css
/* BEM命名法 */
.tk-card { /* Block */ }
.tk-card__title { /* Element */ }
.tk-card--featured { /* Modifier */ }

/* 工具类 */
.tk-text-center { text-align: center; }
.tk-mt-4 { margin-top: 1rem; }
```

#### 2. 组织结构
```css
/* 变量定义 */
:root {
    --tk-primary-color: #0073aa;
    --tk-spacing-unit: 8px;
}

/* 基础样式 */
.tk-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* 组件样式 */
.tk-card {
    border: 1px solid #ddd;
    border-radius: 4px;
}
```

## 🛡️ 安全开发指南

### 1. 输入验证

#### 永远不要信任用户输入
```php
// ✅ 正确做法
$email = sanitize_email($_POST['email']);
$url = esc_url_raw($_POST['url']);
$text = sanitize_text_field($_POST['text']);

// ✅ 使用安全工具类
$result = WordPress_Toolkit_Security::validate_and_sanitize_input($_POST, $rules);
```

#### 验证规则示例
```php
$validation_rules = array(
    'name' => array(
        'type' => 'text',
        'required' => true,
        'label' => '姓名',
        'max_length' => 100
    ),
    'email' => array(
        'type' => 'email',
        'required' => true,
        'label' => '邮箱'
    ),
    'age' => array(
        'type' => 'int',
        'min' => 0,
        'max' => 150,
        'label' => '年龄'
    )
);
```

### 2. 数据库安全

#### 使用预处理语句
```php
// ✅ 正确做法
global $wpdb;
$table_name = $wpdb->prefix . 'my_table';

$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM %i WHERE user_id = %d AND status = %s",
    $table_name,
    $user_id,
    $status
));

// ✅ 使用数据库优化器
$items = WordPress_Toolkit_Database_Optimizer::get_optimized_results($args);
```

#### 表名安全
```php
// ✅ 正确做法
$table_name = $wpdb->prepare("%i", $table_name);
$sql = "SELECT * FROM {$table_name} WHERE id = %d";
```

### 3. 权限检查

#### 统一权限验证
```php
// ✅ 正确做法
WordPress_Toolkit_Security::verify_ajax_nonce($_POST['nonce'], 'my_action');
WordPress_Toolkit_Security::verify_user_capability('manage_options');

// ✅ 自定义权限检查
public function can_edit_item($item_id) {
    return current_user_can('manage_options') ||
           $this->is_item_owner($item_id, get_current_user_id());
}
```

### 4. 输出安全

#### 防止XSS攻击
```php
// ✅ 正确做法
echo esc_html($text);
echo esc_url($url);
echo wp_kses_post($html_content);

// JavaScript安全
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text; // 安全的文本设置
    return div.innerHTML;
}
```

## 🏗️ 模块开发指南

### 1. 创建新模块

#### 目录结构
```
modules/my-module/
├── my-module-module.php          # 模块主类
├── includes/
│   ├── class-item.php           # 数据模型
│   └── class-database.php       # 数据库操作
├── admin/
│   └── admin.php                # 管理页面
├── assets/
│   ├── css/
│   │   └── style.css            # 样式文件
│   └── js/
│       └── script.js            # JavaScript文件
├── templates/
│   └── item-template.php        # 模板文件
└── languages/
    └── my-module.pot            # 语言文件
```

#### 模块主类模板
```php
<?php
/**
 * My Module 主类
 */

if (!defined('ABSPATH')) {
    exit;
}

class My_Module extends WordPress_Toolkit_Module_Base {

    protected function init_module_properties() {
        $this->module_name = 'my-module';
        $this->module_version = '1.0.0';
        $this->option_name = 'wordpress_toolkit_my_module_options';
        $this->required_capability = 'manage_options';
    }

    public function get_module_info() {
        return array(
            'name' => __('我的模块', 'wordpress-toolkit'),
            'description' => __('模块描述', 'wordpress-toolkit'),
            'version' => $this->module_version,
            'menu_name' => __('我的模块', 'wordpress-toolkit')
        );
    }

    protected function render_page_content() {
        // 渲染管理页面内容
        $this->render_settings_form();
    }

    public function get_default_settings() {
        return array(
            'enabled' => true,
            'option1' => 'default_value',
            'option2' => 100
        );
    }

    public function register_shortcodes() {
        add_shortcode('my_shortcode', array($this, 'handle_shortcode'));
    }

    public function handle_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'type' => 'default'
        ), $atts);

        // 处理短代码逻辑
        return $this->render_shortcode_output($atts);
    }

    public function register_ajax_handlers() {
        $ajax_handler = new My_Module_AJAX($this->module_name);
    }

    protected function get_validation_rules() {
        return array(
            'name' => array(
                'type' => 'text',
                'required' => true,
                'label' => '名称'
            ),
            'description' => array(
                'type' => 'textarea',
                'label' => '描述'
            )
        );
    }

    protected function render_settings_form() {
        $settings = $this->get_settings();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($this->option_name); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enabled"><?php _e('启用模块', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="<?php echo $this->option_name; ?>[enabled]"
                               value="1" <?php checked($settings['enabled']); ?> />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="option1"><?php _e('选项1', 'wordpress-toolkit'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="<?php echo $this->option_name; ?>[option1]"
                               value="<?php echo esc_attr($settings['option1']); ?>" />
                    </td>
                </tr>
            </table>

            <?php submit_button(__('保存设置', 'wordpress-toolkit')); ?>
        </form>
        <?php
    }

    private function render_shortcode_output($atts) {
        // 获取数据并渲染输出
        $data = $this->get_shortcode_data($atts);
        return $this->render_template('shortcode-template', $data);
    }

    private function get_shortcode_data($atts) {
        // 从数据库获取数据
        return array(); // 返回数据
    }

    private function render_template($template_name, $data) {
        // 加载模板文件
        ob_start();
        extract($data);
        include WORDPRESS_TOOLKIT_PLUGIN_PATH . "modules/{$this->module_name}/templates/{$template_name}.php";
        return ob_get_clean();
    }
}
```

#### AJAX处理器类
```php
<?php
/**
 * My Module AJAX处理器
 */

class My_Module_AJAX extends WordPress_Toolkit_AJAX_Handler {

    protected function get_actions() {
        return array(
            'save_item' => array(
                'callback' => 'handle_save_item',
                'capability' => 'manage_options'
            ),
            'delete_item' => array(
                'callback' => 'handle_delete_item',
                'capability' => 'manage_options'
            ),
            'get_items' => array(
                'callback' => 'handle_get_items',
                'capability' => 'read',
                'nopriv' => false
            )
        );
    }

    protected function handle_save_item() {
        // 验证和清理输入
        $rules = array(
            'title' => array('type' => 'text', 'required' => true, 'label' => '标题'),
            'content' => array('type' => 'textarea', 'label' => '内容')
        );

        $data = $this->validate_input($_POST, $rules);

        // 保存数据
        $item = new My_Module_Item();
        $result = $item->save($data);

        if ($result) {
            $this->send_success($result, __('保存成功', 'wordpress-toolkit'));
        } else {
            $this->send_error(__('保存失败', 'wordpress-toolkit'));
        }
    }

    protected function handle_delete_item() {
        $item_id = intval($_POST['item_id']);

        // 验证权限
        if (!$this->can_manage_resource($item_id, 'my_module_item')) {
            $this->send_error(__('权限不足', 'wordpress-toolkit'), 'permission_denied', 403);
        }

        // 删除数据
        $item = new My_Module_Item($item_id);
        $result = $item->delete();

        if ($result) {
            $this->send_success(null, __('删除成功', 'wordpress-toolkit'));
        } else {
            $this->send_error(__('删除失败', 'wordpress-toolkit'));
        }
    }

    protected function handle_get_items() {
        $page = intval($_GET['page']) ?: 1;
        $per_page = intval($_GET['per_page']) ?: 20;

        $args = array(
            'page' => $page,
            'per_page' => $per_page,
            'user_id' => $this->get_current_user_id()
        );

        $item = new My_Module_Item();
        $items = $item->get_items($args);

        $this->send_success($items);
    }

    protected function can_manage_resource($resource_id, $resource_type) {
        // 实现自定义权限检查逻辑
        return current_user_can('manage_options') ||
               $this->check_resource_ownership($resource_id, $resource_type, $this->get_current_user_id());
    }
}
```

### 2. 数据模型开发

#### 数据模型类
```php
<?php
/**
 * My Module Item 数据模型
 */

class My_Module_Item {

    private $wpdb;
    private $table_name;

    public function __construct($id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'my_module_items';

        if ($id) {
            $this->load($id);
        }
    }

    public function save($data) {
        $data = $this->sanitize_data($data);

        if (isset($data['id']) && $data['id'] > 0) {
            return $this->update($data['id'], $data);
        } else {
            return $this->insert($data);
        }
    }

    public function insert($data) {
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $data['user_id'] = get_current_user_id();

        $result = $this->wpdb->insert($this->table_name, $data);

        if ($result !== false) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    public function update($id, $data) {
        $data['updated_at'] = current_time('mysql');

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            array('%s', '%s', '%d'),
            array('%d')
        );

        return $result !== false;
    }

    public function delete($id) {
        return $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }

    public function get($id) {
        $item = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $this->table_name,
            $id
        ));

        return $item;
    }

    public function get_items($args = array()) {
        $defaults = array(
            'user_id' => null,
            'status' => 'active',
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        // 构建查询
        $where_conditions = array();
        $where_values = array();

        if ($args['user_id']) {
            $where_conditions[] = "user_id = %d";
            $where_values[] = $args['user_id'];
        }

        if ($args['status']) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        $where_clause = !empty($where_conditions) ?
            "WHERE " . implode(" AND ", $where_conditions) : "";

        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit_clause = $this->wpdb->prepare("LIMIT %d OFFSET %d", $args['per_page'], $offset);

        $sql = "SELECT * FROM %i {$where_clause} ORDER BY {$args['orderby']} {$args['order']} {$limit_clause}";

        if (!empty($where_values)) {
            $sql = $this->wpdb->prepare($sql, $where_values);
        }

        return $this->wpdb->get_results($sql);
    }

    private function sanitize_data($data) {
        $sanitized = array();

        $sanitized['title'] = sanitize_text_field($data['title'] ?? '');
        $sanitized['content'] = wp_kses_post($data['content'] ?? '');
        $sanitized['status'] = sanitize_text_field($data['status'] ?? 'active');
        $sanitized['meta_data'] = $this->sanitize_meta_data($data['meta_data'] ?? array());

        return $sanitized;
    }

    private function sanitize_meta_data($meta_data) {
        if (!is_array($meta_data)) {
            return array();
        }

        $sanitized = array();
        foreach ($meta_data as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($value);
        }

        return $sanitized;
    }
}
```

## 🧪 测试指南

### 1. 单元测试

#### 测试文件结构
```
tests/
├── Unit/
│   ├── ModuleTest.php
│   ├── SecurityTest.php
│   └── DatabaseTest.php
├── Integration/
│   ├── ModuleIntegrationTest.php
│   └── AdminPageTest.php
├── bootstrap.php
└── phpunit.xml
```

#### 单元测试示例
```php
<?php
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {

    public function testValidateInput() {
        $input_data = array(
            'name' => 'Test Name',
            'email' => 'test@example.com',
            'age' => '25'
        );

        $rules = array(
            'name' => array('type' => 'text', 'required' => true),
            'email' => array('type' => 'email', 'required' => true),
            'age' => array('type' => 'int', 'min' => 0, 'max' => 150)
        );

        $result = WordPress_Toolkit_Security::validate_and_sanitize_input($input_data, $rules);

        $this->assertEmpty($result['errors']);
        $this->assertEquals('Test Name', $result['data']['name']);
        $this->assertEquals('test@example.com', $result['data']['email']);
        $this->assertEquals(25, $result['data']['age']);
    }

    public function testInvalidEmailValidation() {
        $input_data = array('email' => 'invalid-email');
        $rules = array('email' => array('type' => 'email', 'required' => true));

        $result = WordPress_Toolkit_Security::validate_and_sanitize_input($input_data, $rules);

        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
    }
}
```

### 2. 集成测试

#### WordPress集成测试
```php
<?php
class ModuleIntegrationTest extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
        $this->module = new My_Module();
    }

    public function testModuleActivation() {
        // 测试模块激活
        $this->module->activate();

        $options = get_option('wordpress_toolkit_my_module_options');
        $this->assertIsArray($options);
        $this->assertTrue($options['enabled']);
    }

    public function testAdminPageRendering() {
        // 测试管理页面渲染
        ob_start();
        $this->module->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContains('my-module', $output);
        $this->assertStringContains('form', $output);
    }

    public function testShortcodeOutput() {
        // 测试短代码输出
        $output = do_shortcode('[my_shortcode id="1"]');

        $this->assertNotEmpty($output);
        $this->assertIsString($output);
    }
}
```

## 🚀 部署指南

### 1. 代码检查

#### 使用PHP_CodeSniffer
```bash
# 安装WordPress编码标准
composer global require wp-coding-standards/wpcs

# 运行代码检查
vendor/bin/phpcs --standard=WordPress --extensions=php .
```

#### 使用PHPStan
```bash
# 安装PHPStan
composer require --dev phpstan/phpstan

# 配置phpstan.neon
echo "includes:
    - classes/
    - modules/
parameters:
    level: 6
    paths:
        - ." > phpstan.neon

# 运行静态分析
vendor/bin/phpstan analyse
```

### 2. 构建流程

#### 资源构建
```bash
# 构建CSS/JS
npm run build

# 压缩图片
npm run optimize-images

# 生成语言文件
npm run makepot
```

#### 版本发布
```bash
# 更新版本号
sed -i "s/Version: .*/Version: $NEW_VERSION/" wordpress-toolkit.php

# 生成发布包
git archive --format=zip --output=wordpress-toolkit-$NEW_VERSION.zip HEAD

# 上传到WordPress.org
wp plugin install wordpress-toolkit-$NEW_VERSION.zip --activate
```

### 3. 监控和维护

#### 性能监控
```php
// 添加性能监控
if (defined('WP_DEBUG') && WP_DEBUG) {
    $start_time = microtime(true);

    // 执行代码

    $execution_time = microtime(true) - $start_time;
    if ($execution_time > 1.0) {
        error_log("Slow query detected: {$execution_time}s");
    }
}
```

#### 错误追踪
```php
// 自定义错误处理
function my_error_handler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $error_message = "Error [{$errno}] {$errstr} in {$errfile} on line {$errline}";
    WordPress_Toolkit_Security::log_security_event('php_error', array(
        'error' => $error_message,
        'file' => $errfile,
        'line' => $errline
    ));

    return true;
}
set_error_handler('my_error_handler');
```

## 📚 学习资源

### WordPress开发资源
- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Security Handbook](https://developer.wordpress.org/plugins/security/)

### PHP开发资源
- [PHP The Right Way](https://phptherightway.com/)
- [PHP Standards Recommendations](https://www.php-fig.org/psr/)

### 安全开发资源
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)

---

遵循本指南可以确保WordPress Toolkit插件的高质量开发和维护。所有贡献者都应该熟悉这些规范和最佳实践。