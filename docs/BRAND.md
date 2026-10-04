# Didi Walson-Jack

Brand book for the official website and communications of Mrs. Didi Esther Walson-Jack, OON, mni: lawyer, public administrator, reformer, author, and from here on a speaker and global advisor. It holds the palette, type, spacing, logo and usage rules.

## Brand essence

Leadership without arrogance. Achievement without boasting. Authority without distance. Humanity without sentimentality.

The look has to hold two things at once: the dignity of a head of the civil service and the warmth of a woman who wrote *Roses in the Thorns*. Deep aubergine carries the first. Lilac, blush and a small amount of rose carry the second.

### Voice

- Statesmanlike but human. Plain, precise sentences. No slogans, no exclamation marks.
- Facts over adjectives. Say what changed and for whom, then stop.
- Use the formal name on first mention: Mrs. Didi Esther Walson-Jack, OON, mni. Afterwards, Mrs. Walson-Jack.
- The title line, taken from the approved lockup, is "20th Head of the Civil Service of the Federation (2024-2026)". Write titles out in full. Confirm this wording before it appears on the website and printed stationery.

## Colour

Purple leads, pink supports, and the base stays light and quiet. There is no gold anywhere in this system.

| Role | Tokens | Share of any layout |
| --- | --- | --- |
| Base | `ivory`, `white` | about 60% |
| Anchor | `aubergine`, `night`, `amethyst` | about 25% |
| Soft supports | `lilac`, `mist`, `blush` | about 10% |
| Accent | `rose` | about 5% |

Rules:

- Headings, navigation, footer and the logo sit in `aubergine`. The footer may drop to `night`.
- Buttons and links use `amethyst`. One filled button per view; secondary actions use an outline.
- Pink is for warmth, not decoration: pull quotes and the book section on `blush`, with a single `rose` rule or quote mark. Never a full pink page.
- Awards and honours that once used gold now take a `lilac` badge with `aubergine` text.
- Avoid neon magenta, purple-to-pink gradients, glow effects and pastel-only pages with no deep anchor colour.

Approved text pairs (all pass WCAG AA): `ink` on `ivory` 15.3:1, `aubergine` on `ivory` 13.4:1, `amethyst` on `ivory` 5.8:1, `white` on `amethyst` 6.2:1, `white` on `rose` 5.8:1, `aubergine` on `lilac` 7.6:1, `aubergine` on `blush` 11.7:1, `aubergine` on `mist` 11.0:1, `ink-muted` on `ivory` 6.7:1, `ivory` on `night` 15.7:1. Do not put `lilac` or `blush` text on `ivory`.

## Typography

Two families: Source Serif 4 for headings and Century Gothic for everything else. Century Gothic is her stated preference. The serif adds institutional weight and contrasts with Century Gothic's round geometric forms, so the pair looks deliberate. Lora is the warmer alternative if the site ever needs to lean closer to her memoir.

- Headings and display: Source Serif 4 Bold, in `aubergine`.
- Pull quotes: Source Serif 4 Regular at 24px, not italic.
- Body, navigation, buttons, labels and captions: Century Gothic Regular at 17px on 29px line height, lines no wider than about 68 characters. The face is wide, so do not set body text smaller than 16px.
- Stat figures: Century Gothic Bold, so the numbers stay modern.
- Labels: Century Gothic Bold, 12px, capitals, with open letter spacing. Use sparingly.
- Source Serif 4 is free on Google Fonts. Century Gothic is a licensed Microsoft font and is not on every device. For documents, slides and stationery use Century Gothic directly. For the website, either buy a web licence or use the free stand-ins in the font stack (`URW Gothic`, `Questrial`), which look very close.

## Logo

Two approved versions, both on transparent backgrounds, kept in `assets/Logos`.

- Emblem badge (`walson-jack-emblem-badge.png`): a round badge with her portrait, her name, the Federal coat of arms and the ribbon "20th HCSF of Nigeria (2024-2026)". Use it where the mark is small and round: social posts, profile pictures, email signature.
- Horizontal lockup (`walson-jack-lockup-horizontal.png`): the badge beside her full name and title. Use it for the website header, letterhead, business card and documents.

Rules:

- Place on `ivory`, `white`, `mist` or `blush`. On a dark ground such as `aubergine`, set the badge on a white disc.
- Clear space: at least one eighth of the badge width on every side.
- Minimum size: badge 64px (15mm in print); lockup 200px wide (40mm in print).
- Do not recolour, stretch, crop, rotate or add shadows or outlines. Do not remove the coat of arms or the ribbon from the badge.
- The lockup's name uses `amethyst` and its title line uses `aubergine`, so it already sits inside this palette. The badge's own violet (`emblem-violet`) is more saturated than the interface palette, which is why the badge stays on light grounds.

Two points to settle before the mark is used widely: written permission to show the Federal coat of arms on a personal website and materials, and whether she needs a second mark without the office title and the "2024-2026" dates for her work as a speaker and global advisor.

## Imagery

- Real photographs only: official portraits, engagements and events. No stock photography of other people and no AI-generated images of real people or events.
- Portraits sit on `ivory` or `lilac` backgrounds with `radius-lg` corners. The cream-suit portrait on warm beige and the purple portrait both sit well with this palette.
- No filters that shift skin tones. Colour-correct for consistency and leave it there.

## Website specifications

```css
:root{
  --aubergine:#3B1F4A; --night:#2A1636; --amethyst:#7A4BA8;
  --lilac:#C9B6E4; --mist:#E9DFF5; --blush:#F8E4EC; --rose:#B03A63;
  --ivory:#FBF8F4; --white:#FFFFFF; --ink:#231F26; --ink-muted:#5E5566;
  --font-serif:"Source Serif 4",Georgia,serif;
  --font-sans:"Century Gothic","URW Gothic","Questrial","Avant Garde",sans-serif;
  --radius-sm:4px; --radius-md:8px; --radius-lg:16px;
}
body{background:var(--ivory);color:var(--ink);font:400 17px/29px var(--font-sans)}
h1,h2,h3{font-family:var(--font-serif);color:var(--aubergine);font-weight:700}
.stat{font-family:var(--font-sans);font-weight:700;font-size:48px;line-height:52px}
a,.btn{color:var(--amethyst)}
.btn-primary{background:var(--amethyst);color:var(--white);border-radius:var(--radius-md);padding:12px 24px}
.stat-band{background:var(--mist);color:var(--aubergine)}
.quote{background:var(--blush);border-radius:var(--radius-md);color:var(--aubergine);font:400 24px/34px var(--font-serif)}
.quote::before{content:"";display:block;width:40px;height:3px;background:var(--rose)}
footer{background:var(--night);color:var(--ivory)}
```

- Layout: content width 1120px, section spacing `space-5` (80px) on desktop and `space-4` (40px) on mobile.
- Fonts: load Source Serif 4 (weights 400 and 700) from Google Fonts with `display=swap`.
- Stat counter: four figures on a `mist` band, numerals in `aubergine` Century Gothic Bold at 48px, labels in `ink-muted` at 13px.
- Focus rings: 2px `amethyst` with a 2px `ivory` gap.
- Instagram feed slider sits just above the footer on `ivory`, with `amethyst` arrow controls.

## Applications

Social media templates and stationery are built from the same tokens in the companion design file. Rules that apply across them:

- Social posts: 4:5 portrait at 1080 by 1350. `ivory` or `aubergine` ground, name and one message only, logo bottom-left, a single `rose` rule at most.
- Quote cards: `blush` ground, quote in `aubergine` Source Serif 4, attribution in `ink-muted` Century Gothic.
- Stationery: letterhead and business card on `ivory`, name in `aubergine` Source Serif 4 Bold, one thin `amethyst` rule. Contact details are placeholders until she confirms them.
- Email signature: name in `aubergine` Source Serif 4 Bold, title and contact lines in `ink-muted` Century Gothic, nothing animated, no social icon rows.

## To confirm before launch

- The title line on the website and stationery: "20th Head of the Civil Service of the Federation (2024-2026)".
- Official email, phone, address and media contact for stationery and the contact page.
- Web licence for Century Gothic, or sign-off on the Questrial and URW Gothic stand-ins. Source Serif 4 needs no licence.
- Permission to use the Federal coat of arms, and whether a second mark without the office title is needed.
