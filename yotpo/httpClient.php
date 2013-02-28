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

	public function makePastOrdersRequest($data, $app_key, $secret_token)
	{
		$token = $this->grantOauthAccess($app_key, $secret_token);
		if(isset($token))
		{
			$data['utoken'] = $token;
		    $this->makePostRequest(self::YOTPO_API_URL . '/apps/' . $app_key . "/purchases/mass_create", $data);
		}	
	}

	public function makeMapRequest($data, $app_key, $secret_token)
	{
		$token = $this->grantOauthAccess($app_key, $secret_token);
		if(isset($token))
		{
			$data['utoken'] = $token;
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
    	$OAuthStorePath = _PS_MODULE_DIR_ . 'yotpo/lib/oauth-php/library/OAuthStore.php';
    	$OAuthRequesterPath = _PS_MODULE_DIR_ . 'yotpo/lib/oauth-php/library/OAuthRequester.php';
		include_once ($OAuthStorePath);
		include_once ($OAuthRequesterPath);	    		   	
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