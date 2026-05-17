<?php
$pageTitle = 'Dosya Paylaşımı';
require_once dirname(__DIR__) . '/app/includes/header.php';
$serverMaxBytes = Security::effectiveMaxBytes();
$maxSizeFmt = formatBytes($serverMaxBytes);
$allowGuest = Settings::get('allow_guest', ALLOW_GUEST_UPLOAD ? '1' : '0') === '1';
$heroTitle = Settings::get('hero_title', 'Basit ve gizli dosya paylaşımı');
$heroDescription = Settings::get('hero_description', 'Send ile dosyalarınızı güvenli bir bağlantı üzerinden paylaşın. Şifre ekleyin, süre belirleyin ve saniyeler içinde paylaşım linkinizi oluşturun.');
$packageOptions = [10, 15, 20, 25, 30];
?>
<?php require_once dirname(__DIR__) . '/app/includes/navbar.php'; ?>

<main>
<div class="container">
    <section class="hero hero-compact">
        <div class="hero-shell glass-panel">
            <div class="hero-grid hero-grid-compact">
                <div class="hero-left">
                    <?php if (!$allowGuest && !$isLoggedIn): ?>
                    <div class="upload-box locked-box" style="text-align:center; padding:48px 32px;">
                        <div class="upload-icon" style="margin:0 auto 16px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 0L8 7m4-4l4 4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
                        </div>
                        <h3 style="margin-bottom:8px;">Yükleme için giriş yapın</h3>
                        <p class="text-muted" style="font-size:.95rem; margin-bottom:20px;">Dosya paylaşımını başlatmak için hesabınıza giriş yapmanız gerekiyor.</p>
                        <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;">
                            <a href="/login.php" class="btn btn-primary">Giriş Yap</a>
                            <a href="/register.php" class="btn btn-secondary">Üye Ol</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="upload-box" id="dropZone" data-server-max-bytes="<?= (int)$serverMaxBytes ?>">
                        <input type="file" id="file-input" multiple>
                        <div class="upload-drop-zone" id="dropZone-inner">
                            <div class="upload-icon upload-icon-lg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 17.5C4 19.433 5.567 21 7.5 21h9c1.933 0 3.5-1.567 3.5-3.5S18.433 14 16.5 14c-.182 0-.36.014-.535.04A5.5 5.5 0 0 0 5.33 12.4 3.75 3.75 0 0 0 4 17.5Z"/>
                                </svg>
                            </div>
                            <div class="upload-title">Dosyaları sürükleyip bırak</div>
                            <div class="upload-subtitle">veya <button class="btn btn-secondary btn-sm" id="btnSelectFiles" type="button">dosya seç</button></div>
                            <div class="upload-limit">Sunucu üst limiti: <?= $maxSizeFmt ?></div>
                        </div>

                        <div id="fileList" class="file-list"></div>
                        <div id="uploadError" class="alert alert-error" style="display:none; margin-top:12px;"></div>

                        <div class="upload-options" id="uploadOptions" style="display:none;">
                            <div class="option-group option-group-full">
                                <label for="uploadCap">Paylaşım boyutu seç</label>
                                <select id="uploadCap">
                                    <?php foreach ($packageOptions as $sizeGb): ?>
                                        <option value="<?= $sizeGb ?>" <?= $sizeGb === 10 ? 'selected' : '' ?>><?= $sizeGb ?> GB</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">Seçtiğiniz paylaşım boyutuna göre toplam yükleme limiti uygulanır.</small>
                            </div>
                            <div class="option-group option-group-full">
                                <label for="uploadTitle">Başlık (opsiyonel)</label>
                                <input type="text" id="uploadTitle" class="form-input" placeholder="Paylaşım başlığı">
                            </div>
                            <div class="option-group">
                                <label for="uploadPassword">Şifre (opsiyonel)</label>
                                <input type="password" id="uploadPassword" class="form-input" placeholder="Şifreyle koru">
                            </div>
                            <div class="option-group">
                                <label for="uploadExpire">Saklama süresi</label>
                                <select id="uploadExpire">
                                    <option value="1">1 Saat</option>
                                    <option value="6">6 Saat</option>
                                    <option value="24" selected>24 Saat</option>
                                    <option value="72">3 Gün</option>
                                    <option value="168">7 Gün</option>
                                    <option value="720">30 Gün</option>
                                    <option value="0">Süresiz</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label for="uploadLimit">İndirme limiti</label>
                                <select id="uploadLimit">
                                    <option value="0">Limitsiz</option>
                                    <option value="1">1 İndirme</option>
                                    <option value="5">5 İndirme</option>
                                    <option value="10">10 İndirme</option>
                                    <option value="25">25 İndirme</option>
                                    <option value="50">50 İndirme</option>
                                </select>
                            </div>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">

                        <div class="upload-progress" id="uploadProgress">
                            <div class="progress-bar-outer">
                                <div class="progress-bar-inner" id="progressBar"></div>
                            </div>
                            <div class="progress-text" id="progressText">Hazırlanıyor...</div>
                        </div>

                        <div class="upload-actions-row">
                            <div class="upload-mini-note">Güvenli bağlantı · Süreli link · Esnek dosya limiti</div>
                            <button class="btn btn-primary btn-lg" id="uploadBtn" disabled type="button">
                                <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                                Dosyaları Gönder
                            </button>
                        </div>

                        <div class="upload-result" id="uploadResult">
                            <div class="result-headline"><span>✅</span><strong>Paylaşım linkiniz hazır</strong></div>
                            <div class="result-link-row">
                                <div class="result-link" id="resultLink"></div>
                                <button class="btn btn-primary btn-sm" id="copyBtn" onclick="copyText(document.getElementById('resultLink').textContent, this)">Kopyala</button>
                            </div>
                            <div class="share-buttons" style="margin-top:12px;">
                                <a href="#" id="shareWa" target="_blank" class="share-btn share-wa">WhatsApp</a>
                                <a href="#" id="shareTg" target="_blank" class="share-btn share-tg">Telegram</a>
                                <a href="#" id="shareMail" class="share-btn share-mail">E-posta</a>
                            </div>
                            <div style="margin-top:16px;text-align:center;">
                                <img id="qrCode" src="" alt="QR Kod" class="qr-img" width="120" height="120">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="hero-right">
                    <div class="hero-badge">Güvenli paylaşım</div>
                    <h1 class="hero-title compact"><?= nl2br(e($heroTitle)) ?></h1>
                    <p class="hero-desc compact"><?= e($heroDescription) ?></p>
                    <div class="hero-meta-grid">
                        <div class="meta-pill"><strong>10–30 GB</strong><span>Seçilebilir paylaşım limiti</span></div>
                        <div class="meta-pill"><strong>Özel link</strong><span>Hızlı kopyala ve paylaş</span></div>
                        <div class="meta-pill"><strong>Şifre koruması</strong><span>Ek güvenlik seçeneği</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
</main>

<script src="/assets/js/upload.js"></script>
<script>
const fileListEl = document.getElementById('fileList');
const optionsEl = document.getElementById('uploadOptions');
if (fileListEl && optionsEl) {
    const obs = new MutationObserver(() => {
        optionsEl.style.display = fileListEl.children.length > 0 ? 'grid' : 'none';
    });
    obs.observe(fileListEl, { childList: true });
}
</script>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
