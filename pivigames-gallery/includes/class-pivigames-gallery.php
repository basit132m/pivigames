<?php
/**
 * Main plugin class for PiviGames Gallery.
 *
 * @package PiviGames_Gallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PiviGames_Gallery
 *
 * "Capturas" screenshots gallery with a Nintendo-style zoom lightbox.
 */
class PiviGames_Gallery {

	/**
	 * Meta key storing the array of attachment IDs.
	 *
	 * @var string
	 */
	const META_KEY = '_pivigames_gallery';

	/**
	 * Nonce name/action.
	 *
	 * @var string
	 */
	const NONCE = 'pivigames_gallery_nonce';

	/**
	 * Singleton instance.
	 *
	 * @var PiviGames_Gallery|null
	 */
	private static $instance = null;

	/**
	 * Whether the front-end assets have been enqueued this request.
	 *
	 * @var bool
	 */
	private $assets_done = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return PiviGames_Gallery
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

		// AJAX save (fires as soon as images are added/removed/reordered, so the
		// gallery persists even if the classic meta box form isn't submitted).
		add_action( 'wp_ajax_pivigames_gallery_save', array( $this, 'ajax_save' ) );

		// Front-end. Priority 5 places the screenshots BEFORE the requirements
		// (10), Steam (15) and downloads (20) blocks.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
		add_filter( 'the_content', array( $this, 'append_gallery_to_content' ), 5 );
		add_shortcode( 'pivigames_gallery', array( $this, 'shortcode' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'pivigames-gallery', false, dirname( plugin_basename( PIVIGAMES_GALLERY_FILE ) ) . '/languages' );
	}

	/**
	 * Post types the gallery box appears on.
	 *
	 * @return array
	 */
	private function post_types() {
		/**
		 * Filter which post types get the gallery meta box.
		 *
		 * @param array $post_types Post type slugs.
		 */
		return apply_filters( 'pivigames_gallery_post_types', array( 'post' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Data
	 * --------------------------------------------------------------------- */

	/**
	 * Get saved attachment IDs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public function get_ids( $post_id ) {
		$ids = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
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
				'pivigames_gallery',
				esc_html__( 'Capturas / Screenshots', 'pivigames-gallery' ),
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
		$ids = $this->get_ids( $post->ID );
		?>
		<p class="pivigames-help">
			<?php esc_html_e( 'Selecciona las capturas (recomendado 1280x720 px). Arrastra para reordenar. / Select the screenshots (1280x720 px recommended). Drag to reorder.', 'pivigames-gallery' ); ?>
		</p>

		<ul id="pivigames-gallery-list" class="pivigames-gallery-list" data-post="<?php echo esc_attr( $post->ID ); ?>">
			<?php foreach ( $ids as $id ) : ?>
				<?php $thumb = wp_get_attachment_image_url( $id, 'thumbnail' ); ?>
				<?php if ( $thumb ) : ?>
					<li class="pivigames-gi" data-id="<?php echo esc_attr( $id ); ?>">
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" />
						<button type="button" class="pivigames-gi__remove" aria-label="<?php esc_attr_e( 'Quitar / Remove', 'pivigames-gallery' ); ?>">&times;</button>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>

		<input type="hidden" id="pivigames_gallery" name="pivigames_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" />

		<p>
			<button type="button" class="button button-secondary" id="pivigames-gallery-add">
				<?php esc_html_e( '+ Añadir imágenes / Add images', 'pivigames-gallery' ); ?>
			</button>
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

		$raw = isset( $_POST['pivigames_gallery'] ) ? sanitize_text_field( wp_unslash( $_POST['pivigames_gallery'] ) ) : '';
		$ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );

		if ( ! empty( $ids ) ) {
			update_post_meta( $post_id, self::META_KEY, $ids );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Enqueue admin assets on post edit screens.
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

		wp_enqueue_media();
		wp_enqueue_style(
			'pivigames-gallery-admin',
			PIVIGAMES_GALLERY_URL . 'assets/css/admin.css',
			array(),
			PIVIGAMES_GALLERY_VERSION
		);
		wp_enqueue_script(
			'pivigames-gallery-admin',
			PIVIGAMES_GALLERY_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			PIVIGAMES_GALLERY_VERSION,
			true
		);
		wp_localize_script(
			'pivigames-gallery-admin',
			'pivigamesGallery',
			array(
				'title'   => esc_html__( 'Seleccionar capturas', 'pivigames-gallery' ),
				'button'  => esc_html__( 'Usar estas imágenes', 'pivigames-gallery' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pivigames_gallery_ajax' ),
			)
		);
	}

	/**
	 * AJAX handler: persist the gallery IDs immediately on change.
	 */
	public function ajax_save() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! check_ajax_referer( 'pivigames_gallery_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
		$ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );

		if ( ! empty( $ids ) ) {
			update_post_meta( $post_id, self::META_KEY, $ids );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}

		wp_send_json_success( array( 'ids' => $ids ) );
	}

	/* --------------------------------------------------------------------- *
	 * Front-end
	 * --------------------------------------------------------------------- */

	/**
	 * Register front assets and enqueue them in the head when the current
	 * singular post actually contains a gallery (saved images or shortcode).
	 */
	public function register_front_assets() {
		wp_register_style(
			'pivigames-gallery',
			PIVIGAMES_GALLERY_URL . 'assets/css/pivigames-gallery.css',
			array(),
			PIVIGAMES_GALLERY_VERSION
		);
		wp_register_script(
			'pivigames-gallery',
			PIVIGAMES_GALLERY_URL . 'assets/js/pivigames-gallery.js',
			array(),
			PIVIGAMES_GALLERY_VERSION,
			true
		);
		wp_localize_script(
			'pivigames-gallery',
			'pivigamesGalleryL10n',
			array(
				'close' => esc_html__( 'Cerrar', 'pivigames-gallery' ),
				'prev'  => esc_html__( 'Anterior', 'pivigames-gallery' ),
				'next'  => esc_html__( 'Siguiente', 'pivigames-gallery' ),
				'zoom'  => esc_html__( 'Clic para ampliar', 'pivigames-gallery' ),
			)
		);

		if ( ! is_singular( $this->post_types() ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$has_gallery = ! empty( $this->get_ids( $post->ID ) ) || has_shortcode( $post->post_content, 'pivigames_gallery' );
		if ( $has_gallery ) {
			$this->enqueue_front_assets();
		}
	}

	/**
	 * Ensure front assets are enqueued when the gallery is actually rendered.
	 */
	private function enqueue_front_assets() {
		if ( $this->assets_done ) {
			return;
		}
		wp_enqueue_style( 'pivigames-gallery' );
		wp_enqueue_script( 'pivigames-gallery' );
		$this->assets_done = true;
	}

	/**
	 * Append the gallery to single-post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_gallery_to_content( $content ) {
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
	 * Shortcode: [pivigames_gallery id="123"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'pivigames_gallery' );
		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return '';
		}
		return $this->render( $post_id );
	}

	/**
	 * Build the gallery HTML for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render( $post_id ) {
		$ids = $this->get_ids( $post_id );
		if ( empty( $ids ) ) {
			return '';
		}

		$items = '';
		foreach ( $ids as $id ) {
			$full = wp_get_attachment_image_url( $id, 'full' );
			if ( ! $full ) {
				continue;
			}
			$thumb_small = wp_get_attachment_image_url( $id, 'thumbnail' );
			$img         = wp_get_attachment_image(
				$id,
				'large',
				false,
				array(
					'loading'  => 'lazy',
					'decoding' => 'async',
					'class'    => 'pivigames-gallery__img',
				)
			);
			if ( ! $img ) {
				continue;
			}

			$items .= sprintf(
				'<a class="pivigames-gallery__item" href="%1$s" data-full="%1$s" data-thumb="%2$s">%3$s</a>',
				esc_url( $full ),
				esc_url( $thumb_small ? $thumb_small : $full ),
				$img
			);
		}

		if ( '' === $items ) {
			return '';
		}

		// Assets are only needed when we actually output a gallery.
		$this->enqueue_front_assets();

		return '<div class="pivigames-gallery" data-pivigames-gallery>'
			. '<h2 class="pivigames-gallery__title">' . esc_html__( 'Capturas', 'pivigames-gallery' ) . '</h2>'
			. '<div class="pivigames-gallery__grid">' . $items . '</div>'
			. '</div>';
	}
}
