<?php
/**
 * Plugin Name: Custom Field Auditor
 * Description: Advanced tracking and versioning for custom post meta fields.
 * Version: 1.0.1
 * Author: Manoj Mohan
 * Author URI: https://manojmohan.dev
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'CFA_VERSION', '1.0.1' );
define( 'CFA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CFA_URL', plugin_dir_url( __FILE__ ) );

// Modular Includes
require_once CFA_PATH . 'includes/class-cfa-core.php';
require_once CFA_PATH . 'includes/class-cfa-admin.php';

class Custom_Field_Auditor_Init {

    private $core;
    private $admin;

    public function __construct() {
        // Initialize Core Logic
        $this->core = new Custom_Field_Auditor_Core();

        // Initialize Admin Logic
        if ( is_admin() ) {
            $this->admin = new Custom_Field_Auditor_Admin( $this->core );
            
            // Shared Admin Assets/Scripts
            add_action( 'wp_ajax_get_classic_revisions_html', array( $this, 'ajax_get_classic_revisions_html' ) );
            add_action( 'admin_init', array( $this, 'handle_ui_optimization' ) );
        }
    }

    public function handle_ui_optimization() {
        global $pagenow;
        // Keep filter if on revision page, or if doing a revision-related AJAX/REST request.
        $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
        $rest_route = isset( $_GET['rest_route'] ) ? $_GET['rest_route'] : '';
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

        $is_revision_request = ( 
            'revision.php' === $pagenow || 
            ( defined( 'DOING_AJAX' ) && DOING_AJAX && strpos( $action, 'revision' ) !== false ) ||
            strpos( $uri, '/v2/revisions' ) !== false ||
            strpos( $rest_route, '/v2/revisions' ) !== false ||
            strpos( $uri, 'wp-json/wp/v2/posts' ) !== false // Gutenberg fetches revisions via sub-routes
        );
        
        if ( ! $is_revision_request ) {
            remove_filter( 'wp_get_revision_ui_diff', array( $this->core, 'custom_meta_revision_ui_diff' ) );
        }
    }

    public function ajax_get_classic_revisions_html() {
        check_ajax_referer( 'cfa_revisions_nonce', 'security' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();
        $post = get_post( $post_id );
        ob_start();
        if ( function_exists( 'wp_list_post_revisions' ) ) wp_list_post_revisions( $post );
        wp_send_json_success( ob_get_clean() );
    }
}

new Custom_Field_Auditor_Init();
