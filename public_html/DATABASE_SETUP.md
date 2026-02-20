# Intebwio Database Setup (New Schema)

Since you cleared your database tables, follow these steps to set up the new schema:

## Option 1: Quick Setup (Recommended)

### Step 1: Upload SQL to Hostinger

You have two ways to run the SQL:

**A) Via phpMyAdmin (easiest):**
1. Log into your Hostinger cPanel
2. Go to **phpMyAdmin**
3. Select your database: `u757840095_Intebwio`
4. Click **Import** tab
5. Upload the file: `intebwio_new_schema.sql`
6. Click **Go** to execute

**B) Via Hostinger File Manager:**
1. The SQL file is at `public_html/intebwio_new_schema.sql`
2. Download it or keep it in the public_html folder

### Step 2: Verify Tables Created

After importing, you should see these tables:
- `pages` - Stores generated pages with slug for URL
- `search_results` - Cached search/aggregation results
- `activity` - User view tracking
- `analytics` - Daily analytics
- `recommendations` - Recommended pages
- `page_cache` - Cache layer
- `settings` - Application settings

---

## Option 2: Manual Setup (If Option 1 fails)

### Via SSH (if available):

```bash
ssh youruser@intebwio.com

cd /home/u757840095/domains/intebwio.com/public_html

mysql -u u757840095_Yaroslav -p u757840095_Intebwio < intebwio_new_schema.sql
```

(Enter password: `l1@ArIsM`)

---

## How URLs Work Now

### Page Generation Flow:

1. **User generates a page** for topic "artificial intelligence"
2. **System creates:**
   - Database entry in `pages` table
   - URL slug: `artificial-intelligence`
   - Unique URL: `/?q=artificial%20intelligence&slug=artificial-intelligence`

3. **User can access page via:**
   - `https://intebwio.com/?q=artificial%20intelligence&slug=artificial-intelligence`
   - Or simply: `https://intebwio.com/view.php?q=artificial%20intelligence&slug=artificial-intelligence`

4. **Each generation creates NEW entry** (ID auto-increments)

### URL Format Examples:

```
Generated topic: "computer science"
Slug: computer-science
URL: /?q=computer%20science&slug=computer-science

Generated topic: "machine learning AI"
Slug: machine-learning-ai
URL: /?q=machine%20learning%20AI&slug=machine-learning-ai

Generated topic: "web development 2024"
Slug: web-development-2024
URL: /?q=web%20development%202024&slug=web-development-2024
```

---

## Database Schema Details

### Pages Table Structure:

```sql
CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- Unique ID
    query VARCHAR(500) NOT NULL,                  -- Original search query
    slug VARCHAR(500) UNIQUE NOT NULL,            -- URL-friendly slug
    title VARCHAR(255) NOT NULL,                  -- Page title
    description TEXT,                             -- Meta description
    html_content LONGTEXT NOT NULL,               -- Full HTML content
    ai_provider VARCHAR(50),                      -- 'gemini' or 'openai'
    ai_model VARCHAR(100),                        -- 'gemini-2.5-flash', etc.
    view_count INT DEFAULT 0,                     -- Number of views
    status VARCHAR(50) DEFAULT 'active',          -- 'active' or 'archived'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Testing the Setup

### Test 1: Generate a Page
1. Go to: `https://intebwio.com/landing-page-generator.html`
2. Enter: `test`
3. Click: "Generate Page"
4. You should see:
   - ✓ Page generated successfully
   - **Page URL:** `/?q=test&slug=test`
   - Option to view the new page

### Test 2: View Generated Page
1. Click the "View Page" link from the success message
2. It should open: `https://intebwio.com/?q=test&slug=test`
3. The generated content displays
4. View count increases

### Test 3: Direct Database Check
SSH into Hostinger:
```bash
mysql -u u757840095_Yaroslav -p u757840095_Intebwio

SELECT id, query, slug, status FROM pages LIMIT 5;
```

You should see your generated pages listed.

---

## Troubleshooting

### Issue: Tables Not Created
- Check phpMyAdmin shows the tables
- Run `intebwio_new_schema.sql` manually
- If error about "column name is reserved", edit the SQL and use backticks

### Issue: Page URL Not Working
- Check slug contains only letters, numbers, hyphens
- Ensure `view.php` exists in `public_html`
- Check error logs at: `/public_html/logs/intebwio.log`

### Issue: View Count Not Updating
- Verify `activity` and `analytics` tables exist
- Check database permissions for INSERT statements
- Check `view.php` runs without errors

---

## Next Steps

1. **Import the SQL schema** (Option 1 recommended)
2. **Test page generation** with the landing page generator
3. **Check database** to verify pages are being stored
4. **Share error logs** if anything fails

Once tables are created, the system will:
- ✓ Generate unique URLs for each topic
- ✓ Store pages with unique slugs
- ✓ Track view counts
- ✓ Record user activity
- ✓ Support analytics

---

Need help? Check the error logs in `/public_html/logs/intebwio.log` after attempting to generate a page!
