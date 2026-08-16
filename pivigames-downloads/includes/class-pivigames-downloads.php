<?php
/**
 * Main plugin class for PiviGames Downloads.
 *
 * @package PiviGames_Downloads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PiviGames_Downloads
 *
 * Repeatable download links meta box + front-end rendering.
 */
class PiviGames_Downloads {

	/**
	 * Meta key storing the array of download rows.
	 *
	 * @var string
	 */
	const META_KEY = '_pivigames_downloads';

	/**
	 * Nonce name/action.
	 *
	 * @var string
	 */
	const NONCE = 'pivigames_downloads_nonce';

	/**
	 * Default button label used when a row leaves the button text empty.
	 *
	 * @var string
	 */
	const DEFAULT_BUTTON = 'Descargar ahora';

	/**
	 * Singleton instance.
	 *
	 * @var PiviGames_Downloads|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return PiviGames_Downloads
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

		// Front-end. Priority 20 places downloads AFTER the specs plugin's
		// system-requirements block (which appends at priority 10).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_filter( 'the_content', array( $this, 'append_downloads_to_content' ), 20 );
		add_shortcode( 'pivigames_downloads', array( $this, 'shortcode' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'pivigames-downloads', false, dirname( plugin_basename( PIVIGAMES_DOWNLOADS_FILE ) ) . '/languages' );
	}

	/**
	 * Post types the download box appears on.
	 *
	 * @return array
	 */
	private function post_types() {
		/**
		 * Filter which post types get the downloads meta box.
		 *
		 * @param array $post_types Post type slugs.
		 */
		return apply_filters( 'pivigames_downloads_post_types', array( 'post' ) );
	}

	/**
	 * Field definitions for a single download row.
	 *
	 * @return array
	 */
	private function fields() {
		return array(
			'source' => array(
				'es'          => 'Fuente de descarga',
				'en'          => 'Download Source',
				'placeholder' => 'MediaFire',
			),
			'size'   => array(
				'es'          => 'Tamaño',
				'en'          => 'Size',
				'placeholder' => '752 MB',
			),
			'button' => array(
				'es'          => 'Texto del botón',
				'en'          => 'Button Text',
				'placeholder' => self::DEFAULT_BUTTON,
			),
			'url'    => array(
				'es'          => 'Enlace de descarga',
				'en'          => 'Download URL',
				'placeholder' => 'https://...',
			),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Data
	 * --------------------------------------------------------------------- */

	/**
	 * Get saved download rows for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of rows.
	 */
	public function get_downloads( $post_id ) {
		$rows = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $rows ) ? $rows : array();
	}

	/* --------------------------------------------------------------------- *
	 * Admin: meta box
	 * --------------------------------------------------------------------- */

	/**
	 * Register the meta box.
	 */
	public function register_meta_box() {
		foreach ( $this->post_types() as $post_type ) {
			add_meta_box(
				'pivigames_downloads',
				esc_html__( 'Descargas / Downloads', 'pivigames-downloads' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
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
		$rows = $this->get_downloads( $post->ID );
		?>
		<p class="pivigames-help">
			<?php esc_html_e( 'Añade uno o más enlaces de descarga. Puedes ordenar por fuente (MediaFire, MEGA, Google Drive, etc.). Las filas sin enlace se descartan. / Add one or more download links (MediaFire, MEGA, Google Drive…). Rows without a URL are discarded.', 'pivigames-downloads' ); ?>
		</p>

		<div id="pivigames-downloads-rows">
			<?php
			if ( empty( $rows ) ) {
				// Render one empty starter row.
				echo $this->row_html( 0, array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside row_html().
			} else {
				$i = 0;
				foreach ( $rows as $row ) {
					echo $this->row_html( $i, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside row_html().
					$i++;
				}
			}
			?>
		</div>

		<p>
			<button type="button" class="button button-secondary" id="pivigames-add-download">
				<?php esc_html_e( '+ Añadir descarga / Add download', 'pivigames-downloads' ); ?>
			</button>
		</p>

		<script type="text/html" id="pivigames-download-template">
			<?php echo $this->row_html( '__index__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside row_html(). ?>
		</script>
		<?php
	}

	/**
	 * Build the HTML for a single download row.
	 *
	 * @param int|string $index Row index (or __index__ placeholder for the JS template).
	 * @param array      $row   Saved row values.
	 * @return string
	 */
	private function row_html( $index, $row ) {
		$fields = $this->fields();
		$name   = 'pivigames_downloads[' . $index . ']';

		ob_start();
		?>
		<div class="pivigames-download-row">
			<div class="pivigames-download-fields">
				<?php foreach ( $fields as $key => $field ) : ?>
					<?php
					$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
					$type  = ( 'url' === $key ) ? 'url' : 'text';
					?>
					<label class="pivigames-download-field pivigames-download-field--<?php echo esc_attr( $key ); ?>">
						<span class="pivigames-download-label">
							<?php echo esc_html( $field['es'] ); ?>
							<span class="pivigames-en"><?php echo esc_html( $field['en'] ); ?></span>
						</span>
						<input
							type="<?php echo esc_attr( $type ); ?>"
							name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
						/>
					</label>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button-link pivigames-remove-download" aria-label="<?php esc_attr_e( 'Eliminar / Remove', 'pivigames-downloads' ); ?>">
				<?php esc_html_e( 'Eliminar / Remove', 'pivigames-downloads' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * Admin: saving
	 * --------------------------------------------------------------------- */

	/**
	 * Save the download rows.
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

		$clean = array();

		if ( isset( $_POST['pivigames_downloads'] ) && is_array( $_POST['pivigames_downloads'] ) ) {
			$raw = wp_unslash( $_POST['pivigames_downloads'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.

			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$url = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';

				// Skip rows with no valid URL — the link is what makes a row useful.
				if ( '' === $url ) {
					continue;
				}

				$clean[] = array(
					'source' => isset( $row['source'] ) ? sanitize_text_field( $row['source'] ) : '',
					'size'   => isset( $row['size'] ) ? sanitize_text_field( $row['size'] ) : '',
					'button' => isset( $row['button'] ) ? sanitize_text_field( $row['button'] ) : '',
					'url'    => $url,
				);
			}
		}

		if ( ! empty( $clean ) ) {
			update_post_meta( $post_id, self::META_KEY, $clean );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Assets
	 * --------------------------------------------------------------------- */

	/**
	 * Enqueue admin CSS/JS on post edit screens.
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
			'pivigames-downloads-admin',
			PIVIGAMES_DOWNLOADS_URL . 'assets/css/admin.css',
			array(),
			PIVIGAMES_DOWNLOADS_VERSION
		);
		wp_enqueue_script(
			'pivigames-downloads-admin',
			PIVIGAMES_DOWNLOADS_URL . 'assets/js/admin.js',
			array(),
			PIVIGAMES_DOWNLOADS_VERSION,
			true
		);
	}

	/**
	 * Enqueue front-end CSS.
	 */
	public function enqueue_front_assets() {
		wp_enqueue_style(
			'pivigames-downloads',
			PIVIGAMES_DOWNLOADS_URL . 'assets/css/pivigames-downloads.css',
			array(),
			PIVIGAMES_DOWNLOADS_VERSION
		);
	}

	/* --------------------------------------------------------------------- *
	 * Front-end
	 * --------------------------------------------------------------------- */

	/**
	 * Append the downloads section to single-post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_downloads_to_content( $content ) {
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
	 * Shortcode: [pivigames_downloads id="123"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'pivigames_downloads' );
		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return '';
		}
		return $this->render( $post_id );
	}

	/**
	 * Build the download section HTML for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render( $post_id ) {
		$rows = $this->get_downloads( $post_id );
		if ( empty( $rows ) ) {
			return '';
		}

		// Inline download icon (arrow into tray) shown on the left of the button.
		$icon = '<svg class="pivigames-downloads__icon" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 3a1 1 0 0 1 1 1v8.586l2.293-2.293a1 1 0 0 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L11 12.586V4a1 1 0 0 1 1-1zM5 18a1 1 0 0 1 1-1h12a1 1 0 0 1 0 2H6a1 1 0 0 1-1-1z"/></svg>';

		$items = '';
		foreach ( $rows as $row ) {
			if ( empty( $row['url'] ) ) {
				continue;
			}

			$button = ( ! empty( $row['button'] ) ) ? $row['button'] : self::DEFAULT_BUTTON;

			// Optional subtitle inside the button: "Fuente · Tamaño".
			$sub_parts = array();
			if ( ! empty( $row['source'] ) ) {
				$sub_parts[] = $row['source'];
			}
			if ( ! empty( $row['size'] ) ) {
				$sub_parts[] = $row['size'];
			}
			$sub = '';
			if ( ! empty( $sub_parts ) ) {
				$sub = '<span class="pivigames-downloads__btn-sub">' . esc_html( implode( ' · ', $sub_parts ) ) . '</span>';
			}

			$items .= sprintf(
				'<a class="pivigames-downloads__btn" href="%1$s" target="_blank" rel="nofollow noopener">%2$s<span class="pivigames-downloads__btn-text"><span class="pivigames-downloads__btn-label">%3$s</span>%4$s</span></a>',
				esc_url( $row['url'] ),
				$icon,
				esc_html( $button ),
				$sub
			);
		}

		if ( '' === $items ) {
			return '';
		}

		return '<div class="pivigames-downloads">'
			. '<h2 class="pivigames-downloads__title">' . esc_html__( 'Descargas', 'pivigames-downloads' ) . '</h2>'
			. '<div class="pivigames-downloads__list">' . $items . '</div>'
			. '</div>';
	}
}
