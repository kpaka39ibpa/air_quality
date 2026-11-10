Set-Content -Path main.js -Value '@
document.addEventListener("DOMContentLoaded", async () => {
    const CHART_GRAY_COLOR = "#64748b";
    const pollutantsConfig = [
        { id: "pm25", name: "Particule în suspensie (PM2,5)", unit: "µg/m³", colNames: ["PM2.5", "PM2,5"], levels: { bun: [0, 5], acc: [6, 15], mod: [16, 50], rau: [51, 90], frau: [91, 140], ext: [141, 1000] }, rawLevels: { bun: "0-5", acc: "6-15", mod: "16-50", rau: "51-90", frau: "91-140", ext: ">140" } },
        { id: "pm10", name: "Particule în suspensie (PM10)", unit: "µg/m³", colNames: ["PM10"], levels: { bun: [0, 15], acc: [16, 45], mod: [46, 120], rau: [121, 195], frau: [196, 270], ext: [271, 1200] }, rawLevels: { bun: "0-15", acc: "16-45", mod: "46-120", rau: "121-195", frau: "196-270", ext: ">270" } },
        { id: "no2", name: "Dioxid de azot (NO₂)", unit: "µg/m³", colNames: ["NO2"], levels: { bun: [0, 10], acc: [11, 25], mod: [26, 60], rau: [61, 100], frau: [101, 150], ext: [151, 1000] }, rawLevels: { bun: "0-10", acc: "11-25", mod: "26-60", rau: "61-100", frau: "101-150", ext: ">150" } },
        { id: "o3", name: "Ozon (O₃)", unit: "µg/m³", colNames: ["O3"], levels: { bun: [0, 60], acc: [61, 100], mod: [101, 120], rau: [121, 160], frau: [161, 180], ext: [181, 800] }, rawLevels: { bun: "0-60", acc: "61-100", mod: "101-120", rau: "121-160", frau: "161-180", ext: ">180" } },
        { id: "so2", name: "Dioxid de sulf (SO₂)", unit: "µg/m³", colNames: ["SO2"], levels: { bun: [0, 20], acc: [21, 40], mod: [41, 125], rau: [126, 190], frau: [191, 275], ext: [276, 1250] }, rawLevels: { bun: "0-20", acc: "21-40", mod: "41-125", rau: "126-190", frau: "191-275", ext: ">275" } },
        { id: "co", name: "Monoxid de carbon (CO)", unit: "µg/m³", colNames: ["CO"], levels: { bun: [0, 1], acc: [1, 4], mod: [4, 6], rau: [6, 8], frau: [8, 10], ext: [10, 100] }, rawLevels: { bun: "0-1.000", acc: "<4.000", mod: "<6.000", rau: "<8.000", frau: "<10.000", ext: ">10.000" } },
        { id: "temp", name: "Temperatura aerului", unit: "°C", colNames: ["Temperature", "RT"], hideTable: true }
    ];

    Highcharts.setOptions({ time: { useUTC: false } });
    const accordion = document.getElementById("accordion");
    const urlParams = new URLSearchParams(window.location.search);
    const stationParam = urlParams.get("station") || "chisinau";

    try {
        const response = await fetch(`get_data.php?station=${stationParam}&_t=` + new Date().getTime());
        if (!response.ok) throw new Error("HTTP error");
        const jsonData = await response.json();
        renderUI(jsonData);
    } catch (e) {
        accordion.innerHTML = `<p style="color:red;">A apărut o eroare la încărcarea datelor pentru stația '${stationParam}'. Asigurați-vă că fișierul data/${stationParam}/latest.csv există.</p>`;
    }

    function getActiveLevelKey(val, levels) {
        if (val === null || !levels) return null;
        if (val <= levels.bun[1]) return "bun";
        if (val <= levels.acc[1]) return "acc";
        if (val <= levels.mod[1]) return "mod";
        if (val <= levels.rau[1]) return "rau";
        if (val <= levels.frau[1]) return "frau";
        return "ext";
    }

    function renderUI(data) {
        accordion.innerHTML = "";
        if (!data.series) return;

        pollutantsConfig.forEach(item => {
            let matchedKey = item.colNames.find(key => data.series[key] !== undefined);
            if (!matchedKey || !data.series[matchedKey] || data.series[matchedKey].length === 0) return;

            const dataset = data.series[matchedKey];
            const itemEl = document.createElement("div");
            itemEl.className = "accordion__item";

            const lastPoint = dataset[dataset.length - 1][1];
            const lastValText = lastPoint !== null ? `${lastPoint.toFixed(1)} ${item.unit}` : "N/A";
            const activeKey = getActiveLevelKey(lastPoint, item.levels);
            const kpiColorClass = activeKey ? `kpi-${activeKey}` : "";

            let tableHTML = "";
            if (!item.hideTable && item.rawLevels) {
                tableHTML = `
                <div class="table-responsive">
                    <table class="quality-table">
                        <thead><tr><th colspan="7">Starea de calitate a aerului atmosferic</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Concentrația poluantului monitorizat</td>
                                <td class="bun-cell ${activeKey === 'bun' ? 'active-level' : ''}">BUN</td>
                                <td class="acceptable-cell ${activeKey === 'acc' ? 'active-level' : ''}">ACCEPTABIL</td>
                                <td class="moderat-cell ${activeKey === 'mod' ? 'active-level' : ''}">MODERAT</td>
                                <td class="rau-cell ${activeKey === 'rau' ? 'active-level' : ''}">RĂU</td>
                                <td class="foarte-rau-cell ${activeKey === 'frau' ? 'active-level' : ''}">FOARTE RĂU</td>
                                <td class="extrem-de-rau-cell ${activeKey === 'ext' ? 'active-level' : ''}">EXTREM DE RĂU</td>
                            </tr>
                            <tr>
                                <td>${item.name} (${item.unit})</td>
                                <td class="bun-lvl ${activeKey === 'bun' ? 'active-level' : ''}">${item.rawLevels.bun}</td>
                                <td class="acceptable-lvl ${activeKey === 'acc' ? 'active-level' : ''}">${item.rawLevels.acc}</td>
                                <td class="moderat-lvl ${activeKey === 'mod' ? 'active-level' : ''}">${item.rawLevels.mod}</td>
                                <td class="rau-lvl ${activeKey === 'rau' ? 'active-level' : ''}">${item.rawLevels.rau}</td>
                                <td class="foarte-rau-lvl ${activeKey === 'frau' ? 'active-level' : ''}">${item.rawLevels.frau}</td>
                                <td class="extrem-de-rau-lvl ${activeKey === 'ext' ? 'active-level' : ''}">${item.rawLevels.ext}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>`;
            }

            itemEl.innerHTML = `
                <div class="accordion__header-wrapper">
                    <h3 class="accordion__title">${item.name}</h3>
                    <div class="kpi-badge ${kpiColorClass}"><span class="status-dot"></span>${lastValText}</div>
                </div>
                <div class="accordion__content">
                    <div id="chart-${item.id}" class="chart-container"></div>
                    ${tableHTML}
                </div>
            `;
            accordion.appendChild(itemEl);

            Highcharts.chart(`chart-${item.id}`, {
                chart: { type: "spline", zoomType: "x", backgroundColor: "#ffffff" },
                title: { text: null },
                legend: { enabled: false },
                plotOptions: { spline: { connectNulls: true, lineWidth: 2, marker: { enabled: false } } },
                xAxis: { type: "datetime", title: { text: "Data / Ora" }, gridLineWidth: 1, gridLineColor: "#f1f5f9" },
                yAxis: { title: { text: `${item.name} (${item.unit})` }, gridLineColor: "#f1f5f9" },
                tooltip: { xDateFormat: "%H:%M<br/>%d.%m.%Y", valueDecimals: 1, valueSuffix: ` ${item.unit}` },
                series: [{ name: item.name, data: dataset, color: CHART_GRAY_COLOR }],
                credits: { enabled: false }
            });
        });
    }
});
'@