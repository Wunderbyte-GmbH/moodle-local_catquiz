// This file is part of Moodle - http://moodle.org/
//
// Read-endpoint load test for local_catquiz (CAT manager + statistics).
//
// k6 logs in once per virtual user with the Moodle session flow (fetch the
// login page, read the logintoken, POST the credentials) and then repeatedly
// GETs the heavy read pages the "Performance: CAT Manager & Statistik" work is
// about. Parameters come from environment variables set by the workflow:
//
//   BASE_URL    Moodle wwwroot (e.g. http://localhost:8000)
//   ADMIN_USER  admin username (default "admin")
//   ADMIN_PASS  admin password
//   SCALEID     seeded root scale id
//   CONTEXTID   seeded context id
//   VUS         concurrent virtual users (default 25)
//   DURATION    plateau duration (default 90s)
//
// Run: k6 run catquiz-read-endpoints.js
//
// @copyright 2026 Wunderbyte GmbH
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import http from 'k6/http';
import { check, sleep, fail } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const USER = __ENV.ADMIN_USER || 'admin';
const PASS = __ENV.ADMIN_PASS || 'Admin!23';
const SCALEID = __ENV.SCALEID || '';
const CONTEXTID = __ENV.CONTEXTID || '';

const loginFailRate = new Rate('login_failures');
const managerTrend = new Trend('catmanager_ms', true);
const statsTrend = new Trend('statistics_ms', true);

export const options = {
  scenarios: {
    read: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '10s', target: Number(__ENV.VUS || 25) },
        { duration: __ENV.DURATION || '90s', target: Number(__ENV.VUS || 25) },
        { duration: '5s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    // Fail the run if reads error out or the pages get pathologically slow.
    login_failures: ['rate<0.01'],
    http_req_failed: ['rate<0.02'],
    catmanager_ms: ['p(95)<4000'],
    statistics_ms: ['p(95)<6000'],
  },
};

// Extract Moodle's login token from the login form markup.
function loginToken(html) {
  const m = html.match(/name="logintoken"\s+value="([a-zA-Z0-9]+)"/);
  return m ? m[1] : '';
}

// Each VU authenticates once in setup-per-VU fashion (first iteration), then
// reuses its cookie jar for the read requests.
export default function () {
  const jar = http.cookieJar();
  if (!jar.cookiesForURL(BASE + '/').MoodleSession) {
    const page = http.get(`${BASE}/login/index.php`);
    const token = loginToken(page.body);
    const res = http.post(`${BASE}/login/index.php`, {
      username: USER,
      password: PASS,
      logintoken: token,
    });
    const ok = check(res, {
      'logged in': (r) => r.url.indexOf('/login/index.php') === -1 || r.body.indexOf('logout') !== -1,
    });
    loginFailRate.add(!ok);
    if (!ok) {
      fail('login failed - check ADMIN_USER / ADMIN_PASS');
    }
  }

  // CAT manager dashboard (aggregates over the scale tree / item pool).
  const manager = http.get(`${BASE}/local/catquiz/manage_catscales.php`);
  managerTrend.add(manager.timings.duration);
  check(manager, { 'manager 200': (r) => r.status === 200 });

  // Statistics view for the seeded scale + context (histogram aggregation).
  if (SCALEID && CONTEXTID) {
    const stats = http.get(
      `${BASE}/local/catquiz/manage_catscales.php?contextid=${CONTEXTID}&scaleid=${SCALEID}`
    );
    statsTrend.add(stats.timings.duration);
    check(stats, { 'statistics 200': (r) => r.status === 200 });
  }

  sleep(1);
}

// k6 v2 no longer writes anything for --summary-export: the run produced correct
// numbers while the uploaded artefact contained a summary with every metric at zero,
// which reads like a run that never happened. handleSummary() is the supported way
// and writes the same structure the evaluation expects.
export function handleSummary(data) {
    return {
        'k6-summary.json': JSON.stringify(data, null, 2),
        stdout: '\nSee k6-summary.json for the full metrics.\n',
    };
}
