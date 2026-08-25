<?php
/** Front page: one image-led featured band, then dense grids per section. */
if (!defined('ABSPATH')) { exit; }
get_header();

/** Renders a grid of apps from a WP_Query, or nothing when there are none. */
function alogweb_render_section($title, $query, $more_link = '') {
	if (!$query->have_posts()) { return; }
	?>
	<section class="sect">
		<div class="sect-hd">
			<h2><?php echo esc_html($title); ?></h2>
			<span class="rule"></span>
			<?php if ($more_link) : ?><a href="<?php echo esc_url($more_link); ?>">see all →</a><?php endif; ?>
		</div>
		<div class="grid">
			<?php while ($query->have_posts()) { $query->the_post(); get_template_part('parts/app', 'card'); } ?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}

/* Featured: the three highest-rated posts that actually have a screenshot. */
$featured = new WP_Query(array(
	'post_type'      => 'post',
	'posts_per_page' => 3,
	'meta_key'       => '_screenshots',
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
));

if ($featured->have_posts()) : ?>
	<section class="sect">
		<div class="sect-hd"><h2>Featured</h2><span class="rule"></span></div>
		<div class="feat">
			<?php $first = true; while ($featured->have_posts()) : $featured->the_post(); $app = alogweb_app(); $shot = !empty($app['screenshots']) ? screenshot_rewrite_lh3_in_url($app['screenshots'][0]) : ''; ?>
			<article class="feat-c">
				<a class="shot" href="<?php echo esc_url($app['permalink']); ?>">
					<?php if ($first) : ?><span class="feat-tag">Editor&rsquo;s pick</span><?php endif; ?>
					<?php if ($shot) : ?>
						<img src="<?php echo esc_url($shot); ?>" loading="lazy" decoding="async" alt="<?php echo esc_attr($app['name']); ?> screenshot">
					<?php endif; ?>
				</a>
				<a class="meta" href="<?php echo esc_url($app['permalink']); ?>">
					<?php if ($app['icon']) : ?><img src="<?php echo esc_url($app['icon']); ?>" width="40" height="40" loading="lazy" decoding="async" alt=""><?php endif; ?>
					<span class="feat-t">
						<span class="nm"><?php echo esc_html($app['name']); ?></span>
						<?php $specs = alogweb_spec_parts($app); if ($specs) : ?>
							<span class="spec"><?php echo implode(' <s>·</s> ', array_map('esc_html', $specs)); ?></span>
						<?php endif; ?>
					</span>
					<?php if ($app['rating'] > 0) : ?>
						<?php echo alogweb_rating_html($app, 12); ?>
					<?php endif; ?>
				</a>
			</article>
			<?php $first = false; endwhile; ?>
		</div>
	</section>
	<?php wp_reset_postdata();
endif;

if (function_exists('ads_head')) { ads_head(); }

/* One grid per top category, largest first. */
$sections = get_categories(array('hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 4));
foreach ($sections as $cat) {
	alogweb_render_section(
		$cat->name,
		new WP_Query(array(
			'post_type'      => 'post',
			'cat'            => $cat->term_id,
			'posts_per_page' => 12,
			'no_found_rows'  => true,
		)),
		get_category_link($cat->term_id)
	);
}

get_footer();
