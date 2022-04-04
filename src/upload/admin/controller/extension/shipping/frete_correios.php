<?php

class ControllerExtensionShippingFreteCorreios extends Controller {
  const DEFAULT_SHIPPING_SETTINGS = [
		'frete_correios_days_to_prepare' => 3, /* Default days to prepare the product */
    'frete_correios_destination_cep' => '14781123', /* Deafault destination CEP */
    'frete_correios_order' => 1, /* Default order */
		'frete_correios_status' => 1 /* Enabled by default */
	];
    
	private $error = array();
	
	public function index() {
		$this->load->model('setting/setting');

		/* Set page title */
		$this->load->language('extension/shipping/frete_correios');
		$this->document->setTitle($this->language->get('heading_title'));
		
		/* Edit request */
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$this->model_setting_setting->editSetting('frete_correios', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');
						
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		/* Set breadcrumbs data to view */
		$data = array(); 

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/frete_correios', 'user_token=' . $this->session->data['user_token'], true)
		);

		/* Get data from settings otherwise get the defaults */
		foreach (self::DEFAULT_SHIPPING_SETTINGS as $key => $value) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else if($this->config->get($key) !== null) {
				$data[$key] = $this->config->get($key);
			} else {
				$data[$key] = $value;
			}
		}

		$data['action']['cancel'] = $this->url->link('marketplace/extension', 'user_token='.$this->session->data['user_token'].'&type=shipping');
		$data['action']['save'] = $this->url->link('extension/shipping/frete_correios', 'user_token=' . $this->session->data['user_token'], true);

		$data['error'] = $this->error;	
    
    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');
		
		$htmlOutput = $this->load->view('extension/shipping/frete_correios', $data);
		$this->response->setOutput($htmlOutput);
	}

	public function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/frete_correios')) {
			$this->error['permission'] = true;
			return false;
		}
		
		foreach (self::DEFAULT_SHIPPING_SETTINGS as $key => $value) {
			if (!utf8_strlen($this->request->post[$key])) {
				$this->error[$key] = true;
			}
		}
		
		return empty($this->error);
	}
	
	public function install() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('shipping_frete_correios', ['shipping_frete_correios_status' => 1]);
	}
	
	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('shipping_frete_correios');
	}
}