<?php
/**
 * Main application shell — served for all /app routes.
 * Authentication check happens client-side via /api/me.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
$base = APP_BASE; // e.g. "/GitHub-Project-Management"
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GitHub Kanban</title>
  <script>const BASE = <?= json_encode($base) ?>;</script>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <style>
    /* -----------------------------------------------------------------------
       GitHub design tokens
    ----------------------------------------------------------------------- */
    :root {
      --gh-canvas:          #ffffff;
      --gh-canvas-subtle:   #f6f8fa;
      --gh-canvas-inset:    #f0f6fc;
      --gh-border:          #d0d7de;
      --gh-border-muted:    #d8dee4;
      --gh-fg:              #1f2328;
      --gh-fg-muted:        #656d76;
      --gh-fg-subtle:       #6e7781;
      --gh-accent:          #0969da;
      --gh-accent-hover:    #0550ae;
      --gh-success:         #1a7f37;
      --gh-attention:       #9a6700;
      --gh-danger:          #d1242f;
      --gh-done:            #8250df;
      --gh-neutral:         #6e7781;
      --gh-header-bg:       #24292f;
      --gh-font:            -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans", Helvetica, Arial, sans-serif;
      --gh-font-mono:       ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
      --gh-radius:          6px;
      --gh-radius-sm:       4px;
      --gh-shadow-sm:       0 1px 0 rgba(31,35,40,.04);
      --gh-shadow-md:       0 3px 6px rgba(140,149,159,.15);
    }

    /* -----------------------------------------------------------------------
       Base
    ----------------------------------------------------------------------- */
    *, *::before, *::after { box-sizing: border-box; }

    body {
      background: var(--gh-canvas-subtle);
      color: var(--gh-fg);
      font-family: var(--gh-font);
      font-size: 14px;
      line-height: 1.5;
      min-height: 100vh;
    }

    a { color: var(--gh-accent); }
    a:hover { color: var(--gh-accent-hover); }

    #app-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }

    /* -----------------------------------------------------------------------
       Navbar
    ----------------------------------------------------------------------- */
    .navbar {
      background: var(--gh-header-bg) !important;
      border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .navbar-brand {
      font-size: 14px;
      font-weight: 600;
      color: #ffffff !important;
    }
    .breadcrumb-nav .breadcrumb {
      margin-bottom: 0;
      background: transparent;
    }
    .breadcrumb-item { color: rgba(255,255,255,.7); font-size: 13px; }

    /* -----------------------------------------------------------------------
       Buttons — GitHub-style
    ----------------------------------------------------------------------- */
    .btn {
      font-family: var(--gh-font);
      font-size: 14px;
      font-weight: 500;
      border-radius: var(--gh-radius);
      transition: background-color .1s, border-color .1s;
    }
    .btn-sm { font-size: 12px; }

    /* Primary (blue) */
    .btn-gh-primary {
      background-color: var(--gh-accent);
      border: 1px solid rgba(31,35,40,.15);
      color: #fff;
    }
    .btn-gh-primary:hover {
      background-color: var(--gh-accent-hover);
      color: #fff;
    }

    /* Default (white/gray) */
    .btn-gh-default {
      background-color: var(--gh-canvas);
      border: 1px solid var(--gh-border);
      color: var(--gh-fg);
    }
    .btn-gh-default:hover {
      background-color: var(--gh-canvas-subtle);
      border-color: var(--gh-border);
      color: var(--gh-fg);
    }

    /* Danger */
    .btn-gh-danger {
      background-color: var(--gh-canvas);
      border: 1px solid var(--gh-border);
      color: var(--gh-danger);
    }
    .btn-gh-danger:hover {
      background-color: var(--gh-danger);
      border-color: var(--gh-danger);
      color: #fff;
    }

    /* -----------------------------------------------------------------------
       List screens (repos / milestones)
    ----------------------------------------------------------------------- */
    .list-item-card {
      cursor: pointer;
      border: 1px solid var(--gh-border) !important;
      border-radius: var(--gh-radius) !important;
      background: var(--gh-canvas) !important;
      box-shadow: var(--gh-shadow-sm);
      transition: border-color .15s, box-shadow .15s;
    }
    .list-item-card:hover {
      border-color: var(--gh-accent) !important;
      box-shadow: var(--gh-shadow-md);
    }
    .card-title { color: var(--gh-fg); font-weight: 600; font-size: 14px; }
    .card-text  { color: var(--gh-fg-muted); font-size: 12px; }

    /* -----------------------------------------------------------------------
       Kanban board
    ----------------------------------------------------------------------- */
    #board {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      overflow-x: auto;
      padding-bottom: 1rem;
      min-height: calc(100vh - 130px);
    }
    .kanban-col {
      flex: 0 0 272px;
      min-width: 272px;
    }
    .kanban-col-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 12px;
      background: var(--gh-canvas-subtle);
      border: 1px solid var(--gh-border);
      border-bottom: none;
      border-radius: var(--gh-radius) var(--gh-radius) 0 0;
      font-weight: 600;
      font-size: 12px;
      color: var(--gh-fg);
    }
    /* Colored top accent line per column */
    .col-todo        .kanban-col-header { border-top: 3px solid var(--gh-neutral); }
    .col-in-progress .kanban-col-header { border-top: 3px solid var(--gh-accent); }
    .col-review      .kanban-col-header { border-top: 3px solid var(--gh-attention); }
    .col-done        .kanban-col-header { border-top: 3px solid var(--gh-success); }

    .kanban-col-body {
      min-height: 80px;
      padding: 8px;
      background: var(--gh-canvas-subtle);
      border: 1px solid var(--gh-border);
      border-top: none;
      border-radius: 0 0 var(--gh-radius) var(--gh-radius);
    }
    .kanban-col-body.drag-over {
      background: var(--gh-canvas-inset);
      border-color: var(--gh-accent);
      outline: 2px dashed var(--gh-accent);
    }

    /* Column count badge */
    .col-count-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 10px;
      background: var(--gh-border-muted);
      color: var(--gh-fg-muted);
      font-size: 11px;
      font-weight: 600;
    }

    /* Issue cards */
    .issue-card {
      background: var(--gh-canvas);
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      padding: 8px 10px;
      margin-bottom: 6px;
      cursor: grab;
      position: relative;
      transition: box-shadow .1s;
    }
    .issue-card:hover {
      box-shadow: var(--gh-shadow-md);
      border-color: var(--gh-border);
    }
    .issue-card.dragging {
      opacity: .4;
      cursor: grabbing;
    }
    .issue-card.is-closed {
      opacity: .55;
    }
    .issue-card-title {
      font-size: 13px;
      font-weight: 500;
      color: var(--gh-fg);
      line-height: 1.35;
      margin-bottom: 5px;
      padding-right: 52px; /* room for action buttons */
    }
    .issue-card-title a {
      color: var(--gh-fg);
      text-decoration: none;
    }
    .issue-card-title a:hover { color: var(--gh-accent); }
    .issue-card-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 4px;
      font-size: 11px;
      color: var(--gh-fg-muted);
    }
    .issue-num {
      font-family: var(--gh-font-mono);
      font-size: 11px;
      color: var(--gh-fg-muted);
    }
    .label-badge {
      display: inline-block;
      padding: 0 7px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 500;
      line-height: 18px;
      white-space: nowrap;
    }
    .assignee-avatar {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      border: 1px solid var(--gh-border);
      margin-left: 2px;
    }

    /* Card action buttons */
    .card-actions {
      position: absolute;
      top: 6px;
      right: 6px;
      display: none;
      gap: 2px;
    }
    .issue-card:hover .card-actions { display: flex; }
    .card-actions .btn {
      padding: 2px 5px;
      font-size: 11px;
      line-height: 18px;
      background: var(--gh-canvas);
      border: 1px solid var(--gh-border);
      color: var(--gh-fg-muted);
      border-radius: var(--gh-radius-sm);
    }
    .card-actions .btn:hover {
      background: var(--gh-canvas-subtle);
      color: var(--gh-fg);
      border-color: var(--gh-border);
    }

    /* -----------------------------------------------------------------------
       Backlog panel
    ----------------------------------------------------------------------- */
    #backlog-panel {
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      margin-bottom: 12px;
      background: var(--gh-canvas);
    }
    #backlog-toggle {
      width: 100%;
      text-align: left;
      background: var(--gh-canvas-subtle);
      border: none;
      border-radius: var(--gh-radius);
      padding: 8px 14px;
      font-weight: 600;
      font-size: 12px;
      color: var(--gh-fg);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    #backlog-toggle:hover { background: var(--gh-border-muted); }
    #backlog-body {
      padding: 8px 12px 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    .backlog-card {
      background: var(--gh-canvas-subtle);
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      padding: 5px 8px;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 6px;
      max-width: 360px;
    }
    .backlog-card-title {
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: var(--gh-fg);
    }
    .backlog-card-title a { color: var(--gh-fg); text-decoration: none; }
    .backlog-card-title a:hover { color: var(--gh-accent); }

    /* -----------------------------------------------------------------------
       Drop insertion indicator
    ----------------------------------------------------------------------- */
    .drop-indicator {
      height: 2px;
      background: var(--gh-accent);
      border-radius: 2px;
      margin: 3px 0;
      pointer-events: none;
    }

    /* -----------------------------------------------------------------------
       Milestone progress bar
    ----------------------------------------------------------------------- */
    .progress {
      border-radius: var(--gh-radius-sm);
      background: var(--gh-border-muted);
      overflow: hidden;
    }
    .progress-bar { transition: width .3s ease; }

    /* -----------------------------------------------------------------------
       Misc / screens
    ----------------------------------------------------------------------- */
    .screen { display: none; }
    .screen.active { display: block; }

    .alert-float {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      min-width: 280px;
      max-width: 420px;
    }

    /* GitHub-style toast */
    .gh-toast {
      background: var(--gh-header-bg);
      color: #ffffff;
      border: none;
      border-radius: var(--gh-radius);
      font-size: 13px;
      box-shadow: 0 8px 24px rgba(140,149,159,.2);
    }
    .gh-toast.error  { background: #a40e26; }
    .gh-toast.warning { background: #7d4e00; }
    .gh-toast .btn-close { filter: invert(1); }

    /* GitHub-style form controls */
    .form-control, .form-select {
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      font-size: 14px;
      color: var(--gh-fg);
      background: var(--gh-canvas);
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--gh-accent);
      box-shadow: 0 0 0 3px rgba(9,105,218,.1);
    }

    /* GitHub-style modals */
    .modal-content {
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      box-shadow: var(--gh-shadow-md);
    }
    .modal-header {
      border-bottom: 1px solid var(--gh-border);
      padding: 12px 16px;
      background: var(--gh-canvas-subtle);
      border-radius: var(--gh-radius) var(--gh-radius) 0 0;
    }
    .modal-title { font-size: 14px; font-weight: 600; color: var(--gh-fg); }
    .modal-footer {
      border-top: 1px solid var(--gh-border);
      padding: 12px 16px;
    }

    /* Input group search */
    .input-group-text {
      background: var(--gh-canvas);
      border-color: var(--gh-border);
    }

    /* Section heading */
    .screen-heading {
      font-size: 16px;
      font-weight: 600;
      color: var(--gh-fg);
    }
  </style>
</head>
<body>

<!-- =========================================================================
     Loading spinner
========================================================================== -->
<div id="app-loading">
  <div class="text-center text-muted">
    <div class="spinner-border mb-3" role="status"></div>
    <p>Loading…</p>
  </div>
</div>

<!-- =========================================================================
     Login screen
========================================================================== -->
<div id="screen-login" class="screen">
  <div class="d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center" style="max-width:380px;width:100%;padding:2rem">
      <svg height="48" viewBox="0 0 16 16" width="48" style="color:#1f2328;margin-bottom:1.25rem;display:block;margin-left:auto;margin-right:auto" aria-hidden="true"><path fill="currentColor" d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"/></svg>
      <h2 style="font-size:24px;font-weight:600;color:#1f2328;margin-bottom:6px">GitHub Kanban</h2>
      <p style="color:#656d76;font-size:14px;margin-bottom:24px">Manage GitHub Issues as a Kanban board, milestone by milestone.</p>
      <a href="<?= $base ?>/auth/login" class="btn btn-gh-primary btn-lg w-100" style="font-size:16px;padding:10px 20px">
        <svg height="16" viewBox="0 0 16 16" width="16" class="me-2" aria-hidden="true" style="vertical-align:text-bottom"><path fill="currentColor" d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"/></svg>Sign in with GitHub
      </a>
    </div>
  </div>
</div>

<!-- =========================================================================
     Main app wrapper (shown once authenticated)
========================================================================== -->
<div id="screen-app" class="screen">

  <!-- Navbar -->
  <nav class="navbar navbar-expand-sm px-3 py-2">
    <a class="navbar-brand" href="#" id="nav-home" style="display:flex;align-items:center;gap:8px">
      <svg height="20" viewBox="0 0 16 16" width="20" aria-hidden="true" style="color:#fff"><path fill="currentColor" d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"/></svg>
      <span>Kanban</span>
    </a>

    <div class="ms-3 breadcrumb-nav d-none d-sm-block" id="nav-breadcrumb">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item active" id="bc-repo" style="display:none"></li>
          <li class="breadcrumb-item active" id="bc-milestone" style="display:none"></li>
        </ol>
      </nav>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <span style="color:rgba(255,255,255,.7);font-size:13px" class="d-none d-sm-inline" id="nav-user-login"></span>
      <img src="" alt="" id="nav-avatar" class="rounded-circle d-none" style="width:20px;height:20px">
      <a href="<?= $base ?>/auth/logout" class="btn btn-gh-default btn-sm" style="font-size:12px;padding:4px 10px">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span class="d-none d-sm-inline ms-1">Sign out</span>
      </a>
    </div>
  </nav>

  <!-- Sub-screens -->

  <!-- Repository list -->
  <div id="sub-repos" class="sub-screen container-fluid py-4" style="display:none">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="screen-heading mb-0">Select a repository</h5>
      <div class="input-group" style="max-width:240px">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" style="color:var(--gh-fg-muted);font-size:12px"></i></span>
        <input type="text" id="repo-search" class="form-control border-start-0" placeholder="Find a repository…" style="font-size:13px">
      </div>
    </div>
    <div id="repos-loading" class="text-center text-muted py-5">
      <div class="spinner-border spinner-border-sm me-2"></div>Loading repositories…
    </div>
    <div id="repo-list" class="row g-3" style="display:none"></div>
  </div>

  <!-- Milestone list -->
  <div id="sub-milestones" class="sub-screen container-fluid py-4" style="display:none">
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
      <button class="btn btn-sm btn-gh-default" id="btn-back-repos">
        <i class="fa-solid fa-arrow-left me-1"></i>Repositories
      </button>
      <h5 class="screen-heading mb-0 me-auto" id="milestone-repo-title"></h5>
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="milestone-show-closed">
        <label class="form-check-label small" style="color:var(--gh-fg-muted);font-size:12px" for="milestone-show-closed">Show closed</label>
      </div>
      <button class="btn btn-sm btn-gh-primary" id="btn-new-milestone">
        <i class="fa-solid fa-plus me-1"></i>New milestone
      </button>
    </div>
    <div id="milestones-loading" class="text-center text-muted py-5">
      <div class="spinner-border spinner-border-sm me-2"></div>Loading milestones…
    </div>
    <div id="milestone-list" class="row g-3" style="display:none"></div>
  </div>

  <!-- Kanban board -->
  <div id="sub-board" class="sub-screen" style="display:none">
    <div class="container-fluid py-3">
      <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <button class="btn btn-sm btn-gh-default" id="btn-back-milestones">
          <i class="fa-solid fa-arrow-left me-1"></i>Milestones
        </button>
        <h5 class="screen-heading mb-0 me-auto" id="board-title"></h5>
        <button class="btn btn-sm btn-gh-default" id="btn-refresh-board">
          <i class="fa-solid fa-rotate-right me-1"></i>Refresh
        </button>
        <button class="btn btn-sm btn-gh-default" id="btn-ensure-labels">
          <i class="fa-solid fa-tags me-1"></i>Ensure labels
        </button>
      </div>

      <!-- Backlog panel -->
      <div id="backlog-panel" style="display:none">
        <button id="backlog-toggle" data-open="1">
          <i class="fa-solid fa-inbox text-muted"></i>
          <span id="backlog-title">Backlog</span>
          <i class="fa-solid fa-chevron-down ms-auto" id="backlog-chevron"></i>
        </button>
        <div id="backlog-body"></div>
      </div>

      <div id="board-loading" class="text-center text-muted py-5">
        <div class="spinner-border me-2"></div>Loading issues…
      </div>

      <div id="board" style="display:none">
        <div class="kanban-col col-todo">
          <div class="kanban-col-header">
            <span>Todo</span>
            <span class="col-count-badge col-count" data-col="todo">0</span>
          </div>
          <div class="kanban-col-body" data-col="todo"></div>
        </div>
        <div class="kanban-col col-in-progress">
          <div class="kanban-col-header">
            <span>In Progress</span>
            <span class="col-count-badge col-count" data-col="in-progress">0</span>
          </div>
          <div class="kanban-col-body" data-col="in-progress"></div>
        </div>
        <div class="kanban-col col-review">
          <div class="kanban-col-header">
            <span>In Review</span>
            <span class="col-count-badge col-count" data-col="review">0</span>
          </div>
          <div class="kanban-col-body" data-col="review"></div>
        </div>
        <div class="kanban-col col-done">
          <div class="kanban-col-header">
            <span>Done</span>
            <span class="col-count-badge col-count" data-col="done">0</span>
          </div>
          <div class="kanban-col-body" data-col="done"></div>
        </div>
      </div>
    </div>
  </div>

</div><!-- #screen-app -->

<!-- Create milestone modal -->
<div class="modal fade" id="modal-create-milestone" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fa-solid fa-flag me-2"></i>New Milestone</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
          <input type="text" id="ms-title" class="form-control" placeholder="e.g. v1.0 Release">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea id="ms-description" class="form-control" rows="2" placeholder="Optional"></textarea>
        </div>
        <div class="mb-0">
          <label class="form-label fw-semibold">Due date</label>
          <input type="date" id="ms-due-on" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-gh-default" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-gh-primary" id="btn-create-milestone-submit">
          <i class="fa-solid fa-plus me-1"></i>Create milestone
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Move-to-milestone modal -->
<div class="modal fade" id="modal-move-milestone" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Move to milestone</h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2" id="modal-milestone-list">
        <div class="text-center text-muted py-2">
          <div class="spinner-border spinner-border-sm"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Floating toast container -->
<div id="toast-container" class="alert-float" aria-live="polite"></div>

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
/* =============================================================================
   Application State
============================================================================= */
const App = {
  user:             null,
  csrfToken:        null,
  repo:             null,       // { full_name, name, owner }
  milestone:        null,       // { number, title }
  repoMilestones:   [],         // all milestones for current repo (for move picker)
  issues:           [],
  movingIssueNumber: null,      // issue being moved in the modal
};

/* =============================================================================
   Utilities
============================================================================= */

/** Show a transient toast message */
function toast(msg, type = 'success') {
  const id  = 'toast-' + Date.now();
  const el  = $(`
    <div id="${id}" class="alert gh-toast ${type === 'error' ? 'error' : type === 'warning' ? 'warning' : ''} alert-dismissible" role="alert" style="font-size:13px;padding:10px 14px">
      ${msg}
      <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
  `);
  $('#toast-container').append(el);
  setTimeout(() => { el.alert('close'); }, 4000);
}

/** POST to an API endpoint with CSRF header */
function apiPost(url, data) {
  return $.ajax({
    url,
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(data),
    headers: { 'X-CSRF-Token': App.csrfToken },
  });
}

/** GET an API endpoint */
function apiGet(url, params = {}) {
  return $.getJSON(url, params);
}

/** Darken/lighten a hex colour for contrast text */
function labelTextColor(hex) {
  const r = parseInt(hex.slice(0,2), 16);
  const g = parseInt(hex.slice(2,4), 16);
  const b = parseInt(hex.slice(4,6), 16);
  const luminance = (0.299*r + 0.587*g + 0.114*b) / 255;
  return luminance > 0.5 ? '#000' : '#fff';
}

/* =============================================================================
   Screen management
============================================================================= */

function showScreen(id) {
  $('.screen').removeClass('active').hide();
  $('#' + id).addClass('active').show();
  $('[id^=sub-]').hide();
}

function showSub(id) {
  $('[id^=sub-]').hide();
  $('#' + id).show();
}

/* =============================================================================
   Initialisation — check authentication
============================================================================= */

$(function () {
  apiGet(BASE + '/api/me')
    .done(function (me) {
      App.user      = me;
      App.csrfToken = me.csrf_token;

      $('#nav-user-login').text(me.login).removeClass('d-none');
      $('#app-loading').hide();
      showScreen('screen-app');

      // Restore view from current URL (handles page refresh / deep links)
      const prefix = BASE + '/app';
      const path   = window.location.pathname;
      const rest   = path.startsWith(prefix) ? path.slice(prefix.length).replace(/^\/+/, '') : '';
      const parts  = rest ? rest.split('/') : [];

      if (parts.length >= 3 && /^\d+$/.test(parts[2])) {
        // /app/owner/name/milestone-number → board
        App.repo = { full_name: parts[0] + '/' + parts[1], owner: parts[0], name: parts[1] };
        const msNum = parseInt(parts[2], 10);
        apiGet(BASE + '/api/milestones', { repo: App.repo.full_name, state: 'all' })
          .done(function (milestones) {
            App.repoMilestones = milestones;
            const ms = milestones.find(m => m.number === msNum)
                    || { number: msNum, title: 'Milestone #' + msNum };
            App.milestone = ms;
            history.replaceState(
              { view: 'board', repo: App.repo.full_name, milestone: ms.number, milestoneTitle: ms.title },
              '', path
            );
            updateBreadcrumb(App.repo, ms);
            loadBoard();
          })
          .fail(function () {
            App.milestone = { number: msNum, title: '#' + msNum };
            updateBreadcrumb(App.repo, App.milestone);
            loadBoard();
          });
      } else if (parts.length >= 2 && parts[0] && parts[1]) {
        // /app/owner/name → milestones
        App.repo = { full_name: parts[0] + '/' + parts[1], owner: parts[0], name: parts[1] };
        history.replaceState({ view: 'milestones', repo: App.repo.full_name }, '', path);
        updateBreadcrumb(App.repo, null);
        loadMilestones();
      } else {
        history.replaceState({ view: 'repos' }, '', BASE + '/app');
        loadRepos();
      }
    })
    .fail(function () {
      $('#app-loading').hide();
      showScreen('screen-login');
    });
});

/* Browser back / forward */
window.addEventListener('popstate', function (e) {
  const s = e.state || {};
  if (s.view === 'board') {
    const [owner, name] = s.repo.split('/');
    App.repo      = { full_name: s.repo, owner, name };
    App.milestone = { number: s.milestone, title: s.milestoneTitle || ('#' + s.milestone) };
    updateBreadcrumb(App.repo, App.milestone);
    loadBoard();
  } else if (s.view === 'milestones') {
    const [owner, name] = s.repo.split('/');
    App.repo = { full_name: s.repo, owner, name };
    updateBreadcrumb(App.repo, null);
    loadMilestones();
  } else {
    updateBreadcrumb(null, null);
    loadRepos();
  }
});

/* =============================================================================
   Repository list
============================================================================= */

let allRepos = [];

function loadRepos() {
  showSub('sub-repos');
  updateBreadcrumb(null, null);

  $('#repos-loading').show();
  $('#repo-list').hide().empty();

  apiGet(BASE + '/api/repos')
    .done(function (repos) {
      allRepos = repos;
      renderRepos(repos);
      $('#repos-loading').hide();
      $('#repo-list').show();
    })
    .fail(function (xhr) {
      $('#repos-loading').html(
        '<div class="alert alert-danger">Failed to load repositories: ' +
        (xhr.responseJSON?.error ?? 'Unknown error') + '</div>'
      );
    });
}

function renderRepos(repos) {
  const $list = $('#repo-list').empty();

  if (!repos.length) {
    $list.html('<p class="text-muted">No repositories found.</p>');
    return;
  }

  repos.forEach(function (r) {
    const icon  = r.private ? 'fa-lock' : 'fa-code-branch';
    const $card = $(`
      <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="card list-item-card h-100" data-full-name="${r.full_name}">
          <div class="card-body" style="padding:14px 16px">
            <div style="font-size:14px;font-weight:600;color:var(--gh-accent);margin-bottom:2px">
              <i class="fa-solid ${icon} me-1" style="color:var(--gh-fg-muted);font-size:12px"></i>${escHtml(r.name)}
            </div>
            <div style="font-size:12px;color:var(--gh-fg-muted);margin-bottom:4px">${escHtml(r.owner)}</div>
            ${r.description ? `<div style="font-size:12px;color:var(--gh-fg-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(r.description)}</div>` : ''}
          </div>
        </div>
      </div>
    `);
    $card.find('.card').on('click', function () {
      selectRepo(r);
    });
    $list.append($card);
  });
}

$('#repo-search').on('input', function () {
  const q = $(this).val().toLowerCase().trim();
  if (!q) { renderRepos(allRepos); return; }
  renderRepos(allRepos.filter(r =>
    r.full_name.toLowerCase().includes(q) ||
    (r.description || '').toLowerCase().includes(q)
  ));
});

/* =============================================================================
   Milestone list
============================================================================= */

function selectRepo(repo) {
  App.repo = repo;
  history.pushState(
    { view: 'milestones', repo: repo.full_name },
    '',
    BASE + '/app/' + repo.full_name
  );
  updateBreadcrumb(repo, null);
  loadMilestones();
}

function loadMilestones(showClosed = false) {
  showSub('sub-milestones');
  $('#milestone-repo-title').text(App.repo.full_name);

  $('#milestones-loading').show();
  $('#milestone-list').hide().empty();

  const state = showClosed ? 'all' : 'open';

  apiGet(BASE + '/api/milestones', { repo: App.repo.full_name, state })
    .done(function (milestones) {
      App.repoMilestones = milestones;
      renderMilestones(milestones);
      $('#milestones-loading').hide();
      $('#milestone-list').show();
    })
    .fail(function (xhr) {
      $('#milestones-loading').html(
        '<div class="alert alert-danger">Failed to load milestones: ' +
        (xhr.responseJSON?.error ?? 'Unknown error') + '</div>'
      );
    });
}

function renderMilestones(milestones) {
  const $list = $('#milestone-list').empty();

  if (!milestones.length) {
    $list.html('<div class="col-12"><p class="text-muted">No milestones found. ' +
      'Use the <strong>New Milestone</strong> button to create one.</p></div>');
    return;
  }

  milestones.forEach(function (m) {
    const total      = m.open_issues + m.closed_issues;
    const due        = m.due_on ? new Date(m.due_on).toLocaleDateString() : null;
    const dueBadge   = due
      ? `<span style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:var(--gh-fg-muted);margin-left:6px"><i class="fa-regular fa-calendar"></i>${escHtml(due)}</span>`
      : '';
    const stateBadge = m.state === 'closed'
      ? '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:500;background:#6e7781;color:#fff;margin-left:6px">closed</span>'
      : '';

    const $col = $(`
      <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="card list-item-card h-100" data-milestone="${m.number}">
          <div class="card-body d-flex flex-column">
            <div style="font-size:14px;font-weight:600;color:var(--gh-fg);margin-bottom:4px;display:flex;align-items:baseline;flex-wrap:wrap;gap:2px">
              ${escHtml(m.title)}${stateBadge}
            </div>
            ${m.description ? `<p class="card-text mb-2 text-truncate" style="font-size:12px">${escHtml(m.description)}</p>` : '<div class="mb-2"></div>'}
            <div class="mt-auto">
              <!-- Progress bar: starts as simple open/closed, updated by stats -->
              <div class="progress mb-1" style="height:6px" data-ms="${m.number}" title="Loading…">
                <div class="progress-bar" style="width:${total > 0 ? Math.round((m.open_issues / total) * 100) : 100}%;background:#d0d7de"></div>
                <div class="progress-bar" style="width:${total > 0 ? Math.round((m.closed_issues / total) * 100) : 0}%;background:#1a7f37"></div>
              </div>
              <!-- Stats text: updated once stats load -->
              <p class="mb-0 ms-stats" style="font-size:11px;color:var(--gh-fg-muted)" data-ms="${m.number}">
                <span class="spinner-border spinner-border-sm me-1" style="width:.65rem;height:.65rem;border-width:1px"></span>
                ${total} issue${total !== 1 ? 's' : ''} total${due ? ` &bull; due ${escHtml(due)}` : ''}
              </p>
            </div>
          </div>
        </div>
      </div>
    `);

    // Open the board on click (but not on badge/button clicks)
    $col.find('.card').on('click', function () { selectMilestone(m); });
    $list.append($col);

    // Fetch detailed stats asynchronously — update bar and text when ready
    loadMilestoneStats(m.number, total);
  });
}

function loadMilestoneStats(milestoneNumber, total) {
  apiGet(BASE + '/api/milestone_stats', { repo: App.repo.full_name, milestone: milestoneNumber })
    .done(function (s) {
      const t = s.total || 0;
      if (t === 0) {
        $(`.ms-stats[data-ms="${milestoneNumber}"]`).text('No issues yet');
        return;
      }
      const pct = v => t > 0 ? (v / t * 100).toFixed(1) : 0;

      // Segmented bar: todo (gray) / in-progress (blue) / review (orange) / done (green)
      const $bar = $(`.progress[data-ms="${milestoneNumber}"]`);
      $bar.attr('title',
        `Todo: ${s.todo} · In Progress: ${s.in_progress} · Review: ${s.review} · Done: ${s.done}`
      ).html(`
        <div class="progress-bar" style="width:${pct(s.todo)}%;background:#d0d7de"       title="Todo: ${s.todo}"></div>
        <div class="progress-bar" style="width:${pct(s.in_progress)}%;background:#0969da" title="In Progress: ${s.in_progress}"></div>
        <div class="progress-bar" style="width:${pct(s.review)}%;background:#bf8700"     title="Review: ${s.review}"></div>
        <div class="progress-bar" style="width:${pct(s.done)}%;background:#1a7f37"       title="Done: ${s.done}"></div>
      `);

      const doneOf = `${s.done}/${t} done`;
      const parts  = [];
      if (s.in_progress) parts.push(`${s.in_progress} in progress`);
      if (s.review)      parts.push(`${s.review} in review`);
      if (s.todo)        parts.push(`${s.todo} todo`);

      $(`.ms-stats[data-ms="${milestoneNumber}"]`).html(
        `<strong>${doneOf}</strong>${parts.length ? ' &bull; ' + parts.join(' &bull; ') : ''}`
      );
    })
    .fail(function () {
      // On failure just leave the basic bar in place, remove the spinner
      $(`.ms-stats[data-ms="${milestoneNumber}"] .spinner-border`).remove();
    });
}

$('#milestone-show-closed').on('change', function () {
  loadMilestones($(this).is(':checked'));
});

$('#btn-back-repos').on('click', function () {
  history.pushState({ view: 'repos' }, '', BASE + '/app');
  loadRepos();
});

/* -----------------------------------------------------------------------
   Create milestone
----------------------------------------------------------------------- */

const createMilestoneModal = new bootstrap.Modal(document.getElementById('modal-create-milestone'));

$('#btn-new-milestone').on('click', function () {
  $('#ms-title').val('');
  $('#ms-description').val('');
  $('#ms-due-on').val('');
  createMilestoneModal.show();
  setTimeout(() => $('#ms-title').trigger('focus'), 300);
});

$('#btn-create-milestone-submit').on('click', function () {
  const title = $('#ms-title').val().trim();
  if (!title) {
    $('#ms-title').addClass('is-invalid').trigger('focus');
    return;
  }
  $('#ms-title').removeClass('is-invalid');

  const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>Creating…');

  apiPost(BASE + '/api/milestone_create', {
    repo:        App.repo.full_name,
    title,
    description: $('#ms-description').val().trim(),
    due_on:      $('#ms-due-on').val(),
  })
  .done(function (res) {
    createMilestoneModal.hide();
    toast(`Milestone "${res.milestone.title}" created.`);
    loadMilestones($('#milestone-show-closed').is(':checked'));
  })
  .fail(function (xhr) {
    toast('Failed: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
  })
  .always(function () {
    $btn.prop('disabled', false).html('<i class="fa-solid fa-plus me-1"></i>Create');
  });
});

// Allow Enter key to submit the modal form
$('#modal-create-milestone').on('keydown', function (e) {
  if (e.key === 'Enter' && !$(e.target).is('textarea')) {
    $('#btn-create-milestone-submit').trigger('click');
  }
});

/* =============================================================================
   Kanban board
============================================================================= */

function selectMilestone(milestone) {
  App.milestone = milestone;
  history.pushState(
    { view: 'board', repo: App.repo.full_name, milestone: milestone.number, milestoneTitle: milestone.title },
    '',
    BASE + '/app/' + App.repo.full_name + '/' + milestone.number
  );
  updateBreadcrumb(App.repo, milestone);
  loadBoard();
}

function loadBoard() {
  showSub('sub-board');
  $('#board-title').text(App.repo.full_name + ' / ' + App.milestone.title);
  $('#board-loading').show();
  $('#board').hide();
  $('#backlog-panel').hide();

  // If milestones weren't loaded yet (deep-linked), fetch them for the move modal
  if (!App.repoMilestones.length) {
    apiGet(BASE + '/api/milestones', { repo: App.repo.full_name, state: 'open' })
      .done(ms => { App.repoMilestones = ms; });
  }

  const boardReq   = apiGet(BASE + '/api/issues', { repo: App.repo.full_name, milestone: App.milestone.number });
  const backlogReq = apiGet(BASE + '/api/issues', { repo: App.repo.full_name, milestone: 'none' });

  $.when(boardReq, backlogReq)
    .done(function (boardData, backlogData) {
      const issues  = boardData[0];
      const backlog = backlogData[0];
      App.issues = issues;
      renderBoard(issues);
      renderBacklog(backlog);
      $('#board-loading').hide();
      $('#board').show();
    })
    .fail(function (xhr) {
      $('#board-loading').html(
        '<div class="alert alert-danger">Failed to load issues: ' +
        (xhr.responseJSON?.error ?? 'Unknown error') + '</div>'
      );
    });
}

function renderBoard(issues) {
  // Clear columns
  $('.kanban-col-body').empty();
  $('.col-count').text('0');

  const cols = { todo: [], 'in-progress': [], review: [], done: [] };

  issues.forEach(function (issue) {
    const col = cols[issue.status] ? issue.status : 'todo';
    cols[col].push(issue);
  });

  Object.entries(cols).forEach(function ([col, colIssues]) {
    const $body = $(`.kanban-col-body[data-col="${col}"]`);
    $(`.col-count[data-col="${col}"]`).text(colIssues.length);
    colIssues.forEach(function (issue) {
      $body.append(renderCard(issue));
    });
  });
}

/* -----------------------------------------------------------------------
   Backlog panel
----------------------------------------------------------------------- */

function renderBacklog(issues) {
  const $body = $('#backlog-body').empty();

  if (!issues.length) {
    $('#backlog-panel').hide();
    return;
  }

  $('#backlog-title').text(`Backlog (${issues.length} unassigned issue${issues.length !== 1 ? 's' : ''})`);
  $('#backlog-panel').show();

  issues.forEach(function (issue) {
    const $card = $(`
      <div class="backlog-card" data-number="${issue.number}">
        <span class="issue-num">#${issue.number}</span>
        <span class="backlog-card-title">
          <a href="${escHtml(issue.html_url)}" target="_blank">${escHtml(issue.title)}</a>
        </span>
        <button class="btn btn-sm btn-gh-primary btn-add-to-board"
                style="font-size:11px;padding:2px 8px;white-space:nowrap"
                title="Add to ${escHtml(App.milestone.title)}"
                data-number="${issue.number}">
          <i class="fa-solid fa-plus me-1"></i>Add to board
        </button>
      </div>
    `);
    $body.append($card);
  });
}

// Toggle backlog collapse
$('#backlog-toggle').on('click', function () {
  const open = $(this).data('open');
  $('#backlog-body').toggle(!open);
  $(this).data('open', open ? 0 : 1);
  $('#backlog-chevron').toggleClass('fa-chevron-down fa-chevron-right');
});

// "Add to board" — assign backlog issue to current milestone → top of TODO
$(document).on('click', '.btn-add-to-board', function (e) {
  e.stopPropagation();
  const $btn   = $(this).prop('disabled', true);
  const number = parseInt($(this).data('number'), 10);

  apiPost(BASE + '/api/issue_update', {
    repo:         App.repo.full_name,
    issue_number: number,
    action:       'assign_milestone',
    milestone:    App.milestone.number,
  })
  .done(function () {
    toast(`Issue #${number} added to ${App.milestone.title}.`);
    loadBoard(); // reload to reflect new card at top of TODO
  })
  .fail(function (xhr) {
    $btn.prop('disabled', false);
    toast('Failed: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
  });
});

/* -----------------------------------------------------------------------
   Board card rendering
----------------------------------------------------------------------- */

function renderCard(issue) {
  const labels = issue.labels
    .filter(l => !l.name.startsWith('status:'))
    .map(l => {
      const bg  = '#' + l.color;
      const fg  = labelTextColor(l.color);
      return `<span class="label-badge" style="background:${bg};color:${fg}">${escHtml(l.name)}</span>`;
    }).join('');

  const assignees = issue.assignees
    .map(a => `<img src="${escHtml(a.avatar_url)}&s=40" alt="${escHtml(a.login)}" class="assignee-avatar" title="${escHtml(a.login)}">`)
    .join('');

  const closedClass = issue.state === 'closed' ? ' is-closed' : '';
  const closeBtn = issue.state === 'open'
    ? `<button class="btn" data-action="close"  title="Close issue"><i class="fa-solid fa-xmark"></i></button>`
    : `<button class="btn" data-action="reopen" title="Reopen issue" style="color:var(--gh-success)"><i class="fa-solid fa-rotate-left"></i></button>`;

  return $(`
    <div class="issue-card${closedClass}"
         draggable="true"
         data-number="${issue.number}"
         data-status="${escHtml(issue.status)}">
      <div class="card-actions">
        ${closeBtn}
        <button class="btn" data-action="move_milestone"
                title="Move to another milestone"><i class="fa-solid fa-right-left"></i></button>
        <button class="btn" data-action="remove_milestone"
                title="Send back to backlog"><i class="fa-solid fa-inbox"></i></button>
      </div>
      <div class="issue-card-title">
        <a href="${escHtml(issue.html_url)}" target="_blank" title="Open on GitHub">${escHtml(issue.title)}</a>
      </div>
      <div class="issue-card-meta">
        <span class="issue-num">#${issue.number}</span>
        ${labels}
        <span class="ms-auto">${assignees}</span>
      </div>
    </div>
  `);
}

/* --- Card action buttons ------------------------------------------------- */

$(document).on('click', '.card-actions button', function (e) {
  e.stopPropagation();
  const $card  = $(this).closest('.issue-card');
  const number = parseInt($card.data('number'), 10);
  const action = $(this).data('action');

  if (action === 'move_milestone') {
    openMoveMilestoneModal(number);
    return;
  }

  if (action === 'remove_milestone') {
    if (!confirm(`Remove #${number} from "${App.milestone.title}" and send to backlog?`)) return;
    apiPost(BASE + '/api/issue_update', {
      repo: App.repo.full_name, issue_number: number, action: 'remove_milestone',
    })
    .done(function () {
      toast(`Issue #${number} sent to backlog.`);
      loadBoard();
    })
    .fail(function (xhr) {
      toast('Failed: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
    });
    return;
  }

  // close / reopen
  apiPost(BASE + '/api/issue_update', {
    repo: App.repo.full_name, issue_number: number, action,
  })
  .done(function (res) {
    const idx = App.issues.findIndex(i => i.number === number);
    if (idx !== -1) { App.issues[idx].state = res.issue.state; }
    toast(action === 'close' ? `Issue #${number} closed.` : `Issue #${number} reopened.`);
    $card.toggleClass('is-closed', res.issue.state === 'closed');
    $card.find('[data-action=close],[data-action=reopen]').replaceWith(
      res.issue.state === 'open'
        ? `<button class="btn" data-action="close"  title="Close issue"><i class="fa-solid fa-xmark"></i></button>`
        : `<button class="btn" data-action="reopen" title="Reopen issue" style="color:var(--gh-success)"><i class="fa-solid fa-rotate-left"></i></button>`
    );
  })
  .fail(function (xhr) {
    toast('Error: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
  });
});

/* -----------------------------------------------------------------------
   Move-to-milestone modal
----------------------------------------------------------------------- */

const moveMilestoneModal = new bootstrap.Modal(document.getElementById('modal-move-milestone'));

function openMoveMilestoneModal(issueNumber) {
  App.movingIssueNumber = issueNumber;
  const $list = $('#modal-milestone-list').empty();

  const others = App.repoMilestones.filter(m => m.number !== App.milestone.number);
  if (!others.length) {
    $list.html('<p class="text-muted small p-2 mb-0">No other open milestones found.</p>');
    moveMilestoneModal.show();
    return;
  }

  others.forEach(function (m) {
    const $btn = $(`
      <button class="btn btn-gh-default btn-sm w-100 text-start mb-1"
              style="font-size:13px"
              data-milestone="${m.number}">
        <i class="fa-solid fa-flag me-1" style="color:var(--gh-fg-muted)"></i>${escHtml(m.title)}
      </button>
    `);
    $btn.on('click', function () {
      moveMilestoneModal.hide();
      const target = parseInt($(this).data('milestone'), 10);
      apiPost(BASE + '/api/issue_update', {
        repo:         App.repo.full_name,
        issue_number: App.movingIssueNumber,
        action:       'move_milestone',
        milestone:    target,
      })
      .done(function (res) {
        const targetTitle = others.find(m => m.number === target)?.title ?? `#${target}`;
        toast(`Issue #${App.movingIssueNumber} moved to "${targetTitle}".`);
        loadBoard();
      })
      .fail(function (xhr) {
        toast('Failed: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
      });
    });
    $list.append($btn);
  });

  moveMilestoneModal.show();
}

/* =============================================================================
   Drag and Drop
   Supports:
   - Moving cards between columns (updates status:* label on GitHub)
   - Reordering cards within a column (saves position to local DB)
   An insertion indicator line shows exactly where the card will land.
============================================================================= */

let draggedCard    = null;
let dragOriginCol  = null;

// The placeholder line shown between cards during drag
const $dropIndicator = $('<div class="drop-indicator"></div>');

$(document).on('dragstart', '.issue-card', function (e) {
  draggedCard   = this;
  dragOriginCol = $(this).closest('.kanban-col-body').data('col');
  $(this).addClass('dragging');
  e.originalEvent.dataTransfer.effectAllowed = 'move';
  e.originalEvent.dataTransfer.setData('text/plain', $(this).data('number'));
});

$(document).on('dragend', '.issue-card', function () {
  $(this).removeClass('dragging');
  $dropIndicator.detach();
  $('.kanban-col-body').removeClass('drag-over');
  draggedCard   = null;
  dragOriginCol = null;
});

$(document).on('dragover', '.kanban-col-body', function (e) {
  e.preventDefault();
  e.originalEvent.dataTransfer.dropEffect = 'move';
  $(this).addClass('drag-over');

  // Find the card the cursor is over (excluding the dragged card itself)
  const $col   = $(this);
  const mouseY = e.originalEvent.clientY;
  let $after   = null; // card to insert before (null = append to end)

  $col.find('.issue-card:not(.dragging)').each(function () {
    const rect   = this.getBoundingClientRect();
    const middle = rect.top + rect.height / 2;
    if (mouseY < middle) {
      $after = $(this);
      return false; // break
    }
  });

  // Position the indicator
  if ($after) {
    $after.before($dropIndicator);
  } else {
    $col.append($dropIndicator);
  }
});

$(document).on('dragleave', '.kanban-col-body', function (e) {
  if (!$(this).is($(e.relatedTarget).closest('.kanban-col-body'))) {
    $(this).removeClass('drag-over');
    $dropIndicator.detach();
  }
});

$(document).on('drop', '.kanban-col-body', function (e) {
  e.preventDefault();
  $(this).removeClass('drag-over');

  if (!draggedCard) { return; }

  const $col     = $(this);
  const newCol   = $col.data('col');
  const $card    = $(draggedCard);
  const prevCol  = $card.data('status');
  const number   = parseInt($card.data('number'), 10);

  // Insert card at the indicator position, then remove the indicator
  $dropIndicator.replaceWith($card);
  $card.data('status', newCol);
  updateColCounts();

  const saveOrder = () => saveColumnOrder();

  if (newCol === prevCol) {
    // Same-column reorder — only save positions
    saveOrder();
    return;
  }

  // Cross-column move — update label on GitHub, then save positions
  apiPost(BASE + '/api/issue_update', {
    repo:         App.repo.full_name,
    issue_number: number,
    action:       'move',
    column:       newCol,
  })
  .done(function () {
    const idx = App.issues.findIndex(i => i.number === number);
    if (idx !== -1) { App.issues[idx].status = newCol; }
    toast(`Moved #${number} to ${colLabel(newCol)}`);
    saveOrder();
  })
  .fail(function (xhr) {
    // Revert: put card back at the end of its original column
    $(`.kanban-col-body[data-col="${prevCol}"]`).append($card);
    $card.data('status', prevCol);
    updateColCounts();
    toast('Failed to move issue: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
  });
});

/**
 * Collect the current DOM order of all cards across the board and persist
 * it via /api/issue_order. Optionally pass two column IDs when a card moved
 * between columns (both need their order saved).
 */
function saveColumnOrder() {
  const numbers = [];
  document.querySelectorAll('.kanban-col-body').forEach(body => {
    body.querySelectorAll('.issue-card').forEach(card => {
      numbers.push(parseInt(card.dataset.number, 10));
    });
  });

  apiPost(BASE + '/api/issue_order', {
    repo:      App.repo.full_name,
    milestone: App.milestone.number,
    numbers,
  }).fail(function () {
    toast('Could not save card order.', 'warning');
  });
}

function updateColCounts() {
  document.querySelectorAll('.kanban-col-body').forEach(function (body) {
    const col   = body.dataset.col;
    const count = body.querySelectorAll('.issue-card').length;
    document.querySelector(`.col-count[data-col="${col}"]`).textContent = count;
  });
}

function colLabel(col) {
  return { todo: 'Todo', 'in-progress': 'In Progress', review: 'Review', done: 'Done' }[col] ?? col;
}

/* =============================================================================
   Toolbar buttons
============================================================================= */

$('#nav-home').on('click', function (e) {
  e.preventDefault();
  history.pushState({ view: 'repos' }, '', BASE + '/app');
  loadRepos();
});
$('#btn-back-milestones').on('click', function () {
  history.pushState({ view: 'milestones', repo: App.repo.full_name }, '', BASE + '/app/' + App.repo.full_name);
  loadMilestones();
});
$('#btn-refresh-board').on('click', function () { loadBoard(); });

$('#btn-ensure-labels').on('click', function () {
  const STATUS_LABELS = [
    { name: 'status:todo',        color: 'd0d7de' },
    { name: 'status:in-progress', color: 'ddf4ff' },
    { name: 'status:review',      color: 'fff8c5' },
    { name: 'status:done',        color: 'dafbe1' },
  ];

  let pending = STATUS_LABELS.length;
  let created = 0;
  let errors  = 0;

  const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>Working…');

  STATUS_LABELS.forEach(function (lbl) {
    apiPost(BASE + '/api/issue_update', {
      repo:   App.repo.full_name,
      action: 'ensure_label',
      label:  lbl.name,
      color:  lbl.color,
    })
    .done(function (res) {
      if (res.created) { created++; }
    })
    .fail(function () { errors++; })
    .always(function () {
      pending--;
      if (pending === 0) {
        $btn.prop('disabled', false).html('<i class="fa-solid fa-tags me-1"></i>Ensure Labels');
        if (errors) {
          toast(`${created} label(s) created, ${errors} error(s).`, 'warning');
        } else {
          toast(`Status labels ready (${created} created).`);
        }
      }
    });
  });
});

/* =============================================================================
   Breadcrumb
============================================================================= */

function updateBreadcrumb(repo, milestone) {
  if (!repo) {
    $('#bc-repo, #bc-milestone').hide().text('');
    return;
  }
  $('#bc-repo').text(repo.full_name).show();
  if (milestone) {
    $('#bc-milestone').text(milestone.title).show();
  } else {
    $('#bc-milestone').hide().text('');
  }
}

/* =============================================================================
   XSS guard
============================================================================= */
function escHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
</script>
</body>
</html>
