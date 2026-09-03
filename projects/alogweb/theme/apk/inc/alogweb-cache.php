<?php
/**
 * Purging Cloudflare's cache from wp-admin.
 *
 * Worth knowing what this does and does not reach. Cloudflare here caches
 * static assets and text files - CSS, images, favicon.ico, robots.txt, ads.txt
 * - and answers HTML with cf-cache-status: DYNAMIC, meaning pages are not
 * cached at the edge at all. Pages are cached at the origin instead, by nginx,
 * in a volume this container cannot touch: nginx creates those directories
 * 0700 as uid 101 and php-fpm runs as uid 33. So the two caches are separate
 * concerns and this screen is honest about only clearing one of them.
 *
 * The credentials come from the environment, not the database, for the same
 * reason the SMTP password does: a token in wp_options ends up in every
 * database dump and every backup.
 */

if (!defined('ABSPATH')) { exit; }

function alogweb_cf_config() {
    return array(
        'token' => trim((string) getenv('CLOUDFLARE_API_TOKEN')),
        'zone'  => trim((string) getenv('CLOUDFLARE_ZONE_ID')),
    );
}

function alogweb_cf_ready() {
    $config = alogweb_cf_config();
    return $config['token'] !== '' && $config['zone'] !== '';
}

/**
 * One call to the Cloudflare API. Returns the decoded result, or a WP_Error
 * carrying whatever Cloudflare said was wrong - its messages are specific
 * ("Invalid request headers", "Actor does not have permission") and far more
 * use to whoever is reading the screen than "purge failed".
 */
function alogweb_cf_request($path, $body = null, $method = 'POST') {
    $config = alogweb_cf_config();
    if (!alogweb_cf_ready()) {
        return new WP_Error('alogweb_cf_config', 'CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID are not both set.');
    }

    $args = array(
        'method'  => $method,
        'timeout' => 20,
        'headers' => array(
            'Authorization' => 'Bearer ' . $config['token'],
            'Content-Type'  => 'application/json',
        ),
    );
    if ($body !== null) { $args['body'] = wp_json_encode($body); }

    $response = wp_remote_request('https://api.cloudflare.com/client/v4/' . $path, $args);
    if (is_wp_error($response)) { return $response; }

    $decoded = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($decoded)) {
        return new WP_Error('alogweb_cf_body', 'Cloudflare returned HTTP ' . wp_remote_retrieve_response_code($response) . ' with a response this could not read.');
    }
    if (empty($decoded['success'])) {
        $messages = array();
        foreach ((array) (isset($decoded['errors']) ? $decoded['errors'] : array()) as $error) {
            $messages[] = trim((isset($error['code']) ? $error['code'] . ': ' : '') . (isset($error['message']) ? $error['message'] : ''));
        }
        return new WP_Error('alogweb_cf_api', $messages ? implode(' / ', $messages) : 'Cloudflare rejected the request.');
    }
    return isset($decoded['result']) ? $decoded['result'] : array();
}

function alogweb_cf_purge_everything() {
    $config = alogweb_cf_config();
    return alogweb_cf_request('zones/' . rawurlencode($config['zone']) . '/purge_cache', array('purge_everything' => true));
}

/**
 * Cloudflare takes at most 30 files per call, so this batches rather than
 * silently dropping whatever came after the thirtieth URL.
 */
function alogweb_cf_purge_urls(array $urls) {
    $config = alogweb_cf_config();
    $urls = array_values(array_filter(array_unique(array_map('trim', $urls))));
    if (!$urls) { return new WP_Error('alogweb_cf_empty', 'No URLs given.'); }

    foreach (array_chunk($urls, 30) as $batch) {
        $result = alogweb_cf_request('zones/' . rawurlencode($config['zone']) . '/purge_cache', array('files' => $batch));
        if (is_wp_error($result)) { return $result; }
    }
    return count($urls);
}

function alogweb_cf_verify_token() {
    return alogweb_cf_request('user/tokens/verify', null, 'GET');
}

add_action('admin_menu', function () {
    add_management_page('Purge cache', 'Purge cache', 'manage_options', 'alogweb-purge', 'alogweb_cf_render_page');
});

add_action('admin_post_alogweb_purge', function () {
    if (!current_user_can('manage_options')) { wp_die('Permission denied.'); }
    check_admin_referer('alogweb_purge');

    $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : '';
    if ($mode === 'urls') {
        $raw = isset($_POST['urls']) ? (string) wp_unslash($_POST['urls']) : '';
        $urls = array();
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = esc_url_raw(trim($line));
            if ($line !== '') { $urls[] = $line; }
        }
        $result = alogweb_cf_purge_urls($urls);
        $notice = is_wp_error($result)
            ? array('error', $result->get_error_message())
            : array('success', sprintf('Purged %d URL(s) from Cloudflare.', (int) $result));
    } else {
        $result = alogweb_cf_purge_everything();
        $notice = is_wp_error($result)
            ? array('error', $result->get_error_message())
            : array('success', 'Purged everything from Cloudflare. The edge refills as visitors arrive.');
    }

    set_transient('alogweb_cf_notice_' . get_current_user_id(), $notice, 60);
    wp_safe_redirect(admin_url('tools.php?page=alogweb-purge'));
    exit;
});

function alogweb_cf_render_page() {
    if (!current_user_can('manage_options')) { wp_die('You do not have permission to use this page.'); }

    $notice_key = 'alogweb_cf_notice_' . get_current_user_id();
    $notice = get_transient($notice_key);
    if ($notice) { delete_transient($notice_key); }
    ?>
    <div class="wrap">
        <h1>Purge cache</h1>

        <?php if ($notice): ?>
            <div class="notice notice-<?php echo esc_attr($notice[0] === 'error' ? 'error' : 'success'); ?>">
                <p><?php echo esc_html($notice[1]); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!alogweb_cf_ready()): ?>
            <div class="notice notice-warning">
                <p>Set <code>CLOUDFLARE_API_TOKEN</code> and <code>CLOUDFLARE_ZONE_ID</code> in the deployment
                   environment, then redeploy. The token needs one permission: <strong>Zone &rarr; Cache Purge &rarr; Purge</strong>.</p>
            </div>
        <?php else:
            $token = alogweb_cf_verify_token(); ?>
            <p><strong>Token:</strong>
                <?php if (is_wp_error($token)): ?>
                    <span style="color:#b32d2e">rejected &mdash; <?php echo esc_html($token->get_error_message()); ?></span>
                <?php else: ?>
                    <span style="color:#00733f">valid<?php echo isset($token['status']) ? ' (' . esc_html($token['status']) . ')' : ''; ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <p>Cloudflare caches assets here &mdash; CSS, images, <code>favicon.ico</code>, <code>robots.txt</code>,
           <code>ads.txt</code>. It answers HTML with <code>DYNAMIC</code>, so pages are not cached at the edge.
           Those are cached by nginx at the origin, which this screen cannot reach: run
           <code>./scripts/purge-cache.sh</code> on the server for pages.</p>

        <h2>Specific URLs</h2>
        <p>One per line. Faster to refill than a full purge, and the usual thing you want after replacing a file.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="alogweb_purge">
            <input type="hidden" name="mode" value="urls">
            <?php wp_nonce_field('alogweb_purge'); ?>
            <textarea name="urls" rows="4" class="large-text code" spellcheck="false"
                      placeholder="<?php echo esc_attr(home_url('/favicon.ico')); ?>"></textarea>
            <?php submit_button('Purge these URLs', 'primary', 'submit', true, alogweb_cf_ready() ? array() : array('disabled' => 'disabled')); ?>
        </form>

        <h2>Everything</h2>
        <p>Clears the whole zone. Every asset is fetched from the origin again, so use it when you do not know
           which files changed.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              onsubmit="return confirm('Purge the entire Cloudflare cache for this zone?');">
            <input type="hidden" name="action" value="alogweb_purge">
            <input type="hidden" name="mode" value="everything">
            <?php wp_nonce_field('alogweb_purge'); ?>
            <?php submit_button('Purge everything', 'delete', 'submit', true, alogweb_cf_ready() ? array() : array('disabled' => 'disabled')); ?>
        </form>
    </div>
    <?php
}
