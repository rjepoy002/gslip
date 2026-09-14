<?php
session_start();
require_once 'includes/config.php';
$conn = getDBConnection();

$gas_slip_id  = $_GET['id'] ?? null;
$mode = $_GET['mode'] ?? 'body';
$role = $_SESSION['role'] ?? null;
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover = !empty($_SESSION['is_private_approver']);

$stmt = $conn->prepare("
  SELECT *
  FROM gas_slips
  WHERE id = ?
");
$stmt->bind_param('i', $gas_slip_id);
$stmt->execute();

$result = $stmt->get_result();
$gasSlip = $result->fetch_assoc();

if (!$gasSlip) {
  exit('Gas slip not found');
}
if (!$gas_slip_id) {
  if ($mode === 'json') {
    echo json_encode(['error' => 'Invalid ID']);
  } else {
    echo '<div class="text-danger">Invalid gas slip ID.</div>';
  }
  exit;
}

/* FETCH DATA */
$stmt = $conn->prepare("
  SELECT
    gs.id,
    gs.user_id,
    gs.gas_slip_id,
    gs.date_issued,
    gs.validity_until,
    gs.requested_by,
    
    CONCAT(
      ru.last_name, ', ', ru.first_name, ' ', IFNULL(ru.middle_name, '')) AS recommended_by,
    CONCAT(
      au.last_name, ', ', au.first_name, ' ', IFNULL(au.middle_name, '')) AS approved_by,
    
    CONCAT(
        rju.last_name, ', ', rju.first_name, ' ', IFNULL(rju.middle_name, '')
    ) AS rejected_by,

    gs.recommended_at,
    gs.rejected_at,
    gs.rejection_reason,
    gs.approved_at,
    gs.purpose,
    gs.status,
    v.plate_no,
    v.brand,
    v.model,
    v.ownership,

    CONCAT(
      MIN(r.origin),
      ' → ',
      GROUP_CONCAT(
        r.destination
        ORDER BY gsr.id
        SEPARATOR ' → '
      )
    ) AS route_path

  FROM gas_slips gs
  LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
  LEFT JOIN routes r ON r.id = gsr.route_id
  LEFT JOIN vehicles v ON v.id = gs.vehicle_id
  LEFT JOIN users ru ON ru.id = gs.recommended_by
  LEFT JOIN users au ON au.id = gs.approved_by
  LEFT JOIN users rju ON rju.id = gs.rejected_by

  WHERE gs.id = ?
  GROUP BY gs.id
");
$stmt->bind_param('i', $gas_slip_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$isExpired = strtotime(date('Y-m-d', strtotime($data['validity_until']))) < strtotime(date('Y-m-d'));

if (!$data) {
  if ($mode === 'json') {
    echo json_encode(['error' => 'Not found', 'id' => $gas_slip_id]);
  } else {
    echo '<div class="text-danger">Gas slip not found.</div>';
  }
  exit;
}

$fuelStmt = $conn->prepare("
  SELECT
    fi.name AS fuel_name,
    fr.quantity,
    fr.container
  FROM fuel_requests fr
  LEFT JOIN fuel_items fi ON fi.id = fr.fuel_item_id
  WHERE fr.gas_slip_id = ?
");
$fuelStmt->bind_param('i', $data['id']); // correct FK
$fuelStmt->execute();
$fuelResult = $fuelStmt->get_result();

/* STATUS MAP */
switch ($data['status']) {
  case 'pending':
    $data['statusLabel'] = 'Pending';
    $data['statusClass'] = 'bg-info';
    break;
  case 'recommended':
    $data['statusLabel'] = 'Recommended';
    $data['statusClass'] = 'bg-warning';
    break;
  case 'approved':
    $data['statusLabel'] = 'Approved';
    $data['statusClass'] = 'bg-success';
    break;
  case 'draft':
    $data['statusLabel'] = 'Draft';
    $data['statusClass'] = 'bg-secondary';
    break;
  case 'rejected':
    $data['statusLabel'] = 'Rejected';
    $data['statusClass'] = 'bg-danger';
    break;
  default:
    $data['statusLabel'] = 'Unknown';
    $data['statusClass'] = 'bg-light text-dark';
}

/* JSON MODE (HEADER) */
if ($mode === 'json') {
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

/* BODY MODE (HTML ONLY) */
// $isApproved = ($data['status'] === 'approved');
// $isApprover = ($_SESSION['role'] === 'approver');
?>
<div class="modal-body">
<?php
$showExpired = $isExpired && $data['status'] !== 'approved';
?>
  <!-- GAS SLIP INFORMATION -->
  <div class="gas-slip-info mb-4">

      <div class="info-item">
          <div class="info-label">Date Issued</div>
          <div class="info-value">
              <?= date('M d, Y · h:i A', strtotime($data['date_issued'])) ?>
          </div>
      </div>

      <div class="info-item">
          <div class="info-label">Validity Until</div>
          <div class="info-value <?= $showExpired ? 'text-danger fw-semibold' : '' ?>">
              <?= date('M d, Y · h:i A', strtotime($data['validity_until'])) ?>
              <?= $showExpired ? ' (Expired)' : '' ?>
          </div>
      </div>

      <div class="info-item">
          <div class="info-label">Requested By</div>
          <div class="info-value">
              <?= htmlspecialchars($data['requested_by']) ?>
          </div>
      </div>

      <div class="info-item">
          <div class="info-label">Vehicle</div>
          <div class="info-value">
              <?= htmlspecialchars(
                  $data['plate_no'] . ' - ' .
                  $data['brand'] . ' ' .
                  $data['model']
              ) ?>
          </div>
      </div>

      <div class="info-item info-full">
          <div class="info-label">Purpose</div>
          <div class="info-value">
              <?= nl2br(htmlspecialchars($data['purpose'])) ?>
          </div>
      </div>

      <div class="info-item info-full">
          <div class="info-label">Destination</div>
          <div class="info-value">
              <?= htmlspecialchars($data['route_path']) ?>
          </div>
      </div>

  </div>
  
  <!-- table for fuel item list -->
  <?php $no = 1; ?>
  <?php 
    // echo $role . $data['status'];
  ?>
  <table class="styled-table excel-table">
    <thead class="table-light">
      <tr>
        <th>No</th>
        <th>Fuel Item</th>
        <th class="text-end">Quantity</th>
        <th hidden>Container</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($fuelResult->num_rows > 0): ?>
        <?php while ($fuel = $fuelResult->fetch_assoc()): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($fuel['fuel_name']); ?></td>
            <td class="text-end">
              <?php echo number_format($fuel['quantity'], 2); ?>
            </td>
            <td hidden><?php echo htmlspecialchars($fuel['container']); ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="3" class="text-center text-muted">
            No fuel items requested
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- MODAL FOOTER -->
<div class="modal-footer d-flex justify-content-between align-items-stretch border-top">

  <!-- Audit Info (LEFT) -->
  <!-- GAS SLIP TIMELINE -->
  <div class="gas-slip-timeline">

  <div class="fw-semibold mb-3">
      <i class="fas fa-clock-rotate-left me-1"></i>
      Gas Slip Timeline
  </div>

  <!-- CREATED -->
  <div class="timeline-item completed">
      <div class="timeline-marker">
          <i class="fas fa-file-circle-plus"></i>
      </div>

      <div class="timeline-content">
          <div class="timeline-title">Gas Slip Created</div>

          <div class="timeline-user">
              <?= htmlspecialchars($data['requested_by']) ?>
          </div>

          <div class="timeline-date">
              <?= date('M d, Y · h:i A', strtotime($data['date_issued'])) ?>
          </div>
      </div>
  </div>


  <!-- RECOMMENDED -->
  <?php if (!empty($data['recommended_by'])): ?>

      <div class="timeline-item completed">
          <div class="timeline-marker">
              <i class="fas fa-thumbs-up"></i>
          </div>

          <div class="timeline-content">
              <div class="timeline-title">Recommended</div>

              <div class="timeline-user">
                  <?= htmlspecialchars(strtoupper($data['recommended_by'])) ?>
              </div>

              <div class="timeline-date">
                  <?= !empty($data['recommended_at'])
                      ? date('M d, Y · h:i A', strtotime($data['recommended_at']))
                      : '' ?>
              </div>
          </div>
      </div>

  <?php endif; ?>


  <!-- APPROVED -->
  <?php if ($data['status'] === 'approved'): ?>

      <div class="timeline-item approved">
          <div class="timeline-marker">
              <i class="fas fa-check"></i>
          </div>

          <div class="timeline-content">
              <div class="timeline-title">Approved</div>

              <div class="timeline-user">
                  <?= htmlspecialchars(strtoupper($data['approved_by'])) ?>
              </div>

              <div class="timeline-date">
                  <?= !empty($data['approved_at'])
                      ? date('M d, Y · h:i A', strtotime($data['approved_at']))
                      : '' ?>
              </div>
          </div>
      </div>

  <?php endif; ?>


  <!-- REJECTED -->
  <?php if ($data['status'] === 'rejected'): ?>

      <div class="timeline-item rejected">
          <div class="timeline-marker">
              <i class="fas fa-times"></i>
          </div>

          <div class="timeline-content">
              <div class="timeline-title">Rejected</div>

              <div class="timeline-user">
                  <?= htmlspecialchars(strtoupper($data['rejected_by'])) ?>
              </div>

              <div class="timeline-date">
                  <?= !empty($data['rejected_at'])
                      ? date('M d, Y · h:i A', strtotime($data['rejected_at']))
                      : '' ?>
              </div>

              <?php if (!empty($data['rejection_reason'])): ?>
                  <div class="timeline-reason">
                      <strong>Reason:</strong>
                      <?= nl2br(htmlspecialchars(
                          trim($data['rejection_reason'])
                      )) ?>
                  </div>
              <?php endif; ?>

          </div>
      </div>

  <?php endif; ?>

  </div>

  <!-- Action Buttons (RIGHT / BOTTOM-ALIGNED) -->
  <div class="d-flex align-items-end gap-2 ms-auto">

    <?php if ($gasSlip['status'] === 'draft' && $gasSlip['user_id'] == $_SESSION['user_id']): ?>

      <button type="button"
              class="btn btn-danger btn-cancel-draft"
              data-id="<?= $gasSlip['id'] ?>">
        <i class="fas fa-times me-1"></i> Cancel Draft
      </button>

      <div class="mt-3 text-end">
          <a href="edit_gas_slip.php?id=<?= $gasSlip['id'] ?>"
            class="btn btn-primary">
              <i class="fas fa-edit"></i> Edit Draft
          </a>
      </div>
    <?php endif; ?>

    <!-- // Show Withdraw button only if slip is pending and current user is the requester -->
    <?php if (
        in_array($data['status'], ['pending', 'rejected']) &&
        $data['user_id'] == $_SESSION['user_id']
    ): ?>
        <button
          type="button"
          class="btn btn-danger"
          id="btnWithdraw"
          data-gas-slip-id="<?= (int)$data['id']; ?>"
        >
          <i class="fas fa-undo"></i> Withdraw
        </button>
    <?php endif; ?>

    <!-- // Show Recommend button only if user is a recommender and slip is pending -->
    <?php

    /* COOP-OWNED PENDING -> RECOMMENDER */
   
    // $isExpired = strtotime($data['validity_until']) < strtotime(date('Y-m-d'));

    if (
        $isRecommender &&
        $data['ownership'] === 'coop-owned' &&
        $data['status'] === 'pending'
    ):
    ?>

        <button
          type="button"
          class="btn btn-success"
          id="recommendBtn"
          data-gas-slip-id="<?= (int)$data['id']; ?>"
          <?= $isExpired ? 'disabled title="Gas slip has expired."' : '' ?>
        >
          <i class="fas fa-thumbs-up"></i> Recommend
        </button>

        <?php if ($data['user_id'] != $_SESSION['user_id']): ?>
          <!-- Reject button on far left of action area -->
          <button
              type="button"
              class="btn btn-danger align-self-end rejectBtn  me-auto"
              data-gas-slip-id="<?= (int)$data['id']; ?>"
          >
              <i class="fas fa-times"></i> Reject
          </button>
        <?php endif; ?>

    <?php

    /* PRIVATE PENDING -> APPROVER (acts as recommender) */
    elseif (
        $isApprover &&
        $data['ownership'] === 'private' &&
        $data['status'] === 'pending'
    ):
    ?>
        <button
          type="button"
          class="btn btn-success"
          id="recommendBtn"
          data-gas-slip-id="<?= (int)$data['id']; ?>"
          <?= $isExpired ? 'disabled title="Gas slip has expired."' : '' ?>
        >
          <i class="fas fa-thumbs-up"></i> Recommend
        </button>

        <?php if ($data['user_id'] != $_SESSION['user_id']): ?>
          <!-- Reject button on far left of action area -->
          <button
              type="button"
              class="btn btn-danger align-self-end rejectBtn  me-auto"
              data-gas-slip-id="<?= (int)$data['id']; ?>"
          >
              <i class="fas fa-times"></i> Reject
          </button>
        <?php endif; ?>

    <?php

    /* COOP-OWNED RECOMMENDED -> APPROVER */
    elseif (
        $isApprover &&
        $data['ownership'] === 'coop-owned' &&
        $data['status'] === 'recommended'
    ):
    ?>
        <button
          type="button"
          class="btn btn-success"
          id="approveBtn"
          data-gas-slip-id="<?= (int)$data['id']; ?>"
          <?= $isExpired ? 'disabled title="Gas slip has expired."' : '' ?>
        >
          <i class="fas fa-check"></i> Approve
        </button>

        <?php if ($data['user_id'] != $_SESSION['user_id']): ?>
          <!-- Reject button on far left of action area -->
          <button
              type="button"
              class="btn btn-danger align-self-end rejectBtn  me-auto"
              data-gas-slip-id="<?= (int)$data['id']; ?>"
          >
              <i class="fas fa-times"></i> Reject
          </button>
        <?php endif; ?>

    <?php

    /* PRIVATE RECOMMENDED -> PRIVATE APPROVER */
    elseif (
        $isPrivateApprover &&
        $data['ownership'] === 'private' &&
        $data['status'] === 'recommended'
    ):
    ?>
        <button
          type="button"
          class="btn btn-success"
          id="approveBtn"
          data-gas-slip-id="<?= (int)$data['id']; ?>"
          <?= $isExpired ? 'disabled title="Gas slip has expired."' : '' ?>
        >
          <i class="fas fa-check"></i> Approve
        </button>

        <?php if ($data['user_id'] != $_SESSION['user_id']): ?>
          <!-- Reject button on far left of action area -->
          <button
              type="button"
              class="btn btn-danger align-self-end rejectBtn  me-auto"
              data-gas-slip-id="<?= (int)$data['id']; ?>"
          >
              <i class="fas fa-times"></i> Reject
          </button>
        <?php endif; ?>

    <?php endif; ?>

    <button
      type="button"
      class="btn btn-secondary"
      data-bs-dismiss="modal"
    >
      Close
    </button>

  </div>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>
