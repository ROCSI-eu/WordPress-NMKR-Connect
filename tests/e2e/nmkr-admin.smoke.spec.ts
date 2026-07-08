import { expect, test, type Page } from '@playwright/test';

function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) throw new Error(`${name} is required for NMKR admin smoke tests.`);
  return value;
}

function siteUrl(path: string): string {
  const base = requiredEnv('WP_BASE_URL').replace(/\/$/, '');
  return `${base}${path.startsWith('/') ? path : `/${path}`}`;
}

function pathExpectation(path: string): RegExp {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const escapedPath = normalizedPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp(escapedPath);
}

async function expectNoWordPressFatal(page: Page) {
  const body = page.locator('body');
  await expect(body).not.toContainText(/There has been a critical error|Fatal error|Parse error|Warning:\s|Notice:\s/i);
}

async function loginToWordPressAdmin(page: Page) {
  const adminPath = process.env.WP_ADMIN_PATH || '/wp-admin';
  const username = requiredEnv('WP_ADMIN_USER');
  const password = requiredEnv('WP_ADMIN_PASSWORD');

  await page.goto(siteUrl(`${adminPath.replace(/\/$/, '')}/`));

  if (page.url().includes('wp-login.php') || await page.locator('#loginform').isVisible()) {
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
  }

  await expect(page).toHaveURL(/\/wp-admin\//);
  await expect(page.locator('#wpadminbar'), 'WordPress admin toolbar should be visible after login.').toBeVisible();
  await expectNoWordPressFatal(page);
}

test.describe('NMKR Connect WordPress admin smoke tests', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPressAdmin(page);
  });

  test('WordPress admin is reachable and NMKR Connect is active', async ({ page }) => {
    await page.goto(siteUrl('/wp-admin/plugins.php'));
    await expectNoWordPressFatal(page);

    const pluginRow = page.locator('tr.active[data-plugin*="nmkr-connect"]').filter({ hasText: /NMKR Connect/i });
    await expect(pluginRow, 'NMKR Connect should appear as an active plugin without exposing credentials.').toBeVisible();
  });

  test('NMKR dashboard and settings pages load safely', async ({ page }) => {
    const dashboardPath = process.env.NMKR_DASHBOARD_PATH || '/wp-admin/admin.php?page=nmkr-connect-dashboard';
    const settingsPath = process.env.NMKR_SETTINGS_PATH || '/wp-admin/options-general.php?page=nmkr-connect-settings';

    await page.goto(siteUrl(dashboardPath));
    await expect(page, 'NMKR dashboard should stay on the configured dashboard URL.').toHaveURL(pathExpectation(dashboardPath));
    await expectNoWordPressFatal(page);
    await expect(page.locator('body'), 'NMKR dashboard page should contain NMKR-specific page content.').toContainText(/NMKR/i);

    await page.goto(siteUrl(settingsPath));
    await expect(page, 'NMKR settings should stay on the configured settings URL.').toHaveURL(pathExpectation(settingsPath));
    await expectNoWordPressFatal(page);
    await expect(page.locator('body'), 'NMKR settings page should contain NMKR-specific page content.').toContainText(/NMKR/i);

    const apiKeyInput = page.locator('#nmkr_api_key, input[name="nmkr_connect_options[api_key]"]').first();
    await expect(apiKeyInput, 'NMKR API key input should be present; its value is never printed.').toBeVisible();

    const syncProfileControl = page.locator('#nmkr_sync_profile, select[name="nmkr_connect_options[sync_profile]"]').first();
    await expect(syncProfileControl, 'NMKR sync profile control should be present.').toBeVisible();

    await expect(page.locator('#submit, input[type="submit"][name="submit"], button[type="submit"]').first()).toBeVisible();

    const hasApiKey = await apiKeyInput.evaluate((input) => (input as HTMLInputElement).value.length > 0);
    expect(hasApiKey, 'Configured NMKR API key field should be non-empty; the value is intentionally never logged.').toBe(true);

    if (process.env.RUN_REAL_SYNC === 'true') {
      test.skip(true, 'RUN_REAL_SYNC=true is reserved for a future phase; Phase 1 does not start real NMKR syncs.');
    } else {
      expect(process.env.RUN_REAL_SYNC || 'false').toBe('false');
    }
  });
});
