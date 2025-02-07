<?php
/**
 * Location-related functions.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Holds location (and weather) functions.
 */
class Location {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		// Add a "Location" meta box.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'update_meta' ), 11, 3 );

		// Look up a location name (and weather info).
		add_action( 'transition_post_status', array( __CLASS__, 'set_location' ), 12, 3 );
		add_action( 'admin_footer', array( __CLASS__, 'add_script' ) );

		// Register for REST API use.
		add_action( 'rest_api_init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'indieblocks_loc_nonce' );

		$lat = get_post_meta( $post->ID, 'geo_latitude', true );
		$lon = get_post_meta( $post->ID, 'geo_longitude', true );
		?>
		<div style="margin-bottom: 6px;">
			<label><input type="checkbox" name="indieblocks_loc_enabled" value="1" /> <?php esc_html_e( 'Update location data?', 'indieblocks' ); ?></label>
		</div>
		<div style="display: flex; justify-content: space-between;">
			<div style="width: 47.5%;"><label for="indieblocks-lat"><?php esc_html_e( 'Latitude', 'indieblocks' ); ?></label><br />
			<input type="text" name="indieblocks_lat" value="<?php echo esc_attr( '' !== $lat ? round( (float) $lat, 8 ) : '' ); ?>" id="indieblocks-lat" style="max-width: 100%; box-sizing: border-box;" /></div>

			<div style="width: 47.5%;"><label for="indieblocks-lon"><?php esc_html_e( 'Longitude', 'indieblocks' ); ?></label><br />
			<input type="text" name="indieblocks_lon" value="<?php echo esc_attr( '' !== $lon ? round( (float) $lon, 8 ) : '' ); ?>" id="indieblocks-lon" style="max-width: 100%; box-sizing: border-box;" /></div>
		</div>
		<div>
			<label for="indieblocks-address"><?php esc_html_e( 'Name', 'indieblocks' ); ?></label><br />
			<input type="text" name="indieblocks_address" value="<?php echo esc_attr( get_post_meta( $post->ID, 'geo_address', true ) ); ?>" id="indieblocks-address" style="width: 100%; box-sizing: border-box;" />
		</div>
		<?php
	}

	/**
	 * Asks browser for location coordinates.
	 *
	 * @todo: Move to, you know, an actual JS file.
	 */
	public static function add_script() {
		?>
		<script type="text/javascript">
		var indieblocks_loc = document.querySelector( '[name="indieblocks_loc_enabled"]' );

		function indieblocks_update_location() {
			var indieblocks_lat = document.querySelector( '[name="indieblocks_lat"]' );
			var indieblocks_lon = document.querySelector( '[name="indieblocks_lon"]' );

			if ( indieblocks_lat && '' === indieblocks_lat.value && indieblocks_lon && '' === indieblocks_lon.value ) {
				// If the "Latitude" and "Longitude" fields are empty, ask the
				// browser for location information.
				navigator.geolocation.getCurrentPosition( function( position ) {
					indieblocks_lat.value = position.coords.latitude;
					indieblocks_lon.value = position.coords.longitude;

					<?php if ( static::is_recent() ) : // If the post is less than one hour old. ?>
						indieblocks_loc.checked = true; // Auto-enable.
					<?php endif; ?>
				}, function( error ) {
					// Do nothing.
				} );
			}
		}

		indieblocks_update_location();

		if ( indieblocks_loc ) {
			indieblocks_loc.addEventListener( 'click', function( event ) {
				if ( indieblocks_loc.checked ) {
					indieblocks_update_location();
				}
			} );
		}
		</script>
		<?php
	}

	/**
	 * Registers a new meta box.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'indieblocks-location',
			__( 'Location', 'indieblocks' ),
			array( __CLASS__, 'render_meta_box' ),
			apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ),
			'side',
			'default'
		);
	}

	/**
	 * Updates post meta after save.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function update_meta( $new_status, $old_status, $post ) {
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		/* @see https://github.com/WordPress/gutenberg/issues/15094#issuecomment-1021288811. */
		if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_POST['indieblocks_loc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['indieblocks_loc_nonce'] ), basename( __FILE__ ) ) ) {
			// Nonce missing or invalid.
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ), true ) ) {
			// Unsupported post type.
			return;
		}

		if ( empty( $_POST['indieblocks_loc_enabled'] ) ) {
			// Save location meta only if the checkbox was checked. Helps
			// prevent overwriting existing data.
			return;
		}

		if ( isset( $_POST['indieblocks_lat'] ) && is_numeric( $_POST['indieblocks_lat'] ) ) {
			update_post_meta( $post->ID, 'geo_latitude', round( (float) $_POST['indieblocks_lat'], 8 ) );
		}

		if ( isset( $_POST['indieblocks_lon'] ) && is_numeric( $_POST['indieblocks_lon'] ) ) {
			update_post_meta( $post->ID, 'geo_longitude', round( (float) $_POST['indieblocks_lon'], 8 ) );
		}

		if ( ! empty( $_POST['indieblocks_address'] ) ) {
			update_post_meta( $post->ID, 'geo_address', sanitize_text_field( wp_unslash( $_POST['indieblocks_address'] ) ) );
		}
	}

	/**
	 * Cleans up location metadata.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function set_location( $new_status, $old_status, $post ) {
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ), true ) ) {
			// Unsupported post type.
			return;
		}

		// Saved previously.
		$lat = get_post_meta( $post->ID, 'geo_latitude', true );
		$lon = get_post_meta( $post->ID, 'geo_longitude', true );

		if ( '' === $lat || '' === $lon ) {
			// Nothing to do.
			return;
		}

		// Adds address, or rather, city/town data.
		if ( '' === get_post_meta( $post->ID, 'geo_address', true ) ) {
			// Okay, so we've got coordinates but no name; let's change that.
			$geo_address = static::get_address( $lat, $lon );

			if ( ! empty( $geo_address ) ) {
				// Add town and country metadata.
				update_post_meta( $post->ID, 'geo_address', $geo_address );
			}
		}

		// Adds weather data.
		if ( static::is_recent( $post ) && '' === get_post_meta( $post->ID, '_indieblocks_weather', true ) ) {
			// Let's do weather information, too.
			$weather = static::get_weather( $lat, $lon );

			if ( ! empty( $weather ) ) {
				update_post_meta( $post->ID, '_indieblocks_weather', $weather ); // Will be an associated array.
			}
		}
	}

	/**
	 * Given a latitude and longitude, returns address data (i.e., reverse
	 * geolocation).
	 *
	 * Uses OSM's Nominatim for geocoding.
	 *
	 * @param  float $lat Latitude.
	 * @param  float $lon Longitude.
	 * @return string     (Currently) town or city.
	 */
	public static function get_address( $lat, $lon ) {
		$location = get_transient( "indieblocks_loc_{$lat}_{$lon}" );

		if ( empty( $location ) ) {
			$response = remote_get( "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lon}&zoom=18&addressdetails=1", true );

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				error_log( "Failed to retrieve address data for {$lat}, {$lon}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return '';
			}

			$location = json_decode( $response['body'], true );

			if ( empty( $location ) ) {
				error_log( "Failed to decode address data for {$lat}, {$lon}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return '';
			}

			set_transient( "indieblocks_loc_{$lat}_{$lon}", $location, WEEK_IN_SECONDS );
		}

		$geo_address = '';

		if ( ! empty( $location['address']['town'] ) ) {
			$geo_address = $location['address']['town'];
		} elseif ( ! empty( $location['address']['city'] ) ) {
			$geo_address = $location['address']['city'];
		} elseif ( ! empty( $location['address']['municipality'] ) ) {
			$geo_address = $location['address']['municipality'];
		}

		if ( ! empty( $geo_address ) && ! empty( $location['address']['country_code'] ) ) {
			$geo_address .= ', ' . strtoupper( $location['address']['country_code'] );
		}

		return sanitize_text_field( $geo_address );
	}

	/**
	 * Given a latitude and longitude, returns current weather information.
	 *
	 * @param  float $lat Latitude.
	 * @param  float $lon Longitude.
	 * @return array      Weather data.
	 */
	public static function get_weather( $lat, $lon ) {
		$weather = get_transient( "indieblocks_weather_{$lat}_{$lon}" );

		if ( empty( $weather ) ) {
			if ( ! defined( 'OPEN_WEATHER_MAP_API_KEY' ) || empty( OPEN_WEATHER_MAP_API_KEY ) ) {
				// No need to try and fetch weather data when no API key is set.
				return array();
			}

			// As of version 0.7, we no longer pass along `units=metric`, and temperatures are converted on display.
			$response = remote_get( "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid=" . OPEN_WEATHER_MAP_API_KEY, true );

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				error_log( "Failed to retrieve weather data for {$lat}, {$lon}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return array();
			}

			$weather = json_decode( $response['body'], true );

			if ( empty( $weather ) ) {
				error_log( "Failed to decode weather data for {$lat}, {$lon}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return array();
			}

			// Valid JSON. Store response for half an hour.
			set_transient( "indieblocks_weather_{$lat}_{$lon}", $weather, HOUR_IN_SECONDS / 2 );
		}

		$weather_data = array();

		$weather_data['temperature'] = isset( $weather['main']['temp'] ) && is_numeric( $weather['main']['temp'] )
			? round( $weather['main']['temp'], 2 )
			: null;

		$weather_data['humidity'] = isset( $weather['main']['humidity'] ) && is_numeric( $weather['main']['humidity'] )
			? round( $weather['main']['humidity'] )
			: null;

		if ( ! empty( $weather['weather'] ) ) {
			$weather = ( (array) $weather['weather'] )[0];

			$weather_data['id'] = isset( $weather['id'] ) && is_int( $weather['id'] )
				? static::icon_map( (int) $weather['id'] )
				: '';

			$weather_data['description'] = isset( $weather['description'] )
				? ucfirst( sanitize_text_field( $weather['description'] ) )
				: '';
		}

		return array_filter( $weather_data ); // Removes empty values.
	}

	/**
	 * Maps OpenWeather's IDs to SVG icons. Kindly borrowed from the Simple
	 * Location plugin by David Shanske.
	 *
	 * @link https://github.com/dshanske/simple-location
	 *
	 * @param int $id OpenWeather ID.
	 */
	public static function icon_map( $id ) {
		if ( in_array( $id, array( 200, 201, 202, 230, 231, 232 ), true ) ) {
			return 'wi-thunderstorm';
		}

		if ( in_array( $id, array( 210, 211, 212, 221 ), true ) ) {
			return 'wi-lightning';
		}

		if ( in_array( $id, array( 300, 301, 321, 500 ), true ) ) {
			return 'wi-sprinkle';
		}

		if ( in_array( $id, array( 302, 311, 312, 314, 501, 502, 503, 504 ), true ) ) {
			return 'wi-rain';
		}

		if ( in_array( $id, array( 310, 511, 611, 612, 615, 616, 620 ), true ) ) {
			return 'wi-rain-mix';
		}

		if ( in_array( $id, array( 313, 520, 521, 522, 701 ), true ) ) {
			return 'wi-showers';
		}

		if ( in_array( $id, array( 531, 901 ), true ) ) {
			return 'wi-storm-showers';
		}

		if ( in_array( $id, array( 600, 601, 621, 622 ), true ) ) {
			return 'wi-snow';
		}

		if ( in_array( $id, array( 602 ), true ) ) {
			return 'wi-sleet';
		}

		if ( in_array( $id, array( 711 ), true ) ) {
			return 'wi-smoke';
		}

		if ( in_array( $id, array( 721 ), true ) ) {
			return 'wi-day-haze';
		}

		if ( in_array( $id, array( 731, 761 ), true ) ) {
			return 'wi-dust';
		}

		if ( in_array( $id, array( 741 ), true ) ) {
			return 'wi-fog';
		}

		if ( in_array( $id, array( 771, 801, 802, 803 ), true ) ) {
			return 'wi-cloudy-gusts';
		}

		if ( in_array( $id, array( 781, 900 ), true ) ) {
			return 'wi-tornado';
		}

		if ( in_array( $id, array( 800 ), true ) ) {
			return 'wi-day-sunny';
		}

		if ( in_array( $id, array( 804 ), true ) ) {
			return 'wi-cloudy';
		}

		if ( in_array( $id, array( 902, 962 ), true ) ) {
			return 'wi-hurricane';
		}

		if ( in_array( $id, array( 903 ), true ) ) {
			return 'wi-snowflake-cold';
		}

		if ( in_array( $id, array( 904 ), true ) ) {
			return 'wi-hot';
		}

		if ( in_array( $id, array( 905 ), true ) ) {
			return 'wi-windy';
		}

		if ( in_array( $id, array( 906 ), true ) ) {
			return 'wi-day-hail';
		}

		if ( in_array( $id, array( 957 ), true ) ) {
			return 'wi-strong-wind';
		}

		if ( in_array( $id, array( 762 ), true ) ) {
			return 'wi-volcano';
		}

		if ( in_array( $id, array( 751 ), true ) ) {
			return 'wi-sandstorm';
		}

		return '';
	}

	/**
	 * Registers (block-related) REST API endpoints.
	 *
	 * @todo: (Eventually) also add an "author" endpoint. Or have the single endpoint return both title and author information.
	 */
	public static function register_meta() {
		$post_types = (array) apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'geo_address',
				array(
					'single'       => true,
					'show_in_rest' => array(
						'prepare_callback' => function( $value ) {
							// `return $value` would've sufficed, too. The funny thing is WP doesn't actually fetch and unserialize `$value` if this isn't here.
							return maybe_unserialize( $value );
						},
					),
					'type'         => 'object',
				)
			);

			register_post_meta(
				$post_type,
				'_indieblocks_weather',
				array(
					'single'        => true,
					'show_in_rest'  => array(
						'prepare_callback' => function( $value ) {
							// `return $value` would've sufficed, too. The funny thing is WP doesn't actually fetch and unserialize `$value` if this isn't here.
							return maybe_unserialize( $value );
						},
					),
					'type'          => 'object',
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Whether a post is new or under one hour old.
	 *
	 * @param  int|WP_Post $post The post (or `null`, which means `global $post`).
	 * @return bool              True if the post is unpublished or less than one hour old.
	 */
	protected static function is_recent( $post = null ) {
		$post_time = get_post_time( 'U', true, $post );

		return false === $post_time || time() - $post_time < HOUR_IN_SECONDS;
	}
}
