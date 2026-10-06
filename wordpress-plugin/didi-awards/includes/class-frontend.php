<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public pages: /awards/ (all awards) and /award/{slug}/ (one award).
 * Both are rendered as complete, self-contained pages in the site's branding.
 */
class Didi_Awards_Frontend {

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'pre_get_posts', array( __CLASS__, 'archive_query' ) );
	}

	public static function template( $tpl ) {
		if ( is_singular( Didi_Awards_CPT::TYPE ) ) {
			return DIDI_AWARDS_DIR . 'templates/single-award.php';
		}
		if ( is_post_type_archive( Didi_Awards_CPT::TYPE ) ) {
			return DIDI_AWARDS_DIR . 'templates/archive-awards.php';
		}
		return $tpl;
	}

	public static function archive_query( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_post_type_archive( Didi_Awards_CPT::TYPE ) ) {
			$q->set( 'posts_per_page', -1 );
			$q->set( 'meta_key', '_da_sort' );
			$q->set( 'orderby', array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) );
		}
	}

	/* ---------- shared view helpers ---------- */

	public static function asset( $path ) {
		return DIDI_AWARDS_URL . 'assets/' . ltrim( $path, '/' );
	}

	public static function head( $title, $desc, $url, $image = '', $extra = '' ) {
		?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<meta name="description" content="<?php echo esc_attr( $desc ); ?>">
<link rel="canonical" href="<?php echo esc_url( $url ); ?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>">
<meta property="og:url" content="<?php echo esc_url( $url ); ?>">
<?php if ( $image ) : ?><meta property="og:image" content="<?php echo esc_url( $image ); ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?php echo esc_url( self::asset( 'logos/favicon-192.png' ) ); ?>" sizes="192x192">
<link rel="apple-touch-icon" href="<?php echo esc_url( self::asset( 'logos/favicon-192.png' ) ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Questrial&amp;family=Source+Serif+4:wght@400;600;700&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( self::asset( 'css/front.css' ) . '?v=' . DIDI_AWARDS_VERSION ); ?>">
<?php
		echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-LD built with wp_json_encode.
	}

	public static function header( $active = '' ) {
		$links = array(
			'home'    => array( home_url( '/' ), 'Home' ),
			'about'   => array( home_url( '/about' ), 'About' ),
			'career'  => array( home_url( '/career' ), 'Career & Achievements' ),
			'awards'  => array( get_post_type_archive_link( Didi_Awards_CPT::TYPE ), 'Awards' ),
			'books'   => array( home_url( '/books' ), 'Books' ),
			'consulting' => array( 'https://www.walsonjackconsulting.org', 'Consulting ↗' ),
		);
		?>
<header class="site-header" id="siteHeader">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand"><img src="<?php echo esc_url( self::asset( 'logos/walson-jack-lockup-horizontal.png' ) ); ?>" alt="Mrs. Didi Esther Walson-Jack, OON, mni, 20th Head of the Civil Service of the Federation (2024-2026)" width="200" height="83"></a>
	<nav class="nav" aria-label="Main">
		<?php foreach ( $links as $k => $l ) : ?>
			<a href="<?php echo esc_url( $l[0] ); ?>"<?php echo $k === $active ? ' class="is-active" aria-current="page"' : ''; ?><?php echo 'consulting' === $k ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $l[1] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<a class="nav-cta" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact <span aria-hidden="true">↗</span></a>
	<button class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false">☰</button>
	<div class="drawer" id="navDrawer">
		<?php foreach ( $links as $k => $l ) : ?><a href="<?php echo esc_url( $l[0] ); ?>"<?php echo 'consulting' === $k ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $l[1] ); ?></a><?php endforeach; ?>
		<a class="btn" href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact ↗</a>
	</div>
</header>
		<?php
	}

	public static function footer() {
		?>
<footer class="site-footer">
	<div class="wrap foot-grid">
		<div>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="foot-disc"><img src="<?php echo esc_url( self::asset( 'logos/walson-jack-emblem-badge.png' ) ); ?>" alt="Mrs. Didi Esther Walson-Jack, OON, mni" width="96" height="96"></a>
			<p>20th Head of the Civil Service of the Federation (2024-2026), OON, mni, now retired from active public service. Lawyer, public administrator, reformer, author, speaker and global advisor.</p>
		</div>
		<div>
			<h4>Navigation</h4>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
				<li><a href="<?php echo esc_url( home_url( '/about' ) ); ?>">Biography</a></li>
				<li><a href="<?php echo esc_url( home_url( '/career' ) ); ?>">Career &amp; Reforms</a></li>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( Didi_Awards_CPT::TYPE ) ); ?>">Awards</a></li>
				<li><a href="<?php echo esc_url( home_url( '/books' ) ); ?>">Books</a></li>
				<li><a href="https://www.walsonjackconsulting.org" target="_blank" rel="noopener noreferrer">Consulting ↗</a></li>
				<li><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact &amp; Advisory</a></li>
			</ul>
		</div>
		<div>
			<h4>Official Contact</h4>
			<p><strong>Email:</strong> <a href="mailto:didi@didiwalsonjack.com">didi@didiwalsonjack.com</a></p>
			<p><strong>Phone:</strong> <a href="tel:+2349095119999">+234 909 511 9999</a></p>
			<p><strong>Location:</strong> Beautiful Gate Villa, Plot 812 Paul Unongo Crescent, Jabi, Abuja, Nigeria</p>
		</div>
	</div>
	<div class="wrap foot-bottom"><span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Mrs. Didi Esther Walson-Jack, OON, mni. All Rights Reserved.</span><a href="https://www.vicint.org.ng" target="_blank" rel="noopener noreferrer">Professionally designed by Vicint hub</a></div>
</footer>
<script>
(function(){var t=document.getElementById('navToggle'),d=document.getElementById('navDrawer');if(t&&d){t.addEventListener('click',function(){var o=d.classList.toggle('open');t.setAttribute('aria-expanded',o?'true':'false');t.textContent=o?'✕':'☰';});}
var h=document.getElementById('siteHeader'),l=window.scrollY;window.addEventListener('scroll',function(){var c=window.scrollY;if(c<100){h.classList.remove('hide');}else if(c>l+8){h.classList.add('hide');}else if(c<l-8){h.classList.remove('hide');}l=c;},{passive:true});})();
</script>
		<?php
	}

	/** Wrap every "mni" so no uppercase styling can turn it into "MNI". */
	public static function mni( $html ) {
		return preg_replace( '/(?<![A-Za-z>"\'])mni(?![A-Za-z])/i', '<span class="mni">mni</span>', $html );
	}

	/** One award card. */
	public static function card( $a, $extra_class = '' ) {
		?>
<article class="award-card <?php echo esc_attr( $extra_class ); ?>" data-year="<?php echo (int) $a['year']; ?>" data-text="<?php echo esc_attr( strtolower( $a['title'] . ' ' . $a['issuer'] ) ); ?>">
	<a class="award-card-img" href="<?php echo esc_url( $a['permalink'] ); ?>" tabindex="-1" aria-hidden="true"><img src="<?php echo esc_url( $a['thumb'] ); ?>" alt="" loading="lazy"><?php if ( $a['featured'] ) : ?><span class="award-star" title="Featured">★</span><?php endif; ?></a>
	<div class="award-card-body">
		<span class="chip"><?php echo $a['year'] ? (int) $a['year'] : ''; ?></span>
		<h3><a href="<?php echo esc_url( $a['permalink'] ); ?>"><?php echo esc_html( $a['title'] ); ?></a></h3>
		<p class="award-issuer"><?php echo esc_html( $a['issuer'] ); ?></p>
		<a class="more" href="<?php echo esc_url( $a['permalink'] ); ?>">Read more <span aria-hidden="true">→</span></a>
	</div>
</article>
		<?php
	}
}
