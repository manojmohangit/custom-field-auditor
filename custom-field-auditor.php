<?php
/**
 * Plugin Name: Custom Field Auditor
 * Description: Advanced tracking and versioning for custom post meta fields.
 * Version: 1.0.0
 * Author: Manoj Mohan
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFA_VERSION', '1.0.0' );
define( 'CFA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CFA_URL', plugin_dir_url( __FILE__ ) );


require_once CFA_PATH . 'includes/class-cfa-core.php';
require_once CFA_PATH . 'includes/class-cfa-admin.php';

class Custom_Field_Auditor_Init {

    private $core;
    private $admin;

    public function __construct() {
        
        $this->core = new Custom_Field_Auditor_Core();

        if ( is_admin() ) {
            $this->admin = new Custom_Field_Auditor_Admin( $this->core );
            
            // Shared Admin Assets/Scripts
            add_action( 'admin_footer', array( $this, 'inject_ui_refresh_script' ) );
            add_action( 'wp_ajax_get_classic_revisions_html', array( $this, 'ajax_get_classic_revisions_html' ) );
            add_action( 'admin_init', array( $this, 'handle_ui_optimization' ) );
        }
    }

    public function handle_ui_optimization() {
        global $pagenow;
        $is_revision_ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) && 'get-revision-diffs' === $_REQUEST['action'] );
        if ( 'revision.php' !== $pagenow && ! $is_revision_ajax ) {
            remove_filter( 'wp_get_revision_ui_diff', array( $this->core, 'custom_meta_revision_ui_diff' ) );
        }
    }

    public function inject_ui_refresh_script() {
        global $pagenow;
        if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) return;
        ?>
        <script type="text/javascript">
        (function($) {
            window.refreshCustomFieldAuditorUI = function() {
                if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                    var editor = wp.data.select('core/editor');
                    var postId = editor.getCurrentPostId();
                    if (postId) {
                        wp.data.dispatch( 'core' ).invalidateResolution( 'getEntityRecord', [ 'postType', editor.getCurrentPostType(), postId ] );
                    }
                } 
                if ($('#revisionsdiv').length) {
                    var postId = $('#post_ID').val();
                    $.post(ajaxurl, { action: 'get_classic_revisions_html', post_id: postId }, function(response) {
                        if (response.success) {
                            $('#revisionsdiv .inside').html(response.data);
                            $(document).trigger('cfa-revisions-updated');
                        }
                    });
                }
            };
            $(document).ajaxSuccess(function(e, x, s) {
                if (s.data && (s.data.indexOf('add-meta') !== -1 || s.data.indexOf('save-post') !== -1 || s.data.indexOf('delete-meta') !== -1 || s.data.indexOf('update-post-meta') !== -1)) {
                    setTimeout(window.refreshCustomFieldAuditorUI, 600);
                }
            });
            if (typeof wp !== 'undefined' && wp.apiFetch) {
                wp.apiFetch.use((opt, next) => {
                    return next(opt).then((res) => {
                        if (opt.method === 'POST' && opt.path && (opt.path.indexOf('posts') !== -1 || opt.path.indexOf('meta') !== -1)) {
                            setTimeout(window.refreshCustomFieldAuditorUI, 700);
                        }
                        return res;
                    });
                });
            }
        })(jQuery);
        </script>
        <?php
    }

    public function ajax_get_classic_revisions_html() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();
        $post = get_post( $post_id );
        ob_start();
        if ( function_exists( 'wp_list_post_revisions' ) ) wp_list_post_revisions( $post );
        wp_send_json_success( ob_get_clean() );
    }
}

new Custom_Field_Auditor_Init();
