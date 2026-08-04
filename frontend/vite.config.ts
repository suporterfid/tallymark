import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({ base: '/build/dashboard/', plugins: [vue()], build: { outDir: '../public/build/dashboard', emptyOutDir: true } })
