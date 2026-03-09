<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce > Points & Rewards admin page.
 *
 * Three tabs: Manage Points, Points Log, Settings (links to main KDNA settings).
 */
class KDNA_Points_Admin {

    private $points_table;
    private $log_table;

    public function __construct() {
        global $wpdb;
        $this->points_table = $wpdb->prefix . 'kdna_points';
        $this->log_table    = $wpdb->prefix . 'kdna_points_log';

        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        add_action( 'wp_ajax_kdna_update_user_points', [ $this, 'ajax_update_user_points' ] );
        add_action( 'wp_ajax_kdna_bulk_points_action', [ $this, 'ajax_bulk_points_action' ] );
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Points & Rewards', 'kdna-ecommerce' ),
            __( 'Points & Rewards', 'kdna-ecommerce' ),
            'manage_woocommerce',
            'kdna-points',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'manage';
        ?>
        <div class="wrap">
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdna-points&tab=manage' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'manage' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Manage Points', 'kdna-ecommerce' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdna-points&tab=log' ) ); ?>"
                   class="nav-tab <?php echo $tab === 'log' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Points Log', 'kdna-ecommerce' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdna-ecommerce&tab=points' ) ); ?>"
                   class="nav-tab">
                    <?php esc_html_e( 'Settings', 'kdna-ecommerce' ); ?>
                </a>
            </nav>

            <?php
            if ( $tab === 'log' ) {
                $this->render_log_tab();
            } else {
                $this->render_manage_tab();
            }
            ?>
        </div>
        <?php
    }

    // ─── Manage Points Tab ───

    private function render_manage_tab() {
        global $wpdb;

        $per_page = 20;
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset = ( $paged - 1 ) * $per_page;
        $orderby = in_array( ( $_GET['orderby'] ?? '' ), [ 'points', 'user_email' ], true ) ? $_GET['orderby'] : 'points';
        $order = ( ( $_GET['order'] ?? '' ) === 'asc' ) ? 'ASC' : 'DESC';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        // Build query for users who have points records.
        $where = '';
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where = $wpdb->prepare( " AND (u.user_email LIKE %s OR u.display_name LIKE %s)", $like, $like );
        }

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.user_id)
             FROM {$this->points_table} p
             INNER JOIN {$wpdb->users} u ON u.ID = p.user_id
             WHERE 1=1 {$where}"
        );

        if ( $orderby === 'user_email' ) {
            $order_clause = "u.user_email {$order}";
        } else {
            $order_clause = "balance {$order}";
        }

        $rows = $wpdb->get_results(
            "SELECT p.user_id, u.user_email, u.display_name,
                    COALESCE(SUM(p.points_balance), 0) AS balance
             FROM {$this->points_table} p
             INNER JOIN {$wpdb->users} u ON u.ID = p.user_id
             WHERE 1=1 {$where}
             GROUP BY p.user_id
             ORDER BY {$order_clause}
             LIMIT {$per_page} OFFSET {$offset}"
        );

        $total_pages = ceil( $total / $per_page );
        $page_url = admin_url( 'admin.php?page=kdna-points&tab=manage' );

        // Toggle sort direction.
        $next_order = $order === 'ASC' ? 'desc' : 'asc';
        ?>
        <h2><?php esc_html_e( 'Manage Customer Points', 'kdna-ecommerce' ); ?></h2>

        <form method="get" style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
            <input type="hidden" name="page" value="kdna-points">
            <input type="hidden" name="tab" value="manage">

            <select name="bulk_action" id="kdna-bulk-action">
                <option value=""><?php esc_html_e( 'Bulk actions', 'kdna-ecommerce' ); ?></option>
                <option value="set"><?php esc_html_e( 'Set points to...', 'kdna-ecommerce' ); ?></option>
                <option value="add"><?php esc_html_e( 'Add points...', 'kdna-ecommerce' ); ?></option>
                <option value="subtract"><?php esc_html_e( 'Subtract points...', 'kdna-ecommerce' ); ?></option>
            </select>
            <input type="number" name="bulk_points_value" id="kdna-bulk-value" placeholder="<?php esc_attr_e( 'Points', 'kdna-ecommerce' ); ?>" style="width:100px; display:none;">
            <button type="button" class="button" id="kdna-bulk-apply"><?php esc_html_e( 'Apply', 'kdna-ecommerce' ); ?></button>

            <span style="margin-left:auto;"></span>

            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search customers...', 'kdna-ecommerce' ); ?>">
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'kdna-ecommerce' ); ?></button>
        </form>

        <p class="displaying-num" style="text-align:right; margin:0 0 4px;">
            <?php printf( esc_html( _n( '%s item', '%s items', $total, 'kdna-ecommerce' ) ), number_format_i18n( $total ) ); ?>
        </p>

        <table class="wp-list-table widefat fixed striped" id="kdna-manage-points-table">
            <thead>
                <tr>
                    <td class="check-column"><input type="checkbox" id="kdna-select-all"></td>
                    <th class="manage-column sortable <?php echo $orderby === 'user_email' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( add_query_arg( [ 'orderby' => 'user_email', 'order' => ( $orderby === 'user_email' ? $next_order : 'asc' ) ], $page_url ) ); ?>">
                            <span><?php esc_html_e( 'Customer', 'kdna-ecommerce' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th class="manage-column sortable <?php echo $orderby === 'points' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( add_query_arg( [ 'orderby' => 'points', 'order' => ( $orderby === 'points' ? $next_order : 'desc' ) ], $page_url ) ); ?>">
                            <span><?php esc_html_e( 'Points', 'kdna-ecommerce' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th><?php esc_html_e( 'Update', 'kdna-ecommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="4"><?php esc_html_e( 'No customer points found.', 'kdna-ecommerce' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $rows as $row ) : ?>
                <tr data-user-id="<?php echo esc_attr( $row->user_id ); ?>">
                    <th class="check-column"><input type="checkbox" class="kdna-user-cb" value="<?php echo esc_attr( $row->user_id ); ?>"></th>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row->user_id ) ); ?>">
                            <?php echo esc_html( $row->user_email ); ?>
                        </a>
                    </td>
                    <td class="kdna-points-display"><?php echo (int) $row->balance; ?></td>
                    <td>
                        <input type="number" class="kdna-update-input" value="<?php echo (int) $row->balance; ?>" style="width:100px;">
                        <button type="button" class="button kdna-update-btn"><?php esc_html_e( 'Update', 'kdna-ecommerce' ); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php $this->render_pagination( $paged, $total_pages, $page_url ); ?>

        <script>
        jQuery(function($) {
            var nonce = '<?php echo wp_create_nonce( 'kdna-points-admin' ); ?>';

            // Show/hide bulk value input.
            $('#kdna-bulk-action').on('change', function() {
                $('#kdna-bulk-value').toggle( $(this).val() !== '' );
            });

            // Select all checkbox.
            $('#kdna-select-all').on('change', function() {
                $('.kdna-user-cb').prop('checked', this.checked);
            });

            // Individual update.
            $(document).on('click', '.kdna-update-btn', function() {
                var $row = $(this).closest('tr');
                var userId = $row.data('user-id');
                var newPoints = parseInt($row.find('.kdna-update-input').val(), 10);

                if (isNaN(newPoints) || newPoints < 0) {
                    alert('<?php echo esc_js( __( 'Please enter a valid points value.', 'kdna-ecommerce' ) ); ?>');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('...');

                $.post(ajaxurl, {
                    action: 'kdna_update_user_points',
                    nonce: nonce,
                    user_id: userId,
                    points: newPoints
                }, function(resp) {
                    if (resp.success) {
                        $row.find('.kdna-points-display').text(newPoints);
                        $btn.text('<?php echo esc_js( __( 'Updated!', 'kdna-ecommerce' ) ); ?>');
                        setTimeout(function() { $btn.text('<?php echo esc_js( __( 'Update', 'kdna-ecommerce' ) ); ?>'); }, 1500);
                    } else {
                        alert(resp.data || 'Error');
                    }
                    $btn.prop('disabled', false);
                }).fail(function() {
                    alert('Request failed.');
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Update', 'kdna-ecommerce' ) ); ?>');
                });
            });

            // Bulk action.
            $('#kdna-bulk-apply').on('click', function() {
                var action = $('#kdna-bulk-action').val();
                var value = parseInt($('#kdna-bulk-value').val(), 10);
                var userIds = [];

                $('.kdna-user-cb:checked').each(function() {
                    userIds.push($(this).val());
                });

                if (!action) {
                    alert('<?php echo esc_js( __( 'Please select a bulk action.', 'kdna-ecommerce' ) ); ?>');
                    return;
                }
                if (isNaN(value) || value < 0) {
                    alert('<?php echo esc_js( __( 'Please enter a valid points value.', 'kdna-ecommerce' ) ); ?>');
                    return;
                }
                if (!userIds.length) {
                    alert('<?php echo esc_js( __( 'Please select at least one customer.', 'kdna-ecommerce' ) ); ?>');
                    return;
                }

                if (!confirm('<?php echo esc_js( __( 'Apply this action to the selected customers?', 'kdna-ecommerce' ) ); ?>')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('...');

                $.post(ajaxurl, {
                    action: 'kdna_bulk_points_action',
                    nonce: nonce,
                    bulk_action: action,
                    points: value,
                    user_ids: userIds
                }, function(resp) {
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert(resp.data || 'Error');
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Apply', 'kdna-ecommerce' ) ); ?>');
                    }
                }).fail(function() {
                    alert('Request failed.');
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Apply', 'kdna-ecommerce' ) ); ?>');
                });
            });
        });
        </script>
        <?php
    }

    // ─── Points Log Tab ───

    private function render_log_tab() {
        global $wpdb;

        $per_page = 20;
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset = ( $paged - 1 ) * $per_page;

        $filter_user = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
        $filter_type = isset( $_GET['event_type'] ) ? sanitize_text_field( $_GET['event_type'] ) : '';
        $filter_date = isset( $_GET['event_date'] ) ? sanitize_text_field( $_GET['event_date'] ) : '';

        $orderby = in_array( ( $_GET['orderby'] ?? '' ), [ 'points', 'date' ], true ) ? $_GET['orderby'] : 'date';
        $order = ( ( $_GET['order'] ?? '' ) === 'asc' ) ? 'ASC' : 'DESC';

        $where = ' WHERE 1=1';
        if ( $filter_user ) {
            $where .= $wpdb->prepare( ' AND l.user_id = %d', $filter_user );
        }
        if ( $filter_type ) {
            $where .= $wpdb->prepare( ' AND l.type = %s', $filter_type );
        }
        if ( $filter_date ) {
            // Expect YYYY-MM format.
            $where .= $wpdb->prepare( ' AND DATE_FORMAT(l.date, "%%Y-%%m") = %s', $filter_date );
        }

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$this->log_table} l
             INNER JOIN {$wpdb->users} u ON u.ID = l.user_id
             {$where}"
        );

        $order_clause = ( $orderby === 'points' ? 'l.points' : 'l.date' ) . " {$order}";

        $rows = $wpdb->get_results(
            "SELECT l.*, u.user_email, u.display_name
             FROM {$this->log_table} l
             INNER JOIN {$wpdb->users} u ON u.ID = l.user_id
             {$where}
             ORDER BY {$order_clause}
             LIMIT {$per_page} OFFSET {$offset}"
        );

        // Get distinct types for filter dropdown.
        $types = $wpdb->get_col( "SELECT DISTINCT type FROM {$this->log_table} WHERE type IS NOT NULL AND type != '' ORDER BY type" );

        // Get distinct months for date filter.
        $months = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') AS m FROM {$this->log_table} ORDER BY m DESC" );

        // Get users who have log entries for user filter.
        $log_users = $wpdb->get_results(
            "SELECT DISTINCT l.user_id, u.user_email
             FROM {$this->log_table} l
             INNER JOIN {$wpdb->users} u ON u.ID = l.user_id
             ORDER BY u.user_email"
        );

        $total_pages = ceil( $total / $per_page );
        $page_url = admin_url( 'admin.php?page=kdna-points&tab=log' );
        $next_order = $order === 'ASC' ? 'desc' : 'asc';

        $type_labels = [
            'order-placed'    => __( 'Points earned for purchase', 'kdna-ecommerce' ),
            'order-cancelled' => __( 'Order cancelled', 'kdna-ecommerce' ),
            'order-refunded'  => __( 'Order refunded', 'kdna-ecommerce' ),
            'order-redeem'    => __( 'Points redeemed', 'kdna-ecommerce' ),
            'account-signup'  => __( 'Account signup bonus', 'kdna-ecommerce' ),
            'product-review'  => __( 'Product review bonus', 'kdna-ecommerce' ),
            'expire'          => __( 'Points expired', 'kdna-ecommerce' ),
            'admin-adjust'    => __( 'Points adjusted by admin', 'kdna-ecommerce' ),
        ];
        ?>
        <h2><?php esc_html_e( 'Points Log', 'kdna-ecommerce' ); ?></h2>

        <form method="get" style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
            <input type="hidden" name="page" value="kdna-points">
            <input type="hidden" name="tab" value="log">

            <select name="user_id">
                <option value=""><?php esc_html_e( 'Show All Customers', 'kdna-ecommerce' ); ?></option>
                <?php foreach ( $log_users as $u ) : ?>
                    <option value="<?php echo esc_attr( $u->user_id ); ?>" <?php selected( $filter_user, $u->user_id ); ?>>
                        <?php echo esc_html( $u->user_email ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="event_type">
                <option value=""><?php esc_html_e( 'Show All Event Types', 'kdna-ecommerce' ); ?></option>
                <?php foreach ( $types as $type ) : ?>
                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_type, $type ); ?>>
                        <?php echo esc_html( $type_labels[ $type ] ?? ucwords( str_replace( '-', ' ', $type ) ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="event_date">
                <option value=""><?php esc_html_e( 'Show all Event Dates', 'kdna-ecommerce' ); ?></option>
                <?php foreach ( $months as $month ) : ?>
                    <option value="<?php echo esc_attr( $month ); ?>" <?php selected( $filter_date, $month ); ?>>
                        <?php echo esc_html( date_i18n( 'F Y', strtotime( $month . '-01' ) ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'kdna-ecommerce' ); ?></button>
        </form>

        <p class="displaying-num" style="text-align:right; margin:0 0 4px;">
            <?php printf( esc_html( _n( '%s item', '%s items', $total, 'kdna-ecommerce' ) ), number_format_i18n( $total ) ); ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="manage-column sortable <?php echo $orderby === 'user_email' ? 'sorted' : ''; ?>">
                        <?php esc_html_e( 'Customer', 'kdna-ecommerce' ); ?>
                    </th>
                    <th class="manage-column sortable <?php echo $orderby === 'points' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( add_query_arg( [ 'orderby' => 'points', 'order' => ( $orderby === 'points' ? $next_order : 'desc' ) ], $page_url ) ); ?>">
                            <span><?php esc_html_e( 'Points', 'kdna-ecommerce' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th><?php esc_html_e( 'Event', 'kdna-ecommerce' ); ?></th>
                    <th class="manage-column sortable <?php echo $orderby === 'date' ? 'sorted' : ''; ?>">
                        <a href="<?php echo esc_url( add_query_arg( [ 'orderby' => 'date', 'order' => ( $orderby === 'date' ? $next_order : 'desc' ) ], $page_url ) ); ?>">
                            <span><?php esc_html_e( 'Date', 'kdna-ecommerce' ); ?></span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="4"><?php esc_html_e( 'No log entries found.', 'kdna-ecommerce' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $row->user_id ) ); ?>">
                            <?php echo esc_html( $row->user_email ); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo (int) $row->points > 0 ? '+' . (int) $row->points : (int) $row->points; ?>
                    </td>
                    <td>
                        <?php echo esc_html( $type_labels[ $row->type ] ?? ucwords( str_replace( '-', ' ', $row->type ?? '' ) ) ); ?>
                    </td>
                    <td>
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->date ) ) ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php $this->render_pagination( $paged, $total_pages, $page_url ); ?>
        <?php
    }

    // ─── Pagination ───

    private function render_pagination( $current, $total_pages, $base_url ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo '<span class="pagination-links">';

        if ( $current > 1 ) {
            printf(
                '<a class="first-page button" href="%s">&laquo;</a> ',
                esc_url( add_query_arg( 'paged', 1, $base_url ) )
            );
            printf(
                '<a class="prev-page button" href="%s">&lsaquo;</a> ',
                esc_url( add_query_arg( 'paged', $current - 1, $base_url ) )
            );
        }

        printf(
            '<span class="paging-input">%d / <span class="total-pages">%d</span></span>',
            $current,
            $total_pages
        );

        if ( $current < $total_pages ) {
            printf(
                ' <a class="next-page button" href="%s">&rsaquo;</a>',
                esc_url( add_query_arg( 'paged', $current + 1, $base_url ) )
            );
            printf(
                ' <a class="last-page button" href="%s">&raquo;</a>',
                esc_url( add_query_arg( 'paged', $total_pages, $base_url ) )
            );
        }

        echo '</span></div></div>';
    }

    // ─── AJAX: Update Single User Points ───

    public function ajax_update_user_points() {
        check_ajax_referer( 'kdna-points-admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'kdna-ecommerce' ) );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        $new_points = absint( $_POST['points'] ?? 0 );

        if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
            wp_send_json_error( __( 'Invalid user.', 'kdna-ecommerce' ) );
        }

        $module = kdna_ecommerce()->get_module( 'points_rewards' );
        if ( ! $module ) {
            wp_send_json_error( __( 'Points module is not active.', 'kdna-ecommerce' ) );
        }

        $current = $module->get_user_points( $user_id );
        $diff = $new_points - $current;

        if ( $diff > 0 ) {
            $module->increase_points( $user_id, $diff, 'admin-adjust' );
        } elseif ( $diff < 0 ) {
            $module->decrease_points( $user_id, abs( $diff ), 'admin-adjust' );
        }

        wp_send_json_success( [ 'new_balance' => $new_points ] );
    }

    // ─── AJAX: Bulk Points Action ───

    public function ajax_bulk_points_action() {
        check_ajax_referer( 'kdna-points-admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'kdna-ecommerce' ) );
        }

        $action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
        $points = absint( $_POST['points'] ?? 0 );
        $user_ids = array_map( 'absint', (array) ( $_POST['user_ids'] ?? [] ) );

        if ( ! in_array( $action, [ 'set', 'add', 'subtract' ], true ) || empty( $user_ids ) ) {
            wp_send_json_error( __( 'Invalid request.', 'kdna-ecommerce' ) );
        }

        $module = kdna_ecommerce()->get_module( 'points_rewards' );
        if ( ! $module ) {
            wp_send_json_error( __( 'Points module is not active.', 'kdna-ecommerce' ) );
        }

        $count = 0;
        foreach ( $user_ids as $user_id ) {
            if ( ! get_user_by( 'id', $user_id ) ) {
                continue;
            }

            $current = $module->get_user_points( $user_id );

            switch ( $action ) {
                case 'set':
                    $diff = $points - $current;
                    if ( $diff > 0 ) {
                        $module->increase_points( $user_id, $diff, 'admin-adjust' );
                    } elseif ( $diff < 0 ) {
                        $module->decrease_points( $user_id, abs( $diff ), 'admin-adjust' );
                    }
                    break;
                case 'add':
                    if ( $points > 0 ) {
                        $module->increase_points( $user_id, $points, 'admin-adjust' );
                    }
                    break;
                case 'subtract':
                    if ( $points > 0 ) {
                        $module->decrease_points( $user_id, min( $points, $current ), 'admin-adjust' );
                    }
                    break;
            }
            $count++;
        }

        wp_send_json_success( sprintf(
            /* translators: %d is the number of customers updated */
            __( 'Updated %d customers.', 'kdna-ecommerce' ),
            $count
        ) );
    }

    // ─── Handle non-AJAX form actions ───

    public function handle_actions() {
        // Reserved for future form-based actions.
    }
}
