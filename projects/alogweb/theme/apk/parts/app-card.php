<?php
/**
 * One app in a grid. Expects $args['app'] from alogweb_app(), or falls back to
 * the current post in the loop.
 */
if (!defined('ABSPATH')) { exit; }

$app = isset($args['app']) ? $args['app'] : alogweb_app();
if (empty($app)) { return; }

$specs     = alogweb_spec_parts($app);
$highlight = isset($args['highlight']) ? $args['highlight'] : '';
$name      = esc_html($app['name']);
if ($highlight !== '') {
	$name = preg_replace('/(' . preg_quote($highlight, '/') . ')/i', '<mark>$1</mark>', $name);
}
?>
<a class="card" href="<?php echo esc_url($app['permalink']); ?>">
	<?php if ($app['icon']) : ?>
		<img src="<?php echo esc_url($app['icon']); ?>" width="52" height="52" loading="lazy" decoding="async"
		     alt="<?php echo esc_attr($app['name']); ?> icon">
	<?php else : ?>
		<span class="card-noicon" aria-hidden="true"><?php echo esc_html(mb_substr($app['name'], 0, 1)); ?></span>
	<?php endif; ?>

	<span class="card-b">
		<span class="nm"><?php echo $name; ?></span>
		<?php if ($app['dev']) : ?><span class="dev"><?php echo esc_html($app['dev']); ?></span><?php endif; ?>
		<?php if ($specs) : ?>
			<span class="spec"><?php echo implode(' <s>·</s> ', array_map('esc_html', $specs)); ?></span>
		<?php endif; ?>
		<?php if ($app['rating'] > 0) : ?>
			<span class="rating"><?php echo esc_html(number_format($app['rating'], 2)); ?><span class="meter"><i style="width:<?php echo esc_attr($app['rating_pct']); ?>%"></i></span></span>
		<?php endif; ?>
	</span>
</a>
