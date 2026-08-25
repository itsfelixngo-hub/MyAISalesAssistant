<?php
/**
 * Two guards for the move to a new server.
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Keep the port-testing phase out of the index.
 *
 * The plan is to run on http://<vps-ip>:8093 until DNS is switched. Google will
 * happily index that host if it finds it, and then the real domain competes
 * with a copy of itself. This disappears on its own the moment SITE_URL becomes
 * the real domain - nothing to remember to turn off.
 */
function alogweb_is_provisional_host() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    if (!$host) { return true; }
    if (filter_var($host, FILTER_VALIDATE_IP)) { return true; }
    if (strpos($host, '.') === false) { return true; }          // localhost
    $port = wp_parse_url(home_url(), PHP_URL_PORT);
    return !empty($port) && !in_array((int) $port, array(80, 443), true);
}

// wp_robots is the supported way in since WordPress 5.7; printing a second
// meta from wp_head just leaves two robots tags disagreeing with each other.
add_filter('wp_robots', function ($robots) {
    if (alogweb_is_provisional_host()) {
        return array('noindex' => true, 'nofollow' => true);
    }
    return $robots;
});

add_filter('robots_txt', function ($output) {
    if (alogweb_is_provisional_host()) {
        return "User-agent: *\nDisallow: /\n";
    }
    return $output;
}, 20);

/**
 * The live site declares Yoast's sitemap in robots.txt and Google has been
 * fetching /sitemap_index.xml for years. This theme has no Yoast, so those
 * paths would 404 the moment the new server takes over - throwing away a
 * discovery route Google already trusts. Redirect them at the core sitemap
 * instead of letting them die.
 */
add_action('template_redirect', function () {
    if (!is_404()) { return; }

    $path = strtok(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), '?');
    $legacy = array(
        '/sitemap_index.xml',
        '/sitemap.xml',
        '/post-sitemap.xml',
        '/page-sitemap.xml',
        '/category-sitemap.xml',
        '/author-sitemap.xml',
    );

    if (in_array($path, $legacy, true)) {
        wp_safe_redirect(home_url('/wp-sitemap.xml'), 301);
        exit;
    }
});
