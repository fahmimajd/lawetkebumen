import fs from "node:fs";
import path from "node:path";
import {
  downloadContentFromMessage,
  getContentType,
  type WAMessage,
} from "@whiskeysockets/baileys";
import { logger } from "../logger";

type MediaType = "image" | "video" | "audio" | "document" | "sticker";

type ExtractedMedia = {
  type: "text" | MediaType;
  text: string | null;
  caption: string | null;
  media: {
    mime: string | null;
    size: number | null;
    url: string | null;
    name: string | null;
  };
  mediaMessage: unknown | null;
  mediaType: MediaType | null;
};

type QuotedReply = {
  waMessageId: string | null;
  senderWaId: string | null;
  text: string | null;
  type: "text" | MediaType | null;
};

const lidToPnMap = new Map<string, string>();
let mappingFile: string | null = null;
let persistTimer: NodeJS.Timeout | null = null;

const setLidMapping = (lid: string, pn: string): boolean => {
  if (!lid || !pn) {
    return false;
  }

  const normalizedLid = normalizeWaId(lid);
  const normalizedPn = normalizeWaId(pn);

  if (!isLid(normalizedLid)) {
    return false;
  }

  if (!normalizedPn.endsWith("@s.whatsapp.net") && !normalizedPn.endsWith("@hosted")) {
    return false;
  }

  if (lidToPnMap.get(normalizedLid) === normalizedPn) {
    return false;
  }

  lidToPnMap.set(normalizedLid, normalizedPn);
  return true;
};

const persistLidMapping = (): void => {
  if (!mappingFile) {
    return;
  }

  if (persistTimer) {
    return;
  }

  persistTimer = setTimeout(() => {
    persistTimer = null;
    try {
      fs.mkdirSync(path.dirname(mappingFile), { recursive: true });
      const payload = Object.fromEntries(lidToPnMap.entries());
      fs.writeFileSync(mappingFile, JSON.stringify(payload), "utf8");
    } catch (error) {
      logger.warn({ err: error }, "Failed to persist LID mapping");
    }
  }, 1000);
};

export const configureLidMappingStorage = (filePath: string): void => {
  mappingFile = filePath;

  try {
    if (!fs.existsSync(filePath)) {
      return;
    }

    const raw = fs.readFileSync(filePath, "utf8");
    const parsed = JSON.parse(raw);

    if (!parsed || typeof parsed !== "object") {
      return;
    }

    Object.entries(parsed).forEach(([lid, pn]) => {
      if (typeof lid !== "string" || typeof pn !== "string") {
        return;
      }

      setLidMapping(lid, pn);
    });
  } catch (error) {
    logger.warn({ err: error }, "Failed to load LID mapping");
  }
};

export const normalizeWaId = (jid: string): string => {
  if (!jid.includes("@")) {
    return jid;
  }

  const [user, server] = jid.split("@");
  const baseUser = user.split(":")[0];
  return `${baseUser}@${server}`;
};

export const isLid = (jid: string): boolean => {
  if (!jid) {
    return false;
  }

  const normalized = normalizeWaId(jid);
  return normalized.endsWith("@lid") || normalized.endsWith("@hosted.lid");
};

export const registerLidMapping = (lid: string, pn: string): void => {
  if (!setLidMapping(lid, pn)) {
    return;
  }

  persistLidMapping();
};

export const resolvePnFromLid = (jid: string): string | null => {
  const normalized = normalizeWaId(jid);
  if (!normalized.endsWith("@lid") && !normalized.endsWith("@hosted.lid")) {
    return null;
  }

  return lidToPnMap.get(normalized) ?? null;
};

export const extractPhone = (jid: string): string | null => {
  if (!jid.includes("@")) {
    return null;
  }

  const user = jid.split("@")[0];
  const baseUser = user.split(":")[0];
  const digits = baseUser.replace(/\D+/g, "");
  return digits || null;
};

const unwrapMessage = (message: WAMessage): WAMessage["message"] | null => {
  let content = message.message ?? null;

  if (!content) {
    return null;
  }

  if (content.ephemeralMessage?.message) {
    content = content.ephemeralMessage.message;
  }

  if (content.viewOnceMessage?.message) {
    content = content.viewOnceMessage.message;
  }

  if (content.viewOnceMessageV2?.message) {
    content = content.viewOnceMessageV2.message;
  }

  return content;
};

export const normalizeTimestamp = (message: WAMessage): string => {
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

export const extractTextAndMedia = (message: WAMessage): ExtractedMedia => {
  const content = unwrapMessage(message);

  if (!content) {
    return {
      type: "text",
      text: null,
      caption: null,
      media: { mime: null, size: null, url: null, name: null },
      mediaMessage: null,
      mediaType: null,
    };
  }

  const contentType = getContentType(content);

  switch (contentType) {
    case "conversation":
      return {
        type: "text",
        text: content.conversation ?? null,
        caption: null,
        media: { mime: null, size: null, url: null, name: null },
        mediaMessage: null,
        mediaType: null,
      };
    case "extendedTextMessage":
      return {
        type: "text",
        text: content.extendedTextMessage?.text ?? null,
        caption: null,
        media: { mime: null, size: null, url: null, name: null },
        mediaMessage: null,
        mediaType: null,
      };
    case "imageMessage":
      return {
        type: "image",
        text: null,
        caption: content.imageMessage?.caption ?? null,
        media: {
          mime: content.imageMessage?.mimetype ?? null,
          size: content.imageMessage?.fileLength
            ? Number(content.imageMessage.fileLength)
            : null,
          url: content.imageMessage?.url ?? null,
          name: null,
        },
        mediaMessage: content.imageMessage ?? null,
        mediaType: "image",
      };
    case "videoMessage":
      return {
        type: "video",
        text: null,
        caption: content.videoMessage?.caption ?? null,
        media: {
          mime: content.videoMessage?.mimetype ?? null,
          size: content.videoMessage?.fileLength
            ? Number(content.videoMessage.fileLength)
            : null,
          url: content.videoMessage?.url ?? null,
          name: null,
        },
        mediaMessage: content.videoMessage ?? null,
        mediaType: "video",
      };
    case "audioMessage":
      return {
        type: "audio",
        text: null,
        caption: null,
        media: {
          mime: content.audioMessage?.mimetype ?? null,
          size: content.audioMessage?.fileLength
            ? Number(content.audioMessage.fileLength)
            : null,
          url: content.audioMessage?.url ?? null,
          name: null,
        },
        mediaMessage: content.audioMessage ?? null,
        mediaType: "audio",
      };
    case "documentMessage":
      return {
        type: "document",
        text: null,
        caption: content.documentMessage?.caption ?? null,
        media: {
          mime: content.documentMessage?.mimetype ?? null,
          size: content.documentMessage?.fileLength
            ? Number(content.documentMessage.fileLength)
            : null,
          url: content.documentMessage?.url ?? null,
          name: content.documentMessage?.fileName ?? null,
        },
        mediaMessage: content.documentMessage ?? null,
        mediaType: "document",
      };
    case "stickerMessage":
      return {
        type: "sticker",
        text: null,
        caption: null,
        media: {
          mime: content.stickerMessage?.mimetype ?? null,
          size: content.stickerMessage?.fileLength
            ? Number(content.stickerMessage.fileLength)
            : null,
          url: content.stickerMessage?.url ?? null,
          name: null,
        },
        mediaMessage: content.stickerMessage ?? null,
        mediaType: "sticker",
      };
    default:
      return {
        type: "text",
        text: null,
        caption: null,
        media: { mime: null, size: null, url: null, name: null },
        mediaMessage: null,
        mediaType: null,
      };
  }
};

const extractContextInfo = (
  content: WAMessage["message"]
): { stanzaId?: string | null; participant?: string | null; quotedMessage?: WAMessage["message"] } | null => {
  return (
    content.extendedTextMessage?.contextInfo ||
    content.imageMessage?.contextInfo ||
    content.videoMessage?.contextInfo ||
    content.documentMessage?.contextInfo ||
    content.audioMessage?.contextInfo ||
    content.stickerMessage?.contextInfo ||
    null
  );
};

const mapQuotedType = (contentType: string | null): QuotedReply["type"] => {
  if (!contentType) {
    return null;
  }

  if (contentType === "conversation" || contentType === "extendedTextMessage") {
    return "text";
  }
  if (contentType === "imageMessage") {
    return "image";
  }
  if (contentType === "videoMessage") {
    return "video";
  }
  if (contentType === "audioMessage") {
    return "audio";
  }
  if (contentType === "documentMessage") {
    return "document";
  }
  if (contentType === "stickerMessage") {
    return "sticker";
  }

  return null;
};

const extractQuotedText = (
  quoted: WAMessage["message"] | undefined,
  contentType: string | null
): string | null => {
  if (!quoted || !contentType) {
    return null;
  }

  if (contentType === "conversation") {
    return quoted.conversation ?? null;
  }
  if (contentType === "extendedTextMessage") {
    return quoted.extendedTextMessage?.text ?? null;
  }
  if (contentType === "imageMessage") {
    return quoted.imageMessage?.caption ?? null;
  }
  if (contentType === "videoMessage") {
    return quoted.videoMessage?.caption ?? null;
  }
  if (contentType === "documentMessage") {
    return quoted.documentMessage?.caption ?? quoted.documentMessage?.fileName ?? null;
  }

  return null;
};

export const extractQuotedReply = (message: WAMessage): QuotedReply | null => {
  const content = unwrapMessage(message);

  if (!content) {
    return null;
  }

  const context = extractContextInfo(content);

  if (!context || !context.stanzaId) {
    return null;
  }

  const quotedMessage = context.quotedMessage;
  const quotedTypeRaw = quotedMessage ? getContentType(quotedMessage) : null;
  const quotedType = mapQuotedType(quotedTypeRaw);
  const quotedText = extractQuotedText(quotedMessage, quotedTypeRaw);
  const senderWaId = context.participant ? normalizeWaId(context.participant) : null;

  return {
    waMessageId: context.stanzaId ?? null,
    senderWaId,
    text: quotedText,
    type: quotedType,
  };
};

const streamToBuffer = async (
  stream: AsyncIterable<Uint8Array>
): Promise<Buffer> => {
  const chunks: Buffer[] = [];
  for await (const chunk of stream) {
    chunks.push(Buffer.from(chunk));
  }
  return Buffer.concat(chunks);
};

export const loadMediaBase64 = async (
  mediaMessage: unknown,
  mediaType: MediaType,
  waMessageId: string | null
): Promise<string | null> => {
  try {
    const stream = await downloadContentFromMessage(
      mediaMessage as Parameters<typeof downloadContentFromMessage>[0],
      mediaType
    );
    const buffer = await streamToBuffer(stream);
    return buffer.toString("base64");
  } catch (error) {
    logger.warn(
      { err: error, waMessageId },
      "Failed to download media content"
    );
    return null;
  }
};
