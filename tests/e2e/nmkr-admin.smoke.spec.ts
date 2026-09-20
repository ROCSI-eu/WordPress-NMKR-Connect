import { expect, test } from '@playwright/test';
import { cssAttrValue, env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

test.describe('NMKR Connect WordPress admin smoke test', () => {
  test('loads admin, plugin pages, and safe Phase 1 controls', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const dashboardPath = env('NMKR_DASHBOARD_PATH', '/wp-admin/admin.php?page=nmkr-connect-dashboard');
    const settingsPath = env('NMKR_SETTINGS_PATH', '/wp-admin/options-general.php?page=nmkr-connect-settings');
    const pluginSlug = env('NMKR_PLUGIN_SLUG', 'connector-for-nmkr/nmkr-connect.php');
    const runRealSync = env('RUN_REAL_SYNC', 'false') === 'true';

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

    const apiKeyField = page.locator('#nmkr_api_key');
    await expect(apiKeyField).toBeVisible();
    await expect(apiKeyField).toHaveAttribute('type', 'password');

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
