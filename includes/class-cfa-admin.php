<?php
/**
 * Admin Custom Field Auditor Class
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Custom_Field_Auditor_Admin {

    private $core;
    private $option_key = 'custom_field_auditor_config';

    public function __construct( $core ) {
        $this->core = $core;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( CFA_PATH . 'custom-field-auditor.php' ), array( $this, 'add_settings_link' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_admin_menu() {
        add_options_page( 'Field Auditor', 'Field Auditor', 'manage_options', 'custom-field-auditor', array( $this, 'render_page' ) );
    }

    public function add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=custom-field-auditor">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_custom-field-auditor' !== $hook ) return;
        wp_enqueue_style( 'cfa-admin-css', CFA_URL . 'assets/css/cfa-admin.css', array(), CFA_VERSION );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized access' );

        $message = '';
        if ( isset( $_POST['cfa_action'] ) && check_admin_referer( 'cfa_action_nonce' ) ) {
            $message = $this->handle_actions( $_POST );
        }

        $fields = $this->core->get_fields();
        include_once CFA_PATH . 'templates/admin-settings-page.php';
        if ( function_exists( 'cfa_render_template' ) ) {
            cfa_render_template( $fields, $message );
        }
    }

    private function handle_actions( $post ) {
        $fields = $this->core->get_fields();
        $action = $post['cfa_action'];

        if ( 'add_field' === $action ) {
            $key = sanitize_key( $post['new_key'] );
            $label = sanitize_text_field( $post['new_label'] );
            if ( $key && $label ) {
                $fields[ $key ] = array( 'label' => $label, 'tracking' => true );
                update_option( $this->option_key, $fields );
                return 'Field added successfully.';
            }
        }

        if ( 'bulk_action' === $action && ! empty( $post['selected_fields'] ) ) {
            $selected = $post['selected_fields'];
            $type = $post['bulk_type'];
            
            if ( 'delete' === $type ) {
                $count_del = 0;
                global $wpdb;
                foreach ( $selected as $k ) {
                    $used = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = %s", $k ) );
                    if ( ! $used ) {
                        unset( $fields[ $k ] );
                        $count_del++;
                    }
                }
                update_option( $this->option_key, $fields );
                return "$count_del fields deleted. Active fields were skipped.";
            }

            if ( in_array( $type, array( 'enable', 'disable' ) ) ) {
                $status = ( 'enable' === $type );
                foreach ( $selected as $k ) {
                    if ( isset( $fields[ $k ] ) ) $fields[ $k ]['tracking'] = $status;
                }
                update_option( $this->option_key, $fields );
                return 'Tracking status updated.';
            }
        }

        if ( 'toggle' === $action ) {
            $k = sanitize_key( $post['field_key'] );
            if ( isset( $fields[ $k ] ) ) {
                $fields[ $k ]['tracking'] = ! empty( $post['status'] );
                update_option( $this->option_key, $fields );
                return 'Status updated.';
            }
        }

        return '';
    }
}
