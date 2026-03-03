<?php
/**
 * Page Generation API
 * Main logic:
 * 1. Check if exact page exists
 * 2. Check if similar page exists (similarity matching)
 * 3. If not exists, create new page
 * 4. Returns page data with slug for redirect
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'generate';
    $query = $_GET['query'] ?? $_POST['query'] ?? '';
    
    if (!$query) {
        throw new Exception('Search query is required');
    }
    
    // Sanitize query
    $query = trim(htmlspecialchars($query, ENT_QUOTES, 'UTF-8'));
    
    if (strlen($query) < 2) {
        throw new Exception('Query must be at least 2 characters');
    }
    
    switch ($action) {
        case 'generate':
            handleSearch($pdo, $query);
            break;
        case 'check':
            checkPageExists($pdo, $query);
            break;
        case 'get':
            getPageBySlug($pdo, $query);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Main search handler - implements the core logic
 * 1. Check for exact match (case-insensitive)
 * 2. Check for similar pages (Levenshtein similarity)
 * 3. Create new page if nothing found
 */
function handleSearch($pdo, $query) {
    // Step 1: Check for exact match (case-insensitive)
    $stmt = $pdo->prepare("
        SELECT id, query, slug, title, description, html_content, view_count, 
               created_at, updated_at
        FROM pages 
        WHERE LOWER(TRIM(query)) = LOWER(TRIM(:query))
        AND status = 'active'
        LIMIT 1
    ");
    
    $stmt->execute(['query' => $query]);
    $existingPage = $stmt->fetch();
    
    if ($existingPage) {
        // Exact match found - return cached page
        updatePageViews($pdo, $existingPage['id']);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'cached' => true,
            'found_type' => 'exact_match',
            'page' => [
                'id' => (int)$existingPage['id'],
                'query' => $existingPage['query'],
                'slug' => $existingPage['slug'],
                'title' => $existingPage['title'],
                'description' => $existingPage['description'],
                'content' => $existingPage['html_content'],
                'views' => (int)$existingPage['view_count'],
                'createdAt' => $existingPage['created_at'],
                'updatedAt' => $existingPage['updated_at']
            ],
            'message' => 'Found exact match in database',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Step 2: Check for similar pages
    $stmt = $pdo->prepare("
        SELECT id, query, slug, title, description, html_content, view_count,
               created_at, updated_at
        FROM pages 
        WHERE status = 'active'
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    
    $stmt->execute();
    $allPages = $stmt->fetchAll();
    
    $bestMatch = null;
    $bestSimilarity = SIMILARITY_THRESHOLD;
    
    foreach ($allPages as $page) {
        $similarity = levenshteinSimilarity($query, $page['query']);
        
        if ($similarity > $bestSimilarity) {
            $bestSimilarity = $similarity;
            $bestMatch = $page;
        }
    }
    
    if ($bestMatch) {
        // Similar page found - return it
        updatePageViews($pdo, $bestMatch['id']);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'cached' => true,
            'found_type' => 'similar_match',
            'similarity_score' => round($bestSimilarity, 3),
            'page' => [
                'id' => (int)$bestMatch['id'],
                'query' => $bestMatch['query'],
                'slug' => $bestMatch['slug'],
                'title' => $bestMatch['title'],
                'description' => $bestMatch['description'],
                'content' => $bestMatch['html_content'],
                'views' => (int)$bestMatch['view_count'],
                'createdAt' => $bestMatch['created_at'],
                'updatedAt' => $bestMatch['updated_at']
            ],
            'message' => 'Found similar page: "' . $bestMatch['query'] . '"',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Step 3: No page found - create new one
    createNewPage($pdo, $query);
}

/**
 * Create a new page 
 */
function createNewPage($pdo, $query) {
    try {
        $slug = createSlug($query);
        
        // Check if slug already exists and make it unique
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pages WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $slug = $slug . '-' . time();
        }
        
        // Generate description
        $descriptions = [
            "Complete guide to {$query}",
            "Everything you need to know about {$query}",
            "In-depth overview of {$query}",
            "The ultimate resource for {$query}",
            "Comprehensive information on {$query}",
            "Expert insights on {$query}",
            "Detailed analysis of {$query}",
            "Best practices for {$query}",
            "Comprehensive overview of {$query}"
        ];
        $description = $descriptions[array_rand($descriptions)];
        
        // Generate HTML content
        $htmlContent = generateHTMLPage($query);
        
        // Store in database
        $stmt = $pdo->prepare("
            INSERT INTO pages (
                query, slug, title, description, html_content, 
                ai_provider, ai_model, status, view_count, created_at, updated_at
            ) VALUES (
                :query, :slug, :title, :description, :html_content,
                :ai_provider, :ai_model, 'active', 1, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            'query' => $query,
            'slug' => $slug,
            'title' => $query,
            'description' => $description,
            'html_content' => $htmlContent,
            'ai_provider' => AI_PROVIDER,
            'ai_model' => 'gemini-pro'
        ]);
        
        $pageId = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'cached' => false,
            'found_type' => 'new_page',
            'page' => [
                'id' => (int)$pageId,
                'query' => $query,
                'slug' => $slug,
                'title' => $query,
                'description' => $description,
                'content' => $htmlContent,
                'views' => 1,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ],
            'message' => 'New page created successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create page: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

/**
 * Check if page exists
 */
function checkPageExists($pdo, $query) {
    $stmt = $pdo->prepare("
        SELECT id, slug, title, query, created_at
        FROM pages 
        WHERE LOWER(TRIM(query)) = LOWER(TRIM(:query))
        AND status = 'active'
        LIMIT 1
    ");
    
    $stmt->execute(['query' => $query]);
    $page = $stmt->fetch();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'exists' => !empty($page),
        'page' => $page ?: null,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get page by slug
 */
function getPageBySlug($pdo, $slug) {
    $stmt = $pdo->prepare("
        SELECT id, query, slug, title, description, html_content, view_count,
               created_at, updated_at
        FROM pages 
        WHERE slug = :slug 
        AND status = 'active'
    ");
    
    $stmt->execute(['slug' => $slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Page not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Update views
    updatePageViews($pdo, $page['id']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'page' => [
            'id' => (int)$page['id'],
            'query' => $page['query'],
            'slug' => $page['slug'],
            'title' => $page['title'],
            'description' => $page['description'],
            'content' => $page['html_content'],
            'views' => (int)$page['view_count'],
            'createdAt' => $page['created_at'],
            'updatedAt' => $page['updated_at']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update page view count
 */
function updatePageViews($pdo, $pageId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE pages 
            SET view_count = view_count + 1, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $pageId]);
    } catch (Exception $e) {
        // Log error but don't fail
        error_log('Error updating page views: ' . $e->getMessage());
    }
}

/**
 * Calculate Levenshtein similarity (0-1 score)
 */
function levenshteinSimilarity($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    if ($str1 === $str2) return 1.0;
    
    if (strlen($str1) === 0 || strlen($str2) === 0) return 0.0;
    
    $distance = levenshtein($str1, $str2);
    $longer = strlen($str1) > strlen($str2) ? strlen($str1) : strlen($str2);
    
    return (double)(($longer - $distance) / $longer);
}

/**
 * Create URL slug from text
 */
function createSlug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 100);
}

/**
 * Generate complete HTML page content
 */
function generateHTMLPage($topic) {
    $safeTopicText = htmlspecialchars($topic, ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
<article class="page-article">
    <section class="page-section">
        <h2>Overview of {$safeTopicText}</h2>
        <p>
            {$safeTopicText} is a multifaceted and important subject that has garnered significant attention 
            from researchers, professionals, and industry experts. This comprehensive guide provides an 
            in-depth examination of the fundamental concepts, historical context, and current developments 
            within this field.
        </p>
    </section>

    <section class="page-section">
        <h3>Historical Context</h3>
        <p>
            The evolution of {$safeTopicText} reflects decades of innovation and discovery. From its initial 
            conceptualization to present-day applications, this field has undergone remarkable transformation. 
            Pioneering figures established foundational principles that continue to shape modern practices.
        </p>
        <ul>
            <li>Early developments and foundational work</li>
            <li>Key breakthroughs and innovations</li>
            <li>Evolution of methodology and practice</li>
            <li>Contemporary applications and impact</li>
        </ul>
    </section>

    <section class="page-section">
        <h3>Core Concepts and Principles</h3>
        <p>Understanding {$safeTopicText} requires familiarity with several fundamental concepts:</p>
        <ul>
            <li><strong>Basic Fundamentals:</strong> The essential building blocks and foundational knowledge</li>
            <li><strong>Theoretical Frameworks:</strong> Major theories explaining processes and phenomena</li>
            <li><strong>Practical Implementation:</strong> Real-world applications and use cases</li>
            <li><strong>Best Practices:</strong> Industry standards and optimal approaches</li>
            <li><strong>Emerging Trends:</strong> New developments and future directions</li>
        </ul>
    </section>

    <section class="page-section">
        <h3>Key Benefits and Applications</h3>
        <p>
            The study and application of {$safeTopicText} offers numerous advantages and practical benefits:
        </p>
        <ul>
            <li>Enhanced understanding and knowledge</li>
            <li>Practical problem-solving abilities</li>
            <li>Professional advancement opportunities</li>
            <li>Innovation and creative thinking</li>
            <li>Improved decision-making capabilities</li>
        </ul>
    </section>

    <section class="page-section">
        <h3>Challenges and Considerations</h3>
        <p>While {$safeTopicText} offers tremendous potential, several challenges require careful consideration:</p>
        <ul>
            <li>Complexity and learning curve</li>
            <li>Resource requirements</li>
            <li>Evolving standards and practices</li>
            <li>Integration with existing systems</li>
            <li>Ongoing education and development</li>
        </ul>
    </section>

    <section class="page-section">
        <h3>Future Prospects</h3>
        <p>
            The future of {$safeTopicText} appears promising, with numerous opportunities for advancement. 
            Emerging technologies, changing market dynamics, and increased awareness are driving innovation 
            and opening new possibilities for development and application.
        </p>
    </section>

    <section class="page-section">
        <h3>Conclusion</h3>
        <p>
            {$safeTopicText} represents a rich and evolving field with significant implications for professionals 
            and organizations. By staying informed about key developments and best practices, individuals and 
            institutions can leverage these insights to achieve their goals and contribute to ongoing progress 
            within the discipline.
        </p>
    </section>
</article>
HTML;
}

?>

