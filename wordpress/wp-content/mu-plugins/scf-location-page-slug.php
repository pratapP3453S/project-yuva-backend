<?php
/**
 * Plugin Name: SCF Location - Page Slug
 * Description: Adds a portable "Page Slug" field-group location rule so page field groups can
 *   target a page by its slug (e.g. "ai-coaching") instead of a numeric post ID. Post IDs are
 *   per-database (they differ between local and production, and change when a page is deleted and
 *   recreated), so an ID-based `page == 1396` rule silently stops matching the moment the ID drifts
 *   and the page's SCF fields vanish from get_fields()/REST. Slugs are identical across
 *   environments, so `page_slug == ai-coaching` is stable everywhere.
 * Notes for other developers:
 *   - This site runs Secure Custom Fields (SCF), the WordPress.org fork of ACF. SCF still fires the
 *     legacy acf/* hooks and uses ACF's class-based location system (acf_register_location_type +
 *     classes extending ACF_Location), so we register on `acf/include_location_rules`, the action
 *     SCF fires once ACF_Location is loaded (secure-custom-fields.php).
 *   - Use it in a field group's location array as:
 *       { "param": "page_slug", "operator": "==", "value": "ai-coaching" }
 *   - "!=" is supported too (matches every page except the given slug).
 * Version: 1.0.0
 * Author:  Yuva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'acf/include_location_rules',
	function () {
		if ( ! function_exists( 'acf_register_location_type' ) || ! class_exists( 'ACF_Location' ) ) {
			return;
		}

		if ( ! class_exists( 'Yuva_Location_Page_Slug' ) ) {
			class Yuva_Location_Page_Slug extends ACF_Location {

				public function initialize() {
					$this->name           = 'page_slug';
					$this->label          = __( 'Page Slug', 'yuva' );
					$this->category       = 'page';
					$this->object_type    = 'post';
					$this->object_subtype = 'page';
				}

				/**
				 * Match the rule's slug against the slug of the post currently being evaluated.
				 *
				 * @param array $rule        The location rule ({param, operator, value}).
				 * @param array $screen      The screen args; carries post_id (same key the core
				 *                            "post"/"page" rules read).
				 * @param array $field_group The field group settings (unused).
				 * @return boolean
				 */
				public function match( $rule, $screen, $field_group ) {
					if ( empty( $screen['post_id'] ) ) {
						return false;
					}
					$slug = get_post_field( 'post_name', $screen['post_id'] );
					if ( '' === $slug || null === $slug ) {
						return false;
					}
					return $this->compare_to_rule( $slug, $rule );
				}

				/**
				 * Populate the rule's value dropdown in the field-group editor with
				 * slug => page-title choices.
				 *
				 * @param array $rule A location rule.
				 * @return array
				 */
				public function get_values( $rule ) {
					$choices = array();
					if ( ! function_exists( 'acf_get_grouped_posts' ) ) {
						return $choices;
					}
					$groups = acf_get_grouped_posts( array( 'post_type' => array( 'page' ) ) );
					$posts  = reset( $groups );
					if ( $posts ) {
						foreach ( $posts as $post ) {
							if ( ! empty( $post->post_name ) ) {
								$choices[ $post->post_name ] = acf_get_post_title( $post );
							}
						}
					}
					return $choices;
				}
			}
		}

		acf_register_location_type( 'Yuva_Location_Page_Slug' );
	}
);
