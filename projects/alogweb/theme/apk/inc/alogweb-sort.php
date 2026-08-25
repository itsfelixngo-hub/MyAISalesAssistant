<?php
/**
 * Sort control for the archive and search grids.
 *
 * Applied through pre_get_posts rather than a second WP_Query in the template,
 * so the order holds across pagination and found_posts stays correct.
 */

if (!defined('ABSPATH')) { exit; }

/** The offered orders. Keys appear in the URL, so keep them short and stable. */
function alogweb_sort_options() {
    return array(
        'relevance' => __('Relevance', 'alogweb'),
        'rating'    => __('Top rated', 'alogweb'),
        'recent'    => __('Newest', 'alogweb'),
        'light'     => __('Lightest', 'alogweb'),
    );
}

/** The order in effect, falling back to the sensible default for the screen. */
function alogweb_current_sort() {
    $requested = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : '';
    if (isset(alogweb_sort_options()[$requested])) { return $requested; }
    return is_search() ? 'relevance' : 'recent';
}

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) { return; }
    if (!$query->is_search() && !$query->is_category() && !$query->is_archive()) { return; }

    switch (alogweb_current_sort()) {
        case 'rating':
            // OR NOT EXISTS turns the join into a LEFT JOIN, so posts that have
            // no rating still appear - they just sort last. A bare meta_key
            // would drop them, and on a site whose index has not been built yet
            // that means an empty page rather than an unsorted one.
            $query->set('meta_query', array(
                'relation' => 'OR',
                'rated'    => array('key' => ALOGWEB_META_RATING, 'compare' => 'EXISTS'),
                'unrated'  => array('key' => ALOGWEB_META_RATING, 'compare' => 'NOT EXISTS'),
            ));
            $query->set('orderby', array('rated' => 'DESC', 'date' => 'DESC'));
            break;

        case 'light':
            // 0 means "unknown" or "varies", so those are excluded rather than
            // presented as the lightest apps on the site. If nothing at all has
            // a known size the filter would show an empty page, so fall back to
            // date instead of pretending there are no light apps.
            if (alogweb_index_has(ALOGWEB_META_SIZE)) {
                $query->set('meta_query', array('sized' => array(
                    'key'     => ALOGWEB_META_SIZE,
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                )));
                $query->set('orderby', array('sized' => 'ASC', 'date' => 'DESC'));
            } else {
                $query->set('orderby', array('date' => 'DESC'));
            }
            break;

        case 'recent':
            $query->set('orderby', array('date' => 'DESC'));
            break;

        case 'relevance':
        default:
            // Leave WordPress's own relevance ranking alone on a search; on an
            // archive there is nothing to rank against, so fall back to date.
            if (!$query->is_search()) { $query->set('orderby', array('date' => 'DESC')); }
            break;
    }
});

/** The filter row. Each option is a link, so it works without JavaScript. */
function alogweb_sort_bar() {
    $current = alogweb_current_sort();
    $options = alogweb_sort_options();

    // Search keeps its term; an archive keeps whatever else is in the URL.
    $base = remove_query_arg(array('sort', 'paged'));

    echo '<nav class="filters" aria-label="' . esc_attr__('Sort results', 'alogweb') . '">';
    echo '<span class="lbl">' . esc_html__('Sort', 'alogweb') . '</span>';
    foreach ($options as $key => $label) {
        if ($key === 'relevance' && !is_search()) { continue; }
        printf(
            '<a class="chip" href="%s"%s>%s</a>',
            esc_url(add_query_arg('sort', $key, $base)),
            $key === $current ? ' aria-current="true"' : '',
            esc_html($label)
        );
    }
    echo '</nav>';
}
