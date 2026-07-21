import { expect, Page, test } from "@playwright/test";
import { installNmkrSyncAjaxHarness, type AdminAjaxHarness } from "./helpers/admin-ajax";
import { env, expectWpAdmin, loginToWpAdmin, urlFor } from "./helpers/wp-admin";

const dashboardPath = env("NMKR_DASHBOARD_PATH", "/wp-admin/admin.php?page=nmkr-connect-dashboard");

type State = "run-a" | "terminal" | "run-b" | "changed" | "fatal";
function progress(state: State) {
  if (state === "terminal") return { success: true, data: { progress: 100, in_progress: false, finished: true, activeRunId: "", current_item: "Done" } };
  if (state === "fatal") return { success: false, data: { message: "Forbidden" } };
  const activeRunId = state === "run-b" || state === "changed" ? "run-B" : "run-A";
  return { success: true, data: { progress: 42, in_progress: true, finished: false, activeRunId, current_item: "Synthetic progress", error: "" } };
}

async function open(page: Page): Promise<{ harness: AdminAjaxHarness; setState: (value: State) => void }> {
  let state: State = "run-a";
  let starts = 0;
  const harness = await installNmkrSyncAjaxHarness(page, {
    handlers: {
      nmkr_start_sync: () => ({ body: { success: true, data: { run_id: ++starts === 1 ? "run-A" : "run-B" } } }),
      nmkr_stop_sync: ({ params }) => ({ body: { success: true, data: { stop_pending: true, received_run_id: params.get("run_id") } } }),
    },
    onSyncProgress: () => ({ status: state === "fatal" ? 403 : 200, body: progress(state) }),
  });
  const baseUrl = await loginToWpAdmin(page);
  await page.goto(urlFor(baseUrl, dashboardPath));
  await expectWpAdmin(page);
  return { harness, setState: (value) => { state = value; } };
}

async function start(page: Page) { await page.locator("#nmkr-sync-button").click(); }
async function poll(page: Page) { await page.evaluate(() => window.NMKRProgress?.startPolling?.()); }

test.describe("NMKR Connect run-authority regression", () => {
  test("Start is provisional until matching progress attaches, then Stop sends the exact run", async ({ page }) => {
    const { harness } = await open(page);
    await page.route("**/wp-admin/admin-ajax.php", async route => route.fallback());
    await start(page);
    const stop = page.locator("#nmkr-stop-sync-button");
    await expect(stop).toBeVisible();
    await expect(stop).toBeDisabled();
    expect(harness.actionCount("nmkr_stop_sync")).toBe(0);
    await expect(stop).toBeEnabled();
    await stop.click();
    await expect.poll(() => harness.actionCount("nmkr_stop_sync")).toBe(1);
    expect(harness.blockedActions).toEqual([]);
  });

  test("terminal cleanup and owner changes require a later clean attachment", async ({ page }) => {
    const { harness, setState } = await open(page);
    await start(page);
    const stop = page.locator("#nmkr-stop-sync-button");
    await expect(stop).toBeEnabled();
    setState("terminal"); await poll(page);
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await start(page);
    await expect(stop).toBeDisabled();
    setState("run-b"); await poll(page);
    await expect(stop).toBeEnabled();
    setState("changed"); await poll(page);
    await expect(stop).toBeDisabled();
    await poll(page);
    await expect(stop).toBeEnabled();
    expect(harness.blockedActions).toEqual([]);
  });

  test("recoverable failures retain authority while fatal failure clears it", async ({ page }) => {
    const { harness, setState } = await open(page);
    await start(page);
    const stop = page.locator("#nmkr-stop-sync-button");
    await expect(stop).toBeEnabled();
    await page.route("**/wp-admin/admin-ajax.php", async (route, request) => {
      if (request.postData()?.includes("action=nmkr_sync_progress")) await route.fulfill({ status: 503, contentType: "application/json", body: "{}" });
      else await route.fallback();
    }, { times: 1 });
    await poll(page);
    await expect(stop).toBeEnabled();
    setState("fatal"); await poll(page);
    await expect(page.locator("#nmkr-sync-button")).toBeVisible();
    await start(page);
    await expect(stop).toBeDisabled();
    expect(harness.blockedActions).toEqual([]);
  });
});
