<?php
/**
 * PolePlace API Marketplace
 *
 * Handles marketplace-related REST API endpoints for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_API_Marketplace Class
 */
class PolePlace_API_Marketplace {
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
        // Register products endpoint
        register_rest_route('marketplace/v1', '/products', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_products'),
            'permission_callback' => '__return_true',
        ));
        
        // Register product endpoint
        register_rest_route('marketplace/v1', '/products/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_product'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        // Register orders endpoint
        register_rest_route('marketplace/v1', '/orders', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'create_order'),
            'permission_callback' => array('PolePlace_API', 'check_authentication'),
        ));
    }

    /**
     * Get products
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_products($request) {
        // Parse request parameters
        $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;
        $page = $request->get_param('page') ? absint($request->get_param('page')) : 1;
        $category = $request->get_param('category');
        $search = $request->get_param('search');
        $min_price = $request->get_param('min_price');
        $max_price = $request->get_param('max_price');
        $orderby = $request->get_param('orderby') ? $request->get_param('orderby') : 'date';
        $order = $request->get_param('order') ? $request->get_param('order') : 'desc';
        
        // Build query args
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        );
        
        // Add category filter
        if ($category) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }
        
        // Add search filter
        if ($search) {
            $args['s'] = $search;
        }
        
        // Add price filter
        if ($min_price || $max_price) {
            $args['meta_query'] = array('relation' => 'AND');
            
            if ($min_price) {
                $args['meta_query'][] = array(
                    'key'     => '_price',
                    'value'   => $min_price,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                );
            }
            
            if ($max_price) {
                $args['meta_query'][] = array(
                    'key'     => '_price',
                    'value'   => $max_price,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                );
            }
        }
        
        // Get products
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                $products[] = $this->format_product_data($product);
            }
        }
        
        wp_reset_postdata();
        
        // Build response
        $response = array(
            'products' => $products,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
        );
        
        return PolePlace_API::format_success_response($response);
    }

    /**
     * Get product
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_product($request) {
        $product_id = $request['id'];
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_status() !== 'publish') {
            return new WP_Error(
                'poleplace_invalid_product',
                __('Invalid product.', 'pole-place-marketplace'),
                array('status' => 404)
            );
        }
        
        return PolePlace_API::format_success_response(
            $this->format_product_data($product)
        );
    }

    /**
     * Create order
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function create_order($request) {
        $params = $request->get_params();
        
        // Create order
        $order_id = PolePlace_Order::create_order($params);
        
        if (is_wp_error($order_id)) {
            return PolePlace_API::format_error_response($order_id);
        }
        
        $order = wc_get_order($order_id);
        
        return PolePlace_API::format_success_response(
            $this->format_order_data($order),
            201
        );
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
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'on_sale'           => $product->is_on_sale(),
            'purchasable'       => $product->is_purchasable(),
            'total_sales'       => $product->get_total_sales(),
            'average_rating'    => $product->get_average_rating(),
            'rating_count'      => $product->get_rating_count(),
            'categories'        => $categories,
            'images'            => $this->get_product_images($product),
            'attributes'        => $attributes,
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
            'line_items'        => $items,
        );
    }
}
