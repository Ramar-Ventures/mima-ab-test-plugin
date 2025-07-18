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
     
    
    public function __construct() {
        add_action('wp_ajax_nopriv_ab_test_check', array($this, 'handle_ajax_request'));
        add_action('wp_ajax_ab_test_check', array($this, 'handle_ajax_request'));
    }
    
    public function handle_ajax_request(): void
    {
        $target_url = $this->determine_target_url();

        
        if($this->has_bypass_cookie()) {
            wp_redirect($target_url, 302);
            exit;
        }

        if($this->should_bypass_test($target_url)) {
            $this->set_ab_test_cookie('bypass');
            wp_redirect($target_url, 302);
            exit;
        }

        if (!$this->lottery_check()) {
            $this->set_ab_test_cookie('lottery');
            // Redirect back to the determined URL
            wp_redirect($target_url, 302);
            exit;
        }

        // Prevent browser caching of the redirect
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        wp_redirect(self::VARIANT_URL, 302);
        exit;
    }
    
    private function determine_target_url(): string
    {
        $original_path = $_GET['original_path'] ?? '';
        if(empty($original_path)) {
            return home_url('/');
        }

        // Parse the original path to get just the path part
        $parsed_url = parse_url($original_path);
        $path = $parsed_url['path'] ?? '/';

        // Get all query parameters from $_GET and filter out AB test parameters
        $query_params = $_GET;
        unset($query_params['action']);
        unset($query_params['ab_test_check']);
        unset($query_params['original_path']);

        // Rebuild the query string if there are remaining parameters
        if (!empty($query_params)) {
            $filtered_query = http_build_query($query_params);
            return home_url($path . '?' . $filtered_query);
        }

        return home_url($path);
    }

    private function lottery_check(): bool
    {
        // Use a deterministic approach based on current timestamp
        // This creates a predictable cycle that approximates the desired ratio
        $seed = floor(time() / 10); // Changes every 10 seconds
        $hash = crc32($seed . 'ab_test_salt');
        $percentage = abs($hash) % 100;
        
        return $percentage < self::AB_SPLIT_RATIO;
    }
    
    private function set_ab_test_cookie(string $value): void
    {
        setcookie(
            name: self::AB_TEST_COOKIE,
            value: $value,
            expires_or_options: time() + self::AB_TEST_COOKIE_DURATION,
            path: self::AB_TEST_COOKIE_PATH
        );
    }
    
    private function should_bypass_test(string $target_url = ''): bool 
    {
        $is_non_get = $this->is_non_get_request();
        $is_xmlrpc = (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST);
        $is_rest = (defined('REST_REQUEST') && REST_REQUEST);
        $is_bot = $this->is_bot();
        $is_login = $this->is_login_page($target_url);
        $is_api = $this->is_api_request($target_url);
        $is_wp_core = $this->is_wordpress_core_file($target_url);
        $is_logged_in = is_user_logged_in();
        $is_webhook = $this->is_webhook_request($target_url);
        $is_woocommerce = $this->is_woocommerce_page($target_url);
        $is_post_blog = $this->is_post_or_blog($target_url);
        $has_wc_session = $this->has_woocommerce_session();
                
        return
            $is_non_get ||
            $is_xmlrpc ||
            $is_rest ||
            $is_bot ||
            $is_login ||
            $is_api ||
            $is_wp_core ||
            $is_logged_in ||
            $is_webhook ||
            $is_woocommerce ||
            $is_post_blog ||
            $has_wc_session;
    }

    private function has_bypass_cookie(): bool
    {
        return isset($_COOKIE[self::AB_TEST_COOKIE]);
    }
    
    private function is_bot(): bool 
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_pattern = '/(?:googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|facebookexternalhit|twitterbot|crawler|spider|robot|crawling|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|check)/i';
        
        return preg_match($bot_pattern, $user_agent) === 1;
    }
    
    private function is_login_page(string $target_url = '') {
        global $pagenow;
        $request_uri = !empty($target_url) ? parse_url($target_url, PHP_URL_PATH) : ($_SERVER['REQUEST_URI'] ?? '');
        
        return 
            $pagenow === 'wp-login.php' ||
            str_contains($request_uri, 'wp-login.php') ||
            str_contains($request_uri, 'wp-admin') ||
            $GLOBALS['pagenow'] === 'wp-login.php'
        ;
    }
    
    private function is_api_request(string $target_url = '') {
        $request_uri = !empty($target_url) ? parse_url($target_url, PHP_URL_PATH) : ($_SERVER['REQUEST_URI'] ?? '');
        
        return 
            str_contains($request_uri, '/wp-json/') ||
            str_contains($request_uri, 'admin-ajax.php')
        ;
    }
    
    private function is_webhook_request(string $target_url = '') {
        $request_uri = !empty($target_url) ? parse_url($target_url, PHP_URL_PATH) : ($_SERVER['REQUEST_URI'] ?? '');
        
        return 
            strpos($request_uri, '/webhook') !== false ||
            strpos($request_uri, '/wc-api/') !== false ||
            strpos($request_uri, '/wp-json/wc/') !== false ||
            strpos($request_uri, '/wc-webhook/') !== false
        ;
    }
    
    private function is_woocommerce_page(string $target_url = ''): bool 
    {
        if (!empty($target_url)) {
            // For target URL, we need to check the page type by URL patterns
            $path = parse_url($target_url, PHP_URL_PATH);
            return 
                str_contains($path, '/cart') ||
                str_contains($path, '/checkout') ||
                str_contains($path, '/my-account') ||
                str_contains($path, '/shop');
        }
        
        return is_cart() || is_checkout() || is_account_page();
    }
    
    private function is_post_or_blog(string $target_url = '') : bool
    {
        if (!empty($target_url)) {
            // For target URL, we need to get the post ID and check its type
            $post_id = url_to_postid($target_url);
            if ($post_id) {
                return get_post_type($post_id) === 'post';
            }
            // If we can't determine the post ID, check URL patterns
            $path = parse_url($target_url, PHP_URL_PATH);
            return str_contains($path, '/blog') || preg_match('/\/\d{4}\/\d{2}\//', $path);
        }
        
        return get_post_type() === 'post';
    }
    
    private function is_wordpress_core_file(string $target_url = ''): bool
    {
        $request_uri = !empty($target_url) ? parse_url($target_url, PHP_URL_PATH) : ($_SERVER['REQUEST_URI'] ?? '');
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