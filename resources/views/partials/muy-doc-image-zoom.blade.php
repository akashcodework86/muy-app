<style>
    .muy-doc-zoom { display: grid; gap: 0.55rem; }
    .muy-doc-zoom__hint {
        margin: 0;
        font-size: 0.8rem;
        color: #64748b;
    }
    .muy-doc-zoom__row {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        align-items: start;
    }
    .muy-doc-zoom__stage {
        position: relative;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        cursor: crosshair;
        touch-action: none;
        user-select: none;
    }
    .muy-doc-zoom__img {
        display: block;
        width: 100%;
        height: auto;
        max-height: 72vh;
        object-fit: contain;
        pointer-events: none;
    }
    .muy-doc-zoom__lens {
        position: absolute;
        border: 2px solid rgba(79, 70, 229, 0.85);
        background: rgba(79, 70, 229, 0.12);
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.65) inset;
        pointer-events: none;
        display: none;
        z-index: 2;
    }
    .muy-doc-zoom__result {
        min-height: 320px;
        max-height: 72vh;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background-color: #fff;
        background-repeat: no-repeat;
        box-shadow: inset 0 0 0 1px #f8fafc;
    }
    .muy-doc-zoom__result.is-idle {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 0.82rem;
        text-align: center;
        padding: 1rem;
        background-image: none !important;
    }
    .muy-doc-zoom__open {
        font-size: 0.8rem;
        font-weight: 700;
        color: #3730a3;
        text-decoration: none;
    }
    .muy-doc-zoom__open:hover { text-decoration: underline; }
    @media (max-width: 860px) {
        .muy-doc-zoom__row { grid-template-columns: 1fr; }
        .muy-doc-zoom__result { min-height: 220px; }
    }
</style>
<script>
    window.muyMountDocImageZoom = function (container, url, name) {
        if (!container || !url) return false;

        var supportsHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!supportsHover) {
            var plain = document.createElement('img');
            plain.src = url;
            plain.alt = name || 'Document image';
            plain.style.maxWidth = '100%';
            plain.style.maxHeight = '72vh';
            plain.style.display = 'block';
            plain.style.margin = '0 auto';
            plain.style.border = '1px solid #e2e8f0';
            plain.style.borderRadius = '10px';
            plain.style.background = '#fff';
            container.appendChild(plain);
            return false;
        }

        var zoomFactor = 2.8;
        var lensSize = 150;

        var wrap = document.createElement('div');
        wrap.className = 'muy-doc-zoom';

        var hint = document.createElement('p');
        hint.className = 'muy-doc-zoom__hint';
        hint.textContent = 'Move mouse over the document — the zoomed area appears on the right.';

        var openLink = document.createElement('a');
        openLink.className = 'muy-doc-zoom__open';
        openLink.href = url;
        openLink.target = '_blank';
        openLink.rel = 'noopener';
        openLink.textContent = 'Open full image in new tab';

        var row = document.createElement('div');
        row.className = 'muy-doc-zoom__row';

        var stage = document.createElement('div');
        stage.className = 'muy-doc-zoom__stage';

        var img = document.createElement('img');
        img.className = 'muy-doc-zoom__img';
        img.src = url;
        img.alt = name || 'Document image';
        img.draggable = false;

        var lens = document.createElement('div');
        lens.className = 'muy-doc-zoom__lens';

        var result = document.createElement('div');
        result.className = 'muy-doc-zoom__result is-idle';
        result.textContent = 'Hover over the image to zoom';

        stage.appendChild(img);
        stage.appendChild(lens);
        row.appendChild(stage);
        row.appendChild(result);
        wrap.appendChild(hint);
        wrap.appendChild(row);
        wrap.appendChild(openLink);
        container.appendChild(wrap);

        function hideLens() {
            lens.style.display = 'none';
            result.classList.add('is-idle');
            result.textContent = 'Hover over the image to zoom';
            result.style.backgroundImage = '';
        }

        function showLens() {
            lens.style.display = 'block';
            result.classList.remove('is-idle');
            result.textContent = '';
            result.style.setProperty('background-image', 'url(' + JSON.stringify(url) + ')');
        }

        function onMove(e) {
            var rect = img.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;

            if (x < 0 || y < 0 || x > rect.width || y > rect.height) {
                hideLens();
                return;
            }

            showLens();

            var half = lensSize / 2;
            var lensX = Math.max(0, Math.min(x - half, rect.width - lensSize));
            var lensY = Math.max(0, Math.min(y - half, rect.height - lensSize));

            lens.style.width = lensSize + 'px';
            lens.style.height = lensSize + 'px';
            lens.style.left = lensX + 'px';
            lens.style.top = lensY + 'px';

            var resultRect = result.getBoundingClientRect();
            var resultH = Math.max(resultRect.height, 320);
            result.style.height = resultH + 'px';

            var bgW = rect.width * zoomFactor;
            var bgH = rect.height * zoomFactor;
            result.style.backgroundSize = bgW + 'px ' + bgH + 'px';
            result.style.backgroundPosition = (-lensX * zoomFactor) + 'px ' + (-lensY * zoomFactor) + 'px';
        }

        stage.addEventListener('mousemove', onMove);
        stage.addEventListener('mouseleave', hideLens);
        stage.addEventListener('wheel', function (e) {
            e.preventDefault();
        }, { passive: false });

        return true;
    };
</script>
