/**
 * responsible to unlock content lock
 */
(function($) {
    'use strict';

    // Handle break lock button click
    $(document).on('click', '.break-lock-btn', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const postId = $btn.data('post-id');
        const $spinner = $btn.siblings('.spinner');
        
        if (!postId || $btn.prop('disabled')) {
            return;
        }

        if (!confirm('Are you sure you want to take over editing this content? The other user will lose any unsaved changes.')) {
            return;
        }

        // Disable button and show spinner
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        // AJAX request to break lock
        $.ajax({
            url: contentLockData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'break_content_lock',
                post_id: postId,
                nonce: contentLockData.breakLockNonce
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to edit page
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data || 'Could not break lock'));
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

})(jQuery);