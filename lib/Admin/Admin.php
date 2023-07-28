<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

/**
 * General hooks for the back-office.
 *
 * @since 0.0.1
 */
class Admin {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {

		// Admin scripts.
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts'] );

		// Whitelist query vars.
		add_filter( 'query_vars', [ $this, 'whitelist_query_vars' ] );
	}

	/**
	 * Prints the admin notice when Unbabble is idling.
	 *
	 * @since 0.0.9
	 *
	 * @return void
	 */
	public function idle_notice() : void {
		$message = sprintf(
			/* translators: %s: Code html with constant name */
			esc_html( __( 'Unbabble is not running due to the constant %s.', 'unbabble' ) ),
			'<code>UNBABBLE_IDLE</code>'
		);
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', $message );
	}

	/**
	 * Prints the admin notice when the Unbabble options failed to update via the filter.
	 *
	 * @since 0.0.10
	 *
	 * @return void
	 */
	public function options_update_failed_notice() : void {
		$message = sprintf(
			/* translators: %s: Code html with options name */
			esc_html( __( 'Unbabble was not able to update the %s option value following an options value change via the filter.', 'unbabble' ) ),
			'<code>ubb_options</code>'
		);
		printf( '<div class="notice notice-error"><p><b>Unbabble: </b>%s</p></div>', $message );
	}

	/**
	 * Prints the admin notice when the Unbabble options where updated successfully.
	 *
	 * @since 0.0.11
	 *
	 * @return void
	 */
	public function options_updated() : void {
		?>
		<div class="notice notice-success">
			<p> <?php \_e( 'Unbabble options were updated.', 'unbabble' ); ?> </p>
		</div>
		<?php
	}

	/**
	 * Prints the admin notice when the Unbabble options are invalid in a certain context.
	 *
	 * @since 0.0.11
	 *
	 * @param string $context
	 * @param array $errors
	 * @return void
	 */
	public function invalid_options_notice( string $context, array $errors ) : void {
		?>
		<div class="notice notice-error">
			<p>
				<b>Unbabble - </b>
				<?php printf( __( 'Error while %s:', 'unbabble' ), $context ); ?>
				<br>
				<?php /* TODO: Make collapsable lists */ ?>
				<?php foreach ( $errors as $key => $messages ) : ?>
					<p><code><?php print( $key ) ?></code></p>
					<div style='margin-left:2em;'>
						<?php foreach ( $messages as $message ) : ?>
							<p> <?php print( '- ' . $message ) ?> </p>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}



	/**
	 * Enqueues admin scripts.
	 *
	 * @since 0.0.6
	 *
	 * @return void
	 */
	public function enqueue_scripts() : void {
		\wp_enqueue_script(
			'frontend',
			plugin_dir_url( dirname( __FILE__, 2 ) ) . 'build/index.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n' ],
			'0.0.6',
			true
		);
	}

	/**
	 * Whitelists Unbabble's query vars.
	 *
	 * @since 0.0.6
	 *
	 * @param  array $query_vars Whitelist of query vars.
	 * @return array
	 */
	public function whitelist_query_vars( array $query_vars ) : array {
		$query_vars[] = 'lang';
		$query_vars[] = 'ubb_source';
		return $query_vars;
	}
}
