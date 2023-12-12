<?php

/**
 * The public-facing functionality of the plugin.
 */
class App_Management_Api_Auth
{
    /**
     * The namespace to add to the api calls.
     *
     * @var string The namespace to add to the api call
     */
    private $namespace;

    /**
     * Setup action & filter hooks.
     */
    public function __construct()
    {
        $this->namespace = 'appmanagement-api/v1';
        //Rest Api init
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    /**
     * Add the endpoints to the API
     */
    public function register_rest_routes()
    {
        //For getting form data
        register_rest_route(
            $this->namespace,
            'apps',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_app'),
                'permission_callback' => '__return_true',
            )
        );

        //For adding form data
        register_rest_route(
            $this->namespace,
            'add-update',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'add_update_app'),
                'permission_callback' => '__return_true',
            )
        );
        // For updating form data
        register_rest_route(
            $this->namespace,
            '/updates_status/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_updates_status'),
                'permission_callback' => '__return_true',
            )
        );
        // For status
        register_rest_route(
            $this->namespace,
            'status',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'status_app'),
                'permission_callback' => '__return_true',
            )
        );
        //For adding form data
        register_rest_route(
            $this->namespace,
            'delete',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'delete_app'),
                'permission_callback' => '__return_true',
                //'permission_callback' => array($this, 'permission_delete'),
            )
        );   
    }
    public function permission_delete()
    {
        if (is_admin()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get token by sending POST request to woomobile-api/v1/token.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response The response.
     */
    public function get_app(WP_REST_Request $request)
    {
        $page    = $request->get_param('page');
        $per_page    = $request->get_param('perPage');

        $args = array(
            'post_type' => 'app_manage', // Replace with your post type name
            'paged' => $page,
            'posts_per_page' => $per_page, // Retrieve all posts of this type
        );

        $query = new WP_Query($args);
        $posts_data = array(); //Initialize the array to store post data
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Retrieve post data
                $post_data = array(
                    'id' => get_the_ID(),
                    'application_name' => get_the_title(),
                    'icon_url' => get_comment_meta(get_the_ID(), 'icon_url', true),
                    'package_name' => get_post_meta(get_the_ID(), 'pack_name', true),
                    'app_type' => get_post_meta(get_the_ID(), 'app_type', true),
                    'status' => get_post_meta(get_the_ID(), 'appm_app_status', true),
                    'firebase_file_url' => get_post_meta(get_the_ID(), 'firebase_file_url', true),
                );
                $posts_data[] = $post_data; // Add post data to the array
            }
        }

        // Restore global post data
        wp_reset_postdata();
        // Encode the array as JSON and return it
        return rest_ensure_response($posts_data);
    }

    //Add and update form data
    public function add_update_app(WP_REST_Request $request)
    {
        $data = $request->get_json_params(); // Get data from the JSON request
        // Create a new post with the provided data
        if (!empty($data['id'])) {
            $post_id =  wp_update_post(array(
                'post_title' => sanitize_text_field($data['application_name']),
                'ID' => $data['id']
            ));
        } else {
            $post_id = wp_insert_post(array(
                'post_title' => sanitize_text_field($data['application_name']),
                'post_type' => 'app_manage', // Your post type
                'post_status' => 'publish',
            ));
        }
        // Update custom fields with the provided data
        update_post_meta($post_id, 'icon_url', sanitize_text_field($data['icon_url']));
        update_post_meta($post_id, 'pack_name', sanitize_text_field($data['package_name']));
        update_post_meta($post_id, 'app_type', sanitize_text_field($data['app_type']));
        update_post_meta($post_id, 'appm_app_status', sanitize_text_field($data['status']));
        update_post_meta($post_id, 'firebase_file_url', sanitize_text_field($data['firebase_file_url']));

        // Return a success response
        return rest_ensure_response(array(
            'id' => $post_id,
            'message' => 'Record added successfully'
        ));
    }

    //For status data
     //Add and update form data
     public function status_app(WP_REST_Request $request)
     {
         $data = $request->get_json_params(); // Get data from the JSON request
         // Create a new post with the provided data
         if (!empty($data['post_id'])) {
             $post_id =  wp_update_post(array(
                
                'id' => $data['post_id'],
                 'appm_app_status' => sanitize_text_field($data['appm_app_status']),
                //  'post_type' => 'app_manage', // Your post type
                //  'post_status' => 'publish'
             ));
          } 
       else {
             $post_id = wp_insert_post(array(
                'appm_app_status' => sanitize_text_field($data['appm_app_status']),
                'post_type' => 'app_manage', // Your post type
                 'post_status' => 'publish',
             ));

         }
         // Update custom fields with the provided data
         update_post_meta($post_id, 'appm_app_status', sanitize_text_field($data['appm_app_status']));
 
         // Return a success response
         return rest_ensure_response(array(
             'id' => $post_id,
             'appm_app_status' => sanitize_text_field($data['appm_app_status']),
             'message' => 'Status updated successfully'
         ));
     }

    //Delete data
    public function delete_app(WP_REST_Request $request)
    {
        $post_id = $request->get_param('post_id'); // Get the post ID to delete

        // Check if the post exists and is of the correct post type
        if (get_post_type($post_id) === 'app_manage') {
            // Delete the post and its associated custom fields
            wp_delete_post($post_id, true);

            // Return a success response
            return rest_ensure_response(array('message' => 'Record deleted successfully'));
        } else {
            // Return an error response if the post doesn't exist or is of the wrong type
            return new WP_REST_Response(
                array('message' => 'Record not found or incorrect post type'),
                404
            );
        }
    }
    public function get_updates_status($data)
{
    $post_id = $data['id'];

    if ($post = get_post($post_id)) {
        $post_type = get_post_type($post);

        if ($post_type === 'app_manage') {
            // Retrieve all comments for the post
            $comments = get_comments(array(
                'post_id' => $post_id,
            ));

            // Extract all messages from comments
            $all_messages = array();
            foreach ($comments as $comment) {
                $message_type = $comment->comment_type;
                $decoded_content = json_decode($comment->comment_content, true);
                $content = is_array($decoded_content) ? $decoded_content : $comment->comment_content;

                $all_messages[$message_type][] = array(
                    'date'    => $comment->comment_date,
                    'content' => $content,
                );
            }

            // Retrieve other information (version, order status, etc.)
            $version = get_comment_meta($post_id, 'appm_app_version', true);
            $order_status = get_comment_meta($post_id, 'appm_app_status', true);
            $status_messages = get_comment_meta($post_id, 'appm_order_messages', true);
            $status_messages = is_array($status_messages) ? $status_messages : array();

            return rest_ensure_response(array(
                'post_id'      => $post_id,
                'version'      => $version,
                'app_status'   => $order_status,
                'date'         => $comment->comment_date,
                'all_messages' => $all_messages,
            ));
        } else {
            return new WP_REST_Response(
                array('message' => 'Record not found or incorrect post type'),
                404
            );
        }
    } else {
        return new WP_REST_Response(
            array('message' => 'Record not found'),
            404
        );
    }
}
}










