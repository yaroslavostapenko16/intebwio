/**
 * Intebwio - Advanced Analytics & Metrics
 * Comprehensive tracking and reporting
 */

class AnalyticsTracker {
    /**
     * Initialize analytics system
     */
    static init() {
        this.initiated = true;
        this.sessionData = {
            startTime: Date.now(),
            events: [],
            pageViews: 0,
            interactions: 0,
            searches: 0
        };
        
        window.addEventListener('beforeunload', () => this.flushAnalytics());
        this.trackPageLoad();
        this.setupEventTracking();
    }
    
    /**
     * Track page load performance
     */
    static trackPageLoad() {
        if (!window.performance) return;
        
        window.addEventListener('load', () => {
            const perfData = window.performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            
            this.trackEvent('page_load', {
                duration: pageLoadTime,
                dns: perfData.domainLookupEnd - perfData.domainLookupStart,
                tcp: perfData.connectEnd - perfData.connectStart,
                ttfb: perfData.responseStart - perfData.navigationStart,
                domContentLoaded: perfData.domContentLoadedEventEnd - perfData.navigationStart
            });
            
            // Report to server
            fetch('/api/analytics.php?action=performance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    page_load_time: pageLoadTime,
                    metrics: {
                        dns: perfData.domainLookupEnd - perfData.domainLookupStart,
                        tcp: perfData.connectEnd - perfData.connectStart,
                        ttfb: perfData.responseStart - perfData.navigationStart,
                        dcl: perfData.domContentLoadedEventEnd - perfData.navigationStart
                    }
                })
            }).catch(err => console.error('Analytics error:', err));
        });
    }
    
    /**
     * Track user events
     */
    static setupEventTracking() {
        // Track clicks
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-analytics]');
            if (target) {
                this.trackEvent('click', {
                    element: target.tagName,
                    id: target.id,
                    class: target.className,
                    text: target.innerText ? target.innerText.substring(0, 50) : ''
                });
            }
        });
        
        // Track form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            this.trackEvent('form_submit', {
                form_id: form.id,
                form_name: form.name
            });
        });
        
        // Track scroll depth
        this.trackScrollDepth();
        
        // Track time spent
        this.trackTimeSpent();
    }
    
    /**
     * Track scroll depth
     */
    static trackScrollDepth() {
        let maxScroll = 0;
        let trackingPoints = [25, 50, 75, 100];
        let trackedPoints = [];
        
        window.addEventListener('scroll', () => {
            const scrollPercentage = 
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
            
            if (scrollPercentage > maxScroll) {
                maxScroll = scrollPercentage;
            }
            
            // Track specific scroll points
            trackingPoints.forEach(point => {
                if (scrollPercentage >= point && !trackedPoints.includes(point)) {
                    trackedPoints.push(point);
                    this.trackEvent('scroll_depth', {
                        percentage: point
                    });
                }
            });
        });
    }
    
    /**
     * Track time spent on page
     */
    static trackTimeSpent() {
        let visibleTime = 0;
        let lastTimestamp = Date.now();
        
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                visibleTime += Date.now() - lastTimestamp;
            } else {
                lastTimestamp = Date.now();
            }
        });
        
        window.addEventListener('beforeunload', () => {
            visibleTime += Date.now() - lastTimestamp;
            this.trackEvent('time_spent', {
                seconds: Math.round(visibleTime / 1000)
            });
        });
    }
    
    /**
     * Track custom event
     */
    static trackEvent(eventType, eventData = {}) {
        if (!this.initiated) return;
        
        const event = {
            type: eventType,
            timestamp: Date.now(),
            url: window.location.href,
            ...eventData
        };
        
        this.sessionData.events.push(event);
        
        // Log specific event types
        if (eventType === 'search') {
            this.sessionData.searches++;
        } else if (eventType === 'page_view') {
            this.sessionData.pageViews++;
        } else if (eventType === 'click' || eventType === 'form_submit') {
            this.sessionData.interactions++;
        }
        
        console.debug('Analytics:', event);
    }
    
    /**
     * Track search query
     */
    static trackSearch(query, resultCount) {
        this.trackEvent('search', {
            query: query,
            results: resultCount,
            timestamp: Date.now()
        });
    }
    
    /**
     * Track page view
     */
    static trackPageView(pageId, pageTitle) {
        this.trackEvent('page_view', {
            page_id: pageId,
            page_title: pageTitle
        });
    }
    
    /**
     * Track user action
     */
    static trackAction(actionType, actionData) {
        this.trackEvent('user_action', {
            action: actionType,
            ...actionData
        });
    }
    
    /**
     * Get session summary
     */
    static getSessionSummary() {
        const sessionDuration = Date.now() - this.sessionData.startTime;
        
        return {
            duration: Math.round(sessionDuration / 1000),
            pageViews: this.sessionData.pageViews,
            interactions: this.sessionData.interactions,
            searches: this.sessionData.searches,
            eventCount: this.sessionData.events.length,
            bounceRate: this.sessionData.pageViews <= 1 ? true : false
        };
    }
    
    /**
     * Flush analytics to server
     */
    static flushAnalytics() {
        if (this.sessionData.events.length === 0) return;
        
        const summary = this.getSessionSummary();
        const payload = {
            session_summary: summary,
            events: this.sessionData.events,
            user_agent: navigator.userAgent,
            referrer: document.referrer
        };
        
        // Use sendBeacon for best reliability on unload
        navigator.sendBeacon('/api/analytics.php?action=session', 
            JSON.stringify(payload)
        );
    }
    
    /**
     * Get event statistics
     */
    static getEventStats() {
        const stats = {};
        
        this.sessionData.events.forEach(event => {
            if (!stats[event.type]) {
                stats[event.type] = 0;
            }
            stats[event.type]++;
        });
        
        return stats;
    }
}

// Heatmap tracking for UI elements
class HeatmapTracker {
    static init() {
        this.clicks = new Map();
        document.addEventListener('click', (e) => {
            this.trackClick(e);
        });
    }
    
    static trackClick(event) {
        const rect = event.target.getBoundingClientRect();
        const key = `${Math.round(event.clientX / 50) * 50}-${Math.round(event.clientY / 50) * 50}`;
        
        this.clicks.set(key, (this.clicks.get(key) || 0) + 1);
    }
    
    static getHeatmapData() {
        return Array.from(this.clicks.entries()).map(([coords, count]) => {
            const [x, y] = coords.split('-').map(Number);
            return { x, y, intensity: count };
        });
    }
    
    static reportHeatmap() {
        const data = this.getHeatmapData();
        fetch('/api/analytics.php?action=heatmap', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    }
}

// Engagement metrics tracker
class EngagementTracker {
    static init() {
        this.metrics = {
            scrollEvents: 0,
            hoverTime: 0,
            focusTime: 0,
            mouseMovements: 0
        };
        
        this.setupTrackers();
    }
    
    static setupTrackers() {
        // Scroll tracking
        window.addEventListener('scroll', () => {
            this.metrics.scrollEvents++;
        }, { passive: true });
        
        // Hover time tracking
        document.addEventListener('mouseenter', (e) => {
            if (e.target.matches('a, button, [role="button"]')) {
                const startTime = Date.now();
                const cleanup = () => {
                    this.metrics.hoverTime += Date.now() - startTime;
                };
                e.target.addEventListener('mouseleave', cleanup, { once: true });
            }
        }, true);
        
        // Focus time tracking
        document.addEventListener('focus', (e) => {
            if (e.target.matches('input, textarea')) {
                const startTime = Date.now();
                const cleanup = () => {
                    this.metrics.focusTime += Date.now() - startTime;
                };
                e.target.addEventListener('blur', cleanup, { once: true });
            }
        }, true);
        
        // Mouse movement tracking (sample)
        let lastCheck = Date.now();
        document.addEventListener('mousemove', () => {
            const now = Date.now();
            if (now - lastCheck > 1000) {
                this.metrics.mouseMovements++;
                lastCheck = now;
            }
        });
    }
    
    static getMetrics() {
        return this.metrics;
    }
    
    static reportEngagement() {
        fetch('/api/analytics.php?action=engagement', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.metrics)
        });
    }
}

// Network monitoring
class NetworkMonitor {
    static init() {
        this.networkEvents = [];
        
        if (window.PerformanceObserver) {
            try {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach((entry) => {
                        this.networkEvents.push({
                            name: entry.name,
                            duration: entry.duration,
                            transferSize: entry.transferSize || 0,
                            decodedBodySize: entry.decodedBodySize || 0
                        });
                    });
                });
                
                observer.observe({ entryTypes: ['resource'] });
            } catch (e) {
                console.warn('PerformanceObserver not supported');
            }
        }
    }
    
    static getNetworkStats() {
        const stats = {
            totalRequests: this.networkEvents.length,
            totalTransferSize: 0,
            totalDuration: 0,
            slowestRequest: null,
            slowestDuration: 0
        };
        
        this.networkEvents.forEach(evt => {
            stats.totalTransferSize += evt.transferSize;
            stats.totalDuration += evt.duration;
            
            if (evt.duration > stats.slowestDuration) {
                stats.slowestDuration = evt.duration;
                stats.slowestRequest = evt.name;
            }
        });
        
        return stats;
    }
}

// Initialize all trackers when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof AnalyticsTracker !== 'undefined') {
        AnalyticsTracker.init();
        HeatmapTracker.init();
        EngagementTracker.init();
        NetworkMonitor.init();
    }
});
