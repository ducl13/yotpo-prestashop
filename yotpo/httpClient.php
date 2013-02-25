<?php
class YotpoHttpClient 
{
	const YOTPO_API_URL = "https://api.yotpo.com";
	const HTTP_REQUEST_TIMEOUT = 30;
  	const YOTPO_OAUTH_TOKEN_URL = "https://api.yotpo.com/oauth/token";


	public function __construct($name = NULL)
  	{
    	$this->name = $name;
  	}

  	public function checkeMailAvailability($email)
  	{
  		$data = array();
  		$data['model'] = 'user';
  		$data['field'] = 'email';
  		$data['value'] = $email;

  		return $this->makePostRequest(self::YOTPO_API_URL . '/apps/check_availability', $data);
  	}

	public function register($email, $name, $password, $url)
	{

		$data = array();
		$user = array();
		$user["email"] = $email;
		$user["display_name"] = $name;
		$user["password"] = $password;
		$user['url'] = $url;
		$data['user'] = $user;
		$data['install_step'] = 'done';

		return $this->makePostRequest(self::YOTPO_API_URL . '/users.json', $data);
	}

	public function createAcountPlatform($app_key, $secret_token, $shop_url)
	{
		$token = $this->grantOauthAccess($app_key, $secret_token);
		if(isset($token))
		{
			$data = array();
			$data['utoken'] = $token;
			$platform_type = array();
			$platform_type['platform_type_id'] = 8;
			$platform_type['shop_domain'] = $shop_url;
			$data['account_platform'] = $platform_type;
			return $this->makePostRequest(self::YOTPO_API_URL . '/apps/' . $app_key .'/account_platform', $data);
		}
		return $token;
	}

	public function makeMapRequest($params, $app_key, $secret_token, $context)
	{

		$token = $this->grantOauthAccess($app_key, $secret_token);
		
		if(isset($token))
		{
			$data = array();
			$data['utoken'] = $token;
		    $customer = NULL;

	        $order = new Order((int)$params['id_order']);
	        $customer = new Customer((int)$order->id_customer);
		    $data["order_date"] = $order->date_add;
		    $data["email"] = $customer->email;
		    $data["customer_name"] = $customer->firstname . ' ' . $customer->lastname;
		    $data["order_id"] = $params['id_order'];
		    $data['platform'] = 'prestashop';

		    $products_arr = array();
		    $currency = Currency::getCurrencyInstance($params['cart']->id_currency);
		    $data["currency_iso"] = $currency->iso_code;
		    $products = $params['cart']->getProducts();
		    foreach ($products as $product) {

		      $product_data = array();    
		      $product_data['url'] = $context->getProductLink($product['id_product']); 
		      $product_data['name'] = $product['name'];
		      $product_data['image'] = $context->getProductImageUrl($product['id_product']);
		      $product_data['description'] = $context->getDescritpion($product, intval($params['cookie']->id_lang));
			  $product_data['price'] = $product['price'];

		      $products_arr[$product['id_product']] = $product_data;
		    }

		    $data['products'] = $products_arr;
		    $this->makePostRequest(self::YOTPO_API_URL . '/apps/' . $app_key . "/purchases/", $data);
		}
	}

	public function makePostRequest($url, $data)
	{
		if (!function_exists('curl_init'))
			return NULL;	

		$data_string = json_encode($data); 
		$ch = curl_init($url);                                                                      
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,self::HTTP_REQUEST_TIMEOUT);                                                                
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                                
		    'Content-Length: ' . strlen($data_string))                                                                       
		);                                                                                                                   
 
		$result = curl_exec($ch);
		curl_close ($ch);	
		return json_decode($result, true);
	}

	private function grantOauthAccess($app_key, $secret_token)
	{
	    if(!class_exists('OAuthStore', false))
	    {	
	    	$_ds = defined('DS') ? DS : '/';
	    	$OAuthStorePath =  dirname(__FILE__) . $_ds . 'lib'. $_ds .'oauth-php' . $_ds . 'library' . $_ds . 'OAuthStore.php';
	    	$OAuthRequesterPath =  dirname(__FILE__) . $_ds . 'lib'. $_ds .'oauth-php' . $_ds . 'library' . $_ds . 'OAuthRequester.php';
	    	if(stream_resolve_include_path($OAuthStorePath) AND stream_resolve_include_path($OAuthRequesterPath))
	    	{
	    		include_once ($OAuthStorePath);
	 	   		include_once ($OAuthRequesterPath);		
	    	}
	    	else 
	    	{
		    	$OAuthStorePath = _PS_MODULE_DIR_. $this->name . $_ds . 'lib'. $_ds .'oauth-php' . $_ds . 'library' . $_ds . 'OAuthStore.php';
		    	$OAuthRequesterPath = _PS_MODULE_DIR_ . $this->name . $_ds . 'lib'. $_ds .'oauth-php' . $_ds . 'library' . $_ds . 'OAuthRequester.php';
	    		if (stream_resolve_include_path($OAuthStorePath) AND stream_resolve_include_path($OAuthRequesterPath))
	    		{
					include_once ($OAuthStorePath);
	 	    		include_once ($OAuthRequesterPath);	
	    		}
	    	}
		}
	   
	    $yotpo_options = array( 'consumer_key' => $app_key, 'consumer_secret' => $secret_token, 'client_id' => $app_key, 'client_secret' => $secret_token, 'grant_type' => 'client_credentials' );
    
      	OAuthStore::instance("2Leg", $yotpo_options);
	    try
	    {

	      $request = new OAuthRequester(self::YOTPO_OAUTH_TOKEN_URL, "POST", $yotpo_options);         
	      $result = $request->doRequest(0);

	      $response = $result['body'];
	      
	      $tokenParams = json_decode($result['body'], true);
	     
	      if(isset($tokenParams['access_token']))
	      	return $tokenParams['access_token'];
	      else
	      	return NULL;
		}
		catch(OAuthException2 $e)
	    {//Do nothing
	    	return NULL;
	    }
	}
}
?>