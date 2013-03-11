<?php
if (!defined('_PS_VERSION_')){
	exit;
}

class Yotpo extends Module
{
	const PAST_ORDERS_DAYS_BACK = 90;
	const PAST_ORDERS_LIMIT = 10000;
	const BULK_SIZE = 1000;	
	private $_html = '';
	private $_httpClient = NULL;
	private $_yotpo_module_path = '';
	private static $_MAP_STATUS = NULL;

	private $_required_files = array('/httpClient.php', '/lib/oauth-php/library/YotpoOAuthStore.php', '/lib/oauth-php/library/YotpoOAuthRequester.php'); 
	
	public function __construct()
	{
		// version test
		$version_mask = explode('.', _PS_VERSION_, 3);
		$version_test = $version_mask[0] > 0 && $version_mask[1] > 4;

		$this->name = 'yotpo';
		$this->tab = $version_test ? 'advertising_marketing' : 'Reviews';
		$this->version = '1.1.1';
		if($version_test){
			$this->author = 'Yotpo';
		}
		$this->need_instance = 1;

		parent::__construct();
		 
		$this->displayName = $this->l('AddReviews - Social reviews by Yotpo');
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

	public static function getAcceptedMapStatuses()
	{
		if(is_null(self::$_MAP_STATUS))
		{
			self::$_MAP_STATUS = array();
			$statuses = array('PS_OS_WS_PAYMENT', 'PS_OS_PAYMENT','PS_OS_DELIVERED','PS_OS_SHIPPING');
			foreach ($statuses as $status)
			{
				if(defined($status))
				{
					self::$_MAP_STATUS[] = (int)Configuration::get($status);
				}
				elseif (defined('_'.$status.'_')) 
				{
					self::$_MAP_STATUS[] = constant('_'.$status.'_');
				}
			}
		}
		return self::$_MAP_STATUS;
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
			if(!file_exists($this->_yotpo_module_path .$file))
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
			!$this->registerHook('extraLeft') OR
			!$this->registerHook('extraRight') OR			
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

		// Set bottom line enabled by default.
		Configuration::updateValue('yotpo_bottom_line_enabled', 1, false);
		
		// Set default bottom line location.
		Configuration::updateValue('yotpo_bottom_line_location', 'left_column', false);
				
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
		$accepted_status = self::getAcceptedMapStatuses();

		if(in_array($params['newOrderStatus']->id, $accepted_status))
		{
			$data = $this->prepareMapData($params);
			$app_key = Configuration::get('yotpo_app_key');
			$secret = Configuration::get('yotpo_oauth_token');

			if(isset($app_key) && isset($secret) && !is_null($data))
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
		$product = $this->getPageProduct(null);
		if($product != NULL && Configuration::get('yotpo_widget_location') == 'tab')
		{
			return "<div id='idTab-yotpo'>" . $this->showWidget($product) . "</div>";
		}
	}

	public function hookextraLeft($params)
	{
		return $this->showBottomLine('left_column');	
	}
	
	public function hookextraRight($params)
	{		
		return $this->showBottomLine('right_column');	
	}

	public function uninstall()
	{
		Configuration::deleteByName('yotpo_app_key');
		Configuration::deleteByName('yotpo_oauth_token');
		Configuration::deleteByName('yotpo_is_installed');
		Configuration::deleteByName('yotpo_language');
		Configuration::deleteByName('yotpo_widget_location');
		Configuration::deleteByName('yotpo_widget_tab_name');
		Configuration::deleteByName('yotpo_past_orders');

		return parent::uninstall();
	}

	// module configuration
	public function getContent()
	{
		if(Configuration::get('yotpo_is_installed') == NULL)
		{
			Configuration::updateValue('yotpo_is_installed', '1', false);
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
		$this->assignProductParams($product);
		if(Configuration::get('yotpo_widget_location') != 'other')
		{
			return $this->display(__FILE__,'tpl/widgetDiv.tpl');
		}
		return NULL;
	}

	private function assignProductParams($product)
	{
		global $smarty;
		$smarty->assign('yotpoProductId', $product->id);
		$smarty->assign('yotpoProductName', strip_tags($product->name));
		$smarty->assign('yotpoProductDescription', strip_tags($product->description));
		$smarty->assign('yotpoProductModel', $this->getProductModel($product));
		$smarty->assign('yotpoProductImageUrl', $this->getProductImageUrl($product->id));
		$smarty->assign('yotpoProductBreadCrumbs', $this->getBreadCrumbs($product));
	    $smarty->assign('yotpoLanguage', Configuration::get('yotpo_language'));
	}
	
	private function showBottomLine($bottom_line_location)
	{
		if(Configuration::get('yotpo_bottom_line_enabled') == true && Configuration::get('yotpo_bottom_line_location') === $bottom_line_location)
		{
			if(Configuration::get('yotpo_bottom_line_location') != 'other')
			{
				return $this->display(__FILE__,'tpl/bottomLineDiv.tpl');
			}
		}
		return null;
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

			if($is_mail_valid['status_code'] == 200 && 
			  	($is_mail_valid['json'] == true && $is_mail_valid['response']['available'] == true) || 
			  	($is_mail_valid['json'] == false && preg_match("/available[\W]*(true)/",$is_mail_valid['response']) == 1)
			  )
			{
				$registerResponse = $this->httpClient()->register($email, $name, $password, _PS_BASE_URL_);
				if($registerResponse['status_code'] == 200)
				{
					$app_key ='';
					$secret = '';
					if($registerResponse['json'] == true)
					{
						$app_key = $registerResponse['response']['app_key'];
					}
					else 
					{
						preg_match("/app_key[\W]*[\"'](.*?)[\"']/",$registerResponse['response'],$matches);
						$app_key = $matches[1];
						unset($matches);
					}
					$secret ='';
					if($registerResponse['json'] == true)
					{
						$secret = $registerResponse['response']['secret'];
					}
					else 
					{
						preg_match("/secret[\W]*[\"'](.*?)[\"']/",$registerResponse['response'],$matches);
						$secret = $matches[1];
					}					
					$accountPlatformResponse = $this->httpClient()->createAcountPlatform($app_key, $secret, _PS_BASE_URL_);
					if($accountPlatformResponse['status_code'] == 200)
					{
						Configuration::updateValue('yotpo_app_key', $app_key, false);
						Configuration::updateValue('yotpo_oauth_token', $secret, false);
						return $this->prepareSuccess($this->l('Account successfully created'));
					}
					else
					{
						return $this->prepareError($accountPlatformResponse['status_message']);	
					}
				}
				else
				{
					return $this->prepareError($registerResponse['status_message']);
				}
			}
			else
			{
				if($is_mail_valid['status_code'] == 200 )
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
			$language = Tools::getValue('yotpo_language');
			$location = Tools::getValue('yotpo_widget_location');
			$tabName = Tools::getValue('yotpo_widget_tab_name');
			$bottomLineEnabled = Tools::getValue('yotpo_bottom_line_enabled');
			$bottomLineLocation = Tools::getValue('yotpo_bottom_line_location');
			
			if($api_key == '')
			{
				return $this->prepareError($this->l('Api key is missing'));	
			}
			if($secret_token == '')
			{
				return $this->prepareError($this->l('Please fill out the secret token'));	
			}
			Configuration::updateValue('yotpo_app_key', Tools::getValue('yotpo_app_key'), false);
			Configuration::updateValue('yotpo_oauth_token', Tools::getValue('yotpo_oauth_token'), false);
			Configuration::updateValue('yotpo_language', $language, false);
			Configuration::updateValue('yotpo_widget_location', $location, false);
			Configuration::updateValue('yotpo_widget_tab_name', $tabName, false);
			Configuration::updateValue('yotpo_bottom_line_enabled', $bottomLineEnabled, false);
			Configuration::updateValue('yotpo_bottom_line_location', $bottomLineLocation, false);
			return $this->prepareSuccess();
		}
		elseif(Tools::isSubmit('yotpo_past_orders'))
		{
			$api_key = Tools::getValue('yotpo_app_key');
			$secret_token = Tools::getValue('yotpo_oauth_token');
			if($api_key != '' && $secret_token != '')
			{
				$past_orders = $this->getPastOrders();
				$is_success = true;
				foreach ($past_orders as $post_bulk) 
				{
					if(!is_null($post_bulk))
					{
						$response = $this->httpClient()->makePastOrdersRequest($post_bulk, $api_key, $secret_token);
						if ($response['status_code'] != 200 && $is_success)
						{
							$is_success = false;
							$this->prepareError($this->l($response['status_message']));
						}
					}
				}
				if($is_success)
				{
					Configuration::updateValue('yotpo_past_orders', 1, false);
					$this->prepareSuccess('Past orders sent successfully');
				}	
			}
			else 
			{
				$this->prepareError($this->l('You need to set your app key and secret token to post past orders'));
			}
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
        'widgetLanguage' => Configuration::get('yotpo_language'),       
        'widgetLocation' => Configuration::get('yotpo_widget_location'),
		'showPastOrdersButton' => Configuration::get('yotpo_past_orders') != 1 ? true : false,  
		'bottomLineEnabled' => Configuration::get('yotpo_bottom_line_enabled'), 
		'bottomLineLocation' => Configuration::get('yotpo_bottom_line_location'),      
        'tabName' => Configuration::get('yotpo_widget_tab_name')));

		$settings_template = $this->display(__FILE__, 'tpl/settingsForm.tpl');
		if (strpos($settings_template, 'yotpo_map_enabled') != false)
		{
			try 
			{
				$smarty->clear_compiled_template('settingsForm.tpl');
				$settings_template = $this->display(__FILE__, 'tpl/settingsForm.tpl');	
			}
			catch (Exception $e)
			{
				try 
				{
					$smarty->clear_compiled_tpl(_PS_MODULE_DIR_ . $this->name .'/tpl/settingsForm.tpl');
					$settings_template = $this->display(__FILE__, 'tpl/settingsForm.tpl');
				} catch (Exception $e) 
				{
					try 
					{
						$smarty->clearCompiledTemplate(_PS_MODULE_DIR_ . $this->name .'/tpl/settingsForm.tpl');	
						$settings_template = $this->display(__FILE__, 'tpl/settingsForm.tpl');								
					} catch (Exception $e) 
					{
					}
					
				}
			}
		}
		$this->_html .= $settings_template;
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
		$lang_id;
		if(isset($this->context))
		{
			$lang_id = $this->context->language->id; 
		}
		else 
		{
			global $cookie;
			$lang_id = $cookie->id_lang; 
		}
		$all_product_subs = Product::getProductCategoriesFull($product->id, $lang_id);
		if(isset($all_product_subs) && count($all_product_subs)>0)
		{
			foreach($all_product_subs as $subcat)
			{
				$sub_category = new Category($subcat['id_category'], $lang_id);
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

        $order = new Order((int)$params['id_order']);
        $customer = new Customer((int)$order->id_customer);
		$id_lang = !is_null($params['cookie']) && !is_null($params['cookie']->id_lang) ? $params['cookie']->id_lang : Configuration::get('PS_LANG_DEFAULT');
        if(Validate::isLoadedObject($order) && Validate::isLoadedObject($customer))
        {
        	$singleMapParams = array('id_order' => (int)$params['id_order'],
        								'date_add' => $order->date_add,
        								'email'    => $customer->email,
        								'firstname'=> $customer->firstname,
        								'lastname' => $customer->lastname,
        								'id_lang'  => $id_lang);
        	$result = $this->getSingleMapData($singleMapParams);
        	if(!is_null($result) && is_array($result))
        	{
        		$result['platform'] = 'prestashop';
        		return $result;
        	}
        }
	 	return NULL;
	}

	private function getSingleMapData($params)
	{
		$cart = Cart::getCartByOrderId($params['id_order']);
		if(Validate::isLoadedObject($cart))
		{
			$products = $cart->getProducts();
       		$currency = Currency::getCurrencyInstance($cart->id_currency);
       		if(!is_null($products) && is_array($products) && Validate::isLoadedObject($currency))
       		{
       			$data = array();
    	    	$data["order_date"] = $params['date_add'];
		    	$data["email"] = $params['email'];
		    	$data["customer_name"] = $params['firstname'] . ' ' . $params['lastname'];
		    	$data["order_id"] = $params['id_order'];
			    $data["currency_iso"] = $currency->iso_code;			    
			    $products_arr = array();
			    foreach ($products as $product) 
			    {
					$product_data = array();    
					$product_data['url'] = $this->getProductLink($product['id_product']); 
					$product_data['name'] = $product['name'];
					$product_data['image'] = $this->getProductImageUrl($product['id_product']);
					$product_data['description'] = $this->getDescritpion($product, intval($params['id_lang']));
					$product_data['price'] = $product['price'];

					$products_arr[$product['id_product']] = $product_data;

			    }
			    $data['products'] = $products_arr;
			    return $data;
        	}
        }
	 	return NULL;
	}	
	
	private function getPastOrders()
	{
		$accepted_status = join(',',self::getAcceptedMapStatuses());
		$result = Db::getInstance()->ExecuteS('SELECT  o.`id_order`,o.`id_lang`, o.`date_add`, c.`firstname`, c.`lastname`, c.`email` 
		FROM `'._DB_PREFIX_.'order_history` oh
		LEFT JOIN `'._DB_PREFIX_.'orders` o on o.`id_order` = oh.`id_order` 
		LEFT JOIN `'._DB_PREFIX_.'customer` c on c.`id_customer` = o.`id_customer` 	
		WHERE oh.`id_order_history` IN (SELECT MAX(`id_order_history`) FROM `'._DB_PREFIX_.'order_history` GROUP BY `id_order`) AND
		o.`date_add` <  NOW() AND 
		DATE_SUB(NOW(), INTERVAL '.self::PAST_ORDERS_DAYS_BACK.' day) < o.`date_add` AND 
		oh.`id_order_state` in ('.$accepted_status.')
		LIMIT 0,'.self::PAST_ORDERS_LIMIT.'');
		if(is_array($result))
		{
			$orders = array();
			foreach ($result as $singleMap)
			{
				$res = $this->getSingleMapData($singleMap);
				if(!is_null($res))
				{
					$orders[]= $res;
				}
			}
			$post_bulk_orders = array_chunk($orders, self::BULK_SIZE);
			$data = array();
			foreach ($post_bulk_orders as $index=>$bulk)
			{
				$data[$index] = array();
				$data[$index]['orders'] = $bulk;
				$data[$index]['platform'] = 'prestashop';			
			}			
			return $data;
		}
		return NULL;
	}	

	private function getPageProduct($product_id = null)
	{
		if($product_id == null)
		{
			$product_id = $this->parseProductId();
		}
		$product = new Product((int)($product_id), false, Configuration::get('PS_LANG_DEFAULT'));
		if(Validate::isLoadedObject($product))
		{
			return $product;
		}
		return null;
	}	
}
?>