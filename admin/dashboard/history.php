<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf = $_SESSION['csrf_history'] ??= bin2hex(random_bytes(16));

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function next_month_window(): array
{
    $first = new DateTimeImmutable('first day of next month');
    return [$first->format('Y-m-01'), $first->format('Y-m-t'), $first->format('F Y')];
}

if (isset($_GET['action']) && $_GET['action'] === 'get') {
    $recordId = (int)($_GET['id'] ?? 0);

    $st = $pdo->prepare("
        SELECT patient_id, appointment_id, treatment_id, dentist_id
        FROM tbl_dental_records
        WHERE record_id = ?
    ");
    $st->execute([$recordId]);
    $rec = $st->fetch(PDO::FETCH_ASSOC);
    if (!$rec) {
        json_out(['ok' => false, 'message' => 'Record not found.'], 404);
    }
    $patientId = (int)$rec['patient_id'];

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

    $st = $pdo->prepare("
        SELECT tooth_number, tooth_condition, remarks
        FROM tbl_tooth_conditions
        WHERE record_id = ?
        ORDER BY CAST(tooth_number AS UNSIGNED), tooth_number
    ");
    $st->execute([$recordId]);
    $teeth = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("
        SELECT
            r.review_id,
            r.appointment_id,
            r.rating,
            r.review_text,
            r.created_at,
            a.appointment_date,
            a.procedure_name
        FROM tbl_patient_reviews r
        LEFT JOIN tbl_appointments a
            ON a.appointment_id = r.appointment_id
        WHERE r.patient_id = ?
        ORDER BY r.created_at DESC, r.review_id DESC
    ");
    $st->execute([$patientId]);
    $patientReviews = $st->fetchAll(PDO::FETCH_ASSOC);


    $st = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time,
               a.procedure_name, a.reason, a.status,
               CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS dentist
        FROM tbl_appointments a
        LEFT JOIN tbl_dentists d ON d.dentist_id = a.dentist_id
        WHERE a.appointment_id = ? AND a.patient_id = ?
    ");
    $st->execute([(int)$rec['appointment_id'], $patientId]);
    $appointments = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("
        SELECT t.treatment_id, t.procedure_name, t.treatment_notes,
               t.prescription, t.remarks, t.status,
               a.appointment_date
        FROM tbl_treatments t
        INNER JOIN tbl_appointments a ON a.appointment_id = t.appointment_id
        WHERE t.treatment_id = ? AND a.patient_id = ?
    ");
    $st->execute([(int)$rec['treatment_id'], $patientId]);
    $sessions = $st->fetchAll(PDO::FETCH_ASSOC);

    $dentistsList = $pdo->query("
        SELECT dentist_id, CONCAT('Dr. ', first_name, ' ', last_name) AS full_name
        FROM tbl_dentists
        WHERE status = 'active'
        ORDER BY last_name ASC, first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    [$nextMin, $nextMax, $nextLabel] = next_month_window();

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
        'remaining_session' => $p['remaining_session'] ?? '0',
    ],
    'dentists'        => $dentistsList,
    'default_dentist' => (int)$rec['dentist_id'],
    'next_month'      => [
        'min'   => $nextMin,
        'max'   => $nextMax,
        'label' => $nextLabel
    ],
    'teeth'        => $teeth,
    'appointments' => $appointments,
    'sessions'     => $sessions,
    'reviews'      => $patientReviews
]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
    $in = json_decode(file_get_contents('php://input'), true);

    if (!is_array($in) || !hash_equals($csrf, (string)($in['csrf'] ?? ''))) {
        json_out(['ok' => false, 'message' => 'Invalid request.'], 400);
    }

    $recordId = (int)($in['record_id'] ?? 0);
    $st = $pdo->prepare("
        SELECT patient_id, appointment_id, treatment_id, dentist_id
        FROM tbl_dental_records
        WHERE record_id = ?
    ");
    $st->execute([$recordId]);
    $rec = $st->fetch(PDO::FETCH_ASSOC);
    if (!$rec) {
        json_out(['ok' => false, 'message' => 'Record not found.'], 404);
    }
    $patientId = (int)$rec['patient_id'];
    $recAppt   = (int)$rec['appointment_id'];
    $recTreat  = (int)$rec['treatment_id'];

    $balance = $in['remaining_balance'] ?? '';
    if (!is_numeric($balance) || (float)$balance < 0) {
        json_out(['ok' => false, 'message' => 'Remaining balance must be a valid amount.'], 422);
    }

    $sessionsLeft = $in['remaining_session'] ?? '';
    if (!ctype_digit((string)$sessionsLeft)) {
        json_out(['ok' => false, 'message' => 'Remaining sessions must be a whole number (0 or more).'], 422);
    }

    $apptStatuses = ['pending','confirmed','for_dentist','in_progress','completed','cancelled','no_show'];
    $sessStatuses = ['in_progress','completed'];

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("UPDATE tbl_patients SET remaining_balance = ?, remaining_session = ? WHERE patient_id = ?");
        $st->execute([number_format((float)$balance, 2, '.', ''), (string)(int)$sessionsLeft, $patientId]);

        $upA = $pdo->prepare("
            UPDATE tbl_appointments
            SET appointment_date = ?, appointment_time = ?, procedure_name = ?,
                reason = ?, status = ?
            WHERE appointment_id = ? AND patient_id = ?
        ");
        foreach (($in['appointments'] ?? []) as $a) {
            if ((int)($a['appointment_id'] ?? 0) !== $recAppt) {
                continue;
            }
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
            if ((int)($s['treatment_id'] ?? 0) !== $recTreat) {
                continue;
            }
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
            : 'Could not save changes. Please try again.';
        json_out(['ok' => false, 'message' => $msg], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'book') {
    $in = json_decode(file_get_contents('php://input'), true);

    if (!is_array($in) || !hash_equals($csrf, (string)($in['csrf'] ?? ''))) {
        json_out(['ok' => false, 'message' => 'Invalid request.'], 400);
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        json_out(['ok' => false, 'message' => 'Admin session not found. Please log in again.'], 401);
    }

    $recordId = (int)($in['record_id'] ?? 0);
    $st = $pdo->prepare("SELECT patient_id, appointment_id FROM tbl_dental_records WHERE record_id = ?");
    $st->execute([$recordId]);
    $rec = $st->fetch(PDO::FETCH_ASSOC);
    if (!$rec) {
        json_out(['ok' => false, 'message' => 'Record not found.'], 404);
    }
    $patientId = (int)$rec['patient_id'];

    $dentistId = (int)($in['dentist_id'] ?? 0);
    $date      = trim((string)($in['appointment_date'] ?? ''));
    $time      = trim((string)($in['appointment_time'] ?? ''));
    $proc      = trim((string)($in['procedure_name'] ?? ''));
    $reason    = trim((string)($in['reason'] ?? ''));

    try {
        [$nextMin, $nextMax] = next_month_window();

        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new RuntimeException('Please choose a valid date.');
        }
        if ($date < $nextMin || $date > $nextMax) {
            throw new RuntimeException('The next appointment must be within next month.');
        }

        $t = DateTime::createFromFormat('H:i', $time);
        if (!$t || $t->format('H:i') !== $time) {
            throw new RuntimeException('Please choose a valid time.');
        }
        $time .= ':00';

        if ($proc === '' || strlen($proc) > 150) {
            throw new RuntimeException('Procedure is required (150 characters max).');
        }

        $st = $pdo->prepare("
            SELECT p.patient_id
            FROM tbl_patients p
            INNER JOIN tbl_users u ON u.user_id = p.user_id
            WHERE p.patient_id = ? AND u.status = 'active'
        ");
        $st->execute([$patientId]);
        if (!$st->fetch()) {
            throw new RuntimeException('This patient account is inactive.');
        }

        $st = $pdo->prepare("SELECT dentist_id FROM tbl_dentists WHERE dentist_id = ? AND status = 'active'");
        $st->execute([$dentistId]);
        if (!$st->fetch()) {
            throw new RuntimeException('The selected dentist is not available.');
        }

        $st = $pdo->prepare("
            SELECT COUNT(*) FROM tbl_appointments
            WHERE dentist_id = ? AND appointment_date = ? AND appointment_time = ?
              AND status NOT IN ('cancelled', 'no_show')
        ");
        $st->execute([$dentistId, $date, $time]);
        if ($st->fetchColumn() > 0) {
            throw new RuntimeException('That dentist is already booked for this date and time.');
        }

        $st = $pdo->prepare("
            INSERT INTO tbl_appointments
                (admin_id, patient_id, dentist_id, appointment_date, appointment_time,
                 procedure_name, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'for_dentist')
        ");
        $st->execute([
            $adminId, $patientId, $dentistId, $date, $time, $proc,
            $reason !== '' ? $reason : null,
        ]);

        json_out([
            'ok'       => true,
            'message'  => 'Next appointment booked.',
        ]);
    } catch (RuntimeException $e) {
        json_out(['ok' => false, 'message' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        error_log('History book error: ' . $e->getMessage());
        json_out(['ok' => false, 'message' => 'Could not book the appointment. Please try again.'], 500);
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
                    <th>ID</th>
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
                                <?php echo htmlspecialchars(
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

<style>
    .hm-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: none;
        align-items: center; justify-content: center; z-index: 9999; padding: 16px; }
    .hm-overlay.open { display: flex; animation: hmFade 0.15s ease; }
    .hm-box { background: var(--surface, #fff); color: var(--text-main, #111827);
        border: 1px solid var(--border-color, #e5e7eb); border-radius: var(--radius-lg, 12px);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25); width: 100%; max-width: 860px;
        max-height: 90vh; overflow-y: auto; animation: hmPop 0.2s ease; }
    .hm-top { display: flex; justify-content: space-between; align-items: center;
        padding: 20px 24px 12px; border-bottom: 1px solid var(--border-color, #e5e7eb);
        position: sticky; top: 0; background: var(--surface, #fff); z-index: 2; }
    .hm-top h3 { margin: 0; font-size: 18px; color: var(--text-main, #111827); }
    .hm-x { background: none; border: 0; font-size: 26px; line-height: 1; cursor: pointer;
        color: var(--text-muted, #6b7280); }
    .hm-x:hover { color: var(--text-main, #111827); }
    #hmLoading { padding: 24px; }
    #hmBody { padding: 8px 24px 0; }
    .hm-sec { padding: 16px 0; border-bottom: 1px dashed var(--border-color, #e5e7eb); }
    .hm-sec > h4 { margin: 0 0 12px; font-size: 15px; color: var(--text-main, #111827); }
    .hm-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .hm-field { display: flex; flex-direction: column; gap: 8px; }
    .hm-field label { font-size: 13px; font-weight: 600; color: var(--text-muted, #6b7280); }
    .hm-field input, .hm-field select, .hm-field textarea { width: 100%; padding: 10px 14px;
        border: 1px solid var(--border-color, #d1d5db); border-radius: var(--radius-md, 8px);
        font: inherit; font-size: 14px; box-sizing: border-box; outline: none;
        background: var(--surface, #fff); color: var(--text-main, #111827);
        transition: border-color 0.2s ease; }
    .hm-field input:focus, .hm-field select:focus, .hm-field textarea:focus {
        border-color: var(--brand-purple, #9333ea); }
    .hm-field input[readonly], .hm-field textarea[readonly] { background: var(--gradient-subtle, #f3f4f6); }
    .hm-full { grid-column: 1 / -1; }
    .hm-peso { display: flex; align-items: stretch; }
    .hm-peso span { display: flex; align-items: center; padding: 0 14px;
        background: var(--gradient-subtle, #f3f4f6); border: 1px solid var(--border-color, #d1d5db);
        border-right: 0; border-radius: var(--radius-md, 8px) 0 0 var(--radius-md, 8px); font-weight: 600; }
    .hm-peso input { border-radius: 0 var(--radius-md, 8px) var(--radius-md, 8px) 0; }
    .hm-teeth { display: flex; flex-wrap: wrap; gap: 8px; }
    .hm-tooth { background: var(--gradient-subtle, #f3f4f6); border: 1px solid var(--border-color, #e5e7eb);
        border-radius: var(--radius-md, 8px); padding: 8px 12px; font-size: 13px; font-weight: 600; }
    .hm-item { border: 1px solid var(--border-color, #e5e7eb); border-radius: var(--radius-md, 8px);
        padding: 16px; margin-bottom: 12px; }
    .hm-item:last-child { margin-bottom: 0; }
    .hm-sec:last-of-type { border-bottom: 0; }
    .hm-item-title { font-weight: 700; margin-bottom: 12px; font-size: 14px; color: var(--text-main, #111827); }
    .hm-empty { color: var(--text-muted, #6b7280); font-size: 13px; font-style: italic; }
    .hm-foot { display: flex; justify-content: flex-end; align-items: center; gap: 12px;
        padding: 16px 24px 20px; margin: 0 -24px; border-top: 1px solid var(--border-color, #e5e7eb);
        position: sticky; bottom: 0; background: var(--surface, #fff); z-index: 2; }
    .hm-msg { margin-right: auto; font-size: 13px; font-weight: 600; }
    .hm-btn { padding: 10px 20px; border-radius: var(--radius-md, 8px); font: inherit; font-size: 14px;
        font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        background: var(--gradient-subtle, #f3f4f6); color: var(--text-main, #374151);
        border: 1px solid var(--border-color, #d1d5db); transition: all 0.2s ease; }
    .hm-btn.primary { background: var(--gradient-brand, #9333ea); color: #fff; border: none;
        box-shadow: 0 4px 12px rgba(147, 51, 234, 0.25); }
    .hm-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(147, 51, 234, 0.35); }
    .hm-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    .hm-sub { font-weight: 500; font-size: 12px; color: var(--text-muted, #6b7280); margin-left: 6px; }
    .hm-bookbar { display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin-top: 16px; }
    @keyframes hmFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes hmPop { from { opacity: 0; transform: translateY(12px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); } }
        
.hm-review-card {
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: var(--radius-md, 8px);
    padding: 16px;
    margin-bottom: 12px;
    background: var(--surface, #fff);
}

.hm-review-card:last-child {
    margin-bottom: 0;
}

.hm-review-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
}

.hm-review-rating {
    color: #f59e0b;
    font-size: 16px;
    letter-spacing: 2px;
    white-space: nowrap;
}

.hm-review-date {
    color: var(--text-muted, #6b7280);
    font-size: 12px;
    margin-top: 4px;
}

.hm-review-procedure {
    color: var(--text-muted, #6b7280);
    font-size: 12px;
    margin-bottom: 10px;
}

.hm-review-text {
    color: var(--text-main, #374151);
    font-size: 14px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.hm-review-score {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-main, #374151);
}

    @media (max-width: 768px) { .hm-grid { grid-template-columns: 1fr; } .hm-full { grid-column: auto; } }
</style>

<div class="hm-overlay" id="hmOverlay" aria-hidden="true">
    <div class="hm-box" role="dialog" aria-modal="true" aria-labelledby="hmTitle">
        <div class="hm-top">
            <h3 id="hmTitle">Patient History</h3>
            <button type="button" class="hm-x" id="hmClose" aria-label="Close">&times;</button>
        </div>

        <div id="hmLoading" class="hm-empty">Loading...</div>

        <div id="hmBody" style="display:none;">
            <div class="hm-sec">
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
    <h4>Patient Reviews</h4>
    <div id="hmReviews">
        <div class="hm-empty">Loading patient reviews...</div>
    </div>
</div>


            <div class="hm-sec">
                <h4>Balance &amp; Sessions</h4>
                <div class="hm-grid">
                    <div class="hm-field">
                        <label for="hmBalance">Remaining Balance</label>
                        <div class="hm-peso">
                            <span>&#8369;</span>
                            <input type="number" id="hmBalance" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="hm-field">
                        <label for="hmSessionsLeft">Remaining Sessions</label>
                        <input type="number" id="hmSessionsLeft" min="0" step="1" placeholder="0">
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

            <div class="hm-sec">
                <h4>Book Next Appointment <span class="hm-sub" id="hmNextLabel"></span></h4>
                <div class="hm-item">
                    <div class="hm-grid">
                        <div class="hm-field"><label for="hmBookDentist">Dentist</label><select id="hmBookDentist"></select></div>
                        <div class="hm-field"><label for="hmBookDate">Date (next month only)</label><input type="date" id="hmBookDate"></div>
                        <div class="hm-field"><label for="hmBookTime">Time</label><input type="time" id="hmBookTime"></div>
                        <div class="hm-field"><label for="hmBookProc">Procedure</label><input type="text" id="hmBookProc" maxlength="150"></div>
                        <div class="hm-field hm-full"><label for="hmBookReason">Reason / Notes</label><textarea id="hmBookReason" rows="2"></textarea></div>
                    </div>
                    <div class="hm-bookbar">
                        <span class="hm-msg" id="hmBookMsg"></span>
                        <button type="button" class="hm-btn primary" id="hmBook">Book Appointment</button>
                    </div>
                </div>
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

    
function renderReviews(reviews) {
    var box = $('hmReviews');
    box.innerHTML = '';

    if (!Array.isArray(reviews) || reviews.length === 0) {
        box.appendChild(
            el('div', {
                'class': 'hm-empty',
                text: 'No review submitted yet.'
            })
        );
        return;
    }

    reviews.forEach(function (review) {
        var rating = Math.max(
            0,
            Math.min(5, parseInt(review.rating, 10) || 0)
        );

        var stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);

        var dateText = review.created_at || '';
        if (dateText) {
            var parsedDate = new Date(
                dateText.replace(' ', 'T')
            );

            if (!isNaN(parsedDate.getTime())) {
                dateText = parsedDate.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            }
        }

        var card = el('div', {
            'class': 'hm-review-card'
        });

        var head = el('div', {
            'class': 'hm-review-head'
        });

        var details = el('div');

        details.appendChild(
            el('div', {
                'class': 'hm-review-rating',
                text: stars
            })
        );

        details.appendChild(
            el('div', {
                'class': 'hm-review-score',
                text: rating + ' out of 5'
            })
        );

        details.appendChild(
            el('div', {
                'class': 'hm-review-date',
                text: dateText ? 'Submitted: ' + dateText : 'Date unavailable'
            })
        );

        head.appendChild(details);
        card.appendChild(head);

        if (review.appointment_date || review.procedure_name) {
            var appointmentInfo = [];

            if (review.procedure_name) {
                appointmentInfo.push(review.procedure_name);
            }

            if (review.appointment_date) {
                appointmentInfo.push(
                    fmtDate(review.appointment_date)
                );
            }

            card.appendChild(
                el('div', {
                    'class': 'hm-review-procedure',
                    text: 'Appointment: ' + appointmentInfo.join(' • ')
                })
            );
        }

        card.appendChild(
            el('div', {
                'class': 'hm-review-text',
                text: review.review_text || 'No written feedback provided.'
            })
        );

        box.appendChild(card);
    });
}


    var apptRows = [], sessRows = [];

    function setBookMsg(text, ok) {
        var m = $('hmBookMsg');
        m.textContent = text || '';
        m.style.color = ok ? '#15803d' : '#b91c1c';
    }

    function renderBookForm(data) {
        var sel = $('hmBookDentist'); sel.innerHTML = '';
        (data.dentists || []).forEach(function (d) {
            var o = el('option', { value: d.dentist_id, text: d.full_name });
            if (parseInt(d.dentist_id, 10) === parseInt(data.default_dentist, 10)) o.selected = true;
            sel.appendChild(o);
        });
        var nm = data.next_month || {};
        $('hmNextLabel').textContent = nm.label ? '(' + nm.label + ')' : '';
        $('hmBookDate').min = nm.min || '';
        $('hmBookDate').max = nm.max || '';
        $('hmBookDate').value = '';
        var a0 = (data.appointments || [])[0], s0 = (data.sessions || [])[0];
        $('hmBookTime').value = a0 ? (a0.appointment_time || '').substring(0, 5) : '';
        $('hmBookProc').value = (a0 && a0.procedure_name) || (s0 && s0.procedure_name) || '';
        $('hmBookReason').value = '';
        setBookMsg('');
    }

    function render(data) {
        var p = data.patient;
        $('hmName').value = p.full_name || '';
        $('hmBirth').value = fmtDate(p.birth_date);
        $('hmSex').value = p.sex || '';
        $('hmContact').value = p.contact_number || '';
        $('hmEmail').value = p.email || '';
        $('hmAddress').value = p.address || '';
        $('hmBalance').value = parseFloat(p.remaining_balance || 0).toFixed(2);
        $('hmSessionsLeft').value = parseInt(p.remaining_session || 0, 10) || 0;
        renderReviews(data.reviews || []);

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

    function book() {
        var btn = $('hmBook');
        if (!$('hmBookDate').value || !$('hmBookTime').value || !$('hmBookProc').value.trim()) {
            setBookMsg('Please fill in the dentist, date, time and procedure.', false);
            return;
        }
        btn.disabled = true; setBookMsg('Booking...', true);

        fetch('history.php?action=book', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf: CSRF,
                record_id: currentRecord,
                dentist_id: $('hmBookDentist').value,
                appointment_date: $('hmBookDate').value,
                appointment_time: $('hmBookTime').value,
                procedure_name: $('hmBookProc').value,
                reason: $('hmBookReason').value
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                setBookMsg(d.message || (d.ok ? 'Booked.' : 'Failed.'), d.ok);
                if (d.ok) {
                    $('hmBookDate').value = '';
                    $('hmBookReason').value = '';
                }
            })
            .catch(function () { setBookMsg('Network error. Please try again.', false); })
            .then(function () { btn.disabled = false; });
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
                renderBookForm(d);
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
            remaining_session: $('hmSessionsLeft').value,
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
    $('hmBook').addEventListener('click', book);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>