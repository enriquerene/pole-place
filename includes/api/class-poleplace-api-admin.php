<?php
/**
 * PolePlace API Admin
 *
 * Handles admin-related REST API endpoints for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_API_Admin Class
 */
class PolePlace_API_Admin {
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
        // Register admin stats endpoint
        register_rest_route('marketplace/v1', '/admin/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_admin_stats'),
            'permission_callback' => array('PolePlace_API', 'check_admin_authentication'),
        ));
        
        // Register admin users endpoint
        register_rest_route('marketplace/v1', '/admin/users', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_admin_users'),
            'permission_callback' => array('PolePlace_API', 'check_admin_authentication'),
        ));
        
        // Register admin user endpoint
        register_rest_route('marketplace/v1', '/admin/users/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_admin_user'),
            'permission_callback' => array('PolePlace_API', 'check_admin_authentication'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Get admin stats
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_admin_stats($request) {
        // Get period from request
        $period = $request->get_param('period');
        
        if (!in_array($period, array('day', 'week', 'month', 'year', 'all'))) {
            $period = 'month';
        }
        
        // Get marketplace stats
        $stats = PolePlace_User::get_marketplace_stats($period);
        
        return PolePlace_API::format_success_response($stats);
    }

    /**
     * Get admin users
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_admin_users($request) {
        // Get period from request
        $period = $request->get_param('period');
        
        if (!in_array($period, array('day', 'week', 'month', 'year', 'all'))) {
            $period = 'month';
        }
        
        // Get users with stats
        $users = PolePlace_User::get_all_users_with_stats($period);
        
        return PolePlace_API::format_success_response($users);
    }

    /**
     * Get admin user
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_admin_user($request) {
        $user_id = $request['id'];
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return new WP_Error(
                'poleplace_invalid_user',
                __('Invalid user.', 'pole-place-marketplace'),
                array('status' => 404)
            );
        }
        
        // Get period from request
        $period = $request->get_param('period');
        
        if (!in_array($period, array('day', 'week', 'month', 'year', 'all'))) {
            $period = 'month';
        }
        
        // Get user stats
        $stats = PolePlace_User::get_user_stats($user_id, $period);
        
        // Get user products
        $products = PolePlace_Product::get_seller_products($user_id);
        $product_data = array();
        
        foreach ($products as $product) {
            $product_data[] = $this->format_product_data($product);
        }
        
        // Get user orders
        $orders = PolePlace_Order::get_user_orders($user_id, 'seller', array(
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        
        $order_data = array();
        
        foreach ($orders as $order) {
            $order_data[] = $this->format_order_data($order);
        }
        
        // Build response
        $response = array(
            'id'         => $user->ID,
            'name'       => $user->display_name,
            'email'      => $user->user_email,
            'registered' => $user->user_registered,
            'stats'      => $stats,
            'products'   => $product_data,
            'orders'     => $order_data,
        );
        
        return PolePlace_API::format_success_response($response);
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
        
        return array(
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'permalink'         => get_permalink($product->get_id()),
            'date_created'      => wc_rest_prepare_date_response($product->get_date_created()),
            'status'            => $product->get_status(),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'on_sale'           => $product->is_on_sale(),
            'total_sales'       => $product->get_total_sales(),
            'categories'        => $categories,
            'image'             => wp_get_attachment_url($product->get_image_id()),
        );
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
            );
        }
        
        return array(
            'id'                => $order->get_id(),
            'order_number'      => $order->get_order_number(),
            'date_created'      => wc_rest_prepare_date_response($order->get_date_created()),
            'status'            => $order->get_status(),
            'total'             => $order->get_total(),
            'subtotal'          => $order->get_subtotal(),
            'customer_id'       => $order->get_customer_id(),
            'line_items'        => $items,
        );
    }
}
