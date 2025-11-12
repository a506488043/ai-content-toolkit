<?php
/**
 * Template Name: 简单友情链接页面
 *
 * @package WordPress Toolkit
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 处理友情链接申请表单提交
if (isset($_POST['submit_friendlink']) && class_exists('Simple_FriendLink_Module')) {
    $friendlink_module = Simple_FriendLink_Module::get_instance();
    $friendlink_module->handle_form_submission();
}

// 加载简单的友情链接样式
wp_enqueue_style('simple-friendlink-style', WORDPRESS_TOOLKIT_PLUGIN_URL . 'modules/simple-friendlink/assets/css/simple-friendlink.css', array(), WORDPRESS_TOOLKIT_VERSION);

get_header(); ?>

<div class="friends-plugin-container">
    <?php while (have_posts()) : the_post(); ?>

        <?php
        if (class_exists('Simple_FriendLink_Module')) {
            $friendlink_module = Simple_FriendLink_Module::get_instance();
            // 获取友情链接数据
            $links = $friendlink_module->get_friendlinks();
        ?>

        <?php if ( ! empty( $links ) ) : ?>
            <div class="friends-grid">
                <?php foreach ( $links as $link ) : ?>
                    <div class="friend-card">
                        <div class="friend-card-header">
                            <a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener noreferrer" class="friend-icon-link">
                                <?php
                                // 获取网站Logo - 仅使用缓存，不实时获取
                                $site_logo = null;
                                if (!empty($link->icon_url)) {
                                    // 用户自定义图标优先级最高
                                    $site_logo = esc_url($link->icon_url);
                                } else {
                                    // 仅从缓存获取Logo，不实时请求
                                    $cache_key = 'friendlink_logo_' . md5($link->url);
                                    $cached_logo = get_transient($cache_key);
                                    if ($cached_logo !== false) {
                                        $site_logo = $cached_logo;
                                    }
                                }

                                $first_char = mb_substr($link->name, 0, 1);

                                // 根据网站名称生成渐变色，让每个链接更有个性
                                $colors = array(
                                    'linear-gradient(45deg, #3b82f6, #2563eb)', // 蓝色
                                    'linear-gradient(45deg, #10b981, #059669)', // 绿色
                                    'linear-gradient(45deg, #f59e0b, #d97706)', // 橙色
                                    'linear-gradient(45deg, #ef4444, #dc2626)', // 红色
                                    'linear-gradient(45deg, #8b5cf6, #7c3aed)', // 紫色
                                    'linear-gradient(45deg, #ec4899, #db2777)', // 粉色
                                    'linear-gradient(45deg, #06b6d4, #0891b2)', // 青色
                                    'linear-gradient(45deg, #84cc16, #65a30d)', // 黄绿色
                                );

                                // 使用网站名称的字符码来选择颜色
                                $color_index = ord($first_char) % count($colors);
                                $gradient_color = $colors[$color_index];

                                $fallback_style = 'background: ' . $gradient_color . '; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 20px; width: 60px; height: 60px; border-radius: 50%;';

                                if ($site_logo) {
                                    // 显示获取到的Logo
                                    ?>
                                    <img src="<?php echo $site_logo; ?>" alt="<?php echo esc_attr($link->name); ?> Logo" class="friend-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="friend-icon" style="<?php echo $fallback_style; ?> display: none;">
                                        <?php echo $first_char; ?>
                                    </div>
                                    <?php
                                } else {
                                    // 没有Logo，显示首字母占位符
                                    ?>
                                    <div class="friend-icon" style="<?php echo $fallback_style; ?>">
                                        <?php echo $first_char; ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </a>
                            <div class="friend-info">
                                <h3 class="friend-name">
                                    <a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html( $link->name ); ?>
                                    </a>
                                </h3>
                                <p class="friend-description">
                                    <?php
                                    $description = !empty($link->description) ? $link->description : '';

                                    // 仅从缓存获取网站信息，不实时请求
                                    if (empty($description)) {
                                        $cache_key = 'friendlink_site_info_' . md5($link->url);
                                        $cached_info = get_transient($cache_key);
                                        if ($cached_info !== false && !empty($cached_info['description'])) {
                                            $description = $cached_info['description'];
                                        } else {
                                            $description = '个人博客网站';
                                        }
                                    }

                                    echo esc_html($description);
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="friend-latest-post">
                            <?php
                            // 仅从缓存获取RSS最新文章，不实时请求
                            $cache_key = 'friendlink_rss_' . md5($link->url);
                            $latest_post = get_transient($cache_key);
                            if ($latest_post !== false && !empty($latest_post['title'])): ?>
                                <a href="<?php echo esc_url($latest_post['url']); ?>" target="_blank" rel="noopener noreferrer" class="latest-post-title">
                                    <?php echo esc_html($latest_post['title']); ?>
                                </a>
                                <span class="latest-post-date">
                                    <?php echo esc_html(date('Y-m-d', $latest_post['date'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-latest-post">暂无文章</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p>No friend links to display yet.</p>
        <?php endif; ?>

        <!-- 友情链接申请表单 -->
        <?php
        $settings = $friendlink_module->get_settings();
        $allow_submit = $settings['allow_user_submit'] ?? true;
        $require_login = $settings['require_login'] ?? true;

        if ($allow_submit): ?>
            <div class="friendlink-application">
                <h3>申请友情链接</h3>
                <?php if ($require_login && !is_user_logged_in()): ?>
                    <p class="login-required">请先<a href="<?php echo wp_login_url(get_permalink()); ?>">登录</a>后再申请友情链接。</p>
                <?php else: ?>
                    <form class="friendlink-form" method="post">
                        <?php wp_nonce_field('simple_friendlink_submit', 'friendlink_nonce'); ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="friendlink_name">网站名称 *</label>
                                <input type="text" id="friendlink_name" name="friendlink_name" required maxlength="100">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="friendlink_url">网站地址 *</label>
                                <input type="url" id="friendlink_url" name="friendlink_url" required maxlength="200" placeholder="https://example.com">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="friendlink_email">联系邮箱</label>
                                <input type="email" id="friendlink_email" name="friendlink_email" maxlength="100">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="friendlink_description">网站描述</label>
                                <textarea id="friendlink_description" name="friendlink_description" rows="3" maxlength="500" placeholder="请简要描述您的网站内容..."></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <button type="submit" name="submit_friendlink" class="submit-btn">
                                提交申请
                            </button>
                        </div>
                    </form>

                    <div class="form-note">
                        <p>* 为必填项。提交后将在24小时内审核。</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        }
        ?>
    </div>
    <?php endwhile; ?>
</div>

<style>
.friendlink-application {
    margin-top: 40px;
    padding: 30px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.friendlink-application h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    font-size: 24px;
    text-align: center;
}

.friendlink-form {
    max-width: 600px;
    margin: 0 auto;
}

.form-row {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
}

.submit-btn {
    background: linear-gradient(135deg, #0073aa, #005a87);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.submit-btn:hover {
    background: linear-gradient(135deg, #005a87, #00415c);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 115, 170, 0.3);
}

.submit-btn:active {
    transform: translateY(0);
}

.login-required {
    text-align: center;
    padding: 20px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    color: #856404;
}

.login-required a {
    color: #0073aa;
    text-decoration: none;
    font-weight: 600;
}

.login-required a:hover {
    text-decoration: underline;
}

.form-note {
    text-align: center;
    margin-top: 20px;
    color: #666;
    font-size: 14px;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .friendlink-application {
        margin-top: 30px;
        padding: 20px;
    }

    .friendlink-form {
        max-width: 100%;
    }

    .submit-btn {
        padding: 12px 20px;
        font-size: 14px;
    }
}
</style>

<?php get_footer(); ?>