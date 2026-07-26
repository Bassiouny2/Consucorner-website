/* Admin: Attribute term image uploader
   Depends on: jquery, wp-media (loaded via wp_enqueue_media in PHP)
*/
(function ($) {
  'use strict';

  /* Guard: wait until both jQuery and wp.media are truly ready */
  $(function () {
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
      return;
    }

    /* ── Upload button ──────────────────────────────────────────── */
    $(document).on('click', '.cc-attr-upload-btn', function (e) {
      e.preventDefault();

      var $btn     = $(this);
      var inputId  = $btn.data('input');
      var prevId   = $btn.data('preview');
      var removeId = $btn.data('remove');

      /* Create a new media frame each click (avoids stale selection) */
      var frame = wp.media({
        title:    'Select or Upload Image',
        button:   { text: 'Use this image' },
        multiple: false,
        library:  { type: 'image' }
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();

        /* Prefer thumbnail size URL for the preview */
        var previewUrl = (attachment.sizes && attachment.sizes.thumbnail)
          ? attachment.sizes.thumbnail.url
          : attachment.url;

        /* Update hidden input */
        $('#' + inputId).val(attachment.id);

        /* Update preview image */
        $('#' + prevId).attr('src', previewUrl).show();

        /* Show the remove button */
        if (removeId) {
          $('#' + removeId).show();
        } else {
          $btn.siblings('.cc-attr-remove-btn').show();
        }
      });

      frame.open();
    });

    /* ── Remove button ──────────────────────────────────────────── */
    $(document).on('click', '.cc-attr-remove-btn', function (e) {
      e.preventDefault();

      var $btn    = $(this);
      var prevId  = $btn.data('preview');
      var inputId = $btn.data('input');

      $('#' + inputId).val('');
      $('#' + prevId).attr('src', '').hide();
      $btn.hide();
    });
  });

}(jQuery));
