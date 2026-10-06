<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Messages dashboard, settings, actions and CSV export.
 */
class Didi_Messages_Admin {

	const CAP = 'edit_others_posts';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_didi_msg_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_didi_msg_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_didi_msg_test', array( __CLASS__, 'test_mail' ) );
		add_action( 'wp_ajax_didi_msg_action', array( __CLASS__, 'ajax_action' ) );
	}

	public static function menu() {
		$new = Didi_Messages::counts()['status']['new'] ?? 0;
		$bub = $new ? ' <span class="awaiting-mod">' . (int) $new . '</span>' : '';
		add_menu_page( 'Messages', 'Messages' . $bub, self::CAP, 'didi-messages', array( __CLASS__, 'page' ), 'dashicons-email-alt', 27 );
		add_submenu_page( 'didi-messages', 'Messages', 'Dashboard', self::CAP, 'didi-messages', array( __CLASS__, 'page' ) );
		add_submenu_page( 'didi-messages', 'Messages settings', 'Settings', 'manage_options', 'didi-messages-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'didi-messages' ) ) {
			return;
		}
		wp_enqueue_style( 'didi-awards-fonts', 'https://fonts.googleapis.com/css2?family=Questrial&family=Source+Serif+4:wght@400;600;700&display=swap', array(), null );
		wp_enqueue_style( 'didi-awards-admin', DIDI_AWARDS_URL . 'assets/css/admin.css', array(), DIDI_AWARDS_VERSION );
		wp_enqueue_style( 'didi-messages-admin', DIDI_AWARDS_URL . 'assets/css/messages.css', array( 'didi-awards-admin' ), DIDI_AWARDS_VERSION );
		wp_enqueue_script( 'didi-messages-admin', DIDI_AWARDS_URL . 'assets/js/messages.js', array( 'jquery' ), DIDI_AWARDS_VERSION, true );
		$s = Didi_Messages::settings();
		wp_localize_script( 'didi-messages-admin', 'DidiMsg', array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'didi_msg' ),
			'principal' => $s['principal_email'],
			'office'    => $s['office_email'],
		) );
	}

	/* ------------------------------------------------------------ */

	private static function f() {
		$g = function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		};
		$status = $g( 'status' );
		$reason = $g( 'reason' );
		return array(
			'status' => isset( Didi_Messages::statuses()[ $status ] ) ? $status : '',
			'reason' => isset( Didi_Messages::reasons()[ $reason ] ) ? $reason : '',
			's'      => $g( 's' ),
			'from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'from' ) ) ? $g( 'from' ) : '',
			'to'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'to' ) ) ? $g( 'to' ) : '',
		);
	}

	private static function url( $over = array() ) {
		$cur = array_filter( array_merge( self::f(), $over ), function ( $v ) { return '' !== $v && null !== $v; } );
		return add_query_arg( array_merge( array( 'page' => 'didi-messages' ), $cur ), admin_url( 'admin.php' ) );
	}

	private static function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( $name ) );
		$i     = mb_substr( $parts[0] ?? '', 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '' );
		return mb_strtoupper( $i ?: '?' );
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'You are not allowed to see this page.' );
		}
		$f      = self::f();
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$q      = Didi_Messages::query( $f, $paged, 15 );
		$counts = Didi_Messages::counts();
		$S      = Didi_Messages::settings();
		$items  = array();
		foreach ( $q->posts as $p ) {
			$items[] = Didi_Messages::data( $p->ID );
		}
		$open = isset( $_GET['message'] ) ? absint( $_GET['message'] ) : 0;
		if ( $open && ! array_filter( $items, function ( $i ) use ( $open ) { return $i['id'] === $open; } ) && get_post_type( $open ) === Didi_Messages::TYPE ) {
			$items[] = Didi_Messages::data( $open );
		}
		$new   = $counts['status']['new'] ?? 0;
		$appr  = $counts['status']['approved'] ?? 0;
		$arch  = $counts['status']['archived'] ?? 0;
		$spam  = $counts['status']['spam'] ?? 0;
		$allc  = $counts['total'] - $spam;
		$warn  = array();
		if ( '' === trim( (string) $S['turnstile_secret'] ) ) {
			$warn[] = 'The security check is not set up, so the contact form cannot accept messages yet. Add the Turnstile secret key in Settings.';
		}
		if ( ! Didi_Messages::smtp_ready() ) {
			$warn[] = 'Email sending is not set up. Messages are saved here, but emails to the office may not be delivered. Add the mail server details in Settings.';
		}
		?>
		<div class="da-wrap dm-wrap">
			<?php
			if ( isset( $_GET['msg'] ) ) {
				$m = sanitize_key( wp_unslash( $_GET['msg'] ) );
				$t = array( 'saved' => 'Settings saved.', 'testok' => 'Test email sent. Check the inbox.', 'testfail' => 'The test email could not be sent. Check the mail server details.' );
				if ( isset( $t[ $m ] ) ) {
					printf( '<div class="da-notice da-notice-%s">%s</div>', 'testfail' === $m ? 'err' : 'ok', esc_html( $t[ $m ] ) );
				}
			}
			foreach ( $warn as $w ) {
				echo '<div class="da-notice da-notice-warn">' . esc_html( $w ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=didi-messages-settings' ) ) . '">Open settings</a></div>';
			}
			?>
			<header class="da-hero">
				<div class="da-hero-text">
					<span class="da-eyebrow">Mrs. Didi Esther Walson-Jack, OON, <span class="mni">mni</span></span>
					<h1>Contact messages</h1>
					<p>Every message from the website lands here and is emailed to <strong><?php echo esc_html( $S['office_email'] ); ?></strong>. After review, approve it to forward the full details to <strong><?php echo esc_html( $S['principal_email'] ); ?></strong>.</p>
				</div>
				<div class="da-hero-actions">
					<a class="da-btn da-btn-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'didi_msg_export' ), array_filter( $f ) ), admin_url( 'admin-post.php' ) ), 'didi_msg_export' ) ); ?>"><span class="dashicons dashicons-download"></span> Export CSV<?php echo array_filter( $f ) ? ' (filtered)' : ''; ?></a>
					<?php if ( current_user_can( 'manage_options' ) ) : ?><a class="da-btn da-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=didi-messages-settings' ) ); ?>"><span class="dashicons dashicons-admin-generic"></span> Settings</a><?php endif; ?>
				</div>
			</header>

			<section class="da-stats">
				<a class="da-stat dm-stat<?php echo 'new' === $f['status'] ? ' is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'status' => 'new', 'reason' => '' ) ) ); ?>"><span class="da-stat-num"><?php echo (int) $new; ?></span><span class="da-stat-lbl">Awaiting approval</span></a>
				<a class="da-stat dm-stat<?php echo 'approved' === $f['status'] ? ' is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'status' => 'approved', 'reason' => '' ) ) ); ?>"><span class="da-stat-num"><?php echo (int) $appr; ?></span><span class="da-stat-lbl">Forwarded to Principal</span></a>
				<a class="da-stat dm-stat<?php echo 'archived' === $f['status'] ? ' is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'status' => 'archived', 'reason' => '' ) ) ); ?>"><span class="da-stat-num"><?php echo (int) $arch; ?></span><span class="da-stat-lbl">Archived</span></a>
				<a class="da-stat dm-stat<?php echo 'spam' === $f['status'] ? ' is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'status' => 'spam', 'reason' => '' ) ) ); ?>"><span class="da-stat-num"><?php echo (int) $spam; ?></span><span class="da-stat-lbl">Spam blocked</span></a>
			</section>

			<div class="dm-layout">
				<aside class="dm-side" aria-label="Filters">
					<h2>Status</h2>
					<ul>
						<li><a class="<?php echo '' === $f['status'] ? 'is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'status' => '', 'paged' => '' ) ) ); ?>">All messages <b><?php echo (int) $allc; ?></b></a></li>
						<?php foreach ( Didi_Messages::statuses() as $k => $l ) : ?>
							<li><a class="<?php echo $f['status'] === $k ? 'is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'status' => $k, 'paged' => '' ) ) ); ?>"><?php echo esc_html( $l ); ?> <b><?php echo (int) ( $counts['status'][ $k ] ?? 0 ); ?></b></a></li>
						<?php endforeach; ?>
					</ul>
					<h2>Category</h2>
					<ul>
						<li><a class="<?php echo '' === $f['reason'] ? 'is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'reason' => '', 'paged' => '' ) ) ); ?>">All categories</a></li>
						<?php foreach ( Didi_Messages::reasons() as $k => $l ) : ?>
							<li><a class="<?php echo $f['reason'] === $k ? 'is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'reason' => $k, 'paged' => '' ) ) ); ?>"><?php echo esc_html( $l ); ?> <b><?php echo (int) ( $counts['reason'][ $k ] ?? 0 ); ?></b></a></li>
						<?php endforeach; ?>
					</ul>
				</aside>

				<main class="dm-main">
					<form class="da-toolbar dm-toolbar" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<input type="hidden" name="page" value="didi-messages">
						<?php foreach ( array( 'status', 'reason' ) as $k ) : if ( $f[ $k ] ) : ?><input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $f[ $k ] ); ?>"><?php endif; endforeach; ?>
						<label class="da-search"><span class="dashicons dashicons-search"></span><input type="search" name="s" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="Search name, email, organisation or text"></label>
						<label class="dm-date">From <input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
						<label class="dm-date">To <input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
						<button class="da-btn da-btn-small da-btn-primary" type="submit">Apply</button>
						<?php if ( array_filter( $f ) ) : ?><a class="da-btn da-btn-small" href="<?php echo esc_url( admin_url( 'admin.php?page=didi-messages' ) ); ?>">Clear</a><?php endif; ?>
						<span class="da-count"><?php echo (int) $q->found_posts; ?> message<?php echo 1 === (int) $q->found_posts ? '' : 's'; ?></span>
					</form>

					<?php if ( ! $items ) : ?>
						<div class="dm-empty"><span class="dashicons dashicons-email-alt"></span><h3>No messages here yet</h3><p>Messages sent from the contact page will appear in this list.</p></div>
					<?php endif; ?>

					<div class="dm-list" id="dmList">
						<?php foreach ( $items as $m ) : ?>
							<button type="button" class="dm-row is-<?php echo esc_attr( $m['status'] ); ?>" data-id="<?php echo (int) $m['id']; ?>">
								<span class="dm-avatar"><?php echo esc_html( self::initials( $m['name'] ) ); ?></span>
								<span class="dm-row-main">
									<span class="dm-row-top"><strong><?php echo esc_html( $m['name'] ); ?></strong><?php if ( $m['org'] ) : ?><em> · <?php echo esc_html( $m['org'] ); ?></em><?php endif; ?></span>
									<span class="dm-row-sub"><?php echo esc_html( $m['subject'] ); ?></span>
									<span class="dm-row-snip"><?php echo esc_html( wp_trim_words( $m['message'], 22 ) ); ?></span>
								</span>
								<span class="dm-row-meta">
									<span class="dm-cat"><?php echo esc_html( $m['reason_l'] ); ?></span>
									<span class="dm-badge dm-b-<?php echo esc_attr( $m['status'] ); ?>"><?php echo esc_html( Didi_Messages::statuses()[ $m['status'] ] ?? $m['status'] ); ?></span>
									<span class="dm-time"><?php echo esc_html( $m['date_h'] ); ?></span>
								</span>
							</button>
						<?php endforeach; ?>
					</div>

					<?php
					$pages = (int) $q->max_num_pages;
					if ( $pages > 1 ) {
						echo '<nav class="dm-pager" aria-label="Pages">';
						for ( $i = 1; $i <= $pages; $i++ ) {
							printf( '<a class="%s" href="%s">%d</a>', $i === $paged ? 'is-on' : '', esc_url( self::url( array( 'paged' => $i ) ) ), $i );
						}
						echo '</nav>';
					}
					?>
				</main>
			</div>

			<div class="dm-drawer-wrap" id="dmDrawer" hidden>
				<div class="dm-scrim" data-close></div>
				<aside class="dm-drawer" role="dialog" aria-modal="true" aria-labelledby="dmDTitle" tabindex="-1">
					<header class="dm-d-head"><div><span class="dm-cat" id="dmDCat"></span><h2 id="dmDTitle"></h2><p id="dmDWhen"></p></div><button type="button" class="da-modal-x" data-close aria-label="Close">&times;</button></header>
					<div class="dm-d-body">
						<ol class="dm-steps" id="dmSteps"></ol>
						<dl class="dm-fields" id="dmFields"></dl>
						<h3>Message</h3>
						<div class="dm-msg" id="dmMsg"></div>
						<h3>Internal note</h3>
						<textarea id="dmNote" class="da-input" rows="3" placeholder="Visible to the office only"></textarea>
						<button type="button" class="da-btn da-btn-small" id="dmNoteSave">Save note</button>
						<p class="da-hint" id="dmMeta"></p>
					</div>
					<footer class="dm-d-foot">
						<button type="button" class="da-btn da-btn-primary" id="dmApprove"><span class="dashicons dashicons-yes"></span> Approve &amp; forward to Principal</button>
						<a class="da-btn" id="dmReply" href="#"><span class="dashicons dashicons-undo"></span> Reply</a>
						<button type="button" class="da-btn" id="dmArchive">Archive</button>
						<button type="button" class="da-btn" id="dmSpam">Mark as spam</button>
						<button type="button" class="da-btn dm-danger" id="dmDelete">Delete</button>
					</footer>
				</aside>
			</div>
			<script type="application/json" id="dmData"><?php echo wp_json_encode( $items ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ */
	/* Settings                                                      */
	/* ------------------------------------------------------------ */

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You are not allowed to see this page.' );
		}
		$s = Didi_Messages::settings();
		$m = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		?>
		<div class="da-wrap dm-wrap">
			<?php if ( 'saved' === $m ) : ?><div class="da-notice da-notice-ok">Settings saved.</div><?php endif; ?>
			<?php if ( 'testok' === $m ) : ?><div class="da-notice da-notice-ok">Test email sent. Check the office inbox.</div><?php endif; ?>
			<?php if ( 'testfail' === $m ) : ?><div class="da-notice da-notice-err">The test email could not be sent. Check the mail server details and password.</div><?php endif; ?>
			<header class="da-hero"><div class="da-hero-text"><span class="da-eyebrow">Messages</span><h1>Settings</h1><p>Recipients, spam protection and the mail server used to send email.</p></div>
				<div class="da-hero-actions"><a class="da-btn da-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=didi-messages' ) ); ?>">&larr; Back to messages</a></div></header>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
				<input type="hidden" name="action" value="didi_msg_settings"><?php wp_nonce_field( 'didi_msg_settings' ); ?>
				<div class="dm-set-grid">
					<section class="da-panel">
						<div class="da-panel-head"><h2>Recipients</h2></div>
						<label class="da-label" for="o">Office inbox (receives every message)</label>
						<input id="o" class="da-input" type="email" name="office_email" value="<?php echo esc_attr( $s['office_email'] ); ?>">
						<label class="da-label" for="p">Principal (receives a message only after approval)</label>
						<input id="p" class="da-input" type="email" name="principal_email" value="<?php echo esc_attr( $s['principal_email'] ); ?>">
					</section>
					<section class="da-panel">
						<div class="da-panel-head"><h2>Spam protection: Cloudflare Turnstile</h2></div>
						<label class="da-label" for="ts">Site key (public)</label>
						<input id="ts" class="da-input" type="text" name="turnstile_site" value="<?php echo esc_attr( $s['turnstile_site'] ); ?>">
						<label class="da-label" for="tk">Secret key</label>
						<input id="tk" class="da-input" type="password" name="turnstile_secret" value="" placeholder="<?php echo $s['turnstile_secret'] ? 'Saved. Leave blank to keep it.' : 'Paste the secret key'; ?>" autocomplete="new-password">
						<p class="da-hint">The site key goes on the contact page. The secret key stays on the server and is stored encrypted. The contact form will not accept messages until the secret key is saved.</p>
					</section>
					<section class="da-panel dm-wide">
						<div class="da-panel-head"><h2>Mail server (SMTP)</h2></div>
						<label class="da-switch"><input type="checkbox" name="smtp_enabled" value="1" <?php checked( $s['smtp_enabled'] ); ?>><span class="da-switch-ui"></span><span class="da-switch-text"><strong>Send email through this mail server</strong><small>Recommended. Mail sent straight from the web host often lands in spam.</small></span></label>
						<div class="da-two dm-three">
							<div><label class="da-label" for="h">Outgoing server</label><input id="h" class="da-input" name="smtp_host" value="<?php echo esc_attr( $s['smtp_host'] ); ?>"></div>
							<div><label class="da-label" for="po">Port</label><input id="po" class="da-input" type="number" name="smtp_port" value="<?php echo esc_attr( $s['smtp_port'] ); ?>"></div>
							<div><label class="da-label" for="se">Security</label><select id="se" class="da-input" name="smtp_secure"><option value="ssl" <?php selected( $s['smtp_secure'], 'ssl' ); ?>>SSL (port 465)</option><option value="tls" <?php selected( $s['smtp_secure'], 'tls' ); ?>>TLS (port 587)</option><option value="" <?php selected( $s['smtp_secure'], '' ); ?>>None</option></select></div>
						</div>
						<div class="da-two">
							<div><label class="da-label" for="u">Username (the full mailbox address)</label><input id="u" class="da-input" name="smtp_user" value="<?php echo esc_attr( $s['smtp_user'] ); ?>" autocomplete="off"></div>
							<div><label class="da-label" for="w">Password</label><input id="w" class="da-input" type="password" name="smtp_pass" value="" placeholder="<?php echo $s['smtp_pass'] ? 'Saved. Leave blank to keep it.' : 'Mailbox password'; ?>" autocomplete="new-password"></div>
						</div>
						<div class="da-two">
							<div><label class="da-label" for="fe">Send from (address)</label><input id="fe" class="da-input" type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>"></div>
							<div><label class="da-label" for="fn">Send from (name)</label><input id="fn" class="da-input" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>"></div>
						</div>
						<p class="da-hint">The password is stored encrypted in the WordPress database and is never shown again.</p>
					</section>
				</div>
				<div class="da-btn-row"><button class="da-btn da-btn-primary" type="submit">Save settings</button>
					<a class="da-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=didi_msg_test' ), 'didi_msg_test' ) ); ?>">Send a test email to the office</a></div>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		check_admin_referer( 'didi_msg_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$old = Didi_Messages::settings();
		$t   = function ( $k ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		};
		$new = array(
			'turnstile_site'   => $t( 'turnstile_site' ),
			'turnstile_secret' => $old['turnstile_secret'],
			'office_email'     => sanitize_email( $t( 'office_email' ) ) ?: $old['office_email'],
			'principal_email'  => sanitize_email( $t( 'principal_email' ) ) ?: $old['principal_email'],
			'smtp_enabled'     => empty( $_POST['smtp_enabled'] ) ? 0 : 1,
			'smtp_host'        => $t( 'smtp_host' ),
			'smtp_port'        => absint( $t( 'smtp_port' ) ) ?: 465,
			'smtp_secure'      => in_array( $t( 'smtp_secure' ), array( 'ssl', 'tls' ), true ) ? $t( 'smtp_secure' ) : '',
			'smtp_user'        => $t( 'smtp_user' ),
			'smtp_pass'        => $old['smtp_pass'],
			'from_email'       => sanitize_email( $t( 'from_email' ) ),
			'from_name'        => $t( 'from_name' ),
		);
		$sec = isset( $_POST['turnstile_secret'] ) ? trim( (string) wp_unslash( $_POST['turnstile_secret'] ) ) : '';
		if ( '' !== $sec ) {
			$new['turnstile_secret'] = Didi_Messages::enc( $sec );
		}
		$pw = isset( $_POST['smtp_pass'] ) ? (string) wp_unslash( $_POST['smtp_pass'] ) : '';
		if ( '' !== $pw ) {
			$new['smtp_pass'] = Didi_Messages::enc( $pw );
		}
		update_option( Didi_Messages::OPT, $new, false );
		wp_safe_redirect( admin_url( 'admin.php?page=didi-messages-settings&msg=saved' ) );
		exit;
	}

	public static function test_mail() {
		check_admin_referer( 'didi_msg_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$s  = Didi_Messages::settings();
		$h  = array( 'Content-Type: text/html; charset=UTF-8' );
		$ok = wp_mail( $s['office_email'], 'Test email from didiwalsonjack.com', '<p>This is a test. If you can read it, the website can send email to the office.</p>', $h );
		wp_safe_redirect( admin_url( 'admin.php?page=didi-messages-settings&msg=' . ( $ok ? 'testok' : 'testfail' ) ) );
		exit;
	}

	/* ------------------------------------------------------------ */
	/* Actions                                                       */
	/* ------------------------------------------------------------ */

	public static function ajax_action() {
		check_ajax_referer( 'didi_msg', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$act = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( ! $id || get_post_type( $id ) !== Didi_Messages::TYPE ) {
			wp_send_json_error( array( 'message' => 'Message not found.' ) );
		}
		switch ( $act ) {
			case 'approve':
				if ( ! Didi_Messages::approve( $id ) ) {
					wp_send_json_error( array( 'message' => 'The email to the Principal could not be sent, so the message was not marked as forwarded. Check the mail settings and try again.' ) );
				}
				break;
			case 'spam':
			case 'archived':
			case 'new':
				update_post_meta( $id, '_dm_status', 'spam' === $act ? 'spam' : ( 'archived' === $act ? 'archived' : 'new' ) );
				break;
			case 'note':
				update_post_meta( $id, '_dm_note', isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '' );
				break;
			case 'delete':
				wp_delete_post( $id, true );
				wp_send_json_success( array( 'deleted' => true ) );
				break;
			default:
				wp_send_json_error( array( 'message' => 'Unknown action.' ) );
		}
		wp_send_json_success( array( 'message' => Didi_Messages::data( $id ) ) );
	}

	public static function export() {
		check_admin_referer( 'didi_msg_export' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$f = self::f();
		$q = Didi_Messages::query( $f, 1, 5000 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="contact-messages-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Received', 'Status', 'Category', 'Name', 'Organisation', 'Email', 'Phone', 'Subject', 'Message', 'Forwarded to Principal', 'Approved by', 'Approved at', 'Internal note', 'IP address' ) );
		$safe = function ( $v ) {
			$v = (string) $v;
			return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
		};
		foreach ( $q->posts as $p ) {
			$m = Didi_Messages::data( $p->ID );
			fputcsv( $out, array_map( $safe, array( $m['date_h'], Didi_Messages::statuses()[ $m['status'] ] ?? $m['status'], $m['reason_l'], $m['name'], $m['org'], $m['email'], $m['phone'], $m['subject'], $m['message'], $m['principal_sent'] ? 'Yes' : 'No', $m['approved_by'], $m['approved_at'], $m['note'], $m['ip'] ) ) );
		}
		fclose( $out );
		exit;
	}
}
