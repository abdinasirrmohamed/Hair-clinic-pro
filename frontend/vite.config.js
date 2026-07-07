import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const apiTarget = 'http://127.0.0.1:8000';

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],
  server: {
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
      '/storage': { target: apiTarget, changeOrigin: true },
    },
  },
  build: { outDir: 'dist', emptyOutDir: true },
});
