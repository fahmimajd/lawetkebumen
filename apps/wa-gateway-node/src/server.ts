import { createHash } from "node:crypto";
import http from "node:http";
import {
  getHealthState,
  getLatestQr,
  logoutClient,
  reconnectClient,
  resetClient,
  revokeMessage,
  sendMediaMessage,
  sendTextMessage,
} from "./client";
import { logger } from "./logger";
import { getOutboundRecord, setOutboundRecord } from "./store/outboundIdempotency";

type SendMessagePayload = {
  client_message_id?: string;
  to_wa_id?: string;
  type?: string;
  text?: string;
  media_url?: string;
  media_mime?: string | null;
  media_name?: string | null;
  reply_to_wa_message_id?: string | null;
  reply_to_sender_wa_id?: string | null;
  reply_to_text?: string | null;
  reply_to_type?: string | null;
};

type RevokeMessagePayload = {
  to_wa_id?: string;
  wa_message_id?: string;
  from_me?: boolean;
  participant?: string | null;
};

type SendMessageRequest = {
  clientMessageId: string;
  toWaId: string;
  type: "text" | "image" | "video" | "audio" | "document" | "sticker";
  text: string | null;
  mediaUrl: string | null;
  mediaMime: string | null;
  mediaName: string | null;
  replyToWaMessageId: string | null;
  replyToSenderWaId: string | null;
  replyToText: string | null;
  replyToType: string | null;
};

type RevokeMessageRequest = {
  toWaId: string;
  waMessageId: string;
  fromMe: boolean;
  participant: string | null;
};

const gatewayToken = process.env.WA_GATEWAY_TOKEN || "";
const sendMaxBytes = Number(process.env.SEND_MAX_BYTES || 1_000_000);
const sendRateLimit = Number(process.env.SEND_RATE_LIMIT_RPM || 120);
const sendRateWindowMs = 60_000;

const rateBuckets = new Map<string, { count: number; resetAt: number }>();

const getRateLimitKey = (req: http.IncomingMessage): string => {
  const authHeader = req.headers.authorization || "";
  if (authHeader) {
    const hashed = createHash("sha256").update(authHeader).digest("hex");
    return `auth:${hashed}`;
  }

  return `ip:${req.socket.remoteAddress ?? "unknown"}`;
};

const isRateLimited = (key: string): boolean => {
  if (sendRateLimit <= 0) {
    return false;
  }

  const now = Date.now();
  const bucket = rateBuckets.get(key);

  if (!bucket || now > bucket.resetAt) {
    rateBuckets.set(key, { count: 1, resetAt: now + sendRateWindowMs });
    return false;
  }

  if (bucket.count >= sendRateLimit) {
    return true;
  }

  bucket.count += 1;
  return false;
};

const readBody = (req: http.IncomingMessage, maxBytes: number): Promise<string> => {
  return new Promise((resolve, reject) => {
    let raw = "";
    req.on("data", (chunk) => {
      raw += chunk;
      if (maxBytes > 0 && raw.length > maxBytes) {
        reject(new Error("Payload too large"));
        req.destroy();
      }
    });
    req.on("end", () => resolve(raw));
    req.on("error", reject);
  });
};

const parseJson = (rawBody: string): SendMessagePayload | null => {
  if (!rawBody) {
    return {};
  }

  try {
    return JSON.parse(rawBody);
  } catch {
    return null;
  }
};

const normalizeSendPayload = (
  payload: SendMessagePayload
): { ok: true; value: SendMessageRequest } | { ok: false; message: string } => {
  const clientMessageId = payload.client_message_id;
  const toWaId = payload.to_wa_id;
  const type = payload.type;
  const text = payload.text ?? null;
  const mediaUrl = payload.media_url ?? null;
  const mediaMime = payload.media_mime ?? null;
  const mediaName = payload.media_name ?? null;
  const replyToWaMessageId = payload.reply_to_wa_message_id ?? null;
  const replyToSenderWaId = payload.reply_to_sender_wa_id ?? null;
  const replyToText = payload.reply_to_text ?? null;
  const replyToType = payload.reply_to_type ?? null;

  if (!clientMessageId || typeof clientMessageId !== "string") {
    return { ok: false, message: "client_message_id is required" };
  }

  if (!toWaId || typeof toWaId !== "string") {
    return { ok: false, message: "to_wa_id is required" };
  }

  if (
    !type ||
    !["text", "image", "video", "audio", "document", "sticker"].includes(type)
  ) {
    return { ok: false, message: "type is invalid" };
  }

  if (type === "text") {
    if (!text || typeof text !== "string") {
      return { ok: false, message: "text is required" };
    }
  } else {
    if (!mediaUrl || typeof mediaUrl !== "string") {
      return { ok: false, message: "media_url is required" };
    }
  }

  return {
    ok: true,
    value: {
      clientMessageId,
      toWaId,
      type: type as SendMessageRequest["type"],
      text: typeof text === "string" ? text : null,
      mediaUrl,
      mediaMime,
      mediaName,
      replyToWaMessageId:
        typeof replyToWaMessageId === "string" && replyToWaMessageId !== ""
          ? replyToWaMessageId
          : null,
      replyToSenderWaId:
        typeof replyToSenderWaId === "string" && replyToSenderWaId !== ""
          ? replyToSenderWaId
          : null,
      replyToText: typeof replyToText === "string" && replyToText !== "" ? replyToText : null,
      replyToType: typeof replyToType === "string" && replyToType !== "" ? replyToType : null,
    },
  };
};

const normalizeRevokePayload = (
  payload: RevokeMessagePayload
): { ok: true; value: RevokeMessageRequest } | { ok: false; message: string } => {
  const toWaId = payload.to_wa_id;
  const waMessageId = payload.wa_message_id;
  const fromMe = payload.from_me ?? true;
  const participant = payload.participant ?? null;

  if (!toWaId || typeof toWaId !== "string") {
    return { ok: false, message: "to_wa_id is required" };
  }

  if (!waMessageId || typeof waMessageId !== "string") {
    return { ok: false, message: "wa_message_id is required" };
  }

  if (typeof fromMe !== "boolean") {
    return { ok: false, message: "from_me must be boolean" };
  }

  return {
    ok: true,
    value: {
      toWaId,
      waMessageId,
      fromMe,
      participant: typeof participant === "string" && participant !== "" ? participant : null,
    },
  };
};

const requireAuth = (req: http.IncomingMessage, res: http.ServerResponse): boolean => {
  if (!gatewayToken) {
    res.statusCode = 500;
    res.setHeader("Content-Type", "application/json");
    res.end(JSON.stringify({ ok: false, message: "Gateway token not configured" }));
    return false;
  }

  const authHeader = req.headers.authorization || "";
  if (authHeader !== `Bearer ${gatewayToken}`) {
    res.statusCode = 401;
    res.setHeader("Content-Type", "application/json");
    res.end(JSON.stringify({ ok: false, message: "Unauthorized" }));
    return false;
  }

  return true;
};

export const startServer = (port: number): http.Server => {
  const server = http.createServer(async (req, res) => {
    const url = new URL(req.url ?? "/", "http://localhost");

    if (req.method === "GET" && url.pathname === "/health") {
      const health = getHealthState();
      res.statusCode = 200;
      res.setHeader("Content-Type", "application/json");
      res.end(
        JSON.stringify({
          ok: true,
          service: "wa-gateway",
          health,
        })
      );
      return;
    }

    if (req.method === "GET" && url.pathname === "/status") {
      if (!requireAuth(req, res)) {
        return;
      }

      const health = getHealthState();
      res.statusCode = 200;
      res.setHeader("Content-Type", "application/json");
      res.end(
        JSON.stringify({
          ok: true,
          status: health.connection,
          last_disconnect_reason: health.lastDisconnectReason,
          updated_at: health.updatedAt,
        })
      );
      return;
    }

    if (req.method === "GET" && url.pathname === "/qr") {
      if (!requireAuth(req, res)) {
        return;
      }

      const health = getHealthState();
      const qr = getLatestQr();
      res.statusCode = 200;
      res.setHeader("Content-Type", "application/json");
      res.end(
        JSON.stringify({
          ok: true,
          status: health.connection,
          qr: qr.qr,
          qr_updated_at: qr.updatedAt,
        })
      );
      return;
    }

    if (req.method === "POST" && url.pathname === "/reconnect") {
      if (!requireAuth(req, res)) {
        return;
      }

      try {
        await reconnectClient();
        res.statusCode = 200;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: true }));
      } catch (error) {
        logger.error({ err: error }, "Failed to reconnect WhatsApp client");
        res.statusCode = 500;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: false, message: "Failed to reconnect client" }));
      }
      return;
    }

    if (req.method === "POST" && url.pathname === "/logout") {
      if (!requireAuth(req, res)) {
        return;
      }

      try {
        await logoutClient();
        res.statusCode = 200;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: true }));
      } catch (error) {
        logger.error({ err: error }, "Failed to logout WhatsApp client");
        res.statusCode = 500;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: false, message: "Failed to logout client" }));
      }
      return;
    }

    if (req.method === "POST" && url.pathname === "/reset") {
      if (!requireAuth(req, res)) {
        return;
      }

      try {
        await resetClient();
        res.statusCode = 200;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: true }));
      } catch (error) {
        logger.error({ err: error }, "Failed to reset WhatsApp client");
        res.statusCode = 500;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: false, message: "Failed to reset client" }));
      }
      return;
    }

    if (req.method === "POST" && url.pathname === "/revoke") {
      if (!requireAuth(req, res)) {
        return;
      }

      try {
        if (sendMaxBytes > 0) {
          const contentLength = Number(req.headers["content-length"] || 0);
          if (contentLength > sendMaxBytes) {
            res.statusCode = 413;
            res.setHeader("Content-Type", "application/json");
            res.end(JSON.stringify({ ok: false, message: "Payload too large" }));
            return;
          }
        }

        const rateKey = getRateLimitKey(req);
        if (isRateLimited(rateKey)) {
          res.statusCode = 429;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: "Rate limit exceeded" }));
          return;
        }

        const rawBody = await readBody(req, sendMaxBytes);
        const payload = parseJson(rawBody) as RevokeMessagePayload | null;

        if (!payload) {
          res.statusCode = 400;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: "Invalid JSON payload" }));
          return;
        }

        const normalized = normalizeRevokePayload(payload);

        if (!normalized.ok) {
          res.statusCode = 422;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: normalized.message }));
          return;
        }

        const { toWaId, waMessageId, fromMe, participant } = normalized.value;

        await revokeMessage(toWaId, waMessageId, fromMe, participant);

        res.statusCode = 200;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: true }));
        return;
      } catch (error) {
        logger.error({ err: error }, "Failed to revoke message");
        res.statusCode = 500;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: false, message: "Failed to revoke message" }));
        return;
      }
    }

    if (req.method === "POST" && url.pathname === "/send") {
      if (!requireAuth(req, res)) {
        return;
      }

      try {
        if (sendMaxBytes > 0) {
          const contentLength = Number(req.headers["content-length"] || 0);
          if (contentLength > sendMaxBytes) {
            res.statusCode = 413;
            res.setHeader("Content-Type", "application/json");
            res.end(JSON.stringify({ ok: false, message: "Payload too large" }));
            return;
          }
        }

        const rateKey = getRateLimitKey(req);
        if (isRateLimited(rateKey)) {
          res.statusCode = 429;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: "Rate limit exceeded" }));
          return;
        }

        const rawBody = await readBody(req, sendMaxBytes);
        const payload = parseJson(rawBody);

        if (!payload) {
          res.statusCode = 400;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: "Invalid JSON payload" }));
          return;
        }

        const normalized = normalizeSendPayload(payload);

        if (!normalized.ok) {
          res.statusCode = 422;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: normalized.message }));
          return;
        }

        const {
          clientMessageId,
          toWaId,
          text,
          type,
          mediaUrl,
          mediaMime,
          mediaName,
          replyToWaMessageId,
          replyToSenderWaId,
          replyToText,
          replyToType,
        } = normalized.value;
        const correlationId =
          (req.headers["x-correlation-id"] as string | undefined) ||
          clientMessageId;
        const cached = await getOutboundRecord(clientMessageId);

        if (cached) {
          res.statusCode = 200;
          res.setHeader("Content-Type", "application/json");
          res.end(
            JSON.stringify({
              ok: true,
              wa_message_id: cached.waMessageId,
              wa_timestamp: cached.waTimestamp,
            })
          );
          return;
        }

        const replyQuote = replyToWaMessageId
          ? {
              waMessageId: replyToWaMessageId,
              senderWaId: replyToSenderWaId,
              text: replyToText,
              type: replyToType,
            }
          : null;

        const result =
          type === "text"
            ? await sendTextMessage(toWaId, text || "", replyQuote)
            : await sendMediaMessage(
                toWaId,
                type,
                mediaUrl || "",
                mediaMime,
                text,
                mediaName,
                replyQuote
              );

        logger.info(
          {
            clientMessageId,
            waMessageId: result.waMessageId,
            correlationId,
          },
          "Outbound message sent"
        );

        await setOutboundRecord(clientMessageId, {
          waMessageId: result.waMessageId,
          waTimestamp: result.waTimestamp,
        });

        res.statusCode = 200;
        res.setHeader("Content-Type", "application/json");
        res.end(
          JSON.stringify({
            ok: true,
            wa_message_id: result.waMessageId,
            wa_timestamp: result.waTimestamp,
          })
        );
        return;
      } catch (error) {
        const isTooLarge =
          error instanceof Error && error.message === "Payload too large";
        if (isTooLarge) {
          res.statusCode = 413;
          res.setHeader("Content-Type", "application/json");
          res.end(JSON.stringify({ ok: false, message: "Payload too large" }));
          return;
        }

        logger.error({ err: error }, "Failed to send outbound message");
        res.statusCode = 500;
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ ok: false, message: "Failed to send message" }));
        return;
      }
    }

    res.statusCode = 404;
    res.setHeader("Content-Type", "application/json");
    res.end(JSON.stringify({ ok: false, message: "Not found" }));
  });

  server.listen(port, "0.0.0.0", () => {
    logger.info({ port }, "WA gateway HTTP server listening");
  });

  return server;
};
