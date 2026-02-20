<?php
/**
 * Intebwio - AI Integration Service
 * Integrates with OpenAI, Google Gemini, or other AI APIs for page generation
 * ~300 lines
 */

class AIService {
    private $apiProvider;
    private $apiKey;
    private $model;
    private $maxTokens = 4000;
    
    public function __construct($provider = 'openai', $apiKey = '') {
        $this->apiProvider = $provider;
        $this->apiKey = $apiKey ?? getenv('AI_API_KEY');
        $this->model = $this->getModelForProvider($provider);
    }
    
    /**
     * Generate comprehensive page content using AI
     */
    public function generatePageContent($searchQuery, $aggregatedContent = []) {
        try {
            error_log("AI Content Generation started for query: " . $searchQuery);
            error_log("Using AI Provider: " . $this->apiProvider);
            
            $prompt = $this->buildPrompt($searchQuery, $aggregatedContent);
            
            switch ($this->apiProvider) {
                case 'openai':
                    return $this->callOpenAI($prompt);
                case 'gemini':
                    return $this->callGemini($prompt);
                case 'anthropic':
                    return $this->callAnthropic($prompt);
                default:
                    error_log("Unknown AI provider: " . $this->apiProvider . ", using fallback");
                    return $this->generateFallbackContent($searchQuery, $aggregatedContent);
            }
        } catch (Exception $e) {
            error_log("AI Generation error: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Build comprehensive prompt for AI
     */
    private function buildPrompt($searchQuery, $aggregatedContent = []) {
        $contentSummary = '';
        if (!empty($aggregatedContent)) {
            $contentSummary = "Based on this research:\n";
            foreach (array_slice($aggregatedContent, 0, 5) as $item) {
                $contentSummary .= "- " . ($item['title'] ?? 'Info') . ": " . substr($item['description'] ?? '', 0, 200) . "\n";
            }
        }
        
        $prompt = <<<PROMPT
You are an expert content curator creating a comprehensive, professional landing page about: "$searchQuery"

$contentSummary

Create a detailed, well-structured HTML landing page that includes:

1. EXECUTIVE SUMMARY: 2-3 paragraphs explaining what "$searchQuery" is
2. KEY CONCEPTS: 5-7 important concepts with explanations
3. HISTORICAL CONTEXT: Brief history or background
4. CURRENT STATE: Modern developments and trends
5. APPLICATIONS/USE CASES: Real-world applications
6. FUTURE PROSPECTS: What's ahead
7. RESOURCES & LEARNING: Where to learn more
8. FAQ SECTION: 5-7 common questions

Format with proper HTML structure, include nice formatting with sections, lists, and emphasis.
Make it professional, accurate, and engaging. Include relevant statistics and facts where appropriate.
PROMPT;
        
        return $prompt;
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt) {
        if (!$this->apiKey) {
            error_log("OpenAI API Error: No API key provided");
            return null;
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional content curator and web designer. Create comprehensive, well-formatted landing pages with detailed information.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => 0.7
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log curl errors
        if ($curlError) {
            error_log("OpenAI API cURL Error: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices']) && !empty($result['choices'])) {
                $text = $result['choices'][0]['message']['content'] ?? null;
                if ($text) {
                    return $text;
                }
            }
            error_log("OpenAI API: Invalid response structure - " . substr($response, 0, 500));
            return null;
        }
        
        // Log error responses
        error_log("OpenAI API Error - HTTP $httpCode: " . substr($response, 0, 500));
        return null;
    }
    
    /**
     * Call Google Gemini API
     */
    private function callGemini($prompt) {
        if (!$this->apiKey) {
            error_log("Gemini API Error: No API key provided");
            return null;
        }
        
        // Check for placeholder or invalid key
        if (strpos($this->apiKey, 'YOUR_') !== false || strlen($this->apiKey) < 10) {
            error_log("Gemini API Error: Invalid API key format");
            return null;
        }
        
        // Use the model from configuration
        $model = $this->model ?: 'gemini-pro';
        $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $this->apiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log curl errors
        if ($curlError) {
            error_log("Gemini API cURL Error: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['candidates']) && !empty($result['candidates'])) {
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    return $text;
                }
            }
            error_log("Gemini API: Invalid response structure - " . substr($response, 0, 500));
            return null;
        }
        
        // Log error responses with more detail
        $responseData = json_decode($response, true);
        $errorMsg = "Gemini API Error - HTTP $httpCode";
        if (isset($responseData['error']['message'])) {
            $errorMsg .= ": " . $responseData['error']['message'];
        }
        error_log($errorMsg);
        
        // Special handling for specific errors
        if ($httpCode === 403 && isset($responseData['error']['message'])) {
            if (strpos($responseData['error']['message'], 'leaked') !== false) {
                error_log("WARNING: Gemini API key has been reported as leaked and is disabled!");
                error_log("Please generate a new API key at: https://aistudio.google.com/apikey");
            }
        }
        
        return null;
    }
    
    /**
     * Call Anthropic Claude API
     */
    private function callAnthropic($prompt) {
        if (!$this->apiKey) {
            error_log("Anthropic API Error: No API key provided");
            return null;
        }
        
        $url = 'https://api.anthropic.com/v1/messages';
        
        $data = [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 4000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log curl errors
        if ($curlError) {
            error_log("Anthropic API cURL Error: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['content']) && !empty($result['content'])) {
                $text = $result['content'][0]['text'] ?? null;
                if ($text) {
                    return $text;
                }
            }
            error_log("Anthropic API: Invalid response structure - " . substr($response, 0, 500));
            return null;
        }
        
        // Log error responses
        error_log("Anthropic API Error - HTTP $httpCode: " . substr($response, 0, 500));
        return null;
    }
    
    /**
     * Generate fallback content without AI
     */
    private function generateFallbackContent($searchQuery, $aggregatedContent = []) {
        $html = "<section class='ai-generated-content'>";
        $html .= "<h2>Comprehensive Guide to " . htmlspecialchars($searchQuery) . "</h2>";
        
        if (!empty($aggregatedContent)) {
            $html .= "<h3>Overview</h3>";
            $html .= "<p>" . htmlspecialchars($aggregatedContent[0]['description'] ?? 'Information about ' . $searchQuery) . "</p>";
            
            $html .= "<h3>Key Sources</h3>";
            $html .= "<ul>";
            foreach (array_slice($aggregatedContent, 0, 10) as $item) {
                $html .= "<li>";
                if (!empty($item['source_url'])) {
                    $html .= "<a href='" . htmlspecialchars($item['source_url']) . "' target='_blank'>";
                }
                $html .= htmlspecialchars($item['title'] ?? 'Information');
                if (!empty($item['source_url'])) {
                    $html .= "</a>";
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        }
        
        $html .= "</section>";
        return $html;
    }
    
    /**
     * Get appropriate model for provider
     */
    private function getModelForProvider($provider) {
        $models = [
            'openai' => 'gpt-4-turbo',
            'gemini' => 'gemini-1.5-pro',  // Updated from gemini-pro to gemini-1.5-pro
            'anthropic' => 'claude-3-sonnet-20240229'
        ];
        return $models[$provider] ?? 'gpt-4-turbo';
    }
    
    /**
     * Analyze content relevance using AI
     */
    public function analyzeRelevance($query, $content) {
        $prompt = "Rate the relevance of this content to the query '$query' on a scale of 0-1:\n\n$content\n\nRespond with just a number.";
        
        $response = match($this->apiProvider) {
            'openai' => $this->callOpenAI($prompt),
            'gemini' => $this->callGemini($prompt),
            'anthropic' => $this->callAnthropic($prompt),
            default => '0.5'
        };
        
        preg_match('/\d+\.?\d*/', $response, $matches);
        return floatval($matches[0] ?? 0.5);
    }
    
    /**
     * Generate SEO metadata
     */
    public function generateSEOMetadata($searchQuery, $content) {
        $prompt = "Generate SEO metadata for content about '$searchQuery'. Provide JSON format: {\"title\": \"\", \"description\": \"\", \"keywords\": []}";
        
        $response = match($this->apiProvider) {
            'openai' => $this->callOpenAI($prompt),
            'gemini' => $this->callGemini($prompt),
            'anthropic' => $this->callAnthropic($prompt),
            default => '{}'
        };
        
        $json = json_decode($response, true);
        return $json ?: [];
    }
}

?>
