import { expect, test } from '@playwright/test';

const requiredEnv = ['WP_BASE_URL', 'WP_ADMIN_USER', 'WP_ADMIN_PASSWORD'] as const;

function env(name: string, fallback = ''): string {
  return process.env[name] || fallback;
}

function requireEnv(name: (typeof requiredEnv)[number]): string {
  const value = process.env[name];

  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }

  return value;
}

function urlFor(baseUrl: string, path: string): string {
  return new URL(path, baseUrl).toString();
}

test.describe('NMKR Connect WordPress admin smoke test', () => {
  test('loads admin, plugin pages, and safe Phase 1 controls', async ({ page }) => {
    const baseUrl = requireEnv('WP_BASE_URL');
    const username = requireEnv('WP_ADMIN_USER');
    const password = requireEnv('WP_ADMIN_PASSWORD');
    const adminPath = env('WP_ADMIN_PATH', '/wp-admin');
    const dashboardPath = env('NMKR_DASHBOARD_PATH', '/wp-admin/admin.php?page=nmkr-connect-dashboard');
    const settingsPath = env('NMKR_SETTINGS_PATH', '/wp-admin/options-general.php?page=nmkr-connect-settings');
    const runRealSync = env('RUN_REAL_SYNC', 'false') === 'true';

    await page.goto(urlFor(baseUrl, '/wp-login.php'));
    await expect(page.locator('#user_login')).toBeVisible();
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();

    await page.waitForURL(/wp-admin/);
    await expect(page.locator('body.wp-admin')).toBeVisible();

    await page.goto(urlFor(baseUrl, '/wp-admin/plugins.php'));
    const pluginRow = page.locator('tr[data-slug="nmkr-connect"], tr:has-text("NMKR Connect")').first();
    await expect(pluginRow).toBeVisible();
    await expect(pluginRow).toContainText(/active|deactivate/i);

    await page.goto(urlFor(baseUrl, adminPath));
    await expect(page.locator('body.wp-admin')).toBeVisible();

    await page.goto(urlFor(baseUrl, dashboardPath));
    await expect(page.locator('body.wp-admin')).toBeVisible();
    await expect(page.locator('body')).toContainText(/NMKR|Connect|Dashboard/i);

    await page.goto(urlFor(baseUrl, settingsPath));
    await expect(page.locator('body.wp-admin')).toBeVisible();
    await expect(page.locator('body')).toContainText(/NMKR|Connect|Settings/i);

    const apiKeyField = page
      .locator('input[type="password"], input[name*="api" i], input[id*="api" i]')
      .first();
    await expect(apiKeyField).toBeVisible();

    const apiKeyValue = await apiKeyField.inputValue();
    if (apiKeyValue.length > 0) {
      expect(apiKeyValue.trim().length).toBeGreaterThan(0);
    }

    const syncProfileControl = page
      .locator('select[name*="profile" i], input[name*="profile" i], select[id*="profile" i], input[id*="profile" i]')
      .first();
    await expect(syncProfileControl).toBeVisible();

    if (!runRealSync) {
      test.skip(true, 'Phase 1 proof-of-concept skips real NMKR sync behavior unless RUN_REAL_SYNC=true.');
    }

    await expect(page.locator('body')).toContainText(/sync/i);
  });
});
