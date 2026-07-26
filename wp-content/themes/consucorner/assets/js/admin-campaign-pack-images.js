/* Admin: Campaign bundle pack banner image picker */
(function ($) {
  "use strict";

  $(function () {
    if (typeof wp === "undefined" || typeof wp.media === "undefined") {
      return;
    }

    $(document).on("click", ".cc-campaign-pack-upload", function (e) {
      e.preventDefault();

      var $btn = $(this);
      var inputId = $btn.data("input");
      var previewId = $btn.data("preview");
      var removeId = $btn.data("remove");

      var frame = wp.media({
        title: "Select image",
        button: { text: "Use this image" },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        var attachment = frame.state().get("selection").first().toJSON();
        var previewUrl =
          attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;

        $("#" + inputId).val(attachment.id);
        $("#" + previewId).attr("src", previewUrl).show();
        if (removeId) {
          $("#" + removeId).show();
        }
      });

      frame.open();
    });

    $(document).on("click", ".cc-campaign-pack-remove", function (e) {
      e.preventDefault();

      var $btn = $(this);
      var inputId = $btn.data("input");
      var previewId = $btn.data("preview");

      $("#" + inputId).val("");
      $("#" + previewId).attr("src", "").hide();
      $btn.hide();
    });
  });
})(jQuery);
