<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts\Helpers;

/**
 * For presenting the quick edit message that the post's language has been changed in the post
 * list table.
 */
class QuickEditPostListTable extends \WP_Posts_List_Table {

	public function display_rows( $posts = array(), $level = 0 ) : void {
		if ( count( $posts ) !== 1 ) {
			parent::display_rows( $posts, $level );
			return;
		}

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		// TODO: verify language change and quick edit again.

		// TODO: improve visuals.
		// TODO: Add link to post edit/view. Add dismiss row button. Add post title. Add link to change language.
		echo '<tr>';
		echo '<th></th><td> <div> Post moved due to language change </div> </td>';
		echo '</tr>';
	}
}
