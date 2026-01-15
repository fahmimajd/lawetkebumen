import Redis from "ioredis";
import { logger } from "../logger";

type OutboundRecord = {
  waMessageId: string | null;
  waTimestamp: string;
};

const redisUrl = process.env.REDIS_URL || "";
const ttlSeconds = Number(process.env.OUTBOUND_IDEMPOTENCY_TTL_SECONDS || 86400);

let redisClient: Redis | null = null;

if (redisUrl) {
  redisClient = new Redis(redisUrl, {
    maxRetriesPerRequest: 2,
    enableOfflineQueue: false,
  });

  redisClient.on("error", (error) => {
    logger.warn({ err: error }, "Redis error for idempotency store");
  });
}

const memoryStore = new Map<
  string,
  { value: OutboundRecord; expiresAt: number }
>();

const getFromMemory = (key: string): OutboundRecord | null => {
  const entry = memoryStore.get(key);
  if (!entry) {
    return null;
  }

  if (Date.now() > entry.expiresAt) {
    memoryStore.delete(key);
    return null;
  }

  return entry.value;
};

const setInMemory = (key: string, value: OutboundRecord): void => {
  memoryStore.set(key, {
    value,
    expiresAt: Date.now() + ttlSeconds * 1000,
  });
};

const redisKey = (clientMessageId: string) =>
  `wa:outbound:${clientMessageId}`;

export const getOutboundRecord = async (
  clientMessageId: string
): Promise<OutboundRecord | null> => {
  if (!clientMessageId) {
    return null;
  }

  if (!redisClient) {
    return getFromMemory(clientMessageId);
  }

  try {
    const raw = await redisClient.get(redisKey(clientMessageId));
    if (!raw) {
      return null;
    }

    return JSON.parse(raw) as OutboundRecord;
  } catch (error) {
    logger.warn({ err: error }, "Failed to read idempotency record from Redis");
    return getFromMemory(clientMessageId);
  }
};

export const setOutboundRecord = async (
  clientMessageId: string,
  record: OutboundRecord
): Promise<void> => {
  if (!clientMessageId) {
    return;
  }

  if (!redisClient) {
    setInMemory(clientMessageId, record);
    return;
  }

  try {
    await redisClient.set(
      redisKey(clientMessageId),
      JSON.stringify(record),
      "EX",
      ttlSeconds
    );
  } catch (error) {
    logger.warn({ err: error }, "Failed to write idempotency record to Redis");
    setInMemory(clientMessageId, record);
  }
};
