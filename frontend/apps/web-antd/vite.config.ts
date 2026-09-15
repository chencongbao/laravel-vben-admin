import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

import { defineConfig } from '@vben/vite-config';

import { searchForWorkspaceRoot } from 'vite';

export default defineConfig(async () => {
  const defaultWorkspace = fileURLToPath(
    new URL('src/views/dashboard/workspace/index.vue', import.meta.url),
  );
  const configuredWorkspace = process.env.VBEN_ADMIN_WORKSPACE?.trim();
  const workspace = configuredWorkspace
    ? resolve(configuredWorkspace)
    : defaultWorkspace;

  if (!existsSync(workspace)) {
    throw new Error(
      `VBEN_ADMIN_WORKSPACE points to a missing file: ${workspace}`,
    );
  }

  return {
    application: {},
    vite: {
      resolve: {
        alias: {
          '#workspace': workspace,
        },
      },
      server: {
        fs: {
          allow: [searchForWorkspaceRoot(process.cwd()), dirname(workspace)],
        },
        proxy: {
          '/api': {
            changeOrigin: true,
            rewrite: (path) => path.replace(/^\/api/, ''),
            // mock代理目标地址
            target: 'http://localhost:5320/api',
            ws: true,
          },
        },
      },
    },
  };
});
