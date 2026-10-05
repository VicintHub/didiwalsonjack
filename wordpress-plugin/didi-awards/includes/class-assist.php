<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smart import: reads pasted links and notes, pulls out the facts that matter and
 * arranges them into the award fields for review. It uses plain text analysis, not
 * an AI service, so it needs no key and costs nothing. Nothing is saved until the
 * editor approves it and presses Save.
 */
class Didi_Awards_Assist {

	const MAX_LINKS = 8;
	const MAX_BYTES = 1500000;

	public static function init() {
		add_action( 'wp_ajax_didi_award_gather', array( __CLASS__, 'ajax_gather' ) );
		add_action( 'wp_ajax_didi_award_save_crop', array( __CLASS__, 'ajax_save_crop' ) );
	}

	/* ---------------------------------------------------------------- */
	/* AJAX                                                              */
	/* ---------------------------------------------------------------- */

	public static function ajax_gather() {
		check_ajax_referer( 'didi_awards', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		$links = isset( $_POST['links'] ) ? (string) wp_unslash( $_POST['links'] ) : '';
		$notes = isset( $_POST['notes'] ) ? (string) wp_unslash( $_POST['notes'] ) : '';
		$urls  = array();
		foreach ( preg_split( '/\s+/', $links ) as $u ) {
			$u = esc_url_raw( trim( $u ) );
			if ( $u && preg_match( '#^https?://#i', $u ) ) {
				$urls[ $u ] = $u;
			}
		}
		$urls = array_slice( array_values( $urls ), 0, self::MAX_LINKS );
		if ( ! $urls && '' === trim( $notes ) ) {
			wp_send_json_error( array( 'message' => 'Paste at least one link or some notes first.' ) );
		}
		$sources = array();
		$errors  = array();
		foreach ( $urls as $u ) {
			$s = self::fetch( $u );
			if ( is_wp_error( $s ) ) {
				$errors[] = $u . ': ' . $s->get_error_message();
			} else {
				$sources[] = $s;
			}
		}
		if ( '' !== trim( $notes ) ) {
			$sources[] = array(
				'url'        => '',
				'label'      => 'Pasted notes',
				'site'       => '',
				'title'      => '',
				'description'=> '',
				'image'      => '',
				'published'  => '',
				'paragraphs' => self::paragraphs( $notes ),
			);
		}
		if ( ! $sources ) {
			wp_send_json_error( array( 'message' => 'None of the links could be read. ' . implode( ' ', $errors ) ) );
		}
		$out           = self::analyse( $sources );
		$out['errors'] = $errors;
		wp_send_json_success( $out );
	}

	public static function ajax_save_crop() {
		check_ajax_referer( 'didi_awards', 'nonce' );
		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! current_user_can( 'upload_files' ) || ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) ) {
			wp_send_json_error( array( 'message' => 'The cropped image did not arrive. Please try again.' ) );
		}
		if ( $_FILES['file']['size'] > 12 * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => 'The cropped image is too large.' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Award image';
		$up    = wp_handle_upload( $_FILES['file'], array(
			'test_form' => false,
			'mimes'     => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ),
		) );
		if ( ! empty( $up['error'] ) ) {
			wp_send_json_error( array( 'message' => $up['error'] ) );
		}
		$att = wp_insert_attachment( array(
			'post_mime_type' => $up['type'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
		), $up['file'], $post_id );
		if ( is_wp_error( $att ) ) {
			wp_send_json_error( array( 'message' => $att->get_error_message() ) );
		}
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
		update_post_meta( $att, '_wp_attachment_image_alt', $title );
		wp_send_json_success( array( 'id' => $att, 'url' => wp_get_attachment_image_url( $att, 'large' ) ?: $up['url'] ) );
	}

	/* ---------------------------------------------------------------- */
	/* Fetching                                                          */
	/* ---------------------------------------------------------------- */

	public static function fetch( $url ) {
		$res = wp_safe_remote_get( $url, array(
			'timeout'             => 12,
			'redirection'         => 4,
			'limit_response_size' => self::MAX_BYTES,
			'user-agent'          => 'Mozilla/5.0 (compatible; DidiAwardsBot/1.0; +' . home_url( '/' ) . ')',
			'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml' ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http', 'the page returned status ' . $code );
		}
		$type = (string) wp_remote_retrieve_header( $res, 'content-type' );
		if ( $type && false === stripos( $type, 'html' ) && false === stripos( $type, 'text' ) ) {
			return new WP_Error( 'type', 'this is not a web page' );
		}
		return self::parse_html( wp_remote_retrieve_body( $res ), $url );
	}

	public static function parse_html( $html, $url = '' ) {
		$out = array(
			'url'        => $url,
			'label'      => $url ? preg_replace( '#^https?://(www\.)?#', '', $url ) : '',
			'site'       => '',
			'title'      => '',
			'description'=> '',
			'image'      => '',
			'published'  => '',
			'paragraphs' => array(),
		);
		if ( '' === trim( $html ) ) {
			return $out;
		}
		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$xp   = new DOMXPath( $doc );
		$meta = function ( $names ) use ( $xp ) {
			foreach ( (array) $names as $n ) {
				$nodes = $xp->query( '//meta[@property="' . $n . '" or @name="' . $n . '"]/@content' );
				if ( $nodes && $nodes->length ) {
					return trim( $nodes->item( 0 )->nodeValue );
				}
			}
			return '';
		};
		$out['title']       = $meta( array( 'og:title', 'twitter:title' ) );
		if ( '' === $out['title'] ) {
			$t = $xp->query( '//title' );
			$out['title'] = $t && $t->length ? trim( $t->item( 0 )->textContent ) : '';
		}
		$out['site']        = $meta( 'og:site_name' );
		$out['description'] = $meta( array( 'og:description', 'description', 'twitter:description' ) );
		$out['image']       = $meta( array( 'og:image', 'twitter:image' ) );
		$out['published']   = $meta( array( 'article:published_time', 'date', 'pubdate' ) );
		if ( $out['image'] && ! preg_match( '#^https?://#', $out['image'] ) && $url ) {
			$out['image'] = self::absolutise( $out['image'], $url );
		}
		foreach ( $xp->query( '//script|//style|//nav|//footer|//header|//aside|//form|//noscript' ) as $n ) {
			$n->parentNode->removeChild( $n );
		}
		$paras = array();
		foreach ( $xp->query( '//p|//h2|//h3|//li|//blockquote' ) as $n ) {
			$txt = trim( preg_replace( '/\s+/u', ' ', $n->textContent ) );
			if ( mb_strlen( $txt ) >= 40 ) {
				$paras[] = $txt;
			}
		}
		$out['paragraphs'] = array_values( array_unique( $paras ) );
		return $out;
	}

	private static function absolutise( $rel, $base ) {
		$p = wp_parse_url( $base );
		if ( 0 === strpos( $rel, '//' ) ) {
			return ( $p['scheme'] ?? 'https' ) . ':' . $rel;
		}
		if ( 0 === strpos( $rel, '/' ) ) {
			return ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? '' ) . $rel;
		}
		return $base;
	}

	public static function paragraphs( $text ) {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$parts = preg_split( '/\n{2,}|\n/', $text );
		$out   = array();
		foreach ( $parts as $p ) {
			$p = trim( preg_replace( '/\s+/u', ' ', $p ) );
			if ( mb_strlen( $p ) >= 20 ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------- */
	/* Analysis                                                          */
	/* ---------------------------------------------------------------- */

	const KEYWORDS = 'award|honour|honor|commendation|recognition|recognised|recognized|induct|hall of fame|presented|conferred|distinguished|lifetime|appreciation|excellence';

	/** House style: her name, titles and spellings. */
	public static function style( $s ) {
		$rules = array(
			'/\bMrs\.?\s+(Didi\s+)?(Esther\s+)?Walson-?Jack\b/u' => 'Mrs. Didi Esther Walson-Jack',
			'/\b(Dame|Dr\.?|Chief|Madam)\s+(Didi\s+)?(Esther\s+)?Walson-?Jack\b/u' => 'Mrs. Didi Esther Walson-Jack',
			'/\b(Esther\s+)?Didi\s+(Wilson|Walson)-?\s?Jack\b/u' => 'Didi Esther Walson-Jack',
			'/(?<!Esther )(?<!Mrs\. )(?<![\w-])Walson-Jack\b/u' => 'Mrs. Walson-Jack',
			'/\bMNI\b/' => 'mni',
			'/\bServiceWise\b/i' => 'Service-Wise',
			'/\bHOS(F)?\b/' => 'Head of the Civil Service',
			'/\s+([,.;:!?])/u' => '$1',
			'/\s{2,}/u' => ' ',
		);
		foreach ( $rules as $re => $to ) {
			$s = preg_replace( $re, $to, $s );
		}
		return trim( $s );
	}

	private static function sentences( $text ) {
		$text = preg_replace( '/([.!?])(["”’]?)\s+(?=[A-Z“"‘\'])/u', "$1$2\n", $text );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) );
	}

	private static function relevant( $sources ) {
		$out  = array();
		$seen = array();
		foreach ( $sources as $i => $s ) {
			$count = 0;
			foreach ( $s['paragraphs'] as $p ) {
				$hit   = preg_match( '/walson|head of (the )?(civil )?service|\bhos\b/i', $p );
				$kw    = preg_match( '/' . self::KEYWORDS . '/i', $p );
				$event = $kw && preg_match( '/\b(held|hosted|took place|ceremony|dinner|summit|edition|gala)\b/i', $p );
				if ( ! $hit && ! $event && ! ( 'Pasted notes' === $s['label'] && $kw ) ) {
					continue;
				}
				foreach ( self::sentences( $p ) as $sent ) {
					if ( mb_strlen( $sent ) < 35 || mb_strlen( $sent ) > 420 ) {
						continue;
					}
					if ( ! preg_match( '/walson|' . self::KEYWORDS . '/i', $sent ) && 'Pasted notes' !== $s['label'] ) {
						continue;
					}
					if ( ! preg_match( '/walson/i', $sent ) && ! preg_match( '/\b(held|hosted|took place|ceremony|dinner|summit|edition|gala)\b/i', $sent ) && 'Pasted notes' !== $s['label'] ) {
						continue;
					}
					$key = strtolower( preg_replace( '/[^a-z0-9]+/i', '', $sent ) );
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$out[]        = array( 'text' => $sent, 'src' => $i, 'kw' => (bool) preg_match( '/' . self::KEYWORDS . '/i', $sent ), 'name' => (bool) preg_match( '/walson/i', $sent ) );
					$count++;
					if ( $count >= 6 ) {
						break;
					}
				}
				if ( $count >= 6 ) {
					break;
				}
			}
		}
		return $out;
	}

	public static function analyse( $sources ) {
		$corpus = '';
		foreach ( $sources as $s ) {
			$corpus .= ' ' . $s['title'] . '. ' . $s['description'] . '. ' . implode( ' ', array_slice( $s['paragraphs'], 0, 60 ) );
		}
		$rel   = self::relevant( $sources );
		$rtext = implode( ' ', wp_list_pluck( $rel, 'text' ) );
		$focus = $rtext ? $rtext : $corpus;

		$fields = array();

		/* title */
		$title = self::guess_title( $focus, $sources );
		$fields['title'] = self::field( $title, self::src_for( $sources, $title ) );

		/* issuer */
		$issuer = self::guess_issuer( $focus, $sources );
		$fields['issuer'] = self::field( $issuer, self::src_for( $sources, $issuer ) );

		/* date + year */
		$date = self::guess_date( $focus, $sources );
		if ( '' === $date['text'] ) {
			$alt = self::guess_date( $corpus, $sources );
			if ( $alt['text'] ) {
				$date = $alt;
			}
		}
		$fields['date_text'] = self::field( $date['text'], $date['src'] );
		$fields['year']      = self::field( $date['year'] ? (string) $date['year'] : '', $date['src'] );

		/* venue */
		$venue = self::guess_venue( $focus );
		if ( '' === $venue ) {
			$venue = self::guess_venue( $corpus );
		}
		$fields['venue'] = self::field( $venue, self::src_for( $sources, $venue ) );

		/* summary */
		$summary = '';
		foreach ( $rel as $r ) {
			if ( $r['name'] && $r['kw'] ) {
				$summary = $r['text'];
				break;
			}
		}
		if ( '' === $summary && $rel ) {
			$summary = $rel[0]['text'];
		}
		if ( '' === $summary ) {
			foreach ( $sources as $s ) {
				if ( $s['description'] ) {
					$summary = $s['description'];
					break;
				}
			}
		}
		$summary = self::style( $summary );
		if ( mb_strlen( $summary ) > 200 ) {
			$summary = rtrim( mb_substr( $summary, 0, 197 ), ' ,;:' ) . '…';
		}
		$fields['summary'] = self::field( $summary, self::src_for( $sources, $summary ) );

		/* story */
		$html = '';
		$used = array();
		foreach ( $rel as $r ) {
			$t = self::style( $r['text'] );
			if ( preg_match( '/[“"]([^”"]{25,220})[”"]/u', $t, $q ) && preg_match( '/said|noted|remarked|stated|according/i', $t ) ) {
				$html .= '<blockquote>' . esc_html( $q[1] ) . '</blockquote>';
			}
			$html .= '<p>' . esc_html( $t ) . '</p>';
			$used[ $r['src'] ] = true;
			if ( substr_count( $html, '<p>' ) >= 6 ) {
				break;
			}
		}
		if ( '' === $html && $summary ) {
			$html = '<p>' . esc_html( $summary ) . '</p>';
		}
		$fields['story'] = array(
			'value'   => $html,
			'sources' => array_values( array_map( function ( $i ) use ( $sources ) {
				return $sources[ $i ]['label']; }, array_keys( $used ) ) ),
			'note'    => 'Assembled from the sentences that mention her and the award. Read it through and put it in your own words before publishing.',
		);

		/* sources + images + issuer link */
		$links  = array();
		$images = array();
		foreach ( $sources as $s ) {
			if ( $s['url'] ) {
				$links[] = $s['url'];
			}
			if ( $s['image'] ) {
				$images[] = array( 'url' => $s['image'], 'from' => $s['label'] );
			}
		}
		$fields['sources'] = array( 'value' => implode( "\n", $links ), 'sources' => array(), 'note' => '' );

		return array(
			'fields'  => $fields,
			'images'  => $images,
			'read'    => array_map( function ( $s ) {
				return array( 'label' => $s['label'], 'title' => $s['title'], 'paragraphs' => count( $s['paragraphs'] ) ); }, $sources ),
		);
	}

	private static function field( $value, $sources ) {
		return array( 'value' => (string) $value, 'sources' => (array) $sources, 'note' => '' );
	}

	private static function src_for( $sources, $needle ) {
		if ( '' === (string) $needle ) {
			return array();
		}
		$out = array();
		foreach ( $sources as $s ) {
			$hay = $s['title'] . ' ' . $s['description'] . ' ' . implode( ' ', $s['paragraphs'] );
			if ( false !== stripos( $hay, $needle ) || false !== stripos( $hay, mb_substr( $needle, 0, 40 ) ) ) {
				$out[] = $s['label'];
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 3 );
	}

	public static function guess_title( $text, $sources ) {
		$cands = array();
		$re    = '/\b((?:The\s+)?(?:[A-Z][\p{L}\'’&\-]+\s+){0,5}(?:Award|Honour|Honor|Commendation|Recognition|Induction)(?:\s+(?:of|for|in)\s+(?:the\s+)?[A-Z][\p{L}\'’&\-]+(?:\s+[A-Z][\p{L}\'’&\-]+){0,4})?)\b/u';
		if ( preg_match_all( $re, $text, $m ) ) {
			foreach ( $m[1] as $c ) {
				$c = trim( preg_replace( '/^(The|A|An|Received|Presented|Honoured|Honored|Awarded|With)\s+/u', '', $c ) );
				if ( mb_strlen( $c ) < 8 || mb_strlen( $c ) > 90 ) {
					continue;
				}
				$cands[ $c ] = ( $cands[ $c ] ?? 0 ) + 1;
			}
		}
		if ( $cands ) {
			uksort( $cands, function ( $a, $b ) use ( $cands ) {
				return ( $cands[ $b ] <=> $cands[ $a ] ) ?: ( mb_strlen( $b ) <=> mb_strlen( $a ) ); } );
			return self::style( (string) key( $cands ) );
		}
		foreach ( $sources as $s ) {
			if ( $s['title'] && 'Pasted notes' !== $s['label'] ) {
				return self::style( preg_replace( '/\s*[|\-–—]\s*[^|\-–—]{3,40}$/u', '', $s['title'] ) );
			}
		}
		return '';
	}

	public static function guess_issuer( $text, $sources ) {
		$pats = array(
			'/\b(?:presented|conferred|awarded|given|bestowed|honoured|honored)\b[^.]{0,80}?\b(?:by|from)\s+(?:the\s+)?([A-Z][\p{L}\'’&\-]+(?:\s+(?:of|and|for|the|&|[A-Z][\p{L}\'’&\-]+)){0,9})/u',
			'/\b(?:organi[sz]ed|hosted|convened)\s+by\s+(?:the\s+)?([A-Z][\p{L}\'’&\-]+(?:\s+(?:of|and|for|the|&|[A-Z][\p{L}\'’&\-]+)){0,9})/u',
			'/\b(?:from|by)\s+the\s+((?:[A-Z][\p{L}\'’&\-]+\s+){1,8}(?:Association|Council|Committee|Ministry|Foundation|Institute|Club|University|Society|Network|Commission|Awards|Summit|Forum|Alumni)[\p{L}\s]{0,30})/u',
			'/\b((?:[A-Z][\p{L}\'’&\-]+\s+){1,6}(?:Awards|Association|Foundation|Council|Club|Society)(?:\s+of\s+[A-Z][\p{L}]+)?)\b/u',
		);
		foreach ( $pats as $re ) {
			if ( preg_match( $re, $text, $m ) ) {
				$v = trim( preg_replace( '/\s+(at|in|on|for|during|where|who|which|that)\b.*$/u', '', $m[1] ), " ,.;" );
				if ( mb_strlen( $v ) >= 5 ) {
					return $v;
				}
			}
		}
		foreach ( $sources as $s ) {
			if ( $s['site'] ) {
				return $s['site'];
			}
		}
		return '';
	}

	public static function guess_date( $text, $sources ) {
		$months = 'January|February|March|April|May|June|July|August|September|October|November|December';
		$res    = array( 'text' => '', 'year' => 0, 'src' => array() );
		if ( preg_match( '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?(' . $months . ')[,\s]+(\d{4})\b/', $text, $m ) ) {
			$res['text'] = (int) $m[1] . ' ' . $m[2] . ' ' . $m[3];
			$res['year'] = (int) $m[3];
		} elseif ( preg_match( '/\b(' . $months . ')\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/', $text, $m ) ) {
			$res['text'] = (int) $m[2] . ' ' . $m[1] . ' ' . $m[3];
			$res['year'] = (int) $m[3];
		} elseif ( preg_match( '/\b(' . $months . ')\s+(\d{4})\b/', $text, $m ) ) {
			$res['text'] = $m[1] . ' ' . $m[2];
			$res['year'] = (int) $m[2];
		}
		if ( ! $res['year'] ) {
			foreach ( $sources as $s ) {
				if ( $s['published'] && preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $s['published'], $d ) ) {
					$res['year'] = (int) $d[1];
					$res['text'] = '';
					$res['src']  = array( $s['label'] );
					break;
				}
			}
		}
		if ( ! $res['year'] && preg_match_all( '/\b(20[0-3]\d|19[89]\d)\b/', $text, $y ) ) {
			$c = array_count_values( $y[1] );
			arsort( $c );
			$res['year'] = (int) key( $c );
		}
		if ( $res['text'] ) {
			$res['src'] = self::src_for( $sources, $res['text'] );
		}
		return $res;
	}

	public static function guess_venue( $text ) {
		$cities = 'Abuja|Lagos|Accra|Port Harcourt|Yenagoa|Ibadan|Kano|Enugu|Calabar|Uyo|Dubai|London|Singapore|Lusaka|Nairobi|Kigali|Johannesburg|Cape Town|New York|Washington|Paris|Geneva|Addis Ababa|Abeokuta|Benin City|Owerri|Jos|Kaduna|Ilorin|Asaba|Awka|Lokoja|Makurdi';
		if ( preg_match( '/\b(?:held\s+)?at\s+(?:the\s+)?((?:[A-Z][\p{L}\'’&\-]+\s+){1,7}(?:Hotel|Centre|Center|Hall|Suites|Resort|Auditorium|Arena|Villa|Complex|Stadium|House)(?:\s+(?:and|&)\s+[A-Z][\p{L}]+)?)(?:,\s*([A-Z][\p{L}\s]{2,30}?))?(?=[,.;]|\s+(?:on|in|where|for|as)\b|$)/u', $text, $m ) ) {
			$v = trim( $m[1] );
			if ( ! empty( $m[2] ) && ! preg_match( '/\b(Award|Honour|Committee|Ministry)\b/', $m[2] ) ) {
				$v .= ', ' . trim( $m[2] );
			}
			return $v;
		}
		if ( preg_match( '/\bin\s+(' . $cities . ')(?:,\s*([A-Z][a-z]+))?/', $text, $m ) ) {
			return $m[1] . ( ! empty( $m[2] ) ? ', ' . $m[2] : '' );
		}
		return '';
	}
}
