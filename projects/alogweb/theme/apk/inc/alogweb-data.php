<?php
/**
 * Normalised access to the app data stored in post meta.
 *
 * Every template reads apps through alogweb_app() instead of touching _info,
 * _feature and _screenshots directly. The raw meta is inconsistent: _info is a
 * serialised stdClass that may be missing entirely, _screenshots is an array on
 * some posts and an empty string on others, and _feature is sometimes queried
 * with single=true and sometimes without. Reading it in eleven places was how
 * single.php ended up calling sizeof() on a string and fatalling on PHP 8.
 */

if (!defined('ABSPATH')) { exit; }

/**
 * The short store name ("Genshin Impact"), not the long article headline.
 *
 * The AI content job rewrote every post_title into an article headline, so the
 * post title is no longer usable as an app name. The original name survives in
 * the schema.org blob the importer stored in _info->json_script.
 */
function alogweb_app_name($post_id, $info = null) {
    if ($info === null) { $info = get_post_meta($post_id, '_info', true); }

    if (is_object($info) && !empty($info->json_script)) {
        $schema = json_decode($info->json_script, true);
        if (is_array($schema) && !empty($schema['name'])) {
            return $schema['name'];
        }
    }

    // Fall back to the slug, which still reflects the original name.
    $post = get_post($post_id);
    if ($post && $post->post_name) {
        return ucwords(str_replace('-', ' ', $post->post_name));
    }
    return get_the_title($post_id);
}

/**
 * Everything a template needs about one app, with safe defaults throughout.
 * Missing values come back as '' or array(), never null or a stray object.
 */
function alogweb_app($post_id = null) {
    $post_id = $post_id ? (int) $post_id : get_the_ID();
    if (!$post_id) { return array(); }

    $info = get_post_meta($post_id, '_info', true);
    $info = is_object($info) ? $info : new stdClass();

    $screenshots = get_post_meta($post_id, '_screenshots', true);
    $screenshots = is_array($screenshots) ? array_values(array_filter($screenshots)) : array();

    $icon = get_post_meta($post_id, '_feature', true);
    if (is_array($icon)) { $icon = reset($icon); }

    $rating = isset($info->ratingValue) ? round((float) $info->ratingValue, 2) : 0.0;

    // The importer scraped some fields off the wrong part of the Play Store
    // page: one post has the whole content-rating block, markup and all, sitting
    // in `android`. Strip tags everywhere, then drop values that cannot be what
    // the field claims to be.
    $clean = static function ($value) {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    };
    $plausible = static function ($value) {
        // A version requirement starts with a digit, or says it varies.
        return $value !== '' && (ctype_digit(substr($value, 0, 1)) || stripos($value, 'varies') !== false);
    };

    $size    = $clean($info->size ?? '');
    $version = $clean($info->version ?? '');
    $android = $clean($info->android ?? '');
    if (!$plausible($android)) { $android = ''; }

    $terms = get_the_category($post_id);
    $term  = (is_array($terms) && !empty($terms)) ? $terms[0] : null;

    return array(
        'id'          => $post_id,
        'name'        => alogweb_app_name($post_id, $info),
        'headline'    => get_the_title($post_id),
        'permalink'   => get_permalink($post_id),
        'dev'         => $clean($info->dev ?? ''),
        'size'        => $size,
        'version'     => $version,
        'android'     => $android,
        'published'   => $clean($info->publish ?? ''),
        'store_url'   => isset($info->store_url) ? esc_url_raw((string) $info->store_url) : '',
        'rating'      => $rating,
        'rating_pct'  => $rating > 0 ? min(100, ($rating / 5) * 100) : 0,
        'icon'        => is_string($icon) ? $icon : '',
        'screenshots' => $screenshots,
        'cat_name'    => $term ? $term->name : '',
        'cat_link'    => $term ? get_category_link($term->term_id) : '',
        'download'    => home_url('/download-apk?pid=' . $post_id),
    );
}

/**
 * The compact metadata line: "868M · v1.6.0 · 5.0+".
 *
 * Only values that read well at 11px survive. "Varies with device" is honest
 * information and stays in the manifest table on the detail page, but in a card
 * it is three times the width of a real answer - and prefixing it turned into
 * "vVaries with device".
 */
function alogweb_spec_parts($app) {
    $parts = array();

    $size = $app['size'];
    if ($size !== '' && stripos($size, 'varies') === false) { $parts[] = $size; }

    $version = $app['version'];
    if ($version !== '' && stripos($version, 'varies') === false && ctype_digit(substr($version, 0, 1))) {
        // Importer versions like 1.6.0_2961400_3070488 are unreadable in a card.
        if (strlen($version) > 12) { $version = substr($version, 0, strcspn($version, '_')); }
        $parts[] = 'v' . $version;
    }

    $android = $app['android'];
    if ($android !== '' && stripos($android, 'varies') === false) {
        $parts[] = preg_replace('/\s*and up$/i', '+', $android);
    }

    return $parts;
}

/** Android package id, pulled out of the Play Store URL. */
function alogweb_package_id($app) {
    if (empty($app['store_url'])) { return ''; }
    if (preg_match('/[?&]id=([A-Za-z0-9._]+)/', $app['store_url'], $m)) { return $m[1]; }
    return '';
}
