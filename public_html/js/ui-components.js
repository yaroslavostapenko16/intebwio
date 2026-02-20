/**
 * Intebwio - Enhanced UI Components
 * Interactive UI with filters, modals, and advanced features
 */

class IntebwioUIComponents {
    constructor() {
        this.modals = new Map();
        this.toasts = [];
        this.init();
    }

    init() {
        this.setupSearchFilters();
        this.setupModals();
        this.setupToasts();
        this.setupDarkMode();
        this.setupExportButtons();
    }

    // ============ SEARCH FILTERS ============

    setupSearchFilters() {
        const filterBtn = document.getElementById('filterBtn');
        if (filterBtn) {
            filterBtn.addEventListener('click', () => this.openFilterPanel());
        }

        const applyFiltersBtn = document.getElementById('applyFiltersBtn');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => this.applyFilters());
        }

        const resetFiltersBtn = document.getElementById('resetFiltersBtn');
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', () => this.resetFilters());
        }
    }

    openFilterPanel() {
        const panel = document.getElementById('filterPanel');
        if (panel) {
            panel.classList.toggle('visible');
        }
    }

    applyFilters() {
        const filters = {
            sortBy: document.getElementById('sortBy')?.value || 'relevance',
            minRelevance: document.getElementById('minRelevance')?.value || 0,
            dateFrom: document.getElementById('dateFrom')?.value || '',
            dateTo: document.getElementById('dateTo')?.value || '',
            sources: this.getSelectedSources(),
            minViews: document.getElementById('minViews')?.value || 0
        };

        // Trigger search with filters
        const query = document.getElementById('searchInput')?.value;
        if (query) {
            this.performAdvancedSearch(query, filters);
        }
    }

    resetFilters() {
        document.querySelectorAll('.filter-input').forEach(input => {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        this.applyFilters();
    }

    getSelectedSources() {
        const sources = [];
        document.querySelectorAll('input[name="source"]:checked').forEach(checkbox => {
            sources.push(checkbox.value);
        });
        return sources;
    }

    performAdvancedSearch(query, filters) {
        const params = new URLSearchParams({
            q: query,
            sort_by: filters.sortBy,
            min_relevance: filters.minRelevance,
            min_views: filters.minViews
        });

        if (filters.dateFrom) params.append('date_from', filters.dateFrom);
        if (filters.dateTo) params.append('date_to', filters.dateTo);
        if (filters.sources.length > 0) {
            params.append('sources', filters.sources.join(','));
        }

        fetch('/api/advanced-search.php?action=search&' + params)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.displaySearchResults(data);
                } else {
                    this.showToast('Search failed: ' + data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Search error:', err);
                this.showToast('Connection error', 'error');
            });
    }

    displaySearchResults(data) {
        const resultsContainer = document.getElementById('searchResults');
        if (!resultsContainer) return;

        resultsContainer.innerHTML = '';

        if (data.results.length === 0) {
            resultsContainer.innerHTML = '<p class="no-results">No results found. Try a different search.</p>';
            return;
        }

        data.results.forEach(result => {
            const card = this.createResultCard(result);
            resultsContainer.appendChild(card);
        });

        // Show result count
        const countElement = document.getElementById('resultCount');
        if (countElement) {
            countElement.textContent = `Found ${data.total} results in ${data.took}ms`;
        }
    }

    createResultCard(result) {
        const card = document.createElement('div');
        card.className = 'result-card';
        
        const relevancePercent = Math.round((result.avg_relevance || 0) * 100);
        
        card.innerHTML = `
            <div class="result-header">
                <h3><a href="/page.php?id=${result.id}">${escapeHtml(result.title)}</a></h3>
                <div class="result-meta">
                    <span class="badge">${result.source_count} sources</span>
                    <span class="relevance">${relevancePercent}% relevant</span>
                </div>
            </div>
            <p class="result-description">${escapeHtml(result.description || '')}</p>
            <div class="result-stats">
                <span>👁️ ${result.view_count} views</span>
                <span>📅 ${new Date(result.created_at).toLocaleDateString()}</span>
            </div>
            <div class="result-actions">
                <button onclick="intebwioUI.addFavorite(${result.id})" class="btn-icon">⭐ Favorite</button>
                <button onclick="intebwioUI.shareResult(${result.id})" class="btn-icon">🔗 Share</button>
            </div>
        `;
        
        return card;
    }

    // ============ MODALS ============

    setupModals() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal(e.target.id);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('visible');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('visible');
            document.body.style.overflow = 'auto';
        }
    }

    closeAllModals() {
        document.querySelectorAll('.modal-overlay.visible').forEach(modal => {
            modal.classList.remove('visible');
        });
        document.body.style.overflow = 'auto';
    }

    // ============ TOASTS/NOTIFICATIONS ============

    setupToasts() {
        this.toastContainer = document.createElement('div');
        this.toastContainer.className = 'toast-container';
        document.body.appendChild(this.toastContainer);
    }

    showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${icons[type]}</span>
            <span class="toast-message">${escapeHtml(message)}</span>
        `;
        
        this.toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('visible');
        }, 10);
        
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        return toast;
    }

    showConfirm(message, onConfirm, onCancel) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>${message}</h3>
                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="this.parentElement.parentElement.remove(); ${onConfirm}()">Confirm</button>
                    <button class="btn btn-secondary" onclick="this.parentElement.parentElement.remove(); ${onCancel}()">Cancel</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('visible'), 10);
    }

    // ============ DARK MODE ============

    setupDarkMode() {
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', () => this.toggleDarkMode());
            
            // Load saved preference
            if (localStorage.getItem('intebwio_darkMode') === 'true') {
                this.enableDarkMode();
            }
        }
    }

    toggleDarkMode() {
        if (document.body.classList.contains('dark-mode')) {
            this.disableDarkMode();
        } else {
            this.enableDarkMode();
        }
    }

    enableDarkMode() {
        document.body.classList.add('dark-mode');
        localStorage.setItem('intebwio_darkMode', 'true');
        this.showToast('Dark mode enabled', 'success', 2000);
    }

    disableDarkMode() {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('intebwio_darkMode', 'false');
        this.showToast('Light mode enabled', 'success', 2000);
    }

    // ============ EXPORT FUNCTIONALITY ============

    setupExportButtons() {
        const exportMenuBtn = document.getElementById('exportMenuBtn');
        if (exportMenuBtn) {
            exportMenuBtn.addEventListener('click', () => this.openExportMenu());
        }
    }

    openExportMenu() {
        const pageId = this.getCurrentPageId();
        if (!pageId) {
            this.showToast('No page selected', 'warning');
            return;
        }

        const menu = document.createElement('div');
        menu.className = 'export-menu';
        menu.innerHTML = `
            <div class="export-menu-content">
                <button onclick="intebwioUI.exportAs('pdf', ${pageId})" class="export-option">
                    📄 Export as PDF
                </button>
                <button onclick="intebwioUI.exportAs('markdown', ${pageId})" class="export-option">
                    📝 Export as Markdown
                </button>
                <button onclick="intebwioUI.exportAs('html', ${pageId})" class="export-option">
                    🌐 Export as HTML
                </button>
                <button onclick="intebwioUI.shareResult(${pageId})" class="export-option">
                    🔗 Create Share Link
                </button>
            </div>
        `;
        
        document.body.appendChild(menu);
        setTimeout(() => menu.classList.add('visible'), 10);
        
        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target) && e.target !== document.getElementById('exportMenuBtn')) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 100);
    }

    exportAs(format, pageId) {
        this.showToast(`Exporting as ${format}...`, 'info');
        
        fetch(`/api/export.php?action=${format}&page_id=${pageId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (format === 'html' || format === 'markdown') {
                        this.downloadContent(data.content, `intebwio_page.${format === 'markdown' ? 'md' : 'html'}`);
                    } else {
                        this.showToast(`${format.toUpperCase()} export ready`, 'success');
                    }
                } else {
                    this.showToast('Export failed: ' + data.message, 'error');
                }
            })
            .catch(err => this.showToast('Export error', 'error'));
    }

    downloadContent(content, filename) {
        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        
        this.showToast('Download started', 'success');
    }

    // ============ HELPER METHODS ============

    getCurrentPageId() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('id') || '0');
    }

    addFavorite(pageId) {
        fetch('/api/user.php?action=favorite&page_id=' + pageId, { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.showToast('Added to favorites ⭐', 'success');
                }
            });
    }

    shareResult(pageId) {
        fetch('/api/export.php?action=share&page_id=' + pageId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const input = document.createElement('input');
                    input.value = data.share_url;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                    
                    this.showToast('Share link copied to clipboard! 🔗', 'success');
                }
            });
    }
}

// Global helper functions
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Initialize UI on page load
document.addEventListener('DOMContentLoaded', () => {
    if (!window.intebwioUI) {
        window.intebwioUI = new IntebwioUIComponents();
    }
});
