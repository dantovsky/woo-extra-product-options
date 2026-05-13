<?php
/**
 * Frontend: renderização antes do botão e scripts de preço.
 *
 * @package Woo_Extra
 */

defined( 'ABSPATH' ) || exit;

class Woo_Extra_Frontend {

	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_extras' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	/**
	 * Carrega assets apenas em produto simples/variável com conjuntos visíveis.
	 */
	public static function maybe_enqueue() {
		if ( ! is_product() ) {
			return;
		}
		$pid = get_queried_object_id();
		$product = wc_get_product( $pid );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$pid = (int) $product->get_id();
		$sets = Woo_Extra_Core::get_visible_sets_for_product( $pid );
		if ( empty( $sets ) ) {
			return;
		}

		wp_register_style(
			'woo-extra-frontend',
			WOO_EXTRA_URL . 'assets/css/frontend-woo-extra.css',
			array(),
			WOO_EXTRA_VERSION
		);
		wp_enqueue_style( 'woo-extra-frontend' );

		wp_register_script(
			'woo-extra-frontend',
			WOO_EXTRA_URL . 'assets/js/frontend-woo-extra.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			WOO_EXTRA_VERSION,
			true
		);

		$base_display = (float) wc_get_price_to_display( $product );
		$config       = Woo_Extra_Core::get_config();

		$price_suffix = '';
		if ( $product && is_a( $product, 'WC_Product' ) && method_exists( $product, 'get_price_suffix' ) ) {
			$price_suffix = $product->get_price_suffix();
		}

		$sets_payload = array();
		foreach ( $sets as $set ) {
			$opts = array();
			foreach ( $set['options'] as $idx => $o ) {
				$opts[] = array(
					'index' => (int) $idx,
					'label' => $o['label'],
					'add'   => (float) wc_format_decimal( $o['price'] ),
				);
			}
			$sets_payload[] = array(
				'id'          => $set['id'],
				'name'        => isset( $set['name'] ) ? $set['name'] : '',
				'choice_type' => $set['choice_type'],
				'required'    => ! empty( $set['required'] ),
				'options'     => $opts,
			);
		}

		wp_enqueue_script( 'woo-extra-frontend' );
		wp_localize_script(
			'woo-extra-frontend',
			'wooExtraFront',
			array(
				'isVariable'   => $product->is_type( 'variable' ),
				'basePrice'    => $base_display,
				'sets'         => $sets_payload,
				'currencySym'  => get_woocommerce_currency_symbol(),
				'decimalSep'   => wc_get_price_decimal_separator(),
				'thousandSep'  => wc_get_price_thousand_separator(),
				'decimals'     => wc_get_price_decimals(),
				'currencyPos'  => get_option( 'woocommerce_currency_pos', 'left' ),
				'priceSuffix'  => wp_kses_post( $price_suffix ),
				'strings'      => array(
					'requiredMultiple' => __( 'Escolha pelo menos uma opção em cada extra obrigatório (tipo múltipla).', 'woo-extra' ),
				),
			)
		);
	}

	public static function render_extras() {
		if ( ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$pid  = (int) $product->get_id();
		$sets = Woo_Extra_Core::get_visible_sets_for_product( $pid );
		if ( empty( $sets ) ) {
			return;
		}

		$config = Woo_Extra_Core::get_config();

		echo '<div class="woo-extra-fields-wrap">';

		if ( ! empty( $config['show_global_label'] ) && ! empty( $config['global_label'] ) ) {
			echo '<div class="woo-extra-global-label">' . esc_html( $config['global_label'] ) . '</div>';
		}

		foreach ( $sets as $set ) {
			$cid = isset( $set['css_id'] ) ? $set['css_id'] : '';
			$cc  = isset( $set['css_class'] ) ? $set['css_class'] : '';
			$sid = isset( $set['id'] ) ? $set['id'] : '';

			$classes = trim( 'woo-extra-set woo-extra-set-' . sanitize_html_class( $sid, 'set' ) . ' ' . $cc );
			$attr_id = $cid !== '' ? ' id="' . esc_attr( $cid ) . '"' : '';
			$req     = ! empty( $set['required'] );
			$dreq    = $req ? ' data-required="1"' : '';

			echo '<fieldset class="' . esc_attr( $classes ) . '"' . $attr_id . $dreq . '>';
			if ( ! empty( $set['name'] ) ) {
				echo '<legend class="woo-extra-set-legend">' . esc_html( $set['name'] ) . '</legend>';
			}

			$fname = 'woo_extra_selection[' . esc_attr( $sid ) . ']';
			$type  = isset( $set['choice_type'] ) ? $set['choice_type'] : 'exclusive';

			if ( 'multiple' === $type ) {
				echo '<ul class="woo-extra-options woo-extra-options-multiple">';
				foreach ( $set['options'] as $idx => $opt ) {
					$lid = 'woo-extra-' . $sid . '-' . $idx;
					echo '<li><label for="' . esc_attr( $lid ) . '">';
					echo '<input type="checkbox" id="' . esc_attr( $lid ) . '" name="' . esc_attr( $fname ) . '[]" value="' . esc_attr( (string) $idx ) . '" class="woo-extra-input" data-add="' . esc_attr( wc_format_decimal( $opt['price'] ) ) . '" /> ';
					echo esc_html( $opt['label'] );
					echo ' <span class="woo-extra-option-suffix">(+' . wp_kses_post( wc_price( $opt['price'] ) ) . ')</span>';
					echo '</label></li>';
				}
				echo '</ul>';
			} else {
				$req_attrs = $req ? ' required aria-required="true"' : '';
				echo '<select name="' . esc_attr( $fname ) . '" class="woo-extra-input woo-extra-select" data-exclusive="1"' . $req_attrs . '>';
				echo '<option value="">' . esc_html__( '— Selecionar —', 'woo-extra' ) . '</option>';
				foreach ( $set['options'] as $idx => $opt ) {
					echo '<option value="' . esc_attr( (string) $idx ) . '" data-add="' . esc_attr( wc_format_decimal( $opt['price'] ) ) . '">';
					echo esc_html( $opt['label'] );
					echo ' (+' . wp_strip_all_tags( wc_price( $opt['price'] ) ) . ')';
					echo '</option>';
				}
				echo '</select>';
			}

			echo '</fieldset>';
		}

		echo '</div>';
	}
}
