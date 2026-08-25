<?php
/**
 * Search: title suggestions and the snippet used on the results page.
 */

if (!defined('ABSPATH')) { exit; }

const ALOGWEB_SUGGEST_LIMIT = 8;

/**
 * Posts whose app name or headline contains $term.
 *
 * Matches both because they are often unrelated: the AI content job rewrote
 * post_title into an article headline, so "Among Us" is nowhere in the title of
 * the Among Us post. The short name is kept in _alogweb_name for exactly this.
 */
function alogweb_suggest($term, $limit = ALOGWEB_SUGGEST_LIMIT) {
    global $wpdb;

    $term = trim(wp_strip_all_tags($term));
    if (mb_strlen($term) < 2) { return array(); }

    $like = '%' . $wpdb->esc_like($term) . '%';

    // Prefix matches first: someone typing "gen" wants Genshin Impact at the
    // top, not a post that mentions it halfway through its headline.
    $prefix = $wpdb->esc_like($term) . '%';

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID
           FROM {$wpdb->posts} p
           LEFT JOIN {$wpdb->postmeta} m
             ON m.post_id = p.ID AND m.meta_key = %s
          WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND (p.post_title LIKE %s OR m.meta_value LIKE %s)
       GROUP BY p.ID
       ORDER BY (m.meta_value LIKE %s) DESC,
                (p.post_title LIKE %s) DESC,
                CHAR_LENGTH(COALESCE(m.meta_value, p.post_title)) ASC
          LIMIT %d",
        ALOGWEB_META_NAME, $like, $like, $prefix, $prefix, $limit
    ));

    $out = array();
    foreach ($ids as $id) {
        $app = alogweb_app($id);
        $out[] = array(
            'name' => $app['name'],
            'dev'  => $app['dev'],
            'cat'  => $app['cat_name'],
            'icon' => $app['icon'],
            'url'  => $app['permalink'],
        );
    }
    return $out;
}

add_action('rest_api_init', function () {
    register_rest_route('alogweb/v1', '/suggest', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array('required' => true, 'type' => 'string'),
        ),
        'callback'            => function ($request) {
            $results  = alogweb_suggest((string) $request->get_param('q'));
            $response = rest_ensure_response(array('results' => $results));
            // Suggestions change only when posts do; a short shared cache keeps
            // repeated keystrokes off the database without going stale.
            $response->header('Cache-Control', 'public, max-age=60');
            return $response;
        },
    ));
});

/**
 * A snippet of the post around the search term, with every hit marked.
 *
 * Google-style results are only useful if the extract shows why the page
 * matched, so the window is centred on the first hit rather than taken from the
 * top of the article.
 */
function alogweb_search_snippet($post, $term, $length = 210) {
    // Line breaks are kept until the Markdown is gone: wp_strip_all_tags($x, true)
    // removes them, and then the heading pattern has no line start to anchor to.
    $text = wp_strip_all_tags(strip_shortcodes($post->post_content), false);
    // The AI-written posts carry Markdown that was never rendered, so "###" and
    // "**" turn up verbatim in an extract unless they are taken out here.
    $text = preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text);
    $text = preg_replace('/(\*\*|__|`|~~)/u', '', $text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') { return ''; }

    $term = trim($term);
    $at   = $term !== '' ? mb_stripos($text, $term) : false;

    if ($at === false) {
        $snippet = mb_substr($text, 0, $length);
        if (mb_strlen($text) > $length) { $snippet .= '…'; }
    } else {
        $start = max(0, $at - (int) ($length / 3));
        // Do not start mid-word.
        if ($start > 0) {
            $space = mb_strpos($text, ' ', $start);
            $start = ($space !== false && $space - $start < 20) ? $space + 1 : $start;
        }
        $snippet = ($start > 0 ? '…' : '') . mb_substr($text, $start, $length);
        if ($start + $length < mb_strlen($text)) { $snippet .= '…'; }
    }

    $snippet = esc_html($snippet);
    if ($term !== '') {
        $snippet = preg_replace('/(' . preg_quote($term, '/') . ')/iu', '<mark>$1</mark>', $snippet);
    }
    return $snippet;
}

/** Breadcrumb-style URL line, the way a search engine shows it. */
function alogweb_result_url_line($app) {
    $host  = wp_parse_url(home_url(), PHP_URL_HOST);
    $parts = array($host);
    if ($app['cat_name']) { $parts[] = $app['cat_name']; }
    $parts[] = trim(wp_parse_url($app['permalink'], PHP_URL_PATH), '/');
    return implode(' › ', array_map('esc_html', $parts));
}

/**
 * A "search Google within this site" URL, or '' when that would be nonsense.
 *
 * Worth offering only where internal search comes up empty and Google might
 * not: its index can still hold pages this site has since rewritten, and it
 * matches words that WordPress's own search does not.
 *
 * Returns nothing on a hostname Google cannot crawl - localhost, a bare name,
 * an IP - because a site: query against those returns nothing, and a link that
 * is always empty is worse than no link.
 */
function alogweb_google_site_search_url($term) {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $term = trim((string) $term);

    if (!$host || $term === '') { return ''; }
    if (strpos($host, '.') === false) { return ''; }
    if (filter_var($host, FILTER_VALIDATE_IP)) { return ''; }

    return 'https://www.google.com/search?q=' . rawurlencode('site:' . $host . ' ' . $term);
}
