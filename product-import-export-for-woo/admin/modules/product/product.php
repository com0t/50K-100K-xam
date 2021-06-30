<?php
/**
 * Product section of the plugin
 *
 * @link          
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}

if(!class_exists('Wt_Import_Export_For_Woo_Basic_Product')){
class Wt_Import_Export_For_Woo_Basic_Product {

    public $module_id = '';
    public static $module_id_static = '';
    public $module_base = 'product';
    public $module_name = 'Product Import Export for WooCommerce';
    public $min_base_version= '1.0.0'; /* Minimum `Import export plugin` required to run this add on plugin */

    private $importer = null;
    private $exporter = null;
    private $product_categories = null;
    private $product_tags = null;
    private $product_taxonomies = array();
    private $all_meta_keys = array();
    private $product_attributes = array();
    private $exclude_hidden_meta_columns = array();
    private $found_product_meta = array();
    private $found_product_hidden_meta = array();
    private $selected_column_names = null;

    public function __construct()
    {
        /**
        *   Checking the minimum required version of `Import export plugin` plugin available
        */
        if(!Wt_Import_Export_For_Woo_Basic_Common_Helper::check_base_version($this->module_base, $this->module_name, $this->min_base_version))
        {
            return;
        }
        if(!function_exists('is_plugin_active'))
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        if(!is_plugin_active('woocommerce/woocommerce.php'))
        {
            return;
        }

        $this->module_id = Wt_Import_Export_For_Woo_Basic::get_module_id($this->module_base);
        self::$module_id_static = $this->module_id;

        add_filter('wt_iew_exporter_post_types_basic', array($this, 'wt_iew_exporter_post_types_basic'), 10, 1);
        add_filter('wt_iew_importer_post_types_basic', array($this, 'wt_iew_exporter_post_types_basic'), 10, 1);

        add_filter('wt_iew_exporter_alter_filter_fields_basic', array($this, 'exporter_alter_filter_fields'), 10, 3);
        
        add_filter('wt_iew_exporter_alter_mapping_fields_basic', array($this, 'exporter_alter_mapping_fields'), 10, 3);        
        add_filter('wt_iew_importer_alter_mapping_fields_basic', array($this, 'get_importer_post_columns'), 10, 3);  
        
        add_filter('wt_iew_exporter_alter_advanced_fields_basic', array($this, 'exporter_alter_advanced_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_advanced_fields_basic', array($this, 'importer_alter_advanced_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_meta_mapping_fields_basic', array($this, 'exporter_alter_meta_mapping_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_meta_mapping_fields_basic', array($this, 'importer_alter_meta_mapping_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_mapping_enabled_fields_basic', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_mapping_enabled_fields_basic', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);

        add_filter('wt_iew_exporter_do_export_basic', array($this, 'exporter_do_export'), 10, 7);
        add_filter('wt_iew_importer_do_import_basic', array($this, 'importer_do_import'), 10, 8); 
                
        add_filter('wt_iew_importer_steps_basic', array($this, 'importer_steps'), 10, 2);
        
    }
    
    /**
    *   Altering advanced step description
    */
    public function importer_steps($steps, $base)
    {
        if($this->module_base==$base)
        {
            $steps['advanced']['description']=__('Use advanced options from below to decide updates to existing products, batch import count. You can also save the template file for future imports.');
        }
        return $steps;
    }

    public function importer_do_import($import_data, $base, $step, $form_data, $selected_template_data, $method_import, $batch_offset, $is_last_batch) {        
        if ($this->module_base != $base) {
            return $import_data;
        }
            
        if(0 == $batch_offset){                        
            $memory = size_format(wt_let_to_num_basic(ini_get('memory_limit')));
            $wp_memory = size_format(wt_let_to_num_basic(WP_MEMORY_LIMIT));                      
            Wt_Import_Export_For_Woo_Basic_Logwriter::write_log($this->module_base, 'import', '---[ New import started at '.date('Y-m-d H:i:s').' ] PHP Memory: ' . $memory . ', WP Memory: ' . $wp_memory);
        }
        
        include plugin_dir_path(__FILE__) . 'import/import.php';
        $import = new Wt_Import_Export_For_Woo_Basic_Product_Import($this);
        
        $response = $import->prepare_data_to_import($import_data,$form_data,$batch_offset,$is_last_batch);
        
        if($is_last_batch){
            Wt_Import_Export_For_Woo_Basic_Logwriter::write_log($this->module_base, 'import', '---[ Import ended at '.date('Y-m-d H:i:s').']---');
        }
        return $response;
    }

    public function exporter_do_export($export_data, $base, $step, $form_data, $selected_template_data, $method_export, $batch_offset) {        
        if ($this->module_base != $base) {
            return $export_data;
        }
               
        switch ($method_export) {
            case 'quick':
                $this->set_export_columns_for_quick_export($form_data);  
                break;

            case 'template':               
            case 'new':
                $this->set_selected_column_names($form_data);
                break;
            
            default:
                break;
        }
        
        include plugin_dir_path(__FILE__) . 'export/export.php';
        $export = new Wt_Import_Export_For_Woo_Basic_Product_Export($this);

        $header_row = $export->prepare_header();

        $data_row = $export->prepare_data_to_export($form_data, $batch_offset,$step);
        

            $export_data = array(
                'head_data' => $header_row,
                'body_data' => $data_row['data'],
                'total' => $data_row['total'],
            ); 

        
        return $export_data;
    }
        
    /**
     * Adding current post type to export list
     *
     */
    public function wt_iew_exporter_post_types_basic($arr) {
        $arr['product'] = __('Product');
        return $arr;
    }

    /**
     * Add/Remove steps in export section.
     * @param array $steps array of built in steps
     * @param string $base product, order etc
     * @return array $steps 
     */
    public function wt_iew_exporter_steps($steps, $base) {
        if ($base == $this->module_base) {
            foreach ($steps as $stepk => $stepv) {
                $out[$stepk] = $stepv;
                if ($stepk == 'filter') {
                    /*
                      $out['product']=array(
                      'title'=>'Product',
                      'description'=>'',
                      );
                     */
                }
            }
        } else {
            $out = $steps;
        }
        return $out;
    }
    
    
    /*
     * Setting default export columns for quick export
     */
    
    public function set_export_columns_for_quick_export($form_data) {

        $post_columns = self::get_product_post_columns();

        $this->selected_column_names = array_combine(array_keys($post_columns), array_keys($post_columns));
        
        if (isset($form_data['method_export_form_data']['mapping_enabled_fields']) && !empty($form_data['method_export_form_data']['mapping_enabled_fields'])) {
            foreach ($form_data['method_export_form_data']['mapping_enabled_fields'] as $value) {
                $additional_quick_export_fields[$value] = array('fields' => array());
            }

            $export_additional_columns = $this->exporter_alter_meta_mapping_fields($additional_quick_export_fields, $this->module_base, array());
            foreach ($export_additional_columns as $value) {
                $this->selected_column_names = array_merge($this->selected_column_names, $value['fields']);
            }
        }
    }


    /**
     * Get product categories
     * @return array $categories 
     */
    private function get_product_categories() {
        if (!is_null($this->product_categories)) {
            return $this->product_categories;
        }
        $out = array();
        $product_categories = get_terms('product_cat');
        if (!is_wp_error($product_categories)) {
            $version = get_bloginfo('version');
            foreach ($product_categories as $category) {
                $out[$category->slug] = (( $version < '4.8') ? $category->name : get_term_parents_list($category->term_id, 'product_cat', array('separator' => ' -> ')));
            }
        }
        $this->product_categories = $out;
        return $out;
    }

    private function get_product_tags() {
        if (!is_null($this->product_tags)) {
            return $this->product_tags;
        }
        $out = array();
        $product_tags = get_terms('product_tag');
        if (!is_wp_error($product_tags)) {
            foreach ($product_tags as $tag) {
                $out[$tag->slug] = $tag->name;
            }
        }
        $this->product_tags = $out;
        return $out;
    }

    public static function get_product_types() {
//        return include plugin_dir_path(__FILE__) . 'data/data-allowed-product-types.php';
        
        $product_types = array();
        foreach ( wc_get_product_types() as $value => $label ) {
            $product_types[esc_attr( $value )] = esc_html( $label );
        }
        
        return array_merge($product_types, array('variation' => 'Product variations'));
    }

    public static function get_product_statuses() {
        $product_statuses = array('publish', 'private', 'draft', 'pending', 'future');
        return apply_filters('wt_iew_allowed_product_statuses', array_combine($product_statuses, $product_statuses));
    }

    public static function get_product_sort_columns() {    
//        $sort_columns = array('post_parent', 'ID', 'post_author', 'post_date', 'post_title', 'post_name', 'post_modified', 'menu_order', 'post_modified_gmt', 'rand', 'comment_count');
        $sort_columns = array('ID'=>'ID', 'name'=>'post_title', 'type'=>'Product Type', 'date'=>'post_date', 'modified'=>'post_modified');
        return apply_filters('wt_iew_allowed_product_sort_columns', $sort_columns);
    }

    public static function get_product_post_columns() {
        return include plugin_dir_path(__FILE__) . 'data/data-product-post-columns.php';
    }

    public function get_importer_post_columns($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        $colunm = include plugin_dir_path(__FILE__) . 'data/data/data-wf-reserved-fields-pair.php';
//        $colunm = array_map(function($vl){ return array('title'=>$vl, 'description'=>$vl); }, $arr); 
        return $colunm;
    }

    public function exporter_alter_mapping_enabled_fields($mapping_enabled_fields, $base, $form_data_mapping_enabled_fields) {
        if ($base == $this->module_base) {
            $mapping_enabled_fields = array();
            $mapping_enabled_fields['taxonomies'] = array(__('Taxonomies (cat/tags/shipping-class)'), 1);

            $mapping_enabled_fields['attributes'] = array(__('Attributes'), 1);

        }
        return $mapping_enabled_fields;
    }

    
    public function exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        
        foreach ($fields as $key => $value) {
            switch ($key) {
                case 'taxonomies':
                    $product_taxonomies = $this->wt_get_product_taxonomies();
                    foreach ($product_taxonomies as $taxonomy) {
                        if (strstr($taxonomy->name, 'pa_'))
                            continue; // Skip attributes                        
                        $fields[$key]['fields']['tax:' . $taxonomy->name] = 'tax:' . $taxonomy->name;
                    }
                    break;
                    


                case 'attributes':
                    $found_attributes = $this->wt_get_product_attributes();
                    
                    if(!empty($meta_attributes)){  // adding meta attributes
                        foreach ($meta_attributes as $attribute_value) {
                            $fields[$key]['fields']['meta:' . $attribute_value] = 'meta:' . $attribute_value;
                        }
                    }
                    
                    foreach ($found_attributes as $attribute) {
                        $fields[$key]['fields']['attribute:' . $attribute] = 'attribute:' . $attribute;
                        $fields[$key]['fields']['attribute_data:' . $attribute] = 'attribute_data:' . $attribute;
                        $fields[$key]['fields']['attribute_default:' . $attribute] = 'attribute_default:' . $attribute;
                    }
                    
                    break;


                default:
                    break;
            }
        }

        return $fields;
    }
    
    public function importer_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        
        $fields=$this->exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data);
        $out=array();
        foreach ($fields as $key => $value) 
        {
            $value['fields']=array_map(function($vl){ return array('title'=>$vl, 'description'=>$vl); }, $value['fields']);
            $out[$key]=$value;
        }
        return $out;
    }
    
    public function wt_get_product_taxonomies() {

        if (!empty($this->product_taxonomies)) {
            return $this->product_taxonomies;
        }
        $product_ptaxonomies = get_object_taxonomies('product', 'name');
        $product_vtaxonomies = get_object_taxonomies('product_variation', 'name');
        $product_taxonomies = array_merge($product_ptaxonomies, $product_vtaxonomies);

        $this->product_taxonomies = $product_taxonomies;
        return $this->product_taxonomies;
    }

    public function wt_get_found_product_meta() {

        if (!empty($this->found_product_meta)) {
            return $this->found_product_meta;
        }

        // Loop products and load meta data
        $found_product_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.

        $all_meta_keys = $this->wt_get_all_meta_keys();
        $csv_columns = self::get_product_post_columns();
        $exclude_hidden_meta_columns = $this->wt_get_exclude_hidden_meta_columns();
        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) == '_') || in_array($meta, $exclude_hidden_meta_columns) || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_product_meta[] = $meta;
        }

        $found_product_meta = array_diff($found_product_meta, array_keys($csv_columns));

        $this->found_product_meta = $found_product_meta;
        return $this->found_product_meta;
    }

    public function wt_get_found_product_hidden_meta() {

        if (!empty($this->found_product_hidden_meta)) {
            return $this->found_product_hidden_meta;
        }

        // Loop products and load meta data
        $found_product_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.

        $all_meta_keys = $this->wt_get_all_meta_keys();
        $csv_columns = self::get_product_post_columns();//$this->get_selected_column_names();
        $exclude_hidden_meta_columns = $this->wt_get_exclude_hidden_meta_columns();
        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) != '_') || in_array($meta, $exclude_hidden_meta_columns) || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_product_meta[] = $meta;
        }

        $found_product_meta = array_diff($found_product_meta, array_keys($csv_columns));

        $this->found_product_hidden_meta = $found_product_meta;
        return $this->found_product_hidden_meta;
    }

    public function wt_get_exclude_hidden_meta_columns() {

        if (!empty($this->exclude_hidden_meta_columns)) {
            return $this->exclude_hidden_meta_columns;
        }

        $exclude_hidden_meta_columns = include( plugin_dir_path(__FILE__) . 'data/data-wf-hidden-meta-columns.php' );

        $this->exclude_hidden_meta_columns = $exclude_hidden_meta_columns;
        return $this->exclude_hidden_meta_columns;
    }

    public function wt_get_all_meta_keys() {

        if (!empty($this->all_meta_keys)) {
            return $this->all_meta_keys;
        }

        $all_meta_pkeys = self::get_all_metakeys('product');
        $all_meta_vkeys = self::get_all_metakeys('product_variation');
        $all_meta_keys = array_merge($all_meta_pkeys, $all_meta_vkeys);
        $all_meta_keys = array_unique($all_meta_keys);

        $this->all_meta_keys = $all_meta_keys;
        return $this->all_meta_keys;
    }

    /**
     * Get a list of all the meta keys for a post type. This includes all public, private,
     * used, no-longer used etc. They will be sorted once fetched.
     */
    public static function get_all_metakeys($post_type = 'product') {
        global $wpdb;

        $meta = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )", $post_type
        ));

        sort($meta);

        return $meta;
    }

    public function set_selected_column_names($full_form_data) {   
        if (is_null($this->selected_column_names)) {
            if (isset($full_form_data['mapping_form_data']['mapping_selected_fields']) && !empty($full_form_data['mapping_form_data']['mapping_selected_fields'])) {
                $this->selected_column_names = $full_form_data['mapping_form_data']['mapping_selected_fields'];
            }
            if (isset($full_form_data['meta_step_form_data']['mapping_selected_fields']) && !empty($full_form_data['meta_step_form_data']['mapping_selected_fields'])) {
                $export_additional_columns = $full_form_data['meta_step_form_data']['mapping_selected_fields'];
                foreach ($export_additional_columns as $value) {
                    $this->selected_column_names = array_merge($this->selected_column_names, $value);
                }
            }
        }

        return $full_form_data;
    }

    public function get_selected_column_names() {
            
        return $this->selected_column_names;
    }

    public function wt_get_product_attributes() {
        if (!empty($this->product_attributes)) {
            return $this->product_attributes;
        }
        $found_pattributes = self::get_all_product_attributes('product');
        $found_vattributes = self::get_all_product_attributes('product_variation');
        $found_attributes = array_merge($found_pattributes, $found_vattributes);
        $found_attributes = array_unique($found_attributes);

        $this->product_attributes = $found_attributes;
        return $this->product_attributes;
    }

    /**
     * Get a list of all the product attributes for a post type.
     * These require a bit more digging into the values.
     */
    public static function get_all_product_attributes($post_type = 'product') {

        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'pending', 'private', 'draft' )
            AND pm.meta_key = '_product_attributes'", $post_type
        ));

        // Go through each result, and look at the attribute keys within them.
        $result = array();

        if (!empty($results)) {
            foreach ($results as $_product_attributes) {
                $attributes = maybe_unserialize(maybe_unserialize($_product_attributes));
                if (!empty($attributes) && is_array($attributes)) {
                    foreach ($attributes as $key => $attribute) {
                        if (!$key) {
                            continue;
                        }
                        if (!strstr($key, 'pa_')) {
                            if (empty($attribute['name'])) {
                                continue;
                            }
                            $key = $attribute['name'];
                        }

                        $result[$key] = $key;
                    }
                }
            }
        }

        sort($result);

        return $result;
    }

    public function exporter_alter_mapping_fields($fields, $base, $mapping_form_data) {
        if ($base == $this->module_base) {
            $fields = self::get_product_post_columns();
        }
        return $fields;
    }

    public function exporter_alter_advanced_fields($fields, $base, $advanced_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }
        $out = array();

        foreach ($fields as $fieldk => $fieldv) {
            $out[$fieldk] = $fieldv;
        }



        return $out;
    }
    
    public function importer_alter_advanced_fields($fields, $base, $advanced_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }
        $out = array(); 
        
        $out['skip_new'] = array(
            'label' => __("Update only"),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes'),
                '0' => __('No')
            ),
            'value' => '0',
            'field_name' => 'skip_new',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('The store is updated with the data from the input file only for matching/existing records from the file.'),
                    'condition'=>array(
                        array('field'=>'wt_iew_skip_new', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> __('The entire data from the input file is processed for an update or insert as the case maybe.'),
                    'condition'=>array(
                        array('field'=>'wt_iew_skip_new', 'value'=>0)
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_skip_new',
            )
        );    
        
        $out['merge_with'] = array(
            'label' => __("Match products by their"),
            'type' => 'radio',
            'radio_fields' => array(
                'id' => __('ID'),
                'sku' => __('SKU'),             
            ),
            'value' => 'id',
            'field_name' => 'merge_with',
            'help_text' => __('The products are either looked up based on their ID or SKU as per the selection.'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('If the post ID of the product being imported exists already(for any of the other post types like coupon, order, user, pages, media etc) skip the product from being updated into the store.'),
                    'condition'=>array(
                        array('field'=>'wt_iew_merge_with', 'value'=>'id'),
                        'AND',
                        array('field'=>'wt_iew_skip_new', 'value'=>1)
                    )
                )
            )
        );
        
        $out['found_action_merge'] = array(
            'label' => __("If product exists in the store"),
            'type' => 'radio',
            'radio_fields' => array(
                'skip' => __('Skip'),
                'update' => __('Update'),                
            ),
            'value' => 'skip',
            'field_name' => 'found_action',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Retains the product in the store as is and skips the matching product from the input file.'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Update product as per data from the input file'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'update')
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_found_action'
            )
        );       
        
        $out['merge_empty_cells'] = array(
            'label' => __("Update even if empty values"),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes'),
                '0' => __('No')
            ),
            'value' => '0',
            'field_name' => 'merge_empty_cells',
            'help_text' => __('Updates the product data respectively even if some of the columns in the input file contains empty value.'),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_found_action',
                'val'=>'update',
            )
        );

        
       
        foreach ($fields as $fieldk => $fieldv) {
            $out[$fieldk] = $fieldv;
        }
        return $out;
    }

    /**
     *  Customize the items in filter export page
     */
    public function exporter_alter_filter_fields($fields, $base, $filter_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }

        /* altering help text of default fields */
	$fields['limit']['label']=__('Total number of products to export'); 
	$fields['limit']['help_text']=__('Exports specified number of products. e.g. Entering 500 with a skip count of 10 will export products from 11th to 510th position.');
	$fields['offset']['label']=__('Skip first <i>n</i> orders');
	$fields['offset']['help_text']=__('Skips specified number of products from the beginning. e.g. Enter 10 to skip first 10 products from export.');

        
        $fields['product'] = array(
            'label' => __('Products', 'wt-import-export-for-woo'),
            'placeholder' => __('All products', 'wt-import-export-for-woo'),
            'field_name' => 'product',
            'sele_vals' => array(),
            'help_text' => __('Export specific products. Keyin the product names to export to export multiple products.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-product-search',
            'validation_rule' => array('type'=>'text_arr')
        );
        
        $fields['exclude_product'] = array(
            'label' => __('Exclude products', 'wt-import-export-for-woo'),
            'placeholder' => __('Exclude products', 'wt-import-export-for-woo'),
            'field_name' => 'exclude_product',
            'sele_vals' => array(),
            'help_text' => __('Use this if you need to exclude a specific or multiple products from your export list.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-product-search',
            'validation_rule' => array('type'=>'text_arr')
        );

        $fields['product_categories'] = array(
            'label' => __('Product categories', 'wt-import-export-for-woo'),
            'placeholder' => __('Any category', 'wt-import-export-for-woo'),
            'field_name' => 'product_categories',
            'sele_vals' => $this->get_product_categories(),
            'help_text' => __('Export products belonging to a particular or from multiple categories. Just select the respective categories.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'text_arr')
        );

        $fields['product_tags'] = array(
            'label' => __('Product tags', 'wt-import-export-for-woo'),
            'placeholder' => __('Any tag', 'wt-import-export-for-woo'),
            'field_name' => 'product_tags',
            'sele_vals' => $this->get_product_tags(),
            'help_text' => __('Enter the product tags to export only the respective products that have been tagged accordingly.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'text_arr')
        );

        $fields['product_status'] = array(
            'label' => __('Product status', 'wt-import-export-for-woo'),
            'placeholder' => __('Any status', 'wt-import-export-for-woo'),
            'field_name' => 'product_status',
            'sele_vals' => self::get_product_statuses(),
            'help_text' => __('Filter products by their status.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'text_arr')
        );

 

        return $fields;
    }

    /**
     * Get File name by url
     * @param string $file_url URL of the file.
     * @return string the base name of the given URL (File name).
     */
    public static function xa_wc_get_filename_from_url($file_url) {
        $parts = parse_url($file_url);
        if (isset($parts['path'])) {
            return basename($parts['path']);
        }
    }

    /**
     * Get info like language code, parent product ID etc by product id.
     * @param int Product ID.
     * @return array/false.
     */
    public static function wt_get_wpml_original_post_language_info($element_id) {
        $get_language_args = array('element_id' => $element_id, 'element_type' => 'post_product');
        $original_post_language_info = apply_filters('wpml_element_language_details', null, $get_language_args);
        return $original_post_language_info;
    }

    public static function wt_get_product_id_by_sku($sku) {
        global $wpdb;
        $post_exists_sku = $wpdb->get_var($wpdb->prepare("
	    		SELECT $wpdb->posts.ID
	    		FROM $wpdb->posts
	    		LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )
	    		WHERE $wpdb->posts.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )
	    		AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = '%s'
	    		", $sku));
        if ($post_exists_sku) {
            return $post_exists_sku;
        }
        return false;
    }

    /**
     * To strip the specific string from the array key as well as value.
     * @param array $array.
     * @param string $data.
     * @return array.
     */
    public static function wt_array_walk($array, $data) {
        $new_array = array();
        foreach ($array as $key => $value) {
            $new_array[str_replace($data, '', $key)] = str_replace($data, '', $value);
        }
        return $new_array;
    }
    
    public function get_item_by_id($id) {
        $post['edit_url']=get_edit_post_link($id);
        $post['title'] = get_the_title($id);
        return $post; 
    }

}
}

new Wt_Import_Export_For_Woo_Basic_Product();
