import { randomUUID } from "node:crypto";
import { type WAMessage } from "@whiskeysockets/baileys";
import { logger } from "../logger";
import { sendWebhookWithRetry } from "../webhooks/sender";
import {
  extractPhone,
  extractTextAndMedia,
  extractQuotedReply,
  isLid,
  loadMediaBase64,
  normalizeTimestamp,
  normalizeWaId,
  registerLidMapping,
  resolvePnFromLid,
} from "./messagePayload";

type MessageOutgoingEvent = {
  event_id: string;
  correlation_id: string;
  event_type: "message.outgoing";
  event_version: 1;
  timestamp: string;
  data: {
    wa_message_id: string | null;
    from_wa_id: string;
    phone: string | null;
    push_name: string | null;
    is_group: boolean;
    group_wa_id: string | null;
    group_subject: string | null;
    sender_wa_id: string | null;
    sender_phone: string | null;
    sender_name: string | null;
    type: "text" | "image" | "video" | "audio" | "document" | "sticker";
    text: string | null;
    caption: string | null;
    reply_to_wa_message_id: string | null;
    reply_to_sender_wa_id: string | null;
    reply_to_text: string | null;
    reply_to_type: "text" | "image" | "video" | "audio" | "document" | "sticker" | null;
    media: {
      mime: string | null;
      size: number | null;
      url: string | null;
      name?: string | null;
      base64?: string | null;
    };
    wa_timestamp: string;
  };
};

type BuildOutgoingOptions = {
  groupSubject?: string | null;
};

type GroupSubjectFetcher = (jid: string) => Promise<string | null>;

export const buildOutgoingEvent = async (
  message: WAMessage,
  options: BuildOutgoingOptions = {}
): Promise<MessageOutgoingEvent | null> => {
  const remoteJid = message.key.remoteJid || "";
  const remoteJidAlt =
    (message.key as WAMessage["key"] & { remoteJidAlt?: string }).remoteJidAlt || "";
  const participant = message.key.participant || "";
  const isGroup = remoteJid.endsWith("@g.us");
  const groupWaId = isGroup ? normalizeWaId(remoteJid) : null;
  if (remoteJidAlt) {
    registerLidMapping(remoteJid, remoteJidAlt);
  } else if (!isGroup && isLid(remoteJid) && participant && !isLid(participant)) {
    registerLidMapping(remoteJid, participant);
  }
  const rawPeerWaId = remoteJidAlt || remoteJid || participant;
  const normalizedPeerWaId = rawPeerWaId ? normalizeWaId(rawPeerWaId) : "";
  let peerWaId = resolvePnFromLid(normalizedPeerWaId) ?? normalizedPeerWaId;
  if (!isGroup && isLid(peerWaId)) {
    const fallback = [remoteJidAlt, participant]
      .map((jid) => (jid ? normalizeWaId(jid) : ""))
      .find((jid) => jid && !isLid(jid));
    if (fallback) {
      peerWaId = fallback;
    }
  }
  const fromWaId = isGroup ? groupWaId || peerWaId : peerWaId;
  const phone = extractPhone(fromWaId);
  const normalizedSenderWaId = participant ? normalizeWaId(participant) : null;
  const senderWaId = normalizedSenderWaId
    ? resolvePnFromLid(normalizedSenderWaId) ?? normalizedSenderWaId
    : null;
  const senderPhone = senderWaId
    ? extractPhone(senderWaId)
    : null;
  const senderName = senderWaId ? message.pushName ?? null : null;
  const groupSubject = isGroup ? options.groupSubject ?? null : null;
  const pushName = isGroup ? groupSubject : null;
  const waTimestamp = normalizeTimestamp(message);
  const { type, text, caption, media, mediaMessage, mediaType } =
    extractTextAndMedia(message);
  const quoted = extractQuotedReply(message);
  const hasPayload = Boolean(text || caption || mediaMessage || media.url);

  if (!fromWaId || !hasPayload) {
    return null;
  }

  let mediaBase64: string | null = null;

  if (mediaMessage && mediaType) {
    mediaBase64 = await loadMediaBase64(mediaMessage, mediaType, message.key.id ?? null);
  }

  const eventId = randomUUID();
  const correlationId = message.key.id ?? eventId;

  return {
    event_id: eventId,
    correlation_id: correlationId,
    event_type: "message.outgoing",
    event_version: 1,
    timestamp: new Date().toISOString(),
    data: {
      wa_message_id: message.key.id ?? null,
      from_wa_id: fromWaId,
      phone,
      push_name: pushName ?? null,
      is_group: isGroup,
      group_wa_id: groupWaId,
      group_subject: groupSubject,
      sender_wa_id: senderWaId,
      sender_phone: senderPhone,
      sender_name: senderName,
      type,
      text,
      caption,
      reply_to_wa_message_id: quoted?.waMessageId ?? null,
      reply_to_sender_wa_id: quoted?.senderWaId ?? null,
      reply_to_text: quoted?.text ?? null,
      reply_to_type: quoted?.type ?? null,
      media: {
        ...media,
        base64: mediaBase64,
      },
      wa_timestamp: waTimestamp,
    },
  };
};

export const handleOutgoingMessage = async (
  message: WAMessage,
  options: { getGroupSubject?: GroupSubjectFetcher } = {}
): Promise<void> => {
  let groupSubject: string | null = null;
  const remoteJid = message.key.remoteJid || "";
  if (remoteJid.endsWith("@g.us") && options.getGroupSubject) {
    try {
      const normalized = normalizeWaId(remoteJid);
      groupSubject = await options.getGroupSubject(normalized);
    } catch (error) {
      logger.warn({ err: error, remoteJid }, "Failed to fetch group subject");
    }
  }

  const event = await buildOutgoingEvent(message, { groupSubject });

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
      "Failed to deliver webhook for outgoing message"
    );
  }
};
