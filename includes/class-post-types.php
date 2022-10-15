<?php
/**
 * Short-form custom post types.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Introduces a number of short-form custom post types, and more.
 */
class Post_Types {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		$plugin  = IndieBlocks::get_instance();
		$options = $plugin->get_options_handler()->get_options();

		if ( ! empty( $options['post_types'] ) ) {
			// Register short-form post types.
			add_action( 'init', array( __CLASS__, 'register_post_types' ) );

			// Set the post type for Micropub posts.
			add_filter( 'micropub_post_type', array( __CLASS__, 'set_post_type' ), 10, 2 );
			add_filter( 'micropub_post_content', array( __CLASS__, 'set_post_content' ), 10, 2 );

			if ( ! empty( $options['default_taxonomies'] ) ) {
				// Include Notes in category and tag archives.
				add_filter( 'pre_get_posts', array( __CLASS__, 'include_in_archives' ), 99 );
			}

			if ( ! empty( $options['include_in_search'] ) ) {
				// Include short-form entries in search results.
				add_filter( 'pre_get_posts', array( __CLASS__, 'include_in_search' ), 99 );
			}

			if ( ! empty( $options['automatic_titles'] ) ) {
				// Automatically generate a "post title" for short-form posts.
				add_filter( 'wp_insert_post_data', array( __CLASS__, 'set_title' ), 10, 2 );
			}

			if ( ! empty( $options['random_slugs'] ) ) {
				// Generate a random slug for short-form posts.
				add_filter( 'wp_insert_post_data', array( __CLASS__, 'set_slug' ), 11, 2 );
			}

			if ( ! empty( $options['custom_menu_order'] ) ) {
				// Have Notes and Likes appear right under Posts in WP's main menu.
				add_filter( 'custom_menu_order', '__return_true' );
				add_filter( 'menu_order', array( __CLASS__, 'menu_order' ) );
			}
		}
	}

	/**
	 * Modifies WP's main menu.
	 */
	public static function menu_order() {
		return array(
			'index.php', // Dashboard.
			'separator1',
			'edit.php', // Posts.
			'edit.php?post_type=indieblocks_note', // Notes.
			'edit.php?post_type=indieblocks_like', // Likes.
			// Let WordPress take it from here. This works, somehow.
		);
	}

	/**
	 * Registers custom post types.
	 */
	public static function register_post_types() {
		$plugin  = IndieBlocks::get_instance();
		$options = $plugin->get_options_handler()->get_options();

		// Notes.
		$args = array(
			'labels'            => array(
				'name'               => __( 'Notes', 'indieblocks' ),
				'singular_name'      => __( 'Note', 'indieblocks' ),
				'add_new'            => __( 'New Note', 'indieblocks' ),
				'add_new_item'       => __( 'Add New Note', 'indieblocks' ),
				'edit_item'          => __( 'Edit Note', 'indieblocks' ),
				'view_item'          => __( 'View Note', 'indieblocks' ),
				'view_items'         => __( 'View Notes', 'indieblocks' ),
				'search_items'       => __( 'Search Notes', 'indieblocks' ),
				'not_found'          => __( 'No notes found.', 'indieblocks' ),
				'not_found_in_trash' => __( 'No notes found in trash.', 'indieblocks' ),
			),
			'public'            => true,
			'has_archive'       => true,
			'show_in_nav_menus' => true,
			'rewrite'           => array(
				'slug'       => __( 'notes', 'indieblocks' ),
				'with_front' => false,
			),
			'supports'          => array( 'author', 'title', 'editor', 'thumbnail', 'custom-fields', 'comments', 'wpcom-markdown' ),
			'menu_icon'         => 'dashicons-format-status',
		);

		if ( ! empty( $options['enable_blocks'] ) ) {
			// Enable the block editor.
			$args['show_in_rest'] = true;
		}

		if ( ! empty( $options['default_taxonomies'] ) ) {
			// Enable WordPress' default categories and tags.
			$args['taxonomies'] = array( 'category', 'post_tag' );
		}

		register_post_type( 'indieblocks_note', $args );

		// Likes.
		$args = array(
			'labels'            => array(
				'name'               => __( 'Likes', 'indieblocks' ),
				'singular_name'      => __( 'Like', 'indieblocks' ),
				'add_new'            => __( 'New Like', 'indieblocks' ),
				'add_new_item'       => __( 'Add New Like', 'indieblocks' ),
				'edit_item'          => __( 'Edit Like', 'indieblocks' ),
				'view_item'          => __( 'View Like', 'indieblocks' ),
				'view_items'         => __( 'View Likes', 'indieblocks' ),
				'search_items'       => __( 'Search Likes', 'indieblocks' ),
				'not_found'          => __( 'No likes found.', 'indieblocks' ),
				'not_found_in_trash' => __( 'No likes found in trash.', 'indieblocks' ),
			),
			'public'            => true,
			'has_archive'       => true,
			'show_in_nav_menus' => true,
			'rewrite'           => array(
				'slug'       => __( 'likes', 'indieblocks' ),
				'with_front' => false,
			),
			'supports'          => array( 'author', 'title', 'editor', 'custom-fields', 'wpcom-markdown' ),
			'menu_icon'         => 'dashicons-heart',
		);

		if ( ! empty( $options['enable_blocks'] ) ) {
			$args['show_in_rest'] = true;
		}

		register_post_type( 'indieblocks_like', $args );
	}

	/**
	 * Maps Micropub entries to a custom post type.
	 *
	 * @param  string $post_type Post type.
	 * @param  array  $input     Input data.
	 * @return string            Post type slug.
	 */
	public static function set_post_type( $post_type, $input ) {
		if ( ! empty( $input['properties']['like-of'][0] ) ) {
			$post_type = 'indieblocks_like';
		} elseif ( ! empty( $input['properties']['bookmark-of'][0] ) ) {
			$post_type = 'indieblocks_note';
		} elseif ( ! empty( $input['properties']['repost-of'][0] ) ) {
			$post_type = 'indieblocks_note';
		} elseif ( ! empty( $input['properties']['in-reply-to'][0] ) ) {
			$post_type = 'indieblocks_note';
		} elseif ( ! empty( $input['properties']['content'][0] ) && empty( $input['post_title'] ) ) {
			$post_type = 'indieblocks_note';
		}

		return $post_type;
	}

	/**
	 * Overrides default Micropub post content.
	 *
	 * @todo: Clean up.
	 *
	 * @param  string $post_content Post content.
	 * @param  array  $input        Input properties.
	 * @return string               Modified content.
	 */
	public static function set_post_content( $post_content, $input ) {
		$plugin  = IndieBlocks::get_instance();
		$options = $plugin->get_options_handler()->get_options();

		if ( empty( $options['enable_blocks'] ) ) {
			return $post_content;
		}

		// Replace default content with block-based content.
		if ( ! empty( $input['properties']['like-of'][0] ) ) {
			$post_content = static::render( 'like', $input['properties']['like-of'][0], $input );
		} elseif ( ! empty( $input['properties']['bookmark-of'][0] ) ) {
			$post_content = static::render( 'bookmark', $input['properties']['bookmark-of'][0], $input );
		} elseif ( ! empty( $input['properties']['repost-of'][0] ) ) {
			$post_content = static::render( 'repost', $input['properties']['repost-of'][0], $input );
		} elseif ( ! empty( $input['properties']['in-reply-to'][0] ) ) {
			$post_content = static::render( 'reply', $input['properties']['in-reply-to'][0], $input );
		}

		return $post_content;
	}

	/**
	 * Sets a random slug for short-form content.
	 *
	 * @param  array $data    Filtered data.
	 * @param  array $postarr Original data.
	 * @return array          Updated (slashed) data.
	 */
	public static function set_slug( $data, $postarr ) {
		if ( ! empty( $postarr['ID'] ) ) {
			// Not a new post. Bail.
			return $data;
		}

		if ( ! in_array( $data['post_type'], array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			return $data;
		}

		global $wpdb;

		// Generate a random slug for short-form content, i.e., notes and likes.
		do {
			// Generate random slug.
			$slug = bin2hex( openssl_random_pseudo_bytes( 5 ) );

			// Check uniqueness.
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name = %s LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		} while ( $result );

		$data['post_name'] = $slug;

		return $data;
	}

	/**
	 * Automatically sets a post title for short-form content, so that it's
	 * easier to browse within WP Admin.
	 *
	 * The one exception is bookmarks, which often _do_ have an actual title.
	 *
	 * @param  array $data    Filtered data.
	 * @param  array $postarr Original data, mostly.
	 * @return array          Updated (slashed) data.
	 */
	public static function set_title( $data, $postarr ) {
		if ( ! in_array( $data['post_type'], array( 'indieblocks_like', 'indieblocks_note' ), true ) ) {
			return $data;
		}

		// Whether bookmarks should get autogenerated titles.
		$ignore_bookmark_titles = apply_filters( 'indieblocks_ignore_bookmark_titles', true );

		if ( ! empty( $postarr['meta_input']['mf2_bookmark-of'][0] ) && ! empty( $data['post_title'] ) && $ignore_bookmark_titles ) {
			// Leave _non-empty_ bookmark titles alone. Only affects Micropub
			// posts, though.
			return $data;
		}

		/*
		 * In all other cases, let's generate a post title from the post's
		 * content.
		 */

		$title = wp_unslash( $data['post_content'] );

		/*
		 * Some default "filters." Use the `indieblocks_title` filter to undo or
		 * extend.
		 */

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.InlineComment.InvalidEndChar
		// $title = preg_replace( '~\[caption.*\](.+?)\[/caption\]~', '', $title );

		// We might want to later on add a feed with titles, for backwards
		// compatibility with older feed readers. We'll then want to have (more
		// modern) feed readers recognize these titles as truncated versions of
		// the posts' contents.
		$title = apply_filters( 'the_content', $title );
		$title = trim( wp_strip_all_tags( $title ) );
		// Avoid double-encoded characters.
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

		// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.BlockComment.NoEmptyLineBefore
		/*
		// Wrap lines that start with `> ` in (double) quotes.
		$title = preg_replace( '/^> (.+)$/m', "\"$1\"", $title ); // phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
		// Prevent duplicate quotes.
		$title = str_replace( '""', '"', $title );
		$title = str_replace( '"“', '"', $title );
		$title = str_replace( '”"', '"', $title );
		*/
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.BlockComment.NoEmptyLineBefore

		// Collapse lines and remove excess whitespace.
		$title = preg_replace( '/\s+/', ' ', $title );

		// Shorten.
		$title = wp_trim_words( $title, 8, ' ...' );
		// Prevent duplicate ellipses.
		$title = str_replace( '... ...', '...', $title );
		$title = str_replace( '… ...', '...', $title );

		$title = wp_slash( $title );

		// Define a filter that allows others to do something else entirely.
		$data['post_title'] = apply_filters( 'indieblocks_title', $title, $data['post_title'], $data['post_content'] );

		return $data;
	}

	/**
	 * Includes notes in category and tag archives.
	 *
	 * @param  WP_Query $query The WP_Query object.
	 * @return WP_Query        Modified query object.
	 */
	public static function include_in_archives( $query ) {
		if ( is_admin() ) {
			return $query;
		}

		if ( ! $query->is_main_query() ) {
			return $query;
		}

		if ( ! empty( $query->query_vars['suppress_filters'] ) ) {
			return $query;
		}

		if ( ! $query->is_category() && ! $query->is_tag() ) {
			return $query;
		}

		$post_types = $query->get( 'post_type' );

		if ( is_string( $post_types ) ) {
			$post_types = explode( ',', $post_types );
		}

		$post_types   = ! empty( $post_types ) ? $post_types : array( 'post' );
		$post_types   = array_filter( (array) $post_types );
		$post_types[] = 'indieblocks_note';

		$query->set( 'post_type', array_unique( $post_types ) );

		return $query;
	}

	/**
	 * Includes notes (and pages) in search results.
	 *
	 * @param  WP_Query $query The WP_Query object.
	 * @return WP_Query        Modified query object.
	 */
	public static function include_in_search( $query ) {
		if ( is_admin() ) {
			return $query;
		}

		if ( ! $query->is_main_query() ) {
			return $query;
		}

		if ( ! empty( $query->query_vars['suppress_filters'] ) ) {
			return $query;
		}

		if ( ! $query->is_search() ) {
			return $query;
		}

		$post_types = $query->get( 'post_type' );

		if ( is_string( $post_types ) ) {
			$post_types = explode( ',', $post_types );
		}

		$post_types   = ! empty( $post_types ) ? $post_types : array( 'post' );
		$post_types   = array_filter( (array) $post_types );
		$post_types[] = 'indieblocks_note';
		$post_types[] = 'page';

		$query->set( 'post_type', array_unique( $post_types ) );

		return $query;
	}

	/**
	 * Render `e-content` for certain post types.
	 *
	 * @param  string $post_type (IndieWeb) post type.
	 * @param  array  $url       The URL being interacted with, if applicable.
	 * @param  array  $input     Micropub input arguments.
	 * @return string            Rendered content.
	 */
	public static function render( $post_type, $url = '', $input = array() ) {
		if ( ! empty( $url ) ) {
			if ( preg_match( '~https?://.+?(?:$|\s)~', $url, $matches ) ) {
				$url = $matches[0]; // Just the URL, please.
			}

			$post_content = '';

			switch ( $post_type ) {
				case 'like':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-like-of"} -->' . PHP_EOL;
					/* translators: %s: URL of the "liked" page. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Likes %s.', 'indieblocks' ), '<a class="u-like-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;

				case 'bookmark':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-bookmark-of"} -->' . PHP_EOL;
					/* translators: %s: URL of the bookmarked page. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Bookmarked %s.', 'indieblocks' ), '<a class="u-bookmark-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;

				case 'reply':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-in-reply-to"} -->' . PHP_EOL;
					/* translators: %s: URL of the page being replied to. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'In reply to %s.', 'indieblocks' ), '<a class="u-in-reply-to" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;

				case 'repost':
					$post_content .= '<!-- wp:indieblocks/context {"kind":"u-repost-of"} -->' . PHP_EOL;
					/* translators: %s: URL of the "page" being reposted. */
					$post_content .= '<div class="wp-block-indieblocks-context"><i>' . sprintf( __( 'Reposted %s.', 'indieblocks' ), '<a class="u-repost-of" href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>' ) . '</i></div>
						<!-- /wp:indieblocks/context -->' . PHP_EOL;
					break;
			}
		}

		if ( ! empty( $input['properties']['content'][0] ) ) {
			if ( 'repost' === $post_type ) {
				$post_content .= '<!-- wp:quote {"className":"e-content"} -->
					<blockquote class="wp-block-quote e-content"><p>' . wp_kses_post( $input['properties']['content'][0] ) . '</p></blockquote>
					<!-- /wp:quote -->';
			} else {
				$post_content .= '<!-- wp:group {"className":"e-content"} -->
					<div class="wp-block-group e-content"><!-- wp:paragraph -->
					<p>' . wp_kses_post( $input['properties']['content'][0] ) . '</p>
					<!-- /wp:paragraph --></div>
					<!-- /wp:group -->';
			}
		}

		return trim( $post_content );
	}
}
