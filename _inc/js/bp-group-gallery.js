jQuery(document).ready(function($) {
  // Sanity check
  if ( typeof wp === 'undefined' ) return;

  var settings = wp.media.view.settings.bp_group_gallery;

  // Setup buttons
  var media_button_edit = settings.can_edit ? '<a href="#" class="button edit-bp-group-gallery" title="' + bpGroupGalleryL10n.edit_media + '"><span class="wp-media-buttons-icon"></span> ' + bpGroupGalleryL10n.edit_button_text + '</a>' : '',
      media_button_add  = settings.can_add  ? '<a href="#" class="button add-bp-group-gallery" title="' + bpGroupGalleryL10n.add_media + '"><span class="wp-media-buttons-icon"></span> ' + bpGroupGalleryL10n.add_button_text + '</a>' : '',
      media_buttons = '<div id="bp-group-gallery-media-buttons" class="wp-media-buttons">' + media_button_edit + media_button_add + '</div>';
  $(".bp-group-gallery-wrap").prev().append(media_buttons);

  /**** EDIT GALLERY */
  var galleryEdit = {
    currentGallery: function() {
      var shortcode = wp.shortcode.next( 'gallery', settings.shortcode ),
          attachments, selection;

      // Bail if we didn't match the shortcode or all of the content.
      if ( ! shortcode )
          return;

      // Ignore the rest of the match object.
      shortcode = shortcode.shortcode;

      attachments = wp.media.gallery.attachments( shortcode );
      selection = new wp.media.model.Selection( attachments.models, {
          props:    attachments.props.toJSON(),
          multiple: true
      });

      selection.gallery = attachments.gallery;

      // Fetch the query's attachments, and then break ties from the
      // query to allow for sorting.
      selection.more().done( function() {
          // Break ties with the query.
          selection.props.set({ query: false });
          selection.unmirror();
          selection.props.unset('orderby');
      });

      return selection;
    },
    init: function() {
      this.reset();
      $(".edit-bp-group-gallery").click( function() {
        wp.media.frames.bp_group_gallery_edit.open();
      });
    },
    reset: function() {
      // Create the media frame.
      var editFrame = wp.media.frames.bp_group_gallery_edit = wp.media({
        multiple: true,
//        state: 'gallery-library',
        state: 'gallery-edit',
        frame: 'post',
        selection: this.currentGallery(),
      });
      editFrame.on('open',function() {
        $(".gallery-settings").remove();
      });

      // Callback for when the gallery is updated
      editFrame.on( 'update', function() {
        var controller = editFrame.states.get('gallery-edit'),
            library = controller.get('library'),
            // Need to get all the attachment ids for gallery
            ids = library.pluck('id');

        /**
         * @todo Check if ids are the same as provided ones
         **/
        if ( ids == '' ) return;

        // send ids to server
        $(".bp-group-gallery-wrap").append( "<div class='loading'></div>" );
        saveGallery(ids,'update');
      });
    }
  };
  galleryEdit.init();

  //**** ADD IMAGE */
  //
  // Create the media frame.
  var addFrame = wp.media.frames.bp_group_gallery_add = wp.media({
    //title: jQuery( this ).data( 'uploader_title' ),
    title: bpGroupGalleryL10n.uploader_title,
    button: {
      text: bpGroupGalleryL10n.uploader_button,
    },
    multiple: true,
    library: {
      type: 'image'
    }
  });

  // When an image is selected, run a callback.
  addFrame.on( 'select', function(selection) {

    /**
     * From media-editor.js
     */
    var state = addFrame.state();
    selection = selection || state.get('selection');

    if ( ! selection )
        return;

    $(".bp-group-gallery-wrap").append( "<div class='loading'></div>" );

    var toInsert = [],
        uploading = 0;
    selection.forEach( function(att) {
      if ( att.attributes.type === 'image' ) {
        if ( att.isEnqueued ) return;
        else if ( att.id ) {
          toInsert.push(att.id);
        } else { // Still uploading
          att.isEnqueued = true;
          uploading++;
          att.on("change:uploading",function()  {
            if ( att.id ) {
              toInsert.push(att.id);
              att.off("change:uploading");
              uploading--;
              if ( uploading < 1 )
                saveGallery(toInsert,'append');
            }
          });
        }
      }
    });
    if ( uploading < 1 )
      saveGallery(toInsert,'append');

  });

  $(".add-bp-group-gallery").click( function() {
    addFrame.open();
  });

  function saveGallery(ids,action) {
    $.post(wp.media.model.settings.ajaxurl,{
      action: 'bp-group-gallery-' + action,
      _ajax_nonce:  settings.nonce,
      group_id:     settings.group_id,
      ids:          ids,
    }, function(response) {
          var data = jQuery.parseJSON(response);
          if ( data.success )
            $(".bp-group-gallery-wrap").html(data.html);
          else
            alert( bpGroupGalleryL10n.ajax_error );

          $(".bp-group-gallery-wrap .loading").remove();
          $(".gallery-icon a").addClass("thickbox").attr( "rel","bp_group_gallery" );
          if ( 'append' == action ) {
            settings.shortcode = data.shortcode;
            galleryEdit.reset();
          }
    });
  }
});