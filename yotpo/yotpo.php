<?php
if (!defined('_PS_VERSION_')){
	exit;
}

class Yotpo extends Module
{
	private $_html = '';
	private $_httpClient = NULL;
	private $_yotpo_module_path = '';
	
	private $_required_files = array('/httpClient.php', '/lib/oauth-php/library/OAuthStore.php', '/lib/oauth-php/library/OAuthRequester.php'); 
	
	public function __construct()
	{
		// version test
		$version_mask = explode('.', _PS_VERSION_, 3);
		$version_test = $version_mask[0] > 0 && $version_mask[1] > 4;

		$this->name = 'yotpo';
		$this->tab = $version_test ? 'advertising_marketing' : 'Reviews';
		$this->version = '1.0.9';
		if($version_test){
			$this->author = 'Yotpo';
		}
		$this->need_instance = 1;

		parent::__construct();
		 
		$this->displayName = $this->l('Add Reviews - Social reviews by Yotpo');
		$this->description = $this->l('The #1 reviews add-on for SMBs. Generate beautiful, trusted reviews for your shop.');
		$this->_yotpo_module_path = _PS_MODULE_DIR_ . $this->name;

		if(!Configuration::get('yotpo_app_key'))
		{
			$this->warning = $this->l('Set your api key in order to use this module correctly');	
		}
		Yotpo::defineBaseUrl();
	}

	public static function getBaseUrl()
	{
		$domain = (isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST']);
		$domain = 'http://'.$domain;
		return $domain;
	}

	public static function defineBaseUrl()
	{
		if(!defined('_PS_BASE_URL_'))
		{
			define('_PS_BASE_URL_', Yotpo::getBaseUrl());
		}
	}

	public function install()
	{
		if (!function_exists('curl_init'))
		{
			$this->setError($this->l('Yotpo needs the PHP Curl extension, please ask your hosting provider to enable it prior to install this module.'));
		}
		$version_mask = explode('.', _PS_VERSION_, 3);

		if($version_mask[0] == 0 || $version_mask[1] < 3)
		{
			$this->setError($this->l('Minimum version required for Yotpo module is Prestashop 1.3'));
		}

		foreach ($this->_required_files as $file)
		{
			if(!stream_resolve_include_path($this->_yotpo_module_path .$file))
			{
				$this->setError($this->l('Can\'t include file ' . $this->_yotpo_module_path .$file));
			}
		}


		if (
			(is_array($this->_errors) && count($this->_errors) > 0) OR
			parent::install() == false OR
			!$this->registerHook('productfooter') OR
			!$this->registerHook('postUpdateOrderStatus') OR
			!$this->registerHook('productTab') OR
			!$this->registerHook('productTabContent') OR
			!$this->registerHook('header')) 
		{
			return false;
		}
		// Set default language to english.
		Configuration::updateValue('yotpo_language', 'en', false);

		// Set default widget location to product page footer.
		Configuration::updateValue('yotpo_widget_location', 'footer', false);

		// Set default widget tab name.
		Configuration::updateValue('yotpo_widget_tab_name', 'Reviews', false);

		return true;
	}

	public function hookheader($params)
	{
		global $smarty;
		$smarty->assign('yotpoAppkey', Configuration::get('yotpo_app_key'));
		$smarty->assign('yotpoDomain', $this->getShopDomain());
		return "<script src ='http://www.yotpo.com/js/yQuery.js'></script>";
	}

	public function hookproductfooter($params)
	{
		$widgetLocation = Configuration::get('yotpo_widget_location');
		if($widgetLocation == 'footer' || $widgetLocation == 'other')
		{
			return $this->showWidget($params['product']);
		}
		return NULL;
	}

	public function hookpostUpdateOrderStatus($params)
	{
		$accepted_status = array(defined('PS_OS_WS_PAYMENT') ? (int)Configuration::get(PS_OS_WS_PAYMENT) : _PS_OS_WS_PAYMENT_,
								 defined('PS_OS_PAYMENT') ? (int)Configuration::get(PS_OS_PAYMENT) : _PS_OS_PAYMENT_,
								 defined('PS_OS_DELIVERED') ? (int)Configuration::get(PS_OS_DELIVERED) : _PS_OS_DELIVERED_,
								 defined('PS_OS_SHIPPING') ? (int)Configuration::get(PS_OS_SHIPPING) : _PS_OS_SHIPPING_);
		if(in_array($params['newOrderStatus']->id, $accepted_status))
		{
			$data = $this->prepareMapData($params);
			$app_key = Configuration::get('yotpo_app_key');
			$secret = Configuration::get('yotpo_oauth_token');
			$enable_feature = Configuration::get('yotpo_map_enabled');

			if(isset($app_key) && isset($secret) && $enable_feature == "1" && !is_null($data))
			{
				$this->httpClient()->makeMapRequest($data, $app_key, $secret);				
			}
		}
	}

	public function hookProductTab($params)
	{
		$product_id = $this->parseProductId();
		if($product_id != NULL && Configuration::get('yotpo_widget_location') == 'tab')
		{
			return "<li><a href='#idTab-yotpo'> ". Configuration::get('yotpo_widget_tab_name') ." </a></li>";
		}
		return NULL;
	}

	public function hookProductTabContent($params)
	{
		$product_id = $this->parseProductId();
		if($product_id != NULL && Configuration::get('yotpo_widget_location') == 'tab')
		{
			return "<div id='idTab-yotpo'>" . $this->showWidget(new Product((int)($product_id), false, Configuration::get('PS_LANG_DEFAULT'))) . "</div>";
		}
	}

	public function uninstall()
	{
		Configuration::deleteByName('yotpo_app_key');
		Configuration::deleteByName('yotpo_oauth_token');
		Configuration::deleteByName('yotpo_map_enabled');
		Configuration::deleteByName('yotpo_language');
		Configuration::deleteByName('yotpo_widget_location');
		Configuration::deleteByName('yotpo_widget_tab_name');
		return parent::uninstall();
	}

	// module configuration
	public function getContent()
	{
		if(Configuration::get('yotpo_map_enabled') == NULL)
		{
			Configuration::updateValue('yotpo_map_enabled', '1', false);
			echo '
        <script type="text/javascript">
        var prefix ="";
        if (typeof _gaq != "object") {
          window["_gaq"] = [];
          _gaq.push(["_setAccount", "UA-25706646-2"]);
          (function() {
            var ga = document.createElement("script");
            ga.type = "text/javascript";
            ga.async = true;
            ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js";
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(ga, s);
          })();
        } else {
          prefix = "t2.";
          _gaq.push([prefix + "_setAccount", "UA-25706646-2"]);
        }
        _gaq.push([prefix + "_trackEvent", "prestashop", "install"]);
        </script>';
		}

		if(isset($this->context) && isset($this->context->controller) && method_exists($this->context->controller, 'addCSS'))
		{
			$this->context->controller->addCSS($this->_path.'/css/form.css', 'all');		
		}
		else
		{
			echo '<link rel="stylesheet" type="text/css" href="../modules/yotpo/css/form.css" />';	
		}
		$this->processRegistrationForm();
		$this->processSettingsForm();
		$this->displayForm();
		return $this->_html;
	}

	private function getProductImageUrl($id_product)
	{
		$id_image = Product::getCover($id_product);
		// get Image by id
		if (sizeof($id_image) > 0) {
			$image = new Image($id_image['id_image']);
			// get image full URL

			return $image_url = method_exists($image, 'getExistingImgPath') ? _PS_BASE_URL_._THEME_PROD_DIR_.$image->getExistingImgPath().".jpg" : $this->getExistingImgPath($image);
		}
		return NULL;
	}

	private function getExistingImgPath($image)
	{
		if (!$image->id)
		{
			return NULL;	
		}
		if (file_exists(_PS_PROD_IMG_DIR_.$image->id_product.'-'.$image->id.'.jpg'))
		{
			return _PS_BASE_URL_._THEME_PROD_DIR_.$image->id_product.'-'.$image->id.'.'.'jpg';	
		}	
	}

	private function getProductLink($product_id)
	{
		if(isset($this->context) && isset($this->context->link) && method_exists($this->context->link, 'getProductLink'))
		{
			return $this->context->link->getProductLink($product_id);
		}
		global $link;
		if(isset($link) && method_exists($link, 'getProductLink'))
		{
			return $link->getProductLink($product_id);
		}
		else
		{
			$full_product = new Product($product_id, false);
			return $full_product->getLink();
		}
	}

	private function getDescritpion($product,$lang_id)
	{
		if(!empty($product['description_short']))
		{
			return strip_tags($product['description_short']);
		}
		$full_product = new Product($product['id_product'], false, $lang_id);
		return strip_tags($full_product->description);
	}

	private function setError($error){
		if(!$this->_errors){
			$this->_errors = array();
		}
		$this->_errors[] = $error;
	}

	private function httpClient()
	{
		if(is_null($this->_httpClient)){
			include_once($this->_yotpo_module_path . '/httpClient.php');
			$this->_httpClient = new YotpoHttpClient($this->name);
		}
		return $this->_httpClient;
	}

	private function parseProductId()
	{
		$product_id = (int)(Tools::getValue('id_product'));

		if(!empty($product_id))
		{
			return $product_id;
		}
		else
		{
			parse_str($_SERVER['QUERY_STRING'], $query);
			if(!empty($query['id_product']))
			{
				return $query['id_product'];
			}
		}
		return NULL;
	}

	private function showWidget($product)
	{
		global $smarty;
		$smarty->assign('yotpoProductId', $product->id);
		$smarty->assign('yotpoProductName', strip_tags($product->name));
		$smarty->assign('yotpoProductDescription', strip_tags($product->description));
		$smarty->assign('yotpoProductModel', $this->getProductModel($product));
		$smarty->assign('yotpoProductImageUrl', $this->getProductImageUrl($product->id));
		$smarty->assign('yotpoProductBreadCrumbs', $this->getBreadCrumbs($product));
		$smarty->assign('yotpoLanguage', Configuration::get('yotpo_language'));

		if(Configuration::get('yotpo_widget_location') != 'other')
		{
			return $this->display(__FILE__,'tpl/widgetDiv.tpl');
		}
		return NULL;
	}

	private function getShopDomain()
	{
		if(method_exists('Tools', 'getShopDomain')){
			return Tools::getShopDomain(false,false);
		}
		return str_replace('www.', '', $_SERVER['HTTP_HOST']);;
	}

	private function processRegistrationForm()
	{
		if (Tools::isSubmit('yotpo_register'))
		{
			$email = Tools::getValue('yotpo_user_email');
			$name = Tools::getValue('yotpo_user_name');
			$password = Tools::getValue('yotpo_user_password');
			$confirm = Tools::getValue('yotpo_user_confirm_password');
			if ($email === false || $email === '')
			{
				return $this->prepareError($this->l('Provide valid email address'));	
			}
			if(strlen($password) < 6 || strlen($password) > 128)
			{
				return $this->prepareError($this->l('Password must be at least 6 characters'));	
			}
			if ($password != $confirm)
			{
				return $this->prepareError($this->l('Passwords are not identical'));	
			}
			if ($name === false || $name === '')
			{
				return $this->prepareError($this->l('Name is missing'));	
			}
			
			$is_mail_valid = $this->httpClient()->checkeMailAvailability($email);

			if($is_mail_valid['status']['code'] == 200 && $is_mail_valid['response']['available'] == true)
			{
				$response = $this->httpClient()->register($email, $name, $password, _PS_BASE_URL_);
				if($response['status']['code'] == 200)
				{
					$accountPlatformResponse = $this->httpClient()->createAcountPlatform($response['response']['app_key'], $response['response']['secret'], _PS_BASE_URL_);
					if($accountPlatformResponse['status']['code'] == 200)
					{
						Configuration::updateValue('yotpo_app_key', $response['response']['app_key'], false);
						Configuration::updateValue('yotpo_oauth_token', $response['response']['secret'], false);
						return $this->prepareSuccess($this->l('Account successfully created'));
					}
					else
					{
						return $this->prepareError($response['status']['message']);	
					}
				}
				else
				{
					return $this->prepareError($response['status']['message']);
				}
			}
			else
			{
				if($is_mail_valid['status']['code'] == 200 )
				{
					return $this->prepareError('This mail is allready taken.');	
				}
				else
				{
					return $this->prepareError();	
				}
			}
		}
	}

	private function processSettingsForm()
	{
		if (Tools::isSubmit('yotpo_settings'))
		{

			$api_key = Tools::getValue('yotpo_app_key');
			$secret_token = Tools::getValue('yotpo_oauth_token');
			$map_enabled = Tools::getValue('yotpo_map_enabled');
			$language = Tools::getValue('yotpo_language');
			$location = Tools::getValue('yotpo_widget_location');
			$tabName = Tools::getValue('yotpo_widget_tab_name');
			if($api_key == '')
			{
				return $this->prepareError($this->l('Api key is missing'));	
			}
			if($map_enabled && $secret_token == '')
			{
				return $this->prepareError($this->l('Please fill out the secret token'));	
			}
			$yotpo_map_enabled = Tools::getValue('yotpo_map_enabled') == false ? "0" : "1";
			Configuration::updateValue('yotpo_map_enabled', $yotpo_map_enabled, false);
			Configuration::updateValue('yotpo_app_key', Tools::getValue('yotpo_app_key'), false);
			Configuration::updateValue('yotpo_oauth_token', Tools::getValue('yotpo_oauth_token'), false);
			Configuration::updateValue('yotpo_language', $language, false);
			Configuration::updateValue('yotpo_widget_location', $location, false);
			Configuration::updateValue('yotpo_widget_tab_name', $tabName, false);
			return $this->prepareSuccess();
		}
	}

	private function displayForm()
	{
		global $smarty;
		$smarty->assign('finishedRegistration', false);
		$smarty->assign('allreadyUsingYotpo', false);
		if (Tools::isSubmit('log_in_button'))
		{
			$smarty->assign('allreadyUsingYotpo', true);
			return $this->displaySettingsForm();
		}
		if (Tools::isSubmit('yotpo_register'))
		{
			global $smarty;
			$smarty->assign('finishedRegistration', true);
		}
		return Configuration::get('yotpo_app_key') == '' ? $this->displayRegistrationForm() : $this->displaySettingsForm();
	}

	private function displayRegistrationForm()
	{
		global $smarty;
		$smarty->assign(array(
        'action' => Tools::safeOutput($_SERVER['REQUEST_URI']),
        'email' => Tools::safeOutput(Tools::getValue('yotpo_user_email')),
        'userName' => Tools::safeOutput(Tools::getValue('yotpo_user_name'))));

		$this->_html .= $this->display(__FILE__, 'tpl/registrationForm.tpl');
		return $this->_html;
	}

	private function displaySettingsForm()
	{
		global $smarty;
		$smarty->assign(array(
        'action' => Tools::safeOutput($_SERVER['REQUEST_URI']),
        'appKey' => Tools::safeOutput(Tools::getValue('yotpo_app_key',Configuration::get('yotpo_app_key'))),
        'oauthToken' => Tools::safeOutput(Tools::getValue('yotpo_oauth_token',Configuration::get('yotpo_oauth_token'))),
        'mapEnabled' => Configuration::get('yotpo_map_enabled') == "0" ? false : true,
        'widgetLanguage' => Configuration::get('yotpo_language'),
        'widgetLocation' => Configuration::get('yotpo_widget_location'),
        'tabName' => Configuration::get('yotpo_widget_tab_name')));

		$this->_html .= $this->display(__FILE__, 'tpl/settingsForm.tpl');
	}

	private function getProductModel($product)
	{
		if(Validate::isEan13($product->ean13))
		{
			return $product->ean13;
		}
		else if(Validate::isUpc($product->upc))
		{
			return $product->upc;
		}
		return NULL;
	}

	private function getBreadCrumbs($product)
	{
		if (!method_exists('Product', 'getProductCategoriesFull'))
		{
			return '';	
		}
		$result = array();
		$all_product_subs = Product::getProductCategoriesFull($product->id, $this->context->language->id);
		if(isset($all_product_subs) && count($all_product_subs)>0)
		{
			foreach($all_product_subs as $subcat)
			{
				$sub_category = new Category($subcat['id_category'], $this->context->language->id);
				$sub_category_path = $sub_category->getParentsCategories();
				foreach ($sub_category_path as $key) {
					$result[] = $key['name'];
				}
			}
		}
		$result = implode(';', $result);
		return $result;
	}

	private function prepareError($message = '')
	{
		$this->_html .= sprintf('<div class="alert">%s</div>', $message == '' ? $this->l('Error occured') : $message);
	}

	private function prepareSuccess($message = '')
	{
		$this->_html .= sprintf('<div class="conf confirm">%s</div>', $message == '' ? $this->l('Settings updated') : $message);
	}

	private function prepareMapData($params)
	{
		$data = array();
	    $customer = NULL;

        $order = new Order((int)$params['id_order']);
        $customer = new Customer((int)$order->id_customer);
        $cart = Cart::getCartByOrderId($params['id_order']);
        if(Validate::isLoadedObject($order) && Validate::isLoadedObject($customer) && Validate::isLoadedObject($cart))
        {
        	$products = $cart->getProducts();
        	$currency = Currency::getCurrencyInstance($cart->id_currency);
        	if(!is_null($products) && is_array($products) && Validate::isLoadedObject($currency))
        	{
	    	    $data["order_date"] = $order->date_add;
			    $data["email"] = $customer->email;
			    $data["customer_name"] = $customer->firstname . ' ' . $customer->lastname;
			    $data["order_id"] = $params['id_order'];
			    $data['platform'] = 'prestashop';
			    $products_arr = array();
			    $data["currency_iso"] = $currency->iso_code;			    
			    foreach ($products as $product) 
			    {
					$product_data = array();    
					$product_data['url'] = $this->getProductLink($product['id_product']); 
					$product_data['name'] = $product['name'];
					$product_data['image'] = $this->getProductImageUrl($product['id_product']);
					$product_data['description'] = $this->getDescritpion($product, intval($params['cookie']->id_lang));
					$product_data['price'] = $product['price'];

					$products_arr[$product['id_product']] = $product_data;
			    }
			    $data['products'] = $products_arr;
			    return $data;
        	}
        }
	 	return NULL;
	}	
}
?>