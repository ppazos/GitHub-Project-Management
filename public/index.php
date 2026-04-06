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
       Layout
    ----------------------------------------------------------------------- */
    body {
      background: #f6f8fa;
      min-height: 100vh;
    }
    #app-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }

    /* -----------------------------------------------------------------------
       Navbar
    ----------------------------------------------------------------------- */
    .navbar-brand img {
      width: 28px;
      height: 28px;
      border-radius: 50%;
    }
    .breadcrumb-nav .breadcrumb {
      margin-bottom: 0;
      background: transparent;
    }

    /* -----------------------------------------------------------------------
       List screens (repos / milestones)
    ----------------------------------------------------------------------- */
    .list-item-card {
      cursor: pointer;
      transition: box-shadow .15s, transform .1s;
    }
    .list-item-card:hover {
      box-shadow: 0 4px 16px rgba(0,0,0,.12);
      transform: translateY(-1px);
    }

    /* -----------------------------------------------------------------------
       Kanban board
    ----------------------------------------------------------------------- */
    #board {
      display: flex;
      gap: 1rem;
      align-items: flex-start;
      overflow-x: auto;
      padding-bottom: 1rem;
      min-height: calc(100vh - 130px);
    }
    .kanban-col {
      flex: 0 0 280px;
      min-width: 280px;
    }
    .kanban-col-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .5rem .75rem;
      border-radius: .5rem .5rem 0 0;
      font-weight: 600;
      font-size: .85rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .col-todo        .kanban-col-header { background: #e9ecef; color: #495057; }
    .col-in-progress .kanban-col-header { background: #cfe2ff; color: #084298; }
    .col-review      .kanban-col-header { background: #fff3cd; color: #856404; }
    .col-done        .kanban-col-header { background: #d1e7dd; color: #0a3622; }

    .kanban-col-body {
      min-height: 80px;
      padding: .5rem;
      background: #fff;
      border: 1px solid #dee2e6;
      border-top: none;
      border-radius: 0 0 .5rem .5rem;
    }
    .kanban-col-body.drag-over {
      background: #f0f7ff;
      border-color: #0d6efd;
      outline: 2px dashed #0d6efd;
    }

    /* Issue cards */
    .issue-card {
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: .4rem;
      padding: .6rem .75rem;
      margin-bottom: .5rem;
      cursor: grab;
      position: relative;
      transition: box-shadow .12s;
    }
    .issue-card:hover {
      box-shadow: 0 2px 10px rgba(0,0,0,.1);
    }
    .issue-card.dragging {
      opacity: .45;
      cursor: grabbing;
    }
    .issue-card.is-closed {
      opacity: .6;
    }
    .issue-card-title {
      font-size: .88rem;
      font-weight: 500;
      color: #24292f;
      line-height: 1.35;
      margin-bottom: .35rem;
    }
    .issue-card-title a {
      color: inherit;
      text-decoration: none;
    }
    .issue-card-title a:hover {
      color: #0d6efd;
    }
    .issue-card-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: .25rem;
      font-size: .76rem;
      color: #6c757d;
    }
    .issue-num {
      font-family: monospace;
      color: #6c757d;
    }
    .label-badge {
      display: inline-block;
      padding: .15em .5em;
      border-radius: 1em;
      font-size: .72rem;
      font-weight: 500;
      line-height: 1.4;
      white-space: nowrap;
    }
    .assignee-avatar {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      border: 1.5px solid #dee2e6;
      margin-left: 2px;
    }

    /* Card action buttons */
    .card-actions {
      position: absolute;
      top: .4rem;
      right: .4rem;
      display: none;
      gap: .2rem;
    }
    .issue-card:hover .card-actions {
      display: flex;
    }
    .card-actions .btn {
      padding: .1rem .3rem;
      font-size: .7rem;
      line-height: 1.2;
    }

    /* Drop insertion indicator */
    .drop-indicator {
      height: 3px;
      background: #0d6efd;
      border-radius: 2px;
      margin: 2px 0;
      pointer-events: none;
    }

    /* -----------------------------------------------------------------------
       Misc
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
      <i class="fa-brands fa-github" style="font-size:3.5rem;color:#24292f;margin-bottom:1rem"></i>
      <h2 class="fw-bold mb-1">GitHub Kanban</h2>
      <p class="text-muted mb-4">Manage your GitHub Issues as a Kanban board, milestone by milestone.</p>
      <a href="<?= $base ?>/auth/login" class="btn btn-dark btn-lg w-100">
        <i class="fa-brands fa-github me-2"></i>Login with GitHub
      </a>
    </div>
  </div>
</div>

<!-- =========================================================================
     Main app wrapper (shown once authenticated)
========================================================================== -->
<div id="screen-app" class="screen">

  <!-- Navbar -->
  <nav class="navbar navbar-expand-sm navbar-dark bg-dark px-3 py-2">
    <a class="navbar-brand fw-bold" href="#" id="nav-home">
      <i class="fa-brands fa-github me-1"></i>Kanban
    </a>

    <div class="ms-3 breadcrumb-nav text-white-50 d-none d-sm-block" id="nav-breadcrumb">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item active" id="bc-repo" style="display:none"></li>
          <li class="breadcrumb-item active" id="bc-milestone" style="display:none"></li>
        </ol>
      </nav>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="text-white-50 small d-none d-sm-inline" id="nav-user-login"></span>
      <img src="" alt="" id="nav-avatar" class="rounded-circle d-none" style="width:28px;height:28px">
      <a href="<?= $base ?>/auth/logout" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span class="d-none d-sm-inline ms-1">Logout</span>
      </a>
    </div>
  </nav>

  <!-- Sub-screens -->

  <!-- Repository list -->
  <div id="sub-repos" class="sub-screen container-fluid py-4" style="display:none">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-semibold mb-0"><i class="fa-solid fa-code-branch me-2 text-muted"></i>Select a Repository</h5>
      <div class="input-group" style="max-width:260px">
        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
        <input type="text" id="repo-search" class="form-control border-start-0" placeholder="Filter repos…">
      </div>
    </div>
    <div id="repos-loading" class="text-center text-muted py-5">
      <div class="spinner-border spinner-border-sm me-2"></div>Loading repositories…
    </div>
    <div id="repo-list" class="row g-3" style="display:none"></div>
  </div>

  <!-- Milestone list -->
  <div id="sub-milestones" class="sub-screen container-fluid py-4" style="display:none">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <button class="btn btn-sm btn-outline-secondary me-2" id="btn-back-repos">
          <i class="fa-solid fa-arrow-left me-1"></i>Repos
        </button>
        <h5 class="fw-semibold mb-0 d-inline" id="milestone-repo-title"></h5>
      </div>
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="milestone-show-closed">
        <label class="form-check-label small text-muted" for="milestone-show-closed">Show closed</label>
      </div>
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
        <button class="btn btn-sm btn-outline-secondary" id="btn-back-milestones">
          <i class="fa-solid fa-arrow-left me-1"></i>Milestones
        </button>
        <h5 class="fw-semibold mb-0 me-auto" id="board-title"></h5>
        <button class="btn btn-sm btn-outline-primary" id="btn-refresh-board">
          <i class="fa-solid fa-rotate-right me-1"></i>Refresh
        </button>
        <button class="btn btn-sm btn-success" id="btn-ensure-labels">
          <i class="fa-solid fa-tags me-1"></i>Ensure Labels
        </button>
      </div>

      <div id="board-loading" class="text-center text-muted py-5">
        <div class="spinner-border me-2"></div>Loading issues…
      </div>

      <div id="board" style="display:none">
        <div class="kanban-col col-todo">
          <div class="kanban-col-header">
            <span><i class="fa-regular fa-circle me-1"></i>Todo</span>
            <span class="badge bg-secondary col-count" data-col="todo">0</span>
          </div>
          <div class="kanban-col-body" data-col="todo"></div>
        </div>
        <div class="kanban-col col-in-progress">
          <div class="kanban-col-header">
            <span><i class="fa-regular fa-clock me-1"></i>In Progress</span>
            <span class="badge bg-primary col-count" data-col="in-progress">0</span>
          </div>
          <div class="kanban-col-body" data-col="in-progress"></div>
        </div>
        <div class="kanban-col col-review">
          <div class="kanban-col-header">
            <span><i class="fa-solid fa-eye me-1"></i>Review</span>
            <span class="badge bg-warning text-dark col-count" data-col="review">0</span>
          </div>
          <div class="kanban-col-body" data-col="review"></div>
        </div>
        <div class="kanban-col col-done">
          <div class="kanban-col-header">
            <span><i class="fa-solid fa-check me-1"></i>Done</span>
            <span class="badge bg-success col-count" data-col="done">0</span>
          </div>
          <div class="kanban-col-body" data-col="done"></div>
        </div>
      </div>
    </div>
  </div>

</div><!-- #screen-app -->

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
  user:       null,
  csrfToken:  null,
  repo:       null,   // { full_name, name, owner }
  milestone:  null,   // { number, title }
  issues:     [],
};

/* =============================================================================
   Utilities
============================================================================= */

/** Show a transient toast message */
function toast(msg, type = 'success') {
  const id  = 'toast-' + Date.now();
  const cls = type === 'error' ? 'alert-danger' : (type === 'warning' ? 'alert-warning' : 'alert-success');
  const el  = $(`
    <div id="${id}" class="alert ${cls} alert-dismissible shadow-sm" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
      loadRepos();
    })
    .fail(function (xhr) {
      $('#app-loading').hide();
      showScreen('screen-login');
    });
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
          <div class="card-body">
            <h6 class="card-title mb-1">
              <i class="fa-solid ${icon} me-1 text-muted"></i>${escHtml(r.name)}
            </h6>
            <p class="card-text small text-muted mb-2">${escHtml(r.owner)}</p>
            ${r.description ? '<p class="card-text small text-muted text-truncate">' + escHtml(r.description) + '</p>' : ''}
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
    $list.html('<p class="text-muted">No milestones found. ' +
      '<a href="' + escHtml('https://github.com/' + App.repo.full_name + '/milestones/new') +
      '" target="_blank">Create one on GitHub</a>.</p>');
    return;
  }

  milestones.forEach(function (m) {
    const total    = m.open_issues + m.closed_issues;
    const progress = total > 0 ? Math.round((m.closed_issues / total) * 100) : 0;
    const due      = m.due_on ? new Date(m.due_on).toLocaleDateString() : 'No due date';
    const stateBadge = m.state === 'closed'
      ? '<span class="badge bg-secondary ms-1">closed</span>'
      : '';

    const $card = $(`
      <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="card list-item-card h-100" data-milestone="${m.number}">
          <div class="card-body">
            <h6 class="card-title mb-1">
              <i class="fa-solid fa-flag me-1 text-muted"></i>${escHtml(m.title)}${stateBadge}
            </h6>
            ${m.description ? '<p class="card-text small text-muted mb-2 text-truncate">' + escHtml(m.description) + '</p>' : ''}
            <div class="progress mb-1" style="height:6px">
              <div class="progress-bar bg-success" style="width:${progress}%"></div>
            </div>
            <p class="card-text small text-muted">
              ${m.closed_issues}/${total} issues &bull; ${escHtml(due)}
            </p>
          </div>
        </div>
      </div>
    `);
    $card.find('.card').on('click', function () {
      selectMilestone(m);
    });
    $list.append($card);
  });
}

$('#milestone-show-closed').on('change', function () {
  loadMilestones($(this).is(':checked'));
});

$('#btn-back-repos').on('click', function () { loadRepos(); });

/* =============================================================================
   Kanban board
============================================================================= */

function selectMilestone(milestone) {
  App.milestone = milestone;
  updateBreadcrumb(App.repo, milestone);
  loadBoard();
}

function loadBoard() {
  showSub('sub-board');
  $('#board-title').text(App.repo.full_name + ' / ' + App.milestone.title);
  $('#board-loading').show();
  $('#board').hide();

  apiGet(BASE + '/api/issues', { repo: App.repo.full_name, milestone: App.milestone.number })
    .done(function (issues) {
      App.issues = issues;
      renderBoard(issues);
      $('#board-loading').hide();
      $('#board').show();
      initDragDrop();
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
    ? `<button class="btn btn-outline-secondary btn-sm" data-action="close" title="Close issue"><i class="fa-solid fa-xmark"></i></button>`
    : `<button class="btn btn-outline-success btn-sm"    data-action="reopen" title="Reopen issue"><i class="fa-solid fa-rotate-left"></i></button>`;

  return $(`
    <div class="issue-card${closedClass}"
         draggable="true"
         data-number="${issue.number}"
         data-status="${escHtml(issue.status)}">
      <div class="card-actions">
        ${closeBtn}
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

  apiPost(BASE + '/api/issue_update', {
    repo:         App.repo.full_name,
    issue_number: number,
    action,
  })
  .done(function (res) {
    // Update local state
    const idx = App.issues.findIndex(i => i.number === number);
    if (idx !== -1) { App.issues[idx].state = res.issue.state; }
    toast(action === 'close' ? `Issue #${number} closed.` : `Issue #${number} reopened.`);
    // Re-render the single card in place
    $card.toggleClass('is-closed', res.issue.state === 'closed');
    $card.find('.card-actions').html(
      res.issue.state === 'open'
        ? `<button class="btn btn-outline-secondary btn-sm" data-action="close" title="Close issue"><i class="fa-solid fa-xmark"></i></button>`
        : `<button class="btn btn-outline-success btn-sm"    data-action="reopen" title="Reopen issue"><i class="fa-solid fa-rotate-left"></i></button>`
    );
  })
  .fail(function (xhr) {
    toast('Error: ' + (xhr.responseJSON?.error ?? 'Unknown'), 'error');
  });
});

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

  // Always save the new order for every column the drop affected
  const saveOrder = () => saveColumnOrder(newCol, prevCol !== newCol ? prevCol : null);

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
function saveColumnOrder(col1, col2 = null) {
  // Build the full ordered list across all columns so positions are global
  const numbers = [];
  document.querySelectorAll('.kanban-col-body').forEach(body => {
    body.querySelectorAll('.issue-card').forEach(card => {
      numbers.push(parseInt(card.dataset.number, 10));
    });
  });

  apiPost(BASE + '/api/issue_order', {
    repo:    App.repo.full_name,
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

$('#nav-home').on('click', function (e) { e.preventDefault(); loadRepos(); });
$('#btn-back-milestones').on('click', function () { loadMilestones(); });
$('#btn-refresh-board').on('click', function () { loadBoard(); });

$('#btn-ensure-labels').on('click', function () {
  const STATUS_LABELS = [
    { name: 'status:todo',        color: 'e9ecef' },
    { name: 'status:in-progress', color: 'cfe2ff' },
    { name: 'status:review',      color: 'fff3cd' },
    { name: 'status:done',        color: 'd1e7dd' },
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
