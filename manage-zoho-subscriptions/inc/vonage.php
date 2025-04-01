<?php

/**
 * VonageController class
 * 
 * @package vonage
 */
if (!class_exists('VonageController')) :
    class VonageController
    {
        private $client;

        public function __construct()
        {

            // Load the private key from a local file or secure location
            $privateKeyPath = WP_CONTENT_DIR . '/uploads/private.key';

            // Check if the private key file exists
            if (!file_exists($privateKeyPath)) {
                error_log('Vonage private key file not found.');
                return;
            }

            // Set the keypair
            $keypair = new Vonage\Client\Credentials\Keypair(
                file_get_contents($privateKeyPath),
                "78fbcb71-4289-49a7-88f4-e648c0b894d1" // Replace with your own key ID
            );

            // Set the client
            $this->client = new Vonage\Client($keypair);

            // Register REST route for Vonage events
            add_action('rest_api_init', function () {
                register_rest_route('vonage', '/events', [
                    'methods' => 'POST',
                    'callback' => [$this, 'handle_vonage_event'],
                    'permission_callback' => '__return_true', // Adjust as needed for your permissions
                ]);
            });

            // Register REST route for Vonage DTMF
            add_action('rest_api_init', function () {
                register_rest_route('vonage/webhook', '/dtmf', [
                    'methods'  => 'GET',
                    'callback' => [$this, 'handle_vonage_dtmf_webhook'],
                    'permission_callback' => '__return_true', // Allow public access to the webhook.
                ]);
            });
        }

        /**
         * Make a call to the user using Vonage.
         *
         * @param array $args Associative array with the following keys:
         *  - to (string): The phone number to call.
         *  - from (string): The phone number to use as the caller ID.
         *  - name (string): The name of the customer.
         *  - user_id (int): The ID of the current user.
         *  - message (string): The message to play during the call.
         * @param string $notify_type The type of notification to send to the user.
         *
         * @return \Vonage\Voice\OutboundCall|\Vonage\Voice\Endpoint\Phone|null The result of the
         *  call if successful, or null if an error occurred.
         */
        public function makeCall($args, $notify_type)
        {

            insert_log_in_db('Start Making Call');

            if (empty($this->client)) {
                error_log("Vonage client not initialized.");
                return null;
            }

            try {
                // Set up outbound call endpoints
                $to = new \Vonage\Voice\Endpoint\Phone($args['to']);
                $from = new \Vonage\Voice\Endpoint\Phone($args['from']);
                $outboundCall = new \Vonage\Voice\OutboundCall($to, $from);

                $outgoing_audio_stream = site_url() . '/wp-content/uploads/2025/02/welfare-check-outgoing.wav';
                $general_audio_stream = site_url() . '/wp-content/uploads/2025/02/general-welfare.wav';
                $medication_reminder_stream = site_url() . '/wp-content/uploads/2025/02/medication-reminder.wav';
                $unanswered_audio_stream = site_url() . '/wp-content/uploads/2025/02/unanswered-call-message.wav';

                // Set the correct audio stream based on notify_type and call_type
                if ($notify_type == 'unanswered') {
                    $audio_stream = $unanswered_audio_stream;
                } elseif ($args['call_type'] == 1) {
                    $audio_stream = $medication_reminder_stream;
                } else {
                    $audio_stream = $general_audio_stream;
                }

                // Select appropriate audio stream based on call type
                // $audio_stream = ($args['call_type'] == 1) ? $medication_reminder_stream : $general_audio_stream;
                error_log('Audio Stream: ' . $audio_stream);

                // Add NCCO Action with a message
                $ncco = new \Vonage\Voice\NCCO\NCCO();
                // $talk = new \Vonage\Voice\NCCO\Action\Talk($args['message']);
                $stream = new \Vonage\Voice\NCCO\Action\Stream($audio_stream);
                $stream->setBargeIn(true);

                // make input from user
                $input = new Vonage\Voice\NCCO\Action\Input();
                $input->setEnableDtmf(true)
                    ->setDtmfTimeout(10)
                    ->setDtmfMaxDigits(1)
                    ->setEventWebhook(new \Vonage\Voice\Webhook(rest_url('vonage/webhook/dtmf'), 'GET'));

                $ncco->addAction($stream)->addAction($input);
                $outboundCall->setNCCO($ncco);

                // Attempt to make the call
                $response = $this->client->voice()->createOutboundCall($outboundCall);

                if ($response) {
                    error_log('# ===============> Call Response ');
                    // Extract conversation UUID and call UUID
                    $array = (array)$response;
                    $prefix = chr(0) . '*' . chr(0); // Ensure compatibility with properties
                    $con_uuid = $array[$prefix . 'conversationUuid'] ?? null;
                    $uuid = $array[$prefix . 'uuid'] ?? null;

                    if ($con_uuid && $uuid) {
                        error_log('# ===============> Got con_uuid and uuid');
                        global $wpdb;

                        $user_id = !empty($args['user_id']) && $args['user_id'] != 0
                            ? $args['user_id']
                            : get_current_user_id();

                        // Insert call data into `user_call_logs`
                        $wpdb->insert(
                            $wpdb->prefix . 'user_call_logs',
                            [
                                'user_id' =>  $user_id,
                                'call_service_id' =>  $args['call_service_id'],
                                'call_to' => $args['to'],
                                'call_from' => $args['from'],
                                'call_name' => $args['name'],
                                'con_uuid' => $con_uuid,
                                'uuid' => $uuid,
                                'original_phone_no' => $args['from'],
                                'notify_type' => $notify_type,
                                'call_status' => $response->getStatus(),
                            ],
                            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                        );
                        return $response;
                    } else {
                        error_log("Error: Missing con_uuid or uuid in Vonage response.");
                    }
                } else {
                    error_log("Vonage call response is empty or null.");
                }
            } catch (Exception $e) {
                error_log("Error in makeCall method: " . $e->getMessage());
            }
            return null;
        }

        /**
         * Handles Vonage events and processes the call data for retries and updates.
         * 
         * @param WP_REST_Request $request The request object containing the Vonage event data.
         * 
         * @return WP_REST_Response The response object indicating the success or failure of event processing.
         */
        public function handle_vonage_event(WP_REST_Request $request)
        {
            global $wpdb;

            // Retrieve the data sent by Vonage
            $eventData = json_decode($request->get_body(), true);

            // Ensure conversation UUID is present
            if (empty($eventData['conversation_uuid'])) {
                return new WP_REST_Response('Invalid request', 400);
            }

            // Call statuses from Vonage
            $callStatus = $eventData['status'] ?? null;
            $conversationUUID = $eventData['conversation_uuid'];

            // Get the current call log
            $get_call_log = get_current_call_data($conversationUUID);

            if (!$get_call_log) {
                return new WP_REST_Response('Call log not found', 404);
            }

            // Handle calls that need to be retried
            $retryStatuses = ['cancelled', 'rejected', 'busy', 'timeout', 'failed', 'unanswered'];

            // if (in_array($callStatus, $retryStatuses)) {
            if ($callStatus == 'completed' && ($get_call_log->notify_type == 'call_1' || $get_call_log->notify_type == 'call_2')) {
                $notify_type = 'second-call';

                // Only add to the second-time call log if no second-call exists and name count is less than 2
                if ($get_call_log->is_answered == 0) {
                    $call_type_selection = $get_call_log->call_2_call_type ?: $get_call_log->call_1_call_type;

                    // Only add to the second-time call log if no record exists
                    $wpdb->insert(
                        $wpdb->prefix . 'user_second_time_call_logs',
                        [
                            'user_id' => $get_call_log->user_id ?? get_current_user_id(),
                            'call_service_id' => $get_call_log->call_service_id,
                            'contact_no' => $get_call_log->call_to,
                            'call_from' => $get_call_log->call_from,
                            'call_type_selection' => $call_type_selection,
                            'call_name' => $get_call_log->call_name,
                            'con_uuid' => $conversationUUID,
                            'notify_type' => $notify_type
                        ],
                        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                    );

                    // Schedule a retry for the second call after 15 minutes
                    // wp_schedule_single_event(time() + 5 * 60, 'second_time_make_call', [$conversationUUID]);
                    // wp_schedule_single_event(time() + 15 * 60, 'second_time_make_call', [$conversationUUID]);
                }

                // Create a Vonage log
                create_vonage_log((object)[
                    'con_uuid' => $conversationUUID,
                    'uuid' => $eventData['uuid'] ?? null,
                    'call_to' => $get_call_log->call_to ?? null,
                    'call_from' => $get_call_log->call_from ?? null,
                    'status' => $callStatus,
                    'notify_type' => $get_call_log->notify_type,
                    'response' => json_encode($eventData),
                ]);
            }

            // Update call log based on the current status
            if ($callStatus && $get_call_log) {
                /* if ($callStatus == 'completed' && ( $get_call_log->notify_type == 'call_1' || $get_call_log->notify_type == 'call_2' || $get_call_log->notify_type == 'second-call') && $get_call_log->is_answered == 1) {
                    $wpdb->update(
                        $wpdb->prefix . 'user_call_logs',
                        ['is_answered' => 1],
                        ['con_uuid' => $conversationUUID, 'id' => $get_call_log->id],
                        ['%d'],
                        ['%s', '%d']
                    );
                } */

                // Update call status in user_call_logs
                $wpdb->update(
                    $wpdb->prefix . 'user_call_logs',
                    ['call_status' => $callStatus],
                    ['con_uuid' => $conversationUUID, 'id' => $get_call_log->id],
                    ['%s'],
                    ['%s', '%d']
                );
            }

            // Check if the current call log is for a second call
            if ($callStatus == 'completed') {
                error_log('===========================> notify types ' . $get_call_log->call_name . ' ---- ' . $get_call_log->notify_type);
                // if (in_array($callStatus, $retryStatuses)) {
                if ($get_call_log->is_answered == 1) {
                    // Get the caring person's name
                    $caringPeopleName = $get_call_log->call_name ?? 'Caring Person';

                    // Generate the message based on the call status
                    // $message = get_call_status_wise_msg($callStatus, $caringPeopleName, $user_call_answered);
                    $message =  $caringPeopleName . "  responded to our call ✅";

                    $userdata = get_userdata($get_call_log->user_id);
                    $user_email = $userdata->user_email;
                    $notify_email = $get_call_log->notify_email;

                    // if notify_email not then use user_email
                    if (empty($notify_email)) {
                        $notify_email = $user_email;
                    }
                    error_log('email message is: ' . $message . ' - ' . $notify_email);


                    $headers = [
                        'From: No-Reply <no-reply@carealert.com.au>', // Replace with your desired From address
                        'Content-Type: text/html; charset=UTF-8',
                    ];

                    // Send the email notification
                    $recipient_email = $notify_email;
                    $subject = "Welfare Carealert Notification";
                    wp_mail($recipient_email, $subject, $message, $headers);
                } else if ($get_call_log->notify_type == 'second-call' || $get_call_log->notify_type == 'second_call_attempt') {

                    // Get the caring person's name
                    $caringPeopleName = $get_call_log->call_name ?? 'Caring Person';
                    $call_type_selection = $get_call_log->call_2_call_type ?: $get_call_log->call_1_call_type;

                    // Generate the message based on the call status
                    // $message = get_call_status_wise_msg($callStatus, $caringPeopleName, $user_call_answered);
                    $message =  $caringPeopleName . " did not respond to our calls, please take action ❤";
                    error_log('email  message is: ' . $message . ' - ' . $get_call_log->notify_email);

                    $userdata = get_userdata($get_call_log->user_id);
                    $user_email = $userdata->user_email;
                    $notify_email = $get_call_log->notify_email;

                    // if notify_email not then use user_email
                    if (empty($notify_email)) {
                        $notify_email = $user_email;
                    }

                    $headers = [
                        'From: No-Reply <no-reply@carealert.com.au>', // Replace with your desired From address
                        'Content-Type: text/html; charset=UTF-8',
                    ];

                    // Send the email notification
                    $recipient_email = $notify_email;

                    // $recipient_email = 'testing.email7804@gmail.com';
                    $subject = "Welfare Carealert Notification";

                    wp_mail($recipient_email, $subject, $message, $headers);


                    if (!empty($get_call_log->notify_phone)) {
                        // Prepare SMS Data
                        $sms_data = [
                            'user_id' => $get_call_log->user_id,
                            'call_service_id' => $get_call_log->call_service_id,
                            'to' => $get_call_log->call_to,
                            'from' => $get_call_log->call_from,
                            'notify_email' => $notify_email,
                            'notify_phone' => $get_call_log->notify_phone,
                            'message' => $message,
                            'call_type_selection' => $call_type_selection,
                        ];

                        $unanswered_args = [
                            'user_id' => $get_call_log->user_id,
                            'call_service_id' => $get_call_log->call_service_id,
                            'name' => $get_call_log->notify_name,
                            'to' => $get_call_log->notify_phone,
                            'from' => '61485805667',
                        ];

                        error_log('Unanswered args are: ' . json_encode($unanswered_args));

                        // make call 
                        $this->makeCall($unanswered_args, 'unanswered');

                        // Send SMS
                        if ($get_call_log->send_sms == 1) {
                            error_log('sending sms....');
                            $this->sendSms($sms_data);
                        }
                    }
                }
            }

            // Insert Vonage log with consistent property names
            create_vonage_log((object)[
                'call_to' => $eventData['to'] ?? null,
                'call_from' => $eventData['from'] ?? null,
                'uuid' => $eventData['uuid'] ?? null,
                'con_uuid' => $conversationUUID,
                'status' => $callStatus,
                'notify_type' => $get_call_log->notify_type ?? 'call',
                'response' => json_encode($eventData)
            ]);

            return new WP_REST_Response('Event processed', 200);
        }

        /**
         * Handles Vonage DTMF webhook requests.
         *
         * @param WP_REST_Request $request The incoming request object.
         *
         * @return WP_REST_Response The response object.
         */
        function handle_vonage_dtmf_webhook(WP_REST_Request $request)
        {
            global $wpdb;

            $table_name = $wpdb->prefix . 'user_call_logs';

            // Retrieve query parameters from the GET request.
            $dtmf = $request->get_param('dtmf'); // DTMF digit sent by the user.
            $to = $request->get_param('to'); // Optional: TO of the event.
            $from = $request->get_param('from'); // Optional: FROM of the event.
            $con_uuid = $request->get_param('conversation_uuid'); // Optional: CON UUID of the event.
            $uuid = $request->get_param('uuid'); // Optional: UUID of the event.

            // Log or process the DTMF data.
            if (!empty($dtmf)) {
                // Log the DTMF data.
                $result = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE con_uuid = %d AND uuid = %s",
                        $con_uuid,
                        $uuid
                    )
                );

                // Check if the row exists.
                if ($result) {
                    // Update the row if it exists.
                    $wpdb->update(
                        $table_name,  // Table name.
                        ['is_answered' => 1],    // New data to update.
                        ['id' => $result->id],       // Condition (WHERE clause).
                    );
                }

                // Insert Vonage log with consistent property names
                create_vonage_log((object)[
                    'call_to' => $to,
                    'call_from' => $from,
                    'uuid' => $uuid,
                    'con_uuid' => $con_uuid,
                    'status' => '',
                    'notify_type' => 'dtmf',
                    'response' => json_encode($request->get_params())
                ]);
            }

            // /** @var \Vonage\Voice\Webhook\Input $input */
            // $stream_url = env('AWS_WAV_FILE_URL') . 'thankyouforlettin.wav';
            $ncco = new \Vonage\Voice\NCCO\NCCO();
            $ncco->addAction(
                new \Vonage\Voice\NCCO\Action\Talk('<speak><p>Thank you For your response.</p></speak>')
                // new \Vonage\Voice\NCCO\Action\Stream($stream_url)
            );

            return new WP_REST_Response($ncco);
        }

        function sendSms($sms_data)
        {
            try {
                error_log('sending sms' . json_encode($sms_data));
                $sms_to = $sms_data['notify_phone'];
                $sms_from = $sms_data['from'];
                $sms_text = $sms_data['message'];

                $text = new \Vonage\SMS\Message\SMS($sms_to, $sms_from, $sms_text);
                $text->setClientRef('welfare-message');

                $response = $this->client->sms()->send($text);

                $data = $response->current();

                $message = $response->current();

                // check message is sent
                $is_message_sent = 0;
                if ($message->getStatus() == 0) {
                    $is_message_sent = 1;
                }

                create_sms_log((object)[
                    'user_id' =>  $sms_data['user_id'],
                    'call_service_id' => $sms_data['call_service_id'],
                    'call_notify_to' => $sms_to,
                    'call_from' => $sms_from,
                    'call_type_selection' => $sms_data['call_type_selection'],
                    'is_sms_sent' => $is_message_sent,
                ]);

                return $message;
            } catch (\Throwable $th) {
                return $th;
            }
        }
    }
endif;

// Initialize the VonageController
new VonageController();

/**
 * Attempts a second call
 *
 * @param string $conversationUUID The conversation UUID.
 */
add_action('second_time_make_call', 'retry_unanswered_call');

function retry_unanswered_call($conversationUUID)
{
    global $wpdb;

    // Fetch the call details for the retry attempt
    $second_call = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_second_time_call_logs WHERE con_uuid = %s",
        $conversationUUID
    ));

    if ($second_call) {
        $args = [
            'call_service_id' => $second_call->call_service_id,
            'to' => $second_call->contact_no,
            'from' => $second_call->call_from,
            'user_id' => $second_call->user_id ?? get_current_user_id(),
            'name' => $second_call->call_name ?? 'Caring Person',
            'message' => 'This is a second attempt scheduled call from the welfare care alert system.'
        ];

        // Attempt second call and log response
        $vonage_controller = new VonageController();
        $response = $vonage_controller->makeCall($args, 'second-call');
        /*  if ($response) {

            // Check the status of the call
            $call_status = $response->getStatus(); // Adjust if you need to extract differently based on the response structure

            $no_retry_statuses = ['cancelled', 'rejected', 'busy', 'timeout', 'failed', 'unanswered'];

             // Check if call status is one of the specified statuses
            if (in_array($call_status, $no_retry_statuses)) {
                error_log('Second call status found in array');

                $caringPeopleName = $second_call->call_name ?? 'Caring Person';

                $message = get_call_status_wise_msg($call_status, $caringPeopleName, false);

                $userdata = get_userdata($second_call->user_id);
                $user_email = $userdata->user_email;

                $recipient_email = $user_email;
                $subject = "Call Status Notification";
                wp_mail($recipient_email, $subject, $message);
            }
        } else {
            error_log("No response for second call attempt.");
        } */

        // Remove from second time call logs regardless of call success
        $wpdb->delete(
            $wpdb->prefix . 'user_second_time_call_logs',
            ['con_uuid' => $conversationUUID],
            ['%s']
        );
    } else {
        error_log("No call data found for retry attempt with conversation UUID: " . $conversationUUID);
    }
}


/**
 * Retrieves the current call log data for a given conversation UUID.
 *
 * @param string $conversationUUID The conversation UUID to search for.
 * @return object|null The call log data if found, otherwise null.
 */
function get_current_call_data($conversationUUID)
{
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare(
        "SELECT whucl.*,
                whccs.call_1_call_type,
                whccs.call_2_call_type,
                whccs.notify_name,
                whccs.notify_email,
                whccs.notify_phone,
                whccs.send_sms
                FROM wp_user_call_logs as whucl
                    INNER JOIN wp_ha_customer_calls_services as whccs ON whccs.id = whucl.call_service_id
                WHERE con_uuid = %s",
        $conversationUUID
    ));

    /* return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_call_logs WHERE con_uuid = %s",
        $conversationUUID
    )); */
}

/**
 * Sends an email based on call status, caring person's name, and whether the call was answered.
 *
 * @param string $call_status The status of the call.
 * @param string $caring_people_name The name of the caring person.
 * @param bool $user_call_answered Whether the call was answered or not.
 * @return string The formatted message.
 */
function get_call_status_wise_msg($call_status, $caring_people_name, $user_call_answered)
{
    if ($user_call_answered) {
        $message = " answered to our call ✅";
    } else {
        switch ($call_status) {
            case 'cancelled':
                $message = " call was cancelled.";
                break;
            case 'rejected':
                $message = " rejected our call.";
                break;
            case 'busy':
                $message = " did not respond, we will try again in 15 mins ➡️";
                break;
            case 'timeout':
                $message = " call is not connected.";
                break;
            case 'failed':
                $message = " call failed.";
                break;
            case 'unanswered':
                $message = " didn't receive our call.";
                break;
            default:
                $message = " didn't respond to our call.";
                break;
        }
    }
    return $caring_people_name . $message;
}

/**
 * Creates a log entry for Vonage interactions.
 *
 * @param object $logData The data to log.
 */
function create_vonage_log($logData)
{
    global $wpdb;
    $vonage_log_table_name = $wpdb->prefix . 'vonage_logs';

    if (empty($logData->con_uuid)) {
        return;
    }

    // Insert validated data into the database
    $wpdb->insert($vonage_log_table_name, [
        'con_uuid' => $logData->con_uuid,
        'uuid' => $logData->uuid,
        'call_to' => $logData->call_to,
        'call_from' => $logData->call_from,
        'status' => $logData->status,
        'notify_type' => $logData->notify_type,
        'response' => $logData->response,
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
}


/**
 * Creates a log entry for sent SMS messages.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function create_sms_log($smsData)
{
    global $wpdb;

    $sms_log_table_name = $wpdb->prefix . 'user_send_sms_logs';

    $wpdb->insert($sms_log_table_name, [
        'user_id' => $smsData->user_id,
        'call_service_id' => $smsData->call_service_id,
        'call_notify_to' => $smsData->call_notify_to,
        'call_from' => $smsData->call_from,
        'call_type_selection' => $smsData->call_type_selection,
        'is_sms_sent' => $smsData->is_sms_sent,
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
}
