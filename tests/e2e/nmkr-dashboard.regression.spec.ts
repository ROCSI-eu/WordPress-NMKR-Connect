import { expect, Locator, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

async function expectButtonElement(locator: Locator): Promise<void> {
  await expect(locator).toBeAttached();
  await expect(locator).toHaveJSProperty('tagName', 'BUTTON');
}

async function expectHiddenInput(locator: Locator): Promise<void> {
  await expect(locator).toBeAttached();
  await expect(locator).toHaveJSProperty('tagName', 'INPUT');
  await expect(locator).toHaveAttribute('type', 'hidden');
}

test.describe('NMKR Connect dashboard page regression', () => {
  test('loads dashboard structure and safe controls without mutating state', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const dashboardPath = env('NMKR_DASHBOARD_PATH', '/wp-admin/admin.php?page=nmkr-connect-dashboard');

    await page.goto(urlFor(baseUrl, dashboardPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);

    const dashboard = page.locator('.wrap.nmkr-dashboard');
    await expect(dashboard).toBeAttached();
    await expect(page.getByRole('heading', { name: 'NMKR Connect Dashboard' })).toBeVisible();

    const apiPanel = page.locator('.api-status-panel');
    await expect(apiPanel).toBeAttached();
    await expect(apiPanel).toContainText('API Connection Status');
    await expect(apiPanel.locator('#api-status')).toBeAttached();
    await expectButtonElement(apiPanel.locator('#refresh-api-status'));

    const syncPanel = page.locator('.sync-data');
    await expect(syncPanel).toBeAttached();
    await expect(syncPanel).toContainText('Data Synchronization');
    await expectButtonElement(syncPanel.locator('#nmkr-sync-button'));
    await expectButtonElement(syncPanel.locator('#nmkr-stop-sync-button'));

    await expectHiddenInput(page.locator('#nmkr-sync-nonce'));
    await expectHiddenInput(page.locator('#nmkr-dashboard-nonce'));

    const progressContainer = page.locator('#nmkr-sync-progress-container');
    await expect(progressContainer).toBeAttached();
    await expect(progressContainer).toHaveAttribute('role', 'progressbar');
    await expect(page.locator('#nmkr-sync-progress-bar')).toBeAttached();
    await expect(page.locator('#status-message')).toHaveAttribute('aria-live', 'polite');
    await expect(page.locator('#nmkr-sync-phase-label')).toBeAttached();
    await expect(page.locator('#active-sync-metrics')).toBeAttached();

    for (const selector of [
      '.total-projects-active',
      '.total-tokens-active',
      '.total-sync-duration-active',
      '.total-api-time-active',
      '.avg-response-time',
      '.api-requests',
      '.memory-usage',
    ]) {
      await expect(page.locator(selector).first()).toBeAttached();
    }

    const statisticsPanel = page.locator('.sync-statistics');
    await expect(statisticsPanel).toBeAttached();
    await expect(statisticsPanel).toContainText('Previous Synchronization Statistics');

    for (const selector of [
      '#last-synced',
      '#performance-stats',
      '#total-projects',
      '#total-tokens',
      '#total-sync-time',
      '#total-api-time',
      '#avg-response-time',
      '#request-count',
      '#memory-usage',
      '#performance-legend',
    ]) {
      await expect(statisticsPanel.locator(selector)).toBeAttached();
    }

    const debugLogsPanel = page.locator('.debug-logs-panel');

    if (await debugLogsPanel.isVisible().catch(() => false)) {
      for (const selector of [
        '#nmkr-debug-logs',
        '.debug-logs-panel',
        '#nmkr-log-search',
        '#nmkr-log-type-filter',
        '#nmkr-log-category-filter',
        '#nmkr-clear-filters',
        '#nmkr-clear-all-logs',
        '.clear-section-logs-btn',
        '.log-section',
      ]) {
        await expect(page.locator(selector).first()).toBeAttached();
      }
    }
  });
});
