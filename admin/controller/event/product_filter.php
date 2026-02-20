<?php
/**
 * Product Filter Module - Admin event handlers
 */
class ControllerEventProductFilter extends Controller {
	/**
	 * Add filter config tab after category form is rendered
	 */
	public function categoryFormAfter(&$route, &$data, &$output) {
		if (!isset($data['category_id']) && !isset($this->request->get['category_id'])) {
			return;
		}
		$category_id = isset($data['category_id']) ? $data['category_id'] : (int)$this->request->get['category_id'];
		$this->load->model('extension/module/product_filter');
		$configs = $this->model_extension_module_product_filter->getCategoryFilterConfig($category_id);
		$attributes = $this->model_extension_module_product_filter->getAttributes();
		$options = $this->model_extension_module_product_filter->getOptions();

		$tab_html = '<li><a href="#tab-product-filter" data-toggle="tab">Расширенный фильтр</a></li>';
		$content = '<div class="tab-pane" id="tab-product-filter">';
		$content .= '<p class="text-muted">Настройка критериев фильтрации для этой категории. Добавьте критерии в нужном порядке.</p>';
		$content .= '<div id="product-filter-configs">';
		foreach ($configs as $i => $c) {
			$content .= $this->renderFilterConfigRow($c, $attributes, $options, $i);
		}
		$content .= '</div>';
		$content .= '<button type="button" id="add-filter-criterion" class="btn btn-default" style="margin-top:10px">Добавить критерий</button>';
		$src_opts = '<option value="0">-- Выберите --</option><optgroup label="Атрибуты">';
		foreach ($attributes as $a) {
			$src_opts .= '<option value="' . (int)$a['attribute_id'] . '">' . htmlspecialchars($a['name'], ENT_QUOTES) . '</option>';
		}
		$src_opts .= '</optgroup><optgroup label="Опции">';
		foreach ($options as $o) {
			$src_opts .= '<option value="' . (int)$o['option_id'] . '">' . htmlspecialchars($o['name'], ENT_QUOTES) . '</option>';
		}
		$src_opts .= '</optgroup>';
		$content .= '<script type="text/template" id="product-filter-source-options">' . $src_opts . '</script>';
		$content .= '<script>
		$(document).ready(function(){
			$("#add-filter-criterion").on("click", function(){
				var idx = $("#product-filter-configs .filter-criterion-row").length;
				var srcHtml = $("#product-filter-source-options").html();
				var row = $(\'<div class="well well-sm filter-criterion-row" style="margin-bottom:8px"><select name="product_filter_config[\'+idx+\'][criterion_type]" class="form-control" style="display:inline-block;width:150px"><option value="price">Цена</option><option value="manufacturer">Производитель</option><option value="rating">Рейтинг</option><option value="discount">Скидки</option><option value="attribute">Атрибут</option><option value="option">Опция</option></select> <select name="product_filter_config[\'+idx+\'][source_id]" class="form-control filter-source" style="display:none;width:180px">\'+srcHtml+\'</select> <select name="product_filter_config[\'+idx+\'][widget_type]" class="form-control" style="display:inline-block;width:120px"><option value="checkboxes">Чекбоксы</option><option value="select">Селект</option><option value="slider">Слайдер</option><option value="inputs">Поля ввода</option></select> <button type="button" class="btn btn-danger btn-sm remove-criterion">×</button></div>\');
				$("#product-filter-configs").append(row);
			});
			$(document).on("change", "#product-filter-configs select[name*=\\"[criterion_type]\\"]", function(){
				var t = $(this).val();
				var src = $(this).siblings(".filter-source");
				if(t==="attribute"||t==="option"){ src.show(); } else { src.hide(); src.val(0); }
			});
			$(document).on("click", ".remove-criterion", function(){ $(this).closest(".filter-criterion-row").remove(); });
		});
		</script>';
		$content .= '</div>';

		$output = str_replace('<li><a href="#tab-design"', $tab_html . '<li><a href="#tab-design"', $output);
		$output = str_replace('<div class="tab-pane" id="tab-design">', $content . '<div class="tab-pane" id="tab-design">', $output);
	}

	protected function renderFilterConfigRow($config, $attributes, $options, $index) {
		$ct = isset($config['criterion_type']) ? $config['criterion_type'] : 'price';
		$types = array('price' => 'Цена', 'manufacturer' => 'Производитель', 'rating' => 'Рейтинг', 'discount' => 'Скидки', 'attribute' => 'Атрибут', 'option' => 'Опция');
		$widgets = array('checkboxes' => 'Чекбоксы', 'select' => 'Селект', 'slider' => 'Слайдер', 'inputs' => 'Поля ввода');
		$html = '<div class="well well-sm filter-criterion-row" style="margin-bottom:8px">';
		$html .= '<select name="product_filter_config[' . $index . '][criterion_type]" class="form-control" style="display:inline-block;width:150px">';
		foreach ($types as $k => $v) {
			$sel = ($ct == $k) ? ' selected' : '';
			$html .= '<option value="' . $k . '"' . $sel . '>' . $v . '</option>';
		}
		$html .= '</select> ';
		$html .= '<select name="product_filter_config[' . $index . '][source_id]" class="form-control filter-source" style="display:inline-block;width:180px;' . (in_array($ct, array('attribute', 'option')) ? '' : 'display:none!important') . '">';
		$html .= '<option value="0">-- Выберите --</option>';
		$html .= '<optgroup label="Атрибуты">';
		foreach ($attributes as $a) {
			$sel = ($ct == 'attribute' && isset($config['source_id']) && $config['source_id'] == $a['attribute_id']) ? ' selected' : '';
			$html .= '<option value="' . $a['attribute_id'] . '" data-type="attribute"' . $sel . '>' . $a['name'] . '</option>';
		}
		$html .= '</optgroup><optgroup label="Опции">';
		foreach ($options as $o) {
			$sel = ($ct == 'option' && isset($config['source_id']) && $config['source_id'] == $o['option_id']) ? ' selected' : '';
			$html .= '<option value="' . $o['option_id'] . '" data-type="option"' . $sel . '>' . $o['name'] . '</option>';
		}
		$html .= '</optgroup></select> ';
		$html .= '<select name="product_filter_config[' . $index . '][widget_type]">';
		foreach ($widgets as $k => $v) {
			$sel = (isset($config['widget_type']) && $config['widget_type'] == $k) ? ' selected' : '';
			$html .= '<option value="' . $k . '"' . $sel . '>' . $v . '</option>';
		}
		$html .= '</select> ';
		$html .= '<button type="button" class="btn btn-danger btn-sm remove-criterion">×</button>';
		$html .= '</div>';
		return $html;
	}

	public function addCategoryAfter(&$route, &$args, &$output) {
		$category_id = $output;
		if ($category_id && isset($this->request->post['product_filter_config']) && is_array($this->request->post['product_filter_config'])) {
			$this->load->model('extension/module/product_filter');
			$this->model_extension_module_product_filter->saveCategoryFilterConfig($category_id, $this->request->post['product_filter_config']);
		}
	}

	public function editCategoryAfter(&$route, &$args, &$output) {
		$category_id = isset($args[0]) ? $args[0] : (int)$this->request->get['category_id'];
		if ($category_id && isset($this->request->post['product_filter_config']) && is_array($this->request->post['product_filter_config'])) {
			$this->load->model('extension/module/product_filter');
			$this->model_extension_module_product_filter->saveCategoryFilterConfig($category_id, $this->request->post['product_filter_config']);
		}
	}
}
