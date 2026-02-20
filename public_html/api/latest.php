<?php
/**
 * Intebwio - Latest Pages API
 * Returns recently generated pages
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, search_query as title, thumbnail_image, view_count, created_at 
        FROM pages 
        WHERE status = 'active'
        ORDER BY created_at DESC
        LIMIT 12
    ");
    
    $stmt->execute();
    $pages = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'pages' => $pages,
        'total' => count($pages)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>
