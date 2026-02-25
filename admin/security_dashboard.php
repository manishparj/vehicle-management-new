<?php
session_start();
include("../config/db.php");

// Check authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'security_admin') {
    header("location:login.php");
    exit();
}

/* ===========================
   UPDATE STATUS (Departure)
=========================== */
if (isset($_POST['mark_departure'])) {
    $id = $_POST['app_id'];

    // Fetch existing record
    $row = $conn->query("SELECT * FROM vehicle_applications WHERE id='$id'")->fetch_assoc();

    if (!$row) {
        die("Invalid application ID");
    }

    if ($row['status'] == 'Approved') {
        // Journey starting: save OUT details
        $out_datetime = $_POST['out_datetime'];
        $km_out = $_POST['km_out'];
        $guard_out = $_POST['guard_out'];

        $conn->query("UPDATE vehicle_applications SET
            out_datetime='$out_datetime',
            km_out='$km_out',
            security_guard_name='$guard_out',
            status='Departure'
            WHERE id='$id'
        ");
    } elseif ($row['status'] == 'Departure') {
        // Journey ending: save IN details
        $in_datetime = $_POST['in_datetime'];
        $km_in = $_POST['km_in'];
        $guard_in = $_POST['guard_in'];
        $status = $_POST['journey_status']; // Completed / Cancelled

        // Calculate total KM
        $total_km = ($row['km_out'] && $km_in) ? $km_in - $row['km_out'] : NULL;

        // Calculate total hours in HH:MM format
        $total_hours = NULL;
        if ($row['out_datetime'] && $in_datetime) {
            $start = new DateTime($row['out_datetime']);
            $end = new DateTime($in_datetime);
            $diff = $start->diff($end);
            $hours = ($diff->days * 24) + $diff->h;
            $minutes = $diff->i;
            $total_hours = sprintf("%02d:%02d", $hours, $minutes);
        }

        // Append return guard
        $guard_names = $row['security_guard_name'];
        if ($guard_in) {
            $guard_names .= ", $guard_in (Return)";
        }

        $conn->query("UPDATE vehicle_applications SET
            in_datetime='$in_datetime',
            km_in='$km_in',
            total_km=" . ($total_km !== NULL ? "'$total_km'" : "NULL") . ",
            total_hours=" . ($total_hours !== NULL ? "'$total_hours'" : "NULL") . ",
            security_guard_name='$guard_names',
            status='$status'
            WHERE id='$id'
        ");
    }
}

// Fetch applications
$res = $conn->query("
    SELECT 
        va.*,
        u.name AS forwarded_admin_name,
        u.designation AS forwarded_designation,
        d.driver_name AS driver_name,
        d.driver_mob AS driver_mobile,
        v.vehicle_name AS vehicle_name,
        v.vehicle_no AS vehicle_no,
        v.vehicle_type AS vehicle_type
    FROM vehicle_applications va
    LEFT JOIN users u ON va.forwarded_to = u.id
    LEFT JOIN drivers d ON va.driver_id = d.id
    LEFT JOIN vehicles v ON va.vehicle_id = v.id
    WHERE va.status IN ('Approved', 'Departure', 'Completed', 'Cancelled')
    ORDER BY va.created_at DESC
");

// Status helper functions
function getStatusBadgeClass($status) {
    $badgeClasses = [
        'Approved'    => 'bg-info',
        'Pending'     => 'bg-warning',
        'In Progress' => 'bg-primary',
        'Started'     => 'bg-success',
        'Departure'   => 'bg-primary',
        'Completed'   => 'bg-success',
        'Cancelled'   => 'bg-danger',
        'Rejected'    => 'bg-danger',
        'Expired'     => 'bg-secondary',
        'On Hold'     => 'bg-secondary',
        'Draft'       => 'bg-secondary'
    ];
    return $badgeClasses[$status] ?? 'bg-secondary';
}

function getStatusDisplayText($status) {
    $displayText = [
        'Approved'    => 'Pending Start',
        'Pending'     => 'Ongoing',
        'In Progress' => 'In Progress',
        'Started'     => 'Started',
        'Departure'   => 'In Progress',
        'Completed'   => 'Completed',
        'Cancelled'   => 'Cancelled',
        'Rejected'    => 'Rejected',
        'Expired'     => 'Expired',
        'On Hold'     => 'On Hold',
        'Draft'       => 'Draft'
    ];
    return $displayText[$status] ?? $status;
}

function getButtonIcon($status) {
    $icons = [
        'Approved'    => 'bi-play-fill me-2',
        'Pending'     => 'bi-hourglass-split me-2',
        'In Progress' => 'bi-arrow-repeat me-2',
        'Departure'   => 'bi-arrow-repeat me-2',
        'Completed'   => 'bi-check-circle-fill me-2',
        'Cancelled'   => 'bi-x-circle-fill me-2',
        'Rejected'    => 'bi-exclamation-triangle-fill me-2'
    ];
    return $icons[$status] ?? 'bi-question-circle-fill me-2';
}

function getButtonConfig($status) {
    $disabledStatuses = ['Completed', 'Cancelled', 'Rejected', 'Expired'];
    $isDisabled = in_array($status, $disabledStatuses);

    if ($isDisabled) {
        return [
            'class' => 'btn btn-secondary disabled',
            'text' => $status,
            'disabled' => true
        ];
    } else {
        return [
            'class' => $status == 'Approved' ? 'btn btn-success' : 'btn btn-warning',
            'text' => $status == 'Approved' ? 'Start Journey' : 'End Journey',
            'disabled' => false
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Security Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="security.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="container-fluid px-3">
                <div class="row align-items-center">
                    <div class="col-8 col-sm-9">
                        <div class="dashboard-title">
                            <i class="bi bi-speedometer2"></i>
                            <span>Vehicle Management</span>
                        </div>
                    </div>
                    <div class="col-4 col-sm-3 text-end">
                        <a href="logout.php" class="btn-logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-sm-inline">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid px-3 py-3">
            <h3 class="page-title">
                <i class="bi bi-shield-check"></i> Vehicle Applications
            </h3>

            <div class="applications-grid">
                <?php if ($res->num_rows > 0): ?>
                    <?php while ($row = $res->fetch_assoc()): 
                        $btnConfig = getButtonConfig($row['status']);
                    ?>
                        <div class="application-card">
                            <!-- Card Header -->
                            <div class="card-header">
                                <div class="app-id">#<?= htmlspecialchars($row['id']) ?></div>
                                <span class="status-badge <?= getStatusBadgeClass($row['status']) ?>">
                                    <?= getStatusDisplayText($row['status']) ?>
                                </span>
                            </div>

                            <div class="card-body">
                                <!-- Applicant Section -->
                                <div class="info-section">
                                    <div class="section-title">
                                        <i class="bi bi-person-badge"></i>
                                        <span>Applicant</span>
                                    </div>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Name:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['applicant_name']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Email:</span>
                                            <span class="info-value text-truncate"><?= htmlspecialchars($row['email']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Mobile:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['mobile']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Staff Type:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['staff_type']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Journey Section -->
                                <div class="info-section">
                                    <div class="section-title">
                                        <i class="bi bi-map"></i>
                                        <span>Journey</span>
                                    </div>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">From:</span>
                                            <span class="info-value"><?= date("d M Y H:i", strtotime($row['datetime_from'])) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">To:</span>
                                            <span class="info-value"><?= date("d M Y H:i", strtotime($row['datetime_to'])) ?></span>
                                        </div>
                                        <div class="info-item full-width">
                                            <span class="info-label">Place:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['place_of_visit']) ?></span>
                                        </div>
                                        <div class="info-item full-width">
                                            <span class="info-label">Purpose:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['purpose']) ?></span>
                                        </div>
                                        <?php if (!empty($row['remark'])): ?>
                                        <div class="info-item full-width">
                                            <span class="info-label">Remark:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['remark']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Driver & Vehicle Section -->
                                <div class="info-section">
                                    <div class="section-title">
                                        <i class="bi bi-truck"></i>
                                        <span>Vehicle & Driver</span>
                                    </div>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Driver:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['driver_name']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['driver_mobile']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Vehicle:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['vehicle_name']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Type:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['vehicle_type']) ?></span>
                                        </div>
                                        <div class="info-item full-width">
                                            <span class="info-label">Reg No:</span>
                                            <span class="info-value vehicle-no"><?= htmlspecialchars($row['vehicle_no']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Co-Travelers Section -->
                                <?php if ($row['cotraveler1'] || $row['cotraveler2'] || $row['cotraveler3'] || $row['cotraveler4']): ?>
                                <div class="info-section">
                                    <div class="section-title">
                                        <i class="bi bi-people"></i>
                                        <span>Co-Travelers</span>
                                    </div>
                                    <div class="travelers-list">
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <?php if (!empty($row['cotraveler' . $i])): ?>
                                                <div class="traveler-item">
                                                    <i class="bi bi-person"></i>
                                                    <?= htmlspecialchars($row['cotraveler' . $i]) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Approval Section -->
                                <div class="info-section">
                                    <div class="section-title">
                                        <i class="bi bi-check-circle"></i>
                                        <span>Approval</span>
                                    </div>
                                    <div class="info-grid">
                                        <div class="info-item full-width">
                                            <span class="info-label">Forwarded To:</span>
                                            <span class="info-value"><?= htmlspecialchars($row['forwarded_admin_name']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Tour Order:</span>
                                            <span class="info-value">
                                                <?= $row['tour_approved'] ? '✓ Attached' : '✗ Not Attached' ?>
                                            </span>
                                        </div>
                                        <?php if ($row['tour_order_pdf']): ?>
                                        <div class="info-item full-width">
                                            <a href="../uploads/<?= htmlspecialchars($row['tour_order_pdf']) ?>" 
                                               target="_blank" class="btn-view-pdf">
                                                <i class="bi bi-file-pdf"></i> View Tour Order
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Security Movement Section -->
                                <div class="info-section">
                                    <div class="section-title">
                                        <i class="bi bi-shield-lock"></i>
                                        <span>Security Details</span>
                                    </div>
                                    <div class="security-grid">
                                        <div class="security-item">
                                            <span class="security-label">Out:</span>
                                            <span class="security-value">
                                                <?= $row['out_datetime'] ? date('d M H:i', strtotime($row['out_datetime'])) : '--' ?>
                                            </span>
                                        </div>
                                        <div class="security-item">
                                            <span class="security-label">KM Out:</span>
                                            <span class="security-value"><?= $row['km_out'] ?: '--' ?></span>
                                        </div>
                                        <div class="security-item">
                                            <span class="security-label">In:</span>
                                            <span class="security-value">
                                                <?= $row['in_datetime'] ? date('d M H:i', strtotime($row['in_datetime'])) : '--' ?>
                                            </span>
                                        </div>
                                        <div class="security-item">
                                            <span class="security-label">KM In:</span>
                                            <span class="security-value"><?= $row['km_in'] ?: '--' ?></span>
                                        </div>
                                        <div class="security-item">
                                            <span class="security-label">Total KM:</span>
                                            <span class="security-value"><?= $row['total_km'] ?: '--' ?></span>
                                        </div>
                                        <div class="security-item">
                                            <span class="security-label">Hours:</span>
                                            <span class="security-value"><?= $row['total_hours'] ?: '--' ?></span>
                                        </div>
                                        <div class="security-item full-width">
                                            <span class="security-label">Guard:</span>
                                            <span class="security-value"><?= htmlspecialchars($row['security_guard_name'] ?: '--') ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Created At -->
                                <div class="created-at">
                                    <i class="bi bi-clock"></i>
                                    <?= date('d M Y H:i', strtotime($row['created_at'])) ?>
                                </div>

                                <!-- Action Button -->
                                <button class="action-btn <?= $btnConfig['class'] ?>"
                                        data-id="<?= htmlspecialchars($row['id']) ?>"
                                        data-status="<?= htmlspecialchars($row['status']) ?>"
                                        <?= !$btnConfig['disabled'] ? 'data-bs-toggle="modal" data-bs-target="#modal' . $row['id'] . '"' : '' ?>
                                        <?= $btnConfig['disabled'] ? 'disabled' : '' ?>>
                                    <i class="bi <?= getButtonIcon($row['status']) ?>"></i>
                                    <span><?= htmlspecialchars($btnConfig['text']) ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- Modal -->
                        <div class="modal fade" id="modal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="bi bi-<?= $row['status'] == 'Approved' ? 'play' : 'flag' ?>-circle"></i>
                                                <?= $row['status'] == 'Approved' ? 'Start Journey' : 'Complete Journey' ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">
                                            <input type="hidden" name="app_id" value="<?= htmlspecialchars($row['id']) ?>">

                                            <?php if ($row['status'] == 'Approved'): ?>
                                                <!-- Out Journey Fields -->
                                                <div class="form-group">
                                                    <label>Out Date & Time</label>
                                                    <input type="datetime-local" name="out_datetime" class="form-control" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>KM Out</label>
                                                    <input type="number" name="km_out" class="form-control" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Security Guard Name</label>
                                                    <input type="text" name="guard_out" class="form-control" required>
                                                </div>
                                                
                                                <input type="hidden" name="journey_status" value="Departure">

                                            <?php elseif ($row['status'] == 'Departure'): ?>
                                                <!-- Return Journey Fields -->
                                                <div class="form-group">
                                                    <label>In Date & Time</label>
                                                    <input type="datetime-local" name="in_datetime" class="form-control" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>KM In</label>
                                                    <input type="number" name="km_in" class="form-control" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Security Guard (Return)</label>
                                                    <input type="text" name="guard_in" class="form-control" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Journey Status</label>
                                                    <select name="journey_status" class="form-control" required>
                                                        <option value="Completed">Completed - Successful</option>
                                                        <option value="Cancelled">Cancelled - Not Completed</option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                            <button type="submit" name="mark_departure" class="btn-modal-submit">
                                                <i class="bi bi-check-circle"></i> 
                                                <?= $row['status'] == 'Approved' ? 'Start Journey' : 'Complete' ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-applications">
                        <i class="bi bi-inbox"></i>
                        <p>No applications found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>