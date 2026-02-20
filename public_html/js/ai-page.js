/**
 * Intebwio - AI Page JavaScript
 * Enhanced interactivity for AI-generated pages
 * ~300 lines
 */

class AIPageEnhancer {
    constructor() {
        this.pageQuery = new URLSearchParams(window.location.search).get('search');
        this.init();
    }

    init() {
        this.setupTableOfContents();
        this.setupSmoothScroll();
        this.setupReadingProgress();
        this.setupArticleHighlighting();
        this.setupCopyButtons();
        this.setupFontSize();
        this.setupDarkMode();
        this.trackReading();
        this.loadComments();
    }

    /**
     * Setup Table of Contents scrolling
     */
    setupTableOfContents() {
        const tocLinks = document.querySelectorAll('.table-of-contents a');
        
        tocLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const id = link.getAttribute('href').substring(1);
                const target = document.getElementById(id);
                
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    this.highlightHeading(target);
                }
            });
        });
    }

    /**
     * Setup smooth scrolling
     */
    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const href = anchor.getAttribute('href');
                if (href !== '#') {
                    e.preventDefault();
                    const element = document.querySelector(href);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });
    }

    /**
     * Setup reading progress bar
     */
    setupReadingProgress() {
        const progressBar = document.createElement('div');
        progressBar.className = 'reading-progress';
        progressBar.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899);
            z-index: 9999;
            transition: width 0.1s ease;
        `;
        document.body.appendChild(progressBar);

        window.addEventListener('scroll', () => {
            const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrolled = (window.scrollY / totalHeight) * 100;
            progressBar.style.width = scrolled + '%';
        });
    }

    /**
     * Setup article highlighting
     */
    setupArticleHighlighting() {
        const article = document.querySelector('.ai-content-article');
        if (!article) return;

        article.addEventListener('mouseup', () => {
            const selectedText = window.getSelection().toString();
            if (selectedText.length > 0) {
                this.showHighlightOptions(selectedText);
            }
        });
    }

    /**
     * Show highlight options
     */
    showHighlightOptions(text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'highlight-tooltip';
        tooltip.style.cssText = `
            position: fixed;
            top: ${window.mouseY}px;
            left: ${window.mouseX}px;
            background: #1e1b4b;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 10000;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        
        tooltip.textContent = '✓ Highlighted';
        document.body.appendChild(tooltip);

        // Store highlight
        const highlights = JSON.parse(localStorage.getItem('page_highlights') || '{}');
        highlights[this.pageQuery] = (highlights[this.pageQuery] || []);
        highlights[this.pageQuery].push(text);
        localStorage.setItem('page_highlights', JSON.stringify(highlights));

        setTimeout(() => tooltip.remove(), 2000);
    }

    /**
     * Setup copy buttons for code blocks
     */
    setupCopyButtons() {
        document.querySelectorAll('.ai-content-article pre').forEach(pre => {
            const button = document.createElement('button');
            button.textContent = 'Copy';
            button.style.cssText = `
                position: absolute;
                top: 5px;
                right: 5px;
                padding: 5px 10px;
                background: #6366f1;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
            `;
            
            pre.style.position = 'relative';
            pre.appendChild(button);

            button.addEventListener('click', () => {
                const code = pre.textContent;
                navigator.clipboard.writeText(code).then(() => {
                    button.textContent = 'Copied!';
                    setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 1500);
                });
            });
        });
    }

    /**
     * Setup font size adjustment
     */
    setupFontSize() {
        const container = document.querySelector('.ai-content-article');
        if (!container) return;

        const controls = document.createElement('div');
        controls.className = 'font-controls';
        controls.style.cssText = `
            position: fixed;
            right: 20px;
            bottom: 100px;
            background: white;
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            padding: 8px;
            z-index: 1000;
            display: flex;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        `;

        const buttons = [
            { symbol: 'A−', delta: -2 },
            { symbol: 'A+', delta: 2 }
        ];

        let currentSize = 16;

        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.textContent = btn.symbol;
            button.style.cssText = `
                padding: 6px 10px;
                background: none;
                border: 1px solid #e0e7ff;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
                color: #6366f1;
            `;
            
            button.addEventListener('click', () => {
                currentSize += btn.delta;
                currentSize = Math.max(12, Math.min(24, currentSize));
                container.style.fontSize = currentSize + 'px';
                localStorage.setItem('article_font_size', currentSize);
            });

            controls.appendChild(button);
        });

        document.body.appendChild(controls);

        // Restore saved font size
        const saved = localStorage.getItem('article_font_size');
        if (saved) {
            container.style.fontSize = saved + 'px';
        }
    }

    /**
     * Setup dark mode toggle
     */
    setupDarkMode() {
        const button = document.createElement('button');
        button.className = 'dark-mode-toggle';
        button.textContent = '🌙';
        button.style.cssText = `
            position: fixed;
            right: 20px;
            bottom: 140px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: white;
            border: 1px solid #e0e7ff;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        `;

        button.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            button.textContent = isDark ? '☀️' : '🌙';
            localStorage.setItem('dark_mode', isDark);

            if (isDark) {
                document.body.style.background = '#1e1b4b';
                document.body.style.color = '#e0e7ff';
            } else {
                document.body.style.background = 'white';
                document.body.style.color = '#3f3f46';
            }
        });

        document.body.appendChild(button);

        // Restore dark mode preference
        if (localStorage.getItem('dark_mode') === 'true') {
            button.click();
        }
    }

    /**
     * Track reading progress
     */
    trackReading() {
        const startTime = Date.now();
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY;
            if (scrolled > lastScroll + 500) { // Update every 500px
                lastScroll = scrolled;
                
                fetch('/api/track.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reading_progress',
                        page_scroll: scrolled,
                        page_height: document.documentElement.scrollHeight
                    })
                }).catch(e => console.log('Tracking error:', e));
            }
        });

        // Track time on page
        window.addEventListener('beforeunload', () => {
            const timeSpent = Math.round((Date.now() - startTime) / 1000);
            if (timeSpent > 5) { // Only record if spent more than 5 seconds
                navigator.sendBeacon('/api/track.php', JSON.stringify({
                    action: 'page_read',
                    time_spent: timeSpent,
                    query: this.pageQuery
                }));
            }
        });
    }

    /**
     * Load comments/discussions
     */
    loadComments() {
        fetch('/api/comments.php?query=' + encodeURIComponent(this.pageQuery))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.comments) {
                    this.displayComments(data.comments);
                }
            })
            .catch(e => console.log('Comments error:', e));
    }

    /**
     * Display comments section
     */
    displayComments(comments) {
        const section = document.createElement('section');
        section.className = 'comments-section';
        section.style.cssText = `
            grid-column: 1;
            margin-top: 60px;
            padding: 40px;
            background: #f5f3ff;
            border-radius: 12px;
        `;

        section.innerHTML = '<h2 style="margin-top: 0;">Discussion</h2>';
        
        if (comments.length === 0) {
            section.innerHTML += '<p>Be the first to discuss this topic.</p>';
        } else {
            comments.forEach(comment => {
                section.innerHTML += `
                    <div style="background: white; padding: 16px; margin-bottom: 12px; border-radius: 8px;">
                        <strong>${comment.author}</strong>
                        <p>${comment.text}</p>
                    </div>
                `;
            });
        }

        const article = document.querySelector('.ai-content-article');
        if (article) {
            article.parentNode.insertBefore(section, article.nextSibling);
        }
    }

    /**
     * Highlight heading on scroll
     */
    highlightHeading(element) {
        element.style.animation = 'pulse 0.5s ease';
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    new AIPageEnhancer();
});
