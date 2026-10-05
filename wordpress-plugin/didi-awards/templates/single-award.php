<?php
/**
 * One award. Layout: centred title, three columns (about / story / other awards).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
the_post();
$F    = 'Didi_Awards_Frontend';
$a    = Didi_Awards_CPT::prepare( get_the_ID(), 'full' );
$arch = get_post_type_archive_link( Didi_Awards_CPT::TYPE );
$all  = array_map( array( 'Didi_Awards_CPT', 'prepare' ), Didi_Awards_CPT::query( array( 'exclude' => $a['id'] ) ) );
$related = array_slice( array_values( array_filter( $all, function ( $x ) use ( $a ) { return $x['year'] === $a['year']; } ) ), 0, 2 );
if ( count( $related ) < 2 ) {
	foreach ( $all as $x ) {
		if ( count( $related ) >= 2 ) { break; }
		if ( ! in_array( $x['id'], wp_list_pluck( $related, 'id' ), true ) ) { $related[] = $x; }
	}
}
$others = array_slice( $all, 0, 4 );
$years  = Didi_Awards_CPT::years();
$meta   = array_filter( array( 'Award', $a['year'] ?: '', $a['issuer'] ? 'Issued by ' . $a['issuer'] : '', $a['date_text'] ) );
$ld     = wp_json_encode( array(
	'@context'    => 'https://schema.org',
	'@type'       => 'Article',
	'headline'    => $a['title'],
	'description' => $a['summary'],
	'image'       => $a['image'],
	'url'         => $a['permalink'],
	'about'       => array( '@type' => 'Person', 'name' => 'Mrs. Didi Esther Walson-Jack, OON, mni', 'url' => home_url( '/' ) ),
	'publisher'   => array( '@type' => 'Person', 'name' => 'Mrs. Didi Esther Walson-Jack, OON, mni' ),
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$share = rawurlencode( $a['permalink'] );
$stext = rawurlencode( $a['title'] . ' | Mrs. Didi Esther Walson-Jack' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php $F::head( $a['title'] . ' | Awards | Mrs. Didi Esther Walson-Jack, OON, mni', $a['summary'], $a['permalink'], $a['has_image'] ? $a['image'] : '', '<script type="application/ld+json">' . $ld . '</script>' ); ?>
</head>
<body class="award-single">
<?php $F::header( 'awards' ); ?>

<main>
	<header class="a-hero wrap">
		<nav class="crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span>/</span> <a href="<?php echo esc_url( $arch ); ?>">Awards</a></nav>
		<h1><?php echo esc_html( $a['title'] ); ?></h1>
		<p class="a-meta"><?php echo wp_kses_post( $F::mni( esc_html( implode( ' · ', $meta ) ) ) ); ?></p>
	</header>

	<div class="a-grid wrap">
		<aside class="a-left" aria-label="About">
			<h2 class="side-h">About her</h2>
			<img class="portrait" src="https://res.cloudinary.com/pwkdspnn/image/upload/f_auto,q_auto:good,w_600/v1787818879/IMG_1929_h6dhus.webp" alt="Mrs. Didi Esther Walson-Jack" loading="lazy">
			<p class="side-p">Lawyer, public administrator, reformer and author. 20th Head of the Civil Service of the Federation (2024-2026).</p>
			<h3 class="side-k">Let’s connect</h3>
			<ul class="social">
				<li><a href="https://x.com/Didi_WalsonJack" target="_blank" rel="noopener" aria-label="X">X</a></li>
				<li><a href="https://www.linkedin.com/in/didi-esther-walson-jack-mcipm-oon-mni-7285a22a6/" target="_blank" rel="noopener" aria-label="LinkedIn">in</a></li>
				<li><a href="https://www.facebook.com/didi.walsonjack/" target="_blank" rel="noopener" aria-label="Facebook">f</a></li>
				<li><a href="https://www.instagram.com/didi.walsonjack/" target="_blank" rel="noopener" aria-label="Instagram">ig</a></li>
			</ul>
		</aside>

		<article class="a-main">
			<figure class="a-figure<?php echo $a['has_image'] ? '' : ' is-placeholder'; ?>"><img src="<?php echo esc_url( $a['image'] ); ?>" alt="<?php echo esc_attr( $a['title'] ); ?>"></figure>

			<dl class="a-facts">
				<?php if ( $a['issuer'] ) : ?><div><dt>Issued by</dt><dd><?php echo esc_html( $a['issuer'] ); ?></dd></div><?php endif; ?>
				<?php if ( $a['year'] ) : ?><div><dt>Year</dt><dd><?php echo (int) $a['year']; ?></dd></div><?php endif; ?>
				<?php if ( $a['date_text'] ) : ?><div><dt>Date</dt><dd><?php echo esc_html( $a['date_text'] ); ?></dd></div><?php endif; ?>
				<?php if ( $a['venue'] ) : ?><div><dt>Venue</dt><dd><?php echo esc_html( $a['venue'] ); ?></dd></div><?php endif; ?>
			</dl>

			<div class="a-content"><?php echo wp_kses_post( wpautop( get_the_content() ) ); ?></div>

			<?php if ( $a['issuer_url'] ) : ?>
				<p class="a-issuer-link"><a class="btn" href="<?php echo esc_url( $a['issuer_url'] ); ?>" target="_blank" rel="noopener noreferrer">Visit the issuer’s page <span aria-hidden="true">↗</span></a></p>
			<?php endif; ?>

			<?php if ( $a['gallery'] ) : ?>
				<section class="a-gallery" aria-label="Photographs">
					<h2 class="block-h">Photographs</h2>
					<div class="g-grid">
						<?php foreach ( $a['gallery'] as $gid ) : $full = wp_get_attachment_image_url( $gid, 'large' ); if ( ! $full ) { continue; } ?>
							<a href="<?php echo esc_url( $full ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $gid, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => esc_attr( $a['title'] ) ) ); ?></a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $a['sources'] ) : ?>
				<section class="a-sources"><h2 class="block-h">Sources</h2><ul>
					<?php foreach ( $a['sources'] as $s ) : ?><li><a href="<?php echo esc_url( $s ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', $s ) ); ?></a></li><?php endforeach; ?>
				</ul></section>
			<?php endif; ?>

			<div class="a-share"><span>Share this:</span>
				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share; // phpcs:ignore ?>" target="_blank" rel="noopener">Facebook</a>
				<a href="https://twitter.com/intent/tweet?url=<?php echo $share; // phpcs:ignore ?>&amp;text=<?php echo $stext; // phpcs:ignore ?>" target="_blank" rel="noopener">X</a>
				<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $share; // phpcs:ignore ?>" target="_blank" rel="noopener">LinkedIn</a>
				<a href="https://wa.me/?text=<?php echo $stext . '%20' . $share; // phpcs:ignore ?>" target="_blank" rel="noopener">WhatsApp</a>
			</div>

			<section class="a-author">
				<img src="<?php echo esc_url( $F::asset( 'logos/walson-jack-emblem-badge.png' ) ); ?>" alt="" width="92" height="92">
				<div><h3>Mrs. Didi Esther Walson-Jack</h3><p>Lawyer, public administrator, reformer and author. Thirty-four years of public service, now a speaker and global advisor on institutions and leadership.</p></div>
			</section>

			<?php if ( $related ) : ?>
			<section class="a-related" aria-label="Related awards">
				<h2 class="block-h">Related awards</h2>
				<div class="rel-grid">
					<?php foreach ( $related as $r ) : ?>
						<a class="rel" href="<?php echo esc_url( $r['permalink'] ); ?>"><img src="<?php echo esc_url( $r['thumb'] ); ?>" alt="" loading="lazy"><span><?php echo esc_html( $r['title'] ); ?></span></a>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<p class="a-back"><a href="<?php echo esc_url( $arch ); ?>">&larr; All awards &amp; commendations</a></p>
		</article>

		<aside class="a-right" aria-label="More awards">
			<h2 class="side-h">Other awards</h2>
			<ul class="recent">
				<?php foreach ( $others as $o ) : ?>
					<li><span class="tag"><?php echo (int) $o['year']; ?></span><a href="<?php echo esc_url( $o['permalink'] ); ?>"><?php echo esc_html( $o['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<h3 class="side-k">Browse by year</h3>
			<ul class="archive-list">
				<?php foreach ( $years as $y => $n ) : ?>
					<li><a href="<?php echo esc_url( add_query_arg( 'year', $y, $arch ) ); ?>"><?php echo (int) $y; ?> <span>(<?php echo (int) $n; ?>)</span></a></li>
				<?php endforeach; ?>
			</ul>
			<p class="side-all"><a href="<?php echo esc_url( $arch ); ?>">View all awards →</a></p>
		</aside>
	</div>
</main>

<?php if ( current_user_can( 'edit_post', $a['id'] ) ) : ?><a class="edit-pill" href="<?php echo esc_url( $a['edit_url'] ); ?>">Edit this award</a><?php endif; ?>
<?php $F::footer(); ?>
</body>
</html>
