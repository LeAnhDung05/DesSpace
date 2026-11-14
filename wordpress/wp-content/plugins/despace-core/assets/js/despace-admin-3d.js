/**
 * DeSpace 3D Admin Media Picker
 */
(function($) {
  'use strict';

  $(document).ready(function() {
    var glbFrame;
    var posterFrame;

    // GLB File Picker
    $('#despace_select_glb_btn').on('click', function(e) {
      e.preventDefault();

      if (glbFrame) {
        glbFrame.open();
        return;
      }

      glbFrame = wp.media({
        title: 'Chọn hoặc tải lên mô hình 3D (.glb)',
        button: { text: 'Sử dụng mô hình này' },
        multiple: false
      });

      glbFrame.on('select', function() {
        var attachment = glbFrame.state().get('selection').first().toJSON();
        $('#despace_glb_attachment_id').val(attachment.id);
        $('#despace_glb_preview').text(attachment.url).show();
        $('#despace_remove_glb_btn').show();
        $('#despace_select_glb_btn').text('Thay đổi tệp GLB');
      });

      glbFrame.open();
    });

    $('#despace_remove_glb_btn').on('click', function(e) {
      e.preventDefault();
      $('#despace_glb_attachment_id').val('');
      $('#despace_glb_preview').text('').hide();
      $(this).hide();
      $('#despace_select_glb_btn').text('Chọn tệp GLB từ Media Library');
    });

    // Poster Picker
    $('#despace_select_poster_btn').on('click', function(e) {
      e.preventDefault();

      if (posterFrame) {
        posterFrame.open();
        return;
      }

      posterFrame = wp.media({
        title: 'Chọn ảnh Poster 3D',
        button: { text: 'Sử dụng ảnh này' },
        library: { type: 'image' },
        multiple: false
      });

      posterFrame.on('select', function() {
        var attachment = posterFrame.state().get('selection').first().toJSON();
        var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
        $('#despace_poster_attachment_id').val(attachment.id);
        $('#despace_poster_preview').html('<img src="' + url + '" style="max-height:100px;border-radius:4px;border:1px solid #ddd;"/>').show();
        $('#despace_remove_poster_btn').show();
        $('#despace_select_poster_btn').text('Thay ảnh Poster');
      });

      posterFrame.open();
    });

    $('#despace_remove_poster_btn').on('click', function(e) {
      e.preventDefault();
      $('#despace_poster_attachment_id').val('');
      $('#despace_poster_preview').html('').hide();
      $(this).hide();
      $('#despace_select_poster_btn').text('Chọn ảnh Poster từ Media Library');
    });
  });
})(jQuery);
