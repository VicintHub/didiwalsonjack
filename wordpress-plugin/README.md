# Didi Walson-Jack Awards (WordPress plugin)

Awards & Commendations for didiwalsonjack.com.

## Install
1. WordPress admin → Plugins → Add New → Upload Plugin → choose `didi-awards.zip` (in the repo root) → Activate.
2. On activation the plugin imports the 32 awards listed in the CV (published, 10 marked as featured).
3. Settings → Permalinks → press **Save** once, so `/awards/` and `/award/{name}/` work.

## What you get
| Part | Where |
|---|---|
| Dashboard: card grid, search, year filter, quick filters, featured star | WP admin → **Awards** |
| Add / edit form: title, card summary, rich-text story, featured image, extra images, issuer, year, date, venue, issuer's page link, sources, status, feature-on-home switch | Awards → **Add award** / Edit |
| Import an image from a web address into the Media Library | Edit screen → Featured image → "Import from a web address" |
| **Smart import:** paste links and notes → the plugin reads the pages, pulls out title, issuer, year, date, venue, a summary and the story, and shows them side by side with what is in the form now. You tick, edit and press "Apply selected to form". No AI service, no key, no cost | Edit screen → Smart import |
| **Crop tool:** after choosing or importing a photo, a 4:3 frame opens. Drag, zoom, "Crop and use" saves a 1600 × 1200 copy (warns if the photo is too small) | Edit screen → Featured image |
| All awards page (filterable grid) | `/awards/` |
| One page per award (centred title, About / story / Other awards columns, issuer button, gallery, share, related awards) | `/award/{name}/` |
| Feed for the home-page slider | `/wp-json/didi/v1/awards?featured=1` |
| Re-import missing CV awards | Awards → Tools |

## Home page
`index.html` already contains the awards slider. It loads `/wp-json/didi/v1/awards?featured=1&limit=10` from the same domain, so awards you feature appear automatically with no re-pasting of the embed code. If the feed is unreachable it shows a built-in copy of the same ten awards.

* At most **10** awards can be featured. The dashboard counter and the star buttons enforce this.
* Cards without an image use a neutral brand placeholder until a photo is added.

## Notes
* The award pages are complete branded pages (header, footer, fonts) and do not depend on the theme or Elementor.
* Images: two awards (African Public Service Award, UNILAG Distinguished Alumnus Award) import an approved press photo from the web into the Media Library when the plugin is activated; if the download fails the placeholder stays and the award can be retried from its edit screen. Other awards use placeholders until you upload photos. Use only images you have permission to use.
* Logos are bundled in `assets/logos`.
* Requires WordPress 6.0+ and PHP 7.4+. Tested on WordPress 6.8 with PHP 8.3.

## Smart import: what it does and does not do
* It reads up to 8 links per run and any pasted text. It keeps the sentences that mention her and the award, picks the most likely title, issuer, date, year and venue, and applies the house style (Mrs. Didi Esther Walson-Jack, mni, Service-Wise).
* It does **not** rewrite text in new words: that needs an AI service with a key. The story is assembled from the relevant sentences of your sources, so read it through and put it in your own words before publishing.
* Every proposed field shows which source it came from. Nothing is saved until you press Save changes.
