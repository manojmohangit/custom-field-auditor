(function ($) {
    /**
     * Custom Field Auditor: Syncing the revision in Editor
     */
    window.refreshCustomFieldAuditorUI = function () {
        // Block Editor (Gutenberg)
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
            var editor = wp.data.select('core/editor');
            var postId = editor.getCurrentPostId();
            if (postId) {
                wp.data.dispatch('core').invalidateResolution('getRevisions', [
                    'postType',
                    editor.getCurrentPostType(),
                    { parent: postId }
                ]);
                console.log('CFA: Block Editor revisions synced.');
            }
        }

        // Classic Editor
        if ($('#revisionsdiv').length) {
            var postId = $('#post_ID').val();
            $.post(ajaxurl, {
                action: 'get_classic_revisions_html',
                post_id: postId,
                security: (typeof cfaEditorConfig !== 'undefined') ? cfaEditorConfig.nonce : ''
            }, function (response) {
                if (response.success) {
                    $('#revisionsdiv .inside').html(response.data);
                    // Log the update for debugging as per user's previous manual edit
                    console.log('CFA: Classic Editor HTML updated.');
                    $(document).trigger('cfa-revisions-updated');
                }
            });
        }
    };

    // Standard AJAX success hook (for Classic Editor / some plugins)
    $(document).ajaxSuccess(function (e, x, s) {
        if (s.data && (
            s.data.indexOf('add-meta') !== -1 ||
            s.data.indexOf('save-post') !== -1 ||
            s.data.indexOf('delete-meta') !== -1 ||
            s.data.indexOf('update-post-meta') !== -1
        )) {
            setTimeout(window.refreshCustomFieldAuditorUI, 600);
        }
    });

    // REST API middleware (for Block Editor)
    if (typeof wp !== 'undefined' && wp.apiFetch) {
        wp.apiFetch.use((opt, next) => {
            return next(opt).then((res) => {
                if (opt.method === 'POST' && opt.path && (
                    opt.path.indexOf('posts') !== -1 ||
                    opt.path.indexOf('meta') !== -1
                )) {
                    setTimeout(window.refreshCustomFieldAuditorUI, 700);
                }
                return res;
            });
        });
    }
})(jQuery);
