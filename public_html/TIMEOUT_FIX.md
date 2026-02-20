# Page Generation Timeout Fix

## Problem Identified

**Issue**: Page generation times out after 2 minutes and redirects to index

**Root Causes**:
1. PHP execution timeout was too short (default 30 seconds)
2. Content aggregation from multiple sources takes significant time
3. Gemini API generation takes ~30 seconds
4. Browser fetch request had no explicit timeout
5. No progress feedback to user during generation

**Timeline**:
- ⏱️ ~30 seconds: Gemini API call
- ⏱️ ~20-40 seconds: Content aggregation (optional)
- ⏱️ ~5-10 seconds: Page generation & database operations
- **Total**: 55-80 seconds (can exceed browser timeout in some cases)

## Solutions Implemented ✅

### 1. **Increased PHP Execution Timeouts**
- ✅ Set `max_execution_time = 300` (5 minutes) in config.php
- ✅ Set `default_socket_timeout = 120` (2 minutes) in config.php
- ✅ Added explicit `set_time_limit(300)` in search.php and ai-search.php
- ✅ Set curl timeout to 120 seconds for Gemini API calls

**Files Modified**:
- `/includes/config.php` - Global timeout settings
- `/api/search.php` - Set 5-minute timeout
- `/api/ai-search.php` - Set 5-minute timeout
- `/includes/AIService.php` - Increased curl timeout to 120s

### 2. **Added Browser-Side Timeout**
- ✅ Added AbortController to fetch request with 180-second timeout
- ✅ Better error handling for timeout scenarios
- ✅ Clear user feedback: "Generation timeout - took too long"

**Files Modified**:
- `/landing-page-generator.html` - Added fetch timeout logic

### 3. **Optimized Backend Processing**
- ✅ Added optional content aggregation (`skip_aggregation` parameter)
- ✅ Fallback to minimal content if aggregation takes too long
- ✅ Better error logging with timing information
- ✅ Improved Gemini API logging with request timing

**Files Modified**:
- `/api/ai-search.php` - Made aggregation optional, added timing logs
- `/includes/AIService.php` - Enhanced logging with timing metrics

### 4. **Enhanced Debugging**
- ✅ Created test scripts:
  - `test-simple-timeout.php` - Tests Gemini API response time
  - `test-timeout.php` - Tests full generation pipeline
- ✅ Comprehensive error logging with timestamps
- ✅ Clear progress tracking in error logs

## How to Test

### Test 1: Check API Response Time
```bash
php /workspaces/intebwio/public_html/test-simple-timeout.php
```
Expected output: ~30 seconds

### Test 2: Generate a Page via Web
1. Go to http://localhost/landing-page-generator.html
2. Enter a topic: "artificial intelligence"
3. Click "Generate Page"
4. Wait for generation (should complete in 30-60 seconds)

### Test 3: Check Error Logs
```bash
tail -f /workspaces/intebwio/public_html/logs/intebwio.log
```

Look for:
- "AI Content Generation started"
- "Gemini API: Request completed in XXXs"
- "AI Content Generation completed in XXXs"

## Timeout Configuration

| Setting | Value | Purpose |
|---------|-------|---------|
| PHP max_execution_time | 300s | Server won't kill long-running scripts |
| Socket timeout | 120s | External API won't hang forever |
| Curl timeout (Gemini) | 120s | Gemini API call won't hang |
| Browser fetch timeout | 180s (3 min) | Frontend won't give up too early |

## Performance Optimization Tips

To further speed up generation:

### Option 1: Skip Content Aggregation
Send `skip_aggregation: true` to use only Gemini's knowledge:
```javascript
fetch('/api/ai-search.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        query: 'artificial intelligence',
        skip_aggregation: true  // Skip slow content fetching
    })
});
```

### Option 2: Enable Caching
Implemented in database if pages repeat. Check database table `pages`.

### Option 3: Use Async Processing
For production, implement background job queue:
- Return immediately with job ID
- Client polls for results
- Prevents browser timeout entirely

## Files Modified Summary

| File | Changes |
|------|---------|
| `/includes/config.php` | Added timeout settings |
| `/includes/AIService.php` | Enhanced logging, 120s curl timeout |
| `/api/search.php` | Added 5-minute timeout |
| `/api/ai-search.php` | Optional aggregation, added timing logs |
| `/landing-page-generator.html` | Added fetch timeout (180s) |
| `/test-simple-timeout.php` | Created timeout test script |

## Monitoring & Debugging

Check `/workspaces/intebwio/public_html/logs/intebwio.log` for:

```
=== AI Content Generation started for: artificial intelligence
Using AI Provider: gemini
Available aggregated content items: 1
Prompt built in 0.12 seconds (968 chars)
Calling Gemini API...
Gemini API: Sending request to gemini-2.5-flash model...
Gemini API: Request completed in 29.63s (HTTP 200)
Gemini API: Successfully generated content (25066 characters)
AI Content Generation completed in 29.63 seconds
```

## If Still Timing Out

1. **Check server resources**: CPU, memory, network
2. **Check external sources**: Wikipedia, Google API may be slow
3. **Try `skip_aggregation: true`**: Forces fast generation
4. **Check error logs**: `/logs/intebwio.log`
5. **Restart PHP-FPM**: `systemctl restart php-fpm`

## Next Steps (Optional)

For production deployment:
1. Implement Redis caching for aggregated content
2. Use message queue for async processing
3. Add CDN for static assets
4. Monitor Gemini API rate limits
5. Implement page result caching

---

✅ **Status**: Timeouts fixed. Your system should now handle page generation up to 5 minutes without losing the connection.
