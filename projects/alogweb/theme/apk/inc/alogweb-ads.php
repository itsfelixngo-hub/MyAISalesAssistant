<?php
/**
 * Ad slots, edited in wp-admin.
 *
 * Ad code is raw HTML and JavaScript - escaping it would break it - so this is
 * the one place in the theme that prints an option verbatim. Saving is gated on
 * manage_options, the same capability that opens the page.
 *
 * Not unfiltered_html, which is the obvious choice and the wrong one: on a
 * single site WordPress grants it to editors as well as administrators, so it
 * would have let an editor put a script tag on every page of the site.
 *
 * The theme already called ads_head(), ads_single() and ads_footer() from its
 * templates, but all three were empty stubs and none of them was in <head> -
 * which is where an AdSense loader has to be. This wires the existing call
 * sites up and adds the head slot they were missing.
 */

if (!defined('ABSPATH')) { exit; }

function alogweb_ads_slots() {
    return array(
        'head'   => array('Head (AdSense loader / verification)', 'Printed inside &lt;head&gt;. The AdSense code snippet goes here - it verifies the site and loads ads with one install.'),
        'list'   => array('Listing pages', 'Between the sections on the home and archive pages.'),
        'single' => array('App page, under the article', 'After "About this app", before related apps.'),
        'footer' => array('Footer', 'Just before &lt;/body&gt;. Good for a sticky or anchor unit.'),
    );
}

function alogweb_ads_option() {
    $defaults = array('enabled' => 0, 'ads_txt' => '');
    foreach (array_keys(alogweb_ads_slots()) as $slot) { $defaults[$slot] = ''; }
    return wp_parse_args((array) get_option('alogweb_ads', array()), $defaults);
}

/**
 * Every reason to stay silent, in one place.
 *
 * Logged-in users are excluded because an editor's own pageviews are not
 * traffic and their clicks are the fastest way to lose an AdSense account. A
 * staging host is excluded because ads served from an IP with no content
 * policy behind it are a policy problem, not revenue.
 */
function alogweb_ads_active() {
    $options = alogweb_ads_option();
    if (empty($options['enabled'])) { return false; }
    if (is_admin() || is_user_logged_in()) { return false; }
    if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) { return false; }
    if (function_exists('alogweb_is_provisional_host') && alogweb_is_provisional_host()) { return false; }
    return true;
}

function alogweb_ads_print($slot) {
    if (!alogweb_ads_active()) { return; }
    $options = alogweb_ads_option();
    $code = isset($options[$slot]) ? trim($options[$slot]) : '';
    if ($code === '') { return; }
    echo "\n" . $code . "\n";   // Raw on purpose - see the file header.
}

// The three names the templates already call. Defined here, which runs before
// functions.php reaches its own function_exists() stubs.
if (!function_exists('ads_head'))   { function ads_head()   { alogweb_ads_print('list'); } }
if (!function_exists('ads_single')) { function ads_single() { alogweb_ads_print('single'); } }
if (!function_exists('ads_footer')) { function ads_footer() { alogweb_ads_print('footer'); } }

add_action('wp_head', function () { alogweb_ads_print('head'); }, 5);
add_action('wp_footer', function () { alogweb_ads_print('footer'); }, 20);

/**
 * /ads.txt, from the same screen.
 *
 * Not gated on the enabled switch or on being logged out: this file exists for
 * crawlers, and an empty one is worse than none - AdSense treats a missing
 * ads.txt and an ads.txt that omits your publisher id very differently.
 */
add_action('template_redirect', function () {
    $path = strtok((string) wp_unslash(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''), '?');
    if ($path !== '/ads.txt') { return; }

    $code = trim(alogweb_ads_option()['ads_txt']);
    if ($code === '') { return; }   // Fall through to the normal 404.

    // WordPress resolved this request to a 404 and already sent that status in
    // send_headers(), well before template_redirect runs - so the body would go
    // out under a 404 and every crawler would ignore it.
    status_header(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo $code . "\n";
    exit;
}, 0);

add_action('admin_menu', function () {
    add_options_page('Ads', 'Ads', 'manage_options', 'alogweb-ads', 'alogweb_ads_render_page');
});

add_action('admin_init', function () {
    register_setting('alogweb_ads_group', 'alogweb_ads', array(
        'sanitize_callback' => 'alogweb_ads_sanitize',
        'default'           => array(),
    ));
});

function alogweb_ads_sanitize($input) {
    $previous = alogweb_ads_option();
    $clean = array('enabled' => empty($input['enabled']) ? 0 : 1);
    $may_write_code = current_user_can('manage_options');
    $refused = false;

    foreach (array_merge(array_keys(alogweb_ads_slots()), array('ads_txt')) as $field) {
        $value = isset($input[$field]) ? trim((string) wp_unslash($input[$field])) : '';
        if (!$may_write_code && $value !== $previous[$field]) {
            $clean[$field] = $previous[$field];
            $refused = true;
            continue;
        }
        $clean[$field] = $value;
    }

    // Guarded: this callback also runs from WP-CLI's `wp option update`, where
    // wp-admin/includes/template.php is not loaded and the call would be fatal.
    if ($refused && function_exists('add_settings_error')) {
        add_settings_error('alogweb_ads', 'alogweb_ads_caps',
            'Your account is not allowed to save ad code. The previous code was kept.');
    }
    return $clean;
}

function alogweb_ads_render_page() {
    if (!current_user_can('manage_options')) { wp_die('You do not have permission to use this page.'); }
    $options = alogweb_ads_option();
    ?>
    <div class="wrap">
        <h1>Ads</h1>
        <?php settings_errors('alogweb_ads'); ?>

        <p>Ad code is printed exactly as entered. Nothing here is escaped or filtered, so paste only code you trust.
           Ads never load in wp-admin, never for a logged-in user, and never while the site is on a temporary
           host - so you will not see them yourself while signed in.</p>

        <div class="notice notice-info inline"><p>Pages are cached for an hour. After saving, run
           <code>./scripts/purge-cache.sh</code> on the server to see the change immediately.</p></div>

        <form method="post" action="options.php">
            <?php settings_fields('alogweb_ads_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Serve ads</th>
                    <td><label><input type="checkbox" name="alogweb_ads[enabled]" value="1" <?php checked($options['enabled'], 1); ?>>
                        Enabled</label>
                        <p class="description">Turn off to pull every slot at once without clearing the code.</p></td>
                </tr>
                <?php foreach (alogweb_ads_slots() as $slot => $meta): ?>
                    <tr>
                        <th scope="row"><label for="alogweb_ads_<?php echo esc_attr($slot); ?>"><?php echo esc_html($meta[0]); ?></label></th>
                        <td>
                            <textarea id="alogweb_ads_<?php echo esc_attr($slot); ?>" name="alogweb_ads[<?php echo esc_attr($slot); ?>]"
                                      rows="5" class="large-text code" spellcheck="false"
                                      <?php disabled(!current_user_can('manage_options')); ?>><?php echo esc_textarea($options[$slot]); ?></textarea>
                            <p class="description"><?php echo wp_kses($meta[1], array('code' => array())); ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><label for="alogweb_ads_ads_txt">ads.txt</label></th>
                    <td>
                        <textarea id="alogweb_ads_ads_txt" name="alogweb_ads[ads_txt]" rows="4" class="large-text code" spellcheck="false"
                                  <?php disabled(!current_user_can('manage_options')); ?>><?php echo esc_textarea($options['ads_txt']); ?></textarea>
                        <p class="description">Served at <a href="<?php echo esc_url(home_url('/ads.txt')); ?>" target="_blank" rel="noopener"><?php echo esc_html(home_url('/ads.txt')); ?></a>
                           when this is not empty. AdSense gives you the line to paste. Independent of the switch above.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save ads'); ?>
        </form>
    </div>
    <?php
}
