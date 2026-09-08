<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AI Assistant · Chat</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <div class="d-flex" id="appWrapper">
    <aside class="sidebar d-none d-md-flex flex-column" id="sidebarDesktop">
      <div class="sidebar-header">
        <h5 class="fw-bold mb-0"><i class="bi bi-robot me-2"></i>AI Assistant</h5>
        <button class="btn btn-sm mt-3 w-100 new-chat-btn" id="newChatBtn" type="button">
          <i class="bi bi-plus-circle me-1"></i> New Chat
        </button>
      </div>

      <div class="px-3 my-2">
        <div class="input-group input-group-sm">
          <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control form-control-sm border-start-0" placeholder="Search chats..." id="searchHistory" />
        </div>
      </div>

      <div class="history-container flex-grow-1 overflow-auto px-3" id="historyContainer">
        <p class="text-secondary small mb-2 mt-2">Recent</p>
        <ul class="list-unstyled mb-0" id="historyList"></ul>
      </div>

      <div class="sidebar-footer px-3 py-3 border-top border-secondary-subtle">
        <div class="d-flex justify-content-between align-items-center">
          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal">
            <i class="bi bi-gear"></i> Settings
          </button>
          <button class="btn btn-outline-secondary btn-sm" id="themeToggle" type="button" aria-label="Toggle theme">
            <i class="bi bi-moon-stars"></i>
          </button>
        </div>
        <div class="d-flex align-items-center mt-3">
          <i class="bi bi-person-circle fs-5 me-2"></i>
          <span class="small">Guest user</span>
        </div>
      </div>
    </aside>

    <main class="chat-main flex-grow-1 d-flex flex-column">
      <header class="chat-header d-flex justify-content-between align-items-center px-3 px-md-4 border-bottom">
        <div class="d-flex align-items-center">
          <button class="btn d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Open menu">
            <i class="bi bi-list fs-4"></i>
          </button>
          <div>
            <h6 class="mb-0 fw-semibold" id="chatTitle">NumPy workspace</h6>
            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" id="onlineStatus">Checking Python API...</span>
          </div>
        </div>
      </header>

      <div class="messages-container flex-grow-1 overflow-auto p-3 p-md-4" id="messagesContainer">
        <div id="emptyState" class="empty-state numpy-workspace">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
              <span class="eyebrow">Python + NumPy</span>
              <h4 class="fw-semibold mb-1">Numerical playground</h4>
              <p class="text-muted mb-0">Send numeric values through the CodeIgniter gateway.</p>
            </div>
            <span class="api-status-dot" id="numpyStatusDot" aria-hidden="true"></span>
          </div>
          <form id="numpyForm" novalidate>
            <label class="form-label" for="numpyInput">Values</label>
            <textarea class="form-control numpy-input" id="numpyInput" rows="3" placeholder="10, 20, 30, 40, 50" required></textarea>
            <div class="row g-3 mt-1">
              <div class="col-sm-6">
                <label class="form-label" for="numpyOperation">Operation</label>
                <select class="form-select" id="numpyOperation">
                  <option value="sum">Sum</option>
                  <option value="mean">Mean</option>
                  <option value="min">Minimum</option>
                  <option value="max">Maximum</option>
                  <option value="std">Standard deviation</option>
                  <option value="sort">Sort</option>
                  <option value="filter">Filter</option>
                  <option value="reshape">Reshape</option>
                  <option value="info">Array information</option>
                  <option value="create">Create array</option>
                  <option value="abs">Absolute values</option>
                  <option value="square">Square values</option>
                </select>
              </div>
              <div class="col-sm-6 numpy-filter-fields d-none">
                <label class="form-label" for="filterValue">Filter value</label>
                <input class="form-control" id="filterValue" type="number" step="any" placeholder="30" />
              </div>
            </div>
            <div class="row g-3 mt-1 numpy-reshape-fields d-none">
              <div class="col-6"><label class="form-label" for="reshapeRows">Rows</label><input class="form-control" id="reshapeRows" type="number" min="1" /></div>
              <div class="col-6"><label class="form-label" for="reshapeCols">Columns</label><input class="form-control" id="reshapeCols" type="number" min="1" /></div>
            </div>
            <button class="btn btn-primary mt-3" id="numpySubmit" type="submit"><i class="bi bi-calculator me-1"></i> Calculate</button>
            <div class="alert alert-danger d-none mt-3 mb-0" id="numpyError" role="alert"></div>
            <div class="numpy-result d-none mt-3" id="numpyResult" aria-live="polite"></div>
          </form>
        </div>
        <div id="messageArea"></div>
        <div id="typingIndicator" class="d-none mb-3">
          <div class="message-row ai">
            <div class="avatar"><i class="bi bi-robot"></i></div>
            <div class="message-bubble">
              <span class="me-2">AI is typing</span>
              <span class="typing-dots"><span>●</span><span>●</span><span>●</span></span>
            </div>
          </div>
        </div>
      </div>

      <div class="input-area p-3 border-top">
        <div class="composer">
          <input type="file" id="fileInput" class="d-none" />
          <button class="btn btn-outline-secondary" id="attachBtn" type="button" aria-label="Attach file">
            <i class="bi bi-paperclip"></i>
          </button>
          <textarea class="form-control" id="messageInput" rows="1" placeholder="Type a message…"></textarea>
          <button class="btn btn-primary send-btn" id="sendBtn" type="button" aria-label="Send">
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
        <p class="text-center text-muted small mt-2 mb-0">Enter to send · Shift + Enter for a new line</p>
      </div>
    </main>
  </div>

  <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="offcanvasSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary">
      <h5 class="offcanvas-title" id="offcanvasSidebarLabel"><i class="bi bi-robot me-2"></i>AI Assistant</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
      <button class="btn w-100 mb-3 new-chat-btn" id="newChatBtnMobile" type="button">
        <i class="bi bi-plus-circle me-1"></i> New Chat
      </button>
      <div class="input-group input-group-sm mb-3">
        <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control form-control-sm border-start-0" placeholder="Search chats..." id="searchHistoryMobile" />
      </div>
      <div class="history-container flex-grow-1 overflow-auto" id="historyContainerMobile">
        <p class="small mb-2 text-secondary">Recent</p>
        <ul class="list-unstyled mb-0" id="historyListMobile"></ul>
      </div>
      <div class="border-top border-secondary pt-3 mt-auto">
        <div class="d-flex justify-content-between">
          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#settingsModal">
            <i class="bi bi-gear"></i> Settings
          </button>
          <button class="btn btn-outline-secondary btn-sm" id="themeToggleMobile" type="button" aria-label="Toggle theme">
            <i class="bi bi-moon-stars"></i>
          </button>
        </div>
        <div class="d-flex align-items-center mt-3">
          <i class="bi bi-person-circle fs-5 me-2"></i>
          <span class="small">Guest user</span>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Python + NumPy Settings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="settingsForm">
            <h6 class="settings-heading">Python API</h6>
            <div class="mb-3"><label class="form-label" for="apiUrl">Python API URL</label><input class="form-control" id="apiUrl" type="url" required /></div>
            <div class="row g-3">
              <div class="col-7"><label class="form-label" for="apiTimeout">Request timeout (seconds)</label><input class="form-control" id="apiTimeout" type="number" min="1" max="300" required /></div>
              <div class="col-5 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" id="pythonEnabled" type="checkbox" /><label class="form-check-label" for="pythonEnabled">Enabled</label></div></div>
            </div>
            <button class="btn btn-outline-primary btn-sm mt-2" id="healthCheckBtn" type="button"><i class="bi bi-heart-pulse me-1"></i> Test connection</button>
            <span class="small ms-2" id="settingsStatus"></span>
            <hr />
            <h6 class="settings-heading">NumPy</h6>
            <div class="row g-3">
              <div class="col-sm-6"><label class="form-label" for="defaultOperation">Default operation</label><select class="form-select" id="defaultOperation"><option value="sum">Sum</option><option value="mean">Mean</option><option value="min">Minimum</option><option value="max">Maximum</option><option value="std">Standard deviation</option><option value="sort">Sort</option><option value="filter">Filter</option><option value="reshape">Reshape</option><option value="info">Array information</option><option value="create">Create array</option><option value="abs">Absolute values</option><option value="square">Square values</option></select></div>
              <div class="col-sm-6"><label class="form-label" for="decimalPrecision">Decimal precision</label><input class="form-control" id="decimalPrecision" type="number" min="0" max="10" /></div>
              <div class="col-sm-6"><label class="form-label" for="maxInputValues">Maximum input values</label><input class="form-control" id="maxInputValues" type="number" min="1" max="100000" /></div>
              <div class="col-sm-6"><label class="form-label" for="reshapeDefaults">Default reshape</label><div class="input-group"><input class="form-control" id="defaultRows" type="number" min="1" aria-label="Default rows" /><span class="input-group-text">x</span><input class="form-control" id="defaultCols" type="number" min="1" aria-label="Default columns" /></div></div>
            </div>
            <div class="settings-checks mt-3"><label class="form-check"><input class="form-check-input" id="allowNegative" type="checkbox" /> <span class="form-check-label">Allow negative numbers</span></label><label class="form-check"><input class="form-check-input" id="allowDecimal" type="checkbox" /> <span class="form-check-label">Allow decimal numbers</span></label><label class="form-check"><input class="form-check-input" id="autoArrayConversion" type="checkbox" /> <span class="form-check-label">Automatic array conversion</span></label></div>
            <div class="alert alert-danger d-none mt-3 mb-0" id="settingsError" role="alert"></div>
            <div class="modal-footer px-0 pb-0 mt-4"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" id="saveSettingsBtn">Save settings</button></div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>window.numpySettings = <?= json_encode($settings ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script>window.numpyEndpoints = { process: "<?= site_url('numpy/process') ?>", health: "<?= site_url('numpy/health') ?>", settings: "<?= site_url('settings') ?>", settingsTest: "<?= site_url('settings/test') ?>" };</script>
  <script src="assets/js/script.js"></script>
</body>
</html>
