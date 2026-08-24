<?php 
$args = array(
		'post_type' => 'post',
		// 'fields' => 'ids',
		'post__not_in' => array (get_the_ID()),
		'posts_per_page' => 15,
		'orderby' => 'rand',
		// 'paged' => $paged	
	);

$query= new WP_Query($args);

if($query->have_posts() ){
	echo '<aside>';
	echo '<div class="title-tit">Popular Games In Last 24 Hours</div>';
	echo '<ul>';
	$i= 1;
	while ($query->have_posts() ){
		$query->the_post();
		echo '<li>';
		echo '<div class="day_list_number">'.$i.'</div>';
		get_template_part('parts/top', 'game' );
		$i++;
		echo '</li>';		
	}
	wp_reset_postdata();
	echo '</ul>';
echo '</aside>';
}
wp_reset_query();

?>
