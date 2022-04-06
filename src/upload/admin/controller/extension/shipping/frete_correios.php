<?php

class ControllerExtensionShippingFreteCorreios extends Controller {
  const DEFAULT_SHIPPING_SETTINGS = [
		'shipping_frete_correios_days_to_prepare' => 3, /* Default days to prepare the product */
    'shipping_frete_correios_origin_cep' => '00000000', /* Deafault origin CEP */
    'shipping_frete_correios_sort_order' => 1, /* Default order */
		'shipping_frete_correios_status' => 1 /* Enabled by default */
	];
    
	private $error = array();
	
	public function index() {
		$this->load->model('setting/setting');

		/* Set page title */
		$this->load->language('extension/shipping/frete_correios');
		$this->document->setTitle($this->language->get('heading_title'));
		
		/* Edit request */
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_frete_correios', $this->request->post);
			
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

	/* Event function */
	public function getFormProduct(&$route, &$data) {
		$this->load->language('extension/shipping/frete_correios');

		$data['entry_days_to_prepare'] = $this->language->get('entry_days_to_prepare');
		$data['placeholder_days_to_prepare'] = $this->language->get('placeholder_days_to_prepare');

		if (isset($this->request->get['product_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$product_info = $this->model_catalog_product->getProduct($this->request->get['product_id']);
		}

		if (isset($this->request->post['frete_correios_days_to_prepare'])) {
			$data['frete_correios_days_to_prepare'] = $this->request->post['frete_correios_days_to_prepare'];
		} elseif (!empty($product_info) && isset($product_info['frete_correios_days_to_prepare'])) {
			$data['frete_correios_days_to_prepare'] = $product_info['frete_correios_days_to_prepare'];
		} else {
			$data['frete_correios_days_to_prepare'] = '';
		}

		$route = str_replace('catalog/product_form', 'extension/shipping/product_form', $route);
	}

  /* Event Function */
  public function editProductModel(&$route, &$data){
		$this->load->model('extension/dashboard/products');
    $route = str_replace('catalog/product/editProduct', 'extension/dashboard/products/editProduct', $route);
  }

  /* Event Function */
  public function addProductModel(&$route, &$data){
		$this->load->model('extension/dashboard/products');
    $route = str_replace('catalog/product/addProduct', 'extension/dashboard/products/addProduct', $route);
  }
	
	public function install() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('shipping_frete_correios', ['shipping_frete_correios_status' => 1]);

		/* Models */
		$this->load->model('extension/dashboard/products');
		$this->model_extension_dashboard_products->alterProductsTable();

		/* Events */
		$this->load->model('setting/event');
		$this->model_setting_event->addEvent('change_product_form', 'admin/view/catalog/product_form/before', 'extension/shipping/frete_correios/getFormProduct');
		$this->model_setting_event->addEvent('change_edit_product_model', 'admin/model/catalog/product/editProduct/before', 'extension/shipping/frete_correios/editProductModel');
		$this->model_setting_event->addEvent('change_add_product_model', 'admin/model/catalog/product/addProduct/before', 'extension/shipping/frete_correios/addProductModel');
	}
	
	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('shipping_frete_correios');

		/* Events */
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('change_product_form');
		$this->model_setting_event->deleteEventByCode('change_product_model');
	}
}