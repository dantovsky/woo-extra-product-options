<?php
/**
 * Plugin Name: Woo Extra Product Opitons
 * Description: Opções extra para produtos do WooCommerce com regras por produto/categoria/tag, preço dinâmico e integração com carrinho e encomendas.
 * Version: 1.1.1
 * Author: deinglass
 * Text Domain: woo-extra
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 10.6
 *
 * @package Woo_Extra
 */

defined( 'ABSPATH' ) || exit;

define( 'WOO_EXTRA_VERSION', '1.1.1' );
define( 'WOO_EXTRA_FILE', __FILE__ );
define( 'WOO_EXTRA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_EXTRA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Inicialização após plugins carregados (WooCommerce).
 */
function woo_extra_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Woo Extras necessita do WooCommerce ativo.', 'woo-extra' ) . '</p></div>';
			}
		);
		return;
	}

	if ( is_admin() && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.2', '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				if ( ! defined( 'WC_VERSION' ) ) {
					return;
				}
				echo '<div class="notice notice-warning"><p>';
				echo esc_html(
					sprintf(
						/* translators: %s: WooCommerce version */
						__( 'Woo Extras foi testado com WooCommerce 8.2 ou superior (recomendado para WordPress 6.9 e WooCommerce 10.6). A tua versão é %s.', 'woo-extra' ),
						WC_VERSION
					)
				);
				echo '</p></div>';
			}
		);
	}

	require_once WOO_EXTRA_PATH . 'includes/class-woo-extra-core.php';
	require_once WOO_EXTRA_PATH . 'includes/class-woo-extra-admin.php';
	require_once WOO_EXTRA_PATH . 'includes/class-woo-extra-frontend.php';
	require_once WOO_EXTRA_PATH . 'includes/class-woo-extra-cart.php';

	Woo_Extra_Core::init();
	Woo_Extra_Admin::init();
	Woo_Extra_Frontend::init();
	Woo_Extra_Cart::init();
}
add_action( 'plugins_loaded', 'woo_extra_bootstrap', 11 );

add_action(
	'before_woocommerce_init',
	function () {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WOO_EXTRA_FILE, true );

		// Carrinho/checkout em blocos (desde WooCommerce 7.6; recomendado em 8.3+ e 10.x).
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.6', '>=' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WOO_EXTRA_FILE, true );
		}
	}
);
