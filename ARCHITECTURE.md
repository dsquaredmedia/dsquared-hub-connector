# Dsquared Hub Connector — WordPress Plugin Architecture

## Plugin Structure
```
dsquared-hub-connector/
├── dsquared-hub-connector.php      # Main plugin file (bootstrap, hooks, activation)
├── readme.txt                       # WordPress.org readme
├── README.md                        # GitHub readme
├── uninstall.php                    # Clean uninstall
├── includes/
│   ├── class-dhc-core.php           # Core singleton, module loader
│   ├── class-dhc-api-key.php        # API key validation + subscription check
│   ├── class-dhc-admin.php          # Admin settings page (Hub-styled)
│   ├── class-dhc-rest.php           # REST API endpoint registration
│   └── modules/
│       ├── class-dhc-auto-post.php      # Module 1: Auto-Post to Draft
│       ├── class-dhc-schema.php         # Module 2: Schema Injector
│       ├── class-dhc-seo-meta.php       # Module 3: SEO Meta Sync
│       └── class-dhc-site-health.php    # Module 4: Site Health Monitor
├── admin/
│   ├── css/
│   │   └── dhc-admin.css            # Hub-matching admin styles
│   └── js/
│       └── dhc-admin.js             # Admin page interactions
└── assets/
    └── dhc-site-health.js           # Frontend CWV reporting script
```

## Design System (matching Hub backend)
- Font: Plus Jakarta Sans (loaded from Google Fonts)
- Primary BG: #0F1629 (dark navy)
- Card BG: #1A2035
- Accent: #5661FF (Hub accent blue)
- Text Primary: #F1F3F8
- Text Muted: #8892A8
- Border: #2A3150
- Success: #22C55E
- Warning: #F59E0B
- Error: #E8466D
- Border Radius: 12px (cards), 8px (inputs), 6px (buttons)

## API Key & Subscription Flow
1. User enters API key in plugin settings
2. Plugin validates key against Hub API: GET /api/plugin/validate-key
3. Hub returns: { valid: true, tier: "growth", expires: "2026-05-01", modules: [...] }
4. Plugin caches validation for 12 hours (transient)
5. Each module checks if it's enabled for the current tier before executing
6. On expiry: modules stop executing, admin shows warning banner, site is NOT affected

## Graceful Degradation
- Plugin deactivation: removes all hooks, leaves data intact
- Subscription lapse: modules become dormant, admin shows renewal notice
- No site breakage ever — all features are additive only
- Clear messaging: "Your Dsquared Hub subscription has lapsed. Features are currently disabled but your website is unaffected. Renew your subscription to restore full functionality."

## REST Endpoints (registered by plugin)
- POST /wp-json/dsquared-hub/v1/post     → Auto-post content as draft
- POST /wp-json/dsquared-hub/v1/schema   → Push schema markup for a page/post
- POST /wp-json/dsquared-hub/v1/seo-meta → Push meta title/description
- GET  /wp-json/dsquared-hub/v1/status   → Health check + plugin info
- POST /wp-json/dsquared-hub/v1/health   → Receive CWV data from frontend

## Module Tier Mapping
| Module | Starter | Growth | Pro |
|--------|---------|--------|-----|
| Auto-Post Draft | ✓ | ✓ | ✓ |
| Schema Injector | ✗ | ✓ | ✓ |
| SEO Meta Sync | ✗ | ✓ | ✓ |
| Site Health Monitor | ✗ | ✗ | ✓ |
