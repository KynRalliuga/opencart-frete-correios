<?php

class ModelExtensionShippingFreteCorreios extends Model {
	function getQuote($address) {
		$this->load->language('extension/shipping/frete_correios');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('frete_correios_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if (!$this->config->get('frete_correios_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$quote_data = array();

			$weight = 0;
			$length = 0;
			$width  = 0;
			$height = 0;
			$daysToPrepare = 0;

			foreach ($this->getProducts() as $product) {
				if ($product['shipping']) {
					$weight += $product['weight'];

					if($length < floatval($product['length'])){
						$length = floatval($product['length']);
					}
					if($width < floatval($product['width'])){
						$width = floatval($product['width']);
					}
					if($height < floatval($product['height'])){
						$height = floatval($product['height']);
					}

					if(!empty($product['frete_correios_days_to_prepare']) && $daysToPrepare < $product['frete_correios_days_to_prepare']){
						$daysToPrepare = $product['frete_correios_days_to_prepare'];
					}
				}
			}

			$weight = $weight >= 0.3 ? $weight : 0.3;
			$length = $length >= 15 ? $length : 15;
			$width = $width >= 10 ? $width : 10;
			$height = $height >= 1 ? $height : 1;
			$daysToPrepare = $daysToPrepare == 0 ? intval($this->config->get('frete_correios_days_to_prepare')) : $daysToPrepare;

			$precoPrazoCorreios = self::getPrecoPrazoCorreios(
					$this->config->get('frete_correios_origin_cep'),
					$address['postcode'],
					$weight,
					$length,
					$height,
					$width,
					$width
				);

			$method_data = array(
				'code'       => 'frete_correios',
				'title'      => $this->language->get('text_title'),
				'quote'      => array(),
				'sort_order' => $this->config->get('frete_correios_sort_order'),
				'error'      => false
			);
	
			if(!is_object($precoPrazoCorreios) && is_array($precoPrazoCorreios['errors']) && count($precoPrazoCorreios['errors']) > 0){
				$method_data['error'] = join("<br>", $precoPrazoCorreios['errors']);
			} else if (is_object($precoPrazoCorreios) && $precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->PrazoEntrega == 0) {
				$method_data['error'] = $precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->MsgErro;
			} else if(is_object($precoPrazoCorreios) && $precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->PrazoEntrega > 0) {
				$cost = floatval($precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->Valor);
				$deadlineDays = intval($precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->PrazoEntrega) + $daysToPrepare;
				$textDealineDays = $deadlineDays > 1 ? $this->language->get('text_deadline_days') : $this->language->get('text_deadline_day');
				$title = "{$this->language->get('text_description')} - {$textDealineDays}";
				$title = str_replace('%s', $deadlineDays, $title);

				$quote_data['frete_correios'] = array(
					'code'         => 'frete_correios.frete_correios',
					'title'        => $title,
					'cost'         => $cost,
					'tax_class_id' => 0,
					'text'         => $this->currency->format($cost, $this->session->data['currency'])
				);
	
				$method_data['quote'] = $quote_data;
			} else {
				$method_data['error'] = $this->language->get('error_server_indisponivel');
			}
		}

		return $method_data;
	}
	
	private function getPrecoPrazoCorreios(
		$cepOrigem = '00000000',
		$cepDestino = '00000000',
		$peso = '0,3', // Peso em KG
		$comprimento = 15, // Dimensão em cm
		$altura = 1, // Dimensão em cm
		$largura = 10, // Dimensão em cm
		$diametro = 5, // Dimensão em cm
		$cdServico = '04014', // SEDEX = 04014; PAC = 04510
		$cdFormato = 1, // 1 = Caixa, Pacote; 2 = Rolo, Prisma; 3 = Envelope
		$maoPropria = 'N',
		$valorDeclarado = 0,
		$avisoRecebimento = 'N'
	){
		$this->load->language('extension/shipping/frete_correios');

		$data = array();
		
		$cepOrigem = str_replace('-', '', $cepOrigem);
		$cepDestino = str_replace('-', '', $cepDestino);
	
		if(strlen($cepOrigem) != 8){
			$data['errors'][] = $this->language->get('error_cep_origem');
		}
		if(strlen($cepDestino) != 8){
			$data['errors'][] = $this->language->get('error_cep_destino');
		}
		if($peso > 30){
			$data['errors'][] = $this->language->get('error_peso_max');
		}
		if($altura > 100){
			$data['errors'][] = $this->language->get('error_altura_max');
		}
		if($largura > 100){
			$data['errors'][] = $this->language->get('error_largura_max');
		}
		if($comprimento > 100){
			$data['errors'][] = $this->language->get('error_comprimento_max');
		}
		if($comprimento + $largura + $altura > 200){
			$data['errors'][] = $this->language->get('error_soma_dimensoes');
		}
	
		if(isset($data['errors']) && count($data['errors']) > 0){
			return $data;
		}
	
		$urlApiCorreios = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx?wsdl';
		$methodApiCorreios = 'CalcPrecoPrazo';
	
		$clientApiCorreios = new SoapClient($urlApiCorreios);
		
		$argumentsApiCorreios = array( 
			$methodApiCorreios => array(
				'nCdEmpresa' => '',
				'sDsSenha' => '',
				'nCdServico' => $cdServico,
				'sCepOrigem' => $cepOrigem,
				'sCepDestino' => $cepDestino,
				'nVlPeso' => $peso,
				'nCdFormato' => $cdFormato,
				'nVlComprimento' => $comprimento,
				'nVlAltura' => $altura,
				'nVlLargura' => $largura,
				'nVlDiametro' => $diametro,
				'sCdMaoPropria' => $maoPropria,
				'nVlValorDeclarado' => $valorDeclarado,
				'sCdAvisoRecebimento' => $avisoRecebimento,
			)
		);

		$optionsApiCorreios = array('location' => substr($urlApiCorreios, 0, -5));
		
		$resultApiCorreios = $clientApiCorreios->__soapCall($methodApiCorreios, $argumentsApiCorreios, $optionsApiCorreios);
		
		$data = $resultApiCorreios;
		
		return $data;
	}
	
	public function getProducts() {
		$product_data = array();

		$cart_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "cart WHERE api_id = '" . (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND session_id = '" . $this->db->escape($this->session->getId()) . "'");

		foreach ($cart_query->rows as $cart) {
			$stock = true;

			$product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store p2s LEFT JOIN " . DB_PREFIX . "product p ON (p2s.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND p2s.product_id = '" . (int)$cart['product_id'] . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() AND p.status = '1'");

			if ($product_query->num_rows && ($cart['quantity'] > 0)) {
				$option_price = 0;
				$option_points = 0;
				$option_weight = 0;

				$option_data = array();

				foreach (json_decode($cart['option']) as $product_option_id => $value) {
					$option_query = $this->db->query("SELECT po.product_option_id, po.option_id, od.name, o.type FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_option_id = '" . (int)$product_option_id . "' AND po.product_id = '" . (int)$cart['product_id'] . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($option_query->num_rows) {
						if ($option_query->row['type'] == 'select' || $option_query->row['type'] == 'radio') {
							$option_value_query = $this->db->query("SELECT pov.option_value_id, ovd.name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$value . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

							if ($option_value_query->num_rows) {
								if ($option_value_query->row['price_prefix'] == '+') {
									$option_price += $option_value_query->row['price'];
								} elseif ($option_value_query->row['price_prefix'] == '-') {
									$option_price -= $option_value_query->row['price'];
								}

								if ($option_value_query->row['points_prefix'] == '+') {
									$option_points += $option_value_query->row['points'];
								} elseif ($option_value_query->row['points_prefix'] == '-') {
									$option_points -= $option_value_query->row['points'];
								}

								if ($option_value_query->row['weight_prefix'] == '+') {
									$option_weight += $option_value_query->row['weight'];
								} elseif ($option_value_query->row['weight_prefix'] == '-') {
									$option_weight -= $option_value_query->row['weight'];
								}

								if ($option_value_query->row['subtract'] && (!$option_value_query->row['quantity'] || ($option_value_query->row['quantity'] < $cart['quantity']))) {
									$stock = false;
								}

								$option_data[] = array(
									'product_option_id'       => $product_option_id,
									'product_option_value_id' => $value,
									'option_id'               => $option_query->row['option_id'],
									'option_value_id'         => $option_value_query->row['option_value_id'],
									'name'                    => $option_query->row['name'],
									'value'                   => $option_value_query->row['name'],
									'type'                    => $option_query->row['type'],
									'quantity'                => $option_value_query->row['quantity'],
									'subtract'                => $option_value_query->row['subtract'],
									'price'                   => $option_value_query->row['price'],
									'price_prefix'            => $option_value_query->row['price_prefix'],
									'points'                  => $option_value_query->row['points'],
									'points_prefix'           => $option_value_query->row['points_prefix'],
									'weight'                  => $option_value_query->row['weight'],
									'weight_prefix'           => $option_value_query->row['weight_prefix']
								);
							}
						} elseif ($option_query->row['type'] == 'checkbox' && is_array($value)) {
							foreach ($value as $product_option_value_id) {
								$option_value_query = $this->db->query("SELECT pov.option_value_id, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix, ovd.name FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

								if ($option_value_query->num_rows) {
									if ($option_value_query->row['price_prefix'] == '+') {
										$option_price += $option_value_query->row['price'];
									} elseif ($option_value_query->row['price_prefix'] == '-') {
										$option_price -= $option_value_query->row['price'];
									}

									if ($option_value_query->row['points_prefix'] == '+') {
										$option_points += $option_value_query->row['points'];
									} elseif ($option_value_query->row['points_prefix'] == '-') {
										$option_points -= $option_value_query->row['points'];
									}

									if ($option_value_query->row['weight_prefix'] == '+') {
										$option_weight += $option_value_query->row['weight'];
									} elseif ($option_value_query->row['weight_prefix'] == '-') {
										$option_weight -= $option_value_query->row['weight'];
									}

									if ($option_value_query->row['subtract'] && (!$option_value_query->row['quantity'] || ($option_value_query->row['quantity'] < $cart['quantity']))) {
										$stock = false;
									}

									$option_data[] = array(
										'product_option_id'       => $product_option_id,
										'product_option_value_id' => $product_option_value_id,
										'option_id'               => $option_query->row['option_id'],
										'option_value_id'         => $option_value_query->row['option_value_id'],
										'name'                    => $option_query->row['name'],
										'value'                   => $option_value_query->row['name'],
										'type'                    => $option_query->row['type'],
										'quantity'                => $option_value_query->row['quantity'],
										'subtract'                => $option_value_query->row['subtract'],
										'price'                   => $option_value_query->row['price'],
										'price_prefix'            => $option_value_query->row['price_prefix'],
										'points'                  => $option_value_query->row['points'],
										'points_prefix'           => $option_value_query->row['points_prefix'],
										'weight'                  => $option_value_query->row['weight'],
										'weight_prefix'           => $option_value_query->row['weight_prefix']
									);
								}
							}
						} elseif ($option_query->row['type'] == 'text' || $option_query->row['type'] == 'textarea' || $option_query->row['type'] == 'file' || $option_query->row['type'] == 'date' || $option_query->row['type'] == 'datetime' || $option_query->row['type'] == 'time') {
							$option_data[] = array(
								'product_option_id'       => $product_option_id,
								'product_option_value_id' => '',
								'option_id'               => $option_query->row['option_id'],
								'option_value_id'         => '',
								'name'                    => $option_query->row['name'],
								'value'                   => $value,
								'type'                    => $option_query->row['type'],
								'quantity'                => '',
								'subtract'                => '',
								'price'                   => '',
								'price_prefix'            => '',
								'points'                  => '',
								'points_prefix'           => '',
								'weight'                  => '',
								'weight_prefix'           => ''
							);
						}
					}
				}

				$price = $product_query->row['price'];

				// Product Discounts
				$discount_quantity = 0;

				foreach ($cart_query->rows as $cart_2) {
					if ($cart_2['product_id'] == $cart['product_id']) {
						$discount_quantity += $cart_2['quantity'];
					}
				}

				$product_discount_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$cart['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity <= '" . (int)$discount_quantity . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC LIMIT 1");

				if ($product_discount_query->num_rows) {
					$price = $product_discount_query->row['price'];
				}

				// Product Specials
				$product_special_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$cart['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

				if ($product_special_query->num_rows) {
					$price = $product_special_query->row['price'];
				}

				// Reward Points
				$product_reward_query = $this->db->query("SELECT points FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$cart['product_id'] . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

				if ($product_reward_query->num_rows) {
					$reward = $product_reward_query->row['points'];
				} else {
					$reward = 0;
				}

				// Downloads
				$download_data = array();

				$download_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_download p2d LEFT JOIN " . DB_PREFIX . "download d ON (p2d.download_id = d.download_id) LEFT JOIN " . DB_PREFIX . "download_description dd ON (d.download_id = dd.download_id) WHERE p2d.product_id = '" . (int)$cart['product_id'] . "' AND dd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

				foreach ($download_query->rows as $download) {
					$download_data[] = array(
						'download_id' => $download['download_id'],
						'name'        => $download['name'],
						'filename'    => $download['filename'],
						'mask'        => $download['mask']
					);
				}

				// Stock
				if (!$product_query->row['quantity'] || ($product_query->row['quantity'] < $cart['quantity'])) {
					$stock = false;
				}

				$recurring_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r LEFT JOIN " . DB_PREFIX . "product_recurring pr ON (r.recurring_id = pr.recurring_id) LEFT JOIN " . DB_PREFIX . "recurring_description rd ON (r.recurring_id = rd.recurring_id) WHERE r.recurring_id = '" . (int)$cart['recurring_id'] . "' AND pr.product_id = '" . (int)$cart['product_id'] . "' AND rd.language_id = " . (int)$this->config->get('config_language_id') . " AND r.status = 1 AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

				if ($recurring_query->num_rows) {
					$recurring = array(
						'recurring_id'    => $cart['recurring_id'],
						'name'            => $recurring_query->row['name'],
						'frequency'       => $recurring_query->row['frequency'],
						'price'           => $recurring_query->row['price'],
						'cycle'           => $recurring_query->row['cycle'],
						'duration'        => $recurring_query->row['duration'],
						'trial'           => $recurring_query->row['trial_status'],
						'trial_frequency' => $recurring_query->row['trial_frequency'],
						'trial_price'     => $recurring_query->row['trial_price'],
						'trial_cycle'     => $recurring_query->row['trial_cycle'],
						'trial_duration'  => $recurring_query->row['trial_duration']
					);
				} else {
					$recurring = false;
				}

				$product_data[] = array(
					'cart_id'                        => $cart['cart_id'],
					'product_id'                     => $product_query->row['product_id'],
					'frete_correios_days_to_prepare' => $product_query->row['frete_correios_days_to_prepare'],
					'name'                           => $product_query->row['name'],
					'model'                          => $product_query->row['model'],
					'shipping'                       => $product_query->row['shipping'],
					'image'                          => $product_query->row['image'],
					'option'                         => $option_data,
					'download'                       => $download_data,
					'quantity'                       => $cart['quantity'],
					'minimum'                        => $product_query->row['minimum'],
					'subtract'                       => $product_query->row['subtract'],
					'stock'                          => $stock,
					'price'                          => ($price + $option_price),
					'total'                          => ($price + $option_price) * $cart['quantity'],
					'reward'                         => $reward * $cart['quantity'],
					'points'                         => ($product_query->row['points'] ? ($product_query->row['points'] + $option_points) * $cart['quantity'] : 0),
					'tax_class_id'                   => $product_query->row['tax_class_id'],
					'weight'                         => ($product_query->row['weight'] + $option_weight) * $cart['quantity'],
					'weight_class_id'                => $product_query->row['weight_class_id'],
					'length'                         => $product_query->row['length'],
					'width'                          => $product_query->row['width'],
					'height'                         => $product_query->row['height'],
					'length_class_id'                => $product_query->row['length_class_id'],
					'recurring'                      => $recurring
				);
			} else {
				$this->cart->remove($cart['cart_id']);
			}
		}

		return $product_data;
	}
}