import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const apiTarget = 'http://127.0.0.1:8000';

export default defineConfig({
  plugins: [react()],
  cacheDir: '../tmp/vite-cache',
  server: {
    proxy: {
      '/api': apiTarget,
      '/storage': apiTarget
    }
  },
  build: { outDir: 'dist', emptyOutDir: true }
});
