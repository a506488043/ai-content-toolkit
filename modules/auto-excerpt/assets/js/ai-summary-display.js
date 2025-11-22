/* AI摘要显示JavaScript - 打字机效果 */
(function($) {
    'use strict';

    class AISummaryDisplay {
        constructor() {
            this.init();
        }

        init() {
            console.log('AI Summary Display: Initializing...');
            this.bindEvents();
        }

        bindEvents() {
            // 页面加载完成后初始化打字机效果
            $(document).ready(() => {
                console.log('AI Summary Display: Document ready, initializing...');
                this.initializeTypewriterEffects();
            });

            // 使用MutationObserver监听DOM变化（现代浏览器支持）
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === 1 && $(node).hasClass('ai-summary-container')) {
                                console.log('AI Summary Display: New AI summary container detected');
                                this.initializeTypewriterEffects();
                            }
                        });
                    });
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }

            // 备用方案：延迟检查以确保所有元素都已加载
            setTimeout(() => {
                console.log('AI Summary Display: Delayed initialization check');
                this.initializeTypewriterEffects();
            }, 1000);
        }

        initializeTypewriterEffects() {
            console.log('AI Summary Display: Looking for AI summary containers...');
            const containers = $('.ai-summary-container');
            console.log('Found', containers.length, 'AI summary containers');

            if (containers.length === 0) {
                console.log('No AI summary containers found on this page');
                return;
            }

            containers.each((index, container) => {
                const $container = $(container);
                const $content = $container.find('.ai-summary-text');

                // 检查元素是否存在
                if ($content.length === 0) {
                    console.log('Container', index, 'has no .ai-summary-text element');
                    return;
                }

                // 获取原始文本并清理
                const originalText = $content.text();
                // 彻底清理文本：移除开头空格、移除所有换行符和多余空格、trim
                const text = originalText
                    .replace(/^\s+/, '') // 移除开头空格
                    .replace(/\n/g, '')   // 移除所有换行符
                    .replace(/\s+/g, ' ') // 将多个空格替换为单个空格
                    .trim();              // 移除开头和结尾空格

                console.log('Container', index, 'original text:', originalText);
                console.log('Container', index, 'cleaned text:', text);
                console.log('Container', index, 'text length:', text.length);

                if (text && !$container.hasClass('typewriter-initialized')) {
                    console.log('Starting typewriter effect for container', index);
                    this.startTypewriterEffect($container, $content, text);
                    $container.addClass('typewriter-initialized');
                } else if (!$container.hasClass('typewriter-initialized')) {
                    console.log('No text found or already initialized for container', index);
                }
            });
        }

        startTypewriterEffect($container, $content, text) {
            console.log('Starting typewriter effect with text:', text);
            console.log('Text length:', text.length);

            // 如果文本为空或太短，直接显示
            if (!text || text.length < 5) {
                console.log('Text too short, showing directly');
                $content.addClass('visible');
                return;
            }

            // 保存原始文本用于调试
            $content.data('original-text', text);

            // 清空内容
            $content.text('');
            $content.addClass('ai-summary-typing');

            let charIndex = 0;
            const speed = 50; // 增加打字速度（毫秒），让效果更明显
            const cursorHtml = '<span class="typed-cursor">丨</span>';

            // 添加光标到内容内部
            $content.append(cursorHtml);

            const typeWriter = () => {
                if (charIndex < text.length) {
                    // 移除光标
                    $content.find('.typed-cursor').remove();

                    // 添加新字符
                    const currentText = $content.text() + text.charAt(charIndex);
                    $content.text(currentText);

                    // 重新添加光标到文本末尾
                    $content.append(cursorHtml);

                    charIndex++;

                    // 每10个字符记录一次进度
                    if (charIndex % 10 === 0) {
                        console.log('Typing progress:', charIndex + '/' + text.length);
                    }

                    setTimeout(typeWriter, speed);
                } else {
                    // 打字完成，移除光标和打字样式
                    $content.removeClass('ai-summary-typing');
                    $content.find('.typed-cursor').remove();
                    $content.addClass('visible');

                    console.log('Typewriter effect completed - Final text:', $content.text());
                    console.log('Final text length:', $content.text().length);
                    // 触发完成事件
                    $container.trigger('aiSummaryTypingComplete');
                }
            };

            // 延迟开始打字效果，让用户有时间注意到
            setTimeout(typeWriter, 1000);
        }

        // 手动触发打字机效果（可用于动态内容）
        triggerTypewriter($container) {
            const $content = $container.find('.ai-summary-text');
            const text = $content.data('original-text') || $content.text().trim();

            if (text) {
                $content.data('original-text', text);
                this.startTypewriterEffect($container, $content, text);
            }
        }

        // 重新开始打字效果
        restartTypewriter($container) {
            $container.removeClass('typewriter-initialized');
            this.triggerTypewriter($container);
        }
    }

    // 初始化
    $(document).ready(() => {
        window.aiSummaryDisplay = new AISummaryDisplay();
    });

})(jQuery);