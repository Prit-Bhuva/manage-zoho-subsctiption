<?php

/**
 * Create table to store customer call time preferences in Zoho.
 *
 * @since 1.0.0
 *
 * @return void
 */

use Vonage\Voice\Webhook\Error;

if (!function_exists('create_customer_calls_table')):
    function create_customer_calls_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ha_customer_calls_services';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        zoho_customer_id varchar(255) NOT NULL,
        subscription_id varchar(255) NOT NULL,
        name varchar(255) NOT NULL,
        phone varchar(20) NOT NULL,
        timezone varchar(50) NOT NULL,
        notify_name varchar(255) NOT NULL,
        notify_email varchar(255) NOT NULL,
        notify_phone varchar(20) NOT NULL,
        call_time_1 varchar(10) NULL,
        call_1_call_type tinyint(1) DEFAULT 0 NOT NULL,
        call_time_2 varchar(10) NULL,
        call_2_call_type tinyint(1) DEFAULT 0 NOT NULL,
        status varchar(50) NOT NULL,
        send_sms tinyint(1) DEFAULT 0 NOT NULL,
        is_paused tinyint(1) DEFAULT 0 NOT NULL,
        is_call_sent TINYINT(1) DEFAULT 0 NOT NULL,
        is_call_sent_2 TINYINT(1) DEFAULT 0 NOT NULL,
        is_answered_call_1 TINYINT(1) DEFAULT 0 NOT NULL,
        is_answered_call_2 TINYINT(1) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
endif;

/**
 * Create table to store customer subscriptions in Zoho.
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('create_customer_subscription_table')):
    function create_customer_subscription_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ha_customer_subscriptions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        zoho_customer_id varchar(255) NOT NULL,
        subscription_id varchar(255) NOT NULL,
        plan_code varchar(255) NOT NULL,
        status varchar(20) NOT NULL,
        -- name varchar(255) NOT NULL,
        -- phone varchar(20) NOT NULL,
        -- timezone varchar(50) NOT NULL,
        start_date datetime NOT NULL,
        end_date datetime NOT NULL,
        next_billing_date datetime NOT NULL,
        subscription_response LONGTEXT  NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
endif;

/**
 * Create table to store user call logs.
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('create_user_call_logs_table')):
    function create_user_call_logs_table()
    {
        global $wpdb;

        // Specify the table name with the WordPress prefix
        $table_name = $wpdb->prefix . 'user_call_logs';
        $charset_collate = $wpdb->get_charset_collate();

        // SQL to create the table
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            call_service_id bigint(20) NOT NULL,
            call_to varchar(255) NOT NULL,
            call_from varchar(255) NOT NULL,
            call_name varchar(255) NOT NULL,
            con_uuid varchar(255) NOT NULL,
            uuid varchar(255) NOT NULL,
            original_phone_no varchar(20) NOT NULL,
            notify_type varchar(255) NOT NULL,
            call_status varchar(20) NOT NULL,
            is_answered tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Include the necessary WordPress file and run the query
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
endif;



/**
 * Create table to store second time call logs.
 *
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('create_second_time_call_logs_table')):
    function create_second_time_call_logs_table()
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_second_time_call_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        call_service_id bigint(20) NOT NULL,
        call_name varchar(255) NOT NULL,
        contact_no varchar(20) NOT NULL,
        call_from varchar(255) NOT NULL,
        call_type_selection tinyint(1) DEFAULT 0 NOT NULL,
        con_uuid varchar(255) NOT NULL,
        notify_type varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
endif;

/**
 * Create table to store SMS logs.
 *
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('create_send_sms_logs_table')):
    function create_send_sms_logs_table()
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_send_sms_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        call_service_id bigint(20) NOT NULL,
        call_notify_to varchar(20) NOT NULL,
        call_from varchar(255) NOT NULL,
        call_type_selection tinyint(1) DEFAULT 0 NOT NULL,
        is_sms_sent tinyint(1) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
endif;

/**
 * Create table to store Vonage logs.
 *
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('create_vonage_log_table')):
    function create_vonage_log_table()
    {

        global $wpdb;

        $table_name = $wpdb->prefix . 'vonage_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        con_uuid varchar(255) NOT NULL,
        uuid varchar(255) NOT NULL,
        call_to varchar(255) NOT NULL,
        call_from varchar(255) NOT NULL,
        status varchar(20) NOT NULL,
        notify_type varchar(255) NOT NULL,
        response LONGTEXT  NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
endif;

/**
 * Truncate the ha_customer_subscriptions and ha_customer_calls_services tables.
 * 
 * @return void
 */
function truncateTable()
{
    global $wpdb;
    // Truncate the ha_customer_subscriptions table
    /*  $table_name = $wpdb->prefix . 'ha_customer_subscriptions';
    $sql = "TRUNCATE TABLE $table_name";
    $wpdb->query($sql); */

    // Truncate the ha_customer_calls_services table
    // $table_name = $wpdb->prefix . 'ha_customer_calls_services';
    // $sql = "TRUNCATE TABLE $table_name";
    // $wpdb->query($sql);
}


/**
 * Inserts customer subscription and call data into the database.
 *
 * @param int $user_id The ID of the current user.
 * @param string $zoho_customer_id The Zoho customer ID.
 * @param array $subscription_response The response from the subscription API.
 * @param string $name The name of the customer.
 * @param string $phone The phone number of the customer.
 * @param string $timezone The timezone of the customer.
 * @param string $call_time_1 The first call time.
 * @param string|null $call_time_2 The second call time or null.
 */
if (!function_exists('insertCustomerData')):
    function insertCustomerData($data)
    {
        error_log('Insert data: ' . print_r($data, true));
        global $wpdb;

        // Extract values from the $data array
        $user_id = $data['user_id'];
        $zoho_customer_id = $data['zoho_customer_id'];
        $subscription_response = $data['subscription_response'];
        $name = $data['name'];
        $phone = $data['phone'];
        $notify_name = $data['notify_name'];
        $notify_phone = $data['notify_phone'];
        $notify_email = $data['notify_email'];
        $timezone = $data['timezone'];
        $call_time_1 = $data['call_time_1'];
        $call_1_call_type = $data['call_1_call_type'];
        $call_time_2 = $data['call_time_2'];
        $call_2_call_type = $data['call_2_call_type'];
        $status = $subscription_response['subscription']['status'];
        $send_sms = $data['send_sms'];

        $start_date = ($status === 'active' || $status === 'live') && isset($subscription_response['subscription']['start_date'])
            ? $subscription_response['subscription']['start_date']
            : $subscription_response['subscription']['trial_starts_at'];

        $end_date = ($status === 'active' || $status === 'live') && isset($subscription_response['subscription']['current_term_ends_at'])
            ? $subscription_response['subscription']['current_term_ends_at']
            : $subscription_response['subscription']['trial_ends_at'];

        // Insert into customer subscriptions table
        $table_subscriptions = $wpdb->prefix . 'ha_customer_subscriptions';

        $wpdb->insert($table_subscriptions, [
            'user_id' => $user_id,
            'zoho_customer_id' => $zoho_customer_id,
            'subscription_id' => $subscription_response['subscription']['subscription_id'],
            'plan_code' => $subscription_response['subscription']['plan']['plan_code'],
            'status' => $status,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'next_billing_date' => $subscription_response['subscription']['next_billing_at'],
            'subscription_response' => json_encode($subscription_response),
        ]);

        // Check for errors in subscriptions insertion
        if ($wpdb->last_error) {
            return new WP_Error('db_insert_error', 'Error inserting customer subscription: ' . $wpdb->last_error);
        }

        // Insert into customer calls services table
        $table_calls = $wpdb->prefix . 'ha_customer_calls_services';

        $wpdb->insert($table_calls, [
            'user_id' => $user_id,
            'zoho_customer_id' => $zoho_customer_id,
            'subscription_id' => $subscription_response['subscription']['subscription_id'],
            'name' => $name,
            'phone' => $phone,
            'timezone' => $timezone,
            'notify_name' => $notify_name,
            'notify_email' => $notify_email,
            'notify_phone' => $notify_phone,
            'call_time_1' => $call_time_1,
            'call_1_call_type' => $call_1_call_type,
            'call_time_2' => $call_time_2,
            'call_2_call_type' => $call_2_call_type,
            'status' => $subscription_response['subscription']['status'],
            'send_sms' => $send_sms

        ]);

        // Check for errors in calls insertion
        if ($wpdb->last_error) {
            return new WP_Error('db_insert_error', 'Error inserting customer calls: ' . $wpdb->last_error);
        }

        return true; // Return true if both insertions are successful
    }
endif;

/**
 * Updates customer call data in the database.
 *
 * @param array $data An associative array containing the data to update.
 *  The array should have the following keys:
 *  - id (int): The ID of the customer call to update.
 *  - name (string): The name of the customer.
 *  - phone (string): The phone number of the customer.
 *  - timezone (string): The timezone of the customer.
 *  - call_time_1 (string|null): The first call time.
 *  - call_time_2 (string|null): The second call time or null.
 *
 * @return bool|WP_Error True if the update was successful, or a WP_Error object if an error occurred.
 */
if (!function_exists('updateCustomerCallData')):
    function updateCustomerCallData($data)
    {
        error_log('Updated data: ' . print_r($data, true));
        global $wpdb;

        // Extract values from the $data array
        $edit_id = isset($data['id']) ? intval($data['id']) : null; // Ensure $edit_id is an integer
        $name = isset($data['name']) ? $data['name'] : '';
        $phone = isset($data['phone']) ? $data['phone'] : '';
        $timezone = isset($data['timezone']) ? $data['timezone'] : '';
        $notify_name = $data['notify_name'];
        $notify_phone = $data['notify_phone'];
        $notify_email = $data['notify_email'];
        $call_time_1 = isset($data['call_time_1']) ? $data['call_time_1'] : null;
        $call_1_call_type = $data['call_1_call_type'];
        $call_time_2 = isset($data['call_time_2']) ? $data['call_time_2'] : null;
        $call_2_call_type = $data['call_2_call_type'];
        $send_sms = $data['send_sms'];
        $update_subscription_response = $data['update_subscription_response'];


        // Table name
        $table_calls = $wpdb->prefix . 'ha_customer_calls_services';
        $subscription_table = $wpdb->prefix . 'ha_customer_subscriptions';

        // get the subscriotion id based on the edit id from the servcice table
        $subscription_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT subscription_id FROM $table_calls WHERE id = %d",
                $edit_id
            )
        );

        // update the plan_code and status on the subscription table
        $wpdb->update(
            $subscription_table,
            [
                'plan_code' => $update_subscription_response['subscription']['plan']['plan_code'],
                'status' => $update_subscription_response['subscription']['status'],
                'subscription_response' => json_encode($update_subscription_response)
            ],
            ['subscription_id' => $subscription_id]
        );

        if ($wpdb->last_error) {
            return new WP_Error('db_update_error', 'Error updating subscription: ' . $wpdb->last_error);
        }

        // Update only the specified fields
        $result = $wpdb->update(
            $table_calls,
            [
                'name' => $name,
                'phone' => $phone,
                'timezone' => $timezone,
                'notify_name' => $notify_name,
                'notify_email' => $notify_email,
                'notify_phone' => $notify_phone,
                'call_time_1' => $call_time_1,
                'call_1_call_type' => $call_1_call_type,
                'call_time_2' => $call_time_2,
                'call_2_call_type' => $call_2_call_type,
                'send_sms' => $send_sms
            ],
            ['id' => $edit_id]
        );

        return $result;

        /* // Check for errors in calls update
        if ($result === false) {
            return new WP_Error('db_update_error', 'Error updating customer calls: ' . $wpdb->last_error);
        } elseif ($result === 0) {
            // If result is 0, it means no rows were affected
            return new WP_Error('no_rows_updated', 'No rows were updated. Please check the ID.');
        }

        return true;  */ // Return true if the update was successful
    }
endif;

/**
 * Displays customer call data in a table.
 *
 * This function displays customer call data in a table for easy viewing. The
 * table includes columns for the customer's name, phone number, time zone, and
 * call times. The table also includes an "Action" column with links to edit or
 * cancel the customer's call data.
 *
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('displayCustomerCallData')):
    function displayCustomerCallData()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ha_customer_calls_services';
        $current_user_id = get_current_user_id();

        // SQL query with WHERE clause to filter by the logged-in user's ID
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $current_user_id);
        $results = $wpdb->get_results($sql);

        if ($results) {
?>
            <div id="service-message"></div>
            <table class="wp-list-table widefat" id="services-table">
                <tr>
                    <th>Name of Person</th>
                    <th>Mobile Number</th>
                    <th>Time Zone</th>
                    <th>Call Time 1</th>
                    <th>Call Time 2</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>
                <?php
                foreach ($results as $result) {
                    // $call_type_selection = $result->call_type_selection == 1 ? 'A Medication Reminder Call' : 'A General Welfare Check Call';
                ?>
                    <tr>
                        <td><?php echo $result->name; ?></td>
                        <td><?php echo $result->phone; ?></td>
                        <td><?php echo $result->timezone; ?></td>
                        <td><?php echo $result->call_time_1; ?></td>
                        <td><?php echo $result->call_time_2; ?></td>
                        <td><?php echo $result->status; ?></td>
                        <td>

                            <a href="<?php echo site_url('/welfare-services-form/') ?>?edit_id=<?php echo $result->id; ?>" class="edit-service">Edit</a>

                            <a href="javascript:void(0);"
                                class="cancel-subscription"
                                data-subscription-id="<?php echo $result->subscription_id; ?>"
                                data-customer-id="<?php echo $result->zoho_customer_id; ?>">
                                Cancel
                            </a>

                            <a href="javascript:void(0);"
                                class="toggle-pause"
                                data-service-id="<?php echo $result->id; ?>"
                                data-paused="<?php echo $result->is_paused; ?>">
                                <?php echo ($result->is_paused == 1) ? 'Resume' : 'Pause'; ?>
                            </a>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </table>
            <?php
        } else {
            echo '<p class="warning-msg">Currently, no services have been added.</p>';
        }
    }
endif;


/**
 * Displays customer card details.
 *
 * This function displays customer card details .
 * The table includes columns for the card status, last 4 digits of the card number,
 * and the card expiry date.
 *
 * @since 1.0.0
 *
 * @return void
 */
if (!function_exists('displayCustomerCardData')) :
    function displayCustomerCardData()
    {
        $user_id = get_current_user_id();
        $zoho_customer_id = get_user_meta($user_id, 'zoho_customer_id', true);

        // Fetch card details
        if ($zoho_customer_id) {
            $card_details = getCustomerCardDetails($zoho_customer_id);


            if (!empty($card_details['cards'])) {

                foreach ($card_details['cards'] as $card) {
                    // display message if card is expire
                    /*  if ($card['status'] == 'Expired') {
                        echo '<p class="warning-msg">Your card has expired.</p>';
                    } */

            ?>
                    <div class="card-info">
                        <table class="card-details-table" style="width: 50%;">
                            <tr>
                                <th>Card Status</th>
                                <td><?php echo esc_html(ucfirst($card['status'])); ?></td>
                            </tr>
                            <tr>
                                <th>Card Last 4 digit</th>
                                <td><?php echo esc_html($card['last_four_digits']); ?></td>
                            </tr>
                            <tr>
                                <th>Card Expiry date</th>
                                <td><?php echo esc_html($card['expiry_month']); ?>/<?php echo esc_html($card['expiry_year']); ?></td>
                            </tr>
                        </table>
                    </div>
<?php
                }
            } else {
                echo '<p class="nofound-msg">No Payment Details Found.</p>';
            }
        }
    }
endif;

/**
 * 
 * Registers a REST endpoint to handle Zoho subscription updates.
 *
 *This function registers a POST endpoint at /wp-json/zoho-subscription/update
 * that calls the handleZohoSubscriptionUpdate() function when invoked.
 * 
 * 
 * Handles Zoho subscription updates by updating the status of the subscription
 * in two tables: ha_customer_calls_services and ha_customer_subscriptions.
 *
 * @param WP_REST_Request $request The request object containing the
 *                                 subscription ID and status.
 *
 * @return WP_REST_Response A response object indicating the success or failure
 *                          of the update.
 */
if (!function_exists('registerZohoSubscriptionEndpoint')):
    function registerZohoSubscriptionEndpoint()
    {
        register_rest_route('zoho-subscription', '/update', array(
            'methods' => 'POST',
            'callback' => 'handleZohoSubscriptionUpdate',
            'permission_callback' => '__return_true'
        ));
    }
    add_action('rest_api_init', 'registerZohoSubscriptionEndpoint');
endif;


if (!function_exists('handleZohoSubscriptionUpdate')):
    function handleZohoSubscriptionUpdate($request)
    {
        error_log('Handling Zoho subscription update...');
        global $wpdb;

        // Step 1: Parse and validate the incoming data
        $data = $request->get_json_params();

        if (empty($data) || !isset($data['data']['subscription'])) {
            return new WP_Error('invalid_data', 'Invalid subscription data received', array('status' => 400));
        }

        $subscription_data = $data['data']['subscription'];

        // Validate required fields
        $required_fields = ['subscription_id', 'status', 'updated_time'];
        foreach ($required_fields as $field) {
            if (empty($subscription_data[$field])) {
                return new WP_Error('missing_field', "Missing field: $field", array('status' => 400));
            }
        }

        // Step 2: Extract and sanitize necessary data
        $subscription_id = sanitize_text_field($subscription_data['subscription_id']);
        $status = sanitize_text_field($subscription_data['status']);
        $updated_time_raw = sanitize_text_field($subscription_data['updated_time']);

        try {
            $updated_at = (new DateTime($updated_time_raw))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return new WP_Error('invalid_date', 'Invalid date format for updated_time', array('status' => 400));
        }

        // Prepare the data array for the update query
        $update_data = [
            'status' => $status,
            'updated_at' => $updated_at,
            'subscription_response' => json_encode($data),
        ];

        // Only add next billing date and end date if status is active or in_trial
        if (in_array($status, ['active', 'trial', 'live'])) {
            $next_billing_date = !empty($subscription_data['next_billing_at'])
                ? sanitize_text_field($subscription_data['next_billing_at'])
                : null;

            // Set end_date based on status
            if ($status === 'active' || $status === 'live') {
                $subscription_end_date = !empty($subscription_data['current_term_ends_at'])
                    ? sanitize_text_field($subscription_data['current_term_ends_at'])
                    : null;
            } elseif ($status === 'trial') {
                $subscription_end_date = !empty($subscription_data['trial_ends_at'])
                    ? sanitize_text_field($subscription_data['trial_ends_at'])
                    : null;
            }

            $update_data['next_billing_date'] = $next_billing_date;
            $update_data['end_date'] = $subscription_end_date;
        }

        // Define table and column names
        $customer_service_table = $wpdb->prefix . 'ha_customer_calls_services';
        $customer_subscription_table = $wpdb->prefix . 'ha_customer_subscriptions';
        $subscription_id_column = 'subscription_id';

        // Step 3: Start a database transaction for atomic updates
        $wpdb->query('START TRANSACTION');

        // Step 4: Update subscription status in the ha_customer_subscriptions table
        $subscription_result = $wpdb->update(
            $customer_subscription_table,
            $update_data,
            [$subscription_id_column => $subscription_id]
        );

        // Step 5: Update the status of the subscription in the ha_customer_calls_services table
        $service_result = $wpdb->update(
            $customer_service_table,
            [
                'status' => $status,
            ],
            [$subscription_id_column => $subscription_id]
        );

        // Step 6: Check if either update failed and handle transaction
        if (false === $service_result || false === $subscription_result) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Database update failed', array('status' => 500));
        }

        // Step 7: Commit the transaction
        $wpdb->query('COMMIT');

        return new WP_REST_Response('Subscription status updated successfully in the database.', 200);
    }
endif;
