# Supreme Motors — Homepage Design Language

The reference document for how the public homepage looks and behaves. Any new
front-facing section must follow these rules; when in doubt, copy an existing
section's anatomy.

## 1. Design tokens

### Palette

| Token | Value | Use |
|---|---|---|
| Navy (primary) | `#0b1e3b` | Headings, dark cards, active tiles, buttons |
| Navy deep | `#081730` | Dark-card gradient end, footer bottom |
| Navy raised | `#12284a` | Dark-card gradient start |
| Red (accent) | `#e01f26` | Eyebrows, prices, icons, hover fills — the single accent |
| Red gradient | `linear-gradient(150deg, #e5262d, #c8151c)` | Primary buttons, brand pills |
| Red on navy | `#ff6b70` / `#ff8085` | Red that stays readable on dark cards |
| Gold | `#ffc24b` | Star ratings only |
| Body text | `#5b6b82` | Section subheadings, paragraphs |
| Muted | `#8494ab` | Secondary labels, counts, idle icons |
| Faint | `#9aa8bd` / `#c3cdda` | Tertiary labels, disabled, arrows |
| Hairline | `#eef1f6` | Card borders, dividers |
| Hairline strong | `#e6eaf0` / `#e3e8f0` | Input borders, control borders |
| Surface | `#f8fafc` | Idle tiles, input backgrounds |
| Surface alt | `#f4f6f9` | Image placeholders, icon wells |
| On-navy text | `#a9b7cc` (body) / `#cdd8e8` (strong) / `#93a3bd` (footer) | Text on dark cards |

Dark-card overlays: borders `rgba(255,255,255,0.1–0.18)`, fills
`rgba(255,255,255,0.06–0.12)`, red glow
`radial-gradient(circle, rgba(224,31,38,0.14–0.22), transparent ~70%)`.

### Typography

| Role | Font | Spec |
|---|---|---|
| Display / headings / buttons-in-cards | **Archivo** | 800 for h1/h2, 700 for card titles; letter-spacing −0.02 to −0.025em |
| Body / UI | **Manrope** | 500–700; 800 for emphasis (buttons, eyebrows) |

Loaded from bunny.net fonts in `resources/views/app.blade.php`.

Scale: h1 hero 54→44→36px (responsive) · h2 section 40px · h2 dark-card 34px ·
card title 15.5–18px · body 15.5–16px/1.65 · labels 12.5–13.5px ·
micro-labels 10.5–12px at 800 weight with letter-spacing 0.03–0.08em.

### Geometry

- Section container: `max-width: 1280px; margin: 0 auto`, side padding 24px.
- Vertical rhythm: every section uses the `sm-sec` class — **60px top AND
  bottom padding on desktop, 40px on mobile (≤700px)**. Never hand-roll
  section padding.
- When two adjacent sections share the same background color, separate them
  with `<SectionDivider />` — a 1px `#eef1f6` hairline at **container width**
  (never full-bleed). The 60+60 gap alone is not enough separation.
- The footer uses `sm-footgap` (60px / 40px margin-top), no divider before it
  (color change is the separation).
- Radii: hero/dark feature cards **28px** · product/content cards **16–18px** ·
  buttons **13px** · chips/pills **6–11px** · circles `100px`/`50%`.
- Shadows: cards `rgba(11,30,59,0.04) 0 4px 14px`, hover
  `0 14px 30px rgba(11,30,59,0.08)`; navy buttons
  `rgba(11,30,59,0.25) 0 12px 28px`; red buttons
  `rgba(224,31,38,0.32–0.35) 0 12px 28px`.

## 2. Recurring patterns

### Section header (light sections)

**Banned: pill-badge eyebrows** (rounded-full chip with a dot and label,
e.g. `● WRITE TO US` in a bordered pill) — they read as AI-generated. The
only eyebrow style is the dash eyebrow below; on dark/centered banners use
label color `#cdd8e8` with a red dash on each side.

Left-aligned, always this exact stack:

```html
<div><!-- eyebrow: 22px red dash + 12.5px/800 letterspaced label -->
  <span style="width:22px;height:2px;background:#e01f26"></span>EYEBROW TEXT
</div>
<h2><!-- Archivo 800, 40px, navy, letter-spacing -0.025em, margin-top 12px --></h2>
<p><!-- 16px/1.65, #5b6b82, 500, margin-top 14px, max-width 520px --></p>
```

Content follows at `margin-top: 36px` (44px for card grids). If the section
has controls (tabs, carousel arrows), the header row is
`display:flex; align-items:flex-end; justify-content:space-between`.

### Dark feature card

`border-radius: 28px`, background
`linear-gradient(150deg, #12284a, #0b1e3b 55%, #081730)`, padding 48–62px,
`position:relative; overflow:hidden`, plus one decorative layer: an offset
radial red glow and/or a giant faint line-art vehicle
(`stroke: rgba(255,255,255,0.09)`). Used by: Stay-updated strip context,
Testimonials featured quote, Can't-find CTA.

### Buttons

- **Primary red**: red gradient, white 14.5–15px/800, radius 13px, red shadow,
  class `scp2` (hover translateY lift).
- **Primary navy**: navy gradient, same shape (e.g. "View all X stock →").
- **Carousel arrow**: 46px circle, class `sm-carbtn` — white/hairline idle,
  navy fill on hover, greyed when disabled.
- Arrows in labels are either the `→` character or a 16px stroked SVG chevron.

### Icons

Always **stroke line-art, never emoji, never filled glyphs**:
`fill:none; stroke:currentColor` (or explicit color), width 1.7–2.4,
round caps/joins. Vehicle silhouettes come from `BodyTypeIcon.vue`
(64×40 viewBox, side profiles with window detail, wheels = two concentric
circles). Small UI icons are 12–17px 24×24 SVGs. Red icons on light: `#e01f26`;
idle grey: `#8494ab`; on navy: `#ff6b70`/`#ff8085`.

### Cards & tiles

- **ProductCard** (shared): 220px cover image (falls back to a grey truck
  silhouette and emits `img-error` so parents drop/backfill the card), red
  brand pill, 18px title (1-line clamp), location pin + country, 3-cell
  FUEL/GEAR/TRAVELLED strip (red icons, 10.5px/800 labels), footer with red
  price (only for own/priced sources) or navy "Enquire" + "View details →".
- **Tile (body type)**: 132px min, radius 16px; idle `#f8fafc`/hairline;
  active = navy gradient + navy shadow, icon turns red, count `#a9b7cc`.
- **Compact row card (categories)**: flex row — 52px icon well (`#f4f6f9`,
  radius 14px), Archivo 700 title, "N in stock" count, chevron pushed right.

### Interaction classes (app.css)

`scp2` lift · `scpd` card lift+shadow · `scpe` red fill on hover ·
`scpf` white text on hover (footer links) · `sm-carbtn` carousel arrows.
Transitions: 0.16–0.2s, no bounce. Respect `prefers-reduced-motion`
(preloader already does).

## 3. Page anatomy (top → bottom)

| # | Section | Component | Key traits |
|---|---|---|---|
| 0 | Preloader | `app.blade.php` | White overlay, logo eases in, red sweep bar on hairline track |
| 1 | Header | `Header.vue` | White, logo left, center nav, "Sell Your Vehicle" + navy CTA right |
| 2 | Hero | `HeroV2.vue` | Full-bleed photo (`/assets/images/hero-truck.jpg` — hero's exclusive image) under navy scrims (95deg, 0.96→0.62→0.72 + radial); h1 with red underline on a keyword; search card (type/make/body/price → `/inventory`); gold-star rating strip with 4 real vehicle avatars |
| 3 | Explore our categories | `ExploreCategories.vue` | 7 top-level categories, compact row cards, 4-col grid, "View all +N" navy expander tile when >7 |
| 4 | Popular brands | `BrandsExplorer.vue` | Top-4 brand cards (3rd = navy highlight) + 2-col text index with counts |
| 5 | Shop by body type | `ShopByBodyType.vue` | Tile carousel (arrows in header), line-art icons, 6 ProductCards below, "View all X stock" |
| 6 | Recommended for you | `RecommendedForYou.vue` | Japan/China/Europe pill tabs right of header, 3-col ProductCards, view-all per country |
| 7 | Testimonials | `Testimonials.vue` | Centered header (red accent words); double-bezel dark quote card with red quote badge, gold stars, initials avatar, PURCHASED tag; prev/next arrows + elongated-dot pagination; snap-scroll strip of all 10 voices (active = red hairline + lift); 7s auto-rotate, crossfade via `tq-*` transitions, section reveals on scroll (`sm-reveal`) |
| 8 | CTA banner | `CantFindCta.vue` | Dark card over `/assets/images/cta-vehicle.jpg` (own-catalogue photo — never reuse the hero image, never watermarked scrape images) + left scrim + red glow, red Contact Us button |
| 9 | Footer | `Footer.vue` | Navy gradient: Stay-updated strip (inline email + red subscribe), brand block + link columns + contact rows, dynamic © year, 5px red gradient baseline |

## 4. Data rules for homepage sections

- Only products with `front_image` set **and** `front_image_dead_at IS NULL`
  are eligible; the frontend still drops any card whose image 404s at render
  and backfills from extra candidates (endpoints over-fetch: 24 featured /
  40 per body type, trimmed payloads).
- Body-type cards interleave makes (newest per make) — never a single-make wall.
- Categories show the 7 top-level parents with rolled-up child counts.
- Counts are formatted `toLocaleString()`; prices `$` + `toLocaleString()`,
  shown only for `tcv`/`suprememotors`/`electricvehicles` sources, else "Enquire".
- Cached payloads use `Cache::flexible` (stale-while-revalidate) — never make a
  visitor wait for a rebuild.

## 5. Responsive breakpoints (app.css `sm-*` helpers)

| Breakpoint | Changes |
|---|---|
| ≤1080px | desktop nav → burger; product/category/testimonial grids → 2-col; footer grid → 3-col |
| ≤920px | hero grid stacks; h1 44px; footer → 2-col; newsletter/why grids stack |
| ≤560px | h1 36px; all card grids → 1-col; footer → 1-col; CTA padding 40/26; newsletter input full-width |

Horizontal scrollers (`sm-typesrow`) hide scrollbars and use snap points;
navigation is by the themed arrow buttons.

## 6. Voice & content

- Sentence case everywhere except eyebrows and micro-labels (UPPERCASE, letterspaced).
- Countries are exactly **Japan, China, Europe** — copy says "Japan, China and Europe".
- Numbers are real DB counts, never invented ("171,915 in stock").
- CTAs are verbs: "Browse Inventory", "Contact Us", "View all Truck stock →".
- No emoji in UI. No stock-photo faces — avatars are initials circles.
