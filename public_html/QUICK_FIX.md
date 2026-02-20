# Quick Fix Guide - Gemini API Error

## TL;DR

**Your API key is disabled.** Get a new one and update the config file.

## 3-Minute Fix

### 1️⃣ Get New API Key (1 minute)
- Go to: https://aistudio.google.com/apikey
- Click "Create API Key"
- Copy it

### 2️⃣ Update Config (1 minute)
Edit `/workspaces/intebwio/public_html/includes/config.php`:
```php
define('GEMINI_API_KEY', 'PASTE_YOUR_NEW_KEY_HERE');
```

### 3️⃣ Test (1 minute)
```bash
cd /workspaces/intebwio/public_html
php test-gemini-api.php
```

Done! ✅

---

## Do You Want Alternatives?

### Option A: Keep Using Gemini
Follow the 3-minute fix above.

### Option B: Use OpenAI Instead
```php
define('OPENAI_API_KEY', 'sk-YOUR_KEY');
define('AI_PROVIDER', 'openai');
```

### Option C: Use Claude (Anthropic)
```php
define('ANTHROPIC_API_KEY', 'YOUR_KEY');
define('AI_PROVIDER', 'anthropic');
```

### Option D: No AI API (Fallback Only)
```php
define('AI_PROVIDER', 'fallback');
```
Uses content from configured sources only, no AI.

---

## Diagnostic Scripts

```bash
# Check complete status
php /workspaces/intebwio/public_html/diagnose-gemini.php

# Test API directly
php /workspaces/intebwio/public_html/test-gemini-api.php

# List available models
php /workspaces/intebwio/public_html/list-gemini-models.php
```

---

## What Was Fixed in Code

✅ Better error messages  
✅ Detect disabled API keys  
✅ Log all failures properly  
✅ Improved response validation  
✅ cURL error detection  

Check logs: `/workspaces/intebwio/public_html/logs/intebwio.log`

---

**Questions?** See `ISSUE_SUMMARY.md` or `GEMINI_API_FIX.md`
