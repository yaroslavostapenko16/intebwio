# 🐛 Page Loading Timeout Issues - Complete Troubleshooting Guide

## Problem Summary
**Pages don't load after 5+ minutes of waiting from search request**

Your system now has the following timeout configuration:

| Component | Timeout Value | What It Controls |
|-----------|---------------|------------------|
| 🌐 Browser | **600 seconds** (10 minutes) | How long frontend waits for response |
| 💻 PHP Script | **600 seconds** (10 minutes) | How long server process can run |
| 🔌 Socket | **180 seconds** (3 minutes) | External API connection timeout |
| 🤖 Gemini API | **300 seconds** (5 minutes) | Individual API call timeout |
| 🔐 API Token | **30 seconds** | Initial connection to Gemini |

## ✅ Verification Steps

### Step 1: Check System Health
```bash
curl http://localhost/api/health-check.php | jq .
```

Expected output:
```json
{
  "success": true,
  "system_ready": true,
  "checks": {
    "php": {
      "max_execution_time": 600,
      "socket_timeout": 180,
      "status": "OK"
    },
    "database": {
      "status": "OK"
    },
    "gemini_api": {
      "status": "OK",
      "response_time_ms": < 5000
    }
  }
}
```

### Step 2: Test Simple Page Generation (120 seconds)
```javascript
fetch('/api/ai-search.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        query: 'Python',  // Short, simple query
        skip_aggregation: true  // Speeds up the process
    })
})
.then(r => r.json())
.then(d => console.log('Success:', d.metadata.generation_time_seconds + 's'))
.catch(e => console.log('Error:', e.message))
```

### Step 3: Test Complex Page Generation (180-300 seconds)
```javascript
fetch('/api/ai-search.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        query: 'Artificial Intelligence and Machine Learning',
        skip_aggregation: false  // Full content aggregation
    })
})
```

## 🔍 Diagnosing Timeout Issues

### Issue 1: Stuck at "Generating..." (60+ seconds)
**Symptom:** Progress message shows "Still generating..." or "Deep processing..."
**Cause:** Gemini API is slow or network latency

**Solution:**
1. Check API key validity:
   ```bash
   curl https://generativelanguage.googleapis.com/v1/models?key=YOUR_KEY
   ```

2. Try a simpler query (fewer words = faster generation)

3. Use `skip_aggregation: true` to skip content aggregation:
   ```javascript
   body: JSON.stringify({
       query: query,
       skip_aggregation: true
   })
   ```

### Issue 2: HTTP 500 Error
**Symptom:** Error message with time stamp but no content

**Solution:**
1. Check server logs:
   ```bash
   tail -50 /workspaces/intebwio/public_html/logs/intebwio.log | grep -A5 "ERROR"
   ```

2. Check database connection:
   ```bash
   curl http://localhost/api/health-check.php | jq '.checks.database'
   ```

3. Check memory usage:
   ```bash
   tail -20 /workspaces/intebwio/public_html/logs/intebwio.log | grep "Memory"
   ```

### Issue 3: Timeout After Exactly 300 Seconds (5 minutes)
**Symptom:** Request always fails at 300s mark

**Cause:** PHP max_execution_time limit is being hit

**Solution:**
```bash
# Check current setting
php -i | grep max_execution_time

# Should be >= 600 seconds
```

### Issue 4: Timeout After 180 Seconds (3 minutes) 
**Symptom:** Browser shows "Generation timeout" after exactly 3 minutes

**Cause:** Old browser timeout value not updated

**Solution:**
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh page (Ctrl+Shift+R)
- Or update landing-page-generator.html timeout to 600000ms

## 📊 Performance Expectations

Expected generation times by query type:

| Query Type | Time | Bottleneck |
|-----------|------|-----------|
| Simple (1-2 words) | 30-45s | Gemini API |
| Medium (3-5 words) | 45-75s | Content aggregation |
| Complex (6+ words) | 75-120s | Multiple API calls |

## 🚀 Optimization Tips

### For Fast Generation (< 60 seconds):
1. Use short, simple queries: "Python" instead of "How to learn Python"
2. Set `skip_aggregation: true` to skip Wikipedia/news scraping
3. Use `use_existing: true` to reuse cached pages

### For Complex Queries (60-120 seconds):
1. Be patient, don't refresh
2. Check the progress message (it updates every second)
3. Ensure browser stays open and connected

### For Repeated Searches:
1. Same topic = faster (uses cached page)
2. Different variations = new generation required

## 📝 Configuration Files to Check

All timeout settings are configured in:

1. **[landing-page-generator.html](landing-page-generator.html#L369)**
   - Line 369: Browser timeout = 600000ms

2. **[api/ai-search.php](api/ai-search.php#L9-L10)**
   - Line 9: PHP timeout = 600 seconds
   - Line 10: Socket timeout = 180 seconds

3. **[includes/config.php](includes/config.php#L58-L59)**
   - Line 58: Global PHP timeout
   - Line 59: Global socket timeout

4. **[includes/AIService.php](includes/AIService.php#L205)**
   - Line 205: Gemini API curl timeout = 300 seconds
   - Line 136: OpenAI API curl timeout = 300 seconds
   - Line 294: Anthropic API curl timeout = 300 seconds

## 🔧 Manual Testing Commands

### Test Gemini API Directly:
```bash
php /workspaces/intebwio/public_html/test-simple-timeout.php
```

### Test Full Pipeline:
```bash
php /workspaces/intebwio/public_html/test-timeout.php
```

### Watch Live Logs:
```bash
tail -f /workspaces/intebwio/public_html/logs/intebwio.log | grep -E "PAGE GENERATION|completed in|ERROR"
```

## ⚠️ Common Mistakes

❌ **DON'T**: Refresh the page while it's generating
❌ **DON'T**: Close the browser tab/window during generation
❌ **DON'T**: Use very long queries (> 500 characters)
❌ **DON'T**: Generate same topic repeatedly without waiting

✅ **DO**: Wait for the progress indicator to complete
✅ **DO**: Check the error message if it fails
✅ **DO**: Use shorter, simpler search terms
✅ **DO**: Reuse existing pages when possible

## 📞 Still Having Issues?

If you still experience timeouts after these changes:

1. Check [health-check.php](health-check.php) output for system issues
2. Look at logs for specific error messages:
   ```bash
   grep "ERROR\|Timeout\|Exception" /workspaces/intebwio/public_html/logs/intebwio.log
   ```
3. Try test-simple-timeout.php to isolate the issue:
   ```bash
   php /workspaces/intebwio/public_html/test-simple-timeout.php 2>&1
   ```

---

**Last Updated:** March 3, 2026
**Configuration Version:** 2.0
**Browser Timeout:** 10 minutes ✓
**Server Timeout:** 10 minutes ✓
**API Timeout:** 5 minutes ✓
