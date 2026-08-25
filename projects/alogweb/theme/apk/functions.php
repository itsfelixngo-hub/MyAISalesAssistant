<?php
/*
 * Error display is controlled by WP_DEBUG_DISPLAY in wp-config.php, not here.
 * Forcing display_errors on printed notices into the page body, which then
 * broke every wp_redirect() with "Cannot modify header information".
 */

include 'inc/class_games.php';
include 'inc/alogweb-data.php';
// include 'inc/class_yoast_seo.php';
/*
 * Theme setup must run on after_setup_theme. Calling __() while this file is
 * still being included loads the 'alogweb' textdomain before init, which
 * WordPress 6.7+ reports as "_load_textdomain_just_in_time was called
 * incorrectly" - and once that notice is printed, headers can no longer be sent.
 */
add_action( 'after_setup_theme', 'alogweb_theme_setup' );
function alogweb_theme_setup() {
	load_theme_textdomain( 'alogweb' );
	register_nav_menus(
			array(
				'main-menu'    => __( 'Main menu', 'alogweb' ),
				'bottom-menu' => __( 'Bottom menu', 'alogweb' ),
	            'footer-menu' => __( 'Footer menu', 'alogweb' ),
			)
		);
	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	// Adding Shortcodes to the_excerpt() function
	add_filter('the_excerpt', 'do_shortcode');

	// Enable shortcodes in widgets
	add_filter('widget_text', 'do_shortcode');
	/*
	 * Enable support for Post Formats.
	 *
	 * See: https://wordpress.org/support/article/post-formats/
	 */
	add_theme_support(
		'post-formats',
		array(
			'aside',
			'image',
			'video',
			'quote',
			'link',
			'gallery',
			'audio',
		)
	);

	// Add theme support for Custom Logo.
	add_theme_support(
		'custom-logo',
		array(
			'width'      => 250,
			'height'     => 250,
			'flex-width' => true,
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'script',
				'style',
			)
		);
}

	function alog_scripts(){
		// Google Fonts is the only external host the front end talks to. jQuery,
		// Font Awesome and main.js are gone: nothing in the templates uses them
		// any more, and they cost 141KB for a menu toggle.
		wp_enqueue_style( 'alogweb-fonts', 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap', array(), null );
		wp_enqueue_style( 'alogweb-style', get_stylesheet_uri(), array( 'alogweb-fonts' ), wp_get_theme()->get( 'Version' ) );
		wp_enqueue_script( 'alogweb-stars', get_theme_file_uri( '/assets/js/rating-stars.js' ), array(), '2.0.0', true );
	}
	add_action( 'wp_enqueue_scripts', 'alog_scripts' );

	// Emoji script and the block-library CSS are dead weight on this front end.
	add_action( 'init', function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	} );
	add_action( 'wp_enqueue_scripts', function () {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'classic-theme-styles' );
	}, 100 );
	// The global styles blob is printed by its own action, so dequeuing the
	// 'global-styles' handle does nothing - the action has to go instead.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	add_action('admin_head', function(){
		echo '<style> #adminmenu .wp-menu-image img {width: 20px;}.cmb2-wrap input, .cmb2-wrap textarea {width: 98%;}</style>';
	});
// yoast seo category
// if( isset($_POST['wpseo_noindex']) ){
//     $meta_robots = $_POST['wpseo_noindex'];
//     $catID = $_POST['tag_ID'];
//     switch ($meta_robots) {
//         case 'index':
//             update_term_meta($catID, '_yoast_wpseo_meta-robots-noindex', 2 );
//             break;
//          case 'noindex':
//             update_term_meta($catID, '_yoast_wpseo_meta-robots-noindex', 1 );
//             break;
//         default:
//              update_term_meta($catID, '_yoast_wpseo_meta-robots-noindex', 0 );
//             break;
//     }
    
// }
//beadcrumbs
if(!function_exists('breadcrumb_cat')):
function breadcrumb_cat( $term_id, $separator='/') {
    $list = '';
    $args = '';
    $taxonomy = '';
    $term = get_term( $term_id, 'category' );

    if ( is_wp_error( $term ) ) {
        return $term;
    }

    if ( ! $term ) {
        return $list;
    }

    $term_id = $term->term_id;

    $defaults = array(
        'format'    => 'name',
        'separator' => $separator,
        'link'      => true,
        'inclusive' => true,
    );

    $args = wp_parse_args( $args, $defaults );

    foreach ( array( 'link', 'inclusive' ) as $bool ) {
        $args[ $bool ] = wp_validate_boolean( $args[ $bool ] );
    }

    $parents = get_ancestors( $term_id, $taxonomy, 'taxonomy' );

    if ( $args['inclusive'] ) {
        array_unshift( $parents, $term_id );
    }
    foreach ( array_reverse( $parents ) as $key=>$term_id ) {
        $pos = intval($key + 2);
        $parent = get_term( $term_id, $taxonomy );
        $name   = ( 'slug' === $args['format'] ) ? $parent->slug : $parent->name;
        if ( $args['link'] ) {
            $list .= '<li class="beadrumbs" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href="' . esc_url( get_term_link( $parent->term_id, $taxonomy ) ) . '" title="'.strtolower($name).'" ><span itemprop="name">' . wp_trim_words($name, 2, "...") . '</span></a><meta itemprop="name" content="'.$name.'"><meta itemprop="position" content="'.$pos.'">' . $args['separator'].'</li>';

        } else {
            $list .= $name . $args['separator'];
        }
       
    }

    return $list;
}
endif;
if(!function_exists('the_breadcrumb')):
function the_breadcrumb($id='') {
    $parent_post_id = '';
    global $post;
    $icon_home = '<svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px"
    width="15" height="15"
    viewBox="0 0 50 50"
    style=" fill:#999999;">    <path d="M 25 1.0507812 C 24.7825 1.0507812 24.565859 1.1197656 24.380859 1.2597656 L 1.3808594 19.210938 C 0.95085938 19.550938 0.8709375 20.179141 1.2109375 20.619141 C 1.5509375 21.049141 2.1791406 21.129062 2.6191406 20.789062 L 4 19.710938 L 4 46 C 4 46.55 4.45 47 5 47 L 19 47 L 19 29 L 31 29 L 31 47 L 45 47 C 45.55 47 46 46.55 46 46 L 46 19.710938 L 47.380859 20.789062 C 47.570859 20.929063 47.78 21 48 21 C 48.3 21 48.589063 20.869141 48.789062 20.619141 C 49.129063 20.179141 49.049141 19.550938 48.619141 19.210938 L 25.619141 1.2597656 C 25.434141 1.1197656 25.2175 1.0507812 25 1.0507812 z M 35 5 L 35 6.0507812 L 41 10.730469 L 41 5 L 35 5 z"></path></svg>';
    echo '<ul class="beadrumbs" itemscope itemtype="http://schema.org/BreadcrumbList" >';
    if(is_front_page()){
        $icon_home = '<i class="icon-ello icon-home">&#xe800;</i>'.get_bloginfo('tagline').' - '.get_bloginfo('description');
        $name = get_bloginfo('tagline').' - '.get_bloginfo('description');
        echo '<li class="home" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href='.get_bloginfo('url').'>'.$icon_home.'&nbsp; &raquo; </a><meta itemprop="name" content="'.$name.'"><meta itemprop="position" content="1"></li>'; 
    }else{
        if(is_category() ){
            echo '<li class="home" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href='.get_bloginfo('url').'>'.$icon_home.'&nbsp; &raquo; </a><meta itemprop="name" content="'.get_bloginfo("name").'"><meta itemprop="position" content="1"></li>'.breadcrumb_cat($id, ' &raquo; ');
        }
        if( is_singular('post') ){
            $cats = get_the_category($id);
            echo '<li class="home" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href='.get_bloginfo('url').'><span>'.$icon_home.'&nbsp; &raquo; </span></a><meta  itemprop="name" content="'.get_bloginfo("name").'"><meta itemprop="position" content="1"></li>'.breadcrumb_cat($cats[0]->term_id, ' &raquo; ').'<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="item-title"><a itemprop="item" href="'.get_permalink($id).'"><span itemprop="name">'.wp_trim_words(get_the_title($id), 50, "...").'</span></a><meta itemprop="position" content="3"></li>';
            
        }
        // if(is_singular('version')){
        //     $parent_post_id =get_post_meta( $id, '_parent_post_version', true );
        //     $cats = get_the_category($parent_post_id);
        //     // var_dump($cats) ;
        //     echo '<li class="home" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href='.get_bloginfo('url').'><span>'.$icon_home.'&nbsp; &raquo; </span></a><meta itemprop="name" content="'.get_bloginfo("name").'"><meta itemprop="position" content="1"></li>'.breadcrumb_cat($cats[0]->term_id, ' &raquo; ').'<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem" class="item-title"><a itemprop="item" href="'.get_permalink($id).'"><span itemprop="name">'.wp_trim_words(get_the_title($id), 50, "...").'</span></a><meta itemprop="position" content="4"></li>';
        // }
        if(is_page()){
            echo '<li class="home" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href='.get_bloginfo('url').'><span>'.$icon_home.'&nbsp; &raquo; </span></a><meta itemprop="name" content="'.get_bloginfo("name").'"><meta itemprop="position" content="1"></li>'.'<li class="item-title" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a itemprop="item" href="'.get_permalink($id).'"><span itemprop="name">'.wp_trim_words(get_the_title($id), 50, "...").'</span></a><meta itemprop="position" content="2"></li>';   
        }
    }
    echo '</ul>';
   
}
endif;
function alog_pagging($query){
	$total_pages = $query->max_num_pages;
    if ($total_pages > 1){
        $current_page = max(1, get_query_var('paged'));
        echo '<div class="pagging">';
        echo paginate_links(array(
            'base' => get_pagenum_link(1) . '%_%',
            'format' => '/page/%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text'    => __('« prev'),
            'next_text'    => __('next »'),
        ));
        echo '</div>';
    }
}
function alog_generate_featured_image( $post_name, $image_url, $post_id  ){
    if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
        return 0;
    }
    $upload_dir = wp_upload_dir();
    $response = wp_safe_remote_get($image_url, array('timeout' => 20));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
        return 0;
    }
    $image_data = wp_remote_retrieve_body($response);
    $filename = sanitize_file_name($post_name).".webp";
    // echo wp_mkdir_p($upload_dir['path']);exit;
    if(wp_mkdir_p($upload_dir['path']))
      $file = $upload_dir['path'] . '/' . $filename;
    else
      $file = $upload_dir['basedir'] . '/' . $filename;
    
    if(! file_exists($file) && $image_data ){
        file_put_contents($file, $image_data);
        $wp_filetype = wp_check_filetype($filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($post_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        // list($file) = explode(' ',$file);
        $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        // set_post_thumbnail( $post_id, $attach_id );

        $_basefeature = wp_get_attachment_url($attach_id);
        // echo $_basefeature; exit;
        update_post_meta($post_id, '_feature', $_basefeature);
    }
}

/**
 * Check Google Play URLs stored in _store_game.
 * Dry-run: wp alogweb check-store-links
 * Move exact HTTP 404 posts to Trash: wp alogweb check-store-links --trash-404
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('alogweb check-store-links', function ($args, $assoc_args) {
        $limit = isset($assoc_args['limit']) ? max(1, absint($assoc_args['limit'])) : -1;
        $trash_404 = isset($assoc_args['trash-404']);
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'pending'),
            'posts_per_page' => $limit,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(array('key' => '_store_game', 'compare' => 'EXISTS')),
        ));
        $checked = 0; $missing = 0; $trashed = 0; $skipped = 0;
        foreach ($posts as $post) {
            $url = trim((string) get_post_meta($post->ID, '_store_game', true));
            if (!$url || !wp_http_validate_url($url)) { $skipped++; WP_CLI::warning('Post ' . $post->ID . ': invalid or empty _store_game'); continue; }
            $response = wp_safe_remote_get($url, array('timeout' => 20, 'redirection' => 3, 'user-agent' => 'Alogweb Store Link Checker/1.0'));
            $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
            $checked++;
            if ($code === 404) {
                $missing++;
                if ($trash_404) {
                    if (wp_trash_post($post->ID)) { $trashed++; WP_CLI::warning('Trashed post ' . $post->ID . ': ' . $post->post_title); }
                } else { WP_CLI::log('404 post ' . $post->ID . ': ' . $post->post_title); }
            } elseif ($code === 0) {
                $skipped++; WP_CLI::warning('Post ' . $post->ID . ': request failed or timed out');
            } else {
                WP_CLI::log('OK ' . $code . ' post ' . $post->ID . ': ' . $post->post_title);
            }
        }
        WP_CLI::success('Checked: ' . $checked . ' | 404: ' . $missing . ' | Trashed: ' . $trashed . ' | Skipped: ' . $skipped);
    });
}
/**
 *    Intermediate function to call add_action with parameters
 */
// function call_somefunction() { 
// 	global $wpdb;
// 	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
// 	$table = $wpdb->prefix . 'games';
// 	$charset_collate = $wpdb->get_charset_collate();

// 	// Write creating query
// 	$sql =  "CREATE TABLE IF NOT EXISTS  ".$table." (
// 	            game_id INT(11) AUTO_INCREMENT,
// 	            game_name VARCHAR(255),
// 	            game_feature VARCHAR(255),
// 	            game_thumb VARCHAR(255),
// 	            game_cat VARCHAR(255),
// 	            game_link VARCHAR(255),
// 	            game_source VARCHAR(255),
// 	            order_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
// 	            PRIMARY KEY(game_id)
// 	            )$charset_collate;";
// 	dbDelta( $sql );

// 	$filename = get_theme_file_uri( '/json/games_html5.json');
// 	$games = json_decode(file_get_contents($filename), true);
// 	foreach ($games as $g) {
// 		$game_id     = $g['id'];
// 		$game_name    = $g['name'];
// 		$game_feature = $g['fi'];
// 		$game_thumb    = $g['thumb'];
// 		$game_cat  = $g['cat'];
// 		$game_link   =$g['link'];
// 		$game_source  = $g['source'];
// 		$now      = new DateTime(); //string value use: %s
// 		$order_time = $now->format('Y-m-d H:i:s'); //string value use: %s

// 		$sql = $wpdb->prepare("INSERT INTO `$table` (`game_id`, `game_name`,`game_feature`, `game_thumb`, `game_cat`, `game_link`, `game_source`, `order_time`) values (%d, %s, %s, %s, %s, %s, %s, %s)", $game_id, $game_name, $game_feature, $game_thumb, $game_cat, $game_link, $game_source, $order_time);

// 		$wpdb->query($sql);
// 	}

// }
// add_action( 'cmb2_admin_init', 'source_metabox' );
// function source_metabox(){
// 	$prefix = '_';
// 	$cmb = new_cmb2_box( array(
// 		'id'           => $prefix .'option_game',
// 		'title'        => 'Source Games',
// 		'object_types' => array( 'post' ),
// 	) );
// 	$cmb->add_field( array(
// 		'name'    => 'URL Source',
// 		'desc'    => 'URL Source',
// 		// 'default' => 'standard value (optional)',
// 		'id'      => $prefix .'source',
// 		'type'    => 'text',
// 	) );
// }
/**
 * Register a custom menu page.
 */
// function wpdocs_register_my_custom_menu_page(){
//     add_menu_page( 
//         __( 'Import data', 'textdomain' ),
//         'Import data',
//         'manage_options',
//         'import_data',
//         'call_somefunction',
//         get_theme_file_uri( '/images/sync.svg' ),
//         6
//     ); 
//     add_menu_page( 
// 	    __( 'Update post games', 'textdomain' ),
// 	    'Update post games',
// 	    'manage_options',
// 	    'insert_games',
// 	    'alog_insert_games',
// 	    get_theme_file_uri( '/images/sync.svg' ),
// 	    7
// 	);
// }
//add_action( 'admin_menu', 'wpdocs_register_my_custom_menu_page' );
// Insert post with game ID
// insert post
function alog_insert_post_id($post_id='', $post_title= '', $post_content='', $post_category= '', $post_status= '') {
	$post_options = array();
	if( !empty($post_id) ) $post_options['import_id'] = wp_strip_all_tags($post_id); 
	if( !empty($post_title) ) $post_options['post_title'] = wp_strip_all_tags($post_title);
	if( !empty($post_content) ) $post_options['post_content'] = wp_strip_all_tags($post_content);
	if( !empty($post_category) ) $post_options['post_category'] = array($post_category); 
	if( !empty($post_status) ) $post_options['post_status'] = $post_status; 
	// if( !empty($post_feature) ) $post_options['post_mime_type'] = $post_feature; 
	
	wp_insert_post($post_options);
}

// function alog_insert_games(){	
// 	global $wpdb;
// 	$table = $wpdb->prefix . 'games';
// 	$sql = "SELECT * FROM {$table} ";
// 	$games = $wpdb->get_results( $sql , ARRAY_A );
// 	$wpdb->flush();
// 	// echo '<pre>';
// 	// var_dump($games);
// 	$cats = array();
// 	foreach ($games as $g) { 
// 		if(in_array($g['game_cat'], $cats) || has_category($g['game_cat']) == true ) continue;
// 		wp_create_category($g['game_cat']);
// 		// update_term_meta(get_cat_ID($g['game_cat']) , '_wpseo_noindex', 'index' );
// 	}
// 	foreach ($games as $g) {
// 		if (get_post_status($g['game_id'])== true ) break;
// 		$post_id = $g['game_id'];
// 		$post_title = $g['game_name'];
// 		$post_content= $g['game_name'];
// 		$post_category = get_cat_ID($g['game_cat']);
// 		$post_status= 'publish'; 
// 		$post_feature = $g['game_thumb'];

// 		$post_source = $g['game_source']; 
// 		preg_match('/[\/\/\.\/www]+(.*)/s', $post_source, $source); 
// 		$post_source = $source[0];

// 		$get_image_file = $g['game_feature'];
// 		preg_match('/[\/\/\.\/www]+(.*)/s', $get_image_file, $file);


		
// 		alog_insert_post_id($post_id, $post_title, $post_content, $post_category, $post_status);
// 		//robots index yoast post
		
// 		update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', 2);
// 		alog_generate_featured_image( 'https://'.$file[1], $post_id  );
// 		update_post_meta($post_id, '_feature', $post_feature);
// 		update_post_meta($post_id, '_source', $post_source);
// 	}
// }
//
function get_menus_lv1($name){
  $locations = get_nav_menu_locations();
  $lv1= [];
    if(isset($locations[ $name ]) ):
      $menu = wp_get_nav_menu_object( $locations[ $name ] );
      $menus = wp_get_nav_menu_items( $menu->term_id, array( 'order' => 'DESC' ) );   
    if(is_array($menus) ) {
    	foreach($menus as $key => $val){
    		$_lv1 = [];
    		if($val->menu_item_parent == 0 ){
    			$_lv1["ID"] = $val->ID;
    			$_lv1["name"] = $val->title;
    			$_lv1["url"] = $val->url;
    			// $item = [
    			// 	$_lv1["ID"] => $_lv1
    			// ]; 
    			array_push( $lv1, $_lv1);
    		}else{ continue;}
    		
    	}
    }
    endif;
	return $lv1;
}
function get_menus_lv2($id_menu, $id_lv1){
	$menus = wp_get_nav_menu_items($id_menu);
	$lv2= [];
    if(is_array($menus)):
	foreach($menus as $key => $val){
		$_lv2 = [];
		if($val->menu_item_parent == 0 ){ continue; }else {
			if($val->menu_item_parent == $id_lv1 ){
				$_lv2["ID"] = $val->ID;
				$_lv2["name"] = $val->title;
				$_lv2["url"] = $val->url;
				array_push( $lv2, $_lv2);
			}else{continue;}
			
		}
	}
    endif;
	return $lv2;
}
if(is_user_logged_in() ){
    

}
// function rudr_post_permalink( $permalink, $post){
//     add_rewrite_tag('%rule%','(\d+)');
//     $info = get_post_meta($post->ID, "_info", true);
//     $_param = $info->store_url;
//     $params = explode("?id=", $_param);
//     list( , $rule) = $params;
//     // $_rule = $rule;
//     $permalink = str_replace("%rule%", $rule, $permalink);
    
//     return $permalink;
// }
// add_filter( 'post_link', 'rudr_post_permalink' );
if(!function_exists("ads_head")):
    function ads_head(){
        $ads_head = "";
        $ads_head .= '<center>';
        $ads_head .= '';
        $ads_head .= '</center>';
        echo $ads_head;
    }
endif;
if(!function_exists("ads_footer")):
    function ads_footer(){
        $ads_footer = "";
        $ads_footer .= '<center>';
        $ads_footer .= '';
        $ads_footer .= '</center>';
        echo $ads_footer;
    }
endif;
if(!function_exists("ads_single")):
    function ads_single(){
        $ads_single = "";
        $ads_single .= '<center>';
        $ads_single .= '';
        $ads_single .= '</center>';
        echo $ads_single;
    }
endif;

function screenshot_rewrite_lh3_in_url($url) {
    if (empty($url)) return $url;

    // Lấy host từ site URL
    $host = parse_url(home_url(), PHP_URL_HOST);
    if (!$host) return $url;

    // Tuỳ chọn: bỏ www.
    $host = preg_replace('/^www\./i', '', $host);

    // Tạo host tĩnh: static.example.com
    $staticHost = 'static.' . $host;

    // Thay domain ảnh lh3 -> static.{host}/lh3...
    return preg_replace(
        '#^https?://([^.\/]+)\.googleusercontent\.com/#',
        'https://' . $staticHost . '/lh3.googleusercontent.com/',
        $url
    );
}


/**
 * Pagination for the main query. alog_pagging() is kept for any old template
 * that still passes its own WP_Query.
 */
function alogweb_pagination() {
	$chevron = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>';

	$links = paginate_links( array(
		'type'      => 'array',
		'mid_size'  => 1,
		'prev_text' => sprintf( $chevron, 'm15 18-6-6 6-6' ) . '<span class="vh">Previous page</span>',
		'next_text' => sprintf( $chevron, 'm9 18 6-6-6-6' ) . '<span class="vh">Next page</span>',
	) );
	if ( empty( $links ) ) { return; }
	echo '<nav class="pager" aria-label="Pagination"><ul>';
	foreach ( $links as $link ) { echo '<li>' . $link . '</li>'; }
	echo '</ul></nav>';
}

/**
 * Rating: the score as text, with a canvas the script fills with stars.
 *
 * The number stays in the markup on purpose - it is the actual data, and it has
 * to survive a crawler, a screen reader and the moment before the script runs.
 */
function alogweb_rating_html( $app, $size = 12 ) {
	if ( empty( $app['rating'] ) || $app['rating'] <= 0 ) { return ''; }
	$score = number_format( (float) $app['rating'], 2 );

	return sprintf(
		'<span class="rating" role="img" aria-label="%1$s"><canvas class="stars" data-rating="%2$s" data-size="%3$d" width="%4$d" height="%3$d"></canvas><b>%5$s</b></span>',
		esc_attr( sprintf( 'Rated %s out of 5', $score ) ),
		esc_attr( $score ),
		(int) $size,
		(int) ( 5 * $size + 4 * max( 1, round( $size * 0.18 ) ) ),
		esc_html( $score )
	);
}
