<?php
class appm
{
    public $plugin_name;  
    

    public function __construct()
    {
         // check if required plugin is installed/not.
        add_action('admin_notice', array($this, 'check_required_plugin'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        // Add frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        // create post type hook
        add_action('init', array($this, 'app_manage_register_post_type'));
        // Register the shortcode
        add_shortcode('app_details_form', array($this, 'app_details_form_shortcode'));
        add_action('wp_ajax_handle_form_submission', array($this, 'handle_form_submission'), 10);
        add_action('wp_ajax_nopriv_handle_form_submission', array($this, 'handle_form_submission'), 10);
        // Add submenu for "App Builds"
        add_action('admin_menu', array($this, 'add_app_builds_submenu'));
        // Handle form submission
        // add_action('init', array($this, 'handle_form_submission'));
        // Add custom columns to the post list table
        add_filter('manage_app_manage_posts_columns', array($this, 'add_custom_columns'));

        // Display data in custom columns
        add_action('manage_app_manage_posts_custom_column', array($this, 'custom_column_data'), 10, 2);

        //Metabox hooks
        add_action('add_meta_boxes', array($this, 'add_custom_metabox'));
        add_action('save_post', array($this, 'save_custom_metabox_data'));
        add_filter('manage_app_manage_posts_columns', array($this, 'wpse152971_replace_column_title_method_a'));
        add_action('post_edit_form_tag', array($this, 'update_edit_form'));
        //Create zip 
        add_action('wp_ajax_create_zip', array($this, 'create_zip_callback'));
        add_action('wp_ajax_nopriv_create_zip', array($this, 'create_zip_callback'));

        add_action('wp_ajax_increment_app_version_callback',array($this, 'increment_app_version_callback'));
        
        add_action('wp_ajax_nopriv_increment_app_version_callback', 'increment_app_version_callback');
        $auth    = new App_Management_Api_Auth();
    }
    
    public function increment_app_version_callback()
{
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $app_status = isset($_POST['app_status']) ? intval($_POST['app_status']) : '';

    $app_version = get_comment_meta($post_id, 'appm_app_version', true);
    if( empty($app_version) ){
        $app_version = '1.0.0';
        add_comment_meta($post_id, 'appm_app_version', $app_version);
    }
    else if( $app_status === 'processing' || $app_status === 'cancelled' || $app_status === 'Completed' ){
        $app_version = str_replace(' ', '', $app_version);
        $app_version = $this->increment_version($app_version);
        update_comment_meta($post_id, 'appm_app_version', $app_version);
    }
    // if (!empty($app_version) && isset($_POST['app_action']) && $_POST['app_action'] === 'download_zip') {
    //     // Increment the version only when Download ZIP is clicked
    //     $app_version = str_replace(' ', '', $app_version);
    //     $app_version = $this->increment_version($app_version);
    //     update_comment_meta($post_id, 'appm_app_version', $app_version);
    // } elseif (empty($app_version) && isset($_POST['app_action']) && $_POST['app_action'] === 'download_zip') {
    //     // If there's no existing version and Download ZIP is clicked, start with '1.0.0'
    //     $app_version = '1.0.0';
    //     add_comment_meta($post_id, 'appm_app_version', $app_version);
    // }
    $response_data = array(
        'current_version' => $app_version,
    );
    wp_send_json_success($response_data);
}
public function increment_version($current_version)
{
    // Explode the current version into major, minor, and patch parts
    $version_parts = explode('.', $current_version);

    $major = (int)$version_parts[0];
    $minor = (int)$version_parts[1];
    $patch = (int)$version_parts[2];

    if ($major === 0 && $minor === 0 && $patch === 0) {
        // If the version is '0.0.0', start with '1.0.0'
        $major = 1;
        $minor = 0;
        $patch = 0;
    } else {
        $patch++;

        if ($patch > 9) {
            $patch = 0;
            $minor++;

            if ($minor > 9) {
                $minor = 0;
                $major++;
            }
        }
    }

    return "{$major}.{$minor}.{$patch}";
}

    
    public function add_app_builds_submenu()
    {
        add_submenu_page(
            'edit.php?post_type=app_manage', // Parent menu (App Manage)
            'App Builds', // Page title
            'App Builds', // Menu title
            'manage_options', // Capability
            'app_builds', // Menu slug
            array($this, 'app_builds_page_callback') // Callback function to display the page
        );
    }
       
   
        public function app_builds_page_callback()
       {
          
   
        }
    
   

    


    public function create_zip_callback()
    {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id > 0) {
            $zip_url = $this->create_zip_file_new($post_id);
            echo $zip_url;
            exit;
        }
    }



    public function update_edit_form()
    {
        echo ' enctype="multipart/form-data"';
    }
    public function wpse152971_replace_column_title_method_a($columns)
    {
        $columns['title'] = 'Application Name';
        return $columns;
    }

    public function check_required_plugin()
    {
        // Implement your plugin check logic here
    }

    /**
     * Add admin scripts for the plugin
     *
     * @param void
     * @return void
     *
     */
    public function admin_scripts()
    {
        wp_enqueue_style('app-admin-style', plugins_url('assets/admin/css/admin-style.css', APPM_FILE));
        // Enqueue Font Awesome from the CDN
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
        wp_enqueue_script('admin-script', plugins_url('assets/admin/js/admin-script.js', APPM_FILE), array('jquery'), null, true);
        // $this->enqueue_custom_scripts();
        $params = array(
            'ajaxurl'         => admin_url('admin-ajax.php'),
            'security'        => wp_create_nonce('zip_download'),
        );
        wp_localize_script('admin-script', 'zip_download', $params);
         
        wp_enqueue_script('increment-version-script', plugins_url('assets/admin/js/increment-version-script.js', APPM_FILE), array('jquery'), null, true);
        $version_params = array(
           'post_id' => get_the_ID(),
           'currentVersion' => get_post_meta(get_the_ID(), 'app_version', true) ?: '1.0.0',
           'security' => wp_create_nonce('increment_version'),
           'ajax_url' => admin_url('admin-ajax.php')
       );
       wp_localize_script('increment-version-script', 'increment_version_params', $version_params);
       // Localize the AJAX URL
    wp_localize_script('increment-version-script', 'increment_version_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));

        
    }
//     public function enqueue_custom_scripts()
// {
//     wp_enqueue_script('app-status-script', plugins_url('assets/js/app-status-script.js', APPM_FILE), array('jquery'), null, true);
//     $app_status_data = array(
//         'pending' => array('accepted', 'cancelled'),
//         'accepted' => array('processing', 'cancelled'),
//         'processing' => array('completed', 'cancelled'),
//         'completed' => array(),
//         'cancelled' => array()
//     );
//     wp_localize_script('app-status-script', 'appStatusData', $app_status_data);
// }



    /**
     * Add admin scripts for the plugin
     *
     * @param void
     * @return void
     *
     */
    public function frontend_scripts()
    {
        wp_enqueue_script('jquery');

        wp_enqueue_script('app-form-script', plugins_url('assets/js/custom-script.js', APPM_FILE), array('jquery'), null, true);
        $params = array(
            'ajaxurl'         => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('file_upload'),
        );
        wp_localize_script('app-form-script', 'form_submission', $params);
    }
    // post type create
    public function app_manage_register_post_type()
    {
        $labels = array(
            'name' => 'App Manage',
            'singular_name' => 'App Manage',
            'menu_name' => 'App Manage',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'app-manage'), // Changed to a valid slug
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        );
        register_post_type('app_manage', $args); // Changed to a valid post type name
    }

    // creating form shortcode
    public function app_details_form_shortcode()
    {
        ob_start();
        ?>
        <form id="app-details-form" method="post" action="" enctype="multipart/form-data">
            <label>Upload Icon:</label>
            <input type="file" name="icon_upload" id="icon_upload">
            <span id="valid_check_box" style="display:none;color:red;">Please choose icon image</span></br>

            <label for="app_name">App Name:</label>
            <input type="text" name="app_name" id="app_name"></br>

            <label for="pack_name">Package Name:</label>
            <input type="text" name="pack_name" id="pack_name"></br>

            <!-- <label for="pack_name">Platform:</label> -->
            <input type="radio" id="android" name="app_type" value="android">
            <label for="android">Android App</label>
            <input type="radio" id="ios" name="app_type" value="ios">
            <label for="ios">IOS App</label><br>
            <span id="valid_radio" style="color:red;"></span><br>

            <label for="firebase_file">Firebase file:</label>
            <input type="file" name="firebase_file" id="firebase_file"></br>
            <span id="valid_firebase_file" style="display:none;color:red;">Please choose Firebase</span></br>

            <!-- <input type="submit" name="submit"> -->
            <button type="submit" value="submit">Create</button>
            <button type="button" id="cancel-button">Cancel</button>
           
        </form>
        <?php
        return ob_get_clean(); // Return the buffered content
    }

    // Handle form submission
    public function handle_form_submission()
    {
        if (!empty($_POST['app_name'])) {
            $post_data = array(
                'post_title' => sanitize_text_field($_POST['app_name']),
                'post_type' => 'app_manage', // Use the same post type name
                'post_status' => 'publish',
            );

            $post_id = wp_insert_post($post_data);

            if ($post_id) {
                // Save form data as post meta
                update_post_meta($post_id, 'app_name', sanitize_text_field($_POST['app_name']));
                update_post_meta($post_id, 'pack_name', sanitize_text_field($_POST['pack_name']));

                // Save selected radio button value as post meta
                if (isset($_POST['app_type']) && ($_POST['app_type'] === 'android' || $_POST['app_type'] === 'ios')) {
                    update_post_meta($post_id, 'app_type', sanitize_text_field($_POST['app_type']));
                }
                // Handle icon_upload and firebase_file here
                $this->handle_uploaded_files($post_id);
            }
        }
        wp_die();
    }
    // }

    public function handle_uploaded_files($post_id)
    {
        if (isset($_FILES['icon_upload'])) {
            $icon_upload = $_FILES['icon_upload'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($icon_upload, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $file_url = $movefile['url'];
                update_post_meta($post_id, 'icon_url', $file_url);
            }
        }

        if (isset($_FILES['firebase_file'])) {
            $firebase_upload = $_FILES['firebase_file'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($firebase_upload, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $file_url = $movefile['url'];
                update_post_meta($post_id, 'firebase_file_url', $file_url);
            }
        }
    }

    // Add a custom column to the post list table
    public function add_custom_columns($columns)
    {
        $columns['icon_url'] = 'Icon';
        $columns['pack_name'] = 'Package Name';
        $columns['app_type'] = 'Platform';
        $columns['status'] = 'Status';
        $columns['action'] = 'Action';
        return $columns;
    }

    // Populate custom columns with data
    public function custom_column_data($column, $post_id)
    {
        if ($column === 'icon_url') {
            $icon_url = get_post_meta($post_id, 'icon_url', true);
            if (!empty($icon_url)) {
                echo '<img id="blah" src="' . esc_url($icon_url) . '" alt="Icon" class="icon-image">';
            } else {
                echo 'No Icon';
            }
        }
        if ($column === 'pack_name') {
            $pack_name = get_post_meta($post_id, 'pack_name', true);
            echo esc_html($pack_name);
        }
        if ($column === 'app_type') {
            $app_type = get_post_meta($post_id, 'app_type', true);
            echo esc_html($app_type);
        }
        if ($column === 'status') { ?>
            <select name="appm_app_status" class="rp_order_status rp_current_status_pending">
                <option value="pending" <?php selected(get_post_meta($post_id, 'appm_app_status', true), 'pending'); ?>>Pending</option>
                <option value="accepted" <?php selected(get_post_meta($post_id, 'appm_app_status', true), 'accepted'); ?>>Accepted</option>
                <option value="processing" <?php selected(get_post_meta($post_id, 'appm_app_status', true), 'processing'); ?>>Processing</option>
                <option value="cancelled" <?php selected(get_post_meta($post_id, 'appm_app_status', true), 'cancelled'); ?>>Cancelled</option>
                <option value="completed" <?php selected(get_post_meta($post_id, 'appm_app_status', true), 'completed'); ?>>Completed</option>
            </select>
        <?php
        }
        if ($column === 'action') {
            // Get the URL of the image from the plugin's directory
            // $image_path = plugins_url('app-management/assets/images/download-icon.png');
            // // Check if the image exists and then display it
            // if ($image_path) {
            //     echo '<img src="' . esc_url($image_path) . '" alt="Firebase" class="download-icon">';
            // } else {
            //     echo 'No Icon';
            // }
        ?>
           
            <a href="#" class="download-zip" data-post-id="<?php echo esc_attr($post_id); ?>">Download ZIP</a>


            <!-- <input type="button" class="download-zip" data-post-id=<?php // echo $post_id 
                                                                        ?> value="Download ZIP"> -->
        <?php
        }
    }
    //////////////////////META BOX FOR ADD REGISTER USER IN ADMIN //////////////////////

    // Hook to add the metabox to the post editing screen
    public function add_custom_metabox()
    {
        add_meta_box(
            'custom_metabox_id', // Unique ID
            'App Details Form', // Title displayed in the metabox
            array($this, 'render_custom_metabox_content'), // Callback function to render the metabox content
            'app_manage', // Post type (you can specify which post types should have this metabox)
            'normal', // Context (normal, advanced, or side)
            'high' // Priority (high, core, default, or low)
        );
        add_meta_box(
            'order_status_metabox',
            'updates status',
            array($this, 'add_right_sidebar_metabox_content'),
            'app_manage',
            'side', // Change 'normal' to 'side' to display on the right sidebar
            'default'
        );
        
    }

    // Callback function to render the metabox content
    public function render_custom_metabox_content($post)
    {
        $custom_value = get_post_meta($post->ID, 'custom_metabox_key', true);
        ?>
        <form class="ram" method="post" action="" enctype="multipart/form-data">
            <div class="inside">
                <div class="rpress-admin-box">


                    <div class="rpress-admin-box-inside">

                        <p>
                            <span class="label">App Status:</span>
                            <select name="appm_app_status" class="medium-text">
                                <option  value="pending" <?php selected(get_post_meta($post->ID, 'appm_app_status', true), 'pending'); ?>>
                                    Pending </option>
                                <option value="accepted" <?php selected(get_post_meta($post->ID, 'appm_app_status', true), 'accepted'); ?>>
                                    Accepted </option>
                                <option  class="initial-app-status" data-status="processing" value="processing" <?php selected(get_post_meta($post->ID, 'appm_app_status', true), 'processing'); ?>>
                                    Processing </option>
                                <option  class="initial-app-status" data-status="cancelled" value="cancelled" <?php selected(get_post_meta($post->ID, 'appm_app_status', true), 'cancelled'); ?>>
                                    Cancelled </option>
                                <option  class="initial-app-status" data-status="Completed" value="completed" <?php selected(get_post_meta($post->ID, 'appm_app_status', true), 'completed'); ?>>
                                    Completed </option>
                            </select>
                            <span alt="f223" class="rpress-help-tip dashicons dashicons-editor-help" title="<ul><li><strong>Pending</strong>: When the order is initially received by the restaurant.</li><li><strong>Accepted</strong>: When the restaurant accepts the order.</li><li><strong>Processing</strong>: When the restaurant starts preparing the food.</li><li><strong>Ready</strong>: When the order has been prepared by the restaurant.</li><li><strong>In Transit</strong>: When the order is out for delivery</li><li><strong>Cancelled</strong>: Order has been cancelled</li><li><strong>Completed</strong>: Payment has been done and the order has been completed.</li></ul>"></span>
                        </p>  
                    </div>
                    <div class="rpress-admin-box-inside">
                        <p class="rpress-order-payment">
                            <span class="label">Icon:</span>&nbsp;

                            <?php
                            // Get the post meta value for the icon URL here
                            $icon_url = get_post_meta(get_the_ID(), 'icon_url', true);
                            ?>

                            <?php if (!empty($icon_url)) :
                            ?>
                                <!-- Display the icon image if uploaded -->
                                <img src="<?php echo esc_url($icon_url); ?>" alt="Icon" class="icon-image">
                                <br>
                                <!-- Add a download button for the icon -->
                                <a href="<?php echo esc_url($icon_url); ?>" download class="download-button">Download Icon</a>
                            <?php else : ?>
                                <!-- Display a file upload input -->
                                <input type="file" name="icon_upload" id="icon_upload" /></br>
                            <?php endif; ?>
                        </p>
                    </div>



                    <div class="rpress-admin-box-inside">
                        <p class="rpress-order-taxes">
                            <span class="label">Package Name:</span>
                            <?php
                            // Get the post meta value for "App Name" here
                            $pack_name = get_post_meta(get_the_ID(), 'pack_name', true);
                            ?>
                            <input name="pack_name" class="med-text" type="text" value="<?php echo esc_attr($pack_name); ?>">
                            <span class="rpress-tax-rate">
                            </span>
                        </p>
                    </div>


                    <div class="rpress-admin-box-inside">
                        <p class="rpress-order-payment">
                            <span class="label">Platform:</span>&nbsp;

                            <?php
                            // Get the post meta value for "App Type" here
                            $app_type = get_post_meta(get_the_ID(), 'app_type', true);
                            ?>

                            <input type="radio" id="android" name="app_type" value="android" <?php echo ($app_type === 'android') ? 'checked' : ''; ?>>
                            <label for="android">Android App</label>

                            <input type="radio" id="ios" name="app_type" value="ios" <?php echo ($app_type === 'ios') ? 'checked' : ''; ?>>
                            <label for="ios">IOS App</label>
                        </p>
                    </div>

                    <div class="rpress-admin-box-inside">
                        <p class="rpress-order-payment">
                            <span class="label">Firebase File:</span>&nbsp;

                            <?php
                            // Get the post meta value for the icon URL here
                            $firebase_file_url = get_post_meta(get_the_ID(), 'firebase_file_url', true);
                            ?>

                            <?php if (!empty($firebase_file_url)) : ?>
                                <!-- Display the icon image if uploaded -->
                                <img src="<?php echo esc_url($firebase_file_url); ?>" alt="Icon" class="icon-image">
                                <br>
                                <!-- Add a download button for the icon -->
                                <a href="<?php echo esc_url($firebase_file_url); ?>" download class="download-button">Download Icon</a>
                            <?php else : ?>
                                <!-- Display a file upload input if no icon is uploaded -->
                                <input type="file" name="firebase_file_url" id="firebase_file_url">
                            <?php endif; ?>
                        </p>
                    </div>


                    <div class="rpress-order-payment-recalc-totals rpress-admin-box-inside" style="display:none">
                        <p>
                            <span class="label">Recalculate Totals:</span>&nbsp;
                            <a href="" id="rpress-order-recalc-total" class="button button-secondary right">Recalculate</a>
                        </p>
                    </div>
                    <div class="download">
                          
                            <button class="download-zip" data-post-id=<?php echo $post->ID ?>>Download ZIP</button>

                        </div>
                        <div class="version-message">
                    <p>
                        <span class="label">Version: </span>
                        <?php 
                        $version_app = get_comment_meta($post->ID, 'appm_app_version', true);
                        $version_app = !empty($version_app) ? $version_app : 'No builds yet';
                        echo $version_app;

                        ?>
                    </p>
                    </div>


                </div>
            </div>
        </form>
        <?php   
    }
    public function add_right_sidebar_metabox_content($post)
    {
        $comments = get_comments(array('post_id' => $post->ID));
        $appStatusMessages = array();
        $versionMessages = array();
        foreach ($comments as $message) {
            if ($message->comment_type === 'appm_app_status') {
                $appStatusMessages[] = '<span style="background-color: #e6e6e6; color: #333; font-size: 16px;">App Status: ' . esc_html($message->comment_content) . '</span>';
            } elseif ($message->comment_type === 'appm_app_version') {
                $versionMessages[] = '<span style="background-color: #f2f2f2; color: #0073aa; font-size: 14px;">App Version: ' . esc_html($message->comment_content) . '</span>';
            } elseif ($message->comment_type === 'appm_order_messages') {
                $orderMessages = json_decode($message->comment_content, true);
                if (!empty($orderMessages['messages'])) {
                    foreach ($orderMessages['messages'] as $orderMessage) {
                        $appStatusMessages[] = '<span style="background-color: #ffffcc; color: #990000; font-size: 12px;">App Status: ' . esc_html($orderMessage) . '</span>';
                    }
                }
            }
        }
        $mergedMessages = array();
        $count = max(count($appStatusMessages), count($versionMessages));
        for ($i = 0; $i < $count; $i++) {
            if (isset($appStatusMessages[$i])) {
                $mergedMessages[] = $appStatusMessages[$i];
            }
            if (isset($versionMessages[$i])) {
                $mergedMessages[] = $versionMessages[$i];
            }
        }
        foreach ($mergedMessages as $mergedMessage) {
            echo $mergedMessage . '<br>'. date('F j, Y g:i a'). '<br>';
        }
    }
    public function save_custom_metabox_data($post_id)
   {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    // Handle file uploads if needed
    if (isset($_FILES['icon_upload'])) {
        $icon_upload = $_FILES['icon_upload'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($icon_upload, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $file_url = $movefile['url'];
            update_post_meta($post_id, 'icon_url', $file_url);
        }
    }
    if (isset($_POST['appm_app_status'])) {
        $new_status = sanitize_text_field($_POST['appm_app_status']);
        $old_status = get_post_meta($post_id, 'appm_app_status', true);
        if ($new_status !== $old_status) {
            $current_version = get_comment_meta($post_id, 'appm_app_version', true);
            if (in_array($new_status, array('processing', 'cancelled', 'completed'))) {
                $new_version = $this->increment_version($current_version);
                $version_message = 'Version updated from ' . esc_html($current_version) . ' to ' . esc_html($new_version) . '.';
                update_comment_meta($post_id, 'appm_app_version', $new_version);
                $commentdata = array(
                    'comment_post_ID' => $post_id,
                    'comment_content' => $version_message,
                    'comment_type' => 'appm_app_version', // Use the correct comment type
                );
                wp_insert_comment($commentdata);
            }
            $old_status = get_comment_meta($post_id, 'appm_app_status', true);
            $current_message = 'Order status changed from ' . $old_status . ' to ' . $new_status . '.';
            $previous_messages['status'] = $new_status;
            $previous_messages['messages'] = array($current_message);
            update_comment_meta($post_id, 'appm_order_messages', $previous_messages);
            update_comment_meta($post_id, 'appm_app_status', $new_status);
            $commentdata = array(
                'comment_post_ID' => $post_id,
                'comment_content' => json_encode($previous_messages),
                'comment_type' => 'appm_order_messages', // Use the correct comment type
            );
            wp_insert_comment($commentdata);
        }
    }
        // Save the custom field data
        if (isset($_POST['custom_metabox_field'])) {
            update_post_meta($post_id, 'custom_metabox_key', sanitize_text_field($_POST['custom_metabox_field']));
        }
        if (isset($_POST['pack_name'])) {
            update_post_meta($post_id, 'pack_name', sanitize_text_field($_POST['pack_name']));
        }
        if (isset($_POST['app_type'])) {
            update_post_meta($post_id, 'app_type', sanitize_text_field($_POST['app_type']));
        }
        if (isset($_POST['appm_app_status'])) {
            update_post_meta($post_id, 'appm_app_status', sanitize_text_field($_POST['appm_app_status']));
        }
    }

    public function create_zip_file_new($post_id)
    {
        $post = get_post($post_id);
        $post_title = sanitize_file_name($post->post_title);
        $dir = wp_upload_dir()['basedir'] . '/' . $post_title . '/';
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        // Create the icon folder inside the temporary directory
        $replace_folder = $dir . 'replace/';
        if (!file_exists($replace_folder)) {
            mkdir($replace_folder, 0755, true);
        }
        // Create the "assets" folder inside the "replace" folder
        $assets_folder = $replace_folder . 'assets/';
        if (!file_exists($assets_folder)) {
            mkdir($assets_folder, 0755, true);
        }
        // Create the "package_rename.json" file
        $package_rename_data = [
            "package_rename_config" => [
                "client" => [
                    "client" => "client_info",
                ],
                "oauth_client" => [
                    "app_name" => "519179141595-jdfq9bm2aqgjtdq95nmlrfp0sueomstl.apps.googleusercontent.com",

                ]
            ]
        ];
        $package_rename_json_file = $replace_folder . 'google-services.json';
        file_put_contents($package_rename_json_file, json_encode($package_rename_data, JSON_PRETTY_PRINT));
        // Create a JSON structure based on platform values
        $json_data = [];
        $app_type = get_post_meta($post_id, 'app_type', true);
        if ($app_type === 'android') {
            $json_data["package_rename_config"]["android"] = [
                "app_name" => "RestroPress - Order Tracking",
                "package_name" => "com.restropress.tracking"
            ];
        } elseif ($app_type === 'ios') {
            $json_data["package_rename_config"]["ios"] = [
                "app_name" => "RestroPress - Order Tracking",
                "bundle_name" => "RestroPress - Order Tracking",
                "package_name" => "com.restropress.tracking"
            ];
        }
        $json_file = $dir . 'package_rename_config.json';
        file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT));
        // Add code to copy the icon image to the "assets" folder
        $icon_url = get_post_meta($post_id, 'icon_url', true);
        if ($icon_url) {
            $icon_filename = basename(parse_url($icon_url, PHP_URL_PATH));
            copy($icon_url, $assets_folder . $icon_filename);
        }
        //Folder Structure end



        // Create a new ZIP archive

        $rootPath = realpath($dir);
        $zipFileName = $post_title . '.zip';

        // Initialize archive object
        $base_dir = wp_upload_dir()['basedir'] . '/' . $zipFileName;
        // print_r($base_dir); exit;

        $zip = new ZipArchive();
        $zip->open($base_dir, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();
        $file_url = wp_upload_dir()['baseurl'] . '/' . $zipFileName;
        return $file_url;
        exit;
        // Send the ZIP file for download
        // header('Content-Type: application/zip');
        // header('Content-Disposition: attachment; filename= "' . $file_url . '"');
        // header('Content-Length: ' . filesize($base_dir));


        // readfile($file_url);

        // Clean up: Remove the temporary ZIP file
        // unlink($zipFileName);
        


    }
    
}
