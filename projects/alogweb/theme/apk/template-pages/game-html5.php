<?php 
/**
	template name: Games html5
**/
get_header();?>
<section class="wrap-games">
	<?php
		// // Pass each argument for every time you need it.
		// global $wpdb;
		// $table = $wpdb->prefix . 'games';
		// $sql = "SELECT * FROM {$table} ";
		// $games = $wpdb->get_results( $sql , ARRAY_A );
		// $wpdb->flush();
		$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

		$args = array(
			'post_type' => 'post',
			// 'fields' => 'ids',
			// 'posts_per_page' => 16,
			// 'orderby' => 'rand',
			'paged' => $paged	
		);

		$query= new WP_Query($args);
		if($query->have_posts() ){
			alog_pagging($query);
			echo '<div style="clear:both;"></div>';
			while ($query->have_posts() ){
				$query->the_post();
				get_template_part('parts/list', 'game' );
			}
			echo '<div style="clear:both;"></div>';
			alog_pagging($query);
			wp_reset_postdata();
		}
		wp_reset_query();
	?>
</section>
<?php get_footer();?>