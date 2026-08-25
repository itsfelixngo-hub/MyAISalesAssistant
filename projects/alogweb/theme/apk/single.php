<?php
/** App detail: sticky identity card, screenshot strip, then the article. */
if (!defined('ABSPATH')) { exit; }
get_header();

while (have_posts()) : the_post();
	$app     = alogweb_app();
	$specs   = alogweb_spec_parts($app);
	$package = alogweb_package_id($app);
?>
<nav class="crumb" aria-label="Breadcrumb">
	<a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
	<?php if ($app['cat_name']) : ?>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url($app['cat_link']); ?>"><?php echo esc_html($app['cat_name']); ?></a>
	<?php endif; ?>
	<span aria-hidden="true">/</span>
	<span><?php echo esc_html($app['name']); ?></span>
</nav>

<div class="detail">
	<aside class="idcard">
		<div class="idcard-top">
			<?php if ($app['icon']) : ?>
				<img src="<?php echo esc_url($app['icon']); ?>" width="66" height="66" decoding="async"
				     alt="<?php echo esc_attr($app['name']); ?> icon">
			<?php endif; ?>
			<div>
				<h1><?php echo esc_html($app['name']); ?></h1>
				<?php if ($app['dev']) : ?><div class="dev"><?php echo esc_html($app['dev']); ?></div><?php endif; ?>
				<?php if ($app['rating'] > 0) : ?>
					<?php echo alogweb_rating_html($app, 14); ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="idcard-act">
			<a class="btn btn-lg" href="<?php echo esc_url($app['download']); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M4 19h16"/></svg>
				Download APK<?php echo $app['size'] ? ' · ' . esc_html($app['size']) : ''; ?>
			</a>
			<?php if ($app['store_url']) : ?>
				<a class="btn btn-2 btn-lg" href="<?php echo esc_url($app['store_url']); ?>" target="_blank" rel="nofollow noopener">View on Google Play</a>
			<?php endif; ?>
		</div>

		<p class="safety">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 3 4.5 6v6c0 4.5 3 7.9 7.5 9 4.5-1.1 7.5-4.5 7.5-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
			Original link from Google Play, checked automatically every day
		</p>

		<dl class="manifest">
			<?php if ($app['version']) : ?><div><dt>Version</dt><dd><?php echo esc_html($app['version']); ?></dd></div><?php endif; ?>
			<?php if ($app['size']) : ?><div><dt>Size</dt><dd><?php echo esc_html($app['size']); ?></dd></div><?php endif; ?>
			<?php if ($app['android']) : ?><div><dt>Android</dt><dd><?php echo esc_html($app['android']); ?></dd></div><?php endif; ?>
			<?php if ($app['cat_name']) : ?><div><dt>Category</dt><dd><a href="<?php echo esc_url($app['cat_link']); ?>"><?php echo esc_html($app['cat_name']); ?></a></dd></div><?php endif; ?>
			<?php if ($package) : ?><div><dt>Package</dt><dd><?php echo esc_html($package); ?></dd></div><?php endif; ?>
			<?php if ($app['published']) : ?><div><dt>Released</dt><dd><?php echo esc_html($app['published']); ?></dd></div><?php endif; ?>
		</dl>
	</aside>

	<div class="detail-main">
		<?php if (!empty($app['screenshots'])) : ?>
			<div class="shots" role="region" aria-label="Screenshots">
				<?php foreach ($app['screenshots'] as $i => $raw) :
					$src = screenshot_rewrite_lh3_in_url($raw); ?>
					<a href="<?php echo esc_url($src); ?>" target="_blank" rel="nofollow noopener">
						<img src="<?php echo esc_url($src); ?>" loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>" decoding="async"
						     alt="<?php echo esc_attr($app['name']); ?> screenshot <?php echo (int) ($i + 1); ?>">
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<article class="article">
			<h2>About this app</h2>
			<p class="headline"><?php the_title(); ?></p>
			<?php the_content(); ?>
		</article>

		<?php if (function_exists('ads_single')) { ads_single(); } ?>

		<?php
		/* Related apps from the same category. */
		if ($app['cat_name']) :
			$related = new WP_Query(array(
				'post_type'      => 'post',
				'cat'            => get_the_category($app['id'])[0]->term_id,
				'posts_per_page' => 6,
				'post__not_in'   => array($app['id']),
				'orderby'        => 'rand',
				'no_found_rows'  => true,
			));
			if ($related->have_posts()) : ?>
				<section class="sect">
					<div class="sect-hd"><h2>More in this category</h2><span class="rule"></span></div>
					<div class="grid">
						<?php while ($related->have_posts()) { $related->the_post(); get_template_part('parts/app', 'card'); } ?>
					</div>
				</section>
			<?php endif;
			wp_reset_postdata();
		endif; ?>
	</div>
</div>
<?php endwhile;

get_footer();
