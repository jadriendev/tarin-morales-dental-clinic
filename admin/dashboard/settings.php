<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Settings";
$header_title = "Account Settings";

$admins = [];
$dentists = [];
$patients = [];
$error_message = '';

try {
    $sql_admins = "
        SELECT
            admin_id,
            username,
            CONCAT(first_name, ' ', last_name) AS full_name,
            status
        FROM tbl_admins
        ORDER BY admin_id DESC
    ";

    $stmt_admins = $pdo->prepare($sql_admins);
    $stmt_admins->execute();
    $admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

    $sql_dentists = "
        SELECT
            dentist_id,
            username,
            CONCAT(first_name, ' ', last_name) AS full_name,
            license_no,
            specialization,
            status
        FROM tbl_dentists
        ORDER BY dentist_id DESC
    ";

    $stmt_dentists = $pdo->prepare($sql_dentists);
    $stmt_dentists->execute();
    $dentists = $stmt_dentists->fetchAll(PDO::FETCH_ASSOC);

    $sql_patients = "
        SELECT
            p.patient_id,
            u.email,
            u.username,
            CONCAT(
                p.first_name,
                ' ',
                IFNULL(p.middle_name, ''),
                ' ',
                p.last_name
            ) AS full_name,
            p.contact_number,
            p.date_registered,
            u.status
        FROM tbl_patients p
        INNER JOIN tbl_users u
            ON p.user_id = u.user_id
        ORDER BY p.patient_id DESC
    ";

    $stmt_patients = $pdo->prepare($sql_patients);
    $stmt_patients->execute();
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Settings Database Error: " . $e->getMessage());
    $error_message = "Unable to load account information. Please try again.";
}

$admin_rows = [];
foreach ($admins as $a) {
    $s = strtolower($a['status'] ?? 'inactive');
    $admin_rows[] = [
        'id'        => (string)$a['admin_id'],
        'username'  => (string)($a['username'] ?? ''),
        'full_name' => trim(preg_replace('/\s+/', ' ', $a['full_name'] ?? '')) ?: 'Unknown',
        'status'    => in_array($s, ['active', 'inactive'], true) ? $s : 'inactive',
    ];
}

$dentist_rows = [];
foreach ($dentists as $d) {
    $s = strtolower($d['status'] ?? 'inactive');
    $dentist_rows[] = [
        'id'             => (string)$d['dentist_id'],
        'username'       => (string)($d['username'] ?? ''),
        'full_name'      => trim(preg_replace('/\s+/', ' ', $d['full_name'] ?? '')) ?: 'Unknown',
        'license_no'     => (string)($d['license_no'] ?? 'N/A'),
        'specialization' => (string)(($d['specialization'] ?? '') !== '' ? $d['specialization'] : 'N/A'),
        'status'         => in_array($s, ['active', 'inactive'], true) ? $s : 'inactive',
    ];
}

$patient_rows = [];
foreach ($patients as $p) {
    $s = strtolower($p['status'] ?? 'inactive');
    $patient_rows[] = [
        'id'              => (string)$p['patient_id'],
        'full_name'       => trim(preg_replace('/\s+/', ' ', $p['full_name'] ?? '')) ?: 'Unknown Patient',
        'username'        => (string)($p['username'] ?? 'N/A'),
        'email'           => (string)($p['email'] ?? 'N/A'),
        'contact_number'  => (string)($p['contact_number'] ?? 'N/A'),
        'date_registered' => !empty($p['date_registered'])
            ? date('M d, Y', strtotime($p['date_registered']))
            : 'N/A',
        'status'          => in_array($s, ['active', 'inactive'], true) ? $s : 'inactive',
    ];
}

$js_data = [
    'admin'   => $admin_rows,
    'dentist' => $dentist_rows,
    'patient' => $patient_rows,
];
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
  .settings-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    padding: 20px;
    align-items: start;
  }

  .settings-grid > .card {
    min-width: 0;
    margin: 0;
  }

  .settings-grid .head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .table-fit {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
  }

  .table-fit th,
  .table-fit td {
    padding: 10px 8px;
    font-size: 13px;
    text-align: left;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .table-fit th:first-child,
  .table-fit td:first-child {
    padding-left: 16px;
  }

  .table-fit .c-id     { width: 14%; }
  .table-fit .c-status { width: 26%; }
  .table-fit .c-action { width: 24%; text-align: center; padding-right: 12px; }

  .btn-view,
  .btn-viewall,
  .btn-edit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--brand-purple, #9333ea);
    background: rgba(147, 51, 234, 0.08);
    border: 1px solid rgba(147, 51, 234, 0.25);
  }

  .btn-view:hover,
  .btn-viewall:hover,
  .btn-edit:hover {
    background: var(--brand-purple, #9333ea);
    color: #fff;
  }

  .sm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    z-index: 9999;
  }

  .sm-overlay.is-open {
    display: flex;
    animation: smFade 0.15s ease;
  }

  .sm-modal {
    background: var(--surface, #fff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: var(--radius-lg, 12px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: smPop 0.2s ease;
  }

  .sm-modal.wide {
    max-width: 1000px;
  }

  .sm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
  }

  .sm-header h3 {
    margin: 0;
    font-size: 18px;
    color: var(--text-main, #111827);
  }

  .sm-close {
    background: none;
    border: none;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    color: var(--text-muted, #6b7280);
  }

  .sm-body {
    padding: 8px 24px;
    overflow-y: auto;
  }

  .sm-toolbar {
    padding: 16px 24px 0;
  }

  .sm-search {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--border-color, #d1d5db);
    background: var(--surface, #fff);
    color: var(--text-main, #111827);
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
  }

  .sm-search:focus {
    border-color: var(--brand-purple, #9333ea);
  }

  .sm-body.table-body {
    padding: 16px 24px 8px;
    overflow: auto;
  }

  .sm-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px dashed var(--border-color, #e5e7eb);
    font-size: 14px;
  }

  .sm-row:last-child {
    border-bottom: none;
  }

  .sm-label {
    flex: 0 0 130px;
    font-weight: 600;
    color: var(--text-muted, #6b7280);
  }

  .sm-value {
    flex: 1;
    text-align: right;
    font-weight: 600;
    color: var(--text-main, #111827);
    word-break: break-word;
  }

  .sm-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px 20px;
    border-top: 1px solid var(--border-color, #e5e7eb);
  }

  .sm-btn-secondary {
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    background: var(--gradient-subtle, #f3f4f6);
    color: var(--text-main, #374151);
    border: 1px solid var(--border-color, #d1d5db);
  }

  .all-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
  }

  .all-table th,
  .all-table td {
    padding: 10px 12px;
    font-size: 13px;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
  }

  .all-table th {
    color: var(--text-muted, #6b7280);
    font-weight: 600;
  }

  .all-empty {
    text-align: center;
    color: var(--text-muted, #6b7280);
    padding: 20px;
  }

  @keyframes smFade {
    from { opacity: 0; }
    to   { opacity: 1; }
  }

  @keyframes smPop {
    from { opacity: 0; transform: translateY(12px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  @media (max-width: 1200px) {
    .settings-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 768px) {
    .settings-grid {
      padding: 12px;
      gap: 16px;
    }

    .sm-row {
      flex-direction: column;
      gap: 4px;
    }

    .sm-label {
      flex: none;
    }

    .sm-value {
      text-align: left;
    }
  }
</style>

<?php if (!empty($error_message)): ?>
  <div
    class="alert alert-danger"
    style="padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;"
  >
    <strong>Database Error:</strong>
    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
  </div>
<?php endif; ?>

<div class="settings-grid">

  <div class="card">
    <div class="head">
      <h3>Administrators</h3>
      <button type="button" class="btn-viewall js-viewall" data-type="admin">
        <i class="fa-solid fa-table-list"></i> View All
      </button>
    </div>

    <div class="table-container">
      <table class="table-fit">
        <thead>
          <tr>
            <th class="c-id">ID</th>
            <th>Name</th>
            <th class="c-status">Status</th>
            <th class="c-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($admin_rows)): ?>
            <?php foreach ($admin_rows as $i => $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td title="<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td>
                  <span class="status <?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>
                <td class="c-action">
                  <button type="button" class="btn-view js-view" data-type="admin" data-index="<?php echo (int)$i; ?>">
                    <i class="fa-solid fa-eye"></i> View
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-muted);">
                No administrator accounts found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="head">
      <h3>Dentists</h3>
      <button type="button" class="btn-viewall js-viewall" data-type="dentist">
        <i class="fa-solid fa-table-list"></i> View All
      </button>
    </div>

    <div class="table-container">
      <table class="table-fit">
        <thead>
          <tr>
            <th class="c-id">ID</th>
            <th>Name</th>
            <th class="c-status">Status</th>
            <th class="c-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($dentist_rows)): ?>
            <?php foreach ($dentist_rows as $i => $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td title="<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td>
                  <span class="status <?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>
                <td class="c-action">
                  <button type="button" class="btn-view js-view" data-type="dentist" data-index="<?php echo (int)$i; ?>">
                    <i class="fa-solid fa-eye"></i> View
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-muted);">
                No dentist accounts found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="head">
      <h3>Patients</h3>
      <button type="button" class="btn-viewall js-viewall" data-type="patient">
        <i class="fa-solid fa-table-list"></i> View All
      </button>
    </div>

    <div class="table-container">
      <table class="table-fit">
        <thead>
          <tr>
            <th class="c-id">ID</th>
            <th>Name</th>
            <th class="c-status">Status</th>
            <th class="c-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($patient_rows)): ?>
            <?php foreach ($patient_rows as $i => $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td title="<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td>
                  <span class="status <?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>
                <td class="c-action">
                  <button type="button" class="btn-view js-view" data-type="patient" data-index="<?php echo (int)$i; ?>">
                    <i class="fa-solid fa-eye"></i> View
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-muted);">
                No patient accounts found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<div class="sm-overlay" id="viewOverlay" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
  <div class="sm-modal">
    <div class="sm-header">
      <h3 id="viewTitle">Details</h3>
      <button type="button" class="sm-close" data-close="viewOverlay" aria-label="Close">&times;</button>
    </div>
    <div class="sm-body" id="viewBody"></div>
    <div class="sm-footer">
      <button type="button" class="sm-btn-secondary" data-close="viewOverlay">Close</button>
      <a href="#" class="btn-edit" id="viewEdit">
        <i class="fa-solid fa-pen"></i> Edit
      </a>
    </div>
  </div>
</div>

<div class="sm-overlay" id="allOverlay" role="dialog" aria-modal="true" aria-labelledby="allTitle">
  <div class="sm-modal wide">
    <div class="sm-header">
      <h3 id="allTitle">All Records</h3>
      <button type="button" class="sm-close" data-close="allOverlay" aria-label="Close">&times;</button>
    </div>
    <div class="sm-toolbar">
      <input type="text" class="sm-search" id="allSearch" placeholder="Search...">
    </div>
    <div class="sm-body table-body" id="allBody"></div>
    <div class="sm-footer">
      <button type="button" class="sm-btn-secondary" data-close="allOverlay">Close</button>
    </div>
  </div>
</div>

<script>
(function () {
  var DATA = <?php
    echo json_encode(
        $js_data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
  ?>;

  var CONFIG = {
    admin: {
      label: 'Administrator',
      plural: 'Administrators',
      editUrl: 'account-settings.php',
      fields: [
        ['ID', 'id'],
        ['Username', 'username'],
        ['Full Name', 'full_name'],
        ['Status', 'status']
      ]
    },
    dentist: {
      label: 'Dentist',
      plural: 'Dentists',
      editUrl: 'edit-dentist.php',
      fields: [
        ['ID', 'id'],
        ['Username', 'username'],
        ['Full Name', 'full_name'],
        ['License No.', 'license_no'],
        ['Specialization', 'specialization'],
        ['Status', 'status']
      ]
    },
    patient: {
      label: 'Patient',
      plural: 'Patients',
      editUrl: 'edit-patient.php',
      fields: [
        ['ID', 'id'],
        ['Full Name', 'full_name'],
        ['Username', 'username'],
        ['Email', 'email'],
        ['Contact Number', 'contact_number'],
        ['Date Registered', 'date_registered'],
        ['Status', 'status']
      ]
    }
  };

  var viewOverlay = document.getElementById('viewOverlay');
  var allOverlay  = document.getElementById('allOverlay');
  var viewBody    = document.getElementById('viewBody');
  var viewTitle   = document.getElementById('viewTitle');
  var viewEdit    = document.getElementById('viewEdit');
  var allBody     = document.getElementById('allBody');
  var allTitle    = document.getElementById('allTitle');
  var allSearch   = document.getElementById('allSearch');

  var currentAllType = null;

  function make(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function statusBadge(value) {
    var v = (value || '').toLowerCase() === 'active' ? 'active' : 'inactive';
    return make('span', 'status ' + v, v.charAt(0).toUpperCase() + v.slice(1));
  }

  function fillValue(container, key, value) {
    if (key === 'status') {
      container.appendChild(statusBadge(value));
    } else {
      container.textContent = value;
    }
  }

  function openOverlay(overlay) {
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeOverlay(overlay) {
    overlay.classList.remove('is-open');
    if (!viewOverlay.classList.contains('is-open') &&
        !allOverlay.classList.contains('is-open')) {
      document.body.style.overflow = '';
    }
  }

  function openView(type, index) {
    var cfg  = CONFIG[type];
    var item = DATA[type][index];
    if (!cfg || !item) return;

    viewTitle.textContent = cfg.label + ' Details';
    viewBody.textContent = '';

    cfg.fields.forEach(function (f) {
      var row = make('div', 'sm-row');
      row.appendChild(make('span', 'sm-label', f[0]));
      var val = make('span', 'sm-value');
      fillValue(val, f[1], item[f[1]]);
      row.appendChild(val);
      viewBody.appendChild(row);
    });

    viewEdit.setAttribute('href', cfg.editUrl + '?id=' + encodeURIComponent(item.id));
    openOverlay(viewOverlay);
  }

  function renderAll(type, filter) {
    var cfg  = CONFIG[type];
    var rows = DATA[type];
    var q = (filter || '').toLowerCase().trim();

    allBody.textContent = '';

    var table = make('table', 'all-table');
    var thead = make('thead');
    var htr = make('tr');

    cfg.fields.forEach(function (f) {
      htr.appendChild(make('th', '', f[0]));
    });
    htr.appendChild(make('th', '', 'Action'));
    thead.appendChild(htr);
    table.appendChild(thead);

    var tbody = make('tbody');
    var shown = 0;

    rows.forEach(function (item) {
      if (q !== '') {
        var haystack = cfg.fields.map(function (f) {
          return String(item[f[1]] || '');
        }).join(' ').toLowerCase();
        if (haystack.indexOf(q) === -1) return;
      }

      shown++;
      var tr = make('tr');

      cfg.fields.forEach(function (f) {
        var td = make('td');
        fillValue(td, f[1], item[f[1]]);
        tr.appendChild(td);
      });

      var actionTd = make('td');
      var link = make('a', 'btn-edit');
      link.setAttribute('href', cfg.editUrl + '?id=' + encodeURIComponent(item.id));
      var icon = make('i', 'fa-solid fa-pen');
      link.appendChild(icon);
      link.appendChild(document.createTextNode(' Edit'));
      actionTd.appendChild(link);
      tr.appendChild(actionTd);

      tbody.appendChild(tr);
    });

    if (shown === 0) {
      var emptyTr = make('tr');
      var emptyTd = make('td', 'all-empty', 'No records found.');
      emptyTd.setAttribute('colspan', String(cfg.fields.length + 1));
      emptyTr.appendChild(emptyTd);
      tbody.appendChild(emptyTr);
    }

    table.appendChild(tbody);
    allBody.appendChild(table);
  }

  function openAll(type) {
    var cfg = CONFIG[type];
    if (!cfg) return;

    currentAllType = type;
    allTitle.textContent = 'All ' + cfg.plural + ' (' + DATA[type].length + ')';
    allSearch.value = '';
    renderAll(type, '');
    openOverlay(allOverlay);
  }

  document.addEventListener('click', function (e) {
    var viewBtn = e.target.closest('.js-view');
    if (viewBtn) {
      openView(viewBtn.getAttribute('data-type'), parseInt(viewBtn.getAttribute('data-index'), 10));
      return;
    }

    var allBtn = e.target.closest('.js-viewall');
    if (allBtn) {
      openAll(allBtn.getAttribute('data-type'));
      return;
    }

    var closeBtn = e.target.closest('[data-close]');
    if (closeBtn) {
      closeOverlay(document.getElementById(closeBtn.getAttribute('data-close')));
      return;
    }

    if (e.target === viewOverlay) closeOverlay(viewOverlay);
    if (e.target === allOverlay)  closeOverlay(allOverlay);
  });

  allSearch.addEventListener('input', function () {
    if (currentAllType) renderAll(currentAllType, allSearch.value);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (viewOverlay.classList.contains('is-open')) closeOverlay(viewOverlay);
    else if (allOverlay.classList.contains('is-open')) closeOverlay(allOverlay);
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>