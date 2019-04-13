PrestaShop
----------

NATURES:

/public_html/modules/fieldfeaturedproductslider/views/templates/hook/fieldfeaturedproductslider.tpl:
/public_html/modules/fieldfeaturedproductslider/views/templates/hook/fieldfeaturedproductslider_vertical.tpl:
                                <div class="right-block">
                                    <h5 class="sub_title_font">
                                    {if isset($product.pack_quantity) && $product.pack_quantity}{$product.pack_quantity|intval|cat:' x '}{/if}
                                    <a class="product-name" href="{$product.link|escape:'html':'UTF-8'}" title="{$product.name|escape:'html':'UTF-8'}">
                                        {$product.name|truncate:45:'...'|escape:'html':'UTF-8'}
                                    </a>
                                </h5>
                                <div class="yotpo bottomLine" 
                                        data-product-id="{$product.id_product|intval}"
                                        data-url="{$product.link|escape:'html':'UTF-8'}">
                                </div>


/public_html/modules/fieldfeaturedproductslider/views/css/hook/fieldfeaturedproductslider.css:

/*******************************************************
Yotpo
********************************************************/
#featured_products .item .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


/public_html/modules/fieldproductcates/views/templates/hook/fieldproductcates.tpl:
                                <div class="right-block">
                                    <h5 class="sub_title_font">
                                    {if isset($product.pack_quantity) && $product.pack_quantity}{$product.pack_quantity|intval|cat:' x '}{/if}
                                    <a class="product-name" href="{$product.link|escape:'html':'UTF-8'}" title="{$product.name|escape:'html':'UTF-8'}">
                                        {$product.name|truncate:45:'...'|escape:'html':'UTF-8'}
                                    </a>
                                </h5>
                                <div class="yotpo bottomLine"
                                        data-product-id="{$product.id_product|intval}"
                                        data-url="{$product.link|escape:'html':'UTF-8'}">
                                </div>


/public_html/modules/fieldproductcates/views/css/hook/fieldproductcates.css:
/*******************************************************
Yotpo
********************************************************/
#productCates .item .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


/public_html/themes/roma1/product.tpl:
                        <h1 itemprop="name">{$product->name|escape:'html':'UTF-8'}</h1>
                        <div class="yotpo bottomLine"
                                data-product-id="{$yotpoProductId|intval}"
                                data-url="{$yotpoProductLink|escape:'htmlall':'UTF-8'}">
                        </div>


/public_html/themes/roma1/product-list.tpl:
            <h2 itemprop="name">
                <a class="product-name" href="{$product.link|escape:'html':'UTF-8'}" title="{$product.name|escape:'html':'UTF-8'}" itemprop="url" >
                    {if isset($product.pack_quantity) && $product.pack_quantity}<strong>{$product.pack_quantity|intval|cat:' x '}</strong>{/if}
                    {$product.name|truncate:45:'...'|escape:'html':'UTF-8'}
                </a>
            </h2>
            <div class="yotpo bottomLine" 
                data-product-id="{$product.id_product|intval}"
                data-url="{$product.link|escape:'html':'UTF-8'}">
            </div>

/public_html/themes/roma1/products-comparison.tpl:
                                                <h5>
                                                    	<a class="product-name" href="{$product->getLink()|escape:'html':'UTF-8'}" title="{$product->name|truncate:32:'...'|escape:'html':'UTF-8'}">
                                                                {$product->name|truncate:45:'...'|escape:'html':'UTF-8'}
                                                        </a>
                                                </h5>
                                                <div class="yotpo bottomLine"
                                                        data-product-id="{$product->id}"
                                                        data-url="{$product->getLink()|escape:'html':'UTF-8'}">
                                                </div>


/public_html/themes/roma1/css/product.css:

/*******************************************************
Yotpo
********************************************************/
.yotpo .standalone-bottomline {
    margin-bottom: 20px;
}

.aggregateRating.no-display {
    display: none;
}


/public_html/themes/roma1/css/product_list.css:

/*******************************************************
Yotpo
********************************************************/
.product_list.grid .product-container .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


/public_html/themes/roma1/css/comparator.css:

/*******************************************************
Yotpo
********************************************************/
.products_block .ajax_block_product .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


/public_html/themes/roma1/js/global.js:
- add after line 262:
                        var yotpoE = $(element).find('.yotpo');
                        var yotpoAttributes = 'class="yotpo bottomLine yotpo-small"';
                        yotpoAttributes += ' data-product-id="' + yotpoE.attr('data-product-id') + '"';
                        yotpoAttributes += ' data-url="' + yotpoE.attr('data-url') + '"';
                        yotpoAttributes += ' data-yotpo-element-id="' + yotpoE.attr('data-yotpo-element-id') + '"';

- add after line 272 (after above addition):
                                        html += '<div ' + yotpoAttributes + '>' + $(element).find('.yotpo').html() + '</div>';

- add after line 306 (after above addition):
                        var yotpoE = $(element).find('.yotpo');
                        var yotpoAttributes = 'class="yotpo bottomLine yotpo-small"';
                        yotpoAttributes += ' data-product-id="' + yotpoE.attr('data-product-id') + '"';
                        yotpoAttributes += ' data-url="' + yotpoE.attr('data-url') + '"';
                        yotpoAttributes += ' data-yotpo-element-id="' + yotpoE.attr('data-yotpo-element-id') + '"';

- add after line 323 (after above addition):
                                html += '<div ' + yotpoAttributes + '">' + $(element).find('.yotpo').html() + '</div>';


Add new block on home page for Reviews Carousel
  - FIELDTHEMES/Manage Staticblocks.  Add a new block.

<div class="yotpo yotpo-reviews-carousel" data-background-color="transparent" data-mode="most_recent" data-type="both" data-count="9" data-show-bottomline="1" data-autoplay-enabled="1" data-autoplay-speed="3000" data-show-navigation="1">&nbsp;</div>
<div style="clear: both;"></div>


SEO Page
  - Preferences/CMS/Add new CMS Page

<div class="block title_center horizontal_mode">
<div class="yotpo yotpo-reviews-carousel" data-background-color="transparent" data-mode="most_recent" data-type="both" data-count="9" data-show-bottomline="1" data-autoplay-enabled="1" data-autoplay-speed="3000" data-show-navigation="1">&nbsp;</div>
<div style="clear: both;"></div>
<script>// <![CDATA[
(function e(){var e=document.createElement("script");e.type="text/javascript",e.async=!0, e.src="//staticw2.yotpo.com/### API KEY ###/widget.js";var t=document.getElementsByTagName("script")[0]; t.parentNode.insertBefore(e,t)})();
// ]]></script>
<div id="yotpo-testimonials-custom-tab"></div>
</div>

