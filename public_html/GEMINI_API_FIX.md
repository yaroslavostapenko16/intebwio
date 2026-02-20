# Gemini API Error - Resolution Guide

## Problem Found ❌

Your Gemini API key has been **reported as leaked** and is now **disabled by Google**. This is why content generation is failing.

```
Error: "Your API key was reported as leaked. Please use another API key."
HTTP Status: 403 PERMISSION_DENIED
```

## Root Causes

1. **Disabled API Key**: The key `AIzaSyBbgKuLh-pYnG2S-3woVM53_1cdnuwxino` is publicly exposed
2. **Outdated Model**: Old code was using `gemini-pro` which is deprecated (now requires `gemini-1.5-pro`)
3. **Poor Error Handling**: No detailed error logging to diagnose the issue

## Solutions

### ✅ Solution 1: Generate a New Gemini API Key (RECOMMENDED)

1. Go to [Google AI Studio](https://aistudio.google.com/apikey)
2. Click "Create API Key"
3. Select your project or create a new one
4. Copy the new API key

**Then update the config:**

Edit `/workspaces/intebwio/public_html/includes/config.php`:

```php
define('GEMINI_API_KEY', 'YOUR_NEW_API_KEY_HERE');
define('AI_PROVIDER', 'gemini');
```

### ✅ Solution 2: Use a Different AI Provider

If you don't want to get a new Gemini API key, switch to OpenAI or Anthropic:

**For OpenAI:**
```php
define('OPENAI_API_KEY', 'sk-YOUR_OPENAI_KEY');
define('AI_PROVIDER', 'openai');
```

**For Anthropic Claude:**
```php
define('ANTHROPIC_API_KEY', 'YOUR_CLAUDE_KEY');
define('AI_PROVIDER', 'anthropic');
```

### ✅ Solution 3: Use Fallback Content Generation

If you don't want to use any AI API right now, the application will automatically fall back to aggregating content from configured sources:

```php
define('AI_PROVIDER', 'fallback');
```

## Improvements Made ✅

I've updated the code to:

1. **Better error logging** - All API errors are now logged with HTTP status and error details
2. **Curl error detection** - Captures network and connectivity errors
3. **Response validation** - Checks response structure before parsing
4. **User-friendly messages** - Detects leaked keys and suggests getting a new one
5. **Model management** - Uses the correct model name in the API call

## Files Modified

- `/workspaces/intebwio/public_html/includes/AIService.php`
  - Enhanced `callGemini()` with better error logging
  - Enhanced `callOpenAI()` with better error logging
  - Enhanced `callAnthropic()` with better error logging
  - Improved `generatePageContent()` logging
  - Fixed model name handling

## Test the Fix

After getting a new API key:

```bash
cd /workspaces/intebwio/public_html
php test-gemini-api.php
```

Should see:
```
✅ API Request Successful!
```

## Diagnostic Tools Created

1. **test-gemini-api.php** - Tests Gemini API directly and shows detailed errors
2. **list-gemini-models.php** - Lists all available models for your API key

## Prevention Tips

- **Never commit API keys** to public repositories
- Store sensitive keys in environment variables
- Use key restrictions in Google Cloud Console
- Rotate keys regularly
- Monitor API key usage in Google Cloud Console

---

**Need Help?**
- Check error logs in `/workspaces/intebwio/public_html/logs/intebwio.log`
- Run diagnostic test: `php test-gemini-api.php`
- View model list: `php list-gemini-models.php`
