<?php

namespace Bluehost\Maestro;

// Hooks for the user list table
add_filter( 'manage_users_columns', __NAMESPACE__ . '\\add_maestro_column' );
add_action( 'manage_users_custom_column', __NAMESPACE__ . '\\maestro_column_details', 10, 3 );

/**
 * Adds a column for indicating Maestro status
 *
 * @since 1.0
 *
 * @return array The columns
 */
function add_maestro_column( $columns ) {

	$old_columns = $columns;
	// Check the last value.
	// We only want to move ahead of the Posts column if it's visible.
	$last = array_pop( $columns );
	if ( 'Posts' === $last ) {
		$columns['maestro'] = 'Maestro';
		$columns['posts']   = 'Posts';
		return $columns;
	} else {
		$old_columns['maestro'] = 'Maestro';
		return $old_columns;
	}
}

function maestro_column_details( $value, $column_name, $user_id ) {
	if ( 'maestro' === $column_name && is_user_maestro( $user_id ) ) {
		$value = '<img style="max-width: 80%;" src="' . BH_MAESTRO_URL . '/assets/images/bh-maestro-logo.svg" />';
		$value .= '<div class="row-actions"><a href="">Revoke Access</a></div>';
	}
	return $value;
}
