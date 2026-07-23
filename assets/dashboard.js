(function () {
  const BASE = (document.body.dataset.base || "").replace(/\/$/, "");

  function apiUrl(path) {
    return (BASE ? BASE : "") + path;
  }

  function setStatus(card, message, kind) {
    const statusEl = card.querySelector(".card-status");
    if (!statusEl) return;
    statusEl.textContent = message || "";
    statusEl.className = "status card-status" + (kind ? " " + kind : "");
  }

  function setCardBusy(card, busy) {
    card.querySelectorAll(".run-btn").forEach((btn) => {
      btn.disabled = busy;
    });
    card.querySelectorAll(".command-input").forEach((input) => {
      input.disabled = busy;
    });
    const refreshBtn = card.querySelector(".refresh-btn");
    if (refreshBtn) refreshBtn.disabled = busy;
    const clearLink = card.querySelector(".clear-log-link");
    if (clearLink) clearLink.classList.toggle("is-disabled", busy);
  }

  function scrollLogToEnd(pre) {
    if (!pre) return;
    pre.scrollTop = pre.scrollHeight;
    pre.scrollLeft = 0;
  }

  function renderLog(card, payload) {
    const log = payload.log;
    const pre = card.querySelector(".log-content");
    const hint = card.querySelector(".log-hint");

    if (hint) hint.textContent = log.truncated ? "last 2000 lines" : "full file";
    if (pre) {
      pre.textContent = log.content || "";
      pre.classList.toggle("empty", !log.content);
      scrollLogToEnd(pre);
    }
  }

  async function parseJson(res) {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (err) {
      throw new Error(
        "Server returned non-JSON (HTTP " + res.status + "). Check api path and PHP errors."
      );
    }
  }

  async function refreshLog(card, statusMessage) {
    const id = card.dataset.projectId;
    setStatus(card, "Loading log...", "busy");
    const res = await fetch(apiUrl("/api/log.php?id=" + encodeURIComponent(id)), {
      headers: { Accept: "application/json" },
    });
    const data = await parseJson(res);
    if (!data.ok) {
      throw new Error(data.error || "Failed to load log.");
    }
    renderLog(card, data);
    setStatus(card, statusMessage || "Log updated.", "ok");
  }

  async function clearLog(card) {
    const id = card.dataset.projectId;
    setStatus(card, "Clearing log...", "busy");
    setCardBusy(card, true);

    try {
      const res = await fetch(apiUrl("/api/clear_log.php"), {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id }),
      });
      const data = await parseJson(res);
      if (!data.ok) {
        throw new Error(data.error || "Failed to clear log.");
      }
      renderLog(card, data);
      setStatus(card, "Log cleared.", "ok");
    } catch (err) {
      setStatus(card, err.message || "Clear failed.", "err");
    } finally {
      setCardBusy(card, false);
    }
  }

  async function runCommand(card, row) {
    const id = card.dataset.projectId;
    const commandId = row.dataset.commandId;
    const hasInput = row.dataset.hasInput === "1";
    const inputEl = row.querySelector(".command-input");
    const labelEl = row.querySelector(".command-label");
    const label = labelEl ? labelEl.textContent : commandId;

    let input = "";
    if (hasInput) {
      input = (inputEl && inputEl.value ? inputEl.value : "").trim();
      if (!input) {
        setStatus(card, "Please enter a value before running.", "err");
        if (inputEl) inputEl.focus();
        return;
      }
    }

    setCardBusy(card, true);
    setStatus(card, 'Running "' + label + '"... this may take a while.', "busy");

    try {
      const res = await fetch(apiUrl("/api/run.php"), {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id: id,
          command_id: commandId,
          input: input,
        }),
      });
      const data = await parseJson(res);
      const failMessage = !data.ok
        ? (data.error || "Script failed with exit code " + data.exit_code) +
          (data.elapsed_ms != null ? " (" + data.elapsed_ms + " ms)" : "") +
          (data.output ? "\n\n" + data.output : "")
        : null;

      if (failMessage) {
        setStatus(card, failMessage + "\n\nRefreshing log...", "err");
      } else {
        setStatus(
          card,
          '"' + label + '" finished in ' + data.elapsed_ms + " ms. Refreshing log...",
          "ok"
        );
      }

      const logRes = await fetch(apiUrl("/api/log.php?id=" + encodeURIComponent(id)), {
        headers: { Accept: "application/json" },
      });
      const logData = await parseJson(logRes);
      if (!logData.ok) {
        throw new Error(logData.error || "Failed to load log.");
      }
      renderLog(card, logData);

      if (failMessage) {
        setStatus(card, failMessage + "\n\nLog refreshed after failure.", "err");
      } else {
        setStatus(card, '"' + label + '" finished. Log refreshed.', "ok");
      }
    } catch (err) {
      setStatus(card, err.message || "Run failed.", "err");
    } finally {
      setCardBusy(card, false);
    }
  }

  function closest(el, selector) {
    return el && el.closest ? el.closest(selector) : null;
  }

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const refreshBtn = closest(target, ".refresh-btn");
    if (refreshBtn) {
      if (refreshBtn.disabled) return;
      const card = closest(refreshBtn, ".project-card");
      if (!card) return;
      if (card.dataset.logOk !== "1") {
        setStatus(card, "Log file not found.", "err");
        return;
      }
      refreshLog(card).catch((err) =>
        setStatus(card, err.message || "Refresh failed.", "err")
      );
      return;
    }

    const clearLink = closest(target, ".clear-log-link");
    if (clearLink) {
      event.preventDefault();
      if (clearLink.classList.contains("is-disabled")) return;
      const card = closest(clearLink, ".project-card");
      if (!card) return;
      if (card.dataset.logOk !== "1") {
        setStatus(card, "Log file not found.", "err");
        return;
      }
      clearLog(card);
      return;
    }

    const runBtn = closest(target, ".run-btn");
    if (runBtn) {
      if (runBtn.disabled) return;
      const row = closest(runBtn, ".command-row");
      const card = closest(runBtn, ".project-card");
      if (!row || !card) return;
      if (card.dataset.scriptOk !== "1") {
        setStatus(card, "Script not found.", "err");
        return;
      }
      runCommand(card, row);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Enter") return;
    const target = event.target;
    if (!(target instanceof Element) || !target.classList.contains("command-input")) {
      return;
    }
    event.preventDefault();
    const row = closest(target, ".command-row");
    const card = closest(target, ".project-card");
    if (!row || !card) return;
    runCommand(card, row);
  });

  document.querySelectorAll(".project-card .log-content").forEach(scrollLogToEnd);
})();
