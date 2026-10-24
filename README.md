# Comms.Church — Planning Center Registrations

Display Planning Center Registrations events on any WordPress site via Gutenberg blocks and shortcodes. API credentials are stored server-side and never exposed to visitors.

## Installation (first time on a new site)

This plugin is not on WordPress.org, so the first install is manual:

1. Download the latest `comms-church-pco.zip` from [Releases](https://github.com/Comms-Church/comms-church-pco/releases).
2. In WordPress: **Plugins → Add New → Upload Plugin**.
3. Upload the zip, install, activate.
4. Go to **Settings → PCO Registrations** and enter your Planning Center API credentials.

After this first install, the plugin checks GitHub for new releases automatically and updates show up in the normal **Plugins** and **Dashboard → Updates** screens — no need to repeat this process.

## Releasing a new version

1. Bump the version in **two places** in `comms-church-pco.php`:
   - The `Version:` line in the file header comment
   - The `define( 'CCPCO_VERSION', '...' )` constant
   
   These must match each other and the git tag, or the release Action will fail on purpose.

2. Commit and push (GitHub Desktop or `git push`).

3. Create a GitHub Release with a tag matching `vX.X.X` (e.g. `v1.1.0`).

4. GitHub Actions automatically builds `comms-church-pco.zip` and attaches it to the release. Sites with the plugin installed will see the update within ~24 hours (or instantly via the "Check for updates" link next to the plugin on the Plugins screen).

## How the updater works

`includes/class-ccpco-updater.php` hooks into WordPress's native plugin-update system. It polls the public GitHub Releases API (`api.github.com/repos/Comms-Church/comms-church-pco/releases/latest`) once a day, compares the tag to the installed version, and if newer, points WordPress's built-in updater at the `.zip` asset attached to that release. No third-party service, no API keys required — GitHub's public API is unauthenticated for public repos.

## Repo structure

```
comms-church-pco/
├── comms-church-pco.php       # Main plugin file, version lives here
├── includes/
│   ├── class-ccpco-updater.php   # GitHub-based auto-updater
│   ├── class-ccpco-api.php       # PCO API client
│   ├── class-ccpco-cache.php     # Transient cache + circuit breaker
│   ├── class-ccpco-renderer.php  # Shared HTML output
│   ├── class-ccpco-shortcodes.php
│   ├── class-ccpco-blocks.php    # Gutenberg blocks
│   ├── class-ccpco-webhook.php   # Webhook receiver (ready for when PCO supports it)
│   └── class-ccpco-admin.php     # Settings + shortcode generator pages
├── assets/                    # CSS/JS for front end, admin, and block editor
└── .github/workflows/release.yml  # Builds & attaches zip on tag push
```

## Changelog

### 1.3.0
**Design and accessibility pass**
- Add to Calendar now drops down from its button and works from the keyboard (arrow keys, Escape, Tab closes it). Cards no longer clip it.
- Signup grids follow the space they're placed in, so a 3- or 4-column list in a narrow Bricks column or sidebar no longer squeezes into tiny columns. Fixed: on phones, 3- and 4-column grids never dropped to one column.
- Closed signups keep their text readable — only the artwork fades. Buttons respond on press, and the "Free" label meets contrast.
- Shortcode generator: the preview matches the live page, updates faster without blanking, and sits at full width; the tabs work with the keyboard.

### 1.2.0
**Bricks Builder support**
- Added three native Bricks elements — PCO Signup List, PCO Signup Card, and PCO Register Button — under a "Planning Center" category in the Bricks element panel, with full visual controls. Registered only when Bricks is active; Gutenberg blocks and shortcodes remain available everywhere.
- Fixed broken styling on Bricks sites: v1.1.0's conditional asset loading sniffed post_content for PCO shortcodes, but Bricks stores content in its own meta fields, so styles never loaded in the head. On Bricks sites the stylesheet now always loads in the head; Bricks elements also declare their assets via the proper enqueue_scripts() mechanism.
- Asset loading is now self-registering, so late/builder-context rendering can never produce unstyled output.
- Added an admin warning when the GCC-specific PCO plugin is active alongside this one (both register the same shortcode names and will conflict).


### 1.1.0
**Compatibility & stability**
- Added PHP 7.4 compatibility: polyfills for `str_contains`/`str_ends_with` (previously fataled on PHP < 8.0, common on shared church hosting) plus `Requires PHP` header and a graceful deactivation guard for even older PHP.
- Fixed cache invalidation: flushes previously wiped the stale fallback copies and the updater's release cache, defeating the circuit breaker. Now uses generation-based invalidation — flushes are instant, and stale/updater caches survive by design.
- Fixed cache-key mismatches left over from an earlier codebase (targeted flushes were silently missing).
- Fixed a bug where an empty cache-duration setting created never-expiring caches; TTL now has a 60-second floor.
- Any API failure (not just rate limits) now serves stale data to visitors instead of an error.

**Calendar (.ics) fixes**
- Fixed double URL-decoding that corrupted event titles containing `%` or `+`.
- All-day events now set the RFC 5545 exclusive end date (previously rendered wrong in some calendar apps).
- Calendar download links are now HMAC-signed, so the public .ics endpoint only serves files the site itself generated.

**Performance**
- Front-end CSS/JS now load only on pages that actually contain PCO content.
- List caches are keyed on data-affecting settings only — two differently styled blocks now share one API fetch.
- Category-filtered lists fetch a full page from the API so results aren't silently truncated.

**Polish**
- "Add to Calendar" dropdown flips downward when near the top of the viewport instead of clipping offscreen.
- Block editor: color panels now show whether a block uses a custom or global color, with a one-click reset to global.
- Webhook endpoint throttles failed-authentication logging.
- Added `uninstall.php` — deleting the plugin now removes all stored options (including API credentials) and caches.
- Added `prefers-reduced-motion` support and visible keyboard-focus styles.

### 1.0.0
- Initial release.
