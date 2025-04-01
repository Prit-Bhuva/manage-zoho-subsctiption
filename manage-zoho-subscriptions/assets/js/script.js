($ => {
    $(document).ready(function () {
        $('#add-payment-method .btn-add-payment').click(function () {
            // Create the loader element dynamically
            const loader = $(`
                <div id="dynamic-loader">
                    <div class="loader-circle"></div>
                    <div>Loading...</div>
                </div>
            `);

            loader.css({
                position: 'fixed',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                background: '#fff',
                padding: '10px 20px',
                borderRadius: '5px',
                boxShadow: '0 2px 10px rgba(0,0,0,0.1)',
                zIndex: 9999 // Ensure it's on top of other elements
            });

            // Append the loader to the body
            $('body').append(loader);

            // Perform the AJAX request
            $.ajax({
                url: mzsData.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'add_customer_to_zoho',
                    nonce: mzsData.nonce,
                    userId: mzsData.userId
                },
                success: (res) => {
                    const notification = $('#notification');

                    if (res.success || res.data.status === 200) {
                        const details = res.data;

                        // Create popup structure with iframe
                        const popupTitle = details.is_already_set ? 'Update Payment Form' : 'Add Payment Form';
                        const iframeUrl = details.is_already_set ? details.update_iframe_url : details.iframe_url;

                        const popup = `
                                <div id="myPopup" class="popup-overlay">
                                    <div class="popup-content">
                                        <div class="popup-header">
                                            <span class="close">&times;</span>
                                            <h3 class="popup-title">${popupTitle}</h3>
                                        </div>
                                        <iframe src="${iframeUrl}" style="width: 100%; height: 655px; border: none;"></iframe>
                                    </div>
                                </div>`;

                        // Append the popup to the body and display it
                        $('body').append(popup);
                        $('#myPopup').show();

                        // Close the popup when the close button is clicked
                        $('.close').click(function () {
                            $('#myPopup').remove(); // Remove the popup from the DOM
                            location.reload();
                        });

                        // Optional: Close the popup when clicking outside of the content area
                        $('#myPopup').click(function (e) {
                            if ($(e.target).is('#myPopup')) {
                                $('#myPopup').remove();
                                location.reload();
                            }
                        });

                        // Display success notification
                        notification
                            .text(details.message || 'Payment method processed successfully.')
                            .css({ color: 'green', display: 'block' });

                        // Automatically fade out notification
                        setTimeout(() => {
                            notification.fadeOut();
                        }, 60000);

                    } else {
                        // Display error message from server response
                        notification
                            .text(res.data.message || 'An error occurred while processing the payment method or creating the customer.')
                            .css({ color: 'red', display: 'block' });

                        setTimeout(() => {
                            notification.fadeOut();
                        }, 60000);
                    }
                },
                error: (err) => {
                    console.error('Error occurred:', err);
                },
                complete: () => {
                    // Remove the loader when the request is complete
                    loader.remove();
                }
            });
        });

        // Cancel Subscription Click Event
        $('.cancel-subscription').click(function (e) {
            e.preventDefault();

            // Get the subscription ID and customer ID from data attributes
            const subscription_id = $(this).data('subscription-id');
            const customer_id = $(this).data('customer-id');
            const messageContainer = $('#service-message');

            // Confirm the action
            if (!confirm("Are you sure you want to cancel this subscription?")) {
                return;
            }

            // Show the loader
            const loader = $(`
                <div id="dynamic-loader">
                    <div class="loader-circle"></div>
                    <div>Loading...</div>
                </div>
            `);

            loader.css({
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

            // Perform the AJAX request to cancel the subscription
            $.ajax({
                url: mzsData.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'cancel_subscription',
                    subscription_id: subscription_id,
                    customer_id: customer_id,
                    nonce: mzsData.nonce
                },
                success: (res) => {
                    if (res.success) {
                        // Display success message with Bootstrap alert style
                        messageContainer.html(`
                            <div class="alert alert-success" role="alert">
                                ${res.data || 'Subscription canceled successfully.'}
                            </div>
                        `).fadeIn().delay(3000).fadeOut();

                        // Optional: Refresh the table or page if needed
                        setTimeout(() => {
                            location.reload();
                        }, 200);
                    } else {
                        // Display error message with Bootstrap alert style
                        messageContainer.html(`
                            <div class="alert alert-danger" role="alert">
                                ${res.data || 'Failed to cancel subscription.'}
                            </div>
                        `).fadeIn().delay(3000).fadeOut();
                    }
                },
                error: (err) => {
                    console.error('Error occurred:', err);
                    messageContainer.html(`
                        <div class="alert alert-danger" role="alert">
                            An error occurred. Please try again.
                        </div>
                    `).fadeIn().delay(3000).fadeOut();
                },
                complete: () => {
                    loader.remove();
                }
            });
        });

        // Toggle Pause Service Click Event
        $('.toggle-pause').on('click', function () {
            var button = $(this);
            var serviceId = button.data('service-id');
            var isPaused = button.data('paused'); // 1 = paused, 0 = active
            var messageContainer = $('#service-message');

            // Add loading spinner inside button
            button.html('<span class="loader-spinner"></span>').prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: mzsData.ajaxUrl,
                data: {
                    action: 'toggle_pause_service',
                    service_id: serviceId,
                    is_paused: isPaused
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {
                        var newStatus = response.data.is_paused ? 'Resume' : 'Pause';
                        console.log(newStatus);

                        // Update button text and status
                        button.html(newStatus).prop('disabled', false);
                        button.data('paused', response.data.is_paused);

                        // Show success message in Bootstrap alert style
                        var message = response.data.is_paused
                            ? '<div class="alert alert-success">Call is paused successfully.</div>'
                            : '<div class="alert alert-success">Call is resumed successfully.</div>';

                        messageContainer.html(message).fadeIn().delay(3000).fadeOut();
                    } else {
                        // Show error message in Bootstrap alert style
                        messageContainer.html('<div class="alert alert-danger">Error updating status. Please try again.</div>').fadeIn().delay(3000).fadeOut();
                    }
                },
                error: function () {
                    messageContainer.html('<div class="alert alert-danger">An error occurred. Please try again.</div>').fadeIn().delay(3000).fadeOut();
                },
                complete: function () {
                    button.prop('disabled', false); // Re-enable the button after processing
                }
            });
        });
    });
})(jQuery);
