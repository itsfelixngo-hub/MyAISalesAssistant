<?php
/**
 * Keeps the word "APK" off the public site.
 *
 * The imported posts carry it in their titles, app names and meta descriptions
 * ("... MOD APK", "APK Download"), and to an ad reviewer it reads as a piracy
 * signal whatever the page holds. Rewriting thousands of rows would lose the
 * originals, so it is taken out on the way to the page instead. wp-admin and
 * WP-CLI still see the stored text, so editors know what is really there.
 */

if (!defined('ABSPATH')) { exit; }

/**
 * $text with every standalone APK / APKs / XAPK removed and the gap closed up.
 * Plain text only - for HTML use alogweb_strip_apk_html().
 */
function alogweb_strip_apk($text) {
    if (!is_string($text) || stripos($text, 'apk') === false) { return $text; }

    // Whole words only, so "/download-apk", "game.apk" or "apkpure" survive -
    // \b treats the hyphen and dot as boundaries, hence the lookarounds. A dot
    // that ends the sentence still counts as the end of the word.
    $text = preg_replace('/(?<![\w\/.-])X?APKs?(?![\w\/-]|\.\w)/i', '', $text);

    // What the word leaves behind: "()", "Name  1.2", "Name - ", " | Name".
    $text = preg_replace('/[(\[]\s*[)\]]/', '', $text);
    $text = preg_replace('/[ \t]{2,}/', ' ', $text);
    $text = preg_replace('/\s+([,.:;!?)\]])/', '$1', $text);
    $text = preg_replace('/^[\s\-–—|:,]+|[\s\-–—|:,]+$/u', '', $text);
    return $text;
}

/**
 * The same for HTML: only the text between tags changes, so attributes, links
 * and anything inside <script> or <style> are left exactly as stored.
 */
function alogweb_strip_apk_html($html) {
    if (!is_string($html) || stripos($html, 'apk') === false) { return $html; }

    $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $raw   = false;
    foreach ($parts as $i => $part) {
        if ($part === '') { continue; }
        if ($part[0] === '<') {
            if (preg_match('/^<(script|style)\b/i', $part)) { $raw = true; }
            elseif (preg_match('/^<\/(script|style)\b/i', $part)) { $raw = false; }
            continue;
        }
        if (!$raw && stripos($part, 'apk') !== false) {
            // Keep the leading and trailing space: they separate this text
            // from the inline tags around it.
            preg_match('/^(\s*)(.*?)(\s*)$/s', $part, $m);
            $parts[$i] = $m[1] . alogweb_strip_apk($m[2]) . $m[3];
        }
    }
    return implode('', $parts);
}

// admin-ajax counts as is_admin(), but the search suggestions come through it.
function alogweb_wording_applies() {
    return (!is_admin() || wp_doing_ajax()) && !(defined('WP_CLI') && WP_CLI);
}

function alogweb_strip_apk_term($term) {
    if ($term instanceof WP_Term) {
        $term = clone $term;
        $term->name = alogweb_strip_apk($term->name);
    }
    return $term;
}

function alogweb_strip_apk_terms($terms) {
    return is_array($terms) ? array_map('alogweb_strip_apk_term', $terms) : $terms;
}

function alogweb_strip_apk_title_parts($parts) {
    return array_map('alogweb_strip_apk', $parts);
}

add_action('init', function () {
    if (!alogweb_wording_applies()) { return; }

    foreach (array('the_title', 'single_post_title', 'single_cat_title', 'single_tag_title',
                   'single_term_title', 'option_blogname', 'option_blogdescription',
                   'nav_menu_item_title') as $hook) {
        add_filter($hook, 'alogweb_strip_apk');
    }
    add_filter('document_title_parts', 'alogweb_strip_apk_title_parts');
    add_filter('get_term', 'alogweb_strip_apk_term');
    add_filter('get_the_terms', 'alogweb_strip_apk_terms');

    foreach (array('the_content', 'the_excerpt', 'get_the_excerpt', 'term_description') as $hook) {
        add_filter($hook, 'alogweb_strip_apk_html', 20);
    }
});

/* ------------------------------------------------------------ the download URL
 * The download step lived at /download-apk, the slug of a page in the database.
 * It is served at /official-source/ now, which says what the page actually does
 * - hand the visitor to the official release page - where "apk" and "download"
 * both read to an ad reviewer as a site that hands out files. The database row
 * is left alone: the page is found
 * by its template, /download/ is pointed at it, its permalink is rewritten to
 * match, and the old address answers with a 301 that keeps ?pid. Renaming the
 * slug in wp-admin later changes nothing - the template is what is looked up.
 */
const ALOGWEB_DOWNLOAD_PATH = 'official-source';
const ALOGWEB_DOWNLOAD_TITLE = 'Official source';

function alogweb_download_page_id() {
    static $id = null;
    if ($id === null) {
        $ids = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'template-pages/download-template.php',
        ));
        $id = $ids ? (int) $ids[0] : 0;
    }
    return $id;
}

function alogweb_download_url($post_id) {
    return add_query_arg('pid', (int) $post_id, home_url(user_trailingslashit(ALOGWEB_DOWNLOAD_PATH)));
}

add_action('parse_request', function ($wp) {
    if ($wp->request !== ALOGWEB_DOWNLOAD_PATH) { return; }
    $page = alogweb_download_page_id();
    if ($page) { $wp->query_vars = array('page_id' => $page); }
});

// The row is titled "Download APK"; the APK filter alone would leave "Download".
add_filter('the_title', function ($title, $post_id = 0) {
    return (alogweb_wording_applies() && (int) $post_id && (int) $post_id === alogweb_download_page_id())
        ? ALOGWEB_DOWNLOAD_TITLE
        : $title;
}, 10, 2);

// <title> and og:title come through here, not the_title.
add_filter('single_post_title', function ($title, $post = null) {
    return (alogweb_wording_applies() && $post && (int) $post->ID === alogweb_download_page_id())
        ? ALOGWEB_DOWNLOAD_TITLE
        : $title;
}, 10, 2);

add_filter('page_link', function ($link, $post_id) {
    return ((int) $post_id === alogweb_download_page_id())
        ? home_url(user_trailingslashit(ALOGWEB_DOWNLOAD_PATH))
        : $link;
}, 10, 2);

// Ahead of redirect_canonical (10), which would otherwise send the old slug to
// the new permalink but drop ?pid along the way.
add_action('template_redirect', function () {
    $page = alogweb_download_page_id();
    if (!$page || !is_page($page)) { return; }

    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path === ALOGWEB_DOWNLOAD_PATH) { return; }

    $pid = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
    wp_safe_redirect($pid ? alogweb_download_url($pid) : home_url(user_trailingslashit(ALOGWEB_DOWNLOAD_PATH)), 301);
    exit;
}, 5);
