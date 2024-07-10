<?php

namespace TwentySixB\WP\Plugin\Unbabble\API\Gutenberg;

use Throwable;
use TwentySixB\WP\Plugin\Unbabble\Integrations\YoastDuplicatePost;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Plugin;
use TwentySixB\WP\Plugin\Unbabble\Posts\LinkTranslation;
use WP_Post;
use WP_REST_Response;

class Post {
	public function __construct( Plugin $plugin, string $namespace ) {
		$this->namespace = $namespace;
	}

	public function register() {
		// create new translation: No need for API, can be done from just a redirect.
		// copy new translation and redirect
		// Linking/Unlinking

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'post_information' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/language/(?P<change_lang>[a-zA-Z_-]+)',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'change_post_language' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/(?P<translation_lang>[a-zA-Z_-]+)/copy',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'new_translation_copy' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/(?P<translation_id>[0-9]+)/link',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'link_translations' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/unlink',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'unlink_from_translations' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/link',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_possible_links' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	public function permission_callback( \WP_REST_Request $request ) : bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$post_type = get_post_type( $request['id'] );

		if ( $post_type === false ) {
			return false;
		}

		$capability = get_post_type_object( $post_type )->cap->edit_post ?? null;
		if ( empty( $capability ) ) {
			return false;
		}

		return current_user_can( $capability, $request['id'] );
	}

	public function post_information( \WP_REST_Request $request ) {
		try {
			$data    = [];
			$post_id = $request['id'];

			$post_type = get_post_type( $post_id );
			if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
				return new WP_REST_Response( $data, 403 );
			}

			$data['language'] = LangInterface::get_post_language( $post_id );
			if ( empty( $data['language'] ) ) {
				$data['language'] = null;
			}

			$data['translations'] = [];

			$translations         = LangInterface::get_post_translations( $post_id );
			foreach ( $translations as $translation_id => $language ) {
				$data['translations'][] = [
					'language' => $language,
					'ID'       => $translation_id,
					'edit'     => get_edit_post_link( $translation_id, '' ),
					'view'     => get_permalink( $translation_id ),
				];
			}

			usort( $data['translations'], fn( $trans_a, $trans_b ) => $trans_a['language'] <=> $trans_b['language'] );

			return new WP_REST_Response( $data, 200 );

		} catch ( Throwable $e ) {
			return new WP_REST_Response( null, 500 );
		}
	}

	public function change_post_language( \WP_REST_Request $request ) {
		try {
			$post_id      = $request['id'];
			$new_language = $request['change_lang'];

			$previous_language = LangInterface::get_post_language($post_id);
			if ( empty( $previous_language ) ) {
				if ( ! LangInterface::set_post_language( $post_id, $new_language ) ) {
					return new WP_REST_Response( null, 400 );
				}

			} else {
				if ( ! LangInterface::change_post_language( $post_id, $new_language ) ) {
					return new WP_REST_Response( null, 400 );
				}
			}

			return new WP_REST_Response( null, 200 );

		} catch ( Throwable $e ) {
			return new WP_REST_Response( null, 500 );
		}
	}

	public function new_translation_copy( \WP_REST_Request $request ) {
		try {

			// Check if the Yoast's duplicate post plugin is active.
			if ( ! is_plugin_active( 'duplicate-post/duplicate-post.php' ) ) {
				return new WP_REST_Response( null, 404 );
			}

			$post_id = $request['id'];

			$new_post_id = ( new YoastDuplicatePost() )->copy( $post_id, $request['translation_lang'] );
			if ( ! is_int( $new_post_id ) || $new_post_id < 1 ) {
				// TODO: error message
				return new WP_REST_Response( null, 400 );
			}

			$new_post_url = get_edit_post_link( $new_post_id, '' );
			return new WP_REST_Response( [ 'copy_url' => $new_post_url ], 200 );

		} catch ( Throwable $e ) {
			return new WP_REST_Response( null, 500 );
		}
	}

	public function link_translations( \WP_REST_Request $request ) {
		try {
			$post_id        = $request['id'];
			$translation_id = $request['translation_id'];

			$link = ( new LinkTranslation() )->link_translations( $post_id, $translation_id );

			if ( ! $link ) {
				return new WP_REST_Response( null, 400 );
			}

			return new WP_REST_Response( null, 200 );

		} catch ( Throwable $e ) {
			return new WP_REST_Response( null, 500 );
		}
	}

	public function unlink_from_translations( \WP_REST_Request $request ) {
		try {
			$post_id = $request['id'];

			$unlinked = ( new LinkTranslation() )->unlink( $post_id );

			if ( ! $unlinked ) {
				return new WP_REST_Response( null, 400 );
			}

			return new WP_REST_Response( null, 200 );

		} catch ( Throwable $e ) {
			return new WP_REST_Response( null, 500 );
		}
	}

	public function get_possible_links( \WP_REST_Request $request ) {
		try {
			$post_id = $request['id'];

			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				return new WP_REST_Response( null, 404 );
			}

			if ( $post->post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post->post_type ) ) {
				return null;
			}

			$language = LangInterface::get_post_language( $post_id );

			// TODO: if language is empty, return error message

			$page = $request->get_param( 'page' );
			if ( ! is_numeric( $page ) ) {
				$page = 1;
			}

			$possible_links = ( new LinkTranslation() )->get_possible_links( $post, $language, $page );

			return new WP_REST_Response( $possible_links, 200 );

		} catch ( Throwable $e ) {
			return new WP_REST_Response( null, 500 );
		}
	}
}
