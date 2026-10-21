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
            var tx = 50 + 30 * Math.cos(rad);
            var ty = 50 + 30 * Math.sin(rad);
            var text = el("text");
            text.setAttribute("x", String(tx));
            text.setAttribute("y", String(ty));
            text.setAttribute("text-anchor", "middle");
            text.setAttribute("dominant-baseline", "middle");
            text.setAttribute("transform", "rotate(" + mid + " " + tx + " " + ty + ")");
            text.setAttribute("class", "wheel-label");
            text.textContent = sector.icon + " " + sector.short;
            svg.appendChild(text);
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

    function spin(host, pointer, lamps, index, slice, onCross, done) {
        if (spinning) return;
        spinning = true;
        var from = Number(host.dataset.rotation || 0);
        var landing = -(index * slice + slice / 2) - (Math.random() * slice * 0.45 - slice * 0.22);
        var turns = 360 * (5 + Math.floor(Math.random() * 2));
        var distance = turns + landing - (from % 360);
        if (distance < turns) distance += 360;
        var target = from + distance;
        var started = performance.now();
        var duration = 5400;
        var lastSector = -1;

        function ease(progress) {
            if (progress < 0.84) {
                var unit = progress / 0.84;
                return (1 - Math.pow(1 - unit, 3)) * 0.94;
            }
            var unit = (progress - 0.84) / 0.16;
            if (unit < 0.5) return 0.94 + unit * 0.16;
            return 1.02 - (unit - 0.5) * 0.04;
        }

        function frame(now) {
            var progress = Math.min(1, (now - started) / duration);
            var angle = from + distance * ease(progress);
            host.style.transform = "rotate(" + angle + "deg)";
            var sector = Math.floor((((-angle % 360) + 360) % 360) / slice);
            if (sector !== lastSector) {
                lastSector = sector;
                pointer.classList.remove("is-flick");
                void pointer.offsetWidth;
                pointer.classList.add("is-flick");
                if (onCross) onCross(1 - progress);
            }
            lamps.style.setProperty("--led", String(now / 80));
            if (progress < 1) {
                requestAnimationFrame(frame);
                return;
            }
            host.dataset.rotation = String(target);
            host.style.transform = "rotate(" + target + "deg)";
            var winner = host.querySelector('[data-index="' + index + '"]');
            if (winner) {
                winner.setAttribute("stroke", "#ffffff");
                winner.setAttribute("stroke-width", "1.4");
            }
            spinning = false;
            done();
        }

        requestAnimationFrame(frame);
    }

    global.AulaWheel = { draw: draw, lights: lights, spin: spin, busy: function () { return spinning; } };
})(window);
