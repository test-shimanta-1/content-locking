<?php
/**
 * Enhanced Content lock admin class
 * 
 * @since 1.0.0
 * @package Content_Lock
 */


if (!defined('ABSPATH')) {
    exit;
}

class Content_Lock_Admin
{

    /**
     * Lock duration in seconds.
     *
     * @var int
     */
    private $lock_duration;

    /**
     * Registers hooks for content lock handling.
     *
     * @return void
     * @since 1.0.0
     */
    public function __construct()
    {
        // Initialize lock duration from settings (minutes → seconds)
        $this->lock_duration = $this->get_lock_duration_from_settings();

        // initializes other hooks
        add_action('admin_init', [$this, 'check_and_handle_lock']);
        add_action('admin_init', [$this, 'add_break_lock_capability']);
        add_filter('wp_post_lock_window', [$this, 'custom_lock_time']);
        add_action('wp_ajax_break_content_lock', [$this, 'break_content_lock']);
        add_action('wp_ajax_check_post_lock_status', [$this, 'ajax_check_lock_status']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'show_lock_notice']);
        add_filter('heartbeat_settings', [$this, 'customize_heartbeat']);
        add_filter('heartbeat_received', [$this, 'refresh_lock'], 10, 2);
        add_action('admin_init', [$this, 'register_lock_duration_setting']);
    }

    /**
     * Check lock status and redirect if necessary
     * 
     * @since 1.0.0
     * @return void
     */
    public function check_and_handle_lock()
    {
        // Only on edit screens
        if (!isset($_GET['post'], $_GET['action']) || $_GET['action'] !== 'edit') {
            return;
        }

        $post_id = intval($_GET['post']);

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $lock_user_id = wp_check_post_lock($post_id);

        // If locked by another user
        if ($lock_user_id && $lock_user_id !== get_current_user_id()) {
            $can_break_lock = current_user_can('break_content_lock');

            // Store lock info for notice display
            set_transient('content_lock_notice_' . get_current_user_id(), [
                'post_id' => $post_id,
                'locked_by' => $lock_user_id,
                'can_break' => $can_break_lock
            ], 300);

            // Redirect to posts list with error
            wp_redirect(add_query_arg([
                'post_type' => get_post_type($post_id),
                'content_locked' => 1,
                'locked_post' => $post_id
            ], admin_url('edit.php')));
            exit;
        }

        // Set lock for current user
        wp_set_post_lock($post_id);
    }

    /**
     * Displays an admin notice when a post is locked.
     *
     * @since 1.0.0
     * @return void
     */
    public function show_lock_notice()
    {
        $user_id = get_current_user_id();
        $notice_data = get_transient('content_lock_notice_' . $user_id);

        if (!$notice_data) {
            return;
        }

        delete_transient('content_lock_notice_' . $user_id);

        $locked_user = get_userdata($notice_data['locked_by']);
        $post = get_post($notice_data['post_id']);
        $post_title = $post ? $post->post_title : 'this content';
        $user_name = $locked_user ? $locked_user->display_name : 'another user';

        ?>
        <div class="notice notice-error is-dismissible content-lock-notice">
            <p>
                <strong><?php _e('Content Locked', 'content-lock'); ?></strong>
            </p>
            <p>
                <?php
                $lock_meta = get_post_meta($notice_data['post_id'], '_edit_lock', true);
                $time_since = '';

                if ($lock_meta) {
                    [$timestamp] = explode(':', $lock_meta);
                    $time_since = $this->get_time_since($timestamp);
                }

                printf(
                    __('This content is being edited by user <strong>%1$s</strong> and is therefore locked to prevent changes. This lock is in place since <strong>%2$s</strong>.', 'content-lock'),
                    esc_html($user_name),
                    esc_html($time_since)
                );

                ?>
            </p>
            <?php if ($notice_data['can_break']): ?>
                <p>
                    <button type="button" class="button button-secondary break-lock-btn"
                        data-post-id="<?php echo esc_attr($notice_data['post_id']); ?>">
                        <?php _e('Break Lock', 'content-lock'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Adjusts the Heartbeat API interval on post edit screens.
     *
     * @param array $settings Heartbeat settings.
     * @since 1.0.0
     * @return array Modified heartbeat settings.
     */
    public function customize_heartbeat($settings)
    {
        // Check every 15 seconds on edit screens
        global $pagenow;
        if (is_admin() && in_array($pagenow, ['post.php', 'post-new.php'])) {
            $settings['interval'] = 15;
        }
        return $settings;
    }

    /**
     * Refreshes or validates the post lock via Heartbeat.
     *
     * @param array $response Heartbeat response data.
     * @param array $data     Heartbeat request data.
     * @since 1.0.0
     * @return array Modified heartbeat response.
     */
    public function refresh_lock($response, $data)
    {
        if (empty($data['wp-refresh-post-lock'])) {
            return $response;
        }

        $post_id = absint($data['wp-refresh-post-lock']['post_id']);
        if (!$post_id) {
            return $response;
        }

        $lock = wp_check_post_lock($post_id);
        $user_id = get_current_user_id();

        if ($lock && $lock != $user_id) {
            $user = get_userdata($lock);

            $lock_meta = get_post_meta($post_id, '_edit_lock', true);
            $time_since = '';

            if ($lock_meta) {
                [$timestamp] = explode(':', $lock_meta);
                $time_since = $this->get_time_since($timestamp);
            }

            $response['wp-refresh-post-lock'] = [
                'locked' => true,
                'locked_by' => $lock,
                'locked_by_name' => $user ? $user->display_name : __('Someone', 'content-lock'),
                'time_since' => $time_since
            ];


        } else {
            // Refresh the lock
            wp_set_post_lock($post_id);
            $response['wp-refresh-post-lock'] = [
                'locked' => false
            ];
        }

        return $response;
    }

    /**
     * Returns a human-readable time difference from a timestamp.
     *
     * @param int $timestamp UNIX timestamp.
     * 
     * @since 1.0.0
     * @return string Relative time string.
     */
    private function get_time_since($timestamp)
    {
        if (!$timestamp) {
            return '';
        }

        $diff = time() - (int) $timestamp;
        if ($diff < 1) {
            return __('just now', 'content-lock');
        }

        $minutes = floor($diff / 60);
        $seconds = $diff % 60;
        if ($minutes < 1) {
            return sprintf(
                _n('%d second ago', '%d seconds ago', $seconds, 'content-lock'),
                $seconds
            );
        }

        return sprintf(
            __('%1$d minute %2$d second ago', 'content-lock'),
            $minutes,
            $seconds
        );
    }



    /**
     * Sets the post lock duration.
     *
     * @param int $time Default lock duration.
     * 
     * @since 1.0.0
     * @return int Lock duration in seconds.
     */
    public function custom_lock_time($time)
    {
        return $this->lock_duration;
    }

    /**
     * Adds the break lock capability to administrators.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_break_lock_capability()
    {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('break_content_lock')) {
            $role->add_cap('break_content_lock');
        }
    }

    /**
     * Enqueues admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets($hook)
    {
        // Only on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'content-lock-styles',
            CONTENT_LOCK_URL . 'assets/content-lock.css',
            [],
            CONTENT_LOCK_VERSION
        );

        // Only enqueue JS on edit screens
        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            wp_enqueue_script('heartbeat');
            wp_enqueue_script('wp-post-lock');

            wp_enqueue_script(
                'content-lock-script',
                CONTENT_LOCK_URL . 'assets/content-lock.js',
                ['jquery', 'heartbeat', 'wp-post-lock'],
                CONTENT_LOCK_VERSION,
                true
            );

            wp_localize_script('content-lock-script', 'contentLockData', [
                'currentUserId' => get_current_user_id(),
                'postId' => get_the_ID(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'breakLockNonce' => wp_create_nonce('break_content_lock_nonce'),
                'strings' => [
                    'lockedTitle' => __('Content Locked', 'content-lock'),
                    'lockedMessage' => __('This content is currently being edited by', 'content-lock'),
                    'cannotEdit' => __('You cannot edit this content right now.', 'content-lock'),
                    'goBack' => __('Go Back', 'content-lock'),
                ]
            ]);
        }

        // Break lock functionality on listing pages
        if ($hook === 'edit.php') {
            wp_enqueue_script(
                'content-lock-break',
                CONTENT_LOCK_URL . 'assets/content-lock-break.js',
                ['jquery'],
                CONTENT_LOCK_VERSION,
                true
            );

            wp_localize_script('content-lock-break', 'contentLockData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'breakLockNonce' => wp_create_nonce('break_content_lock_nonce'),
            ]);
        }
    }

    /**
     * AJAX handler to check post lock status.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_check_lock_status()
    {
        check_ajax_referer('check_lock_nonce', 'nonce');

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $lock = wp_check_post_lock($post_id);

        if ($lock && $lock !== get_current_user_id()) {
            $user = get_userdata($lock);
            wp_send_json_success([
                'locked' => true,
                'locked_by' => $user ? $user->display_name : 'Someone'
            ]);
        }

        wp_send_json_success(['locked' => false]);
    }

    /**
     * AJAX handler to forcibly break a post lock.
     *
     * @since 1.0.0
     * @return void
     */
    public function break_content_lock()
    {
        check_ajax_referer('break_content_lock_nonce', 'nonce');

        if (!current_user_can('break_content_lock')) {
            wp_send_json_error(__('Permission denied', 'content-lock'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'content-lock'));
        }

        // Delete the lock
        delete_post_meta($post_id, '_edit_lock');

        // Set new lock for current user
        wp_set_post_lock($post_id);

        wp_send_json_success([
            'message' => __('Lock removed successfully', 'content-lock'),
            'redirect' => get_edit_post_link($post_id, 'raw')
        ]);
    }

    /**
     * Register lock duration setting in Settings → Reading.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_lock_duration_setting()
    {
        register_setting(
            'reading',
            'content_lock_duration_minutes',
            [
                'type' => 'integer',
                'sanitize_callback' => [$this, 'sanitize_lock_duration'],
                'default' => 20,
            ]
        );

        add_settings_field(
            'content_lock_duration_minutes',
            __('Content Lock Duration (minutes)', 'content-lock'),
            [$this, 'render_lock_duration_field'],
            'reading'
        );
    }


    /**
     * Render lock duration input field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_lock_duration_field()
    {
        $value = get_option('content_lock_duration_minutes', 20);
        ?>
        <input type="number" name="content_lock_duration_minutes" value="<?php echo esc_attr($value); ?>" min="1" max="1440"
            class="small-text" />
        <p class="description">
            <?php _e('Set how long (in minutes) a post remains locked while being edited. Range: 1–1440 minutes.', 'content-lock'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize lock duration value.
     *
     * @param int $value Minutes.
     * @since 1.0.0
     * @return int
     */
    public function sanitize_lock_duration($value)
    {
        $value = absint($value);

        if ($value < 1) {
            $value = 1;
        }

        if ($value > 1440) {
            $value = 1440;
        }

        return $value;
    }

    /**
     * Get lock duration from settings.
     *
     * @since 1.0.0
     * @return int Lock duration in seconds.
     */
    private function get_lock_duration_from_settings()
    {
        $minutes = get_option('content_lock_duration_minutes', 20);
        return absint($minutes) * 60;
    }


}

