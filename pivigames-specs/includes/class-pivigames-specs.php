<?php
/**
 * Main plugin class.
 *
 * @package PiviGames_Specs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PiviGames_Specs
 *
 * Handles admin meta boxes, saving, and front-end rendering of the
 * Technical Information table and System Requirements section.
 */
class PiviGames_Specs {

	/**
	 * Meta key that stores all specs data as a single array.
	 *
	 * @var string
	 */
	const META_KEY = '_pivigames_specs';

	/**
	 * Nonce action/name.
	 *
	 * @var string
	 */
	const NONCE = 'pivigames_specs_nonce';

	/**
	 * Singleton instance.
	 *
	 * @var PiviGames_Specs|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return PiviGames_Specs
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Hooks everything up.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin.
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Front-end.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_filter( 'the_content', array( $this, 'prepend_specs_to_content' ) );
		add_shortcode( 'pivigames_specs', array( $this, 'shortcode' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'pivigames-specs', false, dirname( plugin_basename( PIVIGAMES_SPECS_FILE ) ) . '/languages' );
	}

	/**
	 * Post types the specs are available on.
	 *
	 * @return array
	 */
	private function post_types() {
		/**
		 * Filter which post types get the specs meta boxes.
		 *
		 * @param array $post_types Array of post type slugs.
		 */
		return apply_filters( 'pivigames_specs_post_types', array( 'post' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Field definitions
	 * --------------------------------------------------------------------- */

	/**
	 * Technical Information fields.
	 *
	 * key => array( 'es' => Spanish label, 'en' => English hint, 'placeholder' => example )
	 *
	 * @return array
	 */
	public function tech_fields() {
		return array(
			'plataforma'    => array(
				'es'          => 'PLATAFORMA',
				'en'          => 'Platform',
				'placeholder' => 'PC',
			),
			'peso_total'    => array(
				'es'          => 'PESO TOTAL',
				'en'          => 'Total size',
				'placeholder' => '752 MB',
			),
			'formato'       => array(
				'es'          => 'FORMATO',
				'en'          => 'Format',
				'placeholder' => 'PORTABLE',
			),
			'fecha_estreno' => array(
				'es'          => 'FECHA DE ESTRENO',
				'en'          => 'Release date',
				'placeholder' => '18/06/25',
			),
			'fecha_actualizacion' => array(
				'es'          => 'FECHA DE ACTUALIZACIÓN',
				'en'          => 'Update date',
				'placeholder' => '23/07/26',
			),
		);
	}

	/**
	 * System Requirements rows (shared by Mínimos / Recomendados).
	 *
	 * @return array
	 */
	public function requirement_rows() {
		return array(
			'so'             => array(
				'es'          => 'SO',
				'en'          => 'OS',
				'placeholder' => 'Windows (64-bit) 10',
			),
			'procesador'     => array(
				'es'          => 'Procesador',
				'en'          => 'Processor',
				'placeholder' => 'i5 3550 / RYZEN 5 2500X',
			),
			'memoria'        => array(
				'es'          => 'Memoria',
				'en'          => 'Memory',
				'placeholder' => '4 GB de RAM',
			),
			'graficos'       => array(
				'es'          => 'Gráficos',
				'en'          => 'Graphics',
				'placeholder' => 'NVIDIA GTX 1050 / AMD R9 270X',
			),
			'directx'        => array(
				'es'          => 'DirectX',
				'en'          => 'DirectX',
				'placeholder' => 'Versión 11',
			),
			'almacenamiento' => array(
				'es'          => 'Almacenamiento',
				'en'          => 'Storage',
				'placeholder' => '5 GB de espacio disponible',
			),
		);
	}

	/**
	 * The two requirement tiers.
	 *
	 * @return array
	 */
	public function requirement_tiers() {
		return array(
			'minimos'     => array(
				'es' => 'Mínimos',
				'en' => 'Minimum',
			),
			'recomendados' => array(
				'es' => 'Recomendados',
				'en' => 'Recommended',
			),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Data helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Get all saved specs data for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_data( $post_id ) {
		$data = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $data ) ? $data : array();
	}

	/* --------------------------------------------------------------------- *
	 * Admin: meta boxes
	 * --------------------------------------------------------------------- */

	/**
	 * Register the meta boxes.
	 */
	public function register_meta_boxes() {
		foreach ( $this->post_types() as $post_type ) {
			add_meta_box(
				'pivigames_tech_info',
				esc_html__( 'Información Técnica / Technical Information', 'pivigames-specs' ),
				array( $this, 'render_tech_meta_box' ),
				$post_type,
				'normal',
				'high'
			);

			add_meta_box(
				'pivigames_system_requirements',
				esc_html__( 'Requisitos del Sistema / System Requirements', 'pivigames-specs' ),
				array( $this, 'render_requirements_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the Technical Information meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_tech_meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$data = $this->get_data( $post->ID );
		$tech = isset( $data['tech'] ) && is_array( $data['tech'] ) ? $data['tech'] : array();
		?>
		<p class="pivigames-help">
			<?php esc_html_e( 'Rellena los campos para mostrar la tabla de información técnica en la parte superior de la entrada. Los campos vacíos no se mostrarán. / Fill in the fields to show the technical information table at the top of the post. Empty fields are hidden.', 'pivigames-specs' ); ?>
		</p>
		<table class="form-table pivigames-fields">
			<tbody>
			<?php foreach ( $this->tech_fields() as $key => $field ) : ?>
				<?php $id = 'pivigames_tech_' . $key; ?>
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $id ); ?>">
							<?php echo esc_html( $field['es'] ); ?>
							<span class="pivigames-en"><?php echo esc_html( $field['en'] ); ?></span>
						</label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text"
							id="<?php echo esc_attr( $id ); ?>"
							name="pivigames_tech[<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( isset( $tech[ $key ] ) ? $tech[ $key ] : '' ); ?>"
							placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
						/>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the System Requirements meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_requirements_meta_box( $post ) {
		// Nonce already printed by the tech meta box, but print a dedicated one
		// in case meta box order changes / one box is hidden.
		wp_nonce_field( self::NONCE, self::NONCE . '_req' );
		$data = $this->get_data( $post->ID );
		$req  = isset( $data['req'] ) && is_array( $data['req'] ) ? $data['req'] : array();
		?>
		<p class="pivigames-help">
			<?php esc_html_e( 'Introduce los requisitos mínimos y recomendados. Los campos vacíos no se mostrarán. / Enter the minimum and recommended requirements. Empty fields are hidden.', 'pivigames-specs' ); ?>
		</p>
		<div class="pivigames-req-grid">
			<?php foreach ( $this->requirement_tiers() as $tier_key => $tier ) : ?>
				<?php $tier_data = isset( $req[ $tier_key ] ) && is_array( $req[ $tier_key ] ) ? $req[ $tier_key ] : array(); ?>
				<div class="pivigames-req-col">
					<h3>
						<?php echo esc_html( $tier['es'] ); ?>
						<span class="pivigames-en"><?php echo esc_html( $tier['en'] ); ?></span>
					</h3>
					<table class="form-table pivigames-fields">
						<tbody>
						<?php foreach ( $this->requirement_rows() as $key => $field ) : ?>
							<?php $id = 'pivigames_req_' . $tier_key . '_' . $key; ?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $id ); ?>">
										<?php echo esc_html( $field['es'] ); ?>
										<span class="pivigames-en"><?php echo esc_html( $field['en'] ); ?></span>
									</label>
								</th>
								<td>
									<input
										type="text"
										class="regular-text"
										id="<?php echo esc_attr( $id ); ?>"
										name="pivigames_req[<?php echo esc_attr( $tier_key ); ?>][<?php echo esc_attr( $key ); ?>]"
										value="<?php echo esc_attr( isset( $tier_data[ $key ] ) ? $tier_data[ $key ] : '' ); ?>"
										placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
									/>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Admin: saving
	 * --------------------------------------------------------------------- */

	/**
	 * Save the meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		// Bail on autosave / revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Only handle allowed post types.
		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		$data = array();

		// Technical information.
		$tech = array();
		if ( isset( $_POST['pivigames_tech'] ) && is_array( $_POST['pivigames_tech'] ) ) {
			$raw = wp_unslash( $_POST['pivigames_tech'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
			foreach ( $this->tech_fields() as $key => $field ) {
				if ( isset( $raw[ $key ] ) ) {
					$value = sanitize_text_field( $raw[ $key ] );
					if ( '' !== $value ) {
						$tech[ $key ] = $value;
					}
				}
			}
		}
		if ( ! empty( $tech ) ) {
			$data['tech'] = $tech;
		}

		// System requirements.
		$req = array();
		if ( isset( $_POST['pivigames_req'] ) && is_array( $_POST['pivigames_req'] ) ) {
			$raw_req = wp_unslash( $_POST['pivigames_req'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
			foreach ( $this->requirement_tiers() as $tier_key => $tier ) {
				if ( ! isset( $raw_req[ $tier_key ] ) || ! is_array( $raw_req[ $tier_key ] ) ) {
					continue;
				}
				$tier_values = array();
				foreach ( $this->requirement_rows() as $key => $field ) {
					if ( isset( $raw_req[ $tier_key ][ $key ] ) ) {
						$value = sanitize_text_field( $raw_req[ $tier_key ][ $key ] );
						if ( '' !== $value ) {
							$tier_values[ $key ] = $value;
						}
					}
				}
				if ( ! empty( $tier_values ) ) {
					$req[ $tier_key ] = $tier_values;
				}
			}
		}
		if ( ! empty( $req ) ) {
			$data['req'] = $req;
		}

		if ( ! empty( $data ) ) {
			update_post_meta( $post_id, self::META_KEY, $data );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Assets
	 * --------------------------------------------------------------------- */

	/**
	 * Enqueue admin CSS on post edit screens.
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
			'pivigames-specs-admin',
			PIVIGAMES_SPECS_URL . 'assets/css/admin.css',
			array(),
			PIVIGAMES_SPECS_VERSION
		);
	}

	/**
	 * Enqueue front-end CSS.
	 */
	public function enqueue_front_assets() {
		wp_enqueue_style(
			'pivigames-specs',
			PIVIGAMES_SPECS_URL . 'assets/css/pivigames-specs.css',
			array(),
			PIVIGAMES_SPECS_VERSION
		);
	}

	/* --------------------------------------------------------------------- *
	 * Front-end rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Prepend the specs output to the post content on single posts.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function prepend_specs_to_content( $content ) {
		if ( ! is_singular( $this->post_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$output = $this->render( get_the_ID() );
		if ( '' === $output ) {
			return $content;
		}

		return $output . $content;
	}

	/**
	 * Shortcode handler: [pivigames_specs id="123"].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'pivigames_specs' );
		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			return '';
		}
		return $this->render( $post_id );
	}

	/**
	 * Build the full HTML for a post's specs.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function render( $post_id ) {
		$data = $this->get_data( $post_id );
		if ( empty( $data ) ) {
			return '';
		}

		$html = $this->render_tech( isset( $data['tech'] ) ? $data['tech'] : array() );
		$html .= $this->render_requirements( isset( $data['req'] ) ? $data['req'] : array() );

		if ( '' === $html ) {
			return '';
		}

		return '<div class="pivigames-specs">' . $html . '</div>';
	}

	/**
	 * Render the Technical Information table.
	 *
	 * @param array $tech Saved tech values.
	 * @return string
	 */
	private function render_tech( $tech ) {
		if ( empty( $tech ) || ! is_array( $tech ) ) {
			return '';
		}

		$rows = '';
		foreach ( $this->tech_fields() as $key => $field ) {
			if ( empty( $tech[ $key ] ) ) {
				continue;
			}
			$rows .= sprintf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
				esc_html( $field['es'] ),
				esc_html( $tech[ $key ] )
			);
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<div class="pivigames-specs__block pivigames-specs__tech">'
			. '<h2 class="pivigames-specs__title">' . esc_html__( 'Información Técnica', 'pivigames-specs' ) . '</h2>'
			. '<table class="pivigames-specs__table"><tbody>' . $rows . '</tbody></table>'
			. '</div>';
	}

	/**
	 * Render the System Requirements section.
	 *
	 * @param array $req Saved requirement values.
	 * @return string
	 */
	private function render_requirements( $req ) {
		if ( empty( $req ) || ! is_array( $req ) ) {
			return '';
		}

		$columns = '';
		foreach ( $this->requirement_tiers() as $tier_key => $tier ) {
			if ( empty( $req[ $tier_key ] ) || ! is_array( $req[ $tier_key ] ) ) {
				continue;
			}
			$items = '';
			foreach ( $this->requirement_rows() as $key => $field ) {
				if ( empty( $req[ $tier_key ][ $key ] ) ) {
					continue;
				}
				$items .= sprintf(
					'<li><span class="pivigames-specs__label">%1$s:</span> <span class="pivigames-specs__value">%2$s</span></li>',
					esc_html( $field['es'] ),
					esc_html( $req[ $tier_key ][ $key ] )
				);
			}
			if ( '' === $items ) {
				continue;
			}
			$columns .= '<div class="pivigames-specs__req-col">'
				. '<h3 class="pivigames-specs__subtitle">' . esc_html( $tier['es'] ) . ':</h3>'
				. '<ul class="pivigames-specs__list">' . $items . '</ul>'
				. '</div>';
		}

		if ( '' === $columns ) {
			return '';
		}

		return '<div class="pivigames-specs__block pivigames-specs__requirements">'
			. '<h2 class="pivigames-specs__title">' . esc_html__( 'Requisitos del Sistema', 'pivigames-specs' ) . '</h2>'
			. '<div class="pivigames-specs__req-grid">' . $columns . '</div>'
			. '</div>';
	}
}
