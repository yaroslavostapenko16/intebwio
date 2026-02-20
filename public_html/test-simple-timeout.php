<?php
/**
 * Simple Page Generation Timeout Test
 * Tests without database dependency
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

echo "=== Simple Page Generation Test ===\n\n";

$testQuery = "artificial intelligence";

echo "Configuration:\n";
echo "- Max execution time: " . ini_get('max_execution_time') . "s\n";
echo "- Socket timeout: " . ini_get('default_socket_timeout') . "s\n";
echo "- Query: $testQuery\n\n";

// Load API key directly
define('GEMINI_API_KEY', 'AIzaSyAPMrwvoxVtFBegqxqOT1JH_7QQZLnhqzg');
define('AI_PROVIDER', 'gemini');

require_once __DIR__ . '/includes/AIService.php';

$globalStart = microtime(true);

echo "Step 1: Create AIService instance\n";
$aiService = new AIService('gemini', GEMINI_API_KEY);
echo "✓ Done\n\n";

echo "Step 2: Call AI Content Generation (Gemini API)\n";
$step2Start = microtime(true);

// Simple test data
$aggregatedContent = [
    [
        'title' => 'Artificial Intelligence Overview',
        'description' => 'AI is intelligence demonstrated by machines as opposed to natural intelligence displayed by animals and humans.',
        'source' => 'Overview'
    ]
];

echo "Calling generatePageContent()...\n";
$aiContent = $aiService->generatePageContent($testQuery, $aggregatedContent);

$step2Time = microtime(true) - $step2Start;

if ($aiContent) {
    echo "✓ Success in " . round($step2Time, 2) . " seconds\n";
    echo "  Generated " . strlen($aiContent) . " characters\n";
    echo "  First 100 chars: " . substr($aiContent, 0, 100) . "...\n";
} else {
    echo "✗ Failed - No content returned\n";
}

$totalTime = microtime(true) - $globalStart;

echo "\n=== Results ===\n";
echo "Total Time: " . round($totalTime, 2) . " seconds\n";

if ($totalTime > 120) {
    echo "⚠️  Exceeds 2 minutes - browser may timeout\n";
} else if ($totalTime > 30) {
    echo "⚠️  Exceeds 30 seconds - may timeout\n";
} else {
    echo "✓ Acceptable time\n";
}
?>
