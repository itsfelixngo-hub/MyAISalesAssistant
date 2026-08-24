<?php 
	global $post;

	$name = get_the_title(get_the_ID() );
	$tracking = 'onclick="trackingClick(this,\'Click '.$name.'\');"';
	
	$link = get_permalink(get_the_ID());
	$thumbnail = get_post_meta( get_the_ID(), '_feature' );
	$info = get_post_meta( get_the_ID(), '_info', true);
	

	
?>
<div class="game">
	<?php 
		if(is_object($info) ) {
			$_ratingValue = $info->ratingValue;
			$stars = floatval($_ratingValue);
			$_stars = round($stars, 1);
			$_per = ($_stars *100)/5;
		}
	?>
	<a <?=$tracking;?> title="<?=bloginfo('name')?>- <?=$name;?>" href="<?=isset($link) ? $link: "#";?>" class="pic_box">
		<div class="desc_box">
			<div class="rate">
				<div class="stars"><span class="score" title="<?=bloginfo('name')?>- <?=$name;?>" style="width:<?=(isset($_per) ) ? $_per : 0;?>%"></span><span class="star"><?=(isset($_stars)) ? $_stars : "";?></span></div>	
			</div>
			<?=$name;?>
		</div>
		<img src="<?=isset($thumbnail[0]) ? $thumbnail[0] : "#";?>" alt="<?=bloginfo('name')?>- <?=$name;?>">
		<?php if(!wp_is_mobile() ):?>
		<div class="btn-download"><span class="txt-download">Download APK</span></div>
		<?php endif;?>
	</a>
</div>