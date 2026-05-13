<?php
/**
 * Carrinho, checkout e encomenda.
 *
 * @package Woo_Extra
 */

defined( 'ABSPATH' ) || exit;

class Woo_Extra_Cart {

	const CART_KEY = 'woo_extra_selection';

	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_required_extras' ), 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 20, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'before_calculate_totals' ), 20, 1 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'get_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_item_meta' ), 10, 4 );
	}

	/**
	 * Garante que conjuntos marcados como obrigatórios tenham seleção antes de adicionar ao carrinho.
	 *
	 * @param bool $passed
	 * @param int  $product_id   ID do produto (pai em variações).
	 * @param int  $quantity
	 * @param int  $variation_id
	 * @return bool
	 */
	public static function validate_required_extras( $passed, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! $passed ) {
			return false;
		}
		$parent = (int) $product_id;
		if ( $parent < 1 ) {
			return $passed;
		}

		$visible = Woo_Extra_Core::get_visible_sets_for_product( $parent );
		if ( empty( $visible ) ) {
			return $passed;
		}

		$raw = array();
		if ( ! empty( $_POST['woo_extra_selection'] ) && is_array( $_POST['woo_extra_selection'] ) ) {
			$raw = wp_unslash( $_POST['woo_extra_selection'] );
		}

		foreach ( $visible as $set_def ) {
			if ( empty( $set_def['required'] ) ) {
				continue;
			}
			$sid = isset( $set_def['id'] ) ? $set_def['id'] : '';
			if ( '' === $sid ) {
				continue;
			}
			$type = isset( $set_def['choice_type'] ) ? $set_def['choice_type'] : 'exclusive';

			if ( 'multiple' === $type ) {
				$incoming = isset( $raw[ $sid ] ) ? $raw[ $sid ] : array();
				if ( ! is_array( $incoming ) ) {
					$incoming = ( null === $incoming || '' === $incoming ) ? array() : array( $incoming );
				}
				$ok = false;
				foreach ( $incoming as $one ) {
					$i = absint( $one );
					if ( isset( $set_def['options'][ $i ] ) ) {
						$ok = true;
						break;
					}
				}
				if ( ! $ok ) {
					$label = isset( $set_def['name'] ) && $set_def['name'] !== '' ? $set_def['name'] : __( 'Extras', 'woo-extra' );
					/* translators: %s: extra set name */
					wc_add_notice( sprintf( __( 'Escolha pelo menos uma opção em "%s".', 'woo-extra' ), wp_strip_all_tags( $label ) ), 'error' );
					return false;
				}
			} else {
				$incoming = isset( $raw[ $sid ] ) ? $raw[ $sid ] : '';
				if ( '' === (string) $incoming ) {
					$label = isset( $set_def['name'] ) && $set_def['name'] !== '' ? $set_def['name'] : __( 'Extras', 'woo-extra' );
					/* translators: %s: extra set name */
					wc_add_notice( sprintf( __( 'Escolha uma opção em "%s".', 'woo-extra' ), wp_strip_all_tags( $label ) ), 'error' );
					return false;
				}
				$i = absint( $incoming );
				if ( ! isset( $set_def['options'][ $i ] ) ) {
					$label = isset( $set_def['name'] ) && $set_def['name'] !== '' ? $set_def['name'] : __( 'Extras', 'woo-extra' );
					/* translators: %s: extra set name */
					wc_add_notice( sprintf( __( 'Escolha uma opção em "%s".', 'woo-extra' ), wp_strip_all_tags( $label ) ), 'error' );
					return false;
				}
			}
		}

		return $passed;
	}

	/**
	 * @param array $cart_item_data
	 * @param int   $product_id
	 * @param int   $variation_id
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( empty( $_POST['woo_extra_selection'] ) || ! is_array( $_POST['woo_extra_selection'] ) ) {
			return $cart_item_data;
		}

		$main_id = $variation_id ? (int) $variation_id : (int) $product_id;
		$parent  = $variation_id ? (int) $product_id : $main_id;
		$product = wc_get_product( $main_id );
		if ( ! $product ) {
			return $cart_item_data;
		}

		$visible = Woo_Extra_Core::get_visible_sets_for_product( $parent );
		if ( empty( $visible ) ) {
			return $cart_item_data;
		}

		$allowed_ids = array();
		foreach ( $visible as $s ) {
			if ( ! empty( $s['id'] ) ) {
				$allowed_ids[ $s['id'] ] = $s;
			}
		}

		$raw     = wp_unslash( $_POST['woo_extra_selection'] );
		$clean   = array();
		$display = array();

		foreach ( $allowed_ids as $set_id => $set_def ) {
			if ( ! isset( $raw[ $set_id ] ) ) {
				continue;
			}
			$incoming = $raw[ $set_id ];
			$type     = isset( $set_def['choice_type'] ) ? $set_def['choice_type'] : 'exclusive';

			if ( 'multiple' === $type ) {
				if ( ! is_array( $incoming ) ) {
					$incoming = array( $incoming );
				}
				$indices = array();
				foreach ( $incoming as $one ) {
					$i = absint( $one );
					if ( isset( $set_def['options'][ $i ] ) ) {
						$indices[] = $i;
					}
				}
				$indices = array_values( array_unique( $indices ) );
				if ( ! empty( $indices ) ) {
					$clean[ $set_id ] = $indices;
					$labels           = Woo_Extra_Core::labels_for_selection( $set_def, $indices );
					$set_name         = isset( $set_def['name'] ) && $set_def['name'] !== '' ? $set_def['name'] : __( 'Extras', 'woo-extra' );
					$display[]        = array(
						'set_name' => $set_name,
						'labels'   => $labels,
						'total'    => Woo_Extra_Core::calculate_addon_total( $set_def, $indices ),
					);
				}
			} else {
				if ( '' === $incoming || null === $incoming ) {
					continue;
				}
				$i = absint( $incoming );
				if ( isset( $set_def['options'][ $i ] ) ) {
					$clean[ $set_id ] = array( $i );
					$labels           = Woo_Extra_Core::labels_for_selection( $set_def, array( $i ) );
					$set_name         = isset( $set_def['name'] ) && $set_def['name'] !== '' ? $set_def['name'] : __( 'Extras', 'woo-extra' );
					$display[]        = array(
						'set_name' => $set_name,
						'labels'   => $labels,
						'total'    => Woo_Extra_Core::calculate_addon_total( $set_def, array( $i ) ),
					);
				}
			}
		}

		if ( empty( $clean ) ) {
			return $cart_item_data;
		}

		$price_product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( $price_product ) {
			$cart_item_data['woo_extra_base_price'] = (float) $price_product->get_price( 'edit' );
		}

		$cart_item_data[ self::CART_KEY ]       = $clean;
		$cart_item_data['woo_extra_display']    = $display;
		$cart_item_data['woo_extra_product_id'] = $parent;

		return $cart_item_data;
	}

	/**
	 * @param array $cart_item
	 * @param array $values
	 */
	public static function get_cart_item_from_session( $cart_item, $values ) {
		if ( ! empty( $values[ self::CART_KEY ] ) ) {
			$cart_item[ self::CART_KEY ] = $values[ self::CART_KEY ];
		}
		if ( ! empty( $values['woo_extra_display'] ) ) {
			$cart_item['woo_extra_display'] = $values['woo_extra_display'];
		}
		if ( ! empty( $values['woo_extra_product_id'] ) ) {
			$cart_item['woo_extra_product_id'] = (int) $values['woo_extra_product_id'];
		}
		if ( isset( $values['woo_extra_base_price'] ) ) {
			$cart_item['woo_extra_base_price'] = (float) $values['woo_extra_base_price'];
		}
		return $cart_item;
	}

	/**
	 * @param WC_Cart $cart
	 */
	public static function before_calculate_totals( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item[ self::CART_KEY ] ) || empty( $item['data'] ) ) {
				continue;
			}
			$base = isset( $item['woo_extra_base_price'] ) ? (float) $item['woo_extra_base_price'] : (float) $item['data']->get_price( 'edit' );
			$pid  = ! empty( $item['woo_extra_product_id'] ) ? (int) $item['woo_extra_product_id'] : (int) $item['product_id'];

			$addon = 0.0;
			foreach ( $item[ self::CART_KEY ] as $set_id => $indices ) {
				$set = Woo_Extra_Core::get_set_by_id( $set_id );
				if ( ! $set ) {
					continue;
				}
				$addon += Woo_Extra_Core::calculate_addon_total( $set, $indices );
			}

			$item['data']->set_price( $base + $addon );
			$cart->cart_contents[ $key ]['data'] = $item['data'];
		}
	}

	/**
	 * @param array $item_data
	 * @param array $cart_item
	 */
	public static function get_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['woo_extra_display'] ) || ! is_array( $cart_item['woo_extra_display'] ) ) {
			return $item_data;
		}
		foreach ( $cart_item['woo_extra_display'] as $row ) {
			if ( empty( $row['labels'] ) ) {
				continue;
			}
			$name = isset( $row['set_name'] ) ? $row['set_name'] : __( 'Extras', 'woo-extra' );
			$item_data[] = array(
				'name'    => $name,
				'value'   => implode( ', ', array_map( 'wp_strip_all_tags', $row['labels'] ) ),
				'display' => '',
			);
		}
		return $item_data;
	}

	/**
	 * @param WC_Order_Item_Product $item
	 * @param string                $cart_item_key
	 * @param array                 $values
	 * @param WC_Order              $order
	 */
	public static function order_line_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['woo_extra_display'] ) || ! is_array( $values['woo_extra_display'] ) ) {
			return;
		}
		foreach ( $values['woo_extra_display'] as $row ) {
			if ( empty( $row['labels'] ) ) {
				continue;
			}
			$name = isset( $row['set_name'] ) ? $row['set_name'] : __( 'Extras', 'woo-extra' );
			$item->add_meta_data( $name, implode( ', ', $row['labels'] ), true );
		}
	}

}
