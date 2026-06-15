<?php
/**
 * Plugin Name: Yuva Config
 * Description: THE single config file for Yuva site-wide settings — the one place to edit.
 *   Other Yuva mu-plugins read these constants. Works on any host (Hostinger plain WP, cPanel,
 *   or local Docker) with NO environment variables: just edit the values below and deploy this
 *   file. The file name sorts before the feature plugins, so the constants exist before use.
 * Version: 1.0.0
 * Author:  Yuva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 *  >>>>>  EDIT HERE  <<<<<   — the only values you normally change.
 *
 *  To change in production (Hostinger): hPanel -> File Manager ->
 *  wp-content/mu-plugins/yuva-config.php, edit a value, Save. Done.
 *  (The `if ( ! defined(...) )` guard simply lets wp-config.php override a value
 *   if you ever want to; you never have to use that.)
 * ========================================================================= */

// Frontend origin(s) allowed for CORS. Comma-separated, NO trailing slash.
// NOTE: WordPress core already permits all origins for the REST API; this list
// only bites if a future feature makes CORS authoritative for a custom route.
if ( ! defined( 'YUVA_FRONTEND_ORIGINS' ) ) {
	define( 'YUVA_FRONTEND_ORIGINS', 'http://localhost:3000' );
}

/* ----------------------- end editable section --------------------------- */

if ( ! function_exists( 'yuva_allowed_origins' ) ) {
	/**
	 * YUVA_FRONTEND_ORIGINS parsed into a clean array of origins.
	 *
	 * @return string[]
	 */
	function yuva_allowed_origins() {
		return array_values( array_filter( array_map( 'trim', explode( ',', YUVA_FRONTEND_ORIGINS ) ) ) );
	}
}
