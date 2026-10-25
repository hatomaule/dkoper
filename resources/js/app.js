import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('dkTheme', () => ({
    dark: localStorage.getItem('dk-theme') === 'dark',
    init() {
        document.documentElement.dataset.theme = this.dark ? 'dark' : 'light';
    },
    toggle() {
        this.dark = !this.dark;
        document.documentElement.dataset.theme = this.dark ? 'dark' : 'light';
        localStorage.setItem('dk-theme', this.dark ? 'dark' : 'light');
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-table-tools]').forEach((panel) => {
        const search = panel.querySelector('[data-table-search]');
        const rows = [...panel.querySelectorAll('[data-table-row]')];

        search?.addEventListener('input', () => {
            const needle = search.value.trim().toLocaleLowerCase();
            rows.forEach((row) => {
                row.hidden = needle !== '' && !row.textContent.toLocaleLowerCase().includes(needle);
            });
        });

        panel.querySelectorAll('[data-view-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                panel.querySelectorAll('[data-table-view]').forEach((view) => {
                    view.classList.toggle('hidden', view.dataset.tableView !== button.dataset.viewMode);
                });
                panel.querySelectorAll('[data-view-mode]').forEach((toggle) => {
                    toggle.classList.toggle('is-active', toggle === button);
                });
            });
        });

        panel.querySelector('[data-page-size]')?.addEventListener('change', (event) => {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', event.target.value);
            url.searchParams.delete('page');
            window.location.assign(url);
        });
    });

    const loanSelect = document.querySelector('[data-loan-select]');
    const sourceSelect = document.querySelector('[data-nominal-source]');
    const installmentPanel = document.querySelector('[data-system-installment]');
    const manualAmount = document.querySelector('[data-manual-amount]');
    const amountInput = manualAmount?.querySelector('input');
    if (!loanSelect || !sourceSelect || !installmentPanel) return;

    const updateInstallment = () => {
        const option = loanSelect.selectedOptions[0];
        const amount = Number(option?.dataset.amount || 0);
        const installmentId = option?.dataset.installment || '';

        document.querySelector('[data-installment-label]').textContent = option?.dataset.sequence
            ? `Setoran ke-${option.dataset.sequence}`
            : 'Tidak ada cicilan tersisa';
        document.querySelector('[data-installment-amount]').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
        installmentPanel.querySelector('input[name="installment_id"]').setAttribute('value', installmentId);
        installmentPanel.querySelector('input[name="installment_id"]').value = installmentId;
        const systemOption = sourceSelect.querySelector('option[value="system"]');
        systemOption.disabled = amount <= 0;
        if (amount <= 0) sourceSelect.value = 'manual';
        updateNominalMode();
    };

    function updateNominalMode() {
        const isManual = sourceSelect.value === 'manual';
        manualAmount?.classList.toggle('hidden', !isManual);
        if (amountInput) {
            amountInput.disabled = !isManual;
            amountInput.required = isManual;
        }
        installmentPanel.classList.toggle('hidden', isManual);
    }

    loanSelect.addEventListener('change', updateInstallment);
    sourceSelect.addEventListener('change', updateNominalMode);
    updateInstallment();

    const methodSelect = document.querySelector('[data-payment-method]');
    const proofPanel = document.querySelector('[data-transfer-proof]');
    const finalProofInput = document.querySelector('.dk-proof-final');
    const proofStatus = document.querySelector('[data-proof-status]');
    const proofPreview = document.querySelector('[data-proof-preview]');

    methodSelect?.addEventListener('change', () => {
        proofPanel?.classList.toggle('hidden', methodSelect.value !== 'transfer');
        if (methodSelect.value !== 'transfer' && finalProofInput) finalProofInput.value = '';
    });

    document.querySelectorAll('.dk-proof-source').forEach((sourceInput) => {
        sourceInput.addEventListener('change', async () => {
            const original = sourceInput.files?.[0];
            if (!original || !finalProofInput) return;
            try {
                const bitmap = await createImageBitmap(original);
                const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(bitmap.width * scale);
                canvas.height = Math.round(bitmap.height * scale);
                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                bitmap.close();
                const blob = await new Promise((resolve, reject) => {
                    canvas.toBlob((result) => result ? resolve(result) : reject(new Error('Gambar tidak dapat dikompres.')), 'image/webp', 0.78);
                });
                const compressed = new File([blob], 'bukti-transfer.webp', { type: 'image/webp' });
                const transfer = new DataTransfer();
                transfer.items.add(compressed);
                finalProofInput.files = transfer.files;
                proofStatus.textContent = `Siap diunggah · ${(compressed.size / 1024).toFixed(0)} KB (WebP)`;
                proofPreview.src = URL.createObjectURL(compressed);
                proofPreview.classList.remove('hidden');
            } catch {
                finalProofInput.value = '';
                proofStatus.textContent = 'Foto tidak dapat diproses. Coba pilih gambar JPG, PNG, atau WebP lain.';
            }
        });
    });

    document.querySelector('[data-payment-form]')?.addEventListener('submit', (event) => {
        if (methodSelect?.value === 'transfer' && !finalProofInput?.files?.length) {
            event.preventDefault();
            proofStatus.textContent = 'Pilih foto bukti transfer atau ambil foto dengan kamera terlebih dahulu.';
            proofStatus.classList.add('text-red-600');
        }
    });
});
