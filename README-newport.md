PrestaShop
----------

NEWPORT:

/public_html/themes/leo_xalem/css/global.css, line 1339: (DONE)
  .col-sm-6 {
    width: 50%;
    height: 500px;
  }

/*******************************************************
Yotpo
********************************************************/
.owl-carousel .product-container .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


/public_html/themes/leo_xalem/product.tpl: (DONE)
                        <h1 itemprop="name">{$product->name|escape:'html':'UTF-8'}</h1>
                        <div class="yotpo bottomLine"
                                data-product-id="{$yotpoProductId|intval}"
                                data-url="{$yotpoProductLink|escape:'htmlall':'UTF-8'}">
                        </div>

/public_html/themes/leo_xalem/css/product.css: (DONE)

/*******************************************************
Yotpo
********************************************************/
.yotpo .standalone-bottomline {
    margin-bottom: 20px;
}

.aggregateRating.no-display {
    display: none;
}


AP PageBuilder/Ap Products List Builder/Edit default/Add "reviews" after "name"/Edit "reviews": (DONE)
            <div class="yotpo bottomLine" 
                data-product-id="{$product.id_product|intval}"
                data-url="{$product.link|escape:'html':'UTF-8'}">
            </div>

/public_html/themes/roma1/css/product_list.css: (DONE)

/*******************************************************
Yotpo
********************************************************/
.product_list.grid .product-container .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


/public_html/themes/leo_xalem/products-comparison.tpl: (DONE)
                                                <h5>
                                                    	<a class="product-name" href="{$product->getLink()|escape:'html':'UTF-8'}" title="{$product->name|truncate:32:'...'|escape:'html':'UTF-8'}">
                                                                {$product->name|truncate:45:'...'|escape:'html':'UTF-8'}
                                                        </a>
                                                </h5>
                                                <div class="yotpo bottomLine"
                                                        data-product-id="{$product->id}"
                                                        data-url="{$product->getLink()|escape:'html':'UTF-8'}">
                                                </div>


/public_html/themes/leo_xalem/css/comparator.css: (DONE)

/*******************************************************
Yotpo
********************************************************/
.products_block .ajax_block_product .standalone-bottomline {
    display: flex;
    justify-content: center;
    padding: 0 0 10px 0;
}


AP PageBuilder/Ap Profiles/Manage/Edit Home 6 (profile1477332994)/Add new block after New Products: (DONE)

<div class="yotpo yotpo-reviews-carousel" data-background-color="transparent" data-mode="most_recent" data-type="both" data-count="9" data-show-bottomline="1" data-autoplay-enabled="1" data-autoplay-speed="3000" data-show-navigation="1">&nbsp;</div>
<div style="clear: both;"></div>


SEO Page
  - Preferences/CMS/Add new CMS Page (DONE)
  - Add "Reviews" to footer using AP PageBuilder


<div class="block title_center horizontal_mode">
<div class="yotpo yotpo-reviews-carousel" data-background-color="transparent" data-mode="most_recent" data-type="both" data-count="9" data-show-bottomline="1" data-autoplay-enabled="1" data-autoplay-speed="3000" data-show-navigation="1">&nbsp;</div>
<div style="clear: both;"></div>
<div id="yotpo-testimonials-custom-tab"></div>
</div>

/public_html/themes/leo_xalem/js/cms.js, line 38: (DONE)

	if ($('body').hasClass('cms-10')) {
    (function e(){var e=document.createElement("script");e.type="text/javascript",e.async=!0, e.src="//staticw2.yotpo.com/### API KEY ###/widget.js";var t=document.getElementsByTagName("script")[0]; t.parentNode.insertBefore(e,t)})(); 
  }
