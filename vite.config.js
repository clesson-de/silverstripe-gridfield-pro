import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'client/dist',
    emptyOutDir: true,
    lib: {
      entry: 'client/src/index.js',
      name: 'SilverstripeGridFieldPro',
      formats: ['iife'],
      fileName: () => 'js/silverstripe-gridfield-pro.js',
      cssFileName: 'silverstripe-gridfield-pro',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'css/[name][extname]',
      },
    },
  },
});
