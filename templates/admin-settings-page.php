<?php
/**
 * Admin Template for Custom Field Auditor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cfa_render_template( $fields, $message, $suggested_keys = array() ) {
    ?>
    <div class="wrap cfa-wrap">
        <?php if ( $message ) : 
            $msg_text = is_array( $message ) ? $message['message'] : $message;
            $msg_type = ( is_array( $message ) && isset( $message['type'] ) ) ? $message['type'] : 'success';
            if ( $msg_text ) :
            ?>
            <div class="notice notice-<?php echo esc_attr( $msg_type ); ?> is-dismissible">
                <p><?php echo esc_html( $msg_text ); ?></p>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="cfa-header">
            <h1>Custom Field Auditor</h1>
            <button type="button" class="cfa-btn cfa-btn-primary" onclick="cfaOpenModal()">
                <span class="dashicons dashicons-plus-alt2" style="margin-right: 5px;"></span> Add New Field
            </button>
        </div>

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
                    <button type="submit" class="cfa-btn cfa-btn-primary cfa-btn-small" style="margin-left: 10px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 16px; margin-right: 4px; line-height: 1.2;"></span> Apply
                    </button>
                </div>

                <table class="cfa-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="cfa-select-all"></th>
                            <th>Label</th>
                            <th>Meta Key</th>
                            <th style="text-align: center;">Tracking</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cfa-table-body">
                        <?php cfa_render_table_body( $fields ); ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- Add Modal Dialog -->
    <div id="cfa-modal" class="cfa-modal-overlay">
        <div class="cfa-modal">
            <h2>Add Custom Field</h2>
            <form method="post">
                <?php wp_nonce_field( 'cfa_action_nonce' ); ?>
                <input type="hidden" name="cfa_action" value="add_field">
                
                <div class="cfa-field">
                    <label>Field Name</label>
                    <input type="text" name="new_label" placeholder="e.g. Item Details" id="new-label-input" required>
                </div>
                
                <div class="cfa-field">
                    <label>Meta Key</label>
                    <input type="text" name="new_key" placeholder="e.g. item_details_meta" id="new-key-input" required>
                </div>

                <?php if ( ! empty( $suggested_keys ) ) : ?>
                    <div class="cfa-field">
                        <label style="font-size: 11px; color: #718096; margin-bottom: 8px;">Suggested Keys (From Database)</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <?php foreach ( $suggested_keys as $s_key ) : ?>
                                <span class="cfa-badge cfa-badge-suggest" 
                                      style="cursor: pointer; background: #edf2f7; color: #4a5568; text-transform: none; font-family: monospace;"
                                      onclick="cfaPickKey('<?php echo esc_attr( $s_key ); ?>')">
                                    <?php echo esc_html( $s_key ); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" class="cfa-btn cfa-btn-secondary" onclick="cfaCloseModal()">Cancel</button>
                    <button type="submit" class="cfa-btn cfa-btn-primary">Add Field</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal Dialog -->
    <div id="cfa-edit-modal" class="cfa-modal-overlay">
        <div class="cfa-modal">
            <h2>Edit Custom Field</h2>
            <form method="post">
                <?php wp_nonce_field( 'cfa_action_nonce' ); ?>
                <input type="hidden" name="cfa_action" value="edit_field">
                <input type="hidden" name="old_key" id="edit-old-key">
                
                <div class="cfa-field">
                    <label>Field Name</label>
                    <input type="text" name="edit_label" id="edit-label" required>
                </div>
                
                <div class="cfa-field">
                    <label>Meta Key</label>
                    <input type="text" name="edit_key" id="edit-key" required>
                </div>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" class="cfa-btn cfa-btn-secondary" onclick="cfaCloseEditModal()">Cancel</button>
                    <button type="submit" class="cfa-btn cfa-btn-primary">Update Field</button>
                </div>
            </form>
        </div>
    </div>
<?php
}

/**
 * Reusable table body renderer for AJAX updates
 */
function cfa_render_table_body( $fields ) {
    if ( empty( $fields ) ) : ?>
        <tr><td colspan="7" style="text-align: center; padding: 40px;">No fields managed. Click "Add New Field" to begin.</td></tr>
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
                <td>
                    <div class="cfa-actions-cell">
                        <button type="button" class="cfa-btn cfa-btn-edit cfa-btn-small" 
                                onclick="cfaOpenEditModal('<?php echo esc_attr( $key ); ?>', '<?php echo esc_attr( $config['label'] ); ?>')">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <button type="button" class="cfa-btn cfa-btn-delete cfa-btn-small" 
                                onclick="cfaDeleteField('<?php echo esc_attr( $key ); ?>')">
                            <span class="dashicons dashicons-trash"></span> Delete
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif;
}
