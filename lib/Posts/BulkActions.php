<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to bulk actions for posts.
 *
 * TODO: Name of this class should be different if quick edit is here.
 *
 * @since 0.3.2
 */
class BulkActions {

	/**
	 * Register hooks.
	 *
	 * @since 0.3.2
	 */
	public function register() {

		// Add language column.
		\add_action( 'init', [ $this, 'add_custom_columns' ], 99 );

		// Hide language column. Column is necessary for quick and bulk edit.
		\add_filter( 'hidden_columns', [ $this, 'hide_language_column' ], 99, 3 );

		// Add quick edit custom box. Quick edit uses `save_post` in lib/Posts/ChangeLanguage.php.
		\add_action( 'quick_edit_custom_box', [ $this, 'quick_edit_custom_box' ], 10, 3 );
		\add_action( 'add_inline_data', [ $this, 'quick_edit_add_inline_data' ], 10, 2 );
		\add_action( 'admin_enqueue_scripts', [ $this, 'quick_edit_scripts' ], 10, 1 );

		// Add bulk edit custom box. Bulk edit uses `save_post` here due to using $_GET and not $_POST.
		\add_action( 'bulk_edit_custom_box', [ $this, 'bulk_edit_custom_box' ], 10, 2 );
		\add_action( 'save_post', [ $this, 'save_bulk_edit' ], PHP_INT_MAX - 10 );
		\add_action( 'bulk_post_updated_messages', [ $this, 'bulk_edit_messages' ], 10, 2 );
		\add_action( 'wp_redirect', [ $this, 'bulk_edit_redirect' ] );
	}

	public function quick_edit_custom_box( string $column_name, string $post_type, string $taxonomy ) : void {
		if ( $column_name !== 'ubb_lang' ) {
			return;
		}

		$post_types = LangInterface::get_translatable_post_types();
		if ( ! in_array( $post_type, $post_types, true ) ) {
			return;
		}

		$languages_info     = Options::get_languages_info();
		$languages          = LangInterface::get_languages();
		$post_language      = LangInterface::get_post_language( \get_the_ID() );
		$disabled_languages = array_values( LangInterface::get_post_translations( \get_the_ID() ) );

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
									$disabled = in_array( $lang, $disabled_languages, true );
									$selected = $lang === $post_language;
									?>

									<?php if ( ! empty( $label ) ) : ?>
										<option value="<?php echo $lang ?>" >
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

	public function quick_edit_add_inline_data( \WP_Post $post, \WP_Post_Type $post_type_object ) : void {
		$post_language      = LangInterface::get_post_language( $post->ID );
		$disabled_languages = array_values( LangInterface::get_post_translations( $post->ID ) );

		echo '<div class="ubb_post_lang">' . $post_language . '</div>';
		echo '<div class="ubb_post_translations">' . implode( ',', $disabled_languages ) . '</div>';
	}

	public function quick_edit_scripts( string $hook_suffix ) : void {
		$post_types = LangInterface::get_translatable_post_types();
		if ( $hook_suffix == 'edit.php' && in_array( \get_post_type(), $post_types ) ) {
			$language_in_use_str = __( 'used by translation', 'unbabble' );
			wp_add_inline_script(
				'inline-edit-post',
				'(function($) {
					$(".ptitle").on("focus",function(e){
						let id = parseInt($(e.target).closest(".quick-edit-row").attr("id").replace("edit-","")),
							post_lang = $("#inline_"+id+" .ubb_post_lang").text(),
							translations = $("#inline_"+id+" .ubb_post_translations").text().split(",");

						// Select the language of the post.
						$("#edit-"+id+".quick-edit-row select[name=ubb_lang] option[value=" + post_lang + "]").prop("selected", true);

						// Disable languages used by translations.
						if (translations.length !== 0) {
							$("#edit-"+id+".quick-edit-row select[name=ubb_lang] option").each(function() {
								if (translations.includes($(this).val())) {
									$(this).prop("disabled", true);
									$(this).append(" (' . $language_in_use_str . ')");
								}
							});
						}
					});
				})(jQuery);'
			);
		}
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
		if ( $column_name !== 'ubb_lang' ) {
			return;
		}

		$post_types = LangInterface::get_translatable_post_types();
		if ( ! in_array( $post_type, $post_types, true ) ) {
			return;
		}

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
			// TODO: shouldn't this be edit-post?
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
		if ( 'ubb_lang' !== $column ) {
			return;
		}

		$language = LangInterface::get_post_language( $post_id );
		echo esc_html( $language );
	}

	public function hide_language_column( array $hidden, \WP_Screen $screen, bool $use_defaults ) : array {
		$hidden[] = 'ubb_lang';
		return $hidden;
	}

	public function remove_language_column( array $columns ) : array {
		unset( $columns['ubb_lang'] );
		return $columns;
	}

	public function save_bulk_edit( int $post_id ) : void {

		// Make sure we're in a bulk edit for posts.
		if ( ( $_GET['bulk_edit'] ?? '' ) !== 'Update' || ( $_GET['action'] ?? '' ) !== 'edit' ) {
			return;
		}

		// Make sure post being update is the one in the request.
		if ( empty( $_GET['post'] ?? [] ) || ! in_array( $post_id, $_GET['post'], false ) ) {
			return;
		}

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

		// TODO: notice about which ones were not updated.
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		$success = LangInterface::change_post_language( $post_id, $language );
		if ( ! $success ) {
			add_filter( 'ubb_bulk_edit_fail_count', fn ( $count ) => $count + 1 );
		}
	}

	public function bulk_edit_messages( array $bulk_messages, array $bulk_counts ) : array {
		$current_post_type = $_GET['post_type'] ?? '';
		if ( empty( $current_post_type ) ) {
			return $bulk_messages;
		}

		if ( ! isset( $_GET['ubb_bulk_failed'] ) ) {
			return $bulk_messages;
		}

		$failed = $_GET['ubb_bulk_failed'];

		// Remove from request uri.
		$_SERVER['REQUEST_URI'] = \remove_query_arg( 'ubb_bulk_failed', $_SERVER['REQUEST_URI'] );

		if ( empty( $failed ) ) {
			return $bulk_messages;
		}

		$post_type = $current_post_type;
		if ( ! isset( $bulk_messages[ $current_post_type ] ) ) {
			$post_type = 'post';
		}

		// Attach language failure string to the bulk update message.
		$bulk_messages[ $post_type ]['updated'] .= sprintf(
			_n( " %d post failed to update language.", ' %d posts failed to update language.', $failed ),
			$failed,
			$current_post_type
		);

		return $bulk_messages;
	}

	public function bulk_edit_redirect( string $location ) : string {

		// Make sure we're in a bulk edit for posts.
		if ( ( $_REQUEST['bulk_edit'] ?? '' ) !== 'Update' || ( $_REQUEST['action'] ?? '' ) !== 'edit' ) {
			return $location;
		}

		$failed = apply_filters( 'ubb_bulk_edit_fail_count', 0 );
		return \add_query_arg( 'ubb_bulk_failed', $failed, $location );
	}
}
