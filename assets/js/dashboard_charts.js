/**
 * Visualizaciones y Gráficas Estadísticas con Chart.js
 * Liceo Bolivariano "Rossana Melidei" - Dashboard Administrativo
 */

document.addEventListener('DOMContentLoaded', () => {
    initAttendanceCharts();
});

function initAttendanceCharts() {
    // 1. Gráfico Circular (Doughnut): Distribución de Asistencia Hoy
    const ctxDistribution = document.getElementById('chartAttendanceToday');
    if (ctxDistribution && window.Chart) {
        const rawPresentes = parseInt(ctxDistribution.dataset.presentes || 18, 10);
        const rawRetardos = parseInt(ctxDistribution.dataset.retardos || 3, 10);
        const rawAusentes = parseInt(ctxDistribution.dataset.ausentes || 4, 10);

        new Chart(ctxDistribution, {
            type: 'doughnut',
            data: {
                labels: ['Presentes', 'Retardos', 'Ausencias'],
                datasets: [{
                    data: [rawPresentes, rawRetardos, rawAusentes],
                    backgroundColor: [
                        '#34d399', // Mint Pastel
                        '#fbbf24', // Amber Pastel
                        '#f87171'  // Rose Pastel
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '600' },
                            padding: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.85)',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                cutout: '68%'
            }
        });
    }

    // 2. Gráfico de Tendencia Semanal (Lunes a Viernes)
    const ctxWeekly = document.getElementById('chartWeeklyTrend');
    if (ctxWeekly && window.Chart) {
        new Chart(ctxWeekly, {
            type: 'bar',
            data: {
                labels: ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'],
                datasets: [
                    {
                        label: 'Presentes (%)',
                        data: [92, 88, 95, 89, 94],
                        backgroundColor: 'rgba(96, 165, 250, 0.8)', // Pastel Blue
                        borderRadius: 8,
                        borderSkipped: false
                    },
                    {
                        label: 'Ausencias (%)',
                        data: [8, 12, 5, 11, 6],
                        backgroundColor: 'rgba(251, 113, 133, 0.8)', // Pastel Rose
                        borderRadius: 8,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: value => value + '%'
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '600' }
                        }
                    }
                }
            }
        });
    }

    // 3. Ausentismo por Nivel / Año de Instrucción
    const ctxLevels = document.getElementById('chartAbsenteeismLevels');
    if (ctxLevels && window.Chart) {
        new Chart(ctxLevels, {
            type: 'bar',
            data: {
                labels: ['1er Año', '2do Año', '3er Año', '4to Año', '5to Año'],
                datasets: [{
                    label: 'Índice de Ausentismo Promedio (%)',
                    data: [4.2, 5.8, 8.1, 6.4, 9.7],
                    backgroundColor: [
                        '#a7f3d0',
                        '#bfdbfe',
                        '#fde68a',
                        '#fbcfe8',
                        '#fecdd3'
                    ],
                    borderColor: '#94a3b8',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: val => val + '%'
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    y: {
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
}
