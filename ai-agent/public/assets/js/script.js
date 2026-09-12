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
  const apiStatusDot = document.getElementById("apiStatusDot");
  const onlineStatus = document.getElementById("onlineStatus");
  const settingsForm = document.getElementById("settingsForm");
  const authModal = document.getElementById("authModal");
  const authForm = document.getElementById("authForm");
  const authNameGroup = document.getElementById("authNameGroup");
  const authModalTitle = document.getElementById("authModalTitle");
  const authModeToggle = document.getElementById("authModeToggle");
  const authSubmit = document.getElementById("authSubmit");
  const authError = document.getElementById("authError");
  const userIdentity = document.getElementById("userIdentity");
  const userIdentityMobile = document.getElementById("userIdentityMobile");
  const authActionBtn = document.getElementById("authActionBtn");
  const authActionBtnMobile = document.getElementById("authActionBtnMobile");

  const THEME_KEY = "ai-assistant-theme";

  let chats = [];
  let activeChatId = null;
  let currentUser = null;
  let authMode = "login";

  applyTheme(localStorage.getItem(THEME_KEY) || "light");
  initialiseConnection();
  initialiseChat();

  document.getElementById("newChatBtn")?.addEventListener("click", startNewChat);
  document.getElementById("newChatBtnMobile")?.addEventListener("click", startNewChat);
  sendBtn?.addEventListener("click", sendMessage);
  attachBtn?.addEventListener("click", () => fileInput?.click());
  fileInput?.addEventListener("change", handleFile);
  themeToggle?.addEventListener("click", toggleTheme);
  themeToggleMobile?.addEventListener("click", toggleTheme);
  searchHistory?.addEventListener("input", () => renderHistory(searchHistory.value));
  searchHistoryMobile?.addEventListener("input", () => renderHistory(searchHistoryMobile.value));

  settingsForm?.addEventListener("submit", saveSettings);
  authForm?.addEventListener("submit", submitAuth);
  authModeToggle?.addEventListener("click", toggleAuthMode);
  authActionBtn?.addEventListener("click", handleAuthAction);
  authActionBtnMobile?.addEventListener("click", handleAuthAction);
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

  async function initialiseChat() {
    try {
      await loadCurrentUser();
      const response = await fetch(window.chatEndpoints.conversations, { headers: { Accept: "application/json" } });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "Conversations could not be loaded.");
      chats = result.conversations || [];
      if (!chats.length) {
        const created = await createChat();
        chats = [created];
      }

      activeChatId = chats[0].id;
      await loadChat(activeChatId);
      renderHistory();
      renderActiveChat();
    } catch (error) {
      chatTitle.textContent = "Unable to load conversations";
      appendBubble("ai", error.message || "Conversations are temporarily unavailable.");
    }
  }

  async function loadCurrentUser() {
    const response = await fetch(window.authEndpoints.current, { headers: { Accept: "application/json" } });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || "Authentication state could not be loaded.");
    setCurrentUser(result.user);
  }

  function setCurrentUser(user) {
    currentUser = user;
    const label = user ? user.name : "Guest user";
    [userIdentity, userIdentityMobile].forEach((element) => {
      if (element) element.textContent = label;
    });
    [authActionBtn, authActionBtnMobile].forEach((button) => {
      if (button) button.textContent = user ? "Sign out" : "Sign in";
    });
  }

  function handleAuthAction() {
    if (currentUser) {
      logout();
      return;
    }
    authMode = "login";
    updateAuthModal();
    bootstrap.Modal.getOrCreateInstance(authModal).show();
  }

  function toggleAuthMode() {
    authMode = authMode === "login" ? "register" : "login";
    updateAuthModal();
  }

  function updateAuthModal() {
    const registering = authMode === "register";
    authModalTitle.textContent = registering ? "Create account" : "Sign in";
    authSubmit.textContent = registering ? "Create account" : "Sign in";
    authModeToggle.textContent = registering ? "Already have an account?" : "Create an account";
    authNameGroup.classList.toggle("d-none", !registering);
    document.getElementById("authPassword").setAttribute("autocomplete", registering ? "new-password" : "current-password");
    authError.classList.add("d-none");
  }

  async function submitAuth(event) {
    event.preventDefault();
    authError.classList.add("d-none");
    authSubmit.disabled = true;
    try {
      const registering = authMode === "register";
      const payload = {
        email: document.getElementById("authEmail").value,
        password: document.getElementById("authPassword").value,
      };
      if (registering) payload.name = document.getElementById("authName").value;
      const response = await fetch(registering ? window.authEndpoints.register : window.authEndpoints.login, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "Authentication failed.");
      setCurrentUser(result.user);
      bootstrap.Modal.getOrCreateInstance(authModal).hide();
      await reloadChats();
    } catch (error) {
      authError.textContent = error.message || "Authentication failed.";
      authError.classList.remove("d-none");
    } finally {
      authSubmit.disabled = false;
    }
  }

  async function logout() {
    try {
      const response = await fetch(window.authEndpoints.logout, {
        method: "POST",
        headers: { Accept: "application/json" },
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "Sign out failed.");
      setCurrentUser(null);
      await reloadChats();
    } catch (error) {
      appendBubble("ai", error.message || "Sign out failed.");
    }
  }

  async function reloadChats() {
    const response = await fetch(window.chatEndpoints.conversations, { headers: { Accept: "application/json" } });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || "Conversations could not be loaded.");
    chats = result.conversations || [];
    if (!chats.length) chats = [await createChat()];
    activeChatId = chats[0].id;
    await loadChat(activeChatId);
    renderHistory();
    renderActiveChat();
  }

  function initialiseConnection() {
    const settings = window.pythonSettings || {};
    const python = settings.python || {};

    setValue("apiUrl", python.api_url || "http://127.0.0.1:8001");
    setValue("apiTimeout", python.timeout || 30);
    setChecked("pythonEnabled", python.enabled !== false);
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

  async function checkHealth(showMessage) {
    if (showMessage) setSettingsStatus("Checking...", false);
    try {
      const response = await fetch(window.pythonEndpoints.health, { headers: { Accept: "application/json" } });
      const result = await response.json();
      const connected = response.ok && result.connected === true;
      onlineStatus.textContent = connected ? "Python API connected" : "Python API disconnected";
      onlineStatus.className = `badge ${connected ? "bg-success-subtle text-success border-success-subtle" : "bg-danger-subtle text-danger border-danger-subtle"} border small`;
      apiStatusDot?.classList.toggle("connected", connected);
      if (showMessage) setSettingsStatus(connected ? "Connected" : "Disconnected", connected);
    } catch {
      onlineStatus.textContent = "Python API disconnected";
      onlineStatus.className = "badge bg-danger-subtle text-danger border-danger-subtle border small";
      apiStatusDot?.classList.remove("connected");
      if (showMessage) setSettingsStatus("Disconnected", false);
    }
  }

  async function saveSettings(event) {
    event.preventDefault();
    const errorBox = document.getElementById("settingsError");
    errorBox.classList.add("d-none");
    const payload = {
      python: { api_url: document.getElementById("apiUrl").value, timeout: document.getElementById("apiTimeout").value, enabled: document.getElementById("pythonEnabled").checked },
    };

    try {
      const response = await fetch(window.pythonEndpoints.settings, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify(payload) });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "Settings could not be saved.");
      window.pythonSettings = result.settings;
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

  async function createChat() {
    const response = await fetch(window.chatEndpoints.conversations, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ title: "New conversation" }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || "A new conversation could not be created.");
    return result.conversation;
  }

  async function loadChat(chatId) {
    const response = await fetch(`${window.chatEndpoints.conversations}/${encodeURIComponent(chatId)}`, {
      headers: { Accept: "application/json" },
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || "The conversation could not be loaded.");
    const index = chats.findIndex((chat) => String(chat.id) === String(chatId));
    if (index >= 0) chats[index] = result.conversation;
    return result.conversation;
  }

  function getActiveChat() {
    return chats.find((chat) => String(chat.id) === String(activeChatId));
  }

  async function startNewChat() {
    try {
      const chat = await createChat();
      chats.unshift(chat);
      activeChatId = chat.id;
      renderHistory();
      renderActiveChat();
      messageInput?.focus();
    } catch (error) {
      appendBubble("ai", error.message || "A new conversation could not be created.");
    }
  }

  function sendMessage() {
    const text = (messageInput.value || "").trim();
    if (!text) return;

    const chat = getActiveChat();
    if (!chat) return;

    chat.messages = chat.messages || [];
    chat.messages.push({ role: "user", content: text });
    if (chat.title === "New conversation") {
      chat.title = text.slice(0, 42);
      chatTitle.textContent = chat.title;
    }
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
      const response = await fetch(`${window.chatEndpoints.conversations}/${encodeURIComponent(chatId)}/messages`, {
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
      chat.messages.push({ role: "assistant", content: reply });
      appendBubble("ai", reply);
      renderHistory();
      scrollToBottom();
    } catch (error) {
      const chat = chats.find((entry) => entry.id === chatId);
      if (chat) {
        const reply = `Unable to contact the AI service: ${error.message || "Please try again."}`;
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
    const messages = chat?.messages || [];

    if (!chat || messages.length === 0) {
      emptyState.classList.remove("d-none");
      return;
    }

    emptyState.classList.add("d-none");
    messages.forEach((message) => appendBubble(message.role === "assistant" ? "ai" : message.role, message.content));
    scrollToBottom();
  }

  function renderHistory(query = "") {
    const term = query.trim().toLowerCase();
    const filtered = chats.filter((chat) => (chat.title || "New conversation").toLowerCase().includes(term));
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
      loadChat(chat.id)
        .then(() => {
          renderHistory(searchHistory?.value || "");
          renderActiveChat();
        })
        .catch((error) => appendBubble("ai", error.message || "The conversation could not be loaded."));
    });
    item.querySelector(".delete-chat").addEventListener("click", (event) => {
      event.stopPropagation();
      deleteChat(chat);
    });
    return item;
  }

  async function deleteChat(chat) {
    try {
      const response = await fetch(`${window.chatEndpoints.conversations}/${encodeURIComponent(chat.id)}`, {
        method: "DELETE",
        headers: { Accept: "application/json" },
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "The conversation could not be deleted.");
      chats = chats.filter((entry) => String(entry.id) !== String(chat.id));
      if (!chats.length) {
        const created = await createChat();
        chats = [created];
      }
      if (String(activeChatId) === String(chat.id)) activeChatId = chats[0].id;
      await loadChat(activeChatId);
      renderHistory();
      renderActiveChat();
    } catch (error) {
      appendBubble("ai", error.message || "The conversation could not be deleted.");
    }
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
