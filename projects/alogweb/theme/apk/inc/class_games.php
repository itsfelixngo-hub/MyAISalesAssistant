<?php //echo 'this is lib game';
function file_get_contents_utf8($fn) {
    $opts = array(
        'http' => array(
            'method'=>"GET",
            'header'=>"Content-Type: text/html; charset=utf-8"
        )
    );

    $context = stream_context_create($opts);
    $result = @file_get_contents($fn,false,$context);
    return $result;
}
// var_dump($game_html5);
function get_source($_tPath){
    header("Content-Type: text/html; charset=utf-8");
    $tPath = $_tPath;
    $result = file_get_contents_utf8("http://".$tPath);

    if( $result == false){
        header("Location: https://".$tPath); // fallback
        exit();
    }
    else{
        $res = mb_ereg_replace("http","https",$result);
    }
    preg_match_all('/source(.+)\'/',$res, $s);

    return $s[1][0];
}
// $result = file_get_contents_utf8("https://play.google.com/store/apps/details?id=com.wildspike.wormszone");
// preg_match_all('/<h1[^>]+class="AHFaub"[^>]*>(.*)<\/h1>/sUi', $result, $name);
// preg_match('/<script[^>]+type\=\"application\/ld\+json"[^>]*>(.*)<\/div>/', $result, $jsondata);
// preg_match('/image":"([^"]+)/s', $jsondata[1], $feature_image);
// preg_match_all('/<div[^>]+jsname="sngebd"[^>]*>(.*)<\/div>/sUi', $result, $desc);
// preg_match_all('/<div[^>]+class="BHMmbe"[^>]*>(.*)<\/div>/sUi', $result, $rate);
// preg_match('/<meta itemprop="url" content="([^"]+)">/', $result, $url);
// preg_match_all('/<button[^>]+class="Q4vdJd"[^>]*>(.*)<\/button>/sUi', $result, $m_screenshots);
// echo "<br>Name:".$name[1][0];
// echo "<br> Feature Image: ".$feature_image[1];
// echo "<br>Description:".$desc[1][0];
// echo "<br>Rate:".$rate[1][0];          
// echo "<br>Download app:".$url[1];
// $screenshots = [];
// foreach ($m_screenshots[1] as $key => $value) {
//     preg_match('/srcset="([^"]+) /s', $value, $screen);
//     array_push($screenshots, $screen[1]);
// }

// echo "<br>Sceenshots: <br>";
// echo '<pre>';
// var_dump($screenshots);

 add_action('wp_ajax_clonegame', 'clonegame_function');
 add_action('wp_ajax_nopriv_clonegame', 'clonegame_function');
 function clonegame_function() {
    $post_id = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
    $tPath = isset($_POST['store_url']) ? esc_url_raw(urldecode($_POST['store_url'])) : '';
    if (!$tPath || !preg_match('#^https?://(play\.google\.com|play\.googleusercontent\.com)/#i', $tPath)) {
        wp_send_json_error(array('message' => 'Please enter a valid Google Play URL.'));
    }
    $response = wp_safe_remote_get($tPath, array('timeout' => 30, 'redirection' => 3, 'user-agent' => 'Mozilla/5.0 (WordPress; Alogweb importer)'));
    if (is_wp_error($response)) { wp_send_json_error(array('message' => 'Could not fetch Google Play: ' . $response->get_error_message())); }
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code >= 400) { wp_send_json_error(array('message' => 'Google Play returned HTTP ' . $status_code . '. The app URL may no longer exist or may be blocked.')); }
    $result = wp_remote_retrieve_body($response);
    if (!$result) { wp_send_json_error(array('message' => 'Google Play returned an empty response.')); }
    $res = mb_ereg_replace("http", "https", $result);
    // preg_match_all('/<h1[^>]+class="AHFaub"[^>]*>(.*)<\/h1>/sUi', $result, $name);
    preg_match('/<script[^>]+type\=\"application\/ld\+json"[^>]*>(.*)<\/script>/sUi', $result, $jsondata);
    if (empty($jsondata[1])) { wp_send_json_error(array('message' => 'Google Play page format was not recognized.')); }
    $json_script = $jsondata[1];
    $schema = json_decode(html_entity_decode($json_script, ENT_QUOTES, 'UTF-8'), true);
    if (isset($schema[0]) && is_array($schema[0])) $schema = $schema[0];
    if (!is_array($schema)) $schema = array();
    $meta_value = function ($property) use ($result) {
        $match = array();
        return preg_match('/<meta[^>]+(?:name|property|itemprop)=["\']' . preg_quote($property, '/') . '["\'][^>]+content=["\']([^"\']*)["\']/i', $result, $match) ? html_entity_decode($match[1], ENT_QUOTES, 'UTF-8') : '';
    };
    $name = isset($schema['name']) ? (string) $schema['name'] : $meta_value('og:title');
    $description = isset($schema['description']) ? (string) $schema['description'] : $meta_value('description');
    $feature_image = isset($schema['image']) ? (is_array($schema['image']) ? (string) reset($schema['image']) : (string) $schema['image']) : $meta_value('og:image');
    $operatingSystem = isset($schema['operatingSystem']) ? (string) $schema['operatingSystem'] : '';
    $applicationCategory = isset($schema['applicationCategory']) ? (string) $schema['applicationCategory'] : 'game';
    $ratingValue = isset($schema['aggregateRating']['ratingValue']) ? (string) $schema['aggregateRating']['ratingValue'] : '';
    $dev = isset($schema['author']['name']) ? (string) $schema['author']['name'] : '';
    $download = $meta_value('og:url') ?: $tPath;
    $screenshots = array();
    preg_match_all('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $result, $image_matches);
    foreach (array_slice(isset($image_matches[1]) ? $image_matches[1] : array(), 0, 10) as $image_url) $screenshots[] = html_entity_decode($image_url, ENT_QUOTES, 'UTF-8');
    // Google Play embeds additional screenshots as play-lh.googleusercontent.com
    // URLs in JSON/HTML, not only in the single og:image meta tag.
    preg_match_all('~play-lh\.googleusercontent\.com/[^"\'\\<>\s]+~i', $result, $google_images);
    foreach (isset($google_images[0]) ? $google_images[0] : array() as $image_path) {
        $image_path = str_replace(array('\\/', '\\u003d', '\\u0026'), array('/', '=', '&'), html_entity_decode($image_path, ENT_QUOTES, 'UTF-8'));
        $screenshots[] = 'https://' . $image_path;
    }
    $screenshots = array_values(array_unique(array_filter($screenshots)));
    $feature_image = $feature_image ?: (isset($screenshots[0]) ? $screenshots[0] : '');
    $screenshots = array_slice($screenshots, 0, 20);
    $publish = array('', ''); $size = array('', ''); $version = array('', ''); $android = array('', '');
    $info_values = array();
    preg_match_all('/<span[^>]+class=["\'][^"\']*htlgb[^"\']*["\'][^>]*>(.*?)<\/span>/si', $result, $info_matches);
    foreach (isset($info_matches[1]) ? $info_matches[1] : array() as $value) $info_values[] = trim(wp_strip_all_tags($value));
    foreach (array('publish', 'size', 'version', 'android') as $index => $field) { if (isset($info_values[$index])) ${$field}[1] = $info_values[$index]; }
    $_catname = strtolower(sanitize_title($applicationCategory));
    // Results core
    $info = array(
            'publish' => $publish[1], 
            'size' => $size[1], 
            'version' => $version[1], 
            'download' => $download,
            'android' => $android[1], 
            'dev' => $dev,
            'cat'   => $_catname,
            'ratingValue' => $ratingValue,
            'operatingSystem' => $operatingSystem,
            'store_url' => $tPath,
            'json_script' => $json_script
        );
    $code = array(
        'ID' => $post_id,
        'name' => $name,
        'feature_image' => $feature_image,
        'screenshots' => $screenshots,
        'content' => $description,
        'details' => $info
    );
    echo json_encode($code);  
die(); }
// meta box games
function store_game_register_meta_boxes() {
    add_meta_box( 'store-game-id', __( 'Store game', 'alogweb' ), 'store_game_callback', 'post' );
    add_meta_box( 'dowload', __( 'Download', 'alogweb' ), 'download_callback', 'post' );
    add_meta_box( 'info games', __( 'Infomations games', 'alogweb' ), 'info_games_callback', 'post' );
    add_meta_box( 'screenshots', __( 'Screenshots', 'alogweb' ), 'screenshots_callback', 'post' );
    
}
add_action( 'add_meta_boxes', 'store_game_register_meta_boxes' );
function store_game_callback($post){
    $store_game = get_post_meta($post->ID, '_store_game', true);
    // echo $post->ID;
    // var_dump($info->store_url);exit;
    if($store_game) : $store_url = $store_game; else: $store_url = "#"; endif;
	$html = '<div class="section"><label><strong>Goolge play Store: </strong></lable><hr><input placeholder="https://play.google.com/store/apps/details?id=com.wildspike.wormszone" type="text" name="_store_game" value="'.$store_url.'" class="fg" id="clone_url"/><input type="button" value="Clone" id="action-clone" class="fg"></div>';
	echo $html;
}
function download_callback($post){
    $download_game = get_post_meta($post->ID, '_download_game', true);
    if($download_game): $_download = $download_game; else: $_download = "#"; endif;
    $html = '<div class="section"><label><strong>Goolge play download: </strong></lable><hr><input placeholder="https://play.google.com/store/apps/details?id=linkdesks.pop.bubblegames.bubbleshooter&rdid=linkdesks.pop.bubblegames.bubbleshooter&feature=md&offerId" id="_download" class="fg" type="text" name="_download_game" value="'.$_download.'"/></div>'; 
    echo $html;
}
function info_games_callback($post){
    $info_game = get_post_meta($post->ID, '_info', true);
    if($info_game) : $info_game = json_encode($info_game); else: $info_game = "#"; endif;
    $html = "<textarea id='info' type='hidden' name='_info'>".$info_game."</textarea>";
    echo $html;
}
function screenshots_callback($post){
    $screenshots = get_post_meta($post->ID, '_screenshots', true);
    $_screenshots = "";
    if($screenshots){
        $str = '';
        foreach ($screenshots as $key => $value) {
            $str= $str.'<input class="screenshots" type="text" name="_screenshots['.$key.']" value="'.$value.'">';
        }
        $_screenshots = $str; 
    }
    echo $_screenshots;
}

add_action('admin_head', function(){
		echo '<style>.section #action-clone{width:80px;margin-left:15px;background:#00f;color:#fff;border:none;cursor:pointer;padding:5px 15px}.section input{max-width:100%;width:85%}.section{width:100%}input.screenshots {
    width: 100%;
}textarea#info {
    width: 100%;
}</style>';
		?>
        <script type="text/javascript">
        jQuery(function ($) {     
		$(document).on("click", "#action-clone", function(){
           var store_url = encodeURIComponent($("#clone_url").val());
           var post_id = $("input[name='post_ID']").val();
           $.ajax({ // Hàm ajax
               type : "post", //Phương thức truyền post hoặc get
               dataType : "html", //Dạng dữ liệu trả về xml, json, script, or html
               url : "<?php echo admin_url("admin-ajax.php")?>", // Nơi xử lý dữ liệu
               data : {
                    action: "clonegame", //Tên action, dữ liệu gởi lên cho server
                    store_url: store_url,
                    post_ID: post_id
               },
               beforeSend: function(){
                    // Có thể thực hiện công việc load hình ảnh quay quay trước khi đổ dữ liệu ra
                    // $('#post-body').append('<div><span class="loading"><span class="counter">Loading Game...</span></p>');
               },
               success: function(response) {
                    //Làm gì đó khi dữ liệu đã được xử lý
                    var data = $.parseJSON(response);
                    if (data.success === false) {
                        alert(data.data && data.data.message ? data.data.message : 'Google Play import failed.');
                        return;
                    }
                    alert('clone success');
                    var content = data.content;

                    $("#title").val(data.name);
                    $("#_download").val(data.details.download);
                    $("#clone_url").val(data.details.store_url);
                    var info =  JSON.stringify(data.details);
                    $("#info").val(info);
                    // $("form[name='post']").append("<textarea id="info" type='hidden' name='_info'>'"+info+"'</textarea>");
                    $("form[name='post']").append('<input type="hidden" id="_feature_image" name="_feature_image" value="'+encodeURIComponent($.trim(data.feature_image))+'">');

                    tinyMCE.activeEditor.setContent(content);
                    
                    var _screenshots = data.screenshots;
                   
                    if(_screenshots.length > 0 ){
                        $("#screenshots .inside").html("");
                        $.each(_screenshots, function(i, val){
                              $("#screenshots .inside").append('<input class="screenshots" type="text" name="_screenshots['+i+']" value="'+val+'">');
                            });    
                    }
                    
               },
               error: function( jqXHR, textStatus, errorThrown ){
                    console.log( "The following error occured: " + textStatus, errorThrown );
               }
            });
            });
		});
        </script>';
        <?php
	});
function parseAttributesFromTag($tag){
    //The Regex pattern will match all instances of attribute="value"
    $pattern = '/(\w+)=[\'"]([^\'"]*)/';

    //preg_match_all used with the PREG_SET_ORDER flag will build an array
    //for each attribute-value pair present and put it in $matches. eg:
    /* with tag <answer scale='10' points="23">
    Array (
        [0] =>(
                [0] => scale='10
                [1] => scale
                [2] => 10
              )
        [1] => (
                [0] => points="23
                [1] => points
                [2] => 23
            )
    )
    */
    preg_match_all($pattern,$tag,$matches,PREG_SET_ORDER);

    $result = [];
    foreach($matches as $match){
        $attrName = $match[1];

        //parse the string value into an integer if it's numeric,
        // leave it as a string if it's not numeric,
        $attrValue = is_numeric($match[2])? (int)$match[2]: trim($match[2]);

        $result[$attrName] = $attrValue; //add match to results
    }

    return $result;
}
function append_query_string( $permalink, $post, $leavename=false ) {
    if ( $post->post_type == 'post' ) {
            add_rewrite_tag('%rule%','(\d+)');
    $info = get_post_meta($post->ID, "_info", true);
    $_param = $info->store_url;
    $params = explode("?id=", $_param);
    list( , $rule) = $params;
    // $_rule = $rule;
    $permalink = str_replace("%rule%", $rule, $permalink);
    

    }
        return $permalink;
}
// add_filter( 'post_link', 'append_query_string', 10, 3 );	
add_action('save_post','save_post_callback');
function save_post_callback($post_id){
    global $post;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    // Normal AI updates do not submit the theme's game meta fields. Do not
    // regenerate images or overwrite game metadata in that case.
    if (!isset($_POST['_feature_image']) && !isset($_POST['_info']) && !isset($_POST['_store_game']) && !isset($_POST['_download_game'])) return;
    // var_dump($post->post_type);exit; 
    if (isset($post->post_type) && $post->post_type == 'post')  {
    	// add_filter( 'post_link', 'append_query_string', 10, 3 );
        if(isset($_POST['post_title'])) $post_name = $_POST["post_title"];
        $_feature_image = isset($_POST['_feature_image']) ? urldecode($_POST['_feature_image']) : '';
        $_store_game = isset($_POST['_store_game']) ? urldecode($_POST['_store_game']) : '';
        $_download_game = isset($_POST['_download_game']) ? urldecode($_POST['_download_game']) : '';
        $_info = isset($_POST['_info']) ? stripslashes($_POST['_info']) : '';
        $info = trim($_info, "'");
        $get_info = json_decode($info);
        if (!$get_info) return;
        
        if(isset($_POST['_screenshots'])) $_screenshots = $_POST['_screenshots'];
        $post_name = preg_replace('/[^A-Za-z0-9- ]/', '', $post_name);
        $cat = isset($get_info->cat) ? sanitize_text_field($get_info->cat) : '';
        if ($cat && !has_category($cat)) wp_create_category($cat);
        
        if (!has_post_thumbnail( $post_id ) ) alog_generate_featured_image(strtolower($post_name), $_feature_image, $post_id);
        update_post_meta($post_id, '_store_game', $_store_game);
        update_post_meta($post_id, '_download_game', $_download_game);      
        update_post_meta($post_id, '_info', $get_info );

        update_post_meta($post_id, '_screenshots', $_screenshots);   
    }
}
// add_filter('post_type_link', 'change_permalink_structure', 10, 4);
function change_permalink_structure($permalink, $post, $leavename, $sample)
{
	if ( 'post' == get_post_type() ) {
		$info = get_post_meta($post->ID, "_info", true);
	    $_param = $info->store_url;
	    $params = explode("?id=", $_param);
	    list( , $rule) = $params;
	    // $_rule = $rule;
	    $permalink = str_replace($post->postname, "/".$post->postname."/".$rule, $permalink);
		// $category_terms = get_the_terms( $post->ID, 'category' );
		// if(!is_wp_error($category_terms))
		// 	$term = $category_terms[0]->slug;
		// 	$post_link = str_replace( '/' . $post->post_type . '/', '/' . $term . '/', $post_link );
	}
	// elseif ( 'metier-ville' == get_post_type() ) {
	// 	$category_terms = get_the_terms( $post->ID, 'category' );
	// 	if(!is_wp_error($category_terms))
	// 		$term = $category_terms[0]->slug;
	// 		$post_link = str_replace( '/' . $post->post_type . '/', '/' . $term . '/', $post_link );
	// }
	// elseif ( 'metier-artisans' == get_post_type() ) {
	// 	$category_terms = get_the_terms( $post->ID, 'category' );
	// 	if(!is_wp_error($category_terms))
	// 		$term = $category_terms[0]->slug;
	// 		$post_link = str_replace( '/' . $post->post_type . '/', '/' . $term . '/', $post_link );
	// }
	return $permalink;
}

// function na_parse_request( $query ) {
// 	if ( ! $query->is_main_query() || 2 != count( $query->query ) || ! isset( $query->query['page'] ) ) {
// 		return;
// 	}
// 	if ( ! empty( $query->query['name'] ) ) {
// 		$query->set( 'post_type', array( 'post', 'formulaire', 'metier-ville', 'metier-artisans', 'page' ) );
// 	}
// }
// add_action( 'pre_get_posts', 'na_parse_request' );
