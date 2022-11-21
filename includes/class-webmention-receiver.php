<?php
/**
 * Webmention receiver.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Webmention receiver.
 */
class Webmention_Receiver {
	/**
	 * Registers a new REST API route.
	 */
	public static function register_route() {
		register_rest_route(
			'indieblocks/v1',
			'/webmention',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'store_webmention' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Stores incoming webmentions and that's about it.
	 *
	 * @param  WP_REST_Request $request API request.
	 * @return WP_REST_Response         API response.
	 */
	public static function store_webmention( $request ) {
		error_log( '[Indieblocks/Webmention] Got request: ' . wp_json_encode( $request->get_params() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		// Verify source nor target are invalid URLs.
		if ( empty( $request['source'] ) || ! wp_http_validate_url( $request['source'] ) || empty( $request['target'] ) || ! wp_http_validate_url( $request['target'] ) ) {
			error_log( '[Indieblocks/Webmention] Invalid source or target.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_Error( 'invalid_request', 'Invalid source or target', array( 'status' => 400 ) );
		}

		// Get the target post's slug, sans permalink front.
		$slug = trim( basename( wp_parse_url( $request['target'], PHP_URL_PATH ) ), '/' );

		// We don't currently differentiate between "incoming" and "outgoing" post types.
		$supported_post_types = Webmention_Sender::get_supported_post_types();

		// Fetch the post.
		$post = get_page_by_path( $slug, OBJECT, $supported_post_types );
		$post = apply_filters( 'indieblocks_webmention_post', $post, $request['target'], $supported_post_types );

		if ( empty( $post ) || 'publish' !== get_post_status( $post->ID ) ) {
			// Not found.
			error_log( '[Indieblocks/Webmention] Target post not found.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}

		// Set sender's IP address.
		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', apply_filters( 'indieblocks_webmention_sender_ip', $ip, $request ) );

		global $wpdb;

		// Insert webmention into database.
		$num_rows = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'indieblocks_webmentions',
			array(
				'source'     => esc_url_raw( $request['source'] ),
				'target'     => esc_url_raw( $request['target'] ),
				'post_id'    => $post->ID,
				'ip'         => $ip,
				'status'     => 'draft',
				'created_at' => current_time( 'mysql' ),
			)
		);

		if ( false !== $num_rows ) {
			error_log( '[Indieblocks/Webmention] Stored mention for later processing.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Create an empty REST response and add an 'Accepted' status code.
			$response = new \WP_REST_Response( array() );
			$response->set_status( 202 );

			return $response;
		}

		error_log( '[Indieblocks/Webmention] Could not insert mention into database.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return new \WP_Error( 'server_error', 'Internal server error', array( 'status' => 500 ) );
	}

	/**
	 * Processes queued webmentions. Typically triggered by WP Cron.
	 */
	public static function process_webmentions() {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'indieblocks_webmentions';
		$webmentions = $wpdb->get_results( "SELECT id, source, post_id, ip, created_at FROM $table_name WHERE status = 'draft' LIMIT 5" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $webmentions ) || ! is_array( $webmentions ) ) {
			// Empty queue.
			return;
		}

		foreach ( $webmentions as $webmention ) {
			$update = false;

			// Look for an existing comment with this source URL, see if it
			// needs updated or deleted.
			$query = new \WP_Comment_Query(
				array(
					'comment_post_ID' => $webmention->post_id,
					'orderby'         => 'comment_ID',
					'order'           => 'DESC',
					'fields'          => 'ids',
					'limit'           => 1,
					'meta_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'AND',
						array(
							'key'     => 'indieblocks_webmention_source',
							'compare' => 'EXISTS',
						),
						array(
							'key'     => 'indieblocks_webmention_source',
							'compare' => '=',
							'value'   => esc_url( $webmention->source ),
						),
					),
				)
			);

			$comments = $query->comments;

			// Fetch source HTML.
			error_log( "[Indieblocks/Webmention] Fetching the page at {$webmention->source}." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$response = remote_get( $webmention->source );

			if ( is_wp_error( $response ) ) {
				error_log( "[Indieblocks/Webmention] Something went wrong fetching the page at {$webmention->source}." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				// Something went wrong.
				error_log( $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			if ( ! empty( $comments ) && is_array( $comments ) ) {
				$update     = true;
				$comment_id = reset( $comments );

				error_log( "[Indieblocks/Webmention] Found an existing comment ({$comment_id}) for this mention." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				if ( in_array( wp_remote_retrieve_response_code( $response ), array( 404, 410 ), true ) ) {
					// Delete instead.
					if ( wp_delete_comment( $comment_id ) ) {
						$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
							$table_name,
							array(
								'status'      => 'deleted',
								'modified_at' => current_time( 'mysql' ),
							),
							array( 'id' => $webmention->id ),
							null,
							array( '%d' )
						);
					} else {
						error_log( "[Indieblocks/Webmention] Something went wrong deleting comment {$comment_id} for source URL (" . esc_url_raw( $webmention->source ) . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}

					continue;
				}
			}

			$html   = wp_remote_retrieve_body( $response );
			$target = ! empty( $webmention->target ) ? $webmention->target : get_permalink( $webmention->post_id );

			if ( false === stripos( $html, $target ) ) {
				error_log( "[Indieblocks/Webmention] The page at {$webmention->source} does not seem to mention our target URL ({$target})." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				// Target URL not (or no longer) mentioned by source. Mark webmention as processed.
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$table_name,
					array(
						'status'      => 'invalid',
						'modified_at' => current_time( 'mysql' ),
					),
					array( 'id' => $webmention->id ),
					null,
					array( '%d' )
				);

				// Skip to next webmention.
				continue;
			}

			error_log( "[Indieblocks/Webmention] The page at {$webmention->source} seems to mention our target URL (" . get_permalink( $webmention->post_id ) . '); creating new comment.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			// Grab source domain.
			$host = wp_parse_url( $webmention->source, PHP_URL_HOST );

			// Some defaults.
			$commentdata = array(
				'comment_post_ID'      => apply_filters( 'indieblocks_webmention_post_id', $webmention->post_id ),
				'comment_author'       => $host,
				'comment_author_email' => 'someone@example.org',
				'comment_author_url'   => esc_url_raw( wp_parse_url( $webmention->source, PHP_URL_SCHEME ) . '://' . $host ),
				'comment_author_IP'    => $webmention->ip,
				'comment_content'      => __( '&hellip; commented on this.', 'indieblocks' ),
				'comment_parent'       => 0,
				'user_id'              => 0,
				'comment_date'         => $webmention->created_at,
				'comment_date_gmt'     => get_gmt_from_date( $webmention->created_at ),
				'comment_type'         => '',
				'comment_meta'         => array(
					'indieblocks_webmention_source' => esc_url_raw( $webmention->source ),
				),
			);

			// Search source for supported microformats, and update
			// `$commentdata` accordingly.
			Webmention_Parser::parse_microformats( $commentdata, $html, $webmention->source, get_permalink( $webmention->post_id ) );

			// Disable comment flooding check.
			remove_action( 'check_comment_flood', 'check_comment_flood_db' );

			// Insert new comment.
			if ( $update ) {
				$commentdata['comment_ID']       = $comment_id;
				$commentdata['comment_approved'] = '0'; // Force re-approval, for now. To do: We should do this only if the content has changed.

				error_log( "[Indieblocks/Webmention] Updating comment {$comment_id}." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$result = wp_update_comment( $commentdata, true );
			} else {
				error_log( '[Indieblocks/Webmention] Creating new comment.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$result = wp_new_comment( $commentdata, true );
			}

			// Default status. "Complete" means "done processing," rather than
			// 'success'.
			$status = $update ? 'updated' : 'created';

			if ( is_wp_error( $result ) ) {
				// For troubleshooting.
				error_log( print_r( $comment_id, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( in_array( 'comment_duplicate', $result->get_error_codes(), true ) ) {
					// Log if deemed duplicate. Could come in useful if we ever
					// wanna support "updated" webmentions.
					$status = 'duplicate';
				}
			}

			// Mark webmention as processed.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_name,
				array(
					'status'      => $status,
					'modified_at' => current_time( 'mysql' ),
				),
				array( 'id' => $webmention->id ),
				null,
				array( '%d' )
			);

			error_log( "[Indieblocks/Webmention] And we're done parsing this particular mention." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		}
	}

	/**
	 * Adds comment meta box.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'indieblocks',
			__( 'Webmention', 'indieblocks' ),
			array( __CLASS__, 'render_meta_box' ),
			'comment',
			'normal',
			'high'
		);
	}

	/**
	 * Renders a (read-only) "Webmention" comment meta box.
	 *
	 * @param WP_Comment $comment Comment being edited.
	 */
	public static function render_meta_box( $comment ) {
		// Webmention data.
		$source = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_source', true );
		$kind   = get_comment_meta( $comment->comment_ID, 'indieblocks_webmention_kind', true );
		?>
			<p><label for="indieblocks_webmention_source"><?php esc_html_e( 'Source', 'indieblocks' ); ?></label>
			<input type="url" id="indieblocks_webmention_source" name="indieblocks_webmention_source" value="<?php echo esc_attr( $source ); ?>" class="widefat" readonly="readonly" /></p>

			<p><label for="indieblocks_webmention_kind"><?php esc_html_e( 'Type', 'indieblocks' ); ?></label>
			<input type="url" id="indieblocks_webmention_kind" name="indieblocks_webmention_kind" value="<?php echo esc_attr( ucfirst( $kind ) ); ?>" class="widefat" readonly="readonly" /></p>
		<?php
	}

	/**
	 * Prints the webmention endpoint.
	 */
	public static function webmention_link() {
		echo '<link rel="webmention" href="' . esc_url( get_rest_url( null, '/indieblocks/v1/webmention' ) ) . '" />' . PHP_EOL;
	}
}
