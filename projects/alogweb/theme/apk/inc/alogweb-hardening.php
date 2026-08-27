<?php
/**
 * Closing the doors bots knock on.
 *
 * nginx already refuses the file paths (wp-config.php, dotfiles, logs, dumps).
 * What is left are the leaks WordPress serves on purpose through its own public
 * API, so they have to be closed in PHP - and only for anonymous callers, since
 * the block editor is a first class consumer of exactly these endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * /wp-json/wp/v2/users returns every account's login slug to anyone who asks.
 * That is the first half of a brute force: the attacker no longer has to guess
 * the username. Removing the routes for logged-out callers keeps the editor's
 * author dropdown working while making the endpoint 404 for everyone else.
 */
function alogweb_hide_rest_users( $endpoints ) {
    if ( is_user_logged_in() ) {
        return $endpoints;
    }
    foreach ( array_keys( $endpoints ) as $route ) {
        if ( strpos( $route, '/wp/v2/users' ) === 0 ) {
            unset( $endpoints[ $route ] );
        }
    }
    return $endpoints;
}
add_filter( 'rest_endpoints', 'alogweb_hide_rest_users' );

/**
 * ?author=1 is the same leak by another route: WordPress answers it with the
 * author archive, and the slug in the URL is the login name. This site has one
 * author and no author archive in the design, so the pages have nothing to lose.
 *
 * Priority 1 runs before redirect_canonical (10) on purpose - otherwise the
 * slug escapes in a Location header before we ever get to 404 the page.
 */
function alogweb_block_author_enumeration() {
    if ( is_user_logged_in() ) {
        return;
    }
    if ( ! isset( $_GET['author'] ) && ! is_author() ) {
        return;
    }

    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();
}
add_action( 'template_redirect', 'alogweb_block_author_enumeration', 1 );

/**
 * oEmbed hands out the author's display name and archive URL to any site that
 * embeds a post. Same information, third route.
 */
function alogweb_strip_oembed_author( $data ) {
    unset( $data['author_name'], $data['author_url'] );
    return $data;
}
add_filter( 'oembed_response_data', 'alogweb_strip_oembed_author' );

/**
 * "Unknown username" versus "wrong password" tells a scanner which half of a
 * guess landed. One message for both halves tells it nothing.
 */
function alogweb_generic_login_error() {
    return __( 'Thong tin dang nhap khong dung.', 'alogweb' );
}
add_filter( 'login_errors', 'alogweb_generic_login_error' );

/**
 * The generator tag publishes the exact WordPress version, which is how a
 * scanner decides whether a given exploit is worth trying. RSD and wlwmanifest
 * only advertise XML-RPC, which nginx denies outright.
 */
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * XML-RPC is denied at nginx. Turning it off here too means it stays shut if
 * this stack is ever put behind a different web server.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );
