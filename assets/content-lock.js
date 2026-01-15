/**
 * responsibe to lock content
 */
(function($) {
    'use strict';

    const ContentLock = {
        init: function() {
            this.bindEvents();
            this.setupHeartbeat();
        },

        bindEvents: function() {
            // Monitor for lock takeovers
            $(document).on('heartbeat-tick', this.handleHeartbeatTick.bind(this));
        },

        setupHeartbeat: function() {
            // Ensure heartbeat is running
            if (typeof wp !== 'undefined' && wp.heartbeat) {
                wp.heartbeat.interval(15);
                
                // Send post lock refresh data
                $(document).on('heartbeat-send', function(e, data) {
                    if (contentLockData.postId) {
                        data['wp-refresh-post-lock'] = {
                            post_id: contentLockData.postId
                        };
                    }
                });
            }
        },

        handleHeartbeatTick: function(event, data) {
            // Check if post lock data exists
            if (!data || !data['wp-refresh-post-lock']) {
                return;
            }

            const lockData = data['wp-refresh-post-lock'];

            // If locked by another user
            if (lockData.locked && lockData.locked_by != contentLockData.currentUserId) {
                this.showLockModal(
                    lockData.locked_by_name,
                    lockData.time_since
                );
            }
        },

        showLockModal: function(username, timeSince) {
            // Prevent duplicate modals
            if ($('#content-lock-modal').length) {
                return;
            }

            const modalHTML = `
                <p>
                    This content is being edited by user 
                    <strong>${this.escapeHtml(username)}</strong>
                    and is therefore locked to prevent changes.
                </p>
                <p>
                    This lock is in place since 
                    <strong>${this.escapeHtml(timeSince)}</strong>.
                </p>
            `;

            $('body').append(modalHTML);

            // Disable form submissions
            $('form#post').on('submit', function(e) {
                e.preventDefault();
                alert(contentLockData.strings.cannotEdit);
                return false;
            });

            // Disable publish/update button
            $('#publish, #save-post').prop('disabled', true);
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };

    // Initialize when ready
    $(document).ready(function() {
        ContentLock.init();
    });

})(jQuery);