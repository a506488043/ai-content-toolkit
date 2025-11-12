# 简洁友情链接模块 (Simple FriendLink Module)

## 功能特色

- **🎨 1.16.zip插件风格设计** - 完全匹配1.16.zip插件的卡片式设计和布局
- **🔄 RSS文章获取** - 自动获取友情链接网站的最新文章（可扩展）
- **✨ 动画效果** - 最近更新的友情链接带有炫酷的旋转阴影动画
- **📱 响应式设计** - 完美适配各种设备屏幕
- **🗂️ 自定义数据库** - 独立的数据库表，性能更佳
- **🔧 管理界面** - 简洁易用的后台管理界面
- **📝 用户提交** - 支持用户提交友情链接申请

## 安装说明

1. 确保WordPress Toolkit插件已激活
2. 模块会自动创建数据库表 `wp_simple_friendlinks`
3. 自动迁移现有的WordPress链接数据

## 使用方法

### 创建友情链接页面

1. 在WordPress后台进入 "页面" → "新建页面"
2. 在页面属性中选择模板 "友情链接页面"
3. 发布页面

### 前端特色

#### 卡片式布局
- 350px固定宽度卡片
- 圆角设计 (14px)
- 悬停向上移动效果
- 优雅的阴影效果

#### 网站图标
- 60x60px圆形图标
- 支持自定义图片
- 无图片时显示首字母渐变图标

#### 最新文章显示
- 显示友站最新文章标题和日期
- 1周内文章带旋转阴影动画
- 无文章时显示 "无法获取文章 (＞﹏＜)"

### 后台管理

#### 设置选项
- 是否允许用户提交
- 是否需要登录
- 是否需要管理员审核
- 每页显示数量

#### 链接管理
- 批量审核通过/拒绝
- 删除待审核链接
- 查看链接统计

## 数据库结构

```sql
CREATE TABLE wp_simple_friendlinks (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    url varchar(255) NOT NULL,
    description text,
    icon_url varchar(255),
    latest_post_title varchar(255),
    latest_post_url varchar(255),
    latest_post_date date,
    sort_order int DEFAULT 0,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## CSS类名

### 主要容器
- `.simple-friendlink-container` - 主容器
- `.simple-friendlink-list` - 友情链接网格
- `.link-item` - 单个友情链接卡片

### 卡片元素
- `.link-item-header` - 卡片头部
- `.link-icon-link` - 图标链接
- `.link-icon` - 网站图标
- `.link-info` - 网站信息
- `.link-name` - 网站名称
- `.link-description` - 网站描述
- `.link-latest-post` - 最新文章区域
- `.latest-post-title` - 文章标题
- `.latest-post-date` - 文章日期
- `.no-latest-post` - 无文章提示

### 特效类
- `.recent-post` - 最近文章卡片（带动画）

## 自定义开发

### 添加新的字段
在 `install()` 方法中修改SQL语句，并在 `ajax_add_link()` 中处理新字段。

### 修改样式
编辑 `assets/css/simple-friendlink.css` 文件。

### 扩展功能
模块使用标准WordPress钩子，可以轻松扩展功能。

## 注意事项

1. 模块会自动隐藏WordPress侧边栏
2. 使用自定义数据库表，不影响WordPress链接管理器
3. 支持从现有WordPress链接自动迁移数据
4. 所有用户输入都经过安全验证和清理

## 版本兼容性

- WordPress 5.0+
- PHP 7.2+
- 兼容所有现代主题

## 技术栈

- PHP 7.2+
- MySQL 5.7+
- CSS3 Grid/Flexbox
- WordPress REST API (AJAX)

---

💡 **提示**: 这是匹配1.16.zip插件风格的友情链接解决方案，具有相同的外观和用户体验！