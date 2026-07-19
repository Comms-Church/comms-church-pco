---
name: cut-a-release
description: Full release runbook for the Comms.Church PCO Registrations plugin — lint, smoke test, bump the version, commit, tag, push, verify the GitHub release, regenerate screenshots, and refresh the comms.church landing page. Use when the user says "cut a release", "ship it", "release", "publish a new version", or names a version to release.
---

# Cut a Release — Comms.Church PCO Registrations

Run every step below in order. Stop and report if any step fails — never tag a broken state.

Repo facts:
- The version lives in **two places** in `comms-church-pco.php`: the `Version:` plugin header and the `define( 'CCPCO_VERSION', '...' )` constant. They must match each other and the tag, or `.github/workflows/release.yml` fails the build on purpose.
- Pushing a tag `vX.Y.Z` triggers the workflow, which builds `comms-church-pco.zip` (dev tooling stripped) and publishes a GitHub Release with auto-generated notes from commit messages. Never build or upload a zip manually; never create the release on github.com.
- The plugin's built-in updater (`includes/class-ccpco-updater.php`) checks GitHub Releases, so installed sites see the update in **Plugins → Updates** within a day. Nothing else to deploy.
- Release notes = commit messages since the last tag. Write the release commit in plain, user-facing language — church admins read it in the WordPress "View details" popup.
- The zip build in `release.yml` excludes dev tooling (`node_modules`, `tests/`, `test-results/`, `playwright-report/`, `screenshots/`, `package*.json`, `playwright.config.js`, `.claude`, `deploy.sh`). If you add a new top-level dev file or folder, add a matching exclude there or it ships to every church.
- Git pushes authenticate through the GitHub CLI (`~/.local/bin/gh`, logged in as AddisonRoberts; the https remote `https://github.com/Comms-Church/comms-church-pco.git` uses gh's credential helper) — same as the other two Comms.Church plugins. SSH is reserved for the comms.church → Kinsta site pipeline, not the plugin repos. If a push fails with an auth error, stop and tell the user to re-run `gh auth login` (or `gh auth refresh -s workflow` if the error mentions workflow scope). Never try to work around auth yourself.
- `deploy.sh` is the **legacy** zip-import path (syncs a downloaded zip into the repo, then tags). Ignore it for normal releases from this working tree.

## 1. Preflight

```bash
git status
git fetch origin
git log origin/main..HEAD --oneline   # unpushed commits
git log $(git describe --tags --abbrev=0)..HEAD --oneline  # commits since last release
```

- Must be on `main`. If behind `origin/main`, pull (rebase) first.
- Uncommitted changes are fine — they become part of the release commit. List them so the user knows what's shipping.
- If there is nothing new since the last tag and no uncommitted changes, say so and stop.

## 2. Lint the PHP

No system PHP is installed. Use the PHP bundled with Local (find it fresh each time; the version dir changes when Local updates):

```bash
PHP_BIN=$(find "$HOME/Library/Application Support/Local/lightning-services" \
  -type f -name php -path '*darwin-arm64/bin/php' 2>/dev/null | sort -V | tail -1)
for f in comms-church-pco.php uninstall.php includes/*.php includes/bricks/*.php; do
  "$PHP_BIN" -l "$f" || exit 1
done
```

Any syntax error stops the release. If no PHP binary is found, warn the user and ask whether to proceed on the smoke suite alone.

## 3. Run the smoke suite — green, or no release

```bash
npm test
```

This boots a real WordPress in Node (WordPress Playground, no Docker/PHP needed) with the plugin active and an `/events/` page seeded with `[pco_signups]` (`tests/blueprint.json`), then runs Playwright smoke tests (`tests/smoke.spec.js`): the front end, the shortcode page, and every admin screen — asserting no PHP fatals/warnings/notices anywhere. There are no PCO credentials in Playground; the plugin must degrade gracefully, and the suite verifies exactly that.

- First run downloads WordPress (~1 min); later runs take ~30s.
- If `node_modules` is missing, run `npm install` first (if npm hits an EACCES cache error, add `--cache <scratchpad>/npm-cache` — the user's `~/.npm` has root-owned files).
- **Any failure = stop.** Report which test failed and what it saw. Do not tag.
- A PHP fatal shows up as page text, not an HTTP error — the suite greps for `Fatal error|Parse error|Warning:|Notice:` in every page body.

## 4. Pick the version

- If the user named a version, use it.
- Otherwise read the current version from `comms-church-pco.php` and bump based on what's shipping: bug fixes only → patch; new features/blocks/shortcodes → minor; breaking or major rework → major. State the chosen version and why; only ask if genuinely ambiguous.

## 5. Bump the version in BOTH places

Edit `comms-church-pco.php`:
- ` * Version:           X.Y.Z` (plugin header — preserve column alignment)
- `define( 'CCPCO_VERSION', 'X.Y.Z' );`

Then verify they match:

```bash
grep -n "Version:\|CCPCO_VERSION" comms-church-pco.php | head -4
```

## 6. Commit, tag, push

```bash
git add -A
git commit -m "vX.Y.Z — <plain-language summary of what changed for users>"
git tag vX.Y.Z
git push origin main vX.Y.Z
```

## 7. Verify the release actually published

Poll the public GitHub API (no auth needed) until the release exists with the zip attached — usually under 2 minutes. Poll every ~20s, give up after ~5 minutes:

```bash
curl -s https://api.github.com/repos/Comms-Church/comms-church-pco/releases/tags/vX.Y.Z | grep -E '"name"|"browser_download_url"'
curl -s "https://api.github.com/repos/Comms-Church/comms-church-pco/actions/runs?per_page=1" | grep -E '"status"|"conclusion"|"html_url"' | head -6
```

Success = the release exists and has a `comms-church-pco.zip` asset, and the workflow run concluded `success`.

## 8. Regenerate screenshots

```bash
npm run screenshots
```

Writes fresh full-page captures of the admin UI (settings, shortcode generator) to `screenshots/` — gitignored, regenerable, for marketing/docs use. Send the user any screenshots that changed meaningfully.

## 9. Refresh the comms.church landing page

The plugin's public landing page is `https://comms.church/resources/pco-registrations/`, rendered by the theme template `page-pco-registrations.php` in the comms.church theme repo:

```
/Users/addisonroberts/Local Sites/commschurch-site/app/public/wp-content/themes/comms-church/
```

If this release added or changed user-facing features, update that template's feature/install copy to match, then deploy with the theme's local→Kinsta pipeline (commits, pushes to GitHub, and `git pull`s on Kinsta over SSH):

```bash
cd "/Users/addisonroberts/Local Sites/commschurch-site/app/public/wp-content/themes/comms-church"
./deploy.sh "PCO Registrations vX.Y.Z — <what changed>"
```

**Show the user the page diff and get an explicit OK before running `deploy.sh`** — it publishes straight to production. For a fixes-only patch release, skip this step and say so.

## 10. Report

Tell the user: the version shipped, the release URL (`https://github.com/Comms-Church/comms-church-pco/releases/tag/vX.Y.Z`), whether the comms.church page was updated, and that installed sites pick up the update automatically (or instantly via "Check for updates" on their Plugins screen).

## If the build fails

Most common cause: tag ↔ header version mismatch. Recovery:

```bash
git tag -d vX.Y.Z
git push origin :refs/tags/vX.Y.Z
# fix comms-church-pco.php, commit, push, then re-tag:
git tag vX.Y.Z
git push origin vX.Y.Z
```

If the bad tag already produced a public release, cut a fresh patch version instead of force-moving the tag. Report the Actions run URL (`https://github.com/Comms-Church/comms-church-pco/actions`) so the user can see the log.
