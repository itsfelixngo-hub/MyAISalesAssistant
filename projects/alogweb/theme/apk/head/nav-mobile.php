<div id="mobile" class="menus-mobile">
	<div class="nav-mobile">
		<div id="btn-menu"></div>
		<div class="logo">
		    <span class="blogname">
		    	<?php 
		    		echo esc_html(wp_get_theme()->get( 'TextDomain' ));
		    	?>
		    </span><br>
		    <span class="slogan">Home stays online</span>
		</div>
		 <form method="get" action="<?=bloginfo('url');?>">
			<div class="search">
			  <input type="checkbox" id="trigger" class="search__checkbox" />
			  <label class="search__label-init" for="trigger"></label>
			  <label class="search__label-active" for="trigger"></label>
			  <div class="search__border"></div>
			  <input type="text" class="search__input" type="text" placeholder="Search for a APK" name="s"/>
			  <input style="display: none;" type="submit" name="">
			  <div class="search__close"></div>
			</div>
		</form>
	</div>

  <div id="menus-content">
	<ul class="nav" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
	    <?php 
	        $lv1 = get_menus_lv1("main-menu"); 
	        foreach($lv1 as $key=>$val): ?>
	        <li itemprop="name">
	            <a itemprop="url" href="<?php echo $val["url"];?>"><?php echo $val["name"];?></a>
	            <?php 
	            $lv2 = get_menus_lv2( "main-menu", $val["ID"] );
	            if(sizeof($lv2) > 0 ){ 
	                echo '<ul class="sub-menu">';
	                foreach($lv2 as $key =>$val ):
	                ?>
	            <li itemprop="name">
	                <a itemprop="url" href="<?php echo $val["url"];?>"><?php echo $val["name"];?></a>
	            </li>    
	        <?php  endforeach; echo '</ul>'; } ?>
	        </li>    
	        
	    <?php endforeach;?>
	</ul>
  </div>
</div>