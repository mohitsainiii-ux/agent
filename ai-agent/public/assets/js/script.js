(() => {
  const messageInput = document.getElementById("messageInput");
  const sendBtn = document.getElementById("sendBtn");
  const messageArea = document.getElementById("messageArea");
  const emptyState = document.getElementById("emptyState");
  const typingIndicator = document.getElementById("typingIndicator");
  const messagesContainer = document.getElementById("messagesContainer");
  const chatTitle = document.getElementById("chatTitle");
  const historyList = document.getElementById("historyList");
  const historyListMobile = document.getElementById("historyListMobile");
  const searchHistory = document.getElementById("searchHistory");
  const searchHistoryMobile = document.getElementById("searchHistoryMobile");
  const themeToggle = document.getElementById("themeToggle");
  const themeToggleMobile = document.getElementById("themeToggleMobile");
  const attachBtn = document.getElementById("attachBtn");
  const fileInput = document.getElementById("fileInput");
  const numpyForm = document.getElementById("numpyForm");
  const numpyInput = document.getElementById("numpyInput");
  const numpyOperation = document.getElementById("numpyOperation");
  const numpySubmit = document.getElementById("numpySubmit");
  const numpyError = document.getElementById("numpyError");
  const numpyResult = document.getElementById("numpyResult");
  const numpyFilterFields = document.querySelector(".numpy-filter-fields");
  const numpyReshapeFields = document.querySelector(".numpy-reshape-fields");
  const numpyStatusDot = document.getElementById("numpyStatusDot");
  const onlineStatus = document.getElementById("onlineStatus");
  const settingsForm = document.getElementById("settingsForm");

  const STORAGE_KEY = "ai-assistant-chats";
  const THEME_KEY = "ai-assistant-theme";

  let chats = loadChats();
  let activeChatId = chats[0]?.id || createChat().id;

  applyTheme(localStorage.getItem(THEME_KEY) || "light");
  renderHistory();
  renderActiveChat();
  initialiseNumpy();

  document.getElementById("newChatBtn")?.addEventListener("click", startNewChat);
  document.getElementById("newChatBtnMobile")?.addEventListener("click", startNewChat);
  sendBtn?.addEventListener("click", sendMessage);
  attachBtn?.addEventListener("click", () => fileInput?.click());
  fileInput?.addEventListener("change", handleFile);
  themeToggle?.addEventListener("click", toggleTheme);
  themeToggleMobile?.addEventListener("click", toggleTheme);
  searchHistory?.addEventListener("input", () => renderHistory(searchHistory.value));
  searchHistoryMobile?.addEventListener("input", () => renderHistory(searchHistoryMobile.value));

  numpyOperation?.addEventListener("change", updateNumpyFields);
  numpyForm?.addEventListener("submit", processNumpy);
  settingsForm?.addEventListener("submit", saveSettings);
  document.getElementById("healthCheckBtn")?.addEventListener("click", () => checkHealth(true));

  document.querySelectorAll(".suggestion-chip").forEach((chip) => {
    chip.addEventListener("click", () => {
      messageInput.value = chip.dataset.prompt || chip.textContent;
      autoResize();
      sendMessage();
    });
  });

  messageInput?.addEventListener("input", autoResize);
  messageInput?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      sendMessage();
    }
  });

  function loadChats() {
    try {
      const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  }

  function initialiseNumpy() {
    const settings = window.numpySettings || {};
    const python = settings.python || {};
    const numpy = settings.numpy || {};

    setValue("apiUrl", python.api_url || "http://127.0.0.1:8000");
    setValue("apiTimeout", python.timeout || 30);
    setChecked("pythonEnabled", python.enabled !== false);
    setValue("defaultOperation", numpy.default_operation || "sum");
    setValue("decimalPrecision", numpy.decimal_precision ?? 2);
    setValue("maxInputValues", numpy.max_input_values || 10000);
    setValue("defaultRows", numpy.reshape_rows || 1);
    setValue("defaultCols", numpy.reshape_cols || 5);
    setChecked("allowNegative", numpy.allow_negative !== false);
    setChecked("allowDecimal", numpy.allow_decimal !== false);
    setChecked("autoArrayConversion", numpy.auto_array_conversion !== false);
    numpyOperation.value = numpy.default_operation || "sum";
    updateNumpyFields();
    checkHealth(false);
  }

  function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value;
  }

  function setChecked(id, value) {
    const element = document.getElementById(id);
    if (element) element.checked = Boolean(value);
  }

  function updateNumpyFields() {
    const operation = numpyOperation?.value;
    numpyFilterFields?.classList.toggle("d-none", operation !== "filter");
    numpyReshapeFields?.classList.toggle("d-none", operation !== "reshape");
  }

  async function processNumpy(event) {
    event.preventDefault();
    clearNumpyMessage();
    numpySubmit.disabled = true;
    numpySubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Processing';

    const payload = {
      operation: numpyOperation.value,
      input: numpyInput.value,
      filter_value: document.getElementById("filterValue")?.value || null,
      reshape_rows: document.getElementById("reshapeRows")?.value || undefined,
      reshape_cols: document.getElementById("reshapeCols")?.value || undefined,
    };

    try {
      const response = await fetch(window.numpyEndpoints.process, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "The calculation could not be completed.");
      numpyResult.textContent = result.display || formatNumpyResult(result.result);
      numpyResult.classList.remove("d-none");
    } catch (error) {
      numpyError.textContent = error.message || "Python API is unavailable. Check Settings.";
      numpyError.classList.remove("d-none");
    } finally {
      numpySubmit.disabled = false;
      numpySubmit.innerHTML = '<i class="bi bi-calculator me-1"></i> Calculate';
    }
  }

  function formatNumpyResult(result) {
    return typeof result === "object" ? JSON.stringify(result) : String(result);
  }

  function clearNumpyMessage() {
    numpyError?.classList.add("d-none");
    numpyResult?.classList.add("d-none");
  }

  async function checkHealth(showMessage) {
    if (showMessage) setSettingsStatus("Checking...", false);
    try {
      const response = await fetch(window.numpyEndpoints.health, { headers: { Accept: "application/json" } });
      const result = await response.json();
      const connected = response.ok && result.connected === true;
      onlineStatus.textContent = connected ? "Python API connected" : "Python API disconnected";
      onlineStatus.className = `badge ${connected ? "bg-success-subtle text-success border-success-subtle" : "bg-danger-subtle text-danger border-danger-subtle"} border small`;
      numpyStatusDot?.classList.toggle("connected", connected);
      if (showMessage) setSettingsStatus(connected ? "Connected" : "Disconnected", connected);
    } catch {
      onlineStatus.textContent = "Python API disconnected";
      onlineStatus.className = "badge bg-danger-subtle text-danger border-danger-subtle border small";
      numpyStatusDot?.classList.remove("connected");
      if (showMessage) setSettingsStatus("Disconnected", false);
    }
  }

  async function saveSettings(event) {
    event.preventDefault();
    const errorBox = document.getElementById("settingsError");
    errorBox.classList.add("d-none");
    const payload = {
      python: { api_url: document.getElementById("apiUrl").value, timeout: document.getElementById("apiTimeout").value, enabled: document.getElementById("pythonEnabled").checked },
      numpy: { default_operation: document.getElementById("defaultOperation").value, decimal_precision: document.getElementById("decimalPrecision").value, max_input_values: document.getElementById("maxInputValues").value, reshape_rows: document.getElementById("defaultRows").value, reshape_cols: document.getElementById("defaultCols").value, allow_negative: document.getElementById("allowNegative").checked, allow_decimal: document.getElementById("allowDecimal").checked, auto_array_conversion: document.getElementById("autoArrayConversion").checked },
    };

    try {
      const response = await fetch(window.numpyEndpoints.settings, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify(payload) });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "Settings could not be saved.");
      window.numpySettings = result.settings;
      numpyOperation.value = payload.numpy.default_operation;
      updateNumpyFields();
      setSettingsStatus("Saved", true);
      checkHealth(false);
    } catch (error) {
      errorBox.textContent = error.message || "Settings could not be saved.";
      errorBox.classList.remove("d-none");
    }
  }

  function setSettingsStatus(message, success) {
    const status = document.getElementById("settingsStatus");
    if (!status) return;
    status.textContent = message;
    status.className = `small ms-2 ${success ? "text-success" : "text-danger"}`;
  }

  function saveChats() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(chats));
  }

  function createChat() {
    const chat = {
      id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()),
      title: "New conversation",
      messages: [],
      updatedAt: Date.now(),
    };
    chats.unshift(chat);
    saveChats();
    return chat;
  }

  function getActiveChat() {
    return chats.find((chat) => chat.id === activeChatId);
  }

  function startNewChat() {
    const chat = createChat();
    activeChatId = chat.id;
    renderHistory();
    renderActiveChat();
    messageInput?.focus();
  }

  function sendMessage() {
    const text = (messageInput.value || "").trim();
    if (!text) return;

    const chat = getActiveChat();
    if (!chat) return;

    chat.messages.push({ role: "user", content: text });
    if (chat.title === "New conversation") {
      chat.title = text.slice(0, 42);
      chatTitle.textContent = chat.title;
    }
    chat.updatedAt = Date.now();
    saveChats();

    messageInput.value = "";
    autoResize();
    emptyState.classList.add("d-none");
    appendBubble("user", text);
    scrollToBottom();
    renderHistory();
    requestReply(text, chat.id);
  }

  async function requestReply(prompt, chatId) {
    typingIndicator.classList.remove("d-none");
    scrollToBottom();

    try {
      const response = await fetch(window.chatEndpoints.send, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ message: prompt }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || "The AI response could not be generated.");
      }

      const chat = chats.find((entry) => entry.id === chatId);
      if (!chat) return;
      const reply = result.response;
      chat.messages.push({ role: "ai", content: reply });
      chat.updatedAt = Date.now();
      saveChats();
      appendBubble("ai", reply);
      scrollToBottom();
    } catch (error) {
      const chat = chats.find((entry) => entry.id === chatId);
      if (chat) {
        const reply = `Unable to contact the AI service: ${error.message || "Please try again."}`;
        chat.messages.push({ role: "ai", content: reply });
        chat.updatedAt = Date.now();
        saveChats();
        appendBubble("ai", reply);
        scrollToBottom();
      }
    } finally {
      typingIndicator.classList.add("d-none");
    }
  }

  function appendBubble(role, content) {
    const row = document.createElement("div");
    row.className = `message-row ${role}`;

    if (role === "ai") {
      const avatar = document.createElement("div");
      avatar.className = "avatar";
      avatar.innerHTML = '<i class="bi bi-robot"></i>';
      row.appendChild(avatar);
    }

    const bubble = document.createElement("div");
    bubble.className = "message-bubble";
    bubble.textContent = content;
    row.appendChild(bubble);
    messageArea.appendChild(row);
  }

  function renderActiveChat() {
    const chat = getActiveChat();
    messageArea.innerHTML = "";
    chatTitle.textContent = chat?.title || "New conversation";

    if (!chat || chat.messages.length === 0) {
      emptyState.classList.remove("d-none");
      return;
    }

    emptyState.classList.add("d-none");
    chat.messages.forEach((message) => appendBubble(message.role, message.content));
    scrollToBottom();
  }

  function renderHistory(query = "") {
    const term = query.trim().toLowerCase();
    const filtered = chats.filter((chat) => chat.title.toLowerCase().includes(term));
    [historyList, historyListMobile].forEach((list) => {
      if (!list) return;
      list.innerHTML = "";
      filtered.forEach((chat) => list.appendChild(historyItem(chat)));
    });
  }

  function historyItem(chat) {
    const item = document.createElement("li");
    item.className = "history-item" + (chat.id === activeChatId ? " active" : "");
    item.innerHTML = `
      <i class="bi bi-chat-left-text"></i>
      <span class="title"></span>
      <button class="delete-chat" type="button" aria-label="Delete chat">
        <i class="bi bi-x"></i>
      </button>
    `;
    item.querySelector(".title").textContent = chat.title;
    item.addEventListener("click", (event) => {
      if (event.target.closest(".delete-chat")) return;
      activeChatId = chat.id;
      renderHistory(searchHistory?.value || "");
      renderActiveChat();
    });
    item.querySelector(".delete-chat").addEventListener("click", (event) => {
      event.stopPropagation();
      chats = chats.filter((entry) => entry.id !== chat.id);
      if (!chats.length) {
        activeChatId = createChat().id;
      } else if (activeChatId === chat.id) {
        activeChatId = chats[0].id;
      }
      saveChats();
      renderHistory();
      renderActiveChat();
    });
    return item;
  }

  function handleFile() {
    const file = fileInput.files?.[0];
    if (!file) return;
    messageInput.value = `Attached file: ${file.name}`;
    autoResize();
    fileInput.value = "";
  }

  function toggleTheme() {
    const next = document.documentElement.classList.contains("dark") ? "light" : "dark";
    applyTheme(next);
  }

  function applyTheme(theme) {
    document.documentElement.classList.toggle("dark", theme === "dark");
    localStorage.setItem(THEME_KEY, theme);
    const icon = theme === "dark" ? "bi-sun" : "bi-moon-stars";
    [themeToggle, themeToggleMobile].forEach((btn) => {
      if (!btn) return;
      btn.innerHTML = `<i class="bi ${icon}"></i>`;
    });
  }

  function autoResize() {
    messageInput.style.height = "auto";
    messageInput.style.height = Math.min(messageInput.scrollHeight, 160) + "px";
  }

  function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }
})();
