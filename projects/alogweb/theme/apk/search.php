<?php
/**
 * Search results, laid out the way a search engine does it: the URL line, then
 * the title, then an extract showing why the page matched.
 *
 * The card grid is right for browsing a category, where every item is a
 * candidate. It is wrong for a query, where the reader needs to see which words
 * matched and in what context before deciding to click.
 */
if (!defined('ABSPATH')) { exit; }
get_header();

$term  = get_search_query();
$found = (int) $GLOBALS['wp_query']->found_posts;
?>
<div class="page-hd">
	<h1><?php printf(esc_html__('Results for “%s”', 'alogweb'), esc_html($term)); ?></h1>
	<p class="res-count"><?php printf(esc_html(_n('%d result', '%d results', $found, 'alogweb')), $found); ?></p>
</div>

<?php alogweb_sort_bar(); ?>

<?php if (have_posts()) : ?>
	<ol class="serp">
		<?php while (have_posts()) : the_post();
			$app   = alogweb_app();
			$specs = alogweb_spec_parts($app);
		?>
		<li class="serp-item">
			<a class="serp-src" href="<?php echo esc_url($app['permalink']); ?>" tabindex="-1" aria-hidden="true">
				<?php if ($app['icon']) : ?>
					<img src="<?php echo esc_url($app['icon']); ?>" width="26" height="26" loading="lazy" decoding="async" alt="">
				<?php endif; ?>
				<span class="serp-url"><?php echo alogweb_result_url_line($app); ?></span>
			</a>

			<h2 class="serp-title">
				<a href="<?php echo esc_url($app['permalink']); ?>"><?php echo esc_html($app['name']); ?></a>
			</h2>

			<?php $snippet = alogweb_search_snippet(get_post(), $term); ?>
			<?php if ($snippet) : ?>
				<p class="serp-snippet"><?php echo $snippet; ?></p>
			<?php endif; ?>

			<p class="serp-meta">
				<?php if ($specs) : ?>
					<span class="spec"><?php echo implode(' <s>·</s> ', array_map('esc_html', $specs)); ?></span>
				<?php endif; ?>
				<?php echo alogweb_rating_html($app, 11); ?>
			</p>
		</li>
		<?php endwhile; ?>
	</ol>
	<?php alogweb_pagination(); ?>
<?php else : ?>
	<p class="empty">
		<?php printf(esc_html__('Nothing matched “%s”. Try a shorter keyword.', 'alogweb'), esc_html($term)); ?>
	</p>
<?php endif; ?>

<?php get_footer();
