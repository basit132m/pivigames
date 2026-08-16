<?php
/**
 * Main plugin class for PiviGames Steam.
 *
 * @package PiviGames_Steam
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PiviGames_Steam
 *
 * Renders an SEO-optimized "Comprar en Steam" section from a Steam App ID.
 */
class PiviGames_Steam {

	/**
	 * Meta key storing the Steam settings for a post.
	 *
	 * @var string
	 */
	const META_KEY = '_pivigames_steam';

	/**
	 * Nonce name/action.
	 *
	 * @var string
	 */
	const NONCE = 'pivigames_steam_nonce';

	/**
	 * How long to cache Steam API responses (seconds).
	 *
	 * @var int
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var PiviGames_Steam|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return PiviGames_Steam
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin.
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Front-end. Priority 15 places the Steam section AFTER the system
		// requirements (specs plugin, priority 10) and BEFORE the download
		// links (downloads plugin, priority 20).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_filter( 'the_content', array( $this, 'append_steam_to_content' ), 15 );
		add_shortcode( 'pivigames_steam', array( $this, 'shortcode' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'pivigames-steam', false, dirname( plugin_basename( PIVIGAMES_STEAM_FILE ) ) . '/languages' );
	}

	/**
	 * Post types the box appears on.
	 *
	 * @return array
	 */
	private function post_types() {
		/**
		 * Filter which post types get the Steam meta box.
		 *
		 * @param array $post_types Post type slugs.
		 */
		return apply_filters( 'pivigames_steam_post_types', array( 'post' ) );
	}

	/**
	 * Default store country/currency code (affects price currency).
	 *
	 * @return string
	 */
	private function default_cc() {
		/**
		 * Filter the default Steam country code used for pricing.
		 *
		 * @param string $cc Two-letter country code.
		 */
		return apply_filters( 'pivigames_steam_default_cc', 'us' );
	}

	/* --------------------------------------------------------------------- *
	 * Data
	 * --------------------------------------------------------------------- */

	/**
	 * Get the saved Steam settings for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array { appid, cc, use_iframe }
	 */
	public function get_settings( $post_id ) {
		$data = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		return wp_parse_args(
			$data,
			array(
				'appid'      => '',
				'cc'         => '',
				'use_iframe' => 0,
			)
		);
	}

	/**
	 * Fetch (and cache) game details from Steam's public store API.
	 *
	 * @param string $appid App ID (digits).
	 * @param string $cc    Country code for pricing.
	 * @return array|false  Steam "data" array, or false on failure.
	 */
	public function get_steam_data( $appid, $cc ) {
		$appid = preg_replace( '/\D/', '', (string) $appid );
		if ( '' === $appid ) {
			return false;
		}
		$cc = strtolower( preg_replace( '/[^a-zA-Z]/', '', (string) $cc ) );
		if ( '' === $cc ) {
			$cc = $this->default_cc();
		}

		$transient = 'pivigames_steam_' . $appid . '_' . $cc;
		$cached    = get_transient( $transient );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : false;
		}

		$url = add_query_arg(
			array(
				'appids' => $appid,
				'cc'     => $cc,
				'l'      => 'spanish',
			),
			'https://store.steampowered.com/api/appdetails'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		$data = false;
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $body[ $appid ]['success'] ) && $body[ $appid ]['success'] && ! empty( $body[ $appid ]['data'] ) ) {
				$data = $body[ $appid ]['data'];
			}
		}

		// Cache success for the full TTL, failures briefly so we retry soon.
		set_transient( $transient, $data ? $data : array(), $data ? self::CACHE_TTL : ( 15 * MINUTE_IN_SECONDS ) );

		return $data ? $data : false;
	}

	/**
	 * Steam store page URL for an app.
	 *
	 * @param string $appid App ID.
	 * @return string
	 */
	private function store_url( $appid ) {
		return 'https://store.steampowered.com/app/' . rawurlencode( $appid ) . '/';
	}

	/* --------------------------------------------------------------------- *
	 * Admin
	 * --------------------------------------------------------------------- */

	/**
	 * Register the meta box.
	 */
	public function register_meta_box() {
		foreach ( $this->post_types() as $post_type ) {
			add_meta_box(
				'pivigames_steam',
				esc_html__( 'Comprar en Steam / Buy on Steam', 'pivigames-steam' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$settings = $this->get_settings( $post->ID );
		?>
		<p class="pivigames-steam-field">
			<label for="pivigames_steam_appid">
				<strong><?php esc_html_e( 'Steam App ID', 'pivigames-steam' ); ?></strong>
			</label>
			<input
				type="text"
				inputmode="numeric"
				class="widefat"
				id="pivigames_steam_appid"
				name="pivigames_steam[appid]"
				value="<?php echo esc_attr( $settings['appid'] ); ?>"
				placeholder="2670630"
			/>
			<span class="description">
				<?php esc_html_e( 'El número de la URL de Steam: store.steampowered.com/app/', 'pivigames-steam' ); ?><strong>2670630</strong>/
			</span>
		</p>

		<p class="pivigames-steam-field">
			<label for="pivigames_steam_cc">
				<strong><?php esc_html_e( 'País / moneda del precio', 'pivigames-steam' ); ?></strong>
			</label>
			<input
				type="text"
				class="small-text"
				id="pivigames_steam_cc"
				name="pivigames_steam[cc]"
				value="<?php echo esc_attr( $settings['cc'] ); ?>"
				maxlength="2"
				placeholder="<?php echo esc_attr( $this->default_cc() ); ?>"
			/>
			<span class="description">
				<?php esc_html_e( 'Código de 2 letras: us, es, mx, ar, cl…', 'pivigames-steam' ); ?>
			</span>
		</p>

		<p class="pivigames-steam-field">
			<label>
				<input
					type="checkbox"
					name="pivigames_steam[use_iframe]"
					value="1"
					<?php checked( ! empty( $settings['use_iframe'] ) ); ?>
				/>
				<?php esc_html_e( 'Usar el widget oficial (iframe)', 'pivigames-steam' ); ?>
			</label>
			<span class="description">
				<?php esc_html_e( 'La tarjeta nativa (desmarcado) es mejor para SEO.', 'pivigames-steam' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Save the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		$raw   = isset( $_POST['pivigames_steam'] ) && is_array( $_POST['pivigames_steam'] )
			? wp_unslash( $_POST['pivigames_steam'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
			: array();

		$appid = isset( $raw['appid'] ) ? preg_replace( '/\D/', '', $raw['appid'] ) : '';

		if ( '' === $appid ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		$cc = isset( $raw['cc'] ) ? strtolower( preg_replace( '/[^a-zA-Z]/', '', $raw['cc'] ) ) : '';
		$cc = substr( $cc, 0, 2 );

		$data = array(
			'appid'      => $appid,
			'cc'         => $cc,
			'use_iframe' => ! empty( $raw['use_iframe'] ) ? 1 : 0,
		);

		update_post_meta( $post_id, self::META_KEY, $data );

		// Clear any cached API data for this app so the front-end refreshes.
		delete_transient( 'pivigames_steam_' . $appid . '_' . ( $cc ? $cc : $this->default_cc() ) );
	}

	/* --------------------------------------------------------------------- *
	 * Assets
	 * --------------------------------------------------------------------- */

	/**
	 * Enqueue admin CSS.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && ! in_array( $screen->post_type, $this->post_types(), true ) ) {
			return;
		}
		wp_enqueue_style(
			'pivigames-steam-admin',
			PIVIGAMES_STEAM_URL . 'assets/css/admin.css',
			array(),
			PIVIGAMES_STEAM_VERSION
		);
	}

	/**
	 * Enqueue front-end CSS.
	 */
	public function enqueue_front_assets() {
		wp_enqueue_style(
			'pivigames-steam',
			PIVIGAMES_STEAM_URL . 'assets/css/pivigames-steam.css',
			array(),
			PIVIGAMES_STEAM_VERSION
		);
	}

	/* --------------------------------------------------------------------- *
	 * Front-end
	 * --------------------------------------------------------------------- */

	/**
	 * Append the Steam section to single-post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_steam_to_content( $content ) {
		if ( ! is_singular( $this->post_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$output = $this->render( get_the_ID() );
		if ( '' === $output ) {
			return $content;
		}
		return $content . $output;
	}

	/**
	 * Shortcode: [pivigames_steam id="123"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'pivigames_steam' );
		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return '';
		}
		return $this->render( $post_id );
	}

	/**
	 * Build the Steam section HTML for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render( $post_id ) {
		$settings = $this->get_settings( $post_id );
		$appid    = $settings['appid'];
		if ( '' === $appid ) {
			return '';
		}

		$store_url = $this->store_url( $appid );
		$data      = $this->get_steam_data( $appid, $settings['cc'] );

		$title = ( $data && ! empty( $data['name'] ) ) ? $data['name'] : get_the_title( $post_id );

		$heading = '<h2 class="pivigames-steam__title">' . esc_html__( 'Comprar en Steam', 'pivigames-steam' ) . '</h2>';

		$schema = $this->build_schema( $data, $appid, $store_url, $post_id );

		if ( ! empty( $settings['use_iframe'] ) ) {
			$body = $this->render_iframe( $appid, $title, $store_url );
		} elseif ( $data ) {
			$body = $this->render_card( $data, $appid, $store_url );
		} else {
			// API unavailable — still output a crawlable heading + button.
			$body = $this->render_fallback( $title, $store_url );
		}

		return '<div class="pivigames-steam">' . $heading . $body . $schema . '</div>';
	}

	/**
	 * Render the native Steam card.
	 *
	 * @param array  $data      Steam data array.
	 * @param string $appid     App ID.
	 * @param string $store_url Store URL.
	 * @return string
	 */
	private function render_card( $data, $appid, $store_url ) {
		$name        = ! empty( $data['name'] ) ? $data['name'] : '';
		$description = ! empty( $data['short_description'] ) ? wp_strip_all_tags( $data['short_description'] ) : '';
		$image       = ! empty( $data['header_image'] ) ? $data['header_image'] : '';

		// Platforms.
		$platforms = '';
		if ( ! empty( $data['platforms']['windows'] ) ) {
			$platforms .= $this->platform_icon( 'windows' );
		}
		if ( ! empty( $data['platforms']['mac'] ) ) {
			$platforms .= $this->platform_icon( 'mac' );
		}
		if ( ! empty( $data['platforms']['linux'] ) ) {
			$platforms .= $this->platform_icon( 'linux' );
		}

		// Price.
		$price_html = '';
		if ( ! empty( $data['is_free'] ) ) {
			$price_html = '<span class="pivigames-steam__price">' . esc_html__( 'Gratis', 'pivigames-steam' ) . '</span>';
		} elseif ( ! empty( $data['price_overview']['final_formatted'] ) ) {
			$discount = '';
			if ( ! empty( $data['price_overview']['discount_percent'] ) ) {
				$discount = '<span class="pivigames-steam__discount">-' . esc_html( $data['price_overview']['discount_percent'] ) . '%</span>';
			}
			$price_html = $discount . '<span class="pivigames-steam__price">' . esc_html( $data['price_overview']['final_formatted'] ) . '</span>';
		}

		$image_html = '';
		if ( $image ) {
			$image_html = sprintf(
				'<img class="pivigames-steam__img" src="%1$s" alt="%2$s" loading="lazy" decoding="async" />',
				esc_url( $image ),
				esc_attr( $name )
			);
		}

		$platforms_html = $platforms ? '<div class="pivigames-steam__platforms">' . $platforms . '</div>' : '';

		return '<div class="pivigames-steam__card">'
			. '<div class="pivigames-steam__head">'
				. '<span class="pivigames-steam__game">' . esc_html( $name ) . '</span> '
				. '<span class="pivigames-steam__on">' . esc_html__( 'en Steam', 'pivigames-steam' ) . '</span>'
				. '<span class="pivigames-steam__logo" aria-hidden="true">' . $this->steam_logo() . '</span>'
			. '</div>'
			. '<div class="pivigames-steam__body">'
				. ( $image_html ? '<a class="pivigames-steam__thumb" href="' . esc_url( $store_url ) . '" target="_blank" rel="nofollow noopener">' . $image_html . '</a>' : '' )
				. '<p class="pivigames-steam__desc">' . esc_html( $description ) . '</p>'
			. '</div>'
			. '<div class="pivigames-steam__foot">'
				. $platforms_html
				. '<div class="pivigames-steam__buy">'
					. ( $price_html ? '<span class="pivigames-steam__pricebox">' . $price_html . '</span>' : '' )
					. sprintf(
						'<a class="pivigames-steam__btn" href="%1$s" target="_blank" rel="nofollow noopener">%2$s</a>',
						esc_url( $store_url ),
						esc_html__( 'Comprar en Steam', 'pivigames-steam' )
					)
				. '</div>'
			. '</div>'
		. '</div>';
	}

	/**
	 * Render the official iframe widget (with a crawlable fallback link).
	 *
	 * @param string $appid     App ID.
	 * @param string $title     Game title.
	 * @param string $store_url Store URL.
	 * @return string
	 */
	private function render_iframe( $appid, $title, $store_url ) {
		$src = 'https://store.steampowered.com/widget/' . rawurlencode( $appid ) . '/';
		return '<div class="pivigames-steam__iframe-wrap">'
			. sprintf(
				'<iframe class="pivigames-steam__iframe" src="%1$s" title="%2$s" loading="lazy" frameborder="0" width="646" height="190"></iframe>',
				esc_url( $src ),
				esc_attr( $title )
			)
			. '</div>'
			. sprintf(
				'<p class="pivigames-steam__link"><a href="%1$s" target="_blank" rel="nofollow noopener">%2$s</a></p>',
				esc_url( $store_url ),
				sprintf( esc_html__( 'Comprar %s en Steam', 'pivigames-steam' ), esc_html( $title ) )
			);
	}

	/**
	 * Render a minimal fallback when the Steam API is unavailable.
	 *
	 * @param string $title     Game title.
	 * @param string $store_url Store URL.
	 * @return string
	 */
	private function render_fallback( $title, $store_url ) {
		return '<div class="pivigames-steam__card pivigames-steam__card--min">'
			. '<div class="pivigames-steam__foot">'
				. '<div class="pivigames-steam__buy">'
					. sprintf(
						'<a class="pivigames-steam__btn" href="%1$s" target="_blank" rel="nofollow noopener">%2$s</a>',
						esc_url( $store_url ),
						sprintf( esc_html__( 'Comprar %s en Steam', 'pivigames-steam' ), esc_html( $title ) )
					)
				. '</div>'
			. '</div>'
		. '</div>';
	}

	/**
	 * Build schema.org VideoGame JSON-LD.
	 *
	 * @param array|false $data      Steam data (or false).
	 * @param string      $appid     App ID.
	 * @param string      $store_url Store URL.
	 * @param int         $post_id   Post ID.
	 * @return string
	 */
	private function build_schema( $data, $appid, $store_url, $post_id ) {
		$name = ( $data && ! empty( $data['name'] ) ) ? $data['name'] : get_the_title( $post_id );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'VideoGame',
			'name'     => $name,
			'url'      => get_permalink( $post_id ),
		);

		if ( $data ) {
			if ( ! empty( $data['short_description'] ) ) {
				$schema['description'] = wp_strip_all_tags( $data['short_description'] );
			}
			if ( ! empty( $data['header_image'] ) ) {
				$schema['image'] = $data['header_image'];
			}
			$os = array();
			if ( ! empty( $data['platforms']['windows'] ) ) {
				$os[] = 'Windows';
			}
			if ( ! empty( $data['platforms']['mac'] ) ) {
				$os[] = 'macOS';
			}
			if ( ! empty( $data['platforms']['linux'] ) ) {
				$os[] = 'Linux';
			}
			if ( $os ) {
				$schema['operatingSystem'] = implode( ', ', $os );
			}
			if ( ! empty( $data['developers'] ) && is_array( $data['developers'] ) ) {
				$schema['author'] = array(
					'@type' => 'Organization',
					'name'  => reset( $data['developers'] ),
				);
			}
			if ( ! empty( $data['publishers'] ) && is_array( $data['publishers'] ) ) {
				$schema['publisher'] = array(
					'@type' => 'Organization',
					'name'  => reset( $data['publishers'] ),
				);
			}
			if ( ! empty( $data['genres'] ) && is_array( $data['genres'] ) ) {
				$genres = wp_list_pluck( $data['genres'], 'description' );
				if ( $genres ) {
					$schema['genre'] = array_values( array_filter( $genres ) );
				}
			}

			// Offer.
			if ( ! empty( $data['is_free'] ) ) {
				$schema['offers'] = array(
					'@type'         => 'Offer',
					'price'         => '0',
					'priceCurrency' => 'USD',
					'availability'  => 'https://schema.org/InStock',
					'url'           => $store_url,
				);
			} elseif ( ! empty( $data['price_overview']['final'] ) ) {
				$schema['offers'] = array(
					'@type'         => 'Offer',
					'price'         => number_format( $data['price_overview']['final'] / 100, 2, '.', '' ),
					'priceCurrency' => ! empty( $data['price_overview']['currency'] ) ? $data['price_overview']['currency'] : 'USD',
					'availability'  => 'https://schema.org/InStock',
					'url'           => $store_url,
				);
			}
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! $json ) {
			return '';
		}

		return '<script type="application/ld+json">' . $json . '</script>';
	}

	/* --------------------------------------------------------------------- *
	 * Small inline SVG helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Platform icon SVG.
	 *
	 * @param string $platform windows|mac|linux.
	 * @return string
	 */
	private function platform_icon( $platform ) {
		$icons = array(
			'windows' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 5.7 10.2 4.7v6.8H3zM11.4 4.5 21 3.2v8.3h-9.6zM3 12.5h7.2v6.8L3 18.3zM11.4 12.5H21v8.3l-9.6-1.3z"/></svg>',
			'mac'     => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M16.4 12.9c0-2 1.6-2.9 1.7-3-1-1.4-2.4-1.6-2.9-1.6-1.2-.1-2.4.7-3 .7-.6 0-1.6-.7-2.6-.7-1.3 0-2.6.8-3.3 2-1.4 2.5-.4 6.1 1 8.1.7 1 1.4 2.1 2.5 2 1-.1 1.3-.6 2.5-.6s1.5.6 2.6.6 1.7-1 2.4-2c.7-1.1 1-2.2 1-2.3-.1 0-2-.8-2-3zM14.5 6.7c.6-.7 1-1.6.9-2.6-.8 0-1.9.6-2.5 1.3-.5.6-1 1.5-.9 2.5.9.1 1.8-.5 2.5-1.2z"/></svg>',
			'linux'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2c-1.7 0-3 1.5-3 3.4 0 .9 0 1.9-.5 2.8-.7 1.2-2 2.4-2 4.4 0 .8.2 1.4.2 2-.3.6-1 1.2-1 2.2 0 1.6 1.8 2.3 3.6 2.6 1 .2 1.4.8 2.6.8s1.6-.6 2.6-.8c1.8-.3 3.6-1 3.6-2.6 0-1-.7-1.6-1-2.2 0-.6.2-1.2.2-2 0-2-1.3-3.2-2-4.4-.5-.9-.5-1.9-.5-2.8C15 3.5 13.7 2 12 2z"/></svg>',
		);
		return isset( $icons[ $platform ] ) ? '<span class="pivigames-steam__pf pivigames-steam__pf--' . esc_attr( $platform ) . '">' . $icons[ $platform ] . '</span>' : '';
	}

	/**
	 * Steam logo SVG (simplified).
	 *
	 * @return string
	 */
	private function steam_logo() {
		return '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-9.9 8.7l5.3 2.2a2.8 2.8 0 0 1 1.6-.5h.1l2.4-3.4v-.1a3.8 3.8 0 1 1 3.8 3.8h-.1l-3.4 2.4v.1a2.8 2.8 0 0 1-5.5.8L2 14.8A10 10 0 1 0 12 2zm-3.9 15.2-1.2-.5a2.1 2.1 0 0 0 3.9-1 2.1 2.1 0 0 0-2.7-2l1.3.5a1.5 1.5 0 1 1-1.3 2.7zM17.3 8.4a2.5 2.5 0 1 1-2.5 2.5 2.5 2.5 0 0 1 2.5-2.5zm0 .9a1.6 1.6 0 1 0 1.6 1.6 1.6 1.6 0 0 0-1.6-1.6z"/></svg>';
	}
}
