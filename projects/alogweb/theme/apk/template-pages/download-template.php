<?php
/**
 * Template Name: Download APK
 *
 * One job on screen: send the visitor to the official release page. Navigation
 * chrome is deliberately absent so nothing competes with the download button.
 */
if (!defined('ABSPATH')) { exit; }

$pid = isset($_GET['pid']) ? absint($_GET['pid']) : 0;
$app = $pid ? alogweb_app($pid) : array();

get_header();

if (empty($app) || get_post_status($pid) !== 'publish') : ?>
	<div class="page-hd"><h1>App not found</h1></div>
	<p class="empty">This download link is no longer valid. <a href="<?php echo esc_url(home_url('/')); ?>">Back to home</a>.</p>
<?php else :
	$specs   = alogweb_spec_parts($app);
	$package = alogweb_package_id($app);
	$target  = $app['store_url'] ? $app['store_url'] : $app['permalink'];
?>
<div class="dl">
	<?php if ($app['icon']) : ?>
		<img src="<?php echo esc_url($app['icon']); ?>" width="76" height="76" decoding="async" alt="<?php echo esc_attr($app['name']); ?> icon">
	<?php endif; ?>
	<h1><?php echo esc_html($app['name']); ?></h1>

	<?php if ($specs || $app['dev']) : ?>
		<p class="spec">
			<?php
			$line = $specs;
			if ($app['dev']) { $line[] = $app['dev']; }
			echo implode(' <s>·</s> ', array_map('esc_html', $line));
			?>
		</p>
	<?php endif; ?>

	<div class="dl-box">
		<a class="btn btn-lg" id="dl-go" href="<?php echo esc_url($target); ?>" rel="nofollow noopener" target="_blank">
			Continue to Google Play<?php echo $app['size'] ? ' · ' . esc_html($app['size']) : ''; ?>
		</a>

		<div class="dl-src"><span>source</span><span><?php echo esc_html(wp_parse_url($target, PHP_URL_HOST)); ?></span></div>
		<?php if ($package) : ?>
			<div class="dl-src"><span>package</span><span><?php echo esc_html($package); ?></span></div>
		<?php endif; ?>
	</div>

	<p class="dl-note"><?php bloginfo('name'); ?>  does not host APK files. The link points straight to the official release page.</p>
	<p class="dl-back"><a href="<?php echo esc_url($app['permalink']); ?>">← Back to <?php echo esc_html($app['name']); ?></a></p>
</div>

<?php endif;

get_footer();
