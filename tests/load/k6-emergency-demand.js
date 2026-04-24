import http from 'k6/http';
import exec from 'k6/execution';
import { check } from 'k6';

const hospitalCount = Number(__ENV.HOSPITAL_COUNT || 50);
const baseUrl = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const password = __ENV.HOSPITAL_PASSWORD || 'Password123!';
const city = __ENV.CITY || 'Metro Manila';
const metricsToken = __ENV.METRICS_TOKEN || '';
const requestBloodType = __ENV.BLOOD_TYPE || 'A+';

export const options = {
  scenarios: {
    emergency_hospital_spike: {
      executor: 'shared-iterations',
      vus: hospitalCount,
      iterations: hospitalCount,
      maxDuration: '2m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<200'],
    checks: ['rate>0.98'],
  },
};

export function setup() {
  const tokens = [];

  for (let i = 1; i <= hospitalCount; i += 1) {
    const email = `load.hospital.${i}@example.com`;
    const loginResponse = http.post(
      `${baseUrl}/api/v1/login`,
      JSON.stringify({ email, password }),
      {
        headers: { 'Content-Type': 'application/json' },
        tags: { name: 'hospital_login' },
      }
    );

    check(loginResponse, {
      [`login ${email} returns 200`]: (res) => res.status === 200,
    });

    const token = loginResponse.json('token');
    if (token) {
      tokens.push(token);
    }
  }

  return { tokens };
}

export default function (data) {
  const index = exec.scenario.iterationInTest;
  const token = data.tokens[index];

  const response = http.post(
    `${baseUrl}/api/hospital/request`,
    JSON.stringify({
      blood_type: requestBloodType,
      units_required: 1,
      urgency_level: 'high',
      city,
      latitude: 14.5995,
      longitude: 120.9842,
      distance_limit_km: 25,
    }),
    {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      tags: { name: 'create_emergency_request' },
    }
  );

  check(response, {
    'request returns 201': (res) => res.status === 201,
  });
}

export function teardown() {
  if (!metricsToken) {
    return;
  }

  const metricsResponse = http.get(`${baseUrl}/api/v1/monitor/metrics`, {
    headers: { 'X-Metrics-Token': metricsToken },
    tags: { name: 'prometheus_metrics' },
  });

  check(metricsResponse, {
    'metrics endpoint returns 200': (res) => res.status === 200,
  });
}
