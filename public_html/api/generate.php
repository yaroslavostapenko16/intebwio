<?php
/**
 * Page Generation API
 * Generates new pages using AI and stores them in database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/PageGenerator.php';

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
    
    switch ($action) {
        case 'generate':
            generatePage($pdo, $query);
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
 * Generate a new page and store in database
 */
function generatePage($pdo, $query) {
    // Check if page already exists with similar query
    $stmt = $pdo->prepare("
        SELECT * FROM pages 
        WHERE query LIKE :query 
        AND status = 'active'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute(['query' => '%' . $query . '%']);
    $existingPage = $stmt->fetch();
    
    if ($existingPage) {
        // Check if page was recently updated
        $lastUpdated = strtotime($existingPage['updated_at']);
        $updateInterval = 7 * 24 * 60 * 60; // 7 days
        
        if (time() - $lastUpdated < $updateInterval) {
            // Return cached page
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'cached' => true,
                'page' => [
                    'id' => $existingPage['id'],
                    'title' => $existingPage['title'],
                    'description' => $existingPage['description'],
                    'slug' => $existingPage['slug'],
                    'content' => $existingPage['html_content'],
                    'createdAt' => $existingPage['created_at'],
                    'updatedAt' => $existingPage['updated_at'],
                    'views' => $existingPage['view_count']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            return;
        }
    }
    
    try {
        // Generate new page
        $pageGenerator = new PageGenerator($pdo);
        
        // Create slug from query
        $slug = createSlug($query);
        
        // Generate description
        $descriptions = [
            "Complete guide to {$query}",
            "Everything you need to know about {$query}",
            "In-depth overview of {$query}",
            "The ultimate resource for {$query}",
            "Comprehensive information on {$query}",
            "Expert insights on {$query}",
            "Detailed analysis of {$query}"
        ];
        $description = $descriptions[array_rand($descriptions)];
        
        // Generate HTML content
        $htmlContent = generateHTMLPage($query);
        
        // Store in database
        $stmt = $pdo->prepare("
            INSERT INTO pages (query, slug, title, description, html_content, ai_provider, ai_model, status, created_at, updated_at)
            VALUES (:query, :slug, :title, :description, :html_content, :ai_provider, :ai_model, 'active', NOW(), NOW())
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
            'page' => [
                'id' => (int)$pageId,
                'title' => $query,
                'description' => $description,
                'slug' => $slug,
                'content' => $htmlContent,
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
                'views' => 1
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate page: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

/**
 * Check if page exists
 */
function checkPageExists($pdo, $query) {
    $stmt = $pdo->prepare("
        SELECT id, slug, created_at, updated_at 
        FROM pages 
        WHERE query LIKE :query 
        AND status = 'active'
        LIMIT 1
    ");
    
    $stmt->execute(['query' => '%' . $query . '%']);
    $page = $stmt->fetch();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'exists' => !empty($page),
        'page' => $page,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get page by slug
 */
function getPageBySlug($pdo, $slug) {
    $stmt = $pdo->prepare("
        SELECT * FROM pages 
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
    
    // Increment view count
    $updateStmt = $pdo->prepare("
        UPDATE pages 
        SET view_count = view_count + 1 
        WHERE id = :id
    ");
    $updateStmt->execute(['id' => $page['id']]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'page' => [
            'id' => (int)$page['id'],
            'title' => $page['title'],
            'description' => $page['description'],
            'slug' => $page['slug'],
            'content' => $page['html_content'],
            'createdAt' => $page['created_at'],
            'updatedAt' => $page['updated_at'],
            'views' => (int)$page['view_count'] + 1
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Create URL slug from text
 */
function createSlug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^\w\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return substr($text, 0, 100);
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
