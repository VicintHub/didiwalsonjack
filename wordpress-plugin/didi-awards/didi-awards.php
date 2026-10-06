<?php
/**
 * Plugin Name:       Didi Walson-Jack Awards
 * Description:       Awards & Commendations and Contact Messages for didiwalsonjack.com: award dashboard, /awards/ pages and home-slider feed; contact form endpoint with Cloudflare Turnstile, message dashboard with approval, CSV export and SMTP email.
 * Version:           1.2.0
 * Author:            Vicint hub
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       didi-awards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIDI_AWARDS_VERSION', '1.2.0' );
define( 'DIDI_AWARDS_FILE', __FILE__ );
define( 'DIDI_AWARDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIDI_AWARDS_URL', plugin_dir_url( __FILE__ ) );
define( 'DIDI_AWARDS_MAX_FEATURED', 10 );

require_once DIDI_AWARDS_DIR . 'includes/class-cpt.php';
require_once DIDI_AWARDS_DIR . 'includes/class-seed.php';
require_once DIDI_AWARDS_DIR . 'includes/class-admin.php';
require_once DIDI_AWARDS_DIR . 'includes/class-assist.php';
require_once DIDI_AWARDS_DIR . 'includes/class-messages.php';
require_once DIDI_AWARDS_DIR . 'includes/class-messages-admin.php';
require_once DIDI_AWARDS_DIR . 'includes/class-seo.php';
require_once DIDI_AWARDS_DIR . 'includes/class-frontend.php';

Didi_Awards_CPT::init();
Didi_Awards_Admin::init();
Didi_Awards_Assist::init();
Didi_Messages::init();
Didi_Messages_Admin::init();
Didi_Awards_SEO::init();
Didi_Awards_Frontend::init();

// On plugin update: import any newly added awards (existing ones are left untouched) and refresh permalinks.
add_action( 'admin_init', function () {
	if ( get_option( 'didi_awards_db_version' ) !== DIDI_AWARDS_VERSION ) {
		Didi_Awards_Seed::run( false );
		flush_rewrite_rules();
		update_option( 'didi_awards_db_version', DIDI_AWARDS_VERSION, false );
	}
} );

register_activation_hook( __FILE__, function () {
	Didi_Awards_CPT::register();
	Didi_Messages::register();
	Didi_Awards_Seed::run( false );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
