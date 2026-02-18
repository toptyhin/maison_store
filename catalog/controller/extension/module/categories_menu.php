<?php
class ControllerExtensionModuleCategoriesMenu extends Controller {
	public function index($setting) {
		$data['categories'] = array();

		if (isset($setting['categories']) && is_array($setting['categories'])) {
			// Сортируем категории по sort_order
			$categories = $setting['categories'];
			usort($categories, function($a, $b) {
				$a_order = isset($a['sort_order']) ? (int)$a['sort_order'] : 0;
				$b_order = isset($b['sort_order']) ? (int)$b['sort_order'] : 0;
				return $a_order - $b_order;
			});

			foreach ($categories as $category) {
				if (isset($category['status']) && $category['status']) {
					$category_data = array(
						'icon' => isset($category['icon']) ? $category['icon'] : '',
						'text' => isset($category['text']) ? $category['text'] : '',
						'href' => isset($category['href']) ? $category['href'] : '#'
					);

					// Если указан category_id, получаем ссылку на категорию
					if (isset($category['category_id']) && $category['category_id']) {
						$this->load->model('catalog/category');
						$category_info = $this->model_catalog_category->getCategory($category['category_id']);
						
						if ($category_info) {
							$category_data['href'] = $this->url->link('product/category', 'path=' . $category['category_id']);
							if (empty($category_data['text'])) {
								$category_data['text'] = $category_info['name'];
							}
						}
					}

					$data['categories'][] = $category_data;
				}
			}
		}

		return $this->load->view('extension/module/categories_menu', $data);
	}
}
