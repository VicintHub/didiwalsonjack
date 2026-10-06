<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact messages: public endpoint (with Cloudflare Turnstile), storage, email
 * to the office, approval and forwarding to the Principal, SMTP, CSV export.
 */
class Didi_Messages {

	const TYPE = 'didi_message';
	const OPT  = 'didi_msg_settings';

	public static function reasons() {
		return array(
			'advisory'    => 'Strategic Advisory',
			'diagnostics' => 'Institutional Diagnostics',
			'programmes'  => 'Executive Leadership Programmes',
			'retreats'    => 'Leadership Retreats',
			'mentoring'   => 'Executive Mentoring',
			'board'       => 'Advisory Board Appointments',
			'keynote'     => 'Keynote Speaking',
			'publishing'  => 'Thought Leadership & Publishing',
			'media'       => 'Media, Press & Interview Requests',
			'publication' => 'Publication & Book Enquiries',
			'other'       => 'Other',
		);
	}

	public static function statuses() {
		return array(
			'new'      => 'Awaiting approval',
			'approved' => 'Forwarded to Principal',
			'archived' => 'Archived',
			'spam'     => 'Spam',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ) );
	}

	public static function register() {
		register_post_type( self::TYPE, array(
			'labels'              => array( 'name' => 'Messages', 'singular_name' => 'Message' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title', 'editor' ),
			'capability_type'     => 'post',
		) );
	}

	/* ------------------------------------------------------------ */
	/* Settings                                                      */
	/* ------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'turnstile_site'   => '0x4AAAAAAFO_ipLVQNB0o1y2',
			'turnstile_secret' => '',
			'office_email'     => 'office@didiwalsonjack.com',
			'principal_email'  => 'didi@didiwalsonjack.com',
			'smtp_enabled'     => 0,
			'smtp_host'        => 'smtp.hostinger.com',
			'smtp_port'        => 465,
			'smtp_secure'      => 'ssl',
			'smtp_user'        => 'office@didiwalsonjack.com',
			'smtp_pass'        => '',
			'from_email'       => 'office@didiwalsonjack.com',
			'from_name'        => 'Office of Mrs. Didi Walson-Jack',
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
	}

	public static function enc( $plain ) {
		if ( '' === $plain ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'b64:' . base64_encode( $plain );
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ), true ), 0, 16 );
		return 'enc:' . base64_encode( openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv ) );
	}

	public static function dec( $stored ) {
		if ( 0 === strpos( $stored, 'b64:' ) ) {
			return base64_decode( substr( $stored, 4 ) );
		}
		if ( 0 !== strpos( $stored, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = substr( hash( 'sha256', wp_salt( 'secure_auth' ), true ), 0, 16 );
		$out = openssl_decrypt( base64_decode( substr( $stored, 4 ) ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return false === $out ? '' : $out;
	}

	public static function smtp_ready() {
		$s = self::settings();
		return $s['smtp_enabled'] && $s['smtp_host'] && $s['smtp_user'] && $s['smtp_pass'];
	}

	public static function smtp( $phpmailer ) {
		$s = self::settings();
		if ( ! self::smtp_ready() ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $s['smtp_host'];
		$phpmailer->Port       = (int) $s['smtp_port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $s['smtp_user'];
		$phpmailer->Password   = self::dec( $s['smtp_pass'] );
		$phpmailer->SMTPSecure = in_array( $s['smtp_secure'], array( 'ssl', 'tls' ), true ) ? $s['smtp_secure'] : '';
		$phpmailer->SMTPAutoTLS = ( 'tls' === $s['smtp_secure'] );
		$phpmailer->Timeout    = 15;
		if ( is_email( $s['from_email'] ) ) {
			try {
				$phpmailer->setFrom( $s['from_email'], $s['from_name'], false );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			}
		}
	}

	/* ------------------------------------------------------------ */
	/* Public endpoint                                               */
	/* ------------------------------------------------------------ */

	public static function rest() {
		register_rest_route( 'didi/v1', '/contact', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'rest_contact' ),
		) );
	}

	private static function ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	private static function fail( $msg, $code = 400 ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => $msg ), $code );
	}

	public static function rest_contact( WP_REST_Request $req ) {
		$p  = $req->get_json_params();
		$p  = is_array( $p ) && $p ? $p : $req->get_params();
		$ip = self::ip();
		$s  = self::settings();

		// 1. Honeypot and speed trap: store as spam so it shows in the "spam blocked" count.
		$bot = ! empty( $p['website'] ) || ( isset( $p['elapsed'] ) && (int) $p['elapsed'] < 2500 );

		// 2. Rate limit per address.
		$key   = 'didi_msg_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 6 ) {
			return self::fail( 'Too many messages from this connection. Please try again later.', 429 );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		// 3. Fields.
		$name    = sanitize_text_field( $p['name'] ?? '' );
		$org     = sanitize_text_field( $p['organization'] ?? '' );
		$email   = sanitize_email( $p['email'] ?? '' );
		$phone   = sanitize_text_field( $p['phone'] ?? '' );
		$reason  = sanitize_key( $p['reason'] ?? 'other' );
		$subject = sanitize_text_field( $p['subject'] ?? '' );
		$message = sanitize_textarea_field( $p['message'] ?? '' );
		if ( ! isset( self::reasons()[ $reason ] ) ) {
			$reason = 'other';
		}
		if ( '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 10 || '' === $subject ) {
			return self::fail( 'Please complete your name, a valid email address, a subject and your message.' );
		}
		if ( preg_match_all( '#https?://#i', $message ) > 3 ) {
			$bot = true;
		}

		// 4. Cloudflare Turnstile.
		if ( '' === trim( (string) $s['turnstile_secret'] ) ) {
			return self::fail( 'The form is not available right now. Please email ' . $s['office_email'] . ' instead.', 503 );
		}
		$token = sanitize_text_field( $p['token'] ?? '' );
		if ( '' === $token ) {
			return self::fail( 'Please complete the security check and try again.' );
		}
		$v = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 12,
			'body'    => array( 'secret' => self::dec( $s['turnstile_secret'] ), 'response' => $token, 'remoteip' => $ip ),
		) );
		if ( is_wp_error( $v ) ) {
			return self::fail( 'The security check could not be reached. Please try again in a moment.', 503 );
		}
		$vj = json_decode( wp_remote_retrieve_body( $v ), true );
		if ( empty( $vj['success'] ) ) {
			return self::fail( 'The security check failed. Please reload the page and try again.' );
		}

		// 5. Store.
		$id = wp_insert_post( array(
			'post_type'    => self::TYPE,
			'post_status'  => 'publish',
			'post_title'   => $subject,
			'post_content' => $message,
		), true );
		if ( is_wp_error( $id ) ) {
			return self::fail( 'Your message could not be saved. Please try again.', 500 );
		}
		update_post_meta( $id, '_dm_name', $name );
		update_post_meta( $id, '_dm_org', $org );
		update_post_meta( $id, '_dm_email', $email );
		update_post_meta( $id, '_dm_phone', $phone );
		update_post_meta( $id, '_dm_reason', $reason );
		update_post_meta( $id, '_dm_ip', $ip );
		update_post_meta( $id, '_dm_ua', mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 250 ) );
		update_post_meta( $id, '_dm_status', $bot ? 'spam' : 'new' );

		// 6. Notify the office (not for spam).
		if ( ! $bot ) {
			$sent = self::send( $s['office_email'], $id, 'office' );
			update_post_meta( $id, '_dm_office_sent', $sent ? 1 : 0 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'message' => 'Thank you. Your message has been received and the office will respond shortly.' ), 200 );
	}

	/* ------------------------------------------------------------ */
	/* Email                                                         */
	/* ------------------------------------------------------------ */

	public static function data( $id ) {
		$p = get_post( $id );
		$g = function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, $k, true );
		};
		$reason = $g( '_dm_reason' );
		return array(
			'id'        => (int) $id,
			'name'      => $g( '_dm_name' ),
			'org'       => $g( '_dm_org' ),
			'email'     => $g( '_dm_email' ),
			'phone'     => $g( '_dm_phone' ),
			'reason'    => $reason,
			'reason_l'  => self::reasons()[ $reason ] ?? 'Other',
			'subject'   => $p ? $p->post_title : '',
			'message'   => $p ? $p->post_content : '',
			'status'    => $g( '_dm_status' ) ?: 'new',
			'ip'        => $g( '_dm_ip' ),
			'ua'        => $g( '_dm_ua' ),
			'note'      => $g( '_dm_note' ),
			'office_sent'    => (bool) $g( '_dm_office_sent' ),
			'principal_sent' => (bool) $g( '_dm_principal_sent' ),
			'approved_by'    => $g( '_dm_approved_by' ),
			'approved_at'    => $g( '_dm_approved_at' ),
			'date'      => $p ? get_post_time( 'Y-m-d H:i:s', false, $p, true ) : '',
			'date_h'    => $p ? get_date_from_gmt( get_post_time( 'Y-m-d H:i:s', true, $p ), 'j M Y, g:i a' ) : '',
		);
	}

	public static function send( $to, $id, $kind ) {
		$m  = self::data( $id );
		$s  = self::settings();
		$h  = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $m['email'] ) ) {
			$h[] = 'Reply-To: ' . $m['name'] . ' <' . $m['email'] . '>';
		}
		if ( is_email( $s['from_email'] ) ) {
			$h[] = 'From: ' . $s['from_name'] . ' <' . $s['from_email'] . '>';
		}
		$row = function ( $l, $v ) {
			return '<tr><td style="padding:8px 14px;color:#5E5566;font-size:13px;width:150px;vertical-align:top">' . esc_html( $l ) . '</td><td style="padding:8px 14px;color:#231F26;font-size:14px">' . $v . '</td></tr>';
		};
		$intro = 'office' === $kind
			? 'A new message arrived through the website. It is waiting in the dashboard for approval before it is forwarded to the Principal.'
			: 'This message has been reviewed and approved by the office' . ( $m['approved_by'] ? ' (' . esc_html( $m['approved_by'] ) . ')' : '' ) . ' for your attention.';
		$body  = '<div style="font-family:Arial,Helvetica,sans-serif;background:#FBF8F4;padding:24px"><div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #E9DFF5;border-radius:14px;overflow:hidden">';
		$body .= '<div style="background:#3B1F4A;color:#fff;padding:20px 24px"><div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#C9B6E4">' . ( 'office' === $kind ? 'New website message' : 'Message for the Principal' ) . '</div><div style="font-size:20px;margin-top:6px;font-family:Georgia,serif">' . esc_html( $m['subject'] ) . '</div></div>';
		$body .= '<p style="margin:18px 24px 6px;color:#5E5566;font-size:13px;line-height:1.6">' . $intro . '</p>';
		$body .= '<table style="width:100%;border-collapse:collapse;margin:6px 0 10px">';
		$body .= $row( 'Name', esc_html( $m['name'] ) );
		$body .= $row( 'Organisation', esc_html( $m['org'] ?: 'Not given' ) );
		$body .= $row( 'Email', '<a href="mailto:' . esc_attr( $m['email'] ) . '" style="color:#7A4BA8">' . esc_html( $m['email'] ) . '</a>' );
		$body .= $row( 'Phone / WhatsApp', esc_html( $m['phone'] ?: 'Not given' ) );
		$body .= $row( 'Reason', esc_html( $m['reason_l'] ) );
		$body .= $row( 'Subject', esc_html( $m['subject'] ) );
		$body .= $row( 'Received', esc_html( $m['date_h'] ) );
		$body .= '</table><div style="margin:0 24px 20px;padding:16px 18px;background:#F8E4EC;border-radius:10px;color:#231F26;font-size:14px;line-height:1.7;white-space:pre-wrap">' . esc_html( $m['message'] ) . '</div>';
		if ( 'office' === $kind ) {
			$body .= '<div style="padding:0 24px 24px"><a href="' . esc_url( admin_url( 'admin.php?page=didi-messages&message=' . $id ) ) . '" style="display:inline-block;background:#7A4BA8;color:#fff;text-decoration:none;padding:11px 22px;border-radius:30px;font-size:14px;font-weight:bold">Open in dashboard</a></div>';
		}
		$body .= '</div><p style="text-align:center;color:#8a7f94;font-size:12px;margin-top:14px">didiwalsonjack.com</p></div>';
		$subj = ( 'office' === $kind ? '[Website message] ' : '[Approved] ' ) . $m['subject'] . ' (' . $m['reason_l'] . ')';
		return (bool) wp_mail( $to, $subj, $body, $h );
	}

	/** Approve and forward to the Principal. */
	public static function approve( $id ) {
		$s    = self::settings();
		$user = wp_get_current_user();
		update_post_meta( $id, '_dm_approved_by', $user && $user->ID ? $user->display_name : '' );
		update_post_meta( $id, '_dm_approved_at', current_time( 'mysql' ) );
		$ok = self::send( $s['principal_email'], $id, 'principal' );
		update_post_meta( $id, '_dm_principal_sent', $ok ? 1 : 0 );
		if ( $ok ) {
			update_post_meta( $id, '_dm_status', 'approved' );
		}
		return $ok;
	}

	/* ------------------------------------------------------------ */
	/* Queries                                                       */
	/* ------------------------------------------------------------ */

	public static function counts() {
		global $wpdb;
		$out = array( 'status' => array(), 'reason' => array(), 'total' => 0 );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.meta_value AS st, r.meta_value AS rs, COUNT(*) AS n FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_dm_status'
			 LEFT JOIN {$wpdb->postmeta} r ON r.post_id=p.ID AND r.meta_key='_dm_reason'
			 WHERE p.post_type=%s AND p.post_status='publish' GROUP BY st, rs", self::TYPE ) );
		foreach ( (array) $rows as $r ) {
			$st = $r->st ?: 'new';
			$rs = $r->rs ?: 'other';
			$out['status'][ $st ] = ( $out['status'][ $st ] ?? 0 ) + (int) $r->n;
			if ( 'spam' !== $st ) {
				$out['reason'][ $rs ] = ( $out['reason'][ $rs ] ?? 0 ) + (int) $r->n;
			}
			$out['total'] += (int) $r->n;
		}
		return $out;
	}

	public static function query( $f, $paged = 1, $per = 20 ) {
		$meta = array( 'relation' => 'AND' );
		if ( ! empty( $f['status'] ) ) {
			$meta[] = 'new' === $f['status']
				? array( 'relation' => 'OR', array( 'key' => '_dm_status', 'value' => 'new' ), array( 'key' => '_dm_status', 'compare' => 'NOT EXISTS' ) )
				: array( 'key' => '_dm_status', 'value' => $f['status'] );
		} else {
			$meta[] = array( 'relation' => 'OR', array( 'key' => '_dm_status', 'value' => 'spam', 'compare' => '!=' ), array( 'key' => '_dm_status', 'compare' => 'NOT EXISTS' ) );
		}
		if ( ! empty( $f['reason'] ) ) {
			$meta[] = array( 'key' => '_dm_reason', 'value' => $f['reason'] );
		}
		$args = array(
			'post_type'      => self::TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per,
			'paged'          => max( 1, (int) $paged ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta,
		);
		if ( ! empty( $f['s'] ) ) {
			global $wpdb;
			$like = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$ids  = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key IN ('_dm_name','_dm_email','_dm_org','_dm_phone')
				 WHERE p.post_type=%s AND (p.post_title LIKE %s OR p.post_content LIKE %s OR m.meta_value LIKE %s)",
				self::TYPE, $like, $like, $like ) );
			$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
		}
		$date = array();
		if ( ! empty( $f['from'] ) ) {
			$date['after'] = $f['from'] . ' 00:00:00';
		}
		if ( ! empty( $f['to'] ) ) {
			$date['before'] = $f['to'] . ' 23:59:59';
		}
		if ( $date ) {
			$args['date_query'] = array( array_merge( $date, array( 'inclusive' => true ) ) );
		}
		return new WP_Query( $args );
	}
}
