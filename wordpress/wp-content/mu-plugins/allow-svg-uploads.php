<?php
/**
 * Plugin Name: Allow SVG Uploads (admin only)
 * Description: Lets the media library accept SVG (.svg/.svgz) in addition to the raster
 *   formats WordPress already allows (png, jpg, jpeg, gif, webp). Needed because WP core
 *   blocks SVG by default, so SCF image fields (e.g. the footer Logo and Social icons) can
 *   otherwise only take raster images. SVG can embed scripts, so to keep the attack surface
 *   small this is limited to users who can `manage_options` (the same capability that edits
 *   the Footer options page). Raster uploads for all other roles are unchanged.
 * Notes for other developers:
 *   - This only ENABLES the type at the WP level. Which formats a given SCF field offers is
 *     still controlled per-field by its `mime_types` setting in the field-group JSON.
 *   - SVGs are NOT sanitized here. If untrusted/non-admin users ever need SVG upload, add a
 *     sanitizer (e.g. enshrined/svg-sanitize) before widening the capability check below.
 * Version: 1.0.0
 * Author:  Yuva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) Add the SVG mime types to the allowed-uploads list, for admins only.
 */
add_filter(
	'upload_mimes',
	function ( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}
);

/**
 * 2) Correct WordPress' real-file MIME sniffing for SVG.
 *    WP (>= 4.7) verifies the actual file contents and can report an .svg as text/plain or
 *    text/html, which would reject the upload even when the mime is allowed. When the
 *    filename clearly ends in .svg/.svgz, restore the proper ext/type so the check passes.
 */
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename, $mimes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'svg' === $ext ) {
			$data['ext']             = 'svg';
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $data['proper_filename'] ?? $filename;
		} elseif ( 'svgz' === $ext ) {
			$data['ext']             = 'svgz';
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $data['proper_filename'] ?? $filename;
		}

		return $data;
	},
	10,
	4
);
