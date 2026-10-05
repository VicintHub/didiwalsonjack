<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Awards dashboard: card grid, add/edit form, featured toggle, image import.
 */
class Didi_Awards_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_didi_award_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_didi_award_trash', array( __CLASS__, 'handle_trash' ) );
		add_action( 'admin_post_didi_award_seed', array( __CLASS__, 'handle_seed' ) );
		add_action( 'wp_ajax_didi_award_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wp_ajax_didi_award_import_image', array( __CLASS__, 'ajax_import_image' ) );
	}

	public static function menu() {
		add_menu_page( 'Awards', 'Awards', 'edit_posts', 'didi-awards', array( __CLASS__, 'page_dashboard' ), 'dashicons-awards', 26 );
		add_submenu_page( 'didi-awards', 'Awards dashboard', 'Dashboard', 'edit_posts', 'didi-awards', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'didi-awards', 'Add award', 'Add award', 'edit_posts', 'didi-awards-edit', array( __CLASS__, 'page_edit' ) );
		add_submenu_page( 'didi-awards', 'Awards tools', 'Tools', 'manage_options', 'didi-awards-tools', array( __CLASS__, 'page_tools' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'didi-awards' ) ) {
			return;
		}
		wp_enqueue_style( 'didi-awards-fonts', 'https://fonts.googleapis.com/css2?family=Questrial&family=Source+Serif+4:wght@400;600;700&display=swap', array(), null );
		wp_enqueue_style( 'didi-awards-admin', DIDI_AWARDS_URL . 'assets/css/admin.css', array(), DIDI_AWARDS_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'didi-awards-admin', DIDI_AWARDS_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), DIDI_AWARDS_VERSION, true );
		wp_localize_script( 'didi-awards-admin', 'DidiAwards', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'didi_awards' ),
			'max'   => DIDI_AWARDS_MAX_FEATURED,
			'placeholder' => Didi_Awards_CPT::placeholder_url(),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                           */
	/* ------------------------------------------------------------------ */

	private static function notice() {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		$map = array(
			'saved'    => array( 'ok', 'Award saved.' ),
			'trashed'  => array( 'ok', 'Award moved to the trash.' ),
			'seeded'   => array( 'ok', 'CV awards imported.' ),
			'cap'      => array( 'warn', 'Saved, but this award is not featured: the home page already shows the maximum of ' . DIDI_AWARDS_MAX_FEATURED . '. Remove another award from the home page first.' ),
			'notitle'  => array( 'err', 'Please give the award a title.' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="da-notice da-notice-%s" role="status">%s</div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
	}

	public static function page_dashboard() {
		$posts = Didi_Awards_CPT::query( array( 'status' => array( 'publish', 'draft', 'pending', 'private' ) ) );
		$items = array_map( array( 'Didi_Awards_CPT', 'prepare' ), $posts );
		$total = count( $items );
		$feat  = count( array_filter( $items, function ( $i ) { return $i['featured']; } ) );
		$noimg = count( array_filter( $items, function ( $i ) { return ! $i['has_image']; } ) );
		$years = array();
		foreach ( $items as $i ) {
			if ( $i['year'] ) {
				$years[ $i['year'] ] = true;
			}
		}
		krsort( $years );
		?>
		<div class="da-wrap">
			<?php self::notice(); ?>
			<header class="da-hero">
				<div class="da-hero-text">
					<span class="da-eyebrow">Mrs. Didi Esther Walson-Jack, OON, <span class="mni">mni</span></span>
					<h1>Awards &amp; Commendations</h1>
					<p>Add and edit the honours shown on the website. Mark up to <?php echo (int) DIDI_AWARDS_MAX_FEATURED; ?> awards as featured to place them on the home page.</p>
				</div>
				<div class="da-hero-actions">
					<a class="da-btn da-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=didi-awards-edit' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span> Add award</a>
					<a class="da-btn da-btn-ghost" href="<?php echo esc_url( get_post_type_archive_link( Didi_Awards_CPT::TYPE ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span> View public page</a>
				</div>
			</header>

			<section class="da-stats" aria-label="Summary">
				<div class="da-stat"><span class="da-stat-num" id="daStatTotal"><?php echo (int) $total; ?></span><span class="da-stat-lbl">Awards</span></div>
				<div class="da-stat da-stat-feat">
					<span class="da-stat-num"><span id="daStatFeat"><?php echo (int) $feat; ?></span><small>/<?php echo (int) DIDI_AWARDS_MAX_FEATURED; ?></small></span>
					<span class="da-stat-lbl">Featured on home page</span>
					<span class="da-meter"><i id="daMeter" style="width:<?php echo (int) min( 100, $feat * 100 / DIDI_AWARDS_MAX_FEATURED ); ?>%"></i></span>
				</div>
				<div class="da-stat"><span class="da-stat-num"><?php echo (int) count( $years ); ?></span><span class="da-stat-lbl">Years covered</span></div>
				<div class="da-stat"><span class="da-stat-num"><?php echo (int) $noimg; ?></span><span class="da-stat-lbl">Still need an image</span></div>
			</section>

			<section class="da-toolbar">
				<label class="da-search"><span class="dashicons dashicons-search"></span>
					<input type="search" id="daSearch" placeholder="Search by title or issuer" autocomplete="off">
				</label>
				<select id="daYear" aria-label="Filter by year">
					<option value="">All years</option>
					<?php foreach ( array_keys( $years ) as $y ) : ?>
						<option value="<?php echo (int) $y; ?>"><?php echo (int) $y; ?></option>
					<?php endforeach; ?>
				</select>
				<div class="da-chips" role="group" aria-label="Quick filters">
					<button type="button" class="da-chip is-on" data-filter="all">All</button>
					<button type="button" class="da-chip" data-filter="featured">Featured</button>
					<button type="button" class="da-chip" data-filter="noimage">Needs image</button>
					<button type="button" class="da-chip" data-filter="draft">Drafts</button>
				</div>
				<span class="da-count" id="daCount" aria-live="polite"></span>
			</section>

			<section class="da-grid" id="daGrid">
				<?php foreach ( $items as $i ) : ?>
					<article class="da-card" data-id="<?php echo (int) $i['id']; ?>" data-year="<?php echo (int) $i['year']; ?>" data-featured="<?php echo $i['featured'] ? 1 : 0; ?>" data-noimage="<?php echo $i['has_image'] ? 0 : 1; ?>" data-draft="<?php echo 'publish' === $i['status'] ? 0 : 1; ?>" data-text="<?php echo esc_attr( strtolower( $i['title'] . ' ' . $i['issuer'] ) ); ?>">
						<a class="da-thumb<?php echo $i['has_image'] ? '' : ' is-empty'; ?>" href="<?php echo esc_url( $i['edit_url'] ); ?>">
							<img src="<?php echo esc_url( $i['thumb'] ); ?>" alt="" loading="lazy">
							<?php if ( ! $i['has_image'] ) : ?><span class="da-flag">No image yet</span><?php endif; ?>
							<?php if ( 'publish' !== $i['status'] ) : ?><span class="da-flag da-flag-draft"><?php echo esc_html( ucfirst( $i['status'] ) ); ?></span><?php endif; ?>
						</a>
						<button type="button" class="da-star<?php echo $i['featured'] ? ' is-on' : ''; ?>" data-id="<?php echo (int) $i['id']; ?>" aria-pressed="<?php echo $i['featured'] ? 'true' : 'false'; ?>" title="Feature on the home page">
							<span class="dashicons dashicons-star-filled"></span><span class="screen-reader-text">Feature on home page</span>
						</button>
						<div class="da-card-body">
							<span class="da-year"><?php echo $i['year'] ? (int) $i['year'] : 'No year'; ?></span>
							<h3><a href="<?php echo esc_url( $i['edit_url'] ); ?>"><?php echo esc_html( $i['title'] ); ?></a></h3>
							<p class="da-issuer"><?php echo esc_html( $i['issuer'] ? $i['issuer'] : 'Issuer not set' ); ?></p>
						</div>
						<footer class="da-card-foot">
							<a href="<?php echo esc_url( $i['edit_url'] ); ?>"><span class="dashicons dashicons-edit"></span> Edit</a>
							<a href="<?php echo esc_url( $i['permalink'] ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility"></span> View</a>
							<a class="da-trash" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=didi_award_trash&award=' . $i['id'] ), 'didi_award_trash_' . $i['id'] ) ); ?>" data-confirm="Move this award to the trash?"><span class="dashicons dashicons-trash"></span></a>
						</footer>
					</article>
				<?php endforeach; ?>
			</section>
			<p class="da-empty" id="daEmpty" hidden>No awards match your filters.</p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Add / edit                                                          */
	/* ------------------------------------------------------------------ */

	public static function page_edit() {
		$id   = isset( $_GET['award'] ) ? absint( $_GET['award'] ) : 0;
		$post = $id ? get_post( $id ) : null;
		if ( $id && ( ! $post || Didi_Awards_CPT::TYPE !== $post->post_type ) ) {
			echo '<div class="da-wrap"><div class="da-notice da-notice-err">Award not found.</div></div>';
			return;
		}
		$m = function ( $k, $d = '' ) use ( $id ) {
			if ( ! $id ) {
				return $d;
			}
			$v = get_post_meta( $id, $k, true );
			return '' === $v ? $d : $v;
		};
		$status   = $post ? $post->post_status : 'draft';
		$thumb_id = $id ? get_post_thumbnail_id( $id ) : 0;
		$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
		$gallery  = array_filter( array_map( 'absint', explode( ',', (string) $m( '_da_gallery' ) ) ) );
		$suggest  = (string) $m( '_da_image_suggest' );
		$fcount   = Didi_Awards_CPT::featured_count( $id );
		$is_feat  = (bool) $m( '_da_featured', 0 );
		?>
		<div class="da-wrap">
			<?php self::notice(); ?>
			<form id="daForm" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="didi_award_save">
				<input type="hidden" name="award_id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'didi_award_save' ); ?>

				<div class="da-editbar">
					<div>
						<a class="da-back" href="<?php echo esc_url( admin_url( 'admin.php?page=didi-awards' ) ); ?>">&larr; All awards</a>
						<h1><?php echo $id ? 'Edit award' : 'New award'; ?></h1>
					</div>
					<div class="da-editbar-actions">
						<?php if ( $id ) : ?><a class="da-btn da-btn-ghost" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility"></span> View page</a><?php endif; ?>
						<button type="submit" class="da-btn da-btn-primary" id="daSave"><span class="dashicons dashicons-yes"></span> <?php echo $id ? 'Save changes' : 'Save award'; ?></button>
					</div>
				</div>

				<div class="da-edit-grid">
					<div class="da-edit-main">
						<section class="da-panel">
							<label class="da-label" for="daTitle">Award title</label>
							<input type="text" id="daTitle" name="title" class="da-input da-input-xl" required placeholder="e.g. Distinguished Alumnus Award" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>">
							<?php if ( $post && 'publish' === $post->post_status ) : ?>
								<p class="da-hint">Page address: <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_permalink( $id ) ); ?></a></p>
							<?php endif; ?>
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>Card summary</h2><span class="da-counter" id="daSummaryCount">0 / 200</span></div>
							<p class="da-hint">One or two sentences. Shown on the award cards and the home-page slider.</p>
							<textarea name="summary" id="daSummary" class="da-input" rows="3" maxlength="200"><?php echo esc_textarea( $m( '_da_summary' ) ); ?></textarea>
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>The story</h2></div>
							<p class="da-hint">The full account of the award, shown on the award page. Use the quote button for pull quotes.</p>
							<?php
							wp_editor( $post ? $post->post_content : '', 'da_story', array(
								'textarea_name' => 'story',
								'media_buttons' => true,
								'textarea_rows' => 14,
								'teeny'         => false,
								'quicktags'     => true,
							) );
							?>
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>Other images</h2><button type="button" class="da-btn da-btn-small" id="daGalleryAdd"><span class="dashicons dashicons-format-gallery"></span> Add images</button></div>
							<p class="da-hint">Photographs of the occasion. Drag to reorder.</p>
							<input type="hidden" name="gallery" id="daGalleryField" value="<?php echo esc_attr( implode( ',', $gallery ) ); ?>">
							<ul class="da-gallery" id="daGallery">
								<?php foreach ( $gallery as $gid ) : $u = wp_get_attachment_image_url( $gid, 'thumbnail' ); if ( ! $u ) { continue; } ?>
									<li data-id="<?php echo (int) $gid; ?>"><img src="<?php echo esc_url( $u ); ?>" alt=""><button type="button" class="da-x" aria-label="Remove image">&times;</button></li>
								<?php endforeach; ?>
							</ul>
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>Sources</h2></div>
							<p class="da-hint">Optional. One web address per line, for the “Sources” list on the award page.</p>
							<textarea name="sources" class="da-input" rows="3" placeholder="https://"><?php echo esc_textarea( $m( '_da_sources' ) ); ?></textarea>
						</section>
					</div>

					<aside class="da-edit-side">
						<section class="da-panel">
							<div class="da-panel-head"><h2>Publish</h2></div>
							<label class="da-label" for="daStatus">Status</label>
							<select name="status" id="daStatus" class="da-input">
								<option value="publish"<?php selected( 'publish', $status ); ?>>Published</option>
								<option value="draft"<?php selected( 'draft', $status ); ?>>Draft (hidden)</option>
							</select>
							<label class="da-switch">
								<input type="checkbox" name="featured" value="1" id="daFeatured"<?php checked( $is_feat ); ?><?php echo ( ! $is_feat && $fcount >= DIDI_AWARDS_MAX_FEATURED ) ? ' disabled' : ''; ?>>
								<span class="da-switch-ui"></span>
								<span class="da-switch-text"><strong>Feature on the home page</strong><small><?php echo (int) $fcount; ?> of <?php echo (int) DIDI_AWARDS_MAX_FEATURED; ?> places used by other awards</small></span>
							</label>
							<?php if ( ! $is_feat && $fcount >= DIDI_AWARDS_MAX_FEATURED ) : ?><p class="da-hint da-warn">All <?php echo (int) DIDI_AWARDS_MAX_FEATURED; ?> home-page places are in use. Un-feature another award to free one.</p><?php endif; ?>
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>Featured image</h2></div>
							<input type="hidden" name="thumb_id" id="daThumbId" value="<?php echo (int) $thumb_id; ?>">
							<div class="da-featured-img<?php echo $thumb ? '' : ' is-empty'; ?>" id="daThumbBox">
								<img id="daThumbImg" src="<?php echo esc_url( $thumb ? $thumb : Didi_Awards_CPT::placeholder_url() ); ?>" alt="">
								<span class="da-flag" id="daThumbFlag"<?php echo $thumb ? ' hidden' : ''; ?>>No image yet</span>
							</div>
							<div class="da-btn-row">
								<button type="button" class="da-btn da-btn-small da-btn-primary" id="daThumbPick"><span class="dashicons dashicons-format-image"></span> <span id="daThumbPickTxt"><?php echo $thumb ? 'Replace' : 'Choose image'; ?></span></button>
								<button type="button" class="da-btn da-btn-small da-btn-ghost" id="daThumbRemove"<?php echo $thumb ? '' : ' hidden'; ?>>Remove</button>
							</div>
							<?php if ( $id ) : ?>
							<details class="da-import"<?php echo $suggest ? ' open' : ''; ?>>
								<summary>Import from a web address</summary>
								<input type="url" id="daImportUrl" class="da-input" placeholder="https://…/photo.jpg" value="<?php echo esc_attr( $suggest ); ?>">
								<button type="button" class="da-btn da-btn-small" id="daImportBtn" data-id="<?php echo (int) $id; ?>">Import and use</button>
								<p class="da-hint">Copies the image into the Media Library. Only import images you have permission to use.</p>
								<p class="da-hint" id="daImportMsg" role="status"></p>
							</details>
							<?php endif; ?>
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>Award details</h2></div>
							<label class="da-label" for="daIssuer">Issuer</label>
							<input type="text" id="daIssuer" name="issuer" class="da-input" placeholder="Who gave the award" value="<?php echo esc_attr( $m( '_da_issuer' ) ); ?>">
							<div class="da-two">
								<div><label class="da-label" for="daYearF">Award year</label>
								<input type="number" id="daYearF" name="year" class="da-input" min="1950" max="2100" value="<?php echo esc_attr( $m( '_da_year' ) ); ?>"></div>
								<div><label class="da-label" for="daDate">Date shown</label>
								<input type="text" id="daDate" name="date_text" class="da-input" placeholder="23 May 2026" value="<?php echo esc_attr( $m( '_da_date_text' ) ); ?>"></div>
							</div>
							<label class="da-label" for="daVenue">Venue</label>
							<input type="text" id="daVenue" name="venue" class="da-input" placeholder="City, venue" value="<?php echo esc_attr( $m( '_da_venue' ) ); ?>">
						</section>

						<section class="da-panel">
							<div class="da-panel-head"><h2>Issuer’s page</h2></div>
							<p class="da-hint">Link to the issuer’s own page about this award. Shown as a button on the award page.</p>
							<input type="url" name="issuer_url" class="da-input" placeholder="https://" value="<?php echo esc_attr( $m( '_da_issuer_url' ) ); ?>">
						</section>

						<?php if ( $id ) : ?>
						<p class="da-danger"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=didi_award_trash&award=' . $id ), 'didi_award_trash_' . $id ) ); ?>" data-confirm="Move this award to the trash?">Move to trash</a></p>
						<?php endif; ?>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Tools                                                               */
	/* ------------------------------------------------------------------ */

	public static function page_tools() {
		?>
		<div class="da-wrap">
			<?php self::notice(); ?>
			<header class="da-hero"><div class="da-hero-text"><span class="da-eyebrow">Awards</span><h1>Tools</h1><p>Housekeeping for the awards list.</p></div></header>
			<section class="da-panel da-tool">
				<h2>Import the awards listed in the CV</h2>
				<p>Adds any of the 32 CV awards that are not on the site yet. Awards that are already there, including ones you have edited, are never changed.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="didi_award_seed">
					<?php wp_nonce_field( 'didi_award_seed' ); ?>
					<button class="da-btn da-btn-primary" type="submit">Import missing CV awards</button>
				</form>
			</section>
			<section class="da-panel da-tool">
				<h2>Public links</h2>
				<p>All awards page: <a href="<?php echo esc_url( get_post_type_archive_link( Didi_Awards_CPT::TYPE ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_post_type_archive_link( Didi_Awards_CPT::TYPE ) ); ?></a></p>
				<p>Home-page feed: <a href="<?php echo esc_url( rest_url( 'didi/v1/awards?featured=1' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( rest_url( 'didi/v1/awards?featured=1' ) ); ?></a></p>
				<p class="da-hint">If an award page shows “not found”, open Settings &rarr; Permalinks and press Save once.</p>
			</section>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	private static function redirect( $page, $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_save() {
		check_admin_referer( 'didi_award_save' );
		$id = isset( $_POST['award_id'] ) ? absint( $_POST['award_id'] ) : 0;
		if ( $id ? ! current_user_can( 'edit_post', $id ) : ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'You are not allowed to do that.', 403 );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			self::redirect( 'didi-awards-edit', $id ? array( 'award' => $id, 'msg' => 'notitle' ) : array( 'msg' => 'notitle' ) );
		}
		$status = ( isset( $_POST['status'] ) && 'publish' === $_POST['status'] && current_user_can( 'publish_posts' ) ) ? 'publish' : 'draft';
		$story  = isset( $_POST['story'] ) ? wp_kses_post( wp_unslash( $_POST['story'] ) ) : '';

		$postarr = array(
			'post_type'    => Didi_Awards_CPT::TYPE,
			'post_title'   => $title,
			'post_content' => $story,
			'post_status'  => $status,
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			$id = wp_update_post( $postarr, true );
		} else {
			$id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}

		$text = function ( $k ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		};
		$year      = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
		$date_text = $text( 'date_text' );
		$ts        = $date_text ? strtotime( $date_text ) : false;
		$sort      = $ts && (int) gmdate( 'Y', $ts ) === $year ? (int) gmdate( 'Ymd', $ts ) : $year * 10000 + 101;

		update_post_meta( $id, '_da_issuer', $text( 'issuer' ) );
		update_post_meta( $id, '_da_year', $year );
		update_post_meta( $id, '_da_date_text', $date_text );
		update_post_meta( $id, '_da_venue', $text( 'venue' ) );
		update_post_meta( $id, '_da_summary', mb_substr( isset( $_POST['summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) : '', 0, 200 ) );
		update_post_meta( $id, '_da_issuer_url', isset( $_POST['issuer_url'] ) ? esc_url_raw( wp_unslash( $_POST['issuer_url'] ) ) : '' );
		update_post_meta( $id, '_da_sources', isset( $_POST['sources'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sources'] ) ) : '' );
		update_post_meta( $id, '_da_sort', $sort );

		$gallery = isset( $_POST['gallery'] ) ? implode( ',', array_filter( array_map( 'absint', explode( ',', wp_unslash( $_POST['gallery'] ) ) ) ) ) : '';
		update_post_meta( $id, '_da_gallery', $gallery );

		$thumb = isset( $_POST['thumb_id'] ) ? absint( $_POST['thumb_id'] ) : 0;
		if ( $thumb && get_post( $thumb ) ) {
			set_post_thumbnail( $id, $thumb );
		} else {
			delete_post_thumbnail( $id );
		}

		$msg      = 'saved';
		$featured = ! empty( $_POST['featured'] );
		if ( $featured && Didi_Awards_CPT::featured_count( $id ) >= DIDI_AWARDS_MAX_FEATURED ) {
			$featured = false;
			$msg      = 'cap';
		}
		update_post_meta( $id, '_da_featured', $featured ? 1 : 0 );

		self::redirect( 'didi-awards-edit', array( 'award' => $id, 'msg' => $msg ) );
	}

	public static function handle_trash() {
		$id = isset( $_GET['award'] ) ? absint( $_GET['award'] ) : 0;
		check_admin_referer( 'didi_award_trash_' . $id );
		if ( ! $id || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( 'You are not allowed to do that.', 403 );
		}
		wp_trash_post( $id );
		self::redirect( 'didi-awards', array( 'msg' => 'trashed' ) );
	}

	public static function handle_seed() {
		check_admin_referer( 'didi_award_seed' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You are not allowed to do that.', 403 );
		}
		Didi_Awards_Seed::run( true );
		self::redirect( 'didi-awards-tools', array( 'msg' => 'seeded' ) );
	}

	public static function ajax_toggle() {
		check_ajax_referer( 'didi_awards', 'nonce' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$now = ! get_post_meta( $id, '_da_featured', true );
		if ( $now && Didi_Awards_CPT::featured_count( $id ) >= DIDI_AWARDS_MAX_FEATURED ) {
			wp_send_json_error( array( 'message' => 'The home page already shows ' . DIDI_AWARDS_MAX_FEATURED . ' awards. Remove one first.' ) );
		}
		update_post_meta( $id, '_da_featured', $now ? 1 : 0 );
		wp_send_json_success( array( 'featured' => $now, 'count' => Didi_Awards_CPT::featured_count() ) );
	}

	public static function ajax_import_image() {
		check_ajax_referer( 'didi_awards', 'nonce' );
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid web address.' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_sideload_image( $url, $id, get_the_title( $id ), 'id' );
		if ( is_wp_error( $att ) ) {
			wp_send_json_error( array( 'message' => $att->get_error_message() ) );
		}
		set_post_thumbnail( $id, $att );
		wp_send_json_success( array( 'id' => $att, 'url' => wp_get_attachment_image_url( $att, 'large' ) ) );
	}
}
