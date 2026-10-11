#!/usr/bin/env python3
"""One-off, idempotent patch: responsive and WCAG 2.2 AA refinements for the five static pages.
Additive only; desktop styling is not changed. Run from the repo root."""
import re

MARK = 'id="a11y-resp"'

CSS = '''<style id="a11y-resp">
    /* ===== Accessibility & responsive refinements (additive; desktop look unchanged) ===== */
    .skip-link { transition: none; position: absolute; left: 12px; top: -120px; z-index: 100000; background: #3B1F4A; color: #FFFFFF; padding: 0.8rem 1.2rem; border-radius: 8px; font-weight: 700; text-decoration: none; box-shadow: 0 0 0 2px #FFFFFF; }
    .skip-link:focus { top: 12px; }
    main[tabindex="-1"]:focus, main[tabindex="-1"]:focus-visible { outline: none; box-shadow: none; }
    a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible, summary:focus-visible, [tabindex]:focus-visible { outline: 3px solid #7A4BA8; outline-offset: 3px; box-shadow: 0 0 0 2px #FFFFFF; }
    .mobile-drawer { max-height: calc(100vh - 7rem); max-height: calc(100dvh - 7rem); overflow-y: auto; overscroll-behavior: contain; }
    @media (max-width: 992px) { .mobile-toggle { width: 44px; height: 44px; } }
    img, video, iframe, svg { max-width: 100%; }
    /* contrast fixes (text colour only; backgrounds unchanged) */
    .product-cover-frame .status-badge.badge-available, .featured-book-badge { color: #2A1636; }
    /* touch / target-size */
    .kfa-dot { width: 24px; height: 24px; padding: 7px; margin: 0 -3px; box-sizing: border-box; background-clip: content-box; }
    .kfa-dot.on { width: 42px; }
    /* narrow-screen reflow */
    .contact-split-grid > *, .aw-layout > * { min-width: 0; }
    @media (max-width: 1240px) { .advisory-bento-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } .advisory-bento-grid > * { min-width: 0; } }
    @media (max-width: 576px) { .advisory-bento-grid { grid-template-columns: minmax(0, 1fr) !important; } }
    @media (max-width: 992px) { .aw-layout { grid-template-columns: minmax(0, 1fr) !important; } .contact-split-grid { grid-template-columns: minmax(0, 1fr) !important; } }
    @media (max-width: 359px) { #turnstileBox { transform: scale(0.85); transform-origin: left top; height: 56px; } }
    .contact-input-field, .contact-textarea-field { border-color: #8B8494; }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; scroll-behavior: auto !important; }
      .marquee-track { animation: none !important; }
    }
  </style>
'''

JS = '''<script id="a11y-resp-js">
    /* Menu button state, Escape to close, focus return. Works alongside each page's own toggle code. */
    (function () {
      function init() {
        var btn = document.getElementById('mobileToggleBtn'), drawer = document.getElementById('mobileDrawer');
        if (!btn || !drawer) return;
        btn.setAttribute('aria-controls', 'mobileDrawer');
        btn.setAttribute('aria-expanded', 'false');
        function sync() { btn.setAttribute('aria-expanded', drawer.classList.contains('open') ? 'true' : 'false'); }
        new MutationObserver(sync).observe(drawer, { attributes: true, attributeFilter: ['class'] });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && drawer.classList.contains('open')) { btn.click(); btn.focus(); }
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
  </script>
'''

def common(s):
    if MARK not in s:
        s = s.replace('</head>', CSS + '</head>', 1)
        s = s.replace('</body>', JS + '</body>', 1)
        s = re.sub(r'(<body[^>]*>)', r'\1\n  <a class="skip-link" href="#main-content">Skip to main content</a>', s, count=1)
        s = s.replace('<main>', '<main id="main-content" tabindex="-1">', 1)
    return s

def index(s):
    # awards rail: a button must not carry role=listitem
    s = s.replace(''' role="listitem" data-i="''', ''' data-i="''')
    s = s.replace('<div class="aw-rail" id="awRail" role="list">', '<div class="aw-rail" id="awRail" role="group" aria-label="Award highlights">')
    # hero dots are a visual indicator; the previous/next buttons operate the slider
    s = s.replace('<div class="hero-slider-dots" id="heroDots">', '<div class="hero-slider-dots" id="heroDots" aria-hidden="true">')
    # keep off-screen slides out of the tab order, stop autoplay for reduced motion and while focused
    s = s.replace('''          sliderTrack.style.transform = `translateX(-${currentIndex * 100}%)`;''', '''          sliderTrack.style.transform = `translateX(-${currentIndex * 100}%)`;
          slides.forEach((sl, i) => { if (i === currentIndex) { sl.removeAttribute('inert'); sl.removeAttribute('aria-hidden'); } else { sl.setAttribute('inert', ''); sl.setAttribute('aria-hidden', 'true'); } });''')
    s = s.replace('''        function startAutoSlide() {
          stopAutoSlide();''', '''        function startAutoSlide() {
          stopAutoSlide();
          if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;''')
    s = s.replace('''          sliderSection.addEventListener('mouseleave', startAutoSlide);''', '''          sliderSection.addEventListener('mouseleave', startAutoSlide);
          sliderSection.addEventListener('focusin', stopAutoSlide);''')
    s = s.replace('''        startAutoSlide();
      }
    });''', '''        goToSlide(0);
        startAutoSlide();
      }
    });''', 1)
    return s

def career(s):
    # cards: the inner "Read more" button is the control; the card itself stays clickable for pointer users
    def fix(m):
        tag, label = m.group(0), re.search(r'aria-label="([^"]*)"', m.group(0))
        return tag
    cards = re.findall(r'<(?:article|div) class="(?:kfa-slide|timeline-card is-clickable)"[^>]*data-role="[^"]+"[^>]*>', s)
    for tag in cards:
        lab = re.search(r'aria-label="([^"]*)"', tag)
        label = lab.group(1) if lab else ''
        new = re.sub(r'\s+(?:role="button"|tabindex="0"|aria-haspopup="dialog"|aria-label="[^"]*")', '', tag)
        s = s.replace(tag, new, 1)
        role = re.search(r'data-role="([^"]+)"', tag).group(1)
        # find the button that follows this card and name it
        i = s.index(new) + len(new)
        j = re.compile(r'<button type="button" class="(?:kfa-btn|tl-more-btn)" data-role="%s">' % role).search(s, i)
        if j and label:
            btn = j.group(0)
            s = s[:j.start()] + btn[:-1] + ' aria-haspopup="dialog" aria-label="%s">' % label + s[j.end():]
    s = s.replace('<div class="kfa-dots" id="kfaDots" role="tablist" aria-label="Choose slide">', '<div class="kfa-dots" id="kfaDots" role="group" aria-label="Choose slide">')
    s = re.sub(r'<span class="kfa-num">', '<span class="kfa-num" aria-hidden="true">', s)
    return s

def contact(s):
    names = {'fullName': ('Full name', 'name'), 'organization': ('Organisation, ministry or institution', 'organization'),
             'email': ('Email address', 'email'), 'phone': ('Mobile number or WhatsApp', 'tel'),
             'category': ('Reason for enquiry', None), 'subject': ('Subject', None), 'message': ('Message', None)}
    for i, (lab, ac) in names.items():
        s = re.sub(r'(<(?:input|select|textarea)\b[^>]*\bid="%s")' % i, lambda m: m.group(1) + ' aria-label="%s"' % lab + (' autocomplete="%s"' % ac if ac else ''), s, count=1)
    s = s.replace('<div class="form-success-toast" id="successToast"', '<div class="form-success-toast" id="successToast" role="status" aria-live="polite"', 1)
    s = s.replace("toast.setAttribute('role', 'status');", "toast.setAttribute('aria-live', ok ? 'polite' : 'assertive');")
    return s

PAGES = {'index': index, 'about': None, 'career': career, 'books': None, 'contact': contact}
for name, fn in PAGES.items():
    f = name + '.html'
    s = open(f, encoding='utf-8').read()
    if MARK not in s:
        if fn: s = fn(s)
        s = common(s)
        open(f, 'w', encoding='utf-8').write(s)
        print('patched', f)
    else:
        print('already patched', f)
