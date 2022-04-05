<?php

class ModelExtensionShippingFreteCorreios extends Model {
	function getQuote($address) {
		$this->load->language('extension/shipping/frete_correios');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('shipping_frete_correios_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if (!$this->config->get('shipping_frete_correios_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$quote_data = array();

			$precoPrazoCorreios = self::getPrecoPrazoCorreios(
					'14781123',
					'96204040',
					'10',
					20,
					20,
					20,
					20
				);
	
			if(!is_object($precoPrazoCorreios) && is_array($precoPrazoCorreios['errors']) && count($precoPrazoCorreios['errors']) > 0){
				$method_data['error'] = join("<br>", $precoPrazoCorreios['errors']);
			} else{
				$cost = floatval($precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->Valor);
				$deadlineDays = intval($precoPrazoCorreios->CalcPrecoPrazoResult->Servicos->cServico->PrazoEntrega);
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
	
				$method_data = array(
					'code'       => 'frete_correios',
					'title'      => $this->language->get('text_title'),
					'quote'      => $quote_data,
					'sort_order' => $this->config->get('shipping_frete_correios_sort_order'),
					'error'      => false
				);
			}
		}

		return $method_data;
	}
	
	private function getPrecoPrazoCorreios(
		$cepOrigem = '00000000',
		$cepDestino = '00000000',
		$peso = '1', // Peso em KG
		$comprimento = 1, // Dimensão em cm
		$altura = 1, // Dimensão em cm
		$largura = 1, // Dimensão em cm
		$diametro = 1, // Dimensão em cm
		$cdServico = '04014', // SEDEX = 04014; PAC = 04510
		$cdFormato = 1, // 1 = Caixa, Pacote; 2 = Rolo, Prisma; 3 = Envelope
		$maoPropria = 'N',
		$valorDeclarado = 0,
		$avisoRecebimento = 'N'
	){
		$data = array();
		
		$cepOrigem = str_replace('-', '', $cepOrigem);
		$cepDestino = str_replace('-', '', $cepOrigem);
	
		if(strlen($cepOrigem) != 8){
			$data['errors'][] = 'CEP Origem informado incorretamente';
		}
		if(strlen($cepDestino) != 8){
			$data['errors'][] = 'CEP Destino informado incorretamente';
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
	
}