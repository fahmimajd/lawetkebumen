import assert from "node:assert/strict";
import { test } from "node:test";
import { signPayload } from "../src/webhooks/signature";

test("signature generation is deterministic", () => {
  const payload = "{\"hello\":\"world\"}";
  const secret = "test-secret";

  const first = signPayload(payload, secret);
  const second = signPayload(payload, secret);

  assert.equal(first, second);
  assert.equal(first, "84cc33df716ed0b0598f07437c94069ace3730358778a592bd6bbd1423d111f3");
});
