import { expect, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

const allowedStubbedActions = new Set([
  'nmkr_check_api_status',
  'nmkr_sync_progress',
  'nmkr_get_sync_statistics',
]);

const blockedMutatingActions = new Set([
  'nmkr_start_sync',
  'nmkr_stop_sync',
  'nmkr_force_stop_sync',
  'nmkr_restart_sync_batch',
  'nmkr_cleanup_sync_jobs',
  'nmkr_store_active_metrics',
  'nmkr_clear_all_logs',
  'nmkr_clear_section_logs',
]);

const fixedUpdatedAt = 1783596000;

function progressPayload(progressCallCount: number) {
  if (progressCallCount === 1) {
    return {
      success: true,
      data: {
        progress: 37,
        current_item: 'Processing test project',
        in_progress: true,
        finished: false,
        aborted: false,
        error: '',
        total_items: 100,
        live_metrics: {
          total_projects: 3,
          total_tokens: 14,
          total_sync_duration: 12.5,
          total_api_time: 1.25,
          average_response_time: 0.31,
          api_requests: 4,
          memory_usage: 24.5,
          updated_at: fixedUpdatedAt,
        },
      },
    };
  }

  return {
    success: true,
    data: {
      progress: 100,
      current_item: 'All data synchronized',
      in_progress: false,
      finished: true,
      aborted: false,
      error: '',
      total_items: 100,
      live_metrics: {
        total_projects: 3,
        total_tokens: 14,
        total_sync_duration: 12.5,
        total_api_time: 1.25,
        average_response_time: 0.31,
        api_requests: 4,
        memory_usage: 24.5,
        updated_at: fixedUpdatedAt + 30,
      },
    },
  };
}

test.describe('NMKR Connect sync-state regression', () => {
  test('simulates active sync progress and completion without mutating WordPress state', async ({ page }) => {
    const dashboardPath = env('NMKR_DASHBOARD_PATH', '/wp-admin/admin.php?page=nmkr-connect-dashboard');
    const unexpectedMutatingActions: string[] = [];
    let progressCallCount = 0;
    let statisticsCallCount = 0;
    let statisticsCallsAfterCompletion = 0;
    let completionSeen = false;

    await page.route('**/wp-admin/admin-ajax.php', async (route, request) => {
      const params = new URLSearchParams(request.postData() || '');
      const action = params.get('action');

      if (action === 'nmkr_check_api_status') {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              message: 'Test stub: API status check skipped.',
              connected: true,
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

      if (action === 'nmkr_sync_progress') {
        progressCallCount += 1;
        const payload = progressPayload(progressCallCount);
        completionSeen = completionSeen || payload.data.finished === true;
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify(payload) });
        return;
      }

      if (action === 'nmkr_get_sync_statistics') {
        statisticsCallCount += 1;
        if (completionSeen) {
          statisticsCallsAfterCompletion += 1;
        }
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              last_sync_time: '2026-07-09 11:20 UTC',
              total_projects: 3,
              total_tokens: 14,
              total_sync_duration: '12.5s',
              total_api_time: '1.25s',
              average_response_time: '310.00ms',
              average_response_time_raw: 0.31,
              api_requests: 4,
              memory_usage: '24.5 MB',
              memory_usage_raw: 24.5,
              response_time_class: 'status-excellent',
              memory_class: 'status-excellent',
            },
          }),
        });
        return;
      }

      if (action && blockedMutatingActions.has(action)) {
        unexpectedMutatingActions.push(action);
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: { message: 'Test stub: mutating sync action skipped.' } }),
        });
        return;
      }

      if (action && allowedStubbedActions.has(action)) {
        throw new Error(`Unhandled allowed stubbed action: ${action}`);
      }

      await route.continue();
    });

    const baseUrl = await loginToWpAdmin(page);
    await page.goto(urlFor(baseUrl, dashboardPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);

    await expect(page.locator('.sync-data')).toBeAttached();
    await expect(page.locator('#nmkr-sync-progress-container')).toBeAttached();
    await expect(page.locator('#nmkr-sync-progress-bar')).toBeAttached();
    await expect(page.locator('#status-message')).toBeAttached();
    await expect(page.locator('#active-sync-metrics')).toBeAttached();
    await expect(page.locator('.sync-statistics')).toBeAttached();

    const startButton = page.locator('#nmkr-sync-button');
    const stopButton = page.locator('#nmkr-stop-sync-button');
    await expect(startButton).toBeAttached();
    await expect(stopButton).toBeAttached();

    await page.evaluate(() => {
      const progress = (window as Window & { NMKRProgress?: { startPolling?: () => void } }).NMKRProgress;
      if (!progress || typeof progress.startPolling !== 'function') {
        throw new Error('NMKRProgress.startPolling is not available.');
      }
      progress.startPolling();
    });

    await expect(page.locator('#nmkr-sync-progress-bar')).toContainText('37%');
    await expect(page.locator('#status-message')).toContainText('Processing test project');
    await expect(page.locator('#active-sync-metrics')).toBeVisible();
    await expect(page.locator('.total-projects-active')).toContainText('3');
    await expect(page.locator('.total-tokens-active')).toContainText('14');
    await expect(page.locator('.api-requests')).toContainText('4');
    await expect(page.locator('.memory-usage')).toContainText(/\S+/);

    await expect(page.locator('#nmkr-sync-progress-bar')).toContainText('100%', { timeout: 7000 });
    await expect(page.locator('#status-message')).toContainText(/Synchronization completed successfully|All data synchronized/);
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();
    await expect(stopButton).toBeHidden();

    await expect(page.locator('#last-synced')).toContainText('2026-07-09 11:20 UTC', { timeout: 3000 });
    await expect(page.locator('#total-projects')).toContainText('3');
    await expect(page.locator('#total-tokens')).toContainText('14');
    await expect(page.locator('#request-count')).toContainText('4');

    expect(unexpectedMutatingActions).toEqual([]);
    expect(progressCallCount).toBeGreaterThanOrEqual(2);
    expect(statisticsCallCount).toBeGreaterThanOrEqual(1);
    expect(statisticsCallsAfterCompletion).toBeGreaterThanOrEqual(1);
  });
});
