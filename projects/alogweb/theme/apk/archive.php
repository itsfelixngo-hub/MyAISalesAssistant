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

<?php if (have_posts()) : ?>
	<div class="grid">
		<?php while (have_posts()) { the_post(); get_template_part('parts/app', 'card'); } ?>
	</div>
	<?php alogweb_pagination(); ?>
<?php else : ?>
	<p class="empty">No apps in this category yet.</p>
<?php endif; ?>

<?php get_footer();
