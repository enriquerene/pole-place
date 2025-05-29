<?php
/**
 * PolePlace API
 *
 * Handles REST API functionality for the PolePlace Marketplace
 *
 * @package PolePlace
 */

defined('ABSPATH') || exit;

/**
 * PolePlace_API Class
 */
class PolePlace_API {
    /**
     * Constructor
     */
    public function __construct() {
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));
        
        // Initialize API classes
        new PolePlace_API_User();
        new PolePlace_API_Marketplace();
        new PolePlace_API_Admin();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Register JWT authentication
        $this->register_jwt_authentication();
    }

    /**
     * Register JWT authentication
     */
    private function register_jwt_authentication() {
        // Check if JWT Authentication for WP REST API is active
        if (!class_exists('Jwt_Auth_Public')) {
            // Add notice if JWT Authentication plugin is not active
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning">
                    <p><?php _e('PolePlace Marketplace recommends using JWT Authentication for WP REST API plugin for secure API authentication.', 'pole-place-marketplace'); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Check if user is authenticated
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if user is authenticated, error otherwise
     */
    public static function check_authentication($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error(
                'poleplace_not_authenticated',
                __('You must be authenticated to access this endpoint.', 'pole-place-marketplace'),
                array('status' => 401)
            );
        }
        
        return true;
    }

    /**
     * Check if user is admin
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if user is admin, error otherwise
     */
    public static function check_admin_authentication($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error(
                'poleplace_not_authenticated',
                __('You must be authenticated to access this endpoint.', 'pole-place-marketplace'),
                array('status' => 401)
            );
        }
        
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'poleplace_not_authorized',
                __('You must be an administrator to access this endpoint.', 'pole-place-marketplace'),
                array('status' => 403)
            );
        }
        
        return true;
    }

    /**
     * Format error response
     *
     * @param WP_Error $error Error object
     * @param int $status Status code
     * @return WP_REST_Response Error response
     */
    public static function format_error_response($error, $status = 400) {
        return new WP_REST_Response(array(
            'success' => false,
            'error'   => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ), $status);
    }

    /**
     * Format success response
     *
     * @param mixed $data Response data
     * @param int $status Status code
     * @return WP_REST_Response Success response
     */
    public static function format_success_response($data, $status = 200) {
        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ), $status);
    }
}
