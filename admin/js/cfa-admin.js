(function ($) {

    window.cfaOpenModal = function () { $('#cfa-modal').show(); }
    window.cfaCloseModal = function () { $('#cfa-modal').hide(); }

    window.cfaOpenEditModal = function (key, label) {
        $('#edit-old-key').val(key);
        $('#edit-key').val(key);
        $('#edit-label').val(label);
        $('#cfa-edit-modal').show();
    }
    window.cfaCloseEditModal = function () { $('#cfa-edit-modal').hide(); }

    /**
     * Centralized AJAX Handler
     */
    function cfaPerformAction(data, onSuccess) {
        var $wrap = $('.cfa-wrap');
        $wrap.addClass('cfa-loading');

        data.action = 'cfa_manage_field';
        // Nonce is passed via localized script object cfaConfig
        data.security = cfaConfig.nonce;

        $.post(ajaxurl, data, function (response) {
            $wrap.removeClass('cfa-loading');
            if (response.success) {
                if (response.data.table_html) {
                    $('#cfa-table-body').html(response.data.table_html);
                }
                cfaShowNotice(response.data.message, response.data.notice_type || 'success');
                if (onSuccess) onSuccess(response.data);
            } else {
                alert('Error: ' + (response.data.message || 'Unknown error'));
            }
        });
    }

    function cfaShowNotice(message, type) {
        if (!message) return;
        type = type || 'success'; // success, error, warning
        $('.cfa-wrap > .notice').remove();
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.cfa-header').before($notice);
    }

    window.cfaToggleTracking = function (key, status) {
        cfaPerformAction({
            cfa_action: 'toggle',
            field_key: key,
            status: status ? 1 : ''
        });
    }

    window.cfaDeleteField = function (key) {
        if (!confirm('Are you sure you want to stop auditing this field? (This will NOT delete the actual metadata from posts, only the auditing configuration)')) {
            return;
        }
        cfaPerformAction({
            cfa_action: 'delete_field',
            field_key: key
        });
    }

    window.cfaPickKey = function (key) {
        $('#new-key-input').val(key);
        // Pre-fill label with a cleaned version of the key
        var label = key.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase() });
        $('#new-label-input').val(label);
    }

    $(document).on('submit', '#cfa-modal form', function (e) {
        e.preventDefault();
        var $form = $(this);
        cfaPerformAction({
            cfa_action: 'add_field',
            new_label: $form.find('[name="new_label"]').val(),
            new_key: $form.find('[name="new_key"]').val()
        }, function () {
            cfaCloseModal();
            $form[0].reset();
        });
    });

    $(document).on('submit', '#cfa-edit-modal form', function (e) {
        e.preventDefault();
        var $form = $(this);
        cfaPerformAction({
            cfa_action: 'edit_field',
            old_key: $form.find('[name="old_key"]').val(),
            edit_label: $form.find('[name="edit_label"]').val(),
            edit_key: $form.find('[name="edit_key"]').val()
        }, function () {
            cfaCloseEditModal();
        });
    });

    $(document).on('submit', '.cfa-card form', function (e) {
        var action = $(this).find('input[name="cfa_action"]').val();
        if (action !== 'bulk_action') return;

        e.preventDefault();
        var $form = $(this);
        var selected = [];
        $form.find('input[name="selected_fields[]"]:checked').each(function () {
            selected.push($(this).val());
        });

        if (selected.length === 0) return alert('Please select fields first');

        cfaPerformAction({
            cfa_action: 'bulk_action',
            bulk_type: $form.find('[name="bulk_type"]').val(),
            selected_fields: selected
        });
    });

    $(document).ready(function () {
        $('#cfa-select-all').click(function () {
            $('input[name="selected_fields[]"]').prop('checked', this.checked);
        });
        $(window).click(function (e) {
            if ($(e.target).hasClass('cfa-modal-overlay')) {
                cfaCloseModal();
                cfaCloseEditModal();
            }
        });
    });
})(jQuery);
