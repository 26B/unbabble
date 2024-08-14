<?php

namespace TwentySixB\WP\Plugin\Unbabble\Overrides;

/**
 * This override class is necessary for keeping the filter in the URL when searching for terms
 * without language.
 *
 * The search box in edit-tags.php, made by class-wp-list-table.php, does not have filter with
 * which to add extra hidden inputs for the search form, so we can keep the language filter on.
 *
 * One way around this is through an extension of the WP_Terms_List_Table class, and used via
 * the wp_list_table_class_name (in Terms/EditFilters). This extension defines a search_box
 * override method that inserts the language filter if needed, but otherwise keeps the default
 * behaviour of the method.
 *
 * @since Unreleased
 */
class WP_Terms_List_Table_Override extends \WP_Terms_List_Table {

	/**
	 * Adds the hidden "No Language" filter input and displays the search box.
	 *
	 * @since Unreleased
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		if ( isset( $_REQUEST['ubb_empty_lang_filter'] ) ) {
			echo '<input type="hidden" name="ubb_empty_lang_filter" value="" />';
		}

		parent::search_box( $text, $input_id );
	}
}
