(function ($) {
  'use strict';
  var C = window.DidiMsg || {}, data = {};
  try { JSON.parse($('#dmData').text() || '[]').forEach(function (m) { data[m.id] = m; }); } catch (e) {}
  var cur = null, $d = $('#dmDrawer');
  function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var ST = { 'new': 'Awaiting approval', approved: 'Forwarded to Principal', archived: 'Archived', spam: 'Spam' };

  function render(m) {
    cur = m.id; data[m.id] = m;
    $('#dmDCat').text(m.reason_l); $('#dmDTitle').text(m.subject); $('#dmDWhen').text('Received ' + m.date_h);
    $('#dmSteps').html(
      '<li class="on">Received</li>' +
      '<li class="' + (m.office_sent ? 'on' : (m.status === 'spam' ? 'warn' : '')) + '">' + (m.status === 'spam' ? 'Blocked' : 'Office notified') + '</li>' +
      '<li class="' + (m.principal_sent ? 'on' : '') + '">Forwarded to Principal</li>');
    var rows = [['Name', m.name], ['Organisation', m.org || 'Not given'], ['Email', '<a href="mailto:' + esc(m.email) + '">' + esc(m.email) + '</a>'], ['Phone / WhatsApp', m.phone ? '<a href="tel:' + esc(m.phone.replace(/\s+/g, '')) + '">' + esc(m.phone) + '</a>' : 'Not given'], ['Category', esc(m.reason_l)], ['Status', esc(ST[m.status] || m.status)]];
    $('#dmFields').html(rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + (r[1].indexOf('<a ') === 0 || r[0] === 'Category' || r[0] === 'Status' ? r[1] : esc(r[1])) + '</dd>'; }).join(''));
    $('#dmMsg').text(m.message); $('#dmNote').val(m.note || '');
    $('#dmMeta').text((m.principal_sent ? 'Forwarded to ' + C.principal + (m.approved_by ? ' · approved by ' + m.approved_by : '') + ' · ' : '') + 'From IP ' + (m.ip || 'unknown'));
    $('#dmReply').attr('href', 'mailto:' + m.email + '?subject=' + encodeURIComponent('Re: ' + m.subject));
    $('#dmApprove').prop('disabled', m.status === 'approved' || m.status === 'spam').html('<span class="dashicons dashicons-yes"></span> ' + (m.status === 'approved' ? 'Forwarded to Principal' : 'Approve &amp; forward to Principal'));
    $('#dmArchive').text(m.status === 'archived' ? 'Move back to inbox' : 'Archive').data('to', m.status === 'archived' ? 'new' : 'archived');
    $('#dmSpam').text(m.status === 'spam' ? 'Not spam' : 'Mark as spam').data('to', m.status === 'spam' ? 'new' : 'spam');
  }
  function open(id) { if (!data[id]) return; render(data[id]); $d.prop('hidden', false); $d.find('.dm-drawer').trigger('focus'); history.replaceState(null, '', '#m' + id); }
  function close() { $d.prop('hidden', true); history.replaceState(null, '', location.pathname + location.search); }
  function act(what, extra, done) {
    return $.post(C.ajax, $.extend({ action: 'didi_msg_action', nonce: C.nonce, id: cur, 'do': what }, extra || {})).done(function (r) {
      if (r && r.success) { done && done(r.data); } else { window.alert((r && r.data && r.data.message) || 'That did not work.'); }
    }).fail(function () { window.alert('The request failed. Please try again.'); });
  }
  function reload() { location.hash = 'm' + cur; location.reload(); }

  $('#dmList').on('click', '.dm-row', function () { open($(this).data('id')); });
  $d.on('click', '[data-close]', close);
  $(document).on('keydown', function (e) { if (e.key === 'Escape' && !$d.prop('hidden')) close(); });
  $('#dmApprove').on('click', function () {
    if (!window.confirm('Forward the full details of this message to ' + C.principal + '?')) return;
    var $b = $(this).prop('disabled', true).text('Sending…');
    act('approve', {}, function () { reload(); }).fail(function () { $b.prop('disabled', false); }).always(function () { $b.prop('disabled', false); });
  });
  $('#dmArchive, #dmSpam').on('click', function () { act($(this).data('to'), {}, reload); });
  $('#dmDelete').on('click', function () { if (window.confirm('Delete this message permanently? This cannot be undone.')) act('delete', {}, function () { location.href = location.href.split('#')[0]; }); });
  $('#dmNoteSave').on('click', function () { var $b = $(this).text('Saving…'); act('note', { note: $('#dmNote').val() }, function (m) { data[m.message.id] = m.message; $b.text('Saved'); setTimeout(function () { $b.text('Save note'); }, 1500); }); });
  var h = location.hash.match(/^#m(\d+)$/); if (h) open(Number(h[1]));
  var q = new URLSearchParams(location.search).get('message'); if (q) open(Number(q));
})(jQuery);
