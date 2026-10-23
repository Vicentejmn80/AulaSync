(function () {
    var app = document.getElementById("experiencia-app");
    if (!app) return;

    var configNode = document.getElementById("experiencia-config");
    var config = configNode ? JSON.parse(configNode.textContent) : {};
    var tabletId = config.tabletId || "tablet-1";
    var idleMs = Number(config.idleMs) || 45000;
    AulaAudio.setVolume(config.volume == null ? 0.85 : config.volume);

    var camera = document.getElementById("experiencia-camera");
    var typing = document.getElementById("experiencia-idle-video");
    var wave = document.getElementById("experiencia-wave-video");
    var celebrate = document.getElementById("experiencia-celebrate-video");
    var videos = [typing, wave, celebrate].filter(Boolean);
    var start = document.getElementById("experiencia-comenzar");
    var questionEl = document.getElementById("quiz-question");
    var optionsEl = document.getElementById("quiz-options");
    var progressEl = document.getElementById("quiz-progress");
    var streakEl = document.getElementById("quiz-streak");
    var factEl = document.getElementById("quiz-fact");
    var reactionEl = document.getElementById("quiz-reaction");
    var wheelHost = document.getElementById("ruleta");
    var pointer = document.getElementById("puntero");
    var lamps = document.getElementById("leds");
    var spinBtn = document.getElementById("girar-ruleta");
    var form = document.getElementById("premio-form");
    var formError = document.getElementById("form-error");
    var prizeName = document.getElementById("premio-nombre");
    var prizeText = document.getElementById("premio-texto");
    var prizeIcon = document.getElementById("premio-icono");
    var prizeKind = document.getElementById("premio-tipo");
    var prizeCondition = document.getElementById("premio-condicion");
    var confirmText = document.getElementById("confirm-text");
    var confirmCode = document.getElementById("confirm-code");
    var syncDot = document.getElementById("sync-dot");
    var adminBtn = document.getElementById("esquina-admin");
    var adminPanel = document.getElementById("panel-admin");

    var state = "idle";
    var banks = { profesor: [], director: [] };
    var catalog = { profesor: [], director: [] };
    var role = null;
    var round = [];
    var questionIndex = 0;
    var score = 0;
    var streak = 0;
    var answering = false;
    var timerId = 0;
    var idleTimer = 0;
    var glanceTimer = 0;
    var sectors = [];
    var slice = 45;
    var reserved = null;
    var retoPrincipal = "";
    var resultLocked = false;
    var activePrize = null;
    var spinId = "";
    var audioOn = false;
    var lastTap = 0;

    videos.forEach(function (video) {
        video.preload = "auto";
    });

    function setState(next) {
        if (next !== state) AulaAudio.whoosh();
        state = next;
        app.dataset.state = next;
        if (next === "celebrate" || next === "confirm") document.body.classList.add("festive");
        else document.body.classList.remove("festive");
        if (next === "prize" && window.AulaTally) window.AulaTally.load();
        armIdle();
    }

    function play(video, loop) {
        if (!video) return;
        video.loop = !!loop;
        video.playsInline = true;
        video.volume = config.volume == null ? 0.85 : Number(config.volume);
        video.muted = !audioOn;
        var attempt = video.play();
        if (attempt && attempt.catch) {
            attempt.catch(function () {
                video.muted = true;
                video.play().catch(function () {});
            });
        }
    }

    function show(video) {
        videos.forEach(function (item) {
            var active = item === video;
            item.classList.toggle("is-on", active);
            if (!active) item.pause();
        });
    }

    function unlockAudio() {
        if (audioOn) return;
        audioOn = true;
        AulaAudio.unlock();
        videos.forEach(function (video) {
            video.muted = false;
        });
    }

    function armIdle() {
        window.clearTimeout(idleTimer);
        idleTimer = window.setTimeout(resetExperience, idleMs);
    }

    function guard() {
        var now = Date.now();
        if (now - lastTap < 450) return false;
        lastTap = now;
        return true;
    }

    function shuffle(list) {
        var copy = list.slice();
        for (var i = copy.length - 1; i > 0; i -= 1) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = copy[i];
            copy[i] = copy[j];
            copy[j] = tmp;
        }
        return copy;
    }

    function prepareQuestion(item) {
        var opinion = item.type === "opinion" || item.answer == null || item.answer < 0;
        var options = item.options.map(function (text, index) {
            return { text: text, correct: !opinion && index === item.answer };
        });
        return { q: item.q, fact: item.fact, opinion: opinion, options: shuffle(options) };
    }

    function pickTyped(type) {
        var pool = (banks[role] || []).filter(function (item) { return item.type === type; });
        if (!pool.length) pool = banks[role] || [];
        return prepareQuestion(shuffle(pool)[0]);
    }

    function renderQuestion() {
        answering = false;
        var item = round[questionIndex];
        progressEl.textContent = (questionIndex + 1) + "/3";
        streakEl.textContent = streak ? "Racha " + streak : "";
        questionEl.textContent = item.q;
        factEl.textContent = "";
        reactionEl.textContent = "";
        optionsEl.innerHTML = "";
        item.options.forEach(function (option) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "option";
            button.textContent = option.text;
            button.addEventListener("click", function () { answer(button, option); });
            optionsEl.appendChild(button);
        });
    }

    function answer(button, option) {
        if (answering || state !== "quiz" || !button) return;
        answering = true;
        var item = round[questionIndex];
        var wait = 1100;
        if (item.opinion) {
            button.classList.add("is-chosen");
            retoPrincipal = button.textContent;
            reactionEl.textContent = "🤖";
            factEl.textContent = "¡Gracias! Esto me ayuda a conocerte mejor.";
            AulaAudio.click();
            wait = 1400;
        } else if (option.correct) {
            score += 1;
            streak += 1;
            button.classList.add("is-correct");
            reactionEl.textContent = "😄";
            AulaAudio.ding();
            factEl.textContent = item.fact;
        } else {
            streak = 0;
            button.classList.add("is-wrong");
            reactionEl.textContent = "💡";
            AulaAudio.miss();
            factEl.textContent = item.fact;
        }
        camera.classList.add("is-react");
        window.setTimeout(function () {
            camera.classList.remove("is-react");
            questionIndex += 1;
            if (questionIndex >= 3) openRoulette();
            else renderQuestion();
        }, wait);
    }

    function availableSectors() {
        return (catalog[role] || []).slice();
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") : "";
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken()
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                body.status = response.status;
                body.okHttp = response.ok;
                return body;
            });
        });
    }

    function burst() {
        ["confeti-atras", "confeti-frente"].forEach(function (id) {
            var layer = document.getElementById(id);
            if (!layer) return;
            layer.innerHTML = "";
            for (var i = 0; i < 16; i += 1) {
                var bit = document.createElement("i");
                bit.style.left = (6 + Math.random() * 88) + "%";
                bit.style.animationDelay = (Math.random() * 0.35) + "s";
                bit.style.animationDuration = (2.6 + Math.random() * 1.2) + "s";
                bit.style.background = ["#7C3AED", "#EC4899", "#F59E0B", "#C455ED"][i % 4];
                layer.appendChild(bit);
            }
        });
    }

    function openRoulette() {
        resultLocked = false;
        sectors = availableSectors();
        slice = AulaWheel.draw(wheelHost, sectors);
        AulaWheel.lights(lamps, 18);
        spinBtn.disabled = false;
        setState("roulette");
    }

    function finishSpin(prize) {
        if (state !== "roulette") return;
        prizeName.textContent = prize.name;
        prizeText.textContent = prize.description;
        prizeIcon.textContent = prize.icon;
        if (prizeKind) {
            prizeKind.textContent = prize.kind === "exclusive" ? "Experiencia con cupo" : "Recurso digital";
            prizeKind.classList.toggle("is-exclusive", prize.kind === "exclusive");
        }
        if (prizeCondition) prizeCondition.textContent = prize.condition || "";
        burst();
        setState("celebrate");
        celebrate.currentTime = 0;
        show(celebrate);
        play(celebrate, false);
        celebrate.onended = function () {
            if (state === "celebrate") setState("prize");
        };
    }

    function spin() {
        if (resultLocked || !guard() || AulaWheel.busy() || state !== "roulette") return;
        var note = document.getElementById("ruleta-error");
        if (note) note.textContent = "";
        resultLocked = true;
        spinBtn.disabled = true;
        postJson(config.spinUrl, { rol: role }).then(function (body) {
            if (!body.okHttp || !body.prize) throw new Error("giro");
            var index = -1;
            sectors.forEach(function (sector, i) {
                if (sector.id === body.prize.id) index = i;
            });
            if (index < 0) throw new Error("premio");
            activePrize = body.prize;
            spinId = body.spin_id;
            AulaWheel.spin(wheelHost, pointer, lamps, index, slice, function () {
                AulaAudio.tick();
            }, function () {
                window.setTimeout(function () { finishSpin(activePrize); }, 500);
            });
        }).catch(function () {
            resultLocked = false;
            spinBtn.disabled = false;
            if (note) note.textContent = "No pudimos preparar el premio. Intenta girar otra vez.";
        });
    }

    function resetExperience() {
        window.clearInterval(timerId);
        window.clearTimeout(idleTimer);
        if (reserved) AulaStore.release(tabletId, reserved);
        reserved = null;
        retoPrincipal = "";
        resultLocked = false;
        activePrize = null;
        spinId = "";
        role = null;
        score = 0;
        streak = 0;
        questionIndex = 0;
        answering = false;
        if (form) form.reset();
        if (formError) formError.textContent = "";
        if (wheelHost) wheelHost.style.transform = "rotate(0deg)";
        if (wheelHost) wheelHost.dataset.rotation = "0";
        ["confeti-atras", "confeti-frente"].forEach(function (id) {
            var layer = document.getElementById(id);
            if (layer) layer.innerHTML = "";
        });
        camera.classList.remove("is-close", "is-wide", "is-react");
        if (start) start.classList.remove("is-leaving");
        show(typing);
        play(typing, true);
        setState("idle");
        scheduleGlance();
        refreshSync();
    }

    function scheduleGlance() {
        window.clearTimeout(glanceTimer);
        glanceTimer = window.setTimeout(function () {
            if (state !== "idle" || !wave) return;
            show(wave);
            wave.currentTime = 0;
            play(wave, false);
            window.setTimeout(function () {
                if (state !== "idle") return;
                show(typing);
                play(typing, true);
                scheduleGlance();
            }, 2800);
        }, 40000);
    }

    function onComenzarExperiencia() {
        if (state !== "idle" || !wave || !guard()) return;
        unlockAudio();
        window.clearTimeout(glanceTimer);
        if (start) start.classList.add("is-leaving");
        setState("greeting");
        camera.classList.add("is-close");
        wave.currentTime = 0;
        show(wave);
        play(wave, false);
        window.setTimeout(function () { camera.classList.remove("is-close"); }, 1300);
        function watch() {
            if (!wave.duration) return;
            if (state === "greeting" && wave.currentTime >= wave.duration * 0.5) {
                camera.classList.add("is-wide");
                setState("role_selection");
            }
            if (wave.currentTime < wave.duration - 0.12) return;
            wave.removeEventListener("timeupdate", watch);
            wave.pause();
            camera.classList.add("is-wide");
            if (state === "greeting") setState("role_selection");
        }
        wave.addEventListener("timeupdate", watch);
    }

    function refreshSync() {
        AulaStore.pending().then(function (count) {
            syncDot.textContent = count ? "🟠 " + count : "🟢";
        }).catch(function () {});
        AulaStore.flush(config.webhookUrl || "");
    }

    function validPhone(codeValue, number) {
        if (!String(number || "").trim()) return true;
        return /^\+\d{1,4}$/.test(String(codeValue || "").trim()) && /^\d{7,14}$/.test(String(number).trim());
    }

    app.addEventListener("pointerdown", function () {
        unlockAudio();
        if (state === "idle") onComenzarExperiencia();
        if (state === "celebrate") setState("prize");
        armIdle();
    });

    if (start) start.addEventListener("click", function (event) {
        event.stopPropagation();
        onComenzarExperiencia();
    });

    app.querySelectorAll("[data-role]").forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.stopPropagation();
            if (state !== "role_selection" || !guard()) return;
            AulaAudio.click();
            role = button.getAttribute("data-role");
            round = [pickTyped("conocimiento"), pickTyped("caso"), pickTyped("opinion")];
            questionIndex = 0;
            score = 0;
            streak = 0;
            setState("quiz");
            renderQuestion();
        });
    });

    if (spinBtn) spinBtn.addEventListener("click", function (event) {
        event.stopPropagation();
        spin();
    });

    if (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!guard()) return;
            var data = new FormData(form);
            var nombre = String(data.get("nombre") || "").trim();
            var apellido = String(data.get("apellido") || "").trim();
            var email = String(data.get("email") || "").trim();
            var colegio = String(data.get("colegio") || "").trim();
            var cargo = String(data.get("cargo") || "").trim();
            var problema = String(data.get("problema") || "").trim();
            var codigoPais = String(data.get("codigo") || "").trim();
            var numero = String(data.get("whatsapp") || "").trim();
            if (!nombre || !colegio || !cargo || !data.get("consentimiento") || !validPhone(codigoPais, numero)) {
                formError.textContent = "Revisa nombre, colegio, cargo y, si dejas teléfono, el código de país.";
                return;
            }
            if (!email && !numero) {
                formError.textContent = "Indica un correo o un teléfono para poder entregarte el premio.";
                return;
            }
            if (!activePrize || !spinId) {
                formError.textContent = "Gira la ruleta otra vez para registrar el premio.";
                return;
            }
            formError.textContent = "";
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            postJson(config.contactUrl, {
                spin_id: spinId,
                nombre: nombre,
                apellido: apellido,
                email: email,
                colegio: colegio,
                cargo: cargo,
                telefono: numero ? (codigoPais + numero) : "",
                problema: problema || retoPrincipal,
                rol: role,
                puntaje: score + "/2",
                consentimiento: true
            }).then(function (body) {
                if (submitBtn) submitBtn.disabled = false;
                if (!body.okHttp || !body.ok) {
                    formError.textContent = body.message || "No pudimos guardar tus datos. Inténtalo de nuevo.";
                    return;
                }
                reserved = null;
                var record = {
                    id: spinId,
                    createdAt: new Date().toISOString(),
                    tabletId: tabletId,
                    rol: role,
                    nombre: nombre,
                    apellido: apellido,
                    email: email,
                    colegio: colegio,
                    cargo: cargo,
                    whatsapp: numero ? (codigoPais + numero) : "",
                    puntaje: score + "/2",
                    premio: body.premio || activePrize.name,
                    codigo: body.codigo,
                    reto_principal: problema || retoPrincipal,
                    consentimiento: "si",
                    sync: "synced"
                };
                AulaStore.saveLead(record).catch(function () {});
                refreshSync();
                confirmText.textContent = "¡Listo, " + nombre + "! Guardamos tu premio: " + (body.premio || activePrize.name) + ".";
                confirmCode.textContent = body.codigo || "";
                show(wave);
                wave.currentTime = Math.max(0, (wave.duration || 4) - 2.5);
                play(wave, false);
                AulaAudio.ding();
                setState("confirm");
            }).catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                formError.textContent = "No pudimos guardar tus datos. Revisa la conexión e inténtalo de nuevo.";
            });
        });
    }

    if (adminBtn && adminPanel) {
        var hold = 0;
        adminBtn.addEventListener("pointerdown", function (event) {
            event.stopPropagation();
            hold = window.setTimeout(function () {
                var pin = window.prompt("PIN del stand");
                if (pin !== String(config.pin || "")) return;
                AulaStore.all().then(function (rows) {
                    document.getElementById("admin-conteo").textContent = rows.length + " registros en esta tablet";
                    adminPanel.hidden = false;
                });
            }, 3000);
        });
        adminBtn.addEventListener("pointerup", function () { window.clearTimeout(hold); });
        adminBtn.addEventListener("pointerleave", function () { window.clearTimeout(hold); });
        document.getElementById("admin-cerrar").addEventListener("click", function () { adminPanel.hidden = true; });
        document.getElementById("admin-csv").addEventListener("click", function () {
            AulaStore.toCsv().then(function (csv) {
                var blob = new Blob([csv], { type: "text/csv" });
                var link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = "aulasync-premios.csv";
                link.click();
            });
        });
    }

    document.addEventListener("contextmenu", function (event) { event.preventDefault(); });
    document.addEventListener("pointermove", function (event) {
        var x = (event.clientX / window.innerWidth - 0.5) * 16;
        var y = (event.clientY / window.innerHeight - 0.5) * 10;
        document.documentElement.style.setProperty("--shift-x", x.toFixed(1) + "px");
        document.documentElement.style.setProperty("--shift-y", y.toFixed(1) + "px");
    });

    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register("/sw.js").catch(function () {});
    }

    catalog = {
        profesor: config.prizes || [],
        director: config.prizes || []
    };

    fetch(config.questionsUrl).then(function (response) { return response.json(); }).then(function (payload) {
        banks = payload;
    }).catch(function () {});

    show(typing);
    play(typing, true);
    scheduleGlance();
    refreshSync();
    window.setInterval(refreshSync, 20000);
    window.setTimeout(function () {
        var loader = document.getElementById("experiencia-loader");
        if (loader) loader.classList.add("is-done");
    }, 900);
})();
