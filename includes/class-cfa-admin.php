<?php
/**
 * Custom Field Auditor Admin
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
        
        // AJAX Handlers
        add_action( 'wp_ajax_cfa_manage_field', array( $this, 'ajax_manage_field' ) );
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
        // Enqueue on settings page AND revision comparison page
        if ( 'settings_page_custom-field-auditor' === $hook || 'revision.php' === $hook ) {
            wp_enqueue_style( 'cfa-admin-css', CFA_URL . 'assets/css/cfa-admin.css', array(), CFA_VERSION );
        }

        if ( 'settings_page_custom-field-auditor' === $hook ) {
            wp_enqueue_script( 'cfa-admin-js', CFA_URL . 'assets/js/cfa-admin.js', array( 'jquery' ), CFA_VERSION, true );
            wp_localize_script( 'cfa-admin-js', 'cfaConfig', array(
                'nonce' => wp_create_nonce( 'cfa_action_nonce' )
            ) );
        }
    
        global $pagenow;
        if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
            wp_enqueue_script( 'cfa-editor-js', CFA_URL . 'assets/js/cfa-editor.js', array( 'jquery', 'wp-data', 'wp-api-fetch' ), CFA_VERSION, true );
            wp_localize_script( 'cfa-editor-js', 'cfaEditorConfig', array(
                'nonce' => wp_create_nonce( 'cfa_revisions_nonce' )
            ) );
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized access' );

        $message = '';
        if ( isset( $_POST['cfa_action'] ) && check_admin_referer( 'cfa_action_nonce' ) ) {
            $message = $this->handle_actions( $_POST );
        }

        $fields = $this->core->get_fields();
        $suggested_keys = $this->get_suggested_keys();
        include_once CFA_PATH . 'templates/admin-settings-page.php';
        if ( function_exists( 'cfa_render_template' ) ) {
            cfa_render_template( $fields, $message, $suggested_keys );
        }
    }

    /**
     * Custom sanitization for meta keys to allow camelCase.
     * Based on sanitize_key but allows uppercase.
     */
    private function sanitize_meta_key( $key ) {
        $raw_key = $key;
        $key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );
        return apply_filters( 'cfa_sanitize_meta_key', $key, $raw_key );
    }

    public function ajax_manage_field() {
        check_admin_referer( 'cfa_action_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized' ) );

        $post_data = $_POST;
        $message = $this->handle_actions( $post_data );
        $fields = $this->core->get_fields();
        
        // Capture the table body for surgical update
        ob_start();
        include CFA_PATH . 'templates/admin-settings-page.php';
        if ( function_exists( 'cfa_render_table_body' ) ) {
            cfa_render_table_body( $fields );
        }
        $table_html = ob_get_clean();

        wp_send_json_success( array(
            'message'    => $message,
            'table_html' => $table_html
        ) );
    }

    private function handle_actions( $post ) {
        $fields = $this->core->get_fields();
        if ( ! isset( $post['cfa_action'] ) ) return '';
        $action = $post['cfa_action'];

        if ( 'add_field' === $action ) {
            $key = $this->sanitize_meta_key( $post['new_key'] );
            $label = sanitize_text_field( $post['new_label'] );

            if ( $key && $label ) {
                $fields[ $key ] = array( 
                    'label'    => $label, 
                    'tracking' => true
                );
                update_option( $this->option_key, $fields );
                return 'Field added successfully.';
            }
        }

        if ( 'edit_field' === $action ) {
            $old_key = $this->sanitize_meta_key( $post['old_key'] );
            $new_key = $this->sanitize_meta_key( $post['edit_key'] );
            $label = sanitize_text_field( $post['edit_label'] );

            if ( $old_key && $new_key && $label && isset( $fields[ $old_key ] ) ) {
                $tracking = $fields[ $old_key ]['tracking'];
                
                if ( $old_key !== $new_key ) {
                    unset( $fields[ $old_key ] );
                }
                
                $fields[ $new_key ] = array( 
                    'label'    => $label, 
                    'tracking' => $tracking
                );
                update_option( $this->option_key, $fields );
                return 'Field updated successfully.';
            }
        }

        if ( 'bulk_action' === $action && ! empty( $post['selected_fields'] ) ) {
            $selected = $post['selected_fields'];
            $type = $post['bulk_type'];
            
            if ( 'delete' === $type ) {
                $count_del = 0;
                foreach ( $selected as $k ) {
                    $k = $this->sanitize_meta_key( $k );
                    if ( isset( $fields[ $k ] ) ) {
                        unset( $fields[ $k ] );
                        $count_del++;
                    }
                }
                update_option( $this->option_key, $fields );
                return "$count_del fields deleted successfully.";
            }

            if ( in_array( $type, array( 'enable', 'disable' ) ) ) {
                $status = ( 'enable' === $type );
                foreach ( $selected as $k ) {
                    $k = $this->sanitize_meta_key( $k );
                    if ( isset( $fields[ $k ] ) ) $fields[ $k ]['tracking'] = $status;
                }
                update_option( $this->option_key, $fields );
                return 'Tracking status updated.';
            }
        }

        if ( 'toggle' === $action ) {
            $k = $this->sanitize_meta_key( $post['field_key'] );
            if ( isset( $fields[ $k ] ) ) {
                $fields[ $k ]['tracking'] = ! empty( $post['status'] );
                update_option( $this->option_key, $fields );
                return 'Status updated.';
            }
        }

        if ( 'delete_field' === $action ) {
            $k = $this->sanitize_meta_key( $post['field_key'] );
            if ( isset( $fields[ $k ] ) ) {
                unset( $fields[ $k ] );
                update_option( $this->option_key, $fields );
                return 'Field deleted successfully.';
            }
        }

        return '';
    }

    public function get_suggested_keys() {
        global $wpdb;
        $tracked = array_keys( $this->core->get_fields() );
        
        // Fetch 10 most recent distinct meta keys that aren't binary/private/internal
        $keys_to_exclude = ! empty( $tracked ) ? $tracked : array( '___PLACEHOLDER___' );
        
        // Stricter prepared query for exclusion list
        $placeholders = array_fill( 0, count( $keys_to_exclude ), '%s' );
        $query = $wpdb->prepare( 
            "SELECT DISTINCT TRIM(meta_key) as cleaned_key FROM $wpdb->postmeta 
             WHERE meta_key NOT LIKE %s 
             AND meta_key != '' 
             AND meta_key NOT IN (" . implode( ',', $placeholders ) . ")
             ORDER BY meta_id DESC LIMIT 10",
            array_merge( array( '\_%' ), $keys_to_exclude )
        );
        
        $results = $wpdb->get_col( $query );
        return $results ? $results : array();
    }
}
