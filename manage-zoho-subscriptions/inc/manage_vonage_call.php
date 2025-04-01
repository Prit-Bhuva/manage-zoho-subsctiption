<?php

if (!class_exists('ManageVonageCall')) {

    class ManageVonageCall
    {
        private static $vonage;

        // Initialize the VonageController instance
        public static function init()
        {
            self::$vonage = new VonageController();

            // Register REST route for Vonage Make Call
            /* add_action('rest_api_init', function () {
                register_rest_route('vonage/call', '/make-call', [
                    'methods'  => 'GET',
                    'callback' => [__CLASS__, 'start_making_call'],
                    'permission_callback' => '__return_true', // Allow public access to the webhook.
                ]);
            }); */

            // Register REST route for Vonage Make Call
            add_action('rest_api_init', function () {
                register_rest_route('vonage/call', '/make-first-call', [
                    'methods'  => 'GET',
                    'callback' => [__CLASS__, 'start_making_first_call'],
                    'permission_callback' => '__return_true', // Allow public access to the webhook.
                ]);
            });

            // Register REST route for Vonage Make Second Call
            add_action('rest_api_init', function () {
                register_rest_route('vonage/call', '/make-second-call', [
                    'methods'  => 'GET',
                    'callback' => [__CLASS__, 'start_making_second_call'],
                    'permission_callback' => '__return_true', // Allow public access to the webhook.
                ]);
            });
        }

        /**
         * Make a call to the user using Vonage.
         *
         * @param array $args Array of arguments to pass to the VonageController::makeCall method.
         * @param string $notify_type Type of notification to send to the user.
         *
         * @return mixed|null The result of calling VonageController::makeCall method or null if VonageController is unavailable.
         */
        public static function make_vonage_call($args, $notify_type)
        {
            if (self::$vonage) {
                return self::$vonage->makeCall($args, $notify_type);
            } else {
                error_log('VonageController instance not available.');
                return null;
            }
        }

        /**
         * Start making calls to users who have registered for the service.
         * @return void
         */
        public static function start_making_first_call()
        {
            global $wpdb;

            insert_log_in_db('Start Call Cron');

            // Get the server timezone
            $serverTimeZoneQuery = "SELECT @@session.time_zone";
            $serverTimeZone = $wpdb->get_var($serverTimeZoneQuery);
            if ($serverTimeZone === 'SYSTEM') {
                $serverTimeZone = 'UTC';
            }

            $currentDate = date('Y-m-d');
            $user_service_table = $wpdb->prefix . 'ha_customer_calls_services';
            $subscriptions_table = $wpdb->prefix . 'ha_customer_subscriptions';

            $statuses = ['trial', 'active', 'live'];

            // Prepare the query 
            /* $query = "SELECT wp_ha_customer_calls_services.* 
                FROM $user_service_table
                INNER JOIN $subscriptions_table ON $user_service_table.subscription_id = $subscriptions_table.subscription_id
                WHERE (
                    $user_service_table.is_call_sent = %d 
                    OR $user_service_table.is_call_sent_2 = %d
                )
                AND $subscriptions_table.status IN (%s, %s, %s)"; */

            $query = "
                select
                    ccs.*
                from
                    `wp_ha_customer_calls_services` as ccs
                    inner join `wp_ha_customer_subscriptions` as cs on `cs`.`subscription_id` = `ccs`.`subscription_id`
                WHERE 
                    cs.status IN ('trial', 'active', 'live')
                    AND ccs.is_paused != 1
                    AND
                    (
                        (
                            CONVERT_TZ(STR_TO_DATE(CONCAT(CURRENT_DATE(), ' ', ccs.call_time_1), '%Y-%m-%d %h:%i %p'), ccs.timezone, '$serverTimeZone') <= CURRENT_TIME()
                            AND Addtime(CONVERT_TZ(STR_TO_DATE(CONCAT(CURRENT_DATE(), ' ', ccs.call_time_1), '%Y-%m-%d %h:%i %p'), ccs.timezone, '$serverTimeZone'), '01:00:00') >= CURRENT_TIME()
                        )
                        OR
                        (
                            CONVERT_TZ(STR_TO_DATE(CONCAT(CURRENT_DATE(), ' ', ccs.call_time_2), '%Y-%m-%d %h:%i %p'), ccs.timezone, '$serverTimeZone') <= CURRENT_TIME()
                            AND Addtime(CONVERT_TZ(STR_TO_DATE(CONCAT(CURRENT_DATE(), ' ', ccs.call_time_2), '%Y-%m-%d %h:%i %p'), ccs.timezone, '$serverTimeZone'), '01:00:00') >= CURRENT_TIME()
                        )
                    )";

            // Prepare the query
            // $prepared_query = $wpdb->prepare($query, $statuses[0], $statuses[1], $statuses[2]);
            $user_services_info = $wpdb->get_results($query);

            if (empty($user_services_info)) {
                return;
            }

            foreach ($user_services_info as $service_info) {
                $user_id = $service_info->user_id;
                $id = $service_info->id;
                $name = $service_info->name;
                $phone_no = $service_info->phone;
                $timezone = $service_info->timezone;
                $notify_name = $service_info->notify_name;
                $notify_email = $service_info->notify_email;
                $notify_phone = $service_info->notify_phone;
                $call_time_1 = $service_info->call_time_1;
                $call_time_2 = $service_info->call_time_2;
                $call_1_call_type = $service_info->call_1_call_type;
                $call_2_call_type = $service_info->call_2_call_type;
                $send_sms = $service_info->send_sms;

                // Convert server time to user's local time
                $serverTime = new DateTime("now", new DateTimeZone($serverTimeZone));
                $localTime = $serverTime->setTimezone(new DateTimeZone($timezone));
                $localTimeTimestamp = $localTime->getTimestamp();

                $call_time_1_timestamp = (new DateTime($call_time_1, new DateTimeZone($timezone)))->getTimestamp();
                $call_time_1_new = (new DateTime($call_time_1, new DateTimeZone($timezone)))->modify('+1 hour')->getTimestamp();

                $call_time_2_timestamp = $call_time_2 ? (new DateTime($call_time_2, new DateTimeZone($timezone)))->getTimestamp() : null;
                $call_time_2_new = $call_time_2 ? (new DateTime($call_time_2, new DateTimeZone($timezone)))->modify('+1 hour')->getTimestamp() : null;

                if ($localTimeTimestamp >= $call_time_1_timestamp && $localTimeTimestamp <= $call_time_1_new) {
                    $args = [
                        'user_id' => $service_info->user_id,
                        'call_service_id' => $id,
                        'name' => $name,
                        'to' => $phone_no,
                        'from' => '61485805667',
                        'notify_name' => $notify_name,
                        'notify_email' => $notify_email,
                        'notify_phone' => $notify_phone,
                        'call_type' => $call_1_call_type,
                        'send_sms' => $send_sms,
                        'message' => 'Hello ' . $name . ', this is a call from welfare carealert service site!'
                    ];

                    $instance = new self();

                    if (self::isCallProcessed('call_1', $user_id, $id)) {
                        self::make_vonage_call($args, 'call_1');
                    }

                    $wpdb->update($user_service_table, ['is_call_sent' => 1], ['id' => $service_info->id]);
                }

                if ($call_time_2 && $localTimeTimestamp >= $call_time_2_timestamp && $localTimeTimestamp <= $call_time_2_new) {
                    $args = [
                        'user_id' => $service_info->user_id,
                        'call_service_id' => $id,
                        'name' => $name,
                        'to' => $phone_no,
                        'from' => '61485805667',
                        'notify_name' => $notify_name,
                        'notify_email' => $notify_email,
                        'notify_phone' => $notify_phone,
                        'call_type' => $call_2_call_type,
                        'send_sms' => $send_sms,
                        'message' => 'Hello ' . $name . ', this is a call from welfare carealert service site!'
                    ];

                    if (self::isCallProcessed('call_2', $user_id, $id)) {
                        self::make_vonage_call($args, 'call_2');
                    }

                    $wpdb->update($user_service_table, ['is_call_sent_2' => 1], ['id' => $service_info->id]);
                }
            }
        }

        public static function start_making_second_call()
        {
            global $wpdb;

            insert_log_in_db('Start Second Time Call Cron');

            /* $query = "
                select
                    *
                from
                    `wp_user_second_time_call_logs` as ustcl
                WHERE 
                    Addtime(ustcl.created_at, '00:15:00') <= CURRENT_TIME()
                "; */

            /* $query = "
                    SELECT 
                        ustcl.*, 
                        whccs.call_type_selection
                    FROM 
                        `wp_user_second_time_call_logs` AS ustcl
                    INNER JOIN 
                        `wp_ha_customer_calls_services` AS whccs 
                    ON 
                        ustcl.call_service_id = whccs.id;
                    "; */

            $query = "
                    SELECT 
                        ustcl.*, 
                        whccs.call_1_call_type, 
                        whccs.call_2_call_type, 
                        whccs.send_sms
                    FROM 
                        `wp_user_second_time_call_logs` AS ustcl
                    INNER JOIN 
                        `wp_ha_customer_calls_services` AS whccs 
                    ON 
                        ustcl.call_service_id = whccs.id
                    WHERE
                    Addtime(ustcl.created_at, '00:15:00') <= CURRENT_TIME();
                ";


            // Prepare the query
            $second_time_calls = $wpdb->get_results($query);

            if (empty($second_time_calls)) {
                return;
            }

            foreach ($second_time_calls as $second_call) {

                $args = [
                    'call_service_id' => $second_call->call_service_id,
                    'to' => $second_call->contact_no,
                    'from' => $second_call->call_from,
                    'user_id' => $second_call->user_id ?? get_current_user_id(),
                    'name' => $second_call->call_name ?? 'Caring Person',
                    'message' => 'This is a second attempt scheduled call from the welfare care alert system.',
                    'call_type' => $second_call->call_type_selection,
                    'send_sms' => $second_call->send_sms
                ];

                self::make_vonage_call($args, 'second_call_attempt');

                $wpdb->delete(
                    $wpdb->prefix . 'user_second_time_call_logs',
                    ['id' => $second_call->id],
                    ['%s']
                );
            }
        }

        private static function isCallProcessed($call_type, $user_id, $call_service_id)
        {
            global $wpdb;

            // Prepare the query
            $query = $wpdb->prepare(
                "SELECT COUNT(id) 
                    FROM {$wpdb->prefix}user_call_logs 
                    WHERE 
                        user_id = %d 
                        AND notify_type = %s 
                        AND call_service_id = %d 
                        AND DATE(created_at) = CURRENT_DATE()",
                $user_id,
                $call_type,
                $call_service_id
            );

            // Execute the query and get the count
            $call_count = $wpdb->get_var($query);

            return 1 > $call_count ? true : false;
        }
    }

    ManageVonageCall::init();
}
