<?php
// Presentation shared by the pending queue and recommended list.
$showBulkProcessing = !empty($_SESSION['is_recommender']) || !empty($_SESSION['is_approver']) || !empty($_SESSION['is_private_approver']);
?>
<div class="page-header bulk-page-header">
 <div class="bulk-page-heading">
  <h1 class="mb-1"><?= htmlspecialchars($bulkPageTitle ?? 'Pending Gas Slips', ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="page-subtitle mb-0"><?= htmlspecialchars($bulkPageSubtitle ?? 'Overview of gas slip requests awaiting action', ENT_QUOTES, 'UTF-8') ?></p>
 </div>
</div>
<?php
if (!$showBulkProcessing && ($bulkPageContext ?? '') === 'approved') {
    include __DIR__ . '/approved_slips_toolbar.php';
}
if ($showBulkProcessing):
$bulkPageContext = $bulkPageContext ?? 'pending';
$bulkCsrfKey = 'bulk_csrf_' . $bulkPageContext;
$_SESSION[$bulkCsrfKey] = $_SESSION[$bulkCsrfKey] ?? bin2hex(random_bytes(32));
?>
<section id="bulk-processing" aria-label="Bulk processing"
 data-page="<?= htmlspecialchars($bulkPageContext, ENT_QUOTES, 'UTF-8') ?>" data-user="<?= (int)($_SESSION['user_id'] ?? 0) ?>" data-csrf="<?= htmlspecialchars($_SESSION[$bulkCsrfKey], ENT_QUOTES, 'UTF-8') ?>">
 <div class="bulk-context">
  <span id="bulk-message" class="bulk-message" role="status" aria-live="polite"></span>
  <button type="button" id="bulk-retry" class="btn btn-sm btn-outline-secondary" hidden>Retry</button>
 </div>
 <div class="bulk-navigation">
 <div class="bulk-nav-left">
 <div id="bulk-tabs" role="tablist" aria-label="Gas slip groups"></div>
 <button type="button" class="bulk-manage" id="bulk-manage" title="Manage Groups" aria-label="Manage Groups"><i class="fas fa-users-cog" aria-hidden="true"></i></button>
 </div>
 <?php if ($bulkPageContext === 'pending'): ?>
  <div class="bulk-actions" hidden><button type="button" id="bulk-action" class="btn btn-sm btn-primary" disabled>Loading…</button>
   <button type="button" id="bulk-reject" class="btn btn-sm btn-danger" hidden disabled>Reject Selected (0)</button></div>
 <?php endif; ?>
 <?php if ($bulkPageContext === 'approved') { include __DIR__ . '/approved_slips_toolbar.php'; } ?>
 </div>
</section>
<script src="assets/js/bulk-processing.js?v=<?= filemtime(__DIR__ . '/../assets/js/bulk-processing.js') ?>" defer></script>
<?php endif; ?>
