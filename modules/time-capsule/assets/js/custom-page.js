/**
 * 物品管理自定义页面JavaScript
 * 处理页面交互和AJAX请求
 */

(function($) {
    'use strict';
    
    // 全局变量
    let currentCategory = '';
    let currentView = 'grid';
    let currentTab = 'overview';
    let itemsData = [];
    let filteredItems = [];
    
    // 初始化
    $(document).ready(function() {
        initializeCustomPage();
        loadInitialData();
        bindEvents();
    });

    // 初始化页面
    function initializeCustomPage() {
        // 从URL参数获取初始选项卡
        const urlParams = new URLSearchParams(window.location.search);
        const initialTab = urlParams.get('tab') || 'overview';

        // 设置初始选项卡
        showTab(initialTab);

        // 初始化筛选器
        resetFilters();

        // 设置默认视图
        setView('grid');

        // 如果是列表视图，强制水平布局
        if (currentView === 'list') {
            setTimeout(() => {
                forceHorizontalLayout();
            }, 100);
        }
    }
    
    // 加载初始数据
    function loadInitialData() {
        showLoading();
        loadItems();
    }
    
    // 绑定事件
    function bindEvents() {
        // 导航切换
        $('.tc-nav-link[data-tab]').on('click', function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            showTab(tab);
            updateNavigation($(this));
        });
        
        // 类别筛选
        $('.tc-category-filter').on('click', function(e) {
            e.preventDefault();
            currentCategory = $(this).data('category') || '';
            updateCategoryFilter($(this));
            filterItems();
        });
        
        // 视图切换
        $('.tc-view-btn').on('click', function() {
            const view = $(this).data('view');
            setView(view);
            $(this).addClass('active').siblings().removeClass('active');
        });
        
        // 搜索功能
        $('#tc-quick-search').on('input', debounce(function() {
            filterItems();
        }, 300));
        
        $('.tc-search-btn').on('click', function() {
            filterItems();
        });
        
        // 高级筛选
        $('#tc-status-filter, #tc-warranty-filter, #tc-date-from, #tc-date-to').on('change', function() {
            filterItems();
        });
        
        // 重置筛选器
        $('#tc-reset-filters').on('click', function() {
            resetFilters();
            filterItems();
        });
        
        // 添加物品按钮
        $('#tc-add-item-btn').on('click', function() {
            showTab('add');
            $('.tc-nav-link[data-tab="add"]').trigger('click');
        });
        
        // 导出功能
        $('#tc-export-btn').on('click', function() {
            exportItems();
        });
        
    }
    
    // 显示选项卡
    function showTab(tab) {
        currentTab = tab;
        $('.tc-tab-content').removeClass('active');
        $('#tc-tab-' + tab).addClass('active');
        
        // 根据选项卡加载相应内容
        switch(tab) {
            case 'overview':
                renderItemsOverview();
                break;
            case 'items':
                renderItemsList();
                break;
            case 'add':
                loadAddItemForm();
                break;
        }
    }
    
    // 更新导航状态
    function updateNavigation($activeLink) {
        $('.tc-nav-link[data-tab]').removeClass('active');
        $activeLink.addClass('active');
    }
    
    // 更新类别筛选状态
    function updateCategoryFilter($activeLink) {
        $('.tc-category-filter').removeClass('active');
        $activeLink.addClass('active');
    }
    
    // 设置视图模式
    function setView(view) {
        currentView = view;
        const $container = $('#tc-items-list');
        $container.removeClass('tc-view-grid tc-view-list').addClass('tc-view-' + view);

        // 如果是列表视图，强制水平排列字段
        if (view === 'list') {
            setTimeout(() => {
                forceHorizontalLayout();
            }, 100);
        }
    }
    
    // 加载物品数据
    function loadItems() {
        // 前端所有用户都只显示自己的物品
        $.ajax({
            url: timeCapsuleCustom.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tc_get_items',
                nonce: timeCapsuleCustom.nonce
            },
            success: function(response) {
                if (response.success) {
                    itemsData = response.data || [];
                    filteredItems = [...itemsData];
                    renderCurrentView();
                } else {
                    showNotice('加载数据失败', 'error');
                }
            },
            error: function() {
                showNotice('网络错误，请重试', 'error');
            },
            complete: function() {
                hideLoading();
            }
        });
    }
    
    // 筛选物品
    function filterItems() {
        const searchTerm = $('#tc-quick-search').val().toLowerCase();
        const statusFilter = $('#tc-status-filter').val();
        const warrantyFilter = $('#tc-warranty-filter').val();
        const dateFrom = $('#tc-date-from').val();
        const dateTo = $('#tc-date-to').val();
        
        filteredItems = itemsData.filter(item => {
            // 类别筛选
            if (currentCategory && item.category !== currentCategory) {
                return false;
            }
            
            // 搜索筛选
            if (searchTerm) {
                const searchFields = [
                    item.name,
                    item.brand,
                    item.description,
                    item.model
                ].join(' ').toLowerCase();
                
                if (!searchFields.includes(searchTerm)) {
                    return false;
                }
            }
            
            // 状态筛选
            if (statusFilter && item.status !== statusFilter) {
                return false;
            }
            
            // 保修状态筛选
            if (warrantyFilter) {
                const warrantyStatus = getWarrantyStatus(item);
                if (warrantyStatus !== warrantyFilter) {
                    return false;
                }
            }
            
            // 日期筛选
            if (dateFrom && item.purchase_date < dateFrom) {
                return false;
            }
            
            if (dateTo && item.purchase_date > dateTo) {
                return false;
            }
            
            return true;
        });
        
        renderCurrentView();
    }
    
    // 渲染当前视图
    function renderCurrentView() {
        switch(currentTab) {
            case 'overview':
                renderItemsOverview();
                break;
            case 'items':
                renderItemsList();
                break;
        }
    }
    
    // 渲染概览视图
    function renderItemsOverview() {
        const $container = $('#tc-items-overview');
        
        if (filteredItems.length === 0) {
            $container.html(getEmptyStateHTML());
            return;
        }
        
        const itemsToShow = filteredItems.slice(0, 12); // 限制显示数量
        let html = '';
        
        itemsToShow.forEach(item => {
            html += getItemCardHTML(item);
        });
        
        $container.html(html);
        bindItemEvents();
    }
    
    // 渲染物品列表
    function renderItemsList() {
        const $container = $('#tc-items-list');
        
        if (filteredItems.length === 0) {
            $container.html(getEmptyStateHTML());
            return;
        }
        
        let html = '';
        
        if (currentView === 'grid') {
            html = '<div class="tc-items-grid">';
            filteredItems.forEach(item => {
                html += getItemCardHTML(item);
            });
            html += '</div>';
        } else {
            html = '<div class="tc-items-table-container">';
            html += getItemsTableHTML(filteredItems);
            html += '</div>';
        }
        
        $container.html(html);
        bindItemEvents();
    }
    
    // 获取物品卡片HTML
    function getItemCardHTML(item) {
        const avatar = getItemAvatar(item.category);
        const warrantyStatus = getWarrantyStatus(item);
        const warrantyClass = getWarrantyClass(warrantyStatus);
        const statusClass = getStatusClass(item.status);
        const usageTime = calculateUsageTime(item.purchase_date);
        
        return `
            <div class="tc-item-card" data-item-id="${item.id}">
                <div class="tc-item-header">
                    <div class="tc-item-avatar">${avatar}</div>
                    <div class="tc-item-category">${getCategoryDisplayName(item.category)}</div>
                </div>
                <div class="tc-item-content">
                    <h3 class="tc-item-name">${escapeHtml(item.name)}</h3>
                    ${item.brand ? `<div class="tc-item-brand">${escapeHtml(item.brand)}</div>` : ''}
                    ${item.description ? `<div class="tc-item-description">${escapeHtml(item.description)}</div>` : ''}
                    <div class="tc-item-meta">
                        <div class="tc-meta-item">
                            <div class="tc-meta-label">购买时间</div>
                            <div class="tc-meta-value">${formatDate(item.purchase_date)}</div>
                        </div>
                        <div class="tc-meta-item">
                            <div class="tc-meta-label">使用时长</div>
                            <div class="tc-meta-value">${usageTime}</div>
                        </div>
                        ${item.warranty_period ? `
                        <div class="tc-meta-item">
                            <div class="tc-meta-label">保修状态</div>
                            <div class="tc-meta-value">
                                <span class="tc-warranty-status ${warrantyClass}">${getWarrantyStatusText(warrantyStatus)}</span>
                            </div>
                        </div>
                        ` : ''}
                        ${item.price ? `
                        <div class="tc-meta-item">
                            <div class="tc-meta-label">价格</div>
                            <div class="tc-meta-value">¥${parseFloat(item.price).toLocaleString()}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="tc-item-footer">
                    <span class="tc-item-status ${statusClass}">${getStatusText(item.status)}</span>
                </div>
            </div>
        `;
    }
    
    // 获取物品表格HTML
    function getItemsTableHTML(items) {
        let html = `
            <table class="tc-items-table">
                <thead>
                    <tr>
                        <th>物品信息</th>
                        <th>类别</th>
                        <th>购买时间</th>
                        <th>保修状态</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        items.forEach(item => {
            const avatar = getItemAvatar(item.category);
            const warrantyStatus = getWarrantyStatus(item);
            const warrantyClass = getWarrantyClass(warrantyStatus);
            const statusClass = getStatusClass(item.status);
            const usageTime = calculateUsageTime(item.purchase_date);
            
            html += `
                <tr data-item-id="${item.id}">
                    <td>
                        <div class="tc-item-info">
                            <div class="tc-item-avatar">${avatar}</div>
                            <div class="tc-item-details">
                                <strong>${escapeHtml(item.name)}</strong>
                                ${item.brand ? `<div class="tc-item-brand">${escapeHtml(item.brand)}</div>` : ''}
                                ${item.description ? `<div class="tc-item-description">${escapeHtml(item.description)}</div>` : ''}
                                <div class="tc-item-age">使用 ${usageTime}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="tc-category-badge">
                            ${avatar} ${getCategoryDisplayName(item.category)}
                        </div>
                    </td>
                    <td>${formatDate(item.purchase_date)}</td>
                    <td>
                        ${item.warranty_period ? `<span class="tc-warranty-status ${warrantyClass}">${getWarrantyStatusText(warrantyStatus)}</span>` : '无保修'}
                    </td>
                    <td>
                        <span class="tc-status-badge ${statusClass}">${getStatusText(item.status)}</span>
                    </td>
                    <td>
                        <div class="tc-actions-group">
                            <button class="button tc-edit-item-btn" data-item-id="${item.id}">编辑</button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        return html;
    }
    
    // 获取空状态HTML
    function getEmptyStateHTML() {
        return `
            <div class="tc-empty-state">
                <div class="tc-empty-icon">📦</div>
                <h3>暂无物品</h3>
                <p>还没有添加任何物品，点击"添加物品"开始记录吧！</p>
                <button class="tc-btn tc-btn-primary" id="tc-add-first-item">添加第一个物品</button>
            </div>
        `;
    }
    
    // 绑定物品事件
    function bindItemEvents() {
        // 编辑物品
        $('.tc-edit-item-btn').on('click', function() {
            const itemId = $(this).data('item-id');
            editItem(itemId);
        });

        // 添加第一个物品
        $('#tc-add-first-item').on('click', function() {
            showTab('add');
            $('.tc-nav-link[data-tab="add"]').trigger('click');
        });
    }

    // 强制水平布局
    function forceHorizontalLayout() {
        $('.tc-item-card').each(function() {
            const $card = $(this);
            const $metaContainer = $card.find('.tc-item-meta');
            const $metaRows = $metaContainer.find('.tc-meta-row');

            // 移除所有行的容器，让所有字段项直接成为meta容器的子元素
            $metaRows.each(function() {
                const $row = $(this);
                const $items = $row.find('.tc-meta-item');
                $items.detach().appendTo($metaContainer);
                $row.remove();
            });

            // 确保meta容器是水平布局
            $metaContainer.css({
                'display': 'flex',
                'flex-wrap': 'nowrap',
                'gap': '16px',
                'overflow-x': 'auto',
                'align-items': 'center',
                'justify-content': 'flex-start'
            });

            // 确保每个字段项是水平排列
            $metaContainer.find('.tc-meta-item').css({
                'display': 'flex',
                'flex-direction': 'row',
                'align-items': 'center',
                'gap': '4px',
                'white-space': 'nowrap',
                'flex-shrink': '0'
            });
        });
    }
    
    
    
    // 加载添加物品表单
    function loadAddItemForm() {
        const $container = $('#tc-add-item-form');
        // 这里可以加载表单内容，或者重用现有的表单
        $container.html('<p>添加物品表单将在这里显示...</p>');
    }
    
    // 编辑物品
    function editItem(itemId) {
        // 切换到添加选项卡并加载编辑表单
        showTab('add');
        $('.tc-nav-link[data-tab="add"]').trigger('click');
        // 加载编辑表单逻辑
    }
    
    // 导出物品
    function exportItems() {
        const data = {
            action: 'tc_export_data',
            format: 'csv',
            nonce: timeCapsuleCustom.nonce
        };
        
        const url = timeCapsuleCustom.ajaxUrl + '?' + $.param(data);
        window.open(url, '_blank');
    }
    
    // 重置筛选器
    function resetFilters() {
        $('#tc-quick-search').val('');
        $('#tc-status-filter').val('');
        $('#tc-warranty-filter').val('');
        $('#tc-date-from').val('');
        $('#tc-date-to').val('');
    }
    
    
    // 显示加载指示器
    function showLoading() {
        $('#tc-loading').show();
    }
    
    // 隐藏加载指示器
    function hideLoading() {
        $('#tc-loading').hide();
    }
    
    // 显示通知
    function showNotice(message, type = 'info') {
        // 简单的通知实现
        const $notice = $(`<div class="tc-notice tc-notice-${type}">${message}</div>`);
        $('body').append($notice);
        
        setTimeout(() => {
            $notice.fadeOut(() => $notice.remove());
        }, 3000);
    }
    
    // 工具函数
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('zh-CN');
    }
    
    function calculateUsageTime(purchaseDate) {
        const now = new Date();
        const purchase = new Date(purchaseDate);
        const diffTime = Math.abs(now - purchase);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 30) {
            return diffDays + ' 天';
        } else if (diffDays < 365) {
            return Math.floor(diffDays / 30) + ' 个月';
        } else {
            return Math.floor(diffDays / 365) + ' 年';
        }
    }
    
    function getItemAvatar(category) {
        const avatars = {
            'vehicle': '🚗',
            'electronics': '📱',
            'food': '🍿',
            'furniture': '🪑'
        };
        return avatars[category] || '📦';
    }
    
    function getCategoryDisplayName(category) {
        const names = {
            'vehicle': '交通工具',
            'electronics': '电子产品',
            'food': '零食食品',
            'furniture': '家具'
        };
        return names[category] || category;
    }
    
    function getStatusText(status) {
        const texts = {
            'active': '使用中',
            'inactive': '闲置',
            'disposed': '已处置'
        };
        return texts[status] || status;
    }
    
    function getStatusClass(status) {
        return 'status-' + status;
    }
    
    function getWarrantyStatus(item) {
        if (!item.warranty_period) return 'no_warranty';
        
        const purchaseDate = new Date(item.purchase_date);
        const warrantyEndDate = new Date(purchaseDate);
        warrantyEndDate.setMonth(warrantyEndDate.getMonth() + parseInt(item.warranty_period));
        
        const now = new Date();
        const daysUntilExpiry = Math.ceil((warrantyEndDate - now) / (1000 * 60 * 60 * 24));
        
        if (daysUntilExpiry < 0) return 'expired';
        if (daysUntilExpiry <= 30) return 'expiring';
        return 'valid';
    }
    
    function getWarrantyStatusText(status) {
        const texts = {
            'valid': '保修中',
            'expiring': '即将过保',
            'expired': '已过保',
            'no_warranty': '无保修'
        };
        return texts[status] || status;
    }
    
    function getWarrantyClass(status) {
        return 'warranty-' + status;
    }
    
    function getWarrantyEndDate(item) {
        if (!item.warranty_period) return '无保修';
        
        const purchaseDate = new Date(item.purchase_date);
        const warrantyEndDate = new Date(purchaseDate);
        warrantyEndDate.setMonth(warrantyEndDate.getMonth() + parseInt(item.warranty_period));
        
        return warrantyEndDate.toLocaleDateString('zh-CN');
    }
    
})(jQuery);

