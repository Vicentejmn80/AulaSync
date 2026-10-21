/* Sonidos de la experiencia.
   Si colocas un mp3 en public/assets/sounds/ con estos nombres, se usa ese archivo.
   Si no existe, se sintetiza con Web Audio. */
(function (global) {
    var files = {
        click: "/assets/sounds/click.mp3",
        ding: "/assets/sounds/ding.mp3",
        miss: "/assets/sounds/miss.mp3",
        whoosh: "/assets/sounds/whoosh.mp3",
        tick: "/assets/sounds/tick.mp3"
    };
    var buffers = {};
    var ctx = null;
    var master = 0.85;
    var unlocked = false;

    function context() {
        var AudioCtx = global.AudioContext || global.webkitAudioContext;
        if (!AudioCtx) return null;
        if (!ctx) ctx = new AudioCtx();
        if (ctx.state === "suspended") ctx.resume();
        return ctx;
    }

    function envGain(duration, peak) {
        var audio = context();
        if (!audio) return null;
        var gain = audio.createGain();
        gain.connect(audio.destination);
        var now = audio.currentTime;
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(Math.max(0.001, peak * master), now + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);
        return { audio: audio, gain: gain, now: now };
    }

    function tone(freq, duration, type, peak) {
        var node = envGain(duration, peak);
        if (!node) return;
        var osc = node.audio.createOscillator();
        osc.type = type || "sine";
        osc.frequency.setValueAtTime(freq, node.now);
        osc.connect(node.gain);
        osc.start(node.now);
        osc.stop(node.now + duration + 0.02);
    }

    function noise(duration, peak) {
        var node = envGain(duration, peak);
        if (!node) return;
        var length = Math.floor(node.audio.sampleRate * duration);
        var buffer = node.audio.createBuffer(1, length, node.audio.sampleRate);
        var data = buffer.getChannelData(0);
        for (var i = 0; i < length; i += 1) data[i] = Math.random() * 2 - 1;
        var source = node.audio.createBufferSource();
        source.buffer = buffer;
        source.connect(node.gain);
        source.start(node.now);
    }

    function synth(name) {
        if (name === "click") tone(620, 0.07, "triangle", 0.12);
        if (name === "ding") {
            tone(880, 0.18, "sine", 0.16);
            tone(1320, 0.22, "sine", 0.1);
        }
        if (name === "miss") tone(220, 0.22, "sine", 0.08);
        if (name === "whoosh") noise(0.28, 0.07);
        if (name === "tick") tone(1400, 0.04, "square", 0.05);
    }

    function play(name) {
        if (!unlocked) return;
        var audio = context();
        if (audio && buffers[name]) {
            var source = audio.createBufferSource();
            var gain = audio.createGain();
            gain.gain.value = master;
            source.buffer = buffers[name];
            source.connect(gain);
            gain.connect(audio.destination);
            source.start();
            return;
        }
        synth(name);
    }

    function preload() {
        var audio = context();
        if (!audio) return;
        Object.keys(files).forEach(function (name) {
            fetch(files[name]).then(function (response) {
                if (!response.ok) return null;
                return response.arrayBuffer();
            }).then(function (buffer) {
                if (!buffer) return;
                return audio.decodeAudioData(buffer);
            }).then(function (decoded) {
                if (decoded) buffers[name] = decoded;
            }).catch(function () {});
        });
    }

    global.AulaAudio = {
        setVolume: function (value) { master = Math.max(0, Math.min(1, Number(value) || 0)); },
        unlock: function () {
            unlocked = true;
            context();
            preload();
        },
        click: function () { play("click"); },
        ding: function () { play("ding"); },
        miss: function () { play("miss"); },
        whoosh: function () { play("whoosh"); },
        tick: function () { play("tick"); }
    };
})(window);
