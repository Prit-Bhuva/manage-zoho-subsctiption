<?php

/**
 * Plugin Name: Manage Zoho Subscriptions
 * Description: Manage your Zoho subscriptions from WordPress.
 * Version: 1.0
 * Author: Prit Bhuva
 */

defined('ABSPATH') || exit;

if (!defined('_S_VERSION')) {
    define('_S_VERSION', '1.0.0');
}

class Manage_Zoho_Subscriptions
{
    // Define constants
    const VERSION = _S_VERSION;
    const PLUGIN_DIR = __DIR__;
    private $plugin_url;

    public function __construct()
    {
        // Set the PLUGIN_URL constant
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Include files from the inc folder
        $this->include_files();

        // Hook to create table on plugin activation
        register_activation_hook(__FILE__, [$this, 'create_tables_on_activation']);

        // Hook to truncate table on plugin deactivation
        // register_deactivation_hook(__FILE__, [$this, 'truncate_tables_on_deactivation']);

        // register_activation_hook(__FILE__, [$this, 'schedule_cron_job']);

        // Hook to clear cron job on plugin deactivation
        // register_deactivation_hook(__FILE__, [$this, 'clear_cron_job']);

        // Add custom cron schedule
        // add_filter('cron_schedules', [$this, 'add_custom_cron_schedule']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Hook the function to your custom cron event
        /* add_action('make_vonage_calls', ['ManageVonageCall', 'start_making_call']);
        add_action('make_vonage_calls', ['ManageVonageCall', 'start_making_second_call']); */
    }

    // Function to include necessary files
    private function include_files()
    {
        $files = [
            './vendor/autoload.php',
            'inc/general_functions.php',
            'inc/constants.php',
            'inc/zoho_functions.php',
            'inc/handler_ajax.php',
            'inc/vonage.php',
            'inc/manage_vonage_call.php',
            'inc/cron.php',
            'inc/manage_services.php',
            'shortcode/my-services-shortcode.php',
        ];

        foreach ($files as $file) {
            $file_path = self::PLUGIN_DIR . '/' . $file;
            if (file_exists($file_path)) {
                include $file_path;
            }
        }
    }

    // Function to create tables on plugin activation
    public function create_tables_on_activation()
    {
        // Call the function to create the table (now included in manage_services.php)
        create_customer_calls_table();
        create_customer_subscription_table();
        create_user_call_logs_table();
        create_second_time_call_logs_table();
        create_vonage_log_table();
        create_send_sms_logs_table();

    }

    // Function to truncate tables on plugin deactivation
    /*  public function truncate_tables_on_deactivation()
    {
        // Call the function to truncate the table (now included in manage_services.php)
        truncateTable();
    } */

    // Schedule cron job
    public function schedule_cron_job()
    {
        if (!wp_next_scheduled('make_vonage_calls')) {
            wp_schedule_event(time(), 'every_minute', 'make_vonage_calls');
        }

        if (!wp_next_scheduled('make_second_vonage_calls')) {
            wp_schedule_event(time(), 'every_minute', 'make_second_vonage_calls');
        }
    }

    // Clear cron job
    public function clear_cron_job()
    {
        $timestamp = wp_next_scheduled('make_vonage_calls');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'make_vonage_calls');
        }

        $secondTimestamp = wp_next_scheduled('make_second_vonage_calls');
        if ($secondTimestamp) {
            wp_unschedule_event($secondTimestamp, 'make_second_vonage_calls');
        }
    }

    // Add custom cron schedule
    public function add_custom_cron_schedule($schedules)
    {
        $schedules['every_minute'] = [
            'interval' => 60, // Interval in seconds
            'display' => __('Every Minute'),
        ];
        return $schedules;
    }

    // Enqueue scripts and styles
    public function enqueue_scripts()
    {
        wp_enqueue_style('mzs-css', $this->plugin_url . 'assets/css/style.css', [], time());
        wp_enqueue_script('mzs-js', $this->plugin_url . 'assets/js/script.js', ['jquery'], time(), true);

        // Localize the script with the current user ID
        wp_localize_script('mzs-js', 'mzsData', [
            'userId' => get_current_user_id(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('add_customer_to_zoho'),
        ]);
    }
}

// Instantiate the plugin class
new Manage_Zoho_Subscriptions();
