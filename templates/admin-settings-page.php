<?php
/**
 * Admin Template for Custom Field Auditor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cfa_render_template( $fields, $message ) {
    ?>
    <div class="wrap cfa-wrap">
        <div class="cfa-header">
            <h1>Custom Field Auditor</h1>
            <button type="button" class="cfa-btn cfa-btn-primary" onclick="cfaOpenModal()">+ Add New Field</button>
        </div>

        <?php if ( $message ) : ?>
            <div class="updated notice is-dismissible" style="margin-bottom: 20px; border-radius: 8px;">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
        <?php endif; ?>

        <div class="cfa-card">
            <form method="post">
                <?php wp_nonce_field( 'cfa_action_nonce' ); ?>
                <input type="hidden" name="cfa_action" value="bulk_action">
                
                <div style="margin-bottom: 20px; display: flex; align-items: center;">
                    <select name="bulk_type" style="border-radius: 6px; padding: 4px 10px;">
                        <option value="-1">Bulk Actions</option>
                        <option value="enable">Enable Tracking</option>
                        <option value="disable">Disable Tracking</option>
                        <option value="delete">Delete Unused</option>
                    </select>
                    <input type="submit" class="button action" value="Apply" style="margin-left: 10px; border-radius: 6px;">
                </div>

                <table class="cfa-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="cfa-select-all"></th>
                            <th>Label</th>
                            <th>Meta Key</th>
                            <th style="text-align: center;">Tracking</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $fields ) ) : ?>
                            <tr><td colspan="5" style="text-align: center; padding: 40px;">No fields managed. Click "Add New Field" to begin.</td></tr>
                        <?php else : ?>
                            <?php foreach ( $fields as $key => $config ) : 
                                global $wpdb;
                                $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = %s", $key ) );
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_fields[]" value="<?php echo esc_attr( $key ); ?>"></td>
                                    <td><strong><?php echo esc_html( $config['label'] ); ?></strong></td>
                                    <td><code><?php echo esc_html( $key ); ?></code></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center;">
                                            <input type="checkbox" 
                                                   onchange="cfaToggleTracking('<?php echo esc_attr( $key ); ?>', this.checked)"
                                                   <?php checked( ! empty( $config['tracking'] ) ); ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ( $count > 0 ) : ?>
                                            <span class="cfa-badge cfa-badge-used">In Use (<?php echo $count; ?>)</span>
                                        <?php else : ?>
                                            <span class="cfa-badge cfa-badge-unused">Unused</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- Hidden Form for Single Toggles -->
    <form id="cfa-toggle-form" method="post" style="display:none;">
        <?php wp_nonce_field( 'cfa_action_nonce' ); ?>
        <input type="hidden" name="cfa_action" value="toggle">
        <input type="hidden" name="field_key" id="cfa-toggle-key">
        <input type="hidden" name="status" id="cfa-toggle-status">
    </form>
    <!-- /Hidden Form for Single Toggles -->

    <!-- Modal Dialog -->
    <div id="cfa-modal" class="cfa-modal-overlay">
        <div class="cfa-modal">
            <h2>Add Custom Field</h2>
            <form method="post">
                <?php wp_nonce_field( 'cfa_action_nonce' ); ?>
                <input type="hidden" name="cfa_action" value="add_field">
                
                <div class="cfa-field">
                    <label>Field Name</label>
                    <input type="text" name="new_label" placeholder="e.g. Code Editor Content" required>
                </div>
                
                <div class="cfa-field">
                    <label>Meta Key</label>
                    <input type="text" name="new_key" placeholder="e.g. code_editor_0" required>
                </div>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" class="cfa-btn cfa-btn-secondary" onclick="cfaCloseModal()">Cancel</button>
                    <button type="submit" class="cfa-btn cfa-btn-primary">Add Field</button>
                </div>
            </form>
        </div>
    </div>
    <!-- /Modal Dialog -->

    <script type="text/javascript">
    function cfaOpenModal() { document.getElementById('cfa-modal').style.display = 'block'; }
    function cfaCloseModal() { document.getElementById('cfa-modal').style.display = 'none'; }
    function cfaToggleTracking(key, status) {
        document.getElementById('cfa-toggle-key').value = key;
        document.getElementById('cfa-toggle-status').value = status ? 1 : '';
        document.getElementById('cfa-toggle-form').submit();
    }
    jQuery(document).ready(function($) {
        $('#cfa-select-all').click(function() {
            $('input[name="selected_fields[]"]').prop('checked', this.checked);
        });
        $(window).click(function(e) {
            if ($(e.target).hasClass('cfa-modal-overlay')) cfaCloseModal();
        });
    });
    </script>
    <?php
}
