<?php
class ControllerExtensionModuleSlideshowcustom extends Controller {
	public function index($setting) {
		static $module = 0;		

		$this->load->model('design/banner');
		$this->load->model('tool/image');

	
		$data['banners'] = array();
		$results = $this->model_design_banner->getBanner($setting['banner_id']);
		foreach ($results as $result) {
			$image = '';
			if (is_file(DIR_IMAGE . $result['image'])) {
				$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);
			}
				$data['banners'][] = array(
					'title' => $result['title'],
					'link'  => $result['link'],
					'image' => $image
				);

		}

		$data['module'] = $module++;

		return $this->load->view('extension/module/slideshow', $data);
	}
}