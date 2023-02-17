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

	/**
	 * @var int Number spaces used for a single indentation level.
	 */
	protected const INDENT                   = 4;

	/**
	 * @var string[] WP_CLI color sequences to ignore when formatting the output.
	 */
	private const FORMAT_IGNORED_SEQUENCES = [ '%y', '%g', '%b', '%r', '%p', '%m', '%c', '%w', '%k', '%n', '%Y', '%G', '%B', '%R', '%P', '%M', '%C', '%W', '%K', '%N', '%3', '%2', '%4', '%1', '%5', '%6', '%7', '%0', '%F', '%U', '%8', '%9', '%_' ];

	/**
	 * Number of columns for command output.
	 *
	 * @since 0.0.0
	 *
	 * @return int
	 */
	protected function columns() : int {
		return Shell::columns();
	}

	/**
	 * Log with color.
	 *
	 * @since 0.0.0
	 *
	 * @param string $string
	 * @return void
	 */
	protected static function log_color( string $string ) : void {
		WP_CLI::log( WP_CLI::colorize( $string ) );
	}

	/**
	 * Warn with color.
	 *
	 * @since 0.0.0
	 *
	 * @param string $string
	 * @return void
	 */
	protected static function warning_color( string $string ) : void {
		WP_CLI::warning( WP_CLI::colorize( $string ) );
	}

	/**
	 * Confirm with color.
	 *
	 * @since 0.0.0
	 *
	 * @param string $string
	 * @return void
	 */
	protected static function confirm_color( string $string ) : void {
		WP_CLI::confirm( WP_CLI::colorize( $string ) );
	}

	/**
	 * Returns the indentation string according to the indentation level.
	 *
	 * @since 0.0.0
	 *
	 * @param int $striindent_level
	 * @return string
	 */
	private function get_indentation( int $indent_level ) : string {
		if ( $indent_level > 0 ) {
			return str_repeat( ' ', $indent_level * self::INDENT - 3 );
		}
		return '';
	}

	/**
	 * Formats data and logs it.
	 *
	 * @since 0.0.0
	 *
	 * @param array $data
	 * @param int   $indent_level
	 * @return void
	 */
	protected function format_data_and_log( array $data, int $indent_level = 0 ) : void {

		// If first key is not a string, assume all are not strings.
		if ( ! is_string( current( array_keys( $data ) ) ) ) {
			self::format_lines_and_log( $data, $indent_level );
			WP_CLI::line();
			return;
		}

		foreach ( $data as $index => $value ) {
			if ( is_string( $index ) ) {
				// Output header with $indent_level.
				// TODO: color headers according to indentation level.
				self::log_color( $this->get_indentation( $indent_level + 1 ) . $index );

				self::format_data_and_log( $value, $indent_level + 1 );
				continue;
			}
		}
	}

	/**
	 * Formats lines and logs them.
	 *
	 * @since 0.0.0
	 *
	 * @param array $lines
	 * @param int   $indent_level
	 * @return void
	 */
	protected function format_lines_and_log( array $lines, int $indent_level = 0 ) : void {

		// Remove keys for formatter.
		$lines = array_values( $lines );

		// Add indent as a first column.
		if ( $indent_level > 0 ) {
			$lines = array_map( function ( $line ) use ( $indent_level ) {
				/**
				 * Subtract 3 to account for border padding for the first column (left and right)
				 * and left padding on the second column.
				 */
				array_unshift( $line, $this->get_indentation( $indent_level ) );
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

	/**
	 * Returns a string with a language's information.
	 *
	 * @since 0.0.0
	 *
	 * @param string $language  Code of the language.
	 * @param bool   $show_code Whether to show the code in the language info string.
	 * @return string
	 */
	protected function get_lang_info( string $language, bool $show_code = true ) : string {
		$lang_info       = Options::get_languages_info()[ $language ] ?? [];
		if ( empty( $lang_info ) ) {
			return '%B' . sprintf(
				'(%s%s)',
				$show_code ? "{$language}, " : '',
				__( 'Unknown/removed locale', 'unbabble' )
			) . '%N';
		}
		$lang_info_str   = '';
		$lang_info_parts = $show_code ? [ $language ] : [];
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
