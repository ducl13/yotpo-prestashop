<?php
class Map 
{
  const YOTPO_OAUTH_TOKEN_URL = "https://api.yotpo.com/oauth/token";
  const YOTPO_API_URL = "https://api.yotpo.com/apps";
  const HTTP_REQUEST_TIMEOUT = 15;


  public static function mailAfterPurchase($params, $context)
  {
    $_ds = defined('DS') ? DS : '/';
    $app_key = Configuration::get($context->name.'_app_key');
    $secret = Configuration::get($context->name.'_oauth_token');
    $enable_feature = Configuration::get($context->name.'_map_enabled');
    
    //check if both app_key and secret exist
    if(($app_key == null) or ($secret == null) or $enable_feature == "0" || $enable_feature == NULL)
    {
      return;
    }
    
    $OAuthStorePath = _PS_ROOT_DIR_ . _MODULE_DIR_ . $context->name . $_ds . 'lib'. $_ds .'oauth-php' . $_ds . 'library' . $_ds . 'OAuthStore.php';
    $OAuthRequesterPath = _PS_ROOT_DIR_ .  _MODULE_DIR_ . $context->name . $_ds . 'lib'. $_ds .'oauth-php' . $_ds . 'library' . $_ds . 'OAuthRequester.php';

    require_once ($OAuthStorePath);
    require_once ($OAuthRequesterPath);

    $data = array();
    $customer = NULL;
    if(isset($params['cart']))
      $customer = new Customer((int)$params['cart']->id_customer);
    else
    {
      $order = new Order((int)$params['id_order']);
      $customer = new Customer((int)$order->id_customer);
    }
    $data["email"] = $customer->email;
    $data["customer_name"] = $customer->firstname . ' ' . $customer->lastname;
    $data["order_id"] = $params['id_order'];
    $data['platform'] = 'prestashop';

    $products = Map::_getOrderDetails($params['id_order']);
    $products_arr = array();

    foreach ($products as $product) {

      $product_data = array();
      
      $full_product = new Product((int)($product['product_id']), false, (int)($params['cookie']->id_lang));      
      $product_data['url'] = $full_product->getLink();  
      $product_data['name'] = $full_product->name;
      $product_data['image'] = $context->_getProductImageUrl($product['product_id']);
      $product_data['description'] = strip_tags($full_product->description);

      $products_arr[$product['product_id']] = $product_data;
    }

    $data['products'] = $products_arr;
    
    $yotpo_options = array( 'consumer_key' => $app_key, 'consumer_secret' => $secret, 'client_id' => $app_key, 'client_secret' => $secret, 'grant_type' => 'client_credentials' );
    
    OAuthStore::instance("2Leg", $yotpo_options);

    try
    {

      $request = new OAuthRequester(self::YOTPO_OAUTH_TOKEN_URL, "POST", $yotpo_options);         
      $result = $request->doRequest(0);

      $response = $result['body'];
      $tokenParams = json_decode($result['body'], true);
      
      if(isset($tokenParams['access_token']))
      {
        $data['utoken'] = $tokenParams['access_token'];
        $parsed_data = json_encode($data);
        $opts = array(
          'http'=>array(
            'method'=>"POST",
            'timeout' => self::HTTP_REQUEST_TIMEOUT,
            'header'=> "Content-type: application/json\r\n" .
                       "Content-Length: " . strlen($parsed_data) . "\r\n",
            'content' => $parsed_data
          )
        );
        $feed_url = self::YOTPO_API_URL . '/' . $app_key . "/purchases/";
        $stream_context = @stream_context_create($opts); 
        $fp = fopen($feed_url, 'r', false, $stream_context);
      }
    }
    catch(OAuthException2 $e)
    {//Do nothing
    }
  }

  private static function _getOrderDetails($id_order)
  {
    if(method_exists('OrderDetail', 'getList'))
      return OrderDetail::getList($id_order);
    else
      return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'order_detail` WHERE `id_order` = '.(int)$id_order);  
  }
}
?>