<?php
/**
 * Intebwio - Advanced Search API with AI
 * Unlimited landing pages per topic - AI-powered search and generation
 * Full landing page generation using Gemini API
 * ~300 lines
 */

// Increase timeout for content aggregation and AI generation
set_time_limit(300);  // 5 minutes
ini_set('default_socket_timeout', 120);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ContentAggregator.php';
require_once __DIR__ . '/../includes/AIService.php';
require_once __DIR__ . '/../includes/AdvancedPageGenerator.php';

try {
    // Check if PDO is available before proceeding
    if (!isset($pdo)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Accept both POST JSON and GET parameters
    $data = json_decode(file_get_contents('php://input'), true) ?? $_GET;
    
    if (!isset($data['query']) || empty(trim($data['query']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Query parameter is required']);
        exit;
    }
    
    $searchQuery = trim($data['query']);
    $useExisting = isset($data['use_existing']) ? (bool)$data['use_existing'] : false;
    $skipAggregation = isset($data['skip_aggregation']) ? (bool)$data['skip_aggregation'] : false;
    
    // Normalize query (lowercase, remove extra spaces)
    $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $searchQuery));
    
    // Input validation
    if (strlen($normalizedQuery) < 2 || strlen($normalizedQuery) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Query must be between 2 and 500 characters']);
        exit;
    }
    
    // Optional: Check for existing page if requested
    if ($useExisting && function_exists('class_exists') && class_exists('Database')) {
        $stmt = $pdo->prepare("
            SELECT id, view_count FROM pages 
            WHERE LOWER(search_query) = LOWER(?)
            ORDER BY view_count DESC
            LIMIT 1
        ");
        $stmt->execute([$normalizedQuery]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'page_id' => $existing['id'],
                'is_new' => false,
                'message' => 'Returning existing page'
            ]);
            exit;
        }
    }
    
    // Generate new landing page with Gemini AI
    $startTime = microtime(true);
    
    // Step 1: Aggregate content from multiple sources (optional, can skip for speed)
    $aggregatedResults = [];
    
    if (!$skipAggregation) {
        error_log("Content aggregation: Starting for query '$searchQuery'");
        $aggregationStart = microtime(true);
        
        $aggregator = new ContentAggregator($pdo);
        $aggregatedResults = $aggregator->aggregateContent($normalizedQuery, 0);
        
        $aggregationTime = microtime(true) - $aggregationStart;
        error_log("Content aggregation: Completed in " . round($aggregationTime, 2) . "s, found " . count($aggregatedResults) . " items");
    }
    
    // If no results from aggregator or skipped, use minimal content
    if (empty($aggregatedResults)) {
        $aggregatedResults = [[
            'title' => $normalizedQuery,
            'description' => 'Information about ' . $normalizedQuery,
            'source' => 'AI Generated'
        ]];
    }
    
    // Step 2: Generate AI content with Gemini
    $aiService = new AIService(
        'gemini',
        GEMINI_API_KEY
    );
    
    // Generate comprehensive HTML content
    $aiContent = $aiService->generatePageContent($normalizedQuery, $aggregatedResults);
    
    if (!$aiContent) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate content using Gemini API'
        ]);
        exit;
    }
    
    // Step 3: Generate full landing page using AdvancedPageGenerator
    $pageGenerator = new AdvancedPageGenerator($pdo, $aiService);
    $htmlContent = $pageGenerator->generateAIPage($normalizedQuery, $aggregatedResults, $aiContent);
    
    if (!$htmlContent) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate complete landing page'
        ]);
        exit;
    }
    
    // Step 4: Extract metadata from content
    $title = ucfirst($normalizedQuery);
    preg_match('/<meta name="description" content="([^"]*)"/', $htmlContent, $metaMatch);
    $description = $metaMatch[1] ?? 'AI-generated comprehensive page about ' . $normalizedQuery;
    
    // Step 5: Store page in database (if database available)
    $pageId = null;
    if (function_exists('class_exists')) {
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO pages (
                    search_query, title, description, html_content, 
                    ai_provider, ai_model, view_count, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW(), NOW())
            ");
            
            $insertStmt->execute([
                $normalizedQuery,
                $title,
                substr($description, 0, 500),
                $htmlContent,
                'gemini',
                'gemini-2.5-flash'
            ]);
            
            $pageId = $pdo->lastInsertId();
        } catch (Exception $dbError) {
            error_log("Database insert error: " . $dbError->getMessage());
        }
    }
    
    // Step 6: Cache the generated page
    $cacheKey = 'page_' . md5($normalizedQuery . '_' . time());
    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, [
            'page_id' => $pageId,
            'query' => $normalizedQuery,
            'timestamp' => time(),
            'html' => $htmlContent
        ], 604800); // 7 days
    }
    
    $generationTime = microtime(true) - $startTime;
    
    // Step 7: Return successful response with full page content
    http_response_code(201); // Created
    
    $responseData = [
        'success' => true,
        'message' => 'Full landing page generated successfully using Gemini AI',
        'page_id' => $pageId,
        'is_new' => true,
        'query' => $searchQuery,
        'normalized_query' => $normalizedQuery,
        'title' => $title,
        'description' => $description,
        'html' => $htmlContent,
        'metadata' => [
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-2.5-flash',
            'generation_time_seconds' => round($generationTime, 2),
            'sources_count' => count($aggregatedResults),
            'cache_key' => $cacheKey,
            'timestamp' => date('Y-m-d H:i:s'),
            'content_length' => strlen($htmlContent),
            'content_words' => str_word_count(strip_tags($htmlContent))
        ]
    ];
    
    // Try to encode response, with fallback
    $jsonResponse = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonResponse === false) {
        // If JSON encoding fails, remove large HTML and try again
        error_log("JSON encoding failed for full response, fallback to metadata-only response");
        $jsonResponse = json_encode([
            'success' => true,
            'message' => 'Page generated successfully (metadata only)',
            'page_id' => $pageId,
            'is_new' => true,
            'query' => $searchQuery,
            'title' => $title,
            'description' => $description,
            'redirect_to' => '/page.php?id=' . $pageId,
            'metadata' => $responseData['metadata']
        ]);
    }
    
    echo $jsonResponse;
    
    
} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    http_response_code(500);
    
    // Provide detailed error message for debugging
    $errorResponse = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'type' => get_class($e),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Include file/line info in debug mode
    if (ini_get('display_errors')) {
        $errorResponse['file'] = $e->getFile();
        $errorResponse['line'] = $e->getLine();
    }
    
    echo json_encode($errorResponse);
}

?>
