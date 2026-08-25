<?php
/**
 * Template Name: Contact
 *
 * Plain PHP: no form plugin. Validation, the rotation challenge and delivery
 * live in inc/alogweb-contact.php.
 */
if (!defined('ABSPATH')) { exit; }

$sent   = false;
$errors = array();
$values = array('name' => '', 'email' => '', 'subject' => '', 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alogweb_contact'])) {
	if (!isset($_POST['_alogweb_nonce']) || !wp_verify_nonce($_POST['_alogweb_nonce'], 'alogweb_contact')) {
		$errors[] = __('The form expired. Please try again.', 'alogweb');
	} elseif (alogweb_contact_rate_limited()) {
		$errors[] = __('You have just sent a message. Please wait a minute before sending another.', 'alogweb');
	} else {
		$result = alogweb_validate_contact($_POST);
		$errors = $result['errors'];
		$values = $result['clean'];

		if (empty($errors)) {
			// Burn the challenge before sending, so a replay of the same POST
			// cannot go through even if delivery is slow.
			alogweb_consume_challenge(trim(wp_unslash($_POST['cf_token'])));

			if (alogweb_send_contact($values)) {
				alogweb_contact_mark_submitted();
				$sent   = true;
				$values = array('name' => '', 'email' => '', 'subject' => '', 'message' => '');
			} else {
				$errors[] = __('The message could not be sent. Please try again later.', 'alogweb');
			}
		}
	}
}

$token = alogweb_new_challenge();

get_header();
?>
<article class="page-single contact">
	<div class="page-hd">
		<h1><?php the_title(); ?></h1>
		<?php if (get_the_content()) : ?>
			<div class="page-desc"><?php the_content(); ?></div>
		<?php endif; ?>
	</div>

	<?php if ($sent) : ?>
		<p class="notice notice-ok" role="status">
			<?php esc_html_e('Thanks — your message is on its way. We reply to most enquiries within two working days.', 'alogweb'); ?>
		</p>
	<?php endif; ?>

	<?php if ($errors) : ?>
		<div class="notice notice-bad" role="alert">
			<p><?php esc_html_e('Please fix the following:', 'alogweb'); ?></p>
			<ul>
				<?php foreach ($errors as $error) : ?>
					<li><?php echo esc_html($error); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form class="cform" method="post" action="<?php echo esc_url(get_permalink()); ?>#form">
		<input type="hidden" name="alogweb_contact" value="1">
		<input type="hidden" name="cf_token" value="<?php echo esc_attr($token); ?>">
		<?php wp_nonce_field('alogweb_contact', '_alogweb_nonce'); ?>

		<p class="field">
			<label for="cf-name"><?php esc_html_e('Your name', 'alogweb'); ?></label>
			<input id="cf-name" name="cf_name" type="text" required minlength="2" maxlength="80"
			       autocomplete="name" value="<?php echo esc_attr($values['name']); ?>">
		</p>

		<p class="field">
			<label for="cf-email"><?php esc_html_e('Email', 'alogweb'); ?></label>
			<input id="cf-email" name="cf_email" type="email" required maxlength="120"
			       autocomplete="email" value="<?php echo esc_attr($values['email']); ?>">
		</p>

		<p class="field">
			<label for="cf-subject"><?php esc_html_e('Subject', 'alogweb'); ?></label>
			<input id="cf-subject" name="cf_subject" type="text" required minlength="3" maxlength="140"
			       value="<?php echo esc_attr($values['subject']); ?>">
		</p>

		<p class="field">
			<label for="cf-message"><?php esc_html_e('Message', 'alogweb'); ?></label>
			<textarea id="cf-message" name="cf_message" rows="7" required minlength="20" maxlength="4000"><?php
				echo esc_textarea($values['message']);
			?></textarea>
			<span class="hint"><?php esc_html_e('At least 20 characters.', 'alogweb'); ?></span>
		</p>

		<?php /* Honeypot: hidden from people, irresistible to naive bots. */ ?>
		<p class="hp" aria-hidden="true">
			<label for="cf-website">Website</label>
			<input id="cf-website" name="cf_website" type="text" tabindex="-1" autocomplete="off">
		</p>

		<fieldset class="rotate" id="form">
			<legend><?php esc_html_e('Line up the pattern', 'alogweb'); ?></legend>
			<p class="hint"><?php esc_html_e('Drag the slider until the inner circle continues the pattern around it.', 'alogweb'); ?></p>

			<?php
			$captcha_bg   = add_query_arg(array('alogweb_captcha' => $token, 'part' => 'bg'), home_url('/'));
			$captcha_disc = add_query_arg(array('alogweb_captcha' => $token, 'part' => 'disc'), home_url('/'));
			?>
			<div class="rotate-stage">
				<img class="rotate-bg" width="240" height="240" decoding="async"
				     src="<?php echo esc_url($captcha_bg); ?>"
				     alt="<?php esc_attr_e('A patterned square with a circular piece missing', 'alogweb'); ?>">
				<img class="rotate-disc" id="cf-captcha" width="240" height="240" decoding="async"
				     src="<?php echo esc_url($captcha_disc); ?>"
				     alt="<?php esc_attr_e('The missing circular piece, turned out of line', 'alogweb'); ?>">
			</div>

			<label class="vh" for="cf-rotation"><?php esc_html_e('Rotation in degrees', 'alogweb'); ?></label>
			<input id="cf-rotation" name="cf_rotation" type="range"
			       min="0" max="345" step="15" value="0">
			<output id="cf-rotation-out" for="cf-rotation">0°</output>
		</fieldset>

		<p class="actions">
			<button class="btn" type="submit"><?php esc_html_e('Send message', 'alogweb'); ?></button>
		</p>
	</form>
</article>
<?php get_footer();
