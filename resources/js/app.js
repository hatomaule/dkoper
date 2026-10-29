import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('dkTheme', () => ({
    dark: localStorage.getItem('dk-theme-v2') !== 'light',
    showLogout: false,
    init() {
        document.documentElement.dataset.theme = this.dark ? 'dark' : 'light';
    },
    toggle() {
        this.dark = !this.dark;
        document.documentElement.dataset.theme = this.dark ? 'dark' : 'light';
        localStorage.setItem('dk-theme-v2', this.dark ? 'dark' : 'light');
        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) themeColor.content = this.dark ? '#0b1120' : '#f6f8fb';
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    const withVisibleView = (panel, selector, measure) => {
        const view = panel.querySelector(selector);
        if (!(view instanceof HTMLElement)) {
            return measure(null);
        }

        const wasHidden = view.hidden;
        const hadHiddenClass = view.classList.contains('hidden');
        view.hidden = false;
        view.classList.remove('hidden');
        const result = measure(view);
        view.hidden = wasHidden;
        view.classList.toggle('hidden', hadHiddenClass);

        return result;
    };

    const cardGridColumns = (panel, selector) => {
        return withVisibleView(panel, selector, (grid) => {
            if (!(grid instanceof HTMLElement)) return 1;
            return Math.max(getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length, 1);
        });
    };

    const tablePageSize = (panel, selector = '[data-table-view="table"]') => withVisibleView(panel, selector, (view) => {
        if (!(view instanceof HTMLElement)) return 6;

        const table = view.querySelector('table');
        const firstRow = view.querySelector('tbody tr[data-table-row]:not(.dk-loan-total-row), tbody tr[data-list-row]');
        const rowHeight = firstRow?.getBoundingClientRect().height || 42;
        const headingHeight = table?.tHead?.getBoundingClientRect().height || 0;
        const fixedRowHeight = [...view.querySelectorAll('tbody tr.dk-loan-total-row')]
            .reduce((height, row) => height + row.getBoundingClientRect().height, 0);
        const footerHeight = panel.querySelector('.dk-table-footer, footer')?.getBoundingClientRect().height || 0;
        const availableHeight = window.innerHeight - view.getBoundingClientRect().top - footerHeight - fixedRowHeight - 24;

        return Math.min(Math.max(Math.floor((availableHeight - headingHeight) / rowHeight), 1), 100);
    });
    const rowPageSize = (panel) => {
        const view = panel.querySelector('[data-table-view]:not([hidden])') || panel;
        const firstRow = [...view.querySelectorAll('[data-table-row]')].find((row) => !row.hidden);
        const rowHeight = firstRow?.getBoundingClientRect().height || 42;
        const rowTop = firstRow?.getBoundingClientRect().top
            || (panel.querySelector('.dk-table-toolbar')?.getBoundingClientRect().bottom ?? panel.getBoundingClientRect().top);
        const footerHeight = panel.querySelector('.dk-table-footer, footer')?.getBoundingClientRect().height || 0;
        const availableHeight = window.innerHeight - rowTop - footerHeight - 24;

        return Math.min(Math.max(Math.floor(availableHeight / rowHeight), 1), 100);
    };

    const automaticPageSize = (panel, view) => view === 'table'
        ? tablePageSize(panel)
        : view === 'cards'
            ? cardGridColumns(panel, '[data-table-view="cards"]') * 2
            : rowPageSize(panel);
    const automaticListPageSize = (panel, view) => view === 'table'
        ? tablePageSize(panel, '[data-list-view="table"]')
        : cardGridColumns(panel, '[data-list-view="cards"]') * 2;

    const activeTableView = (panel) => panel.querySelector('[data-view-mode].is-active')?.dataset.viewMode;

    const updatePaginationView = (panel, view) => {
        panel.querySelectorAll('.dk-table-footer a[href]').forEach((link) => {
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('view', view);
            link.href = url.toString();
        });
    };

    document.querySelectorAll('input[type="password"]').forEach((input) => {
        if (input.parentElement?.classList.contains('dk-password-input-wrap')) {
            return;
        }

        const wrap = document.createElement('span');
        wrap.className = 'dk-password-input-wrap';
        wrap.style.marginTop = getComputedStyle(input).marginTop;
        input.style.marginTop = '0';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

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

        const pageSize = panel.querySelector('[data-page-size]');
        const pageSizeUrl = new URL(window.location.href);
        let autoPageSize = pageSize?.value === 'auto';
        const applyView = (view, updateUrl = false) => {
            panel.classList.toggle('is-card-view', view === 'cards');
            panel.querySelectorAll('[data-table-view]').forEach((tableView) => {
                tableView.hidden = tableView.dataset.tableView !== view;
                tableView.classList.toggle('hidden', tableView.hidden);
            });
            panel.querySelectorAll('[data-view-mode]').forEach((toggle) => {
                toggle.classList.toggle('is-active', toggle.dataset.viewMode === view);
            });
            if (updateUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('view', view);
                window.history.replaceState({}, '', url);
            }
            updatePaginationView(panel, view);
        };

        panel.querySelectorAll('[data-view-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                const view = button.dataset.viewMode;
                applyView(view, true);
                synchronizeAutomaticPageSize();
            });
        });

        pageSize?.addEventListener('change', (event) => {
            const url = new URL(window.location.href);
            if (event.target.value === 'auto') {
                autoPageSize = true;
                url.searchParams.set('card_page_auto', '1');
                url.searchParams.set('per_page', String(automaticPageSize(panel, activeTableView(panel))));
            } else if (event.target.value === 'all') {
                autoPageSize = false;
                url.searchParams.delete('card_page_auto');
                url.searchParams.set('per_page', 'all');
            }
            url.searchParams.delete('page');
            window.location.assign(url);
        });

        const initialView = pageSizeUrl.searchParams.get('view')
            || panel.querySelector('[data-view-mode].is-active')?.dataset.viewMode
            || 'cards';
        applyView(initialView);
        const synchronizeAutomaticPageSize = () => {
            const currentView = activeTableView(panel);
            if (!autoPageSize || !pageSize || pageSize.value !== 'auto') {
                return;
            }
            const url = new URL(window.location.href);
            const size = automaticPageSize(panel, currentView);
            if (Number(url.searchParams.get('per_page')) !== size) {
                url.searchParams.set('per_page', String(size));
                url.searchParams.set('card_page_auto', '1');
                url.searchParams.delete('page');
                if (currentView) {
                    url.searchParams.set('view', currentView);
                }
                window.location.assign(url);
            }
        };
        synchronizeAutomaticPageSize();
        panel.addEventListener('click', (event) => {
            const link = event.target.closest('.dk-table-footer a[href]');
            if (!(link instanceof HTMLAnchorElement)) return;
            const url = new URL(link.href);
            url.searchParams.set('view', activeTableView(panel) || 'cards');
            link.href = url.toString();
        });
        let automaticResizeTimeout;
        window.addEventListener('resize', () => {
            window.clearTimeout(automaticResizeTimeout);
            automaticResizeTimeout = window.setTimeout(synchronizeAutomaticPageSize, 200);
        });
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
        let automaticListSize = 12;
        let currentPage = 1;
        const currentListView = () => panel.querySelector('[data-list-view-mode].is-active')?.dataset.listViewMode || 'cards';
        const measureAutomaticListSize = () => automaticListPageSize(panel, currentListView());
        const filterValues = () => [...panel.querySelectorAll('[data-list-filter]')]
            .reduce((values, select) => ({ ...values, [select.dataset.listFilter]: select.value }), {});
        const render = () => {
            const needle = search?.value.trim().toLocaleLowerCase() || '';
            const filters = filterValues();
            const filtered = [...items.values()].filter((item) => {
                return (!needle || item.text.toLocaleLowerCase().includes(needle))
                    && Object.entries(filters).every(([key, value]) => !value || item[key] === value);
            });
            const requestedSize = pageSize?.value || 'auto';
            const size = requestedSize === 'all'
                ? Math.max(filtered.length, 1)
                : requestedSize === 'auto'
                    ? automaticListSize
                    : Math.max(Number(requestedSize) || automaticListSize, 1);
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
        pageSize?.addEventListener('change', () => {
            currentPage = 1;
            if (pageSize.value === 'auto' && panel.classList.contains('is-card-view')) {
                automaticListSize = measureAutomaticListSize();
            } else if (pageSize.value === 'auto') {
                automaticListSize = measureAutomaticListSize();
            }
            render();
        });
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
                const useCards = button.dataset.listViewMode === 'cards';
                panel.querySelectorAll('[data-list-view]').forEach((view) => {
                    const hidden = view.dataset.listView !== button.dataset.listViewMode;
                    view.hidden = hidden;
                    view.classList.toggle('hidden', hidden);
                });
                panel.classList.toggle('is-card-view', useCards);
                if (pageSize?.value === 'auto') {
                    automaticListSize = measureAutomaticListSize();
                    currentPage = 1;
                    render();
                }
                const url = new URL(window.location.href);
                url.searchParams.set('view', button.dataset.listViewMode);
                window.history.replaceState({}, '', url);
                panel.querySelectorAll('[data-list-view-mode]').forEach((toggle) => {
                    const isActive = toggle === button;
                    toggle.classList.toggle('is-active', isActive);
                    toggle.classList.toggle('bg-slate-700', isActive);
                    toggle.classList.toggle('text-white', isActive);
                    toggle.classList.toggle('text-slate-300', !isActive);
                });
            });
        });
        const requestedListView = new URL(window.location.href).searchParams.get('view');
        const activeView = panel.querySelector(`[data-list-view-mode="${requestedListView}"]`)
            || panel.querySelector('[data-list-view-mode].is-active');
        if (activeView) {
            panel.querySelectorAll('[data-list-view]').forEach((view) => {
                const hidden = view.dataset.listView !== activeView.dataset.listViewMode;
                view.hidden = hidden;
                view.classList.toggle('hidden', hidden);
            });
            panel.classList.toggle('is-card-view', activeView.dataset.listViewMode === 'cards');
            if (pageSize?.value === 'auto') {
                automaticListSize = measureAutomaticListSize();
            }
            panel.querySelectorAll('[data-list-view-mode]').forEach((button) => {
                const isActive = button === activeView;
                button.classList.toggle('bg-slate-700', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('text-slate-300', !isActive);
            });
        }
        render();
        let listResizeTimeout;
        window.addEventListener('resize', () => {
            if (pageSize?.value !== 'auto') {
                return;
            }
            window.clearTimeout(listResizeTimeout);
            listResizeTimeout = window.setTimeout(() => {
                automaticListSize = measureAutomaticListSize();
                currentPage = 1;
                render();
            }, 200);
        });
    });

    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.openDialog;
            const dialogId = target?.startsWith('#') ? target.slice(1) : target;
            const dialog = dialogId ? document.getElementById(dialogId) : null;
            if (!(dialog instanceof HTMLDialogElement)) {
                console.error(`Unable to open dialog for selector "${target}".`);
                return;
            }
            if (!dialog.open) dialog.showModal();
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
    const paymentType = document.querySelector('[data-payment-type]');
    const payoffSummary = document.querySelector('[data-payoff-summary]');
    const payoffAmount = document.querySelector('[data-payoff-amount]');
    const paymentTitle = document.querySelector('[data-payment-title]');
    const paymentSubmit = document.querySelector('[data-payment-submit]');
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
        updatePayoffAmount();
    };

    function updatePayoffAmount() {
        const amount = Number(loanSelect?.selectedOptions[0]?.dataset.totalOutstanding || 0);
        if (payoffAmount) {
            payoffAmount.textContent = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(amount);
        }
    }

    function setPaymentMode(mode) {
        if (!paymentType || !installmentSelect || !sourceSelect) return;
        const isPayoff = mode === 'payoff';
        paymentType.value = isPayoff ? 'payoff' : 'installment';
        installmentSelect.required = !isPayoff;
        document.querySelectorAll('[data-installment-field], [data-nominal-field]').forEach((field) => {
            field.classList.toggle('hidden', isPayoff);
        });
        installmentPanel?.classList.toggle('hidden', isPayoff || sourceSelect.value === 'manual');
        manualAmount?.classList.toggle('hidden', isPayoff || sourceSelect.value !== 'manual');
        payoffSummary?.classList.toggle('hidden', !isPayoff);
        if (isPayoff) {
            sourceSelect.value = 'system';
            if (amountInput) {
                amountInput.disabled = true;
                amountInput.required = false;
            }
        } else {
            updateNominalMode();
        }
        if (paymentTitle) paymentTitle.textContent = isPayoff ? 'Lunasi pinjaman' : 'Catat setoran';
        if (paymentSubmit) paymentSubmit.textContent = isPayoff ? 'Konfirmasi Pelunasan' : 'Catat Pembayaran';
        updatePayoffAmount();
    }

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
    document.querySelectorAll('[data-payment-mode-trigger]').forEach((button) => {
        button.addEventListener('click', () => setPaymentMode(button.dataset.paymentModeTrigger));
    });
    if (paymentType) setPaymentMode(paymentType.value);

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
