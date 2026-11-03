document.addEventListener("DOMContentLoaded", () => {
    const dashboard = document.querySelector("[data-dashboard-charts]");

    if (!dashboard) {
        return;
    }

    const layout = dashboard.querySelector("[data-dashboard-layout]");
    const canvas = dashboard.querySelector("[data-dashboard-canvas]");
    const emptyState = dashboard.querySelector("[data-chart-empty]");
    const categoryLabel = dashboard.querySelector("[data-chart-category-label]");
    const chartTitle = dashboard.querySelector("[data-chart-title]");
    const chartSwitcher = dashboard.querySelector("[data-chart-switcher]");
    const chartDisplay = dashboard.querySelector(".dashboard-chart-display");
    const periodControls = dashboard.querySelector("[data-chart-period-controls]");
    const periodSelect = dashboard.querySelector("[data-chart-period]");
    const categoryButtons = [...dashboard.querySelectorAll("[data-dashboard-category]")];
    const viewButtons = [...dashboard.querySelectorAll("[data-chart-view]")];
    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;
    let chartData;
    let activeCategory = null;
    let activeView = null;
    let chartInstance = null;

    function showEmptyState(message) {
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        canvas.hidden = true;
        emptyState.textContent = message;
        emptyState.hidden = false;
    }

    try {
        chartData = JSON.parse(dashboard.dataset.chartData || "");
    } catch (error) {
        console.error("BrickLab dashboard chart data could not be read.", error);
        showEmptyState("Chart data could not be loaded. Please refresh the page.");
        return;
    }

    const css = getComputedStyle(document.documentElement);
    const colors = {
        primary: css.getPropertyValue("--color-primary").trim(),
        accent: css.getPropertyValue("--color-accent").trim(),
        text: css.getPropertyValue("--color-text").trim(),
        muted: css.getPropertyValue("--color-text-muted").trim(),
        border: css.getPropertyValue("--color-border").trim(),
        surface: css.getPropertyValue("--color-surface").trim(),
        success: "#86efac",
    };
    const fontFamily = getComputedStyle(document.body).fontFamily;

    const doughnutCenterPlugin = {
        id: "bricklabDoughnutCenter",
        beforeDraw(chart, args, options) {
            const arc = chart.getDatasetMeta(0).data[0];
            const chartArea = chart.chartArea;

            if (!arc || !chartArea) {
                return;
            }

            const { ctx } = chart;
            const radius = arc.innerRadius * 0.78;

            ctx.save();
            ctx.beginPath();
            ctx.arc(arc.x, arc.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = colors.surface;
            ctx.fill();
            ctx.strokeStyle = colors.border;
            ctx.lineWidth = 1;
            ctx.stroke();
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillStyle = colors.text;
            ctx.font = `700 ${Math.min(32, Math.max(24, radius * 0.52))}px ${fontFamily}`;
            ctx.fillText(String(options.total), arc.x, arc.y - 7);
            ctx.fillStyle = colors.muted;
            ctx.font = `600 10px ${fontFamily}`;
            ctx.letterSpacing = "1px";
            ctx.fillText(options.label, arc.x, arc.y + 17);
            ctx.restore();
        },
    };

    function doughnutSegmentColor(context) {
        const palette = [
            [colors.primary, "#c4b5fd"],
            [colors.accent, "#a5f3fc"],
            [colors.success, "#bbf7d0"],
        ];
        const colorPair = palette[context.dataIndex % palette.length];
        const { chart } = context;
        const { ctx, chartArea } = chart;

        if (!chartArea) {
            return colorPair[0];
        }

        const gradient = ctx.createLinearGradient(
            chartArea.left,
            chartArea.bottom,
            chartArea.right,
            chartArea.top
        );
        gradient.addColorStop(0, colorPair[0]);
        gradient.addColorStop(1, colorPair[1]);

        return gradient;
    }

    function chartDefinition(view) {
        const period = periodSelect.value;

        if (view === "build-activity") {
            const series = chartData.buildActivity[period];

            return {
                category: "Builds",
                title: "Build Activity",
                type: "bar",
                series,
                dataset: {
                    label: "Builds created",
                    backgroundColor: colors.primary,
                    hoverBackgroundColor: colors.accent,
                    borderRadius: 5,
                    maxBarThickness: 30,
                },
                emptyMessage: `No builds were created in the last ${period === "weekly" ? "12 weeks" : "12 months"}.`,
            };
        }

        if (view === "build-status") {
            return {
                category: "Builds",
                title: "Build Status",
                type: "doughnut",
                series: chartData.buildStatus,
                dataset: {
                    backgroundColor: doughnutSegmentColor,
                    borderWidth: 0,
                    borderRadius: 8,
                    spacing: 4,
                    hoverOffset: reducedMotion ? 0 : 8,
                },
                emptyMessage: "Create a build to see your build status breakdown.",
            };
        }

        if (view === "task-status") {
            return {
                category: "Tasks",
                title: "Task Status",
                type: "doughnut",
                series: chartData.taskStatus,
                dataset: {
                    backgroundColor: doughnutSegmentColor,
                    borderWidth: 0,
                    borderRadius: 8,
                    spacing: 4,
                    hoverOffset: reducedMotion ? 0 : 8,
                },
                emptyMessage: "Create a task to see your task status breakdown.",
            };
        }

        const series = chartData.taskCompletionActivity[period];

        return {
            category: "Tasks",
            title: "Task Completion Activity",
            type: "line",
            series,
            dataset: {
                label: "Tasks completed",
                borderColor: colors.accent,
                backgroundColor: colors.accent,
                pointBackgroundColor: colors.primary,
                pointBorderColor: colors.surface,
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 2,
                tension: 0.3,
                fill: false,
            },
            emptyMessage: `No tasks were completed in the last ${period === "weekly" ? "12 weeks" : "12 months"}.`,
        };
    }

    function updateViewButtons() {
        viewButtons.forEach((button) => {
            const view = button.dataset.chartView;
            const belongsToCategory = activeCategory === "builds"
                ? view.startsWith("build-")
                : view.startsWith("task-");

            button.hidden = !belongsToCategory;
            button.setAttribute("aria-pressed", String(view === activeView));
        });
    }

    function updatePeriodControls() {
        const isActivity = activeView === "build-activity"
            || activeView === "task-completion-activity";

        periodControls.hidden = !isActivity;
        periodSelect.disabled = !isActivity;
    }

    function renderChart() {
        const definition = chartDefinition(activeView);
        const series = definition.series;

        categoryLabel.textContent = definition.category;
        chartTitle.textContent = definition.title;
        canvas.setAttribute("aria-label", `${definition.title} chart`);
        updateViewButtons();
        updatePeriodControls();

        if (
            !series
            || !Array.isArray(series.labels)
            || !Array.isArray(series.values)
            || series.labels.length !== series.values.length
            || !series.values.every((value) => Number.isInteger(value) && value >= 0)
        ) {
            console.error(`BrickLab received invalid ${definition.title} chart data.`);
            showEmptyState("Chart data could not be loaded. Please refresh the page.");
            return;
        }

        if (series.values.every((value) => value === 0)) {
            showEmptyState(definition.emptyMessage);
            return;
        }

        if (!window.Chart) {
            console.error("BrickLab charts could not load because Chart.js is unavailable.");
            showEmptyState("Charts are temporarily unavailable. Please refresh the page.");
            return;
        }

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        emptyState.hidden = true;
        canvas.hidden = false;

        const activity = definition.type !== "doughnut";
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: reducedMotion ? false : { duration: 300 },
            plugins: {
                legend: activity
                    ? { display: false }
                    : {
                        position: "bottom",
                        labels: {
                            color: colors.text,
                            usePointStyle: true,
                            pointStyle: "circle",
                            boxWidth: 9,
                            boxHeight: 9,
                            padding: 22,
                            font: { family: fontFamily, size: 12, weight: "500" },
                        },
                    },
                tooltip: {
                    backgroundColor: colors.surface,
                    borderColor: colors.border,
                    borderWidth: 1,
                    titleColor: colors.text,
                    bodyColor: colors.muted,
                    padding: 12,
                    callbacks: definition.type === "doughnut"
                        ? {
                            label(context) {
                                const total = context.dataset.data.reduce(
                                    (sum, value) => sum + value,
                                    0
                                );
                                const value = Number(context.raw);
                                const percentage = total > 0
                                    ? Math.round((value / total) * 100)
                                    : 0;

                                return ` ${context.label}: ${value} (${percentage}%)`;
                            },
                        }
                        : {},
                },
            },
        };

        if (definition.type === "doughnut") {
            options.cutout = "72%";
            options.rotation = -90;
            options.plugins.bricklabDoughnutCenter = {
                total: series.values.reduce((sum, value) => sum + value, 0),
                label: definition.category === "Builds" ? "BUILDS" : "TASKS",
            };
        } else {
            options.scales = {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: colors.muted,
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                    },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: 1,
                    ticks: {
                        color: colors.muted,
                        precision: 0,
                        stepSize: 1,
                    },
                    grid: { color: colors.border },
                    border: { display: false },
                },
            };
        }

        chartInstance = new window.Chart(canvas, {
            type: definition.type,
            data: {
                labels: series.labels,
                datasets: [{
                    ...definition.dataset,
                    data: series.values,
                }],
            },
            options,
            plugins: definition.type === "doughnut" ? [doughnutCenterPlugin] : [],
        });
    }

    function selectCategory(category) {
        const categoryChanged = category !== activeCategory;
        activeCategory = category;
        layout.classList.add("is-expanded");
        layout.dataset.activeCategory = category;
        chartDisplay.setAttribute("aria-hidden", "false");
        chartSwitcher.hidden = false;

        categoryButtons.forEach((button) => {
            button.setAttribute("aria-pressed", String(button.dataset.dashboardCategory === category));
        });

        if (categoryChanged) {
            activeView = category === "builds" ? "build-activity" : "task-status";
            periodSelect.value = "weekly";
        }

        renderChart();
    }

    categoryButtons.forEach((button) => {
        button.addEventListener("click", () => {
            selectCategory(button.dataset.dashboardCategory);
        });
    });

    viewButtons.forEach((button) => {
        button.addEventListener("click", () => {
            if (button.hidden || !activeCategory) {
                return;
            }

            activeView = button.dataset.chartView;
            renderChart();
        });
    });

    periodSelect.addEventListener("change", renderChart);

    selectCategory("builds");
});
