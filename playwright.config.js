// @ts-check
const process = require('node:process');

module.exports = {
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 7_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8001',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
    },
    projects: [
        {
            name: 'chromium',
            use: { browserName: 'chromium' },
        },
    ],
};
