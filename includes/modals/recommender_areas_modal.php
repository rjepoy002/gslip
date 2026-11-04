<?php if (!empty($multiAreaRecommenders) && !empty($canManageRecommenders)): ?>
<div class="modal fade" id="recommenderAreasModal" tabindex="-1" aria-labelledby="recommenderAreasTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="recommenderAreasForm">
            <div class="modal-header">
                <h5 class="modal-title" id="recommenderAreasTitle"><i class="fas fa-map-marker-alt me-2" aria-hidden="true"></i>Edit Assigned Areas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold text-break text-uppercase" id="recommenderAreasName"></p>
                <input type="hidden" name="department_id" value="<?= (int) $recommenderDepartment; ?>">
                <input type="hidden" name="user_id" id="recommenderAreasUser">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['recommender_csrf'], ENT_QUOTES); ?>">
                <label for="recommenderAreasSearch" class="form-label">Search areas</label>
                <input type="search" class="form-control mb-3" id="recommenderAreasSearch" placeholder="Type an area name…" autocomplete="off" aria-controls="recommenderAreasList">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="recommenderAreasSelectAll">Select All</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="recommenderAreasClearAll">Clear All</button>
                    <span class="small text-muted ms-auto" id="recommenderAreasCount" aria-live="polite"></span>
                </div>
                <fieldset class="border rounded p-2 mb-2" id="recommenderAreasList" style="max-height: min(40vh, 320px); overflow-y: auto; overscroll-behavior: contain;">
                    <legend class="visually-hidden">Authorized department areas</legend>
                    <?php foreach ($recommenderAreas as $areaOption): ?>
                    <label class="area-checkbox-option d-flex align-items-center gap-2 rounded px-2 py-2" style="min-height: 44px; cursor: pointer;">
                        <input class="form-check-input flex-shrink-0 m-0" type="checkbox" name="area_ids[]" value="<?= (int) $areaOption['id']; ?>">
                        <span class="text-break"><?= htmlspecialchars($areaOption['area_name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                    <p class="small text-muted text-center my-3" id="recommenderAreasNoResults" hidden>No matching areas.</p>
                </fieldset>
                <div class="alert alert-danger mb-0" id="recommenderAreasError" role="alert" hidden></div>
            </div>
            <div class="modal-footer d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="recommenderAreasSave">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
