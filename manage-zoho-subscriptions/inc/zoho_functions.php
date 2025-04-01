<?php

/**
 * Retrieves the Zoho access token.
 *
 * This function first checks if the access token is already stored in the options table.
 * If it is, it checks if the token is still valid. If it is, it returns the token.
 * If the token is not valid or is not stored, it makes a POST request to the Zoho API
 * to retrieve a new access token using the refresh token.
 *
 * @return string The Zoho access token.
 */
if (!function_exists('getZohoAccessToken')):
    function getZohoAccessToken()
    {
        // Retrieve the stored token details from the options table (if using WordPress)
        $stored_token_details = get_option('zoho_api_access_token');
        $token_details = unserialize($stored_token_details);

        // Check if the token exists and if it has expired
        if ($token_details && isset($token_details['access_token']) && isset($token_details['expires_in'])) {
            if (time() < $token_details['expires_in']) {
                // Token is still valid, return it
                return $token_details['access_token'];
            } else {
                // Token has expired, log it and proceed to refresh
                error_log('Access token has expired, refreshing token.');
            }
        } else {
            // No valid token found, need to get a new one
            error_log('No valid access token found, obtaining a new one.');
        }
        // Initialize cURL session
        $ch = curl_init();

        // Set the URL for the request
        curl_setopt($ch, CURLOPT_URL, "https://accounts.zoho.com/oauth/v2/token");

        // Set method to POST
        curl_setopt($ch, CURLOPT_POST, 1);

        // Prepare the POST fields
        $post = [
            'refresh_token' => ZOHO_REFRESH_TOKEN,
            'client_id' => ZOHO_CLIENT_ID,
            'client_secret' => ZOHO_CLIENT_SECRET,
            'grant_type' => 'refresh_token'
        ];

        // Set the POST fields
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

        // Return response instead of outputting
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Optional: Disable SSL verification (not recommended for production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if ($response === false) {
            // Log cURL error
            error_log('Curl error: ' . curl_error($ch));
            curl_close($ch);
            return null; // Return null on error
        }

        // Close the cURL session
        curl_close($ch);

        // Process the response
        $response_data = json_decode($response);

        // Check if the response contains an access token
        if (isset($response_data->access_token)) {
            // Successfully received access token
            $access_token = $response_data->access_token;

            // Optional: Save the access token and expiration time if needed
            // Store the new access token in the options table (if using WordPress)
            $token_details = [
                'access_token' => $access_token,
                'expires_in' => time() + 3600 // Token expires in 1 hour
            ];
            update_option('zoho_api_access_token', serialize($token_details)); // Assuming you are using WordPress

            // Return the new access token
            return $access_token;
        } else {
            // Handle the case where no access token is received
            error_log('Error: No access token found. Response: ' . json_encode($response_data));
        }

        return null; // Return null if the access token is not found
    }
endif;

/**
 * Returns an array containing the headers for Zoho API requests.
 *
 * This function retrieves the access token for Zoho API requests and returns
 * an array containing the 'Authorization' header with the access token.
 *
 * @return array An array containing the 'headers' key with the 'Authorization' header.
 */
if (!function_exists('getZohoApisHeader')):
    function getZohoApisHeader(): array
    {
        // Retrieve the access token for Zoho API requests
        $access_token = getZohoAccessToken();

        // Return an array containing the 'Authorization' header with the access token
        return [
            'headers' => [
                'X-com-zoho-subscriptions-organizationid' => ZOHO_ORGANIZATION_ID,
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ];
    }
endif;

/**
 * Retrieves a Zoho customer based on their email address.
 *
 * This function makes a GET request to the Zoho API to fetch customer details
 * based on the provided email address.
 *
 * @param String $email The email address of the customer to retrieve.
 * @return array|bool An array containing the customer details if the request is successful, or false if the request fails.
 */
if (!function_exists('getCustomerByEmail')):
    function getCustomerByEmail(String $email, $zoho_customer_id = null)
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to fetch customer details based on email
        $get_contact_url = "https://www.zohoapis.com/billing/v1/customers?email=" . urldecode($email);
        error_log("Retrieves customer by email: " . $get_contact_url);

        // Make API request to fetch customer details based on email
        $response = wp_remote_get($get_contact_url, $zoho_api_header);
        error_log("Retrieves customer by email response: " . wp_remote_retrieve_body($response));

        // Check if the response is successful and the HTTP status code is 200
        if (!is_wp_error($response) && $response['response']['code'] === 200) {
            // Return the customer details as an array
            return json_decode(wp_remote_retrieve_body($response), true);
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Retrieves a Zoho customer based on their customer ID.
 *
 * This function makes a GET request to the Zoho API to fetch customer details
 * based on the provided customer ID.
 *
 * @param String $zoho_customer_id The Zoho customer ID to retrieve.
 * @return array|bool An array containing the customer details if the request is successful, or false if the request fails.
 */
if (!function_exists('getCustomerById')):
    function getCustomerById($zoho_customer_id)
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to fetch customer details based on customer id
        $get_contact_url = "https://www.zohoapis.com/billing/v1/customers/{$zoho_customer_id}";

        // Make API request to fetch customer details based on customer id
        $response = wp_remote_get($get_contact_url, $zoho_api_header);
        error_log("Retrieves customer by id response: " . wp_remote_retrieve_body($response));

        // Check if the response is successful and the HTTP status code is 200
        if (!is_wp_error($response) && $response['response']['code'] === 200) {
            // Return the customer details as an array
            return json_decode(wp_remote_retrieve_body($response), true);
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Creates a Zoho customer based on the provided user object.
 *
 * This function makes a POST request to the Zoho API to create a new customer
 * based on the provided user object.
 *
 * @param WP_User $user_obj The user object to create a Zoho customer for.
 * @return array|bool An array containing the new customer details if the request is successful, or false if the request fails.
 */
if (!function_exists('createZohoCustomer')):
    function createZohoCustomer($user_obj)
    {
        // Zoho API endpoint for creating a customer
        $create_customer_url = 'https://www.zohoapis.com/billing/v1/customers';

        // Retrieve the Zoho API access token
        $zoho_api_header = getZohoApisHeader();

        // Zoho organization ID (replace with your actual organization ID)
        $organization_id = ZOHO_ORGANIZATION_ID;
        error_log('Organization ID: ' . $organization_id);

        // Prepare the customer data based on the user object
        $customer_data = [
            'display_name' => $user_obj->display_name,
            'email'        => $user_obj->user_email,
            'first_name'   => $user_obj->first_name,
            'last_name'    => $user_obj->last_name,
            // Add any other necessary fields required by Zoho API
        ];

        // Set the headers for the POST request
        $headers = array_merge($zoho_api_header['headers'], [
            'X-com-zoho-subscriptions-organizationid' => $organization_id,
            'Content-Type' => 'application/json',
        ]);

        // Make the API request to create the customer
        $response = wp_remote_post($create_customer_url, [
            'headers' => $headers,
            'body'    => json_encode($customer_data), // Convert the customer data to JSON format
            'timeout' => 45, // Optional: Set a reasonable timeout
        ]);
        error_log('Create customer response: ' . wp_remote_retrieve_body($response));


        // Check if the request failed (e.g., due to network issues)
        if (is_wp_error($response)) {
            return ['error' => 'WP Error', 'details' => $response->get_error_message()];
        }

        // Get the response body from the API request
        $body = wp_remote_retrieve_body($response);
        error_log('Create customer response body: ' . $body);

        // Decode the response into an associative array
        $response_data = json_decode($body, true);


        // Check if the customer was created successfully by inspecting the response data
        if (isset($response_data['customer']['customer_id'])) {
            // Return the Zoho customer data including the customer ID
            return $response_data;
        } else {
            // Log or return error details for debugging
            return [
                'error' => 'API Error',
                'response' => $response_data,
                'status_code' => wp_remote_retrieve_response_code($response),
            ];
        }
    }
endif;

/**
 * Retrieves card details for a Zoho customer based on their Zoho customer ID.
 *
 * This function makes a GET request to the Zoho API to fetch the stored card details
 * of the customer using their Zoho customer ID.
 *
 * @param String $zoho_customer_id The Zoho customer ID to retrieve the card details for.
 * @return array|bool An array containing the card details if the request is successful, or false if the request fails.
 */
if (!function_exists('getCustomerCardDetails')):
    function getCustomerCardDetails($zoho_customer_id)
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to fetch card details based on the Zoho customer ID
        $get_cards_url = "https://www.zohoapis.com/billing/v1/customers/{$zoho_customer_id}/cards";

        // Make API request to fetch customer card details
        $response = wp_remote_get($get_cards_url, $zoho_api_header);

        // Check if the response is successful and the HTTP status code is 200
        if (!is_wp_error($response) && $response['response']['code'] === 200) {
            // Return the card details as an array
            return json_decode(wp_remote_retrieve_body($response), true);
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Sets the payment method for a customer in Zoho using the provided customer ID.
 *
 * This function makes a POST request to the Zoho API to create a new hosted page
 * for adding a payment method to the customer's account. The payment gateways
 * specified in the request body are used to generate the hosted page URL.
 *
 * @param String $zoho_customer_id The Zoho customer ID to set the payment method for.
 * @return String The URL of the hosted page to add the payment method.
 */
if (!function_exists('setPaymentMethodForCustomerPage')):
    function setPaymentMethodForCustomerPage($zoho_customer_id)
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to fetch card details based on the Zoho customer ID
        $set_payment_url = "https://www.zohoapis.com/billing/v1/hostedpages/addpaymentmethod";

        $headers = array(
            'X-com-zoho-subscriptions-organizationid' => ZOHO_ORGANIZATION_ID,
            'Content-Type' => 'application/json',
            'Authorization' =>  $zoho_api_header['headers']['Authorization'],
        );

        // Set the request body
        $customer_data = array(
            'customer_id' => $zoho_customer_id,
            // 'redirect_url' => 'http://www.zillum.com/products/piperhost',
            'payment_gateways' => array(
                array(
                    'payment_gateway' => 'stripe'
                )
            )
        );

        // Make the API request to create the customer
        $payment_response = wp_remote_post($set_payment_url, [
            'headers' => $headers,
            'body'    => json_encode($customer_data), // Convert the customer data to JSON format
        ]);

        // Check if the response is successful and the HTTP status code is 200
        if (!is_wp_error($payment_response) && $payment_response['response']['code'] === 201) {

            $body = wp_remote_retrieve_body($payment_response);

            $iframe_url = json_decode($body, true)['hostedpage']['url'];

            // Return the card details as an array
            return $iframe_url;
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Sets a subscription on a Zoho customer using the provided Zoho customer ID.
 *
 * This function makes a POST request to the Zoho API to create a new subscription
 * for the customer using the provided customer ID and a test plan code.
 *
 * @param String $zoho_customer_id The Zoho customer ID to set the subscription for.
 * @return array|bool An array containing the subscription details if the request is successful, or false if the request fails.
 */
if (!function_exists('setSubscriptionOnCustomer')):
    function setSubscriptionOnCustomer($zoho_customer_id, $subscription_type = 'test_code')
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to fetch card details based on the Zoho customer ID
        $set_payment_url = "https://www.zohoapis.com/billing/v1/subscriptions";

        $headers = array(
            'X-com-zoho-subscriptions-organizationid' => ZOHO_ORGANIZATION_ID,
            'Content-Type' => 'application/json',
            'Authorization' =>  $zoho_api_header['headers']['Authorization'],
        );

        // Set the request body
        $customer_data = array(
            "customer_id" => $zoho_customer_id,
            "plan" => array(
                "plan_code" => $subscription_type,
            ),
            "auto_collect" => true
        );

        // Make the API request to create the customer
        $payment_response = wp_remote_post($set_payment_url, [
            'headers' => $headers,
            'body'    => json_encode($customer_data), // Convert the customer data to JSON format
        ]);

        error_log('Payment response: ' . wp_remote_retrieve_body($payment_response));

        // Check if the response is successful and the HTTP status code is 200
        if (!is_wp_error($payment_response) && $payment_response['response']['code'] === 201) {

            return json_decode(wp_remote_retrieve_body($payment_response), true);
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Updates a subscription for a Zoho customer using the provided customer ID.
 *
 * This function makes a PUT request to the Zoho API to update the subscription
 * details for a customer using the given customer ID and subscription type.
 *
 * @param string $zoho_customer_id The Zoho customer ID whose subscription is to be updated.
 * @param string $subscription_type The subscription plan code to update to. Default is 'test_code'.
 * @return array|bool An array containing the updated subscription details if the request is successful, or false if the request fails.
 */
if (!function_exists('updateSubscriptionOnCustomer')):
    function updateSubscriptionOnCustomer($subscription_data)
    {
        error_log('Update subsctipion data: ' . print_r($subscription_data, true));
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to update the subscription based on the Zoho customer ID
        $update_subciption_url = "https://www.zohoapis.com/billing/v1/subscriptions/" . $subscription_data['zoho_subscription_id'];
        error_log('Update subsctipion url: ' . $update_subciption_url);

        $headers = array(
            'X-com-zoho-subscriptions-organizationid' => ZOHO_ORGANIZATION_ID,
            'Content-Type' => 'application/json',
            'Authorization' =>  $zoho_api_header['headers']['Authorization'],
        );

        // Set the request body
        $customer_data = array(
            "card_id" => $subscription_data['card_id'],
            "plan" => array(
                "plan_code" => $subscription_data['subscription_type'],
            ),
            "customer_id" => $subscription_data['zoho_customer_id'],
            "auto_collect" => true
        );

        // Make the API request to update the customer subscription
        $payment_response = wp_remote_request($update_subciption_url, [
            'method'  => 'PUT',  // PUT method to update the subscription
            'headers' => $headers,
            'body'    => json_encode($customer_data), // Convert the customer data to JSON format
        ]);

        error_log('Update subsctipion response: ' . wp_remote_retrieve_body($payment_response));

        // Check if the response is successful and the HTTP status code is 200
        if (!is_wp_error($payment_response) && wp_remote_retrieve_response_code($payment_response) === 200) {
            return json_decode(wp_remote_retrieve_body($payment_response), true);
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Retrieves the hosted page URL for updating a customer's payment method in Zoho using the customer ID and card ID.
 *
 * @param string $zoho_customer_id The Zoho customer ID to update the payment method for.
 * @param string $card_id The ID of the card to update the payment method with.
 * @return string|false The hosted page URL if the request is successful, or false if the request fails.
 */
if (!function_exists('updatePaymentMethodForCustomerPage')):
    function updatePaymentMethodForCustomerPage($zoho_customer_id, $card_id)
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL for updating payment method
        $update_payment_url = "https://www.zohoapis.com/billing/v1/hostedpages/updatepaymentmethod";

        $headers = array(
            'X-com-zoho-subscriptions-organizationid' => ZOHO_ORGANIZATION_ID,
            'Content-Type' => 'application/json',
            'Authorization' => $zoho_api_header['headers']['Authorization'],
        );

        // Set the request body
        $customer_data = array(
            'customer_id' => $zoho_customer_id,
            'card_id' => $card_id,
        );

        // Make the API request to update the payment method
        $response = wp_remote_post($update_payment_url, [
            'headers' => $headers,
            'body'    => json_encode($customer_data), // Convert the data to JSON format
        ]);

        // Check if the response is successful and the HTTP status code is 201
        if (!is_wp_error($response) && $response['response']['code'] === 201) {
            $body = wp_remote_retrieve_body($response);
            $iframe_url = json_decode($body, true)['hostedpage']['url'];

            // Return the hosted page URL
            return $iframe_url;
        }

        // Return false if the request fails
        return false;
    }
endif;

/**
 * Cancels an existing subscription for a customer in Zoho using the provided customer ID.
 *
 * This function makes a request to the Zoho API to cancel the subscription
 * associated with the given Zoho customer ID.
 *
 * @param string $zoho_customer_id The Zoho customer ID whose subscription is to be canceled.
 * @return array|bool An array containing the cancellation details if the request is successful, or false if the request fails.
 */
if (!function_exists('cancelSubscriptionOnCustomer')):
    function cancelSubscriptionOnCustomer($zoho_subscription_id)
    {
        // Retrieve the Zoho API headers
        $zoho_api_header = getZohoApisHeader();

        // Construct the API URL to cancel the subscription
        $cancel_subscription_url = "https://www.zohoapis.com/billing/v1/subscriptions/" . $zoho_subscription_id . "/cancel";

        $headers = array(
            'X-com-zoho-subscriptions-organizationid' => ZOHO_ORGANIZATION_ID,
            'Content-Type' => 'application/json',
            'Authorization' => $zoho_api_header['headers']['Authorization'],
        );

        // Set the request body
        $customer_data = array(
            "cancel_at_end" => false, // Set to `true` if you want to cancel at the end of the billing cycle
        );

        // Make the API request to cancel the subscription
        $cancel_response = wp_remote_post($cancel_subscription_url, [
            'headers' => $headers,
            'body'    => json_encode($customer_data),
            'method'  => 'POST'
        ]);

        // Check if the response is successful
        if (!is_wp_error($cancel_response) && $cancel_response['response']['code'] === 200) {
            return json_decode(wp_remote_retrieve_body($cancel_response), true);
        }

        // Return false if the request fails
        return false;
    }
endif;
