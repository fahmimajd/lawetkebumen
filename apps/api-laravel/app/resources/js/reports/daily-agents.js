const setupDailyAgentReport = () => {
  const root = document.querySelector("[data-daily-agent-report]");
  if (!root) {
    return;
  }

  const dataUrl = root.dataset.reportUrl;
  const dateInput = root.querySelector("[data-report-date]");
  const body = root.querySelector("[data-report-body]");
  const errorEl = root.querySelector("[data-report-error]");

  if (!dataUrl || !dateInput || !body || !errorEl) {
    return;
  }

  const renderRows = (rows) => {
    body.innerHTML = "";

    if (!rows.length) {
      const empty = document.createElement("div");
      empty.className = "report-table__row report-table__row--empty";
      empty.textContent = "No data";
      body.appendChild(empty);
      return;
    }

    rows.forEach((row) => {
      const record = document.createElement("div");
      record.className = "report-table__row";
      record.innerHTML = `
        <div>${row.name}</div>
        <div>${row.assigned_today}</div>
        <div>${row.resolved_today}</div>
        <div>${row.active_now}</div>
        <div>${row.transfer_out_today}</div>
        <div>${row.reopened_today}</div>
        <div>${row.messages_sent_today}</div>
        <div>${row.messages_received_today}</div>
      `;
      body.appendChild(record);
    });
  };

  const fetchReport = async () => {
    errorEl.textContent = "";
    const params = new URLSearchParams();
    if (dateInput.value) {
      params.set("date", dateInput.value);
    }

    const url = params.toString() ? `${dataUrl}?${params.toString()}` : dataUrl;

    try {
      const response = await fetch(url, { headers: { Accept: "application/json" } });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.message || "Failed to load report");
      }

      renderRows(payload);
    } catch (error) {
      errorEl.textContent = error.message || "Failed to load report";
    }
  };

  dateInput.addEventListener("change", fetchReport);
  fetchReport();
};

setupDailyAgentReport();
