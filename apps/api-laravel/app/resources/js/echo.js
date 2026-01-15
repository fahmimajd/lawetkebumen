import Echo from "laravel-echo";
import Pusher from "pusher-js";

const getMeta = (name, fallback = "") => {
  const content = document.querySelector(`meta[name="${name}"]`)?.getAttribute("content");
  return content || fallback;
};

const env = import.meta.env;
const reverbKey = env.VITE_REVERB_APP_KEY || getMeta("reverb-key");
const reverbHost =
  env.VITE_REVERB_HOST ||
  getMeta("reverb-host", window.location.hostname);
const reverbPort = Number(
  env.VITE_REVERB_PORT || getMeta("reverb-port", "8080"),
);
const reverbScheme =
  env.VITE_REVERB_SCHEME ||
  getMeta(
    "reverb-scheme",
    window.location.protocol === "https:" ? "https" : "http",
  );
const useTLS = reverbScheme === "https";

window.Pusher = Pusher;

if (!window.Echo) {
  window.Echo = new Echo({
    broadcaster: "reverb",
    key: reverbKey,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: useTLS,
    enabledTransports: ["ws", "wss"],
    disableStats: true,
  });
}

const demoConversationId = Number(env.VITE_REVERB_CHAT_CONVERSATION_ID || 0);
if (window.Echo && Number.isFinite(demoConversationId) && demoConversationId > 0) {
  window.Echo.private(`chat.${demoConversationId}`).listen("NewMessage", (event) => {
    if (import.meta.env.DEV) {
      console.log("NewMessage", event);
    }
  });
}
