const deptApprovers =
    window.departmentApprovers || {};

document.addEventListener('DOMContentLoaded', function () {

    /* =====================================================
    ADD RECOMMENDER
    ===================================================== */

    document.addEventListener('click', function (e) {

        const addBtn = e.target.closest('.add-recommender-row');

        if (!addBtn) {
            return;
        }

        const userId = addBtn.dataset.id;

        fetch('save_recommender.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: new URLSearchParams({
                user_id: userId
            })

        })
        .then(response => response.json())

        .then(data => {

            if (data.success) {

                location.reload();

            } else {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });

            }

        })

        .catch(error => {

            console.error(error);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save recommender.'
            });

        });

    });

    /* =====================================================
    REMOVE ROW
    ===================================================== */

    document.addEventListener('click', function (e) {

        const removeBtn = e.target.closest('.remove-row');

        if (!removeBtn) {
            return;
        }

        const row = removeBtn.closest('tr');

        if (!row) {
            return;
        }

        const tbody = row.closest('tbody');

        /* ---------------------------------------------
        GET USER INFO
        --------------------------------------------- */

        const userId = row.dataset.userId;

        const nameElement =
            row.querySelector('td:nth-child(2) .fw-semibold');

        const designationElement =
            row.querySelector('td:nth-child(2) .text-muted');

        const name = nameElement
            ? nameElement.textContent.trim()
            : '';

        const designation = designationElement
            ? designationElement.textContent.trim()
            : '';

        /* ---------------------------------------------
        DELETE FROM DATABASE
        --------------------------------------------- */

        fetch('delete_recommender.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: new URLSearchParams({
                user_id: userId
            })

        })
        .then(response => response.json())

        .then(data => {

            if (!data.success) {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });

                return;
            }

            /* ---------------------------------------------
            RESTORE TO MODAL
            --------------------------------------------- */

            let modalTableBody = null;

            if (tbody.id === 'recommenderTableBody') {

                modalTableBody = document.querySelector(
                    '#recommenderModal tbody'
                );
            }

            if (modalTableBody) {

                const emptyModalRow = modalTableBody.querySelector(
                    '.empty-modal-row'
                );

                if (emptyModalRow) {
                    emptyModalRow.remove();
                }

                modalTableBody.insertAdjacentHTML('beforeend', `

                    <tr
                        class="add-recommender-row modal-select-row"

                        data-id="${userId}"
                        data-name="${name}"
                        data-designation="${designation}"
                    >

                        <td class="fw-semibold">
                            ${name}
                        </td>

                        <td>
                            <small class="text-muted">
                                ${designation || '-'}
                            </small>
                        </td>

                    </tr>

                `);

                /* ---------------------------------------------
                SORT MODAL ROWS
                --------------------------------------------- */

                const rows = Array.from(
                    modalTableBody.querySelectorAll('tr')
                );

                rows.sort((a, b) => {

                    const nameA = a.querySelector('td')
                        .textContent
                        .trim()
                        .toLowerCase();

                    const nameB = b.querySelector('td')
                        .textContent
                        .trim()
                        .toLowerCase();

                    return nameA.localeCompare(nameB);

                });

                /* ---------------------------------------------
                RE-APPEND SORTED ROWS
                --------------------------------------------- */

                rows.forEach(sortedRow => {
                    modalTableBody.appendChild(sortedRow);
                });

            }

            /* ---------------------------------------------
            REMOVE ROW
            --------------------------------------------- */

            row.remove();

            /* ---------------------------------------------
            RENUMBER ROWS
            --------------------------------------------- */

            tbody.querySelectorAll('tr').forEach((tr, index) => {

                const firstCell = tr.querySelector('td');

                if (firstCell) {
                    firstCell.textContent = index + 1;
                }

            });

            /* ---------------------------------------------
            EMPTY TABLE STATE
            --------------------------------------------- */

            if (tbody.querySelectorAll('tr').length === 0) {

                tbody.innerHTML = `
                    <tr class="empty-row">

                        <td colspan="3"
                            class="text-center text-muted py-4">

                            <div class="mb-2">
                                <i class="fas fa-users-slash fs-3 opacity-50"></i>
                            </div>

                            No recommenders assigned.

                        </td>

                    </tr>
                `;
            }

        })

        .catch(error => {

            console.error(error);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to remove recommender.'
            });

        });

    });


    /* =====================================================
    SELECT PRIMARY APPROVER
    ===================================================== */

    document.addEventListener('click', function (e) {

        const row = e.target.closest(
            '.select-primary-approver'
        );

        if (!row) {
            return;
        }

        const userId      = row.dataset.id;
        const name        = row.dataset.name;
        const designation = row.dataset.designation;

        fetch('save_primary_approver.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: new URLSearchParams({
                user_id: userId
            })

        })
        .then(response => response.json())

        .then(data => {

            if (!data.success) {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });

                return;
            }

            /* UPDATE UI */

            const primaryName = document.querySelector(
                '.primary-approver-name'
            );

            primaryName.textContent = name;

            primaryName.classList.remove('text-muted');

            primaryName.classList.add(
                'fw-bold',
                'text-dark'
            );

            document.querySelector(
                '.primary-approver-designation'
            ).textContent = designation || '-';

            document.getElementById(
                'primaryApproverInput'
            ).value = userId;

            /* CLOSE MODAL */

            bootstrap.Modal.getInstance(
                document.getElementById(
                    'primaryApproverModal'
                )
            ).hide();

            // Swal.fire({
            //     icon: 'success',
            //     title: 'Saved',
            //     text: 'Primary approver updated.',
            //     timer: 1200,
            //     showConfirmButton: false
            // });
            location.reload();

        })

        .catch(error => {

            console.error(error);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save approver.'
            });

        });

    });


    /* =====================================================
    SELECT SECONDARY APPROVER
    ===================================================== */

    document.addEventListener('click', function (e) {

        const row = e.target.closest(
            '.select-secondary-approver'
        );

        if (!row) {
            return;
        }

        console.log('Secondary approver clicked:', row);

        const userId      = row.dataset.id;
        const name        = row.dataset.name;
        const designation = row.dataset.designation;

        fetch('save_secondary_approver.php', {

            method: 'POST',

            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded'
            },

            body: new URLSearchParams({
                user_id: userId
            })

        })
        .then(response => response.json())

        .then(data => {

            if (!data.success) {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });

                return;
            }

            /* UPDATE UI */

            const secondaryName = document.querySelector(
                '.secondary-approver-name'
            );

            secondaryName.textContent = name;

            secondaryName.classList.remove('text-muted');

            secondaryName.classList.add(
                'fw-bold',
                'text-dark'
            );

            document.querySelector(
                '.secondary-approver-designation'
            ).textContent = designation || '-';

            document.getElementById(
                'secondaryApproverInput'
            ).value = userId;

            /* CLOSE MODAL */

            bootstrap.Modal.getInstance(
                document.getElementById(
                    'secondaryApproverModal'
                )
            ).hide();

            location.reload();

        })

        .catch(error => {

            console.error(error);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save secondary approver.'
            });

        });

    });

    /* =====================================================
    CLEAR SECONDARY APPROVER
    ===================================================== */

    const clearSecondaryApproverBtn =
        document.getElementById('clearSecondaryApproverBtn');

    if (clearSecondaryApproverBtn) {

        clearSecondaryApproverBtn.addEventListener(
            'click',
            function () {

                Swal.fire({
                    icon: 'warning',
                    title: 'Clear Secondary Approver?',
                    text: 'The secondary approver will be removed.',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Clear',
                    cancelButtonText: 'Cancel'
                })
                .then(result => {

                    if (!result.isConfirmed) {
                        return;
                    }

                    fetch('clear_secondary_approver.php', {

                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded'
                        }

                    })

                    .then(response => response.json())

                    .then(data => {

                        if (!data.success) {

                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message
                            });

                            return;
                        }

                        location.reload();

                    })

                    .catch(error => {

                        console.error(error);

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text:
                                'Failed to clear secondary approver.'
                        });

                    });

                });

            }
        );

    }


    /* =====================================================
    SELECT PRIVATE VEHICLE APPROVER
    ===================================================== */

    document.addEventListener('click', function (e) {

        const row = e.target.closest(
            '.select-private-vehicle-approver'
        );

        if (!row) {
            return;
        }

        const userId      = row.dataset.id;
        const name        = row.dataset.name;
        const designation = row.dataset.designation;

        fetch('save_private_vehicle_approver.php', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: new URLSearchParams({
                user_id: userId
            })

        })

        .then(response => response.json())

        .then(data => {

            if (!data.success) {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });

                return;
            }

            /* UPDATE UI */

            const approverName = document.querySelector(
                '.private-vehicle-approver-name'
            );

            approverName.textContent = name;

            approverName.classList.remove('text-muted');

            approverName.classList.add(
                'fw-bold',
                'text-dark'
            );

            document.querySelector(
                '.private-vehicle-approver-designation'
            ).textContent = designation || '-';

            document.getElementById(
                'privateVehicleApproverInput'
            ).value = userId;

            /* CLOSE MODAL */

            bootstrap.Modal.getInstance(
                document.getElementById(
                    'privateVehicleApproverModal'
                )
            ).hide();

            location.reload();

        })

        .catch(error => {

            console.error(error);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save approver.'
            });

        });

    });

    /* =========================================================
    AREA CHANGE
    ========================================================= */

    const areaSelect = document.getElementById('areaSelect');
    const fuelSupplierInput = document.getElementById('fuelSupplierInput');

    if (areaSelect) {

        areaSelect.addEventListener('change', function () {

            const selectedOption =
                this.options[this.selectedIndex];

            fuelSupplierInput.value =
                selectedOption.dataset.fuel || '';

        });

    }

    /* =========================================================
    SAVE FUEL SETTINGS
    ========================================================= */

    const saveFuelBtn =
        document.getElementById('saveFuelSettingsBtn');

    if (saveFuelBtn) {

        saveFuelBtn.addEventListener('click', function () {

            let areaId = '';

            if (areaSelect) {

                areaId = areaSelect.value;

            } else {

                const currentArea =
                    document.getElementById('currentAreaId');

                areaId = currentArea
                    ? currentArea.value
                    : '';
            }

            const fuelSupplier =
                fuelSupplierInput.value.trim();

            fetch('save_fuel_settings.php', {

                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body:
                    'area_id=' + encodeURIComponent(areaId) +
                    '&fuel_supplier=' + encodeURIComponent(fuelSupplier)

            })
            .then(response => response.json())
            .then(data => {

                if (data.success) {

                    // update current option data-fuel
                    if (areaSelect) {

                        const selectedOption =
                            areaSelect.options[
                                areaSelect.selectedIndex
                            ];

                        selectedOption.dataset.fuel =
                            fuelSupplier;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Saved',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });

                } else {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });

                }

            })
            .catch(error => {

                console.error(error);

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong.'
                });

            });

        });

    }

    /* =========================================================
    OFFICE ADDRESS SETTINGS
    ========================================================= */

    const officeAddressAreaSelect =
        document.getElementById('officeAddressAreaSelect');

    const officeAddressInput =
        document.getElementById('officeAddressInput');

    const saveOfficeAddressBtn =
        document.getElementById('saveOfficeAddressBtn');


    function loadOfficeAddress() {

        if (!officeAddressAreaSelect || !officeAddressInput) {
            return;
        }

        const selectedOption =
            officeAddressAreaSelect.options[
                officeAddressAreaSelect.selectedIndex
            ];

        officeAddressInput.value =
            selectedOption.dataset.address || '';

    }


    if (officeAddressAreaSelect) {

        loadOfficeAddress();

        officeAddressAreaSelect.addEventListener(
            'change',
            loadOfficeAddress
        );

    }


    if (saveOfficeAddressBtn) {

        saveOfficeAddressBtn.addEventListener(
            'click',
            function () {

                const areaId =
                    officeAddressAreaSelect.value;

                const officeAddress =
                    officeAddressInput.value.trim();

                fetch('save_office_address.php', {

                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded'
                    },

                    body:
                        'area_id=' +
                        encodeURIComponent(areaId) +
                        '&office_address=' +
                        encodeURIComponent(officeAddress)

                })
                .then(response => response.json())

                .then(data => {

                    if (data.success) {

                        const selectedOption =
                            officeAddressAreaSelect.options[
                                officeAddressAreaSelect.selectedIndex
                            ];

                        selectedOption.dataset.address =
                            officeAddress;

                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        });

                    } else {

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });

                    }

                })

                .catch(error => {

                    console.error(error);

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Something went wrong.'
                    });

                });

            }
        );

    }

        
    const departmentSelector =
        document.getElementById('departmentSelector');

    const approverName =
        document.querySelector('.department-approver-name');

    const approverDesignation =
        document.querySelector('.department-approver-designation');

    const approverInput =
        document.getElementById('departmentApproverInput');

    function loadDepartmentApprover() {

        const departmentId = departmentSelector.value;

        const approver =
            deptApprovers[departmentId];

        if (!approver) {

            approverName.textContent =
                'No approver assigned.';

            approverDesignation.textContent = '';

            approverInput.value = '';

            return;
        }

        approverName.textContent =
            approver.full_name;

        approverDesignation.textContent =
            approver.designation ?? '';

        approverInput.value =
            approver.user_id;
    }

    if (
        departmentSelector &&
        approverName &&
        approverDesignation &&
        approverInput
    ) {

        departmentSelector.addEventListener(
            'change',
            loadDepartmentApprover
        );

        loadDepartmentApprover();

    }

    document
    .querySelectorAll('.select-department-approver')
    .forEach(row => {

        row.addEventListener('click', function () {

            const userId =
                this.dataset.id;

            const fullName =
                this.dataset.name;

            const designation =
                this.dataset.designation;

            const departmentId =
                departmentSelector.value;

            fetch('save_department_approver.php', {

                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body: new URLSearchParams({

                    department_id: departmentId,
                    user_id: userId

                })

            })

            .then(response => response.json())

            .then(data => {

                if (!data.success) {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });

                    return;
                }

                /* UPDATE UI */

                approverName.textContent =
                    fullName;

                approverDesignation.textContent =
                    designation;

                approverInput.value =
                    userId;

                /* UPDATE JS STATE */

                deptApprovers[departmentId] = {

                    user_id: userId,
                    full_name: fullName,
                    designation: designation

                };

                /* CLOSE MODAL */

                bootstrap.Modal
                    .getInstance(
                        document.getElementById(
                            'departmentApproverModal'
                        )
                    )
                    .hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Saved',
                    text: 'Department approver updated.',
                    timer: 1200,
                    showConfirmButton: false
                });

            })

            .catch(error => {

                console.error(error);

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save approver.'
                });

            });

        });

    });

    const departmentApproverModal =
        document.getElementById(
            'departmentApproverModal'
        );

    if (departmentApproverModal) {

        departmentApproverModal
        .addEventListener('show.bs.modal', function () {

            const selectedDepartmentId =
                departmentSelector.value;

            document
            .querySelectorAll('.select-department-approver')
            .forEach(row => {

                row.style.display =
                    row.dataset.departmentId ===
                    selectedDepartmentId
                        ? ''
                        : 'none';

            });

        });

    }

    /* =========================================================
    RECOMMENDER ONLY APPROVAL
    ========================================================= */

    document.addEventListener('change', function (e) {

        const toggle = e.target.closest(
            '.recommender-only-toggle'
        );

        if (!toggle) {
            return;
        }

        const areaId = toggle.dataset.areaId;

        /*
        The checkbox has already changed.
        This is the state the user wants.
        */
        const enabled = toggle.checked;

        /*
        Immediately return it to its previous state.
        It will only permanently change after confirmation.
        */
        toggle.checked = !enabled;

        const actionText = enabled
            ? 'Enable'
            : 'Disable';

        const confirmText = enabled
            ? 'This will allow any one recommender to provide the final approval for regular vehicle gas slips in this area. The approver step will be skipped. Private vehicle gas slips are not affected.'
            : 'Regular vehicle gas slips in this area will return to the normal workflow: Recommender → Approver → Print.';

        Swal.fire({
            icon: enabled ? 'warning' : 'question',
            title: `${actionText} Recommender Only Approval?`,
            text: confirmText,
            showCancelButton: true,
            confirmButtonText: enabled
                ? 'Yes, Enable'
                : 'Yes, Disable',
            cancelButtonText: 'Cancel',
            confirmButtonColor: enabled
                ? '#dc3545'
                : '#dc3545'
        })
        .then(result => {

            if (!result.isConfirmed) {
                return;
            }

            fetch('save_recommender_only_setting.php', {

                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body: new URLSearchParams({
                    area_id: areaId,
                    recommender_only: enabled ? 1 : 0
                })

            })

            .then(response => response.json())

            .then(data => {

                if (!data.success) {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });

                    return;
                }

                /*
                Change the toggle only after successful save.
                */
                toggle.checked = enabled;

                Swal.fire({
                    icon: 'success',
                    title: 'Saved',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                });

            })

            .catch(error => {

                console.error(error);

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text:
                        'Failed to save Recommender Only Approval setting.'
                });

            });

        });

    });


});

