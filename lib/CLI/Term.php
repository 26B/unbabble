<?php

namespace TwentySixB\WP\Plugin\Unbabble\CLI;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_CLI;

/**
 * TODO:
 *
 * @todo translations (__) in logs, warnings and errors.
 * @todo add --yes to all confirmations
 *
 * @since 0.0.5
 */
class Term extends Command {

	/**
	 * Unbabble's information about a term and its translations.
	 *
	 * ## OPTIONS
	 *
	 * <term_id>
	 * : ID of the term.
	 *
	 * @param array $args
	 * @return void
	 */
	public function info( array $args ) : void {
		$term_id = (int) $args[0];
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		if ( $term_id < 0 || ! get_term( $term_id ) ) {
			WP_CLI::error( "Term {$term_id} does not exist." );
		}

		// Warn if taxonomy is not translatable.
		$taxonomy = get_term( $term_id )->taxonomy;
		if ( ! LangInterface::is_taxonomy_translatable( $taxonomy ) ) {
			self::warning_color( "Taxonomy %B{$taxonomy}%N is not currently translatable." );
		}

		// Term information.
		$this->print_term_info( $term_id );

		// Translations information.
		$this->print_translations_info( $term_id );
	}

	/**
	 * Set a terms language.
	 *
	 * ## OPTIONS
	 *
	 * <term_id>
	 * : ID of the term.
	 *
	 * <language>
	 * : Code of the language to set.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function set( array $args ) : void {
		$term_id = (int) $args[0];
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		if ( $term_id < 0 || ! get_term( $term_id ) ) {
			WP_CLI::error( "Term {$term_id} does not exist." );
		}

		$target_language = $args[1];
		if ( ! in_array( $target_language, Options::get()['allowed_languages'] ) ) {
			$allowed_languages_str = implode( ', ', Options::get()['allowed_languages'] );
			WP_CLI::error( "Language {$target_language} is not currently allowed. Allowed languages are: {$allowed_languages_str}" );
		}

		$term_language = LangInterface::get_term_language( $term_id );
		if ( $term_language === $target_language ) {
			WP_CLI::error( "Term {$term_id} already has that language." );
		}

		$term_source = LangInterface::get_term_source( $term_id );
		if ( $term_source !== null ) {
			$source_terms = $this->get_terms_for_source( LangInterface::get_term_source( $term_id ), $term_id );
			foreach ( $source_terms as $source_term_id ) {
				if ( $target_language === LangInterface::get_term_language( $source_term_id ) ) {
					WP_CLI::error( "Term {$term_id} is linked to a translation of that language: {$source_term_id}." );
				}
			}
		}

		if ( $term_language !== null ) {
			self::confirm_color( "Term already has language %B{$term_language}%N. Do you want to continue?" );
		}

		$status = LangInterface::set_term_language( $term_id, $target_language, true );
		if ( ! $status ) {
			WP_CLI::error( "Failed to change language." );
		}

		WP_CLI::success( "Language changed to {$target_language}." );
	}

	/**
	 * Link a term to another term or a source ID .
	 *
	 * ## OPTIONS
	 *
	 * <term_id>
	 * : ID of the term to link.
	 *
	 * <target_id>
	 * : ID of the target term.
	 *
	 * [--force]
	 * : (Optional) Force linking change. By default, term is not changed if it's already linked.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function link( array $args, array $assoc_args ) : void {
		$term_id = (int) $args[0];
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		if ( $term_id < 0 || ! get_term( $term_id ) ) {
			WP_CLI::error( "Term {$term_id} does not exist." );
		}
		$force = $assoc_args['force'] ?? false;

		$target_id = $args[1];
		if ( ! get_term( $target_id ) ) {
			WP_CLI::error( "Target term {$target_id} does not exist." );
		}

		// Check is term_types are different.
		if ( get_term( $term_id )->taxonomy !== get_term( $target_id )->taxonomy ) {
			WP_CLI::error( "Terms have different term types." );
		}

		$term_source   = LangInterface::get_term_source( $term_id );
		$target_source = LangInterface::get_term_source( $target_id );

		if ( $term_source !== null && $term_source === $target_source ) {
			WP_CLI::error( 'Terms are already linked.' );
		}

		$term_already_linked = false;
		// If there is a source but no other term has it, don't stop the linking.
		if ( $term_source && ! empty( $this->get_terms_for_source( $term_source, $term_id ) ) ) {
			$term_already_linked = true;
		}

		$this->print_term_linked_to( $term_id, true );
		$this->print_term_linked_to( $target_id, true );

		if ( $term_already_linked && ! $force ) {
			WP_CLI::error( "Term {$term_id} is already linked. Use --force to force the change." );
		}

		if ( $this->has_language_conflicts( $term_id, $target_id ) ) {
			WP_CLI::error( "Term's {$term_id} language is already present in the target's translation group." );
		}

		if ( $target_source === null ) {
			$target_source = LangInterface::get_new_term_source_id();
			LangInterface::set_term_source( $target_id, $target_source, true );
		}

		if ( ! LangInterface::set_term_source( $term_id, $target_source, true ) ) {
			WP_CLI::error( "Failed to link term {$term_id} to term {$target_id}." );
		}

		WP_CLI::success( "Term {$term_id} linked to term {$target_id}." );
	}

	/**
	 * Unlink a term.
	 *
	 * ## OPTIONS
	 *
	 * <term_id>
	 * : ID of the term to unlink.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function unlink( array $args ) : void {
		$term_id = (int) $args[0];
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		if ( $term_id < 0 || ! get_term( $term_id ) ) {
			WP_CLI::error( "Term {$term_id} does not exist." );
		}
		$term_source = LangInterface::get_term_source( $term_id );
		if ( $term_source === null ) {
			WP_CLI::error( "Term {$term_id} does not have a source ID (not linked)." );
		}

		$this->print_term_linked_to( $term_id, true );

		if ( ! LangInterface::delete_term_source( $term_id ) ) {
			WP_CLI::error( "Failed to unlink term {$term_id}." );
		}

		WP_CLI::success( "Term {$term_id} unlinked." );
	}

	private function print_term_info( int $term_id ) : void {
		$lines = [
			'language' => [
				'%g' . __( 'Language' ) . ':%N',
				__( 'Term missing language.', 'unbabble' ),
			],
			'ubb_source' => [
				'%g' . __( 'Source ID', 'unbabble' ) . " (ubb_source)" . ':%N',
				__( 'No source ID.', 'unbabble' ),
			]
		];
		$language     = LangInterface::get_term_language( $term_id );
		$term_source  = LangInterface::get_term_source( $term_id );

		if ( ! empty( $language ) ) {
			$lang_info_str = $this->get_lang_info( $language );
			$lines['language'][1] = "{$language} {$lang_info_str}";
		}

		if ( ! empty( $term_source ) ) {
			$lines['ubb_source'][1] = $term_source;
		}

		self::log_color( '%4About term:%N' );

		$this->format_and_log( $lines, self::INDENT );
	}

	private function print_translations_info( $term_id ) : void {
		self::log_color( "\n%4Translations:%N" );

		$translations = LangInterface::get_term_translations( $term_id );
		if ( empty( $translations ) ) {
			// TODO: add function for single line.
			self::format_and_log( [ [ "Term has no translations." ] ], self::INDENT );
		} else {
			foreach ( $translations as $tr_id => $tr_lang ) {
				$this->print_translation_info( $tr_id, $tr_lang );
				WP_CLI::line();
			}
		}
	}

	private function print_translation_info( $term_id, $language ) : void {
		$tr_term = get_term( $term_id );
		$lines   = [
			'ID'       => [ 'ID', $term_id ],
			'language' => [
				__( 'Language' ),
				$language . ' ' . $this->get_lang_info( $language )
			],
			'name'    => [
				__( 'Name' ),
				$tr_term->name ]
		];
		$lines = array_map(
			function ( $line ) {
				$line[0] = "%g{$line[0]}:%N";
				return $line;
			},
			$lines
		);

		$this->format_and_log( $lines, self::INDENT );
	}

	private function print_term_linked_to( int $term_id, bool $hide_term = false ) : void {
		$no_links_message = "%4Term {$term_id} is not linked to other terms.%N";
		$term_source      = LangInterface::get_term_source( $term_id );
		if ( $term_source === null ) {
			self::log_color( $no_links_message );
			return;
		}

		$source_terms = $this->get_terms_for_source( $term_source, $hide_term ? $term_id : null );
		if ( empty( $source_terms ) ) {
			self::log_color( $no_links_message );
			return;
		}

		$lines = [];
		foreach ( $source_terms as $source_term_id ) {
			$lines[] = [
				$source_term_id,
				$this->get_lang_info( LangInterface::get_term_language( $source_term_id ) )
			];
		}

		if ( empty( $lines ) ) {
			self::log_color( $no_links_message );
			return;
		}

		$term_language = LangInterface::get_term_language( $term_id );
		self::log_color( "%4Term {$term_id} ({$term_language}) is currently linked to:%N" );
		self::format_and_log( $lines, self::INDENT );
	}

	private function get_terms_for_source( string $term_source, ?int $ignored_term_id = null ) {
		$source_terms = LangInterface::get_terms_for_source( $term_source );
		if ( $ignored_term_id === null ) {
			return $source_terms;
		}
		return array_filter( $source_terms, fn ( $source_term_id ) => $ignored_term_id !== (int) $source_term_id );
	}

	private function has_language_conflicts( int $term_A_id, int $term_B_id ) : bool {
		$term_A_source = LangInterface::get_term_source( $term_A_id );
		$term_B_source = LangInterface::get_term_source( $term_B_id );

		$A_sources = $term_A_source === null ? [ $term_A_id ] : LangInterface::get_terms_for_source( $term_A_source );
		$B_sources = $term_B_source === null ? [ $term_B_id ] : LangInterface::get_terms_for_source( $term_B_source );

		$A_languages = array_map( fn( $term_id ) => LangInterface::get_term_language( $term_id ), $A_sources );
		$B_languages = array_map( fn( $term_id ) => LangInterface::get_term_language( $term_id ), $B_sources );

		return ! empty( array_intersect( $A_languages, $B_languages ) );
	}
}
