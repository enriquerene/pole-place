<?php
/**
 * PolePlace API User
 *
 * Handles user-related REST API endpoints for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_API_User Class
 */
class PolePlace_API_User {
    /**
     * Constructor
     */
    public function __construct() {
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Register user products endpoint
        register_rest_route('marketplace/v1', '/user/products', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_user_products'),
                'permission_callback' => array('PolePlace_API', 'check_authentication'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_user_product'),
                'permission_callback' => array('PolePlace_API', 'check_authentication'),
            ),
        ));
        
        // Register user product endpoint
        register_rest_route('marketplace/v1', '/user/products/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_user_product'),
                'permission_callback' => array('PolePlace_API', 'check_authentication'),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_user_product'),
                'permission_callback' => array('PolePlace_API', 'check_authentication'),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ),
                ),
            ),
        ));
        
        // Register user stats endpoint
        register_rest_route('marketplace/v1', '/user/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_user_stats'),
            'permission_callback' => array('PolePlace_API', 'check_authentication'),
        ));
    }

    /**
     * Get user products
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_user_products($request) {
        $user_id = get_current_user_id();
        
        // Get products
        $products = PolePlace_Product::get_seller_products($user_id);
        
        $response_data = array();
        
        foreach ($products as $product) {
            $response_data[] = $this->format_product_data($product);
        }
        
        return PolePlace_API::format_success_response($response_data);
    }

    /**
     * Create user product
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function create_user_product($request) {
        $params = $request->get_params();
        
        // Ensure product is published by default
        if (!isset($params['status'])) {
            $params['status'] = 'publish';
        }
        
        // Create product
        $product_id = PolePlace_Product::create_product($params);
        
        if (is_wp_error($product_id)) {
            return PolePlace_API::format_error_response($product_id);
        }
        
        // Ensure seller ID is set
        update_post_meta($product_id, '_seller_id', get_current_user_id());
        
        $product = wc_get_product($product_id);
        
        return PolePlace_API::format_success_response(
            $this->format_product_data($product),
            201
        );
    }

    /**
     * Update user product
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function update_user_product($request) {
        $product_id = $request['id'];
        $params = $request->get_params();
        
        // Update product
        $result = PolePlace_Product::update_product($product_id, $params);
        
        if (is_wp_error($result)) {
            return PolePlace_API::format_error_response($result);
        }
        
        $product = wc_get_product($product_id);
        
        return PolePlace_API::format_success_response(
            $this->format_product_data($product)
        );
    }

    /**
     * Delete user product
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function delete_user_product($request) {
        $product_id = $request['id'];
        
        // Delete product
        $result = PolePlace_Product::delete_product($product_id);
        
        if (is_wp_error($result)) {
            return PolePlace_API::format_error_response($result);
        }
        
        return PolePlace_API::format_success_response(
            array('id' => $product_id)
        );
    }

    /**
     * Get user stats
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_user_stats($request) {
        $user_id = get_current_user_id();
        
        // Get period from request
        $period = $request->get_param('period');
        
        if (!in_array($period, array('day', 'week', 'month', 'year', 'all'))) {
            $period = 'month';
        }
        
        // Get user stats
        $stats = PolePlace_User::get_user_stats($user_id, $period);
        
        // Get user products count
        $products = PolePlace_Product::get_seller_products($user_id, array(
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        
        $stats['products_count'] = count($products);
        
        return PolePlace_API::format_success_response($stats);
    }

    /**
     * Format product data for API response
     *
     * @param WC_Product $product Product object
     * @return array Formatted product data
     */
    private function format_product_data($product) {
        $categories = array();
        $category_ids = $product->get_category_ids();
        
        if (!empty($category_ids)) {
            foreach ($category_ids as $category_id) {
                $category = get_term_by('id', $category_id, 'product_cat');
                
                if ($category) {
                    $categories[] = array(
                        'id'   => $category_id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    );
                }
            }
        }
        
        $attributes = array();
        $product_attributes = $product->get_attributes();
        
        if (!empty($product_attributes)) {
            foreach ($product_attributes as $attribute_name => $attribute) {
                $attribute_data = array(
                    'name'   => wc_attribute_label($attribute_name),
                    'values' => array(),
                );
                
                if ($attribute->is_taxonomy()) {
                    $attribute_taxonomy = $attribute->get_taxonomy_object();
                    $attribute_values = $attribute->get_terms();
                    
                    if (!empty($attribute_values)) {
                        foreach ($attribute_values as $attribute_value) {
                            $attribute_data['values'][] = $attribute_value->name;
                        }
                    }
                } else {
                    $attribute_data['values'] = $attribute->get_options();
                }
                
                $attributes[] = $attribute_data;
            }
        }
        
        // Get seller info
        $seller_id = get_post_meta($product->get_id(), '_seller_id', true);
        
        // Fallback to post author if no seller ID is set
        if (!$seller_id) {
            $post_data = get_post($product->get_id());
            $seller_id = $post_data->post_author;
        }
        
        $seller = get_user_by('id', $seller_id);
        $seller_info = array(
            'id'   => $seller_id ? $seller_id : 0,
            'name' => $seller ? $seller->display_name : __('Unknown', 'pole-place-marketplace'),
            'email' => $seller ? $seller->user_email : '',
            'registered_date' => $seller ? $seller->user_registered : '',
            'store_url' => $seller ? get_author_posts_url($seller_id) : '',
        );
        
        return array(
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'permalink'         => get_permalink($product->get_id()),
            'date_created'      => wc_rest_prepare_date_response($product->get_date_created()),
            'date_modified'     => wc_rest_prepare_date_response($product->get_date_modified()),
            'status'            => $product->get_status(),
            'featured'          => $product->is_featured(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'on_sale'           => $product->is_on_sale(),
            'purchasable'       => $product->is_purchasable(),
            'total_sales'       => $product->get_total_sales(),
            'virtual'           => $product->is_virtual(),
            'downloadable'      => $product->is_downloadable(),
            'downloads'         => $product->get_downloads(),
            'download_limit'    => $product->get_download_limit(),
            'download_expiry'   => $product->get_download_expiry(),
            'tax_status'        => $product->get_tax_status(),
            'tax_class'         => $product->get_tax_class(),
            'manage_stock'      => $product->managing_stock(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'stock_status'      => $product->get_stock_status(),
            'backorders'        => $product->get_backorders(),
            'backorders_allowed' => $product->backorders_allowed(),
            'backordered'       => $product->is_on_backorder(),
            'sold_individually' => $product->is_sold_individually(),
            'weight'            => $product->get_weight(),
            'dimensions'        => array(
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
            ),
            'shipping_required' => $product->needs_shipping(),
            'shipping_taxable'  => $product->is_shipping_taxable(),
            'shipping_class'    => $product->get_shipping_class(),
            'shipping_class_id' => $product->get_shipping_class_id(),
            'reviews_allowed'   => $product->get_reviews_allowed(),
            'average_rating'    => $product->get_average_rating(),
            'rating_count'      => $product->get_rating_count(),
            'related_ids'       => $product->get_related(),
            'upsell_ids'        => $product->get_upsell_ids(),
            'cross_sell_ids'    => $product->get_cross_sell_ids(),
            'parent_id'         => $product->get_parent_id(),
            'purchase_note'     => $product->get_purchase_note(),
            'categories'        => $categories,
            'tags'              => array(),
            'images'            => $this->get_product_images($product),
            'attributes'        => $attributes,
            'default_attributes' => $product->get_default_attributes(),
            'variations'        => array(),
            'grouped_products'  => array(),
            'menu_order'        => $product->get_menu_order(),
            'seller'            => $seller_info,
        );
    }

    /**
     * Get product images
     *
     * @param WC_Product $product Product object
     * @return array Product images
     */
    private function get_product_images($product) {
        $images = array();
        $attachment_ids = array();
        
        // Add featured image
        if ($product->get_image_id()) {
            $attachment_ids[] = $product->get_image_id();
        }
        
        // Add gallery images
        $attachment_ids = array_merge($attachment_ids, $product->get_gallery_image_ids());
        
        // Get image data
        foreach ($attachment_ids as $attachment_id) {
            $attachment = wp_get_attachment_image_src($attachment_id, 'full');
            
            if (!$attachment) {
                continue;
            }
            
            $images[] = array(
                'id'   => (int) $attachment_id,
                'src'  => $attachment[0],
                'name' => get_the_title($attachment_id),
                'alt'  => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            );
        }
        
        return $images;
    }

    /**
     * Format order data for API response
     *
     * @param WC_Order $order Order object
     * @return array Formatted order data
     */
    private function format_order_data($order) {
        $items = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $item->get_product_id();
            
            $items[] = array(
                'id'           => $item_id,
                'product_id'   => $product_id,
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'tax'          => $item->get_total_tax(),
                'seller_id'    => $item->get_meta('_seller_id'),
                'product_url'  => $product ? get_permalink($product_id) : '',
                'product_image' => $product ? wp_get_attachment_url($product->get_image_id()) : '',
            );
        }
        
        return array(
            'id'                => $order->get_id(),
            'order_number'      => $order->get_order_number(),
            'date_created'      => wc_rest_prepare_date_response($order->get_date_created()),
            'status'            => $order->get_status(),
            'total'             => $order->get_total(),
            'subtotal'          => $order->get_subtotal(),
            'total_tax'         => $order->get_total_tax(),
            'shipping_total'    => $order->get_shipping_total(),
            'payment_method'    => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id'    => $order->get_transaction_id(),
            'customer_id'       => $order->get_customer_id(),
            'customer_note'     => $order->get_customer_note(),
            'billing'           => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'company'    => $order->get_billing_company(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
            ),
            'shipping'          => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name'  => $order->get_shipping_last_name(),
                'company'    => $order->get_shipping_company(),
                'address_1'  => $order->get_shipping_address_1(),
                'address_2'  => $order->get_shipping_address_2(),
                'city'       => $order->get_shipping_city(),
                'state'      => $order->get_shipping_state(),
                'postcode'   => $order->get_shipping_postcode(),
                'country'    => $order->get_shipping_country(),
            ),
            'line_items'        => $items,
        );
    }
}
