import fs from "node:fs";
import path from "node:path";
import {
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeWASocket,
  useMultiFileAuthState,
  type ConnectionState,
  type WAMessageKey,
  type WASocket,
  type WAMessage,
} from "@whiskeysockets/baileys";
import { logger } from "./logger";
import { handleIncomingMessage, registerLidMapping } from "./events/messageIncoming";
import { handleOutgoingMessage } from "./events/messageOutgoing";
import { configureLidMappingStorage } from "./events/messagePayload";
import { handleMessageAck } from "./events/messageAck";

type HealthState = {
  connection: ConnectionState["connection"] | "unknown";
  lastDisconnectReason: number | null;
  updatedAt: string;
};

const healthState: HealthState = {
  connection: "unknown",
  lastDisconnectReason: null,
  updatedAt: new Date().toISOString(),
};

let latestQr: { value: string | null; updatedAt: string | null } = {
  value: null,
  updatedAt: null,
};
let socket: WASocket | null = null;
let reconnectTimer: NodeJS.Timeout | null = null;
let starting = false;
let reconnectAttempts = 0;
const groupSubjectCache = new Map<string, { subject: string | null; expiresAt: number }>();

type ReplyQuote = {
  waMessageId: string;
  senderWaId: string | null;
  text: string | null;
  type: string | null;
};

const authDir = process.env.WA_AUTH_DIR || "./storage/wa-auth";
const reconnectBaseMs = Number(process.env.RECONNECT_BASE_MS || 1000);
const reconnectMaxMs = Number(process.env.RECONNECT_MAX_MS || 30000);
const reconnectMaxAttempts = Number(process.env.RECONNECT_MAX_ATTEMPTS || 10);
const reconnectCooldownMs = Number(process.env.RECONNECT_COOLDOWN_MS || 60000);
const groupSubjectCacheMs = Number(process.env.GROUP_SUBJECT_CACHE_MS || 300000);

const ensureAuthDir = () => {
  const fullPath = path.resolve(authDir);
  fs.mkdirSync(fullPath, { recursive: true });
};

const clearAuthDir = () => {
  const fullPath = path.resolve(authDir);
  if (fullPath === "/" || fullPath.length < 2) {
    logger.error({ fullPath }, "Refusing to delete auth directory");
    return;
  }

  if (fs.existsSync(fullPath)) {
    fs.rmSync(fullPath, { recursive: true, force: true });
  }
};

const updateHealth = (update: Partial<HealthState>) => {
  Object.assign(healthState, update, { updatedAt: new Date().toISOString() });
};

const updateQr = (qr: string | null) => {
  latestQr = {
    value: qr,
    updatedAt: qr ? new Date().toISOString() : null,
  };
};

const getGroupSubject = async (jid: string): Promise<string | null> => {
  const now = Date.now();
  const cached = groupSubjectCache.get(jid);
  if (cached && cached.expiresAt > now) {
    return cached.subject;
  }

  if (!socket) {
    return null;
  }

  try {
    const metadata = await socket.groupMetadata(jid);
    const subject = metadata?.subject ?? null;
    groupSubjectCache.set(jid, { subject, expiresAt: now + groupSubjectCacheMs });
    return subject;
  } catch (error) {
    logger.warn({ err: error, jid }, "Failed to load group metadata");
    return null;
  }
};

const scheduleReconnect = () => {
  if (reconnectTimer) {
    return;
  }

  if (reconnectAttempts >= reconnectMaxAttempts) {
    logger.warn(
      { attempts: reconnectAttempts, cooldownMs: reconnectCooldownMs },
      "Reconnect attempts exceeded, cooling down"
    );
    reconnectAttempts = 0;
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      void startClient();
    }, reconnectCooldownMs);
    return;
  }

  const backoff = Math.min(reconnectMaxMs, reconnectBaseMs * 2 ** reconnectAttempts);
  const jitter = Math.floor(Math.random() * 250);
  reconnectAttempts += 1;

  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    void startClient();
  }, backoff + jitter);
};

const ensureSocketReady = (): WASocket => {
  if (!socket) {
    throw new Error("WhatsApp socket not initialized");
  }

  if (healthState.connection !== "open") {
    throw new Error("WhatsApp socket is not connected");
  }

  return socket;
};

const normalizeMessageTimestamp = (message: WAMessage): string => {
  const timestamp = message.messageTimestamp;

  if (!timestamp) {
    return new Date().toISOString();
  }

  let seconds: number | null = null;

  if (typeof timestamp === "number") {
    seconds = timestamp;
  } else if (typeof timestamp === "string") {
    seconds = Number(timestamp);
  } else if (typeof (timestamp as { toNumber?: () => number }).toNumber === "function") {
    seconds = (timestamp as { toNumber: () => number }).toNumber();
  }

  if (!seconds || Number.isNaN(seconds)) {
    return new Date().toISOString();
  }

  return new Date(seconds * 1000).toISOString();
};

const buildQuotedMessage = (toWaId: string, reply: ReplyQuote | null): WAMessage | null => {
  if (!reply?.waMessageId) {
    return null;
  }

  const isGroup = toWaId.endsWith("@g.us");
  if (isGroup && !reply.senderWaId) {
    return null;
  }

  const text = reply.text || (reply.type ? `[${reply.type}]` : "");

  if (!text) {
    return null;
  }

  return {
    key: {
      remoteJid: toWaId,
      fromMe: false,
      id: reply.waMessageId,
      participant: isGroup ? reply.senderWaId || undefined : undefined,
    },
    message: {
      conversation: text,
    },
  } as WAMessage;
};

export const startClient = async (): Promise<void> => {
  if (starting) {
    return;
  }

  starting = true;
  updateHealth({ connection: "connecting" });

  try {
    ensureAuthDir();
    configureLidMappingStorage(path.join(authDir, "lid-mapping.json"));

    const { state, saveCreds } = await useMultiFileAuthState(authDir);

    let version: [number, number, number] | undefined;
    try {
      const latest = await fetchLatestBaileysVersion();
      version = latest.version;
    } catch (error) {
      logger.warn({ err: error }, "Failed to fetch latest Baileys version");
    }

    socket = makeWASocket({
      version,
      auth: state,
      logger: logger.child({ scope: "baileys" }),
      printQRInTerminal: true,
      syncFullHistory: false,
    });

    socket.ev.on("creds.update", saveCreds);

    socket.ev.on("connection.update", (update) => {
      if (update.connection) {
        updateHealth({ connection: update.connection });
      }

      if (update.qr) {
        updateQr(update.qr);
      }

      if (update.connection === "close") {
        const statusCode = (update.lastDisconnect?.error as { output?: { statusCode?: number } })
          ?.output?.statusCode;

        updateHealth({
          lastDisconnectReason: statusCode ?? null,
        });

        if (statusCode !== DisconnectReason.loggedOut) {
          logger.warn({ statusCode }, "Connection closed, scheduling reconnect");
          scheduleReconnect();
        } else {
          logger.error("Connection closed due to logout. Re-auth required.");
        }
      }

      if (update.connection === "open") {
        updateHealth({ lastDisconnectReason: null });
        reconnectAttempts = 0;
        updateQr(null);
        logger.info("WhatsApp connection established");
      }
    });

    socket.ev.on("lid-mapping.update", ({ lid, pn }) => {
      registerLidMapping(lid, pn);
    });

    socket.ev.on("messages.upsert", ({ messages, type }) => {
      if (type !== "notify") {
        return;
      }

      messages.forEach((message) => {
        const remoteJid = message.key.remoteJid || "";
        if (remoteJid.endsWith("@broadcast")) {
          return;
        }

        if (message.key.fromMe) {
          void handleOutgoingMessage(message, { getGroupSubject });
          return;
        }

        void handleIncomingMessage(message, { getGroupSubject });
      });
    });

    socket.ev.on("messages.update", (updates) => {
      updates.forEach((update) => {
        if (!update.update || update.update.status == null) {
          return;
        }

        void handleMessageAck(update);
      });
    });
  } finally {
    starting = false;
  }
};

export const sendTextMessage = async (
  toWaId: string,
  text: string,
  reply: ReplyQuote | null = null
): Promise<{ waMessageId: string | null; waTimestamp: string }> => {
  const activeSocket = ensureSocketReady();
  const quoted = buildQuotedMessage(toWaId, reply);
  const result = await activeSocket.sendMessage(
    toWaId,
    { text },
    quoted ? { quoted } : undefined
  );

  return {
    waMessageId: result.key?.id ?? null,
    waTimestamp: normalizeMessageTimestamp(result),
  };
};

const fetchMediaBuffer = async (url: string): Promise<Buffer> => {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Failed to fetch media: ${response.status}`);
  }

  const arrayBuffer = await response.arrayBuffer();
  return Buffer.from(arrayBuffer);
};

export const sendMediaMessage = async (
  toWaId: string,
  type: "image" | "video" | "audio" | "document" | "sticker",
  mediaUrl: string,
  mediaMime: string | null,
  caption: string | null,
  fileName: string | null,
  reply: ReplyQuote | null = null
): Promise<{ waMessageId: string | null; waTimestamp: string }> => {
  const activeSocket = ensureSocketReady();
  const buffer = await fetchMediaBuffer(mediaUrl);

  let content: Record<string, unknown>;

  switch (type) {
    case "image":
      content = { image: buffer, caption: caption ?? undefined, mimetype: mediaMime ?? undefined };
      break;
    case "video":
      content = { video: buffer, caption: caption ?? undefined, mimetype: mediaMime ?? undefined };
      break;
    case "audio":
      content = { audio: buffer, mimetype: mediaMime ?? undefined, ptt: false };
      break;
    case "sticker":
      content = { sticker: buffer };
      break;
    case "document":
    default:
      content = {
        document: buffer,
        mimetype: mediaMime ?? undefined,
        fileName: fileName ?? "file",
        caption: caption ?? undefined,
      };
      break;
  }

  const quoted = buildQuotedMessage(toWaId, reply);
  const result = await activeSocket.sendMessage(
    toWaId,
    content,
    quoted ? { quoted } : undefined
  );

  return {
    waMessageId: result.key?.id ?? null,
    waTimestamp: normalizeMessageTimestamp(result),
  };
};

export const revokeMessage = async (
  toWaId: string,
  waMessageId: string,
  fromMe = true,
  participant: string | null = null
): Promise<void> => {
  const activeSocket = ensureSocketReady();
  const key: WAMessageKey = {
    remoteJid: toWaId,
    fromMe,
    id: waMessageId,
    participant: participant || undefined,
  };

  await activeSocket.sendMessage(toWaId, { delete: key });
};

export const stopClient = (): void => {
  if (reconnectTimer) {
    clearTimeout(reconnectTimer);
    reconnectTimer = null;
  }

  if (socket) {
    socket.end(new Error("shutdown"));
    socket = null;
  }
};

export const getHealthState = (): HealthState => ({
  ...healthState,
});

export const getLatestQr = (): { qr: string | null; updatedAt: string | null } => ({
  qr: latestQr.value,
  updatedAt: latestQr.updatedAt,
});

export const reconnectClient = async (): Promise<void> => {
  stopClient();
  await startClient();
};

export const logoutClient = async (): Promise<void> => {
  if (socket) {
    await socket.logout();
  }

  stopClient();
  updateHealth({ connection: "close" });
  updateQr(null);
};

export const resetClient = async (): Promise<void> => {
  if (socket) {
    try {
      await socket.logout();
    } catch (error) {
      logger.warn({ err: error }, "Failed to logout before reset");
    }
  }

  stopClient();
  clearAuthDir();
  updateHealth({ connection: "close", lastDisconnectReason: DisconnectReason.loggedOut });
  updateQr(null);
};
