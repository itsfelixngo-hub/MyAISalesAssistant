<?php if (!defined('ABSPATH')) { exit; } ?>
</main>

<footer class="site-ft">
	<div class="wrap">
		<div class="ft-cols">
			<div>
				<span class="mark">alog<b>web</b><i>APK</i></span>
				<p>A directory of Android games and apps, linking to the official release pages. No installer files are hosted here.</p>
			</div>

			<?php $flv1 = get_menus_lv1('footer-menu'); ?>
			<?php if (!empty($flv1)) : ?>
			<div>
				<h4>Browse</h4>
				<?php foreach ($flv1 as $item) : ?>
					<a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php $ftcats = get_categories(array('hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 5)); ?>
			<?php if (!empty($ftcats)) : ?>
			<div>
				<h4>Categories</h4>
				<?php foreach ($ftcats as $cat) : ?>
					<a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"><?php echo esc_html($cat->name); ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php $blv1 = get_menus_lv1('bottom-menu'); ?>
			<?php if (!empty($blv1)) : ?>
			<div>
				<h4>About</h4>
				<?php foreach ($blv1 as $item) : ?>
					<a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="ft-base">
			<span>&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?></span>
			<span>Android&trade; is a trademark of Google LLC</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
