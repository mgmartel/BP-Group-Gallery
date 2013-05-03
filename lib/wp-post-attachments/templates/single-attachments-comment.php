<div class='post-attachments-container display comment' data-post-id="<?php the_ID() ?>" data-comment-id="<?php comment_ID() ?>">
    <h4><?php _e ('Added in this comment', wp_post_attachments()->get_textdomain() ); ?></h4>
    <ul id="file-list">
<?php
foreach ( wp_post_attachments('template')->attachments as $attachment ) {
    $url = wp_get_attachment_url( $attachment->ID );
    include ( dirname( __FILE__ ) . '/_single-attachment.php');
}
?>
    </ul>
</div>