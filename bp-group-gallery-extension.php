<?php
class BP_GroupGallery_Extension extends BP_Group_Extension
{

    var $visibility = 'public'; // 'public' will show your extension to non-group members, 'private' means you have to be a member of the group to view your extension.

    var $enable_create_step = false; // If your extension does not need a creation step, set this to false
    var $enable_nav_item = false; // If your extension does not need a navigation item, set this to false
    var $enable_edit_item = false; // If your extension does not need an edit screen, set this to false

    function __construct() {
        global $bp, $iexpert;

        $this->name = __( 'Gallery', 'bp-group-gallery' );
        $this->slug = bp_group_gallery('slug');

        $this->nav_item_position = 41;

        $this->enable_nav_item = true;
    }

    function display() {
        wp_enqueue_style( 'bp-group-gallery', BP_GROUP_GALLERY_INC_URL . 'css/bp-group-gallery.css', array(), bp_group_gallery( 'version' ) );

        ?>
        <h2><?php _e( 'Gallery', 'bp-group-gallery' ) ?></h2>
        <div class='bp-group-gallery-wrap'>
            <?php echo bp_group_gallery('gallery'); ?>
        </div>
        <script>jQuery(".gallery-icon a").addClass("thickbox").attr( "rel","bp_group_gallery" );</script>
        <?php

        if ( bp_current_user_can( 'bp_moderate' ) || bp_current_user_can( 'add_to_group_gallery' ) ) {
            $this->_enqueue_scripts();
        }
    }

    private function _enqueue_scripts() {
            add_filter( 'media_view_settings', array ( $this, 'media_view_settings' ) );
            wp_enqueue_media();

            wp_enqueue_script( 'bp-group-gallery', BP_GROUP_GALLERY_INC_URL . 'js/bp-group-gallery.js', array ( 'jquery' ), bp_group_gallery( 'version' ), true );
            wp_localize_script( 'bp-group-gallery', 'bpGroupGalleryL10n', array (
                "add_button_text"   => __( "Add Images", 'bp-group-gallery' ),
                "add_media"         => __( "Click here to add images to gallery", 'bp-group-gallery' ),
                "edit_button_text"  => __( "Edit Gallery", 'bp-group-gallery' ),
                "edit_media"        => __( "Click here to modify the gallery order or remove images", 'bp-group-gallery' ),

                "uploader_title"    => __( "Add images to gallery", 'bp-group-gallery' ),
                "uploader_button"   => __( "Select", 'bp-group-gallery' ),

                "ajax_error"        => __( "An error has occurred saving the gallery.")
            ) );
    }

    public function media_view_settings($settings ) {
        $group_id = bp_get_current_group_id();
        $current_gallery = bp_group_gallery('group_gallery_all_ids');

        $settings['bp_group_gallery'] = array(
            'nonce'             => wp_create_nonce( 'bp-edit-gallery-' . $group_id ),
            'group_id'          => $group_id,
            'shortcode'         => bp_group_gallery()->shortcode( $current_gallery ),
            'can_edit'          => bp_current_user_can( 'bp_moderate' ) || bp_current_user_can( 'update_group_gallery' ),
            'can_add'           => bp_current_user_can( 'bp_moderate' ) || bp_current_user_can( 'add_to_group_gallery' )
        );

        return $settings;
    }

}
bp_register_group_extension( 'BP_GroupGallery_Extension' );