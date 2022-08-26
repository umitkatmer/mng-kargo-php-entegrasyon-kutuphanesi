<?php


class ControllerToolMngkargo extends Controller{


	public $client_id; 
	public $secret_id; 
	public $customerNumber; 
	public $password ;
	public $identityType; 

	public function __construct() {
		
		$this->client_id       = ''; 
		$this->secret_id       = ''; 
		$this->customerNumber  = ''; 
		$this->password        = ''; 
		$this->identityType    = 1; 
  
	}  

    public function index(){

	$params =  array (
	  'order' => 
	  array (
		'referenceId'          => 'SIPARISNUMARANIZ500',
		'barcode'              => 'SIPARISNUMARANIZ500',
		'billOfLandingId'      => 'İrsaliye 1',
		'isCOD'                => 0,
		'codAmount'            => 0,
		'shipmentServiceType'  => 1,
		'packagingType'        => 3,
		'content'              => 'İçerik 1',
		'smsPreference1'       => 0,
		'smsPreference2'       => 0,
		'smsPreference3'       => 0,
		'paymentType'          => 1,
		'deliveryType'         => 1,
		'description'          => 'Açıklama 1',
		'marketPlaceShortCode' => '',
		'marketPlaceSaleCode'  => '',
	  ),
	  'orderPieceList' => 
	  array (
		0 => 
		array (
		  'barcode' => 'SIPARISNUMARANIZ500',
		  'desi'    => 2,
		  'kg'      => 2,
		  'content' => 'Parça açıklama 1',
		),
	  ),
	  'recipient' => 
	  array (
		'customerId'           => '',
		'refCustomerId'        => '',
		'cityCode'             => 0,
		'cityName'             => 'İstanbul',
		'districtCode'         => 0,
		'districtName'         => 'Bahçelievler',
		'address'              => 'ALICI TEXT ADRESİ',
		'bussinessPhoneNumber' => '',
		'email'                => 'A@A.COM.TR',
		'taxOffice'            => '',
		'taxNumber'            => '',
		'fullName'             => 'ALICI AD SOYAD',
		'homePhoneNumber'      => '',
		'mobilePhoneNumber'    => '5551231212',
	  ),
	);
		$params      =  json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) ;
		
		$responsive = $this->createOrder ($params);
		
		print_r($responsive);


	}
	
	public function generateToken () { 
		

		$customerNumber = $this->customerNumber;
		$password       = $this->password;
		$identityType   = $this->identityType;
					
		$params      = array ( 'customerNumber' => ''.$customerNumber.'', 'password' => ''.$password.'', 'identityType' => $identityType );
		$params      = json_encode( $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) ;
		$token_array = $this->getToken($params);
		
	
		// geçerliliğini kontrol edip yeniden al
		$jwt = '';
		
		if( $token_array['status'] == 1){
	
			$response          = $token_array['response'];
			$jwt               = $response['jwt'];
		  
		}
		
		return $jwt;
		
	}
	
	public function createOrder ($params) {

		$curl  = curl_init();
		
		$token = $this->generateToken ();
	
		if($token){

		 curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://testapi.mngkargo.com.tr/mngapi/api/standardcmdapi/createOrder",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "".$params."",
		  CURLOPT_HTTPHEADER => array(
			"accept: application/json",
			"Authorization: Bearer ".$token."",
			"content-type: application/json",
			"x-api-version: REPLACE_THIS_VALUE",
			"x-ibm-client-id:".$this->client_id."",
			"x-ibm-client-secret: ".$this->secret_id.""
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
	
	public function getToken($params){
		

		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => "https://testapi.mngkargo.com.tr/mngapi/api/token",
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
		"x-ibm-client-id:".$this->client_id."",
		"x-ibm-client-secret: ".$this->secret_id.""
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
		$durum['error_message']      = $errors['error']['Message'];
		$durum['error_description']  = $errors['error']['Description'];
	
		
		} else {
			
		$durum['status'] = true ;	
		$response        = json_decode($response, true);
		$response        = $response[0];
		$durum['orderInvoiceId']         = $response['orderInvoiceId'];
		$durum['orderInvoiceDetailId']   = $response['orderInvoiceDetailId'];
		$durum['shipperBranchCode']      = $response['shipperBranchCode'];
		
		}
		
		return $durum;
		
	}
	
}
