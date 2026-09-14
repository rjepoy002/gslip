<!-- FUEL SUPPLIER SETTINGS -->
<div class="col-md-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body">

            <h5 class="mb-4">
                <i class="fas fa-gas-pump me-2"></i>
                Fuel Supplier Settings
            </h5>

            <?php if ($canEditFuelSupplier): ?>

                <!-- AREA DROPDOWN -->
                <div class="mb-3">

                    <label class="form-label fw-semibold">
                        Area
                    </label>

                    <select 
                        id="areaSelect"
                        class="form-select"
                    >
                        <?php foreach ($areas as $areaRow): ?>

                            <option 
                                value="<?= $areaRow['id']; ?>"
                                data-fuel="<?= htmlspecialchars($areaRow['fuel_supplier']); ?>"
                                <?= ($areaRow['id'] == $area) ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars($areaRow['area_name']); ?>
                            </option>

                        <?php endforeach; ?>
                    </select>

                </div>

            <?php endif; ?>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Fuel Supplier
                </label>
                

                <input 
                    type="text"
                    name="fuel_supplier"
                    id="fuelSupplierInput"
                    class="form-control"
                    value="<?= htmlspecialchars($fuelSupplier ?? ''); ?>"
                    <?= !$canEditFuelSupplier ? 'readonly' : ''; ?>
                >

                <?php if (!$canEditFuelSupplier): ?>
                    <small class="text-muted">
                        Only ISD - Puerto Princesa [Main Office]
                        can modify this setting.
                    </small>
                <?php endif; ?>

            </div>

            <div class="d-flex justify-content-end mt-4">

                <button 
                    type="button"
                    class="btn btn-primary"
                    id="saveFuelSettingsBtn"
                    <?= !$canEditFuelSupplier ? 'hidden' : ''; ?>
                >
                    <i class="fas fa-save me-1"></i>
                    Save Settings
                </button>

            </div>

        </div>
    </div>
</div>