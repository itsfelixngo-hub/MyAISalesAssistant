<?php
/** Static pages: a single readable column. */
if (!defined('ABSPATH')) { exit; }
get_header();

while (have_posts()) : the_post(); ?>
	<article class="page-single">
		<div class="page-hd"><h1><?php the_title(); ?></h1></div>
		<div class="article"><?php the_content(); ?></div>
	</article>
<?php endwhile;

get_footer();
