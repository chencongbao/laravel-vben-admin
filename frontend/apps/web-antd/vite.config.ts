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
  const defaultProjectRoot = fileURLToPath(
    new URL('src/project-admin', import.meta.url),
  );
  const configuredProjectRoot = process.env.VBEN_ADMIN_PROJECT_ROOT?.trim();
  const projectRoot = configuredProjectRoot
    ? resolve(configuredProjectRoot)
    : defaultProjectRoot;
  const workspace = configuredWorkspace
    ? resolve(configuredWorkspace)
    : defaultWorkspace;

  if (!existsSync(workspace)) {
    throw new Error(
      `VBEN_ADMIN_WORKSPACE points to a missing file: ${workspace}`,
    );
  }

  if (!existsSync(projectRoot)) {
    throw new Error(
      `VBEN_ADMIN_PROJECT_ROOT points to a missing directory: ${projectRoot}`,
    );
  }

  return {
    application: {},
    vite: {
      resolve: {
        alias: {
          '#project-admin': projectRoot,
          '#workspace': workspace,
        },
      },
      server: {
        fs: {
          allow: [
            searchForWorkspaceRoot(process.cwd()),
            dirname(workspace),
            projectRoot,
          ],
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
