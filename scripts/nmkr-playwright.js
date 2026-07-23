#!/usr/bin/env node
const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const suppliedPath = process.env.NMKR_AUTH_STATE_PATH;
let directory = process.env.NMKR_AUTH_STATE_DIR;
if (!suppliedPath) {
  directory = fs.mkdtempSync(path.join(os.tmpdir(), 'nmkr-connect-playwright-'));
  fs.chmodSync(directory, 0o700);
  process.env.NMKR_AUTH_STATE_DIR = directory;
  process.env.NMKR_AUTH_STATE_PATH = path.join(directory, 'auth-state.json');
} else if (!directory || path.basename(suppliedPath) !== 'auth-state.json' || path.resolve(path.dirname(suppliedPath)) !== fs.realpathSync(directory)) {
  process.stderr.write('NMKR auth-state path is invalid.\n'); process.exit(1);
}
const result = spawnSync(require.resolve('@playwright/test/cli'), ['test', ...process.argv.slice(2)], { stdio: 'inherit', env: process.env });
process.exit(result.status === null ? 1 : result.status);
