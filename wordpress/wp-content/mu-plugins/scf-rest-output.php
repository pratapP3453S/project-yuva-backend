<?php
/**
 * Plugin Name: SCF REST output (scf key, no _source)
 * Description: Shapes the Secure Custom Fields REST output for this headless site:
 *   1) Exposes field data under an "scf" key. We CLONE SCF's registered "acf"
 *      REST field to "scf" (reusing its get/update/schema callbacks) so that
 *      WordPress computes "scf" natively and ?_fields=scf works correctly.
 *      We deliberately do NOT unregister "acf" — SCF re-registers it per request
 *      and guards by the name "acf" (class-acf-rest-api.php register_field()),
 *      so removing it just makes SCF add it back. Instead we drop "acf" from the
 *      response output in step 2.
 *   2) Removes the "acf" key from REST responses, and strips SCF's hardcoded
 *      "{field}_source" companion objects ({label,type,formatted_value}) from
 *      the "scf" payload so each field appears exactly once.
 *   3) Makes "scf_format" the ONLY query param that controls value formatting.
 *      Any client-supplied "acf_format" is ignored (overwritten) at dispatch,
 *      because SCF reads "acf_format" internally and we want the SCF-named param
 *      to be the sole control. So ?scf_format=standard works and a bare
 *      ?acf_format=standard no longer applies (it falls back to the default
 *      rest_api_format = light).
 * Notes for other developers:
 *   - The "scf" field is returned ONLY when ?scf_format is present (a convention
 *     gate, NOT access control -- anyone can still add scf_format).
 *   - The Yoast head fields ("yoast_head_json" and "yoast_head") follow the SAME
 *     gate: they are dropped from the output unless ?scf_format is present.
 *   - Consumers must read response.scf (NOT response.acf), request ?_fields=scf
 *     (NOT acf), and pass ?scf_format=standard (a plain acf_format is ignored).
 *   - SCF (a fork of ACF) hardcodes the "acf" key and the "_source" entries with
 *     no setting to change them; this adjusts the OUTPUT only (no core edits).
 * Version: 1.1.0
 * Author:  Yuva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 0) Make "scf_format" the sole formatting control (strict scf-only).
 *    SCF reads "acf_format" internally, so we overwrite it on every request:
 *    set it from "scf_format" when provided, otherwise null it out so a
 *    client-supplied "acf_format" is ignored and the default format applies.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		$scf_format = $request->get_param( 'scf_format' );
		$request->set_param(
			'acf_format',
			( null !== $scf_format && '' !== $scf_format ) ? $scf_format : null
		);
		return $result;
	},
	10,
	3
);

/**
 * 1) Clone SCF's registered "acf" REST field to "scf" (after SCF registers it).
 *    Reuses SCF's own get/update/schema callbacks under the new key.
 */
add_action(
	'rest_api_init',
	function () {
		global $wp_rest_additional_fields;
		if ( ! is_array( $wp_rest_additional_fields ) ) {
			return;
		}
		foreach ( $wp_rest_additional_fields as $object_type => $fields ) {
			if ( isset( $fields['acf'] ) && ! isset( $fields['scf'] ) ) {
				$wp_rest_additional_fields[ $object_type ]['scf'] = $fields['acf'];
			}
		}
	},
	99
);

/**
 * 2) In the response: drop "acf" entirely, and strip SCF's "_source" companions
 *    from "scf". The _source check matches SCF's exact companion shape
 *    ({ label, type, formatted_value }) so a real field named "*_source" is never
 *    removed by accident.
 */
function yuva_scf_rest_shape( $response, $post, $request ) {
	if ( ! ( $response instanceof WP_REST_Response ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		return $response;
	}

	// Data lives under "scf" — always remove the legacy "acf" key.
	if ( array_key_exists( 'acf', $data ) ) {
		unset( $data['acf'] );
	}

	// Convention gate: only expose "scf" when the request passes scf_format.
	// (NOT security — anyone can add scf_format; this only enforces the param.)
	$scf_format     = ( $request instanceof WP_REST_Request ) ? $request->get_param( 'scf_format' ) : null;
	$has_scf_format = ( null !== $scf_format && '' !== $scf_format );

	if ( ! $has_scf_format ) {
		unset( $data['scf'] );
		// Gate Yoast output behind scf_format too: when scf_format is absent, drop
		// the Yoast head pair so it follows the exact same rule as "scf". Only
		// removed from the OUTPUT (Yoast still computes it for scf_format requests).
		unset( $data['yoast_head_json'], $data['yoast_head'] );
		$response->set_data( $data );
		return $response;
	}

	if ( ! empty( $data['scf'] ) && is_array( $data['scf'] ) ) {
		foreach ( array_keys( $data['scf'] ) as $key ) {
			if ( '_source' !== substr( (string) $key, -7 ) ) {
				continue;
			}
			$val = $data['scf'][ $key ];
			if ( is_array( $val )
				&& isset( $val['label'], $val['type'] )
				&& array_key_exists( 'formatted_value', $val )
			) {
				unset( $data['scf'][ $key ] );
			}
		}
	}

	// Slim every media attachment (image / gallery item / file) down to { url, alt }
	// to keep the payload small. Recurses through groups, repeaters, flexible content,
	// and galleries. url-type fields (plain strings) and taxonomy term objects are
	// untouched.
	if ( ! empty( $data['scf'] ) && is_array( $data['scf'] ) ) {
		$data['scf'] = yuva_scf_slim_media( $data['scf'] );
	}

	$response->set_data( $data );
	return $response;
}

/**
 * Recursively replace SCF attachment arrays with a slim { url, alt } pair.
 * An SCF attachment is detected by the co-presence of url + mime_type + filename
 * (a signature a normal group/repeater field will not accidentally match; plain
 * "url"-type fields are strings, not arrays, so they pass through untouched).
 */
function yuva_scf_slim_media( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	// This node IS an attachment object — collapse it to { url, alt }.
	if ( isset( $value['url'], $value['mime_type'], $value['filename'] ) ) {
		return array(
			'url' => $value['url'],
			'alt' => isset( $value['alt'] ) ? $value['alt'] : '',
		);
	}

	// Otherwise recurse (groups, repeaters, flexible content, galleries).
	foreach ( $value as $key => $child ) {
		if ( is_array( $child ) ) {
			$value[ $key ] = yuva_scf_slim_media( $child );
		}
	}

	return $value;
}

add_action(
	'init',
	function () {
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'names' ) as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", 'yuva_scf_rest_shape', 20, 3 );
		}
	},
	99
);
