import { expect, Page, test } from "@playwright/test";
import { installNmkrSyncAjaxHarness, type AdminAjaxFulfillment, type AdminAjaxHarness } from "./helpers/admin-ajax";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const dashboardPath = env("NMKR_DASHBOARD_PATH", "/wp-admin/admin.php?page=nmkr-connect-dashboard");

type Deferred<T> = { promise: Promise<T>; resolve: (value: T) => void };
function deferred<T>(): Deferred<T> {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}
function active(runId: string): AdminAjaxFulfillment {
  return { body: { success: true, data: { progress: 42, current_item: "Synthetic progress", in_progress: true, finished: false, error: "", activeRunId: runId } } };
}
function terminal(): AdminAjaxFulfillment {
  return { body: { success: true, data: { progress: 100, current_item: "Done", in_progress: false, finished: true, activeRunId: "" } } };
}

async function open(page: Page) {
  const progressQueue: Deferred<AdminAjaxFulfillment>[] = [];
  const stopRunIds: string[] = [];
  let starts = 0;
  const harness: AdminAjaxHarness = await installNmkrSyncAjaxHarness(page, {
    handlers: {
      nmkr_start_sync: () => ({ body: { success: true, data: { run_id: ++starts === 1 ? "run-A" : "run-B" } } }),
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
    await expect.poll(() => harness.progressCallCount()).toBeGreaterThanOrEqual(progressQueue.length + 1);
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
    first.resolve(active("run-A"));
    await expect(stop).toBeEnabled();
    await stop.click();
    await expect.poll(() => harness.actionCount("nmkr_stop_sync")).toBe(1);
    expect(stopRunIds).toEqual(["run-A"]);
    expect(harness.blockedActions).toEqual([]);
  });

  test("terminal, owner-change, recoverable, and fatal paths use controlled progress responses", async ({ page }) => {
    const { harness, nextProgress } = await open(page);
    const stop = page.locator("#nmkr-stop-sync-button");
    await start(page);
    (await nextProgress()).resolve(active("run-A"));
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve(terminal());
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await start(page);
    const runB = await nextProgress();
    await expect(stop).toBeDisabled();
    runB.resolve(active("run-B"));
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve(active("run-C"));
    await expect(stop).toBeDisabled();
    await poll(page); const cleanC = await nextProgress();
    await expect(stop).toBeDisabled();
    cleanC.resolve(active("run-C"));
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve({ status: 503, body: { success: false, data: { message: "Temporary" } } });
    await expect(stop).toBeEnabled();

    await poll(page); (await nextProgress()).resolve({ status: 403, body: { success: false, data: { message: "Forbidden" } } });
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await start(page);
    const provisional = await nextProgress();
    await expect(stop).toBeDisabled();
    provisional.resolve(active("run-B"));
    await expect(stop).toBeEnabled();
    expect(harness.blockedActions).toEqual([]);
  });
});
