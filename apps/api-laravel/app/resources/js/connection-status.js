const statusEl = document.querySelector("[data-connection-status]");

if (statusEl) {
  const fetchJson = async () => {
    const response = await fetch("/wa/status", {
      headers: { Accept: "application/json" },
    });

    const data = await response.json().catch(() => ({}));
    return { ok: response.ok, data };
  };

  const refreshStatus = async () => {
    const { ok, data } = await fetchJson();
    if (!ok) {
      statusEl.textContent = "Offline";
      return;
    }

    const rawStatus = data.status || data.connection || "unknown";
    const display =
      rawStatus === "open"
        ? "Live"
        : rawStatus === "connecting"
          ? "Connecting"
          : "Offline";
    statusEl.textContent = display;
  };

  refreshStatus();
  setInterval(refreshStatus, 5000);
}
