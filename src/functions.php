<?php

/**
 * Output cachetop fragment cache.
 * It will be updated on every page reload.
 *
 * @param  string $id
 * @param  string $fn
 * @param  array  $args
 */
function cachetop_fragment( $id, $fn, array $args = [] ) {
	if ( ! is_string( $id ) || ! is_string( $fn ) ) {
		return;
	}

	// Only supports functions right now.
	if ( ! function_exists( $fn ) ) {
		return;
	}

	// Prepare data for JSON and base64 encoding.
	$data = [
		'fn'   => $fn,
		'args' => $args
	];
	$data = json_encode( $data );
	$data = base64_encode( $data );

	// Output cachetop comments and the function output.
	echo sprintf( '<!-- cachetop: fragment:%s -->', $id . ':' . $data );
	echo call_user_func_array( $fn, $args );
	echo '<!-- cachetop: end -->';
}
