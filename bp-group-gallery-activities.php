<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

class BP_Group_Gallery_Activities
{
    public function __construct() {
        add_action( 'bp_group_gallery_images_added', array ( &$this, 'record_activity_added' ), 10, 2 );

        add_action('bp_activity_entry_content', array ( &$this, 'maybe_display_meta' ) );
    }

    public function record_activity_added( $ids, $group_id ) {
        $group = groups_get_group ( array ( "group_id" => $group_id ) );
        $images_string = count ( $ids ) > 1 ? count ( $ids ) . " " . __("images", 'bp-group-gallery') : __("an image", 'bp-group-gallery');
        $gallery_link = "<a href='". bp_get_group_permalink( $group ) . bp_group_gallery('slug') . "'>$images_string</a>";

        $activity_id = groups_record_activity( array(
            'action'            => sprintf ( __( '%s added %s to the group %s', 'bp-group-gallery' ),
                                    bp_core_get_userlink( bp_loggedin_user_id() ),
                                    $gallery_link,
                                    '<a href="' . bp_get_group_permalink( $group ) . '">' . esc_attr( $group->name ) . '</a>' ),
            'type'              => 'gallery_added',
            'item_id'           => $group_id,
        ) );
        $this->save_meta( $activity_id, $ids );
        do_action ( 'bp_group_gallery_record_activity_added', $activity_id, $group_id );
    }

    public function save_meta( $activity_id, $ids ) {
        return bp_activity_update_meta( $activity_id, 'bp-group-gallery-images', $ids );
    }

    public function get_meta( $activity_id ) {
        return bp_activity_get_meta( $activity_id, 'bp-group-gallery-images' );
    }

    public function maybe_display_meta() {
        $activity_id = bp_get_activity_id();
        if ( $meta = $this->get_meta( $activity_id ) ) {
            $this->_display_meta( $meta );
        }
    }

        private function _display_meta( $images ) {
            $rel = md5(microtime() . rand());
            shuffle( $images );
            ?>
            <div class="bp-group-gallery activity-images">
                <?php $i = 0; foreach ( $images as $img ) : ?>
                    <a href="<?php echo current ( wp_get_attachment_image_src( $img, 'full' ) ) ?>" class="thickbox" rel="<?php echo $rel; ?>">
                        <img src="<?php echo  wp_get_attachment_thumb_url( $img ) ?>" />
                    </a>
                <?php if ( $i === 4 ) break; $i++; endforeach; ?>
            </div>
            <?php
        }
}