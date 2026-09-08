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

/**
 * Posts the content audit judged too thin to be worth a search result.
 *
 * The flag is written by `wp aipcw audit-content --noindex` in the AI Post
 * Content Writer plugin; this is the half that acts on it. Kept as a literal
 * rather than a reference to the plugin's constant because the theme has to
 * behave the same when the plugin is deactivated - the meta rows outlive it.
 *
 * "follow", not "nofollow": the page still links to category and related posts
 * that should be crawled. Only this page is asking to stay out of the index.
 */
const ALOGWEB_QUALITY_NOINDEX_META = '_alogweb_quality_noindex';

add_filter('wp_robots', function ($robots) {
    if (!is_singular('post')) { return $robots; }
    if (!get_post_meta(get_queried_object_id(), ALOGWEB_QUALITY_NOINDEX_META, true)) { return $robots; }

    $robots['noindex'] = true;
    $robots['follow']  = true;
    unset($robots['max-snippet']);   // meaningless once the page is out of the index
    return $robots;
}, 11);

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
 * Keep the download interstitial out of the pages sitemap.
 *
 * robots.txt already disallows /download-apk, and listing in a sitemap a URL
 * that robots.txt forbids is a contradiction Search Console reports as an
 * error: the sitemap asks Google to index the page, robots.txt refuses to let
 * it read the page. Google can then index the bare URL with no content behind
 * it, under whatever title it can infer.
 *
 * Found by page template rather than by slug or ID, because those are editable
 * in wp-admin and this should not quietly stop working when someone renames the
 * page.
 */
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    // A noindexed post in the sitemap is the same contradiction /download-apk
    // was: the sitemap asks Google to index it while the page asks not to be.
    // Search Console reports it, and the two signals argue in public.
    if ($post_type === 'post') {
        $args['meta_query'] = array_merge(
            isset($args['meta_query']) ? (array) $args['meta_query'] : array(),
            array(array('key' => ALOGWEB_QUALITY_NOINDEX_META, 'compare' => 'NOT EXISTS'))
        );
        return $args;
    }

    if ($post_type !== 'page') { return $args; }

    $hidden = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'template-pages/download-template.php',
    ));
    if (!$hidden) { return $args; }

    $existing = isset($args['post__not_in']) ? (array) $args['post__not_in'] : array();
    $args['post__not_in'] = array_merge($existing, $hidden);
    return $args;
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
 * 410 for a post whose app is gone from Google Play, not 404.
 *
 * The delisted sweep unpublishes those posts, so they already 404 and Google
 * would drop them eventually. 410 says "permanently gone" rather than "not
 * found here right now", and Google acts on it sooner - which is the whole
 * point when the aim is to get them out of the index.
 *
 * A redirect would be the wrong tool and is deliberately not used: 301 means
 * "this moved there", and there is no there. Sending these to the home page or
 * a category is what Google calls a soft 404 - it does not de-index any faster,
 * and it leaves a pile of redirects that mean nothing.
 *
 * Only posts the store sweep marked 'gone' get this. A post drafted for being
 * thin might be rewritten and published again next week; telling Google it is
 * permanently gone would be a lie with a cost.
 *
 * query_vars['name'] rather than parsing REQUEST_URI: the rewrite rules have
 * already worked out which slug was asked for by the time the query 404s, and
 * that answer survives permalink structure changes.
 */
add_action('template_redirect', function () {
    if (!is_404()) { return; }

    $slug = get_query_var('name');
    if (!$slug) { return; }

    $matches = get_posts(array(
        'name'           => $slug,
        'post_type'      => 'post',
        'post_status'    => array('draft', 'pending', 'private', 'trash'),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ));
    if (!$matches) { return; }

    if (get_post_meta($matches[0], '_alogweb_store_status', true) !== 'gone') { return; }

    status_header(410);
    nocache_headers();
}, 12);

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
