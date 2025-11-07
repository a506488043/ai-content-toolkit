jQuery(document).ready(function ($) {
    // 调试日志函数
    function logMessage(message, level = 'info') {
        // 仅在开发模式下输出日志
        if (window.console && console.log && (typeof chf_card_ajax !== 'undefined' && chf_card_ajax.debug_mode)) {
            console.log('[Custom Card] ' + level.toUpperCase() + ': ' + message);
        }
    }
    
    logMessage('Custom Card script initialized', 'info');
    
    // 检查必要的全局变量
    if (typeof chf_card_ajax === 'undefined') {
        logMessage('chf_card_ajax is not defined', 'error');
        return;
    }
    
    logMessage('AJAX URL: ' + chf_card_ajax.ajax_url, 'info');
    logMessage('Plugin URL: ' + chf_card_ajax.plugin_url, 'info');

    // 使用Intersection Observer实现懒加载
    if ('IntersectionObserver' in window) {
        logMessage('Using Intersection Observer for lazy loading', 'info');
        
        // 创建观察器实例
        const cardObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                // 当卡片进入视口时
                if (entry.isIntersecting) {
                    const card = $(entry.target);
                    logMessage('Card is intersecting: ' + card.data('url'), 'info');
                    // 加载卡片内容
                    loadCardContent(card);
                    // 停止观察这个卡片
                    observer.unobserve(entry.target);
                }
            });
        }, {
            // 设置卡片进入视口20%时触发加载
            threshold: 0.2,
            // 提前200px加载，提升用户体验
            rootMargin: '0px 0px 200px 0px'
        });

        // 为所有懒加载卡片添加观察
        const $cards = $('.strict-card[data-url]');
        logMessage('Found ' + $cards.length + ' cards to observe', 'info');
        
        $cards.each(function() {
            const card = $(this);
            const url = card.data('url');
            logMessage('Setting up observer for: ' + url, 'info');
            
            // 添加占位内容
            card.html('<div class="custom-card-placeholder">网站卡片将在滚动到此处时加载...</div>');
            // 添加到观察列表
            cardObserver.observe(this);
        });
    } else {
        logMessage('IntersectionObserver not supported, using scroll fallback', 'info');
        
        // 降级处理：不支持IntersectionObserver的浏览器
        // 使用传统的滚动事件实现懒加载
        $('.strict-card[data-url]').each(function() {
            $(this).html('<div class="custom-card-placeholder">网站卡片将在滚动到此处时加载...</div>');
        });

        // 滚动事件处理函数
        const handleScroll = debounce(function() {
            $('.strict-card[data-url]').each(function() {
                const card = $(this);
                // 如果卡片已经加载过或正在加载中，跳过
                if (card.data('loaded') || card.data('loading')) {
                    return;
                }
                
                // 检查卡片是否在视口中
                if (isElementInViewport(this)) {
                    loadCardContent(card);
                }
            });
        }, 200);

        // 绑定滚动事件
        $(window).on('scroll', handleScroll);
        // 初始触发一次，处理首屏内容
        setTimeout(handleScroll, 500);
        
        // 页面卸载时清理事件监听器
        $(window).on('beforeunload', function() {
            $(window).off('scroll', handleScroll);
        });
    }

    /**
     * 加载卡片内容
     * @param {jQuery} card 卡片jQuery对象
     */
    function loadCardContent(card) {
        logMessage('Loading card content', 'info');
        
        // 标记卡片正在加载中
        card.data('loading', true);
        
        var url = card.data('url'); // 获取 data-url 属性
        // 如果jQuery data()获取不到，尝试使用attr()
        if (!url) {
            url = card.attr('data-url');
        }

        logMessage('Card URL: ' + url, 'info');

        // 检查 URL 是否存在且非空
        if (!url || url.trim() === '') {
            logMessage('URL is empty or invalid', 'error');
            card.html('<div class="custom-card-error">无效URL: 缺少URL参数</div>');
            card.data('loaded', true);
            return; // 跳过该卡片的处理
        }

        // 发起 AJAX 请求
        logMessage('Sending AJAX request', 'info');
        $.ajax({
            url: chf_card_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'wordpress_toolkit_load_custom_card',
                nonce: chf_card_ajax.nonce, // 使用服务器提供的nonce
                url: url,
            },
            beforeSend: function () {
                logMessage('AJAX request beforeSend', 'info');
                card.html('<div class="custom-card-loading">加载中...</div>');
            },
            success: function (response) {
                logMessage('AJAX request successful', 'info');
                logMessage('Response: ' + JSON.stringify(response), 'info');
                
                // 验证响应格式
                if (!response || typeof response !== 'object') {
                    logMessage('Invalid response format', 'error');
                    card.html('<div class="custom-card-error">服务器响应格式错误</div>');
                    return;
                }
                
                if (response.success && response.data && response.data.html) {
                    logMessage('Card loaded successfully', 'success');
                    // 安全插入HTML (jQuery已处理XSS)
                    card.html(response.data.html);
                } else {
                    // 显示错误信息
                    var errorMsg = (response.data && response.data.message) 
                        ? response.data.message 
                        : '加载卡片时出错';
                    
                    logMessage('Error in AJAX response: ' + errorMsg, 'error');
                    card.html('<div class="custom-card-error">' + 
                        $('<div>').text(errorMsg).html() + // 转义HTML
                        '</div>');
                }
            },
            error: function (xhr, status, error) {
                logMessage('AJAX request failed: ' + status + ' - ' + error, 'error');
                logMessage('XHR status: ' + xhr.status, 'error');
                
                // 详细的错误分类处理
                var errorMessage = '网络请求失败';
                var errorType = 'network';
                
                if (xhr.status === 0) {
                    errorMessage = '网络连接失败，请检查网络设置';
                    errorType = 'connection';
                } else if (xhr.status === 403) {
                    errorMessage = '访问被拒绝，可能是权限问题';
                    errorType = 'permission';
                } else if (xhr.status === 404) {
                    errorMessage = '请求的资源不存在';
                    errorType = 'not_found';
                } else if (xhr.status === 500) {
                    errorMessage = '服务器内部错误，请稍后重试';
                    errorType = 'server_error';
                } else if (xhr.status === 503) {
                    errorMessage = '服务暂时不可用，请稍后重试';
                    errorType = 'service_unavailable';
                } else if (status === 'timeout') {
                    errorMessage = '请求超时，请检查网络连接';
                    errorType = 'timeout';
                } else if (status === 'abort') {
                    errorMessage = '请求被取消';
                    errorType = 'aborted';
                } else if (xhr.status >= 400 && xhr.status < 500) {
                    errorMessage = '客户端错误：' + xhr.status;
                    errorType = 'client_error';
                } else if (xhr.status >= 500) {
                    errorMessage = '服务器错误：' + xhr.status;
                    errorType = 'server_error';
                }
                
                // 创建详细的错误显示
                var errorHtml = '<div class="custom-card-error" data-error-type="' + errorType + '">' +
                    '<div class="error-icon">⚠️</div>' +
                    '<div class="error-message">' + errorMessage + '</div>' +
                    '<div class="error-suggestion">';
                
                // 根据错误类型提供不同的建议
                if (errorType === 'connection' || errorType === 'timeout') {
                    errorHtml += '请检查您的网络连接，或稍后重试';
                } else if (errorType === 'server_error') {
                    errorHtml += '服务器暂时出现问题，请稍后重试';
                } else if (errorType === 'permission') {
                    errorHtml += '请联系网站管理员检查权限设置';
                } else {
                    errorHtml += '请刷新页面重试';
                }
                
                errorHtml += '</div></div>';
                
                card.html(errorHtml);
                
                // 触发错误事件，便于其他脚本监听
                $(document).trigger('custom_card_ajax_error', {
                    url: url,
                    status: xhr.status,
                    errorType: errorType,
                    errorMessage: errorMessage
                });
            },
            complete: function() {
                logMessage('AJAX request completed', 'info');
                // 标记卡片已加载完成
                card.data('loading', false);
                card.data('loaded', true);
            }
        });
    }

    /**
     * 检查元素是否在视口中
     * @param {Element} el DOM元素
     * @return {boolean} 是否在视口中
     */
    function isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top <= (window.innerHeight || document.documentElement.clientHeight) + 200 &&
            rect.bottom >= 0 &&
            rect.left <= (window.innerWidth || document.documentElement.clientWidth) &&
            rect.right >= 0
        );
    }

    /**
     * 防抖函数
     * @param {Function} func 要执行的函数
     * @param {number} wait 等待时间
     * @return {Function} 防抖处理后的函数
     */
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                func.apply(context, args);
            }, wait);
        };
    }

    // 点击统计功能
    $(document).on('click', '.strict-card[data-card-id]', function(e) {
        var card = $(this);
        var cardId = card.data('card-id');
        
        // 如果卡片ID存在，记录点击
        if (cardId && cardId > 0) {
            logMessage('Recording click for card ID: ' + cardId, 'info');
            
            // 发送AJAX请求记录点击
            $.ajax({
                url: chf_card_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'wordpress_toolkit_record_card_click',
                    nonce: chf_card_ajax.nonce,
                    card_id: cardId
                },
                success: function(response) {
                    if (response.success) {
                        logMessage('Click recorded successfully for card ID: ' + cardId, 'success');
                    } else {
                        logMessage('Failed to record click: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    logMessage('Error recording click: ' + status + ' - ' + error, 'error');
                }
            });
        } else {
            logMessage('No card ID found for click tracking', 'warning');
        }
        
        // 允许链接正常跳转
        // 不阻止事件冒泡，让链接正常工作
    });
});
