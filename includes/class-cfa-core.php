<?php
/**
 * Custom Field Auditor Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Custom_Field_Auditor_Core {

    private static $is_handling_meta = false;
    private static $force_revision = false;
    private static $posts_to_revise = array();
    private $option_key = 'custom_field_auditor_config';

    public function __construct() {
        add_filter( 'wp_post_revision_meta_keys', array( $this, 'register_revision_meta_keys' ), 10, 2 );
        add_filter( 'wp_save_post_revision_post_has_changed', array( $this, 'force_revision_on_meta_change' ), 10, 3 );
        
        add_action( 'added_post_meta', array( $this, 'handle_meta_change' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'handle_meta_change' ), 10, 4 );
        add_action( 'deleted_post_meta', array( $this, 'handle_meta_change' ), 10, 3 );
        
        // Catch updates before they happen to compare with current DB value
        add_filter( 'update_post_metadata', array( $this, 'filter_meta_update' ), 10, 5 );
        
        add_filter( 'wp_get_revision_ui_diff', array( $this, 'custom_meta_revision_ui_diff' ), 10, 3 );

        // Bundle multiple meta changes into one revision at the end of request
        add_action( 'shutdown', array( $this, 'save_queued_revisions' ) );
    }

    public function get_fields() {
        return get_option( $this->option_key, array() );
    }

    public function register_revision_meta_keys( $keys, $post_id ) {
        $fields = $this->get_fields();
        $track_keys = array();
        
        foreach ( $fields as $key => $config ) {
            if ( empty( $config['tracking'] ) ) continue;
            $track_keys[] = $key;
        }
        
        return array_unique( array_merge( $keys, $track_keys ) );
    }

    public function force_revision_on_meta_change( $post_has_changed, $last_revision, $post ) {
        return self::$force_revision ? true : $post_has_changed;
    }

    /**
     * Pre-check meta updates to see if the value is actually changing in the database.
     */
    public function filter_meta_update( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
        if ( self::$is_handling_meta ) return $check;

        if ( ! $this->is_tracked_key( $meta_key ) ) return $check;

        $current_db_value = get_post_meta( $object_id, $meta_key, true );
        
        // If the value in DB is already same as new value, we might not need a revision
        // However, we still let handle_meta_change do the final check against history
        return $check;
    }

    private function is_tracked_key( $meta_key ) {
        $fields = $this->get_fields();
        return isset( $fields[ $meta_key ] ) && ! empty( $fields[ $meta_key ]['tracking'] );
    }

    private function get_last_recorded_value( $post_id, $meta_key, $depth = 5 ) {
        $revisions = wp_get_post_revisions( $post_id, array( 
            'posts_per_page' => $depth,
            'post_status'    => 'inherit' // Excludes autosaves usually, but let's be safe
        ) );

        if ( empty( $revisions ) ) return null;

        foreach ( $revisions as $revision ) {
            // Skip autosaves for comparison as they are transient
            if ( wp_is_post_autosave( $revision ) ) continue;

            $val = get_post_meta( $revision->ID, $meta_key, true );
                
            // Let's check for existence in the revision meta
            $rev_meta = get_post_custom( $revision->ID );
            if ( isset( $rev_meta[ $meta_key ] ) ) {
                return $val;
            }
        }

        return null;
    }

    /**
     * Handle meta change and queue a revision if a tracked field actually changed.
     */
    public function handle_meta_change( $meta_id, $object_id, $meta_key, $_meta_value = null ) {
        if ( self::$is_handling_meta ) return;

        if ( ! $this->is_tracked_key( $meta_key ) ) return;

        $post_type = get_post_type( $object_id );
        if ( ! $post_type || 'revision' === $post_type || ! post_type_supports( $post_type, 'revisions' ) ) {
            return;
        }

        $last_known_value = $this->get_last_recorded_value( $object_id, $meta_key );
        
        if ( null !== $last_known_value && $last_known_value === $_meta_value ) {
            if ( ! isset( self::$posts_to_revise[ $object_id ] ) ) {
                return;
            }
        }

        // Queue the post for revisioning at the end of the request
        self::$posts_to_revise[ $object_id ] = true;
    }

    /**
     * Finalize and save revisions for all posts that had meta changes.
     * This bundles multiple meta updates into a single revision.
     */
    public function save_queued_revisions() {
        if ( empty( self::$posts_to_revise ) || self::$is_handling_meta ) return;

        self::$is_handling_meta = true;

        global $wpdb;

        foreach ( self::$posts_to_revise as $post_id => $should_save ) {
            if ( ! $should_save ) continue;

            // Double Revision Prevention: 
            // Check if a revision created earlier in this same request already captured the meta changes.
            // This happens if WordPress Core's own save flow runs after meta update but before shutdown.
            $actual_change_found = false;
            $fields = $this->get_fields();
            foreach ( $fields as $key => $config ) {
                if ( empty( $config['tracking'] ) ) continue;
                
                $current_val = get_post_meta( $post_id, $key, true );
                $last_val = $this->get_last_recorded_value( $post_id, $key );
                
                if ( $current_val !== $last_val ) {
                    $actual_change_found = true;
                    break;
                }
            }

            if ( ! $actual_change_found ) {
                continue;
            }

            self::$force_revision = true;

            // Force the post to be seen as modified
            $wpdb->update(
                $wpdb->posts,
                array( 
                    'post_modified'     => current_time( 'mysql' ), 
                    'post_modified_gmt' => current_time( 'mysql', 1 ) 
                ),
                array( 'ID' => $post_id )
            );

            clean_post_cache( $post_id );
            wp_save_post_revision( $post_id );
            
            self::$force_revision = false;
        }

        self::$is_handling_meta = false;
        self::$posts_to_revise = array();
    }

    public function custom_meta_revision_ui_diff( $diffs, $compare_from, $compare_to ) {
        $fields = $this->get_fields();
        
        $managed_diffs = array();
        // Use a set of already added meta keys to prevent duplicates
        $already_added = array();
        foreach ( $diffs as $d ) {
            $already_added[] = $d['id'];
        }

        $custom_from = get_post_custom( $compare_from->ID );
        $custom_to   = get_post_custom( $compare_to->ID );
        
        $keys_from = $custom_from ? array_keys( $custom_from ) : array();
        $keys_to   = $custom_to ? array_keys( $custom_to ) : array();
        $all_meta_keys = array_unique( array_merge( $keys_from, $keys_to ) );

        foreach ( $all_meta_keys as $meta_key ) {
            if ( in_array( $meta_key, $already_added ) ) continue;

            if ( ! isset( $fields[ $meta_key ] ) || empty( $fields[ $meta_key ]['tracking'] ) ) {
                continue;
            }

            $config_to_use = $fields[ $meta_key ];

            $old_val = get_post_meta( $compare_from->ID, $meta_key, true );
            $new_val = get_post_meta( $compare_to->ID, $meta_key, true );

            if ( $old_val === $new_val ) continue;

            $old_display = is_array( $old_val ) || is_object( $old_val ) ? wp_json_encode( $old_val, JSON_PRETTY_PRINT ) : (string) $old_val;
            $new_display = is_array( $new_val ) || is_object( $new_val ) ? wp_json_encode( $new_val, JSON_PRETTY_PRINT ) : (string) $new_val;

            $label = ! empty( $config_to_use['label'] ) ? $config_to_use['label'] : $meta_key;

            $diff_html = wp_text_diff( $old_display, $new_display );
            
            if ( $diff_html ) {
                $managed_diffs[] = array(
                    'id'    => $meta_key,
                    'name'  => $label,
                    'diff'  => $diff_html,
                );
                $already_added[] = $meta_key;
            }
        }
        
        return array_merge( $diffs, $managed_diffs );
    }
}
