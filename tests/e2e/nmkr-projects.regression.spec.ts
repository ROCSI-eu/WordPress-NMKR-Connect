import { expect, test } from '@playwright/test';
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from './helpers/wp-admin';

const blockedProjectsAjaxActions = new Set([
  'nmkr_start_sync',
  'nmkr_stop_sync',
  'nmkr_store_active_metrics',
  'nmkr_get_sync_statistics',
  'nmkr_clear_all_logs',
  'nmkr_clear_section_logs',
  'nmkr_sync_progress',
]);

test.describe('NMKR Connect projects page regression', () => {
  test('loads default project selector state without selecting a project', async ({ page }) => {
    const baseUrl = await loginToWpAdmin(page);
    const projectsPath = env('NMKR_PROJECTS_PATH', '/wp-admin/admin.php?page=nmkr-connect-projects');
    const unexpectedProjectsAjaxActions: string[] = [];

    await page.route('**/wp-admin/admin-ajax.php', async (route, request) => {
      const postData = request.postData();
      const action = postData ? new URLSearchParams(postData).get('action') : null;

      if (action && blockedProjectsAjaxActions.has(action)) {
        unexpectedProjectsAjaxActions.push(action);
        await route.fulfill({
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: { message: 'Test stub: projects AJAX action skipped.' } }),
        });
        return;
      }

      await route.continue();
    });

    await page.goto(urlFor(baseUrl, projectsPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-projects/);

    const projectsDashboard = page.locator('.wrap.nmkr-dashboard');
    await expect(projectsDashboard).toBeAttached();
    await expect(page.getByRole('heading', { name: 'NMKR Projects and Tokens' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Your Projects' })).toBeVisible();
    await expect(page.locator('.nmkr-info-box')).toBeAttached();

    const projectSelectorForm = page.locator('form.project-selector-form');
    await expect(projectSelectorForm).toBeAttached();
    await expect(projectSelectorForm).toHaveAttribute('method', 'post');

    const projectSelect = projectSelectorForm.locator('select#project_uid[name="project_uid"]');
    await expect(projectSelect).toBeAttached();
    await expect(projectSelect).toHaveAttribute('onchange', 'this.form.submit()');

    const placeholderOption = projectSelect.locator('option[value=""]');
    await expect(placeholderOption).toHaveCount(1);
    await expect(placeholderOption).toHaveText('-- Select a Project --');
    await expect(projectSelect).toHaveValue('');

    expect(unexpectedProjectsAjaxActions).toEqual([]);
  });
});
