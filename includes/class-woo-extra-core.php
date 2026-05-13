<?php
/**
 * Opções, sanitização e avaliação de regras.
 *
 * @package Woo_Extra
 */

defined( 'ABSPATH' ) || exit;

class Woo_Extra_Core {

	const OPTION_KEY = 'woo_extra_config';

	/**
	 * @var array|null
	 */
	protected static $config_cache = null;

	public static function init() {
		// Nada obrigatório no init do core.
	}

	/**
	 * @return array{global_label:string,show_global_label:bool,sets:array<int,array>}
	 */
	public static function get_config() {
		if ( null !== self::$config_cache ) {
			return self::$config_cache;
		}
		$defaults = self::default_config();
		$stored   = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$config_cache = wp_parse_args( $stored, $defaults );
		return self::$config_cache;
	}

	public static function clear_config_cache() {
		self::$config_cache = null;
	}

	/**
	 * @return array
	 */
	public static function default_config() {
		return array(
			'global_label'      => __( 'Extras', 'woo-extra' ),
			'show_global_label' => true,
			'sets'              => array(),
		);
	}

	/**
	 * @param array $raw Dados já em array (ex.: $_POST['woo_extra_config'] sanitizado).
	 */
	public static function sanitize_config( $raw ) {
		$out = array(
			'global_label'      => isset( $raw['global_label'] ) ? sanitize_text_field( wp_unslash( $raw['global_label'] ) ) : '',
			'show_global_label' => ! empty( $raw['show_global_label'] ),
			'sets'              => array(),
		);

		if ( empty( $raw['sets'] ) || ! is_array( $raw['sets'] ) ) {
			return $out;
		}

		foreach ( $raw['sets'] as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			$set_id = isset( $set['id'] ) ? sanitize_key( $set['id'] ) : '';
			if ( '' === $set_id ) {
				$set_id = self::generate_set_id();
			}

			$choice_type = isset( $set['choice_type'] ) ? sanitize_key( $set['choice_type'] ) : 'exclusive';
			if ( ! in_array( $choice_type, array( 'exclusive', 'multiple' ), true ) ) {
				$choice_type = 'exclusive';
			}

			$options = array();
			if ( ! empty( $set['options'] ) && is_array( $set['options'] ) ) {
				foreach ( $set['options'] as $opt ) {
					if ( ! is_array( $opt ) ) {
						continue;
					}
					$label = isset( $opt['label'] ) ? sanitize_text_field( wp_unslash( $opt['label'] ) ) : '';
					if ( '' === $label ) {
						continue;
					}
					$price = isset( $opt['price'] ) ? wc_format_decimal( wp_unslash( $opt['price'] ) ) : '0';
					$options[] = array(
						'label' => $label,
						'price' => $price,
					);
				}
			}

			$rules = array();
			if ( ! empty( $set['rules'] ) && is_array( $set['rules'] ) ) {
				foreach ( $set['rules'] as $idx => $rule ) {
					if ( ! is_array( $rule ) ) {
						continue;
					}
					$join = isset( $rule['join'] ) ? strtoupper( sanitize_key( $rule['join'] ) ) : '';
					if ( $idx > 0 && ! in_array( $join, array( 'AND', 'OR' ), true ) ) {
						$join = 'AND';
					}
					if ( 0 === $idx ) {
						$join = '';
					}

					$subject = isset( $rule['subject'] ) ? sanitize_key( $rule['subject'] ) : 'product';
					if ( ! in_array( $subject, array( 'product', 'category', 'product_tag' ), true ) ) {
						$subject = 'product';
					}

					$operator = isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : 'equals';
					if ( ! in_array( $operator, array( 'equals', 'not_equals' ), true ) ) {
						$operator = 'equals';
					}

					$object_id = isset( $rule['object_id'] ) ? absint( $rule['object_id'] ) : 0;
					if ( $object_id < 1 ) {
						continue;
					}

					$rules[] = array(
						'join'      => $join,
						'subject'   => $subject,
						'operator'  => $operator,
						'object_id' => $object_id,
					);
				}
			}

			$enabled = ! isset( $set['enabled'] ) || (string) wp_unslash( $set['enabled'] ) === '1';
			$required = isset( $set['required'] ) && (string) wp_unslash( $set['required'] ) === '1';

			$out['sets'][] = array(
				'id'          => $set_id,
				'name'        => isset( $set['name'] ) ? sanitize_text_field( wp_unslash( $set['name'] ) ) : '',
				'choice_type' => $choice_type,
				'options'     => $options,
				'css_class'   => isset( $set['css_class'] ) ? sanitize_html_class( wp_unslash( $set['css_class'] ), '' ) : '',
				'css_id'      => isset( $set['css_id'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', wp_unslash( $set['css_id'] ) ) : '',
				'rules'       => $rules,
				'enabled'     => $enabled,
				'required'    => $required,
			);
		}

		return $out;
	}

	public static function generate_set_id() {
		return 'set_' . wp_generate_password( 12, false, false );
	}

	/**
	 * Sem regras: o conjunto aplica-se a todos os produtos. Com regras: lógica AND/OR em cadeia.
	 *
	 * @param int   $product_id ID do produto (pai em variações na página do produto).
	 * @param array $rules      Lista de regras sanitizadas.
	 */
	public static function rules_match_product( $product_id, $rules ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return false;
		}

		if ( empty( $rules ) ) {
			return true;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$acc = null;
		foreach ( $rules as $idx => $rule ) {
			$current = self::evaluate_single_rule( $product_id, $product, $rule );
			if ( 0 === $idx ) {
				$acc = $current;
				continue;
			}
			$join = isset( $rule['join'] ) ? $rule['join'] : 'AND';
			if ( 'OR' === $join ) {
				$acc = (bool) $acc || (bool) $current;
			} else {
				$acc = (bool) $acc && (bool) $current;
			}
		}

		return (bool) $acc;
	}

	/**
	 * @param int              $product_id
	 * @param WC_Product|false $product
	 * @param array            $rule
	 */
	protected static function evaluate_single_rule( $product_id, $product, $rule ) {
		$subject  = $rule['subject'] ?? 'product';
		$operator = $rule['operator'] ?? 'equals';
		$oid      = isset( $rule['object_id'] ) ? absint( $rule['object_id'] ) : 0;

		switch ( $subject ) {
			case 'product':
				$pid   = (int) $product_id;
				$match = ( $pid === $oid );
				if ( ! $match && $product && $product->is_type( 'variation' ) ) {
					$match = ( (int) $product->get_parent_id() === $oid );
				}
				break;
			case 'category':
				$check_id = $product && $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product_id;
				$terms    = wp_get_post_terms( $check_id, 'product_cat', array( 'fields' => 'ids' ) );
				$terms    = is_wp_error( $terms ) ? array() : $terms;
				$match    = in_array( $oid, $terms, true );
				break;
			case 'product_tag':
				$check_id = $product && $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product_id;
				$terms    = wp_get_post_terms( $check_id, 'product_tag', array( 'fields' => 'ids' ) );
				$terms    = is_wp_error( $terms ) ? array() : $terms;
				$match    = in_array( $oid, $terms, true );
				break;
			default:
				$match = false;
		}

		if ( 'not_equals' === $operator ) {
			$match = ! $match;
		}

		return (bool) $match;
	}

	/**
	 * Conjuntos visíveis para o produto (opções não vazias e regras satisfeitas).
	 *
	 * @param int $product_id ID principal na página (pai para variações).
	 * @return array<int,array>
	 */
	public static function get_visible_sets_for_product( $product_id ) {
		$config = self::get_config();
		$out    = array();
		foreach ( $config['sets'] as $set ) {
			if ( array_key_exists( 'enabled', $set ) && empty( $set['enabled'] ) ) {
				continue;
			}
			if ( empty( $set['options'] ) ) {
				continue;
			}
			if ( ! self::rules_match_product( $product_id, isset( $set['rules'] ) ? $set['rules'] : array() ) ) {
				continue;
			}
			$out[] = $set;
		}
		return $out;
	}

	/**
	 * @param string $set_id
	 * @return array|null
	 */
	public static function get_set_by_id( $set_id ) {
		$set_id = sanitize_key( $set_id );
		foreach ( self::get_config()['sets'] as $set ) {
			if ( isset( $set['id'] ) && $set['id'] === $set_id ) {
				return $set;
			}
		}
		return null;
	}

	/**
	 * Soma dos preços das opções escolhidas (índices válidos).
	 *
	 * @param array $set   Definição do conjunto.
	 * @param int[] $indices Índices das opções (0-based).
	 */
	public static function calculate_addon_total( $set, $indices ) {
		if ( empty( $set['options'] ) || ! is_array( $indices ) ) {
			return 0.0;
		}
		$total = 0.0;
		foreach ( $indices as $i ) {
			$i = absint( $i );
			if ( ! isset( $set['options'][ $i ] ) ) {
				continue;
			}
			$total += (float) wc_format_decimal( $set['options'][ $i ]['price'] );
		}
		return $total;
	}

	/**
	 * Etiquetas para metadados (carrinho/encomenda).
	 *
	 * @param array $set
	 * @param int[] $indices
	 * @return string[]
	 */
	public static function labels_for_selection( $set, $indices ) {
		$labels = array();
		foreach ( $indices as $i ) {
			$i = absint( $i );
			if ( isset( $set['options'][ $i ] ) ) {
				$labels[] = $set['options'][ $i ]['label'];
			}
		}
		return $labels;
	}
}
