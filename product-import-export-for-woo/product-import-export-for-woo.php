<?php
/*
  Plugin Name: Product CSV Import Export (BASIC)
  Plugin URI: https://wordpress.org/plugins/product-import-export-for-woo/
  Description: Import and Export Products From and To your WooCommerce Store.
  Author: WebToffee
  Author URI: https://www.webtoffee.com/product/product-import-export-woocommerce/
  Version: 2.0.3
  WC tested up to: 5.4
  License:           GPLv3
  License URI:       https://www.gnu.org/licenses/gpl-3.0.html
  Text Domain: product-import-export-for-woo
 */

if ( !defined( 'ABSPATH' ) || !is_admin() ) {
	return;
}


// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
	die;
}

define( 'WT_P_IEW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WT_P_IEW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WT_P_IEW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WT_P_IEW_PLUGIN_FILENAME', __FILE__ );
if ( !defined( 'WT_IEW_PLUGIN_ID_BASIC' ) ) {
	define( 'WT_IEW_PLUGIN_ID_BASIC', 'wt_import_export_for_woo_basic' );
}
define( 'WT_P_IEW_PLUGIN_NAME', 'Product Import Export for WooCommerce' );
define( 'WT_P_IEW_PLUGIN_DESCRIPTION', 'Import and Export Products From and To your WooCommerce Store.' );

if ( !defined( 'WT_IEW_DEBUG_BASIC' ) ) {
	define( 'WT_IEW_DEBUG_BASIC', false );
}
if ( !defined( 'WT_IEW_DEBUG_BASIC_TROUBLESHOOT' ) ) {
	define( 'WT_IEW_DEBUG_BASIC_TROUBLESHOOT', 'https://www.webtoffee.com/finding-php-error-logs/' );
}
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WT_P_IEW_VERSION', '2.0.3' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wt-import-export-for-woo-activator.php
 */
function activate_wt_import_export_for_woo_basic_product() {
//        if(!class_exists( 'WooCommerce' )){
//            deactivate_plugins(basename(__FILE__));
//            wp_die(__("WooCommerce is not installed/actived. it is required for this plugin to work properly. Please activate WooCommerce."), "", array('back_link' => 1));
//        }
//        if(is_plugin_active('wt-import-export-for-woo-product/wt-import-export-for-woo-product.php')){           
//            deactivate_plugins( basename( __FILE__ ) );
//            wp_die(
//                    '<p>'.__("Is everything fine? You already have the Premium version installed in your website. For any issues, kindly raise a ticket via <a target='_blank' href='https://www.webtoffee.com/support/'>support</a>")
//                    . '</p> <a href="' . admin_url( 'plugins.php' ) . '">' . __( 'go back') . '</a>'
//            );
//        }    
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wt-import-export-for-woo-activator.php';
	Wt_Import_Export_For_Woo_Basic_Activator_Product::activate();
}



/* Checking WC is actived or not */
if ( !function_exists( 'is_plugin_active' ) ) {
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

add_action( 'plugins_loaded', 'wt_product_basic_check_for_woocommerce' );

if ( !function_exists( 'wt_product_basic_check_for_woocommerce' ) ) {

	function wt_product_basic_check_for_woocommerce() {


		if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) || !defined( 'WC_VERSION' ) ) {
			add_action( 'admin_notices', 'wt_wc_missing_warning_product_basic' );
		}
		if ( !function_exists( 'wt_wc_missing_warning_product_basic' ) ) {

			function wt_wc_missing_warning_product_basic() {

				$install_url = wp_nonce_url( add_query_arg( array( 'action' => 'install-plugin', 'plugin' => 'woocommerce', ), admin_url( 'update.php' ) ), 'install-plugin_woocommerce' );
				$class		 = 'notice notice-error';
				$post_type	 = 'product';
				$message	 = sprintf( __( 'The <b>WooCommerce</b> plugin must be active for <b>%s CSV Import Export (BASIC)</b> plugin to work effectively. Please <a href="%s" target="_blank">install & activate WooCommerce</a>.' ), ucfirst( $post_type ), esc_url( $install_url ) );
				printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), ( $message ) );
			}

		}
	}
}


/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wt-import-export-for-woo-deactivator.php
 */
function deactivate_wt_import_export_for_woo_basic_product() {

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wt-import-export-for-woo-deactivator.php';
	Wt_Import_Export_For_Woo_Basic_Deactivator_Product::deactivate();
}

register_activation_hook( __FILE__, 'activate_wt_import_export_for_woo_basic_product' );
register_deactivation_hook( __FILE__, 'deactivate_wt_import_export_for_woo_basic_product' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wt-import-export-for-woo.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wt_import_export_for_woo_basic_product() {

	if ( !defined( 'WT_IEW_BASIC_STARTED' ) ) {
		define( 'WT_IEW_BASIC_STARTED', 1 );
		$plugin = new Wt_Import_Export_For_Woo_Basic();
		$plugin->run();
	}
}

/** this added for a temporary when a plugin update with the option upload zip file. need to remove this after some version release */
if ( !get_option( 'wt_p_iew_is_active' ) ) {
	update_option( 'wt_product_show_legacy_menu', 1 );
	activate_wt_import_export_for_woo_basic_product();
}

if ( get_option( 'wt_p_iew_is_active' ) ) {
	run_wt_import_export_for_woo_basic_product();
}

/* Plugin page links */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wt_piew_plugin_action_links_basic_product' );

function wt_piew_plugin_action_links_basic_product( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wt_import_export_for_woo_basic' ) . '">' . __( 'Settings' ) . '</a>',
		'<a href="https://www.webtoffee.com/product-import-export-plugin-woocommerce-user-guide/" target="_blank">' . __( 'Documentation' ) . '</a>',
		'<a href="https://wordpress.org/support/plugin/product-import-export-for-woo/" target="_blank">' . __( 'Support' ) . '</a>',
		'<a href="https://www.webtoffee.com/product/product-import-export-woocommerce/" target="_blank" style="color:#3db634;">' . __( 'Premium Upgrade' ) . '</a>',
	);
	if ( array_key_exists( 'deactivate', $links ) ) {
		$links[ 'deactivate' ] = str_replace( '<a', '<a class="pipe-deactivate-link"', $links[ 'deactivate' ] );
	}
	return array_merge( $plugin_links, $links );
}

/*
 *  Displays update information for the plugin. 
 */

function wt_product_import_export_for_woo_update_message( $data, $response ) {

	if ( isset( $data[ 'upgrade_notice' ] ) ) {
		add_action( 'admin_print_footer_scripts', 'wt_product_imex_basic_plugin_screen_update_js' );
		printf(
		'<div class="update-message wt-product-update-message">%s</div>', $data[ 'upgrade_notice' ]
		);
	}
}

add_action( 'in_plugin_update_message-product-import-export-for-woo/product-import-export-for-woo.php', 'wt_product_import_export_for_woo_update_message', 10, 2 );


if ( !function_exists( 'wt_product_imex_basic_plugin_screen_update_js' ) ) {

	function wt_product_imex_basic_plugin_screen_update_js() {
		?>
		<script>
		( function ( $ ) {
		var update_dv = $( '#product-import-export-for-woo-update' );
		update_dv.find( '.wt-product-update-message' ).next( 'p' ).remove();
		update_dv.find( 'a.update-link:eq(0)' ).click( function () {
			$( '.wt-product-update-message' ).remove();
		} );
		} )( jQuery );
		</script>
		<?php
	}

}
// uninstall feedback catch
include_once plugin_dir_path( __FILE__ ) . 'includes/class-wf-prodimpexp-plugin-uninstall-feedback.php';



// Add dismissble banner for legacy menu
include_once plugin_dir_path( __FILE__ ) . 'includes/class-wt-product-legacy-menu-moved.php';
$product_legacy_menu					 = new wt_product_legacy_menu_moved( 'product' );
$product_legacy_menu->plugin_title		 = "Product Import Export";
$product_legacy_menu->old_menu			 = "WooCommerce > Product Im-EX";
$product_legacy_menu->banner_message	 = sprintf( __( "We have introduced a new main menu %sWebToffee Import Export(basic)%s for the %s plugin. Click the button below or dismiss this banner to remove the old menu from %s." ), '<b>', '</b>', $product_legacy_menu->plugin_title, $product_legacy_menu->old_menu );
$product_legacy_menu->old_menu_params	 = array(
	array( 'parent_slug' => 'woocommerce', 'menu_title' => 'Product Im-EX', 'capability' => 'import' )
);


// Add dismissible server info for file restrictions
include_once plugin_dir_path( __FILE__ ) . 'includes/class-wt-non-apache-info.php';
$inform_server_secure					 = new wt_inform_server_secure( 'product' );
$inform_server_secure->plugin_title		 = "Product Import Export";
$inform_server_secure->banner_message	 = sprintf( __( "The <b>%s</b> plugin uploads the imported file into <b>wp-content/webtoffee_import</b> folder. Please ensure that public access restrictions are set in your server for this folder." ), $inform_server_secure->plugin_title );


add_action( 'wt_product_addon_basic_help_content', 'wt_product_import_export_basic_help_content' );

function wt_product_import_export_basic_help_content() {
	if ( defined( 'WT_IEW_PLUGIN_ID_BASIC' ) ) {
		?>
		<li>
			<img src="<?php echo WT_P_IEW_PLUGIN_URL; ?>assets/images/sample-csv.png">
			<h3><?php _e( 'Sample Product CSV' ); ?></h3>
			<p><?php _e( 'Familiarize yourself with the sample CSV.' ); ?></p>
			<a target="_blank" href="https://www.webtoffee.com/wp-content/uploads/2021/03/Product_SampleCSV.csv" class="button button-primary">
				<?php _e( 'Get Product CSV' ); ?>        
			</a>
		</li>
		<?php
	}
}
