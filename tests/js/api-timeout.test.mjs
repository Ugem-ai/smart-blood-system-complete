import test from 'node:test';
import assert from 'node:assert/strict';
import api from '../../resources/js/lib/api.js';

test('api client allows slower donor acceptance requests to complete', () => {
  assert.ok(api.defaults.timeout >= 60000, `Expected timeout to be at least 60000ms, received ${api.defaults.timeout}`);
});
