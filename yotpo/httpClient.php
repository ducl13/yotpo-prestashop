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
		return array('status_message' => 'Could not create account correctly, authorization failed','status_code' => '401');
	}

	public function makePastOrdersRequest($data, $app_key, $secret_token)
	{
		$token = $this->grantOauthAccess($app_key, $secret_token);
		if(isset($token))
		{
			$data['utoken'] = $token;
		    return $this->makePostRequest(self::YOTPO_API_URL . '/apps/' . $app_key . "/purchases/mass_create", $data);
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
		$ch = curl_init($url);
		list($is_json, $parsed_data) = YotpoHttpClient::jsonOrUrlEncode($data);    
		$content_type = $is_json ? 'application/json' : 'application/x-www-form-urlencoded';                                                                                                                         
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($ch, CURLOPT_POSTFIELDS, $parsed_data);                                                                  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,self::HTTP_REQUEST_TIMEOUT);                                                                
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: ' . $content_type,
			'Content-length: '.strlen($parsed_data)));                                                                                                                   
		$result = curl_exec($ch);
		curl_close ($ch);	
		return YotpoHttpClient::jsonDecode($result, true);
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
	      
	      $pregResult = preg_match("/access_token[\W]*[\"'](.*?)[\"']/",$response,$matches);
		  $token = $pregResult == 1 ? $matches[1] : '';
	     
	      if($token != '')
	      	return $token;
	      else
	      	return NULL;
		}
		catch(OAuthException2 $e)
	    {//Do nothing
	    	return NULL;
	    }
	}
	
	private static function jsonOrUrlEncode($data)
	{
		if(function_exists('json_encode'))
		{
			return array(true, json_encode($data));
		}
		elseif (method_exists('Tools', 'jsonEncode'))
		{
			return array(true, Tools::jsonEncode($data));
		}
		else 
		{
			return array(false,http_build_query($data));
		}
	}
	
	private static function jsonDecode($data, $assoc = false)
	{
		$result = false;
		if(function_exists('json_decode'))
		{
			$result = array(true,json_decode($data, $assoc));
		}
		elseif (method_exists('Tools', 'jsonEncode'))
		{
			$result = array(true,Tools::jsonDecode($data, $assoc));
		}
		else
		{
			$result = array(false);
		}
		if($result)
		{
			return array('json' => true, 'status_code' => $result[1]['status']['code'], 'status_message' => $result[1]['status']['message'], 'response' => $result[1]['response']);
		}
		else
		{
			$result = preg_match("/code[\W]*(\d*)/",$data,$matches);
			$status_code = $result == 1 ? $matches[1] : '';
			unset($matches,$result);
			$result = preg_match("/message[\W]*[\"'](.*?)[\"']/",$data,$matches);
			$status_message = $result == 1 ? $matches[1] : '';
			unset($matches,$result);
			$result = preg_match("/response[\W]*({)/",$data,$matches,PREG_OFFSET_CAPTURE);
			$response = '';
			if($result == 1 && isset($matches[1][1]))
			{
				$response = YotpoHttpClient::getStringBetweenBrackets(substr($data, $matches[1][1]));
			}
			return array('json' => false, 'status_code' => $status_code, 'status_message' => $status_message, 'response' => $response);
		}
	}	

	private static function getStringBetweenBrackets($data)
	{
		$count = 0;
		if($data[0] != '{')
		{
			return '';
		}
		for ($position = 0; $position < strlen($data); $position++) {
			switch ($data[$position])
			{
				case  '{' :
					$count++;
					break;
				case  '}' :
					$count--;
					break;
					
			}
			if($count == 0)
			{
				return substr($data,0,$position);
			}	
		}
		return '';
	}
}
?>