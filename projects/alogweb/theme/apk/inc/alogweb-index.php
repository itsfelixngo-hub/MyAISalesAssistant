<?php
/**
 * Sortable copies of two values that live inside the serialised _info blob.
 *
 * MySQL cannot ORDER BY a field inside a serialised PHP object, so "top rated"
 * and "lightest" are impossible until the values exist as their own meta. These
 * are derived, never authored: _info stays the source of truth and this is
 * rebuilt from it.
 */

if (!defined('ABSPATH')) { exit; }

const ALOGWEB_META_RATING = '_alogweb_rating';
const ALOGWEB_META_SIZE   = '_alogweb_size_bytes';
const ALOGWEB_META_NAME   = '_alogweb_name';

/** "868M" -> 909934592. Unknown or "Varies with device" -> 0, which sorts last. */
function alogweb_size_to_bytes($size) {
    $size = trim((string) $size);
    if ($size === '' || stripos($size, 'varies') !== false) { return 0; }
    if (!preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*([kMGT])?/i', $size, $m)) { return 0; }

    $value = (float) str_replace(',', '.', $m[1]);
    $unit  = isset($m[2]) ? strtoupper($m[2]) : '';
    $scale = array('' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776);

    return (int) round($value * $scale[$unit]);
}

/** Rebuild the derived meta for one post. Returns the values written. */
function alogweb_index_post($post_id) {
    $info = get_post_meta($post_id, '_info', true);
    $info = is_object($info) ? $info : new stdClass();

    $rating = isset($info->ratingValue) ? round((float) $info->ratingValue, 3) : 0.0;
    $bytes  = alogweb_size_to_bytes($info->size ?? '');
    // The short store name lives inside the serialised blob too, so suggestions
    // could not match it. "Among Us" is not a substring of the article headline
    // the AI job wrote over post_title.
    $name   = alogweb_app_name($post_id, $info);

    update_post_meta($post_id, ALOGWEB_META_RATING, $rating);
    update_post_meta($post_id, ALOGWEB_META_SIZE, $bytes);
    update_post_meta($post_id, ALOGWEB_META_NAME, $name);

    return array('rating' => $rating, 'bytes' => $bytes, 'name' => $name);
}

/** True when at least one post carries $key - i.e. the index has been built. */
function alogweb_index_has($key) {
    global $wpdb;
    $found = wp_cache_get('alogweb_index_has_' . $key);
    if ($found === false) {
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", $key
        ));
        wp_cache_set('alogweb_index_has_' . $key, $found, '', 300);
    }
    return (bool) $found;
}

// Keep it fresh when a post is saved, so the index cannot drift from _info.
add_action('save_post_post', function ($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    alogweb_index_post($post_id);
}, 20, 3);

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Rebuild the sort index for every post.
     *
     * ## EXAMPLES
     *     wp alogweb reindex
     */
    WP_CLI::add_command('alogweb reindex', function () {
        $ids = get_posts(array(
            'post_type'   => 'post',
            'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'numberposts' => -1,
            'fields'      => 'ids',
        ));
        $rated = 0; $sized = 0; $named = 0;
        foreach ($ids as $id) {
            $r = alogweb_index_post($id);
            if ($r['rating'] > 0) { $rated++; }
            if ($r['bytes'] > 0) { $sized++; }
            if ($r['name'] !== '') { $named++; }
        }
        WP_CLI::success(sprintf(
            'Indexed %d posts: %d with a rating, %d with a known size, %d named.',
            count($ids), $rated, $sized, $named
        ));
    });
}
