<?php
/**
 * Admin: menu WooCommerce, definições e conjuntos.
 *
 * @package Woo_Extra
 */

defined( 'ABSPATH' ) || exit;

class Woo_Extra_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Extras de produto', 'woo-extra' ),
			__( 'Extras de produto', 'woo-extra' ),
			'manage_woocommerce',
			'woo-extra-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue( $hook ) {
		if ( 'woocommerce_page_woo-extra-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'woo-extra-admin',
			WOO_EXTRA_URL . 'assets/css/admin-woo-extra.css',
			array( 'dashicons' ),
			WOO_EXTRA_VERSION
		);
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'woo-extra-admin',
			WOO_EXTRA_URL . 'assets/js/admin-woo-extra.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WOO_EXTRA_VERSION,
			true
		);
		$cats  = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		$tags  = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );
		$prods = self::get_products_choices();
		if ( is_wp_error( $cats ) ) {
			$cats = array();
		}
		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}
		$obj_map = array(
			'product'     => array(),
			'category'    => array(),
			'product_tag' => array(),
		);
		foreach ( $prods as $pid => $title ) {
			$obj_map['product'][] = array( 'id' => (int) $pid, 'name' => $title );
		}
		foreach ( $cats as $term ) {
			if ( $term instanceof WP_Term ) {
				$obj_map['category'][] = array( 'id' => (int) $term->term_id, 'name' => $term->name );
			}
		}
		foreach ( $tags as $term ) {
			if ( $term instanceof WP_Term ) {
				$obj_map['product_tag'][] = array( 'id' => (int) $term->term_id, 'name' => $term->name );
			}
		}

		wp_localize_script(
			'woo-extra-admin',
			'wooExtraAdmin',
			array(
				'optionRow'          => self::get_option_row_markup( '{{SET}}', '{{I}}', '', '' ),
				'ruleRowFirst'       => self::get_rule_row_markup( '{{SET}}', '{{RI}}', true, 'AND', 'product', 'equals', 0, $cats, $tags, $prods ),
				'ruleRowNext'        => self::get_rule_row_markup( '{{SET}}', '{{RI}}', false, 'AND', 'product', 'equals', 0, $cats, $tags, $prods ),
				'objects'            => $obj_map,
				'defaultSetHeading' => __( 'Sem nome', 'woo-extra' ),
				'enabledLabels'      => array(
					'on'  => __( 'Habilitado', 'woo-extra' ),
					'off' => __( 'Desabilitado', 'woo-extra' ),
				),
			)
		);
	}

	public static function maybe_save() {
		if ( ! isset( $_POST['woo_extra_save'], $_POST['woo_extra_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woo_extra_nonce'] ) ), 'woo_extra_save_settings' ) ) {
			return;
		}

		$raw = isset( $_POST['woo_extra_config'] ) ? wp_unslash( $_POST['woo_extra_config'] ) : array();
		$raw = is_array( $raw ) ? $raw : array();

		$config = Woo_Extra_Core::sanitize_config( $raw );
		update_option( Woo_Extra_Core::OPTION_KEY, $config );
		Woo_Extra_Core::clear_config_cache();

		add_settings_error( 'woo_extra', 'saved', __( 'Definições guardadas.', 'woo-extra' ), 'success' );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		settings_errors( 'woo_extra' );
		$config = Woo_Extra_Core::get_config();
		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $cats ) ) {
			$cats = array();
		}
		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}
		$products_for_select = self::get_products_choices();

		?>
		<div class="wrap woo-extra-admin">
			<h1><?php esc_html_e( 'Extras de produto', 'woo-extra' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configure etiquetas globais, conjuntos de opções e regras de exibição. A lógica é avaliada no servidor antes de mostrar o formulário no produto.', 'woo-extra' ); ?></p>

			<form method="post" action="" id="woo-extra-form">
				<?php wp_nonce_field( 'woo_extra_save_settings', 'woo_extra_nonce' ); ?>
				<input type="hidden" name="woo_extra_save" value="1" />

				<div class="woo-extra-card">
					<h2><?php esc_html_e( 'Etiqueta global', 'woo-extra' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="woo_extra_global_label"><?php esc_html_e( 'Texto da etiqueta', 'woo-extra' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="woo_extra_global_label" name="woo_extra_config[global_label]" value="<?php echo esc_attr( $config['global_label'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Mostrar etiqueta', 'woo-extra' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="woo_extra_config[show_global_label]" value="1" <?php checked( ! empty( $config['show_global_label'] ) ); ?> />
									<?php esc_html_e( 'Exibir a etiqueta global acima dos extras na página do produto', 'woo-extra' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="woo-extra-sets-header">
					<h2><?php esc_html_e( 'Conjuntos de extras', 'woo-extra' ); ?></h2>
					<button type="button" class="button button-secondary" id="woo-extra-add-set"><?php esc_html_e( 'Adicionar conjunto', 'woo-extra' ); ?></button>
				</div>

				<div id="woo-extra-sets">
					<?php
					$sets                 = ! empty( $config['sets'] ) ? $config['sets'] : array();
					$has_persisted_sets   = ! empty( $config['sets'] );
					if ( empty( $sets ) ) {
						$sets[] = self::blank_set();
					}
					foreach ( $sets as $s => $set ) {
						self::render_set_box( $s, $set, $cats, $tags, $products_for_select, $has_persisted_sets );
					}
					?>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar alterações', 'woo-extra' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array
	 */
	protected static function blank_set() {
		return array(
			'id'          => Woo_Extra_Core::generate_set_id(),
			'name'        => '',
			'choice_type' => 'exclusive',
			'options'     => array(
				array( 'label' => '', 'price' => '0' ),
			),
			'css_class'   => '',
			'css_id'      => '',
			'rules'       => array(),
			'enabled'     => true,
			'required'    => false,
		);
	}

	/**
	 * @param int|string $s
	 * @param array      $set
	 * @param array      $cats
	 * @param array      $tags
	 * @param array      $products_for_select id => name
	 * @param bool       $start_collapsed     Se true, o painel inicia fechado (lista já guardada).
	 */
	protected static function render_set_box( $s, $set, $cats, $tags, $products_for_select, $start_collapsed = false ) {
		$set_index = (string) $s;
		$set_id    = isset( $set['id'] ) ? $set['id'] : Woo_Extra_Core::generate_set_id();
		$name      = isset( $set['name'] ) ? $set['name'] : '';
		$choice    = isset( $set['choice_type'] ) ? $set['choice_type'] : 'exclusive';
		$options   = ! empty( $set['options'] ) ? $set['options'] : array( array( 'label' => '', 'price' => '0' ) );
		$css_class = isset( $set['css_class'] ) ? $set['css_class'] : '';
		$css_id    = isset( $set['css_id'] ) ? $set['css_id'] : '';
		$rules     = isset( $set['rules'] ) && is_array( $set['rules'] ) ? $set['rules'] : array();
		$enabled   = ! array_key_exists( 'enabled', $set ) || ! empty( $set['enabled'] );
		$required  = ! empty( $set['required'] );

		$heading_text    = '' !== trim( $name ) ? $name : __( 'Sem nome', 'woo-extra' );
		$heading_classes = 'woo-extra-set-heading' . ( '' === trim( $name ) ? ' is-placeholder' : '' );

		$wrap_classes = 'postbox woo-extra-set';
		if ( $start_collapsed ) {
			$wrap_classes .= ' woo-extra-set-collapsed';
		}

		?>
		<div class="<?php echo esc_attr( $wrap_classes ); ?>" data-set-index="<?php echo esc_attr( $set_index ); ?>">
			<div class="woo-extra-set-header">
				<span class="woo-extra-set-drag dashicons dashicons-menu" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Arrastar para reordenar', 'woo-extra' ); ?>"></span>
				<button type="button" class="woo-extra-set-accordion-toggle" aria-expanded="<?php echo $start_collapsed ? 'false' : 'true'; ?>">
					<span class="woo-extra-set-chevron dashicons <?php echo $start_collapsed ? 'dashicons-arrow-right' : 'dashicons-arrow-down'; ?>" aria-hidden="true"></span>
					<span class="woo-extra-set-h"><span class="<?php echo esc_attr( $heading_classes ); ?>"><?php echo esc_html( $heading_text ); ?></span></span>
				</button>
				<span class="woo-extra-set-order-buttons" role="group" aria-label="<?php esc_attr_e( 'Ordem do conjunto', 'woo-extra' ); ?>">
					<button type="button" class="button button-small woo-extra-set-move-up" aria-label="<?php esc_attr_e( 'Mover conjunto para cima', 'woo-extra' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
					<button type="button" class="button button-small woo-extra-set-move-down" aria-label="<?php esc_attr_e( 'Mover conjunto para baixo', 'woo-extra' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				</span>
				<span class="woo-extra-set-header-meta">
					<label class="woo-extra-set-meta-label">
						<input type="hidden" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][required]" value="0" />
						<input type="checkbox" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][required]" value="1" <?php checked( $required ); ?> class="woo-extra-set-required-cb" />
						<?php esc_html_e( 'Obrigatório', 'woo-extra' ); ?>
					</label>
					<span class="woo-extra-set-enabled-wrap">
						<input type="hidden" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][enabled]" value="0" />
						<label class="woo-extra-set-enabled-toggle">
							<input type="checkbox" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> class="woo-extra-set-enabled-cb screen-reader-text" />
							<span class="woo-extra-set-enabled-track" aria-hidden="true">
								<span class="woo-extra-set-enabled-thumb"></span>
							</span>
							<span class="woo-extra-set-enabled-text"><?php echo esc_html( $enabled ? __( 'Habilitado', 'woo-extra' ) : __( 'Desabilitado', 'woo-extra' ) ); ?></span>
						</label>
					</span>
				</span>
			</div>
			<div class="woo-extra-set-panel" <?php echo $start_collapsed ? ' hidden' : ''; ?>>
			<div class="inside">
				<input type="hidden" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][id]" value="<?php echo esc_attr( $set_id ); ?>" />

				<table class="form-table woo-extra-set-meta">
					<tr>
						<th><label><?php esc_html_e( 'Nome do conjunto', 'woo-extra' ); ?></label></th>
						<td><input type="text" class="regular-text woo-extra-set-name-input" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Ex.: Acabamentos', 'woo-extra' ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tipo de escolha', 'woo-extra' ); ?></th>
						<td>
							<select name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][choice_type]">
								<option value="exclusive" <?php selected( $choice, 'exclusive' ); ?>><?php esc_html_e( 'Exclusiva (select / radio)', 'woo-extra' ); ?></option>
								<option value="multiple" <?php selected( $choice, 'multiple' ); ?>><?php esc_html_e( 'Múltipla (checkbox)', 'woo-extra' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Classe e ID (CSS)', 'woo-extra' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][css_class]" value="<?php echo esc_attr( $css_class ); ?>" placeholder="class" />
							<input type="text" class="regular-text" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][css_id]" value="<?php echo esc_attr( $css_id ); ?>" placeholder="id" />
						</td>
					</tr>
				</table>

				<h3 class="woo-extra-subhead"><?php esc_html_e( 'Opções e preços', 'woo-extra' ); ?></h3>
				<table class="widefat striped woo-extra-options-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Etiqueta', 'woo-extra' ); ?></th>
							<th><?php esc_html_e( 'Preço (+)', 'woo-extra' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody class="woo-extra-options-body">
						<?php
						foreach ( $options as $i => $opt ) {
							echo self::get_option_row_markup( $set_index, (string) $i, isset( $opt['label'] ) ? $opt['label'] : '', isset( $opt['price'] ) ? $opt['price'] : '0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</tbody>
				</table>
				<p class="woo-extra-inline-actions"><button type="button" class="button button-small woo-extra-add-option"><?php esc_html_e( 'Adicionar opção', 'woo-extra' ); ?></button></p>

				<h3 class="woo-extra-subhead"><?php esc_html_e( 'Regras de exibição', 'woo-extra' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Vazio = mostrar em todos os produtos. Com várias regras, use AND/OR entre elas.', 'woo-extra' ); ?></p>
				<table class="widefat striped woo-extra-rules-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ligação', 'woo-extra' ); ?></th>
							<th><?php esc_html_e( 'Alvo', 'woo-extra' ); ?></th>
							<th><?php esc_html_e( 'Operador', 'woo-extra' ); ?></th>
							<th><?php esc_html_e( 'Valor', 'woo-extra' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody class="woo-extra-rules-body">
						<?php
						foreach ( $rules as $ri => $rule ) {
							echo self::get_rule_row_markup(
								$set_index,
								(string) $ri,
								0 === (int) $ri,
								isset( $rule['join'] ) ? $rule['join'] : 'AND',
								isset( $rule['subject'] ) ? $rule['subject'] : 'product',
								isset( $rule['operator'] ) ? $rule['operator'] : 'equals',
								isset( $rule['object_id'] ) ? (int) $rule['object_id'] : 0,
								$cats,
								$tags,
								$products_for_select
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</tbody>
				</table>
				<p class="woo-extra-inline-actions"><button type="button" class="button button-small woo-extra-add-rule"><?php esc_html_e( 'Adicionar regra', 'woo-extra' ); ?></button></p>
			</div>
			<div class="woo-extra-set-footer">
				<button type="button" class="button woo-extra-remove-set" aria-label="<?php esc_attr_e( 'Remover conjunto', 'woo-extra' ); ?>"><?php esc_html_e( 'Remover conjunto', 'woo-extra' ); ?></button>
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $set_index
	 * @param string $i
	 */
	public static function get_option_row_markup( $set_index, $i, $label, $price ) {
		ob_start();
		?>
		<tr class="woo-extra-option-row">
			<td class="woo-extra-col-label">
				<input type="text" class="woo-extra-option-label" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][options][<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" />
			</td>
			<td class="woo-extra-col-price">
				<input type="text" class="small-text wc_input_price" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][options][<?php echo esc_attr( $i ); ?>][price]" value="<?php echo esc_attr( $price ); ?>" />
			</td>
			<td><button type="button" class="button-link woo-extra-remove-option"><?php esc_html_e( 'Remover', 'woo-extra' ); ?></button></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param string     $set_index
	 * @param string     $i
	 * @param bool       $is_first
	 * @param string     $join       AND ou OR (ignorado se $is_first)
	 * @param string     $subject
	 * @param string     $operator
	 * @param int        $object_id
	 * @param \WP_Term[] $cats
	 * @param \WP_Term[] $tags
	 * @param int[]|array $products    id => name
	 */
	public static function get_rule_row_markup( $set_index, $i, $is_first, $join, $subject, $operator, $object_id, $cats = array(), $tags = array(), $products = array() ) {
		ob_start();
		$join_value = $is_first ? '' : ( in_array( $join, array( 'AND', 'OR' ), true ) ? $join : 'AND' );
		?>
		<tr class="woo-extra-rule-row" data-first="<?php echo $is_first ? '1' : '0'; ?>">
			<td>
				<?php if ( $is_first ) : ?>
					<span class="woo-extra-rule-join-placeholder">—</span>
					<input type="hidden" name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][rules][<?php echo esc_attr( $i ); ?>][join]" value="" class="woo-extra-rule-join-input" />
				<?php else : ?>
					<select name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][rules][<?php echo esc_attr( $i ); ?>][join]" class="woo-extra-rule-join-input">
						<option value="AND" <?php selected( $join_value, 'AND' ); ?>>AND</option>
						<option value="OR" <?php selected( $join_value, 'OR' ); ?>>OR</option>
					</select>
				<?php endif; ?>
			</td>
			<td>
				<select name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][rules][<?php echo esc_attr( $i ); ?>][subject]" class="woo-extra-rule-subject">
					<option value="product" <?php selected( $subject, 'product' ); ?>><?php esc_html_e( 'Produto', 'woo-extra' ); ?></option>
					<option value="category" <?php selected( $subject, 'category' ); ?>><?php esc_html_e( 'Categoria', 'woo-extra' ); ?></option>
					<option value="product_tag" <?php selected( $subject, 'product_tag' ); ?>><?php esc_html_e( 'Etiqueta (tag)', 'woo-extra' ); ?></option>
				</select>
			</td>
			<td>
				<select name="woo_extra_config[sets][<?php echo esc_attr( $set_index ); ?>][rules][<?php echo esc_attr( $i ); ?>][operator]">
					<option value="equals" <?php selected( $operator, 'equals' ); ?>><?php esc_html_e( 'Igual a', 'woo-extra' ); ?></option>
					<option value="not_equals" <?php selected( $operator, 'not_equals' ); ?>><?php esc_html_e( 'Diferente de', 'woo-extra' ); ?></option>
				</select>
			</td>
			<td>
				<?php echo self::rule_object_select_single( $set_index, $i, $subject, $object_id, $cats, $tags, $products ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</td>
			<td><button type="button" class="button-link woo-extra-remove-rule"><?php esc_html_e( 'Remover', 'woo-extra' ); ?></button></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Um único select (nome object_id) conforme o tipo de alvo.
	 *
	 * @param array $cats
	 * @param array $tags
	 * @param array $products
	 */
	protected static function rule_object_select_single( $set_index, $i, $subject, $object_id, $cats, $tags, $products ) {
		$name = 'woo_extra_config[sets][' . $set_index . '][rules][' . $i . '][object_id]';
		ob_start();
		echo '<select name="' . esc_attr( $name ) . '" class="woo-extra-rule-object-id">';
		echo '<option value="0">' . esc_html__( '— Escolher —', 'woo-extra' ) . '</option>';
		if ( 'category' === $subject ) {
			foreach ( $cats as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				echo '<option value="' . esc_attr( (string) $term->term_id ) . '" ' . selected( (int) $object_id, (int) $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		} elseif ( 'product_tag' === $subject ) {
			foreach ( $tags as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				echo '<option value="' . esc_attr( (string) $term->term_id ) . '" ' . selected( (int) $object_id, (int) $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		} else {
			foreach ( $products as $pid => $ptitle ) {
				echo '<option value="' . esc_attr( (string) $pid ) . '" ' . selected( (int) $object_id, (int) $pid, false ) . '>' . esc_html( $ptitle ) . '</option>';
			}
		}
		echo '</select>';
		return ob_get_clean();
	}

	/**
	 * Lista limitada de produtos para selects (evitar milhares de opções).
	 *
	 * @return array<int,string>
	 */
	protected static function get_products_choices() {
		$q = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$out = array();
		foreach ( $q->posts as $pid ) {
			$out[ (int) $pid ] = get_the_title( $pid );
		}
		return $out;
	}
}
