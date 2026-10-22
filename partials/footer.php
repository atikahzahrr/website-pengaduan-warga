      </section>
    </main>
  </div>
</div>
<footer>Seluruh aduan, identitas, survei, dan kamera pada dashboard ini merupakan data simulasi.</footer>
<dialog id="detail-dialog" aria-labelledby="dialog-title">
  <div class="dialog-body">
    <div class="dialog-header">
      <h2 id="dialog-title">Detail</h2>
      <button id="close-dialog" class="dialog-close" type="button" aria-label="Tutup detail">×</button>
    </div>
    <div id="dialog-content"></div>
  </div>
</dialog>
<?php if (!empty($trendConfig)): ?>
<script>window.TREND_CONFIG = <?= json_encode($trendConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
