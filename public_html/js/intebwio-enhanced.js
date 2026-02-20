/**
 * Intebwio - Main Enhanced JavaScript
 * Complete browser functionality with AI integration
 * ~250 lines
 */

class IntebwioEnhanced {
    constructor() {
        this.apiUrl = '/api/';
        this.searchQueryParam = new URLSearchParams(window.location.search).get('search');
        this.cache = new Map();
        this.searchHistory = this.loadHistory();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupSearch();
        this.setupAutoComplete();
        this.loadRecentPages();
        this.setupKeyboardShortcuts();
        this.monitorPerformance();
    }

    setupEventListeners() {
        // Search form
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const query = document.getElementById('searchInput').value.trim();
                if (query) {
                    this.performAISearch(query);
                }
            });
        }

        // Navigation search
        const navForm = document.getElementById('navSearchForm');
        if (navForm) {
            navForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const query = document.getElementById('navSearchInput').value.trim();
                if (query) {
                    this.performAISearch(query);
                }
            });
        }

        // Related topics
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('topic-chip') || e.target.classList.contains('tag') || e.target.classList.contains('topic-link')) {
                const query = e.target.textContent.trim().replace(/[^a-zA-Z0-9\s-]/g, '');
                this.performAISearch(query);
            }
        });
    }

    setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('focus', () => {
                this.showSearchSuggestions();
            });

            searchInput.addEventListener('input', (e) => {
                this.updateSearchSuggestions(e.target.value);
            });
        }
    }

    setupAutoComplete() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                const suggestions = document.querySelectorAll('.suggestion-item');
                if (suggestions.length > 0) {
                    suggestions[0].focus();
                }
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const suggestionsDiv = document.getElementById('suggestions');
                if (suggestionsDiv) {
                    suggestionsDiv.classList.remove('visible');
                }
            }
        });
    }

    showSearchSuggestions() {
        const history = this.searchHistory.slice(0, 5);
        this.displaySuggestions(history, 'Recent Searches');
    }

    updateSearchSuggestions(query) {
        if (query.length < 2) {
            this.showSearchSuggestions();
            return;
        }

        fetch(this.apiUrl + 'autocomplete.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                if (data.suggestions && data.suggestions.length > 0) {
                    this.displaySuggestions(data.suggestions, 'Suggestions');
                }
            })
            .catch(error => console.error('Autocomplete error:', error));
    }

    displaySuggestions(suggestions, label) {
        const suggestionsDiv = document.getElementById('suggestions');
        if (!suggestionsDiv) return;

        suggestionsDiv.innerHTML = '';

        if (suggestions.length === 0) {
            suggestionsDiv.classList.remove('visible');
            return;
        }

        suggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.innerHTML = `<span class="suggestion-icon">🔍</span> ${suggestion}`;
            item.tabIndex = 0;

            item.addEventListener('click', () => {
                document.getElementById('searchInput').value = suggestion;
                this.performAISearch(suggestion);
            });

            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    this.performAISearch(suggestion);
                } else if (e.key === 'ArrowDown' && index < suggestions.length - 1) {
                    suggestionsDiv.children[index + 1].focus();
                } else if (e.key === 'ArrowUp' && index > 0) {
                    suggestionsDiv.children[index - 1].focus();
                }
            });

            suggestionsDiv.appendChild(item);
        });

        suggestionsDiv.classList.add('visible');
    }

    performAISearch(query) {
        if (!query || query.length === 0) return;

        this.showLoading();
        this.saveToHistory(query);

        // Use AI search API
        fetch(this.apiUrl + 'ai-search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect to generated page
                window.location.href = '/page.php?id=' + data.page_id;
            } else {
                this.showError('Error: ' + (data.message || 'Unknown error'));
                this.hideLoading();
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            this.showError('Connection error. Please try again.');
            this.hideLoading();
        });
    }

    loadHistory() {
        try {
            const history = localStorage.getItem('intebwio_history');
            return history ? JSON.parse(history) : [];
        } catch {
            return [];
        }
    }

    saveToHistory(query) {
        // Remove duplicates
        this.searchHistory = this.searchHistory.filter(h => h.toLowerCase() !== query.toLowerCase());
        
        this.searchHistory.unshift(query);
        if (this.searchHistory.length > 30) {
            this.searchHistory = this.searchHistory.slice(0, 30);
        }

        localStorage.setItem('intebwio_history', JSON.stringify(this.searchHistory));
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput')?.focus();
            }

            // Ctrl/Cmd + H for history
            if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
                e.preventDefault();
                this.showSearchHistory();
            }

            // Ctrl/Cmd + / for help
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                this.showHelp();
            }
        });
    }

    showSearchHistory() {
        const items = this.searchHistory.slice(0, 10);
        const html = items.map((item, i) => 
            `<div onclick="window.intebwio.performAISearch('${item.replace(/'/g, "\\'")}')">
                ${i + 1}. ${item}
            </div>`
        ).join('');

        alert('Recent Searches:\n\n' + items.join('\n'));
    }

    showHelp() {
        alert(`Intebwio Shortcuts:
Ctrl+K: Focus search
Ctrl+H: Show history
Ctrl+/: Show this help
Enter: Search
`);
    }

    loadRecentPages() {
        fetch(this.apiUrl + 'latest.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.pages) {
                    this.displayRecentPages(data.pages);
                }
            })
            .catch(e => console.error('Error loading recent:', e));
    }

    displayRecentPages(pages) {
        const grid = document.getElementById('pagesGrid');
        if (!grid) return;

        grid.innerHTML = pages.map(page => `
            <a href="/page.php?id=${page.id}" class="page-card">
                <div class="page-card-image" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    ${page.title.charAt(0).toUpperCase()}
                </div>
                <div class="page-card-content">
                    <div class="page-card-title">${page.title}</div>
                    <div class="page-card-meta">
                        👁️ ${page.view_count || 0} views • ${new Date(page.created_at).toLocaleDateString()}
                    </div>
                </div>
            </a>
        `).join('');
    }

    showLoading() {
        const loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = 'flex';
        }
    }

    hideLoading() {
        const loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = 'none';
        }
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        document.body.insertBefore(errorDiv, document.body.firstChild);

        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }

    monitorPerformance() {
        if (window.performance && window.performance.timing) {
            window.addEventListener('load', () => {
                const perfData = window.performance.timing;
                const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;

                // Log performance metrics
                fetch(this.apiUrl + 'track.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'performance',
                        load_time: pageLoadTime,
                        page_load_time: pageLoadTime
                    })
                }).catch(e => console.log('Performance tracking error:', e));
            });
        }
    }
}

// Initialize enhanced browser
document.addEventListener('DOMContentLoaded', () => {
    window.intebwio = new IntebwioEnhanced();
});
