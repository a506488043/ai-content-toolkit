<?php
/**
 * 物品类
 */

if (!defined('ABSPATH')) {
    exit;
}

class TimeCapsule_Item {
    
    private $db;
    
    public function __construct() {
        $this->db = new TimeCapsule_Database();
    }
    
    /**
     * 保存物品
     */
    public function save($data) {
        try {
            // 数据验证
            $validated_data = $this->validate_data($data);
            
            if (isset($validated_data['id']) && $validated_data['id'] > 0) {
                // 更新
                $result = $this->db->update_item($validated_data['id'], $validated_data);
                if ($result !== false) {
                    return array(
                        'success' => true,
                        'message' => __('物品更新成功', 'time-capsule'),
                        'data' => $this->db->get_item($validated_data['id'])
                    );
                }
            } else {
                // 新增
                unset($validated_data['id']);
                $item_id = $this->db->insert_item($validated_data);
                if ($item_id) {
                    return array(
                        'success' => true,
                        'message' => __('物品添加成功', 'time-capsule'),
                        'data' => $this->db->get_item($item_id)
                    );
                }
            }
            
            return array(
                'success' => false,
                'message' => __('保存失败，请重试', 'time-capsule')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * 删除物品
     */
    public function delete($item_id) {
        try {
            $item_id = intval($item_id);
            if ($item_id <= 0) {
                throw new Exception(__('无效的物品ID', 'time-capsule'));
            }
            
            // 检查物品是否存在且属于当前用户
            $item = $this->db->get_item($item_id);
            if (!$item) {
                throw new Exception(__('物品不存在或无权限删除', 'time-capsule'));
            }
            
            $result = $this->db->delete_item($item_id);
            
            if ($result !== false) {
                return array(
                    'success' => true,
                    'message' => __('物品删除成功', 'time-capsule')
                );
            } else {
                throw new Exception(__('删除失败，请重试', 'time-capsule'));
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * 获取物品列表
     */
    public function get_items($args = array()) {
        $items = $this->db->get_items($args);
        
        // 为每个物品添加额外信息
        foreach ($items as &$item) {
            $item = $this->enhance_item_data($item);
        }
        
        return $items;
    }
    
    /**
     * 获取单个物品
     */
    public function get_item($item_id) {
        $item = $this->db->get_item($item_id);
        
        if ($item) {
            $item = $this->enhance_item_data($item);
        }
        
        return $item;
    }
    
    /**
     * 增强物品数据
     */
    private function enhance_item_data($item) {
        // 只有宠物类别计算年龄
        if ($item->category === 'pets' && !empty($item->warranty_period)) {
            $birth_timestamp = strtotime($item->warranty_period);
            $now = time();
            $item->age_years = floor(($now - $birth_timestamp) / (365 * 24 * 60 * 60));
        }

        // 计算使用天数
        $purchase_timestamp = strtotime($item->purchase_date);
        $now = time();
        $item->days_owned = floor(($now - $purchase_timestamp) / (24 * 60 * 60));

        // 计算过期天数（仅对零食食品类别）
        if ($item->category === 'snacks' && $item->shelf_life > 0) {
            $expiry_date = strtotime("+{$item->shelf_life} days", $purchase_timestamp);
            $item->days_expired = floor(($now - $expiry_date) / (24 * 60 * 60));
        }

        // 证书资质特殊处理
        if ($item->category === 'certificate') {
            // 使用purchase_date作为发证时间
            $issue_date = $item->purchase_date;

            // 计算持证时长（如果没有设置，则根据发证时间自动计算）
            if (empty($item->holding_duration) && !empty($issue_date)) {
                $issue_timestamp = strtotime($issue_date);
                $item->holding_duration = floor(($now - $issue_timestamp) / (30 * 24 * 60 * 60)); // 转换为月
            }

            // 检查续证状态
            if (!empty($item->renewal_date) && $item->renewal_period > 0) {
                $renewal_timestamp = strtotime($item->renewal_date);
                $next_renewal = strtotime("+{$item->renewal_period} months", $renewal_timestamp);

                if ($now > $next_renewal) {
                    $item->renewal_status = 'expired'; // 已过期需要续证
                } elseif ($now > strtotime('-30 days', $next_renewal)) {
                    $item->renewal_status = 'expiring'; // 即将到期
                } else {
                    $item->renewal_status = 'valid'; // 有效
                }

                $item->days_to_renewal = floor(($next_renewal - $now) / (24 * 60 * 60));
            }

            // 设置发证时间用于前端显示
            $item->issue_date = $issue_date;
        }

        // 格式化价格
        if ($item->price) {
            $item->formatted_price = '¥' . number_format($item->price, 2);
        }

        // 格式化证书相关费用
        if ($item->training_fee) {
            $item->formatted_training_fee = '¥' . number_format($item->training_fee, 2);
        }
        if ($item->renewal_fee) {
            $item->formatted_renewal_fee = '¥' . number_format($item->renewal_fee, 2);
        }
        
        // 获取类别信息
        $category = $this->db->get_category($item->category);
        if ($category) {
            $item->category_display_name = $category->display_name;
            $item->category_icon = $category->icon;
        }
        
        return $item;
    }
    
    /**
     * 验证数据
     */
    private function validate_data($data) {
        $validated = array();

        // 获取物品类别，用于字段验证
        $item_category = isset($data['category']) ? $data['category'] : '';

        // ID（可选）
        if (isset($data['id'])) {
            $validated['id'] = intval($data['id']);
        }

        // 必填字段
        if (empty($data['name'])) {
            throw new Exception(__('物品名称不能为空', 'time-capsule'));
        }
        $validated['name'] = sanitize_text_field($data['name']);

        if (empty($data['category'])) {
            throw new Exception(__('请选择物品类别', 'time-capsule'));
        }
        $validated['category'] = sanitize_text_field($data['category']);

        if (empty($data['purchase_date'])) {
            throw new Exception(__('购买日期不能为空', 'time-capsule'));
        }
        $validated['purchase_date'] = sanitize_text_field($data['purchase_date']);

        // 验证日期格式
        if (!$this->validate_date($validated['purchase_date'])) {
            throw new Exception(__('购买日期格式不正确', 'time-capsule'));
        }
        
        // 可选字段
        if (isset($data['description'])) {
            $validated['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['purchase_source'])) {
            $validated['purchase_source'] = sanitize_text_field($data['purchase_source']);
        }
        
        if (isset($data['warranty_period'])) {
            if (!empty($data['warranty_period'])) {
                // 只有宠物类别使用出生日期，其他类别使用保修期
                if ($item_category === 'pets') {
                    if ($this->validate_date($data['warranty_period'])) {
                        $validated['warranty_period'] = sanitize_text_field($data['warranty_period']);
                    } else {
                        throw new Exception(__('出生日期格式不正确', 'time-capsule'));
                    }
                } else {
                    // 其他类别使用保修期（整数天数）
                    $validated['warranty_period'] = intval($data['warranty_period']);
                }
            }
        }

        // shelf_life字段只适用于特定类别（如食品）
        if (in_array($item_category, ['appliances', 'food'])) {
            if (isset($data['shelf_life'])) {
                $validated['shelf_life'] = intval($data['shelf_life']);
            }
        }

        if (isset($data['total_mileage'])) {
            $validated['total_mileage'] = floatval($data['total_mileage']);
        }

        if (isset($data['used_time_hours'])) {
            $validated['used_time_hours'] = floatval($data['used_time_hours']);
        }

        // 证书资质特殊字段
        if (isset($data['issue_date'])) {
            if (!empty($data['issue_date'])) {
                if ($this->validate_date($data['issue_date'])) {
                    $validated['issue_date'] = sanitize_text_field($data['issue_date']);
                } else {
                    throw new Exception(__('发证时间格式不正确', 'time-capsule'));
                }
            }
        }

        if (isset($data['issuing_authority'])) {
            $validated['issuing_authority'] = sanitize_text_field($data['issuing_authority']);
        }

        if (isset($data['renewal_period'])) {
            $validated['renewal_period'] = intval($data['renewal_period']);
        }

        if (isset($data['holding_duration'])) {
            $validated['holding_duration'] = intval($data['holding_duration']);
        }

        if (isset($data['renewal_date'])) {
            if (!empty($data['renewal_date'])) {
                if ($this->validate_date($data['renewal_date'])) {
                    $validated['renewal_date'] = sanitize_text_field($data['renewal_date']);
                } else {
                    throw new Exception(__('续证时间格式不正确', 'time-capsule'));
                }
            }
        }

        if (isset($data['training_fee'])) {
            $validated['training_fee'] = floatval($data['training_fee']);
        }

        if (isset($data['renewal_fee'])) {
            $validated['renewal_fee'] = floatval($data['renewal_fee']);
        }

        // 证书资质新增字段验证
        if (isset($data['certificate_number'])) {
            $validated['certificate_number'] = sanitize_text_field($data['certificate_number']);
        }

        if (isset($data['renewal_unit'])) {
            $allowed_units = array('months', 'years');
            if (in_array($data['renewal_unit'], $allowed_units)) {
                $validated['renewal_unit'] = $data['renewal_unit'];
            }
        }

        if (isset($data['certificate_level'])) {
            $allowed_levels = array('entry', 'intermediate', 'senior', 'expert', 'other');
            if (in_array($data['certificate_level'], $allowed_levels)) {
                $validated['certificate_level'] = $data['certificate_level'];
            }
        }

        if (isset($data['reminder_days'])) {
            $validated['reminder_days'] = intval($data['reminder_days']);
        }

        if (isset($data['certificate_status'])) {
            $allowed_statuses = array('valid', 'expired', 'renewing', 'suspended');
            if (in_array($data['certificate_status'], $allowed_statuses)) {
                $validated['certificate_status'] = $data['certificate_status'];
            }
        }
        
        if (isset($data['price'])) {
            $validated['price'] = floatval($data['price']);
        }
        
        if (isset($data['brand'])) {
            $validated['brand'] = sanitize_text_field($data['brand']);
        }
        
        if (isset($data['model'])) {
            $validated['model'] = sanitize_text_field($data['model']);
        }
        
        if (isset($data['serial_number'])) {
            $validated['serial_number'] = sanitize_text_field($data['serial_number']);
        }
        
        if (isset($data['status'])) {
            $allowed_status = array('active', 'inactive', 'disposed');
            if (in_array($data['status'], $allowed_status)) {
                $validated['status'] = $data['status'];
            }
        }
        
        if (isset($data['notes'])) {
            $validated['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        return $validated;
    }
    
    /**
     * 验证日期格式
     */
    private function validate_date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * 获取统计信息
     */
    public function get_stats($user_id = null) {
        return $this->db->get_stats($user_id);
    }
    
    /**
     * 导出数据
     */
    public function export_data($format = 'csv') {
        $items = $this->db->get_items(array('limit' => 0));
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($items);
            case 'json':
                return $this->export_to_json($items);
            default:
                throw new Exception(__('不支持的导出格式', 'time-capsule'));
        }
    }
    
    /**
     * 导出为CSV
     */
    private function export_to_csv($items) {
        $output = fopen('php://temp', 'w');
        
        // CSV头部
        $headers = array(
            '物品名称', '类别', '描述', '购买日期', '购买来源',
            '出生日期', '总里程', '年龄(岁)', '价格',
            '品牌', '型号', '序列号', '状态', '备注'
        );
        fputcsv($output, $headers);
        
        // 数据行
        foreach ($items as $item) {
            $row = array(
                $item->name,
                $item->category_display_name ?? $item->category,
                $item->description,
                $item->purchase_date,
                $item->purchase_source,
                $item->warranty_period,
                $item->total_mileage,
                $item->used_time_hours,
                $item->price,
                $item->brand,
                $item->model,
                $item->serial_number,
                $item->status,
                $item->notes
            );
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * 导出为JSON
     */
    private function export_to_json($items) {
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

