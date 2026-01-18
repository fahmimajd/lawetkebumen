const appRoot = document.querySelector("[data-app-root]");
const conversationListEl = document.querySelector("[data-conversation-list]");
const conversationCountEl = document.querySelector("[data-conversation-count]");
const conversationOpenCountEl = document.querySelector("[data-conversation-count-open]");
const conversationUnassignedCountEl = document.querySelector("[data-conversation-count-unassigned]");
const conversationClosedCountEl = document.querySelector("[data-conversation-count-closed]");
const threadTitleEl = document.querySelector("[data-thread-title]");
const threadMetaEl = document.querySelector("[data-thread-meta]");
const messagesEl = document.querySelector("[data-messages-container]");
const assignmentEl = document.querySelector("[data-assignment-indicator]");
const lockEl = document.querySelector("[data-lock-indicator]");
const lockStateEl = document.querySelector("[data-lock-state]");
const statusToggle = document.querySelector("[data-status-toggle]");
const acceptToggle = document.querySelector("[data-accept-toggle]");
const transferToggle = document.querySelector("[data-transfer-toggle]");
const deleteToggle = document.querySelector("[data-delete-toggle]");
const composerForm = document.querySelector("[data-composer-form]");
const composerInput = document.querySelector("[data-composer-input]");
const composerFileInput = document.querySelector("[data-composer-file]");
const composerFileName = document.querySelector("[data-composer-file-name]");
const lockToggle = document.querySelector("[data-lock-toggle]");
const emojiToggle = document.querySelector("[data-emoji-toggle]");
const emojiPicker = document.querySelector("[data-emoji-picker]");
const quickAnswersEl = document.querySelector("[data-quick-answers]");
const attachToggle = document.querySelector("[data-attach-toggle]");
const sendButton = document.querySelector("[data-send-button]");
const searchInput = document.querySelector("[data-search-input]");
const connectionStatus = document.querySelector("[data-connection-status]");
const replyPreviewEl = document.querySelector("[data-reply-preview]");
const replyPreviewTextEl = document.querySelector("[data-reply-preview-text]");
const replyClearEl = document.querySelector("[data-reply-clear]");

if (!appRoot || !conversationListEl || !messagesEl || !composerForm || !composerInput) {
  // Not on the inbox page.
} else {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  const currentUserId = Number(
    document.querySelector('meta[name="user-id"]')?.getAttribute("content") || "0",
  );
  const apiBase = appRoot?.dataset?.apiBase || "/api";

  const config = {
    reverb: {
      key: document.querySelector('meta[name="reverb-key"]')?.getAttribute("content") || "",
      host: document.querySelector('meta[name="reverb-host"]')?.getAttribute("content") || "localhost",
      port: Number(document.querySelector('meta[name="reverb-port"]')?.getAttribute("content") || "8080"),
      scheme: document.querySelector('meta[name="reverb-scheme"]')?.getAttribute("content") || "http",
    },
  };

  const state = {
    conversations: [],
    activeConversationId: null,
    loadingConversations: false,
    messagesCursor: null,
    loadingMessages: false,
    messageIds: new Set(),
    quickAnswers: [],
    quickAnswerMatches: [],
    quickAnswerActiveIndex: -1,
    lockTimer: null,
    pollTimer: null,
    echo: null,
    conversationChannel: null,
    lockOwnedByUser: false,
    lockInfo: { status: "unlocked", owner_id: null },
    replyTo: null,
  };

  const apiFetch = async (url, options = {}) => {
    const isFormData = options.body instanceof FormData;
    const headers = {
      "X-Requested-With": "XMLHttpRequest",
      ...(options.headers || {}),
    };

    if (!isFormData) {
      headers["Content-Type"] = "application/json";
    }

    if (csrfToken) {
      headers["X-CSRF-TOKEN"] = csrfToken;
    }

    const socketId = state.echo?.socketId?.() || window.Echo?.socketId?.();
    if (socketId) {
      headers["X-Socket-ID"] = socketId;
    }

    const response = await fetch(url, {
      ...options,
      headers,
      credentials: "same-origin",
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(text || `Request failed with ${response.status}`);
    }

    const text = await response.text();
    if (!text) {
      return null;
    }

    try {
      return JSON.parse(text);
    } catch (error) {
      return text;
    }
  };

  const formatTime = (iso) => {
    if (!iso) return "";
    const date = new Date(iso);
    const day = date.toLocaleDateString(undefined, { weekday: "short" });
    const time = date.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });
    return `${day} ${time}`;
  };

  const formatAssigneeLabel = (conversation) => {
    if (!conversation) return "Unassigned";
    const name = conversation.assigned_name || (conversation.assigned_to ? `Agent #${conversation.assigned_to}` : null);
    return name || "Unassigned";
  };

  const formatStatusLabel = (conversation) => {
    if (!conversation) return "open";
    if (conversation.status === "open" && conversation.assigned_name) {
      return `Open by ${conversation.assigned_name}`;
    }
    return conversation.status || "open";
  };

  const isGroupConversation = (conversation) =>
    Boolean(conversation?.contact?.wa_id?.includes("@g.us"));
  const isAwaitingReply = (conversation) => {
    if (!conversation) return false;
    if (conversation.status !== "open") return false;
    if (isGroupConversation(conversation)) return false;
    return conversation.last_message_direction === "in";
  };

  const getActiveConversation = () =>
    state.conversations.find((item) => item.id === state.activeConversationId) || null;

  const shouldSkipSelf = (event) =>
    Number.isFinite(currentUserId) && currentUserId > 0 && event?.actor_id === currentUserId;

  const closeActionMenus = () => {
    document.querySelectorAll(".message__menu.is-open").forEach((menu) => {
      menu.classList.remove("is-open");
    });
  };

  const hideQuickAnswers = () => {
    state.quickAnswerMatches = [];
    state.quickAnswerActiveIndex = -1;
    if (quickAnswersEl) {
      quickAnswersEl.innerHTML = "";
      quickAnswersEl.hidden = true;
    }
  };

  const renderQuickAnswers = () => {
    if (!quickAnswersEl) return;

    quickAnswersEl.innerHTML = "";
    if (!state.quickAnswerMatches.length) {
      quickAnswersEl.hidden = true;
      return;
    }

    const fragment = document.createDocumentFragment();
    state.quickAnswerMatches.forEach((answer, index) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "quick-answers__item";
      if (index === state.quickAnswerActiveIndex) {
        button.classList.add("is-active");
      }

      const shortcut = document.createElement("span");
      shortcut.className = "quick-answers__shortcut";
      shortcut.textContent = `/${answer.shortcut}`;

      const body = document.createElement("span");
      body.className = "quick-answers__body";
      body.textContent = answer.body;

      button.appendChild(shortcut);
      button.appendChild(body);
      button.addEventListener("click", (event) => {
        event.stopPropagation();
        applyQuickAnswer(answer);
      });

      fragment.appendChild(button);
    });

    quickAnswersEl.appendChild(fragment);
    quickAnswersEl.hidden = false;
  };

  const applyQuickAnswer = (answer) => {
    if (!answer || !composerInput) return;
    composerInput.value = answer.body;
    composerInput.focus();
    composerInput.selectionStart = composerInput.value.length;
    composerInput.selectionEnd = composerInput.value.length;
    hideQuickAnswers();
  };

  const getQuickAnswerQuery = (value) => {
    if (!value) return null;
    const trimmed = value.replace(/^\s+/, "");
    if (!trimmed.startsWith("/")) return null;
    const spaceIndex = trimmed.indexOf(" ");
    const token = spaceIndex === -1 ? trimmed.slice(1) : trimmed.slice(1, spaceIndex);
    return token.toLowerCase();
  };

  const updateQuickAnswerMatches = () => {
    if (!composerInput) return;
    const query = getQuickAnswerQuery(composerInput.value);
    if (query === null) {
      hideQuickAnswers();
      return;
    }

    const matches = state.quickAnswers.filter((answer) => {
      if (!query) return true;
      return answer.shortcut.startsWith(query);
    });

    state.quickAnswerMatches = matches.slice(0, 6);
    state.quickAnswerActiveIndex = state.quickAnswerMatches.length ? 0 : -1;
    renderQuickAnswers();
  };

  const loadQuickAnswers = async () => {
    try {
      const result = await apiFetch(`${apiBase}/quick-answers`);
      state.quickAnswers = Array.isArray(result?.data) ? result.data : [];
    } catch (error) {
      console.error(error);
      state.quickAnswers = [];
    }
  };

  const formatMessageMeta = (timestamp, status, senderName, direction) => {
    const timeLabel = formatTime(timestamp);
    const statusLabel = status || "pending";
    const senderLabel =
      direction === "out" && senderName ? ` · by ${senderName}` : "";
    return `${timeLabel} · ${statusLabel}${senderLabel}`;
  };

  const formatReplyPreview = (message) => {
    const reply = message.reply_to;
    if (reply) {
      const sender = reply.sender_name || reply.sender_wa_id || "Contact";
      const body = reply.body || (reply.type ? `[${reply.type}]` : "Message");
      return { sender, body };
    }

    if (message.reply_to_message_id) {
      return { sender: "Reply", body: "Quoted message" };
    }

    return null;
  };

  const buildReplyPreview = (message) => {
    const sender = message.sender_name || message.sender_wa_id || "Contact";
    const body = message.body || `[${message.type || "message"}]`;
    return `${sender}: ${body}`;
  };

  const updateReplyPreview = () => {
    if (!replyPreviewEl || !replyPreviewTextEl) return;

    if (!state.replyTo) {
      replyPreviewEl.hidden = true;
      replyPreviewTextEl.textContent = "";
      return;
    }

    replyPreviewTextEl.textContent = state.replyTo.preview;
    replyPreviewEl.hidden = false;
  };

  const clearReplyTarget = () => {
    state.replyTo = null;
    updateReplyPreview();
  };

  const setReplyTarget = (message) => {
    state.replyTo = {
      id: message.id,
      preview: buildReplyPreview(message),
    };
    updateReplyPreview();
    composerInput.focus();
  };

  const updateConversationCounters = (list) => {
    if (conversationCountEl) {
      conversationCountEl.textContent = String(list.length);
    }
    if (conversationOpenCountEl || conversationClosedCountEl || conversationUnassignedCountEl) {
      const openCount = list.filter((conversation) => conversation.status !== "closed").length;
      const closedCount = list.length - openCount;
      const unassignedCount = list.filter((conversation) => !conversation.assigned_to).length;
      if (conversationOpenCountEl) {
        conversationOpenCountEl.textContent = String(openCount);
      }
      if (conversationUnassignedCountEl) {
        conversationUnassignedCountEl.textContent = String(unassignedCount);
      }
      if (conversationClosedCountEl) {
        conversationClosedCountEl.textContent = String(closedCount);
      }
    }
  };

  const updateConversationItem = (conversation) => {
    const item = conversationListEl.querySelector(`[data-id="${conversation.id}"]`);
    if (!item) return false;

    const activeClass = state.activeConversationId === conversation.id ? " is-active" : "";
    const replyClass = isAwaitingReply(conversation) ? " is-needs-reply" : "";
    item.className = `list-item${activeClass}${replyClass}`;

    const title = item.querySelector(".list-item__title");
    if (title) {
      title.textContent =
        conversation.contact?.display_name ||
        conversation.contact?.phone ||
        conversation.contact?.wa_id ||
        "Unknown";
    }

    const time = item.querySelector(".list-item__time");
    if (time) {
      time.textContent = formatTime(conversation.last_message_at);
    }

    const subtitle = item.querySelector(".list-item__subtitle");
    if (subtitle) {
      subtitle.textContent = conversation.last_message_preview || "No messages yet.";
    }

    const rowMid = item.querySelector(".list-item__row--muted");
    if (rowMid) {
      let badge = rowMid.querySelector(".badge");
      const unreadCount = Number(conversation.unread_count || 0);
      if (unreadCount > 0) {
        if (!badge) {
          badge = document.createElement("div");
          badge.className = "badge";
          rowMid.appendChild(badge);
        }
        badge.textContent = String(unreadCount);
      } else if (badge) {
        badge.remove();
      }
    }

    const rowBottom = item.querySelector(".list-item__row--meta");
    if (rowBottom) {
      const metaTags = rowBottom.querySelectorAll(".tag:not(.tag--group)");
      const statusTag = metaTags[0];
      const assignTag = metaTags[1];
      if (statusTag) {
        statusTag.textContent = formatStatusLabel(conversation);
      }
      if (assignTag) {
        assignTag.textContent = formatAssigneeLabel(conversation);
      }

      let groupTag = rowBottom.querySelector(".tag--group");
      if (isGroupConversation(conversation)) {
        if (!groupTag) {
          groupTag = document.createElement("span");
          groupTag.className = "tag tag--group";
          rowBottom.appendChild(groupTag);
        }
        groupTag.textContent = "Group";
      } else if (groupTag) {
        groupTag.remove();
      }
    }

    return true;
  };

  const renderConversations = (list = state.conversations) => {
    conversationListEl.innerHTML = "";
    updateConversationCounters(list);

    if (!list.length) {
      const empty = document.createElement("div");
      empty.className = "empty-state";
      empty.textContent = "No conversations yet.";
      conversationListEl.appendChild(empty);
      return;
    }

    list.forEach((conversation) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = `list-item${state.activeConversationId === conversation.id ? " is-active" : ""}${
        isAwaitingReply(conversation) ? " is-needs-reply" : ""
      }`;
      item.dataset.id = conversation.id;

      const rowTop = document.createElement("div");
      rowTop.className = "list-item__row";

      const title = document.createElement("div");
      title.className = "list-item__title";
      title.textContent =
        conversation.contact?.display_name ||
        conversation.contact?.phone ||
        conversation.contact?.wa_id ||
        "Unknown";

      const time = document.createElement("div");
      time.className = "list-item__time";
      time.textContent = formatTime(conversation.last_message_at);

      rowTop.appendChild(title);
      rowTop.appendChild(time);

      const rowMid = document.createElement("div");
      rowMid.className = "list-item__row list-item__row--muted";

      const subtitle = document.createElement("div");
      subtitle.className = "list-item__subtitle";
      subtitle.textContent = conversation.last_message_preview || "No messages yet.";

      rowMid.appendChild(subtitle);

      const unreadCount = Number(conversation.unread_count || 0);
      if (unreadCount > 0) {
        const badge = document.createElement("div");
        badge.className = "badge";
        badge.textContent = String(unreadCount);
        rowMid.appendChild(badge);
      }

      const rowBottom = document.createElement("div");
      rowBottom.className = "list-item__row list-item__row--meta";

      const statusTag = document.createElement("span");
      statusTag.className = "tag";
      statusTag.textContent = formatStatusLabel(conversation);

      const assignTag = document.createElement("span");
      assignTag.className = "tag";
      assignTag.textContent = formatAssigneeLabel(conversation);

      rowBottom.appendChild(statusTag);
      rowBottom.appendChild(assignTag);
      if (isGroupConversation(conversation)) {
        const groupTag = document.createElement("span");
        groupTag.className = "tag tag--group";
        groupTag.textContent = "Group";
        rowBottom.appendChild(groupTag);
      }

      item.appendChild(rowTop);
      item.appendChild(rowMid);
      item.appendChild(rowBottom);

      item.addEventListener("click", () => {
        selectConversation(conversation.id);
      });

      conversationListEl.appendChild(item);
    });
  };

  const updateThreadHeader = (conversation) => {
    if (!threadTitleEl || !threadMetaEl || !assignmentEl) return;

    if (!conversation) {
      threadTitleEl.textContent = "Pilih chat";
      threadMetaEl.textContent = "Klik Accept untuk membuka tiket.";
      assignmentEl.textContent = "Unassigned";
      return;
    }

    const contact = conversation.contact || {};
    const isGroup = isGroupConversation(conversation);
    threadTitleEl.textContent = contact.display_name || contact.phone || contact.wa_id || "Unknown";
    const metaLeft = isGroup ? "Group chat" : contact.phone || contact.wa_id || "Unknown";
    threadMetaEl.textContent = `${metaLeft} · ${formatStatusLabel(conversation)}`;
    assignmentEl.textContent = formatAssigneeLabel(conversation);
  };

  const buildMediaContent = (message) => {
    if (!message.media_url) {
      return null;
    }

    const wrapper = document.createElement("div");
    wrapper.className = "message__media";

    if (message.type === "image" || message.type === "sticker") {
      const link = document.createElement("button");
      link.type = "button";
      link.className = "message__media-button";
      link.setAttribute("aria-label", "Open image");

      const img = document.createElement("img");
      img.src = message.media_url;
      img.alt = message.media_name || "Image";
      img.loading = "lazy";
      link.appendChild(img);
      link.addEventListener("click", () => {
        openMediaLightbox(message.media_url);
      });
      wrapper.appendChild(link);
    } else if (message.type === "video") {
      const video = document.createElement("video");
      video.src = message.media_url;
      video.controls = true;
      wrapper.appendChild(video);
    } else if (message.type === "audio") {
      const audio = document.createElement("audio");
      audio.src = message.media_url;
      audio.controls = true;
      wrapper.appendChild(audio);
    } else {
      const link = document.createElement("a");
      link.className = "message__file";
      link.href = message.media_url;
      link.target = "_blank";
      link.rel = "noopener";
      link.textContent = message.media_name || "Download file";
      wrapper.appendChild(link);
    }

    return wrapper;
  };

  const openMediaLightbox = (url) => {
    const overlay = document.createElement("div");
    overlay.className = "media-lightbox";

    const img = document.createElement("img");
    img.className = "media-lightbox__image";
    img.src = url;
    img.alt = "Preview";

    overlay.appendChild(img);
    overlay.addEventListener("click", () => {
      overlay.remove();
    });

    document.body.appendChild(overlay);
  };

  const renderMessages = (messages, { prepend = false, reset = false } = {}) => {
    if (reset) {
      messagesEl.innerHTML = "";
      state.messageIds.clear();
    }

    const fragment = document.createDocumentFragment();

    messages.forEach((message) => {
      if (state.messageIds.has(message.id)) {
        return;
      }

      state.messageIds.add(message.id);
      const item = document.createElement("div");
      item.className = `message message--${message.direction}`;
      item.dataset.messageId = message.id;

      const bubble = document.createElement("div");
      bubble.className = "message__bubble";

      const menu = document.createElement("div");
      menu.className = "message__menu";

      const menuToggle = document.createElement("button");
      menuToggle.type = "button";
      menuToggle.className = "message__menu-toggle";
      menuToggle.setAttribute("aria-label", "Message actions");
      menuToggle.textContent = "...";
      menuToggle.addEventListener("click", (event) => {
        event.stopPropagation();
        closeActionMenus();
        menu.classList.toggle("is-open");
      });

      const menuList = document.createElement("div");
      menuList.className = "message__menu-list";

      const addMenuItem = (label, className, handler) => {
        const itemButton = document.createElement("button");
        itemButton.type = "button";
        itemButton.className = className;
        itemButton.textContent = label;
        itemButton.addEventListener("click", (event) => {
          event.stopPropagation();
          menu.classList.remove("is-open");
          handler();
        });
        menuList.appendChild(itemButton);
      };

      if (message.direction === "in") {
        addMenuItem("Reply", "message__menu-item message__menu-item--accent", () => {
          setReplyTarget(message);
        });
      }

      if (message.direction === "out") {
        addMenuItem("Revoke", "message__menu-item message__menu-item--danger", () => {
          void deleteMessage(message);
        });
      }

      menu.appendChild(menuToggle);
      menu.appendChild(menuList);
      bubble.appendChild(menu);

      const activeConversation = getActiveConversation();
      if (message.direction === "in" && isGroupConversation(activeConversation)) {
        const senderLabel = message.sender_name || message.sender_wa_id;
        if (senderLabel) {
          const sender = document.createElement("div");
          sender.className = "message__sender";
          sender.textContent = senderLabel;
          bubble.appendChild(sender);
        }
      }

      const replyPreview = formatReplyPreview(message);
      if (replyPreview) {
        const replyBox = document.createElement("div");
        replyBox.className = "message__reply-preview";

        const replySender = document.createElement("div");
        replySender.className = "message__reply-sender";
        replySender.textContent = replyPreview.sender;

        const replyBody = document.createElement("div");
        replyBody.textContent = replyPreview.body;

        replyBox.appendChild(replySender);
        replyBox.appendChild(replyBody);
        bubble.appendChild(replyBox);
      }

      const mediaContent = buildMediaContent(message);
      if (mediaContent) {
        bubble.appendChild(mediaContent);
      }

      if (message.body) {
        const text = document.createElement("div");
        text.className = mediaContent ? "message__caption" : "message__text";
        text.textContent = message.body;
        bubble.appendChild(text);
      }

      const meta = document.createElement("div");
      meta.className = "message__meta";
      const metaTimestamp = message.wa_timestamp || message.created_at || "";
      meta.dataset.messageTime = metaTimestamp;
      meta.dataset.messageStatus = message.status || "";
      meta.dataset.messageSender = message.sender_name || "";
      meta.dataset.messageDirection = message.direction || "";
      const metaText = document.createElement("span");
      metaText.className = "message__meta-text";
      metaText.textContent = formatMessageMeta(
        metaTimestamp,
        message.status,
        meta.dataset.messageSender,
        meta.dataset.messageDirection
      );
      meta.appendChild(metaText);

      bubble.appendChild(meta);

      item.appendChild(bubble);
      fragment.appendChild(item);
    });

    if (prepend) {
      messagesEl.prepend(fragment);
    } else {
      messagesEl.appendChild(fragment);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }
  };

  const loadMessages = async (conversationId, { cursor = null, prepend = false } = {}) => {
    if (state.loadingMessages || !conversationId) return;

    state.loadingMessages = true;
    const previousHeight = messagesEl.scrollHeight;

    try {
      const url = new URL(`${apiBase}/conversations/${conversationId}/messages`, window.location.origin);
      if (cursor) url.searchParams.set("cursor", cursor);
      const data = await apiFetch(url.toString());

      const ordered = [...data.data].reverse();
      renderMessages(ordered, { prepend, reset: !prepend });

      state.messagesCursor = data.next_cursor;

      if (prepend) {
        const newHeight = messagesEl.scrollHeight;
        messagesEl.scrollTop = newHeight - previousHeight;
      }
    } catch (error) {
      console.error(error);
    } finally {
      state.loadingMessages = false;
    }
  };

  const selectConversation = async (conversationId) => {
    state.activeConversationId = conversationId;
    renderConversations();
    clearReplyTarget();

    const conversation = state.conversations.find((item) => item.id === conversationId);
    updateThreadHeader(conversation);
    updateStatusButton(conversation);
    updateWorkflowButtons(conversation);
    state.lockInfo = { status: "unlocked", owner_id: null };
    applyLockState();
    state.messagesCursor = null;
    await loadMessages(conversationId);
    await markConversationRead(conversationId);
    await acquireLock();
    subscribeToConversation(conversationId);
  };

  const loadConversations = async () => {
    if (state.loadingConversations) return;
    state.loadingConversations = true;
    try {
      const data = await apiFetch(`${apiBase}/conversations`);
      state.conversations = data.data || [];
      renderConversations();

      if (!state.activeConversationId && state.conversations.length) {
        await selectConversation(state.conversations[0].id);
      } else if (state.activeConversationId) {
        const conversation = getActiveConversation();
        if (conversation) {
          updateThreadHeader(conversation);
          updateStatusButton(conversation);
          updateWorkflowButtons(conversation);
          applyLockState();
        }
      }
    } catch (error) {
      console.error(error);
    } finally {
      state.loadingConversations = false;
    }
  };

  const updateConversationFromEvent = (event) => {
    const index = state.conversations.findIndex((item) => item.id === event.id);
    if (index === -1) {
      void loadConversations();
      return;
    }

    state.conversations[index] = {
      ...state.conversations[index],
      status: event.status,
      assigned_to: event.assigned_to,
      assigned_name: event.assigned_name,
      last_message_at: event.last_message_at,
      unread_count: event.unread_count,
      last_message_preview: event.last_message_preview,
      last_message_direction: event.last_message_direction,
    };

    updateConversationCounters(state.conversations);
    const updated = updateConversationItem(state.conversations[index]);
    if (!updated) {
      renderConversations();
    }

    if (state.activeConversationId === event.id) {
      updateThreadHeader(state.conversations[index]);
      updateStatusButton(state.conversations[index]);
      updateWorkflowButtons(state.conversations[index]);
      applyLockState();
      if ((event.unread_count ?? 0) > 0) {
        void markConversationRead(event.id);
      }
    }
  };

  const updateStatusButton = (conversation) => {
    if (!statusToggle) return;
    if (!conversation) {
      statusToggle.disabled = true;
      statusToggle.textContent = "Close";
      return;
    }

    statusToggle.disabled = false;
    statusToggle.textContent = conversation.status === "closed" ? "Reopen" : "Close";
  };

  const updateWorkflowButtons = (conversation) => {
    if (!acceptToggle || !transferToggle || !statusToggle || !deleteToggle) return;
    if (!conversation) {
      acceptToggle.disabled = true;
      transferToggle.disabled = true;
      statusToggle.disabled = true;
      deleteToggle.disabled = true;
      return;
    }

    const isClosed = conversation.status === "closed";
    const isUnassigned = !conversation.assigned_to;
    acceptToggle.disabled = isClosed || !isUnassigned;
    transferToggle.disabled = isClosed || isUnassigned;
    statusToggle.disabled = false;
    deleteToggle.disabled = false;
  };

  const applyAssignmentUpdate = (assignedTo, assignedName) => {
    if (!state.activeConversationId) return;

    const index = state.conversations.findIndex((item) => item.id === state.activeConversationId);
    if (index === -1) return;

    state.conversations[index] = {
      ...state.conversations[index],
      assigned_to: assignedTo,
      assigned_name: assignedName,
    };

    updateConversationCounters(state.conversations);
    const updated = updateConversationItem(state.conversations[index]);
    if (!updated) {
      renderConversations();
    }
    updateThreadHeader(state.conversations[index]);
    updateStatusButton(state.conversations[index]);
    updateWorkflowButtons(state.conversations[index]);
  };

  const appendMessage = (message, conversationId) => {
    if (!message) return;
    renderMessages([message], { prepend: false, reset: false });
    if (conversationId) {
      void markConversationRead(conversationId);
    }
  };

  const markConversationRead = async (conversationId) => {
    try {
      const data = await apiFetch(`${apiBase}/conversations/${conversationId}/read`, {
        method: "POST",
      });

      const index = state.conversations.findIndex((item) => item.id === conversationId);
      if (index !== -1) {
        state.conversations[index] = {
          ...state.conversations[index],
          unread_count: data.unread_count ?? 0,
        };
        renderConversations();
      }
    } catch (error) {
      console.error(error);
    }
  };

  const updateMessageStatus = ({ message_id, status, wa_timestamp }) => {
    const messageEl = messagesEl.querySelector(`[data-message-id="${message_id}"]`);
    if (!messageEl) return;

    const meta = messageEl.querySelector(".message__meta");
    if (!meta) return;
    const metaText = meta.querySelector(".message__meta-text");
    if (!metaText) return;

    const nextTimestamp = wa_timestamp || meta.dataset.messageTime || "";
    const nextStatus = status || meta.dataset.messageStatus || "pending";
    const senderName = meta.dataset.messageSender || "";
    const direction = meta.dataset.messageDirection || "";

    meta.dataset.messageTime = nextTimestamp;
    meta.dataset.messageStatus = nextStatus;
    metaText.textContent = formatMessageMeta(nextTimestamp, nextStatus, senderName, direction);
  };

  const setLockState = ({ status, owner_id }) => {
    state.lockInfo = {
      status: status || "unlocked",
      owner_id: owner_id ?? null,
    };
    applyLockState();
  };

  const applyLockState = () => {
    if (!lockEl || !lockStateEl || !lockToggle || !composerInput || !sendButton) return;

    const conversation = getActiveConversation();

    if (conversation && !conversation.assigned_to) {
      lockEl.textContent = "Unassigned";
      lockEl.classList.add("pill--danger");
      lockStateEl.textContent = "Klik Accept untuk membuka tiket.";
      composerInput.disabled = true;
      sendButton.disabled = true;
      lockToggle.disabled = true;
      if (emojiToggle) emojiToggle.disabled = true;
      if (composerFileInput) composerFileInput.disabled = true;
      if (attachToggle) attachToggle.disabled = true;
      state.lockOwnedByUser = false;
      return;
    }

    if (conversation?.status === "closed") {
      lockEl.textContent = "Closed";
      lockEl.classList.add("pill--danger");
      lockStateEl.textContent = "Closed · Reopen to reply";
      composerInput.disabled = true;
      sendButton.disabled = true;
      lockToggle.disabled = true;
      if (emojiToggle) emojiToggle.disabled = true;
      if (composerFileInput) composerFileInput.disabled = true;
      if (attachToggle) attachToggle.disabled = true;
      state.lockOwnedByUser = false;
      return;
    }

    const lockStatus = state.lockInfo.status || "unlocked";
    const isLockedByOther = lockStatus === "locked";
    const isOwned = lockStatus === "acquired" || lockStatus === "renewed";

    if (isLockedByOther) {
      lockEl.textContent = `Locked by #${state.lockInfo.owner_id}`;
      lockEl.classList.add("pill--danger");
      lockStateEl.textContent = "Read-only · Locked by another agent";
      composerInput.disabled = true;
      sendButton.disabled = true;
      lockToggle.disabled = true;
      if (emojiToggle) emojiToggle.disabled = true;
      if (composerFileInput) composerFileInput.disabled = true;
      if (attachToggle) attachToggle.disabled = true;
      state.lockOwnedByUser = false;
      return;
    }

    lockEl.textContent = isOwned ? "Locked by you" : "Unlocked";
    lockEl.classList.remove("pill--danger");
    lockStateEl.textContent = isOwned ? "Locked · You can reply" : "Lock to reply";
    composerInput.disabled = false;
    sendButton.disabled = false;
    lockToggle.disabled = false;
    if (emojiToggle) emojiToggle.disabled = false;
    if (composerFileInput) composerFileInput.disabled = false;
    if (attachToggle) attachToggle.disabled = false;
    state.lockOwnedByUser = isOwned;
  };

  const acquireLock = async () => {
    if (!state.activeConversationId) return;

    const conversation = getActiveConversation();
    if (!conversation || !conversation.assigned_to) {
      setLockState({ status: "unlocked", owner_id: null });
      return;
    }

    try {
      const result = await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/lock`, {
        method: "POST",
      });
      setLockState(result);
    } catch (error) {
      console.error(error);
    }
  };

  const releaseLock = async () => {
    if (!state.activeConversationId) return;

    try {
      await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/lock`, {
        method: "DELETE",
      });
      setLockState({ status: "unlocked", owner_id: null });
    } catch (error) {
      console.error(error);
    }
  };

  const startLockRefresh = () => {
    if (state.lockTimer) {
      clearInterval(state.lockTimer);
    }

    state.lockTimer = setInterval(() => {
      if (state.activeConversationId) {
        void acquireLock();
      }
    }, 60000);
  };

  const handleComposerSubmit = async (event) => {
    event.preventDefault();

    if (!state.activeConversationId) return;

    hideQuickAnswers();

    const text = composerInput.value.trim();
    const file = composerFileInput?.files?.[0] || null;
    if (!text && !file) return;

    sendButton.disabled = true;

    try {
      const replyToId = state.replyTo?.id || null;
      if (file) {
        const mime = file.type || "";
        let type = "document";
        if (mime.startsWith("image/")) type = "image";
        else if (mime.startsWith("video/")) type = "video";
        else if (mime.startsWith("audio/")) type = "audio";

        const formData = new FormData();
        formData.append("type", type);
        if (text) {
          formData.append("text", text);
        }
        if (replyToId) {
          formData.append("reply_to_message_id", String(replyToId));
        }
        formData.append("file", file);

        const result = await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/messages`, {
          method: "POST",
          body: formData,
          headers: { Accept: "application/json" },
        });
        if (result?.message) {
          appendMessage(result.message, state.activeConversationId);
        }
      } else {
        const result = await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/messages`, {
          method: "POST",
          body: JSON.stringify({ type: "text", text, reply_to_message_id: replyToId }),
        });
        if (result?.message) {
          appendMessage(result.message, state.activeConversationId);
        }
      }
      composerInput.value = "";
      if (composerFileInput) {
        composerFileInput.value = "";
      }
      if (composerFileName) {
        composerFileName.textContent = "No file selected";
      }
      clearReplyTarget();
    } catch (error) {
      console.error(error);
    } finally {
      sendButton.disabled = false;
    }
  };

  const toggleConversationStatus = async () => {
    if (!state.activeConversationId) return;

    const conversation = state.conversations.find((item) => item.id === state.activeConversationId);
    if (!conversation) return;

    const endpoint = conversation.status === "closed" ? "reopen" : "close";
    const nextStatus = endpoint === "reopen" ? "open" : "closed";

    try {
      await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/${endpoint}`, {
        method: "POST",
      });

      conversation.status = nextStatus;
      updateThreadHeader(conversation);
      updateStatusButton(conversation);
      updateWorkflowButtons(conversation);
      applyLockState();
      renderConversations();
      await loadConversations();
    } catch (error) {
      console.error(error);
    }
  };

  const acceptConversation = async () => {
    if (!state.activeConversationId) return;

    try {
      const data = await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/accept`, {
        method: "POST",
      });

      applyAssignmentUpdate(data?.assigned_to ?? null, data?.assigned_name || null);
      const conversation = getActiveConversation();
      if (conversation) {
        conversation.status = "open";
        updateThreadHeader(conversation);
        updateStatusButton(conversation);
        updateWorkflowButtons(conversation);
        state.lockInfo = { status: "unlocked", owner_id: null };
        applyLockState();
        renderConversations();
        await loadConversations();
        await acquireLock();
      }
    } catch (error) {
      console.error(error);
    }
  };

  const transferConversation = async () => {
    if (!state.activeConversationId) return;

    const assignedInput = window.prompt("Masukkan ID agent tujuan (kosong jika tidak ubah):");
    if (assignedInput === null) return;

    const queueInput = window.prompt("Masukkan ID queue (kosong jika tidak ubah):");
    if (queueInput === null) return;

    const payload = {};

    if (assignedInput !== "") {
      const assignedId = Number.parseInt(assignedInput, 10);
      if (Number.isNaN(assignedId)) {
        window.alert("ID agent harus angka.");
        return;
      }
      payload.assigned_to = assignedId;
    }

    if (queueInput !== "") {
      const queueId = Number.parseInt(queueInput, 10);
      if (Number.isNaN(queueId)) {
        window.alert("ID queue harus angka.");
        return;
      }
      payload.queue_id = queueId;
    }

    if (!("assigned_to" in payload) && !("queue_id" in payload)) {
      return;
    }

    try {
      const data = await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/transfer`, {
        method: "POST",
        body: JSON.stringify(payload),
      });

      applyAssignmentUpdate(data.assigned_to || null, data.assigned_name || null);
    } catch (error) {
      console.error(error);
    }
  };

  const deleteConversation = async () => {
    if (!state.activeConversationId) return;

    const conversation = getActiveConversation();
    if (!conversation) return;

    const label =
      conversation.contact?.display_name ||
      conversation.contact?.phone ||
      conversation.contact?.wa_id ||
      `#${conversation.id}`;

    const confirmed = window.confirm(`Hapus chat ${label}? Semua pesan akan dihapus.`);
    if (!confirmed) return;

    try {
      await apiFetch(`${apiBase}/conversations/${state.activeConversationId}`, {
        method: "DELETE",
      });

      const removedId = state.activeConversationId;
      state.conversations = state.conversations.filter((item) => item.id !== removedId);
      state.activeConversationId = null;
      state.messagesCursor = null;
      state.messageIds.clear();
      messagesEl.innerHTML = '<div class="empty-state">Pick a conversation to load messages.</div>';

      renderConversations();
      updateThreadHeader(null);
      updateStatusButton(null);
      updateWorkflowButtons(null);
      applyLockState();

      if (state.conversations.length) {
        await selectConversation(state.conversations[0].id);
      }
    } catch (error) {
      console.error(error);
    }
  };

  const deleteMessage = async (message) => {
    if (!state.activeConversationId || !message?.id) return;

    const confirmLabel = message.direction === "out" ? "Revoke pesan ini?" : "Hapus pesan ini?";
    const confirmed = window.confirm(confirmLabel);
    if (!confirmed) return;

    try {
      await apiFetch(`${apiBase}/conversations/${state.activeConversationId}/messages/${message.id}`, {
        method: "DELETE",
      });

      const messageEl = messagesEl.querySelector(`[data-message-id="${message.id}"]`);
      if (messageEl) {
        messageEl.remove();
      }
      state.messageIds.delete(message.id);

      if (state.replyTo?.id === message.id) {
        clearReplyTarget();
      }

      await loadConversations();
    } catch (error) {
      console.error(error);
    }
  };

  const handleSearch = () => {
    const query = searchInput.value.toLowerCase();
    const filtered = state.conversations.filter((conversation) => {
      const contact = conversation.contact || {};
      const text = `${contact.display_name || ""} ${contact.phone || ""} ${contact.wa_id || ""}`.toLowerCase();
      return text.includes(query);
    });

    renderConversations(filtered);
  };

  const setupInfiniteScroll = () => {
    messagesEl.addEventListener("scroll", () => {
      if (messagesEl.scrollTop <= 120 && state.messagesCursor) {
        void loadMessages(state.activeConversationId, {
          cursor: state.messagesCursor,
          prepend: true,
        });
      }
    });
  };

  const startConversationPolling = () => {
    if (state.pollTimer) return;
    state.pollTimer = window.setInterval(() => {
      if (!state.loadingConversations) {
        void loadConversations();
      }
    }, 5000);
  };

  const stopConversationPolling = () => {
    if (!state.pollTimer) return;
    clearInterval(state.pollTimer);
    state.pollTimer = null;
  };

  const setupEcho = async () => {
    try {
      if (!window.Echo) {
        throw new Error("Echo is not available");
      }

      state.echo = window.Echo;

      if (connectionStatus) {
        connectionStatus.textContent = "Live";
      }

      state.echo.channel("conversations").listen("ConversationUpdated", (event) => {
        updateConversationFromEvent(event);
      });

      const connection = state.echo.connector?.pusher?.connection;
      if (connection?.bind) {
        connection.bind("connected", () => {
          stopConversationPolling();
        });
        connection.bind("disconnected", () => {
          startConversationPolling();
        });
        connection.bind("error", () => {
          startConversationPolling();
        });
      }

      if (connection?.state === "connected") {
        stopConversationPolling();
      }
    } catch (error) {
      console.warn("Echo unavailable", error);
      if (connectionStatus) {
        connectionStatus.textContent = "Offline";
      }
      startConversationPolling();
    }
  };

  const subscribeToConversation = (conversationId) => {
    if (!state.echo) return;

    if (state.conversationChannel) {
      state.echo.leave(state.conversationChannel);
    }

    const channelName = `conversation.${conversationId}`;
    state.conversationChannel = channelName;

    const channel = state.echo.private(channelName);
    channel.listen("MessageCreated", (event) => {
      if (shouldSkipSelf(event)) return;
      appendMessage(event?.message, conversationId);
    });
    channel.listen("MessageStatusUpdated", (event) => {
      if (shouldSkipSelf(event)) return;
      updateMessageStatus(event);
    });
  };

  const init = async () => {
    const emojiList = [
      "😀",
      "😁",
      "😂",
      "🤣",
      "😊",
      "😍",
      "😘",
      "😎",
      "🤩",
      "🥳",
      "😇",
      "😉",
      "😅",
      "😌",
      "😜",
      "🤔",
      "🙌",
      "👏",
      "🙏",
      "👍",
      "👎",
      "❤️",
      "💙",
      "💚",
      "💛",
      "🔥",
      "🎉",
      "✨",
      "💯",
      "😢",
      "😭",
      "😡",
      "😴",
      "🤝",
      "👌",
      "🤗",
      "😺",
      "🤖",
      "🫶",
      "✅",
    ];

    const insertEmoji = (emoji) => {
      const start = composerInput.selectionStart ?? composerInput.value.length;
      const end = composerInput.selectionEnd ?? composerInput.value.length;
      const value = composerInput.value;
      composerInput.value = `${value.slice(0, start)}${emoji}${value.slice(end)}`;
      const cursor = start + emoji.length;
      composerInput.setSelectionRange(cursor, cursor);
      composerInput.focus();
    };

    const buildEmojiPicker = () => {
      if (!emojiPicker) return;
      emojiPicker.innerHTML = "";
      emojiList.forEach((emoji) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "emoji-picker__item";
        button.textContent = emoji;
        button.addEventListener("click", () => insertEmoji(emoji));
        emojiPicker.appendChild(button);
      });
    };

    const toggleEmojiPicker = () => {
      if (!emojiPicker) return;
      if (emojiPicker.hasAttribute("hidden")) {
        emojiPicker.removeAttribute("hidden");
      } else {
        emojiPicker.setAttribute("hidden", "");
      }
    };

    const closeEmojiPicker = () => {
      if (!emojiPicker) return;
      emojiPicker.setAttribute("hidden", "");
    };

    const triggerComposerSubmit = () => {
      if (typeof composerForm.requestSubmit === "function") {
        composerForm.requestSubmit();
        return;
      }

      composerForm.dispatchEvent(new Event("submit", { cancelable: true }));
    };

    composerForm.addEventListener("submit", handleComposerSubmit);
    if (replyClearEl) {
      replyClearEl.addEventListener("click", clearReplyTarget);
    }
    composerInput.addEventListener("input", () => {
      updateQuickAnswerMatches();
    });

    composerInput.addEventListener("keydown", (event) => {
      if (state.quickAnswerMatches.length) {
        if (event.key === "ArrowDown") {
          event.preventDefault();
          state.quickAnswerActiveIndex = Math.min(
            state.quickAnswerActiveIndex + 1,
            state.quickAnswerMatches.length - 1
          );
          renderQuickAnswers();
          return;
        }

        if (event.key === "ArrowUp") {
          event.preventDefault();
          state.quickAnswerActiveIndex = Math.max(state.quickAnswerActiveIndex - 1, 0);
          renderQuickAnswers();
          return;
        }

        if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
          event.preventDefault();
          const selected = state.quickAnswerMatches[state.quickAnswerActiveIndex] || null;
          if (selected) {
            applyQuickAnswer(selected);
          }
          return;
        }

        if (event.key === "Escape") {
          event.preventDefault();
          hideQuickAnswers();
          return;
        }
      }

      if (event.key !== "Enter" || event.shiftKey || event.isComposing) {
        return;
      }

      event.preventDefault();
      triggerComposerSubmit();
    });

    if (lockToggle) {
      lockToggle.addEventListener("click", () => {
        if (state.lockOwnedByUser) {
          void releaseLock();
        } else {
          void acquireLock();
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener("input", handleSearch);
    }

    if (statusToggle) {
      statusToggle.addEventListener("click", () => {
        void toggleConversationStatus();
      });
    }

    if (acceptToggle) {
      acceptToggle.addEventListener("click", () => {
        void acceptConversation();
      });
    }

    if (emojiToggle) {
      emojiToggle.addEventListener("click", (event) => {
        event.stopPropagation();
        toggleEmojiPicker();
      });
    }

    if (emojiPicker) {
      emojiPicker.addEventListener("click", (event) => {
        event.stopPropagation();
      });
    }

    if (quickAnswersEl) {
      quickAnswersEl.addEventListener("click", (event) => {
        event.stopPropagation();
      });
    }

    document.addEventListener("click", () => {
      closeEmojiPicker();
      closeActionMenus();
      hideQuickAnswers();
    });

    if (transferToggle) {
      transferToggle.addEventListener("click", () => {
        void transferConversation();
      });
    }

    if (deleteToggle) {
      deleteToggle.addEventListener("click", () => {
        void deleteConversation();
      });
    }

    if (attachToggle && composerFileInput) {
      attachToggle.addEventListener("click", () => {
        composerFileInput.click();
      });
    }

    if (composerFileInput && composerFileName) {
      composerFileInput.addEventListener("change", () => {
        const file = composerFileInput.files?.[0];
        composerFileName.textContent = file ? file.name : "No file selected";
      });
    }

    buildEmojiPicker();
    setupInfiniteScroll();
    startLockRefresh();
    startConversationPolling();
    await setupEcho();
    await loadQuickAnswers();
    await loadConversations();
  };

  init();
}
