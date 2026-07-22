import { expect, Page, test } from "@playwright/test";
import {
  installNmkrSyncAjaxHarness,
  startPollingFromWindow,
  type AdminAjaxHarness,
} from "./helpers/admin-ajax";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const dashboardPath = env(
  "NMKR_DASHBOARD_PATH",
  "/wp-admin/admin.php?page=nmkr-connect-dashboard",
);
const fixedUpdatedAt = 1783596000;

type ProgressMode = "soft-retry" | "hard-failure";

function inProgressPayload(progress: number, currentItem: string) {
  return {
    success: true,
    data: {
      progress,
      current_item: currentItem,
      in_progress: true,
      activeRunId: "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
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

async function openDashboardWithHarness(
  page: Page,
  mode: ProgressMode,
): Promise<AdminAjaxHarness> {
  const harness = await installNmkrSyncAjaxHarness(page, {
    onSyncProgress: ({ progressCallCount }) => {
      if (mode === "soft-retry" && progressCallCount === 1) {
        return {
          status: 503,
          body: {
            success: false,
            data: { message: "Temporary sync polling interruption." },
          },
        };
      }

      if (mode === "hard-failure") {
        return {
          status: 403,
          body: {
            success: false,
            data: { message: "Forbidden" },
          },
        };
      }

      return {
        body: inProgressPayload(
          42,
          "Recovered test progress after transient polling failure",
        ),
      };
    },
  });
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
