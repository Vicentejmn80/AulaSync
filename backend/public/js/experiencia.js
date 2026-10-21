(function () {
    var app = document.getElementById("experiencia-app");
    if (!app) return;

    var configNode = document.getElementById("experiencia-config");
    var config = configNode ? JSON.parse(configNode.textContent) : {};
    var tabletId = config.tabletId || "tablet-1";
    var idleMs = Number(config.idleMs) || 45000;
    var questionMs = Number(config.questionMs) || 15000;
    AulaAudio.setVolume(config.volume == null ? 0.85 : config.volume);

    var camera = document.getElementById("experiencia-camera");
    var typing = document.getElementById("experiencia-idle-video");
    var wave = document.getElementById("experiencia-wave-video");
    var celebrate = document.getElementById("experiencia-celebrate-video");
    var celebrateFill = document.getElementById("experiencia-celebrate-fill");
    var videos = [typing, wave, celebrate, celebrateFill].filter(Boolean);
    var start = document.getElementById("experiencia-comenzar");
    var questionEl = document.getElementById("quiz-question");
    var optionsEl = document.getElementById("quiz-options");
    var progressEl = document.getElementById("quiz-progress");
    var streakEl = document.getElementById("quiz-streak");
    var factEl = document.getElementById("quiz-fact");
    var timerEl = document.getElementById("quiz-timer");
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
    var audioOn = false;
    var lastTap = 0;

    videos.forEach(function (video) { video.preload = "auto"; });

    function setState(next) {
        if (next !== state) AulaAudio.whoosh();
        state = next;
        app.dataset.state = next;
        if (next === "celebrate" || next === "confirm") document.body.classList.add("festive");
        else document.body.classList.remove("festive");
        armIdle();
    }

    function play(video, loop) {
        if (!video) return;
        video.loop = !!loop;
        video.playsInline = true;
        video.volume = config.volume == null ? 0.85 : Number(config.volume);
        if (video === celebrateFill) video.muted = true;
        else video.muted = !audioOn;
        var attempt = video.play();
        if (attempt && attempt.catch) {
            attempt.catch(function () {
                if (video === celebrateFill) return;
                video.muted = true;
                video.play().catch(function () {});
            });
        }
    }

    function show(video) {
        [typing, wave, celebrate].forEach(function (item) {
            if (!item || item === celebrateFill) return;
            var active = item === video;
            item.classList.toggle("is-on", active);
            if (!active) item.pause();
        });
        if (celebrateFill) {
            var fill = video === celebrate;
            celebrateFill.classList.toggle("is-on", fill);
            if (fill) play(celebrateFill, true);
            else celebrateFill.pause();
        }
    }

    function unlockAudio() {
        if (audioOn) return;
        audioOn = true;
        AulaAudio.unlock();
        videos.forEach(function (video) {
            if (video === celebrateFill) return;
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
        var options = item.options.map(function (text, index) {
            return { text: text, correct: index === item.answer };
        });
        return { q: item.q, fact: item.fact, options: shuffle(options) };
    }

    function renderQuestion() {
        window.clearInterval(timerId);
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
            button.addEventListener("click", function () { answer(button, option.correct); });
            optionsEl.appendChild(button);
        });
        var started = Date.now();
        timerEl.style.setProperty("--p", "1");
        timerEl.dataset.tone = "ok";
        timerId = window.setInterval(function () {
            var left = questionMs - (Date.now() - started);
            var ratio = Math.max(0, left / questionMs);
            timerEl.style.setProperty("--p", String(ratio));
            timerEl.dataset.tone = ratio > 0.45 ? "ok" : ratio > 0.2 ? "warn" : "late";
            if (ratio < 0.34 && Math.floor(left / 180) !== Math.floor((left + 100) / 180)) AulaAudio.tick();
            if (left <= 0) {
                window.clearInterval(timerId);
                answer(null, false);
            }
        }, 100);
    }

    function answer(button, correct) {
        if (answering || state !== "quiz") return;
        answering = true;
        window.clearInterval(timerId);
        if (button) button.classList.add(correct ? "is-correct" : "is-wrong");
        if (correct) {
            score += 1;
            streak += 1;
            reactionEl.textContent = "😄";
            AulaAudio.ding();
            document.body.classList.add("festive");
        } else {
            streak = 0;
            reactionEl.textContent = "💡";
            AulaAudio.miss();
        }
        camera.classList.add("is-react");
        factEl.textContent = round[questionIndex].fact;
        window.setTimeout(function () {
            camera.classList.remove("is-react");
            document.body.classList.remove("festive");
            questionIndex += 1;
            if (questionIndex >= 3) openRoulette();
            else renderQuestion();
        }, 1100);
    }

    function availableSectors() {
        return catalog[role].map(function (prize) {
            if (prize.quotaKey && AulaStore.used(tabletId, prize.quotaKey) >= prize.quota) {
                var medium = catalog[role].filter(function (item) { return item.tier === "medio"; })[0];
                return Object.assign({}, medium, { icon: "⭐" });
            }
            return prize;
        });
    }

    function pickSector() {
        var weights = sectors.map(function (prize) {
            var group = sectors.filter(function (item) { return item.tier === prize.tier; }).length || 1;
            var base = prize.tier === "raro" ? 0.07 : prize.tier === "medio" ? 0.28 : 0.65;
            var boost = prize.tier === "raro" ? 1 + (score / 3) * 0.5 : prize.tier === "medio" ? 1 + (score / 3) * 0.2 : 1;
            return (base / group) * boost;
        });
        var total = weights.reduce(function (sum, value) { return sum + value; }, 0);
        var cursor = Math.random() * total;
        for (var i = 0; i < weights.length; i += 1) {
            cursor -= weights[i];
            if (cursor <= 0) return i;
        }
        return 0;
    }

    function openRoulette() {
        sectors = availableSectors();
        slice = AulaWheel.draw(wheelHost, sectors);
        AulaWheel.lights(lamps, 18);
        spinBtn.disabled = false;
        setState("roulette");
    }

    function finishSpin(prize) {
        prizeName.textContent = prize.name;
        prizeText.textContent = prize.description;
        prizeIcon.textContent = prize.icon;
        setState("celebrate");
        celebrate.currentTime = 0;
        show(celebrate);
        play(celebrate, false);
        celebrate.onended = function () {
            if (state === "celebrate") setState("prize");
        };
    }

    function spin() {
        if (!guard() || AulaWheel.busy() || state !== "roulette") return;
        spinBtn.disabled = true;
        var index = pickSector();
        var prize = sectors[index];
        if (prize.quotaKey) {
            AulaStore.reserve(tabletId, prize.quotaKey);
            reserved = prize.quotaKey;
        }
        AulaWheel.spin(wheelHost, pointer, lamps, index, slice, function () {
            AulaAudio.tick();
        }, function () {
            wheelHost.classList.add("is-winner");
            window.setTimeout(function () { finishSpin(prize); }, 700);
        });
    }

    function code() {
        var alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        var value = "AULA-";
        for (var i = 0; i < 4; i += 1) value += alphabet[Math.floor(Math.random() * alphabet.length)];
        return value;
    }

    function resetExperience() {
        window.clearInterval(timerId);
        window.clearTimeout(idleTimer);
        if (reserved) AulaStore.release(tabletId, reserved);
        reserved = null;
        role = null;
        score = 0;
        streak = 0;
        questionIndex = 0;
        answering = false;
        if (form) form.reset();
        if (formError) formError.textContent = "";
        if (wheelHost) wheelHost.classList.remove("is-winner");
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
        return /^\+\d{1,4}$/.test(codeValue.trim()) && /^\d{7,14}$/.test(number.trim());
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
            round = shuffle(banks[role]).slice(0, 3).map(prepareQuestion);
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
            var colegio = String(data.get("colegio") || "").trim();
            var codigoPais = String(data.get("codigo") || "").trim();
            var numero = String(data.get("whatsapp") || "").trim();
            if (!nombre || !colegio || !validPhone(codigoPais, numero) || !data.get("consentimiento")) {
                formError.textContent = "Revisa tu nombre, tu colegio, el WhatsApp con código de país y la casilla de aceptación.";
                return;
            }
            formError.textContent = "";
            var prizeCode = code();
            var record = {
                id: tabletId + "-" + Date.now(),
                createdAt: new Date().toISOString(),
                tabletId: tabletId,
                rol: role,
                nombre: nombre,
                colegio: colegio,
                whatsapp: codigoPais + numero,
                puntaje: score + "/3",
                premio: prizeName.textContent,
                codigo: prizeCode,
                consentimiento: "si"
            };
            reserved = null;
            AulaStore.saveLead(record).then(function () {
                refreshSync();
                confirmText.textContent = "¡Listo, " + nombre + "! Te escribiremos por WhatsApp con tu premio.";
                confirmCode.textContent = prizeCode;
                show(wave);
                wave.currentTime = Math.max(0, (wave.duration || 4) - 2.5);
                play(wave, false);
                AulaAudio.ding();
                setState("confirm");
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

    Promise.all([
        fetch(config.questionsUrl).then(function (response) { return response.json(); }),
        fetch(config.prizesUrl).then(function (response) { return response.json(); })
    ]).then(function (payload) {
        banks = payload[0];
        catalog = payload[1];
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
