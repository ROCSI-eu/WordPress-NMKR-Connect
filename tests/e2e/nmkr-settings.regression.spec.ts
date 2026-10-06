import { expect, Locator, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

async function expectType(locator: Locator, type: string): Promise<void> {
  await expect(locator).toBeAttached();
  await expect(locator).toHaveAttribute('type', type);
}

async function expectSelectOptions(select: Locator, values: string[]): Promise<void> {
  await expect(select).toBeVisible();

  for (const value of values) {
    await expect(select.locator(`option[value="${value}"]`)).toHaveCount(1);
  }
}

test.describe('NMKR Connect settings page regression', () => {
  test('loads settings controls without changing persisted options', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const settingsPath = env('NMKR_SETTINGS_PATH', '/wp-admin/options-general.php?page=nmkr-connect-settings');

    await page.goto(urlFor(baseUrl, settingsPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-settings/);
    await expect(page.locator('body')).toContainText(/NMKR|Connect|Settings/i);

    await expect(page.locator('form[action="options.php"][method="post"]')).toBeVisible();

    await expect(page.getByRole('heading', { name: 'Settings', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'NMKR API connection' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Synchronization profile' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Measurement mode and privacy controls' })).toBeVisible();

    await expect(page.locator('#nmkr-settings-sync-advanced')).not.toHaveAttribute('open', '');
    await expect(page.locator('#nmkr-settings-diagnostics')).not.toHaveAttribute('open', '');

    await expectType(page.locator('#nmkr_api_key'), 'password');
    await expectType(page.locator('#toggle_api_key_visibility'), 'button');

    await expectSelectOptions(page.locator('#nmkr_sync_profile'), ['light', 'balanced', 'aggressive', 'custom']);
    await expectType(page.locator('#nmkr_sync_batch_size'), 'number');
    await expectType(page.locator('#nmkr_sync_batch_delay'), 'number');
    await expectType(page.locator('input[name="nmkr_connect_options[sync_initial_interval]"]'), 'number');
    await expectType(page.locator('input[name="nmkr_connect_options[sync_max_interval]"]'), 'number');
    await expectType(page.locator('input[name="nmkr_connect_options[sync_interval_increase]"]'), 'number');
    await expectType(page.locator('input[name="nmkr_connect_options[sync_interval_decrease]"]'), 'number');
    await expectType(page.locator('input[name="nmkr_connect_options[sync_max_errors]"]'), 'number');

    await expectType(page.locator('#nmkr_debug_enabled'), 'checkbox');
    await expectType(page.locator('#nmkr_log_to_debug_file'), 'checkbox');
    await expectType(page.locator('#nmkr_log_to_dashboard'), 'checkbox');
    await expectType(page.locator('#nmkr_api_debug_enabled'), 'checkbox');
    await expectType(page.locator('#nmkr_sync_debug_enabled'), 'checkbox');
    await expectType(page.locator('#nmkr_ui_debug_enabled'), 'checkbox');
    await expectType(page.locator('#nmkr_performance_debug_enabled'), 'checkbox');
    await expectType(page.locator('#nmkr_log_throttle_enabled'), 'checkbox');
    await expectType(page.locator('#nmkr_log_retention_limit'), 'number');
    await expect(page.locator('#nmkr-dashboard-sync-logging-status')).toBeVisible();
    await expect(page.locator('#nmkr-dashboard-sync-logging-status-text')).toBeVisible();

    await expectSelectOptions(page.locator('#nmkr_analytics_mode'), ['off', 'custom', 'ga4', 'both']);
    await expectType(page.locator('#nmkr_ga4_measurement_id'), 'text');
    await expectType(page.locator('#nmkr_ga4_api_secret'), 'password');
    await expectType(page.locator('#nmkr_analytics_retention_days'), 'number');
    await expectType(page.locator('#nmkr_analytics_sample_rate'), 'number');
    await expectType(page.locator('#nmkr_analytics_track_logged_in'), 'checkbox');
    await expectType(page.locator('#nmkr_analytics_require_consent'), 'checkbox');
    await expectType(page.locator('#nmkr_analytics_remove_on_uninstall'), 'checkbox');
    await expectType(page.locator('#nmkr_analytics_debug'), 'checkbox');

    await expect(page.getByRole('button', { name: 'Save Settings' })).toBeVisible();
    await expectType(page.locator('#nmkr-reset-defaults'), 'button');

    await page.locator('#nmkr-settings-sync-advanced > summary').click();
    await expect(page.locator('#nmkr_sync_batch_size')).toBeVisible();

    await page.locator('#nmkr-settings-diagnostics > summary').click();
    await expect(page.locator('#nmkr_debug_enabled')).toBeVisible();

    await page.setViewportSize({ width: 390, height: 900 });
    const settingsWrap = page.locator('.nmkr-settings-wrap');
    const settingsFitViewport = await settingsWrap.evaluate(
      (element) => element.scrollWidth <= element.clientWidth + 1,
    );
    expect(settingsFitViewport).toBe(true);
  });
});
