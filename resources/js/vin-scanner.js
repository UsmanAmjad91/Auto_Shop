import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { api, toast, handleError, esc } from './core/http';

/**
 * VIN capture for the vehicle form:
 *  1. Barcode mode (Code 39 / Code 128 / DataMatrix / QR / PDF417) via html5-qrcode
 *  2. Text mode: camera frame -> tesseract.js OCR -> 17-char candidates
 * A VIN is only reported as scanned once it is 17 valid characters; the check
 * digit is verified (warning only for non-North-American VINs). Decoding uses the
 * server (/vin/decode) so API keys never reach the browser.
 */

const MAP = { A: 1, B: 2, C: 3, D: 4, E: 5, F: 6, G: 7, H: 8, J: 1, K: 2, L: 3, M: 4, N: 5, P: 7, R: 9, S: 2, T: 3, U: 4, V: 5, W: 6, X: 7, Y: 8, Z: 9 };
const WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

export const vinWellFormed = (v) => /^[A-HJ-NPR-Z0-9]{17}$/.test(v);
export function vinCheckDigitOk(v) {
    if (!vinWellFormed(v)) return false;
    const sum = [...v].reduce((s, c, i) => s + (/\d/.test(c) ? +c : MAP[c]) * WEIGHTS[i], 0);
    const r = sum % 11;
    return (r === 10 ? 'X' : String(r)) === v[8];
}

/** Find the best VIN inside noisy scanner/OCR text. */
export function extractVin(text, ocr = false) {
    let s = String(text || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (ocr) s = s.replace(/O/g, '0').replace(/Q/g, '0').replace(/I/g, '1');
    const candidates = [];
    for (let i = 0; i + 17 <= s.length; i++) {
        const c = s.slice(i, i + 17);
        if (vinWellFormed(c)) candidates.push(c);
    }
    return candidates.find(vinCheckDigitOk) || (candidates.length === 1 ? candidates[0] : null);
}

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('vinScanModal');
    const vinInput = document.getElementById('vin');
    if (!vinInput) return;

    const status = (msg, type = 'secondary') => {
        const el = document.getElementById('vin-scan-status');
        if (el) { el.className = `alert alert-${type} small py-2 mb-2`; el.textContent = msg; }
    };
    const resultBox = document.getElementById('vin-decode-result');

    /* ---------------- decode & fill ---------------- */
    async function decode(vin) {
        vinInput.value = vin;
        resultBox.innerHTML = '<div class="small text-secondary"><span class="spinner-border spinner-border-sm"></span> Decoding VIN…</div>';
        try {
            const res = await api(vinInput.dataset.decodeUrl, { method: 'POST', data: { vin } });
            fill(res);
        } catch (err) {
            const v = err.errors?.vin?.[0] || err.message;
            resultBox.innerHTML = `<div class="alert alert-danger small py-2 mb-0">${esc(v)}</div>`;
        }
    }

    function fill(res) {
        const warn = (res.validation?.warnings || []).map(w => `<div>${esc(w)}</div>`).join('');
        const dup = res.duplicate ? `<div class="mt-1"><strong>Duplicate:</strong> this VIN already exists as stock ${esc(res.duplicate.stock_number)} ${res.duplicate.url ? `<a href="${esc(res.duplicate.url)}">(open)</a>` : '(archived)'}.</div>` : '';
        if (!res.success) {
            resultBox.innerHTML = `<div class="alert alert-warning small py-2 mb-0">${esc(res.message)}${warn}${dup}</div>`;
            return;
        }
        const filled = [];
        Object.entries(res.data).forEach(([k, v]) => {
            const input = document.querySelector(`#vehicle-form [name="${k}"]`);
            if (!input || v === null || v === '') return;
            if (input.tagName === 'SELECT' && ![...input.options].some(o => o.value === String(v))) return;
            if (input.value && input.value !== String(v) && !input.dataset.autofilled) return; // never overwrite manual edits
            input.value = v;
            input.dataset.autofilled = '1';
            input.classList.add('border-success');
            filled.push(k);
        });
        const hidden = document.querySelector('#vehicle-form [name="decoded_data"]');
        if (hidden) hidden.value = JSON.stringify({ provider: res.provider, decoded_at: new Date().toISOString(), data: res.data, raw: res.raw });
        resultBox.innerHTML = `<div class="alert alert-${res.duplicate ? 'warning' : 'success'} small py-2 mb-0">
            <strong>${esc(res.data.year || '')} ${esc(res.data.make || '')} ${esc(res.data.model || '')}</strong> ${esc(res.data.trim || '')} — ${esc(res.message)}
            <div class="text-secondary">Auto-filled: ${filled.map(esc).join(', ') || 'nothing new'}. Review and correct any field before saving.</div>${warn}${dup}</div>`;
    }

    document.getElementById('vin-decode-btn')?.addEventListener('click', () => {
        const vin = vinInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (!vinWellFormed(vin)) {
            resultBox.innerHTML = '<div class="alert alert-danger small py-2 mb-0">A VIN must be 17 characters (no I, O or Q).</div>';
            return;
        }
        decode(vin);
    });
    vinInput.addEventListener('input', () => { vinInput.value = vinInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 17); });

    if (!modalEl) return;

    /* ---------------- camera scanning ---------------- */
    let qr = null;
    let stream = null;
    let worker = null;
    let busy = false;

    async function stopAll() {
        try { if (qr?.isScanning) await qr.stop(); } catch { /* ignore */ }
        qr = null;
        stream?.getTracks().forEach(t => t.stop());
        stream = null;
    }

    function found(vin, source) {
        const ok = vinCheckDigitOk(vin);
        status(`VIN recognised from ${source}: ${vin}${ok ? ' (check digit valid)' : ' — check digit does not match; verify manually'}`, ok ? 'success' : 'warning');
        stopAll();
        setTimeout(() => window.bootstrap.Modal.getInstance(modalEl)?.hide(), ok ? 600 : 1800);
        decode(vin);
    }

    async function startBarcode() {
        await stopAll();
        status('Point the camera at the VIN barcode (door jamb or windshield sticker).');
        qr = new Html5Qrcode('vin-reader', {
            formatsToSupport: [Html5QrcodeSupportedFormats.CODE_39, Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.DATA_MATRIX,
                Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.PDF_417],
            verbose: false,
        });
        try {
            await qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: (w, h) => ({ width: Math.floor(w * 0.9), height: Math.floor(h * 0.4) }) },
                (text) => { const vin = extractVin(text); if (vin) found(vin, 'barcode'); else status(`Read "${text.slice(0, 30)}" but it is not a valid VIN — keep scanning.`, 'warning'); },
                () => { /* no code in frame */ });
        } catch (err) { status(cameraError(err), 'danger'); }
    }

    async function startOcr() {
        await stopAll();
        const video = document.getElementById('vin-video');
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
            video.srcObject = stream;
            await video.play();
            status('Fill the dashed box with the printed VIN, hold steady, then tap “Read VIN”.');
        } catch (err) { status(cameraError(err), 'danger'); }
    }

    async function readOcr() {
        if (busy || !stream) return;
        busy = true;
        const btn = document.getElementById('vin-ocr-read');
        btn.disabled = true;
        try {
            const video = document.getElementById('vin-video');
            const canvas = document.createElement('canvas');
            const sx = video.videoWidth * 0.08, sw = video.videoWidth * 0.84, sy = video.videoHeight * 0.4, sh = video.videoHeight * 0.2;
            canvas.width = sw; canvas.height = sh;
            const ctx = canvas.getContext('2d');
            ctx.filter = 'grayscale(1) contrast(1.6)';
            ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sw, sh);
            status('Reading text… (first use downloads the OCR engine)', 'info');
            if (!worker) {
                const { createWorker } = await import('tesseract.js');
                worker = await createWorker('eng');
                await worker.setParameters({ tessedit_char_whitelist: 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789', tessedit_pageseg_mode: '7' });
            }
            const { data } = await worker.recognize(canvas);
            const vin = extractVin(data.text, true);
            if (vin) found(vin, 'text recognition');
            else status(`No valid 17-character VIN found (read: "${data.text.trim().slice(0, 40) || 'nothing'}"). Adjust angle/lighting and try again, or type it manually.`, 'warning');
        } catch (err) {
            status('Text recognition failed: ' + err.message, 'danger');
        } finally { busy = false; btn.disabled = false; }
    }

    function cameraError(err) {
        if (!window.isSecureContext) return 'Camera access requires HTTPS (or localhost). Enter the VIN manually.';
        if (err?.name === 'NotAllowedError') return 'Camera permission was denied. Allow camera access in your browser or enter the VIN manually.';
        if (err?.name === 'NotFoundError') return 'No camera was found on this device. Enter the VIN manually.';
        return 'Could not start the camera: ' + (err?.message || err);
    }

    modalEl.addEventListener('shown.bs.modal', () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            status('Camera access requires HTTPS (or localhost) and a supported browser. Enter the VIN manually.', 'danger');
            return;
        }
        document.querySelector('#vin-mode-barcode').checked ? startBarcode() : startOcr();
    });
    modalEl.addEventListener('hidden.bs.modal', stopAll);
    document.querySelectorAll('[name="vin-mode"]').forEach(r => r.addEventListener('change', () => {
        document.getElementById('vin-barcode-pane').classList.toggle('d-none', r.value !== 'barcode' || !r.checked);
        document.getElementById('vin-ocr-pane').classList.toggle('d-none', r.value !== 'ocr' || !r.checked);
        if (r.checked) (r.value === 'barcode' ? startBarcode : startOcr)();
    }));
    document.getElementById('vin-ocr-read')?.addEventListener('click', readOcr);
    document.getElementById('vin-manual-use')?.addEventListener('click', () => {
        const raw = document.getElementById('vin-manual').value;
        const vin = extractVin(raw) || raw.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (!vinWellFormed(vin)) { status('That is not a valid 17-character VIN.', 'danger'); return; }
        found(vin, 'manual entry');
    });
});

export { toast, handleError };
