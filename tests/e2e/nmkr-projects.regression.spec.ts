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

    const projectsDashboard = page.locator('.wrap.nmkr-projects-page');
    await expect(projectsDashboard).toBeAttached();
    await expect(page.getByRole('heading', { name: 'NFT Projects', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Choose a synchronized project' })).toBeVisible();
    await expect(page.locator('.nmkr-project-count')).toBeAttached();

    const projectSelectorForm = page.locator('form.project-selector-form');
    await expect(projectSelectorForm).toBeAttached();
    await expect(projectSelectorForm).toHaveAttribute('method', 'post');

    const filterNonce = projectSelectorForm.locator(
      'input[type="hidden"][name="nmkr_projects_filter_nonce"]',
    );
    await expect(filterNonce).toHaveCount(1);
    await expect(filterNonce).not.toHaveValue('');

    const projectSelect = projectSelectorForm.locator('select#project_uid[name="project_uid"]');
    await expect(projectSelect).toBeAttached();
    await expect(projectSelect).toHaveAttribute('onchange', 'this.form.submit()');

    const placeholderOption = projectSelect.locator('option[value=""]');
    await expect(placeholderOption).toHaveCount(1);
    await expect(placeholderOption).toHaveText('Select a project');
    await expect(projectSelect).toHaveValue('');

    const availableProjectOptions = projectSelect.locator('option:not([value=""])');
    if ((await availableProjectOptions.count()) > 0) {
      const projectUid = await availableProjectOptions.first().getAttribute('value');
      expect(projectUid).toBeTruthy();

      await projectSelect.selectOption(projectUid!);
      await expect(page).toHaveURL(/page=nmkr-connect-projects/);

      await expect(page.locator('.nmkr-project-overview')).toBeAttached();
      await expect(page.locator('.nmkr-project-stats-grid')).toBeAttached();

      const tokenFilterForm = page.locator('form.token-filter-form');
      await expect(tokenFilterForm).toBeAttached();
      await expect(
        tokenFilterForm.locator('input[type="hidden"][name="nmkr_projects_filter_nonce"]'),
      ).toHaveCount(1);
      await expect(tokenFilterForm.locator('input[type="hidden"][name="project_uid"]')).toHaveValue(
        projectUid!,
      );
      await expect(tokenFilterForm.locator('input#search_token[name="search_token"]')).toBeAttached();
      await expect(tokenFilterForm.locator('select#filter_minted[name="filter_minted"]')).toBeAttached();
    } else {
      await expect(page.locator('.nmkr-projects-empty-state')).toBeVisible();
    }

    await page.setViewportSize({ width: 390, height: 900 });
    const projectsFitViewport = await projectsDashboard.evaluate(
      (element) => element.scrollWidth <= element.clientWidth + 1,
    );
    expect(projectsFitViewport).toBe(true);

    expect(unexpectedProjectsAjaxActions).toEqual([]);
  });
});
