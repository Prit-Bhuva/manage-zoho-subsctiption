<?php

/**
 * Register a custom REST API route to handle Vonage webhook.
 */
add_action('rest_api_init', function () {
    register_rest_route('vonage/webhook', '/dtmf', [
        'methods'  => 'GET',
        'callback' => 'handle_vonage_dtmf_webhook',
        'permission_callback' => '__return_true', // Allow public access to the webhook.
    ]);
});

/**
 * Handle Vonage DTMF webhook requests.
 *
 * @param WP_REST_Request $request The incoming request object.
 * @return WP_REST_Response The response object.
 */
function handle_vonage_dtmf_webhook(WP_REST_Request $request)
{
    // Retrieve query parameters from the GET request.
    $dtmf = $request->get_param('dtmf'); // DTMF digit sent by the user.
    $timestamp = $request->get_param('timestamp'); // Optional: Timestamp of the event.

    // Log or process the DTMF data.
    if (!empty($dtmf)) {
        // Example: Log the DTMF data to a file.
        $log = "Received DTMF: $dtmf at $timestamp" . PHP_EOL;
        file_put_contents(WP_CONTENT_DIR . '/dtmf-log.txt', $log, FILE_APPEND);

        // You can add further logic here (e.g., saving to the database, triggering actions, etc.).
    }

    // Respond to Vonage with a success message.
    return new WP_REST_Response([
        'status'  => 'success',
        'message' => 'DTMF received successfully.',
    ], 200);
}