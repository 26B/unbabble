<?php

namespace TwentySixB\WP\Plugin\Unbabble\CLI;

use cli\Colors;
use cli\Shell;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_CLI;
use WP_CLI_Command;
use DIQA\Formatter;

/**
 * Base class for CLI commands.
 *
 * @since 0.0.5
 */
abstract class Command extends WP_CLI_Command {

	protected const INDENT                   = 4;
	private   const FORMAT_IGNORED_SEQUENCES = [ '%y', '%g', '%b', '%r', '%p', '%m', '%c', '%w', '%k', '%n', '%Y', '%G', '%B', '%R', '%P', '%M', '%C', '%W', '%K', '%N', '%3', '%2', '%4', '%1', '%5', '%6', '%7', '%0', '%F', '%U', '%8', '%9', '%_' ];

	protected function columns() : int {
		return Shell::columns();
	}

	protected static function log_color( string $string  ) : void {
		WP_CLI::log( WP_CLI::colorize( $string ) );
	}

	protected static function warning_color( string $string  ) : void {
		WP_CLI::warning( WP_CLI::colorize( $string ) );
	}

	protected static function confirm_color( string $string  ) : void {
		WP_CLI::confirm( WP_CLI::colorize( $string ) );
	}

	protected function format_and_log( $lines, int $indent = 0 ) : void {

		// Remove keys for formatter.
		$lines = array_values( $lines );

		// Add indent as a first column.
		if ( $indent > 0 ) {
			$lines = array_map( function ( $line ) use ( $indent ) {
				/**
				 * Subtract 3 to account for border padding for the first column (left and right)
				 * and left padding on the second column.
				 */
				array_unshift( $line, str_repeat( ' ', $indent - 3 ) );
				return $line;
			}, $lines );
		}

		// Get column sizes depending on the content.
		$max_columns  = $this->columns();
		$n_columns    = count( current( $lines ) );
		$column_sizes = array_fill( 0, $n_columns, 0 );
		foreach ( $lines as $line ) {
			$i = 0;
			foreach ( $line as $value ) {
				$strlen = mb_strlen( Colors::decolorize( $value ) ) + 2; // Padding of 2 to account for border.
				if ( $strlen > $column_sizes[ $i ] ) {
					$column_sizes[ $i ] = $strlen;
				}
				$i++;
			}
		}

		// Check if last column doesn't go over the limit.
		// TODO: Needs a better method to reduce all the columns when there isn't enough space.
		$diff_columns = array_sum( $column_sizes ) - $max_columns;
		if ( $diff_columns > 0 ) {
			$column_sizes[ $n_columns - 1 ] -= $diff_columns;
		}

		// Format and log lines.
		$config = new Formatter\Config( $column_sizes, null, [ 'borderPadding' => true ] );
		$config->setSequencesToIgnore( self::FORMAT_IGNORED_SEQUENCES );
		$formatter = new Formatter\Formatter($config);
		self::log_color( $formatter->format( $lines ) );
	}

	protected function get_lang_info( string $language ) : string {
		$lang_info       = Options::get_languages_info()[ $language ] ?? [];
		if ( empty( $lang_info ) ) {
			return "%B($language, " . __( 'Unknown/removed locale', 'unbabble' ) . ')%N';
		}
		$lang_info_str   = '';
		$lang_info_parts = [ $language ];
		if ( isset( $lang_info['english_name'] ) ) {
			$lang_info_parts[] = $lang_info['english_name'];
		}
		if ( isset( $lang_info['native_name'] ) ) {
			$lang_info_parts[] = $lang_info['native_name'];
		}
		if ( ! empty( $lang_info_parts ) ) {
			$lang_info_str = '%y(' . implode( ', ', $lang_info_parts ) . ')%N';
		}
		return $lang_info_str;
	}
}
