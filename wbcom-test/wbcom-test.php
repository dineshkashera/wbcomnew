<?php
/*
  Plugin Name: wbcom test
  Description: wbcom test answer questions  
  Author: dineshkashera
  Author URI: https://stackoverflow.com/users/6410722/dineshkashera
  Plugin URI: https://stackoverflow.com/users/6410722/dineshkashera
  Text Domain: wbcom-test
  Version: 1.0.0
  Requires at least: 3.0.0
  Tested up to: 5.0.0
  WC requires at least: 3.0
  WC tested up to: 3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{
    define('PLUG_DIR_URI', plugin_dir_url(__FILE__));
    define('PLUG_DIR_PATH',plugin_dir_path(__FILE__));
    define('PLUG_DOMAIN','wbcom-test');
    
    class Wbcom_Test{
        
        private static $_instance;
        
        public static function getInstance() {
            self::$_instance = new self;
            if( !self::$_instance instanceof self )
                self::$_instance = new self;
                
                return self::$_instance;
                
        }
        
        public function __construct() {
            
            //add cdn file in admin side
            add_action('admin_head',array($this,'call_to_add_cdn'));
            //enqueue frontend script
            add_action( 'wp_enqueue_scripts',array($this, 'add_custom_script'));
            //admin enqueue scripts 
            add_action( 'admin_enqueue_scripts',array($this, 'add_admin_scripts'));
            //add metabox
            add_action( 'add_meta_boxes',array($this,'extra_option_for_job_listing'));
            //save post hook
            add_action( 'save_post',array($this,'save_metabox_extra_callback' ),10,3);
            //add shortcode for job list 
            add_shortcode( 'job-list', array($this,'custom_job_list'));
            //add settings tabs
            add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab_gifts', 50 );
            add_action( 'woocommerce_settings_tabs_settings_tab_gifts', __CLASS__ . '::settings_tab_gifts' );
            add_action( 'woocommerce_update_options_settings_tab_gifts', __CLASS__ . '::update_settings_gifts' );
            
            //add to cart hook
            add_action('wp', array($this,'item_add_to_cart'));
            
        }
        
        
        /**
         * add select2 cdn
         */
        public function call_to_add_cdn(){?>
            <link href="//cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />
            <script src="//cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
        <?php }
        
        /**
         * Add custom scripts for frontend
         */
        public function add_custom_script(){
            wp_enqueue_script( 'my-custom-js', PLUG_DIR_URI.'assets/js/wbcom-frontend.js',array('jquery'),'1.0',true);
            wp_enqueue_style( 'my-custom-css', PLUG_DIR_URI.'assets/css/custom-style.css');
        }
        
        /**
         * Add custom scripts for admin side
         */
        public function add_admin_scripts(){
          
            wp_enqueue_style( 'my-custom-admin-css', PLUG_DIR_URI.'assets/css/admin-style.css');
            wp_enqueue_script( 'admin-custom-script', PLUG_DIR_URI.'assets/js/wbcom-backend.js',array('jquery'),'1.0',true);
            
            $globalData = array(
                'siteurl'  => site_url(),
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            );
            
            wp_localize_script('admin-custom-script', 'CALL', $globalData);
        }
        
        /**
         * Add custom shortcode [job_list]
         */
        
        public function custom_job_list($atts){
            
            $extra_val = $atts['extra'];
            
            $args = array(
                'post_type'  => 'job_listing',
                'meta_query' => array(
                    array(
                        'key'     => 'wb_extra',
                        'value'   => $extra_val,
                        'compare' => 'IN',
                    ),
                ),
            );
            $query = new WP_Query( $args );
            
            ob_start();
            if ( in_array( 'wp-job-manager/wp-job-manager.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
                if ( $query->have_posts() ) {
                ?>
                	<div class="template_job_list_block">
                		<ul>
                    		<?php 
                        		while ( $query->have_posts() ) {
                        		    $query->the_post();
                        		    echo '<li><a href="'. get_the_permalink() .'" title="'. get_the_title() .'">' . get_the_title() . '</a></li>';
                        		}
                    		?>
                		</ul>
                	</div>
            	<?php 
                }else{?>
                    <div class="no-record">No job found.</div>
                <?php }
                // Restore original Post Data
                wp_reset_postdata();
            }else{?>
                <div class="no_plugins_active">Install wp-job-manager plugins to use shortcode [job-list]</div>
            <?php }
           
        	
            $output_string = ob_get_contents();
            ob_end_clean();
            return $output_string;
        }
        
        /**
         * Add metabox for add extra option
         */
        public function extra_option_for_job_listing(){
            add_meta_box( 'rm-meta-box-id', esc_html__( 'Extra', 'wordpress' ),array($this,'extra_meta_box_callback'), 'job_listing', 'advanced', 'high' );
        }
        
      
        /**
         * Add fields for extra meta box
         */
        public  function extra_meta_box_callback( $post ) {
            $wb_extra_val = get_post_meta( $post->ID, 'wb_extra', true );
            $extras = array( '1' => 'first','2'=>'second','3'=>'third','4'=>'fourth');
            ?>
            <label for="title_field" style="width:50px; display:inline-block;"><?php  echo esc_html__('Extra', 'wordpress') ?> </label>
            <select name="extra" id="wb_extra" class="wb_extra_cls" style="width:300px;">
            	<?php foreach($extras as $key => $val){?>
            		<option value="<?php echo $key;?>" <?php if($key == $wb_extra_val){echo 'selected="selected"';}?>><?php echo ucfirst($val);?></option>
            	<?php }?>
            </select>
        	<?php    
        }
        
        /**
         * save post hook, save extra fields value
         */
        public function save_metabox_extra_callback( $post_id, $post, $update ){
            
            if(!is_admin())
                return;
            
            $post_type  =   $post->post_type;
            $extra_val  =   sanitize_text_field($_POST['extra']);
            
            // If this isn't a 'job_listing' post, don't update it.
            if ( "job_listing" != $post_type ) return;
            
            update_post_meta( $post_id, 'wb_extra', $extra_val );
        }
        
        /**
         * Add a new settings tab to the WooCommerce settings tabs array.
         *
         * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
         * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
         */
        public static function add_settings_tab_gifts( $settings_tabs ) {
            $settings_tabs['settings_tab_gifts'] = __( 'Woo Gifts Settings', PLUG_DOMAIN );
            return $settings_tabs;
        }
        
        /**
         * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
         *
         * @uses woocommerce_admin_fields()
         * @uses self::get_settings()
         */
        public static function settings_tab_gifts() {
            woocommerce_admin_fields( self::get_settings() );
        }
        
        /**
         * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
         *
         * @uses woocommerce_update_options()
         * @uses self::get_settings()
         */
        public static function update_settings_gifts() {
            woocommerce_update_options( self::get_settings() );
        }
        
        /**
         * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
         *
         * @return array Array of settings for @see woocommerce_admin_fields() function.
         */
        public static function get_settings() {
            
            // Get products
            $args = array(
                'status' => 'publish',
                'type'   => array( 'simple'),
            );
            
            //get simple products
            $products           =   wc_get_products( $args );
            $simple_pro_list    =   array();
            $empty_list         =   array('0'=>'No product Found');
            
            if(isset($products) && is_array($products)){
                foreach($products as $key => $single_product){
                    
                    $product_id   =   $single_product->id;
                    $title        =   get_the_title($product_id);
                    
                    $simple_pro_list[$product_id] = $title;
                }
            }
            
            
            $settings = array(
                'section_title' => array(
                    'name'     => __( 'Woo Gifts', PLUG_DOMAIN ),
                    'type'     => 'title',
                    'desc'     => 'Selected Gifts item will be added automatically if cart total is greater than $100',
                    'id'       => 'wc_settings_tab_gifts_section_title'
                ),
                'woo_gifts_list' => array(
                    'name' => __( 'Select Gifts', PLUG_DOMAIN ),
                    'type' => 'select',
                    'desc' => __( 'Apply gifts on Cart page', PLUG_DOMAIN ),
                    'id'   => 'wc_settings_tab_gifts_woo_gifts_list',
                    'options' => !empty($simple_pro_list) ? $simple_pro_list : $empty_list
                ),
                'woo_cart_total' => array(
                    'name' => __( 'Cart Total', PLUG_DOMAIN ),
                    'type' => 'text',
                    'desc' => __( 'Apply gifts product if cart total is greater', PLUG_DOMAIN ),
                    'id'   => 'wc_settings_tab_gifts_woo_gifts_cart_total',
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => 'wc_settings_tab_gifts_section_end'
                )
            );
            return apply_filters( 'wc_settings_tab_gifts_settings', $settings );
        }
        
        /**
         * Item add to cart 
         */
        public function item_add_to_cart(){ 
            global $woocommerce;
            $cart_total = (float)get_option('wc_settings_tab_gifts_woo_gifts_cart_total'); 
            $product_id = (int)get_option('wc_settings_tab_gifts_woo_gifts_list');
            
            
            //if cart total is greater than 100
            if( $woocommerce->cart->total >= $cart_total) { 
                
                $found = false;
                //check if product already in cart
                foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
                    $_product = $values['data'];
                    if ( $_product->get_id() == $product_id )
                        $found = true;
                }
                    
                // if product not found, add it
                if ( ! $found )
                    WC()->cart->add_to_cart( $product_id );
                
            }
        }
    }//end of class Wbcom_Test
    
    
    new Wbcom_Test();
    
    
}else{
    
  function send_plugin_error_notice(){?>
       <div class="error notice is-dismissible">
       		<p><?php _e( 'Woocommerce is not activated, please activate woocommerce first to install and use wbcom Test', PLUG_DOMAIN ); ?></p>
       </div>
   <?php
  }
  add_action( 'admin_init', 'plugin_deactivate_call' );
  
  function plugin_deactivate_call(){
      deactivate_plugins( plugin_basename(__FILE__ ) );
      add_action( 'admin_notices', 'send_plugin_error_notice' );
  }
}