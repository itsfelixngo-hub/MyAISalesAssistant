<?php
/**
 * Reading an app's Google Play listing: screenshots, metadata, availability.
 *
 * The hard part is not fetching the page, it is telling the screenshots apart
 * from everything else on it. A listing serves the app icon, the icons of the
 * developer's other apps, the icons of "similar apps", reviewer avatars and the
 * content-rating badges - all from the same play-lh.googleusercontent.com host.
 * Matching that host alone returns roughly 220 URLs for a typical listing, of
 * which about a dozen are screenshots.
 *
 * Anchoring on CSS classes is not the answer either: the carousel's classes
 * (ULeU3b, Utde2e and friends) are generated and change every few weeks, so a
 * selector written today silently returns nothing next month - and "nothing" is
 * exactly the failure that goes unnoticed.
 *
 * Google labels the images itself, in the server-rendered HTML, and that is
 * what this module reads: alt="Screenshot image" plus data-screenshot-index for
 * the ordering.
 */

if (!defined('ABSPATH')) { exit; }

/**
 * The bounding box asked of Google's image server.
 *
 * These suffixes are a box, not a resize: Google fits the image inside it and
 * never upscales. w540-h1080 therefore serves a portrait phone screenshot at
 * 526x1080 and a landscape game screenshot at 540x304, from the same string.
 */
if (!defined('ALOGWEB_SHOT_BOX')) { define('ALOGWEB_SHOT_BOX', 'w540-h1080'); }

/**
 * Swap the size box on a googleusercontent URL, adding one if it has none.
 */
function alogweb_play_image_resize($url, $box = ALOGWEB_SHOT_BOX) {
    $url = trim($url);
    if ($url === '' || $box === '') { return $url; }
    if (strpos($url, 'googleusercontent.com') === false) { return $url; }

    $base = preg_replace('~=[A-Za-z0-9_\-]+$~', '', $url);
    return $base . '=' . $box;
}

/**
 * The best URL an <img> tag offers: the 2x srcset candidate if there is one,
 * otherwise src. Play ships the retina variant in srcset and the half-size one
 * in src, so reading src alone throws away half the resolution for free.
 */
function alogweb_img_best_src($tag) {
    if (preg_match('/\bsrcset="([^"]+)"/i', $tag, $m)) {
        $best = ''; $best_density = 0.0;
        foreach (explode(',', $m[1]) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate));
            if (empty($parts[0])) { continue; }
            $density = isset($parts[1]) ? (float) rtrim($parts[1], 'x') : 1.0;
            if ($density >= $best_density) { $best_density = $density; $best = $parts[0]; }
        }
        if ($best !== '') { return html_entity_decode($best, ENT_QUOTES, 'UTF-8'); }
    }
    if (preg_match('/\bsrc="([^"]+)"/i', $tag, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * Screenshot URLs from a Google Play listing, in the order Play shows them.
 *
 * Returns an empty array rather than a guess when the markup is unrecognised.
 * A caller that keeps the screenshots it already had is better off than one
 * that overwrites them with reviewer avatars.
 */
function alogweb_extract_screenshots($html, $box = ALOGWEB_SHOT_BOX) {
    if (!is_string($html) || $html === '') { return array(); }

    $indexed = array();
    if (preg_match_all('/<img\b[^>]*\balt="Screenshot image"[^>]*>/i', $html, $tags)) {
        foreach ($tags[0] as $n => $tag) {
            $url = alogweb_img_best_src($tag);
            if ($url === '') { continue; }
            // Play states the order explicitly; document order is only a
            // fallback for markup that has dropped the attribute.
            $i = preg_match('/\bdata-screenshot-index="(\d+)"/i', $tag, $m) ? (int) $m[1] : $n;
            $indexed[$i] = alogweb_play_image_resize($url, $box);
        }
    }

    if (!empty($indexed)) {
        ksort($indexed);
        return array_values(array_unique($indexed));
    }

    // Degraded path, for the day Google drops the alt text. Square boxes
    // (=s64, =s128) are icons and avatars without exception, so dropping them
    // removes most of the noise - but this cannot tell this app's promotional
    // images from a neighbouring listing's, so it stays capped and last.
    $loose = array();
    if (preg_match_all('~https?://play-lh\.googleusercontent\.com/[A-Za-z0-9_\-]+=[A-Za-z0-9\-]+~i', $html, $m)) {
        foreach ($m[0] as $url) {
            if (preg_match('~=s\d+~i', $url)) { continue; }
            $loose[] = alogweb_play_image_resize($url, $box);
        }
    }
    return array_slice(array_values(array_unique($loose)), 0, 20);
}

/**
 * Fetch a listing's HTML. Shared by the editor's import button and WP-CLI.
 */
function alogweb_fetch_play_html($store_url) {
    if (!preg_match('#^https?://play\.google\.com/#i', $store_url)) {
        return new WP_Error('alogweb_bad_url', 'Not a Google Play URL: ' . $store_url);
    }
    $response = wp_safe_remote_get($store_url, array(
        'timeout'     => 30,
        'redirection' => 3,
        'user-agent'  => 'Mozilla/5.0 (WordPress; Alogweb importer)',
    ));
    if (is_wp_error($response)) { return $response; }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 400) {
        return new WP_Error('alogweb_http', 'Google Play returned HTTP ' . $code);
    }
    $body = wp_remote_retrieve_body($response);
    if ($body === '') { return new WP_Error('alogweb_empty', 'Google Play returned an empty response'); }
    return $body;
}

/**
 * The metadata a listing still exposes to a plain HTTP fetch.
 *
 * Deliberately partial. Version, download size and the minimum Android release
 * used to sit in <span class="htlgb"> elements; that class no longer appears
 * anywhere on a Play page, and the values now live behind the "About this app"
 * sheet that only exists after JavaScript runs. The importer still carries the
 * old htlgb pattern and has therefore been returning empty strings for those
 * three fields for years.
 *
 * So this returns only the keys it actually read. A caller must merge, never
 * replace: those same three fields are the entire spec line under an app's
 * title (see alogweb_spec_parts), and overwriting them with blanks would empty
 * that line on every post in the site.
 */
function alogweb_extract_app_info($html) {
    if (!is_string($html) || $html === '') { return array(); }
    $info = array();

    if (preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
        $raw = trim($m[1]);
        $schema = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true);
        if (isset($schema[0]) && is_array($schema[0])) { $schema = $schema[0]; }
        if (is_array($schema) && !empty($schema)) {
            // Kept verbatim: alogweb_app_name() reads the short store name back
            // out of this, because the AI pass overwrote every post_title.
            $info['json_script'] = $raw;

            if (!empty($schema['name']))            { $info['name'] = (string) $schema['name']; }
            if (!empty($schema['author']['name']))  { $info['dev'] = (string) $schema['author']['name']; }
            if (!empty($schema['operatingSystem'])) { $info['operatingSystem'] = (string) $schema['operatingSystem']; }
            if (!empty($schema['contentRating']))   { $info['contentRating'] = (string) $schema['contentRating']; }
            if (isset($schema['aggregateRating']['ratingValue'])) {
                $info['ratingValue'] = (string) $schema['aggregateRating']['ratingValue'];
            }
            if (isset($schema['aggregateRating']['ratingCount'])) {
                $info['ratingCount'] = (string) $schema['aggregateRating']['ratingCount'];
            }
            if (!empty($schema['applicationCategory'])) {
                $info['cat'] = strtolower(sanitize_title($schema['applicationCategory']));
            }
        }
    }

    // "Updated on" is a label followed by the date a few tags later. Searching a
    // window after the label rather than writing one long pattern means the
    // markup between the two can change without breaking the match.
    $at = stripos($html, '>Updated on<');
    if ($at !== false && preg_match('/([A-Z][a-z]{2} \d{1,2}, \d{4})/', substr($html, $at, 400), $m)) {
        $info['publish'] = $m[1];
    }

    // The install count as Play prints it: 1M+, 500K+, 100+.
    if (preg_match('/>(\d[\d.,]*[KMB]?\+)</', $html, $m)) {
        $info['downloads'] = $m[1];
    }

    return $info;
}

/**
 * Whether a listing is still on the store.
 *
 * Only an outright 404 counts as gone. A timeout, a 429 or a 5xx says something
 * about the network or about Google's mood, not about the app, and marking an
 * app delisted on that basis would be worse than not checking at all.
 */
function alogweb_play_status($store_url) {
    if (!preg_match('#^https?://play\.google\.com/#i', $store_url)) {
        return new WP_Error('alogweb_bad_url', 'Not a Google Play URL: ' . $store_url);
    }
    $response = wp_safe_remote_get($store_url, array(
        'timeout'     => 25,
        'redirection' => 3,
        'user-agent'  => 'Mozilla/5.0 (WordPress; Alogweb importer)',
    ));
    if (is_wp_error($response)) { return $response; }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code === 404) { return 'gone'; }
    if ($code >= 200 && $code < 300) { return 'available'; }
    return new WP_Error('alogweb_http', 'Google Play returned HTTP ' . $code);
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Re-fetch screenshots for posts that carry a Google Play store URL.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report what would change without writing anything.
     *
     * [--post=<id>]
     * : Only this post.
     *
     * [--limit=<n>]
     * : Stop after n posts.
     *
     * ## EXAMPLES
     *     wp alogweb screenshots --dry-run
     *     wp alogweb screenshots --post=285
     */
    WP_CLI::add_command('alogweb screenshots', function ($args, $assoc) {
        $dry   = isset($assoc['dry-run']);
        $limit = isset($assoc['limit']) ? (int) $assoc['limit'] : 0;

        $ids = isset($assoc['post'])
            ? array((int) $assoc['post'])
            : get_posts(array(
                'post_type'   => 'post',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'numberposts' => -1,
                'fields'      => 'ids',
            ));

        $changed = 0; $skipped = 0; $failed = 0; $done = 0;
        foreach ($ids as $id) {
            if ($limit && $done >= $limit) { break; }

            $info = get_post_meta($id, '_info', true);
            $store = is_object($info) && !empty($info->store_url) ? (string) $info->store_url : '';
            if ($store === '' && is_array($info) && !empty($info['store_url'])) {
                $store = (string) $info['store_url'];
            }
            if ($store === '') { $skipped++; continue; }

            $done++;
            $html = alogweb_fetch_play_html($store);
            if (is_wp_error($html)) {
                WP_CLI::warning(sprintf('#%d %s: %s', $id, get_the_title($id), $html->get_error_message()));
                $failed++;
                continue;
            }

            $shots = alogweb_extract_screenshots($html);
            if (empty($shots)) {
                // Never blank out working screenshots because a fetch came back
                // in a shape this parser does not know.
                WP_CLI::warning(sprintf('#%d %s: no screenshots recognised - left untouched', $id, get_the_title($id)));
                $failed++;
                continue;
            }

            $old = (array) get_post_meta($id, '_screenshots', true);
            if ($old === $shots) { continue; }

            WP_CLI::log(sprintf('#%d %s: %d -> %d', $id, get_the_title($id), count($old), count($shots)));
            if (!$dry) { update_post_meta($id, '_screenshots', $shots); }
            $changed++;
        }

        WP_CLI::success(sprintf(
            '%s%d updated, %d unchanged, %d without a store URL, %d failed.',
            $dry ? 'DRY RUN - ' : '', $changed, $done - $changed - $failed, $skipped, $failed
        ));
    });
}
