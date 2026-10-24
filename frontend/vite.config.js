import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    hmr: {
      host: '127.0.0.1',
      protocol: 'ws',
      clientPort: 5173,
    },
    proxy: {
      '/backend': {
        target: 'http://127.0.0.1:8000',
        rewrite: (path) => path.replace(/^\/backend/, '/backend/api.php'),
        configure: (proxy) => {
          proxy.on('proxyReq', (proxyReq) => proxyReq.removeHeader('origin'));
        },
      },
    },
  },
});
