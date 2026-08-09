import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({ plugins:[react()], build:{ outDir:'../../build', emptyOutDir:true, rollupOptions:{ output:{ entryFileNames:'verbum-app.js', assetFileNames:'verbum-app.[ext]' } } }, test:{ environment:'jsdom' } });
