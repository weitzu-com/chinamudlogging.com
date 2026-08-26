import { defineConfig } from "astro/config";

export default defineConfig({
  site: "https://www.chinamudlogging.com",
  output: "static",
  trailingSlash: "always",
  build: {
    format: "directory",
  },
});
