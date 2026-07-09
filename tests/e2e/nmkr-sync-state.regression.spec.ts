import { expect, test } from "@playwright/test";
import {
  installNmkrSyncAjaxHarness,
  startPollingFromWindow,
} from "./helpers/admin-ajax";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const fixedUpdatedAt = 1783596000;

function progressPayload(progressCallCount: number) {
  if (progressCallCount === 1) {
    return {
      success: true,
      data: {
        progress: 37,
        current_item: "Processing test project",
        in_progress: true,
        finished: false,
        aborted: false,
        error: "",
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
      current_item: "All data synchronized",
      in_progress: false,
      finished: true,
      aborted: false,
      error: "",
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

test.describe("NMKR Connect sync-state regression", () => {
  test("simulates active sync progress and completion without mutating WordPress state", async ({
    page,
  }) => {
    const dashboardPath = env(
      "NMKR_DASHBOARD_PATH",
      "/wp-admin/admin.php?page=nmkr-connect-dashboard",
    );
    const harness = await installNmkrSyncAjaxHarness(page, {
      onSyncProgress: ({ progressCallCount, state }) => {
        const payload = progressPayload(progressCallCount);
        state.completionSeen =
          state.completionSeen || payload.data.finished === true;
        return { body: payload };
      },
      syncStatisticsPayload: {
        success: true,
        data: {
          last_sync_time: "2026-07-09 11:20 UTC",
          total_projects: 3,
          total_tokens: 14,
          total_sync_duration: "12.5s",
          total_api_time: "1.25s",
          average_response_time: "310.00ms",
          average_response_time_raw: 0.31,
          api_requests: 4,
          memory_usage: "24.5 MB",
          memory_usage_raw: 24.5,
          response_time_class: "status-excellent",
          memory_class: "status-excellent",
        },
      },
    });

    const baseUrl = await loginToWpAdmin(page);
    await page.goto(urlFor(baseUrl, dashboardPath));
    await expectWpAdmin(page);
    await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);

    await expect(page.locator(".sync-data")).toBeAttached();
    await expect(page.locator("#nmkr-sync-progress-container")).toBeAttached();
    await expect(page.locator("#nmkr-sync-progress-bar")).toBeAttached();
    await expect(page.locator("#status-message")).toBeAttached();
    await expect(page.locator("#active-sync-metrics")).toBeAttached();
    await expect(page.locator(".sync-statistics")).toBeAttached();

    const startButton = page.locator("#nmkr-sync-button");
    const stopButton = page.locator("#nmkr-stop-sync-button");
    await expect(startButton).toBeAttached();
    await expect(stopButton).toBeAttached();

    await startPollingFromWindow(page);

    await expect(page.locator("#nmkr-sync-progress-bar")).toContainText("37%");
    await expect(page.locator("#status-message")).toContainText(
      "Processing test project",
    );
    await expect(page.locator("#active-sync-metrics")).toBeVisible();
    await expect(page.locator(".total-projects-active")).toContainText("3");
    await expect(page.locator(".total-tokens-active")).toContainText("14");
    await expect(page.locator(".api-requests")).toContainText("4");
    await expect(page.locator(".memory-usage")).toContainText(/\S+/);

    await expect(page.locator("#nmkr-sync-progress-bar")).toContainText(
      "100%",
      { timeout: 7000 },
    );
    await expect(page.locator("#status-message")).toContainText(
      /Synchronization completed successfully|All data synchronized/,
    );
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();
    await expect(stopButton).toBeHidden();

    await expect(page.locator("#last-synced")).toContainText(
      "2026-07-09 11:20 UTC",
      { timeout: 3000 },
    );
    await expect(page.locator("#total-projects")).toContainText("3");
    await expect(page.locator("#total-tokens")).toContainText("14");
    await expect(page.locator("#request-count")).toContainText("4");

    expect(harness.blockedActions).toEqual([]);
    expect(harness.progressCallCount()).toBeGreaterThanOrEqual(2);
    expect(harness.statisticsCallCount()).toBeGreaterThanOrEqual(1);
    expect(harness.statisticsCallsAfterCompletion()).toBeGreaterThanOrEqual(1);
  });
});
