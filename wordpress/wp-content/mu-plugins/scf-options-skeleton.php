<?php
/**
 * Plugin Name: SCF Options Skeleton
 * Description: Builds an "empty field structure" (every field name present, with blank/default
 *   values) from a Secure Custom Fields field group. Used by the navbar/footer REST routes so a
 *   brand-new options page (nothing saved yet) returns the field SHAPE for the headless frontend
 *   instead of an empty {}.
 * Notes for other developers:
 *   - This is ONLY a fallback. The REST callbacks still return get_fields() verbatim whenever any
 *     value has been saved; the skeleton is used solely when get_fields() is empty. So saved data
 *     is never altered and existing behaviour is unchanged.
 *   - Shapes mirror what get_fields() would return when empty: text/url/etc -> default or "",
 *     group -> nested object, repeater/flexible/gallery -> [], image/file -> false.
 * Version: 1.0.0
 * Author:  Yuva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the empty skeleton for a whole field group, by its key.
 *
 * @param string $group_key e.g. "group_navbar_fields" / "group_footer_fields".
 * @return array name => blank/default value (empty array if the group/SCF is unavailable).
 */
if ( ! function_exists( 'yuva_scf_options_skeleton' ) ) {
	function yuva_scf_options_skeleton( $group_key ) {
		if ( ! function_exists( 'acf_get_field_group' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}
		$group = acf_get_field_group( $group_key );
		if ( ! $group ) {
			return array();
		}
		return yuva_scf_field_skeleton( acf_get_fields( $group ) );
	}
}

/**
 * Recursively turn an SCF fields array into a name => blank/default-value map.
 *
 * @param array $fields SCF field definitions (from acf_get_fields() / sub_fields).
 * @return array
 */
if ( ! function_exists( 'yuva_scf_field_skeleton' ) ) {
	function yuva_scf_field_skeleton( $fields ) {
		$out = array();
		if ( ! is_array( $fields ) ) {
			return $out;
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) ) {
				continue;
			}
			$type = $field['type'];
			$name = isset( $field['name'] ) ? $field['name'] : '';

			// Clone fields: a seamless, non-prefixed clone inlines the cloned fields at this
			// level (matching get_fields() output); otherwise it nests under its own name.
			if ( 'clone' === $type ) {
				$skel     = yuva_scf_field_skeleton( yuva_scf_resolve_clone_fields( $field ) );
				$seamless = ( isset( $field['display'] ) && 'seamless' === $field['display'] ) && empty( $field['prefix_name'] );
				if ( $seamless ) {
					$out = array_merge( $out, $skel );
				} elseif ( '' !== $name ) {
					$out[ $name ] = $skel;
				}
				continue;
			}

			if ( '' === $name ) {
				continue;
			}

			switch ( $type ) {
				case 'group':
					$out[ $name ] = yuva_scf_field_skeleton(
						isset( $field['sub_fields'] ) ? $field['sub_fields'] : array()
					);
					break;

				// Lists are empty until rows are added.
				case 'repeater':
				case 'flexible_content':
				case 'gallery':
				case 'checkbox':
					$out[ $name ] = array();
					break;

				// Media / object references are false when empty (same as get_fields()).
				case 'image':
				case 'file':
					$out[ $name ] = false;
					break;

				case 'true_false':
					$out[ $name ] = (bool) ( isset( $field['default_value'] ) ? $field['default_value'] : false );
					break;

				default:
					$out[ $name ] = ( isset( $field['default_value'] ) && '' !== $field['default_value'] )
						? $field['default_value']
						: '';
			}
		}

		return $out;
	}
}

/**
 * Resolve a clone field's referenced fields (field keys or whole group keys) into a flat
 * array of field definitions.
 *
 * @param array $field A clone-type field definition.
 * @return array
 */
if ( ! function_exists( 'yuva_scf_resolve_clone_fields' ) ) {
	function yuva_scf_resolve_clone_fields( $field ) {
		$resolved = array();
		if ( empty( $field['clone'] ) || ! is_array( $field['clone'] ) ) {
			return $resolved;
		}
		foreach ( $field['clone'] as $ref ) {
			if ( function_exists( 'acf_is_field_group_key' ) && acf_is_field_group_key( $ref ) ) {
				$grp = function_exists( 'acf_get_field_group' ) ? acf_get_field_group( $ref ) : false;
				if ( $grp && function_exists( 'acf_get_fields' ) ) {
					$resolved = array_merge( $resolved, acf_get_fields( $grp ) );
				}
			} elseif ( function_exists( 'acf_get_field' ) ) {
				$f = acf_get_field( $ref );
				if ( $f ) {
					$resolved[] = $f;
				}
			}
		}
		return $resolved;
	}
}
