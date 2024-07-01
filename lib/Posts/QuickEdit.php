<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use TwentySixB\WP\Plugin\Unbabble\Posts\Helpers\QuickEditPostListTable;

/**
 * For hooks related to quick edit for posts.
 *
 * @since 0.3.2
 */
class QuickEdit {

	/**
	 * Register hooks.
	 *
	 * @since 0.3.2
	 */
	public function register() {

		// Language column added by BulkEdit.php

		// Add quick edit custom box. Quick edit uses `save_post` in lib/Posts/ChangeLanguage.php.
		\add_action( 'quick_edit_custom_box', [ $this, 'quick_edit_custom_box' ], 10, 3 );
		\add_action( 'add_inline_data', [ $this, 'quick_edit_add_inline_data' ], 10, 2 );
		\add_action( 'admin_enqueue_scripts', [ $this, 'quick_edit_scripts' ], 10, 1 );

		// TODO: Find a better way of displaying a message that the post language was changed.
		\add_filter( 'wp_list_table_class_name', [ $this, 'quick_edit_table_class'], 10, 2 );
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
								if (translations.includes($(this).val()) && ! $(this).prop("selected") ) {
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

	public function quick_edit_table_class( string $class_name, array $args ) : string {
		if ( $class_name !== 'WP_Posts_List_Table' ) {
			return $class_name;
		}

		if ( ! isset( $args['screen'] ) || ! $args['screen'] instanceof \WP_Screen || $args['screen']->id !== 'edit-post' ) {
			return $class_name;
		}

		if ( ( $_REQUEST['action'] ?? '' ) !== 'inline-save' ) {
			return $class_name;
		}

		// TODO: extra verifications to make sure we're in a language edit.
		// TODO: check if language was changed. (post's language is different than the current language)

		return QuickEditPostListTable::class;
	}
}
