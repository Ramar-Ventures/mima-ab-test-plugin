# A/B Testing Traffic Bypass Plugin

A WordPress plugin that intelligently redirects a percentage of website traffic to an external store (loja.jornadamima.com.br) while respecting various bypass conditions for A/B testing purposes.

## Overview

This plugin implements a smart traffic distribution system that redirects 20% of qualified visitors to an external WooCommerce store while ensuring critical WordPress and WooCommerce functionality remains unaffected.

## Features

- **Smart Traffic Splitting**: Redirects 20% of eligible traffic to the external store
- **Intelligent Bypass Logic**: Automatically excludes bots, logged-in users, and critical requests
- **WooCommerce Integration**: Preserves shopping cart sessions and checkout processes
- **Bot Detection**: Recognizes and excludes search engine crawlers and monitoring services
- **API Protection**: Bypasses REST API, AJAX, and webhook requests
- **Admin Safety**: Never redirects admin users or WordPress backend requests

## How It Works

### Traffic Distribution
- **20% of traffic** → Redirected to `https://loja.jornadamima.com.br`
- **80% of traffic** → Stays on the current WordPress site

### Bypass Conditions

The plugin will **NOT** redirect traffic in the following scenarios:

#### User-Based Bypasses
- Logged-in WordPress users
- Admin area access (`wp-admin`)
- Login page access (`wp-login.php`)

#### Request-Based Bypasses
- Non-GET requests (POST, PUT, DELETE, etc.)
- API requests (`/wp-json/`, `admin-ajax.php`)
- Webhook requests (`/wc-api/`, `/webhook/`)
- WordPress core files (`wp-content`, `wp-includes`, etc.)

#### WooCommerce Bypasses
- Cart page visitors
- Checkout page visitors
- Account page visitors
- Users with active WooCommerce sessions
- Users with items in their cart

#### Bot Detection
- Search engine crawlers (Google, Bing, etc.)
- Social media bots (Facebook, Twitter)
- Monitoring services (Lighthouse, PageSpeed, GTmetrix, etc.)
- General web crawlers and spiders

#### Content-Based Bypasses
- Blog posts and post-type content
- WordPress feeds and sitemaps

## Technical Details

### Constants
- `LOJA_URL`: Target redirect URL (`https://loja.jornadamima.com.br`)
- `AB_SPLIT_RATIO`: Percentage of traffic to redirect (20%)
- `AB_TEST_COOKIE`: Cookie name for future session tracking
- `AB_TEST_COOKIE_DURATION`: Cookie lifetime (1 day)

### Implementation
- Hooks into WordPress `init` action with high priority
- Uses `random_int(1, 100)` for lottery-based traffic splitting
- Performs 302 (temporary) redirects to preserve SEO
- Comprehensive user agent detection for bot identification
