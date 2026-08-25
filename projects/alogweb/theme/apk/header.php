<?php if (!defined('ABSPATH')) { exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<a class="skip" href="#main">Skip to main content</a>

<header class="site-hd">
	<div class="site-hd-in wrap">
		<a class="mark" href="<?php echo esc_url(home_url('/')); ?>">
			alog<b>web</b><i>APK</i>
		</a>

		<form class="site-search" role="search" method="get"
		      action="<?php echo esc_url(home_url('/')); ?>"
		      data-suggest="<?php echo esc_url(rest_url('alogweb/v1/suggest')); ?>?q=">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<label class="vh" for="s">Search games and apps</label>
			<input type="search" id="s" name="s" placeholder="Search games and apps…"
			       value="<?php echo esc_attr(get_search_query()); ?>"
			       role="combobox" aria-expanded="false" aria-controls="s-suggest"
			       aria-autocomplete="list" autocomplete="off">
			<ul class="suggest" id="s-suggest" role="listbox"
			    aria-label="<?php esc_attr_e('Suggestions', 'alogweb'); ?>" hidden></ul>
		</form>

		<?php $lv1 = get_menus_lv1('main-menu'); ?>
		<?php if (!empty($lv1)) : ?>
		<nav class="hd-links" aria-label="Main navigation">
			<?php foreach ($lv1 as $item) : ?>
				<a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</div>

	<?php
	$cats = get_categories(array('hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 12));
	if (!empty($cats)) :
		$current = is_category() ? get_queried_object_id() : 0;
	?>
	<div class="chips-wrap wrap">
		<nav class="chips" aria-label="Categories">
			<a class="chip" href="<?php echo esc_url(home_url('/')); ?>"<?php echo is_home() || is_front_page() ? ' aria-current="page"' : ''; ?>>All</a>
			<?php foreach ($cats as $cat) : ?>
				<a class="chip" href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"<?php echo $current === $cat->term_id ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html($cat->name); ?><em><?php echo (int) $cat->count; ?></em>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
	<?php endif; ?>
</header>

<main id="main" class="wrap">
