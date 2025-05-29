<?php
/**
 * PolePlace Product
 *
 * Handles product management for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_Product Class
 */
class PolePlace_Product {
    /**
     * Constructor
     */
    public function __construct() {
        // Add seller ID to products
        add_action('woocommerce_process_product_meta', array($this, 'save_product_seller_id'), 10, 2);
        
        // Filter products by seller ID
        add_action('pre_get_posts', array($this, 'filter_products_by_seller'));
        
        // Add seller info to product
        add_action('woocommerce_single_product_summary', array($this, 'display_seller_info'), 25);
        
        // Restrict product editing to product owner and admin
        add_action('current_screen', array($this, 'restrict_product_editing'));
    }

    /**
     * Save product seller ID
     *
     * @param int $product_id Product ID
     * @param WP_Post $post Post object
     */
    public function save_product_seller_id($product_id, $post) {
        // Only set seller ID if it's not already set or if the user is not an admin
        if (!metadata_exists('post', $product_id, '_seller_id') || !current_user_can('manage_woocommerce')) {
            update_post_meta($product_id, '_seller_id', get_current_user_id());
        }
    }

    /**
     * Filter products by seller ID in admin
     *
     * @param WP_Query $query Query object
     */
    public function filter_products_by_seller($query) {
        global $pagenow, $typenow;
        
        // Only filter in admin product list for non-admins
        if (is_admin() && $pagenow == 'edit.php' && $typenow == 'product' && !current_user_can('manage_woocommerce')) {
            $query->set('meta_key', '_seller_id');
            $query->set('meta_value', get_current_user_id());
        }
    }

    /**
     * Display seller info on product page
     */
    public function display_seller_info() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $seller_id = get_post_meta($product->get_id(), '_seller_id', true);
        
        if (!$seller_id) {
            return;
        }
        
        $seller = get_user_by('id', $seller_id);
        
        if (!$seller) {
            return;
        }
        
        echo '<div class="poleplace-seller-info">';
        echo '<h4>' . __('Seller', 'pole-place-marketplace') . '</h4>';
        echo '<p>' . esc_html($seller->display_name) . '</p>';
        echo '</div>';
    }

    /**
     * Restrict product editing to product owner and admin
     */
    public function restrict_product_editing() {
        global $pagenow, $typenow;
        
        // Only check on product edit page
        if (!is_admin() || $pagenow != 'post.php' || $typenow != 'product' || !isset($_GET['post'])) {
            return;
        }
        
        $product_id = absint($_GET['post']);
        $seller_id = get_post_meta($product_id, '_seller_id', true);
        
        // If user is not the seller and not an admin, redirect to products page
        if ($seller_id && $seller_id != get_current_user_id() && !current_user_can('manage_woocommerce')) {
            wp_redirect(admin_url('edit.php?post_type=product'));
            exit;
        }
    }

    /**
     * Create a product
     *
     * @param array $data Product data
     * @return int|WP_Error Product ID or error
     */
    public static function create_product($data) {
        // Check if user can create products
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('You must be logged in to create products.', 'pole-place-marketplace'));
        }
        
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Product name is required.', 'pole-place-marketplace'));
        }
        
        if (!isset($data['regular_price'])) {
            return new WP_Error('missing_price', __('Product price is required.', 'pole-place-marketplace'));
        }
        
        // Create product object
        $product = new WC_Product_Simple();
        
        // Set product data
        $product->set_name($data['name']);
        $product->set_status(isset($data['status']) ? $data['status'] : 'publish');
        $product->set_catalog_visibility('visible');
        $product->set_description(isset($data['description']) ? $data['description'] : '');
        $product->set_short_description(isset($data['short_description']) ? $data['short_description'] : '');
        $product->set_regular_price($data['regular_price']);
        
        if (isset($data['sale_price'])) {
            $product->set_sale_price($data['sale_price']);
        }
        
        if (isset($data['categories'])) {
            $product->set_category_ids($data['categories']);
        }
        
        if (isset($data['images'])) {
            $product->set_image_id($data['images'][0]);
            
            if (count($data['images']) > 1) {
                $product->set_gallery_image_ids(array_slice($data['images'], 1));
            }
        }
        
        // Save product
        $product_id = $product->save();
        
        // Set seller ID
        update_post_meta($product_id, '_seller_id', get_current_user_id());
        
        // Set custom attributes
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            self::set_product_attributes($product_id, $data['attributes']);
        }
        
        return $product_id;
    }

    /**
     * Update a product
     *
     * @param int $product_id Product ID
     * @param array $data Product data
     * @return int|WP_Error Product ID or error
     */
    public static function update_product($product_id, $data) {
        // Check if product exists
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('invalid_product', __('Invalid product.', 'pole-place-marketplace'));
        }
        
        // Check if user can edit this product
        $seller_id = get_post_meta($product_id, '_seller_id', true);
        
        if ($seller_id != get_current_user_id() && !current_user_can('manage_woocommerce')) {
            return new WP_Error('not_authorized', __('You are not authorized to edit this product.', 'pole-place-marketplace'));
        }
        
        // Update product data
        if (isset($data['name'])) {
            $product->set_name($data['name']);
        }
        
        if (isset($data['status'])) {
            $product->set_status($data['status']);
        }
        
        if (isset($data['description'])) {
            $product->set_description($data['description']);
        }
        
        if (isset($data['short_description'])) {
            $product->set_short_description($data['short_description']);
        }
        
        if (isset($data['regular_price'])) {
            $product->set_regular_price($data['regular_price']);
        }
        
        if (isset($data['sale_price'])) {
            $product->set_sale_price($data['sale_price']);
        }
        
        if (isset($data['categories'])) {
            $product->set_category_ids($data['categories']);
        }
        
        if (isset($data['images'])) {
            $product->set_image_id($data['images'][0]);
            
            if (count($data['images']) > 1) {
                $product->set_gallery_image_ids(array_slice($data['images'], 1));
            }
        }
        
        // Save product
        $product_id = $product->save();
        
        // Set custom attributes
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            self::set_product_attributes($product_id, $data['attributes']);
        }
        
        return $product_id;
    }

    /**
     * Delete a product
     *
     * @param int $product_id Product ID
     * @return bool|WP_Error True on success, error on failure
     */
    public static function delete_product($product_id) {
        // Check if product exists
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('invalid_product', __('Invalid product.', 'pole-place-marketplace'));
        }
        
        // Check if user can delete this product
        $seller_id = get_post_meta($product_id, '_seller_id', true);
        
        if ($seller_id != get_current_user_id() && !current_user_can('manage_woocommerce')) {
            return new WP_Error('not_authorized', __('You are not authorized to delete this product.', 'pole-place-marketplace'));
        }
        
        // Delete product
        return $product->delete(true);
    }

    /**
     * Get products by seller ID
     *
     * @param int $seller_id Seller ID
     * @param array $args Query arguments
     * @return array Products
     */
    public static function get_seller_products($seller_id, $args = array()) {
        $default_args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_seller_id',
                    'value' => $seller_id,
                ),
            ),
        );
        
        $args = wp_parse_args($args, $default_args);
        
        $products = array();
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $products[] = wc_get_product(get_the_ID());
            }
        }
        
        wp_reset_postdata();
        
        return $products;
    }

    /**
     * Set product attributes
     *
     * @param int $product_id Product ID
     * @param array $attributes Attributes
     */
    private static function set_product_attributes($product_id, $attributes) {
        $product_attributes = array();
        
        // Handle attributes array format from API
        if (isset($attributes[0]) && is_array($attributes[0])) {
            $formatted_attributes = array();
            foreach ($attributes as $attr) {
                if (isset($attr['name']) && isset($attr['options'])) {
                    $formatted_attributes[$attr['name']] = $attr['options'];
                }
            }
            $attributes = $formatted_attributes;
        }
        
        foreach ($attributes as $name => $value) {
            // Skip empty attributes
            if (empty($value)) {
                continue;
            }
            
            // Get attribute taxonomy
            $taxonomy = wc_attribute_taxonomy_name($name);
            
            if (taxonomy_exists($taxonomy)) {
                // If it's a taxonomy attribute, set it as such
                $term_ids = array();
                
                if (is_array($value)) {
                    foreach ($value as $term) {
                        $term_obj = get_term_by('name', $term, $taxonomy);
                        
                        if ($term_obj) {
                            $term_ids[] = $term_obj->term_id;
                        } else {
                            // Create the term if it doesn't exist
                            $new_term = wp_insert_term($term, $taxonomy);
                            
                            if (!is_wp_error($new_term)) {
                                $term_ids[] = $new_term['term_id'];
                            }
                        }
                    }
                } else {
                    $term_obj = get_term_by('name', $value, $taxonomy);
                    
                    if ($term_obj) {
                        $term_ids[] = $term_obj->term_id;
                    } else {
                        // Create the term if it doesn't exist
                        $new_term = wp_insert_term($value, $taxonomy);
                        
                        if (!is_wp_error($new_term)) {
                            $term_ids[] = $new_term['term_id'];
                        }
                    }
                }
                
                if (!empty($term_ids)) {
                    wp_set_object_terms($product_id, $term_ids, $taxonomy);
                }
                
                $product_attributes[$taxonomy] = array(
                    'name'         => $taxonomy,
                    'value'        => '',
                    'position'     => 0,
                    'is_visible'   => 1,
                    'is_variation' => 0,
                    'is_taxonomy'  => 1,
                );
            } else {
                // If it's a custom attribute, set it as such
                $product_attributes[sanitize_title($name)] = array(
                    'name'         => $name,
                    'value'        => is_array($value) ? implode(', ', $value) : $value,
                    'position'     => 0,
                    'is_visible'   => 1,
                    'is_variation' => 0,
                    'is_taxonomy'  => 0,
                );
            }
        }
        
        update_post_meta($product_id, '_product_attributes', $product_attributes);
    }
}
