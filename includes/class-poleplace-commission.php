<?php
/**
 * PolePlace Commission
 *
 * Handles commission calculations and tracking for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_Commission Class
 */
class PolePlace_Commission {
    /**
     * Constructor
     */
    public function __construct() {
        // Add commission reports page
        add_action('admin_menu', array($this, 'add_commission_reports_page'), 20);
    }

    /**
     * Add commission reports page
     */
    public function add_commission_reports_page() {
        // Only add for administrators
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            __('Commission Reports', 'pole-place-marketplace'),
            __('Commission Reports', 'pole-place-marketplace'),
            'view_poleplace_commissions',
            'poleplace-commissions',
            array($this, 'render_commission_reports_page')
        );
    }

    /**
     * Render commission reports page
     */
    public function render_commission_reports_page() {
        // Get commissions
        $commissions = self::get_commissions();
        
        // Calculate totals
        $total_commission = 0;
        $total_orders = 0;
        
        foreach ($commissions as $commission) {
            if ($commission->status == 'completed') {
                $total_commission += $commission->amount;
                $total_orders++;
            }
        }
        
        // Render page
        ?>
        <div class="wrap">
            <h1><?php _e('Commission Reports', 'pole-place-marketplace'); ?></h1>
            
            <div class="poleplace-commission-summary">
                <div class="poleplace-commission-total">
                    <h2><?php _e('Total Commission', 'pole-place-marketplace'); ?></h2>
                    <p><?php echo wc_price($total_commission); ?></p>
                </div>
                
                <div class="poleplace-commission-orders">
                    <h2><?php _e('Total Orders', 'pole-place-marketplace'); ?></h2>
                    <p><?php echo $total_orders; ?></p>
                </div>
            </div>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Product', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Seller', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Buyer', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Amount', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Status', 'pole-place-marketplace'); ?></th>
                        <th><?php _e('Date', 'pole-place-marketplace'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commissions as $commission) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $commission->order_id . '&action=edit'); ?>">
                                    <?php echo '#' . $commission->order_id; ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $commission->product_id . '&action=edit'); ?>">
                                    <?php echo get_the_title($commission->product_id); ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                $seller = get_user_by('id', $commission->seller_id);
                                echo $seller ? $seller->display_name : __('Unknown', 'pole-place-marketplace');
                                ?>
                            </td>
                            <td>
                                <?php
                                $buyer = get_user_by('id', $commission->buyer_id);
                                echo $buyer ? $buyer->display_name : __('Unknown', 'pole-place-marketplace');
                                ?>
                            </td>
                            <td><?php echo wc_price($commission->amount); ?></td>
                            <td><?php echo ucfirst($commission->status); ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($commission->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Create a commission
     *
     * @param array $data Commission data
     * @return int|WP_Error Commission ID or error
     */
    public static function create_commission($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['order_id'])) {
            return new WP_Error('missing_order_id', __('Order ID is required.', 'pole-place-marketplace'));
        }
        
        if (empty($data['product_id'])) {
            return new WP_Error('missing_product_id', __('Product ID is required.', 'pole-place-marketplace'));
        }
        
        if (empty($data['seller_id'])) {
            return new WP_Error('missing_seller_id', __('Seller ID is required.', 'pole-place-marketplace'));
        }
        
        if (empty($data['buyer_id'])) {
            return new WP_Error('missing_buyer_id', __('Buyer ID is required.', 'pole-place-marketplace'));
        }
        
        if (!isset($data['amount'])) {
            return new WP_Error('missing_amount', __('Amount is required.', 'pole-place-marketplace'));
        }
        
        // Set default status if not provided
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        
        // Insert commission
        $result = $wpdb->insert(
            $wpdb->prefix . 'poleplace_commissions',
            array(
                'order_id'   => $data['order_id'],
                'product_id' => $data['product_id'],
                'seller_id'  => $data['seller_id'],
                'buyer_id'   => $data['buyer_id'],
                'amount'     => $data['amount'],
                'status'     => $data['status'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return new WP_Error('commission_creation_failed', __('Failed to create commission.', 'pole-place-marketplace'));
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Update commission status
     *
     * @param int $order_id Order ID
     * @param string $status Status
     * @return bool True on success, false on failure
     */
    public static function update_commission_status($order_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'poleplace_commissions',
            array(
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ),
            array('order_id' => $order_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }

    /**
     * Get commissions
     *
     * @param array $args Query arguments
     * @return array Commissions
     */
    public static function get_commissions($args = array()) {
        global $wpdb;
        
        $default_args = array(
            'order_id'  => 0,
            'seller_id' => 0,
            'buyer_id'  => 0,
            'status'    => '',
            'limit'     => -1,
            'offset'    => 0,
            'orderby'   => 'created_at',
            'order'     => 'DESC',
        );
        
        $args = wp_parse_args($args, $default_args);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['order_id']) {
            $where[] = 'order_id = %d';
            $values[] = $args['order_id'];
        }
        
        if ($args['seller_id']) {
            $where[] = 'seller_id = %d';
            $values[] = $args['seller_id'];
        }
        
        if ($args['buyer_id']) {
            $where[] = 'buyer_id = %d';
            $values[] = $args['buyer_id'];
        }
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        $limit = '';
        
        if ($args['limit'] > 0) {
            $limit = 'LIMIT %d';
            $values[] = $args['limit'];
            
            if ($args['offset'] > 0) {
                $limit .= ' OFFSET %d';
                $values[] = $args['offset'];
            }
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $query = "
            SELECT *
            FROM {$wpdb->prefix}poleplace_commissions
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderby}
            {$limit}
        ";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * Get commission by ID
     *
     * @param int $commission_id Commission ID
     * @return object|null Commission object or null
     */
    public static function get_commission($commission_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}poleplace_commissions
            WHERE id = %d
        ", $commission_id));
    }

    /**
     * Get total commission amount
     *
     * @param array $args Query arguments
     * @return float Total commission amount
     */
    public static function get_total_commission_amount($args = array()) {
        global $wpdb;
        
        $default_args = array(
            'seller_id' => 0,
            'status'    => 'completed',
            'period'    => 'all',
        );
        
        $args = wp_parse_args($args, $default_args);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['seller_id']) {
            $where[] = 'seller_id = %d';
            $values[] = $args['seller_id'];
        }
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        // Set date query based on period
        if ($args['period'] != 'all') {
            $date = new DateTime();
            
            switch ($args['period']) {
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
            
            $where[] = 'created_at >= %s';
            $values[] = $date->format('Y-m-d H:i:s');
        }
        
        $query = "
            SELECT SUM(amount) as total
            FROM {$wpdb->prefix}poleplace_commissions
            WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $result = $wpdb->get_var($query);
        
        return (float) $result;
    }
}
