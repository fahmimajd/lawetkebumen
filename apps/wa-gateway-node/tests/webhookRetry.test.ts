import assert from "node:assert/strict";
import { test } from "node:test";

test("webhook retry stops after max attempts", async () => {
  process.env.WEBHOOK_URL = "http://example.test/webhook";
  process.env.WEBHOOK_SECRET = "test-secret";
  process.env.WEBHOOK_TIMEOUT_MS = "100";
  process.env.WEBHOOK_RETRY_MAX = "2";
  process.env.WEBHOOK_RETRY_BASE_MS = "0";

  let attempts = 0;
  const originalFetch = global.fetch;

  global.fetch = (async () => {
    attempts += 1;
    return {
      ok: false,
      status: 500,
      text: async () => "fail",
    } as Response;
  }) as typeof fetch;

  const { sendWebhookWithRetry } = await import("../src/webhooks/sender");

  const event = {
    event_id: "evt-1",
    correlation_id: "corr-1",
    event_type: "message.incoming",
    event_version: 1,
    timestamp: new Date().toISOString(),
    data: { test: true },
  };

  const originalRandom = Math.random;
  Math.random = () => 0;

  try {
    await assert.rejects(sendWebhookWithRetry(event));
  } finally {
    Math.random = originalRandom;
    global.fetch = originalFetch;
  }

  assert.equal(attempts, 3);
});
