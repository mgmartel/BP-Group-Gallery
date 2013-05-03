<div class='post-attachments-container display' data-post-id="<?php the_ID() ?>">
    <h3><?php _e("Attachments to this post",'wp-post-attachments'); ?></h3>
    <ul id="file-list">
    <?php foreach ( wp_post_attachments('template')->attachments as $attachment ) : ?>
            <?php $url = wp_get_attachment_url( $attachment->ID ); ?>
            <?php $attachment->filename = substr ( $url, strrpos( $url, "/" ) + 1 ); ?>

            <?php include ( dirname( __FILE__ ) . '/_single-attachment.php'); ?>
    <?php endforeach; ?>
    </ul>
</div>