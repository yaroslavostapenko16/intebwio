<?php
/**
 * Intebwio - Database Management Class
 */

class Database {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Initialize database tables
     */
    public function initializeTables() {
        try {
            // Pages table
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS pages (
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
                INDEX idx_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Search Results Cache
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS search_results (
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
                INDEX idx_source (source_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Page Elements (images, tables, diagrams, etc)
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS page_elements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                element_type ENUM('image', 'table', 'diagram', 'button', 'link', 'text', 'video') DEFAULT 'text',
                element_data LONGTEXT NOT NULL,
                position_index INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                INDEX idx_page (page_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Update Queue for weekly scans
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS update_queue (
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
                INDEX idx_scheduled (scheduled_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // User Activity Tracking
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT,
                search_query VARCHAR(500),
                ip_address VARCHAR(45),
                user_agent TEXT,
                action_type ENUM('search', 'view', 'click') DEFAULT 'search',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE SET NULL,
                INDEX idx_page (page_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Similar Pages mapping
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS similar_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id INT NOT NULL,
                similar_page_id INT NOT NULL,
                similarity_score FLOAT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                FOREIGN KEY (similar_page_id) REFERENCES pages(id) ON DELETE CASCADE,
                UNIQUE KEY unique_pages (page_id, similar_page_id),
                INDEX idx_page (page_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            return true;
        } catch (Exception $e) {
            error_log("Database initialization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new page or return existing if similar
     */
    public function createOrGetPage($searchQuery, $title, $description, $htmlContent) {
        try {
            // Check if exact match exists
            $stmt = $this->pdo->prepare("SELECT id FROM pages WHERE search_query = ?");
            $stmt->execute([$searchQuery]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update view count
                $this->updateViewCount($existing['id']);
                return $existing['id'];
            }
            
            // Check for similar pages
            $similarPageId = $this->findSimilarPage($searchQuery, $title);
            if ($similarPageId) {
                $this->updateViewCount($similarPageId);
                return $similarPageId;
            }
            
            // Create new page
            $stmt = $this->pdo->prepare("
                INSERT INTO pages (search_query, title, description, html_content, content)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $searchQuery,
                $title,
                $description,
                $htmlContent,
                strip_tags($htmlContent)
            ]);
            
            $pageId = $this->pdo->lastInsertId();
            
            // Schedule update
            $this->scheduleUpdate($pageId);
            
            return $pageId;
        } catch (Exception $e) {
            error_log("Error creating page: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find similar pages using cosine similarity
     */
    private function findSimilarPage($searchQuery, $title) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, search_query, title FROM pages 
                WHERE status = 'active'
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $pages = $stmt->fetchAll();
            
            $threshold = SIMILARITY_THRESHOLD;
            
            foreach ($pages as $page) {
                $similarity = $this->calculateSimilarity($searchQuery, $page['search_query']);
                if ($similarity >= $threshold) {
                    return $page['id'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error finding similar page: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate string similarity (cosine similarity)
     */
    private function calculateSimilarity($str1, $str2) {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        // Create n-grams
        $len = 2;
        $gram1 = array_count_values(str_split($str1, $len));
        $gram2 = array_count_values(str_split($str2, $len));
        
        $common = array_intersect_key($gram1, $gram2);
        $similarity = array_sum($common) / (array_sum($gram1) + array_sum($gram2) - array_sum($common));
        
        return $similarity === 0 ? 0 : min(1, max(0, $similarity));
    }
    
    /**
     * Get page by ID
     */
    public function getPageById($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM pages WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$pageId]);
            $page = $stmt->fetch();
            
            if ($page) {
                // Get search results
                $stmt = $this->pdo->prepare("
                    SELECT * FROM search_results WHERE page_id = ? 
                    ORDER BY position_index ASC
                ");
                $stmt->execute([$pageId]);
                $page['search_results'] = $stmt->fetchAll();
                
                // Get elements
                $stmt = $this->pdo->prepare("
                    SELECT * FROM page_elements WHERE page_id = ? 
                    ORDER BY position_index ASC
                ");
                $stmt->execute([$pageId]);
                $page['elements'] = $stmt->fetchAll();
            }
            
            return $page;
        } catch (Exception $e) {
            error_log("Error getting page: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search pages by query
     */
    public function searchPages($query) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, search_query, title, description, thumbnail_image, view_count, created_at
                FROM pages 
                WHERE (status = 'active') AND 
                      (MATCH(search_query, title, description) AGAINST(? IN BOOLEAN MODE) OR 
                       search_query LIKE ?)
                ORDER BY view_count DESC, updated_at DESC
                LIMIT 50
            ");
            
            $likeQuery = '%' . $query . '%';
            $stmt->execute([$query, $likeQuery]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error searching pages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add search result to page
     */
    public function addSearchResult($pageId, $sourceData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO search_results 
                (page_id, source_name, source_url, title, description, image_url, author, published_date, relevance_score, position_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $pageId,
                $sourceData['source_name'] ?? NULL,
                $sourceData['source_url'] ?? NULL,
                $sourceData['title'] ?? NULL,
                $sourceData['description'] ?? NULL,
                $sourceData['image_url'] ?? NULL,
                $sourceData['author'] ?? NULL,
                $sourceData['published_date'] ?? NULL,
                $sourceData['relevance_score'] ?? 0,
                $sourceData['position_index'] ?? 0
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error adding search result: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update view count
     */
    public function updateViewCount($pageId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE pages SET view_count = view_count + 1 WHERE id = ?");
            return $stmt->execute([$pageId]);
        } catch (Exception $e) {
            error_log("Error updating view count: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule page update
     */
    public function scheduleUpdate($pageId) {
        try {
            $nextUpdate = date('Y-m-d H:i:s', strtotime('+7 days'));
            $stmt = $this->pdo->prepare("
                INSERT INTO update_queue (page_id, scheduled_date, status)
                VALUES (?, ?, 'pending')
            ");
            return $stmt->execute([$pageId, $nextUpdate]);
        } catch (Exception $e) {
            error_log("Error scheduling update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pages due for update
     */
    public function getPagesForUpdate() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.search_query, p.title 
                FROM pages p
                LEFT JOIN update_queue uq ON p.id = uq.page_id
                WHERE (uq.status IS NULL OR uq.status = 'failed') AND
                      (p.last_scan_date IS NULL OR p.last_scan_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
                LIMIT 100
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting pages for update: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Record user activity
     */
    public function recordActivity($pageId = null, $searchQuery = null, $actionType = 'search') {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity (page_id, search_query, ip_address, user_agent, action_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$pageId, $searchQuery, $ipAddress, $userAgent, $actionType]);
        } catch (Exception $e) {
            error_log("Error recording activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total pages
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM pages WHERE status = 'active'");
            $stats['total_pages'] = $stmt->fetch()['count'];
            
            // Total views
            $stmt = $this->pdo->query("SELECT SUM(view_count) as total FROM pages");
            $stats['total_views'] = $stmt->fetch()['total'] ?? 0;
            
            // Pages updated this week
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM pages WHERE last_scan_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['updated_this_week'] = $stmt->fetch()['count'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return [];
        }
    }
}

?>
