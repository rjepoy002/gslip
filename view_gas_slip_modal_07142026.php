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

  <!-- GAS SLIP INFORMATION -->
  <table class="table table-sm table-borderless mb-4 small text-muted">
    <tbody>
      <tr>
        <th class="text-muted w-25">Date Issued</th>
        <td>:</td>
        <td><?php echo date('M d, Y · h:i A', strtotime($data['date_issued'])); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Validity Until</th>
        <td>:</td>
        <?php
        $showExpired = $isExpired && $data['status'] !== 'approved';
        ?>

        <td style="<?= $showExpired ? 'color:red;font-weight:bold;' : '' ?>">
          <?= date('M d, Y · h:i A', strtotime($data['validity_until'])); ?>
          <?= $showExpired ? ' (Expired)' : '' ?>
        </td>
      </tr>
      <tr>
        <th class="text-muted">Requested By</th>
        <td>:</td>
        <td><?php echo htmlspecialchars($data['requested_by']); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Purpose</th>
        <td>:</td>
        <td><?php echo nl2br(htmlspecialchars($data['purpose'])); ?></td>
      </tr>
      <tr>
        <th class="text-muted">Vehicle</th>
        <td>:</td>
        <td><?php echo htmlspecialchars($data['plate_no']) . " - " . $data['brand'] . " " . $data['model']; ?></td>
      </tr>
      <tr>
        <th class="text-muted">Destination</th>
        <td>:</td>
        <td><?php echo htmlspecialchars($data['route_path']); ?></td>
      </tr>

    </tbody>
  </table>
  
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
        <th>Container</th>
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
            <td><?php echo htmlspecialchars($fuel['container']); ?></td>
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
  <table class="table table-sm table-borderless mb-4 small text-muted">
      <tbody>
        <!-- <tr>
          <th class="text-muted w-25">Recommended By</th>
          <td>:</td>
          <td><!?= htmlspecialchars(strtoupper($data['recommended_by'] ?? '')) ?></td>
        </tr>
        <tr>
          <th class="text-muted w-25">Approved By</th>
          <td>:</td>
          <td><!?= htmlspecialchars(strtoupper($data['approved_by'] ?? '')) ?></td>
        </tr>
        <tr>
          <th class="text-muted w-25">Approved Date</th>
          <td>:</td>
          <td><!?= !empty($data['approved_at'])
            ? date('M d, Y · h:i A', strtotime($data['approved_at']))
            : '' ?></td>
        </tr> -->
        <?php if (!empty($data['recommended_by'])): ?>
        <tr>
            <td colspan="3" class="pt-3">
                <div class="border rounded p-3">

                    <div class="fw-semibold mb-2">
                        <i class="fas fa-thumbs-up me-1"></i>
                        Recommendation Information
                    </div>

                    <div class="mb-1">
                        <strong>Recommended By:</strong>
                        <?= htmlspecialchars(strtoupper($data['recommended_by'] ?? '')) ?>
                    </div>

                    <div>
                        <strong>Recommended Date:</strong>
                        <?= !empty($data['recommended_at'])
                            ? date('M d, Y · h:i A', strtotime($data['recommended_at']))
                            : '' ?>
                    </div>

                </div>
            </td>
        </tr>
        <?php endif; ?>

        <?php if ($data['status'] === 'approved'): ?>
        <tr>
            <td colspan="3" class="pt-3">
                <div class="alert alert-success mb-0">

                    <div class="fw-semibold mb-2">
                        <i class="fas fa-circle-check me-1"></i>
                        Approval Information
                    </div>

                    <div class="mb-1">
                        <strong>Approved By:</strong>
                        <?= htmlspecialchars(strtoupper($data['approved_by'] ?? '')) ?>
                    </div>

                    <div>
                        <strong>Approved Date:</strong>
                        <?= !empty($data['approved_at'])
                            ? date('M d, Y · h:i A', strtotime($data['approved_at']))
                            : '' ?>
                    </div>

                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($data['status'] === 'rejected'): ?>
        <tr>
            <td colspan="3" class="pt-3">
                <div class="alert alert-danger mb-0">

                    <div class="fw-semibold mb-2">
                        <i class="fas fa-circle-exclamation me-1"></i>
                        Rejection Information
                    </div>

                    <div class="mb-1">
                        <strong>Rejected By:</strong>
                        <?= htmlspecialchars(strtoupper($data['rejected_by'] ?? '')) ?>
                    </div>

                    <div class="mb-2">
                        <strong>Rejected Date:</strong>
                        <?= !empty($data['rejected_at'])
                            ? date('M d, Y · h:i A', strtotime($data['rejected_at']))
                            : '' ?>
                    </div>

                    <hr class="my-2">

                    <div>
                        <strong>Reason for Rejection:</strong>
                        <div class="mt-1">
                            <?= nl2br(htmlspecialchars(
                                trim($data['rejection_reason'] ?? '')
                            )) ?>
                        </div>
                    </div>

                </div>
            </td>
        </tr>
        <?php endif; ?>
      </tbody>
  </table>

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
