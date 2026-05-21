<script>
(function () {
    const embedScriptSrc = 'https://www.instagram.com/embed.js';
    let embedScriptPromise = null;

    function loadInstagramEmbedScript() {
        if (window.instgrm?.Embeds) {
            return Promise.resolve();
        }
        if (embedScriptPromise) {
            return embedScriptPromise;
        }
        embedScriptPromise = new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[data-smp-instagram-embed="1"]');
            if (existing) {
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', reject);
                return;
            }
            const script = document.createElement('script');
            script.async = true;
            script.src = embedScriptSrc;
            script.dataset.smpInstagramEmbed = '1';
            script.onload = function () { resolve(); };
            script.onerror = reject;
            document.body.appendChild(script);
        });

        return embedScriptPromise;
    }

    window.smpMountInstagramEmbeds = function (root) {
        const scope = root || document;
        const blocks = scope.querySelectorAll('.smp-preview-panel__embed[data-instagram-url]');
        if (!blocks.length) {
            return;
        }

        loadInstagramEmbedScript()
            .then(function () {
                if (window.instgrm?.Embeds?.process) {
                    window.instgrm.Embeds.process(scope);
                }
            })
            .catch(function () {
                blocks.forEach(function (block) {
                    block.classList.add('smp-preview-panel__embed--failed');
                });
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.smpMountInstagramEmbeds(document);
    });
})();
</script>
