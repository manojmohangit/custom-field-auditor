<?php
/**
 * Core Custom Field Auditor Class
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Custom_Field_Auditor_Core {

    private static $is_handling_meta = false;
    private static $force_revision = false;
    private $option_key = 'custom_field_auditor_config';

    public function __construct() {
        add_filter( 'wp_post_revision_meta_keys', array( $this, 'register_revision_meta_keys' ), 10, 2 );
        add_filter( 'wp_save_post_revision_post_has_changed', array( $this, 'force_revision_on_meta_change' ), 10, 3 );
        
        add_action( 'added_post_meta', array( $this, 'handle_meta_change' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'handle_meta_change' ), 10, 4 );
        add_action( 'deleted_post_meta', array( $this, 'handle_meta_change' ), 10, 3 );
        
        add_filter( 'wp_get_revision_ui_diff', array( $this, 'custom_meta_revision_ui_diff' ), 10, 3 );
    }

    public function get_fields() {
        return get_option( $this->option_key, array() );
    }

    public function register_revision_meta_keys( $keys, $post_id ) {
        $fields = $this->get_fields();
        $track_keys = array();
        foreach ( $fields as $key => $config ) {
            if ( ! empty( $config['tracking'] ) ) {
                $track_keys[] = $key;
            }
        }
        return array_unique( array_merge( $keys, $track_keys ) );
    }

    public function force_revision_on_meta_change( $post_has_changed, $last_revision, $post ) {
        return self::$force_revision ? true : $post_has_changed;
    }

    public function handle_meta_change( $meta_id, $object_id, $meta_key, $_meta_value = null ) {
        if ( self::$is_handling_meta ) return;

        $fields = $this->get_fields();
        if ( ! isset( $fields[ $meta_key ] ) || empty( $fields[ $meta_key ]['tracking'] ) ) return;

        $post_type = get_post_type( $object_id );
        if ( ! $post_type || 'revision' === $post_type || ! post_type_supports( $post_type, 'revisions' ) ) {
            return;
        }

        self::$is_handling_meta = true;
        self::$force_revision = true;

        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array( 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', 1 ) ),
            array( 'ID' => $object_id )
        );
        clean_post_cache( $object_id );
        wp_save_post_revision( $object_id );

        self::$is_handling_meta = false;
        self::$force_revision = false;
    }

    public function custom_meta_revision_ui_diff( $diffs, $compare_from, $compare_to ) {
        $fields = $this->get_fields();
        foreach ( $fields as $key => $config ) {
            $old_val = get_post_meta( $compare_from->ID, $key, true );
            $new_val = get_post_meta( $compare_to->ID, $key, true );

            if ( $old_val === $new_val ) continue;

            $old_display = is_array( $old_val ) || is_object( $old_val ) ? wp_json_encode( $old_val, JSON_PRETTY_PRINT ) : (string) $old_val;
            $new_display = is_array( $new_val ) || is_object( $new_val ) ? wp_json_encode( $new_val, JSON_PRETTY_PRINT ) : (string) $new_val;

            $diffs[] = array(
                'id'    => $key,
                'name'  => $config['label'],
                'diff'  => wp_text_diff( $old_display, $new_display ),
            );
        }
        return $diffs;
    }
}
