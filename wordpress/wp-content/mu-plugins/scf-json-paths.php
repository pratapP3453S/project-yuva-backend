<?php
/**
 * Plugin Name: SCF JSON Paths
 * Description: Registers load/save paths under the active theme's scf-json/ directory so Secure Custom Fields (SCF) field-group JSON can be organised into pages/, components/, and options/.
 * Version:     1.1.0
 * Author:      Yuva
 *
 * IMPORTANT FOR OTHER DEVELOPERS:
 *   - This site runs **Secure Custom Fields (SCF)**, the WordPress.org fork of ACF.
 *   - Field-group JSON lives in the active theme under **scf-json/** (renamed from
 *     the historical acf-json/ so the folder name reflects the plugin in use).
 *   - SCF is API-compatible with ACF and still fires the legacy **acf/** filters
 *     (it has NO scf/* equivalents). So the hooks below are intentionally named
 *     `acf/settings/load_json`, `acf/json/save_paths`, `acf/settings/save_json`
 *     even though the folder is scf-json/. Do not "correct" them to scf/* — those
 *     hooks do not exist in SCF and the JSON would stop loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Absolute path to this theme's SCF local-JSON folder. */
function yuva_scf_json_base() {
	return get_stylesheet_directory() . '/scf-json';
}

/**
 * Tell SCF where to LOAD field-group JSON from: the scf-json/ root plus the
 * pages/, components/ and options/ subfolders.
 */
add_filter(
	'acf/settings/load_json',
	function ( $paths ) {
		$base = yuva_scf_json_base();

		if ( is_dir( $base ) ) {
			$paths[] = $base;
		}

		foreach ( array( 'pages', 'components', 'options' ) as $sub ) {
			$dir = $base . '/' . $sub;
			if ( is_dir( $dir ) ) {
				$paths[] = $dir;
			}
		}

		return $paths;
	}
);

/**
 * SCF core appends the theme's acf-json/ folder as the default SAVE path
 * (includes/local-json.php). Repoint that single default to scf-json/ so a
 * brand-new field group never gets written to a stray acf-json/ folder.
 */
add_filter(
	'acf/settings/save_json',
	function ( $path ) {
		$base = yuva_scf_json_base();
		return is_dir( $base ) ? $base : $path;
	}
);

/**
 * Route each field group to the correct scf-json/ subfolder when it is saved.
 */
add_filter(
	'acf/json/save_paths',
	function ( $paths, $post ) {
		$base = trailingslashit( yuva_scf_json_base() );

		// 1. Primary signal: if the field group was loaded from a JSON file under
		//    our scf-json/ tree, save it back to that same subfolder. SCF stamps
		//    `local_file` onto the post at load time (local-json.php) and it
		//    persists through DB round-trips, so it's reliable here.
		if ( ! empty( $post['local_file'] ) && is_string( $post['local_file'] ) ) {
			$load_dir = trailingslashit( wp_normalize_path( dirname( $post['local_file'] ) ) );
			$base_n   = wp_normalize_path( $base );
			if ( 0 === strpos( $load_dir, $base_n ) && is_dir( $load_dir ) ) {
				return array( untrailingslashit( $load_dir ) );
			}
		}

		// 2. Fallback for NEW field groups (no local_file yet): inspect location
		//    rules and route by category. Accept any param that targets a page,
		//    a block, or an options page.
		$page_params    = array( 'page', 'page_type', 'page_template', 'page_parent' );
		$options_params = array( 'options_page', 'options_sub_page' );

		$locations = ( isset( $post['location'] ) && is_array( $post['location'] ) )
			? $post['location']
			: array();

		foreach ( $locations as $rule_group ) {
			if ( ! is_array( $rule_group ) ) {
				continue;
			}
			foreach ( $rule_group as $rule ) {
				$param = isset( $rule['param'] ) ? $rule['param'] : '';
				$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';

				if ( in_array( $param, $page_params, true ) ) {
					return array( untrailingslashit( $base ) . '/pages' );
				}
				if ( 'post_type' === $param && 'page' === $value ) {
					return array( untrailingslashit( $base ) . '/pages' );
				}
				if ( 'block' === $param ) {
					return array( untrailingslashit( $base ) . '/components' );
				}
				if ( in_array( $param, $options_params, true ) ) {
					return array( untrailingslashit( $base ) . '/options' );
				}
			}
		}

		// 3. Final fallback: the default save path (now scf-json/ root, set above).
		return $paths;
	},
	10,
	2
);
