<?php
/**
 * Contact form: challenge, validation and delivery.
 *
 * Mirrors the contract ExchangeHub already uses (same environment variable
 * names, same 15-degree rotation steps, same tolerance and timing defaults) so
 * both sites can be pointed at one mail server with one set of values.
 *
 * One deliberate difference: the image is rotated on the server with GD, so the
 * angle never appears in the HTML. ExchangeHub rotates with a CSS transform,
 * which hands the answer to any bot that reads the page.
 */

if (!defined('ABSPATH')) { exit; }

const ALOGWEB_CAPTCHA_PREFIX = 'alogweb_captcha_';
const ALOGWEB_CAPTCHA_TTL    = 900;   // 15 minutes to fill the form in

function alogweb_contact_env($name, $default = '') {
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
}

function alogweb_contact_config() {
    $to = alogweb_contact_env('SITE_CONTACT_EMAIL', get_option('admin_email'));
    return array(
        'to'         => alogweb_contact_env('CONTACT_FORWARD_TO', $to),
        'from'       => alogweb_contact_env('CONTACT_FROM_EMAIL', $to),
        'rate_limit' => (int) alogweb_contact_env('CONTACT_RATE_LIMIT_SECONDS', '60'),
        'min_time'   => (int) alogweb_contact_env('CONTACT_MIN_SUBMIT_SECONDS', '3'),
        'tolerance'  => (int) alogweb_contact_env('CONTACT_ROTATION_TOLERANCE', '8'),
    );
}

/* ------------------------------------------------------------------ challenge */

/** Shortest angle between two bearings, so 350 and 10 are 20 apart, not 340. */
function alogweb_angular_distance($a, $b) {
    return abs(($a - $b + 180) % 360 - 180);
}

/**
 * A fresh challenge. The answer is what the visitor must rotate the image by to
 * bring it upright, and it is stored server-side only.
 */
function alogweb_new_challenge() {
    $rotation = (random_int(0, 22) + 1) * 15;   // 15..345, never 0
    $token    = bin2hex(random_bytes(16));

    set_transient(ALOGWEB_CAPTCHA_PREFIX . $token, array(
        'rotation' => $rotation,
        'answer'   => (360 - $rotation) % 360,
        'issued'   => time(),
    ), ALOGWEB_CAPTCHA_TTL);

    return $token;
}

function alogweb_get_challenge($token) {
    if (!preg_match('/^[a-f0-9]{32}$/', (string) $token)) { return null; }
    $data = get_transient(ALOGWEB_CAPTCHA_PREFIX . $token);
    return is_array($data) ? $data : null;
}

function alogweb_consume_challenge($token) {
    if (preg_match('/^[a-f0-9]{32}$/', (string) $token)) {
        delete_transient(ALOGWEB_CAPTCHA_PREFIX . $token);
    }
}

/* --------------------------------------------------------------- captcha image */

/** The figure to rotate: a circular badge with an arrow, drawn with GD. */
function alogweb_captcha_image($rotation, $size = 200) {
    $scale = 3;                       // draw large, downsample for smooth edges
    $big   = $size * $scale;
    $im    = imagecreatetruecolor($big, $big);

    imagealphablending($im, true);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

    $disc  = imagecolorallocate($im, 227, 242, 239);
    $ink   = imagecolorallocate($im, 0, 128, 111);
    $mark  = imagecolorallocate($im, 168, 114, 15);
    $c     = $big / 2;

    imagefilledellipse($im, $c, $c, $big * 0.94, $big * 0.94, $disc);

    // Arrow: head, then stem. Its direction is the whole point of the puzzle.
    $head = array(
        $c, $big * 0.16,
        $big * 0.74, $big * 0.50,
        $big * 0.60, $big * 0.50,
        $big * 0.60, $big * 0.84,
        $big * 0.40, $big * 0.84,
        $big * 0.40, $big * 0.50,
        $big * 0.26, $big * 0.50,
    );
    imagefilledpolygon($im, array_map('intval', $head), $ink);

    // An off-centre mark so a symmetric near-miss still reads as wrong.
    imagefilledellipse($im, $c, (int) ($big * 0.72), (int) ($big * 0.09), (int) ($big * 0.09), $mark);

    $rotated = imagerotate($im, 360 - $rotation, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($rotated, false);
    imagesavealpha($rotated, true);
    imagedestroy($im);

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    $rw = imagesx($rotated);
    imagecopyresampled($out, $rotated, 0, 0, 0, 0, $size, $size, $rw, $rw);
    imagedestroy($rotated);

    return $out;
}

/** Serves the rotated image. Never cached: one token, one image, one use. */
add_action('init', function () {
    if (!isset($_GET['alogweb_captcha'])) { return; }

    $token     = sanitize_text_field(wp_unslash($_GET['alogweb_captcha']));
    $challenge = alogweb_get_challenge($token);

    nocache_headers();
    header('Content-Type: image/png');
    header('X-Robots-Tag: noindex, nofollow');

    if (!$challenge) { status_header(404); exit; }

    $image = alogweb_captcha_image((int) $challenge['rotation']);
    imagepng($image);
    imagedestroy($image);
    exit;
});

/* ------------------------------------------------------------------ validation */

function alogweb_contact_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    return hash('sha256', $ip . wp_salt());
}

function alogweb_contact_rate_limited() {
    $config = alogweb_contact_config();
    if ($config['rate_limit'] <= 0) { return false; }
    return (bool) get_transient('alogweb_contact_rl_' . alogweb_contact_client_ip());
}

function alogweb_contact_mark_submitted() {
    $config = alogweb_contact_config();
    if ($config['rate_limit'] > 0) {
        set_transient('alogweb_contact_rl_' . alogweb_contact_client_ip(), 1, $config['rate_limit']);
    }
}

/**
 * Returns array('errors' => [...], 'clean' => [...]).
 * Field rules match ExchangeHub's so a message accepted by one is accepted by
 * the other.
 */
function alogweb_validate_contact($post) {
    $config = alogweb_contact_config();
    $errors = array();

    $get = static function ($key) use ($post) {
        return isset($post[$key]) ? trim(wp_unslash($post[$key])) : '';
    };

    // Every field is prefixed on purpose. WordPress fills its public query
    // vars from $_POST as well as $_GET, so a field plainly called "name" makes
    // the request look like a lookup for a post with that slug and the page
    // 404s before the template ever runs. "s", "order", "author" and "title"
    // are the same trap.
    $name    = $get('cf_name');
    $email   = $get('cf_email');
    $subject = $get('cf_subject');
    $message = $get('cf_message');
    $token   = $get('cf_token');

    if ($get('cf_website') !== '') { $errors[] = __('Spam check failed.', 'alogweb'); }

    $challenge = alogweb_get_challenge($token);
    if (!$challenge) {
        $errors[] = __('The form expired. Please try again.', 'alogweb');
    } else {
        if (time() - (int) $challenge['issued'] < $config['min_time']) {
            $errors[] = __('Please take a moment before submitting the form.', 'alogweb');
        }
        $response = $get('cf_rotation');
        if ($response === '' || !is_numeric($response)) {
            $errors[] = __('The rotation check is incorrect.', 'alogweb');
        } elseif (alogweb_angular_distance((int) $response, (int) $challenge['answer']) > $config['tolerance']) {
            $errors[] = __('The rotation check is incorrect.', 'alogweb');
        }
    }

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        $errors[] = __('Name must be between 2 and 80 characters.', 'alogweb');
    }
    if (!is_email($email) || mb_strlen($email) > 120) {
        $errors[] = __('Enter a valid email address.', 'alogweb');
    }
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 140) {
        $errors[] = __('Subject must be between 3 and 140 characters.', 'alogweb');
    }
    if (mb_strlen($message) < 20 || mb_strlen($message) > 4000) {
        $errors[] = __('Message must be between 20 and 4000 characters.', 'alogweb');
    }

    return array(
        'errors' => $errors,
        'clean'  => compact('name', 'email', 'subject', 'message'),
    );
}

/* -------------------------------------------------------------------- delivery */

/**
 * SMTP for wp_mail(). Without CONTACT_SMTP_HOST this does nothing and WordPress
 * falls back to the local mailer, so an unconfigured site fails loudly at send
 * time rather than silently pretending to have delivered.
 */
/**
 * WordPress defaults the sender to wordpress@<site host>, which PHPMailer
 * rejects outright when the host has no TLD, and which no real mail server will
 * accept from us anyway. Point every message at the configured contact address.
 */
add_filter('wp_mail_from', function ($from) {
    $config = alogweb_contact_config();
    return $config['from'] !== '' ? $config['from'] : $from;
});
add_filter('wp_mail_from_name', function ($name) {
    return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
});

add_action('phpmailer_init', function ($mailer) {
    $host = alogweb_contact_env('CONTACT_SMTP_HOST');
    if ($host === '') { return; }

    if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        && !filter_var($host, FILTER_VALIDATE_IP)) {
        // PHPMailer rejects such a host and reports only "Could not connect to
        // SMTP host", which sends you looking at the network instead.
        error_log(sprintf('alogweb: CONTACT_SMTP_HOST "%s" is not a valid hostname or IP.', $host));
        return;
    }

    $mailer->isSMTP();
    $mailer->Host    = $host;
    $mailer->Port    = (int) alogweb_contact_env('CONTACT_SMTP_PORT', '587');
    $mailer->CharSet = 'UTF-8';

    $user = alogweb_contact_env('CONTACT_SMTP_USER');
    $pass = alogweb_contact_env('CONTACT_SMTP_PASSWORD');
    if ($user !== '') {
        $mailer->SMTPAuth = true;
        $mailer->Username = $user;
        $mailer->Password = $pass;
    } else {
        $mailer->SMTPAuth = false;
    }

    $use_tls = filter_var(alogweb_contact_env('CONTACT_SMTP_USE_TLS', 'true'), FILTER_VALIDATE_BOOLEAN);
    if (!$use_tls) {
        $mailer->SMTPSecure  = '';
        $mailer->SMTPAutoTLS = false;
    } else {
        $mailer->SMTPSecure = ($mailer->Port === 465) ? 'ssl' : 'tls';
    }

    $verify = filter_var(alogweb_contact_env('CONTACT_SMTP_TLS_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN);
    if (!$verify) {
        $mailer->SMTPOptions = array('ssl' => array(
            'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
        ));
    }
});

function alogweb_send_contact($clean) {
    $config = alogweb_contact_config();
    $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    $body = sprintf(
        "New message from the %s contact form.\n\n" .
        "Name:    %s\nEmail:   %s\nSubject: %s\n\n%s\n\n--\nSent %s from %s\n",
        $site, $clean['name'], $clean['email'], $clean['subject'], $clean['message'],
        current_time('mysql'), home_url('/')
    );

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $site, $config['from']),
        // Reply-To, never From: sending as the visitor's own domain is what
        // gets a server marked as a forgery by SPF and DMARC.
        sprintf('Reply-To: %s <%s>', $clean['name'], $clean['email']),
    );

    return wp_mail($config['to'], '[' . $site . '] ' . $clean['subject'], $body, $headers);
}
