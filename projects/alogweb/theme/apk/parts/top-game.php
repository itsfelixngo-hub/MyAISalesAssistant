<?php 
	$name = get_the_title();
	$cat = get_the_category($id);
	$id = get_the_ID();
	$cat_name = $cat[0]->name;
	$tracking = 'onclick="trackingClick(this,\'Click '.$name.'\');"';
	
	$link = get_permalink($id);
	$thumbnail = get_post_meta( $id, '_feature' );
	$info = get_post_meta( $id, '_info', true);
?>
<a <?=$tracking;?> title="<?=bloginfo('name')?>- <?=$name;?>" href="<?=$link;?>" class="pic_box">
<dl class="li-game">
	<?php 
		if(is_object($info) ) {
			$_ratingValue = $info->ratingValue;
			$stars = floatval($_ratingValue);
			$_stars = round($stars, 1);
			$_per = ($_stars *100)/5;
		}
	?>
	<dt><img src="<?=isset($thumbnail[0]) ? $thumbnail[0] : "#";?>" alt="<?=bloginfo('name')?>- <?=$name;?>"></dt>
	<dd class="dd-title"><?=isset($name) ? $name : "";?></dd>
	<dd class="dd-cat"><?=isset($cat_name) ? $cat_name : "";?></dd>
	<dd style="float: right;margin-right: 30px;"><div class="stars"><span class="score" title="<?=bloginfo('name')?>- <?=$name;?>" style="width:<?=(isset($_per) ) ? $_per : 0;?>%"></span><span class="star"><?=(isset($_stars)) ? $_stars : "";?></span></div>	</dd>
	<?php if(!wp_is_mobile() ):?>
	<dd class="dd-download">
		<div class="btn-download">
			<span class="txt-download">Download APK</span>
		</div>
	</dd>
	<?php endif;?>
</dl>
</a>