import assert from "node:assert/strict";
import { test } from "node:test";
import type { WAMessage } from "@whiskeysockets/baileys";
import { buildOutgoingEvent } from "../src/events/messageOutgoing";

test("outgoing payload data is identical for the same message", async () => {
  const message = {
    key: {
      id: "MSG_2",
      fromMe: true,
      remoteJid: "628999888777@s.whatsapp.net",
      participant: "628111222333@s.whatsapp.net",
    },
    messageTimestamp: 1700000000,
    pushName: "Tester",
    message: {
      conversation: "Hello from me",
    },
  } as unknown as WAMessage;

  const first = await buildOutgoingEvent(message);
  const second = await buildOutgoingEvent(message);

  const { event_id: firstId, timestamp: firstTs, ...firstRest } = first;
  const { event_id: secondId, timestamp: secondTs, ...secondRest } = second;

  assert.equal(typeof firstId, "string");
  assert.equal(typeof secondId, "string");
  assert.equal(typeof firstTs, "string");
  assert.equal(typeof secondTs, "string");
  assert.deepEqual(firstRest, secondRest);
});
