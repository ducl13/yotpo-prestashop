<?php
if (!defined('_PS_VERSION_'))
  exit;
 
class Yotpo extends Module
{

  private $_html = '';
  private $_postErrors = array();
  public function __construct()
    {
      // version test
      $version_mask = explode('.', _PS_VERSION_, 3);
      $version_test = $version_mask[0] > 0 && $version_mask[1] > 4;

      $this->name = 'yotpo';
      $this->tab = $version_test ? 'advertising_marketing' : 'Yotpo';
      $this->version = 1.0;
      if($version_test)
        $this->author = 'Alon';
      $this->need_instance = 1;
 
      parent::__construct();
   
      $this->displayName = $this->l('Yotpo');
      $this->description = $this->l('Allow MAP');

      require(_PS_MODULE_DIR_.'yotpo/map/map.php');
    }
 
  public function install()
  {
    $version_mask = explode('.', _PS_VERSION_, 3);
    $version_test = $version_mask[0] > 0 && $version_mask[1] > 4;
    //TODO make the second or part to be valid only if map check box is checked.
    if($version_test)
    {
      
      if (parent::install() == false OR !$this->registerHook('displayFooterProduct') 
                                     OR !$this->registerHook('actionPaymentConfirmation')) {
        return false;
      }
    }
    else
    {
      if (parent::install() == false OR !$this->registerHook('productfooter') 
                                     OR !$this->registerHook('paymentConfirm')) {
        return false;  
      }  
    }
    return true;
  }

  public function hookpaymentConfirm($params)
  {
    $this->hookActionPaymentConfirmation($params);    
  }

  public function hookproductfooter($params)
  {
    return $this->hookdisplayFooterProduct($params);    
  }

  public function hookdisplayFooterProduct($params)
  {

    global $smarty;
    $product = $params['product'];
    $smarty->assign('yotpoAppkey', Configuration::get($this->name.'_app_key'));
    $smarty->assign('yotpoProductId', $product->id);
    $smarty->assign('yotpoProductName', strip_tags(nl2br($product->name)));
    $smarty->assign('yotpoProductDescription', strip_tags(nl2br($product->description)));
    $smarty->assign('yotpoDomain', $this->_getShopDomain());
    $smarty->assign('yotpoProductModel', $this->_getProductModel($product));
    $smarty->assign('yotpoProductImageUrl', $this->_getProductImageUrl($product->id));
    $smarty->assign('yotpoProductBreadCrumbs', $this->_getBreadCrumbs($product));

    // TODO check if can insert this in header part so it will be loaded only once
    echo "<script src ='http://www.yotpo.com/js/yQuery.js'></script>";
    return $this->display(__FILE__,'yotpo.tpl');
  }

  private function _getShopDomain()
  {
    if(method_exists('Tools', 'getShopDomain'))
      return Tools::getShopDomain(false,false);
    return str_replace('www.', '', $_SERVER['HTTP_HOST']);;
  }

  public function hookActionPaymentConfirmation($params)
  {
    Map::mailAfterPurchase($params, $this);
  }

  public function _getProductImageUrl($id_product)
  {
    $id_image = Product::getCover($id_product);
    // get Image by id
    if (sizeof($id_image) > 0) {
        $image = new Image($id_image['id_image']);
        // get image full URL

        return $image_url = method_exists($image, 'getExistingImgPath') ? _PS_BASE_URL_._THEME_PROD_DIR_.$image->getExistingImgPath().".jpg" : NULL;
    }  
    return NULL;
  }

  public function uninstall()
  {
    if (!parent::uninstall())
      Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'yotpo`');
    parent::uninstall();
  }

// module configuration
  public function getContent()
  {
    if (Tools::isSubmit('submit'))
    {
      $this->_postValidation();
      if (!sizeof($this->_postErrors))
      {
       $this->_postProcess();
      }
      else
      {
        foreach ($this->_postErrors AS $err)
        {
          $this->_html .= '<div class="alert error">'.$err.'</div>';
        }
      }
    }
    $this->_displayForm();
    return $this->_html;
  }

// module settings
  private function _displayForm()
  {
    $yotpo_map_enabled = Configuration::get($this->name.'_map_enabled') == "0" ? false : true;
    
    $this->_html .= '<h2>'.$this->displayName.'</h2>';
    $this->_html .= '
    <form action="'.$_SERVER['REQUEST_URI'].'" method="post">
      <fieldset>
        <legend><img src="../img/admin/cog.gif" alt="" class="middle" />'.$this->l('Settings').'</legend>
        <label>'.$this->l('App key').'</label>
        <div class="margin-form">
          <input type="text" name="yotpo_app_key" value="'.Tools::getValue('yotpo_app_key', Configuration::get($this->name.'_app_key')).'"/>
        </div>
        <label>'.$this->l('Secret token').'</label>
        <div class="margin-form">
          <input type="text" name="yotpo_oauth_token" value="'.Tools::getValue('yotpo_oauth_token', Configuration::get($this->name.'_oauth_token')).'"/>
        </div>

        <div class="margin-form">
          <input type="checkbox" name="yotpo_map_enabled" value="yotpo_map_enabled" ';
          if($yotpo_map_enabled == true)
            $this->_html .= 'checked=checked';
          $this->_html .= ''.$this->l('Mail after purchase'). '<br>
        </div>
        <input type="submit" name="submit" value="'.$this->l('Update').'" class="button" />
      </fieldset>
    </form>';
  }

  private function _postValidation()
  {
    $api_key = Tools::getValue('yotpo_app_key');
    $secret_token = Tools::getValue('yotpo_oauth_token');
    $map_enabled = Tools::getValue('yotpo_map_enabled');
    if($api_key == '')
      $this->_postErrors[] = $this->l('Please fill out the api key');
    if($map_enabled && $secret_token == '')
      $this->_postErrors[] = $this->l('Please fill out the secret token');      
  }

  private function _postProcess()
  {
    $yotpo_map_enabled = Tools::getValue('yotpo_map_enabled') == false ? "0" : "1";

    Configuration::updateValue($this->name.'_map_enabled', $yotpo_map_enabled, false);
    Configuration::updateValue($this->name.'_app_key', Tools::getValue('yotpo_app_key'), false);
    Configuration::updateValue($this->name.'_oauth_token', Tools::getValue('yotpo_oauth_token'), false);

    $this->_html .= '<div class="conf confirm">'.$this->l('Settings updated').'</div>';
  }

  private function _getProductModel($product)
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

  private function _getBreadCrumbs($product)
  {
   if (!method_exists('Product', 'getProductCategoriesFull'))
    return ''; 
   $result = '';
   $all_product_subs = Product::getProductCategoriesFull($product->id, $this->context->language->id);
   $all_product_subs_path = array();
   if(isset($all_product_subs) && count($all_product_subs)>0)
   {
      foreach($all_product_subs as $subcat)
      {
        $sub_category = new Category($subcat['id_category'], $this->context->language->id);
        $sub_category_path = $sub_category->getParentsCategories();
        foreach ($sub_category_path as $key) {
          $result .= ''.$key['name'].';';  
        }
        $result .= ',';  
      }
   }
   if($result[strlen($result)-1] == ',')
   {
     $result = substr_replace($result ,"",-1); 
   }
   return $result;
  }

    /**
     * Logs messages/variables/data to browser console from within php
     *
     * @param $name: message to be shown for optional data/vars
     * @param $data: variable (scalar/mixed) arrays/objects, etc to be logged
     * @param $jsEval: whether to apply JS eval() to arrays/objects
     *
     * @return none
     * @author Sarfraz
     */
     function logConsole($name, $data = NULL, $jsEval = FALSE)
     {
          if (! $name) return false;

          $isevaled = false;
          $type = ($data || gettype($data)) ? 'Type: ' . gettype($data) : '';

          if ($jsEval && (is_array($data) || is_object($data)))
          {
               $data = 'eval(' . preg_replace('#[\s\r\n\t\0\x0B]+#', '', json_encode($data)) . ')';
               $isevaled = true;
          }
          else
          {
               $data = json_encode($data);
          }

          # sanitalize
          $data = $data ? $data : '';
          $search_array = array("#'#", '#""#', "#''#", "#\n#", "#\r\n#");
          $replace_array = array('"', '', '', '\\n', '\\n');
          $data = preg_replace($search_array,  $replace_array, $data);
          $data = ltrim(rtrim($data, '"'), '"');
          $data = $isevaled ? $data : ($data[0] === "'") ? $data : "'" . $data . "'";

$js = <<<JSCODE
\n<script>
     // fallback - to deal with IE (or browsers that don't have console)
     if (! window.console) console = {};
     console.log = console.log || function(name, data){};
     // end of fallback

     console.log('$name');
     console.log('------------------------------------------');
     console.log('$type');
     console.log($data);
     console.log('\\n');
</script>
JSCODE;

          echo $js;
     } # end logConsole

}
?>