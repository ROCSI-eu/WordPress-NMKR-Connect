import { test } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import os from 'os';

const statePath = process.env.NMKR_AUTH_STATE_PATH || path.join(os.tmpdir(), `nmkr-connect-auth-${process.pid}.json`);
test('remove sensitive authentication state', () => {
  if (process.env.NMKR_RETAIN_AUTH_STATE === 'true') return;
  fs.rmSync(statePath, { force: true });
});
