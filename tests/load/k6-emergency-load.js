import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 20,
  duration: '60s',
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<800'],
  },
};

const baseUrl = __ENV.BASE_URL || 'http://localhost';
const token = __ENV.API_TOKEN || '';

export default function () {
  const res = http.get(`${baseUrl}/api/v1/monitor/health`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  check(res, {
    'health endpoint status is 200': (r) => r.status === 200,
  });

  sleep(1);
}
