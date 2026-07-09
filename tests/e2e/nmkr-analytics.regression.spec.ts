import { expect, Locator, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

async function expectButtonElement(locator: Locator): Promise<void> {
  await expect(locator).toBeAttached();
  await expect(locator).toHaveJSProperty('tagName', 'BUTTON');
}

const analyticsPageLoadAjaxResponses = new Map<string, object>([
  [
    'nmkr_analytics_kpis',
    {
      success: true,
      data: {
        views: 0,
        clicks: 0,
        ctr: 0,
        range: {
          from: '',
          to: '',
          label: '7d',
        },
      },
    },
  ],
  [
    'nmkr_analytics_timeseries',
    {
      success: true,
      data: {
        series: [],
        from: '',
        to: '',
        bucket: 'day',
      },
    },
  ],
  [
    'nmkr_analytics_top_projects',
    {
      success: true,
      data: {
        rows: [],
        total: 0,
        page: 1,
        per_page: 10,
        sort: 'views',
        order: 'desc',
        range: {
          from: '',
          to: '',
        },
      },
    },
  ],
  [
    'nmkr_analytics_top_tokens',
    {
      success: true,
      data: {
        rows: [],
        total: 0,
        page: 1,
        per_page: 10,
        sort: 'views',
        order: 'desc',
        range: {
          from: '',
          to: '',
        },
      },
    },
  ],
]);

const blockedAnalyticsAjaxActions = new Set([
  'nmkr_start_sync',
  'nmkr_stop_sync',
  'nmkr_store_active_metrics',
  'nmkr_get_sync_statistics',
  'nmkr_clear_all_logs',
  'nmkr_clear_section_logs',
  'nmkr_sync_progress',
  'nmkr_analytics_export',
  'nmkr_analytics_breakdown',
]);

test.describe('NMKR Connect analytics page regression', () => {
  test('loads analytics shell and safe runtime controls without reading analytics data', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const analyticsPath = env('NMKR_ANALYTICS_PATH', '/wp-admin/admin.php?page=nmkr-connect-analytics');
    const unexpectedAnalyticsAjaxActions: string[] = [];

    await page.route('**/wp-admin/admin-ajax.php', async (route, request) => {
      const postData = request.postData();
      const action = postData ? new URLSearchParams(postData).get('action') : null;

      if (action && analyticsPageLoadAjaxResponses.has(action)) {
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify(analyticsPageLoadAjaxResponses.get(action)),
        });
        return;
      }

      if (action && blockedAnalyticsAjaxActions.has(action)) {
        unexpectedAnalyticsAjaxActions.push(action);
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: { message: 'Test stub: analytics AJAX action skipped.' } }),
        });
        return;
      }

      await route.continue();
    });

    await page.goto(urlFor(baseUrl, analyticsPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-analytics/);

    const analyticsWrap = page.locator('.wrap.nmkr-analytics-wrap');
    const analyticsAvailable = await analyticsWrap.isVisible({ timeout: 3000 }).catch(() => false);

    test.skip(!analyticsAvailable, 'Analytics UI is not available in this environment.');

    await expect(analyticsWrap).toBeAttached();
    await expect(page.getByRole('heading', { name: 'Analytics' })).toBeVisible();

    for (const selector of [
      '#nmkr-analytics-filters',
      '#nmkr-analytics-kpis',
      '#nmkr-analytics-charts',
      '#nmkr-analytics-tables',
      '#nmkr-analytics-exports',
    ]) {
      await expect(page.locator(selector)).toBeAttached();
    }

    for (const selector of [
      '#nmkr-f-range',
      '#nmkr-f-type',
      '#nmkr-kpi-views',
      '#nmkr-kpi-clicks',
      '#nmkr-kpi-ctr',
      '#nmkr-chart-views-cv',
      '#nmkr-chart-clicks-cv',
      '#nmkr-top-projects-root',
      '#nmkr-top-tokens-root',
      '#nmkr-exp-what',
      '#nmkr-exp-fmt',
      '#nmkr-exp-status',
    ]) {
      await expect(page.locator(selector)).toBeAttached();
    }

    await expectButtonElement(page.locator('#nmkr-f-apply'));
    expect(unexpectedAnalyticsAjaxActions).toEqual([]);
  });
});
