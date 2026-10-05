<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post type, meta fields, helpers and the public REST feed.
 */
class Didi_Awards_CPT {

	const TYPE = 'didi_award';

	/** meta key => [type, default] */
	public static function meta_keys() {
		return array(
			'_da_issuer'        => 'string',
			'_da_year'          => 'integer',
			'_da_date_text'     => 'string',
			'_da_venue'         => 'string',
			'_da_issuer_url'    => 'string',
			'_da_summary'       => 'string',
			'_da_featured'      => 'boolean',
			'_da_gallery'       => 'string',
			'_da_sources'       => 'string',
			'_da_image_suggest' => 'string',
			'_da_sort'          => 'integer',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
	}

	public static function register() {
		register_post_type( self::TYPE, array(
			'labels'        => array(
				'name'          => 'Awards',
				'singular_name' => 'Award',
				'add_new_item'  => 'Add award',
				'edit_item'     => 'Edit award',
				'all_items'     => 'All awards',
			),
			'public'        => true,
			'show_ui'       => true,
			'show_in_menu'  => false,
			'show_in_rest'  => true,
			'has_archive'   => 'awards',
			'rewrite'       => array( 'slug' => 'award', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'menu_icon'     => 'dashicons-awards',
			'capability_type' => 'post',
		) );

		foreach ( self::meta_keys() as $key => $type ) {
			register_post_meta( self::TYPE, $key, array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}

	/* ---------- data helpers ---------- */

	public static function placeholder_url() {
		return DIDI_AWARDS_URL . 'assets/img/award-placeholder.svg';
	}

	public static function featured_count( $exclude = 0 ) {
		$q = new WP_Query( array(
			'post_type'      => self::TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__not_in'   => $exclude ? array( (int) $exclude ) : array(),
			'meta_query'     => array( array( 'key' => '_da_featured', 'value' => '1' ) ),
		) );
		return (int) $q->found_posts;
	}

	/** Normalised array for one award post. */
	public static function prepare( $post, $size = 'large' ) {
		$post = get_post( $post );
		$id   = $post->ID;
		$img  = get_the_post_thumbnail_url( $id, $size );
		$gal  = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, '_da_gallery', true ) ) ) );
		$summary = get_post_meta( $id, '_da_summary', true );
		return array(
			'id'          => $id,
			'slug'        => $post->post_name,
			'status'      => $post->post_status,
			'title'       => get_the_title( $id ),
			'year'        => (int) get_post_meta( $id, '_da_year', true ),
			'date_text'   => (string) get_post_meta( $id, '_da_date_text', true ),
			'issuer'      => (string) get_post_meta( $id, '_da_issuer', true ),
			'venue'       => (string) get_post_meta( $id, '_da_venue', true ),
			'issuer_url'  => (string) get_post_meta( $id, '_da_issuer_url', true ),
			'summary'     => $summary ? $summary : wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 ),
			'featured'    => (bool) get_post_meta( $id, '_da_featured', true ),
			'image'       => $img ? $img : self::placeholder_url(),
			'has_image'   => (bool) $img,
			'thumb'       => get_the_post_thumbnail_url( $id, 'medium_large' ) ?: ( $img ? $img : self::placeholder_url() ),
			'gallery'     => array_values( $gal ),
			'sources'     => array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $id, '_da_sources', true ) ) ) ) ),
			'permalink'   => get_permalink( $id ),
			'edit_url'    => admin_url( 'admin.php?page=didi-awards-edit&award=' . $id ),
		);
	}

	/** Query awards, newest first. $args: featured, year, search, limit, status, exclude. */
	public static function query( $args = array() ) {
		$a = wp_parse_args( $args, array(
			'featured' => null,
			'year'     => 0,
			'search'   => '',
			'limit'    => -1,
			'status'   => 'publish',
			'exclude'  => 0,
		) );
		$meta = array();
		if ( null !== $a['featured'] ) {
			$meta[] = $a['featured']
				? array( 'key' => '_da_featured', 'value' => '1' )
				: array( 'relation' => 'OR', array( 'key' => '_da_featured', 'compare' => 'NOT EXISTS' ), array( 'key' => '_da_featured', 'value' => '1', 'compare' => '!=' ) );
		}
		if ( $a['year'] ) {
			$meta[] = array( 'key' => '_da_year', 'value' => (int) $a['year'] );
		}
		$meta[] = array( 'relation' => 'OR', array( 'key' => '_da_sort', 'compare' => 'EXISTS' ), array( 'key' => '_da_sort', 'compare' => 'NOT EXISTS' ) );
		$q = new WP_Query( array(
			'post_type'      => self::TYPE,
			'post_status'    => $a['status'],
			'posts_per_page' => (int) $a['limit'],
			's'              => $a['search'],
			'post__not_in'   => $a['exclude'] ? array( (int) $a['exclude'] ) : array(),
			'meta_query'     => $meta,
			'meta_key'       => '_da_sort',
			'orderby'        => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
			'no_found_rows'  => true,
		) );
		return $q->posts;
	}

	public static function years() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value AS y, COUNT(*) AS n FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_da_year' AND p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value <> ''
			 GROUP BY pm.meta_value ORDER BY pm.meta_value DESC",
			self::TYPE
		) );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->y ] = (int) $r->n;
		}
		return $out;
	}

	/* ---------- REST feed ---------- */

	public static function rest() {
		register_rest_route( 'didi/v1', '/awards', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'rest_awards' ),
			'args'                => array(
				'featured' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
				'limit'    => array( 'sanitize_callback' => 'absint' ),
				'year'     => array( 'sanitize_callback' => 'absint' ),
				'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
	}

	public static function rest_awards( WP_REST_Request $req ) {
		$args = array( 'limit' => $req->get_param( 'limit' ) ? min( 100, (int) $req->get_param( 'limit' ) ) : -1 );
		if ( null !== $req->get_param( 'featured' ) ) {
			$args['featured'] = (bool) $req->get_param( 'featured' );
			if ( $args['featured'] && -1 === $args['limit'] ) {
				$args['limit'] = DIDI_AWARDS_MAX_FEATURED;
			}
		}
		if ( $req->get_param( 'year' ) ) {
			$args['year'] = (int) $req->get_param( 'year' );
		}
		if ( $req->get_param( 'search' ) ) {
			$args['search'] = $req->get_param( 'search' );
		}
		$items = array();
		foreach ( self::query( $args ) as $p ) {
			$d       = self::prepare( $p );
			$items[] = array(
				'id'        => $d['id'],
				'title'     => $d['title'],
				'year'      => $d['year'],
				'date_text' => $d['date_text'],
				'issuer'    => $d['issuer'],
				'summary'   => $d['summary'],
				'image'     => $d['image'],
				'thumb'     => $d['thumb'],
				'has_image' => $d['has_image'],
				'featured'  => $d['featured'],
				'link'      => $d['permalink'],
			);
		}
		$res = rest_ensure_response( array( 'total' => count( $items ), 'archive' => get_post_type_archive_link( self::TYPE ), 'items' => $items ) );
		$res->header( 'Cache-Control', 'public, max-age=60' );
		return $res;
	}
}
