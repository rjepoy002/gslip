const d = window.dashboardData;
const ctx = document.getElementById('approvedLineChart').getContext('2d');
    
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            'Jan','Feb','Mar','Apr','May','Jun',
            'Jul','Aug','Sep','Oct','Nov','Dec'
        ],
        datasets: [{
            label: 'Approved Gas Slips',
            data: d.monthlyData,
            tension: 0.3,
            fill: true,

            // ✅ Green styling
            borderColor: '#198754',                 // Bootstrap green
            backgroundColor: 'rgba(25,135,84,0.2)', // Light green fill
            pointBackgroundColor: '#198754',
            pointBorderColor: '#198754',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                suggestedMin: 0,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

document.addEventListener('DOMContentLoaded', function () {

    const fuelCanvas = document.getElementById('fuelBarChart');

    if (!fuelCanvas) return;

    const fuelCtx = fuelCanvas.getContext('2d');

    new Chart(fuelCtx, {

        type: 'bar',

        data: {
            labels: [
                'Jan','Feb','Mar','Apr','May','Jun',
                'Jul','Aug','Sep','Oct','Nov','Dec'
            ],

            datasets: [

                {
                    label: 'Diesel',
                    data: d.dieselData,

                    backgroundColor: 'rgba(25,135,84,0.7)',
                    borderColor: '#198754',
                    borderWidth: 1
                },

                {
                    label: 'Unleaded',
                    data: d.unleadedData,

                    backgroundColor: 'rgba(13,110,253,0.7)',
                    borderColor: '#0d6efd',
                    borderWidth: 1
                }

            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }

    });

});



const topVehicleLabels = d.topVehicleLabels;
const topVehicleData   = d.topVehicleData;

new Chart(document.getElementById('topVehiclesChart'), {
    type: 'horizontalBar',
    data: {
        labels: topVehicleLabels,
        datasets: [{
            label: 'Fuel Used (Liters)',
            data: topVehicleData,
            backgroundColor: [
                'rgba(13,110,253,1.00)',
                'rgba(13,110,253,0.92)',
                'rgba(13,110,253,0.84)',
                'rgba(13,110,253,0.76)',
                'rgba(13,110,253,0.68)',
                'rgba(13,110,253,0.60)',
                'rgba(13,110,253,0.52)',
                'rgba(13,110,253,0.44)',
                'rgba(13,110,253,0.36)',
                'rgba(13,110,253,0.28)'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
            display: false
        },
        tooltips: {
            callbacks: {
                label: function(tooltipItem) {
                    return tooltipItem.xLabel.toLocaleString() + ' L';
                }
            }
        },
        scales: {
            xAxes: [{
                ticks: {
                    beginAtZero: true,
                    fontColor: '#6c757d'
                },
                gridLines: {
                    color: '#f1f3f5'
                }
            }],
            yAxes: [{
                ticks: {
                    fontColor: '#495057'
                },
                gridLines: {
                    display: false
                }
            }]
        }
    }
});

if (d.canViewDepartmentChart) {

    const deptCtx = document.getElementById('departmentPieChart').getContext('2d');

    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: d.deptLabels,
            datasets: [{
                label: 'Approved Gas Slips',
                data: d.deptTotals,
                backgroundColor: [
                    '#198754', '#198754', '#198754', '#198754', '#198754', //ASOD
                    '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', '#0d6efd', //ANOD
                    '#ffc107',
                    '#dc3545',
                    '#6f42c1',
                    '#fd7e14',
                    '#20c997',
                    '#6610f2',
                    '#0dcaf0',
                    '#adb5bd',
                    '#343a40',
                    '#6c757d'
                ],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,

            legend: {
                display: false
            },

            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return data.datasets[0].data[tooltipItem.index] + ' approved slip(s)';
                    }
                }
            },

            scales: {
                xAxes: [{
                    ticks: {
                        beginAtZero: true,
                        precision: 0
                    },
                    scaleLabel: {
                        display: true
                    }
                }],
                yAxes: [{
                    ticks: {
                        autoSkip: false
                    }
                }]
            },

            layout: {
                padding: 20
            }
        }
    });
}


