(function () {
    const root = document.querySelector("[data-stlai-cost-dashboard]");
    if (!root || !window.stlaiSellerCostData) {
        return;
    }

    const data = window.stlaiSellerCostData;
    const models = Array.isArray(data.models) ? data.models.filter((model) => model.active !== false) : [];
    const defaultFlow = Object.assign({}, data.default_flow || {});
    const flowOrder = Array.isArray(data.flow_order) ? data.flow_order : ["volume", "text", "image", "video", "audio", "ugc", "composer"];
    const byId = (id) => models.find((model) => model.id === id);
    const n = (value) => Number(value || 0);
    let usdBrl = n(data.usd_brl || 5);
    let currency = String(data.default_currency || "USD").toUpperCase() === "BRL" ? "BRL" : "USD";

    const els = {
        currency: root.querySelector("[data-currency]"),
        usdBrl: root.querySelector("[data-usd-brl]"),
        kpiGeneration: root.querySelector("[data-kpi-generation]"),
        kpiImagesCost: root.querySelector("[data-kpi-images-cost]"),
        kpiImagesSub: root.querySelector("[data-kpi-images-sub]"),
        kpiVideoCost: root.querySelector("[data-kpi-video-cost]"),
        kpiVideoSub: root.querySelector("[data-kpi-video-sub]"),
        kpiAudioCost: root.querySelector("[data-kpi-audio-cost]"),
        kpiAudioSub: root.querySelector("[data-kpi-audio-sub]"),
        kpiSuggested: root.querySelector("[data-kpi-suggested]"),
        kpiMargin: root.querySelector("[data-kpi-margin]"),
        totalSuggested: root.querySelector("[data-total-suggested]"),
        chart: root.querySelector("[data-projection-chart]"),
        chartMonthTotal: root.querySelector("[data-chart-month-total]"),
        chartSubtitle: root.querySelector("[data-chart-subtitle]"),
        chartFirstDay: root.querySelector("[data-chart-first-day]"),
        chartLastDay: root.querySelector("[data-chart-last-day]"),
        chartTooltip: root.querySelector("[data-chart-tooltip]"),
        projectionDay: root.querySelector("[data-projection-day]"),
        projectionMonth: root.querySelector("[data-projection-month]"),
        projectionRevenue: root.querySelector("[data-projection-revenue]"),
        categoryBars: root.querySelector("[data-category-bars]"),
        costTable: root.querySelector("[data-cost-table]"),
        imageComparison: root.querySelector("[data-image-comparison]"),
        videoReference: root.querySelector("[data-video-reference]"),
        imageComparisonNote: root.querySelector("[data-image-comparison-note]"),
        csv: root.querySelector("[data-export-csv]"),
        pdf: root.querySelector("[data-export-pdf]"),
        reset: root.querySelector("[data-reset-flow]"),
        flowGrid: root.querySelector("[data-flow-grid]"),
        tabs: root.querySelector("[data-cost-tabs]"),
        tabPanels: Array.from(root.querySelectorAll("[data-tab-panel]")),
        plan: {
            builders: Array.from(root.querySelectorAll("[data-plan-builder]")),
            cards: Array.from(root.querySelectorAll("[data-plan-card]")),
            toggles: Array.from(root.querySelectorAll("[data-plan-card-toggle]")),
            summaries: {
                basicGeneration: root.querySelector('[data-plan-summary="basicGeneration"]'),
                premiumGeneration: root.querySelector('[data-plan-summary="premiumGeneration"]'),
                difference: root.querySelector('[data-plan-summary="difference"]'),
                basicMonth: root.querySelector('[data-plan-summary="basicMonth"]'),
                premiumMonth: root.querySelector('[data-plan-summary="premiumMonth"]'),
                comparison: root.querySelector('[data-plan-summary="comparison"]'),
            },
        },
        flow: {
            textModel: root.querySelector("[data-flow-text-model]"),
            textGenerations: root.querySelector("[data-flow-text-generations]"),
            imageModel: root.querySelector("[data-flow-image-model]"),
            imageQuantity: root.querySelector("[data-flow-image-quantity]"),
            videoModel: root.querySelector("[data-flow-video-model]"),
            videoClips: root.querySelector("[data-flow-video-clips]"),
            secondsPerClip: root.querySelector("[data-flow-seconds-per-clip]"),
            audioModel: root.querySelector("[data-flow-audio-model]"),
            audioSeconds: root.querySelector("[data-flow-audio-seconds]"),
            ugcEnabled: root.querySelector("[data-flow-ugc-enabled]"),
            ugcModel: root.querySelector("[data-flow-ugc-model]"),
            ugcClips: root.querySelector("[data-flow-ugc-clips]"),
            ugcSecondsPerClip: root.querySelector("[data-flow-ugc-seconds-per-clip]"),
            composerUsd: root.querySelector("[data-flow-composer-usd]"),
            generationsDay: root.querySelector("[data-flow-generations-day]"),
            daysMonth: root.querySelector("[data-flow-days-month]"),
            suggestedMultiplier: root.querySelector("[data-flow-suggested-multiplier]"),
        },
    };

    function moneyUsd(value) {
        return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(n(value));
    }

    function moneyBrl(value) {
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(n(value));
    }

    function money(valueUsd) {
        return currency === "USD" ? moneyUsd(valueUsd) : moneyBrl(valueUsd * usdBrl);
    }

    function oppositeMoney(valueUsd) {
        return currency === "USD" ? moneyBrl(valueUsd * usdBrl) : moneyUsd(valueUsd);
    }

    function unitLabel(unit) {
        return ({ image: "img", second: "s", minute: "min", generation: "call" })[unit] || unit;
    }

    function categoryColor(category) {
        return ({ Texto: "#4f83ff", Imagem: "#9B51E6", Video: "#ff4da6", "Vídeo": "#ff4da6", Audio: "#f4b942", "Áudio": "#f4b942", UGC: "#22c55e", Composicao: "#2dd4bf", "Composição": "#2dd4bf" })[category] || "#9B51E6";
    }

    function optionLabel(model) {
        return `${model.provider} - ${model.model}${model.variant ? " / " + model.variant : ""}`;
    }

    function fillSelect(select, category, selected) {
        select.innerHTML = "";
        models
            .filter((model) => model.category === category)
            .forEach((model) => {
                const option = document.createElement("option");
                option.value = model.id;
                option.textContent = optionLabel(model);
                option.selected = model.id === selected;
                select.appendChild(option);
            });
    }

    function setDefaults() {
        applyFlowOrder();
        fillSelect(els.flow.textModel, "Texto", defaultFlow.text_model);
        fillSelect(els.flow.imageModel, "Imagem", defaultFlow.image_model);
        fillSelect(els.flow.videoModel, "Video", defaultFlow.video_model);
        fillSelect(els.flow.audioModel, "Audio", defaultFlow.audio_model);
        fillSelect(els.flow.ugcModel, "UGC", defaultFlow.ugc_model);
        els.flow.textGenerations.value = defaultFlow.text_generations || 1;
        els.flow.imageQuantity.value = defaultFlow.image_quantity || 2;
        els.flow.videoClips.value = defaultFlow.video_clips || 4;
        els.flow.secondsPerClip.value = defaultFlow.seconds_per_clip || 8;
        els.flow.audioSeconds.value = defaultFlow.audio_seconds || 30;
        els.flow.ugcEnabled.checked = n(defaultFlow.ugc_enabled) > 0;
        els.flow.ugcClips.value = defaultFlow.ugc_clips || 0;
        els.flow.ugcSecondsPerClip.value = defaultFlow.ugc_seconds_per_clip || 8;
        els.flow.composerUsd.value = defaultFlow.composer_usd || 0;
        els.flow.generationsDay.value = defaultFlow.generations_per_day || 10;
        els.flow.daysMonth.value = defaultFlow.days_per_month || 22;
        els.flow.suggestedMultiplier.value = defaultFlow.suggested_multiplier || 3;
        els.currency.value = currency;
        els.usdBrl.value = usdBrl;
        render();
    }

    function revealDashboard() {
        root.style.visibility = "visible";
        root.style.opacity = "1";
    }

    function applyFlowOrder() {
        if (!els.flowGrid) {
            return;
        }
        flowOrder.forEach((key) => {
            const section = els.flowGrid.querySelector(`[data-flow-section="${key}"]`);
            if (section) {
                els.flowGrid.appendChild(section);
            }
        });
    }

    function currentFlow() {
        return {
            textModel: byId(els.flow.textModel.value),
            textGenerations: n(els.flow.textGenerations.value),
            imageModel: byId(els.flow.imageModel.value),
            imageQuantity: n(els.flow.imageQuantity.value),
            videoModel: byId(els.flow.videoModel.value),
            videoClips: n(els.flow.videoClips.value),
            secondsPerClip: n(els.flow.secondsPerClip.value),
            audioModel: byId(els.flow.audioModel.value),
            audioSeconds: n(els.flow.audioSeconds.value),
            ugcEnabled: els.flow.ugcEnabled.checked,
            ugcModel: byId(els.flow.ugcModel.value),
            ugcClips: n(els.flow.ugcClips.value),
            ugcSecondsPerClip: n(els.flow.ugcSecondsPerClip.value),
            composerUsd: n(els.flow.composerUsd.value),
            generationsDay: n(els.flow.generationsDay.value),
            daysMonth: n(els.flow.daysMonth.value),
            suggestedMultiplier: n(els.flow.suggestedMultiplier.value),
        };
    }

    function calculate(flow) {
        const videoSeconds = flow.videoClips * flow.secondsPerClip;
        const textUsd = (flow.textModel ? n(flow.textModel.usd) : 0) * flow.textGenerations;
        const imageUsd = (flow.imageModel ? n(flow.imageModel.usd) : 0) * flow.imageQuantity;
        const videoUsd = (flow.videoModel ? n(flow.videoModel.usd) : 0) * videoSeconds;
        const audioMinutes = flow.audioSeconds / 60;
        const audioUsd = (flow.audioModel ? n(flow.audioModel.usd) : 0) * audioMinutes;
        const ugcSeconds = flow.ugcEnabled ? flow.ugcClips * flow.ugcSecondsPerClip : 0;
        const ugcUsd = (flow.ugcModel ? n(flow.ugcModel.usd) : 0) * ugcSeconds;
        const composerUsd = flow.composerUsd;
        const audioComposerUsd = audioUsd + composerUsd;
        const totalUsd = textUsd + imageUsd + videoUsd + audioComposerUsd + ugcUsd;

        const rows = [
            { stage: "Geração de texto", type: "Texto", model: flow.textModel, qty: flow.textGenerations, unitCost: flow.textModel ? n(flow.textModel.usd) : 0, total: textUsd },
            { stage: "Imagens", type: "Imagem", model: flow.imageModel, qty: flow.imageQuantity, unitCost: flow.imageModel ? n(flow.imageModel.usd) : 0, total: imageUsd },
            { stage: "Vídeo", type: "Vídeo", model: flow.videoModel, qty: videoSeconds, unitCost: flow.videoModel ? n(flow.videoModel.usd) : 0, total: videoUsd, qtyLabel: `${flow.videoClips}x${flow.secondsPerClip}s` },
            { stage: "Áudio", type: "Áudio", model: flow.audioModel, qty: audioMinutes, unitCost: flow.audioModel ? n(flow.audioModel.usd) : 0, total: audioUsd, qtyLabel: `${flow.audioSeconds}s` },
            { stage: "UGC", type: "UGC", model: flow.ugcModel, qty: ugcSeconds, unitCost: flow.ugcModel ? n(flow.ugcModel.usd) : 0, total: ugcUsd, qtyLabel: flow.ugcEnabled ? `${flow.ugcClips}x${flow.ugcSecondsPerClip}s` : "desativado" },
            { stage: "Composição", type: "Composição", model: null, qty: flow.videoClips, unitCost: flow.videoClips ? composerUsd / flow.videoClips : 0, total: composerUsd, qtyLabel: `${flow.videoClips} vídeos` },
        ];

        return { textUsd, imageUsd, videoUsd, audioUsd, ugcUsd, composerUsd, audioComposerUsd, totalUsd, videoSeconds, ugcSeconds, rows };
    }

    function readPlanBuilder(planKey) {
        const builder = root.querySelector(`[data-plan-builder="${planKey}"]`);
        const value = (field) => {
            const input = builder.querySelector(`[data-plan-field="${field}"]`);
            if (!input) {
                return "";
            }
            return input.type === "checkbox" ? input.checked : input.value;
        };
        const combos = n(value("combos"));
        const videoClips = n(value("videos"));
        const secondsPerClip = n(value("videoSeconds"));
        return {
            name: value("name") || planKey,
            textModel: byId(els.flow.textModel.value),
            textGenerations: n(value("textGenerations")),
            imageModel: byId(els.flow.imageModel.value),
            imageQuantity: n(value("images")) + combos,
            videoModel: byId(els.flow.videoModel.value),
            videoClips,
            secondsPerClip,
            audioModel: byId(els.flow.audioModel.value),
            audioSeconds: videoClips * secondsPerClip,
            ugcEnabled: n(value("ugcVideos")) > 0,
            ugcModel: byId(els.flow.ugcModel.value),
            ugcClips: n(value("ugcVideos")),
            ugcSecondsPerClip: n(value("ugcSeconds")),
            composerUsd: videoClips ? n(els.flow.composerUsd.value) : 0,
            generationsDay: 0,
            daysMonth: 0,
            suggestedMultiplier: n(value("multiplier")),
            combos,
            individualImages: n(value("images")),
            monthlyGenerations: n(value("monthlyGenerations")),
            showSuggested: Boolean(root.querySelector(`[data-plan-card-toggle="${planKey}"]`)?.checked),
        };
    }

    function renderTable(calc) {
        const total = calc.totalUsd || 1;
        els.costTable.innerHTML = calc.rows
            .map((row) => {
                const percent = row.total / total;
                const modelName = row.model ? row.model.model : "Serviço de composição";
                const unit = row.model ? unitLabel(row.model.unit) : "vídeo";
                const color = categoryColor(row.type);
                return `<tr>
                    <td><strong>${row.stage}</strong></td>
                    <td><span class="stlai-cost-badge" style="--badge:${color}">${row.type}</span></td>
                    <td>${modelName}</td>
                    <td>${row.qtyLabel || row.qty.toFixed(row.qty % 1 ? 2 : 0)}</td>
                    <td>${money(row.unitCost)}/${unit}</td>
                    <td><strong>${money(row.total)}</strong></td>
                    <td><div class="stlai-cost-mini-bar"><i style="width:${Math.max(2, percent * 100)}%;background:${color}"></i></div>${(percent * 100).toFixed(1)}%</td>
                </tr>`;
            })
            .join("") + `<tr class="stlai-cost-total-row"><td colspan="5">Total</td><td colspan="2">${money(calc.totalUsd)}</td></tr>`;
    }

    function renderBars(calc) {
        const items = [
            ["Texto", calc.textUsd],
            ["Imagem", calc.imageUsd],
            ["Vídeo", calc.videoUsd],
            ["Áudio", calc.audioUsd],
            ["UGC", calc.ugcUsd],
            ["Composição", calc.composerUsd],
        ];
        const max = Math.max(...items.map((item) => item[1]), 1);
        els.categoryBars.innerHTML = items
            .map(([name, value]) => `<div><div class="stlai-cost-bar-row"><strong>${name}</strong><span>${money(value)}</span></div><div class="stlai-cost-bar-track"><div class="stlai-cost-bar-fill" style="width:${Math.max(2, (value / max) * 100)}%;background:${categoryColor(name)}"></div></div></div>`)
            .join("");
    }

    function renderImageComparison(flow) {
        const current = flow.imageModel ? n(flow.imageModel.usd) * flow.imageQuantity : 0;
        els.imageComparisonNote.textContent = `baseado em ${flow.imageQuantity} imagens`;
        els.imageComparison.innerHTML = models
            .filter((model) => model.category === "Imagem")
            .map((model) => {
                const total = n(model.usd) * flow.imageQuantity;
                const diff = total - current;
                const active = flow.imageModel && model.id === flow.imageModel.id;
                return `<tr class="${active ? "is-active" : ""}">
                    <td><strong>${active ? "· " : ""}${model.model} ${model.variant || ""}</strong></td>
                    <td>${model.provider}</td>
                    <td>${money(model.usd)}/${unitLabel(model.unit)}</td>
                    <td><strong>${money(total)}</strong></td>
                    <td class="${diff > 0 ? "is-bad" : diff < 0 ? "is-good" : ""}">${active ? "atual" : (diff > 0 ? "+" : "") + money(diff)}</td>
                </tr>`;
            })
            .join("");
    }

    function renderVideoReference() {
        els.videoReference.innerHTML = models
            .filter((model) => model.category === "Video" && model.unit === "second")
            .map((model) => `<tr>
                <td><strong>${model.model} ${model.variant || ""}</strong></td>
                <td>${model.provider}</td>
                <td><strong>${money(model.usd)}/s</strong></td>
                <td>${money(n(model.usd) * 4)}</td>
                <td>${money(n(model.usd) * 8)}</td>
                <td>${money(n(model.usd) * 10)}</td>
                <td>${money(n(model.usd) * 15)}</td>
            </tr>`)
            .join("");
    }

    function renderProjectionChart(flow, calc) {
        const days = Math.max(1, Math.round(flow.daysMonth || 30));
        const dailyUsd = calc.totalUsd * flow.generationsDay;
        const suggestedDailyUsd = calc.totalUsd * flow.suggestedMultiplier * flow.generationsDay;
        const max = Math.max(suggestedDailyUsd, dailyUsd, 1);
        const bars = [];

        for (let day = 1; day <= days; day += 1) {
            const costHeight = Math.max(3, (dailyUsd / max) * 100);
            const revenueHeight = Math.max(3, (suggestedDailyUsd / max) * 100);
            const accumulatedCost = dailyUsd * day;
            const accumulatedSuggested = suggestedDailyUsd * day;
            bars.push(`<div class="stlai-cost-chart-day"
                data-day="${day}"
                data-cost="${money(dailyUsd)}"
                data-suggested="${money(suggestedDailyUsd)}"
                data-acc-cost="${money(accumulatedCost)}"
                data-acc-suggested="${money(accumulatedSuggested)}">
                <i style="height:${revenueHeight}%"></i>
                <b style="height:${costHeight}%"></b>
            </div>`);
        }

        els.chart.style.setProperty("--chart-days", String(days));
        els.chart.innerHTML = bars.join("");
        els.chartMonthTotal.textContent = money(dailyUsd * days);
        els.chartSubtitle.textContent = `Custo diário: ${money(dailyUsd)} · ${flow.generationsDay} gerações/dia por ${days} dias`;
        els.chartFirstDay.textContent = "Dia 1";
        els.chartLastDay.textContent = `Dia ${days}`;
    }

    function render() {
        usdBrl = n(els.usdBrl.value || usdBrl);
        currency = els.currency.value;
        const flow = currentFlow();
        const calc = calculate(flow);
        const suggested = calc.totalUsd * flow.suggestedMultiplier;
        const monthlyGenerations = flow.generationsDay * flow.daysMonth;
        const dailyUsd = calc.totalUsd * flow.generationsDay;
        const monthlyUsd = dailyUsd * flow.daysMonth;
        const revenueUsd = suggested * monthlyGenerations;

        els.kpiGeneration.textContent = money(calc.totalUsd);
        els.kpiImagesCost.textContent = money(calc.imageUsd);
        els.kpiImagesSub.textContent = `${flow.imageQuantity} imagens geradas`;
        els.kpiVideoCost.textContent = money(calc.videoUsd);
        els.kpiVideoSub.textContent = `${flow.videoClips} vídeos${flow.ugcEnabled ? " + UGC Vídeo Realista" : ""}`;
        els.kpiAudioCost.textContent = money(calc.audioComposerUsd);
        els.kpiAudioSub.textContent = `${flow.audioSeconds}s áudio + composição`;
        els.kpiSuggested.textContent = money(suggested);
        els.kpiMargin.textContent = `${flow.suggestedMultiplier}x custo`;
        els.totalSuggested.textContent = money(suggested);
        els.projectionDay.textContent = money(dailyUsd);
        els.projectionMonth.textContent = money(monthlyUsd);
        els.projectionRevenue.textContent = money(revenueUsd);

        renderTable(calc);
        renderBars(calc);
        renderProjectionChart(flow, calc);
        renderImageComparison(flow);
        renderVideoReference();
        renderPlan(flow, calc);
        root.querySelector('[data-flow-section="ugc"]')?.classList.toggle("is-disabled", !flow.ugcEnabled);
    }

    function renderPlan(currentFlowData, currentCalc) {
        const plans = ["basic", "premium"].map((key) => {
            const flow = readPlanBuilder(key);
            const calc = calculate(flow);
            const finalUsd = calc.totalUsd * flow.suggestedMultiplier;
            const monthUsd = calc.totalUsd * flow.monthlyGenerations;
            const card = root.querySelector(`[data-plan-card="${key}"]`);
            card.querySelector('[data-plan-output="name"]').textContent = flow.name;
            card.querySelector('[data-plan-output="list"]').innerHTML = [
                `${flow.textGenerations} texto(s) gerado(s)`,
                `${flow.individualImages} imagem(ns) individuais`,
                `${flow.combos} combo(s) 2x2`,
                `${flow.videoClips} vídeo(s)`,
                `${flow.ugcClips} UGC Vídeo Realista`,
                `${flow.secondsPerClip}s por vídeo`,
                `${flow.monthlyGenerations} geração(ões) / mês`,
            ].map((item) => `<li>${item}</li>`).join("");
            card.querySelector('[data-plan-output="price"]').innerHTML = `<span class="stlai-plan-spend">Gasto ${money(calc.totalUsd)}</span>${flow.showSuggested ? `<strong>${money(finalUsd)} sugerido</strong>` : ""}`;
            return { key, flow, calc, finalUsd, monthUsd };
        });

        const basic = plans[0];
        const premium = plans[1];
        const difference = premium.finalUsd - basic.finalUsd;
        const comparison = basic.finalUsd > 0 ? ((premium.finalUsd / basic.finalUsd) - 1) * 100 : 0;

        els.plan.summaries.basicGeneration.textContent = money(basic.calc.totalUsd);
        els.plan.summaries.premiumGeneration.textContent = money(premium.calc.totalUsd);
        els.plan.summaries.difference.textContent = `${difference >= 0 ? "+" : ""}${money(difference)}`;
        els.plan.summaries.basicMonth.textContent = money(basic.monthUsd);
        els.plan.summaries.premiumMonth.textContent = money(premium.monthUsd);
        els.plan.summaries.comparison.textContent = basic.finalUsd > 0 ? `${comparison >= 0 ? "+" : ""}${comparison.toFixed(1)}%` : "sem base";
    }

    function switchTab(target) {
        root.querySelectorAll("[data-tab-target]").forEach((button) => {
            button.classList.toggle("is-active", button.dataset.tabTarget === target);
        });
        els.tabPanels.forEach((panel) => {
            panel.hidden = panel.dataset.tabPanel !== target;
        });
    }

    function exportCsv() {
        const flow = currentFlow();
        const calc = calculate(flow);
        const xml = (value) => String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
        const cell = (value, type = "String") => `<Cell><Data ss:Type="${type}">${xml(value)}</Data></Cell>`;
        const row = (values) => `<Row>${values.map((value) => cell(value, typeof value === "number" ? "Number" : "String")).join("")}</Row>`;
        const sheet = (name, rows) => `<Worksheet ss:Name="${xml(name)}"><Table>${rows.join("")}</Table></Worksheet>`;

        const detailRows = [
            row(["Etapa", "Tipo", "Modelo", "Quantidade", "Custo unitário USD", "Total USD", "Total BRL"]),
            ...calc.rows.map((item) => row([
                item.stage,
                item.type,
                item.model ? item.model.model : "Composição",
                item.qtyLabel || item.qty,
                Number(item.unitCost.toFixed(6)),
                Number(item.total.toFixed(6)),
                Number((item.total * usdBrl).toFixed(2)),
            ])),
            row(["Total", "", "", "", "", Number(calc.totalUsd.toFixed(6)), Number((calc.totalUsd * usdBrl).toFixed(2))]),
        ];

        const imageRows = [
            row(["Modelo", "Provedor", "Variação", "Unitário USD", "Total USD", "Total BRL", "Vs. atual USD"]),
            ...models
                .filter((model) => model.category === "Imagem")
                .map((model) => {
                    const total = n(model.usd) * flow.imageQuantity;
                    const current = flow.imageModel ? n(flow.imageModel.usd) * flow.imageQuantity : 0;
                    return row([
                        model.model,
                        model.provider,
                        model.variant || "",
                        Number(n(model.usd).toFixed(6)),
                        Number(total.toFixed(6)),
                        Number((total * usdBrl).toFixed(2)),
                        Number((total - current).toFixed(6)),
                    ]);
                }),
        ];

        const videoRows = [
            row(["Modelo", "Provedor", "Variação", "$/segundo", "4s USD", "8s USD", "10s USD", "15s USD", "4s BRL", "8s BRL", "10s BRL", "15s BRL"]),
            ...models
                .filter((model) => model.category === "Video" && model.unit === "second")
                .map((model) => {
                    const unit = n(model.usd);
                    return row([
                        model.model,
                        model.provider,
                        model.variant || "",
                        Number(unit.toFixed(6)),
                        Number((unit * 4).toFixed(6)),
                        Number((unit * 8).toFixed(6)),
                        Number((unit * 10).toFixed(6)),
                        Number((unit * 15).toFixed(6)),
                        Number((unit * 4 * usdBrl).toFixed(2)),
                        Number((unit * 8 * usdBrl).toFixed(2)),
                        Number((unit * 10 * usdBrl).toFixed(2)),
                        Number((unit * 15 * usdBrl).toFixed(2)),
                    ]);
                }),
        ];

        const workbook = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
${sheet("Detalhamento", detailRows)}
${sheet("Referência Imagem", imageRows)}
${sheet("Referência Vídeo", videoRows)}
</Workbook>`;

        const blob = new Blob(["\ufeff" + workbook], { type: "application/vnd.ms-excel;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "stlai-seller-projecao-custos.xls";
        link.click();
        URL.revokeObjectURL(url);
    }

    setDefaults();
    Object.values(els.flow).forEach((input) => input.addEventListener("input", render));
    els.plan.builders.forEach((builder) => {
        builder.querySelectorAll("input").forEach((input) => input.addEventListener("input", render));
    });
    els.plan.toggles.forEach((input) => input.addEventListener("input", render));
    els.tabs.addEventListener("click", (event) => {
        const button = event.target.closest("[data-tab-target]");
        if (button) {
            switchTab(button.dataset.tabTarget);
        }
    });
    els.currency.addEventListener("input", render);
    els.usdBrl.addEventListener("input", render);
    els.chart.addEventListener("mousemove", (event) => {
        const day = event.target.closest(".stlai-cost-chart-day");
        if (!day) {
            els.chartTooltip.classList.remove("is-visible");
            return;
        }
        const panel = els.chartTooltip.offsetParent || els.chart;
        const panelRect = panel.getBoundingClientRect();
        els.chartTooltip.innerHTML = `<strong>Dia ${day.dataset.day}</strong><span>Custo diário: ${day.dataset.cost}</span><span>Preço sugerido diário: ${day.dataset.suggested}</span><span>Custo acumulado: ${day.dataset.accCost}</span><span>Sugerido acumulado: ${day.dataset.accSuggested}</span>`;
        els.chartTooltip.classList.add("is-visible");

        const margin = 10;
        const tooltipWidth = els.chartTooltip.offsetWidth;
        const tooltipHeight = els.chartTooltip.offsetHeight;
        let left = event.clientX - panelRect.left + margin;
        let top = event.clientY - panelRect.top + margin;

        if (left + tooltipWidth > panelRect.width - margin) {
            left = event.clientX - panelRect.left - tooltipWidth - margin;
        }
        if (top + tooltipHeight > panelRect.height - margin) {
            top = event.clientY - panelRect.top - tooltipHeight - margin;
        }

        els.chartTooltip.style.left = `${Math.max(margin, left)}px`;
        els.chartTooltip.style.top = `${Math.max(margin, top)}px`;
    });
    els.chart.addEventListener("mouseleave", () => {
        els.chartTooltip.classList.remove("is-visible");
    });
    els.csv.addEventListener("click", exportCsv);
    els.pdf.addEventListener("click", () => window.print());
    els.reset.addEventListener("click", setDefaults);
    revealDashboard();
})();
