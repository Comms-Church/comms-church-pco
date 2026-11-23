// Release smoke suite — green, or no release.
// Runs against WordPress Playground with the plugin active and an /events/
// page seeded with the [pco_signups] shortcode (tests/blueprint.json).
// No Planning Center credentials are configured, so the plugin must degrade
// gracefully — a friendly notice, never a fatal.
const { test, expect } = require('@playwright/test');

// PHP problems surface as text in the page body, not HTTP errors.
const PHP_ERRORS = /Fatal error|Parse error|Warning: |Notice: |Deprecated: /;

async function expectClean(page) {
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(PHP_ERRORS);
}

test.describe('Public pages', () => {
  test('front page renders without PHP errors', async ({ page }) => {
    await page.goto('/');
    await expectClean(page);
  });

  test('shortcode page renders without PHP errors and registers shortcodes', async ({ page }) => {
    await page.goto('/?pagename=events');
    await expectClean(page);
    const body = await page.locator('body').innerText();
    // Raw shortcode text in output means the shortcode failed to register.
    expect(body).not.toContain('[pco_');
  });

  // ?pco_mock=1 switches on a fake Planning Center (mu-plugins/smoke-pco-mock.php)
  // for this one request. PCO's new_registration_url is the Church Center form,
  // which sends a signed-out visitor to a login page instead of the event.
  test('Register links open the Church Center event page, not the login-gated form', async ({ page }) => {
    await page.goto('/?pagename=register&pco_mock=1');
    await expectClean(page);
    const links = page.locator('a.ccpco-btn-primary');
    // Card, detail view and standalone button.
    await expect(links).toHaveCount(3);
    for (const href of await links.evaluateAll((as) => as.map((a) => a.getAttribute('href')))) {
      expect(href).toBe('https://smoke.churchcenter.com/registrations/events/777');
    }
  });
});

test.describe('Admin pages', () => {
  const adminPages = [
    ['ccpco-settings', 'settings'],
    ['ccpco-generator', 'shortcode generator'],
  ];

  for (const [slug, label] of adminPages) {
    test(`${label} (options-general.php?page=${slug}) renders without PHP errors`, async ({ page }) => {
      await page.goto(`/wp-admin/options-general.php?page=${slug}`);
      // Playground --login auto-authenticates as admin; if we ended up on a
      // login form, auth is broken and every admin assertion is meaningless.
      await expect(page.locator('#wpwrap')).toBeVisible();
      await expectClean(page);
    });
  }

  test('plugins screen renders with the plugin active', async ({ page }) => {
    await page.goto('/wp-admin/plugins.php');
    await expect(page.locator('#wpwrap')).toBeVisible();
    await expectClean(page);
    const body = await page.locator('body').innerText();
    expect(body).toContain('Planning Center Registrations');
  });
});
