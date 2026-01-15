import { randomUUID } from "node:crypto";
import { logger } from "../logger";
import { sendWebhookWithRetry } from "../webhooks/sender";

type AckValue = "sent" | "delivered" | "read";

type MessageAckEvent = {
  event_id: string;
  correlation_id: string;
  event_type: "message.ack";
  event_version: 1;
  timestamp: string;
  data: {
    wa_message_id: string;
    ack: AckValue;
    wa_timestamp: string;
  };
};

type MessageStatusUpdate = {
  key?: {
    id?: string | null;
    remoteJid?: string | null;
    fromMe?: boolean | null;
  };
  update?: {
    status?: number | string | null;
    timestamp?: number | string | null;
  };
};

const mapStatusToAck = (status?: number | string | null): AckValue | null => {
  if (status == null) {
    return null;
  }

  if (typeof status === "string") {
    const normalized = status.toLowerCase();
    if (normalized === "sent" || normalized === "server_ack") {
      return "sent";
    }
    if (normalized === "delivered" || normalized === "delivery_ack") {
      return "delivered";
    }
    if (normalized === "read" || normalized === "played") {
      return "read";
    }
    return null;
  }

  if (status === 2) {
    return "sent";
  }
  if (status === 3) {
    return "delivered";
  }
  if (status === 4 || status === 5) {
    return "read";
  }

  return null;
};

const normalizeTimestamp = (timestamp?: number | string | null): string => {
  if (!timestamp) {
    return new Date().toISOString();
  }

  let seconds: number | null = null;

  if (typeof timestamp === "number") {
    seconds = timestamp;
  } else if (typeof timestamp === "string") {
    seconds = Number(timestamp);
  }

  if (!seconds || Number.isNaN(seconds)) {
    return new Date().toISOString();
  }

  return new Date(seconds * 1000).toISOString();
};

const buildAckEvent = (update: MessageStatusUpdate): MessageAckEvent | null => {
  if (update.key?.fromMe === false) {
    return null;
  }

  const waMessageId = update.key?.id;
  const ack = mapStatusToAck(update.update?.status ?? null);

  if (!waMessageId || !ack) {
    return null;
  }

  const eventId = randomUUID();

  return {
    event_id: eventId,
    correlation_id: waMessageId || eventId,
    event_type: "message.ack",
    event_version: 1,
    timestamp: new Date().toISOString(),
    data: {
      wa_message_id: waMessageId,
      ack,
      wa_timestamp: normalizeTimestamp(update.update?.timestamp ?? null),
    },
  };
};

export const handleMessageAck = async (update: MessageStatusUpdate): Promise<void> => {
  const event = buildAckEvent(update);

  if (!event) {
    return;
  }

  try {
    await sendWebhookWithRetry(event);
  } catch (error) {
    logger.error(
      {
        err: error,
        eventId: event.event_id,
        correlationId: event.correlation_id,
        waMessageId: event.data.wa_message_id,
      },
      "Failed to deliver webhook for message ack"
    );
  }
};
