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

    // Core only writes the wp-admin pair. Everything else below is a path that
    // either needs a login, has no content worth an index entry, or generates
    // an unbounded number of URLs a crawler would otherwise walk forever.
    $extra = implode("\n", array(
        'Disallow: /wp-login.php',
        'Disallow: /wp-json/',
        'Disallow: /xmlrpc.php',
        'Disallow: /wp-includes/',
        'Disallow: /wp-content/plugins/',
        'Disallow: /author/',        // 404s now - see alogweb-hardening.php
        'Disallow: /download-apk',   // a redirect step, one URL per ?pid
        'Disallow: /*?s=',           // search results, infinite by definition
        'Disallow: /*?author=',
        'Disallow: /*?replytocom',
    )) . "\n";

    $anchor = "Allow: /wp-admin/admin-ajax.php\n";
    if (strpos($output, $anchor) !== false) {
        return str_replace($anchor, $anchor . $extra, $output);
    }

    // Core changed its default; keep the Sitemap line last either way.
    $pos = strpos($output, "\nSitemap:");
    if ($pos !== false) {
        return substr($output, 0, $pos + 1) . $extra . substr($output, $pos + 1);
    }
    return rtrim($output, "\n") . "\n" . $extra;
}, 20);

/**
 * Drop the users sitemap.
 *
 * It lists one URL, /author/admin, and that URL is a deliberate 404 since the
 * hardening pass - so leaving the provider on means handing Google a sitemap
 * whose only entry is broken, and handing a scraper the login name we just
 * spent the effort hiding. Posts, pages and categories stay.
 */
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return ($name === 'users') ? false : $provider;
}, 10, 2);

/**
 * Unregistering a provider is only half the job.
 *
 * The sitemap rewrite rules still match /wp-sitemap-users-1.xml, and core's
 * renderer simply returns when it cannot find a provider - so the request falls
 * through to a normal query and WordPress answers the *home page* with HTTP 200.
 * A crawler that already knows the URL reads that as "still here, and it is now
 * a duplicate of the front page". Give it the 404 the URL actually deserves.
 *
 * Priority 11: core renders and exits at 10 for every sitemap that does exist,
 * including the index, so this only ever sees the ones that no longer resolve.
 */
add_action('template_redirect', function () {
    $sitemap = get_query_var('sitemap');
    if (empty($sitemap) || $sitemap === 'index') { return; }

    $server = wp_sitemaps_get_server();
    if (!$server || $server->registry->get_provider($sitemap)) { return; }

    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
}, 11);

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
