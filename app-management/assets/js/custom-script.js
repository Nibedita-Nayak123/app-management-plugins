
jQuery(function($) {
    // Create a FormData object to hold the files and other form data
    var form_data = new FormData();

    $('body').on('change', '#icon_upload, #firebase_file', function(event) {
        event.preventDefault();
        $this = $(this);
        var file_data = $(this).prop('files')[0];

        // Append the file data to the FormData object based on the input field's ID
        form_data.append(this.id, file_data);
    });

    $('#app-details-form').on('submit', function (e) {
        e.preventDefault();
        var app_name = $('#app_name').val();
        var pack_name = $('#pack_name').val();
        var app_type = $("input[name='app_type']:checked").val();

        // Append other form data to the FormData object
        form_data.append('app_name', app_name);
        form_data.append('pack_name', pack_name);
        form_data.append('app_type', app_type);
        form_data.append('action', 'handle_form_submission');
        form_data.append('security', form_submission.security);

        $.ajax({
            url: form_submission.ajaxurl,
            type: 'POST',
            contentType: false,
            processData: false,
            data: form_data,
            success: function (response) {
                // Handle the success response
            }
        });
    });

   
});





