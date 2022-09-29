<?php

function ubb_get_post_meta( int $post_id, string $meta_key, string $lang = '' ) {
	$fn   = fn() => __return_true();
	add_filter( 'ubb_get_post_meta_prevent', $fn );
	$meta = get_post_meta( $post_id, empty( $lang ) ? $meta_key : "{$meta_key}_ubb_{$lang}", true );
	remove_filter( 'ubb_get_post_meta_prevent', $fn );
	return $meta;
}
