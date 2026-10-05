<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports the awards listed in the CV (data/awards.json). Safe to run again:
 * an award that was already imported (matched by seed slug) is never touched.
 */
class Didi_Awards_Seed {

	public static function run( $verbose = false ) {
		$file = DIDI_AWARDS_DIR . 'data/awards.json';
		if ( ! file_exists( $file ) ) {
			return array( 'added' => 0, 'skipped' => 0 );
		}
		$rows = json_decode( file_get_contents( $file ), true );
		if ( ! is_array( $rows ) ) {
			return array( 'added' => 0, 'skipped' => 0 );
		}
		$added   = 0;
		$skipped = 0;
		foreach ( $rows as $r ) {
			$exists = get_posts( array(
				'post_type'      => Didi_Awards_CPT::TYPE,
				'post_status'    => 'any',
				'meta_key'       => '_da_seed_slug',
				'meta_value'     => $r['slug'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			if ( $exists ) {
				$skipped++;
				continue;
			}
			$id = wp_insert_post( array(
				'post_type'    => Didi_Awards_CPT::TYPE,
				'post_status'  => 'publish',
				'post_title'   => $r['title'],
				'post_name'    => $r['slug'],
				'post_content' => $r['story'],
			), true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_da_seed_slug', $r['slug'] );
			update_post_meta( $id, '_da_year', (int) $r['year'] );
			update_post_meta( $id, '_da_date_text', $r['date_text'] );
			update_post_meta( $id, '_da_issuer', $r['issuer'] );
			update_post_meta( $id, '_da_venue', $r['venue'] );
			update_post_meta( $id, '_da_summary', $r['summary'] );
			update_post_meta( $id, '_da_issuer_url', $r['issuer_url'] );
			update_post_meta( $id, '_da_sources', implode( "\n", (array) $r['sources'] ) );
			update_post_meta( $id, '_da_image_suggest', $r['image_suggest'] );
			update_post_meta( $id, '_da_sort', (int) $r['sort'] );
			update_post_meta( $id, '_da_featured', ! empty( $r['featured'] ) ? 1 : 0 );
			$added++;
		}
		update_option( 'didi_awards_seeded', time() );
		return array( 'added' => $added, 'skipped' => $skipped );
	}
}
