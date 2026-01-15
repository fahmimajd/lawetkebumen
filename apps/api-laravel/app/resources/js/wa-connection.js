import QRCode from "qrcode";

const setupWaConnection = () => {
  const root = document.querySelector("[data-wa-connection]");
  if (!root) {
    return;
  }

  const statusEl = root.querySelector("[data-wa-status]");
  const errorEl = root.querySelector("[data-wa-error]");
  const hintEl = root.querySelector("[data-wa-qr-hint]");
  const canvas = root.querySelector("#wa-qr-canvas");
  const rawEl = root.querySelector("[data-wa-qr-raw]");

  const statusUrl = root.dataset.statusUrl;
  const qrUrl = root.dataset.qrUrl;

  if (!statusEl || !errorEl || !hintEl || !canvas || !statusUrl || !qrUrl) {
    return;
  }

  const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    const data = await response.json();
    return { ok: response.ok, data };
  };

  const renderQr = async (qr) => {
    if (!qr) {
      canvas.style.display = "none";
      if (rawEl) {
        rawEl.textContent = "";
      }
      hintEl.textContent = "QR akan muncul jika belum terhubung.";
      return;
    }

    canvas.style.display = "block";
    hintEl.textContent = "Scan QR dengan WhatsApp.";
    if (rawEl) {
      rawEl.textContent = qr;
    }

    try {
      await QRCode.toCanvas(canvas, qr, { width: 220 });
    } catch (error) {
      errorEl.textContent = "Gagal menampilkan QR. Silakan refresh halaman.";
    }
  };

  const refreshStatus = async () => {
    const { ok, data } = await fetchJson(statusUrl);
    if (!ok) {
      statusEl.textContent = "disconnected";
      errorEl.textContent = data.message || "Gagal mengambil status gateway.";
      await renderQr(null);
      return;
    }

    const rawStatus = data.status || data.connection || "unknown";
    const displayStatus =
      rawStatus === "open"
        ? "connected"
        : rawStatus === "connecting"
          ? "connecting"
          : "disconnected";

    statusEl.textContent = displayStatus;
    errorEl.textContent = "";

    if (rawStatus !== "open") {
      const qrResp = await fetchJson(qrUrl);
      if (qrResp.ok) {
        await renderQr(qrResp.data.qr);
      } else {
        errorEl.textContent = qrResp.data.message || "Gagal mengambil QR.";
        await renderQr(null);
      }
    } else {
      await renderQr(null);
    }
  };

  refreshStatus();
  setInterval(refreshStatus, 2500);
};

setupWaConnection();
