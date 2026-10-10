<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------
 * AJAX endpoints for the "View" modal (same file, runs before any HTML)
 * TODO: add your admin session check here, e.g.
 *   if (empty($_SESSION['admin_id'])) { http_response_code(403); exit; }
 * ------------------------------------------------------------------ */
$csrf = $_SESSION['csrf_history'] ??= bin2hex(random_bytes(16));

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get') {
    $recordId = (int)($_GET['id'] ?? 0);

    $st = $pdo->prepare("SELECT patient_id FROM tbl_dental_records WHERE record_id = ?");
    $st->execute([$recordId]);
    $patientId = (int)$st->fetchColumn();
    if (!$patientId) {
        json_out(['ok' => false, 'message' => 'Record not found.'], 404);
    }

    $st = $pdo->prepare("
        SELECT p.*, u.email
        FROM tbl_patients p
        LEFT JOIN tbl_users u ON u.user_id = p.user_id
        WHERE p.patient_id = ?
    ");
    $st->execute([$patientId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    $fullName = trim(
        $p['first_name'] . ' ' .
        (!empty($p['middle_name']) ? $p['middle_name'] . ' ' : '') .
        $p['last_name']
    );

    // Teeth: current state per patient; fallback to this record's findings
    $st = $pdo->prepare("
        SELECT tooth_number, tooth_condition, remarks
        FROM tbl_patient_teeth
        WHERE patient_id = ?
        ORDER BY CAST(tooth_number AS UNSIGNED), tooth_number
    ");
    $st->execute([$patientId]);
    $teeth = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$teeth) {
        $st = $pdo->prepare("
            SELECT tooth_number, tooth_condition, remarks
            FROM tbl_tooth_conditions
            WHERE record_id = ?
            ORDER BY CAST(tooth_number AS UNSIGNED), tooth_number
        ");
        $st->execute([$recordId]);
        $teeth = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $st = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time,
               a.procedure_name, a.reason, a.status,
               CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS dentist
        FROM tbl_appointments a
        LEFT JOIN tbl_dentists d ON d.dentist_id = a.dentist_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $st->execute([$patientId]);
    $appointments = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("
        SELECT t.treatment_id, t.procedure_name, t.treatment_notes,
               t.prescription, t.remarks, t.status,
               a.appointment_date
        FROM tbl_treatments t
        INNER JOIN tbl_appointments a ON a.appointment_id = t.appointment_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, t.treatment_id DESC
    ");
    $st->execute([$patientId]);
    $sessions = $st->fetchAll(PDO::FETCH_ASSOC);

    json_out([
        'ok'      => true,
        'patient' => [
            'full_name'         => $fullName,
            'birth_date'        => $p['birth_date'],
            'sex'               => $p['sex'],
            'contact_number'    => $p['contact_number'],
            'address'           => $p['address'],
            'email'             => $p['email'] ?? '',
            'remaining_balance' => $p['remaining_balance'] ?? '0.00',
        ],
        'teeth'        => $teeth,
        'appointments' => $appointments,
        'sessions'     => $sessions,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
    $in = json_decode(file_get_contents('php://input'), true);

    if (!is_array($in) || !hash_equals($csrf, (string)($in['csrf'] ?? ''))) {
        json_out(['ok' => false, 'message' => 'Invalid request.'], 400);
    }

    $recordId = (int)($in['record_id'] ?? 0);
    $st = $pdo->prepare("SELECT patient_id FROM tbl_dental_records WHERE record_id = ?");
    $st->execute([$recordId]);
    $patientId = (int)$st->fetchColumn();
    if (!$patientId) {
        json_out(['ok' => false, 'message' => 'Record not found.'], 404);
    }

    $balance = $in['remaining_balance'] ?? '';
    if (!is_numeric($balance) || (float)$balance < 0) {
        json_out(['ok' => false, 'message' => 'Remaining balance must be a valid amount.'], 422);
    }

    $apptStatuses = ['pending','confirmed','for_dentist','in_progress','completed','cancelled','no_show'];
    $sessStatuses = ['in_progress','completed'];

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("UPDATE tbl_patients SET remaining_balance = ? WHERE patient_id = ?");
        $st->execute([number_format((float)$balance, 2, '.', ''), $patientId]);

        $upA = $pdo->prepare("
            UPDATE tbl_appointments
            SET appointment_date = ?, appointment_time = ?, procedure_name = ?,
                reason = ?, status = ?
            WHERE appointment_id = ? AND patient_id = ?
        ");
        foreach (($in['appointments'] ?? []) as $a) {
            $date = (string)($a['appointment_date'] ?? '');
            $time = (string)($a['appointment_time'] ?? '');
            if (strlen($time) === 5) {
                $time .= ':00';
            }
            $proc = trim((string)($a['procedure_name'] ?? ''));

            if (
                !DateTime::createFromFormat('Y-m-d', $date) ||
                !DateTime::createFromFormat('H:i:s', $time) ||
                $proc === '' ||
                !in_array($a['status'] ?? '', $apptStatuses, true)
            ) {
                throw new RuntimeException('Please complete all appointment fields correctly.');
            }

            $upA->execute([
                $date, $time, $proc,
                trim((string)($a['reason'] ?? '')),
                $a['status'],
                (int)($a['appointment_id'] ?? 0),
                $patientId,
            ]);
        }

        $upS = $pdo->prepare("
            UPDATE tbl_treatments t
            INNER JOIN tbl_appointments a ON a.appointment_id = t.appointment_id
            SET t.procedure_name = ?, t.treatment_notes = ?, t.prescription = ?,
                t.remarks = ?, t.status = ?,
                t.completed_at = CASE
                    WHEN ? = 'completed' THEN COALESCE(t.completed_at, NOW())
                    ELSE NULL
                END
            WHERE t.treatment_id = ? AND a.patient_id = ?
        ");
        foreach (($in['sessions'] ?? []) as $s) {
            $proc = trim((string)($s['procedure_name'] ?? ''));
            if ($proc === '' || !in_array($s['status'] ?? '', $sessStatuses, true)) {
                throw new RuntimeException('Please complete all session fields correctly.');
            }
            $upS->execute([
                $proc,
                trim((string)($s['treatment_notes'] ?? '')),
                trim((string)($s['prescription'] ?? '')),
                trim((string)($s['remarks'] ?? '')),
                $s['status'],
                $s['status'],
                (int)($s['treatment_id'] ?? 0),
                $patientId,
            ]);
        }

        $pdo->commit();
        json_out(['ok' => true, 'message' => 'Changes saved.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Could not save changes. Make sure the remaining_balance column exists.';
        json_out(['ok' => false, 'message' => $msg], 500);
    }
}

$page_title = "Patient History";
$header_title = "Medical & Dental History";

$stmtHistory = $pdo->query("
    SELECT 
        r.record_id,
        CONCAT(
            p.first_name,
            ' ',
            COALESCE(CONCAT(p.middle_name, ' '), ''),
            p.last_name
        ) AS patient,
        r.diagnosis,
        r.treatment_summary,
        r.remarks,
        r.record_date,
        CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS dentist
    FROM tbl_dental_records r
    LEFT JOIN tbl_patients p
        ON r.patient_id = p.patient_id
    LEFT JOIN tbl_dentists d
        ON r.dentist_id = d.dentist_id
    ORDER BY r.record_date DESC
");

$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="card">
    <div class="head">
        <h3>Dental Treatment Records</h3>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>Patient Name</th>
                    <th>Record Date</th>
                    <th>Attending Dentist</th>
                    <th>Diagnosis</th>
                    <th>Treatment Summary</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                #<?php echo htmlspecialchars(
                                    (string)$h['record_id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td class="patient-cell">
                                <?php echo htmlspecialchars(
                                    $h['patient'] ?? 'Unknown',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    !empty($h['record_date'])
                                        ? date('M d, Y g:i A', strtotime($h['record_date']))
                                        : 'N/A',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $h['dentist'] ?? 'N/A',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $h['diagnosis'] ?? 'N/A',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $h['treatment_summary'] ?? 'No summary available',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <a
                                    href="#"
                                    class="js-view-history"
                                    data-id="<?php echo (int)$h['record_id']; ?>"
                                    style="color: var(--brand-purple, #9333ea); text-decoration: none; font-weight: 600; margin-right: 10px;"
                                >
                                    View
                                </a>
                                <a
                                    href="edit-history.php?id=<?php echo (int)$h['record_id']; ?>"
                                    style="color: var(--brand-purple, #9333ea); text-decoration: none; font-weight: 600;"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td
                            colspan="7"
                            style="text-align: center; color: var(--text-muted);"
                        >
                            No medical records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ Patient History Modal (scoped styles, prefix hm-) ============ -->
<style>
    .hm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none;
        align-items: center; justify-content: center; z-index: 9999; padding: 16px; }
    .hm-overlay.open { display: flex; }
    .hm-box { background: var(--card-bg, #fff); color: inherit; width: 100%; max-width: 860px;
        max-height: 90vh; overflow-y: auto; border-radius: 12px; padding: 24px;
        box-shadow: 0 10px 40px rgba(0,0,0,.25); }
    .hm-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .hm-top h3 { margin: 0; }
    .hm-x { background: none; border: 0; font-size: 26px; line-height: 1; cursor: pointer; color: inherit; }
    .hm-sec { margin-top: 22px; }
    .hm-sec > h4 { margin: 0 0 10px; }
    .hm-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .hm-field label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;
        color: var(--text-muted, #6b7280); }
    .hm-field input, .hm-field select, .hm-field textarea { width: 100%; padding: 8px 10px;
        border: 1px solid #d1d5db; border-radius: 8px; font: inherit; box-sizing: border-box;
        background: #fff; color: inherit; }
    .hm-field input[readonly], .hm-field textarea[readonly] { background: #f3f4f6; }
    .hm-full { grid-column: 1 / -1; }
    .hm-peso { display: flex; align-items: stretch; }
    .hm-peso span { display: flex; align-items: center; padding: 0 12px; background: #f3f4f6;
        border: 1px solid #d1d5db; border-right: 0; border-radius: 8px 0 0 8px; font-weight: 600; }
    .hm-peso input { border-radius: 0 8px 8px 0; }
    .hm-teeth { display: flex; flex-wrap: wrap; gap: 8px; }
    .hm-tooth { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px 10px; font-size: 13px; }
    .hm-item { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
    .hm-item-title { font-weight: 600; margin-bottom: 8px; font-size: 13px; }
    .hm-empty { color: var(--text-muted, #6b7280); font-size: 13px; }
    .hm-foot { display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 22px; }
    .hm-msg { margin-right: auto; font-size: 13px; }
    .hm-btn { padding: 9px 18px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff;
        cursor: pointer; font: inherit; font-weight: 600; }
    .hm-btn.primary { background: var(--brand-purple, #9333ea); border-color: var(--brand-purple, #9333ea); color: #fff; }
    .hm-btn:disabled { opacity: .6; cursor: not-allowed; }
    @media (max-width: 640px) { .hm-grid { grid-template-columns: 1fr; } }
</style>

<div class="hm-overlay" id="hmOverlay" aria-hidden="true">
    <div class="hm-box" role="dialog" aria-modal="true" aria-labelledby="hmTitle">
        <div class="hm-top">
            <h3 id="hmTitle">Patient History</h3>
            <button type="button" class="hm-x" id="hmClose" aria-label="Close">&times;</button>
        </div>

        <div id="hmLoading" class="hm-empty">Loading...</div>

        <div id="hmBody" style="display:none;">
            <div class="hm-sec" style="margin-top:0;">
                <h4>Patient Information</h4>
                <div class="hm-grid">
                    <div class="hm-field hm-full"><label>Full Name</label><input type="text" id="hmName" readonly></div>
                    <div class="hm-field"><label>Birthdate</label><input type="text" id="hmBirth" readonly></div>
                    <div class="hm-field"><label>Sex</label><input type="text" id="hmSex" readonly></div>
                    <div class="hm-field"><label>Contact Number</label><input type="text" id="hmContact" readonly></div>
                    <div class="hm-field"><label>Email</label><input type="text" id="hmEmail" readonly></div>
                    <div class="hm-field hm-full"><label>Address</label><textarea id="hmAddress" rows="2" readonly></textarea></div>
                </div>
            </div>

            <div class="hm-sec">
                <h4>Teeth Condition</h4>
                <div class="hm-teeth" id="hmTeeth"></div>
            </div>

            <div class="hm-sec">
                <h4>Remaining Balance</h4>
                <div class="hm-field" style="max-width:280px;">
                    <div class="hm-peso">
                        <span>&#8369;</span>
                        <input type="number" id="hmBalance" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
            </div>

            <div class="hm-sec">
                <h4>Sessions</h4>
                <div id="hmSessions"></div>
            </div>

            <div class="hm-sec">
                <h4>Appointments</h4>
                <div id="hmAppointments"></div>
            </div>

            <div class="hm-foot">
                <span class="hm-msg" id="hmMsg"></span>
                <button type="button" class="hm-btn" id="hmCancel">Close</button>
                <button type="button" class="hm-btn primary" id="hmSave">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CSRF = <?php echo json_encode($csrf); ?>;
    var APPT_STATUS = ['pending','confirmed','for_dentist','in_progress','completed','cancelled','no_show'];
    var SESS_STATUS = ['in_progress','completed'];

    var $ = function (id) { return document.getElementById(id); };
    var overlay = $('hmOverlay'), currentRecord = 0;

    function el(tag, attrs, children) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') n.textContent = attrs[k];
            else if (k === 'value') n.value = attrs[k];
            else n.setAttribute(k, attrs[k]);
        });
        (children || []).forEach(function (c) { n.appendChild(c); });
        return n;
    }

    function field(label, input, full) {
        return el('div', { 'class': 'hm-field' + (full ? ' hm-full' : '') }, [
            el('label', { text: label }), input
        ]);
    }

    function select(options, value) {
        var s = el('select');
        options.forEach(function (o) {
            var opt = el('option', { value: o, text: o.replace(/_/g, ' ') });
            if (o === value) opt.selected = true;
            s.appendChild(opt);
        });
        return s;
    }

    function open() { overlay.classList.add('open'); overlay.setAttribute('aria-hidden', 'false'); }
    function close() { overlay.classList.remove('open'); overlay.setAttribute('aria-hidden', 'true'); }

    function setMsg(text, ok) {
        var m = $('hmMsg');
        m.textContent = text || '';
        m.style.color = ok ? '#15803d' : '#b91c1c';
    }

    function fmtDate(d) {
        if (!d) return '';
        var dt = new Date(d + 'T00:00:00');
        return isNaN(dt) ? d : dt.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    var apptRows = [], sessRows = [];

    function render(data) {
        var p = data.patient;
        $('hmName').value = p.full_name || '';
        $('hmBirth').value = fmtDate(p.birth_date);
        $('hmSex').value = p.sex || '';
        $('hmContact').value = p.contact_number || '';
        $('hmEmail').value = p.email || '';
        $('hmAddress').value = p.address || '';
        $('hmBalance').value = parseFloat(p.remaining_balance || 0).toFixed(2);

        var teeth = $('hmTeeth'); teeth.innerHTML = '';
        if (!data.teeth.length) {
            teeth.appendChild(el('span', { 'class': 'hm-empty', text: 'No teeth condition recorded.' }));
        }
        data.teeth.forEach(function (t) {
            var txt = 'Tooth ' + t.tooth_number + ': ' + t.tooth_condition + (t.remarks ? ' (' + t.remarks + ')' : '');
            teeth.appendChild(el('div', { 'class': 'hm-tooth', text: txt }));
        });

        var sBox = $('hmSessions'); sBox.innerHTML = ''; sessRows = [];
        if (!data.sessions.length) sBox.appendChild(el('div', { 'class': 'hm-empty', text: 'No sessions found.' }));
        data.sessions.forEach(function (s) {
            var r = {
                id: s.treatment_id,
                procedure: el('input', { type: 'text', value: s.procedure_name || '' }),
                notes: el('textarea', { rows: 2, value: s.treatment_notes || '' }),
                rx: el('textarea', { rows: 2, value: s.prescription || '' }),
                remarks: el('textarea', { rows: 2, value: s.remarks || '' }),
                status: select(SESS_STATUS, s.status)
            };
            sessRows.push(r);
            sBox.appendChild(el('div', { 'class': 'hm-item' }, [
                el('div', { 'class': 'hm-item-title', text: 'Session #' + s.treatment_id + ' - ' + fmtDate(s.appointment_date) }),
                el('div', { 'class': 'hm-grid' }, [
                    field('Procedure', r.procedure),
                    field('Status', r.status),
                    field('Treatment Notes', r.notes, true),
                    field('Prescription', r.rx),
                    field('Remarks', r.remarks)
                ])
            ]));
        });

        var aBox = $('hmAppointments'); aBox.innerHTML = ''; apptRows = [];
        if (!data.appointments.length) aBox.appendChild(el('div', { 'class': 'hm-empty', text: 'No appointments found.' }));
        data.appointments.forEach(function (a) {
            var r = {
                id: a.appointment_id,
                date: el('input', { type: 'date', value: a.appointment_date || '' }),
                time: el('input', { type: 'time', value: (a.appointment_time || '').substring(0, 5) }),
                procedure: el('input', { type: 'text', value: a.procedure_name || '' }),
                status: select(APPT_STATUS, a.status),
                reason: el('textarea', { rows: 2, value: a.reason || '' })
            };
            apptRows.push(r);
            aBox.appendChild(el('div', { 'class': 'hm-item' }, [
                el('div', { 'class': 'hm-item-title', text: 'Appointment #' + a.appointment_id + (a.dentist ? ' - ' + a.dentist : '') }),
                el('div', { 'class': 'hm-grid' }, [
                    field('Date', r.date),
                    field('Time', r.time),
                    field('Procedure', r.procedure),
                    field('Status', r.status),
                    field('Reason', r.reason, true)
                ])
            ]));
        });
    }

    function load(id) {
        currentRecord = id;
        $('hmLoading').style.display = 'block';
        $('hmLoading').textContent = 'Loading...';
        $('hmBody').style.display = 'none';
        setMsg('');
        open();

        fetch('history.php?action=get&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) throw new Error(d.message || 'Failed to load.');
                render(d);
                $('hmLoading').style.display = 'none';
                $('hmBody').style.display = 'block';
            })
            .catch(function (e) { $('hmLoading').textContent = e.message || 'Failed to load.'; });
    }

    function save() {
        var btn = $('hmSave');
        btn.disabled = true; setMsg('Saving...', true);

        var payload = {
            csrf: CSRF,
            record_id: currentRecord,
            remaining_balance: $('hmBalance').value,
            appointments: apptRows.map(function (r) {
                return {
                    appointment_id: r.id,
                    appointment_date: r.date.value,
                    appointment_time: r.time.value,
                    procedure_name: r.procedure.value,
                    status: r.status.value,
                    reason: r.reason.value
                };
            }),
            sessions: sessRows.map(function (r) {
                return {
                    treatment_id: r.id,
                    procedure_name: r.procedure.value,
                    treatment_notes: r.notes.value,
                    prescription: r.rx.value,
                    remarks: r.remarks.value,
                    status: r.status.value
                };
            })
        };

        fetch('history.php?action=save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { setMsg(d.message || (d.ok ? 'Saved.' : 'Failed.'), d.ok); })
            .catch(function () { setMsg('Network error. Please try again.', false); })
            .then(function () { btn.disabled = false; });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('.js-view-history');
        if (a) { e.preventDefault(); load(parseInt(a.getAttribute('data-id'), 10)); }
    });
    $('hmClose').addEventListener('click', close);
    $('hmCancel').addEventListener('click', close);
    $('hmSave').addEventListener('click', save);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>