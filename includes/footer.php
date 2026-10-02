    </main>
    <footer class="foot">
      <span><?= e(APP_NAME) ?> &middot; v<?= APP_VERSION ?></span>
      <span>Company 1 &nbsp;|&nbsp; Company 2</span>
    </footer>
    <!-- ===== RED THUNDER STAMP - DO NOT REMOVE (self-restoring) ===== -->
    <div class="rt-stamp" id="rt-stamp" data-rt="1">created by red thunder E.G</div>
    <script>
    /* If someone deletes the stamp element from the DOM, this re-injects it. */
    (function(){
      try {
        var TEXT = "created by red thunder E.G";
        function ensure(){
          if (!document.querySelector('.rt-stamp')) {
            var n = document.createElement('div');
            n.className = 'rt-stamp'; n.setAttribute('data-rt','1');
            n.style.cssText = 'position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none;background:transparent;padding:2px 0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;';
            n.textContent = TEXT;
            (document.body || document.documentElement).appendChild(n);
          }
        }
        ensure();
        var mo = new MutationObserver(function(m){ for (var i=0;i<m.length;i++){ var r=m[i].removedNodes; for (var j=0;j<r.length;j++){ if (r[j] && (r[j].classList && r[j].classList.contains('rt-stamp'))) ensure(); }} });
        mo.observe(document.documentElement, {childList:true, subtree:true});
        window.addEventListener('DOMContentLoaded', ensure);
        window.addEventListener('load', ensure);
      } catch(e){}
    })();
    </script>
  </div>
</div>
<script src="<?= ASSET_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
<script>
/* Footer guard: re-pull in the RT stamp if both lines above were stripped. */
(function(){var s=document.querySelector('.rt-stamp'); if(!s){var n=document.createElement('div');n.className='rt-stamp';n.style.cssText='position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none';n.textContent='created by red thunder E.G';document.body.appendChild(n);}})();
</script>
<?php echo '<div class="rt-stamp" style="position:fixed;left:0;right:0;bottom:0;text-align:center;font-size:8.5px;color:#ff252b;letter-spacing:.04em;z-index:2147483647;pointer-events:none">created by red thunder E.G</div>'; ?>
</body>
</html>
