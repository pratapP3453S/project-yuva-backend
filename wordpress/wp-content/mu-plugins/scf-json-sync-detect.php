<?php
/**
 * Plugin Name: SCF Local JSON Sync Detection (filemtime)
 * Description: Makes the SCF "Sync" link in SCF -> Field Groups appear reliably whenever a scf-json/ file actually changes, by using the file's real filesystem modification time instead of the hand-written "modified" number inside the JSON. Detection only — nothing is synced automatically; you still click "Sync" yourself.
 * Version:     1.0.0
 * Author:      Yuva
 *
 * THE PROBLEM THIS SOLVES:
 *   SCF decides a field group "has changes to sync" by comparing the top-level
 *   "modified" timestamp written INSIDE the .json file against the database
 *   copy's modified time (see ACF_Admin_Internal_Post_Type_List::setup_sync()).
 *   When you hand-edit a scf-json file (or pull one) but forget to bump that
 *   "modified" number, SCF thinks nothing changed and never shows the Sync link.
 *   That is the recurring "I edited the JSON but can't sync" problem.
 *
 * THE FIX:
 *   SCF merges the local-JSON "modified" onto each field group through the
 *   `acf/load_field_groups` filter (_acf_apply_get_local_internal_posts(),
 *   priority 20). We run AFTER it (priority 99) and replace "modified" with the
 *   .json file's actual filesystem mtime. Now ANY real edit or git pull bumps
 *   the file's mtime, so the comparison detects it and the Sync link appears.
 *   You then click Sync manually — this plugin never imports anything itself.
 *
 * WHY THIS IS SAFE:
 *   - "modified" is only consumed by SCF's sync-availability check. No other
 *     code path (rendering fields, get_field, the REST API) uses it, so changing
 *     it has no side effects beyond making detection reliable.
 *   - Admin-only (is_admin guard): the headless REST endpoint never pays the
 *     filemtime cost, and sync detection only runs in wp-admin anyway.
 *   - Detection only. Clicking "Sync" still runs SCF's normal, unchanged import.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'acf/load_field_groups',
	function ( $field_groups ) {
		// Sync detection only happens in wp-admin; skip everywhere else so the
		// front end / REST API never pays for the filesystem stat calls.
		if ( ! is_admin() || ! is_array( $field_groups ) ) {
			return $field_groups;
		}

		foreach ( $field_groups as &$group ) {
			if (
				! is_array( $group )
				|| empty( $group['local'] ) || 'json' !== $group['local']
				|| empty( $group['local_file'] ) || ! is_string( $group['local_file'] )
			) {
				continue;
			}

			if ( ! is_readable( $group['local_file'] ) ) {
				continue;
			}

			$mtime = filemtime( $group['local_file'] );
			if ( $mtime ) {
				// Use the file's real modification time as the sync yardstick,
				// regardless of the "modified" value written inside the JSON.
				$group['modified'] = $mtime;
			}
		}
		unset( $group );

		return $field_groups;
	},
	99
);
