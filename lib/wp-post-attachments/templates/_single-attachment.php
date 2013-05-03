<li class='attachment-<?php echo $attachment->ID ?>' data-attachment-id="<?php echo $attachment->ID ?>">
    <?php echo wp_get_attachment_link($attachment->ID,46,false,true); ?>
    <div class="meta">
        <h4>
            <a href='<?php echo $url; ?>'>
                <?php echo ( ! empty ( $attachment->post_title ) ) ? $attachment->post_title : $attachment->filename ?>
            </a>
            <?php if ( current_user_can( 'remove_attachment', $attachment ) ) : ?>
                <span><a href="#" class="remove"><?php _e('Remove','bp-group-questions'); ?></a></span>
            <?php endif; ?>
        </h4>
        <p><?php echo $attachment->post_content ?></p>
        <em><?php wp_post_attachments()->attachment_file_link($attachment) ?></em>
    </div>
</li>