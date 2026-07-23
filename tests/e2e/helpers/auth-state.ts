import fs from 'fs';
import path from 'path';

const filename = 'auth-state.json';

function canonicalDirectory(directory: string): string {
  fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
  const resolved = fs.realpathSync(directory);
  if (fs.lstatSync(resolved).isSymbolicLink()) throw new Error('auth_state_path_invalid');
  return resolved;
}

export function approvedAuthStatePath(): string {
  const directory = process.env.NMKR_AUTH_STATE_DIR;
  const configured = process.env.NMKR_AUTH_STATE_PATH;
  if (!directory || !configured) throw new Error('auth_state_path_invalid');
  const root = canonicalDirectory(directory);
  const candidate = path.resolve(configured);
  if (path.basename(candidate) !== filename || path.dirname(candidate) !== root) throw new Error('auth_state_path_invalid');
  if (fs.existsSync(candidate) && fs.lstatSync(candidate).isSymbolicLink()) throw new Error('auth_state_path_invalid');
  return candidate;
}
