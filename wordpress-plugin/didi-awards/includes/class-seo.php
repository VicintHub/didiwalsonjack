<?php
/**
 * SEO / GEO / AEO and indexing helper.
 *
 * Serves /sitemap.xml, /llms.txt and the IndexNow key file, adds the sitemap and
 * AI-crawler rules to robots.txt, and pings IndexNow (Bing, Yandex and partners)
 * whenever an award is published or updated. Google does not accept pings: submit
 * the sitemap once in Search Console (see SEO & Indexing page).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Didi_Awards_SEO {

	const OPT_KEY = 'didi_indexnow_key';
	const LOG     = 'didi_indexnow_log';

	/** Static pages of the site (the five embedded pages). */
	public static function pages() {
		return array(
			'/'        => 'weekly',
			'/about'   => 'monthly',
			'/career'  => 'monthly',
			'/books'   => 'monthly',
			'/contact' => 'yearly',
			'/awards/' => 'weekly',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 99, 2 );
		add_action( 'save_post_didi_award', array( __CLASS__, 'on_award_saved' ), 20, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_didi_seo_ping', array( __CLASS__, 'manual_ping' ) );
	}

	public static function key() {
		$k = get_option( self::OPT_KEY );
		if ( ! $k ) {
			$k = wp_generate_password( 32, false, false );
			update_option( self::OPT_KEY, $k, false );
		}
		return $k;
	}

	private static function site() {
		return untrailingslashit( home_url() );
	}

	/** Serve sitemap.xml, llms.txt and the IndexNow key file before WordPress routing. */
	public static function maybe_serve() {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$path = '/' . trim( (string) $path, '/' );
		$home = wp_parse_url( home_url(), PHP_URL_PATH );
		if ( $home && '/' !== $home ) {
			$path = '/' . ltrim( substr( $path, strlen( untrailingslashit( $home ) ) ), '/' );
		}
		if ( '/sitemap.xml' === $path ) {
			nocache_headers();
			header( 'Content-Type: application/xml; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			echo self::sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		if ( '/llms.txt' === $path ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo self::llms(); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		if ( '/' . self::key() . '.txt' === $path ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo self::key(); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
	}

	public static function sitemap() {
		$site = self::site();
		$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( self::pages() as $p => $freq ) {
			$out .= sprintf( "  <url><loc>%s</loc><changefreq>%s</changefreq><priority>%s</priority></url>\n", esc_url( $site . $p ), $freq, '/' === $p ? '1.0' : '0.8' );
		}
		$q = new WP_Query( array( 'post_type' => 'didi_award', 'post_status' => 'publish', 'posts_per_page' => -1, 'no_found_rows' => true ) );
		foreach ( $q->posts as $post ) {
			$out .= sprintf( "  <url><loc>%s</loc><lastmod>%s</lastmod><changefreq>yearly</changefreq><priority>0.6</priority></url>\n", esc_url( get_permalink( $post ) ), esc_html( get_post_modified_time( 'c', true, $post ) ) );
		}
		return $out . "</urlset>\n";
	}

	public static function llms() {
		$s = self::site();
		return "# Mrs. Didi Esther Walson-Jack, OON, mni\n\n"
			. "> Official website of Mrs. Didi Esther Walson-Jack, OON, mni, the 20th Head of the Civil Service of the Federation of Nigeria (14 August 2024 to 27 August 2026). She has 34 years of public service, has handed over and is retired from active public service. She is an author, speaker and global advisor on institutions, leadership and reform.\n\n"
			. "## Key facts\n"
			. "- Full name: Mrs. Didi Esther Walson-Jack, OON, mni (also written Diiarau Didi Esther Walson-Jack)\n"
			. "- OON: Officer of the Order of the Niger (2023). mni: member of the National Institute, NIPSS Kuru (always written in lowercase)\n"
			. "- Career: Legal draftswoman, then Solicitor-General and Permanent Secretary, Ministry of Justice, Bayelsa State; Federal Permanent Secretary from 2017 across five ministries and offices; Head of the Civil Service of the Federation 2024-2026\n"
			. "- Education: LL.B University of Lagos (1986); Nigerian Law School (1987); Executive MPA, London School of Economics (2025); Harvard Kennedy School, Certificate in Leadership for the 21st Century (September 2026)\n"
			. "- Books: Roses in the Thorns (2017); I Planted: A memoir of purposeful service (2026, Safari Books); Beyond the Mandate: A Memoir on Leadership, Legacy & Labour of Public Service\n"
			. "- Contact: didi@didiwalsonjack.com, +234 909 511 9999, Beautiful Gate Villa, Plot 812 Paul Unongo Crescent, Jabi, Abuja, Nigeria\n\n"
			. "## Pages\n"
			. "- [Home]({$s}/): overview, services, awards\n"
			. "- [About]({$s}/about): biography, education, CV and profile downloads\n"
			. "- [Career & Achievements]({$s}/career): roles, reforms, Legacy Report and Scorecard downloads\n"
			. "- [Books]({$s}/books): publications\n"
			. "- [Awards]({$s}/awards/): honours and commendations, one page per award\n"
			. "- [Contact]({$s}/contact): enquiries, advisory, speaking\n\n"
			. "## Documents (PDF)\n"
			. "- [Curriculum vitae]({$s}/wp-content/uploads/2026/10/CV-OF-DIIARAU-DIDI-ESTHER-WALSON-JACK.pdf)\n"
			. "- [Profile]({$s}/wp-content/uploads/2026/10/PROFILE-OF-MRS-DIDI-WALSON-JACK.pdf)\n"
			. "- [HCSF Legacy Report]({$s}/wp-content/uploads/2026/10/Mrs-DIdi-Walson-Jack-HCSF-Legacy-Report.pdf)\n"
			. "- [HCSF Scorecard]({$s}/wp-content/uploads/2026/10/Mrs-DIdi-Walson-Jack-HCSF-ScoreCard.pdf)\n\n"
			. "## Related\n- [Walson-Jack Consulting](https://www.walsonjackconsulting.org)\n";
	}

	public static function robots( $output, $public ) {
		$s    = self::site();
		$add  = "\n# Added by Didi Awards plugin\n";
		foreach ( array( 'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-Web', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot' ) as $bot ) {
			$add .= "User-agent: {$bot}\nAllow: /\n\n";
		}
		$add .= "Sitemap: {$s}/sitemap.xml\n";
		return $output . $add;
	}

	/** URLs to notify when a single award changes. */
	public static function on_award_saved( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		self::ping( array( get_permalink( $post_id ), self::site() . '/awards/', self::site() . '/' ) );
	}

	/** IndexNow ping (Bing, Yandex, Seznam, Naver). Returns the HTTP status or an error string. */
	public static function ping( array $urls ) {
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
		if ( ! $urls ) {
			return 'No URLs';
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$body = array( 'host' => $host, 'key' => self::key(), 'keyLocation' => self::site() . '/' . self::key() . '.txt', 'urlList' => $urls );
		$res  = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
			'timeout' => 8,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( $body ),
		) );
		$msg = is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_response_code( $res );
		update_option( self::LOG, array( 'time' => current_time( 'mysql' ), 'status' => $msg, 'count' => count( $urls ) ), false );
		return $msg;
	}

	public static function menu() {
		add_submenu_page( 'didi-awards', 'SEO & Indexing', 'SEO & Indexing', 'manage_options', 'didi-awards-seo', array( __CLASS__, 'page' ) );
	}

	public static function manual_ping() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'didi_seo_ping' );
		$urls = array();
		foreach ( array_keys( self::pages() ) as $p ) {
			$urls[] = self::site() . $p;
		}
		$q = new WP_Query( array( 'post_type' => 'didi_award', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $q->posts as $id ) {
			$urls[] = get_permalink( $id );
		}
		self::ping( $urls );
		wp_safe_redirect( admin_url( 'admin.php?page=didi-awards-seo&pinged=1' ) );
		exit;
	}

	public static function page() {
		$s   = self::site();
		$log = get_option( self::LOG );
		echo '<div class="wrap"><h1>SEO &amp; Indexing</h1>';
		if ( isset( $_GET['pinged'] ) ) {
			echo '<div class="notice notice-success"><p>IndexNow ping sent. See the last result below.</p></div>';
		}
		echo '<p>The plugin serves these files and notifies Bing and other IndexNow search engines automatically when an award is published or changed.</p><ul style="list-style:disc;margin-left:1.5em">';
		foreach ( array( '/sitemap.xml', '/llms.txt', '/' . self::key() . '.txt' ) as $f ) {
			echo '<li><a href="' . esc_url( $s . $f ) . '" target="_blank" rel="noopener">' . esc_html( $s . $f ) . '</a></li>';
		}
		echo '</ul>';
		echo '<p><strong>Last IndexNow ping:</strong> ' . ( $log ? esc_html( $log['time'] . ' | HTTP ' . $log['status'] . ' (200 or 202 means accepted) | ' . $log['count'] . ' URLs' ) : 'none yet' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="didi_seo_ping" />';
		wp_nonce_field( 'didi_seo_ping' );
		echo '<p><button class="button button-primary">Notify search engines now (all pages and awards)</button></p></form>';
		echo '<h2>One-time steps (Google and Bing)</h2><ol>'
			. '<li><strong>Google Search Console:</strong> add the property <code>' . esc_html( $s ) . '</code>, verify it, open <em>Sitemaps</em>, enter <code>sitemap.xml</code> and press Submit. Then in <em>URL inspection</em> paste each of the five page addresses and press <em>Request indexing</em>.</li>'
			. '<li><strong>Bing Webmaster Tools:</strong> add the site (or import it from Search Console) and submit <code>' . esc_html( $s ) . '/sitemap.xml</code>. IndexNow keeps Bing updated after that.</li>'
			. '<li>If an SEO plugin (Yoast, Rank Math) also serves <code>/sitemap.xml</code>, this plugin answers first. Keep only one sitemap submitted.</li></ol></div>';
	}
}
