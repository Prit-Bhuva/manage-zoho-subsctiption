<?php

if (! function_exists('insert_log_in_db')) :
    function insert_log_in_db($cron_job_name)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ha_cron_logs';

        $data = array(
            'cron_job_name' => $cron_job_name,
        );

        $format = array('%s'); // Data types: %s for strings, %d for integers, %f for floats

        $wpdb->insert($table_name, $data, $format);

        delete_remove_old_log();
    }
endif;

if (! function_exists("remove_old_log")) :
    function delete_remove_old_log()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ha_cron_logs'; // Replace with your actual table name

        // Get the count of total records
        $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        error_log("Remove Record: $record_count");

        if ($record_count >= 2880) {
            // Get the oldest 1440 record IDs
            $oldest_records = $wpdb->get_col("SELECT id FROM $table_name ORDER BY id ASC LIMIT 1440");

            if (!empty($oldest_records)) {
                // Convert IDs into a comma-separated string for deletion
                $ids_to_delete = implode(',', array_map('intval', $oldest_records));

                // Delete the oldest 1440 records
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_to_delete)");
            }
        }

        // Fetch remaining records in ascending order by ID
        $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
    }
endif;
