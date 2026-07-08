import { expect, Page, test } from '@playwright/test';

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

function cssAttrValue(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

async function handleAdminEmailVerification(page: Page): Promise<void> {
  const emailVerificationHeading = page.getByRole('heading', {
    name: /administration email verification/i,
  });

  if (await emailVerificationHeading.isVisible({ timeout: 3000 }).catch(() => false)) {
    const confirmButton = page.getByRole('button', { name: /email is correct/i });

    if (await confirmButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await confirmButton.click();
      await page.waitForLoadState('domcontentloaded');
    }
  }
}

async function expectWpAdmin(page: Page): Promise<void> {
  await expect(page).toHaveURL(/wp-admin/);
  await expect(page.locator('#wpadminbar, #adminmenu, #wpbody-content').first()).toBeVisible();
}

test.describe('NMKR Connect WordPress admin smoke test', () => {
  test('loads admin, plugin pages, and safe Phase 1 controls', async ({ page }) => {
    const baseUrl = requireEnv('WP_BASE_URL');
    const username = requireEnv('WP_ADMIN_USER');
    const password = requireEnv('WP_ADMIN_PASSWORD');
    const adminPath = env('WP_ADMIN_PATH', '/wp-admin');
    const dashboardPath = env('NMKR_DASHBOARD_PATH', '/wp-admin/admin.php?page=nmkr-connect-dashboard');
    const settingsPath = env('NMKR_SETTINGS_PATH', '/wp-admin/options-general.php?page=nmkr-connect-settings');
    const pluginSlug = env('NMKR_PLUGIN_SLUG', 'nmkr-connect/nmkr-connect.php');
    const runRealSync = env('RUN_REAL_SYNC', 'false') === 'true';

    await page.goto(urlFor(baseUrl, '/wp-login.php'));
    await expect(page.locator('#user_login')).toBeVisible();
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();

    await page.waitForLoadState('domcontentloaded');
    await handleAdminEmailVerification(page);
    await page.goto(urlFor(baseUrl, adminPath));
    await expectWpAdmin(page);

    await page.goto(urlFor(baseUrl, '/wp-admin/plugins.php'));
    await expectWpAdmin(page);

    const pluginRow = page.locator(`tr[data-plugin="${cssAttrValue(pluginSlug)}"]`).first();
    await expect(pluginRow).toBeVisible();
    await expect(pluginRow).toContainText(/deactivate/i);

    await page.goto(urlFor(baseUrl, dashboardPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);
    await expect(page.locator('body')).toContainText(/NMKR|Connect|Dashboard/i);

    await page.goto(urlFor(baseUrl, settingsPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-settings/);
    await expect(page.locator('body')).toContainText(/NMKR|Connect|Settings/i);

    const apiKeyField = page
      .locator('input[type="password"], input[name*="api" i], input[id*="api" i]')
      .first();

    await expect(apiKeyField).toBeVisible();

    const apiKeyValue = await apiKeyField.inputValue();

    if (apiKeyValue) {
      expect(apiKeyValue.trim().length).toBeGreaterThan(0);
    }

    const syncProfileControl = page
      .locator('select[name*="profile" i], input[name*="profile" i], select[id*="profile" i], input[id*="profile" i]')
      .first();

    await expect(syncProfileControl).toBeVisible();
    await expect(page.locator('body')).toContainText(/sync/i);

    if (!runRealSync) {
      test.info().annotations.push({
        type: 'phase-1',
        description: 'Real NMKR sync was intentionally not started because RUN_REAL_SYNC is not true.',
      });

      return;
    }

    throw new Error('RUN_REAL_SYNC=true is reserved for a later phase and is not implemented in this smoke test.');
  });
});
