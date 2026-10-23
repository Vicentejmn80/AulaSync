(function (global) {
    var svgNs = "http://www.w3.org/2000/svg";
    var spinning = false;

    function el(name) {
        return document.createElementNS(svgNs, name);
    }

    function draw(host, sectors) {
        host.innerHTML = "";
        var svg = el("svg");
        svg.setAttribute("viewBox", "0 0 100 100");
        svg.setAttribute("class", "wheel-svg");
        var slice = 360 / sectors.length;
        sectors.forEach(function (sector, index) {
            var start = (index * slice - 90) * Math.PI / 180;
            var end = ((index + 1) * slice - 90) * Math.PI / 180;
            var x1 = 50 + 48 * Math.cos(start);
            var y1 = 50 + 48 * Math.sin(start);
            var x2 = 50 + 48 * Math.cos(end);
            var y2 = 50 + 48 * Math.sin(end);
            var path = el("path");
            path.setAttribute("d", "M50 50 L " + x1 + " " + y1 + " A 48 48 0 0 1 " + x2 + " " + y2 + " Z");
            path.setAttribute("fill", sector.color);
            path.setAttribute("data-index", String(index));
            svg.appendChild(path);

            var mid = index * slice + slice / 2;
            var rad = (mid - 90) * Math.PI / 180;
            var labelSize = sectors.length > 5 ? "3.1" : "4.2";
            [["8", 34, sector.icon, "wheel-icon"], [labelSize, 22, String(sector.short || "").slice(0, 12), "wheel-label"]].forEach(function (part) {
                var tx = 50 + part[1] * Math.cos(rad);
                var ty = 50 + part[1] * Math.sin(rad);
                var text = el("text");
                text.setAttribute("x", tx.toFixed(2));
                text.setAttribute("y", ty.toFixed(2));
                text.setAttribute("text-anchor", "middle");
                text.setAttribute("dominant-baseline", "middle");
                text.setAttribute("transform", "rotate(" + mid + " " + tx.toFixed(2) + " " + ty.toFixed(2) + ")");
                text.setAttribute("font-size", part[0]);
                text.setAttribute("class", part[3] + (sector.kind === "exclusive" ? " is-exclusive" : ""));
                text.textContent = part[2];
                svg.appendChild(text);
            });
        });
        var hub = el("circle");
        hub.setAttribute("cx", "50");
        hub.setAttribute("cy", "50");
        hub.setAttribute("r", "9");
        hub.setAttribute("fill", "#ffffff");
        svg.appendChild(hub);
        host.appendChild(svg);
        host.dataset.rotation = host.dataset.rotation || "0";
        return slice;
    }

    function lights(container, count) {
        container.innerHTML = "";
        for (var i = 0; i < count; i += 1) {
            var lamp = document.createElement("i");
            var radius = Math.max(48, container.clientWidth / 2 - 6);
            lamp.style.transform = "rotate(" + (i * (360 / count)) + "deg) translateY(-" + radius + "px)";
            container.appendChild(lamp);
        }
    }

    function sectorAt(angle, slice, count) {
        var norm = ((-angle % 360) + 360) % 360;
        var index = Math.floor(norm / slice);
        return index >= count ? 0 : index;
    }

    function plan(index, slice, from, jitter) {
        var mid = index * slice + slice / 2;
        var landing = -mid + jitter;
        var fromMod = ((from % 360) + 360) % 360;
        var landMod = ((landing % 360) + 360) % 360;
        var delta = landMod - fromMod;
        if (delta <= 0) delta += 360;
        var distance = 360 * 5 + delta;
        return { target: from + distance, distance: distance };
    }

    function spin(host, pointer, lamps, index, slice, onCross, done) {
        if (spinning) return;
        spinning = true;
        var from = Number(host.dataset.rotation || 0);
        var jitter = (Math.random() * 2 - 1) * slice * 0.16;
        var planned = plan(index, slice, from, jitter);
        var target = planned.target;
        var distance = planned.distance;
        var started = performance.now();
        var duration = 5200;
        var lastSector = -1;
        var settled = false;
        var raf = 0;

        function settle() {
            if (settled) return;
            settled = true;
            cancelAnimationFrame(raf);
            host.style.transition = "none";
            host.dataset.rotation = String(target);
            host.style.transform = "rotate(" + target + "deg)";
            var winner = host.querySelector('[data-index="' + index + '"]');
            if (winner) {
                winner.setAttribute("stroke", "#ffffff");
                winner.setAttribute("stroke-width", "1.6");
            }
            spinning = false;
            done();
        }

        function frame(now) {
            if (settled) return;
            var progress = Math.min(1, (now - started) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            var angle = from + distance * eased;
            host.style.transform = "rotate(" + angle + "deg)";
            var sector = sectorAt(angle, slice, 99);
            if (sector !== lastSector) {
                lastSector = sector;
                if (pointer) {
                    pointer.classList.remove("is-flick");
                    void pointer.offsetWidth;
                    pointer.classList.add("is-flick");
                }
                if (onCross) onCross();
            }
            if (lamps) lamps.style.setProperty("--led", String(now / 80));
            if (progress < 1) {
                raf = requestAnimationFrame(frame);
                return;
            }
            settle();
        }

        raf = requestAnimationFrame(frame);
    }

    global.AulaWheel = {
        draw: draw,
        lights: lights,
        spin: spin,
        plan: plan,
        sectorAt: sectorAt,
        busy: function () { return spinning; }
    };
})(window);
