# Dsquared Hub Connector

**Connect your WordPress site to the Dsquared Media Hub** — auto-post drafts, inject schema markup, sync SEO meta, and monitor site health. All features are subscription-gated and will gracefully disable if your subscription lapses without affecting your website.

![Version](https://img.shields.io/badge/version-1.0.0-5661FF)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![License](https://img.shields.io/badge/license-GPL--2.0-green)

---

## Overview

The Dsquared Hub Connector is a lightweight WordPress plugin that bridges your WordPress site with the [Dsquared Media Hub](https://hub.dsquaredmedia.net). It enables seamless content publishing, SEO optimization, and performance monitoring — all controlled from the Hub dashboard.

### Key Features

| Module | Description | Tier |
|--------|-------------|------|
| **Auto-Post to Draft** | Receive blog content from the Hub and create WordPress draft posts | Starter+ |
| **Schema Injector** | Push JSON-LD structured data from the Hub's Schema Generator | Growth+ |
| **SEO Meta Sync** | Sync meta titles, descriptions, and OG data (Yoast/RankMath compatible) | Growth+ |
| **Site Health Monitor** | Collect real-user Core Web Vitals and report to the Hub | Pro |

---

## Installation

1. Download the plugin ZIP file or clone this repository
2. Upload to `wp-content/plugins/dsquared-hub-connector/`
3. Activate the plugin in WordPress Admin → Plugins
4. Navigate to **Dsquared Hub** in the admin sidebar
5. Enter your API key from [Hub → Account → API Keys](https://hub.dsquaredmedia.net/dashboard.html#account)
6. Enable the modules you want to use

---

## Subscription & Graceful Degradation

This plugin is designed with a **zero-disruption guarantee**:

- If the plugin is **disabled** or your **subscription lapses**, it will **not interrupt your website** in any way
- Hub features will simply become unavailable until reactivated
- Your existing content, schema markup, and SEO settings are preserved
- No frontend scripts are loaded when the subscription is inactive
- **Keeping an active subscription is suggested** for continued access to all features

### Tier Access

| Tier | Modules Available |
|------|-------------------|
| **Starter** | Auto-Post to Draft |
| **Growth** | Auto-Post, Schema Injector, SEO Meta Sync |
| **Pro** | All modules |

---

## REST API Endpoints

All endpoints are available at `your-site.com/wp-json/dsquared-hub/v1/`

### Status Check
```
GET /dsquared-hub/v1/status
```
Returns plugin version, connection status, and module availability. No authentication required.

### Auto-Post to Draft
```
POST /dsquared-hub/v1/post
Header: X-DHC-API-Key: your-api-key
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `title` | string | Yes | Post title |
| `content` | string | Yes | Post body (HTML) |
| `excerpt` | string | No | Post excerpt |
| `categories` | array | No | Category names (created if they don't exist) |
| `tags` | array | No | Tag names |
| `featured_image_url` | string | No | URL to download as featured image |
| `meta` | object | No | Custom meta fields (prefixed with `_dhc_`) |

**Response:** Post is created as a **draft** — never auto-published.

### Schema Injector
```
POST /dsquared-hub/v1/schema
Header: X-DHC-API-Key: your-api-key
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `schema` | object/string | Yes | JSON-LD schema markup |
| `post_id` | integer | No | Target post ID |
| `url` | string | No | Target page URL (resolved to post ID) |
| `schema_type` | string | No | Schema type identifier (e.g., "Article", "LocalBusiness") |

### SEO Meta Sync
```
POST /dsquared-hub/v1/seo-meta
Header: X-DHC-API-Key: your-api-key
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `post_id` | integer | No | Target post ID |
| `url` | string | No | Target page URL |
| `meta_title` | string | No | SEO title |
| `meta_description` | string | No | Meta description |
| `focus_keyword` | string | No | Focus keyword |
| `og_title` | string | No | Open Graph title |
| `og_description` | string | No | Open Graph description |

Compatible with: **Yoast SEO**, **Rank Math**, **All in One SEO**, **SEOPress**. Falls back to native meta output if no SEO plugin is detected.

### Site Health Data
```
POST /dsquared-hub/v1/health
```
Receives Core Web Vitals data from the frontend script. No API key required (data comes from site visitors).

---

## SEO Plugin Compatibility

The SEO Meta Sync module automatically detects and writes to the correct meta fields for:

- **Yoast SEO** — `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`
- **Rank Math** — `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`
- **All in One SEO** — `_aioseo_title`, `_aioseo_description`, `_aioseo_keyphrases`
- **SEOPress** — `_seopress_titles_title`, `_seopress_titles_desc`

If no SEO plugin is detected, the plugin outputs meta tags directly in `wp_head`.

---

## Site Health Monitor

The Site Health module injects a lightweight (~2KB) JavaScript snippet that collects:

- **LCP** — Largest Contentful Paint
- **FID** — First Input Delay
- **CLS** — Cumulative Layout Shift
- **INP** — Interaction to Next Paint
- **TTFB** — Time to First Byte
- **FCP** — First Contentful Paint

Data is collected from real users (not synthetic tests) and reported as p75 values. The script:

- Uses `PerformanceObserver` API (no dependencies)
- Reports via `navigator.sendBeacon` for reliability
- Excludes logged-in editors and admin users
- Supports configurable sample rates for high-traffic sites

---

## File Structure

```
dsquared-hub-connector/
├── dsquared-hub-connector.php    # Main plugin file
├── uninstall.php                 # Clean removal
├── README.md                     # This file
├── includes/
│   ├── class-dhc-core.php        # Plugin controller (singleton)
│   ├── class-dhc-api-key.php     # API key validation & subscription
│   ├── class-dhc-rest.php        # REST API endpoint registration
│   ├── class-dhc-admin.php       # Admin settings page
│   └── modules/
│       ├── class-dhc-auto-post.php   # Module 1: Auto-Post
│       ├── class-dhc-schema.php      # Module 2: Schema Injector
│       ├── class-dhc-seo-meta.php    # Module 3: SEO Meta Sync
│       └── class-dhc-site-health.php # Module 4: Site Health
├── admin/
│   ├── css/dhc-admin.css         # Admin styles (Hub design)
│   └── js/dhc-admin.js           # Admin interactions
└── assets/
    └── dhc-site-health.js        # Frontend CWV script
```

---

## Future Roadmap

| Version | Planned Modules |
|---------|----------------|
| **v1.5** | Form Submission Tracker, GA4 Event Tracker, Content Freshness Monitor |
| **v2.0** | AI Citation Optimizer, Redirect Manager, White-Label Widget |
| **v3.0** | WooCommerce Sync, Competitor Content Alerts, Conversion Attribution |

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Active Dsquared Media Hub subscription
- REST API enabled (default in WordPress)

---

## Support

For support, visit the [Dsquared Media Hub Help Center](https://hub.dsquaredmedia.net/dashboard.html#help-center) or contact [support@dsquaredmedia.net](mailto:support@dsquaredmedia.net).

---

**Built by [Dsquared Media](https://dsquaredmedia.net)** — Digital Marketing, Web Design & AI Solutions
