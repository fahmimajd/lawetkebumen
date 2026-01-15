import { setTimeout as delay } from "node:timers/promises";
import { logger } from "../logger";
import { signPayload } from "./signature";

type WebhookEvent = {
  event_id: string;
  correlation_id: string;
  event_type: string;
  event_version: number;
  timestamp: string;
  data: Record<string, unknown>;
};

const webhookUrl = process.env.WEBHOOK_URL || "";
const webhookSecret = process.env.WEBHOOK_SECRET || "";
const webhookTimeoutMs = Number(process.env.WEBHOOK_TIMEOUT_MS || 5000);
const webhookRetryMax = Number(process.env.WEBHOOK_RETRY_MAX || 5);
const webhookRetryBaseMs = Number(process.env.WEBHOOK_RETRY_BASE_MS || 500);
const webhookCircuitFailures = Number(process.env.WEBHOOK_CIRCUIT_FAILURES || 10);
const webhookCircuitCooldownMs = Number(process.env.WEBHOOK_CIRCUIT_COOLDOWN_MS || 60000);

let circuitOpenUntil = 0;
let consecutiveFailures = 0;

const buildHeaders = (
  payload: string,
  eventId: string,
  timestamp: number,
  correlationId: string
) => {
  const signature = signPayload(payload, webhookSecret);

  return {
    "Content-Type": "application/json",
    "X-Signature": signature,
    "X-Event-Id": eventId,
    "X-Timestamp": String(timestamp),
    "X-Correlation-Id": correlationId,
  };
};

const postOnce = async (
  payload: string,
  eventId: string,
  correlationId: string
): Promise<void> => {
  if (!webhookUrl) {
    throw new Error("WEBHOOK_URL is not configured");
  }

  if (!webhookSecret) {
    throw new Error("WEBHOOK_SECRET is not configured");
  }

  const timestamp = Math.floor(Date.now() / 1000);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), webhookTimeoutMs);

  try {
    const response = await fetch(webhookUrl, {
      method: "POST",
      headers: buildHeaders(payload, eventId, timestamp, correlationId),
      body: payload,
      signal: controller.signal,
    });

    if (!response.ok) {
      const responseText = await response.text().catch(() => "");
      throw new Error(
        `Webhook responded with ${response.status}: ${responseText}`.trim()
      );
    }
  } finally {
    clearTimeout(timeout);
  }
};

export const sendWebhookWithRetry = async (event: WebhookEvent): Promise<void> => {
  if (webhookCircuitFailures > 0 && Date.now() < circuitOpenUntil) {
    logger.warn(
      { eventId: event.event_id, correlationId: event.correlation_id },
      "Webhook circuit open, skipping delivery"
    );
    throw new Error("Webhook circuit open");
  }

  const payload = JSON.stringify(event);

  for (let attempt = 0; attempt <= webhookRetryMax; attempt += 1) {
    try {
      await postOnce(payload, event.event_id, event.correlation_id);
      consecutiveFailures = 0;
      circuitOpenUntil = 0;
      logger.info(
        { eventId: event.event_id, correlationId: event.correlation_id, attempt },
        "Webhook delivered"
      );
      return;
    } catch (error) {
      const isLastAttempt = attempt >= webhookRetryMax;
      consecutiveFailures += 1;
      if (webhookCircuitFailures > 0 && consecutiveFailures >= webhookCircuitFailures) {
        circuitOpenUntil = Date.now() + webhookCircuitCooldownMs;
        consecutiveFailures = 0;
      }
      logger.warn(
        { err: error, eventId: event.event_id, correlationId: event.correlation_id, attempt },
        "Webhook delivery failed"
      );

      if (isLastAttempt) {
        throw error;
      }

      const backoff = webhookRetryBaseMs * 2 ** attempt;
      const jitter = Math.floor(Math.random() * 100);
      await delay(backoff + jitter);
    }
  }
};
