<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$page_title = "Patients";
$header_title = "Patient Records";

$stmtPatients = $pdo->query("
    SELECT 
        p.patient_id,
        p.first_name,
        p.middle_name,
        p.last_name,
        p.sex,
        p.contact_number,
        p.address,
        p.date_registered,
        u.email,
        u.status AS user_status
    FROM tbl_patients p
    LEFT JOIN tbl_users u 
        ON p.user_id = u.user_id
    ORDER BY p.patient_id DESC
");

$patients = $stmtPatients->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="card">
    <div class="head">
        <h3>All Patients</h3>
        <a href="add-patient.php">+ Add New Patient</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Sex</th>
                    <th>Contact Number</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($patients)): ?>

                    <?php foreach ($patients as $p): ?>

                        <?php
                        $full_name = trim(
                            $p['first_name'] . ' ' .
                            ($p['middle_name'] ?? '') . ' ' .
                            $p['last_name']
                        );

                        $user_status = strtolower($p['user_status'] ?? 'inactive');

                        $status_class = in_array(
                            $user_status,
                            ['active', 'inactive'],
                            true
                        )
                            ? $user_status
                            : 'inactive';
                        ?>

                        <tr>
                            <td>
                                <?php echo htmlspecialchars(
                                    (string)$p['patient_id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td class="patient-cell">
                                <?php echo htmlspecialchars(
                                    $full_name ?: 'Unknown Patient',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $p['sex'] ?? 'N/A',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $p['contact_number'] ?? 'N/A',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $p['email'] ?? 'N/A',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>

                            <td>
                                <span class="status <?php echo htmlspecialchars(
                                    $status_class,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>">
                                    <?php echo htmlspecialchars(
                                        ucfirst($user_status),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    href="edit-patient.php?id=<?php echo (int)$p['patient_id']; ?>"
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
                            No patients found.
                        </td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>