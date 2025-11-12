/**
 * 物品管理前端JavaScript
 */

(function($) {
    'use strict';
    
    var TimeCapsule = {
        
        currentCategory: '',
        currentView: 'grid',
        currentPage: 1,
        loading: false,
        
        init: function() {
            this.bindEvents();
            this.initFilters();
            this.forceListHorizontalLayout();
        },
        
        bindEvents: function() {
            // 类别筛选
            $(document).on('click', '.tc-filter-tab', this.handleCategoryFilter);
            
            // 搜索
            $(document).on('input', '#tc-search', this.debounce(this.handleSearch, 300));
            $(document).on('click', '#tc-search-btn', this.handleSearch);
            
            // 视图切换
            $(document).on('click', '.tc-view-btn', this.handleViewToggle);
            
            
            // 加载更多
            $(document).on('click', '#tc-load-more', this.handleLoadMore);
            
            // 模态框关闭
            $(document).on('click', '.tc-modal-close', this.closeModal);
            
            // 点击模态框外部关闭
            $(document).on('click', '.tc-modal', function(e) {
                if (e.target === this) {
                    TimeCapsule.closeModal();
                }
            });
            
            // 键盘事件
            $(document).on('keydown', this.handleKeyboard);
        },
        
        initFilters: function() {
            this.updateItemsDisplay();
        },
        
        handleCategoryFilter: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var category = $tab.data('category');
            
            // 更新活动状态
            $('.tc-filter-tab').removeClass('active');
            $tab.addClass('active');
            
            // 更新当前类别
            TimeCapsule.currentCategory = category;
            TimeCapsule.currentPage = 1;
            
            // 更新显示
            TimeCapsule.updateItemsDisplay();
        },
        
        handleSearch: function(e) {
            if (e.type === 'click' || e.keyCode === 13) {
                e.preventDefault();
            }
            
            TimeCapsule.currentPage = 1;
            TimeCapsule.updateItemsDisplay();
        },
        
        handleViewToggle: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var view = $btn.data('view');
            
            // 更新按钮状态
            $('.tc-view-btn').removeClass('active');
            $btn.addClass('active');
            
            // 更新视图
            TimeCapsule.currentView = view;
            TimeCapsule.updateViewClass();
        },
        
        handleLoadMore: function(e) {
            e.preventDefault();
            
            if (TimeCapsule.loading) {
                return;
            }
            
            TimeCapsule.currentPage++;
            TimeCapsule.loadItems(true);
        },
        
        
        updateItemsDisplay: function() {
            var category = this.currentCategory;
            var search = $('#tc-search').val().toLowerCase();
            
            $('.tc-item-card').each(function() {
                var $card = $(this);
                var itemCategory = $card.data('category');
                var itemName = $card.find('.tc-item-name').text().toLowerCase();
                var itemDescription = $card.find('.tc-item-description').text().toLowerCase();
                var itemBrand = $card.find('.tc-item-brand').text().toLowerCase();
                
                var categoryMatch = !category || itemCategory === category;
                var searchMatch = !search || 
                    itemName.indexOf(search) !== -1 || 
                    itemDescription.indexOf(search) !== -1 || 
                    itemBrand.indexOf(search) !== -1;
                
                if (categoryMatch && searchMatch) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
            
            // 检查是否有可见的物品
            var visibleItems = $('.tc-item-card:visible').length;
            
            if (visibleItems === 0) {
                this.showEmptyState();
            } else {
                this.hideEmptyState();
            }
        },
        
        updateViewClass: function() {
            var $container = $('#tc-items-grid');
            $container.removeClass('tc-view-grid tc-view-list');
            $container.addClass('tc-view-' + this.currentView);

            // 如果是列表视图，强制水平排列字段
            if (this.currentView === 'list') {
                this.forceHorizontalLayout();
            }
        },

        forceHorizontalLayout: function() {
            $('.tc-item-card').each(function() {
                var $card = $(this);
                var $metaContainer = $card.find('.tc-item-meta');
                var $metaRows = $metaContainer.find('.tc-meta-row');

                // 移除所有行的容器，让所有字段项直接成为meta容器的子元素
                $metaRows.each(function() {
                    var $row = $(this);
                    var $items = $row.find('.tc-meta-item');
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
        },

        forceListHorizontalLayout: function() {
            // 检查是否是列表视图
            var $container = $('#tc-items-container');
            if ($container.length && $container.attr('data-view') === 'list') {
                $('.tc-item-card').each(function() {
                    var $card = $(this);
                    var $metaContainer = $card.find('.tc-item-meta');
                    var $metaRows = $metaContainer.find('.tc-meta-row');

                    // 移除所有行的容器，让所有字段项直接成为meta容器的子元素
                    $metaRows.each(function() {
                        var $row = $(this);
                        var $items = $row.find('.tc-meta-item');
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
        },
        
        loadItems: function(append) {
            if (this.loading) {
                return;
            }

            this.loading = true;
            var $loadBtn = $('#tc-load-more');
            var originalText = $loadBtn.text();
            $loadBtn.text('加载中...').prop('disabled', true);

            var data = {
                action: 'tc_get_items',
                nonce: time_capsule_ajax.nonce,
                category: this.currentCategory,
                search: $('#tc-search').val(),
                page: this.currentPage,
                limit: 12
            };

            $.post(time_capsule_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success && response.data) {
                        if (append) {
                            TimeCapsule.appendItems(response.data);
                        } else {
                            TimeCapsule.replaceItems(response.data);
                        }
                        
                        // 检查是否还有更多数据
                        if (response.data.length < data.limit) {
                            $loadBtn.hide();
                        } else {
                            $loadBtn.show();
                        }
                    }
                })
                .fail(function() {
                    TimeCapsule.showNotice('error', '加载失败，请重试。');
                })
                .always(function() {
                    TimeCapsule.loading = false;
                    $loadBtn.text(originalText).prop('disabled', false);
                });
        },
        
        appendItems: function(items) {
            var $grid = $('#tc-items-grid');
            
            items.forEach(function(item) {
                var $card = TimeCapsule.createItemCard(item);
                $grid.append($card);
            });
        },
        
        replaceItems: function(items) {
            var $grid = $('#tc-items-grid');
            $grid.empty();
            
            items.forEach(function(item) {
                var $card = TimeCapsule.createItemCard(item);
                $grid.append($card);
            });
        },
        
        createItemCard: function(item) {
            var html = '<div class="tc-item-card" data-category="' + item.category + '" data-item-id="' + item.id + '">';

            html += '<div class="tc-item-header">';
            html += '<div class="tc-item-avatar">' + this.getItemAvatar(item.category) + '</div>';
            html += '<div class="tc-item-category">' + this.escapeHtml(item.category_display_name) + '</div>';
            html += '</div>';

            html += '<div class="tc-item-content">';
            html += '<h4 class="tc-item-name">' + this.escapeHtml(item.name) + '</h4>';

            if (item.brand) {
                html += '<div class="tc-item-brand">' + this.escapeHtml(item.brand) + '</div>';
            }

            if (item.description) {
                html += '<div class="tc-item-description">' + this.escapeHtml(this.truncateText(item.description, 15)) + '</div>';
            }

            // 对于列表视图，我们使用不同的HTML结构
            if (this.currentView === 'list') {
                html += '<div class="tc-item-meta">';
                // 所有字段都在同一行，不分行
                html += '<div class="tc-meta-item">';
                html += '<span class="tc-meta-label">购买于</span>';
                html += '<span class="tc-meta-value">' + this.formatDate(item.purchase_date) + '</span>';
                html += '</div>';

                html += '<div class="tc-meta-item">';
                html += '<span class="tc-meta-label">已拥有</span>';
                html += '<span class="tc-meta-value">' + this.calculateAge(item.purchase_date) + '</span>';
                html += '</div>';

                if (item.warranty_period > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">保修</span>';
                    html += '<span class="tc-meta-value tc-warranty-status ' + this.getWarrantyStatusClass(item.warranty_status) + '">';
                    html += this.getWarrantyStatusText(item.warranty_status);
                    html += '</span>';
                    html += '</div>';
                }

                if (item.used_time_hours > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">使用时间</span>';
                    html += '<span class="tc-meta-value">' + this.formatUsedTime(item.used_time_hours) + '</span>';
                    html += '</div>';
                }

                if (item.category === 'vehicle' && item.total_mileage > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">总里程</span>';
                    html += '<span class="tc-meta-value">' + this.formatMileage(item.total_mileage) + '</span>';
                    html += '</div>';
                }

                if (item.price > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">价格</span>';
                    html += '<span class="tc-meta-value">¥' + parseFloat(item.price).toFixed(2) + '</span>';
                    html += '</div>';
                }
            } else {
                // 网格视图保持原来的结构
                html += '<div class="tc-item-meta">';
                html += '<div class="tc-meta-item">';
                html += '<span class="tc-meta-label">购买于</span>';
                html += '<span class="tc-meta-value">' + this.formatDate(item.purchase_date) + '</span>';
                html += '</div>';

                html += '<div class="tc-meta-item">';
                html += '<span class="tc-meta-label">已拥有</span>';
                html += '<span class="tc-meta-value">' + this.calculateAge(item.purchase_date) + '</span>';
                html += '</div>';

                if (item.warranty_period > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">保修</span>';
                    html += '<span class="tc-meta-value tc-warranty-status ' + this.getWarrantyStatusClass(item.warranty_status) + '">';
                    html += this.getWarrantyStatusText(item.warranty_status);
                    html += '</span>';
                    html += '</div>';
                }

                if (item.used_time_hours > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">使用时间</span>';
                    html += '<span class="tc-meta-value">' + this.formatUsedTime(item.used_time_hours) + '</span>';
                    html += '</div>';
                }

                if (item.category === 'vehicle' && item.total_mileage > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">总里程</span>';
                    html += '<span class="tc-meta-value">' + this.formatMileage(item.total_mileage) + '</span>';
                    html += '</div>';
                }

                if (item.price > 0) {
                    html += '<div class="tc-meta-item">';
                    html += '<span class="tc-meta-label">价格</span>';
                    html += '<span class="tc-meta-value">¥' + parseFloat(item.price).toFixed(2) + '</span>';
                    html += '</div>';
                }
            }

            html += '</div>'; // tc-item-meta
            html += '</div>'; // tc-item-content

            html += '<div class="tc-item-footer">';
            html += '<div class="tc-item-status ' + this.getStatusClass(item.status) + '">';
            html += this.getStatusText(item.status);
            html += '</div>';
            html += '</div>';

            html += '</div>';

            var $card = $(html);

            // 如果是列表视图，立即应用水平布局
            if (this.currentView === 'list') {
                this.applyHorizontalLayout($card);
            }

            return $card;
        },

        applyHorizontalLayout: function($card) {
            var $metaContainer = $card.find('.tc-item-meta');

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
        },
        
        
        showEmptyState: function() {
            var $grid = $('#tc-items-grid');
            if ($grid.find('.tc-empty-state').length === 0) {
                var html = '<div class="tc-empty-state">';
                html += '<div class="tc-empty-icon">🔍</div>';
                html += '<h3>没有找到匹配的物品</h3>';
                html += '<p>尝试调整筛选条件或搜索关键词</p>';
                html += '</div>';
                $grid.append(html);
            }
        },
        
        hideEmptyState: function() {
            $('#tc-items-grid .tc-empty-state').remove();
        },
        
        showNotice: function(type, message) {
            var $notice = $('<div class="tc-notice tc-notice-' + type + '">' + message + '</div>');
            $('.time-capsule-frontend').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },
        
        // 辅助方法
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        truncateText: function(text, wordLimit) {
            var words = text.split(' ');
            if (words.length > wordLimit) {
                return words.slice(0, wordLimit).join(' ') + '...';
            }
            return text;
        },
        
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.getFullYear() + '年' + (date.getMonth() + 1) + '月';
        },

        formatFullDate: function(dateString) {
            var date = new Date(dateString);
            return date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日';
        },
        
        calculateAge: function(purchaseDate) {
            var purchase = new Date(purchaseDate);
            var now = new Date();
            var diff = now - purchase;
            
            var years = Math.floor(diff / (365 * 24 * 60 * 60 * 1000));
            var months = Math.floor((diff % (365 * 24 * 60 * 60 * 1000)) / (30 * 24 * 60 * 60 * 1000));
            var days = Math.floor((diff % (30 * 24 * 60 * 60 * 1000)) / (24 * 60 * 60 * 1000));
            
            if (years > 0) {
                return months > 0 ? years + ' 年 ' + months + ' 个月' : years + ' 年';
            } else if (months > 0) {
                return days > 0 ? months + ' 个月 ' + days + ' 天' : months + ' 个月';
            } else {
                return days + ' 天';
            }
        },
        
        getItemAvatar: function(category) {
            var avatars = {
                'vehicle': '🚗',
                'electronics': '📱',
                'food': '🍿',
                'furniture': '🪑'
            };
            return avatars[category] || '📦';
        },
        
        getStatusText: function(status) {
            var statusTexts = {
                'active': '使用中',
                'inactive': '闲置',
                'disposed': '已处置'
            };
            return statusTexts[status] || '未知';
        },
        
        getStatusClass: function(status) {
            return 'status-' + status;
        },
        
        getWarrantyStatusText: function(status) {
            var statusTexts = {
                'valid': '保修中',
                'expiring': '即将过保',
                'expired': '已过保',
                'no_warranty': '无保修'
            };
            return statusTexts[status] || '未知';
        },
        
        getWarrantyStatusClass: function(status) {
            return 'warranty-' + status;
        },
        
        formatUsedTime: function(hours) {
            if (hours < 24) {
                return hours + ' 小时';
            } else if (hours < 24 * 30) {
                var days = Math.floor(hours / 24);
                var remainingHours = hours % 24;
                return remainingHours > 0 ? days + ' 天 ' + remainingHours + ' 小时' : days + ' 天';
            } else {
                var days = Math.floor(hours / 24);
                var months = Math.floor(days / 30);
                var remainingDays = days % 30;
                return remainingDays > 0 ? months + ' 个月 ' + remainingDays + ' 天' : months + ' 个月';
            }
        },
        
        formatMileage: function(mileage) {
            if (mileage < 1000) {
                return parseFloat(mileage).toFixed(1) + ' 公里';
            } else {
                return (parseFloat(mileage) / 10000).toFixed(1) + ' 万公里';
            }
        }
    };
    
    // 初始化
    $(document).ready(function() {
        if ($('.time-capsule-frontend').length > 0) {
            TimeCapsule.init();
        }
    });
    
})(jQuery);

