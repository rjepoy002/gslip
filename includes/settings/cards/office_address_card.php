<?php if ($canEditFuelSupplier): ?>

<!-- OFFICE ADDRESS SETTINGS -->
<div class="col-md-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body">

            <h5 class="mb-4">
                <i class="fas fa-building me-2"></i>
                Office Address Settings
            </h5>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Area
                </label>

                <select
                    id="officeAddressAreaSelect"
                    class="form-select"
                >
                    <?php foreach ($areas as $areaRow): ?>

                        <option
                            value="<?= $areaRow['id']; ?>"
                            data-address="<?= htmlspecialchars(
                                $areaRow['office_address'] ?? '',
                                ENT_QUOTES
                            ); ?>"
                            <?= ($areaRow['id'] == $area) ? 'selected' : ''; ?>
                        >
                            <?= htmlspecialchars($areaRow['area_name']); ?>
                        </option>

                    <?php endforeach; ?>
                </select>

            </div>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Office Address
                </label>

                <input
                    type="text"
                    id="officeAddressInput"
                    class="form-control"
                    placeholder="Enter office address"
                >

            </div>

            <div class="d-flex justify-content-end mt-4">

                <button
                    type="button"
                    class="btn btn-primary"
                    id="saveOfficeAddressBtn"
                >
                    <i class="fas fa-save me-1"></i>
                    Save Address
                </button>

            </div>

        </div>
    </div>
</div>

<?php endif; ?>