import { Page } from "@playwright/test";

export const ADMIN_AJAX_ROUTE_PATTERN = "**/wp-admin/admin-ajax.php";

export const SYNC_READ_ACTIONS = [
  "nmkr_check_api_status",
  "nmkr_sync_progress",
  "nmkr_get_sync_statistics",
] as const;

export const DEFAULT_BLOCKED_SYNC_MUTATING_ACTIONS = [
  "nmkr_start_sync",
  "nmkr_stop_sync",
  "nmkr_force_stop_sync",
  "nmkr_restart_sync_batch",
  "nmkr_cleanup_sync_jobs",
  "nmkr_store_active_metrics",
  "nmkr_clear_all_logs",
  "nmkr_clear_section_logs",
] as const;

export type AdminAjaxRequestContext = {
  action: string | null;
  params: URLSearchParams;
  postDataPresent: boolean;
  actionCount: number;
  progressCallCount: number;
  progressActionsWithCacheBuster: number;
  statisticsCallCount: number;
  state: {
    completionSeen: boolean;
  };
};

export type AdminAjaxFulfillment = {
  status?: number;
  contentType?: string;
  body: unknown;
};

export type AdminAjaxHandler = (
  context: AdminAjaxRequestContext,
) => AdminAjaxFulfillment | Promise<AdminAjaxFulfillment>;

export type AdminAjaxActionHandlers = Partial<Record<string, AdminAjaxHandler>>;

export type AdminAjaxHarness = {
  blockedActions: string[];
  actionCount: (action: string) => number;
  progressCallCount: () => number;
  progressActionsWithCacheBuster: () => number;
  statisticsCallCount: () => number;
  statisticsCallsAfterCompletion: () => number;
  markCompletionSeen: () => void;
  completionSeen: () => boolean;
};

type AdminAjaxHarnessOptions = {
  allowedActions: readonly string[];
  blockedMutatingActions: readonly string[];
  handlers: AdminAjaxActionHandlers;
};

type NmkrSyncAjaxHarnessOptions = {
  onSyncProgress?: AdminAjaxHandler;
  apiStatusPayload?: unknown;
  syncStatisticsPayload?: unknown;
  handlers?: AdminAjaxActionHandlers;
  allowedActions?: readonly string[];
  blockedMutatingActions?: readonly string[];
};

export function defaultApiStatusPayload(
  overrides: Record<string, unknown> = {},
) {
  return {
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
      ...overrides,
    },
  };
}

export function defaultSyncStatisticsPayload(
  overrides: Record<string, unknown> = {},
) {
  return {
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
      ...overrides,
    },
  };
}

export async function installAdminAjaxHarness(
  page: Page,
  options: AdminAjaxHarnessOptions,
): Promise<AdminAjaxHarness> {
  const allowedActions = new Set(options.allowedActions);
  const blockedMutatingActions = new Set(options.blockedMutatingActions);
  const blockedActions: string[] = [];
  const actionCounts = new Map<string, number>();
  const state = { completionSeen: false };
  let progressCallCount = 0;
  let progressActionsWithCacheBuster = 0;
  let statisticsCallCount = 0;
  let statisticsCallsAfterCompletion = 0;

  await page.route(ADMIN_AJAX_ROUTE_PATTERN, async (route, request) => {
    const postData = request.postData();
    const params = new URLSearchParams(postData || "");
    const action = params.get("action");

    if (action) {
      actionCounts.set(action, (actionCounts.get(action) || 0) + 1);
    }

    if (action === "nmkr_sync_progress") {
      progressCallCount += 1;
      if (params.has("_")) {
        progressActionsWithCacheBuster += 1;
      }
    }

    if (action === "nmkr_get_sync_statistics") {
      statisticsCallCount += 1;
      if (state.completionSeen) {
        statisticsCallsAfterCompletion += 1;
      }
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

    const handler = action ? options.handlers[action] : undefined;
    if (handler) {
      const fulfillment = await handler({
        action,
        params,
        postDataPresent: postData !== null,
        actionCount: action ? actionCounts.get(action) || 0 : 0,
        progressCallCount,
        progressActionsWithCacheBuster,
        statisticsCallCount,
        state,
      });
      await route.fulfill({
        status: fulfillment.status,
        contentType: fulfillment.contentType || "application/json",
        body: JSON.stringify(fulfillment.body),
      });
      return;
    }

    if (action && allowedActions.has(action)) {
      throw new Error(`Unhandled allowed stubbed action: ${action}`);
    }

    await route.continue();
  });

  return {
    blockedActions,
    actionCount: (action: string) => actionCounts.get(action) || 0,
    progressCallCount: () => progressCallCount,
    progressActionsWithCacheBuster: () => progressActionsWithCacheBuster,
    statisticsCallCount: () => statisticsCallCount,
    statisticsCallsAfterCompletion: () => statisticsCallsAfterCompletion,
    markCompletionSeen: () => {
      state.completionSeen = true;
    },
    completionSeen: () => state.completionSeen,
  };
}

export async function installNmkrSyncAjaxHarness(
  page: Page,
  options: NmkrSyncAjaxHarnessOptions = {},
): Promise<AdminAjaxHarness> {
  const handlers: AdminAjaxActionHandlers = {
    nmkr_check_api_status: () => ({
      body: options.apiStatusPayload || defaultApiStatusPayload(),
    }),
    nmkr_get_sync_statistics: () => ({
      body: options.syncStatisticsPayload || defaultSyncStatisticsPayload(),
    }),
    ...(options.handlers || {}),
  };

  if (options.onSyncProgress) {
    handlers.nmkr_sync_progress = options.onSyncProgress;
  }

  return installAdminAjaxHarness(page, {
    allowedActions: options.allowedActions || SYNC_READ_ACTIONS,
    blockedMutatingActions:
      options.blockedMutatingActions || DEFAULT_BLOCKED_SYNC_MUTATING_ACTIONS,
    handlers,
  });
}

export async function startPollingFromWindow(page: Page): Promise<void> {
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
