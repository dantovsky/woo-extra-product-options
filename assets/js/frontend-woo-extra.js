/* global jQuery, wooExtraFront */
(function ($) {
	'use strict';

	function formatMoney(num) {
		var d = parseInt(wooExtraFront.decimals, 10);
		if (isNaN(d)) {
			d = 2;
		}
		var fixed = num.toFixed(d);
		var parts = fixed.split('.');
		var intp = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, wooExtraFront.thousandSep || '');
		var decp = parts.length > 1 ? (wooExtraFront.decimalSep || '.') + parts[1] : '';
		var amount = intp + (parts.length > 1 ? decp : '');
		var sym = wooExtraFront.currencySym || '';
		var pos = wooExtraFront.currencyPos || 'left';
		if (pos === 'right') {
			return amount + sym;
		}
		if (pos === 'left_space') {
			return sym + ' ' + amount;
		}
		if (pos === 'right_space') {
			return amount + ' ' + sym;
		}
		return sym + amount;
	}

	function getAddonTotal() {
		var total = 0;
		$('.woo-extra-fields-wrap .woo-extra-input').each(function () {
			var $el = $(this);
			if ($el.is('select')) {
				var $o = $el.find('option:selected');
				total += parseFloat($o.data('add')) || 0;
			} else if ($el.is(':checkbox') && $el.is(':checked')) {
				total += parseFloat($el.data('add')) || 0;
			}
		});
		return total;
	}

	var baseSimple = parseFloat(wooExtraFront.basePrice) || 0;
	var variationBase = null;

	/** Localiza o elemento do preço: temas clássicos vs FSE / blocos WooCommerce. */
	function findProductPriceEl() {
		var selectors = [
			'.product .entry-summary .price',
			'.product .summary .price',
			'div.product div.summary .price',
			'.wp-block-woocommerce-product-price .wc-block-components-product-price',
			'.wp-block-woocommerce-product-price',
			'.wc-block-single-product .wc-block-components-product-price',
			'.woocommerce .product .price'
		];
		var i;
		for (i = 0; i < selectors.length; i++) {
			var $c = $(selectors[i]).first();
			if ($c.length) {
				return $c;
			}
		}
		if ($('body').is('.single-product')) {
			return $('#primary .price, main .price, .site-main .price').first();
		}
		return $();
	}

	function updatePrice() {
		var $wrap = $('.woo-extra-fields-wrap');
		if (!$wrap.length) {
			return;
		}
		var $price = findProductPriceEl();
		if (!$price.length) {
			return;
		}
		if (wooExtraFront.isVariable && variationBase === null) {
			return;
		}
		var b = variationBase !== null ? variationBase : baseSimple;
		var total = b + getAddonTotal();
		var html =
			'<span class="woocommerce-Price-amount amount"><bdi>' +
			formatMoney(total) +
			'</bdi></span>' +
			(wooExtraFront.priceSuffix || '');
		$price.html(html);
	}

	function validateRequiredMultiple() {
		var ok = true;
		$('.woo-extra-fields-wrap fieldset[data-required="1"]').each(function () {
			var $fs = $(this);
			if ($fs.find('select.woo-extra-input').length) {
				return;
			}
			if (!$fs.find('.woo-extra-input:checkbox:checked').length) {
				ok = false;
				return false;
			}
		});
		return ok;
	}

	document.addEventListener(
		'click',
		function (e) {
			if (!$('.woo-extra-fields-wrap').length) {
				return;
			}
			var el = e.target;
			if (!el || typeof el.closest !== 'function') {
				return;
			}
			var btn = el.closest('.single_add_to_cart_button');
			if (!btn) {
				return;
			}
			if (!btn.closest('form.cart') && !btn.closest('form.variations_form')) {
				return;
			}
			if (!validateRequiredMultiple()) {
				var msg = wooExtraFront.strings && wooExtraFront.strings.requiredMultiple ? wooExtraFront.strings.requiredMultiple : '';
				if (msg) {
					window.alert(msg);
				}
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
			}
		},
		true
	);

	$(function () {
		$(document).on('change', '.woo-extra-fields-wrap .woo-extra-input', updatePrice);

		var $form = $('form.variations_form');
		if ($form.length) {
			$form.on('show_variation', function (event, variation) {
				variationBase = parseFloat(variation.display_price) || 0;
				updatePrice();
			});
			$form.on('hide_variation', function () {
				variationBase = null;
			});
		}
	});
})(jQuery);
