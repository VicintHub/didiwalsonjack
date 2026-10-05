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

  /* featured image: choose, then crop to the site shape */
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
      thumbFrame = wp.media({ title: 'Featured image', button: { text: 'Next: crop' }, library: { type: 'image' }, multiple: false });
      thumbFrame.on('select', function () {
        var a = thumbFrame.state().get('selection').first().toJSON();
        openCrop(a.url, a.id, a.width, a.height);
      });
    }
    thumbFrame.open();
  });
  $('#daThumbRemove').on('click', function () { setThumb(0, ''); });

  $('#daImportBtn').on('click', function () {
    var $b = $(this), url = $.trim($('#daImportUrl').val()), $m = $('#daImportMsg');
    if (!url) { $m.text('Paste an image address first.'); return; }
    $b.prop('disabled', true).text('Importing…'); $m.text('');
    importImage($b.data('id'), url).done(function (d) { $m.text('Imported. Crop it to fit.'); openCrop(d.url, d.id, 0, 0); })
      .fail(function (msg) { $m.text(msg); }).always(function () { $b.prop('disabled', false).text('Import and use'); });
  });
  function importImage(postId, url) {
    var dfd = $.Deferred();
    $.post(C.ajax, { action: 'didi_award_import_image', nonce: C.nonce, id: postId, url: url }).done(function (r) {
      if (r && r.success) { setThumb(r.data.id, r.data.url); dfd.resolve(r.data); }
      else { dfd.reject((r && r.data && r.data.message) || 'The image could not be imported.'); }
    }).fail(function () { dfd.reject('The image could not be imported.'); });
    return dfd.promise();
  }

  /* ---------- crop tool (4:3, output 1600 x 1200) ---------- */
  var OUT_W = 1600, OUT_H = 1200, VIEW_W = 640, VIEW_H = 480;
  var cv = document.getElementById('daCropCanvas'), cx = cv ? cv.getContext('2d') : null;
  var crop = { img: null, id: 0, s: 1, min: 1, x: 0, y: 0, url: '', drag: null };
  function clamp() {
    var w = crop.img.naturalWidth * crop.s, h = crop.img.naturalHeight * crop.s;
    crop.x = Math.min(0, Math.max(VIEW_W - w, crop.x)); crop.y = Math.min(0, Math.max(VIEW_H - h, crop.y));
  }
  function draw() {
    cx.fillStyle = '#E9DFF5'; cx.fillRect(0, 0, VIEW_W, VIEW_H);
    cx.drawImage(crop.img, crop.x, crop.y, crop.img.naturalWidth * crop.s, crop.img.naturalHeight * crop.s);
  }
  function openCrop(url, id, w, h) {
    var $m = $('#daCrop'); crop.id = id; crop.url = url;
    var im = new Image(); im.crossOrigin = 'anonymous';
    im.onload = function () {
      crop.img = im;
      crop.min = Math.max(VIEW_W / im.naturalWidth, VIEW_H / im.naturalHeight);
      crop.s = crop.min;
      crop.x = (VIEW_W - im.naturalWidth * crop.s) / 2; crop.y = (VIEW_H - im.naturalHeight * crop.s) * 0.3;
      clamp(); draw();
      $('#daCropZoom').val(100);
      var small = im.naturalWidth < OUT_W * 0.75 || im.naturalHeight < OUT_H * 0.75;
      $('#daCropInfo').text(im.naturalWidth + ' × ' + im.naturalHeight + ' px');
      $('#daCropWarn').prop('hidden', !small).text(small ? 'This photo is small. It will be enlarged to ' + OUT_W + ' × ' + OUT_H + ' and may look soft. A larger original gives a sharper result.' : '');
      $m.prop('hidden', false); $('#daCropApply').focus();
    };
    im.onerror = function () { window.alert('The image could not be opened for cropping. It was kept as it is.'); setThumb(id, url); };
    im.src = url + (url.indexOf('?') > -1 ? '&' : '?') + 'cb=' + Date.now();
  }
  window.DidiAwards.crop = openCrop;
  if (cv) {
    $(cv).on('pointerdown', function (e) { cv.setPointerCapture(e.originalEvent.pointerId); crop.drag = { x: e.clientX, y: e.clientY, ox: crop.x, oy: crop.y }; });
    $(cv).on('pointermove', function (e) {
      if (!crop.drag) return; var k = VIEW_W / cv.getBoundingClientRect().width;
      crop.x = crop.drag.ox + (e.clientX - crop.drag.x) * k; crop.y = crop.drag.oy + (e.clientY - crop.drag.y) * k; clamp(); draw();
    });
    $(cv).on('pointerup pointercancel', function () { crop.drag = null; });
    $('#daCropZoom').on('input', function () {
      var old = crop.s, cxm = VIEW_W / 2 - crop.x, cym = VIEW_H / 2 - crop.y;
      crop.s = crop.min * (Number(this.value) / 100);
      var r = crop.s / old; crop.x = VIEW_W / 2 - cxm * r; crop.y = VIEW_H / 2 - cym * r; clamp(); draw();
    });
    $('#daCrop').on('keydown', function (e) {
      var step = e.shiftKey ? 30 : 10, used = true;
      if (e.key === 'ArrowLeft') crop.x += step; else if (e.key === 'ArrowRight') crop.x -= step;
      else if (e.key === 'ArrowUp') crop.y += step; else if (e.key === 'ArrowDown') crop.y -= step; else used = false;
      if (used && crop.img && !$(e.target).is('input')) { e.preventDefault(); clamp(); draw(); }
    });
    $('#daCropApply').on('click', function () {
      var $b = $(this).prop('disabled', true).text('Saving…');
      var out = document.createElement('canvas'); out.width = OUT_W; out.height = OUT_H;
      var k = OUT_W / VIEW_W, o = out.getContext('2d');
      o.imageSmoothingQuality = 'high';
      o.drawImage(crop.img, crop.x * k, crop.y * k, crop.img.naturalWidth * crop.s * k, crop.img.naturalHeight * crop.s * k);
      out.toBlob(function (blob) {
        if (!blob) { $b.prop('disabled', false).text('Crop and use'); window.alert('Cropping failed in this browser.'); return; }
        var fd = new FormData(); fd.append('action', 'didi_award_save_crop'); fd.append('nonce', C.nonce);
        fd.append('id', $('input[name=award_id]').val() || 0); fd.append('title', $('#daTitle').val() || 'Award image');
        fd.append('file', blob, 'award-' + Date.now() + '.jpg');
        $.ajax({ url: C.ajax, method: 'POST', data: fd, processData: false, contentType: false }).done(function (r) {
          if (r && r.success) { setThumb(r.data.id, r.data.url); $('#daCrop').prop('hidden', true); }
          else { window.alert((r && r.data && r.data.message) || 'Could not save the cropped image.'); }
        }).fail(function () { window.alert('Could not save the cropped image.'); }).always(function () { $b.prop('disabled', false).text('Crop and use'); });
      }, 'image/jpeg', 0.9);
    });
    $('#daCropSkip').on('click', function () { setThumb(crop.id, crop.url); $('#daCrop').prop('hidden', true); });
  }
  $(document).on('click', '.da-modal [data-close]', function () { $(this).closest('.da-modal').prop('hidden', true); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') $('.da-modal:visible').prop('hidden', true); });
  $('.da-modal').on('click', function (e) { if (e.target === this) $(this).prop('hidden', true); });

  /* ---------- smart import: gather, review, apply ---------- */
  var FIELDS = [
    { k: 'title', label: 'Title', sel: '#daTitle', type: 'text' },
    { k: 'summary', label: 'Card summary', sel: '#daSummary', type: 'area' },
    { k: 'issuer', label: 'Issuer', sel: '#daIssuer', type: 'text' },
    { k: 'year', label: 'Award year', sel: '#daYearF', type: 'text' },
    { k: 'date_text', label: 'Date shown', sel: '#daDate', type: 'text' },
    { k: 'venue', label: 'Venue', sel: '#daVenue', type: 'text' },
    { k: 'story', label: 'The story', sel: null, type: 'story' },
    { k: 'sources', label: 'Sources', sel: 'textarea[name=sources]', type: 'area' }
  ];
  function curStory() { return (window.tinyMCE && tinyMCE.get('da_story') && !tinyMCE.get('da_story').isHidden()) ? tinyMCE.get('da_story').getContent() : $('#da_story').val(); }
  function cur(f) { return f.type === 'story' ? curStory() : ($(f.sel).val() || ''); }
  function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  $('#daAssistToggle').on('click', function () {
    var open = $('#daAssistBody').prop('hidden'); $('#daAssistBody').prop('hidden', !open);
    $(this).text(open ? 'Close' : 'Open').attr('aria-expanded', open ? 'true' : 'false');
  });
  var lastGather = null;
  $('#daGather').on('click', function () {
    var $b = $(this).prop('disabled', true), $m = $('#daGatherMsg').text('Reading the sources…');
    $.post(C.ajax, { action: 'didi_award_gather', nonce: C.nonce, links: $('#daLinks').val(), notes: $('#daNotes').val() }).done(function (r) {
      if (r && r.success) { lastGather = r.data; $m.text(''); showReview(r.data); }
      else { $m.text((r && r.data && r.data.message) || 'Nothing could be read.'); }
    }).fail(function () { $m.text('The request failed. Please try again.'); }).always(function () { $b.prop('disabled', false); });
  });
  function showReview(d) {
    var rows = FIELDS.map(function (f) {
      var p = d.fields[f.k] || { value: '', sources: [] }, c = cur(f), has = $.trim(p.value) !== '';
      var plain = f.type === 'story' ? c.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() : c;
      var input = f.type === 'text' ? '<input type="text" class="da-input" data-prop="' + f.k + '" value="' + esc(p.value) + '">'
        : '<textarea class="da-input" rows="' + (f.type === 'story' ? 7 : 3) + '" data-prop="' + f.k + '">' + esc(p.value) + '</textarea>';
      var src = (p.sources || []).map(function (s) { return '<span class="da-src">' + esc(s) + '</span>'; }).join('');
      return '<div class="da-rev-row' + (has ? '' : ' is-empty') + '"><label class="da-rev-check"><input type="checkbox" data-use="' + f.k + '"' + (has && (f.k !== 'story' || !$.trim(plain)) ? ' checked' : '') + (has ? '' : ' disabled') + '><strong>' + f.label + '</strong></label>' +
        '<div class="da-rev-cur"><span class="da-rev-lbl">Now</span><div class="da-rev-txt">' + (plain ? esc(plain.length > 280 ? plain.slice(0, 280) + '…' : plain) : '<em>empty</em>') + '</div></div>' +
        '<div class="da-rev-new"><span class="da-rev-lbl">Proposed</span>' + (has ? input : '<em>Nothing found. Fill it in by hand.</em>') + (src ? '<div class="da-srcs">From: ' + src + '</div>' : '') + (p.note ? '<p class="da-hint">' + esc(p.note) + '</p>' : '') + '</div></div>';
    }).join('');
    var imgs = (d.images || []).map(function (i) { return '<figure class="da-cand"><img src="' + esc(i.url) + '" alt="" loading="lazy"><figcaption>' + esc(i.from) + '</figcaption><button type="button" class="da-btn da-btn-small" data-cand="' + esc(i.url) + '">Import &amp; crop</button></figure>'; }).join('');
    if (imgs) rows += '<div class="da-rev-images"><h3>Images found in the sources</h3><p class="da-hint">Only use images you have permission to use.</p><div class="da-cands">' + imgs + '</div></div>';
    if (d.errors && d.errors.length) rows += '<p class="da-warn da-hint">Could not read: ' + esc(d.errors.join('  ')) + '</p>';
    $('#daReviewBody').html(rows);
    $('#daReviewRead').text('Read ' + d.read.length + ' source' + (d.read.length === 1 ? '' : 's') + '.');
    $('#daReview').prop('hidden', false);
  }
  $('#daReviewBody').on('click', '[data-cand]', function () {
    var $b = $(this).prop('disabled', true).text('Importing…'), id = $('input[name=award_id]').val() || 0;
    if (!Number(id)) { window.alert('Save the award once first, then import images.'); $b.prop('disabled', false).text('Import & crop'); return; }
    importImage(id, $b.data('cand')).done(function (r) { $('#daReview').prop('hidden', true); openCrop(r.url, r.id, 0, 0); })
      .fail(function (m) { window.alert(m); $b.prop('disabled', false).text('Import & crop'); });
  });
  $('#daApply').on('click', function () {
    var n = 0;
    $('#daReviewBody [data-use]:checked').each(function () {
      var k = $(this).data('use'), v = $('#daReviewBody [data-prop="' + k + '"]').val(), f = FIELDS.filter(function (x) { return x.k === k; })[0];
      if (v == null) return;
      if (f.type === 'story') {
        var ed = window.tinyMCE && tinyMCE.get('da_story');
        if (ed && !ed.isHidden()) { ed.setContent(v); } else { $('#da_story').val(v); }
      } else { $(f.sel).val(v).trigger('input'); }
      n++;
    });
    dirty = true; $('#daReview').prop('hidden', true);
    $('#daGatherMsg').text(n + ' field' + (n === 1 ? '' : 's') + ' filled in. Check them, then press Save changes.');
    if (n) $('html, body').animate({ scrollTop: 0 }, 250);
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
