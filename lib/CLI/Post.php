<?php

namespace TwentySixB\WP\Plugin\Unbabble\CLI;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_CLI;

/**
 * CLI commands for posts.
 *
 * @todo translations (__) in logs, warnings and errors.
 * @todo add --yes to all confirmations
 *
 * @since 0.0.6
 */
class Post extends Command {

	/**
	 * Unbabble's information about a post and its translations.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post.
	 *
	 * @since 0.0.6
	 *
	 * @param array $args
	 * @return void
	 */
	public function info( array $args ) : void {
		$post_id = (int) $args[0];
		add_filter( 'ubb_use_post_lang_filter', '__return_false' );
		if ( $post_id < 0 || ! get_post( $post_id ) ) {
			WP_CLI::error( "Post {$post_id} does not exist." );
		}

		// Warn if post type is not translatable.
		$post_type = get_post_type( $post_id );
		if ( ! LangInterface::is_post_type_translatable( $post_type ) ) {
			self::warning_color( "Post type %B{$post_type}%N is not currently translatable." );
		}

		// Post information.
		$this->print_post_info( $post_id );

		// Translations information.
		$this->print_translations_info( $post_id );
	}

	/**
	 * Set a posts language.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post.
	 *
	 * <language>
	 * : Code of the language to set.
	 *
	 * @since 0.0.6
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function set( array $args ) : void {
		$post_id = (int) $args[0];
		\add_filter( 'ubb_use_post_lang_filter', '__return_false' );
		if ( $post_id < 0 || ! \get_post( $post_id ) ) {
			WP_CLI::error( "Post {$post_id} does not exist." );
		}

		$target_language = $args[1];
		if ( ! LangInterface::is_language_allowed( $target_language ) ) {
			$allowed_languages_str = implode( ', ', LangInterface::get_languages() );
			WP_CLI::error( "Language {$target_language} is not currently allowed. Allowed languages are: {$allowed_languages_str}" );
		}

		$post_language = LangInterface::get_post_language( $post_id );
		if ( $post_language !== null && $post_language === $target_language ) {
			WP_CLI::error( "Post {$post_id} already has that language." );
		}

		$post_source = LangInterface::get_post_source( $post_id );
		if ( $post_source !== null ) {
			$source_posts = $this->get_posts_for_source( LangInterface::get_post_source( $post_id ), $post_id );
			foreach ( $source_posts as $source_post_id ) {
				if ( $target_language === LangInterface::get_post_language( $source_post_id ) ) {
					WP_CLI::error( "Post {$post_id} is linked to a translation of that language: {$source_post_id}." );
				}
			}
		}

		if ( $post_language !== null ) {
			self::confirm_color( "Post already has language %B{$post_language}%N. Do you want to continue?" );
		}

		$status = LangInterface::set_post_language( $post_id, $target_language, true );
		if ( ! $status ) {
			WP_CLI::error( "Failed to change language." );
		}

		WP_CLI::success( "Language changed to {$target_language}." );
	}

	/**
	 * Link a post to another post or a source ID .
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post to link.
	 *
	 * <target_id>
	 * : ID of the target post.
	 *
	 * [--force]
	 * : (Optional) Force linking change. By default, post is not changed if it's already linked.
	 *
	 * @since 0.0.6
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function link( array $args, array $assoc_args ) : void {
		$post_id = (int) $args[0];
		add_filter( 'ubb_use_post_lang_filter', '__return_false' );
		if ( $post_id < 0 || ! get_post( $post_id ) ) {
			WP_CLI::error( "Post {$post_id} does not exist." );
		}
		$force = $assoc_args['force'] ?? false;

		$target_id = $args[1];
		if ( ! get_post( $target_id ) ) {
			WP_CLI::error( "Target post {$target_id} does not exist." );
		}

		// Check is post_types are different.
		if ( get_post_type( $post_id ) !== get_post_type( $target_id ) ) {
			WP_CLI::error( "Posts have different post types." );
		}

		$post_source   = LangInterface::get_post_source( $post_id );
		$target_source = LangInterface::get_post_source( $target_id );

		if ( $post_source !== null && $post_source === $target_source ) {
			WP_CLI::error( 'Posts are already linked.' );
		}

		$post_already_linked = false;
		// If there is a source but no other post has it, don't stop the linking.
		if ( $post_source && ! empty( $this->get_posts_for_source( $post_source, $post_id ) ) ) {
			$post_already_linked = true;
		}

		$this->print_post_linked_to( $post_id, true );
		$this->print_post_linked_to( $target_id, true );

		if ( $post_already_linked && ! $force ) {
			WP_CLI::error( "Post {$post_id} is already linked. Use --force to force the change." );
		}

		if ( $this->has_language_conflicts( $post_id, $target_id ) ) {
			WP_CLI::error( "Post's {$post_id} language is already present in the target's translation group." );
		}

		if ( $target_source === null ) {
			$target_source = LangInterface::get_new_post_source_id();
			LangInterface::set_post_source( $target_id, $target_source, true );
		}

		if ( ! LangInterface::set_post_source( $post_id, $target_source, true ) ) {
			WP_CLI::error( "Failed to link post {$post_id} to post {$target_id}." );
		}

		WP_CLI::success( "Post {$post_id} linked to post {$target_id}." );
	}

	/**
	 * Unlink a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID of the post to unlink.
	 *
	 * @since 0.0.6
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function unlink( array $args ) : void {
		$post_id = (int) $args[0];
		add_filter( 'ubb_use_post_lang_filter', '__return_false' );
		if ( $post_id < 0 || ! get_post( $post_id ) ) {
			WP_CLI::error( "Post {$post_id} does not exist." );
		}
		$post_source = LangInterface::get_post_source( $post_id );
		if ( $post_source === null ) {
			WP_CLI::error( "Post {$post_id} does not have a source ID (not linked)." );
		}

		$this->print_post_linked_to( $post_id, true );

		$translations = LangInterface::get_post_translations( $post_id );

		if ( ! LangInterface::delete_post_source( $post_id ) ) {
			WP_CLI::error( "Failed to unlink post {$post_id}." );
		}

		// Clean up post source if there's a single other post for that source.
		if ( count( $translations ) === 1 ) {
			$other_post_id = array_keys( $translations )[0];
			LangInterface::delete_post_source( $other_post_id );
		}

		WP_CLI::success( "Post {$post_id} unlinked." );
	}

	/**
	 * Prints language information about the post.
	 *
	 * @since 0.0.6
	 *
	 * @param int $post_id
	 * @return void
	 */
	private function print_post_info( int $post_id ) : void {

		// Lines to output for the post.
		$lines = [
			'language' => [
				'%g' . __( 'Language' ) . ':%N',
				__( 'Post missing language.', 'unbabble' ),
			],
			'ubb_source' => [
				'%g' . __( 'Source ID', 'unbabble' ) . " (ubb_source)" . ':%N',
				__( 'No source ID.', 'unbabble' ),
			]
		];

		// Get language and post source.
		$language     = LangInterface::get_post_language( $post_id );
		$post_source  = LangInterface::get_post_source( $post_id );

		// Add language information to the output.
		if ( ! empty( $language ) ) {
			$lang_info_str = $this->get_lang_info( $language );
			$lines['language'][1] = "{$language} {$lang_info_str}";
		}

		// Add source information to the output.
		if ( ! empty( $post_source ) ) {
			$lines['ubb_source'][1] = $post_source;
		}

		// Print post information.
		self::log_color( '%4' . __( 'About post', 'unbabble' ) .':%N' );
		$this->format_lines_and_log( $lines, self::INDENT );
	}

	/**
	 * Prints information about a post's translations.
	 *
	 * @since 0.0.6
	 *
	 * @param int $post_id
	 * @return void
	 */
	private function print_translations_info( int $post_id ) : void {
		self::log_color( "\n%4Translations:%N" );

		// Get post's translations.
		$translations = LangInterface::get_post_translations( $post_id );

		// Print translations information.
		if ( empty( $translations ) ) {
			// TODO: add function for single line.
			self::format_lines_and_log( [ [ "Post has no translations." ] ], self::INDENT );

		} else {
			foreach ( $translations as $tr_id => $tr_lang ) {
				$this->print_translation_info( $tr_id, $tr_lang );
				WP_CLI::line();
			}
		}
	}

	/**
	 * Prints information about a single translation.
	 *
	 * @since 0.0.6
	 *
	 * @param int    $post_id
	 * @param string $language
	 * @return void
	 */
	private function print_translation_info( int $post_id, string $language ) : void {
		$tr_post = get_post( $post_id );

		// Lines to output for the post.
		$lines   = [
			'ID'       => [ 'ID', $post_id ],
			'language' => [
				__( 'Language' ),
				$language . ' ' . $this->get_lang_info( $language )
			],
			'title'    => [
				__( 'Title' ),
				$tr_post->post_title ]
		];

		// Add color to the output strings.
		$lines = array_map(
			function ( $line ) {
				$line[0] = "%g{$line[0]}:%N";
				return $line;
			},
			$lines
		);

		// Print post information.
		$this->format_lines_and_log( $lines, self::INDENT );
	}

	/**
	 * Prints information about other posts that a post is linked to.
	 *
	 * @since 0.0.6
	 *
	 * @param int  $post_id
	 * @param bool $hide_post
	 * @return void
	 */
	private function print_post_linked_to( int $post_id, bool $hide_post = false ) : void {
		$no_links_message = "%4Post {$post_id} is not linked to other posts.%N";

		// Check first if the post has a source and translations.

		$post_source      = LangInterface::get_post_source( $post_id );
		if ( $post_source === null ) {
			self::log_color( $no_links_message );
			return;
		}

		$source_posts = $this->get_posts_for_source( $post_source, $hide_post ? $post_id : null );
		if ( empty( $source_posts ) ) {
			self::log_color( $no_links_message );
			return;
		}

		// If the post has source/translations, get information about each of them.
		$lines = [];
		foreach ( $source_posts as $source_post_id ) {
			$lines[] = [
				$source_post_id,
				$this->get_lang_info( LangInterface::get_post_language( $source_post_id ) )
			];
		}

		if ( empty( $lines ) ) {
			self::log_color( $no_links_message );
			return;
		}

		// Print linked to information.
		$post_language = LangInterface::get_post_language( $post_id );
		self::log_color( "%4Post $post_id} ({$post_language}) is currently linked to:%N" );
		self::format_lines_and_log( $lines, self::INDENT );
	}

	/**
	 * Returns the posts for a specific source.
	 *
	 * @since 0.0.6
	 *
	 * @param string $post_source
	 * @param ?int   $ignored_post_id Whether to ignore a specific post_id.
	 * @return array
	 */
	private function get_posts_for_source( string $post_source, ?int $ignored_post_id = null ) : array {
		$source_posts = array_keys( LangInterface::get_posts_for_source( $post_source ) );
		if ( $ignored_post_id === null ) {
			return $source_posts;
		}
		return array_filter( $source_posts, fn ( $source_post_id ) => $ignored_post_id !== (int) $source_post_id );
	}

	/**
	 * Checks if there are language conflicts between two posts.
	 *
	 * @since 0.0.6
	 *
	 * @param int $post_A_id
	 * @param int $post_B_id
	 * @return bool
	 */
	private function has_language_conflicts( int $post_A_id, int $post_B_id ) : bool {
		$post_A_source = LangInterface::get_post_source( $post_A_id );
		$post_B_source = LangInterface::get_post_source( $post_B_id );

		$A_sources = $post_A_source === null ? [ $post_A_id ] : array_keys( LangInterface::get_posts_for_source( $post_A_source ) );
		$B_sources = $post_B_source === null ? [ $post_B_id ] : array_keys( LangInterface::get_posts_for_source( $post_B_source ) );

		$A_languages = array_map( fn( $post_id ) => LangInterface::get_post_language( $post_id ), $A_sources );
		$B_languages = array_map( fn( $post_id ) => LangInterface::get_post_language( $post_id ), $B_sources );

		return ! empty( array_intersect( $A_languages, $B_languages ) );
	}
}
