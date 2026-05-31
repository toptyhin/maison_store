/* global productOptionsEditorConfig */
(function ($) {
	'use strict';

	var ProductOptions = {
		config: null,
		optionRow: 0,
		optionValueRow: 0,
		choiceTypes: ['select', 'radio', 'checkbox', 'image']
	};

	function escapeHtml(str) {
		if (str === null || str === undefined) {
			return '';
		}

		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function labels() {
		return ProductOptions.config.labels || {};
	}

	function isChoiceType(type) {
		return ProductOptions.choiceTypes.indexOf(type) !== -1;
	}

	function sortCustomFieldKeys(keys) {
		return keys.slice().sort(function (a, b) {
			var sortA = parseInt(a.sort_order, 10) || 0;
			var sortB = parseInt(b.sort_order, 10) || 0;

			if (sortA !== sortB) {
				return sortA - sortB;
			}

			var keyA = a.field_key || a;
			var keyB = b.field_key || b;

			return String(keyA).localeCompare(String(keyB));
		});
	}

	function getOptionCustomFieldKeys(optionId) {
		optionId = String(optionId);
		var map = ProductOptions.config.option_custom_field_keys_by_option || {};

		if (map[optionId] && map[optionId].length) {
			return sortCustomFieldKeys(map[optionId]);
		}

		return [];
	}

	function mapCustomFieldsByKey(customFields) {
		var byKey = {};

		if (!customFields || !customFields.length) {
			return byKey;
		}

		for (var i = 0; i < customFields.length; i++) {
			var field = customFields[i];

			if (field && field.key) {
				byKey[field.key] = field;
			}
		}

		return byKey;
	}

	function buildPrefixSelect(name, selected) {
		var prefixes = ['+', '-', '='];
		var html = '<select name="' + escapeHtml(name) + '" class="form-control input-sm option-prefix-select" style="width:58px;display:inline-block;vertical-align:middle;">';

		for (var i = 0; i < prefixes.length; i++) {
			var prefix = prefixes[i];
			html += '<option value="' + prefix + '"' + (selected === prefix ? ' selected="selected"' : '') + '>' + prefix + '</option>';
		}

		html += '</select>';

		return html;
	}

	function buildSubtractSelect(name, subtract) {
		var L = labels();
		var html = '<select name="' + escapeHtml(name) + '" class="form-control">';

		html += '<option value="1"' + (parseInt(subtract, 10) ? ' selected="selected"' : '') + '>' + escapeHtml(L.text_yes) + '</option>';
		html += '<option value="0"' + (!parseInt(subtract, 10) ? ' selected="selected"' : '') + '>' + escapeHtml(L.text_no) + '</option>';
		html += '</select>';

		return html;
	}

	function buildOptionValueSelect(optionRow, valueRow, optionId, selectedId) {
		var values = ProductOptions.config.option_values[optionId] || [];
		var html = '<select name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][option_value_id]" class="form-control">';

		for (var i = 0; i < values.length; i++) {
			var ov = values[i];
			html += '<option value="' + ov.option_value_id + '"' + (parseInt(selectedId, 10) === parseInt(ov.option_value_id, 10) ? ' selected="selected"' : '') + '>' + escapeHtml(ov.name) + '</option>';
		}

		html += '</select>';

		return html;
	}

	function buildGroupPricesBlock(optionRow, valueRow, valueData) {
		var L = labels();
		var groupPrices = valueData.customer_group_prices || {};
		var hiddenPrice = valueData.price !== undefined && valueData.price !== null ? valueData.price : '';
		var pricePrefix = valueData.price_prefix || '+';
		var html = '';

		html += '<div class="option-price-block">';
		html += '<input type="hidden" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][price_prefix]" value="' + escapeHtml(pricePrefix) + '" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][price]" value="' + escapeHtml(hiddenPrice) + '" class="option-default-price" />';
		html += '<label class="control-label">' + escapeHtml(L.entry_price) + '</label>';
		html += '<table class="table table-condensed table-bordered option-group-prices-table">';
		html += '<thead><tr><th>' + escapeHtml(L.entry_customer_group) + '</th><th style="width:140px;">' + escapeHtml(L.entry_price) + '</th><th style="width:140px;">' + escapeHtml(L.entry_special) + '</th></tr></thead><tbody>';

		var groups = ProductOptions.config.customer_groups || [];

		for (var g = 0; g < groups.length; g++) {
			var cg = groups[g];
			var cgPrices = groupPrices[cg.id] || groupPrices[String(cg.id)] || {};
			var priceVal = cgPrices.price !== undefined && cgPrices.price !== null ? cgPrices.price : '';
			var specialVal = cgPrices.special_price !== undefined && cgPrices.special_price !== null ? cgPrices.special_price : '';
			var isDefault = parseInt(cg.id, 10) === parseInt(ProductOptions.config.default_customer_group_id, 10);

			html += '<tr>';
			html += '<td>' + escapeHtml(cg.name) + (isDefault ? ' <span class="label label-default" title="Default">*</span>' : '') + '</td>';
			html += '<td><input type="text" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][customer_group_prices][' + cg.id + '][price]" value="' + escapeHtml(priceVal) + '" placeholder="0.0000" class="form-control input-sm option-group-price-input" data-customer-group-id="' + cg.id + '"' + (isDefault ? ' data-default-group="1"' : '') + ' /></td>';
			html += '<td><input type="text" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][customer_group_prices][' + cg.id + '][special_price]" value="' + escapeHtml(specialVal) + '" placeholder="0.0000" class="form-control input-sm" /></td>';
			html += '</tr>';
		}

		html += '</tbody></table></div>';

		return html;
	}

	function buildCustomFieldValueInput(optionRow, valueRow, fieldIndex, fieldKey, fieldValue, thumb) {
		var placeholder = ProductOptions.config.placeholder || '';
		var nameBase = 'product_option[' + optionRow + '][product_option_value][' + valueRow + '][custom_fields][' + fieldIndex + ']';

		if (fieldKey === 'images') {
			var imgSrc = thumb || placeholder;

			return '<div class="option-cf-image-value">' +
				'<a href="" id="thumb-option-cf-' + optionRow + '-' + valueRow + '-' + fieldIndex + '" data-toggle="image" class="img-thumbnail"><img src="' + escapeHtml(imgSrc) + '" alt="" data-placeholder="' + escapeHtml(placeholder) + '" /></a>' +
				'<input type="hidden" name="' + nameBase + '[value]" value="' + escapeHtml(fieldValue || '') + '" id="input-option-cf-' + optionRow + '-' + valueRow + '-' + fieldIndex + '" />' +
				'</div>';
		}

		return '<input type="text" name="' + nameBase + '[value]" value="' + escapeHtml(fieldValue || '') + '" placeholder="' + escapeHtml(labels().entry_option_field_value) + '" class="form-control input-sm" />';
	}

	function buildConfiguredFieldCell(optionRow, valueRow, fieldIndex, cfg, fieldData) {
		var fieldKey = cfg.field_key || cfg;
		var fieldLabel = cfg.name ? cfg.name : fieldKey;
		var fieldValue = fieldData ? fieldData.value : '';
		var fieldThumb = fieldData ? fieldData.thumb : '';
		var nameBase = 'product_option[' + optionRow + '][product_option_value][' + valueRow + '][custom_fields][' + fieldIndex + ']';
		var cellClass = 'form-group option-custom-field-cell';

		if (fieldKey === 'images') {
			cellClass += ' option-custom-field-cell--image';
		}

		var html = '<div class="' + cellClass + '" data-field-key="' + escapeHtml(fieldKey) + '">';

		html += '<label class="control-label option-custom-field-label">' + escapeHtml(fieldLabel) + '</label>';
		html += '<input type="hidden" name="' + nameBase + '[key]" value="' + escapeHtml(fieldKey) + '" />';
		html += buildCustomFieldValueInput(optionRow, valueRow, fieldIndex, fieldKey, fieldValue, fieldThumb);
		html += '</div>';

		return html;
	}

	function buildCustomFieldsGrid(optionRow, valueRow, optionId, customFields) {
		var L = labels();
		var keys = getOptionCustomFieldKeys(optionId);

		if (!keys.length) {
			return '';
		}

		var byKey = mapCustomFieldsByKey(customFields);
		var html = '<div class="option-custom-fields-grid" data-option-row="' + optionRow + '" data-value-row="' + valueRow + '" data-option-id="' + optionId + '">';

		html += '<label class="control-label">' + escapeHtml(L.entry_option_custom_fields) + '</label>';
		html += '<div class="option-custom-fields-configured">';

		for (var i = 0; i < keys.length; i++) {
			var fieldKey = keys[i].field_key || keys[i];

			html += buildConfiguredFieldCell(optionRow, valueRow, i, keys[i], byKey[fieldKey]);
		}

		html += '</div></div>';

		return html;
	}

	function buildOptionValueCard(optionRow, valueRow, optionId, valueData) {
		var L = labels();
		valueData = valueData || {};
		var html = '<div class="panel panel-default option-value-card" id="option-value-card-' + valueRow + '" data-value-row="' + valueRow + '">';

		html += '<div class="panel-heading clearfix">';
		html += '<div class="row"><div class="col-sm-9">';
		html += '<label class="control-label" style="margin-right:8px;">' + escapeHtml(L.entry_option_value) + '</label>';
		html += buildOptionValueSelect(optionRow, valueRow, optionId, valueData.option_value_id);
		html += '<input type="hidden" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][product_option_value_id]" value="' + escapeHtml(valueData.product_option_value_id || '') + '" />';
		html += '</div><div class="col-sm-3 text-right">';
		html += '<button type="button" class="btn btn-danger btn-sm btn-remove-option-value" data-value-row="' + valueRow + '" title="' + escapeHtml(L.button_remove) + '"><i class="fa fa-minus-circle"></i></button>';
		html += '</div></div></div>';

		html += '<div class="panel-body option-value-card-body">';
		html += '<div class="row">';
		html += '<div class="col-sm-6 option-value-card-col-stock">';
		html += '<div class="form-group"><label class="control-label">' + escapeHtml(L.entry_quantity) + '</label>';
		html += '<input type="text" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][quantity]" value="' + escapeHtml(valueData.quantity !== undefined ? valueData.quantity : '') + '" placeholder="' + escapeHtml(L.entry_quantity) + '" class="form-control" /></div>';
		html += '<div class="form-group"><label class="control-label">' + escapeHtml(L.entry_subtract) + '</label>';
		html += buildSubtractSelect('product_option[' + optionRow + '][product_option_value][' + valueRow + '][subtract]', valueData.subtract) + '</div>';
		html += '<div class="form-group"><label class="control-label">' + escapeHtml(L.entry_weight) + '</label>';
		html += '<div class="option-prefix-input-row">' + buildPrefixSelect('product_option[' + optionRow + '][product_option_value][' + valueRow + '][weight_prefix]', valueData.weight_prefix || '=');
		html += '<input type="text" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][weight]" value="' + escapeHtml(valueData.weight !== undefined ? valueData.weight : '') + '" placeholder="' + escapeHtml(L.entry_weight) + '" class="form-control option-prefix-input" /></div></div>';
		html += '<div class="form-group"><label class="control-label">' + escapeHtml(L.entry_option_points) + '</label>';
		html += '<div class="option-prefix-input-row">' + buildPrefixSelect('product_option[' + optionRow + '][product_option_value][' + valueRow + '][points_prefix]', valueData.points_prefix || '=');
		html += '<input type="text" name="product_option[' + optionRow + '][product_option_value][' + valueRow + '][points]" value="' + escapeHtml(valueData.points !== undefined ? valueData.points : '') + '" placeholder="' + escapeHtml(L.entry_points) + '" class="form-control option-prefix-input" /></div></div>';
		html += '</div>';
		html += '<div class="col-sm-6 option-value-card-col-prices">';
		html += buildGroupPricesBlock(optionRow, valueRow, valueData);
		html += buildCustomFieldsGrid(optionRow, valueRow, optionId, valueData.custom_fields || []);
		html += '</div>';
		html += '</div>';
		html += '</div></div>';

		return html;
	}

	function getCardsContainer(optionRow) {
		return $('#option-value-cards-' + optionRow);
	}

	function appendOptionValueCard(optionRow, optionId, valueData) {
		var valueRow = ProductOptions.optionValueRow;
		var html = buildOptionValueCard(optionRow, valueRow, optionId, valueData);

		getCardsContainer(optionRow).append(html);
		ProductOptions.optionValueRow++;

		return valueRow;
	}

	function renderChoiceOptionValues(optionRow, productOption) {
		var values = productOption.product_option_value || [];
		var container = getCardsContainer(optionRow);

		container.empty();

		for (var i = 0; i < values.length; i++) {
			appendOptionValueCard(optionRow, productOption.option_id, values[i]);
		}
	}

	function renderExistingOptions() {
		var options = ProductOptions.config.product_options || [];

		for (var optionRow = 0; optionRow < options.length; optionRow++) {
			var productOption = options[optionRow];

			if (!isChoiceType(productOption.type)) {
				continue;
			}

			renderChoiceOptionValues(optionRow, productOption);
		}
	}

	function buildHiddenOptionValuesSelect(optionRow, optionValues) {
		var html = '<select id="option-values' + optionRow + '" style="display: none;">';

		for (var i = 0; i < optionValues.length; i++) {
			html += '<option value="' + optionValues[i].option_value_id + '">' + escapeHtml(optionValues[i].name) + '</option>';
		}

		html += '</select>';

		return html;
	}

	function buildRequiredField(optionRow) {
		var L = labels();
		var html = '<div class="form-group">';
		html += '<label class="col-sm-2 control-label" for="input-required' + optionRow + '">' + escapeHtml(L.entry_required) + '</label>';
		html += '<div class="col-sm-10"><select name="product_option[' + optionRow + '][required]" id="input-required' + optionRow + '" class="form-control">';
		html += '<option value="1">' + escapeHtml(L.text_yes) + '</option>';
		html += '<option value="0" selected="selected">' + escapeHtml(L.text_no) + '</option>';
		html += '</select></div></div>';

		return html;
	}

	function buildSimpleOptionFields(optionRow, type) {
		var L = labels();
		var html = '';

		if (type === 'text') {
			html += '<div class="form-group"><label class="col-sm-2 control-label" for="input-value' + optionRow + '">' + escapeHtml(L.entry_option_value) + '</label>';
			html += '<div class="col-sm-10"><input type="text" name="product_option[' + optionRow + '][value]" value="" placeholder="' + escapeHtml(L.entry_option_value) + '" id="input-value' + optionRow + '" class="form-control" /></div></div>';
		} else if (type === 'textarea') {
			html += '<div class="form-group"><label class="col-sm-2 control-label" for="input-value' + optionRow + '">' + escapeHtml(L.entry_option_value) + '</label>';
			html += '<div class="col-sm-10"><textarea name="product_option[' + optionRow + '][value]" rows="5" placeholder="' + escapeHtml(L.entry_option_value) + '" id="input-value' + optionRow + '" class="form-control"></textarea></div></div>';
		} else if (type === 'file') {
			html += '<div class="form-group" style="display:none;"><label class="col-sm-2 control-label" for="input-value' + optionRow + '">' + escapeHtml(L.entry_option_value) + '</label>';
			html += '<div class="col-sm-10"><input type="text" name="product_option[' + optionRow + '][value]" value="" placeholder="' + escapeHtml(L.entry_option_value) + '" id="input-value' + optionRow + '" class="form-control" /></div></div>';
		} else if (type === 'date') {
			html += '<div class="form-group"><label class="col-sm-2 control-label" for="input-value' + optionRow + '">' + escapeHtml(L.entry_option_value) + '</label>';
			html += '<div class="col-sm-3"><div class="input-group date"><input type="text" name="product_option[' + optionRow + '][value]" value="" placeholder="' + escapeHtml(L.entry_option_value) + '" data-date-format="YYYY-MM-DD" id="input-value' + optionRow + '" class="form-control" /><span class="input-group-btn"><button type="button" class="btn btn-default"><i class="fa fa-calendar"></i></button></span></div></div></div>';
		} else if (type === 'time') {
			html += '<div class="form-group"><label class="col-sm-2 control-label" for="input-value' + optionRow + '">' + escapeHtml(L.entry_option_value) + '</label>';
			html += '<div class="col-sm-10"><div class="input-group time"><input type="text" name="product_option[' + optionRow + '][value]" value="" placeholder="' + escapeHtml(L.entry_option_value) + '" data-date-format="HH:mm" id="input-value' + optionRow + '" class="form-control" /><span class="input-group-btn"><button type="button" class="btn btn-default"><i class="fa fa-calendar"></i></button></span></div></div></div>';
		} else if (type === 'datetime') {
			html += '<div class="form-group"><label class="col-sm-2 control-label" for="input-value' + optionRow + '">' + escapeHtml(L.entry_option_value) + '</label>';
			html += '<div class="col-sm-10"><div class="input-group datetime"><input type="text" name="product_option[' + optionRow + '][value]" value="" placeholder="' + escapeHtml(L.entry_option_value) + '" data-date-format="YYYY-MM-DD HH:mm" id="input-value' + optionRow + '" class="form-control" /><span class="input-group-btn"><button type="button" class="btn btn-default"><i class="fa fa-calendar"></i></button></span></div></div></div>';
		}

		return html;
	}

	function buildChoiceOptionPane(optionRow, item) {
		var L = labels();
		var html = '<div class="tab-pane" id="tab-option' + optionRow + '">';

		html += '<input type="hidden" name="product_option[' + optionRow + '][product_option_id]" value="" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][name]" value="' + escapeHtml(item.label) + '" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][option_id]" value="' + item.value + '" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][type]" value="' + escapeHtml(item.type) + '" />';
		html += buildRequiredField(optionRow);
		html += '<div class="option-value-cards" id="option-value-cards-' + optionRow + '" data-option-row="' + optionRow + '"></div>';
		html += '<div style="margin-top:10px;"><button type="button" class="btn btn-primary btn-add-option-value" data-option-row="' + optionRow + '" title="' + escapeHtml(L.button_option_value_add) + '"><i class="fa fa-plus-circle"></i></button></div>';
		html += buildHiddenOptionValuesSelect(optionRow, item.option_value || []);
		html += '</div>';

		ProductOptions.config.option_values[item.value] = item.option_value || [];

		return html;
	}

	function buildNonChoiceOptionPane(optionRow, item) {
		var html = '<div class="tab-pane" id="tab-option' + optionRow + '">';

		html += '<input type="hidden" name="product_option[' + optionRow + '][product_option_id]" value="" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][name]" value="' + escapeHtml(item.label) + '" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][option_id]" value="' + item.value + '" />';
		html += '<input type="hidden" name="product_option[' + optionRow + '][type]" value="' + escapeHtml(item.type) + '" />';
		html += buildRequiredField(optionRow);
		html += buildSimpleOptionFields(optionRow, item.type);
		html += '</div>';

		return html;
	}

	function datepickerLanguage() {
		if (ProductOptions.config && ProductOptions.config.datepicker) {
			return ProductOptions.config.datepicker;
		}

		if (typeof datepicker !== 'undefined') {
			return datepicker;
		}

		return 'en-gb';
	}

	function initDateTimePickers($context) {
		var lang = datepickerLanguage();

		$context.find('.date').datetimepicker({
			language: lang,
			pickTime: false
		});
		$context.find('.time').datetimepicker({
			language: lang,
			pickDate: false
		});
		$context.find('.datetime').datetimepicker({
			language: lang,
			pickDate: true,
			pickTime: true
		});
	}

	function syncHiddenPrices($scope) {
		$scope.find('.option-value-card').each(function () {
			var $card = $(this);
			var $defaultInput = $card.find('.option-group-price-input[data-default-group="1"]');

			if ($defaultInput.length) {
				$card.find('.option-default-price').val($defaultInput.val());
			}
		});
	}

	function addOptionFromAutocomplete(item) {
		ProductOptions.config.option_custom_field_keys_by_option[String(item.value)] = item.custom_field_keys || [];

		var optionRow = ProductOptions.optionRow;
		var html;

		if (isChoiceType(item.type)) {
			html = buildChoiceOptionPane(optionRow, item);
		} else {
			html = buildNonChoiceOptionPane(optionRow, item);
		}

		$('#tab-option .tab-content').append(html);

		$('#option > li:last-child').before(
			'<li><a href="#tab-option' + optionRow + '" data-toggle="tab"><i class="fa fa-minus-circle" onclick="$(\'#option a:first\').tab(\'show\');$(\'a[href=\\\'#tab-option' + optionRow + '\\\']\').parent().remove();$(\'#tab-option' + optionRow + '\').remove();"></i> ' + escapeHtml(item.label) + '</a></li>'
		);

		$('#option a[href="#tab-option' + optionRow + '"]').tab('show');

		var $pane = $('#tab-option' + optionRow);

		initDateTimePickers($pane);
		$('[data-toggle="tooltip"]').tooltip({container: 'body', html: true});

		ProductOptions.optionRow++;
	}

	function bindEvents() {
		$(document).on('input change', '.option-group-price-input', function () {
			var $input = $(this);
			var $card = $input.closest('.option-value-card');

			if ($input.data('default-group')) {
				$card.find('.option-default-price').val($input.val());
			}
		});

		$(document).on('click', '.btn-add-option-value', function () {
			var optionRow = $(this).data('option-row');
			var optionId = $('#tab-option' + optionRow + ' input[name="product_option[' + optionRow + '][option_id]"]').val();

			appendOptionValueCard(optionRow, optionId, {});
		});

		$(document).on('click', '.btn-remove-option-value', function () {
			var valueRow = $(this).data('value-row');

			$('#option-value-card-' + valueRow).remove();
		});

		$('input[name="option"]').autocomplete({
			source: function (request, response) {
				$.ajax({
					url: 'index.php?route=catalog/option/autocomplete&user_token=' + ProductOptions.config.user_token + '&filter_name=' + encodeURIComponent(request),
					dataType: 'json',
					success: function (json) {
						response($.map(json, function (item) {
							return {
								category: item.category,
								label: item.name,
								value: item.option_id,
								type: item.type,
								option_value: item.option_value,
								custom_field_keys: item.custom_field_keys || []
							};
						}));
					}
				});
			},
			select: function (item) {
				if (!item) {
					return false;
				}

				$('input[name="option"]').val('');
				addOptionFromAutocomplete(item);

				return false;
			}
		});
	}

	ProductOptions.init = function (config) {
		ProductOptions.config = config;
		ProductOptions.optionRow = config.option_row || 0;
		ProductOptions.optionValueRow = config.option_value_row || 0;

		renderExistingOptions();
		syncHiddenPrices($('#tab-option'));
		bindEvents();
	};

	$(document).ready(function () {
		if (typeof productOptionsEditorConfig !== 'undefined') {
			ProductOptions.init(productOptionsEditorConfig);
		}
	});

	window.ProductOptions = ProductOptions;
})(window.jQuery);
