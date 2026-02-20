/**
 * Intebwio - Enhanced JavaScript
 * Advanced features and optimizations
 */

class IntebwioAdvanced {
    constructor() {
        this.apiUrl = '/api/';
        this.cache = new Map();
        this.searchHistory = this.loadHistory();
        this.init();
    }

    init() {
        this.setupAdvancedEventListeners();
        this.setupOfflineSupport();
        this.loadTrendingTopics();
    }

    setupAdvancedEventListeners() {
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', () => this.toggleDarkMode());
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Alt + H for history
            if (e.altKey && e.key === 'h') {
                this.showSearchHistory();
            }
            // Alt + T for trending
            if (e.altKey && e.key === 't') {
                this.showTrendingTopics();
            }
        });

        // Save search on page creation
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('change', () => {
                this.saveSearchToHistory(searchInput.value);
            });
        }
    }

    loadHistory() {
        const history = localStorage.getItem('intebwio_history');
        return history ? JSON.parse(history) : [];
    }

    saveSearchToHistory(query) {
        if (!this.searchHistory.includes(query)) {
            this.searchHistory.unshift(query);
            if (this.searchHistory.length > 20) {
                this.searchHistory.pop();
            }
            localStorage.setItem('intebwio_history', JSON.stringify(this.searchHistory));
            
            // Also save to server for analytics
            fetch(this.apiUrl + 'history.php?action=save_history', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: query })
            }).catch(e => console.log('History save error:', e));
        }
    }

    showSearchHistory() {
        alert('Recent Searches:\n\n' + this.searchHistory.slice(0, 10).join('\n'));
    }

    loadTrendingTopics() {
        fetch(this.apiUrl + 'analytics.php?action=trending')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    // Update trending section if it exists
                    const trendingSection = document.getElementById('trending');
                    if (trendingSection) {
                        trendingSection.innerHTML = data.data.map(item => 
                            `<span onclick="searchFor('${item.search_query}')" style="cursor:pointer;">${item.search_query} (${item.search_count})</span>`
                        ).join(' • ');
                    }
                }
            })
            .catch(e => console.log('Trending load error:', e));
    }

    toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('intebwio_darkMode', 
            document.body.classList.contains('dark-mode'));
    }

    setupOfflineSupport() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.log('Service Worker registration failed:', err));
        }

        // Handle offline events
        window.addEventListener('offline', () => {
            this.showNotification('You are offline. Cached pages are still available.', 'info');
        });

        window.addEventListener('online', () => {
            this.showNotification('You are back online!', 'success');
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        const styles = {
            position: 'fixed',
            bottom: '20px',
            right: '20px',
            padding: '16px',
            borderRadius: '8px',
            color: 'white',
            zIndex: '9999',
            animation: 'slideUp 0.3s ease'
        };

        const typeStyles = {
            success: { backgroundColor: '#10b981' },
            error: { backgroundColor: '#ef4444' },
            info: { backgroundColor: '#2563eb' }
        };

        Object.assign(notification.style, styles, typeStyles[type]);
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 3000);
    }

    searchFor(query) {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = query;
            searchInput.form?.submit();
        }
    }
}

// Global search function
window.searchFor = function(query) {
    const intebwio = window.intebwio || new IntebwioAdvanced();
    intebwio.searchFor(query);
};

// Initialize advanced features
document.addEventListener('DOMContentLoaded', () => {
    if (!window.intebwio) {
        window.intebwioAdvanced = new IntebwioAdvanced();
    }
});
