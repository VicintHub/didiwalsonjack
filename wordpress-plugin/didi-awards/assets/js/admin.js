/* Didi Awards dashboard behaviour. */
(function ($) {
  'use strict';
  var C = window.DidiAwards || {};

  /* ---------- dashboard: filters, featured star ---------- */
  var $grid = $('#daGrid');
  if ($grid.length) {
    var filter = 'all';
    function apply() {
      var q = ($('#daSearch').val() || '').toLowerCase().trim();
      var y = $('#daYear').val();
      var shown = 0;
      $grid.children('.da-card').each(function () {
        var $c = $(this), ok = true;
        if (q && String($c.data('text')).indexOf(q) === -1) ok = false;
        if (y && String($c.data('year')) !== String(y)) ok = false;
        if (filter === 'featured' && !Number($c.data('featured'))) ok = false;
        if (filter === 'noimage' && !Number($c.data('noimage'))) ok = false;
        if (filter === 'draft' && !Number($c.data('draft'))) ok = false;
        $c.prop('hidden', !ok); if (ok) shown++;
      });
      $('#daCount').text(shown + ' of ' + $grid.children('.da-card').length + ' awards');
      $('#daEmpty').prop('hidden', shown !== 0);
    }
    $('#daSearch').on('input', apply);
    $('#daYear').on('change', apply);
    $('.da-chip').on('click', function () {
      filter = $(this).data('filter'); $('.da-chip').removeClass('is-on'); $(this).addClass('is-on'); apply();
    });
    apply();

    $grid.on('click', '.da-star', function () {
      var $b = $(this).prop('disabled', true), $card = $b.closest('.da-card');
      $.post(C.ajax, { action: 'didi_award_toggle', nonce: C.nonce, id: $b.data('id') }).done(function (r) {
        if (r && r.success) {
          $b.toggleClass('is-on', r.data.featured).attr('aria-pressed', r.data.featured ? 'true' : 'false');
          $card.data('featured', r.data.featured ? 1 : 0);
          $('#daStatFeat').text(r.data.count);
          $('#daMeter').css('width', Math.min(100, r.data.count * 100 / C.max) + '%');
          apply();
        } else { window.alert((r && r.data && r.data.message) || 'Could not update.'); }
      }).fail(function () { window.alert('Could not update. Please try again.'); }).always(function () { $b.prop('disabled', false); });
    });
  }

  $(document).on('click', '[data-confirm]', function (e) { if (!window.confirm($(this).data('confirm'))) e.preventDefault(); });

  /* ---------- editor ---------- */
  var $form = $('#daForm');
  if (!$form.length) return;

  var dirty = false;
  $form.on('input change', function () { dirty = true; });
  $form.on('submit', function () { dirty = false; });
  $(window).on('beforeunload', function () { if (dirty) return 'You have unsaved changes.'; });

  function counter() {
    var n = $('#daSummary').val().length, $c = $('#daSummaryCount');
    $c.text(n + ' / 200').toggleClass('is-full', n >= 200);
  }
  $('#daSummary').on('input', counter); counter();

  /* featured image */
  var thumbFrame;
  function setThumb(id, url) {
    $('#daThumbId').val(id || 0); dirty = true;
    $('#daThumbImg').attr('src', url || C.placeholder);
    $('#daThumbBox').toggleClass('is-empty', !id);
    $('#daThumbFlag').prop('hidden', !!id);
    $('#daThumbRemove').prop('hidden', !id);
    $('#daThumbPickTxt').text(id ? 'Replace' : 'Choose image');
  }
  $('#daThumbPick').on('click', function () {
    if (!thumbFrame) {
      thumbFrame = wp.media({ title: 'Featured image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
      thumbFrame.on('select', function () {
        var a = thumbFrame.state().get('selection').first().toJSON();
        setThumb(a.id, (a.sizes && a.sizes.large ? a.sizes.large.url : a.url));
      });
    }
    thumbFrame.open();
  });
  $('#daThumbRemove').on('click', function () { setThumb(0, ''); });

  $('#daImportBtn').on('click', function () {
    var $b = $(this), url = $.trim($('#daImportUrl').val()), $m = $('#daImportMsg');
    if (!url) { $m.text('Paste an image address first.'); return; }
    $b.prop('disabled', true).text('Importing…'); $m.text('');
    $.post(C.ajax, { action: 'didi_award_import_image', nonce: C.nonce, id: $b.data('id'), url: url }).done(function (r) {
      if (r && r.success) { setThumb(r.data.id, r.data.url); $m.text('Imported and set as the featured image.'); dirty = false; }
      else { $m.text((r && r.data && r.data.message) || 'The image could not be imported.'); }
    }).fail(function () { $m.text('The image could not be imported.'); }).always(function () { $b.prop('disabled', false).text('Import and use'); });
  });

  /* gallery */
  var $gal = $('#daGallery'), galFrame;
  function syncGallery() { $('#daGalleryField').val($gal.children().map(function () { return $(this).data('id'); }).get().join(',')); dirty = true; }
  $gal.sortable({ update: syncGallery });
  $gal.on('click', '.da-x', function () { $(this).closest('li').remove(); syncGallery(); });
  $('#daGalleryAdd').on('click', function () {
    if (!galFrame) {
      galFrame = wp.media({ title: 'Add images', button: { text: 'Add to gallery' }, library: { type: 'image' }, multiple: 'add' });
      galFrame.on('select', function () {
        galFrame.state().get('selection').each(function (m) {
          var a = m.toJSON();
          if ($gal.children('[data-id="' + a.id + '"]').length) return;
          var u = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
          $gal.append($('<li>').attr('data-id', a.id).append($('<img alt="">').attr('src', u)).append('<button type="button" class="da-x" aria-label="Remove image">&times;</button>'));
        });
        syncGallery();
      });
    }
    galFrame.open();
  });
})(jQuery);
