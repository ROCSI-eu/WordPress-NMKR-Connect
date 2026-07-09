import { expect, Page, test } from "@playwright/test";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const allowedStubbedActions = new Set([
  "nmkr_check_api_status",
  "nmkr_sync_progress",
  "nmkr_get_sync_statistics",
]);

const blockedMutatingActions = new Set([
  "nmkr_start_sync",
  "nmkr_stop_sync",
  "nmkr_force_stop_sync",
  "nmkr_restart_sync_batch",
  "nmkr_cleanup_sync_jobs",
  "nmkr_store_active_metrics",
  "nmkr_clear_all_logs",
  "nmkr_clear_section_logs",
]);

const dashboardPath = env(
  "NMKR_DASHBOARD_PATH",
  "/wp-admin/admin.php?page=nmkr-connect-dashboard",
);
const fixedUpdatedAt = 1783596000;

type ProgressMode = "soft-retry" | "hard-failure";

type AjaxHarness = {
  blockedActions: string[];
  progressActionsWithCacheBuster: () => number;
  progressCallCount: () => number;
};

function inProgressPayload(progress: number, currentItem: string) {
  return {
    success: true,
    data: {
      progress,
      current_item: currentItem,
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

async function installAdminAjaxHarness(
  page: Page,
  mode: ProgressMode,
): Promise<AjaxHarness> {
  const blockedActions: string[] = [];
  let progressCallCount = 0;
  let progressActionsWithCacheBuster = 0;

  await page.route("**/wp-admin/admin-ajax.php", async (route, request) => {
    const params = new URLSearchParams(request.postData() || "");
    const action = params.get("action");

    if (action === "nmkr_check_api_status") {
      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            message: "Test stub: API status check skipped.",
            connected: true,
            sync_in_progress: false,
            progress: 0,
            heartbeat_age: -1,
            last_update: 0,
            grace: 0,
            last_result: "",
            last_recovery_at: 0,
          },
        }),
      });
      return;
    }

    if (action === "nmkr_sync_progress") {
      progressCallCount += 1;
      if (params.has("_")) {
        progressActionsWithCacheBuster += 1;
      }

      if (mode === "soft-retry" && progressCallCount === 1) {
        await route.fulfill({
          status: 503,
          contentType: "application/json",
          body: JSON.stringify({
            success: false,
            data: { message: "Temporary sync polling interruption." },
          }),
        });
        return;
      }

      if (mode === "hard-failure") {
        await route.fulfill({
          status: 403,
          contentType: "application/json",
          body: JSON.stringify({
            success: false,
            data: { message: "Forbidden" },
          }),
        });
        return;
      }

      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify(
          inProgressPayload(
            42,
            "Recovered test progress after transient polling failure",
          ),
        ),
      });
      return;
    }

    if (action === "nmkr_get_sync_statistics") {
      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: {
            last_sync_time: "Not run in resilience test",
            total_projects: 0,
            total_tokens: 0,
            total_sync_duration: "0s",
            total_api_time: "0s",
            average_response_time: "0.00ms",
            average_response_time_raw: 0,
            api_requests: 0,
            memory_usage: "0 MB",
            memory_usage_raw: 0,
            response_time_class: "status-neutral",
            memory_class: "status-neutral",
          },
        }),
      });
      return;
    }

    if (action && blockedMutatingActions.has(action)) {
      blockedActions.push(action);
      await route.fulfill({
        contentType: "application/json",
        body: JSON.stringify({
          success: true,
          data: { message: "Test stub: mutating sync action skipped." },
        }),
      });
      return;
    }

    if (action && allowedStubbedActions.has(action)) {
      throw new Error(`Unhandled allowed stubbed action: ${action}`);
    }

    await route.continue();
  });

  return {
    blockedActions,
    progressActionsWithCacheBuster: () => progressActionsWithCacheBuster,
    progressCallCount: () => progressCallCount,
  };
}

async function openDashboardWithHarness(
  page: Page,
  mode: ProgressMode,
): Promise<AjaxHarness> {
  const harness = await installAdminAjaxHarness(page, mode);
  const baseUrl = await loginToWpAdmin(page);
  await page.goto(urlFor(baseUrl, dashboardPath));
  await expectWpAdmin(page);
  await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);
  await expect(page.locator("#nmkr-sync-progress-container")).toBeAttached();
  await expect(page.locator("#nmkr-sync-progress-bar")).toBeAttached();
  await expect(page.locator("#status-message")).toBeAttached();
  await expect(page.locator("#nmkr-sync-button")).toBeAttached();
  await expect(page.locator("#nmkr-stop-sync-button")).toBeAttached();
  return harness;
}

async function startPollingFromWindow(page: Page): Promise<void> {
  await page.evaluate(() => {
    const progress = (
      window as Window & { NMKRProgress?: { startPolling?: () => void } }
    ).NMKRProgress;
    if (!progress || typeof progress.startPolling !== "function") {
      throw new Error("NMKRProgress.startPolling is not available.");
    }
    progress.startPolling();
  });
}

test.describe("NMKR Connect sync AJAX resilience regression", () => {
  test("recovers from a retriable progress polling failure without exposing mutating AJAX actions", async ({
    page,
  }) => {
    const harness = await openDashboardWithHarness(page, "soft-retry");
    const startButton = page.locator("#nmkr-sync-button");
    const stopButton = page.locator("#nmkr-stop-sync-button");

    await startPollingFromWindow(page);

    await expect
      .poll(() => harness.progressCallCount())
      .toBeGreaterThanOrEqual(1);
    await expect(stopButton).toBeVisible();
    await expect(stopButton).toBeEnabled();
    await expect(startButton).toBeHidden();

    const warning = page
      .locator(".nmkr-admin-notice-warning, #status-message")
      .filter({ hasText: /hiccup|retry|temporary/i });
    if (
      await warning
        .first()
        .isVisible({ timeout: 1000 })
        .catch(() => false)
    ) {
      await expect(warning.first()).toContainText(/hiccup|retry|temporary/i);
    }

    await expect(page.locator("#nmkr-sync-progress-bar")).toContainText("42%", {
      timeout: 8000,
    });
    await expect(page.locator("#status-message")).toContainText(
      "Recovered test progress after transient polling failure",
    );
    expect(harness.progressCallCount()).toBeGreaterThanOrEqual(2);
    expect(harness.progressActionsWithCacheBuster()).toBeGreaterThanOrEqual(1);
    expect(harness.blockedActions).toEqual([]);
  });

  test("stops polling and restores Start after a hard progress security failure", async ({
    page,
  }) => {
    const harness = await openDashboardWithHarness(page, "hard-failure");
    const startButton = page.locator("#nmkr-sync-button");
    const stopButton = page.locator("#nmkr-stop-sync-button");

    await startPollingFromWindow(page);

    await expect(page.locator("#status-message")).toContainText(
      /Synchronization error|HTTP 403|Forbidden/i,
    );

    const syncError = page.locator("#nmkr-sync-error");
    if ((await syncError.count()) > 0) {
      await expect(syncError).toBeVisible();
      await expect(syncError).toContainText(/Sync failed|HTTP 403|Forbidden/i);
    }

    await expect(stopButton).toBeHidden();
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();

    const callsAfterFatal = harness.progressCallCount();
    await expect
      .poll(() => harness.progressCallCount(), {
        intervals: [1000, 2000, 4000],
        timeout: 7000,
      })
      .toBe(callsAfterFatal);
    expect(callsAfterFatal).toBe(1);
    expect(harness.progressActionsWithCacheBuster()).toBeGreaterThanOrEqual(1);
    expect(harness.blockedActions).toEqual([]);
  });
});
