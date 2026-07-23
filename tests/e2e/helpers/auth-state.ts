import fs from 'fs';
import path from 'path';

const filename = 'auth-state.json';

function canonicalDirectory(directory: string): string {
  const resolved = fs.realpathSync(directory);
  const info = fs.lstatSync(resolved);
  if (info.isSymbolicLink() || !info.isDirectory()) throw new Error('auth_state_path_invalid');
  return resolved;
}

export function approvedAuthStatePath(): string {
  const directory = process.env.NMKR_AUTH_STATE_DIR;
  const configured = process.env.NMKR_AUTH_STATE_PATH;
  const token = process.env.NMKR_AUTH_STATE_OWNER_TOKEN;
  if (!directory || !configured || !token || !/^[a-f0-9]{32}$/.test(token)) throw new Error('auth_state_path_invalid');
  const root = canonicalDirectory(directory);
  const candidate = path.resolve(configured);
  if (path.basename(root) !== `nmkr-connect-auth-${token}` || path.basename(candidate) !== filename || path.dirname(candidate) !== root) throw new Error('auth_state_path_invalid');
  if (fs.existsSync(candidate) && !fs.lstatSync(candidate).isFile()) throw new Error('auth_state_path_invalid');
  return candidate;
}
