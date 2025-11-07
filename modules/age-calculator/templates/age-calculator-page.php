<?php
/**
 * Template Name: Age Calculator Page
 */

get_header();

// Enqueue plugin assets
wp_enqueue_style( 'wordpress-toolkit-age-calculator', plugin_dir_url( __FILE__ ) . '../assets/style.css' );
wp_enqueue_script( 'wordpress-toolkit-age-calculator-script', plugin_dir_url( __FILE__ ) . '../assets/script.js', array('jquery'), null, true );

// Pass data to JavaScript
$current_user_birthdate = '';
if ( is_user_logged_in() ) {
    $current_user = wp_get_current_user();
    $current_user_birthdate = get_user_meta( $current_user->ID, 'birthdate', true );
}
wp_localize_script( 'wordpress-toolkit-age-calculator-script', 'manusAgeCalculatorData', array(
    'isLoggedIn' => is_user_logged_in(),
    'userBirthdate' => $current_user_birthdate,
));

?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <article class="page age-calculator-page">

            <div class="entry-content">
                <div class="age-calculator-container">
                    <?php echo do_shortcode('[manus_age_calculator_form]'); ?>
                </div>
            </div>
        </article>
    </main><!-- #main -->
</div><!-- #primary -->

<style>
/* 页面整体样式 */
body.page-template-age-calculator-page-php {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}


/* 计算器容器 */
.age-calculator-container {
    max-width: 800px;
    margin: 60px auto;
    padding: 0 20px;
}


/* 表单样式优化 */
.age-calculator-form {
    max-width: 500px;
    margin: 0 auto;
    padding: 40px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.age-calculator-form .form-group {
    margin-bottom: 25px;
}

.age-calculator-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 1rem;
}

.age-calculator-form input[type="date"],
.age-calculator-form select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
    color: #333;
}

.age-calculator-form input[type="date"]:focus,
.age-calculator-form select:focus {
    outline: none;
    border-color: #4facfe;
    box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
}

.age-calculator-form .button {
    width: 100%;
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.age-calculator-form .button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
}

/* 结果容器 */
.age-calculator-result-container {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #4facfe;
}

/* 年龄计算结果样式 */
.age-calculator-result {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
}

.age-calculator-result.detailed {
    white-space: nowrap;
}

.age-calculator-result.detailed .age-label {
    color: #666;
    font-weight: 500;
    margin-right: 5px;
}

.age-calculator-result.detailed .age-value {
    color: #4facfe;
    font-weight: 700;
    margin-right: 5px;
}

/* 年龄单位样式 */
.age-calculator-result.detailed .age-unit {
    margin-right: 8px;
}

.age-calculation-note {
    margin-top: 10px;
    font-size: 0.9rem;
    color: #888;
    line-height: 1.4;
}


/* 响应式设计 */
@media (max-width: 768px) {
    .age-calculator-page .page-title {
        font-size: 2rem;
    }

    .age-calculator-container {
        margin: 40px auto;
    }

    .age-calculator-form {
        padding: 30px 20px;
    }
}

/* 修复下拉框显示问题 */
.age-calculator-form select option {
    color: #333 !important;
    background: white !important;
}

.age-calculator-form select:focus option {
    color: #333 !important;
    background: white !important;
}
</style>

<?php
get_footer();
?>

