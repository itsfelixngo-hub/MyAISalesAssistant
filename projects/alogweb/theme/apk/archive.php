<?php
/** Category and other archives: page heading, then the same grid as the home page. */
if (!defined('ABSPATH')) { exit; }
get_header();

$term  = get_queried_object();
$count = isset($term->count) ? (int) $term->count : 0;
?>
<div class="page-hd">
	<?php the_archive_title('<h1>', '</h1>'); ?>
	<?php if ($count) : ?><p class="res-count"><?php echo esc_html($count); ?> apps</p><?php endif; ?>
	<?php the_archive_description('<p class="page-desc">', '</p>'); ?>
</div>

<?php alogweb_sort_bar(); ?>

<?php if (have_posts()) : ?>
	<div class="grid">
		<?php
		// The native banner goes after the fourth card and spans the row. The grid
		// runs 1 to 4 columns by screen width, so that is the end of the first row
		// at full width, and dense packing keeps the grid free of holes otherwise.
		$alogweb_card = 0;
		while (have_posts()) {
			the_post();
			get_template_part('parts/app', 'card');
			if (++$alogweb_card === 4 && function_exists('alogweb_ads_list_native')) {
				echo alogweb_ads_list_native('alog-ad-in-grid');
			}
		}
		?>
	</div>
	<?php if (function_exists('alogweb_ads_list_bottom')) { alogweb_ads_list_bottom(); } ?>
	<?php alogweb_pagination(); ?>
<?php else : ?>
	<p class="empty">No apps in this category yet.</p>
<?php endif; ?>

<?php get_footer();
