import { expect, test } from '@playwright/test';

function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) throw new Error(`${name} is required for NMKR admin smoke tests.`);
  return value;
}

function siteUrl(path: string): string {
  const base = requiredEnv('WP_BASE_URL').replace(/\/$/, '');
  return `${base}${path.startsWith('/') ? path : `/${path}`}`;
}

async function expectNoWordPressFatal(page: import('@playwright/test').Page) {
  const body = page.locator('body');
  await expect(body).not.toContainText(/There has been a critical error|Fatal error|Parse error|Warning:\s|Notice:\s/i);
}

test.describe('NMKR Connect WordPress admin smoke tests', () => {
  test.beforeEach(async ({ page }) => {
    const adminPath = process.env.WP_ADMIN_PATH || '/wp-admin';
    const username = requiredEnv('WP_ADMIN_USER');
    const password = requiredEnv('WP_ADMIN_PASSWORD');

    await page.goto(siteUrl(`${adminPath.replace(/\/$/, '')}/`));
    if (page.url().includes('wp-login.php')) {
      await page.locator('#user_login').fill(username);
      await page.locator('#user_pass').fill(password);
      await page.getByRole('button', { name: /log in/i }).click();
    }

    await expect(page).toHaveURL(/\/wp-admin\//);
    await expect(page.getByRole('heading', { name: /dashboard/i }).or(page.locator('#wpadminbar'))).toBeVisible();
    await expectNoWordPressFatal(page);
  });

  test('WordPress admin is reachable and NMKR Connect is active', async ({ page }) => {
    await page.goto(siteUrl('/wp-admin/plugins.php'));
    await expectNoWordPressFatal(page);

    const pluginRow = page.locator('tr.active[data-plugin*="nmkr-connect"], tr.active').filter({ hasText: /NMKR Connect/i });
    await expect(pluginRow, 'NMKR Connect should appear as an active plugin without exposing credentials.').toBeVisible();
  });

  test('NMKR dashboard and settings pages load safely', async ({ page }) => {
    const dashboardPath = process.env.NMKR_DASHBOARD_PATH || '/wp-admin/admin.php?page=nmkr-connect-dashboard';
    const settingsPath = process.env.NMKR_SETTINGS_PATH || '/wp-admin/options-general.php?page=nmkr-connect-settings';

    await page.goto(siteUrl(dashboardPath));
    await expectNoWordPressFatal(page);
    await expect(page.getByRole('heading', { name: /NMKR|Dashboard/i }).or(page.locator('body', { hasText: /NMKR Connect|Dashboard/i }))).toBeVisible();

    await page.goto(siteUrl(settingsPath));
    await expectNoWordPressFatal(page);
    await expect(page.getByRole('heading', { name: /NMKR Connect Settings|Settings/i })).toBeVisible();
    await expect(page.getByLabel(/API Key/i).or(page.locator('#nmkr_api_key'))).toBeVisible();
    await expect(page.locator('#nmkr_sync_profile, select[name="nmkr_connect_options[sync_profile]"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /save settings/i })).toBeVisible();

    const apiKeyInput = page.locator('#nmkr_api_key');
    await expect(apiKeyInput).toHaveCount(1);
    const hasApiKey = await apiKeyInput.evaluate((input) => (input as HTMLInputElement).value.length > 0);
    expect(hasApiKey, 'Configured NMKR API key field should be non-empty; the value is intentionally never logged.').toBe(true);

    if (process.env.RUN_REAL_SYNC === 'true') {
      test.skip(true, 'RUN_REAL_SYNC=true is reserved for a future phase; Phase 1 does not start real NMKR syncs.');
    } else {
      expect(process.env.RUN_REAL_SYNC || 'false').toBe('false');
    }
  });
});
