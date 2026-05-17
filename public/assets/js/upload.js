(function () {
    'use strict';

    const dropZone    = document.getElementById('dropZone');
    const fileInput   = document.getElementById('file-input');
    const fileList    = document.getElementById('fileList');
    const uploadBtn   = document.getElementById('uploadBtn');
    const progress    = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const progressTxt = document.getElementById('progressText');
    const result      = document.getElementById('uploadResult');
    const resultLink  = document.getElementById('resultLink');
    const btnSelect   = document.getElementById('btnSelectFiles');
    const uploadCapEl = document.getElementById('uploadCap');
    const serverMaxBytes = Number(dropZone?.dataset.serverMaxBytes || 0);

    if (!dropZone) return;

    let selectedFiles = [];

    ['dragenter', 'dragover'].forEach(ev => {
        dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });
    });
    dropZone.addEventListener('drop', e => { addFiles(e.dataTransfer.files); });

    if (btnSelect) btnSelect.addEventListener('click', () => fileInput.click());
    if (fileInput) fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
        fileInput.value = '';
    });

    function addFiles(files) {
        for (const f of files) {
            if (!selectedFiles.find(x => x.name === f.name && x.size === f.size)) {
                selectedFiles.push(f);
            }
        }
        renderFileList();
    }

    function removeFile(idx) {
        selectedFiles.splice(idx, 1);
        renderFileList();
    }

    function getSelectedCapBytes() {
        const gb = Number(uploadCapEl?.value || 10);
        return gb * 1024 * 1024 * 1024;
    }

    function getEffectiveCapBytes() {
        const selectedCap = getSelectedCapBytes();
        return serverMaxBytes > 0 ? Math.min(selectedCap, serverMaxBytes) : selectedCap;
    }

    function currentTotalBytes() {
        return selectedFiles.reduce((sum, f) => sum + (f.size || 0), 0);
    }

    function validateTotalSize(show = false) {
        const total = currentTotalBytes();
        const selected = getSelectedCapBytes();
        const effective = getEffectiveCapBytes();
        if (total <= effective) return true;

        const selectedLabel = formatSize(selected);
        const effectiveLabel = formatSize(effective);
        const msg = selected !== effective
            ? `Seçtiğiniz paylaşım limiti ${selectedLabel}, ancak sunucuda aktif üst limit ${effectiveLabel}. Lütfen daha küçük bir toplam dosya boyutu seçin.`
            : `Toplam dosya boyutu seçtiğiniz ${selectedLabel} paylaşım limitini aşıyor.`;
        if (show) showError(msg);
        return false;
    }

    if (uploadCapEl) {
        uploadCapEl.addEventListener('change', () => {
            clearError();
            validateTotalSize(true);
        });
    }

    function renderFileList() {
        if (!fileList) return;
        fileList.innerHTML = '';
        selectedFiles.forEach((f, i) => {
            const div = document.createElement('div');
            div.className = 'file-item fade-in';
            div.innerHTML = `
                <span class="file-item-icon">${getIcon(f.type)}</span>
                <div class="file-item-info">
                    <div class="file-item-name" title="${esc(f.name)}">${esc(f.name)}</div>
                    <div class="file-item-size">${formatSize(f.size)}</div>
                </div>
                <button class="file-item-remove" data-idx="${i}" title="Kaldır" type="button">×</button>`;
            fileList.appendChild(div);
        });
        fileList.querySelectorAll('.file-item-remove').forEach(btn => {
            btn.addEventListener('click', () => removeFile(+btn.dataset.idx));
        });
        if (uploadBtn) uploadBtn.disabled = selectedFiles.length === 0;
        clearError();
        validateTotalSize(selectedFiles.length > 0);
    }

    if (uploadBtn) uploadBtn.addEventListener('click', startUpload);

    async function startUpload() {
        if (selectedFiles.length === 0) return;
        clearError();
        if (!validateTotalSize(true)) return;

        const formData = new FormData();
        selectedFiles.forEach(f => formData.append('files[]', f));

        const title     = document.getElementById('uploadTitle')?.value ?? '';
        const password  = document.getElementById('uploadPassword')?.value ?? '';
        const expire    = document.getElementById('uploadExpire')?.value ?? '24';
        const limit     = document.getElementById('uploadLimit')?.value ?? '0';
        const capGb     = document.getElementById('uploadCap')?.value ?? '10';
        const csrfToken = document.querySelector('[name="csrf_token"]')?.value ?? '';

        formData.append('title', title);
        formData.append('password', password);
        formData.append('expire_hours', expire);
        formData.append('download_limit', limit);
        formData.append('upload_cap_gb', capGb);
        formData.append('csrf_token', csrfToken);

        setUploading(true);
        if (progress) progress.style.display = 'block';
        if (result) result.style.display = 'none';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/upload.php');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                if (progressBar) progressBar.style.width = pct + '%';
                if (progressTxt) progressTxt.textContent = pct + '% yükleniyor...';
            }
        });

        xhr.addEventListener('load', () => {
            setUploading(false);
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) showResult(data);
                else showError(data.message || 'Yükleme başarısız.');
            } catch {
                showError('Beklenmeyen bir hata oluştu.');
            }
        });

        xhr.addEventListener('error', () => {
            setUploading(false);
            showError('Ağ hatası. Lütfen tekrar deneyin.');
        });

        xhr.send(formData);
    }

    function showResult(data) {
        if (!result) return;
        result.style.display = 'block';
        if (resultLink) resultLink.textContent = data.url;

        const qrImg = document.getElementById('qrCode');
        if (qrImg) qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(data.url);

        const copyBtn = document.getElementById('copyBtn');
        if (copyBtn) copyBtn.onclick = () => window.copyText(data.url, copyBtn);

        const waBtn = document.getElementById('shareWa');
        const tgBtn = document.getElementById('shareTg');
        const mailBtn = document.getElementById('shareMail');
        if (waBtn) waBtn.href = 'https://wa.me/?text=' + encodeURIComponent('Dosya paylaşımı: ' + data.url);
        if (tgBtn) tgBtn.href = 'https://t.me/share/url?url=' + encodeURIComponent(data.url);
        if (mailBtn) mailBtn.href = 'mailto:?subject=Dosya paylaşımı&body=' + encodeURIComponent('Dosyayı indirmek için: ' + data.url);

        selectedFiles = [];
        renderFileList();
        if (progress) progress.style.display = 'none';
    }

    function clearError() {
        const errEl = document.getElementById('uploadError');
        if (errEl) {
            errEl.textContent = '';
            errEl.style.display = 'none';
        }
    }

    function showError(msg) {
        if (progress) progress.style.display = 'none';
        const errEl = document.getElementById('uploadError');
        if (errEl) {
            errEl.textContent = msg;
            errEl.style.display = 'block';
        } else {
            alert(msg);
        }
    }

    function setUploading(state) {
        if (!uploadBtn) return;
        uploadBtn.disabled = state;
        uploadBtn.innerHTML = state
            ? '<span class="spinner"></span> Yükleniyor...'
            : '<svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg> Dosyaları Gönder';
    }

    function formatSize(bytes) {
        const u = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        while (bytes >= 1024 && i < u.length - 1) {
            bytes /= 1024;
            i++;
        }
        return Math.round(bytes * 10) / 10 + ' ' + u[i];
    }
    function getIcon(mime) {
        if (!mime) return '📁';
        if (mime.startsWith('image/')) return '🖼️';
        if (mime.startsWith('video/')) return '🎬';
        if (mime.startsWith('audio/')) return '🎵';
        if (mime === 'application/pdf') return '📄';
        if (mime.includes('zip') || mime.includes('rar')) return '🗜️';
        return '📁';
    }
    function esc(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
