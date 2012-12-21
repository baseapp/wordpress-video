<?php

class Videoba {

    public $ok = 1;
    public $my_post = array();
    public $id, $filename, $wp_file_type, $wp_upload_dir, $upload, $attach_id, $attach_data, $pages, $link, $url_val;
    public $attachment = array();
    public $arr = array();
    public $a = array();
    public $i, $customPosts;

    public function manualSubmit1() {

        if (isset($_POST['submit']) || isset($_POST['editpost'])) {     //If 'submit' or 'update' button is pressed
            if ($_POST['embed'] == '') {                                    //Checking that fields are not empty    
                $this->ok = 0;
            }

            if ($_POST['title'] == '') {
                $this->ok = 0;
            }

            if ($this->ok) {                                                     //If fields are not empty
                $this->my_post = array();
                $this->my_post['post_title'] = $_POST['title'];

                if (isset($_POST['editpost'])) {
                    $this->my_post['post_content'] = $_POST['description'];
                    $this->my_post['ID'] = $_GET['id'];
                    $this->my_post['post_category'] = wp_set_post_terms($_GET['id'], $_POST['post_category'], 'category', TRUE);
                    //print_r($_POST['post_category']);
                } else {
                    $this->my_post['post_content'] = $_POST['description'];
                }
                $this->my_post['post_status'] = 'publish';
                $this->my_post['tags_input'] = $_POST['tags'];
                $this->my_post['post_category'] = $_POST['post_category'];

                if (isset($_POST['submit'])) {
                    //echo "in if";
                    $this->id = wp_insert_post($this->my_post);

                    if ($this->id) {
                        add_post_meta($this->id, 'videoswiper-embed-code', $_POST['embed']);
                        // add_post_meta($id, 'videoswiper-embed-thumb', $_POST['thumb']);
                        add_post_meta($this->id, 'videoswiper-embed-time', $_POST['time']);

                       if (isset($_FILES['thumb'])) {
                            $this->filename = $_FILES['thumb']['name'];
                           
                            $this->wp_filetype = wp_check_filetype(basename($this->filename), null);
                            $this->wp_upload_dir = wp_upload_dir();
                            $this->upload = wp_upload_bits($_FILES["thumb"]["name"], null, file_get_contents($_FILES["thumb"]["tmp_name"]));
                            //print_r($upload);
                            add_post_meta($this->id, 'thumb', $this->upload);
                            //   update_post_meta($id,'thumb',$upload);
                            $this->attachment = array(
                                'guid' => $this->upload['url'],
                                'post_mime_type' => $this->wp_filetype['type'],
                                'post_title' => preg_replace('/\.[^.]+$/', '', basename($this->filename)),
                                'post_content' => '',
                                'post_status' => 'inherit'
                            );

                            $this->attach_id = wp_insert_attachment($this->attachment, $this->upload['file'], $this->id);
                            
                            // you must first include the image.php file
                            // for the function wp_generate_attachment_metadata() to work
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $this->attach_data = wp_generate_attachment_metadata($this->attach_id, $this->upload['file']);
                            wp_update_attachment_metadata($this->attach_id, $this->attach_data);

                            set_post_thumbnail($this->id, $this->attach_id);
                        }
                        if ($_POST['thumb']) {
                            add_post_meta($this->id, 'videoswiper-embed-thumb', $_POST['thumb']);
                        }
                    }
                } else {
                    echo "in else";
                    $this->id = wp_update_post($this->my_post);
                    if ($this->id) {
                        update_post_meta($this->id, 'videoswiper-embed-code', $_POST['embed']);
                        //update_post_meta($id, 'videoswiper-embed-thumb', $_POST['thumb']);
                        update_post_meta($this->id, 'videoswiper-embed-time', $_POST['time']);

                        if ($_POST['thumb']) {
                            //echo "url";
                            $this->pages = & get_children('numberposts=1&post_mime_type=image/jpeg&post_parent=' . $_GET['id']);
                            update_post_meta($this->id, 'videoswiper-embed-thumb', $_POST['thumb']);
                            foreach ($this->pages as $attachment_id => $attachment) {
                                
                                $this->link = $attachment->ID;
                                //echo $link;
                            }
                            if (!(empty($this->pages))) {
                                wp_delete_attachment($this->link);
                            }
                            else
                                echo "empty";
                        }
                        if (isset($_FILES['thumb'])) {
                            $this->filename = $_FILES['thumb']['name'];
                            $this->wp_filetype = wp_check_filetype(basename($this->filename), null);
                            $this->wp_upload_dir = wp_upload_dir();
                            $this->upload = wp_upload_bits($_FILES["thumb"]["name"], null, file_get_contents($_FILES["thumb"]["tmp_name"]));
                            //print_r($upload);
                            add_post_meta($this->id, 'thumb', $this->upload);
                            //   update_post_meta($id,'thumb',$upload);
                            $attachment = array(
                                'guid' => $this->upload['url'],
                                'post_mime_type' => $this->wp_filetype['type'],
                                'post_title' => preg_replace('/\.[^.]+$/', '', basename($this->filename)),
                                'post_content' => '',
                                'post_status' => 'inherit'
                            );

                            $this->attach_id = wp_insert_attachment($this->attachment, $this->upload['file'], $this->id);
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $this->attach_data = wp_generate_attachment_metadata($this->attach_id, $this->upload['file']);
                            wp_update_attachment_metadata($this->attach_id, $this->attach_data);

                            set_post_thumbnail($this->id, $this->attach_id);

                            //insert code to check if embed-thumb for post is non empty, then delete that post meta

                            $this->url_val = get_post_meta($_GET['id'], "videoswiper-embed-thumb", TRUE);
                            //echo "uel= " . $url_val;
                            if (!(empty($this->url_val))) {
                                //echo "url is not empty";
                                delete_post_meta($_GET['id'], "videoswiper-embed-thumb", $this->url_val);
                            }
                        }
                    }
                }
                ?>                        
                <div class="wrap"><h2>&nbsp</h2>                <!-- Success message -->
                    <div class="updated" id="message" style="background-color: rgb(255, 251, 204);">
                        <p><strong>Video was added successfully.</strong>
                            <a href="../">Visit site</a>
                    </div>
                </div>                        
                <?php
            } else {
                ?>                        
                <div class="wrap"><h2>&nbsp</h2>                <!-- Failure message -->
                    <div class="updated" id="message" style="background-color: rgb(255, 251, 204);">
                        <p><strong>There was an error when adding the video.</strong>
                    </div>
                </div>                        
                <?php
            }
            return;
        }

        if (isset($_POST['submit']) || isset($_POST['editpost'])) {     //Error message if fields are empty
            if ($_POST['embed'] == '') {
                echo '<div class="updated" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>You should enter a valid embed-code</strong></div>';
            }
            if ($_POST['thumb'] == '') {
                echo '<div class="updated" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>You should enter a valid thumbnail URL</strong></div>';
            }
            if ($_POST['title'] == '') {
                echo '<div class="updated" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>You should enter a title</strong></div>';
            }
        }
        include_once (plugin_dir_path(__FILE__) . '../views/addform.php');      //  Video submit form
    }

    public function show_main() {
        global $customFields;
        $customFields = "'videoswiper-embed-code'";
        //echo $customFields;
        //$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        if (!(isset($_POST['s'])) || empty($_POST['s']))
            $this->customPosts = new WP_Query();

        add_filter('posts_join', 'get_custom_field_posts_join');
        add_filter('posts_groupby', 'get_custom_field_posts_group');
        if (!(empty($_POST['s']))) {

            //   global $post, $current_post_id;

            function filter_where($where = '') {

                $title = $_POST['s'];
                $where .= "AND post_title LIKE '%$title%' OR post_content LIKE '%$title%'";
                //echo $where;
                return $where;
            }

            add_filter('posts_where', 'filter_where');

            $this->customPosts = new WP_Query();
            // print_r($customPosts);
        }
        $this->customPosts->query('&paged=');
        remove_filter('posts_join', 'get_custom_field_posts_join');
        remove_filter('posts_groupby', 'get_custom_field_posts_group');
        // print_r($customPosts);
        $this->arr = array();
        // global $a;
        $this->a = array();
        $this->i = 0;
        while ($this->customPosts->have_posts()) : $this->customPosts->the_post();
            $this->arr['ID'] = get_the_ID();
            $this->arr['post_title'] = get_the_title();
            $this->arr['guid'] = get_permalink();
            $this->arr['duration'] = get_post_meta(get_the_ID(), "videoswiper-embed-time", TRUE);
            $this->a[$this->i] = $this->arr;
            $this->i = ($this->i + 1);
        endwhile;
        include_once (plugin_dir_path(__FILE__) . '../views/list_table.php');       //  table head to display list of videos
        wp_reset_query();
    }

}

function get_custom_field_posts_join($join) {

    global $wpdb, $customFields;
    return $join . "  JOIN $wpdb->postmeta postmeta ON (postmeta.post_id = $wpdb->posts.ID and postmeta.meta_key in ($customFields)) ";
}

function get_custom_field_posts_group($group) {

    global $wpdb;
    $group .= " $wpdb->posts.ID ";
    return $group;
}
?>