<?php
/*
  Plugin Name: BP Group Gallery
  Plugin URI: http://trenvo.com
  Description: Adds a gallery to your groups
  Version: 0.1
  Author: Mike Martel
  Author URI: http://trenvo.com
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

if (defined('BP_GROUP_GALLERY_VERSION')) return;

/**
 * PATHs and URLs
 *
 * @since 0.1
 */
define('BP_GROUP_GALLERY_DIR', plugin_dir_path(__FILE__));
define('BP_GROUP_GALLERY_URL', plugin_dir_url(__FILE__));
define('BP_GROUP_GALLERY_INC_URL', BP_GROUP_GALLERY_URL . '_inc/');

class BP_Group_Gallery
{
    public $version = "0.1";

//    public $slug; // = 'gallery';
    public $per_page = 20;
    public $total = 0;

    public $activities;

    /**
     * Creates an instance of the BP_Group_Gallery class
     *
     * @return BP_Group_Gallery object
     * @since 0.1
     * @static
    */
    public static function &init() {
        static $instance = false;

        if (!$instance) {
            load_plugin_textdomain('bp-group-gallery', false, basename(BP_GROUP_GALLERY_DIR) . '/languages/');
            $instance = new BP_Group_Gallery;
        }

        return $instance;
    }

    /**
     * Constructor
     *
     * @since 0.1
     */
    public function __construct() {

        $this->includes();
        $this->activities = new BP_Group_Gallery_Activities();

        add_action( 'wp_ajax_bp-group-gallery-update', array ( $this,'wp_ajax_gallery_update' ) );
        add_action( 'wp_ajax_bp-group-gallery-append', array ( $this,'wp_ajax_gallery_append' ) );

        add_filter( 'bp_current_user_can', array ( &$this, 'current_user_capabilities' ), 10, 2 );
    }

    public function __isset( $var ) {
        if ( method_exists( $this, 'get_' . $var ) ) {
            $m = 'get_' . $var;
            $this->$var = $this->$m();
            return true;
        }
    }

    public function includes() {
        require_once BP_GROUP_GALLERY_DIR . 'bp-group-gallery-extension.php';
        require_once BP_GROUP_GALLERY_DIR . 'bp-group-gallery-activities.php';
    }

    public function get_slug() {
        return __('gallery', 'bp-group-gallery');
    }

    /** CAPABILITIES ****************************/
    public function current_user_capabilities( $retval, $cap ) {
        if ( $retval  ) return true;

        if ( 'update_group_gallery' == $cap ) {
            $retval = $this->current_user_can( 'edit' );
        } else if ( 'add_to_group_gallery' == $cap ) {
            $retval = $this->current_user_can( 'append' );
        }

        return $retval;
    }

    public function current_user_can( $action, $group_id = 0 ) {
        if ( ! $group_id ) $group_id = bp_get_current_group_id();

        if ( 'update' == $action )
            return groups_is_user_mod( bp_loggedin_user_id(), $group_id );
        if ( 'append' == $action )
            return groups_is_user_member( bp_loggedin_user_id(), $group_id );
    }

    /** AJAX ************************************/
    public function wp_ajax_gallery_update() {
        $this->_wp_ajax_gallery_action();
    }

    public function wp_ajax_gallery_append() {
        $this->_wp_ajax_gallery_action('append');
    }

        private function _wp_ajax_gallery_action( $action = 'update' ) {
            global $bp;

            $group_id = $_POST['group_id'];

            check_ajax_referer( 'bp-edit-gallery-' . $group_id );

            if ( ! isset ( $_POST['ids'] )
                || ( ! $this->current_user_can( $action, $group_id ) && ! bp_current_user_can ('bp_moderate') )
            ) {
                echo json_encode ( array (
                    "error"
                ) );
                exit();
            }

            if ( class_exists ('BP_Groups_Hierarchy') )
                $bp->groups->current_group = new BP_Groups_Hierarchy ( $group_id );
            else
                $bp->groups->current_group = groups_get_group ( array ( "group_id" => $group_id ) );

            $ids = (array) $_POST['ids'];
            if ( 'append' == $action ) {
                $diff = $this->_add_to_gallery( $ids, $group_id );
                if ( ! empty ( $diff ) )
                    do_action( 'bp_group_gallery_images_added', $diff, $group_id );
            } else {
                groups_update_groupmeta ( $group_id, '_gallery_posts', $ids );
            }

            echo json_encode ( array (
                "success" => true,
                "html" => $this->get_gallery( $group_id ),
                "shortcode"  => $this->shortcode ( $this->get_group_gallery_all_ids( $group_id, 0 ) )
            ) );
            exit();
        }

    /** GETTERS AND SETTERS *********************/
    public function get_group_gallery_ids( $group_id = 0, $per_page = false ) {
        if ( $per_page === false ) $per_page = $this->per_page;

        if ( ! $group_id ) $group_id = bp_get_current_group_id ();

        $posts = array_filter ( (array) groups_get_groupmeta( $group_id, '_gallery_posts' ) );

        $this->total = count ( $posts );

        if ( $per_page && $this->total > $per_page ) {
            if ( !$current_page = get_query_var('paged') )
              $current_page = 1;

            return array_slice($posts, ( ( $current_page - 1 ) * $per_page ), $per_page);
        }

        return $posts;
    }

    public function get_group_gallery_all_ids ( $group_id = 0 ) {
        return $this->get_group_gallery_ids( $group_id, 0 );
    }

    private function _add_to_gallery( $attachment_ids, $group_id ) {
        if ( is_numeric( $attachment_ids ) ) $attachment_ids = array ( $attachment_ids );

        $curr = (array) groups_get_groupmeta( $group_id, '_gallery_posts' );
        $new = array_merge ( $attachment_ids, $curr );
        groups_update_groupmeta($group_id, '_gallery_posts', array_unique ( $new ) );
        return array_diff ( $new, $curr );
    }

    private function _remove_from_gallery( int $attachment_id, int $group_id) {
        $curr = (array) groups_get_groupmeta( $group_id, '_gallery_posts' );
        if ( ( $key = array_search ( $attachment_id, $curr ) ) !== false) {
            unset( $curr[$key] );
        }
        groups_update_groupmeta($group_id, '_gallery_posts', $curr );
    }

    /** TEMPLATE FUNCTIONS **********************/
    public function get_gallery( $group_id = 0) {
        $gallery_ids = $this->get_group_gallery_ids( $group_id );

        if ( ! empty ( $gallery_ids ) ) {

            // Unless explicitly set (by a gallery plugin), use our own styles
            if ( ! has_filter( 'use_default_gallery_style' ) )
                add_filter( 'use_default_gallery_style', '__return_false' );

            $gallery_shortcode = do_shortcode('[gallery ids=' . implode ( ',', $gallery_ids ).' columns=5 link="file"]');
            remove_filter( 'use_default_gallery_style', '__return_false' );

            $html = str_replace( ' style="clear: both"', "", $gallery_shortcode );
            $pagination = $this->pagination();
            return $pagination . $html . $pagination;

        } else {

            return "<p>" . __('No images have been uploaded for this group yet.','bp-group-gallery') . "</p>";

        }
    }

    public function pagination() {
        $found_posts = $this->total;
        $per_page = $this->per_page;

        if ( !$current_page = get_query_var('paged') )
              $current_page = 1;
         // Structure of “format” depends on whether we’re using pretty permalinks
        $permalinks = get_option('permalink_structure');
        $format = empty( $permalinks ) ? '&page=%#%' : 'page/%#%/';

        $links = paginate_links( array(
                    'base'      => $this->get_custom_pagenum_link(),
                    'format'    => $format,
                    'total'     => ceil ( $found_posts / $per_page ),
                    'show_all'  => true,
                    'current'   => $current_page,
                    'prev_text' => _x( '&larr;', 'Pagination previous text', 'bp-group-questions' ),
                    'next_text' => _x( '&rarr;', 'Pagination next text', 'bp-group-questions' ),
                    'mid_size'  => 1
                ) );

        $start_num = intval( ( $current_page - 1 ) * $per_page ) + 1;
        $from_num  = bp_core_number_format( $start_num );
        $to_num    = bp_core_number_format( ( $start_num + ( $per_page - 1 ) > $found_posts ) ? $found_posts : $start_num + ( $per_page - 1 ) );
        $total     = bp_core_number_format( $found_posts );

        $pagination = '<div class="pagination no-ajax">
            <div class="pag-count">' . sprintf( __( 'Viewing item %1$s to %2$s (of %3$s items)', 'buddypress' ), $from_num, $to_num, $total ) . '</div>
            <div class="pagination-links">' . $links . '</div>
            </div>';
        return $pagination;
    }

    public function get_link( $relative = false, $group_id = 0 ) {
        $uri = bp_get_groups_slug() . '/' . bp_get_current_group_slug() . '/' . $this->slug . '/';
        return ( $relative ) ? $uri : home_url( $uri );
    }

    private function get_custom_pagenum_link($pager = "%#%", $escape = true, $url = false ) {
        global $wp_rewrite, $bp;

        $pagenum = 999999;
        if ( ! $url ) $url = $this->get_link(true);

        $request = remove_query_arg( 'paged', $url );

        $home_root = parse_url(home_url());
        $home_root = ( isset($home_root['path']) ) ? $home_root['path'] : '';
        $home_root = preg_quote( $home_root, '|' );

        $request = preg_replace('|^'. $home_root . '|i', '', $request);
        $request = preg_replace('|^/+|', '', $request);

        if ( !$wp_rewrite->using_permalinks() || ( is_admin() && ! defined ('DOING_AJAX') || ! DOING_AJAX ) ) {
            $base = trailingslashit( get_bloginfo( 'url' ) );

            if ( $pagenum > 1 ) {
                $result = add_query_arg( 'paged', $pagenum, $base . $request );
            } else {
                $result = $base . $request;
            }
        } else {
            $qs_regex = '|\?.*?$|';
            preg_match( $qs_regex, $request, $qs_match );

            if ( !empty( $qs_match[0] ) ) {
                $query_string = $qs_match[0];
                $request = preg_replace( $qs_regex, '', $request );
            } else {
                $query_string = '';
            }

            $request = preg_replace( "|$wp_rewrite->pagination_base/\d+/?$|", '', $request);
            $request = preg_replace( '|^index\.php|i', '', $request);
            $request = ltrim($request, '/');

            $base = trailingslashit( get_bloginfo( 'url' ) );

            if ( $wp_rewrite->using_index_permalinks() && ( $pagenum > 1 || '' != $request ) )
                $base .= 'index.php/';

            if ( $pagenum > 1 ) {
                $request = ( ( !empty( $request ) ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( $wp_rewrite->pagination_base . "/" . $pagenum, 'paged' );
            }

            $result = $base . $request . $query_string;
        }

        $result = str_replace( 999999, $pager, $result );
        $result = apply_filters('get_pagenum_link', $result);

        if ( $escape )
            return esc_url( $result );
        else
            return esc_url_raw( $result );
    }

    public function shortcode( $ids ) {
        if ( ! empty ( $ids ) )
            return '[gallery ids = "' . implode(',',$ids) . '"]';
		else return '';
    }

}
add_action('bp_include', array('BP_Group_Gallery', 'init'));

function bp_group_gallery( $var = false ) {
    $bpma = BP_Group_Gallery::init();
    if ( ! $var )
        return $bpma;

    if ( isset ( $bpma->$var ) ) return $bpma->$var;
    else if ( method_exists ( $bpma, $var ) ) return $bpma->$var();
    return false;
}