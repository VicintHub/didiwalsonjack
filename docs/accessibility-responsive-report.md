# Responsiveness and accessibility pass (October 2026)

Scope: the five embedded pages (`index`, `about`, `career`, `books`, `contact`) and the plugin-served pages (`/awards/`, single award). Existing HTML, copy, images, links and the approved desktop design were kept. Changes are additive (one `<style id="a11y-resp">` block and one small script per page, plus targeted attribute fixes). Re-runnable patch: `tools/patch_a11y_responsive.py`.

## Method and what was actually run
- Rendered in Chromium (Playwright) at 320x568, 375x812, 430x932, 667x375, 844x390, 768x1024, 1024x768, 1280x800, 1440x900, 1920x1080, 2560x1440, plus 15 intermediate widths (360 to 1366) on all 7 pages: horizontal overflow and off-screen element scan, before and after.
- axe-core (WCAG 2.0/2.1/2.2 A and AA tags + best practice) at 375 and 1280 on all 7 pages, before and after.
- Scripted keyboard and behaviour tests: skip link, menu open/close with Escape and focus return, 44px menu button, menu fit in landscape, dialog focus trap and focus return, contact form names and live region, inert off-screen hero slides, reduced-motion autoplay, 1.4.12 text-spacing override at 375.
- Desktop regression: 735 element measurements (position, size, font size, font family, colour, background) of the original HTML vs the patched HTML at 1280, 1440 and 1920 on the five pages with external images blocked so both runs are identical: 0 differences. (A raw screenshot diff was not used as the pass criterion because remote images load at different times between runs.)

## Responsive issues found and fixed
| Issue | Where | Fix |
|---|---|---|
| Whole page scrolled sideways (642px wide at 320 to 430, 740 at 667x375) | Home awards section: grid column grew to the width of the card rail | single column `minmax(0,1fr)` below 992px |
| Page 1204px wide at 1024px | Home and Contact "Advisory" cards (4 columns too wide) | 2 columns up to 1240px, 1 column below 576px; 4 columns above 1240px untouched |
| 333px wide at 320px | Contact form (Turnstile widget is 300px) | grid child may shrink; widget scales to 85% below 360px |
| Mobile menu taller than the screen in landscape | all pages | menu scrolls inside the viewport |
Result after: 0 overflow findings at all 11 required viewports and 15 intermediate widths on all 7 pages.

## Accessibility issues found and fixed
| Issue (WCAG) | Fix |
|---|---|
| No skip link (2.4.1) | "Skip to main content" link, visible on focus; `main` is the target |
| Weak or missing focus indicator (2.4.7, 2.4.11) | high-contrast focus ring on all controls |
| Menu button state not exposed, no Escape (4.1.2, 2.1.2) | `aria-expanded`, `aria-controls`, Escape closes and returns focus (also on plugin pages) |
| Contact fields had placeholders only; select had no name (1.3.1, 4.1.2, 1.3.5) | `aria-label` on all 7 fields and autocomplete tokens; error/success message now a live region |
| Form field borders 1.2:1 (1.4.11) | border colour darkened to 3.6:1 |
| White text on lilac badges, 1.85:1 (1.4.3) | badge text colour changed to dark aubergine (Books cards, Home featured book) |
| Career cards were `role=button` containing buttons (nested interactive) | the "Read more" button is the control and carries the full name; the card still opens on click; dialog focus now returns to that button |
| Career slider dots 10px (2.5.8) and invalid tablist role | 24px hit area (same visible size); role fixed |
| Awards rail used `role=listitem` on buttons; accessible name did not contain the visible text (2.5.3) | roles removed, name comes from visible text |
| Off-screen hero slides still focusable | non-current slides are `inert`; autoplay stops on focus and when the user prefers reduced motion |
| Hero dots were mouse-only spans | hidden from assistive tech (the Previous/Next buttons operate the slider) |
| Reduced motion not respected | animation and marquee reduced under `prefers-reduced-motion` |
| Plugin pages: no main landmark skip, heading order h1 to h3, icon-link names | skip link, hidden h2, visible-text names |

## Outstanding items and limits
- Career page big faded numerals (01 to 06) are decorative and low contrast by design (1.28:1). They are marked `aria-hidden`; axe still lists them. Exempt as pure decoration under 1.4.3. Changing them would alter the design.
- Home hero autoplay has no visible pause button (2.2.2). It stops on hover, on focus and under reduced motion. Adding a button changes the hero look, so it was not added; say if you want it.
- Footer text links and contact social icons are 15px to 22px tall. They pass the 2.5.8 spacing exception (axe reports no target-size failure) but are below the 44px comfort size.
- Screen readers (NVDA, VoiceOver, TalkBack) were not run, and real phones were not used; checks used Chromium emulation, axe-core and scripted keyboard tests. Automated checks do not prove full WCAG conformance.
- The embedded YouTube player on the Career page logs a `writeEmbed` error from YouTube's own script in the test browser; it is third-party and was not changed.
- Plugin pages were checked for overflow and axe only; their desktop appearance was not geometry-compared (changes there are a skip link, focus ring, a hidden heading and menu sizing).
