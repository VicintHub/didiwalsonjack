<?php
/**
 * All awards: filterable card grid.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$F     = 'Didi_Awards_Frontend';
$arch  = get_post_type_archive_link( Didi_Awards_CPT::TYPE );
$items = array_map( array( 'Didi_Awards_CPT', 'prepare' ), Didi_Awards_CPT::query() );
$years = Didi_Awards_CPT::years();
$sel   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php $F::head( 'Awards & Commendations | Mrs. Didi Esther Walson-Jack, OON, mni', 'The awards, honours and commendations of Mrs. Didi Esther Walson-Jack, OON, mni, 20th Head of the Civil Service of the Federation (2024-2026).', $arch ); ?>
</head>
<body class="award-archive">
<?php $F::header( 'awards' ); ?>

<main>
	<section class="ar-hero">
		<div class="wrap">
			<span class="eyebrow">Honours &amp; recognition</span>
			<h1>Awards &amp; Commendations</h1>
			<p>Thirty-four years of public service, recognised by institutions across Nigeria and Africa. <?php echo (int) count( $items ); ?> awards, from <?php echo esc_html( $years ? min( array_keys( $years ) ) : '' ); ?> to <?php echo esc_html( $years ? max( array_keys( $years ) ) : '' ); ?>.</p>
		</div>
	</section>

	<section class="wrap ar-body">
		<div class="ar-toolbar">
			<label class="ar-search"><span aria-hidden="true">⌕</span><input type="search" id="arSearch" placeholder="Search awards or issuers" autocomplete="off" aria-label="Search awards"></label>
			<select id="arYear" aria-label="Filter by year">
				<option value="">All years</option>
				<?php foreach ( $years as $y => $n ) : ?><option value="<?php echo (int) $y; ?>"<?php selected( $sel, $y ); ?>><?php echo (int) $y; ?> (<?php echo (int) $n; ?>)</option><?php endforeach; ?>
			</select>
			<span class="ar-count" id="arCount" aria-live="polite"></span>
		</div>

		<div class="ar-grid" id="arGrid">
			<?php foreach ( $items as $a ) { $F::card( $a ); } ?>
		</div>
		<p class="ar-empty" id="arEmpty" hidden>No awards match your search.</p>
	</section>
</main>

<?php if ( current_user_can( 'edit_posts' ) ) : ?><a class="edit-pill" href="<?php echo esc_url( admin_url( 'admin.php?page=didi-awards' ) ); ?>">Manage awards</a><?php endif; ?>
<?php $F::footer(); ?>
<script>
(function(){
  var cards=[].slice.call(document.querySelectorAll('#arGrid .award-card')),s=document.getElementById('arSearch'),y=document.getElementById('arYear'),c=document.getElementById('arCount'),e=document.getElementById('arEmpty');
  function run(){var q=s.value.toLowerCase().trim(),yr=y.value,n=0;cards.forEach(function(k){var ok=(!q||k.getAttribute('data-text').indexOf(q)>-1)&&(!yr||k.getAttribute('data-year')===yr);k.hidden=!ok;if(ok)n++;});c.textContent=n+' of '+cards.length+' awards';e.hidden=n!==0;}
  s.addEventListener('input',run);y.addEventListener('change',run);run();
})();
</script>
</body>
</html>
