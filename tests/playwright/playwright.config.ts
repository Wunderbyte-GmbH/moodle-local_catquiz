import {defineConfig, devices} from '@playwright/test';
import {baseUrl} from './support/env';

/**
 * Playwright configuration for the local_catquiz CAT manager tests.
 *
 * Retries only in CI: locally a flaky test should be visible immediately rather
 * than hidden by a retry.
 */
export default defineConfig({
    testDir: './tests',
    // Moodle over PHP's built-in server is slow on first hit of a page: caches are
    // built on demand and every page pulls many subresources. The 30s default was
    // enough for a warm page but not for a cold one, which showed up as a login
    // that "randomly" timed out.
    timeout: 90_000,
    expect: {timeout: 15_000},
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [['html', {open: 'never'}], ['list']],
    use: {
        baseURL: baseUrl(),
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {name: 'chromium', use: {...devices['Desktop Chrome']}},
    ],
});
