<?php
/**
 * Plugin Name: AI Post Content Writer
 * Description: Process WordPress posts in the background with a local Ollama model and create reviewable drafts.
 * Version: 0.2.0
 * Author: My AI Sales Assistant
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

final class AI_Post_Content_Writer {
    const SETTINGS = 'aipcw_settings';
    const JOB = 'aipcw_background_job';
    const BACKUP_META = '_aipcw_backup_history';
    const CRON = 'aipcw_process_batch';
    const BATCH_SIZE = 1;
    // Sweeps are the store-maintenance jobs: re-read a Play listing and write
    // one thing back. Each keeps its own option, cron hook and queue, so none of
    // them can disturb a content-generation run that is halfway through - or
    // each other. Higher batch size than BATCH_SIZE because a batch here is
    // three HTTP fetches, not three model calls: no token budget to pace.
    const SWEEP_BATCH_SIZE = 3;
    const STORE_STATUS_META = '_alogweb_store_status';
    const STORE_CHECKED_META = '_alogweb_store_checked';

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_aipcw_start_scan', array($this, 'start_scan'));
        add_action('admin_post_aipcw_retry_failed', array($this, 'retry_failed'));
        add_action('admin_post_aipcw_resume_job', array($this, 'resume_job'));
        add_action('admin_post_aipcw_reset_drafts', array($this, 'reset_generated_drafts'));
        add_action('admin_post_aipcw_test_post', array($this, 'test_post'));
        add_action('admin_post_aipcw_stop_scan', array($this, 'stop_scan'));
        add_action('admin_post_aipcw_revert', array($this, 'revert_post'));
        add_action('wp_ajax_aipcw_status', array($this, 'ajax_status'));
        add_action(self::CRON, array($this, 'process_batch'));
        add_action('admin_post_aipcw_start_sweep', array($this, 'start_sweep'));
        add_action('admin_post_aipcw_stop_sweep', array($this, 'stop_sweep'));
        foreach ($this->sweeps() as $sweep_key => $sweep) {
            add_action($sweep['cron'], function () use ($sweep_key) { $this->process_sweep($sweep_key); });
        }
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('aipcw worker', array($this, 'cli_worker'));
            WP_CLI::add_command('aipcw sweep', array($this, 'cli_sweep'));
        }
    }

    public function admin_menu() {
        add_menu_page('AI Content Writer', 'AI Content Writer', 'edit_posts', 'ai-post-content-writer', array($this, 'render_page'), 'dashicons-edit-page', 26);
    }

    public function register_settings() {
        register_setting('aipcw_settings_group', self::SETTINGS, array($this, 'sanitize_settings'));
        add_settings_section('aipcw_ollama', 'AI provider settings', '__return_false', 'ai-post-content-writer-settings');
        add_settings_field('provider', 'Provider', array($this, 'provider_field'), 'ai-post-content-writer-settings', 'aipcw_ollama');
        add_settings_field('ollama_url', 'Ollama URL', array($this, 'url_field'), 'ai-post-content-writer-settings', 'aipcw_ollama');
        add_settings_field('model', 'Model', array($this, 'model_field'), 'ai-post-content-writer-settings', 'aipcw_ollama');
    }

    public function sanitize_settings($input) {
        return array(
            'ollama_url' => !empty($input['ollama_url']) ? esc_url_raw($input['ollama_url']) : 'http://host.docker.internal:11435',
            'model' => !empty($input['model']) ? sanitize_text_field($input['model']) : 'qwen2.5:7b',
            'provider' => isset($input['provider']) && $input['provider'] === 'gemini' ? 'gemini' : 'ollama',
            'gemini_api_key' => !empty($input['gemini_api_key']) ? sanitize_text_field($input['gemini_api_key']) : '',
            'gemini_model' => !empty($input['gemini_model']) ? sanitize_text_field($input['gemini_model']) : 'gemini-3.5-flash-lite',
        );
    }

    private function settings() {
        return wp_parse_args(get_option(self::SETTINGS, array()), array('provider' => 'ollama', 'ollama_url' => 'http://host.docker.internal:11435', 'model' => 'qwen2.5:7b', 'gemini_api_key' => '', 'gemini_model' => 'gemini-3.5-flash-lite'));
    }

    private function job() {
        return wp_parse_args(get_option(self::JOB, array()), array('status' => 'idle', 'mode' => 'draft', 'queue' => array(), 'total' => 0, 'processed' => 0, 'created' => 0, 'created_ids' => array(), 'updated' => 0, 'updated_ids' => array(), 'failed' => 0, 'failed_ids' => array(), 'last_error' => ''));
    }

    public function url_field() {
        $s = $this->settings();
        printf('<input type="url" class="regular-text" name="%s[ollama_url]" value="%s" />', esc_attr(self::SETTINGS), esc_attr($s['ollama_url']));
        echo '<p class="description">For Docker on Linux, use http://host.docker.internal:11435 when Ollama runs on the host.</p>';
    }

    public function provider_field() {
        $s = $this->settings();
        printf('<select name="%s[provider]"><option value="ollama" %s>Ollama (local)</option><option value="gemini" %s>Gemini API</option></select>', esc_attr(self::SETTINGS), selected($s['provider'], 'ollama', false), selected($s['provider'], 'gemini', false));
    }

    public function model_field() {
        $s = $this->settings();
        printf('<input type="text" class="regular-text" name="%s[model]" value="%s" />', esc_attr(self::SETTINGS), esc_attr($s['model']));
        echo '<p class="description">Example: qwen2.5:7b. The model must already be installed in Ollama.</p>';
        printf('<p><label>Gemini API key <input type="password" class="regular-text" name="%s[gemini_api_key]" value="%s" autocomplete="new-password" /></label></p>', esc_attr(self::SETTINGS), esc_attr($s['gemini_api_key']));
        printf('<p><label>Gemini model <input type="text" class="regular-text" name="%s[gemini_model]" value="%s" /></label></p>', esc_attr(self::SETTINGS), esc_attr($s['gemini_model']));
        echo '<p class="description">Gemini quota errors pause the job. Resume it after the quota resets.</p>';
    }

    public function render_page() {
        if (!current_user_can('edit_posts')) { wp_die('You do not have permission to use this page.'); }
        $job = $this->job();
        $posts = get_posts(array('post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending'), 'numberposts' => 100));
        $percent = $job['total'] ? min(100, round(($job['processed'] / $job['total']) * 100)) : 0;

        $test_result = isset($_GET['aipcw_test']) ? sanitize_key($_GET['aipcw_test']) : '';
        $test_id = isset($_GET['test_id']) ? absint($_GET['test_id']) : 0;
        $test_mode = isset($_GET['test_mode']) ? sanitize_key($_GET['test_mode']) : '';
        $reset_count = isset($_GET['aipcw_reset_drafts']) ? absint($_GET['aipcw_reset_drafts']) : -1;
        $preview_id = isset($_GET['aipcw_backup_preview']) ? absint($_GET['aipcw_backup_preview']) : 0;
        if ($preview_id) { $this->render_backup_preview($preview_id); return; }
        // Do not show posts moved to Trash in the backup list. Their backup
        // metadata remains intact if the post is restored later.
        $backup_posts = get_posts(array('post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1, 'meta_key' => self::BACKUP_META, 'orderby' => 'ID', 'order' => 'ASC'));
        ?>
        <div class="wrap">
            <h1>AI Content Writer</h1>
            <p>Scan toàn bộ posts theo batch bằng Ollama. Chế độ cập nhật sẽ lưu bản gốc để có thể revert.</p>
            <?php if ($reset_count >= 0): ?><div class="notice notice-success"><p><?php echo esc_html($reset_count); ?> generated draft(s) moved to Trash and job statistics reset.</p></div><?php endif; ?>
            <?php if ($test_result === 'success' && $test_id): ?><div class="notice notice-success"><p>Test completed: <a href="<?php echo esc_url(get_edit_post_link($test_id)); ?>">Open result</a><?php if ($test_mode === 'update'): ?> | <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline"><input type="hidden" name="action" value="aipcw_revert" /><input type="hidden" name="post_id" value="<?php echo esc_attr($test_id); ?>" /><?php wp_nonce_field('aipcw_revert_' . $test_id); ?><button type="submit" class="button-link">Revert this AI update</button></form><?php endif; ?></p></div><?php elseif ($test_result === 'error'): ?><div class="notice notice-error"><p><?php echo esc_html(isset($_GET['message']) ? wp_unslash($_GET['message']) : 'Test failed.'); ?></p></div><?php endif; ?>
            <h2>Test one post</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="aipcw_test_post" /><?php wp_nonce_field('aipcw_test_post'); ?>
                <table class="form-table">
                    <tr><th><label for="aipcw_test_source">Post</label></th><td><select id="aipcw_test_source" name="source_id" required><option value="">Select one post</option><?php foreach ($posts as $post): ?><option value="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title ?: '(Untitled)'); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th><label for="aipcw_test_mode">Mode</label></th><td><select id="aipcw_test_mode" name="mode"><option value="draft">Create draft</option><option value="update">Update current post (revert available)</option></select></td></tr>
                    <tr><th><label for="aipcw_test_instruction">Instruction</label></th><td><textarea id="aipcw_test_instruction" name="instruction" rows="12" class="large-text" required>Rewrite the original post into a completely original, natural, and useful SEO-friendly article in English.

Requirements:
- Preserve the accurate facts, figures, names, dates, and key meaning from the source.
- Do not copy sentences, paragraphs, headings, examples, opening structure, or paragraph order from the source.
- Create a clearly different angle, outline, headline, introduction, and wording.
- Do not summarize or shorten the source. Preserve all important sections and details.
- Keep the rewritten article at least as long as the source, preferably 10–20% longer where useful.
- Expand explanations, examples, and transitions naturally when needed to reach the target length.
- Do not invent unsupported facts, statistics, quotes, or claims.
- Use a professional, clear, human-friendly tone and short readable paragraphs.
- Add meaningful H2 and H3 headings and bullet points where useful.
- Optimize naturally for the primary keyword; avoid keyword stuffing.
- Write an engaging but accurate title without clickbait.
- Create an SEO meta title of about 60 characters.
- Create an SEO meta description of about 140–160 characters.
- Create a concise and attractive excerpt.
- Return only valid JSON with these fields: title, excerpt, content, meta_title, meta_description.</textarea></td></tr>
                </table>
                <?php submit_button('Run test'); ?>
            </form>
            <h2>Background job</h2>
            <p id="aipcw-progress"><strong>Status:</strong> <?php echo esc_html($job['status']); ?> &nbsp; <strong>Progress:</strong> <?php echo esc_html($job['processed'] . '/' . $job['total'] . ' (' . $percent . '%)'); ?></p>
            <p id="aipcw-counts"><?php if ($job['created'] || $job['updated'] || $job['failed']): ?>Created: <?php echo esc_html($job['created']); ?> draft(s), Updated: <?php echo esc_html($job['updated']); ?> post(s), Failed: <?php echo esc_html($job['failed']); ?>.<?php endif; ?></p>
            <div id="aipcw-error" class="notice notice-error" style="<?php echo $job['last_error'] ? '' : 'display:none;'; ?>"><p><?php echo esc_html($job['last_error']); ?></p></div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                <input type="hidden" name="action" value="aipcw_start_scan" /><?php wp_nonce_field('aipcw_start_scan'); ?>
                <p><label for="aipcw_mode"><strong>Mode:</strong></label> <select id="aipcw_mode" name="mode"><option value="draft">Tạo draft mới (an toàn)</option><option value="update">Cập nhật bài hiện tại (có revert)</option></select></p>
                <?php submit_button($job['status'] === 'running' ? 'Scan đang chạy' : 'Scan toàn bộ posts', 'primary', 'submit', false, $job['status'] === 'running' ? 'disabled="disabled"' : ''); ?>
            </form>
            <?php if ($job['status'] !== 'running' && !empty($job['failed'])): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block"><input type="hidden" name="action" value="aipcw_retry_failed" /><?php wp_nonce_field('aipcw_retry_failed'); ?><?php submit_button('Retry failed posts (' . absint($job['failed']) . ')', 'secondary', 'submit', false); ?></form><?php endif; ?>
            <?php if ($job['status'] === 'paused_quota'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block"><input type="hidden" name="action" value="aipcw_resume_job" /><?php wp_nonce_field('aipcw_resume_job'); ?><?php submit_button('Resume after quota reset', 'primary', 'submit', false); ?></form><?php endif; ?>
            <?php if ($job['status'] !== 'running'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:8px" onsubmit="return confirm('Move all drafts created by AI Content Writer to Trash?');"><input type="hidden" name="action" value="aipcw_reset_drafts" /><?php wp_nonce_field('aipcw_reset_drafts'); ?><?php submit_button('Reset generated drafts', 'delete', 'submit', false); ?></form><?php endif; ?>
            <?php if ($job['status'] === 'running'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block"><input type="hidden" name="action" value="aipcw_stop_scan" /><?php wp_nonce_field('aipcw_stop_scan'); ?><?php submit_button('Stop job', 'secondary', 'submit', false); ?></form><?php endif; ?>
            <?php if (!empty($job['updated_ids'])): ?><h2>Updated posts — revert</h2><ul><?php foreach ($job['updated_ids'] as $updated_id): $updated_post = get_post($updated_id); if (!$updated_post) { continue; } ?><li><a href="<?php echo esc_url(get_edit_post_link($updated_id)); ?>"><?php echo esc_html($updated_post->post_title); ?></a> <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline"><input type="hidden" name="action" value="aipcw_revert" /><input type="hidden" name="post_id" value="<?php echo esc_attr($updated_id); ?>" /><?php wp_nonce_field('aipcw_revert_' . $updated_id); ?><button type="submit" class="button-link">Revert latest AI change</button></form></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if (!empty($job['created_ids'])): ?><h2>Created drafts</h2><ul><?php foreach ($job['created_ids'] as $created_id): $created_post = get_post($created_id); if (!$created_post) { continue; } ?><li><a href="<?php echo esc_url(get_edit_post_link($created_id)); ?>"><?php echo esc_html($created_post->post_title); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
            <h2>Backup history</h2>
            <?php if (empty($backup_posts)): ?><p>No backups available yet. Run a test or scan in update mode first.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Post</th><th>Versions</th><th>Latest backup</th><th>Actions</th></tr></thead><tbody><?php foreach ($backup_posts as $backup_post): $history = get_post_meta($backup_post->ID, self::BACKUP_META, true); $count = is_array($history) ? count($history) : 0; $latest = $count ? $history[$count - 1] : array(); ?><tr><td><a href="<?php echo esc_url(get_edit_post_link($backup_post->ID)); ?>"><?php echo esc_html($backup_post->post_title ?: '(Untitled)'); ?></a></td><td><?php echo esc_html($count); ?></td><td><?php echo esc_html(isset($latest['time']) ? $latest['time'] : ''); ?></td><td><a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'ai-post-content-writer', 'aipcw_backup_preview' => $backup_post->ID), admin_url('admin.php'))); ?>">View latest backup</a> <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline"><input type="hidden" name="action" value="aipcw_revert" /><input type="hidden" name="post_id" value="<?php echo esc_attr($backup_post->ID); ?>" /><?php wp_nonce_field('aipcw_revert_' . $backup_post->ID); ?><button type="submit" class="button">Revert latest</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
            <hr />
            <h2>Store maintenance</h2>
            <?php foreach ($this->sweeps() as $sweep_key => $sweep):
                $sweep_job = $this->sweep_job($sweep_key);
                $sweep_pct = $sweep_job['total'] ? min(100, round(($sweep_job['processed'] / $sweep_job['total']) * 100)) : 0;
                $sweep_running = $sweep_job['status'] === 'running';
            ?>
                <h3><?php echo esc_html($sweep['label']); ?></h3>
                <p><?php echo wp_kses($sweep['blurb'], array('strong' => array())); ?></p>
                <?php if (!$this->sweep_ready($sweep_key)): ?>
                    <div class="notice notice-warning inline"><p>The alogweb theme is not active, so there is no Google Play parser to call.</p></div>
                <?php endif; ?>
                <p><strong>Status:</strong> <?php echo esc_html($sweep_job['status']); ?> &nbsp;
                   <strong>Progress:</strong> <?php echo esc_html($sweep_job['processed'] . '/' . $sweep_job['total'] . ' (' . $sweep_pct . '%)'); ?>
                   <?php if ($sweep_job['finished']): ?> &nbsp; <em>last finished <?php echo esc_html($sweep_job['finished']); ?></em><?php endif; ?></p>
                <?php if ($sweep_job['processed']): ?>
                    <p>Updated: <?php echo esc_html($sweep_job['updated']); ?>,
                       Unchanged: <?php echo esc_html($sweep_job['unchanged']); ?>,
                       No store URL: <?php echo esc_html($sweep_job['skipped']); ?>,
                       Failed: <?php echo esc_html($sweep_job['failed']); ?>.</p>
                <?php endif; ?>
                <?php if ($sweep_job['last_error']): ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html($sweep_job['last_error']); ?></p></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                    <input type="hidden" name="action" value="aipcw_start_sweep" />
                    <input type="hidden" name="sweep" value="<?php echo esc_attr($sweep_key); ?>" />
                    <?php wp_nonce_field('aipcw_start_sweep'); ?>
                    <?php submit_button($sweep_running ? 'Đang chạy' : 'Chạy cho toàn bộ posts', 'primary', 'submit', false,
                        ($sweep_running || !$this->sweep_ready($sweep_key)) ? 'disabled="disabled"' : ''); ?>
                </form>
                <?php if ($sweep_running): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
                        <input type="hidden" name="action" value="aipcw_stop_sweep" />
                        <input type="hidden" name="sweep" value="<?php echo esc_attr($sweep_key); ?>" />
                        <?php wp_nonce_field('aipcw_stop_sweep'); ?>
                        <?php submit_button('Stop', 'secondary', 'submit', false); ?>
                    </form>
                <?php endif; ?>
                <?php if (!empty($sweep_job['failed_ids'])): ?>
                    <p><strong>Failed:</strong>
                    <?php foreach (array_slice($sweep_job['failed_ids'], 0, 40) as $failed_id): ?>
                        <a href="<?php echo esc_url(get_edit_post_link($failed_id)); ?>">#<?php echo absint($failed_id); ?></a>
                    <?php endforeach; ?>
                    <?php if (count($sweep_job['failed_ids']) > 40): ?> and <?php echo absint(count($sweep_job['failed_ids']) - 40); ?> more<?php endif; ?></p>
                <?php endif; ?>
            <?php endforeach; ?>

            <h3>Apps no longer on Google Play</h3>
            <?php $gone = $this->delisted_posts(); ?>
            <?php if (empty($gone)): ?>
                <p>None recorded. Run <em>Delisted app check</em> to populate this.</p>
            <?php else: ?>
                <p><?php echo absint(count($gone)); ?> post(s) point at a listing Google Play answers with 404.
                   Their screenshots and metadata are left as they are - this is a report, not a cleanup.</p>
                <table class="widefat striped"><thead><tr><th>Post</th><th>Store URL</th><th>Checked</th></tr></thead><tbody>
                <?php foreach ($gone as $gone_id): ?>
                    <tr>
                        <td><a href="<?php echo esc_url(get_edit_post_link($gone_id)); ?>"><?php echo esc_html(get_the_title($gone_id) ?: '(Untitled)'); ?></a></td>
                        <td><?php $gone_url = $this->store_url_for($gone_id); ?><a href="<?php echo esc_url($gone_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($gone_url); ?></a></td>
                        <td><?php echo esc_html(get_post_meta($gone_id, self::STORE_CHECKED_META, true)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>

            <hr />
            <h2>AI provider settings</h2>
            <form method="post" action="options.php"><?php settings_fields('aipcw_settings_group'); do_settings_sections('ai-post-content-writer-settings'); submit_button('Save settings'); ?></form>
            <hr />
            <h2>Available source posts (first 100)</h2>
            <ul><?php foreach ($posts as $post): ?><li><?php echo esc_html($post->post_title ?: '(Untitled)'); ?></li><?php endforeach; ?></ul>
            <?php if ($job['status'] === 'running'): ?><script>
            (function () {
                var nonce = <?php echo wp_json_encode(wp_create_nonce('aipcw_status')); ?>;
                function poll() {
                    fetch(ajaxurl + '?action=aipcw_status&nonce=' + encodeURIComponent(nonce), { credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (payload) {
                            if (!payload.success) return;
                            var job = payload.data;
                            var percent = job.total ? Math.min(100, Math.round((job.processed / job.total) * 100)) : 0;
                            document.getElementById('aipcw-progress').innerHTML = '<strong>Status:</strong> ' + job.status + ' &nbsp; <strong>Progress:</strong> ' + job.processed + '/' + job.total + ' (' + percent + '%)';
                            document.getElementById('aipcw-counts').textContent = 'Created: ' + job.created + ' draft(s), Updated: ' + job.updated + ' post(s), Failed: ' + job.failed + '.';
                            var errorBox = document.getElementById('aipcw-error');
                            if (job.last_error) { errorBox.style.display = ''; errorBox.querySelector('p').textContent = job.last_error; }
                            else { errorBox.style.display = 'none'; }
                            if (job.status === 'running') {
                                setTimeout(poll, 5000);
                            } else {
                                // Refresh once when the worker finishes so action
                                // buttons (Retry, Resume, Stop) match the final state.
                                setTimeout(function () { window.location.reload(); }, 500);
                            }
                        }).catch(function () { setTimeout(poll, 10000); });
                }
                setTimeout(poll, 5000);
            }());
            </script><?php endif; ?>
        </div>
        <?php
    }

    public function start_scan() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_start_scan');
        $existing = $this->job();
        if ($existing['status'] === 'running') { $this->redirect(); }
        $ids = get_posts(array('post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending'), 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array(array('key' => '_aipcw_source_post_id', 'compare' => 'NOT EXISTS'))));
        $mode = isset($_POST['mode']) && $_POST['mode'] === 'update' ? 'update' : 'draft';
        update_option(self::JOB, array('status' => 'running', 'mode' => $mode, 'queue' => array_map('absint', $ids), 'total' => count($ids), 'processed' => 0, 'created' => 0, 'created_ids' => array(), 'updated' => 0, 'updated_ids' => array(), 'failed' => 0, 'failed_ids' => array(), 'last_error' => ''), false);
        $this->redirect();
    }

    public function retry_failed() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_retry_failed');
        $job = $this->job();
        if ($job['status'] === 'running') { $this->redirect(); }
        $ids = !empty($job['failed_ids']) ? array_map('absint', $job['failed_ids']) : get_posts(array('post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending'), 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array(array('key' => '_aipcw_source_post_id', 'compare' => 'NOT EXISTS'))));
        update_option(self::JOB, array('status' => 'running', 'mode' => $job['mode'], 'queue' => array_values(array_unique($ids)), 'total' => count($ids), 'processed' => 0, 'created' => 0, 'created_ids' => array(), 'updated' => 0, 'updated_ids' => array(), 'failed' => 0, 'failed_ids' => array(), 'last_error' => ''), false);
        $this->redirect();
    }

    public function test_post() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_test_post');
        $source_id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
        $source = get_post($source_id);
        $mode = isset($_POST['mode']) && $_POST['mode'] === 'update' ? 'update' : 'draft';
        $instruction = isset($_POST['instruction']) ? sanitize_textarea_field(wp_unslash($_POST['instruction'])) : '';
        if (!$source || $source->post_type !== 'post' || !$instruction) { $this->redirect_test_error('Select a post and enter an instruction.'); }
        $result = $this->generate_with_ai($source, $this->settings(), $instruction);
        if (is_wp_error($result)) { $this->redirect_test_error($result->get_error_message()); }
        if ($mode === 'update') {
            if (!$this->update_existing_post($source, $result)) { $this->redirect_test_error('Could not update the selected post.'); }
            $this->redirect_test_success($source_id, 'update');
        }
        $draft_id = $this->create_draft($source_id, $result);
        if (!$draft_id) { $this->redirect_test_error('Could not create the test draft.'); }
        $this->redirect_test_success($draft_id, 'draft');
    }

    public function stop_scan() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_stop_scan');
        $job = $this->job(); $job['status'] = 'stopped'; $job['last_error'] = ''; update_option(self::JOB, $job, false);
        wp_clear_scheduled_hook(self::CRON); $this->redirect();
    }

    public function resume_job() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_resume_job');
        $job = $this->job();
        if ($job['status'] === 'paused_quota') { $job['status'] = 'running'; $job['last_error'] = ''; update_option(self::JOB, $job, false); }
        $this->redirect();
    }

    public function reset_generated_drafts() {
        if (!current_user_can('delete_posts')) { wp_die('You do not have permission to delete posts.'); }
        check_admin_referer('aipcw_reset_drafts');
        $job = $this->job();
        if ($job['status'] === 'running') { wp_die('Stop the background job before resetting generated drafts.'); }
        $draft_ids = get_posts(array('post_type' => 'post', 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_aipcw_source_post_id'));
        $count = 0;
        foreach ($draft_ids as $draft_id) { if (wp_trash_post($draft_id)) { $count++; } }
        update_option(self::JOB, array('status' => 'idle', 'mode' => 'draft', 'queue' => array(), 'total' => 0, 'processed' => 0, 'created' => 0, 'created_ids' => array(), 'updated' => 0, 'updated_ids' => array(), 'failed' => 0, 'failed_ids' => array(), 'last_error' => ''), false);
        wp_safe_redirect(add_query_arg(array('page' => 'ai-post-content-writer', 'aipcw_reset_drafts' => $count), admin_url('admin.php')));
        exit;
    }

    public function ajax_status() {
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied.', 403); }
        check_ajax_referer('aipcw_status', 'nonce');
        $job = $this->job();
        unset($job['queue']);
        wp_send_json_success($job);
    }

    /**
     * The store-maintenance sweeps, and what each one writes back.
     *
     * 'requires' names the theme functions a sweep calls. The parsing lives in
     * the alogweb theme (inc/alogweb-play.php) because that is the only place
     * that knows what an alogweb post stores; the plugin owns the queue and
     * nothing else. One parser to fix when Google changes its markup, rather
     * than two that drift apart.
     */
    public function sweeps() {
        return array(
            'shots' => array(
                'label'    => 'Screenshot sync',
                'blurb'    => "Re-reads every post's Play listing and rewrites its screenshot list.",
                'option'   => 'aipcw_sweep_shots',
                'cron'     => 'aipcw_sweep_shots_cron',
                'method'   => 'sweep_screenshots',
                'requires' => array('alogweb_fetch_play_html', 'alogweb_extract_screenshots'),
            ),
            'info' => array(
                'label'    => 'App info sync',
                'blurb'    => 'Refreshes developer, rating, category, install count and last-updated date. '
                            . 'Version, size and minimum Android are <strong>not touched</strong>: Play stopped '
                            . 'serving them in the HTML, so the stored values are left alone rather than blanked.',
                'option'   => 'aipcw_sweep_info',
                'cron'     => 'aipcw_sweep_info_cron',
                'method'   => 'sweep_info',
                'requires' => array('alogweb_fetch_play_html', 'alogweb_extract_app_info'),
            ),
            'store' => array(
                'label'    => 'Delisted app check',
                'blurb'    => 'Asks Play whether each app still exists. Only a 404 counts as gone - a timeout or '
                            . 'a 5xx says something about the network, not about the app.',
                'option'   => 'aipcw_sweep_store',
                'cron'     => 'aipcw_sweep_store_cron',
                'method'   => 'sweep_store_status',
                'requires' => array('alogweb_play_status'),
            ),
        );
    }

    private function sweep_def($key) {
        $sweeps = $this->sweeps();
        return isset($sweeps[$key]) ? $sweeps[$key] : null;
    }

    public function sweep_job($key) {
        $def = $this->sweep_def($key);
        if (!$def) { return array(); }
        return wp_parse_args(get_option($def['option'], array()), array(
            'status' => 'idle', 'queue' => array(), 'total' => 0, 'processed' => 0,
            'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0,
            'failed_ids' => array(), 'last_error' => '', 'finished' => '',
        ));
    }

    public function sweep_ready($key) {
        $def = $this->sweep_def($key);
        if (!$def) { return false; }
        foreach ($def['requires'] as $fn) { if (!function_exists($fn)) { return false; } }
        return true;
    }

    public function store_url_for($post_id) {
        $info = get_post_meta($post_id, '_info', true);
        if (is_object($info) && !empty($info->store_url)) { return (string) $info->store_url; }
        if (is_array($info) && !empty($info['store_url'])) { return (string) $info['store_url']; }
        return '';
    }

    public function start_sweep() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_start_sweep');
        $key = isset($_POST['sweep']) ? sanitize_key($_POST['sweep']) : '';
        $def = $this->sweep_def($key);
        if (!$def) { $this->redirect(); }
        if ($this->sweep_job($key)['status'] === 'running') { $this->redirect(); }

        if (!$this->sweep_ready($key)) {
            update_option($def['option'], array_merge($this->sweep_job($key), array(
                'status' => 'idle',
                'last_error' => 'The alogweb theme is not active, so there is no Google Play parser to call.',
            )), false);
            $this->redirect();
        }

        $ids = get_posts(array(
            'post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
        ));
        update_option($def['option'], array(
            'status' => 'running', 'queue' => array_map('absint', $ids), 'total' => count($ids),
            'processed' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0,
            'failed_ids' => array(), 'last_error' => '', 'finished' => '',
        ), false);
        wp_schedule_single_event(time() + 1, $def['cron']);
        $this->redirect();
    }

    public function stop_sweep() {
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_stop_sweep');
        $key = isset($_POST['sweep']) ? sanitize_key($_POST['sweep']) : '';
        $def = $this->sweep_def($key);
        if (!$def) { $this->redirect(); }
        $job = $this->sweep_job($key);
        $job['status'] = 'stopped';
        update_option($def['option'], $job, false);
        wp_clear_scheduled_hook($def['cron']);
        $this->redirect();
    }

    public function process_sweep($key, $schedule_next = true) {
        $def = $this->sweep_def($key);
        if (!$def) { return; }
        $job = $this->sweep_job($key);
        if ($job['status'] !== 'running') { return; }

        if (!$this->sweep_ready($key)) {
            $job['status'] = 'stopped';
            $job['last_error'] = 'The alogweb theme is not active, so there is no Google Play parser to call.';
            update_option($def['option'], $job, false);
            return;
        }

        foreach (array_splice($job['queue'], 0, self::SWEEP_BATCH_SIZE) as $post_id) {
            // Re-read rather than trusting the copy taken before the fetch: a
            // Stop pressed mid-batch has to take effect on this iteration.
            if ($this->sweep_job($key)['status'] !== 'running') { return; }
            $post_id = absint($post_id);
            $job['processed']++;

            $outcome = call_user_func(array($this, $def['method']), $post_id);
            $job[$outcome['status']]++;
            if ($outcome['status'] === 'failed') {
                $job['failed_ids'][] = $post_id;
                $job['last_error'] = sprintf('#%d: %s', $post_id, $outcome['error']);
            } else if ($outcome['status'] === 'updated') {
                $job['last_error'] = '';
            }
        }

        if ($this->sweep_job($key)['status'] !== 'running') { return; }
        if (empty($job['queue'])) {
            $job['status'] = 'completed';
            $job['finished'] = current_time('mysql');
            wp_clear_scheduled_hook($def['cron']);
        } else if ($schedule_next) {
            wp_schedule_single_event(time() + 5, $def['cron']);
        }
        update_option($def['option'], $job, false);
    }

    private function sweep_screenshots($post_id) {
        $store = $this->store_url_for($post_id);
        if ($store === '') { return array('status' => 'skipped', 'error' => ''); }

        $html = alogweb_fetch_play_html($store);
        if (is_wp_error($html)) { return array('status' => 'failed', 'error' => $html->get_error_message()); }

        $shots = alogweb_extract_screenshots($html);
        if (empty($shots)) {
            // A listing this parser cannot read is not a reason to throw away
            // screenshots that are on the page and working today.
            return array('status' => 'failed', 'error' => 'no screenshots recognised - left untouched');
        }
        if ((array) get_post_meta($post_id, '_screenshots', true) === $shots) {
            return array('status' => 'unchanged', 'error' => '');
        }
        update_post_meta($post_id, '_screenshots', $shots);
        return array('status' => 'updated', 'error' => '');
    }

    private function sweep_info($post_id) {
        $store = $this->store_url_for($post_id);
        if ($store === '') { return array('status' => 'skipped', 'error' => ''); }

        $html = alogweb_fetch_play_html($store);
        if (is_wp_error($html)) { return array('status' => 'failed', 'error' => $html->get_error_message()); }

        $fresh = alogweb_extract_app_info($html);
        if (empty($fresh)) { return array('status' => 'failed', 'error' => 'no metadata recognised - left untouched'); }

        $existing = get_post_meta($post_id, '_info', true);
        $merged = is_object($existing) ? clone $existing
                : (object) (is_array($existing) ? $existing : array());

        $changed = false;
        foreach ($fresh as $field => $value) {
            if (!isset($merged->$field) || (string) $merged->$field !== (string) $value) {
                $merged->$field = $value;
                $changed = true;
            }
        }
        if (!$changed) { return array('status' => 'unchanged', 'error' => ''); }

        update_post_meta($post_id, '_info', $merged);
        // The rating and name columns used for sorting are derived from _info
        // and would otherwise keep the values this sweep just replaced.
        if (function_exists('alogweb_index_post')) { alogweb_index_post($post_id); }
        return array('status' => 'updated', 'error' => '');
    }

    private function sweep_store_status($post_id) {
        $store = $this->store_url_for($post_id);
        if ($store === '') { return array('status' => 'skipped', 'error' => ''); }

        $result = alogweb_play_status($store);
        if (is_wp_error($result)) { return array('status' => 'failed', 'error' => $result->get_error_message()); }

        $previous = (string) get_post_meta($post_id, self::STORE_STATUS_META, true);
        update_post_meta($post_id, self::STORE_STATUS_META, $result);
        update_post_meta($post_id, self::STORE_CHECKED_META, current_time('mysql'));

        return array('status' => $previous === $result ? 'unchanged' : 'updated', 'error' => '');
    }

    public function delisted_posts() {
        return get_posts(array(
            'post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
            'meta_query' => array(array('key' => self::STORE_STATUS_META, 'value' => 'gone')),
        ));
    }

    public function cli_sweep($args, $assoc_args) {
        $key = isset($args[0]) ? sanitize_key($args[0]) : '';
        $def = $this->sweep_def($key);
        if (!$def) { WP_CLI::error('Unknown sweep. Available: ' . implode(', ', array_keys($this->sweeps()))); }
        if (!$this->sweep_ready($key)) { WP_CLI::error('The alogweb theme is not active, so there is no Google Play parser to call.'); }

        if (isset($assoc_args['start'])) {
            $ids = get_posts(array(
                'post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
                'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
            ));
            update_option($def['option'], array(
                'status' => 'running', 'queue' => array_map('absint', $ids), 'total' => count($ids),
                'processed' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0,
                'failed_ids' => array(), 'last_error' => '', 'finished' => '',
            ), false);
            WP_CLI::log(sprintf('Queued %d posts for %s.', count($ids), $def['label']));
        }

        while ($this->sweep_job($key)['status'] === 'running') {
            $this->process_sweep($key, false);
            $job = $this->sweep_job($key);
            WP_CLI::log(sprintf('%d/%d - %d updated, %d unchanged, %d skipped, %d failed',
                $job['processed'], $job['total'], $job['updated'], $job['unchanged'], $job['skipped'], $job['failed']));
        }
        $job = $this->sweep_job($key);
        WP_CLI::success(sprintf('%s: %d updated, %d unchanged, %d skipped, %d failed.',
            $def['label'], $job['updated'], $job['unchanged'], $job['skipped'], $job['failed']));
    }

    public function process_batch($schedule_next = true) {
        $job = $this->job();
        if ($job['status'] !== 'running') { return; }
        $settings = $this->settings();
        $batch = array_splice($job['queue'], 0, self::BATCH_SIZE);
        foreach ($batch as $source_id) {
            if ($this->job()['status'] !== 'running') { return; }
            $source = get_post($source_id);
            if ($source && get_post_meta($source_id, '_aipcw_source_post_id', true)) { $source = null; }
            if (!$source) { $job['failed']++; $job['failed_ids'][] = absint($source_id); $job['processed']++; continue; }
            $result = $this->generate_with_ai($source, $settings);
            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'gemini_quota_exceeded') {
                    array_unshift($job['queue'], absint($source_id));
                    $job['status'] = 'paused_quota';
                    $job['last_error'] = $result->get_error_message();
                    update_option(self::JOB, $job, false);
                    return;
                }
                $job['failed']++; $job['failed_ids'][] = absint($source_id); $job['last_error'] = $result->get_error_message();
            }
            else if ($job['mode'] === 'update') { if ($this->update_existing_post($source, $result)) { $job['updated']++; $job['updated_ids'][] = absint($source_id); $job['last_error'] = ''; } else { $job['failed']++; $job['failed_ids'][] = absint($source_id); $job['last_error'] = 'Could not update post ' . absint($source_id) . '.'; } }
            else { $draft_id = $this->create_draft($source_id, $result); if ($draft_id) { $job['created']++; $job['created_ids'][] = absint($draft_id); $job['last_error'] = ''; } else { $job['failed']++; $job['failed_ids'][] = absint($source_id); $job['last_error'] = 'Could not create draft for post ' . absint($source_id) . '.'; } }
            $job['processed']++;
        }
        if ($this->job()['status'] !== 'running') { return; }
        if (empty($job['queue'])) { $job['status'] = 'completed'; wp_clear_scheduled_hook(self::CRON); }
        else if ($schedule_next) { wp_schedule_single_event(time() + 5, self::CRON); }
        update_option(self::JOB, $job, false);
    }

    public function cli_worker($args, $assoc_args) {
        $once = isset($assoc_args['once']);
        do {
            $job = $this->job();
            if ($job['status'] === 'running') {
                $this->process_batch(false);
            } else if ($once) {
                return;
            } else {
                sleep(5);
            }
            if ($once) { return; }
        } while (true);
    }

    private function generate_with_ai($source, $settings, $instruction = '') {
        return isset($settings['provider']) && $settings['provider'] === 'gemini'
            ? $this->generate_with_gemini($source, $settings, $instruction)
            : $this->generate_with_ollama($source, $settings, $instruction);
    }

    private function generate_with_gemini($source, $settings, $instruction = '') {
        if (empty($settings['gemini_api_key'])) { return new WP_Error('gemini_missing_key', 'Gemini API key is not configured.'); }
        $source_text = wp_strip_all_tags($source->post_content);
        $source_words = count(preg_split('/\s+/u', trim($source_text), -1, PREG_SPLIT_NO_EMPTY));
        $target_words = max(250, (int) ceil($source_words * 1.1));
        $game_info = $this->game_info($source->ID);
        $prompt = "Create an original WordPress article from this source. Do not summarize or shorten it. Preserve all important sections, details, and facts, and target at least about " . $target_words . " words for the article body. Prefer 10–20% more length when useful. Do not copy sentences or structure. Do not invent information. Return JSON only with keys title, excerpt, content, meta_title, meta_description.\n\nInstruction:\n" . ($instruction ?: 'Rewrite this article with a clear, useful structure.') . "\n\nSource title:\n" . $source->post_title . "\n\nSource content:\n" . $source_text . ($game_info ? "\n\nAdditional game metadata from the database (use as factual context, not as text to copy):\n" . $game_info : '');
        $model = !empty($settings['gemini_model']) ? $settings['gemini_model'] : 'gemini-3.5-flash-lite';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($settings['gemini_api_key']);
        $response = wp_remote_post($url, array('timeout' => 600, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode(array(
            'contents' => array(array('role' => 'user', 'parts' => array(array('text' => $prompt)))),
            'generationConfig' => array('responseMimeType' => 'application/json', 'temperature' => 0.2, 'maxOutputTokens' => 7000),
        ))));
        if (is_wp_error($response)) { return $response; }
        $http_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($http_code === 429 || (isset($body['error']['status']) && $body['error']['status'] === 'RESOURCE_EXHAUSTED')) { return new WP_Error('gemini_quota_exceeded', 'Gemini quota exceeded. The job is paused; resume it after the quota resets.'); }
        if ($http_code >= 400) { return new WP_Error('gemini_http_error', isset($body['error']['message']) ? $body['error']['message'] : 'Gemini request failed.'); }
        $raw_content = isset($body['candidates'][0]['content']['parts'][0]['text']) ? $body['candidates'][0]['content']['parts'][0]['text'] : '';
        $content = $this->parse_json_object($raw_content);
        if (!is_array($content) || empty($content['title']) || empty($content['content'])) {
            $detail = is_string($raw_content) ? substr(trim($raw_content), 0, 240) : 'empty response';
            return new WP_Error('gemini_invalid_response', 'Gemini returned invalid JSON: ' . $detail);
        }
        foreach (array('title', 'excerpt', 'content', 'meta_title', 'meta_description') as $field) { if (isset($content[$field])) { $content[$field] = $this->text_value($content[$field]); } }
        $generated_words = count(preg_split('/\s+/u', trim(wp_strip_all_tags($content['content'])), -1, PREG_SPLIT_NO_EMPTY));
        if ($source_words >= 100 && $generated_words < (int) ceil($source_words * 0.9)) { return new WP_Error('gemini_content_too_short', 'Generated content is too short (' . $generated_words . '/' . $source_words . ' words); original post was not changed.'); }
        return $content;
    }

    private function generate_with_ollama($source, $settings, $instruction = '') {
        $source_text = wp_strip_all_tags($source->post_content);
        $source_words = count(preg_split('/\s+/u', trim($source_text), -1, PREG_SPLIT_NO_EMPTY));
        $target_words = max(250, (int) ceil($source_words * 1.1));
        $game_info = $this->game_info($source->ID);
        $prompt = "Create an original WordPress article from this source. Do not summarize or shorten it. Preserve all important sections, details, and facts, and target at least about " . $target_words . " words for the article body. Prefer 10–20% more length when useful. Do not copy sentences or structure. Do not invent information. Return JSON only with keys title, excerpt, content, meta_title, meta_description.\n\nInstruction:\n" . ($instruction ?: 'Rewrite this article with a clear, useful structure.') . "\n\nSource title:\n" . $source->post_title . "\n\nSource content:\n" . $source_text . ($game_info ? "\n\nAdditional game metadata from the database (use as factual context, not as text to copy):\n" . $game_info : '');
        $response = wp_remote_post(untrailingslashit($settings['ollama_url']) . '/api/chat', array(
            'timeout' => 600,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'model' => $settings['model'],
                'stream' => false,
                'format' => 'json',
                'options' => array(
                    // A larger context prevents the JSON document being cut off
                    // when the source article and generated article are long.
                    'num_ctx' => 8192,
                    'num_predict' => 7000,
                    'temperature' => 0.2,
                ),
                'messages' => array(array('role' => 'system', 'content' => 'You are a careful editorial assistant. Always finish the JSON object completely.'), array('role' => 'user', 'content' => $prompt)),
            )),
        ));
        if (is_wp_error($response)) { return $response; }
        $http_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($http_code >= 400) { return new WP_Error('ollama_http_error', isset($body['error']) ? $body['error'] : 'Ollama request failed.'); }
        $raw_content = isset($body['message']['content']) ? $body['message']['content'] : '';
        $content = $this->parse_json_object($raw_content);
        if (!is_array($content) || empty($content['title']) || empty($content['content'])) {
            $detail = is_string($raw_content) ? substr(trim($raw_content), 0, 240) : 'empty response';
            return new WP_Error('ollama_invalid_response', 'Ollama returned invalid JSON: ' . $detail);
        }
        foreach (array('title', 'excerpt', 'content', 'meta_title', 'meta_description') as $field) {
            if (isset($content[$field])) { $content[$field] = $this->text_value($content[$field]); }
        }
        if ($content['title'] === '' || $content['content'] === '') { return new WP_Error('ollama_invalid_response', 'Ollama returned empty content.'); }
        $source_words = count(preg_split('/\s+/u', trim(wp_strip_all_tags($source->post_content)), -1, PREG_SPLIT_NO_EMPTY));
        $generated_words = count(preg_split('/\s+/u', trim(wp_strip_all_tags($content['content'])), -1, PREG_SPLIT_NO_EMPTY));
        if ($source_words >= 100 && $generated_words < (int) ceil($source_words * 0.9)) {
            return new WP_Error('ollama_content_too_short', 'Generated content is too short (' . $generated_words . '/' . $source_words . ' words); original post was not changed.');
        }
        return $content;
    }

    private function parse_json_object($raw) {
        if (is_array($raw)) { return $raw; }
        if (!is_string($raw) || trim($raw) === '') { return null; }
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) { return $decoded; }
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) { return $decoded; }
        }
        return null;
    }

    private function text_value($value) {
        if (is_string($value) || is_numeric($value)) { return (string) $value; }
        if (!is_array($value)) { return ''; }
        if (isset($value['text'])) { return $this->text_value($value['text']); }
        $parts = array();
        foreach ($value as $item) { $text = $this->text_value($item); if ($text !== '') { $parts[] = $text; } }
        return implode("\n", $parts);
    }

    private function game_info($post_id) {
        $info = get_post_meta($post_id, '_info', true);
        if (empty($info)) { return ''; }
        if (is_string($info)) {
            $decoded = json_decode($info, true);
            if (is_array($decoded)) { $info = $decoded; }
        }
        if (is_object($info)) { $info = json_decode(wp_json_encode($info), true); }
        if (!is_array($info)) { return ''; }
        // json_script often contains a full duplicate description. Keep concise
        // factual fields so local models do not waste context on repeated text.
        $allowed = array('publish', 'size', 'version', 'download', 'android', 'dev', 'cat', 'ratingValue', 'operatingSystem', 'store_url');
        $filtered = array_intersect_key($info, array_flip($allowed));
        return wp_json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function create_draft($source_id, $content) {
        $draft_id = wp_insert_post(wp_slash(array('post_title' => sanitize_text_field($content['title']), 'post_excerpt' => sanitize_textarea_field(isset($content['excerpt']) ? $content['excerpt'] : ''), 'post_content' => wp_kses_post($content['content']), 'post_status' => 'draft', 'post_type' => 'post')), true);
        if (!is_wp_error($draft_id)) {
            update_post_meta($draft_id, '_aipcw_source_post_id', absint($source_id));
            update_post_meta($draft_id, '_aipcw_meta_title', sanitize_text_field(isset($content['meta_title']) ? $content['meta_title'] : ''));
            update_post_meta($draft_id, '_aipcw_meta_description', sanitize_text_field(isset($content['meta_description']) ? $content['meta_description'] : ''));
            return $draft_id;
        }
        return 0;
    }

    private function update_existing_post($source, $content) {
        $history = get_post_meta($source->ID, self::BACKUP_META, true);
        if (!is_array($history)) { $history = array(); }
        $history[] = array('time' => current_time('mysql'), 'title' => $source->post_title, 'excerpt' => $source->post_excerpt, 'content' => $source->post_content, 'meta_title' => get_post_meta($source->ID, '_aipcw_meta_title', true), 'meta_description' => get_post_meta($source->ID, '_aipcw_meta_description', true));
        update_post_meta($source->ID, self::BACKUP_META, array_slice($history, -10));
        wp_save_post_revision($source->ID);
        $updated = wp_update_post(wp_slash(array('ID' => $source->ID, 'post_title' => sanitize_text_field($content['title']), 'post_excerpt' => sanitize_textarea_field(isset($content['excerpt']) ? $content['excerpt'] : ''), 'post_content' => wp_kses_post($content['content']))), true);
        if (is_wp_error($updated)) { return false; }
        update_post_meta($source->ID, '_aipcw_meta_title', sanitize_text_field(isset($content['meta_title']) ? $content['meta_title'] : ''));
        update_post_meta($source->ID, '_aipcw_meta_description', sanitize_text_field(isset($content['meta_description']) ? $content['meta_description'] : ''));
        return true;
    }

    public function revert_post() {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) { wp_die('Permission denied.'); }
        check_admin_referer('aipcw_revert_' . $post_id);
        $history = get_post_meta($post_id, self::BACKUP_META, true);
        if (!is_array($history) || empty($history)) { wp_die('No backup is available for this post.'); }
        $backup = array_pop($history);
        wp_save_post_revision($post_id);
        wp_update_post(wp_slash(array('ID' => $post_id, 'post_title' => $backup['title'], 'post_excerpt' => $backup['excerpt'], 'post_content' => $backup['content'])));
        update_post_meta($post_id, '_aipcw_meta_title', $backup['meta_title']);
        update_post_meta($post_id, '_aipcw_meta_description', $backup['meta_description']);
        if (empty($history)) { delete_post_meta($post_id, self::BACKUP_META); } else { update_post_meta($post_id, self::BACKUP_META, $history); }
        $this->redirect();
    }

    private function redirect() { wp_safe_redirect(admin_url('admin.php?page=ai-post-content-writer')); exit; }

    private function redirect_test_success($post_id, $mode) { wp_safe_redirect(add_query_arg(array('page' => 'ai-post-content-writer', 'aipcw_test' => 'success', 'test_id' => absint($post_id), 'test_mode' => sanitize_key($mode)), admin_url('admin.php'))); exit; }

    private function redirect_test_error($message) { wp_safe_redirect(add_query_arg(array('page' => 'ai-post-content-writer', 'aipcw_test' => 'error', 'message' => rawurlencode($message)), admin_url('admin.php'))); exit; }

    private function render_backup_preview($post_id) {
        if (!current_user_can('edit_post', $post_id)) { wp_die('Permission denied.'); }
        $post = get_post($post_id);
        $history = get_post_meta($post_id, self::BACKUP_META, true);
        if (!$post || !is_array($history) || empty($history)) { wp_die('No backup is available for this post.'); }
        $preview_index = isset($_GET['backup_index']) ? absint($_GET['backup_index']) : count($history) - 1;
        if ($preview_index >= count($history)) { $preview_index = count($history) - 1; }
        $latest = $history[$preview_index];
        ?>
        <div class="wrap">
            <h1>Backup preview</h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=ai-post-content-writer')); ?>">← Back to AI Content Writer</a></p>
            <p><strong>Post:</strong> <?php echo esc_html($post->post_title); ?> &nbsp; <strong>Backup version:</strong> <?php echo esc_html(($preview_index + 1) . '/' . count($history)); ?> &nbsp; <strong>Backup time:</strong> <?php echo esc_html(isset($latest['time']) ? $latest['time'] : ''); ?></p>
            <p><strong>Current title:</strong> <?php echo esc_html($post->post_title); ?></p>
            <p><strong>Original title:</strong> <?php echo esc_html(isset($latest['title']) ? $latest['title'] : ''); ?></p>
            <h2>Original excerpt</h2><div class="card" style="background:#fff;border:1px solid #ccd0d4;padding:12px"><?php echo esc_html(isset($latest['excerpt']) ? $latest['excerpt'] : ''); ?></div>
            <h2>Original content</h2><div class="card" style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:900px"><?php echo wp_kses_post(wpautop(isset($latest['content']) ? $latest['content'] : '')); ?></div>
            <?php if ($preview_index === count($history) - 1): ?><p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="aipcw_revert" /><input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>" /><?php wp_nonce_field('aipcw_revert_' . $post_id); ?><button type="submit" class="button button-primary">Revert this latest backup</button></form></p><?php else: ?><p><em>Chỉ bản backup mới nhất có thể revert trực tiếp; hãy revert theo thứ tự từ mới nhất về cũ nhất.</em></p><?php endif; ?>
            <?php if (count($history) > 1): ?><h2>Backup versions</h2><ol><?php foreach (array_reverse($history, true) as $index => $version): ?><li><a href="<?php echo esc_url(add_query_arg(array('page' => 'ai-post-content-writer', 'aipcw_backup_preview' => $post_id, 'backup_index' => $index), admin_url('admin.php'))); ?>"><?php echo esc_html(isset($version['time']) ? $version['time'] : ''); ?> — <?php echo esc_html(isset($version['title']) ? $version['title'] : ''); ?></a></li><?php endforeach; ?></ol><?php endif; ?>
        </div>
        <?php
    }
}

new AI_Post_Content_Writer();
