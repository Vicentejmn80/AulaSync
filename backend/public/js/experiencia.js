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
    var poster = document.getElementById("experiencia-poster");
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
    var audioOn = false;
    var lastTap = 0;

    videos.forEach(function (video) {
        video.preload = "auto";
        video.addEventListener("loadeddata", function () {
            var alpha = /\.(webm|mov)(\?|$)/i.test(video.currentSrc || "");
            video.classList.toggle("is-matte", !alpha);
            if (poster && video === typing) poster.classList.toggle("is-on", !alpha ? false : typing.readyState < 2);
        });
    });

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
        if (poster) poster.classList.toggle("is-on", video === typing && typing.classList.contains("is-matte") === false && typing.readyState < 2);
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
            return base / group;
        });
        var total = weights.reduce(function (sum, value) { return sum + value; }, 0);
        var cursor = Math.random() * total;
        for (var i = 0; i < weights.length; i += 1) {
            cursor -= weights[i];
            if (cursor <= 0) return i;
        }
        return 0;
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
        resultLocked = true;
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
            window.setTimeout(function () { finishSpin(prize); }, 500);
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
        retoPrincipal = "";
        resultLocked = false;
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
                puntaje: score + "/2",
                premio: prizeName.textContent,
                codigo: prizeCode,
                reto_principal: retoPrincipal,
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
