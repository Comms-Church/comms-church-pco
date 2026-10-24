# Apple Design Audit — PCO

Audit of this plugin against the `apple-design` skill (`.claude/skills/apple-design/SKILL.md`), 2026-10-24. Section references (e.g. §16 *Agency*) point to headings in that skill. The audit of all five Comms.Church plugins is in the shared doc: https://claude.ai/artifact/GGvMskv9nX4bc82HuR4wmS

## Overview

The five plugins have 61 design issues between them: 4 Critical, 13 High, 20 Medium and 24 Low. The worst ones lose people's work or leave them stuck, not visual polish: an editor that hangs on a failed save, sermon edits lost without warning, a confirmation that never fires, and future event dates deleted with no warning.

Each finding gives **What** is wrong, **Why** it matters (citing the section and heading of the vendored `apple-design` skill, e.g. §16 *Agency*), and a **Fix** in the plugin's own stack: plain CSS, jQuery or plain JS, and WordPress PHP. Severity is ranked by harm to the person using it:

- **Critical:** data loss, or a broken layout most visitors will see
- **High:** blocks a task, excludes keyboard or screen-reader users, or shows a wrong state
- **Medium:** friction, unclear feedback, or contrast below WCAG minimums
- **Low:** polish and consistency

The skill is mostly about gesture-driven motion (springs, flicks, momentum). These plugins have no draggable UI, so most citations come from §1 Response, §7 Spatial consistency, §12 Materials, §14 Reduced motion & accessibility, §15 Typography and §16 Design foundations.

### Worst issues across all plugins

| Rank | Plugin | Finding | Severity | Fixed in this pass |
| --- | --- | --- | --- | --- |
| 1 | Recurring Events | Changing a pattern permanently deletes future dates without warning | Critical | Yes |
| 2 | Sermon Suite | A failed save leaves the editor stuck on "Saving…" | Critical | Yes |
| 3 | Sermon Suite | No warning before leaving with unsaved edits | Critical | Yes |
| 4 | PCO TEC | Events without a featured image show an empty gray box | Critical | Yes |
| 5 | PCO TEC | "Force Full Re-sync" never asks for confirmation | High | Yes |
| 6 | PCO | Add-to-Calendar menu opens upward and can't be used from the keyboard | High | Yes |
| 7 | Sermon Suite, PCO TEC | No visible keyboard focus | High | Yes |
| 8 | PCO | Generator preview doesn't match the live page | High | Yes |
| 9 | Groups | Filtering changes the page silently | High | Yes |
| 10 | Groups, PCO, PCO TEC | Cards lift on hover but aren't links | High / Medium / Low | Yes |

A pattern across the suite: Groups and PCO already handle reduced motion, focus rings and container sizing well, while Sermon Suite and PCO TEC don't. A shared base stylesheet for focus, press and reduced-motion styles would stop these drifting apart again.

## Comms Church PCO

PCO has 12 findings. Its worst problems are the Add-to-Calendar menu, which opens upward and can't be used from the keyboard, and the shortcode generator preview, which doesn't match what visitors see. It already handles reduced motion and focus rings. Files: `assets/front.css`, `assets/front.js`, `assets/admin.css`, `assets/admin.js`, `includes/class-ccpco-renderer.php`, `includes/class-ccpco-calendar.php`.

### 1. The Add-to-Calendar menu opens upward and can't be used from the keyboard (High)

**What:** The menu opens *above* its button by default (`bottom: calc(100% + 6px)`) and only flips down near the top of the viewport. The upward default was a workaround: the card clips its overflow, which would cut off a menu that dropped down. It appears with `display:block`, with no anchoring. It is marked `role="menu"`, but focus stays on the button, arrow keys do nothing, Escape closes it without returning focus, and tabbing away leaves it open over the page.

**Why:** §7 *Spatial consistency*: "Anchor interactions to their source". A menu should come out of its trigger. §16 *Familiarity*: menus drop down, so "only break a familiar pattern if you can prove it's better." §16 *Flexibility*: "the full range of abilities."

**Fix** (`front.css` + `front.js`):

```css
.ccpco-cal-dropdown { display:block; top: calc(100% + 6px); bottom: auto; transform-origin: top left;
  opacity: 0; transform: scale(.96); visibility: hidden; transition: opacity .12s ease-out, transform .12s ease-out, visibility 0s .12s; }
.ccpco-cal-wrap.open .ccpco-cal-dropdown { opacity: 1; transform: none; visibility: visible; transition-delay: 0s; }
.ccpco-cal-wrap.ccpco-drop-up .ccpco-cal-dropdown { top: auto; bottom: calc(100% + 6px); transform-origin: bottom left; }
```

```js
// flip up only when there's no room below
wrap.classList.toggle('ccpco-drop-up', window.innerHeight - rect.bottom < 230);
wrap.querySelector('.ccpco-cal-option').focus();
// ArrowDown/ArrowUp move between .ccpco-cal-option; Escape closes and calls trigger.focus();
// focusout outside the wrap closes it.
```

Also move the clipping off the card so the menu can escape it. Remove `overflow: hidden` from `.ccpco-card` and round the image instead: `.ccpco-card-image { border-radius: var(--ccpco-radius) var(--ccpco-radius) 0 0; }`.

### 2. The generator preview doesn't match the live page (High)

**What:** `admin.css` styles its settings panels as `.ccpco-card { padding: 1.5rem 1.75rem; border: 1px solid #e2e8f0 }`. The generator also loads `front.css` for the preview, and its signup cards use the same class. They inherit the admin padding and border, so images sit inside a 24 px frame that visitors never see.

**Why:** §16 *Familiarity*: be consistent "so people can predict what happens next". A preview that differs from the output defeats its purpose. §16 *Responsibility*: "Add previews". A preview only helps if it's accurate.

**Fix:** scope the admin rule so it stops leaking into the preview:

```css
.ccpco-gen-preview .ccpco-card { padding: 0; border: 0; }
```

The longer-term fix is to rename the admin panel class to `.ccpco-panel`.

### 3. Closed signups become hard to read (High)

**What:** `.ccpco-closed { opacity: .72 }` fades the whole card, text included. The muted meta color `#64748b` drops to about 3:1 on white. The "Registration Closed" pill, `#94a3b8` on `#e2e8f0`, is about 2:1, and that's the line that tells people why they can't register.

**Why:** §12 *Vibrancy*: keep text legible, and "use higher-contrast" rather than faded gray. §16 *Simplicity*: "the most important thing is the most obvious."

**Fix:** fade only the artwork and keep the text at full contrast:

```css
.ccpco-closed { opacity: 1; }
.ccpco-closed .ccpco-card-image { opacity: .6; filter: grayscale(.4); }
.ccpco-btn-disabled { background: #f1f5f9; color: #475569 !important; border: 1px dashed #cbd5e1; }
```

### 4. Cards lift on hover but aren't links (Medium)

**What:** `.ccpco-card:hover` lifts 3 px and deepens the shadow, but only the Register button and the calendar menu inside it do anything.

**Why:** §16 *Familiarity*: "things that look the same must behave the same." This is the same issue as Groups #2, and both plugins should answer it the same way.

**Fix:** `.ccpco-card:hover { transform: none; }` and keep a shadow-only hover. Or stretch the Register link over the card with `::after { inset: 0 }`, keeping the calendar button above it with `position: relative; z-index: 1`.

### 5. Columns follow the window width, not the space the block has (Medium)

**What:** The tile grid switches column count with `@media (max-width: 960px / 600px)`. Inside a narrow Bricks column or sidebar on a wide screen, `cols=4` still renders four columns, each about 150 px wide. The Groups plugin already uses container queries for exactly this.

**Why:** §16 *Flexibility*: "Adapt to the platform… and to the situation." §16 *Familiarity*: be consistent across your own plugins.

**Fix:**

```css
.ccpco-signup-list { container-type: inline-size; }
@container (max-width: 760px) { .ccpco-display-tiles.ccpco-cols-3, .ccpco-display-tiles.ccpco-cols-4 { grid-template-columns: repeat(2, 1fr); } }
@container (max-width: 480px) { .ccpco-display-tiles { grid-template-columns: 1fr; } }
```

### 6. The live preview is slow and blanks between updates (Medium)

**What:** Each change waits 700 ms (800 ms on first load), then swaps the whole preview for "Loading preview…". The panel collapses and re-expands on every keystroke pause. Responses aren't cancelled, so a slow older response can replace a newer one.

**Why:** §1 *Response*: "Audit debounces, artificial timers…" and "Feedback must be continuous during the interaction."

**Fix:**

```js
var ctrl;
function doPreview(){
  if (ctrl) ctrl.abort(); ctrl = new AbortController();
  frame.setAttribute('aria-busy', 'true'); frame.style.opacity = '.55'; // keep the old preview visible
  fetch(CCPCOAdmin.ajax_url, { method: 'POST', body: data, signal: ctrl.signal })
    .then(r => r.json()).then(res => { frame.innerHTML = res.success ? res.data : '<p class="ccpco-admin-notice">Preview error.</p>'; })
    .catch(e => { if (e.name !== 'AbortError') frame.innerHTML = '<p class="ccpco-admin-notice">Preview failed.</p>'; })
    .finally(() => { frame.removeAttribute('aria-busy'); frame.style.opacity = ''; });
}
// debounce: 250ms
```

### 7. The "Free" label fails contrast (Medium)

**What:** `.ccpco-price-free { color: #16a34a }` is about 3.3:1 on white at 14 px, below the 4.5:1 minimum for body text.

**Why:** §16 *Craft*: color must be legible as well as meaningful. §14 `prefers-contrast`.

**Fix:** `.ccpco-price-free { color: #15803d; }` (about 5:1).

### 8. Buttons don't react on press, and hover makes them lighter (Medium)

**What:** `.ccpco-btn-primary:hover` applies `filter: brightness(1.12)`, which lowers the contrast of the white text. No button has an `:active` state.

**Why:** §1 *Response*: "Highlight a button the instant it's pressed."

**Fix:**

```css
.ccpco-btn-primary:hover { filter: brightness(.92); }
.ccpco-btn:active, .ccpco-cal-trigger:active { transform: scale(.97); transition: transform 100ms ease-out; }
```

### 9. The generator tabs aren't real tabs (Low)

**What:** The five generator tabs are plain `<button>`s that toggle an `.active` class. Screen readers aren't told which tab is selected, and arrow keys don't move between them.

**Why:** §16 *Familiarity*: use the tab pattern people already know. §16 *Flexibility*.

**Fix:** add `role="tablist"` on the wrapper, `role="tab" aria-selected aria-controls="tab-…"` on each button, and `role="tabpanel"` on each panel. Handle ArrowLeft and ArrowRight in `admin.js`, alongside the existing click handler.

### 10. Text and tap targets are small (Low)

**What:** The calendar button text is 0.8rem in a roughly 30 px-tall target. The Closed badge is 0.7rem uppercase.

**Why:** §10: "Add \~10px of hysteresis/hit padding around the target." §15: small text wants slightly positive tracking and a legible size.

**Fix:** `.ccpco-cal-trigger { min-height: 40px; font-size: var(--ccpco-font-sm); }` and `.ccpco-closed-badge { font-size: .75rem; }`.

### 11. Card titles barely stand out from body text (Low)

**What:** Card titles are 1.05rem and the meta text is 0.875rem, a single step apart, so titles barely lead.

**Why:** §15: "Build hierarchy from weight + size + leading as a set."

**Fix:** `.ccpco-card-title { font-size: 1.2rem; line-height: 1.25; letter-spacing: -0.01em; }` and `.ccpco-detail-title { letter-spacing: -0.02em; line-height: 1.15; }`.

### 12. Brand blue is hard-coded in the admin (Low)

**What:** The admin headers and active tab use the literal `#1a4a8a` instead of the saved brand color or the WordPress admin scheme.

**Why:** §16 *Craft*: "colors that adapt."

**Fix:** `border-bottom-color: var(--wp-admin-theme-color, #1a4a8a);` throughout `admin.css`.

## Fixes applied in this pass

All 61 fixes are now applied on the `claude/quirky-feynman-2ppasd` branch of each repo: 29 in the first pass and the other 32 in a second. Each repo also has the `apple-design` skill and a Markdown copy of its section under `.claude/`, which the release zip excludes.

| Plugin | First pass | Second pass | Also changed |
| --- | --- | --- | --- |
| Sermon Suite | 1–7, part of 8, 9 and 11 | Rest of 8, 9 and 11; 10, 12–17 | Emoji removed from every admin screen, not only the examples cited |
| Groups | 1–4, 7 | 5, 6, 8–12 | Saving credentials now runs the connection test automatically |
| PCO | 1–4, 7, 8 | 5, 6, 9–12 | Fixed an older bug: on phones, 3- and 4-column grids never dropped to one column |
| PCO TEC | 1–8 | 9–12, plus the destructive-button styling from #2 | — |
| Recurring Events | 4, 5, 8, part of 1 and 2 | Rest of 1 and 2; 3, 6, 7 | Removed dates go to the Trash, and a notice says what changed |

Check these behavior changes before merging:

- **Recurring Events:** dates a changed rule drops now go to the Trash, and `children_of()` ignores trashed dates. If the rule later wants that date again, it creates a fresh event instead of restoring the trashed one. Permanently deleting a parent, or rolling back a migration, still removes trashed dates as well.
- **PCO:** signup cards no longer clip their overflow, so the calendar menu can drop down. The grid is also wrapped in a new `.ccpco-signup-list-wrap` div for the container queries. Check any theme CSS that targets the grid's parent.
- **Sermon Suite:** corner radii now follow a five-step scale (3 px becomes 4 px, 10 px becomes 12 px, pills become 999 px), and grid card titles drop from 1.4× to 1.1×. Expect small visual differences.
- **Groups:** the Secret field now loads blank. Leaving it blank keeps the saved secret.

**Verification.** The WordPress test suites still can't run here, because the network policy blocks `wordpress.org` and `playground.wordpress.net`. Every changed PHP file passes `php -l`, and every changed script, including inline `<script>` blocks, passes `node --check`. The headless-Chromium harness now runs 64 checks against each plugin's real CSS and JS, and all pass. The Recurring Events next-dates preview was checked against a stubbed admin-ajax response, so its server endpoint is untested. Run `npm test` in each repo locally before merging.
