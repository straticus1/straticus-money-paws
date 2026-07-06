import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // Both suites share one test database with TRUNCATE in beforeEach;
    // parallel files would race each other.
    fileParallelism: false,
  },
});
