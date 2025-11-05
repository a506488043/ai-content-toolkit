# WordPress Toolkit 架构文档

## 📋 概述

WordPress Toolkit 采用模块化架构设计，遵循WordPress最佳实践，具备企业级的代码质量标准。本文档详细描述了插件的架构设计、核心组件和扩展指南。

## 🏗️ 整体架构

### 架构层次

```
┌─────────────────────────────────────────┐
│              用户界面层                  │
│    ┌─────────────┐  ┌─────────────┐    │
│    │   前端界面    │  │  管理后台    │    │
│    └─────────────┘  └─────────────┘    │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│              业务逻辑层                  │
│    ┌─────────────┐  ┌─────────────┐    │
│    │   模块管理    │  │   AJAX处理   │    │
│    └─────────────┘  └─────────────┘    │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│              服务层                     │
│    ┌─────────────┐  ┌─────────────┐    │
│    │   安全服务   │  │  数据库服务  │    │
│    └─────────────┘  └─────────────┘    │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│              基础设施层                   │
│    ┌─────────────┐  ┌─────────────┐    │
│    │   工具类库   │  │  资源管理器  │    │
│    └─────────────┘  └─────────────┘    │
└─────────────────────────────────────────┘
```

### 核心设计原则

1. **单一职责原则**: 每个类和方法都有明确的职责
2. **开闭原则**: 对扩展开放，对修改封闭
3. **依赖倒置**: 依赖抽象而非具体实现
4. **DRY原则**: 不重复代码，通过基类和工具类复用
5. **安全优先**: 所有用户输入都需要验证和清理

## 📁 目录结构

```
wordpress-toolkit/
├── 📄 wordpress-toolkit.php          # 主插件文件
├── 📄 uninstall.php                  # 卸载脚本
├── 📄 README.md                      # 项目说明
├── 📄 CODE_OPTIMIZATION_REPORT.md    # 优化报告
├── 📄 ARCHITECTURE.md               # 架构文档
├── 📁 assets/                        # 静态资源
│   ├── css/                          # 样式文件
│   │   ├── variables.css             # CSS变量定义
│   │   ├── common.css                # 通用样式
│   │   └── admin.css                 # 管理后台样式
│   └── js/                           # JavaScript文件
│       ├── toolkit-core.js           # 核心脚本
│       ├── migration-helper.js       # 迁移助手
│       └── admin.js                  # 管理脚本
├── 📁 includes/                      # 核心类库
│   ├── class-logger.php              # 日志管理
│   ├── class-admin-page-template.php # 管理页面模板
│   ├── class-utility-functions.php   # 通用工具函数
│   ├── class-asset-manager.php       # 资源管理器
│   ├── 📁 security/                   # 安全组件
│   │   └── class-security-utils.php  # 安全工具类
│   ├── 📁 database/                   # 数据库组件
│   │   └── class-database-optimizer.php # 数据库优化器
│   └── 📁 abstracts/                  # 抽象基类
│       ├── abstract-module-base.php   # 模块基类
│       └── abstract-ajax-handler.php  # AJAX处理器基类
├── 📁 modules/                       # 功能模块
│   ├── 📁 custom-card/                # 网站卡片模块
│   ├── 📁 age-calculator/             # 年龄计算器模块
│   ├── 📁 time-capsule/               # 时间胶囊模块
│   ├── 📁 cookieguard/                # Cookie守护模块
│   ├── 📁 simple-friendlink/          # 简单友情链接模块
│   ├── 📁 auto-excerpt/               # 自动摘要模块
│   └── rest-proxy-fix.php             # REST代理修复
└── 📁 languages/                     # 语言文件
```

## 🧩 核心组件

### 1. 主插件类 (`WordPress_Toolkit`)

**职责**: 插件生命周期管理、模块加载、钩子注册

```php
class WordPress_Toolkit {
    private $asset_manager;      // 资源管理器
    private $custom_card;        // 各模块实例
    private $age_calculator;
    // ... 其他模块

    private function init_asset_manager() {
        $this->asset_manager = new WordPress_Toolkit_Asset_Manager();
    }

    private function load_modules() {
        // 加载各个功能模块
    }
}
```

### 2. 安全工具类 (`WordPress_Toolkit_Security`)

**职责**: 统一的安全验证、输入过滤、事件日志

**核心方法**:
- `verify_ajax_nonce()` - AJAX nonce验证
- `validate_and_sanitize_input()` - 输入数据验证
- `log_security_event()` - 安全事件日志
- `generate_secure_token()` - 安全令牌生成

### 3. 数据库优化器 (`WordPress_Toolkit_Database_Optimizer`)

**职责**: 数据库查询优化、索引管理、N+1查询修复

**核心方法**:
- `get_time_capsule_items_optimized()` - 优化的物品查询
- `add_recommended_indexes()` - 索引管理
- `clean_expired_cache()` - 缓存清理

### 4. 资源管理器 (`WordPress_Toolkit_Asset_Manager`)

**职责**: CSS/JS文件合并压缩、缓存管理、按需加载

**核心方法**:
- `enqueue_merged_css()` - 加载合并的CSS
- `enqueue_merged_js()` - 加载合并的JS
- `generate_merged_file()` - 生成合并文件

### 5. 模块基类 (`WordPress_Toolkit_Module_Base`)

**职责**: 模块通用功能、标准化接口、统一管理

**核心方法**:
- `register_shortcodes()` - 注册短代码
- `register_ajax_handlers()` - 注册AJAX处理器
- `render_admin_page()` - 渲染管理页面
- `handle_settings_save()` - 处理设置保存

### 6. AJAX处理器基类 (`WordPress_Toolkit_AJAX_Handler`)

**职责**: AJAX请求处理、安全验证、频率限制

**核心方法**:
- `verify_request()` - 请求验证
- `handle_ajax_request()` - 处理AJAX请求
- `verify_rate_limit()` - 频率限制验证

## 📦 模块架构

### 模块标准结构

每个功能模块都遵循统一的结构：

```
module-name/
├── 📄 module-name-module.php         # 模块主类
├── 📁 includes/                       # 模块内部类
│   ├── class-item.php                # 数据模型
│   ├── class-database.php            # 数据库操作
│   └── class-category.php            # 分类管理
├── 📁 admin/                          # 管理后台
│   └── admin.php                     # 管理页面
├── 📁 assets/                         # 静态资源
│   ├── css/
│   └── js/
├── 📁 templates/                      # 模板文件
└── 📁 languages/                      # 模块语言文件
```

### 模块开发规范

#### 1. 继承基类
```php
class MyModule extends WordPress_Toolkit_Module_Base {
    protected function init_module_properties() {
        $this->module_name = 'my-module';
        $this->module_version = '1.0.0';
        $this->option_name = 'wordpress_toolkit_my_module_options';
    }
}
```

#### 2. 实现必需方法
```php
abstract protected function render_page_content();
abstract protected function get_default_settings();
abstract public function register_shortcodes();
abstract public function register_ajax_handlers();
```

#### 3. 使用AJAX处理器
```php
class MyModuleAJAX extends WordPress_Toolkit_AJAX_Handler {
    protected function get_actions() {
        return array(
            'save_item' => array(
                'callback' => 'handle_save_item',
                'capability' => 'manage_options',
                'nopriv' => false
            )
        );
    }
}
```

## 🔐 安全架构

### 安全层级

1. **输入验证层**: 所有用户输入验证和清理
2. **权限控制层**: 细粒度的权限检查
3. **执行防护层**: SQL注入、XSS、CSRF防护
4. **审计日志层**: 安全事件记录和监控

### 安全机制

#### 1. 输入验证流程
```php
$data = $_POST['data'];
$rules = $this->get_validation_rules();
$result = WordPress_Toolkit_Security::validate_and_sanitize_input($data, $rules);
```

#### 2. 权限检查流程
```php
WordPress_Toolkit_Security::verify_ajax_nonce($nonce, $action);
WordPress_Toolkit_Security::verify_user_capability($capability);
```

#### 3. 数据库安全
```php
// 使用预处理语句
$sql = $wpdb->prepare("SELECT * FROM %i WHERE id = %d", $table_name, $id);
```

## 🚀 性能架构

### 缓存策略

1. **对象缓存**: WordPress内置对象缓存
2. **Transients缓存**: 临时数据缓存
3. **文件缓存**: 静态资源缓存
4. **数据库查询缓存**: 查询结果缓存

### 资源优化

1. **文件合并**: CSS/JS文件自动合并
2. **代码压缩**: 生产环境自动压缩
3. **按需加载**: 根据页面类型加载
4. **CDN支持**: 支持CDN资源分发

### 数据库优化

1. **索引优化**: 关键字段建立索引
2. **查询优化**: 避免N+1查询
3. **批量操作**: 减少数据库操作次数
4. **连接池**: 复用数据库连接

## 🔧 扩展指南

### 创建新模块

#### 1. 创建模块目录
```bash
mkdir modules/new-module
cd modules/new-module
```

#### 2. 创建模块主类
```php
<?php
class New_Module extends WordPress_Toolkit_Module_Base {
    protected function init_module_properties() {
        $this->module_name = 'new-module';
        $this->module_version = '1.0.0';
        $this->option_name = 'wordpress_toolkit_new_module_options';
    }

    protected function render_page_content() {
        // 实现管理页面内容
    }

    public function get_default_settings() {
        return array(
            'option1' => 'default_value1',
            'option2' => 'default_value2'
        );
    }

    public function register_shortcodes() {
        add_shortcode('new_module_shortcode', array($this, 'handle_shortcode'));
    }

    public function register_ajax_handlers() {
        // 注册AJAX处理器
    }
}
```

#### 3. 在主插件中加载
```php
// 在 WordPress_Toolkit 类中添加
private $new_module = null;

private function load_modules() {
    // 加载新模块
    $this->new_module = new New_Module();
}
```

### 使用工具类

#### 1. 安全工具类
```php
// 验证输入
$data = WordPress_Toolkit_Security::validate_and_sanitize_input($_POST, $rules);

// 记录安全事件
WordPress_Toolkit_Security::log_security_event('user_action', $details);
```

#### 2. 数据库优化器
```php
// 优化查询
$items = WordPress_Toolkit_Database_Optimizer::get_time_capsule_items_optimized($args);

// 添加索引
WordPress_Toolkit_Database_Optimizer::add_recommended_indexes($table_name);
```

#### 3. 资源管理器
```php
// 添加自定义CSS
$asset_manager->add_custom_css($css_content, 'my-module');

// 添加自定义JS
$asset_manager->add_custom_js($js_content, 'my-module', array('jquery'));
```

## 📊 监控和调试

### 性能监控

1. **查询统计**: 监控数据库查询次数和耗时
2. **缓存分析**: 监控缓存命中率
3. **资源加载**: 监控静态资源加载情况
4. **错误追踪**: 自动记录和分析错误

### 调试工具

1. **调试模式**: 开发环境下的详细信息显示
2. **日志系统**: 分级日志记录
3. **性能分析**: 内置性能分析工具
4. **安全审计**: 安全事件追踪

### 开发建议

1. **遵循编码规范**: WordPress编码标准
2. **使用工具类**: 充分利用现有工具
3. **编写测试**: 单元测试和集成测试
4. **文档更新**: 及时更新相关文档

## 🎯 最佳实践

### 安全最佳实践

1. **永远不要信任用户输入**
2. **使用预处理语句操作数据库**
3. **实现适当的权限检查**
4. **记录重要的安全事件**

### 性能最佳实践

1. **合理使用缓存**
2. **避免在循环中查询数据库**
3. **合并和压缩静态资源**
4. **按需加载资源**

### 代码质量最佳实践

1. **保持代码简洁清晰**
2. **使用有意义的命名**
3. **编写必要的注释**
4. **遵循单一职责原则

---

这个架构文档为WordPress Toolkit的维护和扩展提供了全面的指导。所有开发者都应该熟悉这些架构原则和最佳实践，以确保代码的一致性和质量。