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
    let postCompletionStatisticsReads = 0;
    let completionStatisticsApplicationFailures = 0;
    const statisticsData = {
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
    };

    const harness = await installNmkrSyncAjaxHarness(page, {
      onSyncProgress: ({ progressCallCount, state }) => {
        const payload = progressPayload(progressCallCount);
        state.completionSeen =
          state.completionSeen || payload.data.finished === true;
        return { body: payload };
      },
      handlers: {
        nmkr_get_sync_statistics: ({ params, state }) => {
          if (state.completionSeen) {
            postCompletionStatisticsReads += 1;
          }

          if (
            state.completionSeen &&
            params.get("type") === "sync_completed" &&
            completionStatisticsApplicationFailures === 0
          ) {
            completionStatisticsApplicationFailures += 1;
            return {
              body: {
                success: false,
                data: { message: "Synthetic application-level statistics rejection" },
              },
            };
          }

          const latestRunReady =
            state.completionSeen && postCompletionStatisticsReads >= 3;

          return {
            body: {
              success: true,
              data: {
                ...statisticsData,
                latest_run: latestRunReady
                  ? {
                      terminal_result: "success",
                      status: "completed",
                      items_processed: 100,
                      items_successful: 100,
                      items_failed: 0,
                      items_skipped: 0,
                      token_details_synced: 100,
                    }
                  : {
                      terminal_result: null,
                      status: null,
                      items_processed: null,
                      items_successful: null,
                      items_failed: null,
                      items_skipped: null,
                      token_details_synced: null,
                    },
              },
            },
          };
        },
        nmkr_store_active_metrics: () => ({
          body: {
            success: true,
            data: { message: "Test stub: dashboard telemetry write skipped." },
          },
        }),
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

    await expect(page.locator("#latest-run-terminal-result")).toHaveText(
      "success",
      { timeout: 5000 },
    );
    await expect(page.locator("#latest-run-status")).toHaveText("completed");
    await expect(page.locator("#latest-run-processed")).toHaveText("100");
    await expect(page.locator("#latest-run-successful")).toHaveText("100");
    await expect(page.locator("#latest-run-failed")).toHaveText("0");
    await expect(page.locator("#latest-run-skipped")).toHaveText("0");
    await expect(page.locator("#latest-run-token-details")).toHaveText("100");

    expect(harness.blockedActions).toEqual([]);
    expect(harness.progressCallCount()).toBeGreaterThanOrEqual(2);
    expect(completionStatisticsApplicationFailures).toBe(1);
    // The canonical-history refresh is intentionally bounded and asynchronous.
    // Wait for its retry schedule rather than racing the 500/750 ms timers.
    await expect.poll(() => harness.statisticsCallCount(), { timeout: 5000 }).toBeGreaterThanOrEqual(3);
    await expect.poll(() => harness.statisticsCallsAfterCompletion(), { timeout: 5000 }).toBeGreaterThanOrEqual(3);
    await expect.poll(() => postCompletionStatisticsReads, { timeout: 5000 }).toBeGreaterThanOrEqual(3);
  });
});
