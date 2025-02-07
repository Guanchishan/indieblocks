<?php
/**
 * Parse (certain) outgoing links.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Attempts to parse (certain) outgoing links.
 */
class Parser {
	/**
	 * Page URL.
	 *
	 * @var string $url URL of the page we're parsing.
	 */
	private $url;

	/**
	 * Page DOM.
	 *
	 * @var \DOMDocument $dom DOM of the page we're parsing.
	 */
	private $dom;

	/**
	 * Microformats2.
	 *
	 * @var array $mf2 Microformats2 representation of the page we're parsing.
	 */
	private $mf2;

	/**
	 * Constructor.
	 *
	 * @param string|null $url URL of the page we'll be parsing.
	 */
	public function __construct( $url = null ) {
		$this->url = $url;
		$this->dom = new \DOMDocument();
	}

	/**
	 * Fetches the page, then loads its DOM. Then loads its mf2.
	 *
	 * @param string $content (Optional) HTML to be parsed instead.
	 */
	public function parse( $content = '' ) {
		if ( ! empty( $this->url ) ) {
			$hash = hash( 'sha256', esc_url_raw( $this->url ) );
		} elseif ( ! empty( $content ) ) {
			$hash = hash( 'sha256', $content );
		}

		if ( empty( $content ) && ! empty( $this->url ) ) {
			// No `$content` was passed along, but a URL was.
			$content = get_transient( 'indieblocks:html:' . $hash );

			if ( false === $content ) {
				// Could not find a cached version. Download page.
				$response = remote_get( $this->url );
				$content  = wp_remote_retrieve_body( $response );
				set_transient( 'indieblocks:html:' . $hash, $content, 3600 ); // Cache, even if empty.
			}
		}

		if ( empty( $content ) ) {
			// We need HTML to be able to load the DOM.
			return;
		}

		$content = mb_convert_encoding( $content, 'HTML-ENTITIES', mb_detect_encoding( $content ) );
		libxml_use_internal_errors( true );
		$this->dom->loadHTML( $content );

		// Attempt to also load mf2.
		$mf2 = get_transient( 'indieblocks:mf2:' . $hash );

		if ( empty( $mf2 ) ) {
			$mf2 = Mf2\parse( $content, $this->url );
			set_transient( 'indieblocks:mf2:' . $hash, $mf2, 3600 );
		}

		$this->mf2 = Mf2\parse( $content, $this->url );
	}

	/**
	 * Returns a page name.
	 *
	 * @return string Current page's name or title.
	 */
	public function get_name() {
		if ( ! empty( $this->mf2['items'][0]['properties'] ) ) {
			// Microformats.
			$properties = $this->mf2['items'][0]['properties'];

			$name = ! empty( $properties['name'][0] ) && is_string( $properties['name'][0] )
				? trim( $properties['name'][0] )
				: '';

			if ( '' === $name ) {
				// Note.
				return '';
			}

			$content = '';
			if ( ! empty( $properties['content']['value'] ) ) {
				$content = $properties['content']['value'];
			} elseif ( ! empty( $post['summary'][0] ) ) {
				$content = $properties['summary'][0];
			}

			$content = preg_replace( '~\s+~', ' ', trim( $content ) );
			$check   = preg_replace( '~\s+~', ' ', $name );
			if ( '...' === substr( $check, -3 ) ) {
				$check = substr( $check, 0, -3 );
			} elseif ( '…' === substr( $check, -1) ) {
				$check = substr( $check, 0, -1 );
			}

			if ( 0 === strpos( $content, $check ) ) {
				// Note.
				return '';
			}

			return $name;
		}

		// No microformats.
		$title = $this->dom->getElementsByTagName( 'title' );
		foreach ( $title as $el ) {
			return sanitize_text_field( $el->textContent );
		}

		// No nothing.
		return '';
	}

	/**
	 * Returns the author, if it can find one.
	 *
	 * @return string Page author.
	 */
	public function get_author() {
		if ( ! empty( $this->mf2['items'][0]['properties'] ) ) {
			// Microformats.
			$properties = $this->mf2['items'][0]['properties'];

			if ( ! empty( $properties['author'][0] ) && is_string( $properties['author'][0] ) ) {
				return $properties['author'][0];
			}

			if ( ! empty( $properties['author'][0]['properties']['name'][0] ) ) {
				return $properties['author'][0]['properties']['name'][0];
			}
		}

		// No microformats.
		$meta = $this->dom->getElementsByTagName( 'meta' );
		foreach ( $meta as $el ) {
			if ( 'author' === $el->getAttribute( 'name' ) )	 {
				// Some people still use these.
				return sanitize_text_field( $el->getAttribute( 'content' ) ); // Returns an empty string if the `content` attribute does not exist.
			}
		}

		// No nothing.
		return '';
	}

	/**
	 * Returns the author URL, if it can find one.
	 *
	 * @return string Author URL.
	 */
	public function get_author_url() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['author'][0]['properties']['url'][0] ) ) {
				return $post['properties']['author'][0]['properties']['url'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the author's avatar, we can find one.
	 *
	 * @return string Avatar URL.
	 */
	public function get_avatar() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['author'][0]['properties']['photo'][0] ) ) {
				return $post['properties']['author'][0]['properties']['photo'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the URL, i.e., `href` value, of the current page's first "like,"
	 * "bookmark," or "repost" link.
	 *
	 * @return string Link URL.
	 */
	public function get_link_url() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['repost-of'][0] ) && filter_var( $post['properties']['repost-of'][0], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['repost-of'][0];
			}

			if ( ! empty( $post['properties']['repost-of'][0]['value'] ) && filter_var( $post['properties']['repost-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['repost-of'][0]['value'];
			}

			if ( ! empty( $post['properties']['like-of'][0] ) && filter_var( $post['properties']['like-of'][0], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['like-of'][0];
			}

			if ( ! empty( $post['properties']['like-of'][0]['value'] ) && filter_var( $post['properties']['like-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['like-of'][0]['value'];
			}

			if ( ! empty( $post['properties']['bookmark-of'][0] ) && filter_var( $post['properties']['bookmark-of'][0], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['bookmark-of'][0];
			}

			if ( ! empty( $post['properties']['bookmark-of'][0]['value'] ) && filter_var( $post['properties']['bookmark-of'][0]['value'], FILTER_VALIDATE_URL ) ) {
				return $post['properties']['bookmark-of'][0]['value'];
			}
		}

		return '';
	}

	/**
	 * Returns the name (i.e., the text content) of the current page's first
	 * "like," "bookmark," or "repost" link.
	 *
	 * @return string Link name.
	 */
	public function get_link_name() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['properties']['repost-of'][0]['properties']['name'][0] ) && is_string( $post['properties']['repost-of'][0]['properties']['name'][0] ) ) {
				return $post['properties']['repost-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $post['properties']['like-of'][0]['properties']['name'][0] ) && is_string( $post['properties']['like-of'][0]['properties']['name'][0] ) ) {
				return $post['properties']['like-of'][0]['properties']['name'][0];
			}

			if ( ! empty( $post['properties']['bookmark-of'][0]['properties']['name'][0] ) && is_string( $post['properties']['bookmark-of'][0]['properties']['name'][0] ) ) {
				return $post['properties']['bookmark-of'][0]['properties']['name'][0];
			}
		}

		return '';
	}

	/**
	 * Returns the current page's "IndieWeb post type."
	 *
	 * @return string Post type.
	 */
	public function get_type() {
		if ( ! empty( $this->mf2['items'][0] ) ) {
			$post = $this->mf2['items'][0];

			if ( ! empty( $post['type'][0] ) && in_array( $post['type'][0], array( 'event', 'recipe', 'review' ), true ) ) {
				return $post['type'];
			}

			$properties = $post['properties'];

			if ( ! empty( $properties['repost-of'] ) ) {
				return 'repost';
			}

			if ( ! empty( $properties['in-reply-to'] ) ) {
				return 'reply';
			}

			if ( ! empty( $properties['like-of'] ) ) {
				return 'like';
			}

			if ( ! empty( $properties['bookmark-of'] ) ) {
				return 'bookmark';
			}

			if ( ! empty( $properties['follow-of'] ) ) {
				return 'follow';
			}

			if ( ! empty( $properties['checkin'] ) ) {
				return 'checkin';
			}

			if ( ! empty( $properties['video'] ) ) {
				return 'video';
			}

			if ( ! empty( $properties['video'] ) ) {
				return 'audio';
			}

			if ( ! empty( $properties['photo'] ) ) {
				return 'photo';
			}

			$name = ! empty( $properties['name'][0] ) && is_string( $properties['name'][0] )
				? trim( $properties['name'][0] )
				: '';

			if ( '' === $name ) {
				// Note.
				return 'note';
			}

			$content = '';
			if ( ! empty( $properties['content']['value'] ) ) {
				$content = $properties['content']['value'];
			} elseif ( ! empty( $post['summary'][0] ) ) {
				$content = $properties['summary'][0];
			}

			$content = preg_replace( '~\s+~', ' ', trim( $content ) );
			$check   = preg_replace( '~\s+~', ' ', $name );
			if ( '...' === substr( $check, -3 ) ) {
				$check = substr( $check, 0, -3 );
			} elseif ( '…' === substr( $check, -1) ) {
				$check = substr( $check, 0, -1 );
			}

			if ( 0 === strpos( $content, $check ) ) {
				// Note.
				return 'note';
			}

			return 'article';
		}

		return '';
	}
}
