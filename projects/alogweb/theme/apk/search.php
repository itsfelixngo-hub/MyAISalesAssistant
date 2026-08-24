<?php
/** Search results. The query is highlighted inside each app name. */
if (!defined('ABSPATH')) { exit; }
get_header();

$term  = get_search_query();
$found = (int) $GLOBALS['wp_query']->found_posts;
?>
<div class="page-hd">
	<h1>Results for “<?php echo esc_html($term); ?>”</h1>
	<p class="res-count"><?php echo esc_html($found); ?> results</p>
</div>

<?php if (have_posts()) : ?>
	<div class="grid">
		<?php while (have_posts()) { the_post(); get_template_part('parts/app', 'card', array('highlight' => $term)); } ?>
	</div>
	<?php alogweb_pagination(); ?>
<?php else : ?>
	<p class="empty">Nothing matched “<?php echo esc_html($term); ?>”. Try a shorter keyword.</p>
<?php endif; ?>

<?php get_footer();
