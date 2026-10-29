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
                panel.classList.toggle('is-card-view', button.dataset.viewMode === 'cards');
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
            panel.classList.toggle('is-card-view', initialView === 'cards');
            panel.querySelectorAll('[data-table-view]').forEach((view) => {
                view.hidden = view.dataset.tableView !== initialView;
                view.classList.toggle('hidden', view.hidden);
            });
            panel.querySelectorAll('[data-view-mode]').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.viewMode === initialView);
            });
        }
    });

    document.querySelectorAll('[data-list-tools]').forEach((panel) => {
        const search = panel.querySelector('[data-list-search]');
        const rows = [...panel.querySelectorAll('[data-list-row]')];
        const items = new Map();
        rows.forEach((row, index) => {
            const key = row.dataset.listKey || `row-${index}`;
            const item = items.get(key) || { nodes: [], text: '', role: row.dataset.role, status: row.dataset.status };
            item.nodes.push(row);
            const fieldText = [...row.querySelectorAll('input, select')].map((field) => field.value).join(' ');
            item.text += ` ${row.textContent || ''} ${fieldText}`;
            items.set(key, item);
        });
        const pageSize = panel.querySelector('[data-list-page-size]');
        let currentPage = 1;
        const filterValues = () => [...panel.querySelectorAll('[data-list-filter]')]
            .reduce((values, select) => ({ ...values, [select.dataset.listFilter]: select.value }), {});
        const render = () => {
            const needle = search?.value.trim().toLocaleLowerCase() || '';
            const filters = filterValues();
            const filtered = [...items.values()].filter((item) => {
                return (!needle || item.text.toLocaleLowerCase().includes(needle))
                    && Object.entries(filters).every(([key, value]) => !value || item[key] === value);
            });
            const requestedSize = pageSize?.value || 'all';
            const size = requestedSize === 'all' ? Math.max(filtered.length, 1) : Math.max(Number(requestedSize) || 10, 1);
            const pageCount = Math.max(Math.ceil(filtered.length / size), 1);
            currentPage = Math.min(currentPage, pageCount);
            const start = (currentPage - 1) * size;
            const visible = new Set(filtered.slice(start, start + size));
            items.forEach((item) => item.nodes.forEach((node) => {
                node.hidden = !visible.has(item);
            }));

            const empty = panel.querySelector('[data-list-empty]');
            if (empty) empty.hidden = filtered.length !== 0;
            const summary = panel.querySelector('[data-list-summary]');
            if (summary) {
                summary.textContent = filtered.length
                    ? `Menampilkan ${start + 1}–${Math.min(start + size, filtered.length)} dari ${filtered.length} data`
                    : 'Tidak ada data yang cocok';
            }
            const pageLabel = panel.querySelector('[data-list-page]');
            if (pageLabel) pageLabel.textContent = `${currentPage} / ${pageCount}`;
            const previous = panel.querySelector('[data-list-prev]');
            const next = panel.querySelector('[data-list-next]');
            if (previous) previous.disabled = currentPage <= 1;
            if (next) next.disabled = currentPage >= pageCount;
        };

        search?.addEventListener('input', () => { currentPage = 1; render(); });
        panel.querySelectorAll('[data-list-filter]').forEach((select) => select.addEventListener('change', () => {
            currentPage = 1;
            render();
        }));
        pageSize?.addEventListener('change', () => { currentPage = 1; render(); });
        panel.querySelector('[data-list-prev]')?.addEventListener('click', () => {
            currentPage = Math.max(1, currentPage - 1);
            render();
        });
        panel.querySelector('[data-list-next]')?.addEventListener('click', () => {
            currentPage += 1;
            render();
        });
        panel.querySelectorAll('[data-list-view-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                panel.querySelectorAll('[data-list-view]').forEach((view) => {
                    view.hidden = view.dataset.listView !== button.dataset.listViewMode;
                });
                panel.classList.toggle('is-card-view', button.dataset.listViewMode === 'cards');
                panel.querySelectorAll('[data-list-view-mode]').forEach((toggle) => {
                    const isActive = toggle === button;
                    toggle.classList.toggle('is-active', isActive);
                    toggle.classList.toggle('bg-slate-700', isActive);
                    toggle.classList.toggle('text-white', isActive);
                    toggle.classList.toggle('text-slate-300', !isActive);
                });
            });
        });
        const activeView = panel.querySelector('[data-list-view-mode].is-active');
        if (activeView) {
            panel.querySelectorAll('[data-list-view]').forEach((view) => {
                view.hidden = view.dataset.listView !== activeView.dataset.listViewMode;
            });
            panel.classList.toggle('is-card-view', activeView.dataset.listViewMode === 'cards');
            panel.querySelectorAll('[data-list-view-mode]').forEach((button) => {
                const isActive = button === activeView;
                button.classList.toggle('bg-slate-700', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('text-slate-300', !isActive);
            });
        }
        render();
    });

    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById(button.dataset.openDialog)?.showModal();
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('dialog')?.close();
        });
    });
    document.querySelectorAll('dialog[data-open-on-error]').forEach((dialog) => dialog.showModal());

    const refreshInterval = Number(document.body.dataset.platformAutoRefresh);
    if (refreshInterval > 0) {
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('input', () => { form.dataset.unsavedChanges = 'true'; });
            form.addEventListener('change', () => { form.dataset.unsavedChanges = 'true'; });
            form.addEventListener('submit', () => { delete form.dataset.unsavedChanges; });
        });
        window.setInterval(() => {
            const hasUnsavedChanges = [...document.forms].some((form) => form.dataset.unsavedChanges === 'true');
            const dialogOpen = document.querySelector('dialog[open]');
            const editing = document.activeElement?.matches('input, textarea, select');
            if (document.visibilityState === 'visible' && !hasUnsavedChanges && !dialogOpen && !editing) {
                window.location.reload();
            }

            const notificationCount = document.querySelector('[data-notification-count]');
            if (notificationCount) {
                window.setInterval(async () => {
                    if (document.visibilityState !== 'visible') return;

                    try {
                        const response = await fetch(notificationCount.dataset.endpoint, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error(`Notification refresh failed with HTTP ${response.status}.`);
                        const state = await response.json();
                        notificationCount.textContent = String(state.unread_count);
                        notificationCount.hidden = state.unread_count === 0;
                    } catch (error) {
                        console.error('Unable to refresh cooperative notifications.', error);
                    }
                }, 60_000);
            }
        }, refreshInterval);
    }

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
    if (methodSelect) {
        proofPanel?.classList.toggle('hidden', methodSelect.value !== 'transfer');
    }

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
