<div class="col-md-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-print me-2"></i>
                        Print Once
                    </h5>
                    <p class="text-muted mb-0">
                        When enabled, an approved gas slip can only be printed once.
                        Turn this off to allow printed gas slips to be reprinted.
                    </p>
                </div>

                <div class="form-check form-switch fs-4 mb-0">
                    <input
                        class="form-check-input print-once-toggle"
                        type="checkbox"
                        role="switch"
                        id="printOnceToggle"
                        <?= $printOnceEnabled ? 'checked' : '' ?>
                    >
                </div>
            </div>
        </div>
    </div>
</div>
