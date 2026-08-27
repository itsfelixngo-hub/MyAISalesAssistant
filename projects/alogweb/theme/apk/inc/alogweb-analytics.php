<?php
/**
 * The single place a measurement beacon is allowed to load.
 *
 * The point of routing it through here rather than pasting a snippet into
 * header.php is what it refuses to do: never on wp-admin, never for a logged-in
 * user. Those two rules are what keep /wp-admin/post.php and /wp-admin/edit.php
 * out of the "top paths" report - they were never visitors, they were the
 * person running the site, and counting them buries the pages that matter.
 *
 * Set ALOGWEB_CF_BEACON_TOKEN, ALOGWEB_GA_MEASUREMENT_ID or (last resort)
 * ALOGWEB_ANALYTICS_HTML_B64. All empty means no beacon, which is the correct
 * state for a staging host.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function alogweb_analytics_snippet() {
    // Cloudflare Web Analytics. Use the *manual* snippet and turn automatic
    // injection off in the dashboard - automatic injection happens at the edge,
    // after this origin has already decided the page is an admin screen, so it
    // counts wp-admin no matter what the theme does.
    $cf = trim( (string) getenv( 'ALOGWEB_CF_BEACON_TOKEN' ) );
    if ( $cf !== '' ) {
        return '<script defer src="https://static.cloudflareinsights.com/beacon.min.js" '
             . "data-cf-beacon='" . wp_json_encode( array( 'token' => $cf ) ) . "'></script>";
    }

    $ga = trim( (string) getenv( 'ALOGWEB_GA_MEASUREMENT_ID' ) );
    if ( $ga !== '' ) {
        $id = esc_js( $ga );
        return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga ) . '"></script>'
             . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
             . "gtag('js',new Date());gtag('config','" . $id . "');</script>";
    }

    // Anything else. Base64 because a .env value cannot carry raw markup - the
    // quotes and a stray # would be swallowed by the parser before PHP sees it.
    $raw = trim( (string) getenv( 'ALOGWEB_ANALYTICS_HTML_B64' ) );
    if ( $raw !== '' ) {
        $decoded = base64_decode( $raw, true );
        if ( $decoded !== false ) { return trim( $decoded ); }
    }

    return '';
}

/**
 * Every reason to stay silent, in one place so the answer cannot drift between
 * "should we count this" and "did we count this".
 */
function alogweb_analytics_should_load() {
    if ( alogweb_analytics_snippet() === '' ) { return false; }
    if ( is_admin() ) { return false; }               // wp-admin screens
    if ( is_user_logged_in() ) { return false; }      // editors are not traffic
    if ( wp_doing_ajax() || wp_doing_cron() ) { return false; }
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
    if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) { return false; }

    // A staging host should not be reporting into the live site's numbers.
    if ( function_exists( 'alogweb_is_provisional_host' ) && alogweb_is_provisional_host() ) {
        return false;
    }
    return true;
}

function alogweb_analytics_print() {
    if ( ! alogweb_analytics_should_load() ) { return; }
    echo "\n" . alogweb_analytics_snippet() . "\n";
}
add_action( 'wp_footer', 'alogweb_analytics_print', 99 );

/**
 * wp-login.php and the REST API are publicly reachable and would otherwise be
 * indexable on their own merits. robots.txt asks crawlers not to fetch them;
 * this tells the ones that fetch anyway not to index what they got.
 */
function alogweb_noindex_headers() {
    if ( is_user_logged_in() ) { return; }
    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_search() || is_404() ) {
        header( 'X-Robots-Tag: noindex, follow', true );
    }
}
add_action( 'send_headers', 'alogweb_noindex_headers' );
