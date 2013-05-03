<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

// Exit if already loaded
if ( defined ( 'WP_POST_ATTACHMENTS_VERSION' ) )
    return;

define ( 'WP_POST_ATTACHMENTS_VERSION', '0.1' );

define ( 'WP_POST_ATTACHMENTS_DIR', plugin_dir_path(__FILE__) );
define ( 'WP_POST_ATTACHMENTS_URL', plugin_dir_url(__FILE__) );
define ( 'WP_POST_ATTACHMENTS_INC_URL', WP_POST_ATTACHMENTS_URL . '_inc/' );

class WP_PostAttachments
{
    private $domains = array();
    protected $meta_key = '_wp_post_attachments_file';
    protected $meta_type = 'post';
    protected $cache_key = 'wp-post-attachments';

    public $template;
    public $version = WP_POST_ATTACHMENTS_VERSION;

    /**
    * Creates an instance of the WP_PostAttachments class
    *
    * @return WP_PostAttachments object
    * @since 0.1
    * @static
    */
    public static function &init() {
        static $instance = false;

        if ( !$instance ) {
            $className = get_called_class();
            $instance = new $className;
        }

        return $instance;
    }


    public function __construct() {
        $template_class = ( class_exists ( get_called_class() . "_Template" ) ) ? get_called_class() . "_Template" : "WP_PostAttachments_Template";
        $this->template = new $template_class;
        $this->add_actions_and_filters();
    }

    public function get_textdomain( $post_type = '' ) {
        if ( empty ( $post_type ) ) @$post_type = get_post()->post_type;
        if ( isset ( $this->domains[$post_type] ) ) return $this->domains[$post_type];
        else return 'wp-post-attachments';
    }

    public function add_textdomain ( $post_type, $domain ) {
        $this->domains[$post_type] = $domain;
    }

    protected function add_meta( $post_id, $meta_value ) {
        return add_metadata( $this->meta_type, $post_id, $this->meta_key, $meta_value );
    }

    protected function get_meta ( $post_id, $single = false ) {
        return get_metadata( $this->meta_type, $post_id, $this->meta_key, $single);
    }

    protected function delete_meta ( $post_id, $meta_value = '' ) {
        return delete_metadata($this->meta_type, $post_id, $this->meta_key, $meta_value);
    }

    /**
     * PUBLIC FUNCTIONS
     */
    public function post_type_has_attachments ( $post_type ) {
        return post_type_supports( $post_type, 'post_attachments' );
    }

    public function attach_attachment ( $attachment_id, $post_id ) {
        $current_attachments = $this->get_all_attachment_ids( $post_id );
        if ( ! in_array ( $attachment_id, $current_attachments ) )
            return $this->add_meta ( $post_id, (int) $attachment_id );

        return true;
    }

    public function unattach_attachment( $post_id, $attachment_id, $comment_id = 0 ) {
        if ( $comment_id )
            delete_comment_meta( $comment_id, $this->meta_key, $attachment_id );
        return delete_post_meta( $post_id, $this->meta_key, $attachment_id );
    }

    public function get_attachment_count ( $post_id ) {
        return count ( $this->get_meta( $post_id ) );
    }

    public function get_all_attachments( $post_id = 0, $ids_only = false, $args = array() ) {
        if ( ! $post_id && ! $post_id = get_the_ID() )
            return array(); // Doing it wrong

        // Try to use cache
        if ( ! $ids_only && empty ( $args ) ) {
            $cache_key = "all_attachments_{$post_id}";
            if ( $ret = wp_cache_get( $cache_key, $this->cache_key ) )
                return $ret;
        }

        $attachment_ids = (array) $this->get_meta( $post_id );
        if ( $ids_only ) return $attachment_ids;

        $defaults = array(
            "post_type" => "attachment",
            "numberposts" => count ( $attachment_ids ),
            "post__in" => $attachment_ids );

        $args = array_merge( $defaults, $args );

        $ret = ( ! empty ( $attachment_ids ) ) ? get_posts ( $args ) : array();
        if ( isset ( $cache_key ) )
            wp_cache_set ( $cache_key, $ret, $this->cache_key );

        return $ret;
    }

        public function get_all_attachment_ids( $post_id = 0 ) {
            return $this->get_all_attachments( $post_id, true );
        }

    public function get_comment_attachments( $comment_id = 0, $ids_only = false, $args = array() ) {
        if ( ! $comment_id && ! $comment_id = get_comment_ID() )
            return array();

        if ( ! $ids_only && empty ( $args ) ) {
            $cache_key = "all_comment_attachments_{$comment_id}";
            if ( $ret = wp_cache_get( $cache_key, $this->cache_key ) )
                return $ret;
        }

        if ( $attachment_ids = get_comment_meta( $comment_id, $this->meta_key ) ) {
            $post_attachment_ids = $this->get_all_attachment_ids();
            $diff = array_diff( $attachment_ids, $post_attachment_ids);
        }
        if ( ! empty ( $diff ) ) {
            foreach ( $diff as $removable  ) {
                delete_comment_meta( $comment_id, $this->meta_key, $removable );
            }
            $attachment_ids = array_intersect( $attachment_ids, $post_attachment_ids );
        }
        if ( $ids_only ) return $attachment_ids;

        $defaults = array(
            "post_type" => "attachment",
            "numberposts" => count ( $attachment_ids ),
            "post__in" => $attachment_ids );
        $args = array_merge( $defaults, $args );

        $ret = ( ! empty ( $attachment_ids ) ) ? get_posts( $args ) : array();

        if ( isset ( $cache_key ) )
            wp_cache_set ( $cache_key, $ret, $this->cache_key );

        return $ret;
    }

        public function get_all_comment_attachment_ids( $comment_id = 0 ) {
            return $this->get_all_comment_attachments( $comment_id, true );
        }

    public function get_attachment_file_link($attachment) {
        $path = $attachment->guid;
        $url = wp_get_attachment_url( $attachment->ID );

        if ( ! $attachment->filename )
            $attachment->filename = substr ( $url, strrpos( $url, "/" ) + 1 );

        return "<a href='{$url}'>" . $attachment->filename . "</a>";
    }

        public function attachment_file_link($attachment) {
            echo $this->get_attachment_file_link($attachment);
        }

    /**
     * PRIVATE FUNCTIONS
     */
    protected function posted_attachments() {
        if ( isset ( $_POST['attachments'] ) && ! empty ( $_POST['attachments'] ) )
            return  json_decode ( stripslashes ( $_POST['attachments'] ) );
        else return false;
    }

    private function add_comment_attachments( $comment, $post_id ) {
        if ( $attachments = $this->posted_attachments() ) {
            if ( $attached = $this->add_attachments ( $post_id, $attachments ) ) {
                $this->add_attachments_to_comment_meta( $comment->comment_ID, $attached );
            }
        }
    }

    private function add_attachments_to_comment_meta($comment_id,$attachments_arr) {
        if ( empty ( $attachments_arr ) ) return true;
        foreach ( $attachments_arr as $attachment_id )
            add_comment_meta( $comment_id, $this->meta_key, $attachment_id );
    }

    /**
     * Link attachments to post
     *
     * @param int $post_id
     * @param arr $attachments
     * @param bool $exclusive Should we get rid of existing attachments first?
     * @return array All added attachments
     *
     * @todo Is exclusive necessary for something?
     */
    private function add_attachments ( $post_id, $attachments = false, $exclusive = false ) {
        if ( ! $attachments ) $attachments = $this->posted_attachments();

        // Force attachments to be an array
        if ( ! is_array ( $attachments ) ) {
            // If we're not exclusive, there's no need to go through the motions
            if ( ! $exclusive ) return array();
            $attachments = array();
        }
        $unattached = $obsolete = array();

        $old_attachments = $this->get_all_attachments( $post_id, true );
        // If exclusive, delete relationships
        if ( ! empty ( $old_attachments ) && $exclusive ) {
            if ( $obsolete = array_diff( $old_attachments, $attachments ) ) {
                foreach ( $obsolete as $orphan_id ) {
                    $this->unattach_attachment($post_id, $orphan_id);
                }
            }
        }

        // Do we have new attachments?
        if ( ! empty ( $attachments ) && $unattached = array_diff ( $attachments, $old_attachments ) ) {
            // Go!
            foreach ( $unattached as $attachment_id ) {
                $r = $this->attach_attachment( $attachment_id, $post_id );
            }
        }

        return $unattached;
    }

    /**
     * HOOKS
     */
    protected function add_actions_and_filters() {
        // Saving
        if ( current_user_can( 'upload_files' ) ) {
            add_action ( 'comment_post',    array ( &$this, 'maybe_add_comment_attachments'), 10, 1 );
            add_action ( 'save_post',       array ( &$this, 'maybe_add_post_attachments'), 10, 2 );
        }
        // Remove attachment cap
        add_filter ( 'user_has_cap',        array ( &$this, 'remove_attachment_cap_filter' ), 10, 3 );

        // AJAX Remove attachment
        add_action('wp_ajax_post_attachments_remove',
                                            array ( &$this, 'ajax_remove_attachment' ) );

    }

    public function ajax_remove_attachment() {
        check_ajax_referer('wppa_remove_attachment');

        $r = $this->unattach_attachment( $_POST['post_id'], $_POST['attachment_id'], $_POST['comment_id'] );
        if ( is_wp_error( $r ) ) exit(-1);

        echo 1;
        exit();
    }


    public function maybe_add_comment_attachments( $comment_id ) {
        $comment = get_comment ( $comment_id );
        $post_id = $comment->comment_post_ID;
        $post_type = get_post_type($post_id);

        if ( $this->post_type_has_attachments( $post_type ) ) {
            $this->add_comment_attachments( $comment, $post_id );
        }
    }

    public function maybe_add_post_attachments ( $post_id, $post ) {
        if ( ! wp_is_post_revision( $post ) && $this->post_type_has_attachments( $post->post_type ) )
            $this->add_attachments ( $post_id );
    }

    public function remove_attachment_cap_filter( $allcaps, $cap, $args ) {
        if ( 'remove_attachment' != $args[0] ) return $allcaps;

        $set_cap = false;

        // Bail out for users who already can do this
        foreach ( $cap as $curr_cap ) {
            $cap_set = true;
            if ( ! $allcaps[$curr_cap] ) {
                $cap_set = false;
                break;
            }
        }
        if ( $cap_set )
            return $allcaps;

        $attachment = $args[2];

         if ( current_user_can('edit_posts_others') || in_array ( $args[1], array ( $attachment->post_author, get_post( $attachment->post_parent )->post_author ) ) ) {
             $set_cap = true;
         }

        if ( $set_cap ) {
            foreach ( $cap as $curr_cap )
                $allcaps[$curr_cap] = true;
        }

        return $allcaps;
    }
}

function wp_post_attachments( $var = false ) {
    $wppa = WP_PostAttachments::init();
    if ( ! $var )
        return $wppa;

    return ( isset ( $wppa->$var ) ) ? $wppa->$var : false;
}
add_action('init','wp_post_attachments',1);

class WP_PostAttachments_Template
{
    private $assets = WP_POST_ATTACHMENTS_INC_URL;
    private $enqueued = false;

    private $js_vars = array ();
    public $attachments;

    public function __construct() {
        add_filter('the_content', array( &$this, 'maybe_display_attachments') );
        add_filter('comment_text', array( &$this, 'maybe_display_attachments'), 35 ); // After autop
    }

    public function enqueue() {
        if ( $this->enqueued ) return;

        wp_enqueue_media();
        wp_enqueue_style  ( 'wp-post-attachments', $this->assets . 'css/attachments.css', array(), wp_post_attachments('version') );
        wp_enqueue_script ( 'wp-post-attachments', $this->assets . 'js/attachments.js', array ('jquery'), wp_post_attachments('version'), true );
        add_action ( "wp_print_footer_scripts", array ( &$this, 'print_vars' ), 1 );

        $this->enqueued = true;
    }
        public function print_vars() {
            $domain = wp_post_attachments()->get_textdomain();

            $this->js_vars['nonce'] = wp_create_nonce( 'wppa_remove_attachment');
            wp_localize_script( 'wp-post-attachments', 'wpPostAttachmentVars', $this->js_vars);
            wp_localize_script( 'wp-post-attachments', 'wpPostAttachmentl10n', array (
                "attachments"       => __( "Attachments", $domain ),
                "error"             => __( "An error has occurred. Please try again later.", $domain ),
                "add_media"         => __( "Add Media", $domain ),
                "button_text"       => __( "Add attachments or images", $domain ),
                "uploader_title"    => __( "Insert attachments or images", $domain ),
                "uploader_button"   => __( "Select", $domain ),
                "remove"            => __( "Remove", $domain )
            ) );
        }

    protected function get_template ( $template_file ) {
        ob_start();
        $this->locate_template( $template_file, true, false );
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

    protected function locate_template ( $template_file, $load = false, $require_once = true ) {

        $located = '';
        if ( file_exists( STYLESHEETPATH . '/' . $template_file ) ) {
            $located = STYLESHEETPATH . '/' . $template_file;
        } else if ( file_exists( TEMPLATEPATH . '/' . $template_file ) ) {
            $located = TEMPLATEPATH . '/' . $template_file;
        } else if ( file_exists( dirname( __FILE__ ) . '/templates/' . $template_file ) ) {
            $located = dirname( __FILE__ ) . '/templates/' . $template_file;
        }

        if ( '' == $located )
            return false;
        elseif ( ! $load ) {
            return $located;
        }

        load_template( $located, $require_once );
    }

    public function maybe_display_attachments( $content ) {
        if ( ! wp_post_attachments()->post_type_has_attachments( get_post()->post_type ) ) return $content;

        $type = ( 'comment_text' == current_filter() ) ? 'comment' : 'post';
        $method = ( 'comment' == $type ) ? 'get_comment_attachments' : 'get_all_attachments';
        if ( $this->attachments = wp_post_attachments()->$method() ) {
            $this->enqueue();
            $out = $this->get_template ( "single-attachments-$type.php" );
        }
        return $content . $out;

    }

    public function wp_editor( $content, $editor_id, $settings = array() ) {
        $this->enqueue();
        $this->js_vars['wp_editor'] = $editor_id;
        $this->js_vars['show_button'] = ( current_user_can( 'upload_files' ) ) ? true : false;

        if ( apply_filters( 'wp_post_attachments_use_wp_editor', true ) ) {
            $settings['media_buttons'] = false;
            return wp_editor( $content, $editor_id, $settings );
        } else {
            $editor_name = ( isset ( $settings['textarea_name'] ) ) ? $settings['textarea_name'] : $editor_id;
            echo "<textarea id='$editor_id' name='$editor_name'>$content</textarea>";
        }
    }
}