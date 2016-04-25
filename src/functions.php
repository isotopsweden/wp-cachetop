<?php

/**
 * Output fragment data with information so
 * it can be replaced when a page loads.
 *
 * @param  string       $id
 * @param  array|string $fn
 * @param  array        $args
 */
function cachetop_unfragment( $fn, array $args = [] ) {
	// Both `$id` and `$fn` should be a string.
	if ( ! is_string( $fn ) ) {
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
	echo sprintf( '<!-- cachetop: unfragment:%s -->', $data );
	echo call_user_func_array( $fn, $args );
	echo '<!-- cachetop: end -->';
}
