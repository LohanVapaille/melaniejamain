import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.scss",
                "resources/js/app.js",
                "resources/js/stars.js",
                "resources/js/voirplus.js",
                "resources/js/visualisation3d.js",
            ], // <-- Change .css en .scss ici
            refresh: true,
        }),
    ],
});
