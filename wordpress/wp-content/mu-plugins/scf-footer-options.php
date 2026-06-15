<?php
/**
 * Plugin Name: SCF Footer Options
 * Description: Registers the global "Footer" options page for the Footer Fields field group
 *   (scf-json/options/group_footer_fields.json, located by options_page == "footer-settings")
 *   and exposes its values to the headless frontend at a dedicated REST route.
 * Notes for other developers:
 *   - This site runs Secure Custom Fields (SCF), the WordPress.org fork of ACF. SCF is
 *     API-compatible and still fires the legacy acf/* functions, so acf_add_options_page()
 *     and get_fields() are the correct calls (there are no scf_* equivalents).
 *   - The options-page menu_slug MUST equal the field group's location value
 *     ("footer-settings") or the fields will not attach to the page.
 *   - Footer values are saved under the options bucket post_id "footer" (set below), so they
 *     are read with get_field( $name, 'footer' ) / get_fields( 'footer' ).
 *   - Options-page values live in wp_options and are NOT part of the post REST output shaped
 *     by scf-rest-output.php, so this plugin adds GET /wp-json/yuva/v1/footer which
 *     returns the formatted footer payload under a "footer" key (mirroring the site's
 *     convention of namespacing field data).
 * Version: 1.0.0
 * Author:  Yuva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The options bucket (post_id) footer values are stored under, and the menu slug that the
 * Footer Fields field group targets via its options_page location rule.
 */
const YUVA_FOOTER_POST_ID   = 'footer';
const YUVA_FOOTER_MENU_SLUG = 'footer-settings';

/**
 * 1) Register the "Footer" options page (guarded so the site never fatals if the options-page
 *    API is unavailable).
 */
add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_options_page(
			array(
				'page_title' => __( 'Footer', 'yuva' ),
				'menu_title' => __( 'Footer', 'yuva' ),
				'menu_slug'  => YUVA_FOOTER_MENU_SLUG,
				'capability' => 'manage_options',
				'post_id'    => YUVA_FOOTER_POST_ID,
				'position'   => false,
				'redirect'   => false,
			)
		);
	}
);

/**
 * 2) Expose the footer values to the headless frontend.
 *    GET /wp-json/yuva/v1/footer -> { "footer": { ...formatted fields... } }
 *    get_fields() applies SCF formatting (image -> array, repeaters -> nested arrays).
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'yuva/v1',
			'/footer',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$fields = function_exists( 'get_fields' )
						? get_fields( YUVA_FOOTER_POST_ID )
						: false;

					// Nothing saved yet -> return the empty field structure (keys with
					// blank/default values) so the headless frontend still gets the shape.
					// Only a fallback: once any value is saved, get_fields() is used as-is.
					if ( empty( $fields ) && function_exists( 'yuva_scf_options_skeleton' ) ) {
						$fields = yuva_scf_options_skeleton( 'group_footer_fields' );
					}

					return rest_ensure_response(
						array(
							'footer' => ! empty( $fields ) ? $fields : (object) array(),
						)
					);
				},
			)
		);
	}
);
