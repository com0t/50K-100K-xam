<?php

// Reserved column names
$post_columns =  array(
                'post_title' => array('title'=>'Product Title','description'=>'Product Title. ie Name of the product'),
                'post_name' => array('title'=>'Product Permalink','description'=>'Unique part of the product URL'),
                'ID' => array('title'=>'Product ID','description'=>'Product ID'),
                'post_parent' => array('title'=>'Parent ID','description'=>'Parent Product ID , if you are importing variation Product'),
                'post_status' => array('title'=>'Product Status','description'=>'Product Status ( published , draft ...)'),
                'post_content' => array('title'=>'Product Description','description'=>'Description about the Product'),
                'post_excerpt' => array('title'=>'Product Short Description','description'=>'Short description about the Product'),
                'post_date' => array('title'=>'Post Date','description'=>'Product posted date', 'type' => 'date'),
                'post_password' => array('title'=>'Post Password','description'=>'To Protect a post with password'),
                'post_author' => array('title'=>'Product Author','description'=>'Product Author ( 1 - Admin )'),
                'menu_order' => array('title'=>'Menu Order','description'=>'If menu enabled , menu order'),
                'comment_status' => array('title'=>'Comment Status','description'=>'Comment Status ( Open or Closed comments for this prodcut)'),
                //'post_date_gmt' => array('title'=>'Post Date GMT','description'=>'Tooltip data Status'),
                
                'parent' => array('title'=>'Parent Title','description'=>'Parent Product Title , if you are importing variation Product'),
                'parent_sku' => array('title'=>'Parent SKU','description'=>'Parent Product SKU , if you are importing variation Product'),
                'sku' => array('title'=>'Product SKU','description'=>'Product SKU - This will unique and Product identifier'),
		'children' => array('title'=>'Children Product ID','description'=>'Linked Products id if you are importing Grouped products'),
                'downloadable' => array('title'=>'Type: Downloadable','description'=>'Is Product is downloadable eg:- Book'),
                'virtual' => array('title'=>'Type: Virtual','description'=>'Is Product is virtual'),
                'visibility' => array('title'=>'Visibility: Visibility','description'=>'Visibility status ( hidden or visible)'),                
                'purchase_note' => array('title'=>'Purchase note','description'=>'Purchase note'),
                'stock' => array('title'=>'Inventory: Stock','description'=>'Stock quantity'),
                'stock_status' => array('title'=>'Inventory: Stock Status','description'=>'InStock or OutofStock'),
                'backorders' => array('title'=>'Inventory: Backorders','description'=>'Backorders'),
                'sold_individually' => array('title'=>'Inventory: Sold individually','description'=>'Sold individually'),
                'low_stock_amount' => array('title'=>'Inventory: Low stock amount','description'=>'Low stock amount'),
                'manage_stock' => array('title'=>'Inventory: Manage Stock','description'=>'yes to enable no to disable'),
                'sale_price' => array('title'=>'Price: Sale Price','description'=>'Sale Price'),
                'regular_price' => array('title'=>'Price: Regular Price','description'=>'Regular Price'),
                'sale_price_dates_from' => array('title'=>'Sale Price Dates: From','description'=>'Sale Price Dates effect from', 'type' => 'date'),
                'sale_price_dates_to' => array('title'=>'Sale Price Dates: To','description'=>'Sale Price Dates effect to', 'type' => 'date'),
                'weight' => array('title'=>'Dimensions: Weight','description'=>'Wight of product in LB , OZ , KG as of your woocommerce Unit'),
                'length' => array('title'=>'Dimensions: length','description'=>'Length'),
                'width' => array('title'=>'Dimensions: width','description'=>'Width'),
                'height' => array('title'=>'Dimensions: height','description'=>'Height'),
                'tax_status' => array('title'=>'Tax: Tax Status','description'=>'Taxable product or not'),
                'tax_class' => array('title'=>'Tax: Tax Class','description'=>'Tax class ( eg:- reduced rate)'),
                'upsell_ids' => array('title'=>'Related Products: Upsell IDs','description'=>'Upsell Product ids'),
                'crosssell_ids' => array('title'=>'Related Products: Crosssell IDs','description'=>'Crosssell Product ids'),
                'file_paths' => array('title'=>'Downloads: File Paths (WC 2.0.x)','description'=>'File Paths'),
                'downloadable_files' => array('title'=>'Downloads: Downloadable Files (WC 2.1.x)','description'=>'Downloadable Files'),
                'download_limit' => array('title'=>'Downloads: Download Limit','description'=>'Download Limit'),
                'download_expiry' => array('title'=>'Downloads: Download Expiry','description'=>'Download Expiry'),
                'product_url' => array('title'=>'External: Product URL','description'=>'Product URL if the Product is external'),
                'button_text' => array('title'=>'External: Button Text','description'=>'Buy button text for Product , if the Product is external'),
                'images' => array('title'=>'Images/Gallery','description'=>'Image URLs seperated with &#124;'),
                'product_page_url' => array('title'=>'Product Page URL','description'=>'Product Page URL'),
                'meta:total_sales' => array('title'=>'meta:total_sales','description'=>'Total sales for the Product'),
//                'tax:product_type' => array('title'=>'Product Type','description'=>'( eg:- simple , variable)'),
//                'tax:product_cat' => array('title'=>'Product Categories','description'=>'Product related categories'),
//                'tax:product_tag' => array('title'=>'Product Tags','description'=>'Product related tags'),
//                'tax:product_shipping_class' => array('title'=>'Product Shipping Class','description'=>'Allow you to group similar products for shipping'),
//                'tax:product_visibility' => array('title'=>'Product Visibility: Featured','description'=>'Featured Product'),

    
);

if (class_exists('WPSEO_Options')) {
    /* Yoast is active */

    $post_columns['meta:_yoast_wpseo_focuskw'] = array('title' => 'meta:_yoast_wpseo_focuskw', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_canonical'] = array('title' => 'meta:_yoast_wpseo_canonical', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_bctitle'] = array('title' => 'meta:_yoast_wpseo_bctitle', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_meta-robots-adv'] = array('title' => 'meta:_yoast_wpseo_meta-robots-adv', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_is_cornerstone'] = array('title' => 'meta:_yoast_wpseo_is_cornerstone', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_metadesc'] = array('title' => 'meta:_yoast_wpseo_metadesc', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_linkdex'] = array('title' => 'meta:_yoast_wpseo_linkdex', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_estimated-reading-time-minutes'] = array('title' => 'meta:yoast_wpseo_estimated-reading-time-minutes', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_content_score'] = array('title' => 'meta:_yoast_wpseo_focuskw', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_title'] = array('title' => 'meta:_yoast_wpseo_title', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_metadesc'] = array('title' => 'meta:_yoast_wpseo_metadesc', 'description' => 'yoast SEO');
    $post_columns['meta:_yoast_wpseo_metakeywords'] = array('title' => 'meta:_yoast_wpseo_metakeywords', 'description' => 'yoast SEO');
}

if (function_exists( 'aioseo' )) {
        
    /* All in One SEO is active */
    
    $post_columns['meta:_aioseo_title'] = array('title' => 'meta:_aioseo_title', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_description'] = array('title' => 'meta:_aioseo_description', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_keywords'] = array('title' => 'meta:_aioseo_keywords', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_og_title'] = array('title' => 'meta:_aioseo_og_title', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_og_description'] = array('title' => 'meta:_aioseo_og_description', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_twitter_title'] = array('title' => 'meta:_aioseo_twitter_title', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_og_article_tags'] = array('title' => 'meta:_aioseo_og_article_tags', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_twitter_description'] = array('title' => 'meta:_aioseo_twitter_description', 'description' => 'All in One SEO');
}

if (apply_filters('wpml_setting', false, 'setup_complete')) {

    $post_columns['wpml:language_code'] = array('title'=>'wpml:language_code','description'=>'WPML language code');
    $post_columns['wpml:original_product_id'] = array('title'=>'wpml:original_product_id','description'=>'WPML Original Product ID');
    $post_columns['wpml:original_product_sku'] = array('title'=>'wpml:original_product_sku','description'=>'WPML Original Product SKU');
}

return apply_filters('woocommerce_csv_product_import_reserved_fields_pair', $post_columns);