# Page Generation 500 Error - Troubleshooting Guide

## Quick Diagnostics

Your diagnostic script shows **100% system health** ✓, which means configuration is correct. If you're still getting 500 errors, the issue is happening during page generation itself.

## How to Find the Real Error

### Step 1: Check Your Hostinger Error Logs

The most important tool is looking at error logs. Full logging has been added to track which step fails.

**Log location on Hostinger:**
```
/home/u757840095/domains/intebwio.com/public_html/logs/intebwio.log
```

Or via Hostinger cPanel:
1. Go to **File Manager**
2. Navigate to `public_html/logs/`
3. Open `intebwio.log` and look for `=== PAGE GENERATION START ===` entries

### Step 2: Understand the Generation Steps

When you generate a page, the system logs these steps:

```
Step 1: PDO connection verified
Step 2: Input data parsed
Step 3: Query received: 'test topic'
Step 4: Query normalized to: 'test topic'
Step 5: Input validation passed
Step 6: Starting content aggregation
Step 7: Content aggregation complete
Step 8: AIService created, starting content generation
Step 9: AI content generated, length: 21171 chars
Step 10: Creating AdvancedPageGenerator
Step 11: Generating beautiful page
Step 12: Page generated, length: 45000 chars
Step 13: Extracting metadata
Step 14: Metadata extracted
Step 15: Starting database insert
Step 16: Database insert successful, page_id: 123
Step 17: Setting up cache
Step 18: Cache stored
Step 19: Total generation time: 45.32s
Step 20: Building JSON response
Step 21: Response data prepared, size: 1048576 bytes
Step 22: JSON encoding successful
Step 23: Sending response to client
=== PAGE GENERATION COMPLETE ===
```

### Step 3: Identify Where It Fails

Check your error log to see which step appears **last**:

| Last Step | What It Means | Solution |
|-----------|--------------|----------|
| Step 3 | Invalid input | Check query is not empty and < 500 chars |
| Step 5-7 | Content aggregation failure | Network issue or aggregation API down |
| Step 9 | Gemini API failed | API key expired or rate limited |
| Step 12 | Page generation failed | Memory or HTML formatting issue |
| Step 15-16 | Database error | Check database connection in config |
| Step 21-22 | JSON encoding failed | HTML too large or contains invalid chars |
| Step 23 | Response sending failed | Connection dropped or headers sent twice |
| No steps | Script didn't start | PHP parsing error or whitespace before `<?php` |

## Common Solutions

### Issue: "Memory peak: XXX MB" is very high
**Solution:** The generated HTML is too large. Try:
- Shorter queries (e.g., "AI" instead of "artificial intelligence and machine learning overview")
- Edit `includes/AdvancedPageGenerator.php` to reduce content sections
- Increase Hostinger's PHP memory limit (usually `192MB` or `256MB`)

### Issue: Step 19 shows very long generation time (>60s)
**Solution:** Timeout during AI generation. Try:
- Simpler query (fewer API calls)
- Enable `skip_aggregation` to skip content fetching
- Or increase timeout in `api/ai-search.php` from 300s to 600s

### Issue: Database error at Step 15-16
**Solution:** Database credentials or connection issue:
- Check `includes/config.php` has correct credentials
- Verify database exists and tables are created
- Run `public_html/install.php` to create tables

### Issue: JSON encoding error at Step 21-22
**Solution:** Response too large for JSON:
- The fallback should handle this automatically
- If it still fails, reduce HTML content in generator
- Or split response: send metadata only, fetch HTML separately

### Issue: No steps logged at all
**Solution:** PHP error before logging starts:
- Check there's **no whitespace or BOM** before `<?php` tag
- Check syntax in `api/ai-search.php`
- Look for PHP parse errors in error logs

## Enable Extra Debug Mode

To see more details, edit `includes/config.php`:

```php
define('DEBUG_MODE', true);  // Change to true
```

Then after generation fails, check logs for detailed stack traces.

## Browser Console Debug

While generating a page:
1. Open **Developer Tools** (F12)
2. Go to **Console** tab
3. Generate a page
4. Look for detailed error messages printed

## Still Having Issues?

When reporting the issue, include:

1. **Last step from error log** (e.g., "Failed at Step 12")
2. **Error message** (e.g., "Call to undefined method...")
3. **Query used** (e.g., "artificial intelligence")
4. **Time it took** before failing (e.g., "5 seconds")
5. **Memory usage** from logs

Then I can provide a targeted fix!

## Reset Everything

If nothing works, reset to a clean state:

```bash
# Delete old logs
rm /home/u757840095/domains/intebwio.com/public_html/logs/intebwio.log

# Regenerate database tables
Visit: https://intebwio.com/install.php

# Test again with simple query
Go to: https://intebwio.com/landing-page-generator.html
Enter: "test"
```

## Monitor Generation in Real-Time

SSH into Hostinger (if available) and tail the log:

```bash
ssh user@intebwio.com
tail -f /home/u757840095/domains/intebwio.com/public_html/logs/intebwio.log
```

Then generate a page in browser and watch real-time logs!

---

**Next Step:** Check your error logs and report which step fails. I'll provide the exact fix! 🔍
