import { test } from '@playwright/test';
import fs from 'fs';
import { approvedAuthStatePath } from './helpers/auth-state';

const statePath = approvedAuthStatePath();
test('remove sensitive authentication state', () => {
  if (process.env.NMKR_RETAIN_AUTH_STATE === 'true') return;
  if (fs.existsSync(statePath) && !fs.lstatSync(statePath).isFile()) throw new Error('auth_state_path_invalid');
  fs.rmSync(statePath, { force: true });
});
