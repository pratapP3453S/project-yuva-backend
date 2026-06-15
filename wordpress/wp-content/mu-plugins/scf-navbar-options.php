<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The options bucket (post_id) navbar values are stored under, and the menu slug that the
 * Navbar Fields field group targets via its options_page location rule.
 */
const YUVA_NAVBAR_POST_ID   = 'navbar';
const YUVA_NAVBAR_MENU_SLUG = 'navbar-settings';

/**
 * 1) Register the "Navbar" options page (guarded so the site never fatals if the options-page
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
				'page_title' => __( 'Navbar', 'yuva' ),
				'menu_title' => __( 'Navbar', 'yuva' ),
				'menu_slug'  => YUVA_NAVBAR_MENU_SLUG,
				'capability' => 'manage_options',
				'post_id'    => YUVA_NAVBAR_POST_ID,
				'position'   => false,
				'redirect'   => false,
			)
		);
	},
	9 // register before the Footer options page (default priority 10) so "Navbar" sits above it
);

/**
 * 2) Expose the navbar values to the headless frontend.
 *    GET /wp-json/yuva/v1/navbar -> { "navbar": { ...formatted fields... } }
 *    get_fields() applies SCF formatting (image -> array, repeaters -> nested arrays).
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'yuva/v1',
			'/navbar',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$fields = function_exists( 'get_fields' )
						? get_fields( YUVA_NAVBAR_POST_ID )
						: false;

					// Nothing saved yet -> return the empty field structure (keys with
					// blank/default values) so the headless frontend still gets the shape.
					// Only a fallback: once any value is saved, get_fields() is used as-is.
					if ( empty( $fields ) && function_exists( 'yuva_scf_options_skeleton' ) ) {
						$fields = yuva_scf_options_skeleton( 'group_navbar_fields' );
					}

					return rest_ensure_response(
						array(
							'navbar' => ! empty( $fields ) ? $fields : (object) array(),
						)
					);
				},
			)
		);
	}
);
