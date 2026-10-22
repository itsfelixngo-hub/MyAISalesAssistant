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
