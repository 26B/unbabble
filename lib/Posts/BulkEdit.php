<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to bulk actions for posts.
 *
 * @since 0.3.2
 */
class BulkEdit {

	/**
	 * Register hooks.
	 *
	 * @since 0.3.2
	 *
	 * @return void
	 */
	public function register() {

		// Add language column.
		\add_action( 'init', [ $this, 'add_custom_columns' ], 99 );

		// Add bulk edit custom box. Bulk edit uses `save_post` here due to using $_GET and not $_POST.
		\add_action( 'bulk_edit_custom_box', [ $this, 'bulk_edit_custom_box' ], 10, 2 );
		\add_action( 'save_post', [ $this, 'save_bulk_edit' ], PHP_INT_MAX - 10 );
		\add_action( 'bulk_post_updated_messages', [ $this, 'bulk_edit_messages' ], 10, 2 );
		\add_action( 'wp_redirect', [ $this, 'bulk_edit_redirect' ] );
	}

	/**
	 * Adds custom columns.
	 *
	 * @since 0.3.2
	 *
	 * @return void
	 */
	public function add_custom_columns() : void {
		$post_types = LangInterface::get_translatable_post_types();

		// For each post type, add the bulk edit functionality and the columns.
		foreach ( $post_types as $post_type ) {
			add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_admin_columns' ) );
			add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'populate_custom_columns' ), 10, 2 );
		}
	}

	/**
	 * Adds admin columns.
	 *
	 * @since 0.3.2
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_admin_columns( array $columns ) : array {
		$columns['ubb_lang'] = __( 'Unbabble language', 'unbabble' );
		return $columns;
	}

	/**
	 * Populates custom columns.
	 *
	 * @since 0.3.2
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function populate_custom_columns( string $column, int $post_id ) : void {

		// Make sure we're in the right column.
		if ( 'ubb_lang' !== $column ) {
			return;
		}

		// Get the language and display it.
		$language = LangInterface::get_post_language( $post_id );
		$language_info = Options::get_languages_info();
		$native_name = $language_info[ $language ]['native_name'] ?? '';
		if ( empty( $native_name ) ) {
			$native_name = __( 'Unknown', 'unbabble' );
		}

		echo esc_html( $native_name );
	}

	/**
	 * Adds bulk edit custom box.
	 *
	 * @since 0.3.2
	 *
	 * @param string $column_name Column name.
	 * @param string $post_type   Post type.
	 * @return void
	 */
	public function bulk_edit_custom_box( string $column_name, string $post_type ) : void {

		// Make sure we're in the right column.
		if ( $column_name !== 'ubb_lang' ) {
			return;
		}

		// Make sure we're editing translatable posts.
		$post_types = LangInterface::get_translatable_post_types();
		if ( ! in_array( $post_type, $post_types, true ) ) {
			return;
		}

		// Get languages info and current language.
		$languages_info   = Options::get_languages_info();
		$languages        = LangInterface::get_languages();
		$current_language = LangInterface::get_current_language();

		// TODO: Deal with no languages, etc
		?>
		<fieldset class="inline-edit-col-right">
			<span class="title">
				<span class="dashicons dashicons-translation"></span>
				Language
			</span>
			<div class="inline-edit-col">
				<div class="inline-edit-group wp-clearfix">
					<label class="inline-edit-status alignleft">
						<span class="title">Language</span>
						<select name="ubb_lang">
							<?php foreach ( $languages as $lang ): ?>
								<?php
								$label = $languages_info[$lang]['native_name'] ?? null;
								$selected = $lang === $current_language;
								?>

								<?php if ( ! empty( $label ) ) : ?>
									<option
										value="<?php echo $lang ?>"
										<?php echo $selected ? 'selected' : '' ?>
									>
										<?php echo sprintf( '%s (%s)', $label, $lang ); ?>
									</option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Saves the bulk edit.
	 *
	 * @since 0.3.2
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_bulk_edit( int $post_id ) : void {

		// Make sure we're in a bulk edit for posts.
		if ( ( $_GET['bulk_edit'] ?? '' ) !== 'Update' || ( $_GET['action'] ?? '' ) !== 'edit' ) {
			return;
		}

		// Make sure post being update is the one in the request.
		if ( empty( $_GET['post'] ?? [] ) || ! in_array( $post_id, $_GET['post'], false ) ) {
			return;
		}

		// Get language from request and make sure it's valid.
		$language = $_GET['ubb_lang'] ?? '';
		if (
			empty( $language )
			|| ! LangInterface::is_language_allowed( $language )
			|| $language === LangInterface::get_current_language()
		) {
			return;
		}

		// Make sure we're editing translatable posts.
		$post_types = LangInterface::get_translatable_post_types();
		if ( ! in_array( ( $_GET['post_type'] ?? '' ), $post_types, true ) ) {
			return;
		}

		// Make sure post ID is valid.
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		// Change the language.
		$success = LangInterface::change_post_language( $post_id, $language );

		// If failed, increment the fail count to show in update message.
		if ( ! $success ) {
			add_filter( 'ubb_bulk_edit_fail_count', fn ( $count ) => $count + 1 );
		}
	}

	/**
	 * Adds a message to the bulk edit update message.
	 *
	 * @since 0.3.2
	 *
	 * @param array $bulk_messages Bulk messages.
	 * @param array $bulk_counts   Bulk counts.
	 * @return array
	 */
	public function bulk_edit_messages( array $bulk_messages, array $bulk_counts ) : array {

		// Get current post type.
		$current_post_type = $_GET['post_type'] ?? '';
		if ( empty( $current_post_type ) ) {
			return $bulk_messages;
		}

		// Check the bulk failed count is set.
		if ( ! isset( $_GET['ubb_bulk_failed'] ) ) {
			return $bulk_messages;
		}

		// Get the failed count.
		$failed = $_GET['ubb_bulk_failed'];

		// Remove failed count from request uri.
		$_SERVER['REQUEST_URI'] = \remove_query_arg( 'ubb_bulk_failed', $_SERVER['REQUEST_URI'] );

		// If no failed, return the bulk messages.
		if ( empty( $failed ) ) {
			return $bulk_messages;
		}

		// Get the post type to attach the failed message to.
		$post_type = $current_post_type;
		if ( ! isset( $bulk_messages[ $current_post_type ] ) ) {
			$post_type = 'post';
		}

		// Attach language failure string to the bulk update message.
		$bulk_messages[ $post_type ]['updated'] .= sprintf(
			_n( ' %d post failed to update language.', ' %d posts failed to update language.', $failed, 'unbabble' ),
			$failed,
			$current_post_type
		);

		return $bulk_messages;
	}

	/**
	 * Add language update fail count to bulk edit redirect.
	 *
	 * @since 0.3.2
	 *
	 * @param string $location
	 * @return string
	 */
	public function bulk_edit_redirect( string $location ) : string {

		// Make sure we're in a bulk edit for posts.
		if ( ( $_REQUEST['bulk_edit'] ?? '' ) !== 'Update' || ( $_REQUEST['action'] ?? '' ) !== 'edit' ) {
			return $location;
		}

		// Make sure post being update is the one in the request.
		$failed = apply_filters( 'ubb_bulk_edit_fail_count', 0 );
		return \add_query_arg( 'ubb_bulk_failed', $failed, $location );
	}
}
