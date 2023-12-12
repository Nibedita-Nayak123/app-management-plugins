jQuery(document).ready(function ($) {
    var downloadZipClicked = false;

    $('.download-zip').on('click', function () {
        var postID = $(this).data('post-id');
        downloadZipClicked = true;

        // Fetch the app status from the corresponding element, adjust the selector as needed
        var appStatus = $('[name="appm_app_status"]').val();

        $.ajax({
            url: increment_version_params.ajax_url,
            type: 'POST',
            data: {
                action: 'increment_app_version_callback',
                post_id: postID,
                app_status: appStatus
            },
            success: function (response) {
                if (response.success) {
                    var versionMessage = '<p><span class="label">Version: </span>' + response.data.current_version + '</p>';
                    $('.version-message').html(versionMessage);

                    // Check if app status is Completed, Canceled, or Processing
                    var appStatus = response.data.app_status;
                    if (['Completed', 'Canceled', 'Processing'].includes(appStatus)) {
                        // Display alert only for the specified app statuses
                        alert('App status is ' + appStatus + '.');
                    }
                } else {
                    console.error(response.data.error);
                }
            },
            error: function (error) {
                console.error('AJAX error:', error);
            }
        });

        // Enable the "Publish" button after clicking "Download ZIP"
        $('#publish').prop('disabled', false);
    });

    $('#publish').on('click', function (event) {
        if (!downloadZipClicked) {
            // Prevent the default behavior of the "Publish" button if "Download ZIP" was not clicked
            event.preventDefault();
            alert('Please click "Download ZIP" before updating.');
        }
    });
});
