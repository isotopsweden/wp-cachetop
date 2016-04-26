<?php

use Cachetop\Cachetop;

/**
 * Get the current Cachetop class instance.
 *
 * @return \Cachetop\Cachetop
 */
function cachetop() {
	return Cachetop::instance();
}

/**
 * Flush cached html by hash.
 *
 * @return bool
 */
function cachetop_flush_hash( $hash ) {
	return cachetop()->flush_hash( $hash );
}

/**
 * Flush the current url or the given url if it exists.
 *
 * @return bool
 */
function cachetop_flush_url( $url = '' ) {
	return cachetop()->flush_url( $url );
}

/**
 * Output fragment data with information so
 * it can be replaced when a page loads.
 *
 * @param  string $fn
 * @param  array  $args
 * @param  bool   $arr_arg
 */
function cachetop_unfragment( $fn, array $args = [], $arr_arg = true ) {
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
		'fn'      => $fn,
		'args'    => $args,
		'arr_arg' => $arr_arg
	];
	$data = json_encode( $data );
	$data = base64_encode( $data );

	// Should it be passed as a array or not?
	// Default is true.
	$args = $arr_arg ? [$args] : $args;

	// Output cachetop comments and the function output.
	echo sprintf( '<!-- cachetop: unfragment:%s -->', $data );
	echo call_user_func_array( $fn, $args );
	echo '<!-- cachetop: end -->';
}
