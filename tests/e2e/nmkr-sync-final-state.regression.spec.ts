import { expect, Page, test } from "@playwright/test";
import {
  installNmkrSyncAjaxHarness,
  startPollingFromWindow,
  type AdminAjaxHarness,
} from "./helpers/admin-ajax";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const NMKR_DASHBOARD_PATH = env(
  "NMKR_DASHBOARD_PATH",
  "/wp-admin/admin.php?page=nmkr-connect-dashboard",
);
const fixedUpdatedAt = 1783596000;

function inProgressPayload() {
  return {
    success: true,
    data: {
      progress: 42,
      current_item: "Processing before controlled stop",
      in_progress: true,
      finished: false,
      aborted: false,
      error: "",
      total_items: 100,
      live_metrics: {
        total_projects: 2,
        total_tokens: 8,
        total_sync_duration: 9.5,
        total_api_time: 1.1,
        average_response_time: 0.28,
        api_requests: 3,
        memory_usage: 22.5,
        updated_at: fixedUpdatedAt,
      },
    },
  };
}

function stoppedPayload() {
  return {
    success: true,
    data: {
      progress: 42,
      current_item: "Synchronization stopped by test payload",
      activeRunId: "",
      terminalRunId: "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb",
      terminal_outcome: "stopped",
      in_progress: false,
      finished: false,
      aborted: true,
      error: "",
      total_items: 100,
      live_metrics: {
        total_projects: 2,
        total_tokens: 8,
        total_sync_duration: 10,
        total_api_time: 1.2,
        average_response_time: 0.3,
        api_requests: 4,
        memory_usage: 23,
        updated_at: fixedUpdatedAt + 10,
      },
    },
  };
}

async function openDashboard(page: Page): Promise<void> {
  const baseUrl = await loginToWpAdmin(page);
  await page.goto(urlFor(baseUrl, NMKR_DASHBOARD_PATH));
  await expectWpAdmin(page);
  await expect(page).toHaveURL(/page=nmkr-connect-dashboard/);
  await expect(page.locator("#nmkr-sync-progress-container")).toBeAttached();
  await expect(page.locator("#nmkr-sync-progress-bar")).toBeAttached();
  await expect(page.locator("#status-message")).toBeAttached();
  await expect(page.locator("#nmkr-sync-button")).toBeAttached();
  await expect(page.locator("#nmkr-stop-sync-button")).toBeAttached();
}

async function expectPollingStopped(harness: AdminAjaxHarness): Promise<void> {
  const callsAfterFinalState = harness.progressCallCount();
  await expect
    .poll(() => harness.progressCallCount(), {
      intervals: [1000, 2000, 4000],
      timeout: 7000,
    })
    .toBe(callsAfterFinalState);
}

test.describe("NMKR Connect sync final-state regression", () => {
  test("handles server-declared stopped/aborted sync final state without mutating WordPress state", async ({
    page,
  }) => {
    const harness = await installNmkrSyncAjaxHarness(page, {
      onSyncProgress: ({ progressCallCount }) => ({
        body: progressCallCount === 1 ? inProgressPayload() : stoppedPayload(),
      }),
    });
    await openDashboard(page);

    const startButton = page.locator("#nmkr-sync-button");
    const stopButton = page.locator("#nmkr-stop-sync-button");

    await startPollingFromWindow(page);

    await expect(page.locator("#nmkr-sync-progress-bar")).toContainText("42%");
    await expect(page.locator("#status-message")).toContainText(
      "Processing before controlled stop",
    );
    await expect(page.locator("#active-sync-metrics")).toBeVisible();

    await expect(page.locator("#status-message")).toContainText(
      /stopped|aborted|controlled/i,
      { timeout: 7000 },
    );
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();
    await expect(stopButton).toBeHidden();
    await expect(page.locator("#active-sync-metrics")).toBeHidden();
    await expectPollingStopped(harness);

    expect(harness.progressCallCount()).toBe(2);
    expect(harness.blockedActions).toEqual([]);
  });

  test("continues polling when in_progress is false without an explicit final marker", async ({
    page,
  }) => {
    const harness = await installNmkrSyncAjaxHarness(page, {
      onSyncProgress: ({ progressCallCount }) => {
        if (progressCallCount === 1) {
          return {
            body: {
              success: true,
              data: {
                progress: 43,
                current_item: "Transient inactive flag without final marker",
                in_progress: false,
                finished: false,
                aborted: false,
                error: "",
                total_items: 100,
              },
            },
          };
        }

        if (progressCallCount === 2) {
          return {
            body: {
              success: true,
              data: {
                progress: 44,
                current_item: "Polling continued after transient inactive flag",
                in_progress: true,
                finished: false,
                aborted: false,
                error: "",
                total_items: 100,
              },
            },
          };
        }

        return { body: stoppedPayload() };
      },
    });
    await openDashboard(page);

    const startButton = page.locator("#nmkr-sync-button");
    const stopButton = page.locator("#nmkr-stop-sync-button");

    await startPollingFromWindow(page);

    await expect(page.locator("#status-message")).toContainText(
      "Transient inactive flag without final marker",
    );
    await expect(page.locator("#status-message")).toContainText(
      "Polling continued after transient inactive flag",
      { timeout: 7000 },
    );
    expect(harness.progressCallCount()).toBeGreaterThanOrEqual(2);

    await expect(page.locator("#status-message")).toContainText(
      /stopped|aborted|controlled/i,
      { timeout: 7000 },
    );
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();
    await expect(stopButton).toBeHidden();
    await expectPollingStopped(harness);

    expect(harness.progressCallCount()).toBe(3);
    expect(harness.blockedActions).toEqual([]);
  });

  test("handles payload-level sync failure without relying on HTTP failure status", async ({
    page,
  }) => {
    const harness = await installNmkrSyncAjaxHarness(page, {
      onSyncProgress: ({ progressCallCount }) => {
        if (progressCallCount === 1) {
          return { body: inProgressPayload() };
        }

        return {
          status: 200,
          body: {
            success: true,
            data: {
              progress: 42,
              current_item: "Controlled failure",
              activeRunId: "",
              terminalRunId: "cccccccc-cccc-4ccc-8ccc-cccccccccccc",
              terminal_outcome: "failed",
              in_progress: false,
              finished: false,
              aborted: false,
              error: "Controlled test sync failure",
              total_items: 100,
            },
          },
        };
      },
    });
    await openDashboard(page);

    const startButton = page.locator("#nmkr-sync-button");
    const stopButton = page.locator("#nmkr-stop-sync-button");

    await startPollingFromWindow(page);

    await expect(page.locator("#nmkr-sync-progress-bar")).toContainText("42%");
    await expect(page.locator("#status-message")).toContainText(
      /failed|error|controlled/i,
      { timeout: 7000 },
    );
    const syncError = page.locator("#nmkr-sync-error");
    if ((await syncError.count()) > 0) {
      await expect(syncError).toBeVisible();
      await expect(syncError).toContainText(/failed|error|controlled/i);
    }
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();
    await expect(stopButton).toBeHidden();
    await expectPollingStopped(harness);

    expect(harness.progressCallCount()).toBe(2);
    expect(harness.blockedActions).toEqual([]);
  });
});
