/* global jQuery, wooExtraAdmin */
(function ($) {
	'use strict';

	function escRe(s) {
		return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	/**
	 * Atualiza índices do conjunto no HTML clonado: names[] e data-set (evita opções a irem para o conjunto errado).
	 */
	function replaceSetIndex(html, oldIdx, newIdx) {
		oldIdx = String(oldIdx);
		newIdx = String(newIdx);
		html = html.replace(new RegExp('sets\\]\\[' + escRe(oldIdx) + '\\]', 'g'), 'sets][' + newIdx + ']');
		html = html.replace(new RegExp('data-set="' + escRe(oldIdx) + '"', 'g'), 'data-set="' + newIdx + '"');
		html = html.replace(new RegExp("data-set='" + escRe(oldIdx) + "'", 'g'), "data-set='" + newIdx + "'");
		return html;
	}

	function refillObjectSelect($select, subject) {
		var list = wooExtraAdmin.objects && wooExtraAdmin.objects[subject] ? wooExtraAdmin.objects[subject] : [];
		$select.empty();
		$select.append($('<option></option>').attr('value', '0').text('\u2014'));
		$.each(list, function (_, item) {
			$select.append($('<option></option>').attr('value', item.id).text(item.name));
		});
	}

	function nextOptionIndex($tbody) {
		var max = -1;
		$tbody.find('input[name],select[name]').each(function () {
			var m = ($(this).attr('name') || '').match(/\[options\]\[(\d+)\]/);
			if (m) {
				max = Math.max(max, parseInt(m[1], 10));
			}
		});
		return String(max + 1);
	}

	function nextRuleIndex($tbody) {
		var max = -1;
		$tbody.find('input[name],select[name]').each(function () {
			var m = ($(this).attr('name') || '').match(/\[rules\]\[(\d+)\]/);
			if (m) {
				max = Math.max(max, parseInt(m[1], 10));
			}
		});
		return String(max + 1);
	}

	function setAccordionExpanded($set, expanded) {
		var $btn = $set.find('.woo-extra-set-accordion-toggle');
		var $panel = $set.find('.woo-extra-set-panel');
		var $chev = $set.find('.woo-extra-set-chevron');
		$btn.attr('aria-expanded', expanded ? 'true' : 'false');
		if (expanded) {
			$set.removeClass('woo-extra-set-collapsed');
			$panel.prop('hidden', false);
			$chev.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
		} else {
			$set.addClass('woo-extra-set-collapsed');
			$panel.prop('hidden', true);
			$chev.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
		}
	}

	function syncEnabledToggleVisual($set) {
		var on = $set.find('.woo-extra-set-enabled-cb').prop('checked');
		$set.find('.woo-extra-set-enabled-toggle').toggleClass('is-on', !!on).toggleClass('is-off', !on);
		var labels = wooExtraAdmin.enabledLabels;
		var onText = labels && labels.on ? labels.on : 'Habilitado';
		var offText = labels && labels.off ? labels.off : 'Desabilitado';
		$set.find('.woo-extra-set-enabled-text').text(on ? onText : offText);
	}

	function initSetsSortable() {
		var $sets = $('#woo-extra-sets');
		if (!$sets.length || typeof $sets.sortable !== 'function') {
			return;
		}
		if ($sets.data('ui-sortable')) {
			$sets.sortable('destroy');
		}
		$sets.sortable({
			handle: '.woo-extra-set-drag',
			items: '> .woo-extra-set',
			axis: 'y',
			cursor: 'grabbing',
			tolerance: 'pointer',
			placeholder: 'woo-extra-set-placeholder',
			forcePlaceholderSize: true,
		});
	}

	$(document).on('change', '.woo-extra-rule-subject', function () {
		var $row = $(this).closest('tr');
		var sub = $(this).val();
		refillObjectSelect($row.find('.woo-extra-rule-object-id'), sub);
	});

	$(document).on('click', '.woo-extra-set-accordion-toggle', function (e) {
		e.preventDefault();
		var $set = $(this).closest('.woo-extra-set');
		var exp = $(this).attr('aria-expanded') === 'true';
		setAccordionExpanded($set, !exp);
	});

	$(document).on('click', '.woo-extra-set-move-up', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $set = $(this).closest('.woo-extra-set');
		var $prev = $set.prev('.woo-extra-set');
		if ($prev.length) {
			$set.insertBefore($prev);
		}
	});

	$(document).on('click', '.woo-extra-set-move-down', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $set = $(this).closest('.woo-extra-set');
		var $next = $set.next('.woo-extra-set');
		if ($next.length) {
			$set.insertAfter($next);
		}
	});

	$(document).on('click', '.woo-extra-set-required-cb, .woo-extra-set-enabled-cb, .woo-extra-set-enabled-toggle, .woo-extra-set-order-buttons button', function (e) {
		e.stopPropagation();
	});

	$(document).on('change', '.woo-extra-set-enabled-cb', function () {
		syncEnabledToggleVisual($(this).closest('.woo-extra-set'));
	});

	$('#woo-extra-add-set').on('click', function () {
		var $sets = $('#woo-extra-sets');
		var $src = $sets.find('.woo-extra-set').first();
		if (!$src.length) {
			return;
		}
		var oldIdx = $src.attr('data-set-index');
		var newIdx = 'n' + Date.now();
		var html = replaceSetIndex($src.prop('outerHTML'), oldIdx, newIdx);
		var $box = $(html);
		$box.attr('data-set-index', newIdx);
		$box.find('[data-set]').attr('data-set', newIdx);
		$box.removeClass('woo-extra-set-collapsed');
		setAccordionExpanded($box, true);
		$box.find('input').each(function () {
			var $i = $(this);
			var n = $i.attr('name') || '';
			var t = $i.attr('type');
			if (n.indexOf('[sets]') !== -1 && n.indexOf('[id]') !== -1 && n.indexOf('[css_id]') === -1) {
				$i.val('set_' + Math.random().toString(36).slice(2, 14));
			} else if (t === 'text' || t === 'search' || t === 'url') {
				$i.val('');
			}
		});
		$box.find('select').each(function () {
			var $s = $(this);
			if ($s.hasClass('woo-extra-rule-object-id')) {
				return;
			}
			$s.prop('selectedIndex', 0);
		});
		$box.find('.woo-extra-set-required-cb').prop('checked', false);
		$box.find('.woo-extra-set-enabled-cb').prop('checked', true);
		syncEnabledToggleVisual($box);
		$box.find('.woo-extra-options-body').empty().append(
			$(replaceSetIndex(wooExtraAdmin.optionRow, '{{SET}}', newIdx).replace(/\{\{I\}\}/g, '0'))
		);
		$box.find('.woo-extra-rules-body').empty();
		var defHeading = wooExtraAdmin.defaultSetHeading || '\u2014';
		$box.find('.woo-extra-set-heading').text(defHeading).addClass('is-placeholder');
		$sets.append($box);
		initSetsSortable();
	});

	$(document).on('click', '.woo-extra-remove-set', function (e) {
		e.stopPropagation();
		var $sets = $('#woo-extra-sets');
		if ($sets.find('.woo-extra-set').length < 2) {
			return;
		}
		$(this).closest('.woo-extra-set').remove();
		initSetsSortable();
	});

	$(document).on('input', '.woo-extra-set-name-input', function () {
		var $set = $(this).closest('.woo-extra-set');
		var v = $(this).val().trim();
		var def = wooExtraAdmin.defaultSetHeading || '\u2014';
		var $h = $set.find('.woo-extra-set-heading');
		$h.text(v || def);
		$h.toggleClass('is-placeholder', !v);
	});

	$(document).on('click', '.woo-extra-add-option', function () {
		var setIdx = $(this).closest('.woo-extra-set').attr('data-set-index');
		if (!setIdx) {
			return;
		}
		var $tbody = $(this).closest('.inside').find('.woo-extra-options-body');
		var ni = nextOptionIndex($tbody);
		var row = wooExtraAdmin.optionRow.replace(/\{\{SET\}\}/g, setIdx).replace(/\{\{I\}\}/g, ni);
		$tbody.append(row);
	});

	$(document).on('click', '.woo-extra-remove-option', function () {
		var $tb = $(this).closest('tbody');
		if ($tb.find('tr').length < 2) {
			return;
		}
		$(this).closest('tr').remove();
	});

	$(document).on('click', '.woo-extra-add-rule', function () {
		var setIdx = $(this).closest('.woo-extra-set').attr('data-set-index');
		if (!setIdx) {
			return;
		}
		var $tbody = $(this).closest('.inside').find('.woo-extra-rules-body');
		var ri = nextRuleIndex($tbody);
		var isEmpty = $tbody.children('tr').length === 0;
		var tpl = isEmpty ? wooExtraAdmin.ruleRowFirst : wooExtraAdmin.ruleRowNext;
		var row = tpl.replace(/\{\{SET\}\}/g, setIdx).replace(/\{\{RI\}\}/g, ri);
		var $row = $(row);
		if (!isEmpty) {
			$row.attr('data-first', '0');
		}
		$tbody.append($row);
		refillObjectSelect($row.find('.woo-extra-rule-object-id'), $row.find('.woo-extra-rule-subject').val());
	});

	$(document).on('click', '.woo-extra-remove-rule', function () {
		$(this).closest('tr').remove();
	});

	$(function () {
		$('.woo-extra-set').each(function () {
			syncEnabledToggleVisual($(this));
		});
		initSetsSortable();
	});
})(jQuery);
