<?php
/**
 * 通用函数
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取保修状态的显示文本
 */
function tc_get_warranty_status_text($status) {
    switch ($status) {
        case 'valid':
            return __('保修中', 'time-capsule');
        case 'expiring':
            return __('即将过保', 'time-capsule');
        case 'expired':
            return __('已过保', 'time-capsule');
        case 'no_warranty':
        default:
            return __('无保修', 'time-capsule');
    }
}




/**
 * 计算物品年龄
 */
function tc_calculate_item_age($purchase_date) {
    $purchase_timestamp = strtotime($purchase_date);
    $now = time();
    $diff = $now - $purchase_timestamp;
    
    $years = floor($diff / (365 * 24 * 60 * 60));
    $months = floor(($diff % (365 * 24 * 60 * 60)) / (30 * 24 * 60 * 60));
    $days = floor(($diff % (30 * 24 * 60 * 60)) / (24 * 60 * 60));
    
    if ($years > 0) {
        if ($months > 0) {
            return sprintf(__('%d 年 %d 个月', 'time-capsule'), $years, $months);
        } else {
            return sprintf(__('%d 年', 'time-capsule'), $years);
        }
    } elseif ($months > 0) {
        if ($days > 0) {
            return sprintf(__('%d 个月 %d 天', 'time-capsule'), $months, $days);
        } else {
            return sprintf(__('%d 个月', 'time-capsule'), $months);
        }
    } else {
        return sprintf(__('%d 天', 'time-capsule'), $days);
    }
}

/**
 * 获取物品状态的显示文本
 */
function tc_get_status_text($status, $category = '') {
    // 宠物类别使用不同的状态文本
    if ($category === 'pets') {
        switch ($status) {
            case 'active':
                return __('在养', 'time-capsule');
            case 'inactive':
                return __('寄养', 'time-capsule');
            case 'disposed':
                return __('已送养', 'time-capsule');
            default:
                return __('未知', 'time-capsule');
        }
    } else {
        // 其他类别使用原有状态文本
        switch ($status) {
            case 'active':
                return __('使用中', 'time-capsule');
            case 'inactive':
                return __('闲置', 'time-capsule');
            case 'disposed':
                return __('已处置', 'time-capsule');
            default:
                return __('未知', 'time-capsule');
        }
    }
}



/**
 * 检查用户权限
 */
function tc_user_can_manage_items($user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }

    return user_can($user_id, 'manage_options');
}



/**
 * 获取默认头像
 */
function tc_get_default_avatar($category) {
    $avatars = array(
        'vehicle' => '🚗',
        'electronics' => '📱',
        'furniture' => '🪑'
    );

    return isset($avatars[$category]) ? $avatars[$category] : '📦';
}

/**
 * 获取类别的徽章CSS类
 */
function tc_get_category_badge_class($category) {
    switch ($category) {
        case '交通工具':
            return 'badge-transportation';
        case '电子产品':
            return 'badge-electronics';
        case '家具':
            return 'badge-furniture';
        case '服装':
            return 'badge-clothing';
        case '食品':
            return 'badge-food';
        case '书籍':
            return 'badge-books';
        case '运动器材':
            return 'badge-sports';
        default:
            return 'badge-other';
    }
}

/**
 * 获取保修状态的徽章CSS类
 */
function tc_get_warranty_status_badge_class($status) {
    switch ($status) {
        case 'valid':
            return 'badge-valid';
        case 'expiring':
            return 'badge-expiring';
        case 'expired':
            return 'badge-expired';
        case 'no_warranty':
        default:
            return 'badge-no-warranty';
    }
}

/**
 * 获取状态的徽章CSS类
 */
function tc_get_status_badge_class($status) {
    switch ($status) {
        case 'active':
            return 'badge-active';
        case 'inactive':
            return 'badge-inactive';
        case 'disposed':
            return 'badge-disposed';
        default:
            return 'badge-inactive';
    }
}
