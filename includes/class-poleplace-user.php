<?php
/**
 * PolePlace User
 *
 * Handles user-related functionality for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_User Class
 */
class PolePlace_User {
    /**
     * Constructor
     */
    public function __construct() {
        // Add user dashboard page
        add_action('init', array($this, 'register_user_dashboard_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_user_dashboard_menu_item'));
        add_action('woocommerce_account_marketplace-dashboard_endpoint', array($this, 'render_user_dashboard'));
        
        // Add user capabilities
        add_action('init', array($this, 'add_user_capabilities'));
    }

    /**
     * Register user dashboard endpoint
     */
    public function register_user_dashboard_endpoint() {
        add_rewrite_endpoint('marketplace-dashboard', EP_ROOT | EP_PAGES);
    }

    /**
     * Add user dashboard menu item
     *
     * @param array $items Menu items
     * @return array Modified menu items
     */
    public function add_user_dashboard_menu_item($items) {
        // Add marketplace dashboard after dashboard
        $new_items = array();
        
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            
            if ($key === 'dashboard') {
                $new_items['marketplace-dashboard'] = __('Marketplace Dashboard', 'pole-place-marketplace');
            }
        }
        
        return $new_items;
    }

    /**
     * Render user dashboard
     */
    public function render_user_dashboard() {
        $user_id = get_current_user_id();
        
        // Get sales data
        $sales_data = PolePlace_Order::get_user_sales_data($user_id, 'all');
        $recent_sales_data = PolePlace_Order::get_user_sales_data($user_id, 'month');
        
        // Get products
        $products = PolePlace_Product::get_seller_products($user_id);
        
        // Get orders
        $orders = PolePlace_Order::get_user_orders($user_id, 'all', array(
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        
        // Render dashboard
        ?>
        <h2><?php _e('Marketplace Dashboard', 'pole-place-marketplace'); ?></h2>
        
        <div class="poleplace-dashboard-stats">
            <div class="poleplace-dashboard-stat">
                <h3><?php _e('Total Sales', 'pole-place-marketplace'); ?></h3>
                <p><?php echo wc_price($sales_data['total_sales']); ?></p>
            </div>
            
            <div class="poleplace-dashboard-stat">
                <h3><?php _e('Total Orders', 'pole-place-marketplace'); ?></h3>
                <p><?php echo $sales_data['order_count']; ?></p>
            </div>
            
            <div class="poleplace-dashboard-stat">
                <h3><?php _e('Commission Paid', 'pole-place-marketplace'); ?></h3>
                <p><?php echo wc_price($sales_data['commission']); ?></p>
            </div>
            
            <div class="poleplace-dashboard-stat">
                <h3><?php _e('Net Earnings', 'pole-place-marketplace'); ?></h3>
                <p><?php echo wc_price($sales_data['net_earnings']); ?></p>
            </div>
        </div>
        
        <h3><?php _e('Recent Sales (Last 30 Days)', 'pole-place-marketplace'); ?></h3>
        <div class="poleplace-dashboard-stats">
            <div class="poleplace-dashboard-stat">
                <h4><?php _e('Sales', 'pole-place-marketplace'); ?></h4>
                <p><?php echo wc_price($recent_sales_data['total_sales']); ?></p>
            </div>
            
            <div class="poleplace-dashboard-stat">
                <h4><?php _e('Orders', 'pole-place-marketplace'); ?></h4>
                <p><?php echo $recent_sales_data['order_count']; ?></p>
            </div>
            
            <div class="poleplace-dashboard-stat">
                <h4><?php _e('Net Earnings', 'pole-place-marketplace'); ?></h4>
                <p><?php echo wc_price($recent_sales_data['net_earnings']); ?></p>
            </div>
        </div>
        
        <h3><?php _e('Your Products', 'pole-place-marketplace'); ?></h3>
        <?php if (empty($products)) : ?>
            <p><?php _e('You have not created any products yet.', 'pole-place-marketplace'); ?></p>
            <p><a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button"><?php _e('Add Product', 'pole-place-marketplace'); ?></a></p>
        <?php else : ?>
            <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Price', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Category', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Status', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Actions', 'pole-place-marketplace'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_permalink($product->get_id()); ?>">
                                    <?php echo $product->get_name(); ?>
                                </a>
                            </td>
                            <td><?php echo $product->get_price_html(); ?></td>
                            <td>
                                <?php
                                $categories = get_the_terms($product->get_id(), 'product_cat');
                                if ($categories) {
                                    $category_names = array();
                                    foreach ($categories as $category) {
                                        $category_names[] = $category->name;
                                    }
                                    echo implode(', ', $category_names);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo wc_get_product_stock_status_options()[$product->get_stock_status()]; ?></td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $product->get_id() . '&action=edit'); ?>" class="button"><?php _e('Edit', 'pole-place-marketplace'); ?></a>
                                <a href="<?php echo get_delete_post_link($product->get_id()); ?>" class="button"><?php _e('Delete', 'pole-place-marketplace'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button"><?php _e('Add Product', 'pole-place-marketplace'); ?></a></p>
        <?php endif; ?>
        
        <h3><?php _e('Recent Orders', 'pole-place-marketplace'); ?></h3>
        <?php if (empty($orders)) : ?>
            <p><?php _e('You have not received any orders yet.', 'pole-place-marketplace'); ?></p>
        <?php else : ?>
            <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Date', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Status', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Total', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Actions', 'pole-place-marketplace'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo $order->get_view_order_url(); ?>">
                                    <?php echo '#' . $order->get_order_number(); ?>
                                </a>
                            </td>
                            <td><?php echo wc_format_datetime($order->get_date_created()); ?></td>
                            <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                            <td><?php echo $order->get_formatted_order_total(); ?></td>
                            <td>
                                <a href="<?php echo $order->get_view_order_url(); ?>" class="button"><?php _e('View', 'pole-place-marketplace'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    /**
     * Add user capabilities
     */
    public function add_user_capabilities() {
        // Get customer role
        $customer = get_role('customer');
        
        if ($customer) {
            // Add capability to create and manage products
            $customer->add_cap('edit_products');
            $customer->add_cap('delete_products');
            $customer->add_cap('publish_products');
            $customer->add_cap('edit_published_products');
            $customer->add_cap('delete_published_products');
        }
    }

    /**
     * Get user stats
     *
     * @param int $user_id User ID
     * @param string $period Period (day, week, month, year, or all)
     * @return array User stats
     */
    public static function get_user_stats($user_id, $period = 'all') {
        // Get sales data
        $sales_data = PolePlace_Order::get_user_sales_data($user_id, $period);
        
        // Get products
        $products = PolePlace_Product::get_seller_products($user_id, array(
            'post_status' => 'publish',
        ));
        
        // Get orders
        $orders = PolePlace_Order::get_user_orders($user_id, 'seller', array(
            'post_status' => array('wc-completed'),
        ));
        
        // Calculate average order value
        $average_order_value = 0;
        
        if ($sales_data['order_count'] > 0) {
            $average_order_value = $sales_data['total_sales'] / $sales_data['order_count'];
        }
        
        // Calculate order frequency (orders per month)
        $order_frequency = 0;
        
        if (!empty($orders)) {
            $first_order = end($orders);
            $first_order_date = $first_order->get_date_created();
            
            if ($first_order_date) {
                $now = new DateTime();
                $diff = $now->diff($first_order_date);
                $months = $diff->y * 12 + $diff->m;
                
                if ($months > 0) {
                    $order_frequency = count($orders) / $months;
                } else {
                    $order_frequency = count($orders);
                }
            }
        }
        
        // Return stats
        return array(
            'total_sales'         => $sales_data['total_sales'],
            'order_count'         => $sales_data['order_count'],
            'commission'          => $sales_data['commission'],
            'net_earnings'        => $sales_data['net_earnings'],
            'product_count'       => count($products),
            'average_order_value' => $average_order_value,
            'order_frequency'     => $order_frequency,
        );
    }

    /**
     * Get all users with stats
     *
     * @param string $period Period (day, week, month, year, or all)
     * @return array Users with stats
     */
    public static function get_all_users_with_stats($period = 'all') {
        // Get all users
        $users = get_users(array(
            'role__in' => array('customer', 'subscriber'),
        ));
        
        $users_with_stats = array();
        
        foreach ($users as $user) {
            $stats = self::get_user_stats($user->ID, $period);
            
            // Only include users with sales
            if ($stats['total_sales'] > 0) {
                $users_with_stats[] = array(
                    'id'                  => $user->ID,
                    'name'                => $user->display_name,
                    'email'               => $user->user_email,
                    'total_sales'         => $stats['total_sales'],
                    'order_count'         => $stats['order_count'],
                    'commission'          => $stats['commission'],
                    'net_earnings'        => $stats['net_earnings'],
                    'product_count'       => $stats['product_count'],
                    'average_order_value' => $stats['average_order_value'],
                    'order_frequency'     => $stats['order_frequency'],
                );
            }
        }
        
        return $users_with_stats;
    }

    /**
     * Get marketplace stats
     *
     * @param string $period Period (day, week, month, year, or all)
     * @return array Marketplace stats
     */
    public static function get_marketplace_stats($period = 'all') {
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
        
        // Get total sales
        $total_sales = $wpdb->get_var("
            SELECT SUM(meta_value)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_order_total'
            AND post_id IN (
                SELECT ID
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'shop_order'
                AND p.post_status = 'wc-completed'
                {$date_query}
            )
        ");
        
        // Get total orders
        $total_orders = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            {$date_query}
        ");
        
        // Get total commission
        $total_commission = PolePlace_Commission::get_total_commission_amount(array(
            'status' => 'completed',
            'period' => $period,
        ));
        
        // Get active sellers
        $active_sellers = $wpdb->get_var("
            SELECT COUNT(DISTINCT meta_value)
            FROM {$wpdb->prefix}woocommerce_order_itemmeta
            WHERE meta_key = '_seller_id'
            AND order_item_id IN (
                SELECT order_item_id
                FROM {$wpdb->prefix}woocommerce_order_items
                WHERE order_id IN (
                    SELECT ID
                    FROM {$wpdb->posts} p
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status = 'wc-completed'
                    {$date_query}
                )
            )
        ");
        
        // Get total products
        $total_products = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            {$date_query}
        ");
        
        // Return stats
        return array(
            'total_sales'      => (float) $total_sales,
            'total_orders'     => (int) $total_orders,
            'total_commission' => (float) $total_commission,
            'active_sellers'   => (int) $active_sellers,
            'total_products'   => (int) $total_products,
        );
    }
}
