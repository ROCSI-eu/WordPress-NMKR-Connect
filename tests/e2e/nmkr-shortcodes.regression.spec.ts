import { expect, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

const blockedShortcodesAjaxActions = new Set([
  'nmkr_start_sync',
  'nmkr_stop_sync',
  'nmkr_store_active_metrics',
  'nmkr_get_sync_statistics',
  'nmkr_clear_all_logs',
  'nmkr_clear_section_logs',
  'nmkr_sync_progress',
]);

test.describe('NMKR Connect shortcodes page regression', () => {
  test('loads shortcode reference structure without mutating state', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const shortcodesPath = env('NMKR_SHORTCODES_PATH', '/wp-admin/admin.php?page=nmkr-connect-shortcodes');
    const unexpectedShortcodesAjaxActions: string[] = [];

    await page.route('**/wp-admin/admin-ajax.php', async (route, request) => {
      const postData = request.postData();
      const action = postData ? new URLSearchParams(postData).get('action') : null;

      if (action && blockedShortcodesAjaxActions.has(action)) {
        unexpectedShortcodesAjaxActions.push(action);
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: { message: 'Test stub: shortcodes AJAX action skipped.' } }),
        });
        return;
      }

      await route.continue();
    });

    await page.goto(urlFor(baseUrl, shortcodesPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-shortcodes/);

    const shortcodesDashboard = page.locator('.wrap.nmkr-dashboard');
    await expect(shortcodesDashboard).toBeAttached();
    await expect(page.getByRole('heading', { name: 'NMKR Connect - Shortcodes' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Available Shortcodes' })).toBeVisible();
    await expect(page.locator('.nmkr-info-box').first()).toBeAttached();

    for (const shortcodeHeading of ['[nmkr-grid]', '[nmkr-token-list]', '[nmkr-carousel]', '[nmkr-token]', '[nmkr-project]']) {
      await expect(page.getByRole('heading', { name: shortcodeHeading })).toBeVisible();
    }

    expect(unexpectedShortcodesAjaxActions).toEqual([]);
  });
});
