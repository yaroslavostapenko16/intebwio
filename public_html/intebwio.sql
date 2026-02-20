-- ============================================================================
-- INTEBWIO Database Schema
-- AI-Powered Web Browser & Content Aggregation Platform
-- ============================================================================
-- This SQL file creates all necessary tables for the Intebwio application
-- Import this file in phpMyAdmin or use: mysql -u user -p database < intebwio.sql
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = utf8mb4_unicode_ci;

-- ============================================================================
-- PAGES TABLE - Stores generated landing pages
-- ============================================================================
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_query VARCHAR(500) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    content LONGTEXT NOT NULL,
    html_content LONGTEXT NOT NULL,
    thumbnail_image VARCHAR(1000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_scan_date TIMESTAMP NULL,
    relevance_score FLOAT DEFAULT 0,
    view_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    FULLTEXT INDEX ft_query (search_query, title, description),
    INDEX idx_created (created_at),
    INDEX idx_updated (updated_at),
    INDEX idx_status (status),
    INDEX idx_view_count (view_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Main table storing all generated pages with caching and metadata';

-- ============================================================================
-- SEARCH_RESULTS TABLE - Stores aggregated content from multiple sources
-- ============================================================================
CREATE TABLE IF NOT EXISTS search_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    source_name VARCHAR(100),
    source_url VARCHAR(1000),
    title VARCHAR(500),
    description TEXT,
    image_url VARCHAR(1000),
    author VARCHAR(200),
    published_date DATETIME,
    relevance_score FLOAT DEFAULT 0,
    position_index INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_page (page_id),
    INDEX idx_source (source_name),
    INDEX idx_relevance (relevance_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores aggregated search results from Wikipedia, Web, News, GitHub, etc.';

-- ============================================================================
-- PAGE_ELEMENTS TABLE - Stores visual elements (images, tables, diagrams)
-- ============================================================================
CREATE TABLE IF NOT EXISTS page_elements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    element_type ENUM('image', 'table', 'diagram', 'button', 'link', 'text', 'video') DEFAULT 'text',
    element_data LONGTEXT NOT NULL,
    position_index INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_page (page_id),
    INDEX idx_type (element_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores rich page elements like images, tables, and diagrams';

-- ============================================================================
-- UPDATE_QUEUE TABLE - Manages weekly page updates
-- ============================================================================
CREATE TABLE IF NOT EXISTS update_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    scheduled_date DATETIME,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    last_attempt DATETIME,
    attempt_count INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_date),
    INDEX idx_page (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Manages automatic weekly updates of cached pages';

-- ============================================================================
-- USER_ACTIVITY TABLE - Tracks user interactions and analytics
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT,
    search_query VARCHAR(500),
    ip_address VARCHAR(45),
    user_agent TEXT,
    action_type ENUM('search', 'view', 'click') DEFAULT 'search',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
    INDEX idx_page (page_id),
    INDEX idx_created (created_at),
    INDEX idx_action (action_type),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks user searches, views, and clicks for analytics';

-- ============================================================================
-- SIMILAR_PAGES TABLE - Maps similar pages to avoid duplication
-- ============================================================================
CREATE TABLE IF NOT EXISTS similar_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    similar_page_id INT NOT NULL,
    similarity_score FLOAT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (similar_page_id) REFERENCES pages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pages (page_id, similar_page_id),
    INDEX idx_page (page_id),
    INDEX idx_similarity (similarity_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps similar pages to prevent duplicate page generation';

-- ============================================================================
-- CONTENT_SOURCES TABLE - Configuration for external content sources
-- ============================================================================
CREATE TABLE IF NOT EXISTS content_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    api_url VARCHAR(1000),
    api_key VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    success_rate FLOAT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuration for external content sources';

-- ============================================================================
-- SYSTEM_LOGS TABLE - Logs for debugging and monitoring
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type VARCHAR(50),
    message TEXT,
    page_id INT,
    ip_address VARCHAR(45),
    error_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
    INDEX idx_type (log_type),
    INDEX idx_created (created_at),
    INDEX idx_page (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comprehensive logging for debugging and system monitoring';

-- ============================================================================
-- CACHE_STATS TABLE - Performance metrics for pages
-- ============================================================================
CREATE TABLE IF NOT EXISTS cache_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    load_time_ms INT,
    cache_hit BOOLEAN DEFAULT TRUE,
    bandwidth_bytes INT,
    last_accessed TIMESTAMP NULL,
    access_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_page (page_id),
    INDEX idx_cache_hit (cache_hit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Performance and caching statistics for pages';

-- ============================================================================
-- FEATURED_PAGES TABLE - Manually featured pages for homepage
-- ============================================================================
CREATE TABLE IF NOT EXISTS featured_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL UNIQUE,
    featured_order INT,
    featured_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    featured_until TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_active (is_active),
    INDEX idx_order (featured_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pages featured on the homepage';

-- ============================================================================
-- Populate content sources with default providers
-- ============================================================================
INSERT INTO content_sources (name, api_url, is_active, priority) VALUES
('Wikipedia', 'https://en.wikipedia.org/w/api.php', TRUE, 1),
('Google Web Search', 'https://www.google.com/search', FALSE, 2),
('GitHub', 'https://api.github.com', TRUE, 3),
('Medium', 'https://medium.com', TRUE, 4),
('News', 'https://newsapi.org', FALSE, 5)
ON DUPLICATE KEY UPDATE is_active=VALUES(is_active), priority=VALUES(priority);

-- ============================================================================
-- Create Views for easier querying
-- ============================================================================

-- View: Top Pages by Views
CREATE OR REPLACE VIEW top_pages_view AS
SELECT 
    id,
    search_query,
    title,
    view_count,
    created_at,
    last_scan_date,
    relevance_score
FROM pages
WHERE status = 'active'
ORDER BY view_count DESC
LIMIT 100;

-- View: Pages Due for Update
CREATE OR REPLACE VIEW pages_due_update_view AS
SELECT 
    p.id,
    p.search_query,
    p.title,
    p.last_scan_date,
    DATEDIFF(NOW(), p.last_scan_date) as days_since_update
FROM pages p
WHERE (p.last_scan_date IS NULL OR p.last_scan_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
AND p.status = 'active'
ORDER BY p.last_scan_date ASC;

-- View: Trending Topics
CREATE OR REPLACE VIEW trending_topics_view AS
SELECT 
    ua.search_query,
    COUNT(*) as search_count,
    MAX(ua.created_at) as last_searched,
    COUNT(DISTINCT ua.ip_address) as unique_searches
FROM user_activity ua
WHERE ua.action_type = 'search'
AND ua.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY ua.search_query
ORDER BY search_count DESC
LIMIT 50;

-- ============================================================================
-- SESSION TRACKING TABLE - User session management
-- ============================================================================
CREATE TABLE IF NOT EXISTS session_tracking (
    session_id VARCHAR(64) PRIMARY KEY,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks user sessions and activity';

-- ============================================================================
-- USER PREFERENCES TABLE - User settings and preferences
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    preference_key VARCHAR(100),
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES session_tracking(session_id) ON DELETE CASCADE,
    UNIQUE KEY unique_pref (session_id, preference_key),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores user preferences and settings';

-- ============================================================================
-- SESSION HISTORY TABLE - User search and view history
-- ============================================================================
CREATE TABLE IF NOT EXISTS session_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    page_id INT,
    search_query VARCHAR(500),
    action_type ENUM('search', 'view', 'click', 'favorite') DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES session_tracking(session_id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_page (page_id),
    INDEX idx_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores user search and browsing history';

-- ============================================================================
-- USER FAVORITES TABLE - User liked/bookmarked pages
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    page_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES session_tracking(session_id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (session_id, page_id),
    INDEX idx_session (session_id),
    INDEX idx_page (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores user favorite pages';

-- ============================================================================
-- SHARE LINKS TABLE - Shareable page links
-- ============================================================================
CREATE TABLE IF NOT EXISTS share_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL UNIQUE,
    share_token VARCHAR(100) UNIQUE,
    shares_count INT DEFAULT 0,
    unique_shares INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP NULL,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_token (share_token),
    INDEX idx_shares (shares_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores shareable links for pages';

-- ============================================================================
-- PAGE QUALITY SCORES TABLE - Page quality assessment
-- ============================================================================
CREATE TABLE IF NOT EXISTS page_quality_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL UNIQUE,
    quality_score FLOAT DEFAULT 0,
    quality_tier VARCHAR(50),
    details JSON,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    INDEX idx_score (quality_score),
    INDEX idx_tier (quality_tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores quality scores and assessments for pages';

-- ============================================================================
-- SEARCH SUGGESTIONS TABLE - Cached search suggestions
-- ============================================================================
CREATE TABLE IF NOT EXISTS search_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    suggestion TEXT NOT NULL UNIQUE,
    frequency INT DEFAULT 1,
    quality_score FLOAT DEFAULT 0.5,
    is_trending BOOLEAN DEFAULT FALSE,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_frequency (frequency),
    INDEX idx_trending (is_trending),
    FULLTEXT INDEX ft_suggestion (suggestion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Caches popular search suggestions';

-- ============================================================================
-- DUPLICATE_DETECTION TABLE - Tracks potential duplicate pages
-- ============================================================================
CREATE TABLE IF NOT EXISTS duplicate_detection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id_1 INT NOT NULL,
    page_id_2 INT NOT NULL,
    similarity_percentage FLOAT,
    is_duplicate BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id_1) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id_2) REFERENCES pages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pair (page_id_1, page_id_2),
    INDEX idx_similarity (similarity_percentage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detects and tracks duplicate/similar content';

-- ============================================================================
-- Database Summary
-- ============================================================================
-- Total Tables: 18
-- Total Views: 3
-- Features:
-- ✓ Page Caching - Once generated, pages are stored permanently
-- ✓ Auto-Update - Weekly automatic content refresh
-- ✓ Content Aggregation - Pulls from multiple sources
-- ✓ Analytics - Tracks user behavior and trending topics
-- ✓ Performance Metrics - Monitors cache hit rates and load times
-- ✓ Similarity Detection - Prevents duplicate pages
-- ✓ User Sessions - Track user activity and preferences
-- ✓ Favorites System - Users can bookmark pages
-- ✓ Quality Scoring - AI-based page quality assessment
-- ✓ Advanced Search - Multi-faceted search with filters
-- ✓ Sharing - Create and track shareable links
-- ✓ Recommendations - Personalized page suggestions
-- ============================================================================
