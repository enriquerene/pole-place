<?php
/**
 * Plugin Name: PolePlace Marketplace
 * Plugin URI: https://poleplace.com.br
 * Description: A multi-vendor marketplace plugin for WooCommerce with REST API support for React Native mobile app
 * Version: 1.0.0
 * Author: PolePlace
 * Author URI: https://poleplace.com.br
 * Text Domain: pole-place-marketplace
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 *
 * @package PolePlace
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('POLEPLACE_VERSION', '1.0.0');
define('POLEPLACE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POLEPLACE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POLEPLACE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('POLEPLACE_COMMISSION_RATE', 0.05); // 5% commission rate

// Require Composer autoloader if it exists
if (file_exists(POLEPLACE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once POLEPLACE_PLUGIN_DIR . 'vendor/autoload.php';
}

// Main plugin class
class PolePlace_Marketplace {
    /**
     * The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Main PolePlace_Marketplace Instance
     * 
     * Ensures only one instance of PolePlace_Marketplace is loaded or can be loaded
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->includes();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WooCommerce is active
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // Plugin activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Include required files
     */
    private function includes() {
        // Include core files
        require_once POLEPLACE_PLUGIN_DIR . 'includes/class-poleplace-install.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/class-poleplace-product.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/class-poleplace-order.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/class-poleplace-commission.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/class-poleplace-user.php';
        
        // Include API files
        require_once POLEPLACE_PLUGIN_DIR . 'includes/api/class-poleplace-api.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/api/class-poleplace-api-user.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/api/class-poleplace-api-marketplace.php';
        require_once POLEPLACE_PLUGIN_DIR . 'includes/api/class-poleplace-api-admin.php';
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Initialize the plugin if WooCommerce is active
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize classes
        new PolePlace_Product();
        new PolePlace_Order();
        new PolePlace_Commission();
        new PolePlace_User();
        
        // Initialize API
        new PolePlace_API();
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('PolePlace Marketplace requires WooCommerce to be installed and active.', 'pole-place-marketplace'); ?></p>
        </div>
        <?php
    }

    /**
     * Activate the plugin
     */
    public function activate() {
        // Create necessary database tables
        PolePlace_Install::install();
        
        // Add custom product attributes for Pole Dance gear
        PolePlace_Install::add_product_attributes();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivate the plugin
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Returns the main instance of PolePlace_Marketplace
 */
function PolePlace() {
    return PolePlace_Marketplace::instance();
}

// Initialize the plugin
PolePlace();
