<?php

if (!defined('WPINC')) {
    exit;
}

if(!class_exists('Wt_Import_Export_For_Woo_Basic_Product_Export')){
class Wt_Import_Export_For_Woo_Basic_Product_Export {

    public $parent_module = null;

    public function __construct($parent_object) {

        $this->parent_module = $parent_object;
    }

    public function prepare_header() {

        $export_columns = $this->parent_module->get_selected_column_names();       

        return apply_filters('hf_alter_product_export_csv_columns', $export_columns);
    }

    /**
     * Prepare data that will be exported.
     */
    public function prepare_data_to_export($form_data, $batch_offset,$step) {

        $include_products = !empty($form_data['filter_form_data']['wt_iew_product']) ? $form_data['filter_form_data']['wt_iew_product'] : '';
        $exclude_products = !empty($form_data['filter_form_data']['wt_iew_exclude_product']) ? $form_data['filter_form_data']['wt_iew_exclude_product'] : '';

        $prod_categories = !empty($form_data['filter_form_data']['wt_iew_product_categories']) ? $form_data['filter_form_data']['wt_iew_product_categories'] : array();
        $prod_tags = !empty($form_data['filter_form_data']['wt_iew_product_tags']) ? $form_data['filter_form_data']['wt_iew_product_tags'] : array();
        $prod_types = !empty($form_data['filter_form_data']['wt_iew_product_types']) ? $form_data['filter_form_data']['wt_iew_product_types'] : array();
        $prod_status = !empty($form_data['filter_form_data']['wt_iew_product_status']) ? $form_data['filter_form_data']['wt_iew_product_status'] : array();
        
        $export_sortby = !empty($form_data['filter_form_data']['wt_iew_sort_columns']) ? $form_data['filter_form_data']['wt_iew_sort_columns'] : 'ID';
        $export_sort_order = !empty($form_data['filter_form_data']['wt_iew_order_by']) ? $form_data['filter_form_data']['wt_iew_order_by'] : 'ASC';

        $export_limit = !empty($form_data['filter_form_data']['wt_iew_limit']) ? intval($form_data['filter_form_data']['wt_iew_limit']) : 999999999; //user limit
        $current_offset = !empty($form_data['filter_form_data']['wt_iew_offset']) ? intval($form_data['filter_form_data']['wt_iew_offset']) : 0; //user offset

        $batch_count = !empty($form_data['advanced_form_data']['wt_iew_batch_count']) ? $form_data['advanced_form_data']['wt_iew_batch_count'] : Wt_Import_Export_For_Woo_Basic_Common_Helper::get_advanced_settings('default_export_batch');
        $batch_count = apply_filters('wt_woocommerce_csv_export_limit_per_request', $batch_count); //ajax batch limit

        $this->export_children_sku = (!empty($form_data['advanced_form_data']['wt_iew_export_children_sku'] ) && $form_data['advanced_form_data']['wt_iew_export_children_sku'] == 'Yes') ? true : false;
        
        $this->export_shortcodes = (!empty($form_data['advanced_form_data']['wt_iew_export_shortcode_tohtml']) && $form_data['advanced_form_data']['wt_iew_export_shortcode_tohtml'] == 'Yes') ? true : false;

        $this->export_images_zip = (!empty($form_data['advanced_form_data']['wt_iew_image_export']) && $form_data['advanced_form_data']['wt_iew_image_export'] == 'Yes') ? true : false;
                
        $real_offset = ($current_offset + $batch_offset);

        if($batch_count<=$export_limit)
        {
            if(($batch_offset+$batch_count)>$export_limit) //last offset
            {
                $limit=$export_limit-$batch_offset;
            }else
            {
                $limit=$batch_count;
            }
        }else
        {
            $limit=$export_limit;
        }
        
        $product_array = array();
        if ($batch_offset < $export_limit)
        {
            $args = array(
                'status' => array('publish', 'private', 'draft', 'pending', 'future'),
                'type' => array('simple','grouped','external'),
                'limit' => $limit,
                'offset' => $real_offset,
                'orderby' => $export_sortby,
                'order' => $export_sort_order,                                
//                'return' => 'objects',
                'paginate' => true,
            );
            
//            if(1){ //if(is_woocommerce_prior_to_basic('2.7.0')){
                $args['return']   = 'ids';
//            }

            if (!empty($prod_status)) {
                $args['status'] = $prod_status;
            } 
                
            if (!empty($prod_types)) {
                $args['type'] = $prod_types;
            }

            if (!empty($prod_categories)) {
                $args['category'] = $prod_categories;
            }

            if (!empty($prod_tags)) {
                $args['tag'] = $prod_tags;
            }

            if (!empty($include_products)) {
                $args['include'] = $include_products;
            }

            if (!empty($exclude_products)) {
                $args['exclude'] = $exclude_products;
            }

            $args = apply_filters("woocommerce_csv_product_export_args", $args);
            $products = wc_get_products($args); 
            
            $total_products=0;
            if($batch_offset==0) //first batch
            {
                $total_item_args=$args;
                $total_item_args['limit']=$export_limit; //user given limit
                $total_item_args['offset']=$current_offset; //user given offset
                $total_products_count = wc_get_products($total_item_args);
                $total_products=count($total_products_count->products);
            }

            if('export_image' == $step){
                $products_ids = $products;
            }else{
                $products_ids = $products->products;
            } 
                             

                foreach ($products_ids as $key => $product_id) {  
                    $product = wc_get_product($product_id); 
                        
                    if ($product->is_type('variable') || $product->has_child()) {
                        continue;
                    }else{
                        $product_array[] = $this->generate_row_data_wc_lower($product);
                    }
                        
                }
            
        }                
        return array(
            'total' => $total_products,
            'data' => $product_array,
        );

    }        

    protected function generate_row_data_wc_lower($product_object) {

        $export_columns = $this->parent_module->get_selected_column_names();
        
        $post_columns = Wt_Import_Export_For_Woo_Basic_Product::get_product_post_columns();
        $standard_meta_columns = array_keys(array_slice($post_columns, 12));

        $product = get_post($product_object->get_id());

        $csv_columns = $export_columns; //Wt_Import_Export_For_Woo_Basic_Product::wt_array_walk($export_columns,'meta:'); // Remove string 'meta:' from keys and values, YOAST support

        $export_columns = !empty($csv_columns) ? $csv_columns : array();
        
        $row = array();

        if ($product->post_parent == 0)
            $product->post_parent = '';

        // Pre-process data
        $meta_data = get_post_custom($product->ID);
        
        $product->meta = new stdClass;
        $product->attributes = new stdClass;
        // Meta data

        foreach ($meta_data as $meta => $value) {

            if (!$meta) {
                continue;
            }

            $meta_value = maybe_unserialize(maybe_unserialize($value[0]));
            
            if (is_array($meta_value)) {
                $meta_value = json_encode($meta_value);
            }

            if (strstr($meta, 'attribute_pa_')) {
                if ($meta_value) {
                    $get_name_by_slug = get_term_by('slug', $meta_value, str_replace('attribute_', '', $meta));
                    if ($get_name_by_slug) {
                        $product->meta->$meta = self::format_export_meta($get_name_by_slug->name, $meta);
                    } else {
                        $product->meta->$meta = self::format_export_meta($meta_value, $meta);
                    }
                } else {
                    $product->meta->$meta = self::format_export_meta($meta_value, $meta);
                }
            } else {
                $clean_meta_key = ltrim($meta, '_');
                if(in_array($clean_meta_key, $standard_meta_columns)){
                    $meta = $clean_meta_key;
                }
                $product->meta->$meta = self::format_export_meta($meta_value, $meta);
            }
        }
        // Product attributes
        if (isset($meta_data['_product_attributes'][0])) {

            $attributes = maybe_unserialize(maybe_unserialize($meta_data['_product_attributes'][0]));
            
            if (!empty($attributes) && is_array($attributes)) {
                foreach ($attributes as $key => $attribute) {
                    if (!$key) {
                        continue;
                    }

                    $key_to_find_default_attribute = $key;
                    if ($attribute['is_taxonomy'] == 1) {
                        $terms = wp_get_post_terms($product->ID, $key, array("fields" => "names"));
                        if (!is_wp_error($terms)) {
                            $attribute_value = implode('|', $terms);
                        } else {
                            $attribute_value = '';
                        }
                    } else {
                        if (empty($attribute['name'])) {
                            continue;
                        }
                        $key = $attribute['name'];
                        $attribute_value = $attribute['value'];
                    }

                    if (!isset($attribute['position'])) {
                        $attribute['position'] = 0;
                    }
                    if (!isset($attribute['is_visible'])) {
                        $attribute['is_visible'] = 0;
                    }
                    if (!isset($attribute['is_variation'])) {
                        $attribute['is_variation'] = 0;
                    }

                    $attribute_data = $attribute['position'] . '|' . $attribute['is_visible'] . '|' . $attribute['is_variation'];
                    $_default_attributes = isset($meta_data['_default_attributes'][0]) ? maybe_unserialize(maybe_unserialize($meta_data['_default_attributes'][0])) : '';

                    if (is_array($_default_attributes)) {
                        $default_attribute = isset($_default_attributes[$key_to_find_default_attribute]) ? $_default_attributes[$key_to_find_default_attribute] : '';                        
                        $term = get_term_by('slug', $default_attribute, $key_to_find_default_attribute);
                        $_default_attribute = (!is_wp_error($term) && !empty($term) ? $term->name : $default_attribute );
                        
                        
                    } else {
                        $_default_attribute = '';
                    }
                    
                    $product->attributes->$key = array(
                        'value' => $attribute_value,
                        'data' => $attribute_data,
                        'default' => $_default_attribute
                    );
                }
            }
        }


        foreach ($csv_columns as $column => $value) {

            if (!$export_columns || in_array($value, $export_columns) || in_array($column, $export_columns)) {
                if ('_regular_price' == $column && empty($product->meta->$column)) {
                    $column = '_price';
                }
                if (!is_woocommerce_prior_to_basic('2.7')) {
                    if ('_visibility' == $column) {
                        $product_terms = get_the_terms($product->ID, 'product_visibility');
                        if (!empty($product_terms)) {
                            if (!is_wp_error($product_terms)) {
                                $term_slug = '';
                                foreach ($product_terms as $i => $term) {
                                    $term_slug .= $term->slug . (isset($product_terms[$i + 1]) ? '|' : '');
                                }
                                $row[$column] = $term_slug;
                            }
                        } else {
                            $row[$column] = '';
                        }
                        continue;
                    }
                }


                if ('Parent' == $column) {
                    if ($product->post_parent) {
                        $post_parent_title = get_the_title($product->post_parent);
                        if ($post_parent_title) {
                            $row[$column] = self::format_data($post_parent_title);
                        } else {
                            $row[$column] = '';
                        }
                    } else {
                        $row[$column] = '';
                    }
                    continue;
                }

                if ('parent_sku' == $column) {
                    if ($product->post_parent) {
                        $row[$column] = get_post_meta($product->post_parent, '_sku', true);
                    } else {
                        $row[$column] = '';
                    }
                    continue;
                }


                // Export images/gallery
                if ('images' == $column) {

                    $export_image_metadata = apply_filters('hf_export_image_metadata_flag', TRUE); //filter for disable export image meta datas such as alt,title,content,caption...
                    $image_file_names = array();

                    // Featured image
                    if (( $featured_image_id = get_post_thumbnail_id($product->ID))) {
                        $image_object = get_post($featured_image_id);
                        $img_url = wp_get_attachment_image_src($featured_image_id, 'full');

                        $image_meta = '';
                        if ($image_object && $export_image_metadata) {
                            $image_metadata = get_post_meta($featured_image_id);
                            $image_meta = " ! alt : " . ( isset($image_metadata['_wp_attachment_image_alt'][0]) ? $image_metadata['_wp_attachment_image_alt'][0] : '' ) . " ! title : " . $image_object->post_title . " ! desc : " . $image_object->post_content . " ! caption : " . $image_object->post_excerpt;
                        }

                        if ($image_object && $image_object->guid) {
                            $temp_images_export_to_csv = ($this->export_images_zip ? basename($img_url[0]) : $img_url[0]) . ($export_image_metadata ? $image_meta : '');
                        }
                        
                        if (!empty($temp_images_export_to_csv)) {
                            $image_file_names[] = $temp_images_export_to_csv;
                        }
                    }

                    // Images
                    $images = isset($meta_data['_product_image_gallery'][0]) ? explode(',', maybe_unserialize(maybe_unserialize($meta_data['_product_image_gallery'][0]))) : false;
                    $results = array();
                    if ($images) {
                        foreach ($images as $image_id) {
                            if ($featured_image_id == $image_id) {
                                continue;
                            }
                            $temp_gallery_images_export_to_csv = '';
                            $gallery_image_meta = '';
                            $gallery_image_object = get_post($image_id);
                            $gallery_img_url = wp_get_attachment_image_src($image_id, 'full');

                            if ($gallery_image_object && $export_image_metadata) {
                                $gallery_image_metadata = get_post_meta($image_id);
                                $gallery_image_meta = " ! alt : " . ( isset($gallery_image_metadata['_wp_attachment_image_alt'][0]) ? $gallery_image_metadata['_wp_attachment_image_alt'][0] : '' ) . " ! title : " . $gallery_image_object->post_title . " ! desc : " . $gallery_image_object->post_content . " ! caption : " . $gallery_image_object->post_excerpt;
                            }

                            if ($gallery_image_object && $gallery_image_object->guid) {
                                $temp_gallery_images_export_to_csv = ($this->export_images_zip ? basename($gallery_img_url[0]) : $gallery_img_url[0]) . ($export_image_metadata ? $gallery_image_meta : '');
                            }
                            if (!empty($temp_gallery_images_export_to_csv)) {
                                $image_file_names[] = $temp_gallery_images_export_to_csv;
                            }
                        }
                    }


                    if (!empty($image_file_names)) {
                        $row[$column] = implode(' | ', $image_file_names);
                    } else {
                        $row[$column] = '';
                    }
                    continue;
                }


                // Downloadable files
                if ('file_paths' == $column || 'downloadable_files' == $column) {
                    $file_paths_to_export = array();
                    if (!function_exists('wc_get_filename_from_url')) {
                        $file_paths = maybe_unserialize(maybe_unserialize($meta_data['_file_paths'][0]));

                        if ($file_paths) {
                            foreach ($file_paths as $file_path) {
                                $file_paths_to_export[] = $file_path;
                            }
                        }

                        $file_paths_to_export = implode(' | ', $file_paths_to_export);
                        $row[] = self::format_data($file_paths_to_export);
                    } elseif (isset($meta_data['_downloadable_files'][0])) {
                        $file_paths = maybe_unserialize(maybe_unserialize($meta_data['_downloadable_files'][0]));

                        if (is_array($file_paths) || is_object($file_paths)) {
                            foreach ($file_paths as $file_path) {
                                $file_paths_to_export[] = (!empty($file_path['name']) ? $file_path['name'] : Wt_Import_Export_For_Woo_Basic_Common_Helper::wt_wc_get_filename_from_url($file_path['file']) ) . '::' . $file_path['file'];
                            }
                        }
                        $file_paths_to_export = implode(' | ', $file_paths_to_export);
                    }
                    if (!empty($file_paths_to_export)) {
                        $row[$column] = !empty($file_paths_to_export) ? self::format_data($file_paths_to_export) : '';
                    } else {
                        $row[$column] = '';
                    }
                    continue;
                }


                // Export taxonomies
//                if ( 'taxonomies' == $column ) {
                if ('tax:' == substr($column, 0, 4)) {

                    $taxonomy_name = substr($column, 4);

                    if (is_taxonomy_hierarchical($taxonomy_name)) {
                        $terms = wp_get_post_terms($product->ID, $taxonomy_name, array("fields" => "all"));
                        $formatted_terms = array();

                        foreach ($terms as $term) {
                            $ancestors = array_reverse(get_ancestors($term->term_id, $taxonomy_name));
                            $formatted_term = array();

                            foreach ($ancestors as $ancestor)
                                $formatted_term[] = get_term($ancestor, $taxonomy_name)->name;

                            $formatted_term[] = $term->name;

                            $formatted_terms[] = implode(' > ', $formatted_term);
                        }

                        $row[$column] = self::format_data(implode('|', $formatted_terms));
                    } else {
                        $terms = wp_get_post_terms($product->ID, $taxonomy_name, array("fields" => "names"));

                        $row[$column] = self::format_data(implode('|', $terms));
                    }
                    continue;
                }

                // Export meta data                
//                if ( 'meta' == $column ) {
                if ('meta:' == substr($column, 0, 5)) {

                    $product_meta = substr($column, 5);

                    if (isset($product->meta->$product_meta)) {
                        $row[$column] = self::format_data($product->meta->$product_meta);
                    } else {
                        $row[$column] = '';
                    }

                    continue;
                }

                // Find and export attributes
//                if ('attributes' == $column ) {
                if (strstr($column, 'attribute')) {
                    if ('attribute:' == substr($column, 0, 10)) {
                        $attribute = substr($column, 10);
                        if (isset($product->attributes) && isset($product->attributes->$attribute)) {
                            $values = $product->attributes->$attribute;
                            $row[$column] = self::format_data($values['value']);
                        } else {
                            $row[$column] = '';
                        }
                        continue;
                    }


                    if ('attribute_data:' == substr($column, 0, 15)) {
                        $attribute = substr($column, 15);
                        if (isset($product->attributes) && isset($product->attributes->$attribute)) {
                            $values = $product->attributes->$attribute;
                            $row[$column] = self::format_data($values['data']);
                        } else {
                            $row[$column] = '';
                        }
                        continue;
                    }

                    if ('attribute_default:' == substr($column, 0, 18)) {
                        $attribute = substr($column, 18);
                        if (isset($product->attributes) && isset($product->attributes->$attribute)) {
                            $values = $product->attributes->$attribute;
                            $row[$column] = self::format_data($values['default']);
                        } else {
                            $row[$column] = '';
                        }
                        continue;
                    }
                }
                // WF: Adding product permalink.
                if ('product_page_url' == $column) {
                    $product_page_url = '';
                    if (!empty($product->ID)) {
                        $product_page_url = get_permalink($product->ID);
                    }
                    if (!empty($product->post_parent)) {
                        $product_page_url = get_permalink($product->post_parent);
                    }
                    $row[$column] = !empty($product_page_url) ? $product_page_url : '';
                    continue;
                }


                /**
                 * WPML
                 */
                if (apply_filters('wpml_setting', false, 'setup_complete')) {
                    if (in_array($column, array('wpml:language_code', 'wpml:original_product_id', 'wpml:original_product_sku'))) {
                        if ('wpml:language_code' == $column) {
                            $original_post_language_info = Wt_Import_Export_For_Woo_Basic_Common_Helper::wt_get_wpml_original_post_language_info($product->ID);
                            $row[$column] = (isset($original_post_language_info->language_code) && !empty($original_post_language_info->language_code) ? $original_post_language_info->language_code : '');
                            continue;
                        }

                        /*
                         * To get the ID of the original product post 
                         * https://wpml.org/forums/topic/translated-product-get-id-of-original-lang-for-custom-fields/             
                         */

                        global $sitepress;
                        $original_product_id = icl_object_id($product->ID, 'product', false, $sitepress->get_default_language());
                        if ('wpml:original_product_id' == $column) {
                            $row[$column] = ($original_product_id ? $original_product_id : '');
                            continue;
                        }
                        if ('wpml:original_product_sku' == $column) {
                            $sku = get_post_meta($original_product_id, '_sku', true);
                            $row[$column] = ($sku ? $sku : '');
                            continue;
                        }
                    }
                }


                // handling default meta and other columns        
                if (isset($product->meta->$column)) {
                    if (in_array($column, array('_children','_upsell_ids','_crosssell_ids'))) {                           
                        if ($this->export_children_sku) {
                            $children_sku = '';
                            $children_id_array = str_replace('"', '', explode('|', trim($product->meta->$column, '[' . ']')));
                            
                            if (!empty($children_id_array) && $children_id_array[0] != '""') {
                                foreach ($children_id_array as $children_id_array_key => $children_id) {                                    
                                    $children_sku = !empty($children_sku) ? "{$children_sku}|" . get_post_meta($children_id, '_sku', TRUE) : get_post_meta($children_id, '_sku', TRUE);
                                }                                                                
                            }
                            $row[$column] = !empty($children_sku) ? $children_sku : '';
                        } else {
                            $row[$column] = str_replace('"', '', implode('|', explode(',', trim($product->meta->$column, '[' . ']'))));
                        }                        
                    } elseif ('_stock_status' == $column) {
                        $stock_status = self::format_data($product->meta->$column);
                        $product_type = ( WC()->version < '3.0' ) ? $product_object->product_type : $product_object->get_type();
                        $row[$column] = !empty($stock_status) ? $stock_status : ( ( 'variable' == $product_type || 'variable-subscription' == $product_type ) ? '' : 'instock' );
                    } elseif(('_sku' == $column)) {
                        $row[$column] = $product->meta->$column;
                    }else{
                        $row[$column] = self::format_data($product->meta->$column);
                    }
                } elseif (isset($product->$column) && !is_array($product->$column)) {
                    if ($this->export_shortcodes && ( 'post_content' == $column || 'post_excerpt' == $column )) {
                        //Convert Shortcodes to html for Description and Short Description
                        $row[$column] = do_shortcode($product->$column);
                    } elseif ('post_title' === $column) {
                        $row[$column] = sanitize_text_field($product->$column);
                    } else {
                        $row[$column] = self::format_data($product->$column);
                    }
                } else {
                    $row[$column] = '';
                }
            }
        }

        return apply_filters('wt_batch_product_export_row_data', $row, $product);
    }


    /**
     * Format the data if required
     * @param  string $meta_value
     * @param  string $meta name of meta key
     * @return string
     */
    public static function format_export_meta($meta_value, $meta) {
        switch ($meta) {
            case '_sale_price_dates_from' :
            case '_sale_price_dates_to' :
            case 'sale_price_dates_from' :
            case 'sale_price_dates_to' :                
                return $meta_value ? date('Y-m-d', $meta_value) : '';
                break;
            case '_upsell_ids' :
            case '_crosssell_ids' :
            case 'upsell_ids' :
            case 'crosssell_ids' :                
                return implode('|', array_filter((array) json_decode($meta_value)));
                break;
            default :
                return $meta_value;
                break;
        }
    }

    public static function format_data($data) {
        if (!is_array($data))            
            $data = (string) urldecode($data);
//        $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);        
        $use_mb = function_exists('mb_detect_encoding');
        $enc = '';
        if ($use_mb) {
            $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);
        }
        $data = ( $enc == 'UTF-8' ) ? $data : utf8_encode($data);

        return $data;
    }
}
}
