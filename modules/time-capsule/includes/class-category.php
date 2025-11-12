<?php
/**
 * 类别类
 */

if (!defined('ABSPATH')) {
    exit;
}

class TimeCapsule_Category {
    
    private $db;
    
    public function __construct() {
        $this->db = new TimeCapsule_Database();
    }
    
    /**
     * 获取所有类别
     */
    public function get_categories($active_only = true) {
        $categories = $this->db->get_categories($active_only);
        
        foreach ($categories as &$category) {
            if ($category->fields) {
                $category->fields = json_decode($category->fields, true);
            }
        }
        
        return $categories;
    }
    
    /**
     * 获取单个类别
     */
    public function get_category($name) {
        $category = $this->db->get_category($name);
        
        if ($category && $category->fields) {
            $category->fields = json_decode($category->fields, true);
        }
        
        return $category;
    }
    
    /**
     * 获取类别的字段配置
     */
    public function get_category_fields($category_name) {
        $category = $this->get_category($category_name);
        
        if ($category && isset($category->fields)) {
            return $category->fields;
        }
        
        return array();
    }
    
    /**
     * 渲染类别字段表单
     */
    public function render_category_fields($category_name, $values = array()) {
        $fields = $this->get_category_fields($category_name);
        
        if (empty($fields)) {
            return '';
        }
        
        $html = '';
        
        foreach ($fields as $field_name => $field_config) {
            $value = isset($values[$field_name]) ? $values[$field_name] : '';
            $html .= $this->render_field($field_name, $field_config, $value);
        }
        
        return $html;
    }
    
    /**
     * 渲染单个字段
     */
    private function render_field($field_name, $config, $value = '') {
        $label = isset($config['label']) ? $config['label'] : $field_name;
        $type = isset($config['type']) ? $config['type'] : 'text';
        $unit = isset($config['unit']) ? $config['unit'] : '';
        $required = isset($config['required']) && $config['required'] ? 'required' : '';
        
        $html = '<div class="tc-field-group">';
        $html .= '<label for="tc_' . $field_name . '">' . esc_html($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';
        
        switch ($type) {
            case 'number':
                $html .= '<input type="number" id="tc_' . $field_name . '" name="' . $field_name . '" value="' . esc_attr($value) . '" ' . $required . ' step="0.01">';
                break;
                
            case 'date':
                $html .= '<input type="date" id="tc_' . $field_name . '" name="' . $field_name . '" value="' . esc_attr($value) . '" ' . $required . '>';
                break;
                
            case 'textarea':
                $html .= '<textarea id="tc_' . $field_name . '" name="' . $field_name . '" ' . $required . '>' . esc_textarea($value) . '</textarea>';
                break;
                
            case 'select':
                $html .= '<select id="tc_' . $field_name . '" name="' . $field_name . '" ' . $required . '>';
                if (isset($config['options'])) {
                    foreach ($config['options'] as $option_value => $option_label) {
                        $selected = ($value == $option_value) ? 'selected' : '';
                        $html .= '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                    }
                }
                $html .= '</select>';
                break;
                
            default: // text
                $html .= '<input type="text" id="tc_' . $field_name . '" name="' . $field_name . '" value="' . esc_attr($value) . '" ' . $required . '>';
                break;
        }
        
        if ($unit) {
            $html .= '<span class="field-unit">' . esc_html($unit) . '</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 获取类别选项
     */
    public function get_category_options() {
        $categories = $this->get_categories();
        $options = array();
        
        foreach ($categories as $category) {
            $options[$category->name] = $category->display_name;
        }
        
        return $options;
    }
    
    /**
     * 验证类别是否存在
     */
    public function category_exists($name) {
        $category = $this->get_category($name);
        return !empty($category);
    }

    /**
     * 添加类别
     */
    public function add_category($data) {
        // 验证必填字段
        if (empty($data['name']) || empty($data['display_name'])) {
            return new WP_Error('missing_fields', '类别名称和显示名称是必填项');
        }

        // 验证类别名称格式
        if (!preg_match('/^[a-z0-9_-]+$/', $data['name'])) {
            return new WP_Error('invalid_name', '类别名称只能包含小写字母、数字、下划线和连字符');
        }

        // 检查类别是否已存在
        if ($this->category_exists($data['name'])) {
            return new WP_Error('category_exists', '类别名称已存在');
        }

        // 设置默认值
        $defaults = array(
            'description' => '',
            'icon' => 'tag',
            'color' => '#007bff',
            'is_active' => 1,
            'sort_order' => 0
        );

        $data = wp_parse_args($data, $defaults);

        // 插入类别
        $result = $this->db->insert_category($data);

        if ($result === false) {
            return new WP_Error('insert_failed', '添加类别失败');
        }

        return $result;
    }

    /**
     * 删除类别
     */
    public function delete_category($name) {
        // 检查类别是否存在
        if (!$this->category_exists($name)) {
            return new WP_Error('category_not_found', '类别不存在');
        }

        // 检查类别中是否有物品
        $item_count = $this->get_category_item_count($name);
        if ($item_count > 0) {
            return new WP_Error('category_has_items', '该类别中还有物品，无法删除');
        }

        // 删除类别
        $result = $this->db->delete_category($name);

        if (!$result) {
            return new WP_Error('delete_failed', '删除类别失败');
        }

        return true;
    }

    /**
     * 获取类别中的物品数量
     */
    public function get_category_item_count($category_name) {
        global $wpdb;
        $table_items = $wpdb->prefix . 'time_capsule_items';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_items} WHERE category = %s",
                $category_name
            )
        );

        return intval($count);
    }

    /**
     * 插入默认类别
     */
    public function insert_default_categories() {
        $default_categories = array(
            array(
                'name' => 'vehicle',
                'display_name' => '交通工具',
                'description' => '汽车、摩托车、自行车等交通工具',
                'icon' => 'car',
                'color' => '#007bff',
                'is_active' => 1,
                'sort_order' => 1
            ),
            array(
                'name' => 'electronics',
                'display_name' => '电子产品',
                'description' => '手机、电脑、平板等电子设备',
                'icon' => 'devices',
                'color' => '#28a745',
                'is_active' => 1,
                'sort_order' => 2
            ),
            array(
                'name' => 'certificate',
                'display_name' => '证书资质',
                'description' => '专业认证、资格证书、学历证书等',
                'icon' => 'certificate',
                'color' => '#17a2b8',
                'is_active' => 1,
                'sort_order' => 3
            ),
            array(
                'name' => 'pets',
                'display_name' => '宠物',
                'description' => '宠物、动物等',
                'icon' => 'paw',
                'color' => '#6c757d',
                'is_active' => 1,
                'sort_order' => 4
            ),
            array(
                'name' => 'appliances',
                'display_name' => '家用电器',
                'description' => '冰箱、洗衣机、空调等家用电器',
                'icon' => 'home',
                'color' => '#ffc107',
                'is_active' => 0,
                'sort_order' => 5
            ),
            array(
                'name' => 'furniture',
                'display_name' => '家具',
                'description' => '沙发、床、桌子等家具',
                'icon' => 'chair',
                'color' => '#dc3545',
                'is_active' => 0,
                'sort_order' => 6
            ),
            array(
                'name' => 'clothing',
                'display_name' => '服装',
                'description' => '衣服、鞋子、配饰等',
                'icon' => 'shirt',
                'color' => '#6f42c1',
                'is_active' => 0,
                'sort_order' => 7
            ),
            array(
                'name' => 'books',
                'display_name' => '图书',
                'description' => '书籍、杂志、教材等',
                'icon' => 'book',
                'color' => '#fd7e14',
                'is_active' => 0,
                'sort_order' => 8
            ),
            array(
                'name' => 'sports',
                'display_name' => '运动器材',
                'description' => '健身器材、运动装备等',
                'icon' => 'bicycle',
                'color' => '#20c997',
                'is_active' => 0,
                'sort_order' => 9
            ),
            array(
                'name' => 'tools',
                'display_name' => '工具',
                'description' => '手工工具、电动工具等',
                'icon' => 'tools',
                'color' => '#6c757d',
                'is_active' => 0,
                'sort_order' => 10
            ),
            array(
                'name' => 'other',
                'display_name' => '其他',
                'description' => '其他未分类物品',
                'icon' => 'tag',
                'color' => '#6c757d',
                'is_active' => 0,
                'sort_order' => 11
            )
        );

        foreach ($default_categories as $category) {
            // 检查类别是否已存在
            if (!$this->category_exists($category['name'])) {
                $this->db->insert_category($category);
            } else {
                // 如果类别已存在，更新激活状态
                $this->db->update_category($category['name'], array('is_active' => $category['is_active']));
            }
        }

        return true;
    }

    /**
     * 将'other'类别更新为'pets'类别
     */
    public function update_other_to_pets() {
        global $wpdb;

        // 检查是否存在'other'类别
        $other_category = $this->get_category('other');
        if ($other_category) {
            // 检查是否已经存在'pets'类别
            $pets_category = $this->get_category('pets');
            if ($pets_category) {
                // 如果已经存在'pets'类别，则删除'other'类别
                $this->db->delete_category('other');

                // 将使用'other'类别的物品更新为'pets'类别
                $table_items = $wpdb->prefix . 'time_capsule_items';
                $wpdb->update(
                    $table_items,
                    array('category' => 'pets'),
                    array('category' => 'other')
                );
            } else {
                // 如果不存在'pets'类别，则更新'other'类别为'pets'
                $update_data = array(
                    'name' => 'pets',
                    'display_name' => '宠物',
                    'description' => '宠物、动物等',
                    'icon' => 'paw',
                    'color' => '#6c757d'
                );

                $this->db->update_category('other', $update_data);

                // 同时更新物品表中使用'other'类别的物品
                $table_items = $wpdb->prefix . 'time_capsule_items';
                $wpdb->update(
                    $table_items,
                    array('category' => 'pets'),
                    array('category' => 'other')
                );
            }
        }

        return true;
    }

    /**
     * 强制更新类别（公开方法）
     */
    public function force_update_categories() {
        // 重新插入默认类别
        $this->insert_default_categories();

        return true;
    }
}

