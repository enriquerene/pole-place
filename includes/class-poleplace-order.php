<?php
/**
 * PolePlace Order
 *
 * Handles order processing for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_Order Class
 */
class PolePlace_Order {
    /**
     * Constructor
     */
    public function __construct() {
        // Process order status changes
        add_action('woocommerce_order_status_completed', array($this, 'process_completed_order'));
        add_action('woocommerce_order_status_refunded', array($this, 'process_refunded_order'));
        add_action('woocommerce_order_status_cancelled', array($this, 'process_cancelled_order'));
        
        // Add order item meta
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_seller_id_to_order_item'), 10, 4);
        
        // Restrict order viewing to order owner, seller, and admin
        add_action('current_screen', array($this, 'restrict_order_viewing'));
        
        // Filter orders by seller ID
        add_action('pre_get_posts', array($this, 'filter_orders_by_seller'));
    }

    /**
     * Process completed order
     *
     * @param int $order_id Order ID
     */
    public function process_completed_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Process each order item
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $seller_id = $item->get_meta('_seller_id', true);
            
            if (!$seller_id) {
                $seller_id = get_post_meta($product_id, '_seller_id', true);
            }
            
            if (!$seller_id) {
                continue;
            }
            
            // Calculate commission
            $item_total = $item->get_total();
            $commission_amount = $item_total * POLEPLACE_COMMISSION_RATE;
            
            // Create commission record
            PolePlace_Commission::create_commission(array(
                'order_id'   => $order_id,
                'product_id' => $product_id,
                'seller_id'  => $seller_id,
                'buyer_id'   => $order->get_customer_id(),
                'amount'     => $commission_amount,
                'status'     => 'completed',
            ));
        }
    }

    /**
     * Process refunded order
     *
     * @param int $order_id Order ID
     */
    public function process_refunded_order($order_id) {
        // Update commission status to refunded
        PolePlace_Commission::update_commission_status($order_id, 'refunded');
    }

    /**
     * Process cancelled order
     *
     * @param int $order_id Order ID
     */
    public function process_cancelled_order($order_id) {
        // Update commission status to cancelled
        PolePlace_Commission::update_commission_status($order_id, 'cancelled');
    }

    /**
     * Add seller ID to order item
     *
     * @param WC_Order_Item_Product $item Order item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function add_seller_id_to_order_item($item, $cart_item_key, $values, $order) {
        $product_id = $item->get_product_id();
        $seller_id = get_post_meta($product_id, '_seller_id', true);
        
        if ($seller_id) {
            $item->add_meta_data('_seller_id', $seller_id, true);
        }
    }

    /**
     * Restrict order viewing to order owner, seller, and admin
     */
    public function restrict_order_viewing() {
        global $pagenow, $typenow;
        
        // Only check on order edit page
        if (!is_admin() || $pagenow != 'post.php' || $typenow != 'shop_order' || !isset($_GET['post'])) {
            return;
        }
        
        $order_id = absint($_GET['post']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $current_user_id = get_current_user_id();
        $is_seller = false;
        
        // Check if current user is a seller of any product in the order
        foreach ($order->get_items() as $item) {
            $seller_id = $item->get_meta('_seller_id', true);
            
            if (!$seller_id) {
                $product_id = $item->get_product_id();
                $seller_id = get_post_meta($product_id, '_seller_id', true);
            }
            
            if ($seller_id == $current_user_id) {
                $is_seller = true;
                break;
            }
        }
        
        // If user is not the buyer, not a seller, and not an admin, redirect to orders page
        if ($order->get_customer_id() != $current_user_id && !$is_seller && !current_user_can('manage_woocommerce')) {
            wp_redirect(admin_url('edit.php?post_type=shop_order'));
            exit;
        }
    }

    /**
     * Filter orders by seller ID in admin
     *
     * @param WP_Query $query Query object
     */
    public function filter_orders_by_seller($query) {
        global $pagenow, $typenow;
        
        // Only filter in admin order list for non-admins
        if (is_admin() && $pagenow == 'edit.php' && $typenow == 'shop_order' && !current_user_can('manage_woocommerce')) {
            $current_user_id = get_current_user_id();
            
            // Get orders where current user is a seller
            $seller_orders = self::get_seller_order_ids($current_user_id);
            
            // Get orders where current user is a buyer
            $buyer_orders = self::get_buyer_order_ids($current_user_id);
            
            // Combine seller and buyer orders
            $order_ids = array_unique(array_merge($seller_orders, $buyer_orders));
            
            if (empty($order_ids)) {
                // If no orders found, set a non-existent post ID to ensure no results
                $query->set('post__in', array(0));
            } else {
                $query->set('post__in', $order_ids);
            }
        }
    }

    /**
     * Get order IDs where user is a seller
     *
     * @param int $user_id User ID
     * @return array Order IDs
     */
    public static function get_seller_order_ids($user_id) {
        global $wpdb;
        
        // Get order IDs from order item meta
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT order_id
            FROM {$wpdb->prefix}woocommerce_order_itemmeta
            WHERE meta_key = '_seller_id'
            AND meta_value = %d
        ", $user_id));
        
        return $order_ids;
    }

    /**
     * Get order IDs where user is a buyer
     *
     * @param int $user_id User ID
     * @return array Order IDs
     */
    public static function get_buyer_order_ids($user_id) {
        global $wpdb;
        
        // Get order IDs from order meta
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_customer_user'
            AND meta_value = %d
        ", $user_id));
        
        return $order_ids;
    }

    /**
     * Create an order
     *
     * @param array $data Order data
     * @return int|WP_Error Order ID or error
     */
    public static function create_order($data) {
        // Check if user can create orders
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', __('You must be logged in to create orders.', 'pole-place-marketplace'));
        }
        
        // Validate required fields
        if (empty($data['products'])) {
            return new WP_Error('missing_products', __('Products are required.', 'pole-place-marketplace'));
        }
        
        // Create order
        $order = wc_create_order(array(
            'customer_id' => get_current_user_id(),
            'status'      => 'pending',
        ));
        
        if (is_wp_error($order)) {
            return $order;
        }
        
        // Add products to order
        foreach ($data['products'] as $product_data) {
            $product_id = $product_data['id'];
            $quantity = isset($product_data['quantity']) ? $product_data['quantity'] : 1;
            
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            // Check if user is trying to buy their own product
            $seller_id = get_post_meta($product_id, '_seller_id', true);
            
            if ($seller_id == get_current_user_id()) {
                return new WP_Error('own_product', __('You cannot buy your own product.', 'pole-place-marketplace'));
            }
            
            $item_id = $order->add_product($product, $quantity);
            
            if (!$item_id) {
                continue;
            }
            
            // Add seller ID to order item
            wc_add_order_item_meta($item_id, '_seller_id', $seller_id);
        }
        
        // Set billing and shipping address
        if (isset($data['billing'])) {
            $order->set_address($data['billing'], 'billing');
        }
        
        if (isset($data['shipping'])) {
            $order->set_address($data['shipping'], 'shipping');
        }
        
        // Set payment method
        if (isset($data['payment_method'])) {
            $order->set_payment_method($data['payment_method']);
        }
        
        // Calculate totals
        $order->calculate_totals();
        
        // Save order
        $order_id = $order->save();
        
        return $order_id;
    }

    /**
     * Get orders by user ID
     *
     * @param int $user_id User ID
     * @param string $type Order type (buyer, seller, or all)
     * @param array $args Query arguments
     * @return array Orders
     */
    public static function get_user_orders($user_id, $type = 'all', $args = array()) {
        $order_ids = array();
        
        if ($type == 'buyer' || $type == 'all') {
            $buyer_orders = self::get_buyer_order_ids($user_id);
            $order_ids = array_merge($order_ids, $buyer_orders);
        }
        
        if ($type == 'seller' || $type == 'all') {
            $seller_orders = self::get_seller_order_ids($user_id);
            $order_ids = array_merge($order_ids, $seller_orders);
        }
        
        $order_ids = array_unique($order_ids);
        
        if (empty($order_ids)) {
            return array();
        }
        
        $default_args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array_keys(wc_get_order_statuses()),
            'posts_per_page' => -1,
            'post__in'       => $order_ids,
        );
        
        $args = wp_parse_args($args, $default_args);
        
        $orders = array();
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $orders[] = wc_get_order(get_the_ID());
            }
        }
        
        wp_reset_postdata();
        
        return $orders;
    }

    /**
     * Get user sales data
     *
     * @param int $user_id User ID
     * @param string $period Period (day, week, month, year, or all)
     * @return array Sales data
     */
    public static function get_user_sales_data($user_id, $period = 'all') {
        global $wpdb;
        
        $date_query = '';
        
        // Set date query based on period
        if ($period != 'all') {
            $date = new DateTime();
            
            switch ($period) {
                case 'day':
                    $date->modify('-1 day');
                    break;
                case 'week':
                    $date->modify('-1 week');
                    break;
                case 'month':
                    $date->modify('-1 month');
                    break;
                case 'year':
                    $date->modify('-1 year');
                    break;
            }
            
            $date_query = $wpdb->prepare("AND p.post_date >= %s", $date->format('Y-m-d H:i:s'));
        }
        
        // Get sales data
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT p.ID) as order_count,
                SUM(oim.meta_value) as total_sales
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_line_total'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_seller_id' AND oim2.meta_value = %d
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            {$date_query}
        ", $user_id));
        
        $sales_data = array(
            'order_count'  => 0,
            'total_sales'  => 0,
            'commission'   => 0,
            'net_earnings' => 0,
        );
        
        if ($results && isset($results[0])) {
            $sales_data['order_count'] = (int) $results[0]->order_count;
            $sales_data['total_sales'] = (float) $results[0]->total_sales;
            $sales_data['commission'] = $sales_data['total_sales'] * POLEPLACE_COMMISSION_RATE;
            $sales_data['net_earnings'] = $sales_data['total_sales'] - $sales_data['commission'];
        }
        
        return $sales_data;
    }
}
