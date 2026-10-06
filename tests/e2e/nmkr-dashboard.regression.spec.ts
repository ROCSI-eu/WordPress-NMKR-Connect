import { expect, Locator, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

async function expectButtonElement(locator: Locator): Promise<void> {
  await expect(locator).toBeAttached();
  await expect(locator).toHaveJSProperty('tagName', 'BUTTON');
}

const blockedDashboardAjaxActions = new Set([
  'nmkr_store_active_metrics',
  'nmkr_get_sync_statistics',
  'nmkr_clear_all_logs',
  'nmkr_clear_section_logs',
  'nmkr_start_sync',
  'nmkr_stop_sync',
]);

async function expectHiddenInput(locator: Locator): Promise<void> {
  await expect(locator).toBeAttached();
  await expect(locator).toHaveJSProperty('tagName', 'INPUT');
  await expect(locator).toHaveAttribute('type', 'hidden');
}

test.describe('NMKR Connect dashboard page regression', () => {
  test('loads dashboard structure and safe controls without mutating state', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const dashboardPath = env('NMKR_DASHBOARD_PATH', '/wp-admin/admin.php?page=nmkr-connect-dashboard');
    const unexpectedDashboardAjaxActions: string[] = [];

    await page.route('**/wp-admin/admin-ajax.php', async (route, request) => {
      const postData = request.postData();
      const action = postData ? new URLSearchParams(postData).get('action') : null;

      if (action === 'nmkr_check_api_status') {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              message: 'Test stub: API status check skipped.',
              connected: false,
              sync_in_progress: false,
              progress: 0,
              heartbeat_age: -1,
              last_update: 0,
              grace: 0,
              last_result: '',
              last_recovery_at: 0,
            },
          }),
        });
        return;
      }

      if (action && blockedDashboardAjaxActions.has(action)) {
        unexpectedDashboardAjaxActions.push(action);
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: { message: 'Test stub: dashboard AJAX action skipped.' } }),
        });
        return;
      }

      await route.continue();
    });

    await page.goto(urlFor(baseUrl, dashboardPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);

    const dashboard = page.locator('.wrap.nmkr-dashboard');
    await expect(dashboard).toBeAttached();
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    await expect(page.locator('.nmkr-dashboard-eyebrow')).toHaveText('ROCSI Connector for NMKR');
    await expect(page.locator('.nmkr-dashboard-primary-grid')).toBeAttached();

    const apiPanel = page.locator('.api-status-panel');
    await expect(apiPanel).toBeAttached();
    await expect(apiPanel.getByRole('heading', { name: 'NMKR API' })).toBeVisible();
    await expect(apiPanel.locator('#api-status')).toHaveAttribute('role', 'status');
    await expect(apiPanel.locator('#api-status')).toHaveAttribute('aria-live', 'polite');
    await expectButtonElement(apiPanel.locator('#refresh-api-status'));

    const syncPanel = page.locator('.sync-data');
    await expect(syncPanel).toBeAttached();
    await expect(syncPanel.getByRole('heading', { name: 'Data Sync' })).toBeVisible();
    await expectButtonElement(syncPanel.locator('#nmkr-sync-button'));
    await expectButtonElement(syncPanel.locator('#nmkr-stop-sync-button'));

    await expectHiddenInput(page.locator('#nmkr-sync-nonce'));
    await expectHiddenInput(page.locator('#nmkr-dashboard-nonce'));

    const progressContainer = page.locator('#nmkr-sync-progress-container');
    await expect(progressContainer).toBeAttached();
    await expect(progressContainer).toHaveAttribute('role', 'progressbar');
    await expect(page.locator('#nmkr-sync-progress-bar')).toBeAttached();
    await expect(page.locator('#status-message')).toHaveAttribute('role', 'status');
    await expect(page.locator('#status-message')).toHaveAttribute('aria-live', 'polite');
    await expect(page.locator('#status-message')).toHaveAttribute('aria-atomic', 'true');
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
    await expect(statisticsPanel.getByRole('heading', { name: 'Synchronization Summary' })).toBeVisible();
    await expect(statisticsPanel.locator('.nmkr-summary-grid')).toBeAttached();
    await expect(statisticsPanel.locator('.nmkr-summary-card')).toHaveCount(5);
    await expect(statisticsPanel.locator('#nmkr-sync-summary-message')).toBeAttached();

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
      '#latest-run-result',
      '#latest-run-terminal-result',
      '#latest-run-status',
      '#latest-run-processed',
      '#latest-run-successful',
      '#latest-run-failed',
      '#latest-run-skipped',
      '#latest-run-token-details',
    ]) {
      await expect(statisticsPanel.locator(selector)).toBeAttached();
    }

    const performanceDetails = page.locator('#nmkr-performance-details');
    await expect(performanceDetails).toBeAttached();
    await expect(performanceDetails).not.toHaveAttribute('open', '');
    await performanceDetails.locator(':scope > summary').click();
    await expect(statisticsPanel.locator('#performance-stats')).toBeVisible();

    const diagnostics = page.locator('.nmkr-dashboard-diagnostics');
    const debugLogsPanel = page.locator('.debug-logs-panel');

    if (await diagnostics.count()) {
      await expect(diagnostics).toHaveAttribute('id', 'nmkr-debug-logs');
      await expect(diagnostics).not.toHaveAttribute('open', '');

      await page.evaluate(() => {
        window.location.hash = '#nmkr-debug-logs';
      });
      await expect(diagnostics).toHaveAttribute('open', '');
      await expect(debugLogsPanel).toBeVisible();

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

    await page.setViewportSize({ width: 390, height: 900 });
    const dashboardFitsViewport = await dashboard.evaluate((element) => element.scrollWidth <= element.clientWidth + 1);
    expect(dashboardFitsViewport).toBe(true);

    expect(unexpectedDashboardAjaxActions).toEqual([]);
  });
});
