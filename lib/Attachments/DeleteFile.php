<?php

namespace TwentySixB\WP\Plugin\Unbabble\Attachments;

use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Class of hooks for keeping WordPress from deleting attachment files until it is relevant (i.e.
 * no other attachment in the translation map is using it).
 */
class DeleteFile {
	public function register() {
		if ( ! in_array( 'attachment', Options::get_allowed_post_types(), true ) ) {
			return;
		}

		/** When deleting an attachment, get list of its files (main and resizes) and set a filter
		 * for file deletion to check if the main file still exist somewhere else. If it does, don't
		 * delete it anywhere.
		 */
		\add_action( 'delete_attachment', [ $this, 'delete_attachment' ] );
	}

	public function delete_attachment( int $post_id ) : void {
		$meta = wp_get_attachment_metadata( $post_id );
		$file = $meta['file'];
		$sizes = $meta['sizes'];

		$files = [ $file ];

		$parts = explode( '/', $file );
		array_pop( $parts );
		$file_dir = trailingslashit( implode( '/', $parts ) );
		foreach ( $sizes as $size ) {
			$files[] = $file_dir . $size['file'];
		}

		$self = $this;
		\add_filter(
			'wp_delete_file',
			function ( string $file ) use ( $self, $files ) {
				if ( in_array( $this->get_filename( $file ), $files, true ) ) {
					$count = $self->get_file_usage_count( $files[0] );
					if ( ! empty( $count ) ) {
						return null;
					}
				}
				return $file;
			},
			5
		);
	}

	private function get_filename( string $file_path ) : string {
		$upload_dir = wp_upload_dir();
		return str_replace( trailingslashit( $upload_dir['basedir'] ), '', $file_path );
	}

	private function get_file_usage_count( string $filename ) : int {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' and meta_value = %s",
				$filename
			)
		);
	}
}
