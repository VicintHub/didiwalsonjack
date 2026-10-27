<?php
/**
 * Under-construction mode.
 *
 * When the page chosen as the site's front page (Settings > Reading) is the
 * under-construction page, every other public page is sent to the home page.
 * Visitors get a 302 redirect; the front page itself is served with a 503 and
 * Retry-After so search engines treat the closure as temporary.
 * Logged-in editors are never rerouted, so the site can still be worked on.
 * Admin, login, REST (/wp-json), AJAX, cron and uploaded files are untouched.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Didi_Awards_Construction {

	const OPT = 'didi_uc_settings';

	public static function defaults() {
		return array(
			'mode' => 'auto',               // auto | on | off
			'slug' => 'under-construction', // slug of the under-construction page
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
	}

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_reroute' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_didi_uc_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 );
	}

	/** Is the under-construction page currently the site's front page? */
	public static function front_page_is_uc() {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}
		$id = (int) get_option( 'page_on_front' );
		if ( ! $id ) {
			return false;
		}
		$s = self::settings();
		return get_post_field( 'post_name', $id ) === sanitize_title( $s['slug'] );
	}

	/** Should visitors be rerouted right now? */
	public static function active() {
		$s = self::settings();
		if ( 'off' === $s['mode'] ) {
			return false;
		}
		if ( 'on' === $s['mode'] ) {
			return true;
		}
		return self::front_page_is_uc();
	}

	private static function bypass() {
		if ( is_admin() || is_user_logged_in() && current_user_can( 'edit_posts' ) && empty( $_GET['uc_preview'] ) ) {
			return true;
		}
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		return (bool) preg_match( '#/(wp-login\.php|wp-admin|wp-json|xmlrpc\.php|wp-cron\.php|robots\.txt|sitemap\.xml|llms\.txt)#', $path );
	}

	public static function maybe_reroute() {
		if ( ! self::active() || self::bypass() ) {
			return;
		}
		if ( is_front_page() || is_home() && ! is_paged() && 'page' !== get_option( 'show_on_front' ) ) {
			status_header( 503 );
			header( 'Retry-After: 3600' );
			nocache_headers();
			return;
		}
		nocache_headers();
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	public static function admin_bar( $bar ) {
		if ( ! self::active() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node( array(
			'id'    => 'didi-uc',
			'title' => 'Under construction: visitors are rerouted',
			'href'  => admin_url( 'admin.php?page=didi-awards-uc' ),
		) );
	}

	public static function menu() {
		add_submenu_page( 'didi-awards', 'Under construction', 'Under construction', 'manage_options', 'didi-awards-uc', array( __CLASS__, 'page' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'didi_uc_save' );
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';
		$mode = in_array( $mode, array( 'auto', 'on', 'off' ), true ) ? $mode : 'auto';
		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : 'under-construction';
		update_option( self::OPT, array( 'mode' => $mode, 'slug' => $slug ?: 'under-construction' ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=didi-awards-uc&saved=1' ) );
		exit;
	}

	public static function page() {
		$s      = self::settings();
		$active = self::active();
		echo '<div class="wrap"><h1>Under construction</h1>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		}
		echo '<p><strong>Status:</strong> ' . ( $active ? '<span style="color:#b03a63;font-weight:700">ON. Visitors are rerouted to the home page.</span>' : '<span style="color:#2e7d32;font-weight:700">OFF. The whole site is visible.</span>' ) . '</p>';
		echo '<p>' . ( self::front_page_is_uc() ? 'The home page is currently the under-construction page.' : 'The home page is not the under-construction page.' ) . ' Logged-in editors are never rerouted. Add <code>?uc_preview=1</code> to a link to see the site as a visitor.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="didi_uc_save" />';
		wp_nonce_field( 'didi_uc_save' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row">Mode</th><td>';
		$opts = array(
			'auto' => 'Automatic: reroute only while the under-construction page is set as the home page (Settings > Reading)',
			'on'   => 'Always on: reroute every visitor to the home page',
			'off'  => 'Off: never reroute',
		);
		foreach ( $opts as $k => $label ) {
			echo '<label style="display:block;margin:.3rem 0"><input type="radio" name="mode" value="' . esc_attr( $k ) . '"' . checked( $s['mode'], $k, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</td></tr><tr><th scope="row"><label for="uc_slug">Page address (slug)</label></th><td><input id="uc_slug" name="slug" type="text" class="regular-text" value="' . esc_attr( $s['slug'] ) . '"><p class="description">The slug of the page that holds the under-construction design. Default: <code>under-construction</code>.</p></td></tr></table>';
		echo '<p><button class="button button-primary">Save</button></p></form>';
		echo '<h2>How to switch it on</h2><ol><li>Pages > Add New, title it <em>Under Construction</em> (address <code>under-construction</code>), add an HTML widget and paste <code>under-construction.html</code>. Use a blank or full-width template.</li><li>Settings > Reading > Your homepage displays > A static page > choose <em>Under Construction</em> > Save.</li><li>Every other page now goes to the home page. To go live, set the real home page in Settings > Reading again.</li></ol></div>';
	}
}
