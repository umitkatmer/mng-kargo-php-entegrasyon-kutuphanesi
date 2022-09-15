<?php

trait MngTrait {

		
    public function mngAddCargo($order_id){
		
	
	// https://apizone.mngkargo.com.tr/ den geliştirici olarak aldığınız bilgileri girerek test edebilirsiniz
	// Bu sistemdeki metodlar için aşağıdaki abonikleri oluşturacağınız uygulamaya eklemeniz gerekiyor. 
	// identity (1.0.1) (default) | standard-command (1.0.0) (default) | standard-query (1.0.0) (default)
	
	$mngkargo_pays       = $this->config->get('mngkargo_pays'); 
	$mng_client_id       = $this->config->get('mngkargo_client_id'); 
	$mng_secret_id       = $this->config->get('mngkargo_secret_id'); 
	$mng_customerNumber  = $this->config->get('mngkargo_customer_number'); 
	$mng_password        = $this->config->get('mngkargo_password'); 
	$mng_identityType    = 1; 
	$mng_url             = $this->config->get('mngkargo_url'); 	


	$this->load->model('sale/order');
	
	$order_info      = $this->model_sale_order->getOrder($order_id);
	$cargoid         = 100000000 + $order_info['order_id'];
	$receiverAddress = $order_info['shipping_address_1'].' '.$order_info['shipping_address_2'];

	if(strlen($receiverAddress)<30){
		$receiverAddress = substr($receiverAddress.'..............................',0,30);
	}
	
	$address 	    = $receiverAddress;
	$mobilePhoneNumber  = str_replace(' ','',$order_info['telephone']);
	$districtName       = $order_info['shipping_city'];
	$cityName           = $order_info['shipping_zone'];
	$fullName           = html_entity_decode($order_info['shipping_firstname'].' '.$order_info['shipping_lastname'], ENT_COMPAT, "UTF-8");
	$email              = $order_info['email'];
	
	
	$orderPieceList = array();
	$products       = $this->model_sale_order->getOrderProducts($order_id);	
	$order_sort     = 0;
	
	//Eğer belirli bir değerde anlaşıldı ise burayı tek bir üründe sabitlemek gerekiyor ki öyle yaptım
	
	foreach ($products as $product) {
	
		if($order_sort < 1){
		
			$orderPieceList[] = array(
			
				'barcode' => ''.$cargoid.'', 
				'desi'    => 2,
				'kg'      => 2,
				'content' => ''.$product['model'].' '.$product['name'].'',
				
			);
			
		}
	
	$order_sort++;
	
	}
	


	$params  =  array (
	  'order' => 
	  array (
		'referenceId'          => ''.$cargoid.'', 
		'barcode'              => ''.$cargoid.'', 
		'billOfLandingId'      => ''.$cargoid.'', 
		'isCOD'                => 0, // Kapıda ödeme ise 1 olacak
		'codAmount'            => 0, // Kapıda ödeme aktif ise değer yazılacak 
		'shipmentServiceType'  => 1,
		'packagingType'        => 3,
		'content'              => '#'.$order_info['order_id'].' nolu sipariş',
		'smsPreference1'       => 1,
		'smsPreference2'       => 1,
		'smsPreference3'       => 1,
		'paymentType'          => 1, // 1:GONDERICI_ODER, 2:ALICI_ODER,3:PLATFORM_ODER
		'deliveryType'         => 1,
		'description'          => '#'.$order_info['order_id'].' nolu sipariş',
		'marketPlaceShortCode' => '',
		'marketPlaceSaleCode'  => '',
	  ),
	  'orderPieceList' => $orderPieceList,
	  'recipient' => 
	  array (
		'customerId'           => '',
		'refCustomerId'        => '',
		'cityCode'             => 0,
		'cityName'             => ''.$cityName.'',
		'districtCode'         => 0,
		'districtName'         => ''.$districtName.'',
		'address'              => ''.$address.'',
		'bussinessPhoneNumber' => '',
		'email'                => ''.$email.'',
		'taxOffice'            => '',
		'taxNumber'            => '11111111111', // Zorunlu dolu olacak
		'fullName'             => ''.$fullName.'',
		'homePhoneNumber'      => '',
		'mobilePhoneNumber'    => ''.$mobilePhoneNumber.'',
	  ),
	);
	
	 // kapıda ödeme de parametreler değişmektedir
	 if($order_info['payment_code']=='cod') {
		 
		 if (is_array($mngkargo_pays) && in_array($order_info['payment_code'], $mngkargo_pays)) { 
		 
			$params['order']['isCOD']           = 1;
			$params['recipient']['taxOffice']   = 'SAHIS';
			$params['order']['codAmount']       = $order_info['total'];
			//$params['order']['paymentType']     = 2; // mng kargo bu parametreyi bu şekilde etkisiz kılınmasını istedi
			
		}
	}
	
	
		$params      =  json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) ;
		
		
		$responsive = $this->mngCreateOrder ($params);
		
		
		$responsive_status = '';
		
		
		$result = array();
		
		if(isset($responsive['response']['error']) && $responsive['status'] ){
			
			$responsive_status .= 'Hata Kodu '.$responsive['response']['error']['Code'] ;
			$responsive_status .= ' Hata Açıklama '.$responsive['response']['error']['Description'] ;
			
			$log = new Log('mngkargo.log');
			$log->write('Hatalı Giden Parametreler '.$params.'' );
			$log->write('Gelen Değer Parametreler '.$responsive_status );
			
			$result['message']          = 'İşlem Yapılamadı';
			$result['status'] = 0;
		}
		
		
		if(isset($responsive['response'][0]) && $responsive['status'] ){
			
			$responsive_status .= 'Başarılı orderInvoiceId '.$responsive['response'][0]['orderInvoiceId'] ;
			$responsive_status .= 'orderInvoiceDetailId '.$responsive['response'][0]['orderInvoiceDetailId'] ;
			$responsive_status .= 'shipperBranchCode '.$responsive['response'][0]['shipperBranchCode'] ;
		
			$log = new Log('mngkargo.log');
			$log->write('Başarılı Giden Parametreler '.$params.' Gelen Değer Parametreler '.$responsive_status );
		
			$result['status'] = 1;
			$result['message'] = 'Kargo Kaydı Açıldı';
		}
		
		
	
        $result['order_id']         = $order_info['order_id'];
        $result['kargo_firma']      = 'MNG';
        $result['kargo_barcode']    = $cargoid;
        $result['kargo_talepno']    = $cargoid;;
		$result['order_status_id']  = $this->config->get('mngkargo_order_status_id');
		
		
		if($result['status'] == 1){

			$this->db->query("INSERT INTO ".DB_PREFIX."order_mngkargo SET kargo_tarih = CURRENT_TIMESTAMP, kargo_firma = '". $result['kargo_firma'] ."', kargo_barcode = '". $result['kargo_barcode'] ."', kargo_talepno ='". $result['kargo_talepno'] ."', kargo_paketadet = '', kargo_sonuc = '0', order_id = ". $order_info['order_id']);
			$this->db->query("UPDATE ".DB_PREFIX."order SET cargo_company = 'mng' WHERE order_id = '". $order_id ."' ");
		   
			$result["status"] = true;
			$result["text"]   = 'MNG Kargo Kaydı Açıldı.';

			//$this->mngAddHistory($order_info, $comment="Kargo İçin Hazırlandı", $notify = 0, $result['order_status_id']);	
	

		}else{
			$result["status"] = false; 
			$result["text"]   = ''.$result['message'].'';
		}


		return $result;

	}
	
	public function mngGenerateToken () { 
		

		$mng_client_id       = $this->config->get('mngkargo_client_id'); 
		$mng_secret_id       = $this->config->get('mngkargo_secret_id'); 
		$mng_customerNumber  = $this->config->get('mngkargo_customer_number'); 
		$mng_password        = $this->config->get('mngkargo_password'); 
		$mng_identityType    = 1; 
		$mng_url             =  $this->config->get('mngkargo_url'); 
					
		$params         = array ( 'customerNumber' => ''.$mng_customerNumber.'', 'password' => ''.$mng_password.'', 'identityType' => $mng_identityType );
		$params         = json_encode( $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) ;
		$token_array    = $this->mngGetToken($params);

	
		// geçerliliğini kontrol edip yeniden al
		$jwt = array();
		
		if( $token_array['status'] == 1 && isset($token_array['response']['jwt'])){
			
				$response                       = $token_array['response'];
				$jwt['jwt']                     = $response['jwt'];
				$jwt['refreshToken']            = $response['refreshToken'];
				$jwt['jwtExpireDate']           = $response['jwtExpireDate'];
				$jwt['refreshTokenExpireDate']  = $response['refreshTokenExpireDate'];
				$jwt['status']                  = true;
			
				//if( strtotime($jwt['jwtExpireDate']) > strtotime('now') ) {
				
					// eğer süre doldu ise jwtExpireDate göndereceksin eğer refreshTokenExpireDate dolmuşsa da yeni token ileteceksin
				
				//}	
			
				
			
		}else{
			
			$jwt['status']     = false;
			
		}
		
		return $jwt;
		
	}
	
	public function mngCreateOrder ($params) {
		
		
		$mng_client_id       = $this->config->get('mngkargo_client_id'); 
		$mng_secret_id       = $this->config->get('mngkargo_secret_id'); 
		$mng_customerNumber  = $this->config->get('mngkargo_customer_number'); 
		$mng_password        = $this->config->get('mngkargo_password'); 
		$mng_identityType    = 1; 
		$mng_url             =  $this->config->get('mngkargo_url'); 
		
		

		$curl  = curl_init();
		
		$token              = $this->mngGenerateToken ();
 
		if($token['status']){
		
		$token_status       = $token['status'];
		$token_jwt          = $token['jwt'];
		$token_refreshToken = $token['refreshToken'];
	
		  //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		  //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt_array($curl, array(
		  CURLOPT_URL => "".$mng_url."standardcmdapi/createOrder",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "".$params."",
		  CURLOPT_HTTPHEADER => array(
			"accept: application/json",
			"Authorization: Bearer ".$token_jwt."",
			"content-type: application/json",
			"x-api-version: REPLACE_THIS_VALUE",
			"x-ibm-client-id:".$mng_client_id."",
			"x-ibm-client-secret: ".$mng_secret_id.""
		  ),
		));

		$response = curl_exec($curl);
		$err      = curl_error($curl);

		curl_close($curl);

		$durum = array();

		if ($err) {
		
			$durum['status']   = false ;
			$durum['response'] = json_decode($err, true);
			
		} else {
			
			$durum['status'] = true ;	
			$durum['response'] = json_decode($response, true);
				
		}
		
		return $durum;
		
		}			   
	}
	
	public function mngTrackShipment($REFERENCEID){
		
		
		
		$mng_client_id       = $this->config->get('mngkargo_client_id'); 
		$mng_secret_id       = $this->config->get('mngkargo_secret_id'); 
		$mng_customerNumber  = $this->config->get('mngkargo_customer_number'); 
		$mng_password        = $this->config->get('mngkargo_password'); 
		$mng_identityType    = 1; 
		$mng_url             =  $this->config->get('mngkargo_url'); 
		
		
				
		$token              = $this->mngGenerateToken ();
		
		if($token['status']){
		
		$token_status       = $token['status'];
		$token_jwt          = $token['jwt'];
		$token_refreshToken = $token['refreshToken'];
	
		
		$curl = curl_init();
		  //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		  //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt_array($curl, array(
		  CURLOPT_URL => "".$mng_url."standardqueryapi/trackshipment/$REFERENCEID",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
			"accept: application/json",
			"Authorization: Bearer ".$token_jwt."",
			"content-type: application/json",
			"x-api-version: REPLACE_THIS_VALUE",
			"x-ibm-client-id:".$mng_client_id."",
			"x-ibm-client-secret: ".$mng_secret_id.""
		  ),
		));

		$response = curl_exec($curl);
		$err      = curl_error($curl);

	
		curl_close($curl);

		$durum = array();

		if ($err) {
		
			$durum['status']   = false ;
			$durum['response'] = json_decode($err, true);
			
		} else {
			
			$durum['status'] = true ;	
			$durum['response'] = json_decode($response, true);
				
		}
		
		return $durum;	 
		
		}
		 
		
		
	}
	
	
	public function mngGetOrder($REFERENCEID){
		
				
		$mng_client_id       = $this->config->get('mngkargo_client_id'); 
		$mng_secret_id       = $this->config->get('mngkargo_secret_id'); 
		$mng_customerNumber  = $this->config->get('mngkargo_customer_number'); 
		$mng_password        = $this->config->get('mngkargo_password'); 
		$mng_identityType    = 1; 
		$mng_url             =  $this->config->get('mngkargo_url'); 		
				
				
		$token              = $this->mngGenerateToken ();
		
		if($token['status']){
		
		$token_status       = $token['status'];
		$token_jwt          = $token['jwt'];
		$token_refreshToken = $token['refreshToken'];
	
		
		
		  $curl = curl_init();
		  //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		  //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		  curl_setopt_array($curl, array(
		  CURLOPT_URL => "".$mng_url."standardqueryapi/getorder/$REFERENCEID",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
			"accept: application/json",
			"Authorization: Bearer ".$token_jwt."",
			"content-type: application/json",
			"x-api-version: REPLACE_THIS_VALUE",
			"x-ibm-client-id:".$mng_client_id."",
			"x-ibm-client-secret: ".$mng_secret_id.""
		  ),
		));

		$response = curl_exec($curl);
		$err      = curl_error($curl);

	
		curl_close($curl);

		$durum = array();

		if ($err) {
		
			$durum['status']   = false ;
			$durum['response'] = json_decode($err, true);
			
		} else {
			
			$durum['status'] = true ;	
			$durum['response'] = json_decode($response, true);
				
		}
		
		return $durum;	 
		
		}
		
		// print_r($responsive);	

		//Kargo detay bilgisi ihtiyacımız olmayacak
		//$responsive = $this->getorder($REFERENCEID="");
		
		
	}
	
	public function mngGetToken($params){
		
		$mng_client_id       = $this->config->get('mngkargo_client_id'); 
		$mng_secret_id       = $this->config->get('mngkargo_secret_id'); 
		$mng_customerNumber  = $this->config->get('mngkargo_customer_number'); 
		$mng_password        = $this->config->get('mngkargo_password'); 
		$mng_identityType    = 1; 
		$mng_url             =  $this->config->get('mngkargo_url'); 

		$curl = curl_init();
		//curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt_array($curl, array(
		CURLOPT_URL => "".$mng_url."token",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => "".$params."",
		CURLOPT_HTTPHEADER => array(
		"accept: application/json",
		"content-type: application/json",
		"x-api-version: REPLACE_THIS_VALUE",
		"x-ibm-client-id:".$mng_client_id."",
		"x-ibm-client-secret: ".$mng_secret_id.""
		),
		));

		$response = curl_exec($curl);
		$err      = curl_error($curl);
		
		
 
		curl_close($curl);

		$durum = array();

		if ($err) {
		
		$durum['status']             = false ;
		$errors                      = json_decode($err, true);
		$durum['error_code']         = $errors['error']['Code'];
		$durum['error_code']         = $errors['error']['Code'];
		$durum['error_message']      = $errors['error']['Message'];
		$durum['error_description']  = $errors['error']['Description'];
	
		
		} else {
			
		$durum['status'] = true ;	
		$durum['response'] = json_decode($response, true);
			
		}
		
		return $durum;
		
	}
	
	
	    private function mngAddHistory($order_info, $comment, $notify = 0, $order_status_id = false)
    {
        if (!$order_status_id) {
            $order_status_id = $order_info['order_status_id'];
        }

        $sql = "UPDATE ".DB_PREFIX."order SET order_status_id = '$order_status_id' WHERE order_id = " . $order_info['order_id'];
        $this->db->query($sql);


        $sql = "INSERT INTO ".DB_PREFIX."order_history (order_id, order_status_id, notify, comment, date_added) VALUES ( " . $order_info['order_id'] . ", $order_status_id, 0, '$comment', CURRENT_TIMESTAMP)";
        $this->db->query($sql);
    }
	
}
