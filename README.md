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
