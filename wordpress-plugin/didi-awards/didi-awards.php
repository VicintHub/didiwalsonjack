<?php
/**
 * Plugin Name:       Didi Walson-Jack Awards
 * Description:       Awards & Commendations for didiwalsonjack.com: a branded dashboard to add and edit awards, a public REST feed for the home-page slider, a dedicated /awards/ archive and a dynamic page for every award.
 * Version:           1.0.0
 * Author:            Vicint hub
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       didi-awards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIDI_AWARDS_VERSION', '1.0.0' );
define( 'DIDI_AWARDS_FILE', __FILE__ );
define( 'DIDI_AWARDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIDI_AWARDS_URL', plugin_dir_url( __FILE__ ) );
define( 'DIDI_AWARDS_MAX_FEATURED', 10 );

require_once DIDI_AWARDS_DIR . 'includes/class-cpt.php';
require_once DIDI_AWARDS_DIR . 'includes/class-seed.php';
require_once DIDI_AWARDS_DIR . 'includes/class-admin.php';
require_once DIDI_AWARDS_DIR . 'includes/class-assist.php';
require_once DIDI_AWARDS_DIR . 'includes/class-frontend.php';

Didi_Awards_CPT::init();
Didi_Awards_Admin::init();
Didi_Awards_Assist::init();
Didi_Awards_Frontend::init();

register_activation_hook( __FILE__, function () {
	Didi_Awards_CPT::register();
	Didi_Awards_Seed::run( false );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
