import { expect, Page, test } from "@playwright/test";
import { installNmkrSyncAjaxHarness, type AdminAjaxFulfillment, type AdminAjaxHarness } from "./helpers/admin-ajax";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const dashboardPath = env("NMKR_DASHBOARD_PATH", "/wp-admin/admin.php?page=nmkr-connect-dashboard");
const runA = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const runB = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const runC = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";

type Deferred<T> = { promise: Promise<T>; resolve: (value: T) => void };
function deferred<T>(): Deferred<T> {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}
function active(runId: string, ownerMismatch = false): AdminAjaxFulfillment {
  return { body: { success: true, data: { progress: 42, current_item: "Synthetic progress", in_progress: true, finished: false, error: "", activeRunId: runId, owner_mismatch: ownerMismatch } } };
}
function terminal(): AdminAjaxFulfillment {
  return { body: { success: true, data: { progress: 100, current_item: "Done", in_progress: false, finished: true, activeRunId: "" } } };
}
function failedTerminalWithOwner(runId: string): AdminAjaxFulfillment {
  return { body: { success: true, data: { progress: 0, current_item: "Previous run failed", in_progress: false, finished: false, terminal_outcome: "failed", error: "Previous run failure", activeRunId: runId } } };
}

async function open(page: Page) {
  const progressQueue: Deferred<AdminAjaxFulfillment>[] = [];
  const stopRunIds: string[] = [];
  let starts = 0;
  const harness: AdminAjaxHarness = await installNmkrSyncAjaxHarness(page, {
    handlers: {
      nmkr_start_sync: () => ({ body: { success: true, data: { run_id: ++starts === 1 ? runA : runB } } }),
      nmkr_stop_sync: ({ params }) => { stopRunIds.push(params.get("run_id") || ""); return { body: { success: true, data: { stop_pending: true } } }; },
    },
    onSyncProgress: () => {
      const next = deferred<AdminAjaxFulfillment>();
      progressQueue.push(next);
      return next.promise;
    },
  });
  const baseUrl = await loginToWpAdmin(page);
  await page.goto(urlFor(baseUrl, dashboardPath));
  await expectWpAdmin(page);
  async function nextProgress() {
    await expect.poll(() => progressQueue.length).toBeGreaterThan(0);
    return progressQueue.shift()!;
  }
  return { harness, stopRunIds, nextProgress };
}

async function start(page: Page) { await page.locator("#nmkr-sync-button").click(); }
async function poll(page: Page) { await page.evaluate(() => window.NMKRProgress?.startPolling?.()); }

test.describe("NMKR Connect run-authority regression", () => {
  test("provisional Start attaches only after matching progress and Stop submits that exact run", async ({ page }) => {
    const { harness, stopRunIds, nextProgress } = await open(page);
    await start(page);
    const first = await nextProgress();
    const stop = page.locator("#nmkr-stop-sync-button");
    await expect(stop).toBeVisible();
    await expect(stop).toBeDisabled();
    expect(harness.actionCount("nmkr_stop_sync")).toBe(0);
    first.resolve(active(runA));
    await expect(stop).toBeEnabled();
    await stop.click();
    await expect.poll(() => harness.actionCount("nmkr_stop_sync")).toBe(1);
    expect(stopRunIds).toEqual([runA]);
    expect(harness.blockedActions).toEqual([]);
  });

  test("matching queued owner remains trusted across predecessor mismatch", async ({ page }) => {
    const { harness, stopRunIds, nextProgress } = await open(page);
    await start(page);
    const stop = page.locator("#nmkr-stop-sync-button");
    (await nextProgress()).resolve(active(runA, true));
    await expect(stop).toBeEnabled();

    await poll(page);
    (await nextProgress()).resolve(active(runA, true));
    await expect(stop).toBeEnabled();

    await poll(page);
    (await nextProgress()).resolve(active(runB, true));
    await expect(stop).toBeDisabled();
    await expect.poll(() => harness.actionCount("nmkr_stop_sync")).toBe(0);
    expect(stopRunIds).toEqual([]);

    await poll(page);
    (await nextProgress()).resolve(active("not-a-run", true));
    await expect(stop).toBeDisabled();
  });

  test("stops ownerless failed terminal polling without a transient error", async ({ page }) => {
    const { harness, nextProgress } = await open(page);
    await poll(page);
    (await nextProgress()).resolve({ body: { success: true, data: { progress: 0, current_item: "Synchronization failed on the server", in_progress: false, finished: false, terminal_outcome: "failed", error: "", activeRunId: "" } } });
    await expect(page.locator("#status-message")).toContainText(/Synchronization error|failed/i);
    await expect(page.locator("#nmkr-stop-sync-button")).toBeHidden();
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await expect(page.locator("#nmkr-sync-button")).toBeEnabled();
    const callsAfterTerminal = harness.progressCallCount();
    await expect.poll(() => harness.progressCallCount(), { intervals: [1000, 2000, 4000], timeout: 7000 }).toBe(callsAfterTerminal);
    expect(callsAfterTerminal).toBe(1);
  });

  test("terminal, owner-change, recoverable, and fatal paths use controlled progress responses", async ({ page }) => {
    const { harness, nextProgress } = await open(page);
    const stop = page.locator("#nmkr-stop-sync-button");
    await start(page);
    (await nextProgress()).resolve(active(runA));
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve(terminal());
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await start(page);
    const secondProgress = await nextProgress();
    await expect(stop).toBeDisabled();
    secondProgress.resolve(active(runB));
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve(failedTerminalWithOwner(runB));
    await expect(stop).toBeEnabled();
    await expect(page.locator("#nmkr-sync-button")).toBeHidden();

    await poll(page); (await nextProgress()).resolve(active(runC));
    await expect(stop).toBeDisabled();
    await poll(page); const cleanC = await nextProgress();
    await expect(stop).toBeDisabled();
    cleanC.resolve(active(runC));
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve({ status: 503, body: { success: false, data: { message: "Temporary" } } });
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve({ status: 403, body: { success: false, data: { message: "Forbidden" } } });
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await start(page);
    const provisional = await nextProgress();
    await expect(stop).toBeDisabled();
    provisional.resolve(active(runB));
    await expect(stop).toBeEnabled();
    expect(harness.blockedActions).toEqual([]);
  });
});
