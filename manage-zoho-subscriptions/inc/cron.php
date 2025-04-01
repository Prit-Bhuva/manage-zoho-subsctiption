<?php

if (!function_exists('manage_call_data')):
    function manage_call_data()
    {
        global $wpdb;

        // Specify the form ID (replace with your actual form ID)
        $table_namecall = $wpdb->prefix . 'ha_customer_calls_services';
        $table_name = $wpdb->prefix . 'ha_customer_subscriptions';
        $user_call_logs = $wpdb->prefix . 'user_call_logs';
        $user_second_time_call_logs = $wpdb->prefix . 'user_second_time_call_logs';
        $vonage_logs = $wpdb->prefix . 'vonage_logs';

        $serverTimeZoneQuery = "SELECT @@session.time_zone";
        $serverTimeZone = $wpdb->get_var($serverTimeZoneQuery);
        if ($serverTimeZone === 'SYSTEM') {
            $serverTimeZone = 'UTC';
        }

        $current_time = date('h:i A', time());

        /* // Fetch column names
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_namecall");

        // Loop through the columns and print each column name
        foreach ($columns as $column) {
            echo $column->Field . '<br>';
        }
        
        exit; */

        // Prepare the SQL query
        $query_subscription = $wpdb->get_results("SELECT * FROM $table_name");

        $query_demo = $wpdb->get_results("SELECT * FROM $table_namecall");

        $query1 = $wpdb->get_results("SELECT * FROM $user_call_logs");


        $query2 = $wpdb->get_results("SELECT * FROM $user_second_time_call_logs");
        $call_service_query = "
            SELECT
                ccs.*
            FROM
                `wp_ha_customer_calls_services` AS ccs
            INNER JOIN 
                `wp_ha_customer_subscriptions` AS cs 
                ON cs.subscription_id = ccs.subscription_id
            WHERE 
                cs.status IN ('trial', 'active', 'live')
                AND ccs.is_paused != 1
                
        ";
        $user_services_info = $wpdb->get_results($call_service_query);

        echo "Second Time Call logs:";
        echo "<pre>";
        print_r($query2);
        echo "</pre>";

        $query3 = $wpdb->get_results("SELECT * FROM $vonage_logs");
    }
endif;

if (!function_exists('getTimeFromRepeater')):
    function getTimeFromRepeater(int $entry_id, Object $entry): array
    {
        if ($entry_id != 9 && !isset($entry->metas[9])) return [];

        $meta_data = $entry->metas[9];

        if (empty($meta_data)) return [];

        $call_times = [];
        foreach ($meta_data as $data) {
            $repeater_data = FrmEntryMeta::getAll(array('item_id' => $data));

            if (empty($repeater_data)) continue;

            list($entry_meta) = $repeater_data;

            $call_times[] = $entry_meta->meta_value;
        }

        return $call_times;
    }
endif;
