#!/usr/bin/env node
const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

if (process.env.NMKR_AUTH_STATE_PATH || process.env.NMKR_AUTH_STATE_DIR || process.env.NMKR_AUTH_STATE_OWNER_TOKEN) {
  process.stderr.write('NMKR auth-state path is invalid.\n'); process.exit(1);
}

let root = process.env.NMKR_AUTH_STATE_ROOT;
if (root) {
  try {
    if (fs.lstatSync(root).isSymbolicLink() || !fs.statSync(root).isDirectory()) throw new Error('invalid root');
    root = fs.realpathSync(root);
  } catch (_) {
    process.stderr.write('NMKR auth-state root is invalid.\n'); process.exit(1);
  }
} else {
  root = fs.mkdtempSync(path.join(os.tmpdir(), 'nmkr-connect-playwright-root-'));
  fs.chmodSync(root, 0o700);
}
const token = require('crypto').randomBytes(16).toString('hex');
const directory = path.join(root, `nmkr-connect-auth-${token}`);
fs.mkdirSync(directory, { mode: 0o700 });
fs.chmodSync(directory, 0o700);
process.env.NMKR_AUTH_STATE_DIR = directory;
process.env.NMKR_AUTH_STATE_PATH = path.join(directory, 'auth-state.json');
process.env.NMKR_AUTH_STATE_OWNER_TOKEN = token;
let cleaned = false;
function cleanup() {
  if (cleaned || process.env.NMKR_RETAIN_AUTH_STATE === 'true') return;
  cleaned = true;
  fs.rmSync(directory, { recursive: true, force: true });
}
process.once('SIGINT', () => { cleanup(); process.exit(130); });
process.once('SIGTERM', () => { cleanup(); process.exit(143); });
const result = spawnSync(require.resolve('@playwright/test/cli'), ['test', ...process.argv.slice(2)], { stdio: 'inherit', env: process.env });
let status = result.status === null ? 1 : result.status;
try { cleanup(); } catch (_) { status = status || 1; }
process.exit(status);
