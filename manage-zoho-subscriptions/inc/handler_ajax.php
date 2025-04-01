<?php

/**
 * Handles the AJAX request to add a customer to Zoho Subscriptions.
 *
 * @since 1.0.0
 */
add_action('wp_ajax_add_customer_to_zoho', 'mzs_add_customer_to_zoho');  // Hook for authenticated users
add_action('wp_ajax_nopriv_add_customer_to_zoho', 'mzs_add_customer_to_zoho');  // Hook for non-authenticated users

if (!function_exists('mzs_add_customer_to_zoho')):
    function mzs_add_customer_to_zoho()
    {
        // Verify the nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'add_customer_to_zoho')) {
            wp_send_json_error(['message' => 'Invalid nonce.']);
            wp_die();
        }

        // Validate user input
        if (empty($_POST['userId'])) {
            wp_send_json_error(['message' => 'User ID is required.']);
            wp_die();
        }

        // Get the WordPress user details
        $user_id = sanitize_text_field($_POST['userId']);
        error_log('User ID: ' . $user_id);
        $user_obj = get_user_by('id', $user_id);

        if (!$user_obj) {
            wp_send_json_error(['message' => 'User not found.']);
            wp_die();
        }

        // Get the user email
        $user_email = $user_obj->user_email;
        error_log('User email: ' . $user_email);

        // Check if the user already has a Zoho customer ID stored in their meta field
        $zoho_customer_id = get_user_meta($user_id, 'zoho_customer_id', true);
        error_log('Zoho customer ID: ' . $zoho_customer_id);

        // Check if the user is created in Zoho
        $get_customer = getCustomerById($zoho_customer_id);
        error_log('Get customer by id: ' . json_encode($get_customer));

        // If the user is not created in Zoho, create them
        if (empty($get_customer)) {
            $create_response = createZohoCustomer($user_obj);

            if ($create_response && isset($create_response['customer']) && !empty($create_response['customer']['customer_id'])) {
                $zoho_customer_id = $create_response['customer']['customer_id'];
                update_user_meta($user_id, 'zoho_customer_id', $zoho_customer_id);
                error_log('Updated user meta: ' . json_encode(get_user_meta($user_id, 'zoho_customer_id')));
            } else {
                $message = $create_response['customer']['message'] ?? 'Customer not found or not created in Zoho.';
                return json_encode(['message' => $message, 'customer_exists' => false]);
                wp_die();
            }
        }

        // Get the Zoho customer ID and check for stored card details
        if ($zoho_customer_id) {
            // If Zoho customer ID exists, check for stored card details
            $card_details = getCustomerCardDetails($zoho_customer_id);
            error_log('Card details: ' . json_encode($card_details));

            // If card details are found, get the first card's ID
            if ($card_details && isset($card_details['cards']) && count($card_details['cards']) > 0) {
                $card_id = $card_details['cards'][0]['card_id']; // Get the first card's ID

                // Call updatePaymentMethodForCustomerPage if card_id exists
                if ($card_id) {
                    $update_payment_url = updatePaymentMethodForCustomerPage($zoho_customer_id, $card_id);
                    error_log('Update payment URL: ' . $update_payment_url);

                    if ($update_payment_url) {
                        wp_send_json_success([
                            'status' => 200,
                            'is_already_set' => true,
                            'update_iframe_url' => $update_payment_url,
                            'message' => 'Updated payment method URL.',
                        ]);
                        wp_die();
                    }
                }
            }

            // If card details are not found, call manageCustomerCardDetails
            $manage_details = manageCustomerCardDetails($user_id, $zoho_customer_id, $card_details);
            error_log('Manage details: ' . json_encode($manage_details));

            if ($manage_details) {
                wp_send_json_success([
                    'status' => 200,
                    'is_already_set' => true,
                    'iframe_url' => true,
                    'message' => 'set payment method url.'
                ]);
                wp_die();
            }

            // If card details are not found, call setPaymentMethodForCustomerPage
            $set_payment_method = setPaymentMethodForCustomerPage($zoho_customer_id);
            error_log("Set Payment method 1: " . json_encode($set_payment_method));

            wp_send_json_success([
                'status' => 200,
                'is_already_set' => false,
                'iframe_url' => $set_payment_method,
                'message' => 'set payment method url.'
            ]);
        }

        // Handle customer creation or lookup
        $get_zoho_customer = getCustomerByEmail($user_email);
        error_log("Get zoho customer by email: " . json_encode($get_zoho_customer));

        if (empty($get_zoho_customer['customers'])) {
            // Create new customer
            $create_response = createZohoCustomer($user_obj);

            // Check if customer creation was successful
            if ($create_response && isset($create_response['customer']) && !empty($create_response['customer']['customer_id'])) {
                $zoho_customer_id = $create_response['customer']['customer_id'];
                update_user_meta($user_id, 'zoho_customer_id', $zoho_customer_id);
                // update_user_meta($user_id, 'zoho_customer_id', $zoho_customer_id);

                $set_payment_method = setPaymentMethodForCustomerPage($zoho_customer_id);
                error_log("Set Payment method 2: " . json_encode($set_payment_method));

                wp_send_json_success([
                    'status' => 200,
                    'is_already_set' => false,
                    'iframe_url' => $set_payment_method,
                    'message' => 'set payment method url.'
                ]);
            } else {
                wp_send_json_error(['message' => $create_response['customer']['message'], 'customer_exists' => false]);
            }
        }

        // Save the Zoho customer ID and fetch card details
        $zoho_customer_id = $get_zoho_customer['customers'][0]['customer_id'];
        error_log('zoho custom id after if condition: ', $zoho_customer_id);

        update_user_meta($user_id, 'zoho_customer_id', $zoho_customer_id);

        // Check card details for the new customer
        $card_details = getCustomerCardDetails($zoho_customer_id);
        error_log('Card details after if condition from the zoho id: ', $card_details);

        // Manage card details
        $manage_details = manageCustomerCardDetails($user_id, $zoho_customer_id, $card_details);
        error_log('Manage Card details after if condition from the zoho id: ', $manage_details);

        // If card details are not found, call setPaymentMethodForCustomerPage
        if ($manage_details == false) {
            $set_payment_method = setPaymentMethodForCustomerPage($zoho_customer_id);
            error_log("Set Payment method 3: " . json_encode($set_payment_method));

            wp_send_json_success([
                'status' => 200,
                'is_already_set' => false,
                'iframe_url' => $set_payment_method,
                'message' => 'set payment method url.'

            ]);
        }

        wp_send_json_success($manage_details);

        wp_die();
    }
endif;


/**
 * Manages the card details of a customer in Zoho and updates the user meta
 * 
 * @param int $user_id The user ID of the customer
 * @param string $zoho_customer_id The customer ID in Zoho
 * @param array $card_details The card details of the customer
 * 
 * @return boolean True if card details are found, false otherwise
 */
if (!function_exists('manageCustomerCardDetails')):
    function manageCustomerCardDetails($user_id, $zoho_customer_id, $card_details)
    {
        $has_card_details = ($card_details && isset($card_details['cards']) && count($card_details['cards']) > 0) ? true : false;

        // If card details are found, update user meta
        update_user_meta($user_id, 'has_zoho_card_details', "$has_card_details");

        return $has_card_details;
    }
endif;

/**
 * Filters phone number based on the timezone.
 * 
 * If the timezone is from Australia, it appends "+61" to the phone number if it is 9 digits long.
 * If the timezone is from Kolkata, it appends "+91" to the phone number if it is 10 digits long.
 * 
 * @param string $phone_number Phone number to filter
 * @param string $timezone Timezone to check
 * @return string Filtered phone number
 */
if (!function_exists('getFilteredPhoneNumber')) {
    function getFilteredPhoneNumber($phone_number, $timezone)
    {
        // Check if the timezone contains 'Australia' and format accordingly.
        if (strpos($timezone, 'Australia') !== false) {
            if (strlen($phone_number) === 9) {
                return "+61{$phone_number}";
            } else if (strlen($phone_number) === 10) {
                return '+61' . ltrim($phone_number, "0");
            } else {
                return $phone_number;
            }
        } else if (strpos($timezone, 'Kolkata') !== false && strlen($phone_number) === 10) {  // Check if the timezone contains 'Kolkata' and format accordingly.
            return "+91{$phone_number}";
        }

        // Return the phone number unchanged if no conditions match.
        return $phone_number;
    }
}


/**
 * Handles the call participants form submission and creates a subscription in Zoho Books.
 *
 * Sanitizes the input data, assumes the $zoho_customer_id is available, creates a subscription using setSubscriptionOnCustomer()
 * and inserts the customer data into the database using insertCustomerData().
 *
 * @since 1.0.0
 */
add_action('wp_ajax_add_call_participants', 'handle_add_call_participants');
add_action('wp_ajax_nopriv_add_call_participants', 'handle_add_call_participants'); // Allow non-logged in users if needed

if (!function_exists('handle_add_call_participants')):
    function handle_add_call_participants()
    {
        global $wpdb;

        // Check if the user is logged in
        if (!is_user_logged_in()) {
            echo 'You must be logged in to submit this form.';
            wp_die();
        }

        error_log('handle_add_call_participants ID: ' . json_encode($_POST));

        // Sanitize input data
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
        $name = sanitize_text_field($_POST['name']);
        $timezone = sanitize_text_field($_POST['timezone']);
        $phone = getFilteredPhoneNumber($_POST['phone'], $timezone);
        $notify_name = sanitize_text_field($_POST['notify_name']);
        $notify_email = sanitize_text_field($_POST['notify_email']);
        $notify_phone = getFilteredPhoneNumber($_POST['notify_phone'], $timezone);
        $call_1_call_type = isset($_POST['call_1_call_type']) ? intval($_POST['call_1_call_type']) : [];
        $call_2_call_type = isset($_POST['call_2_call_type']) ? intval($_POST['call_2_call_type']) : [];
        $send_sms = !empty($_POST['sms_notification']) ? $_POST['sms_notification'] : 0;

        // Process call time arrays (ensure two values)
        $call_hour = isset($_POST['call_hour']) ? $_POST['call_hour'] : [];
        $call_minute = isset($_POST['call_minute']) ? $_POST['call_minute'] : [];
        $call_time_formate = isset($_POST['call_time_formate']) ? $_POST['call_time_formate'] : [];

        // Format call times
        $call_times = [];
        for ($i = 0; $i < count($call_hour); $i++) {
            if (!empty($call_hour[$i]) && !empty($call_minute[$i]) && !empty($call_time_formate[$i])) {
                $call_times[] = $call_hour[$i] . ':' . $call_minute[$i] . ' ' . $call_time_formate[$i];
            }
        }

        // Prepare data for DB insertion
        $call_time_1 = isset($call_times[0]) ? $call_times[0] : null;
        $call_time_2 = isset($call_times[1]) ? $call_times[1] : null;

        // Assume $zoho_customer_id is available
        $user_id = get_current_user_id();
        $zoho_customer_id = get_user_meta($user_id, 'zoho_customer_id', true);

        $subscription_type = $send_sms == '1' ? 'sms_include' : 'test_code';

        if ($edit_id && $edit_id > 0) {

            error_log('Edit ID: ' . $edit_id);
            // get the subscription_id data based on the $edit id from the wp_ha_customer_calls_services table 
            $service_query = "SELECT subscription_id FROM wp_ha_customer_calls_services WHERE id = {$edit_id}";
            $subscription_id = $wpdb->get_var($service_query);
            error_log('Service Query: ' . $subscription_id);

            // update the subscription
            $get_card_details = getCustomerCardDetails($zoho_customer_id);
            $card_id = $get_card_details['cards'][0]['card_id'];

            $update_subcription_args =  [
                'zoho_subscription_id' => $subscription_id,
                'zoho_customer_id' => $zoho_customer_id,
                'subscription_type' => $subscription_type,
                'card_id' => $card_id
            ];
            $update_subscription = updateSubscriptionOnCustomer($update_subcription_args);
            error_log("Updated subscription: " . json_encode($update_subscription));

            $update_subscription_response = '';
            // if got the usbscription repsonse then update the plan code and status on the subscriotion table
            if ($update_subscription) {
                $update_subscription_response = $update_subscription;
            }

            $data = [
                'id' => $edit_id,
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
                'send_sms' => $send_sms,
                'update_subscription_response' => $update_subscription_response
            ];

            $update_result = updateCustomerCallData($data);

            if (is_wp_error($update_result)) {
                echo "Error: {$update_result->get_error_message()}";
            } else {
                wp_send_json_success([
                    'status' => 200,
                    'redirect_url' => site_url('/my-services/'),
                    'message' => 'Customer updated successfully.'
                ]);
            }
        }

        // Call the function to create a subscription
        $subscription_response = setSubscriptionOnCustomer($zoho_customer_id, $subscription_type); // Adjust as per your implementation

        if ($subscription_response) {
            // Prepare data array for insertCustomerData
            $data = [
                'user_id' => $user_id,
                'zoho_customer_id' => $zoho_customer_id,
                'subscription_response' => $subscription_response,
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
            ];

            // Insert the customer data
            $insert_result = insertCustomerData($data);

            if (is_wp_error($insert_result)) {
                echo "Error: {$insert_result->get_error_message()}";
            } else {

                wp_send_json_success([
                    'status' => 200,
                    'zoho_customer_id' => $zoho_customer_id,
                    'subscription_id' => $subscription_response['subscription']['subscription_id'],
                    'redirect_url' => site_url('/my-services/'),
                    'message' => 'Subscription created and data stored successfully!.'
                ]);
                // echo "Subscription created and data stored successfully!";
            }
        } else {
            echo "Failed to create subscription.";
        }

        wp_die();
    }
endif;


/**
 * Handles the AJAX request to cancel a subscription for a customer.
 *
 * This function checks the incoming POST request for 'subscription_id' and 'customer_id'.
 * It attempts to cancel the subscription using the provided IDs by calling the 
 * cancelSubscriptionOnCustomer() function. If successful, it updates the status of the 
 * subscription in the database to "Canceled". If the cancellation or the update fails, 
 * it sends an appropriate JSON error response.
 *
 * @since 1.0.0
 */
add_action('wp_ajax_cancel_subscription', 'ajax_cancel_subscription_handler');
add_action('wp_ajax_nopriv_cancel_subscription', 'ajax_cancel_subscription_handler'); // For non-logged-in users if needed

if (!function_exists('ajax_cancel_subscription_handler')) {

    function ajax_cancel_subscription_handler()
    {
        global $wpdb;

        // Define table names
        $table_name = $wpdb->prefix . 'ha_customer_calls_services';
        $subscription_table = $wpdb->prefix . 'ha_customer_subscriptions';

        // Check if subscription_id and customer_id are present in the POST request
        if (isset($_POST['subscription_id']) && isset($_POST['customer_id'])) {
            $subscription_id = sanitize_text_field($_POST['subscription_id']);
            $customer_id = sanitize_text_field($_POST['customer_id']);

            // Call the function to cancel the subscription
            $canceled_response = cancelSubscriptionOnCustomer($subscription_id);

            // Handle the case where cancellation response indicates already canceled or inactive
            if ($canceled_response === false) {
                $subscription_status = $canceled_response['subscription']['status'];
                handleAlreadyCanceledSubscription($wpdb, $table_name, $subscription_table, $customer_id, $subscription_id, $subscription_status);
            } else {
                // If cancellation is successful
                if (isset($canceled_response['code']) && $canceled_response['code'] === 0) {
                    // Successful cancellation
                    $subscription_status = $canceled_response['subscription']['status'];

                    // Update status in the ha_customer_calls_services table
                    $update_calls_services_result = $wpdb->update(
                        $table_name,
                        array('status' => $subscription_status),
                        array('zoho_customer_id' => $customer_id, 'subscription_id' => $subscription_id),
                        array('%s'),
                        array('%s', '%s')
                    );

                    // Update status in the ha_customer_subscriptions table
                    $update_subscriptions_result = $wpdb->update(
                        $subscription_table,
                        array('status' => $subscription_status),
                        array('zoho_customer_id' => $customer_id, 'subscription_id' => $subscription_id),
                        array('%s'),
                        array('%s', '%s')
                    );

                    // Check if updates were successful
                    if ($update_calls_services_result !== false && $update_subscriptions_result !== false) {
                        wp_send_json_success("Subscription canceled and status updated successfully.");
                    } else {
                        wp_send_json_error("Subscription canceled, but failed to update status in the database.");
                    }
                } else {
                    wp_send_json_error("Failed to cancel subscription. Please try again.");
                }
            }
        } else {
            wp_send_json_error("Invalid request.");
        }

        wp_die(); // Properly terminate the AJAX request
    }

    /**
     * Handle the situation where the subscription is already canceled or inactive.
     *
     * @param object $wpdb WordPress database object.
     * @param string $table_name Table for customer calls services.
     * @param string $subscription_table Table for customer subscriptions.
     * @param string $customer_id Customer ID.
     * @param string $subscription_id Subscription ID.
     */
    function handleAlreadyCanceledSubscription($wpdb, $table_name, $subscription_table, $customer_id, $subscription_id, $subscription_status)
    {
        // Fetch the current status from the database
        $current_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM $subscription_table WHERE zoho_customer_id = %s AND subscription_id = %s",
                $customer_id,
                $subscription_id
            )
        );

        // Check if the current status is not 'cancelled'
        if ($current_status !== $subscription_status) {
            // Update status to 'inactive' in both tables
            $update_calls_services_result = $wpdb->update(
                $table_name,
                array('status' => $subscription_status), // Adjust this as needed
                array('zoho_customer_id' => $customer_id, 'subscription_id' => $subscription_id),
                array('%s'),
                array('%s', '%s')
            );

            $update_subscriptions_result = $wpdb->update(
                $subscription_table,
                array('status' => $subscription_status), // Adjust this as needed
                array('zoho_customer_id' => $customer_id, 'subscription_id' => $subscription_id),
                array('%s'),
                array('%s', '%s')
            );

            // Check if updates were successful
            if ($update_calls_services_result !== false && $update_subscriptions_result !== false) {
                wp_send_json_success("Subscription is already canceled or in an inactive state.");
            } else {
                wp_send_json_error("Subscription is already canceled, but failed to update status in the database.");
            }
        } else {
            wp_send_json_success("Subscription is already canceled or in an inactive state.");
        }
    }
}

/**
 * Handles the AJAX request to toggle pause status of a service.
 *
 * This function will update the `is_paused` column of the `ha_customer_calls_services`
 * table to either 0 or 1, based on the value of `is_paused` passed in the request.
 *
 * @since 1.0.0
 */
if (!function_exists('toggle_pause_service')):
    function toggle_pause_service()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ha_customer_calls_services';

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $is_paused = isset($_POST['is_paused']) ? intval($_POST['is_paused']) : 0;

        if (!$service_id) {
            wp_send_json_error(['message' => 'Invalid service ID']);
        }

        // Toggle the status
        $new_status = $is_paused ? 0 : 1;

        $update = $wpdb->update(
            $table_name,
            ['is_paused' => $new_status],
            ['id' => $service_id],
            ['%d'],
            ['%d']
        );

        if ($update !== false) {
            wp_send_json_success(['is_paused' => $new_status]);
        } else {
            wp_send_json_error(['message' => 'Database update failed']);
        }
    }

    add_action('wp_ajax_toggle_pause_service', 'toggle_pause_service');
    add_action('wp_ajax_nopriv_toggle_pause_service', 'toggle_pause_service');
endif;
