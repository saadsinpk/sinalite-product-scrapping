<?php /*
Plugin Name: Sinalite Scrapping
Plugin URI: http://sitechno.com
description: >- Sinalite Scrapping Products
Version: 1
Author: Muhammad Saad
Author URI: http://sidtechno.com
*/

// add_action('woocommerce_product_options_general_product_data', 'record');
// // Save Fields
// add_action('woocommerce_process_product_meta', 'record');


function get_from_api($url){
   $curl = curl_init($url);
   curl_setopt($curl, CURLOPT_URL, $url);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

   //for debug only!
   curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
   curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

   $resp = curl_exec($curl);
   curl_close($curl);
   if(!empty($resp)) {
      $resp = json_decode($resp);
   } else {
      $resp = array();
   }
   return $resp;
}
function update_variant_post_woo_id($get_products_variant_value, $variation_product_id) {
   global $wpdb;
   $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `variant_id`=$variation_product_id WHERE `id` = $get_products_variant_value"); 
}
function update_term_to_db($post_id, $option, $attribute_key, $return_term){
   global $wpdb;
   $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant SET `attri_id`=$return_term WHERE `woo_prod_id` = $post_id AND `group` = '".$attribute_key."' AND `name` = '".$option."'"); 
}
function createAttribute(string $attributeName, string $attributeSlug): ?\stdClass {
    delete_transient('wc_attribute_taxonomies');
    \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

    $attributeLabels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
    $attributeWCName = array_search($attributeSlug, $attributeLabels, TRUE);

    if (! $attributeWCName) {
        $attributeWCName = wc_sanitize_taxonomy_name($attributeSlug);
    }

    $attributeId = wc_attribute_taxonomy_id_by_name($attributeWCName);
    if (! $attributeId) {
        $taxonomyName = wc_attribute_taxonomy_name($attributeWCName);
        unregister_taxonomy($taxonomyName);
        $attributeId = wc_create_attribute(array(
            'name' => $attributeName,
            'slug' => $attributeSlug,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => 0,
        ));

        register_taxonomy($taxonomyName, apply_filters('woocommerce_taxonomy_objects_' . $taxonomyName, array(
            'product'
        )), apply_filters('woocommerce_taxonomy_args_' . $taxonomyName, array(
            'labels' => array(
                'name' => $attributeSlug,
            ),
            'hierarchical' => FALSE,
            'show_ui' => FALSE,
            'query_var' => TRUE,
            'rewrite' => FALSE,
        )));
    }

    return wc_get_attribute($attributeId);
}
function createTerm(string $termName, string $termSlug, string $taxonomy, int $order = 0): ?\WP_Term {
    $taxonomy = wc_attribute_taxonomy_name($taxonomy);

    if (! $term = get_term_by('slug', $termSlug, $taxonomy)) {
        $term = wp_insert_term($termName, $taxonomy, array(
            'slug' => $termSlug,
        ));
        $term = get_term_by('id', $term['term_id'], $taxonomy);
        if ($term) {
            update_term_meta($term->term_id, 'order', $order);
        }
    }

    return $term;
}
function fetch_product_from_api($product_id){
   $url = "https://liveapi.sinalite.com/product/".$product_id."/6";
   return get_from_api($url);
}
function fetch_variant_from_product($product_id)
{
   $url = "https://liveapi.sinalite.com/variants/".$product_id;
   return get_from_api($url);
}
function get_list_of_prdocuts_from_api() {
   $url = "https://liveapi.sinalite.com/product";
   return get_from_api($url);
}

add_action( 'init', 'update_products_from_sinalite' );

function insert_attribute_to_wp($post_id, $get_products_value, $resp_value){
   global $wpdb;
    $checkIfExistsVariant = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sinalite_products_variant WHERE `prod_id` = $get_products_value->prod_id AND `variant_id` = $resp_value->id AND `group` = '".$resp_value->group."' AND `name` = '".$resp_value->name."'");
    if ($checkIfExistsVariant == NULL) {
      $wpdb->query("INSERT INTO {$wpdb->prefix}sinalite_products_variant (`woo_prod_id`, `prod_id`, `variant_id`, `group`, `name`, `attri_id`) VALUES($post_id, $get_products_value->prod_id, $resp_value->id, '$resp_value->group', '$resp_value->name', '')"); 
   } else {
      $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant SET `update_prod`=0, `woo_prod_id` = $post_id, `attri_id` = '0'  WHERE `prod_id` = $get_products_value->prod_id AND `variant_id` = $resp_value->id AND `group` = '".$resp_value->group."' AND `name` = '".$resp_value->name."'"); 
   }
}
function update_products_from_sinalite() {
   global $wpdb;
   if( isset( $_GET['clearSinaliteProduct'] ) ) {
      $set_7_day_time = strtotime('-7 day');
       $myproducts = get_posts( array('post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1,) );
       $myproducts_variation = get_posts( array('post_type' => 'product_variation', 'post_status' => 'publish', 'numberposts' => 4000,) );

       foreach ( $myproducts as $myproduct ) {
           wp_delete_post( $myproduct->ID, true); // Set to False if you want to send them to Trash.
       } 

       foreach ( $myproducts_variation as $myproduct_variation ) {
           wp_delete_post( $myproduct_variation->ID, true); // Set to False if you want to send them to Trash.
       } 

      update_option( 'senalite_prod_update', $set_7_day_time);
      $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products");
      $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant");
      $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant_price");
      wp_redirect('?page=sinalite-setting');
      exit;
   }
   if( isset( $_GET['update_products_sinalite'] ) ) {
      ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
      // $product_attributes = get_post_meta( 14442, '_product_attributes', true);
      // echo "<pre>";
      // print_r($product_attributes);
      // echo "</pre>";
      // $product_attributes = get_post_meta( 14442, '', true);
      // echo "<pre>";
      // print_r($product_attributes);
      // echo "</pre>";
      // exit;

      // exit;
      // $get_products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products_variant WHERE update_prod = 0 LIMIT 1", OBJECT );
      // foreach ($get_products as $get_products_key => $get_products_value) {

      // }

      // exit;
      //Create main product

      $resp = get_list_of_prdocuts_from_api();
      $db_time = get_option( 'senalite_prod_update' );
      update_option( 'senalite_last_cron_run', time());
      
      if(empty($db_time)) {
         $check_7_day_time = time();
      } else {
         $check_7_day_time = strtotime('+7 day', $db_time);
      }
      if(time() >= $check_7_day_time) {
         foreach ($resp as $resp_key => $resp_value) {
            update_option( 'senalite_prod_update', time());
             $checkIfExists = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sinalite_products WHERE prod_id = $resp_value->id");

             if ($checkIfExists == NULL) {
               $wpdb->query("INSERT INTO {$wpdb->prefix}sinalite_products (prod_id, prod_sku, prod_name, prod_category, prod_enabled) VALUES($resp_value->id, '$resp_value->sku', '$resp_value->name', '$resp_value->category', '$resp_value->enabled')"); 
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant SET `attri_id`=0 WHERE prod_id = $resp_value->id"); 
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `variant_id`=0 WHERE prod_id = $resp_value->id"); 
            } else {
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products SET `update_prod`=0 WHERE prod_id = $resp_value->id"); 
            }
         }
         $get_products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products WHERE update_prod = 1", OBJECT );

          foreach ( $get_products as $get_product ) {
            if($get_product->woo_prod_id > 0) {
               wp_delete_post( $get_product->woo_prod_id, true); // Set to False if you want to send them to Trash.
            }
            $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products WHERE `id`= $get_product->id");
          } 
         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant");
         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant_price");
      }
      $get_products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products WHERE update_prod = 0 LIMIT 1", OBJECT );
      foreach ($get_products as $get_products_key => $get_products_value) {
         $term = get_term_by('name', $get_products_value->prod_category, 'product_cat');
         if(!isset($term->term_id)) {
            $term = wp_insert_term(
              $get_products_value->prod_category, // the term 
              'product_cat', // the taxonomy
              array(
                'description'=> $get_products_value->prod_category,
                'slug' => $get_products_value->prod_category
              )
            );
         }

         $post_id = 0;
         $data = $wpdb->get_results($wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s", '_sinalite_prod_id', $get_products_value->prod_id) , ARRAY_N );
         if(isset($data[0])) {
            $post_id = wp_update_post( array(
               'ID' => $data[0][1],
               'post_title' => $get_products_value->prod_name,
               'post_content' => $get_products_value->prod_name,
               'post_status' => 'publish',
               'post_type' => "product",
            ));
            $post_id = $data[0][1];

            update_post_meta( $post_id, '_sinalite_prod_id', $get_products_value->prod_id);
            wp_set_object_terms($post_id, 'variable', 'product_type');
            wp_set_object_terms($post_id, $term->term_id, 'product_cat');
         } else {
            $post_id = wp_insert_post( array(
               'post_title' => $get_products_value->prod_name,
               'post_content' => $get_products_value->prod_name,
               'post_status' => 'publish',
               'post_type' => "product",
            ));

            update_post_meta( $post_id, '_sinalite_prod_id', $get_products_value->prod_id);
            wp_set_object_terms($post_id, 'variable', 'product_type');
            wp_set_object_terms($post_id, $term->term_id, 'product_cat');
         }
         $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products SET `woo_prod_id`=$post_id  WHERE `id` = $get_products_value->id "); 
         // $post_id
         $resp = fetch_product_from_api($get_products_value->prod_id);
         $attributes_data = array();
         foreach ($resp[0] as $resp_key => $resp_value) {
         }

         if( sizeof($attributes_data) > 0 ){
             $attributes = array(); // Initializing

             // Loop through defined attribute data
             $count_key = 0;
             foreach( $attributes_data as $attribute_key => $attribute_array ) {
                 if( isset($attribute_key) ){
                     // Clean attribute name to get the taxonomy
                     $taxonomy = wc_sanitize_taxonomy_name( $attribute_key );

                     $option_term_ids = array(); // Initializing

                     // Loop through defined attribute data options (terms values)
                     foreach( $attribute_array as $option ){
                        createAttribute($attribute_key, $taxonomy);
                        if(!empty($option)) {
                           $return_term = createTerm($option, sanitize_title($option), $taxonomy, $count_key);
                           update_term_to_db($post_id, $option, $attribute_key, $return_term->term_id);

                             $option_term_ids[$taxonomy][] = $return_term->term_id;
                             wp_set_object_terms( $post_id, $option, wc_attribute_taxonomy_name($taxonomy), true );
                       }
                     }
                       // Get the term ID
                    // Loop through defined attribute data
                    $attributes[wc_attribute_taxonomy_name($taxonomy)] = array(
                        'name'          => wc_attribute_taxonomy_name($taxonomy),
                        'value'         => '', // Need to be term IDs
                        'position'      => $count_key + 1,
                        'is_visible'    => 1,
                        'is_variation'  => 1,
                        'is_taxonomy'   => '1'
                    );
                 }
                 $count_key++;
             }
             // Save the meta entry for product attributes
             update_post_meta( $post_id, '_product_attributes', $attributes );
         }

         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant WHERE `prod_id`= '".$get_products_value->prod_id."' AND `update_prod`=1");

         $variant_resp = fetch_variant_from_product($get_products_value->prod_id);

         foreach ($variant_resp as $variant_resp_key => $variant_resp_value) {
            $response_key = explode("-", $variant_resp_value->key);
            sort($response_key);
            $response_key = json_encode($response_key);
             $checkIfExistsVariant = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sinalite_products_variant_price WHERE `prod_id` = $get_products_value->prod_id AND `price` = $variant_resp_value->price AND `key` = '".$response_key."'");
             if ($checkIfExistsVariant == NULL) {
               $wpdb->query("INSERT INTO {$wpdb->prefix}sinalite_products_variant_price (`woo_prod_id`, `prod_id`, `price`, `key`) VALUES($post_id, $get_products_value->prod_id,   $variant_resp_value->price, '$response_key')"); 
            } else {
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `update_prod`=0, `woo_prod_id` = $post_id  WHERE `prod_id` = $get_products_value->prod_id AND `price` = $variant_resp_value->price AND `key` = '".$response_key."'"); 
            }
         }
         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant_price WHERE `prod_id`= '".$get_products_value->prod_id."' AND `update_prod`=1");
         $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products SET `update_prod`=1 WHERE `prod_id` = $get_products_value->prod_id "); 
      }
      $variants_array = array();
      $get_products_variant = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products_variant WHERE update_prod = 0", OBJECT );
      foreach ($get_products_variant as $get_products_variant_key => $get_products_variant_value) {
         $variants_array[$get_products_variant_value->variant_id]['group'] = $get_products_variant_value->group;
         $variants_array[$get_products_variant_value->variant_id]['name'] = $get_products_variant_value->name;
      }

      $variation_data = array();
      $get_products_variant_price = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products_variant_price WHERE update_prod = 0 LIMIT 100", OBJECT );
         foreach ($get_products_variant_price as $get_products_variant_key => $get_products_variant_value) {
            if(!empty($get_products_variant_value->key)) {
               $parent_product_id = $get_products_variant_value->woo_prod_id;
               $product_variants = json_decode($get_products_variant_value->key);
               $product_price = $get_products_variant_value->price;
               $variant_array = array();
               foreach ($product_variants as $product_variants_key => $product_variants_value) {
                  $variant_key = $variants_array[$product_variants_value]['group'];
                     $taxonomy = wc_attribute_taxonomy_name(wc_sanitize_taxonomy_name( $variant_key ));
                  $variant_value = $variants_array[$product_variants_value]['name'];
                  $variant_array[$taxonomy] = wc_sanitize_taxonomy_name($variant_value);
               }

               if($get_products_variant_value->variant_id > 0) {
                     $variation_product_id = $get_products_variant_value->variant_id;
               } else {
                  $variation_product = array( 
                     'post_title'  => get_the_title($parent_product_id).' #'.$get_products_variant_value->id,
                     'post_name'   => 'product-' . $parent_product_id . '-variation',
                     'post_status' => 'publish',
                     'post_parent' => $parent_product_id,
                     'post_type'   => 'product_variation'
                  );
                  $variation_product_id = wp_insert_post( $variation_product ); 
               }

               if(get_option( 'sinalite_price_percentage' )){
                  $percentage = get_option( 'sinalite_price_percentage' );
                  $percentage_value = $product_price * $percentage / 100;
                  $product_price = $product_price + $percentage_value;
               }

               update_post_meta( $variation_product_id, '_regular_price', $product_price );
               update_post_meta( $variation_product_id, '_price', $product_price );
               update_post_meta( $variation_product_id, '_manage_stock', 'true' );
               update_post_meta( $variation_product_id, '_stock', 100 );

               $variation = new WC_Product_Variation($variation_product_id);
               $variation->set_attributes($variant_array);

               $variation->save();           
               update_variant_post_woo_id($get_products_variant_value->id, $variation_product_id);
            }
         $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `update_prod`=1 WHERE `id` = $get_products_variant_value->id "); 
      }

      exit();
   }
}

register_deactivation_hook( __FILE__, 'on_deactive' );
function on_deactive() {
   global $wpdb;
   $the_removal_query = "DROP TABLE IF EXISTS `{$wpdb->prefix}sinalite_products`";
   $wpdb->query( $the_removal_query ); 
   $the_removal_query = "DROP TABLE IF EXISTS `{$wpdb->prefix}sinalite_products_variant`";
   $wpdb->query( $the_removal_query ); 
   $the_removal_query = "DROP TABLE IF EXISTS `{$wpdb->prefix}sinalite_products_variant_price`";
   $wpdb->query( $the_removal_query ); 
}
register_activation_hook ( __FILE__, 'on_activate' );
function on_activate() {
   global $wpdb;
   $set_7_day_time = strtotime('-7 day');
   update_option( 'senalite_prod_update', $set_7_day_time);
   $create_table_query = "
           CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sinalite_products` (
           `id` int(11) NOT NULL auto_increment,
           `woo_prod_id` int(11) NOT NULL DEFAULT 0,
           `prod_id` int(11) NOT NULL DEFAULT 0,
           `prod_sku` text DEFAULT NULL,
           `prod_name` text DEFAULT NULL,
           `prod_category` text NOT NULL,
           `prod_enabled` int(11) NOT NULL DEFAULT 0,
           `update_prod` int(11) NOT NULL DEFAULT 0,
           `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY  (`id`)
           ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
   ";
   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $create_table_query );
   $create_table_query = "
           CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sinalite_products_variant` (
           `id` int(11) NOT NULL auto_increment,
           `woo_prod_id` int(11) NOT NULL DEFAULT 0,
           `prod_id` int(11) NOT NULL DEFAULT 0,
           `variant_id` int(11) NOT NULL DEFAULT 0,
           `group` text DEFAULT NULL,
           `name` text DEFAULT NULL,
           `update_prod` int(11) NOT NULL DEFAULT 0,
           `attri_id` int(11) NOT NULL DEFAULT 0,
           `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY  (`id`)
           ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
   ";
   dbDelta( $create_table_query );
   $create_table_query = "
           CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sinalite_products_variant_price` (
           `id` int(11) NOT NULL auto_increment,
           `prod_id` int(11) NOT NULL DEFAULT 0,
           `woo_prod_id` int(11) NOT NULL DEFAULT 0,
           `price` text DEFAULT NULL,
           `key` text DEFAULT NULL,
           `variant_id` int(11) NOT NULL DEFAULT 0,
           `update_prod` int(11) NOT NULL DEFAULT 0,
           `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY  (`id`)
           ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
   ";
   dbDelta( $create_table_query );
}


function sidtechno_my_admin_menu() {
    add_menu_page(
        __( 'Sinalite Setting', 'my-textdomain' ),
        __( 'Sinalite Setting', 'my-textdomain' ),
        'manage_options',
        'sinalite-setting',
        'my_admin_page_contents',
        'dashicons-schedule',
        3
    );
}
add_action( 'admin_menu', 'sidtechno_my_admin_menu' );

function my_admin_page_contents() {
   global $wpdb;
   $db_time = get_option( 'senalite_prod_update' );
   $last_cron_run = get_option( 'senalite_last_cron_run' );
    ?>
    <h1> <?php esc_html_e( 'Sinalite setting.', 'my-plugin-textdomain' ); ?> </h1>
    <table class="form-table">
      <tr>
         <th>Last automation start</th>
         <td><?php echo date('m/d/Y g:i:s A', $db_time);?></td>
      </tr>
      <tr>
         <th>Next automation start</th>
         <td><?php echo date('m/d/Y g:i:s A', strtotime('+7 day', $db_time));?></td>
      </tr>
      <tr>
         <th>Last cron run</th>
         <td><?php echo date('m/d/Y g:i:s A', $last_cron_run);?></td>
      </tr>
      <tr>
         <th>Product Import Left</th>
         <td><?php $get_products_count = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sinalite_products WHERE `update_prod` = 0"); echo count($get_products_count); ?></td>
      </tr>
      <tr>
         <th>Variation Import Left</th>
         <td><?php $get_products_count = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sinalite_products_variant_price WHERE `update_prod` = 0"); echo count($get_products_count); ?></td>
      </tr>
      <tr>
         <th>Delete all products & Restart Sinalite</th>
         <td><a href="?page=sinalite-setting&clearSinaliteProduct">Click Here</a></td>
      </tr>
    </table>
    <form method="POST" action="options.php">
    <?php
    settings_fields( 'sinalite-setting' );
    do_settings_sections( 'sinalite-setting' );
    submit_button();
    ?>
    </form>
    <?php
}


add_action( 'admin_init', 'my_settings_init' );

function my_settings_init() {

    add_settings_section(
        'sample_page_setting_section',
        __( 'Price settings', 'my-textdomain' ),
        'my_setting_section_callback_function',
        'sinalite-setting'
    );

      add_settings_field(
         'sinalite_price_percentage',
         __( 'Percentage', 'my-textdomain' ),
         'my_setting_markup',
         'sinalite-setting',
         'sample_page_setting_section'
      );

      register_setting( 'sinalite-setting', 'sinalite_price_percentage' );
}


function my_setting_section_callback_function() {
}


function my_setting_markup() {
    echo '<input type="text" id="sinalite_price_percentage" name="sinalite_price_percentage" value="'.get_option( 'sinalite_price_percentage' ).'">';
}

?><?php /*
Plugin Name: Sinalite Scrapping
Plugin URI: http://sitechno.com
description: >- Sinalite Scrapping Products
Version: 1
Author: Muhammad Saad
Author URI: http://sidtechno.com
*/

// add_action('woocommerce_product_options_general_product_data', 'record');
// // Save Fields
// add_action('woocommerce_process_product_meta', 'record');


function get_from_api($url){
   $curl = curl_init($url);
   curl_setopt($curl, CURLOPT_URL, $url);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

   //for debug only!
   curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
   curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

   $resp = curl_exec($curl);
   curl_close($curl);
   if(!empty($resp)) {
      $resp = json_decode($resp);
   } else {
      $resp = array();
   }
   return $resp;
}
function update_variant_post_woo_id($get_products_variant_value, $variation_product_id) {
   global $wpdb;
   $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `variant_id`=$variation_product_id WHERE `id` = $get_products_variant_value"); 
}
function update_term_to_db($post_id, $option, $attribute_key, $return_term){
   global $wpdb;
   $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant SET `attri_id`=$return_term WHERE `woo_prod_id` = $post_id AND `group` = '".$attribute_key."' AND `name` = '".$option."'"); 
}
function createAttribute(string $attributeName, string $attributeSlug): ?\stdClass {
    delete_transient('wc_attribute_taxonomies');
    \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

    $attributeLabels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
    $attributeWCName = array_search($attributeSlug, $attributeLabels, TRUE);

    if (! $attributeWCName) {
        $attributeWCName = wc_sanitize_taxonomy_name($attributeSlug);
    }

    $attributeId = wc_attribute_taxonomy_id_by_name($attributeWCName);
    if (! $attributeId) {
        $taxonomyName = wc_attribute_taxonomy_name($attributeWCName);
        unregister_taxonomy($taxonomyName);
        $attributeId = wc_create_attribute(array(
            'name' => $attributeName,
            'slug' => $attributeSlug,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => 0,
        ));

        register_taxonomy($taxonomyName, apply_filters('woocommerce_taxonomy_objects_' . $taxonomyName, array(
            'product'
        )), apply_filters('woocommerce_taxonomy_args_' . $taxonomyName, array(
            'labels' => array(
                'name' => $attributeSlug,
            ),
            'hierarchical' => FALSE,
            'show_ui' => FALSE,
            'query_var' => TRUE,
            'rewrite' => FALSE,
        )));
    }

    return wc_get_attribute($attributeId);
}
function createTerm(string $termName, string $termSlug, string $taxonomy, int $order = 0): ?\WP_Term {
    $taxonomy = wc_attribute_taxonomy_name($taxonomy);

    if (! $term = get_term_by('slug', $termSlug, $taxonomy)) {
        $term = wp_insert_term($termName, $taxonomy, array(
            'slug' => $termSlug,
        ));
        $term = get_term_by('id', $term['term_id'], $taxonomy);
        if ($term) {
            update_term_meta($term->term_id, 'order', $order);
        }
    }

    return $term;
}
function fetch_product_from_api($product_id){
   $url = "https://liveapi.sinalite.com/product/".$product_id."/6";
   return get_from_api($url);
}
function fetch_variant_from_product($product_id)
{
   $url = "https://liveapi.sinalite.com/variants/".$product_id;
   return get_from_api($url);
}
function get_list_of_prdocuts_from_api() {
   $url = "https://liveapi.sinalite.com/product";
   return get_from_api($url);
}

add_action( 'init', 'update_products_from_sinalite' );

function insert_attribute_to_wp($post_id, $get_products_value, $resp_value){
   global $wpdb;
    $checkIfExistsVariant = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sinalite_products_variant WHERE `prod_id` = $get_products_value->prod_id AND `variant_id` = $resp_value->id AND `group` = '".$resp_value->group."' AND `name` = '".$resp_value->name."'");
    if ($checkIfExistsVariant == NULL) {
      $wpdb->query("INSERT INTO {$wpdb->prefix}sinalite_products_variant (`woo_prod_id`, `prod_id`, `variant_id`, `group`, `name`, `attri_id`) VALUES($post_id, $get_products_value->prod_id, $resp_value->id, '$resp_value->group', '$resp_value->name', '')"); 
   } else {
      $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant SET `update_prod`=0, `woo_prod_id` = $post_id, `attri_id` = '0'  WHERE `prod_id` = $get_products_value->prod_id AND `variant_id` = $resp_value->id AND `group` = '".$resp_value->group."' AND `name` = '".$resp_value->name."'"); 
   }
}
function update_products_from_sinalite() {
   global $wpdb;
   if( isset( $_GET['clearSinaliteProduct'] ) ) {
      $set_7_day_time = strtotime('-7 day');
       $myproducts = get_posts( array('post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1,) );
       $myproducts_variation = get_posts( array('post_type' => 'product_variation', 'post_status' => 'publish', 'numberposts' => 4000,) );

       foreach ( $myproducts as $myproduct ) {
           wp_delete_post( $myproduct->ID, true); // Set to False if you want to send them to Trash.
       } 

       foreach ( $myproducts_variation as $myproduct_variation ) {
           wp_delete_post( $myproduct_variation->ID, true); // Set to False if you want to send them to Trash.
       } 

      update_option( 'senalite_prod_update', $set_7_day_time);
      $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products");
      $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant");
      $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant_price");
      wp_redirect('?page=sinalite-setting');
      exit;
   }
   if( isset( $_GET['update_products_sinalite'] ) ) {
      ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
      // $product_attributes = get_post_meta( 14442, '_product_attributes', true);
      // echo "<pre>";
      // print_r($product_attributes);
      // echo "</pre>";
      // $product_attributes = get_post_meta( 14442, '', true);
      // echo "<pre>";
      // print_r($product_attributes);
      // echo "</pre>";
      // exit;

      // exit;
      // $get_products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products_variant WHERE update_prod = 0 LIMIT 1", OBJECT );
      // foreach ($get_products as $get_products_key => $get_products_value) {

      // }

      // exit;
      //Create main product

      $resp = get_list_of_prdocuts_from_api();
      $db_time = get_option( 'senalite_prod_update' );
      update_option( 'senalite_last_cron_run', time());
      
      if(empty($db_time)) {
         $check_7_day_time = time();
      } else {
         $check_7_day_time = strtotime('+7 day', $db_time);
      }
      if(time() >= $check_7_day_time) {
         foreach ($resp as $resp_key => $resp_value) {
            update_option( 'senalite_prod_update', time());
             $checkIfExists = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sinalite_products WHERE prod_id = $resp_value->id");

             if ($checkIfExists == NULL) {
               $wpdb->query("INSERT INTO {$wpdb->prefix}sinalite_products (prod_id, prod_sku, prod_name, prod_category, prod_enabled) VALUES($resp_value->id, '$resp_value->sku', '$resp_value->name', '$resp_value->category', '$resp_value->enabled')"); 
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant SET `attri_id`=0 WHERE prod_id = $resp_value->id"); 
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `variant_id`=0 WHERE prod_id = $resp_value->id"); 
            } else {
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products SET `update_prod`=0 WHERE prod_id = $resp_value->id"); 
            }
         }
         $get_products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products WHERE update_prod = 1", OBJECT );

          foreach ( $get_products as $get_product ) {
            if($get_product->woo_prod_id > 0) {
               wp_delete_post( $get_product->woo_prod_id, true); // Set to False if you want to send them to Trash.
            }
            $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products WHERE `id`= $get_product->id");
          } 
         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant");
         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant_price");
      }
      $get_products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products WHERE update_prod = 0 LIMIT 1", OBJECT );
      foreach ($get_products as $get_products_key => $get_products_value) {
         $term = get_term_by('name', $get_products_value->prod_category, 'product_cat');
         if(!isset($term->term_id)) {
            $term = wp_insert_term(
              $get_products_value->prod_category, // the term 
              'product_cat', // the taxonomy
              array(
                'description'=> $get_products_value->prod_category,
                'slug' => $get_products_value->prod_category
              )
            );
         }

         $post_id = 0;
         $data = $wpdb->get_results($wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s", '_sinalite_prod_id', $get_products_value->prod_id) , ARRAY_N );
         if(isset($data[0])) {
            $post_id = wp_update_post( array(
               'ID' => $data[0][1],
               'post_title' => $get_products_value->prod_name,
               'post_content' => $get_products_value->prod_name,
               'post_status' => 'publish',
               'post_type' => "product",
            ));
            $post_id = $data[0][1];

            update_post_meta( $post_id, '_sinalite_prod_id', $get_products_value->prod_id);
            wp_set_object_terms($post_id, 'variable', 'product_type');
            wp_set_object_terms($post_id, $term->term_id, 'product_cat');
         } else {
            $post_id = wp_insert_post( array(
               'post_title' => $get_products_value->prod_name,
               'post_content' => $get_products_value->prod_name,
               'post_status' => 'publish',
               'post_type' => "product",
            ));

            update_post_meta( $post_id, '_sinalite_prod_id', $get_products_value->prod_id);
            wp_set_object_terms($post_id, 'variable', 'product_type');
            wp_set_object_terms($post_id, $term->term_id, 'product_cat');
         }
         $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products SET `woo_prod_id`=$post_id  WHERE `id` = $get_products_value->id "); 
         // $post_id
         $resp = fetch_product_from_api($get_products_value->prod_id);
         $attributes_data = array();
         foreach ($resp[0] as $resp_key => $resp_value) {
         }

         if( sizeof($attributes_data) > 0 ){
             $attributes = array(); // Initializing

             // Loop through defined attribute data
             $count_key = 0;
             foreach( $attributes_data as $attribute_key => $attribute_array ) {
                 if( isset($attribute_key) ){
                     // Clean attribute name to get the taxonomy
                     $taxonomy = wc_sanitize_taxonomy_name( $attribute_key );

                     $option_term_ids = array(); // Initializing

                     // Loop through defined attribute data options (terms values)
                     foreach( $attribute_array as $option ){
                        createAttribute($attribute_key, $taxonomy);
                        if(!empty($option)) {
                           $return_term = createTerm($option, sanitize_title($option), $taxonomy, $count_key);
                           update_term_to_db($post_id, $option, $attribute_key, $return_term->term_id);

                             $option_term_ids[$taxonomy][] = $return_term->term_id;
                             wp_set_object_terms( $post_id, $option, wc_attribute_taxonomy_name($taxonomy), true );
                       }
                     }
                       // Get the term ID
                    // Loop through defined attribute data
                    $attributes[wc_attribute_taxonomy_name($taxonomy)] = array(
                        'name'          => wc_attribute_taxonomy_name($taxonomy),
                        'value'         => '', // Need to be term IDs
                        'position'      => $count_key + 1,
                        'is_visible'    => 1,
                        'is_variation'  => 1,
                        'is_taxonomy'   => '1'
                    );
                 }
                 $count_key++;
             }
             // Save the meta entry for product attributes
             update_post_meta( $post_id, '_product_attributes', $attributes );
         }

         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant WHERE `prod_id`= '".$get_products_value->prod_id."' AND `update_prod`=1");

         $variant_resp = fetch_variant_from_product($get_products_value->prod_id);

         foreach ($variant_resp as $variant_resp_key => $variant_resp_value) {
            $response_key = explode("-", $variant_resp_value->key);
            sort($response_key);
            $response_key = json_encode($response_key);
             $checkIfExistsVariant = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sinalite_products_variant_price WHERE `prod_id` = $get_products_value->prod_id AND `price` = $variant_resp_value->price AND `key` = '".$response_key."'");
             if ($checkIfExistsVariant == NULL) {
               $wpdb->query("INSERT INTO {$wpdb->prefix}sinalite_products_variant_price (`woo_prod_id`, `prod_id`, `price`, `key`) VALUES($post_id, $get_products_value->prod_id,   $variant_resp_value->price, '$response_key')"); 
            } else {
               $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `update_prod`=0, `woo_prod_id` = $post_id  WHERE `prod_id` = $get_products_value->prod_id AND `price` = $variant_resp_value->price AND `key` = '".$response_key."'"); 
            }
         }
         $wpdb->query("DELETE FROM {$wpdb->prefix}sinalite_products_variant_price WHERE `prod_id`= '".$get_products_value->prod_id."' AND `update_prod`=1");
         $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products SET `update_prod`=1 WHERE `prod_id` = $get_products_value->prod_id "); 
      }
      $variants_array = array();
      $get_products_variant = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products_variant WHERE update_prod = 0", OBJECT );
      foreach ($get_products_variant as $get_products_variant_key => $get_products_variant_value) {
         $variants_array[$get_products_variant_value->variant_id]['group'] = $get_products_variant_value->group;
         $variants_array[$get_products_variant_value->variant_id]['name'] = $get_products_variant_value->name;
      }

      $variation_data = array();
      $get_products_variant_price = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sinalite_products_variant_price WHERE update_prod = 0 LIMIT 100", OBJECT );
         foreach ($get_products_variant_price as $get_products_variant_key => $get_products_variant_value) {
            if(!empty($get_products_variant_value->key)) {
               $parent_product_id = $get_products_variant_value->woo_prod_id;
               $product_variants = json_decode($get_products_variant_value->key);
               $product_price = $get_products_variant_value->price;
               $variant_array = array();
               foreach ($product_variants as $product_variants_key => $product_variants_value) {
                  $variant_key = $variants_array[$product_variants_value]['group'];
                     $taxonomy = wc_attribute_taxonomy_name(wc_sanitize_taxonomy_name( $variant_key ));
                  $variant_value = $variants_array[$product_variants_value]['name'];
                  $variant_array[$taxonomy] = wc_sanitize_taxonomy_name($variant_value);
               }

               if($get_products_variant_value->variant_id > 0) {
                     $variation_product_id = $get_products_variant_value->variant_id;
               } else {
                  $variation_product = array( 
                     'post_title'  => get_the_title($parent_product_id).' #'.$get_products_variant_value->id,
                     'post_name'   => 'product-' . $parent_product_id . '-variation',
                     'post_status' => 'publish',
                     'post_parent' => $parent_product_id,
                     'post_type'   => 'product_variation'
                  );
                  $variation_product_id = wp_insert_post( $variation_product ); 
               }

               if(get_option( 'sinalite_price_percentage' )){
                  $percentage = get_option( 'sinalite_price_percentage' );
                  $percentage_value = $product_price * $percentage / 100;
                  $product_price = $product_price + $percentage_value;
               }

               update_post_meta( $variation_product_id, '_regular_price', $product_price );
               update_post_meta( $variation_product_id, '_price', $product_price );
               update_post_meta( $variation_product_id, '_manage_stock', 'true' );
               update_post_meta( $variation_product_id, '_stock', 100 );

               $variation = new WC_Product_Variation($variation_product_id);
               $variation->set_attributes($variant_array);

               $variation->save();           
               update_variant_post_woo_id($get_products_variant_value->id, $variation_product_id);
            }
         $wpdb->query("UPDATE {$wpdb->prefix}sinalite_products_variant_price SET `update_prod`=1 WHERE `id` = $get_products_variant_value->id "); 
      }

      exit();
   }
}

register_deactivation_hook( __FILE__, 'on_deactive' );
function on_deactive() {
   global $wpdb;
   $the_removal_query = "DROP TABLE IF EXISTS `{$wpdb->prefix}sinalite_products`";
   $wpdb->query( $the_removal_query ); 
   $the_removal_query = "DROP TABLE IF EXISTS `{$wpdb->prefix}sinalite_products_variant`";
   $wpdb->query( $the_removal_query ); 
   $the_removal_query = "DROP TABLE IF EXISTS `{$wpdb->prefix}sinalite_products_variant_price`";
   $wpdb->query( $the_removal_query ); 
}
register_activation_hook ( __FILE__, 'on_activate' );
function on_activate() {
   global $wpdb;
   $set_7_day_time = strtotime('-7 day');
   update_option( 'senalite_prod_update', $set_7_day_time);
   $create_table_query = "
           CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sinalite_products` (
           `id` int(11) NOT NULL auto_increment,
           `woo_prod_id` int(11) NOT NULL DEFAULT 0,
           `prod_id` int(11) NOT NULL DEFAULT 0,
           `prod_sku` text DEFAULT NULL,
           `prod_name` text DEFAULT NULL,
           `prod_category` text NOT NULL,
           `prod_enabled` int(11) NOT NULL DEFAULT 0,
           `update_prod` int(11) NOT NULL DEFAULT 0,
           `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY  (`id`)
           ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
   ";
   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $create_table_query );
   $create_table_query = "
           CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sinalite_products_variant` (
           `id` int(11) NOT NULL auto_increment,
           `woo_prod_id` int(11) NOT NULL DEFAULT 0,
           `prod_id` int(11) NOT NULL DEFAULT 0,
           `variant_id` int(11) NOT NULL DEFAULT 0,
           `group` text DEFAULT NULL,
           `name` text DEFAULT NULL,
           `update_prod` int(11) NOT NULL DEFAULT 0,
           `attri_id` int(11) NOT NULL DEFAULT 0,
           `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY  (`id`)
           ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
   ";
   dbDelta( $create_table_query );
   $create_table_query = "
           CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sinalite_products_variant_price` (
           `id` int(11) NOT NULL auto_increment,
           `prod_id` int(11) NOT NULL DEFAULT 0,
           `woo_prod_id` int(11) NOT NULL DEFAULT 0,
           `price` text DEFAULT NULL,
           `key` text DEFAULT NULL,
           `variant_id` int(11) NOT NULL DEFAULT 0,
           `update_prod` int(11) NOT NULL DEFAULT 0,
           `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY  (`id`)
           ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
   ";
   dbDelta( $create_table_query );
}


function sidtechno_my_admin_menu() {
    add_menu_page(
        __( 'Sinalite Setting', 'my-textdomain' ),
        __( 'Sinalite Setting', 'my-textdomain' ),
        'manage_options',
        'sinalite-setting',
        'my_admin_page_contents',
        'dashicons-schedule',
        3
    );
}
add_action( 'admin_menu', 'sidtechno_my_admin_menu' );

function my_admin_page_contents() {
   global $wpdb;
   $db_time = get_option( 'senalite_prod_update' );
   $last_cron_run = get_option( 'senalite_last_cron_run' );
    ?>
    <h1> <?php esc_html_e( 'Sinalite setting.', 'my-plugin-textdomain' ); ?> </h1>
    <table class="form-table">
      <tr>
         <th>Last automation start</th>
         <td><?php echo date('m/d/Y g:i:s A', $db_time);?></td>
      </tr>
      <tr>
         <th>Next automation start</th>
         <td><?php echo date('m/d/Y g:i:s A', strtotime('+7 day', $db_time));?></td>
      </tr>
      <tr>
         <th>Last cron run</th>
         <td><?php echo date('m/d/Y g:i:s A', $last_cron_run);?></td>
      </tr>
      <tr>
         <th>Product Import Left</th>
         <td><?php $get_products_count = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sinalite_products WHERE `update_prod` = 0"); echo count($get_products_count); ?></td>
      </tr>
      <tr>
         <th>Variation Import Left</th>
         <td><?php $get_products_count = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sinalite_products_variant_price WHERE `update_prod` = 0"); echo count($get_products_count); ?></td>
      </tr>
      <tr>
         <th>Delete all products & Restart Sinalite</th>
         <td><a href="?page=sinalite-setting&clearSinaliteProduct">Click Here</a></td>
      </tr>
    </table>
    <form method="POST" action="options.php">
    <?php
    settings_fields( 'sinalite-setting' );
    do_settings_sections( 'sinalite-setting' );
    submit_button();
    ?>
    </form>
    <?php
}


add_action( 'admin_init', 'my_settings_init' );

function my_settings_init() {

    add_settings_section(
        'sample_page_setting_section',
        __( 'Price settings', 'my-textdomain' ),
        'my_setting_section_callback_function',
        'sinalite-setting'
    );

      add_settings_field(
         'sinalite_price_percentage',
         __( 'Percentage', 'my-textdomain' ),
         'my_setting_markup',
         'sinalite-setting',
         'sample_page_setting_section'
      );

      register_setting( 'sinalite-setting', 'sinalite_price_percentage' );
}


function my_setting_section_callback_function() {
}


function my_setting_markup() {
    echo '<input type="text" id="sinalite_price_percentage" name="sinalite_price_percentage" value="'.get_option( 'sinalite_price_percentage' ).'">';
}

?>
