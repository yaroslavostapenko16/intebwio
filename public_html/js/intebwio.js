/*
 * Intebwio - Main JavaScript
 * Frontend functionality and interaction handling
 */

class Intebwio {
    constructor() {
        this.apiUrl = '/api/';
        this.searchQueryParam = new URLSearchParams(window.location.search).get('search');
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupSearch();
        if (this.searchQueryParam) {
            this.performSearch(this.searchQueryParam);
        }
    }

    setupEventListeners() {
        // Search form submission
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const query = document.getElementById('searchInput').value.trim();
                if (query) {
                    this.performSearch(query);
                }
            });
        }

        // Related tags
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('tag')) {
                const tag = e.target.textContent.trim();
                this.performSearch(tag);
            }
        });

        // Card interactions
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.card');
            if (card && e.target.tagName !== 'A') {
                const link = card.querySelector('.btn');
                if (link) {
                    this.recordCardClick(link.href);
                }
            }
        });
    }

    setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            // Add autocomplete
            searchInput.addEventListener('input', (e) => {
                this.updateSearchSuggestions(e.target.value);
            });
        }
    }

    performSearch(query) {
        // Show loading state
        this.showLoading();

        // Fetch or create page
        fetch(this.apiUrl + 'search.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ query: query })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.exists) {
                    // Page already exists, redirect to it
                    window.location.href = '/page.php?id=' + data.page_id;
                } else {
                    // Page created, redirect to new page
                    window.location.href = '/page.php?id=' + data.page_id;
                }
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

    updateSearchSuggestions(query) {
        if (query.length < 2) return;

        const suggestionsContainer = document.getElementById('suggestions');
        if (!suggestionsContainer) return;

        fetch(this.apiUrl + 'autocomplete.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            suggestionsContainer.innerHTML = '';
            if (data.suggestions && data.suggestions.length > 0) {
                suggestionsContainer.classList.add('visible');
                data.suggestions.forEach(suggestion => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.textContent = suggestion;
                    item.addEventListener('click', () => {
                        document.getElementById('searchInput').value = suggestion;
                        this.performSearch(suggestion);
                    });
                    suggestionsContainer.appendChild(item);
                });
            }
        })
        .catch(error => console.error('Autocomplete error:', error));
    }

    recordCardClick(url) {
        fetch(this.apiUrl + 'track.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                action: 'card_click',
                url: url
            })
        }).catch(error => console.error('Tracking error:', error));
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
}

// Global functions for inline handlers
function scrollToContent() {
    const content = document.getElementById('content');
    if (content) {
        content.scrollIntoView({ behavior: 'smooth' });
    }
}

function shareContent() {
    const title = document.querySelector('.hero-title')?.textContent || 'Intebwio Page';
    const text = 'Check out this page on Intebwio: ' + title;
    const url = window.location.href;

    if (navigator.share) {
        navigator.share({ title, text, url }).catch(e => console.error('Share failed:', e));
    } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copied to clipboard!');
    }
}

function printPage() {
    window.print();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.intebwio = new Intebwio();
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }
    }

    // Escape to close suggestions
    if (e.key === 'Escape') {
        const suggestions = document.getElementById('suggestions');
        if (suggestions) {
            suggestions.classList.remove('visible');
        }
    }
});

// Service Worker registration for offline support
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(error => {
        console.log('Service Worker registration failed:', error);
    });
}
