import assert from "node:assert/strict";
import { test } from "node:test";
import type { WAMessage } from "@whiskeysockets/baileys";
import { buildIncomingEvent } from "../src/events/messageIncoming";

test("incoming payload data is identical for the same message", async () => {
  const message = {
    key: {
      id: "MSG_1",
      fromMe: false,
      remoteJid: "628123456789@s.whatsapp.net",
      participant: "628123456789@s.whatsapp.net",
    },
    messageTimestamp: 1700000000,
    pushName: "Tester",
    message: {
      conversation: "Hello",
    },
  } as unknown as WAMessage;

  const first = await buildIncomingEvent(message);
  const second = await buildIncomingEvent(message);

  const { event_id: firstId, timestamp: firstTs, ...firstRest } = first;
  const { event_id: secondId, timestamp: secondTs, ...secondRest } = second;

  assert.equal(typeof firstId, "string");
  assert.equal(typeof secondId, "string");
  assert.equal(typeof firstTs, "string");
  assert.equal(typeof secondTs, "string");
  assert.deepEqual(firstRest, secondRest);
});
