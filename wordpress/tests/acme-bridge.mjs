import assert from 'node:assert/strict';
import worker from '../staging/acme-bridge.mjs';
let called;
globalThis.fetch = async (url, options) => { called = { url, options }; return new Response('test-challenge'); };
for (const url of ['https://other.example/.well-known/acme-challenge/test', 'https://www.chinamudlogging.com/', 'https://www.chinamudlogging.com/.well-known/acme-challenge/../wp-admin']) {
  called = null;
  assert.equal((await worker.fetch(new Request(url))).status, 404);
  assert.equal(called, null);
}
const request = new Request('https://www.chinamudlogging.com/.well-known/acme-challenge/cml_test-123', { headers: { Authorization: 'must-not-forward' } });
const response = await worker.fetch(request);
assert.equal(await response.text(), 'test-challenge');
assert.equal(called.url, 'https://preview.chinamudlogging.com/.well-known/acme-challenge/cml_test-123');
assert.equal(called.options.headers, undefined);
assert.equal(called.options.redirect, 'manual');
assert.equal((await worker.fetch(new Request(request.url, { method: 'POST' }))).status, 405);
console.log('ACME bridge: host/path scope, credential isolation and method checks passed.');
