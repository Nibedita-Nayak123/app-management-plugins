

jQuery(document).ready(function($) {
    $('.download-zip').on('click', function(e) {
        e.preventDefault();
        var postID = $(this).data('post-id');
        var ajaxurl = zip_download.ajaxurl;

        // Create an AJAX request to trigger the download ZIP action
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'create_zip',
                post_id: postID,
            },
            success: function(response) {
                window.location = response;
            }
        });
    });
});

