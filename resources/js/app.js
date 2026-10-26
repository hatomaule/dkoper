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
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        if (input.parentElement?.querySelector('.dk-password-toggle')) {
            return;
        }

        const wrap = input.parentElement;
        if (!wrap || wrap.querySelector('button.dk-password-toggle')) {
            return;
        }

        wrap.style.position = 'relative';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dk-password-toggle';
        button.setAttribute('aria-label', 'Tampilkan password');
        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
        button.addEventListener('click', () => {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.classList.toggle('is-visible', isHidden);
            button.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
        });
        wrap.appendChild(button);
    });

    const formatRupiahInput = (input) => {
        const digits = input.value.replace(/\D/g, '');
        input.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const minimum = Number(input.dataset.min || 0);
        const maximum = input.dataset.max ? Number(input.dataset.max) : null;
        const belowMinimum = (input.required && !digits) || (digits && Number(digits) < minimum);
        const aboveMaximum = digits && maximum !== null && Number(digits) > maximum;
        input.setCustomValidity(aboveMaximum
            ? `Nominal maksimal Rp ${new Intl.NumberFormat('id-ID').format(maximum)}.`
            : belowMinimum
            ? `Nominal minimal Rp ${new Intl.NumberFormat('id-ID').format(minimum || 1)}.`
            : '');
    };

    document.querySelectorAll('[data-format-rupiah]').forEach((input) => {
        formatRupiahInput(input);
        input.addEventListener('input', () => formatRupiahInput(input));
        input.form?.addEventListener('submit', () => {
            input.value = input.value.replace(/\D/g, '');
        });
    });

    document.querySelectorAll('[data-table-tools]').forEach((panel) => {
        const search = panel.querySelector('[data-table-search]');
        const rows = [...panel.querySelectorAll('[data-table-row]')];
        const applyRowFilters = () => {
            const needle = search?.value.trim().toLocaleLowerCase() || '';
            rows.forEach((row) => {
                row.hidden = needle !== '' && !row.textContent.toLocaleLowerCase().includes(needle);
            });
        };

        search?.addEventListener('input', applyRowFilters);
        applyRowFilters();

        panel.querySelectorAll('[data-view-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                panel.querySelectorAll('[data-table-view]').forEach((view) => {
                    view.hidden = view.dataset.tableView !== button.dataset.viewMode;
                    view.classList.toggle('hidden', view.hidden);
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

        const mobileDefault = panel.dataset.mobileDefault;
        const initialView = mobileDefault && window.matchMedia('(max-width: 640px)').matches
            ? mobileDefault
            : panel.querySelector('[data-view-mode].is-active')?.dataset.viewMode;
        if (initialView) {
            panel.querySelectorAll('[data-table-view]').forEach((view) => {
                view.hidden = view.dataset.tableView !== initialView;
                view.classList.toggle('hidden', view.hidden);
            });
            panel.querySelectorAll('[data-view-mode]').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.viewMode === initialView);
            });
        }
    });

    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector(button.dataset.openDialog)?.showModal();
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('dialog')?.close();
        });
    });
    document.querySelectorAll('dialog[data-open-on-error]').forEach((dialog) => dialog.showModal());

    const loanSelect = document.querySelector('[data-loan-select]');
    const installmentSelect = document.querySelector('[data-installment-select]');
    const sourceSelect = document.querySelector('[data-nominal-source]');
    const installmentPanel = document.querySelector('[data-system-installment]');
    const manualAmount = document.querySelector('[data-manual-amount]');
    const amountInput = manualAmount?.querySelector('input');
    const editLoanSelect = document.querySelector('[data-edit-loan-select]');
    const editInstallmentSelect = document.querySelector('[data-edit-installment-select]');

    const setInstallmentOptions = (loan, installment, selectedId = '') => {
        if (!loan || !installment) return null;
        const choices = JSON.parse(loan.selectedOptions[0]?.dataset.installments || '[]');
        installment.replaceChildren();
        choices.forEach((choice) => {
            const option = document.createElement('option');
            option.value = choice.id;
            option.textContent = `Cicilan ke-${choice.sequence} · sisa Rp ${new Intl.NumberFormat('id-ID').format(choice.amount)}`;
            option.dataset.sequence = choice.sequence;
            option.dataset.amount = choice.amount;
            installment.appendChild(option);
        });
        if (selectedId && choices.some((choice) => String(choice.id) === String(selectedId))) {
            installment.value = String(selectedId);
        }
        return choices.find((choice) => String(choice.id) === installment.value) || choices[0] || null;
    };

    const updateInstallment = () => {
        const selected = setInstallmentOptions(loanSelect, installmentSelect, installmentSelect.value);
        const amount = Number(selected?.amount || 0);
        document.querySelector('[data-installment-label]').textContent = selected?.sequence
            ? `Setoran ke-${selected.sequence}`
            : 'Pilih cicilan';
        document.querySelector('[data-installment-amount]').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
        const systemOption = sourceSelect.querySelector('option[value="system"]');
        systemOption.disabled = !selected;
        if (!selected) sourceSelect.value = 'manual';
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

    if (loanSelect && installmentSelect && sourceSelect && installmentPanel) {
        loanSelect.addEventListener('change', updateInstallment);
        installmentSelect.addEventListener('change', updateInstallment);
        sourceSelect.addEventListener('change', updateNominalMode);
        updateInstallment();
    }

    const methodSelect = document.querySelector('[data-payment-method]');
    const proofPanel = document.querySelector('[data-transfer-proof]');
    const finalProofInput = document.querySelector('.dk-proof-final');
    const proofStatus = document.querySelector('[data-proof-status]');
    const proofPreview = document.querySelector('[data-proof-preview]');

    document.querySelectorAll('[data-payment-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.querySelector('[data-payment-edit-dialog]');
            const form = modal?.querySelector('form');
            const amount = modal?.querySelector('[name="amount"]');
            if (!modal || !form || !amount || !editLoanSelect || !editInstallmentSelect) return;
            form.action = button.dataset.url;
            amount.value = button.dataset.amount;
            editLoanSelect.value = button.dataset.loanId;
            setInstallmentOptions(editLoanSelect, editInstallmentSelect, button.dataset.installmentId);
            formatRupiahInput(amount);
            modal.showModal();
        });
    });
    editLoanSelect?.addEventListener('change', () => setInstallmentOptions(editLoanSelect, editInstallmentSelect));

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
