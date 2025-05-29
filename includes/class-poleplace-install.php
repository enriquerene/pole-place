<?php
/**
 * PolePlace Installation
 *
 * Handles installation and setup of the PolePlace Marketplace plugin
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_Install Class
 */
class PolePlace_Install {
    /**
     * Install the plugin
     */
    public static function install() {
        self::create_tables();
        self::create_roles();
        self::add_product_attributes();
        
        // Set a flag to indicate that we need to flush the rewrite rules
        update_option('poleplace_flush_rewrite_rules', 'yes');
        
        // Set plugin version
        update_option('poleplace_version', POLEPLACE_VERSION);
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = '';

        if ($wpdb->has_cap('collation')) {
            $collate = $wpdb->get_charset_collate();
        }

        // Create commissions table
        $tables = "
        CREATE TABLE {$wpdb->prefix}poleplace_commissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            seller_id BIGINT UNSIGNED NOT NULL,
            buyer_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(19,4) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY seller_id (seller_id),
            KEY buyer_id (buyer_id),
            KEY status (status)
        ) $collate;
        ";

        dbDelta($tables);
    }

    /**
     * Create roles and capabilities
     */
    private static function create_roles() {
        // We don't need custom roles as we're using WordPress's default user system
        // All registered users can both buy and sell products
        
        // However, we need to add custom capabilities to the administrator role
        $admin = get_role('administrator');
        
        if ($admin) {
            $admin->add_cap('manage_poleplace_marketplace');
            $admin->add_cap('view_poleplace_reports');
            $admin->add_cap('view_poleplace_commissions');
        }
    }

    /**
     * Add custom product attributes for Pole Dance gear
     */
    public static function add_product_attributes() {
        // Define attributes
        $attributes = array(
            'pole_diameter' => array(
                'name'         => 'Pole Diameter',
                'type'         => 'select',
                'options'      => array('38mm', '40mm', '42mm', '45mm', '50mm'),
                'default'      => '45mm',
                'description'  => 'The diameter of the pole',
            ),
            'pole_material' => array(
                'name'         => 'Material',
                'type'         => 'select',
                'options'      => array('Chrome', 'Stainless Steel', 'Brass', 'Titanium Gold', 'Powder Coated'),
                'default'      => 'Chrome',
                'description'  => 'The material of the pole',
            ),
            'grip_type' => array(
                'name'         => 'Grip Type',
                'type'         => 'select',
                'options'      => array('Standard', 'Powder Coated', 'Silicone', 'Brass', 'Titanium Gold'),
                'default'      => 'Standard',
                'description'  => 'The type of grip on the pole',
            ),
            'pole_height' => array(
                'name'         => 'Height',
                'type'         => 'select',
                'options'      => array('2.2m', '2.5m', '2.7m', '3.0m', 'Adjustable'),
                'default'      => 'Adjustable',
                'description'  => 'The height of the pole',
            ),
            'mounting_type' => array(
                'name'         => 'Mounting Type',
                'type'         => 'select',
                'options'      => array('Static', 'Spinning', 'Convertible', 'Portable', 'Permanent'),
                'default'      => 'Convertible',
                'description'  => 'The mounting type of the pole',
            ),
        );

        // Register attributes if WooCommerce is active
        if (function_exists('wc_create_attribute')) {
            foreach ($attributes as $slug => $attribute) {
                // Check if attribute already exists
                $attribute_id = wc_attribute_taxonomy_id_by_name($slug);
                
                if (!$attribute_id) {
                    // Create attribute
                    wc_create_attribute(array(
                        'name'         => $attribute['name'],
                        'slug'         => $slug,
                        'type'         => $attribute['type'],
                        'order_by'     => 'menu_order',
                        'has_archives' => false,
                    ));
                    
                    // Register the taxonomy
                    $taxonomy_name = wc_attribute_taxonomy_name($slug);
                    register_taxonomy(
                        $taxonomy_name,
                        apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                        apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
                            'labels'       => array(
                                'name' => $attribute['name'],
                            ),
                            'hierarchical' => true,
                            'show_ui'      => false,
                            'query_var'    => true,
                            'rewrite'      => false,
                        ))
                    );
                    
                    // Add terms
                    foreach ($attribute['options'] as $option) {
                        if (taxonomy_exists($taxonomy_name)) {
                            if (!term_exists($option, $taxonomy_name)) {
                                wp_insert_term($option, $taxonomy_name);
                            }
                        }
                    }
                }
            }
        }
    }
}
