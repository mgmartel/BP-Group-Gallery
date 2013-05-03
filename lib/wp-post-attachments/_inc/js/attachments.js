jQuery(document).ready(function($) {
  // First check if there's a display of attachments
  if ( $(".post-attachments-container.display").get(0) ) {
    $(".post-attachments-container.display .remove").click(function(e){
      e.preventDefault();
      $(this).addClass("loading");

      var $link = $(this),
          $li = $link.parents("li").first(),
          attachment_id = $li.data("attachment-id"),
          $container = $li.parents(".post-attachments-container").first(),
          post_id = $container.data("post-id"),
          comment_id = $container.data("comment-id"),
          $liAll = $(".attachment-" + attachment_id);

      $.post(ajaxurl,{
        _ajax_nonce: wpPostAttachmentVars.nonce,
        action: 'post_attachments_remove',
        post_id: post_id,
        attachment_id: attachment_id,
        comment_id: comment_id
      },function(response){
        if ( response === '-1') {
          alert(wpPostAttachmentl10n.error);
          $link.removeClass("loading");
        } else {
          $liAll.slideUp('fast',function(){
            $(this).remove();
            $("div.post-attachments-container.display").each(function() {
              if ( ! $(this).find("ul#file-list li").get(0) )
                $(this).slideUp("fast", function() { $(this).remove(); });
            });

          });
        }
      });
    });
  }

  /**
   * WP EDITOR
   */
  if ( typeof wp === 'undefined' || ! wpPostAttachmentVars.show_button ) return;

  var $container, $file_list, editor_id = wpPostAttachmentVars.wp_editor;

  var media_button = '<div id="wp-' + editor_id + '-editor-tools" class="wp-editor-tools post-attachment hide-if-no-js"><div id="wp-' + editor_id + '-media-buttons" class="wp-media-buttons"><a href="#" class="button add-post-attachments add_media" data-editor="' + editor_id + '" title="' + wpPostAttachmentl10n.add_media + '"><span class="wp-media-buttons-icon"></span> / <span class="attachment-icon"></span> ' + wpPostAttachmentl10n.button_text + '</a></div></div>';
  $(".wp-editor-wrap").append(media_button).parents('form').addClass("post-attachments-active");

  // Create the media frame.
  var file_frame = wp.media.frames.file_frame = wp.media({
    //title: jQuery( this ).data( 'uploader_title' ),
    title: wpPostAttachmentl10n.uploader_title,
    button: {
      text: wpPostAttachmentl10n.uploader_button,
    },
    multiple: true
  });
  file_frame.files = {
    currFilesIds: [],
    /**
     * Usage add(id,file) / add(file) / add (id,file.attributes) / add(file.attributes)
     *
     * @param mixed id
     * @param {type} file
     * @returns {undefined}
     */
    add: function(id,file) {
      var attributes;

      // Magic js :)
      if (!file) {
        file = id;
        id = file.id;
      }
      if ( file.attributes ) {
        attributes = file.attributes;
      } else {
        attributes = file;
      }

      // Does the file exist already?
      var idx = this.currFilesIds.indexOf(id);
      if ( idx !== -1 )
        this.remove(idx); // @todo update..

      this.currFilesIds.push(id);

      // If the list doesn't exist, create one
      if ( ! $("#post-attachments-upload").get(0) ) this.createMarkup();

      // Generate the entry
      var $fileHtml = $("<li>", { id: id } );

      var $fileIcon = $("<img>", { class: 'icon', src: attributes.icon });
      $fileHtml.append($fileIcon);
      //var fileIcon = "<img class='icon' src='" + attributes.icon + "'>";

      var fileMeta = '';
      if ( attributes.title ) {
        fileMeta += "<h4>" + attributes.title + " <span><a href='#' class='remove'>" + wpPostAttachmentl10n.remove + "</a></span></h4>";
        fileMeta += "<em>" + attributes.filename + "</em>";
      } else {
        fileMeta += "<h4>" + attributes.filename + " <span><a href='#' class='remove'>" + wpPostAttachmentl10n.remove + "</a></span></h4>";
      }
      if ( attributes.description )
        fileMeta += "<p>" + attributes.description + "</p>";

      $fileHtml.append($(
        "<div>", {
        class: 'meta',
        html: fileMeta,
      }));

      $file_list.append($fileHtml);
      $("li#" + id + " .remove").one("click", function(e) { e.preventDefault(); file_frame.files.remove(id); });
    },
    get: function() {
      return JSON.stringify(this.currFilesIds);
    },
    remove: function(id) {
      var idx = this.currFilesIds.indexOf(id);
      if ( idx === -1 )
        return false;

      this.currFilesIds.splice(idx,1);
      $file_list.find("li#" + id).remove();

      if ( this.currFilesIds.length === 0 ) this.removeMarkup();
    },
    createMarkup: function() {
      var html = "<div id='post-attachments-upload' class='post-attachments-container'>" +
              "<h3>" + wpPostAttachmentl10n.attachments + "</h3>" +
              "<ul id='file-list'></ul>" +
          "</div>";
      $("div.wp-editor-wrap").after(html);

      $container = $("#post-attachments-upload");
      $file_list = $container.find("#file-list");

      var that = this;
      $("form").one("submit.post-attachment",function(e) {
        $(this).append( $("<input>", { type: 'hidden', value: that.get(), name: 'attachments' }));
      });
    },
    removeMarkup: function() {
      $container.remove();
      $("form").off("submit.post-attachment");
    }
  };

  // When an image is selected, run a callback.
  file_frame.on( 'select', function(selection) {

    /**
     * From media-editor.js
     */
    var state = file_frame.state();
    selection = selection || state.get('selection');

    if ( ! selection )
        return;

    /**
     * First filter out files, and save them in file_frame.files object
     */
    var files = [];
    selection.forEach( function(att) {
      if ( att.isEnqueued ) files.push(att);
      else if ( att.attributes.type !== 'image' ) {
        if ( att.id ) {
          file_frame.files.add(att);
        } else {
          att.isEnqueued = true;
          att.on("change:uploading",function()  {
            if ( att.id ) {
              file_frame.files.add(att);
              att.off("change:uploading");
            }
          });
        }
        files.push(att);
      }
    });

    selection.remove(files);

    $.when.apply( $, selection.map( function( attachment ) {
        // Force link to be to image, not to attachment page
        attachment.attributes.link = attachment.attributes.url;
        var display = state.display( attachment ).toJSON();
        return wp.media.editor.send.attachment( display, attachment.toJSON() );
    }, wp.media.editor ) ).done( function() {
        wp.media.editor.insert( _.toArray( arguments ).join("\n\n") );
    });

  });

  $(".add-post-attachments").click( function() {
    file_frame.open();
  });
});