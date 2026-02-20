# Intebwio - New URL & Database System

## Summary of Changes

Your system now generates pages with **unique, shareable URLs** for each topic. Each generation creates a new entry in the database with a URL-friendly slug.

---

## How It Works

### User Workflow:

1. **Visit Landing Page Generator**
   ```
   https://intebwio.com/landing-page-generator.html
   ```

2. **Enter Topic** (e.g., "Artificial Intelligence")

3. **System Generates:**
   - Unique page ID in database
   - URL slug: `artificial-intelligence`
   - Shareable URL: `/?q=Artificial%20Intelligence&slug=artificial-intelligence`

4. **Result:**
   - ✓ Page stored in `pages` table with unique slug
   - ✓ Click "View Page" link or share the URL
   - ✓ Visit count tracked automatically
   - ✓ Each generation = new unique page

---

## URL Format

### Standard Format:
```
https://intebwio.com/?q=QUERY&slug=SLUG-NAME

Example:
https://intebwio.com/?q=machine%20learning&slug=machine-learning
https://intebwio.com/?q=web%20development&slug=web-development
https://intebwio.com/?q=python%20programming&slug=python-programming
```

### Via view.php:
```
https://intebwio.com/view.php?q=QUERY&slug=SLUG-NAME

Also works:
https://intebwio.com/view.php?s=SLUG-NAME  (shorthand)
```

### What Each Parameter Does:
- **`q` (query)**: The original search term entered by user
- **`slug`**: URL-friendly version (lowercase, hyphenated)
- **`s`**: Shorthand for slug (alternative syntax)

---

## Database Schema

### New Tables Created:

```
✓ pages              - Stores generated pages with slug
✓ search_results     - Cached source content
✓ activity           - Tracks user views and interactions
✓ analytics          - Daily page statistics
✓ recommendations    - Featured/recommended pages
✓ page_cache         - Cache layer for performance
✓ settings           - Application settings
```

### Key Table: `pages`

```sql
pages (
  id              - Unique page ID (auto-increment)
  query           - Original search query ("Artificial Intelligence")
  slug            - URL-friendly version ("artificial-intelligence")
  title           - Page title
  description     - Meta description
  html_content    - Full generated HTML
  ai_provider     - "gemini" or "openai"
  ai_model        - Model used (e.g., "gemini-2.5-flash")
  view_count      - Number of views (auto-incremented)
  status          - "active" or "archived"
  created_at      - Generation timestamp
  updated_at      - Last update timestamp
)
```

---

## Setup Instructions

### Step 1: Import Database Schema

**Option A - Via phpMyAdmin (Easiest):**
1. Open Hostinger cPanel → phpMyAdmin
2. Select database: `u757840095_Intebwio`
3. Click **Import**
4. Upload: `intebwio_new_schema.sql`
5. Click **Go**

**Option B - Via SSH:**
```bash
mysql -u u757840095_Yaroslav -p u757840095_Intebwio < intebwio_new_schema.sql
```

### Step 2: Deploy to Hostinger

Push from GitHub:
```bash
git push
```

All new files are included:
- ✓ `intebwio_new_schema.sql` - Database schema
- ✓ `DATABASE_SETUP.md` - Setup guide
- ✓ `view.php` - Page display handler
- ✓ Updated `api/ai-search.php` - Slug generation
- ✓ Updated `landing-page-generator.html` - URL display
- ✓ Updated `includes/Database.php` - Schema definition

### Step 3: Test

1. Visit: `https://intebwio.com/landing-page-generator.html`
2. Enter topic: "test"
3. Click "Generate Page"
4. You should see:
   - ✓ Page generated successfully
   - ✓ **Page URL:** `/?q=test&slug=test`
   - ✓ Click to view the generated page

---

## Example Generated Pages

After setup, you'll have URLs like:

```
Generated: "Artificial Intelligence"
URL: /?q=Artificial%20Intelligence&slug=artificial-intelligence
Slug: artificial-intelligence

Generated: "Machine Learning Basics"
URL: /?q=Machine%20Learning%20Basics&slug=machine-learning-basics
Slug: machine-learning-basics

Generated: "Web Development 2024"
URL: /?q=Web%20Development%202024&slug=web-development-2024
Slug: web-development-2024
```

---

## Key Features

### ✓ Unique URLs
- Every page generation creates a unique, shareable URL
- No conflicts - even if same topic requested twice, new unique slug generated
- URLs are human-readable and SEO-friendly

### ✓ View Tracking
- Visit count auto-increments each time page accessed
- Activity recorded (timestamp, IP, user agent)
- Analytics by date available

### ✓ Multiple Pages Per Topic
- Same topic can generate multiple different pages
- Each gets unique ID and slug
- Allows variations and A/B testing

### ✓ Slug Format
- Auto-generated from query
- Lowercase, hyphens only
- If conflict, timestamp+counter appended
- Examples: `test`, `artificial-intelligence`, `machine-learning-ai`

---

## Check Your Setup

### Via SSH:
```bash
ssh youruser@intebwio.com

# Login to MySQL
mysql -u u757840095_Yaroslav -p u757840095_Intebwio

# Check tables created
SHOW TABLES;

# Check generated pages
SELECT id, query, slug, view_count FROM pages LIMIT 10;
```

### Via phpMyAdmin:
1. Select database
2. Should see 7 tables:
   - pages
   - search_results
   - activity
   - analytics
   - recommendations
   - page_cache
   - settings

---

## Troubleshooting

### Issue: "Page not found" when accessing URL
- Check `view.php` exists in public_html
- Verify slug created correctly (no special chars)
- Check database has the page: `SELECT * FROM pages WHERE slug='your-slug'`

### Issue: View count not updating
- Ensure `activity` table exists
- Check INSERT permissions on database
- Review logs at `/public_html/logs/intebwio.log`

### Issue: Database import failed
- Check SQL syntax in phpMyAdmin console
- Verify all tables don't exist yet
- Try importing again or use SSH method

### Issue: URL shows "?q=test&slug=test" but page doesn't display
- Check `view.php` for PHP errors
- Verify `html_content` column has data
- Check browser console for JavaScript errors

---

## Files Modified/Created

```
✅ NEW FILES:
   - intebwio_new_schema.sql      (Database schema)
   - DATABASE_SETUP.md            (Setup guide)
   - view.php                     (Page display handler)
   - TROUBLESHOOTING_500_ERROR.md (Error guide)

✅ UPDATED FILES:
   - api/ai-search.php            (Added slug generation)
   - landing-page-generator.html  (Added URL display)
   - includes/Database.php        (Updated schema)
```

---

## Next Steps

1. **Import database schema** to Hostinger
2. **Deploy code** from GitHub
3. **Test page generation** with landing page generator
4. **Verify URLs** work correctly
5. **Share URLs** with others - each is unique and permanent!

---

## Support

Check error logs if anything fails:
```
/home/u757840095/domains/intebwio.com/public_html/logs/intebwio.log
```

After generating a page, this log will show which step succeeded/failed with detailed error messages!
