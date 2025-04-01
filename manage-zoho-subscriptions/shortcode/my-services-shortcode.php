<?php

function test_shortcode()
{
    global $wpdb;

    ob_start();

    manage_call_data();

    return ob_get_clean();
}
add_shortcode('test_manage_call', 'test_shortcode');

/**
 * Generates a "Get Started" button with a URL and text based on user login status.
 *
 * If the user is logged in, the button will link to the "My Service" page.
 * If the user is not logged in, the button will link to the registration page.
 *
 * @return string HTML markup for the "Get Started" button.
 */
if (!function_exists('custom_get_started_button')):
    function custom_get_started_button()
    {
        $registration_url = site_url('/registration');
        $my_service_url = site_url('/my-service');

        if (is_user_logged_in()) {
            $button_url = $my_service_url;
        } else {
            $button_url = $registration_url;
        }

        // Button HTML
        return '<div class="get-started-wrapper">
                    <a href="' . esc_url($button_url) . '" class="get-started-button">
                        Get Started
                    </a>
                </div>';
    }
    add_shortcode('get_started_button', 'custom_get_started_button');
endif;


/**
 * Shortcode to display a page where users can add a payment method
 * and then add a service. The page will only show the "Add a Service"
 * button if a payment method has been added.
 *
 * @param  array $atts Shortcode attributes
 * @return string
 */
if (!function_exists('mzs_my_services')):
    function mzs_my_services($atts)
    {
        $user_id = get_current_user_id();
        $zoho_customer_id = get_user_meta($user_id, 'zoho_customer_id', true);

        $has_card_details = get_user_meta($user_id, 'has_zoho_card_details', true);

        if (!$has_card_details) {
            // Get card details
            $card_details = getCustomerCardDetails($zoho_customer_id);

            $has_card_details = manageCustomerCardDetails($user_id, $zoho_customer_id, $card_details);
        }

        $button_text = $has_card_details ? 'Update Payment Method' : 'Add Payment Method';

        ob_start();
?>

        <div class="wc-my-services">
            <span>To activate a service, please ensure a payment method is on file.</span>
            <p>🔹 Need to update your payment details? Click <strong>"Update Payment Method"</strong> to make changes.</p>

            <div id="notification" style="display: none; margin-bottom: 10px; color: green;"></div>

            <div class="add-payment-method" id="add-payment-method">
                <button class="btn btn-primary btn-add-payment" type="button"><?php echo $button_text; ?></button>
            </div>

            <div class="display-card-data" id="card-details-data">
                <?php displayCustomerCardData(); ?>
            </div>

            <?php if ($has_card_details):  ?>
                <div class="add-services">
                    <p>🔹 Ready to get started? Click <strong>"Add a Service"</strong> to subscribe to a Welfare or Medication Reminder Call.</p>
                    <a href="<?php the_permalink(get_page_by_path('welfare-services-form')); ?>" class="btn btn-primary btn-add-services">Add a Service</a>
                </div>

                <div class="call-data" id="call-data">
                    <?php displayCustomerCallData(); ?>
                </div>
            <?php endif; ?>

        </div>

    <?php
        return ob_get_clean();
    }

    add_shortcode('wc_my_services', 'mzs_my_services');
endif;


/**
 * Add a shortcode to display a form to add call participants.
 *
 * This shortcode displays a form to add call participants. The form includes fields for
 * the participant's name, phone number, and time zone. The form also includes a mechanism
 * to add up to two daily calls for the participant. The form data is processed via AJAX
 * and the result is displayed in a div with the id "formMessage".
 *
 * @since 1.0.0
 *
 * @return string The HTML output for the shortcode.
 */
if (!function_exists('mys_add_call_participants_shortcode')) :
    function mys_add_call_participants_shortcode($atts)
    {
        $edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
        $data = '';

        if ($edit_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ha_customer_calls_services';
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
        }

        // Prepopulate call time data if available
        $call_time_1 = isset($data->call_time_1) ? $data->call_time_1 : '';
        $call_time_2 = isset($data->call_time_2) ? $data->call_time_2 : '';

        // Function to parse the time string
        function parseTime($time)
        {
            if (empty($time)) {
                return ['hour' => '', 'minute' => '', 'ampm' => ''];
            }
            // Split the time into parts
            preg_match('/(\d+):(\d+) (AM|PM)/', $time, $matches);
            return [
                'hour' => isset($matches[1]) ? $matches[1] : '',
                'minute' => isset($matches[2]) ? $matches[2] : '',
                'ampm' => isset($matches[3]) ? $matches[3] : ''
            ];
        }

        // Parse the call times
        $parsed_call_time_1 = parseTime($call_time_1);
        $parsed_call_time_2 = parseTime($call_time_2);

        $hour1 = $parsed_call_time_1['hour'];
        $minute1 = $parsed_call_time_1['minute'];
        $ampm1 = $parsed_call_time_1['ampm'];

        $hour2 = $parsed_call_time_2['hour'];
        $minute2 = $parsed_call_time_2['minute'];
        $ampm2 = $parsed_call_time_2['ampm'];

        // Prepopulate call type
        $call_1_call_type = isset($data->call_1_call_type) ? $data->call_1_call_type : '';
        $call_2_call_type = isset($data->call_2_call_type) ? $data->call_2_call_type : '';

        ob_start();
    ?>
        <div class="add-contact-details container">
            <div class="progress-bar form-steps" aria-label="Page 1 of 3">
                <div class="progress" id="progress"></div>
                <div class="progress-step active" id="progress-step-1"></div>
                <div class="progress-step" id="progress-step-2"></div>
                <div class="progress-step" id="progress-step-3"></div>
            </div>

            <form class="form" action="#" id="callParticipantsForm">
                <input type="hidden" name="edit_id" id="edit_id" value="<?php echo esc_attr($edit_id); ?>">

                <!-- Step 1: Notification Recipient -->
                <div class="notification-recipient" id="step_1">
                    <div class="form-step-info">
                        <h2 class="title">Stay Informed with CareAlert Notifications</h2>
                        <p>To keep you updated on the welfare of your loved one, please provide your details below.
                            You can choose to receive notifications via email and phone when a welfare check or medication reminder call has been:</p>
                        <ul>
                            <li>✅<strong>Successful</strong> – The recipient has confirmed they are okay.</li>
                            <li>⚠️<strong>Unanswered</strong> – No response was received after two call attempts.</li>
                        </ul>
                        <p>These notifications ensure you stay informed and can take action if needed.</p>
                    </div>
                    <span>Please enter your details below to proceed.</span>
                    <div class="notification-details">
                        <div class="form-group">
                            <label for="notify_name">Name of Person <span class="text-red">*</span></label>
                            <input type="text" name="notify_name" id="notify_name" required value="<?php echo isset($data->notify_name) ? esc_attr($data->notify_name) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="notify_phone">Mobile Number <span class="text-red">*</span></label>
                            <input type="text" name="notify_phone" id="notify_phone" required value="<?php echo isset($data->notify_phone) ? esc_attr($data->notify_phone) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="notify_email">Email Id<span class="text-red">*</span></label>
                            <input type="text" name="notify_email" id="notify_email" required value="<?php echo isset($data->notify_email) ? esc_attr($data->notify_email) : ''; ?>">
                        </div>
                    </div>
                    <div class="step-footer">
                        <button type="button" class="next-step">Next</button>
                    </div>
                </div>

                <!-- Step 2: Details of the Person in Care -->
                <div class="person-in-care " id="step_2" style="display: none;">
                    <div class="form-step-info">
                        <h2 class="title">Set Up Welfare Calls for Your Loved One</h2>
                        <p>Please enter the details of the person receiving the automated welfare or medication reminder calls.</p>
                    </div>
                    <div class="caller-details">
                        <div class="form-group">
                            <label for="name">Name of Person <span class="text-red">*</span></label><br>
                            <small>The individual receiving the calls.</small>
                            <input type="text" name="name" id="name" required
                                value="<?php echo isset($data->name) ? esc_attr($data->name) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Mobile or Home Number <span class="text-red">*</span></label><br>
                            <small>The phone number where the calls will be made.</small>
                            <input type="text" name="phone" id="phone" required
                                value="<?php echo isset($data->phone) ? esc_attr($data->phone) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="timezone">Time Zone <span class="text-red">*</span></label><br>
                            <small>Select the correct time zone to accurately schedule calls.</small>
                            <select id="timezone" name="timezone" required>
                                <option value="" selected>Select Time Zone</option>
                                <?php
                                $timezones = [
                                    "Australia/ACT" => "ACT",
                                    "Australia/Sydney" => "NSW",
                                    "Australia/Darwin" => "NT",
                                    "Australia/Brisbane" => "QLD",
                                    "Australia/Adelaide" => "SA",
                                    "Australia/Hobart" => "TAS",
                                    "Australia/Melbourne" => "VIC",
                                    "Australia/Perth" => "WA",
                                    "Asia/Kolkata" => "IST"
                                ];

                                foreach ($timezones as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '" ' . selected($data->timezone ?? '', $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group send_call_sms">
                        <h2>Notification Options</h2>
                        <p>If a call goes unanswered after two attempts, you can choose to receive alerts:</p>

                        <div class="send_sms_radio">
                            <label class="email_notification">
                                <input type="radio" name="sms_notification" id="email_notification" value="0" <?php echo (isset($data->send_sms) && $data->send_sms == '0') ? 'checked' : ''; ?> required>
                                By <strong>Email & Phone Call</strong> – $8.25 per month
                            </label>
                        </div>
                        <div class="send_sms_radio">
                            <label class="call_notification">
                                <input type="radio" name="sms_notification" id="call_notification" value="1" <?php echo (isset($data->send_sms) && $data->send_sms == '1') ? 'checked' : ''; ?> required>
                                By <strong>SMS, Email & Phone Call</strong> – $12.25 per month
                            </label>
                        </div>
                    </div>

                    <div class="step-footer">
                        <button type="button" class="prev-step">Previous</button>
                        <button type="button" class="next-step">Next</button>
                    </div>
                </div>

                <!-- Step 3: Call Type Selection -->
                <div class="call-selection" id="step_3" style="display: none;">
                    <div class="form-step-info">
                        <h2>Customize Your Daily Calls</h2>
                        <p>Select the type of call your loved one will receive:</p>
                        <ul>
                            <li><strong>✅ General Welfare Check Call</strong> – A daily check-in to confirm their well-being.</li>
                            <li><strong>✅ Medication Reminder Call</strong> – A reminder to take their medication, with confirmation received.</li>
                        </ul>
                        <h4>Schedule Your Calls</h4>
                        <ul>
                            <li>Up to 2 Calls Per Day – Set the times that best suit their routine.</li>
                            <li>Easy Scheduling – Use the fields below to set call times.</li>
                            <li>Need an Extra Call? Click <strong>“Add another call”</strong> to schedule a second call.</li>
                        </ul>

                        <p><strong>Once completed, you’re all set!</strong> Their automated calls will begin at the selected times.</p>
                    </div>

                    <div class="call-time-info">
                        <div class="call-type-selection">
                            <h5>Call Type Selection</h5>
                            <div class="call_type" id="callTypeGroup">
                                <div class="form-group call-types call-1">
                                    <div class="call-type">
                                        <div class="call_type_radio">
                                            <label>
                                                <input type="radio" name="call_1_call_type" id="general_welfare" value="0"
                                                    <?php echo (isset($call_1_call_type) && $call_1_call_type == '0') ? 'checked' : ''; ?> required> A General Welfare Check Call
                                            </label>
                                        </div>
                                        <div class="call_type_radio">
                                            <label>
                                                <input type="radio" name="call_1_call_type" id="medication_reminder" value="1"
                                                    <?php echo (isset($call_1_call_type) && $call_1_call_type == '1') ? 'checked' : ''; ?> required> A Medication Reminder Call
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="call_times">
                            <h5>Add Daily Calls (Maximum 2)</h5>
                            <div class="call-group" id="callGroup">
                                <div class="form-group call-times">
                                    <!-- Call times will be populated dynamically using JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="step-footer">
                        <button type="button" class="prev-step">Previous</button>
                        <button type="submit" class="submit-btn">
                            <?php echo $edit_id ? 'Update' : 'Submit'; ?>
                        </button>
                    </div>
                </div>
            </form>
            <div id="formMessage"></div>
        </div>

        <?php
        $html = ob_get_clean();

        add_action('wp_footer', function () use ($hour1, $minute1, $ampm1, $hour2, $minute2, $ampm2, $call_2_call_type) {
        ?>
            <script>
                ($ => {
                    let currentStep = 1;
                    const totalSteps = 3;

                    // Function to validate required fields
                    function validateFields(step) {
                        let valid = true;
                        let currentStepForm = $('#step_' + step);

                        // Check for required fields (including radio buttons)
                        currentStepForm.find('input[required], select[required]').each(function() {
                            const field = $(this);
                            if (field.is(':radio')) {
                                // Check if any radio button in the group is selected
                                if (!$(`input[name="${field.attr('name')}"]:checked`).length) {
                                    valid = false;
                                    field.closest('.form-group').addClass('error'); // Add error class to show the error state
                                    if (!field.closest('.form-group').next('.error-message').length) {
                                        field.closest('.form-group').after('<div class="error-message" style="color: red; font-size: 12px;">This field is required</div>');
                                    }
                                } else {
                                    field.closest('.form-group').removeClass('error');
                                    field.closest('.form-group').next('.error-message').remove();
                                }
                            } else {
                                // For other fields (like text inputs and selects)
                                if (!field.val()) {
                                    valid = false;
                                    field.addClass('error'); // Add error class to show the error state
                                    if (!field.next('.error-message').length) {
                                        field.after('<div class="error-message" style="color: red; font-size: 12px;">This field is required</div>');
                                    }
                                } else {
                                    field.removeClass('error');
                                    field.next('.error-message').remove();
                                }
                            }
                        });

                        return valid;
                    }

                    // Show the corresponding step and update the progress bar
                    function showStep(step) {
                        // Hide all steps and show the current step
                        $('form > div').hide();
                        $('#step_' + step).show();

                        // Update the progress bar
                        let progressPercentage = (step - 1) / (totalSteps - 1) * 100;
                        $('#progress').css('width', progressPercentage + '%');

                        // Update progress steps classes
                        $('.progress-step').removeClass('active');
                        $('#progress-step-' + step).addClass('active');
                    }

                    // Handle "Next" button click
                    $('.next-step').on('click', function() {
                        if (validateFields(currentStep)) {
                            if (currentStep < totalSteps) {
                                currentStep++;
                                showStep(currentStep);
                            }
                        }
                    });

                    // Handle "Previous" button click
                    $('.prev-step').on('click', function() {
                        if (currentStep > 1) {
                            currentStep--;
                            showStep(currentStep);
                        }
                    });

                    showStep(currentStep); // Display the first step on load

                    // Add logic to update error message dynamically after radio button selection
                    $(document).on('change', 'input[type="radio"]', function() {
                        const radioGroup = $(this).closest('.form-group');
                        radioGroup.removeClass('error'); // Remove error class when user selects a radio button
                        radioGroup.next('.error-message').remove(); // Remove error message
                    });

                    let currentCallCount = <?php echo $hour2 ? 2 : ($hour1 ? 1 : 0); ?>;
                    const maxCallsAllowed = 2;

                    function getCallTypeHtml(call_2_call_type = '') {
                        return `
                            <div class="call-type">
                                <div class="call_type_radio">
                                    <label>
                                        <input type="radio" name="call_2_call_type" id="general_welfare" value="0"
                    ${call_2_call_type == '0' ? 'checked' : ''} required> A General Welfare Check Call
                                    </label>
                                </div>
                                <div class="call_type_radio">
                                    <label>
                                        <input type="radio" name="call_2_call_type" id="medication_reminder" value="1"
                    ${call_2_call_type == '1' ? 'checked' : ''} required> A Medication Reminder Call
                                    </label>
                                </div>
                            </div>
                            `;
                    }

                    // Generate call time HTML
                    function getCallTimeHtml(hour = '', minute = '', ampm = 'AM') {
                        return `
                             <div class="call-time">
                                 <div class="time-inputs">
                                     <select name="call_hour[]" required>
                                         <option value="">HH</option>
                                         ${getHourOptions(hour)}
                                     </select>
                                     <span>:</span>
                                     <select name="call_minute[]" required>
                                         <option value="">MM</option>
                                         ${getMinuteOptions(minute)}
                                     </select>
                                     <select name="call_time_formate[]" required>
                                         <option value="AM" ${ampm === 'AM' ? 'selected' : ''}>AM</option>
                                         <option value="PM" ${ampm === 'PM' ? 'selected' : ''}>PM</option>
                                     </select>
                                 </div>
                                 ${currentCallCount < maxCallsAllowed ? getAddButtonHtml() : getRemoveButtonHtml()}
                             </div>
                         `;
                    }

                    // Generate hour and minute options
                    function getHourOptions(selectedHour) {
                        let options = '';
                        for (let i = 1; i <= 12; i++) {
                            options += `<option value="${i}" ${i == selectedHour ? 'selected' : ''}>${i}</option>`;
                        }
                        return options;
                    }

                    function getMinuteOptions(selectedMinute) {
                        const minutes = ['00', '15', '30', '45'];
                        return minutes.map(minute => `<option value="${minute}" ${minute === selectedMinute ? 'selected' : ''}>${minute}</option>`).join('');
                    }

                    function getAddButtonHtml() {
                        return `<button type="button" class="add-call-btn">Add another call</button>`;
                    }

                    function getRemoveButtonHtml() {
                        return `<button type="button" class="remove-call-btn">Remove</button>`;
                    }

                    // populate the call times
                    $(document).ready(function() {
                        const callGroup = $('#callGroup .call-times');
                        const callTypeGroup = $('#callTypeGroup .call-types');

                        <?php if ($hour1): ?>
                            callGroup.append(getCallTimeHtml('<?php echo esc_js($hour1); ?>', '<?php echo esc_js($minute1); ?>', '<?php echo esc_js($ampm1); ?>'));
                        <?php endif; ?>

                        <?php if ($hour2): ?>
                            callGroup.append(getCallTimeHtml('<?php echo esc_js($hour2); ?>', '<?php echo esc_js($minute2); ?>', '<?php echo esc_js($ampm2); ?>'));

                            callTypeGroup.append(getCallTypeHtml('<?php echo esc_js($call_2_call_type); ?>'));
                        <?php endif; ?>

                        // If no times are set, add a default time slot
                        if (currentCallCount === 0) {
                            callGroup.append(getCallTimeHtml());
                            // callTypeGroup.append(getCallTypeHtml());
                            currentCallCount = 1;
                        }


                    });

                    // Handle adding a call time slot
                    $(document).on('click', '.add-call-btn', function(e) {
                        e.preventDefault();

                        // Replace the "Add another call" button with a "Remove" button
                        $(this).replaceWith(getRemoveButtonHtml());

                        currentCallCount++;

                        // Check if the call limit has been reached
                        if (currentCallCount > maxCallsAllowed) {
                            alert('Call time add limit has been reached!');
                            return;
                        }

                        // Append the new call time slot
                        $('#callGroup .call-times').append(getCallTimeHtml());

                        $('#callTypeGroup .call-types').append(getCallTypeHtml());

                        // If the call limit is reached, replace the newly appended "Add another call" button with the "Remove" button
                        if (currentCallCount === maxCallsAllowed) {
                            $('#callGroup .call-times .call-time').last().find('.add-call-btn').replaceWith(getRemoveButtonHtml());
                        }
                    });

                    $(document).on('click', '.remove-call-btn', function() {
                        if (currentCallCount > 1) {
                            const removeIndex = $(this).parent().index();

                            $('.call-time-info .call-type-selection .call-types .call-type').eq(removeIndex).remove();
                            $('.call-time-info .call_times .call-times .call-time').eq(removeIndex).remove();

                            currentCallCount--;
                            if (currentCallCount < maxCallsAllowed) {
                                $('#callGroup .call-times .call-time').last().find('.remove-call-btn').replaceWith(getAddButtonHtml());
                            }
                        }
                    });

                })(jQuery);

                // Form submission handler
                (function($) {
                    $('#callParticipantsForm').on('submit', function(e) {
                        e.preventDefault(); // Prevent the default form submission

                        const formData = $(this).serialize(); // Serialize the form data
                        const editId = $('#editId').val();

                        // Append the edit ID to the form data if it exists
                        let dataToSend = formData; // Initialize with serialized form data
                        if (editId) {
                            dataToSend += `&edit_id=${encodeURIComponent(editId)}`; // Append edit ID
                        }

                        const loader = $(`
                                            <div id="dynamic-loader">
                                                <div class="loader-circle"></div>
                                                <div>Loading...</div>
                                            </div>
                                        `).css({
                            position: 'fixed',
                            top: '50%',
                            left: '50%',
                            transform: 'translate(-50%, -50%)',
                            background: '#fff',
                            padding: '10px 20px',
                            borderRadius: '5px',
                            boxShadow: '0 2px 10px rgba(0,0,0,0.1)',
                            zIndex: 9999
                        });
                        $('body').append(loader);

                        $.ajax({
                            type: 'POST',
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            data: dataToSend + '&action=add_call_participants',
                            success: function(response) {
                                if (response.success == true) {
                                    // $('#formMessage').html('<div class="success">Call participants added successfully.</div>'); // Display success message
                                    window.location.href = response.data.redirect_url;
                                }

                            },
                            error: function() {
                                $('#formMessage').html('<div class="error">There was an error processing your request.</div>'); // Display error message
                            },
                            complete: function() {
                                loader.remove();
                            }
                        });
                    });
                })(jQuery);
            </script>
<?php
        }, 999);

        return $html;
    }
    add_shortcode('add_call_participants', 'mys_add_call_participants_shortcode');
endif;


// Set the "from" email address
add_filter('wp_mail_from', function ($original_email_address) {
    return 'no-reply@carealert.com.au'; // Replace with your desired "from" email address
});


// Clear the scheduled cron jobs
add_shortcode('print_cron', function () {

    wp_clear_scheduled_hook('make_vonage_calls');
    wp_clear_scheduled_hook('make_second_vonage_calls');

    $cron_array = _get_cron_array();
    /*  echo '<pre>';
    print_r($cron_array);
    echo '</pre>'; */
});
