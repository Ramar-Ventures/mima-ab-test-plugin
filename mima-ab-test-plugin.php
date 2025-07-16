<?php

/**
 * Plugin Name: A/B Testing Traffic Bypass
 * Description: Redirect traffic to loja.jornadamima.com.br respecting A/B testing rules
 * Version: 1.0.0
 * Author: Marllon Gomes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ABTestingTrafficBypass {
    
    private const VARIANT_URL = 'https://loja.jornadamima.com.br';
    private const AB_SPLIT_RATIO = 20; // 20% of traffic goes to loja
    private const AB_TEST_COOKIE = 'ab_test_bypass';
    private const AB_TEST_COOKIE_DURATION = 86400; // 1 day
    private const AB_TEST_COOKIE_PATH = '/';
    private const AB_TEST_COOKIE_HTTP_ONLY = true;
    private const AB_TEST_COOKIE_DOMAIN = 'jornadamima.com.br';
     
    
    public function __construct() {
        add_action('init', array($this, 'handle', -99999999)); // High priority to run before any other init actions
    }
    
    public function handle(): void
    {        
        if($this->should_bypass_test()) {
            return;
        }

        $should_redirect = $this->lottery_check();
        if (!$should_redirect) {
            $this->set_ab_test_cookie();
            return;
        }

        wp_redirect(self::VARIANT_URL, 302);
        exit;
    }

    private function lottery_check(): bool
    {
        return random_int(1, 100) <= self::AB_SPLIT_RATIO;
    }

    private function set_ab_test_cookie(): void
    {
        setcookie(
            name: self::AB_TEST_COOKIE,
            value: 'bypass',
            expires_or_options: time() + self::AB_TEST_COOKIE_DURATION,
            path: self::AB_TEST_COOKIE_PATH,
            domain: self::AB_TEST_COOKIE_DOMAIN,
            httponly: self::AB_TEST_COOKIE_HTTP_ONLY
        );
    }
    
    private function should_bypass_test(): bool 
    {
        return
            $this->is_non_get_request() ||
            $this->is_bot() ||
            $this->is_login_page() ||
            $this->is_api_request() ||
            $this->is_wordpress_core_file() ||
            is_user_logged_in() ||
            is_admin() ||
            $this->is_webhook_request() ||
            $this->is_woocommerce_page() ||
            $this->is_post_or_blog() ||
            $this->has_woocommerce_session();
    }
    
    private function is_bot(): bool 
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_pattern = '/(?:googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|facebookexternalhit|twitterbot|crawler|spider|robot|crawling|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|check)/i';
        
        return preg_match($bot_pattern, $user_agent) === 1;
    }
    
    private function is_login_page() {
        global $pagenow;
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return (
            $pagenow === 'wp-login.php' ||
            str_contains($request_uri, 'wp-login.php') ||
            str_contains($request_uri, 'wp-admin') ||
            $GLOBALS['pagenow'] === 'wp-login.php'
        );
    }
    
    private function is_api_request() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return 
            str_contains($request_uri, '/wp-json/') ||
            str_contains($request_uri, 'admin-ajax.php')||
            defined('REST_REQUEST') && REST_REQUEST ||
            defined('DOING_AJAX') && DOING_AJAX
        ;
    }
    
    private function is_webhook_request() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return 
            strpos($request_uri, '/webhook') !== false ||
            strpos($request_uri, '/wc-api/') !== false ||
            strpos($request_uri, '/wp-json/wc/') !== false ||
            strpos($request_uri, '/wc-webhook/') !== false
        ;
    }
    
    private function is_woocommerce_page(): bool 
    {
        return is_cart() || is_checkout() || is_account_page();
    }
    
    private function is_post_or_blog() : bool
    {
        return get_post_type() === 'post';
    }
    
    private function is_wordpress_core_file(): bool
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $core_pattern = '/\/(wp-content|wp-includes|wp-admin|wp-json)|\/xmlrpc\.php|\/robots\.txt|\/sitemap\.xml|\/feed/';
        
        return preg_match($core_pattern, $request_uri) === 1;
    }
    
    private function has_woocommerce_session() {
        // Check for WooCommerce session cookies
        $wc_cookies = [
            'woocommerce_cart_hash',
            'woocommerce_items_in_cart',
            'wp_woocommerce_session_'
        ];

        foreach ($_COOKIE as $name=>$value) {
            foreach ($wc_cookies as $wc_cookie) {
                if (str_contains($name, $wc_cookie) === 0) {
                    return true;
                }
            }
        }
        
        if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            return true;
        }
        
        return false;
    }
    
    private function is_non_get_request(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET';
    }
}

// Initialize the plugin
new ABTestingTrafficBypass();