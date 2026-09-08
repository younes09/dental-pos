/**
 * Customers Module
 */

var customersModule = {
    table: null,
    wilayas: [
        { id: '01', name: '01 - Adrar' }, { id: '02', name: '02 - Chlef' }, { id: '03', name: '03 - Laghouat' },
        { id: '04', name: '04 - Oum El Bouaghi' }, { id: '05', name: '05 - Batna' }, { id: '06', name: '06 - Béjaïa' },
        { id: '07', name: '07 - Biskra' }, { id: '08', name: '08 - Béchar' }, { id: '09', name: '09 - Blida' },
        { id: '10', name: '10 - Bouira' }, { id: '11', name: '11 - Tamanrasset' }, { id: '12', name: '12 - Tébessa' },
        { id: '13', name: '13 - Tlemcen' }, { id: '14', name: '14 - Tiaret' }, { id: '15', name: '15 - Tizi Ouzou' },
        { id: '16', name: '16 - Alger' }, { id: '17', name: '17 - Djelfa' }, { id: '18', name: '18 - Jijel' },
        { id: '19', name: '19 - Sétif' }, { id: '20', name: '20 - Saïda' }, { id: '21', name: '21 - Skikda' },
        { id: '22', name: '22 - Sidi Bel Abbès' }, { id: '23', name: '23 - Annaba' }, { id: '24', name: '24 - Guelma' },
        { id: '25', name: '25 - Constantine' }, { id: '26', name: '26 - Médéa' }, { id: '27', name: '27 - Mostaganem' },
        { id: '28', name: '28 - M\'Sila' }, { id: '29', name: '29 - Mascara' }, { id: '30', name: '30 - Ouargla' },
        { id: '31', name: '31 - Oran' }, { id: '32', name: '32 - El Bayadh' }, { id: '33', name: '33 - Illizi' },
        { id: '34', name: '34 - Bordj Bou Arreridj' }, { id: '35', name: '35 - Boumerdès' }, { id: '36', name: '36 - El Tarf' },
        { id: '37', name: '37 - Tindouf' }, { id: '38', name: '38 - Tissemsilt' }, { id: '39', name: '39 - El Oued' },
        { id: '40', name: '40 - Khenchela' }, { id: '41', name: '41 - Souk Ahras' }, { id: '42', name: '42 - Tipaza' },
        { id: '43', name: '43 - Mila' }, { id: '44', name: '44 - Aïn Defla' }, { id: '45', name: '45 - Naâma' },
        { id: '46', name: '46 - Aïn Témouchent' }, { id: '47', name: '47 - Ghardaïa' }, { id: '48', name: '48 - Relizane' },
        { id: '49', name: '49 - El M\'Ghair' }, { id: '50', name: '50 - El Meniaa' }, { id: '51', name: '51 - Ouled Djellal' },
        { id: '52', name: '52 - Bordj Baji Mokhtar' }, { id: '53', name: '53 - Béni Abbès' }, { id: '54', name: '54 - Timimoun' },
        { id: '55', name: '55 - Touggourt' }, { id: '56', name: '56 - Djanet' }, { id: '57', name: '57 - In Salah' },
        { id: '58', name: '58 - In Guezzam' }
    ],

    init() {
        this.populateWilayas();
        this.initDataTable();
        this.bindEvents();
        console.log('Customers Module Loaded');
    },

    populateWilayas() {
        const select = document.getElementById('customer-wilaya');
        if (!select) return;
        
        // Clear existing except first
        select.innerHTML = `<option value="">${App.t('customers.modal.select_wilaya') || 'Select Wilaya'}</option>`;
        
        this.wilayas.forEach(w => {
            const option = document.createElement('option');
            option.value = w.id;
            option.textContent = w.name;
            select.appendChild(option);
        });
    },

    bindEvents() {
        document.getElementById('customerForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveCustomer();
        };

        document.getElementById('btn-add-customer').onclick = () => {
            document.getElementById('customerForm').reset();
            document.getElementById('customer-id').value = '';
            document.getElementById('customerModalLabel').textContent = App.t('customers.js.add_title') || 'Add New Customer';
        };

        const settleDebtForm = document.getElementById('settleDebtForm');
        if (settleDebtForm) {
            settleDebtForm.onsubmit = async (e) => {
                e.preventDefault();
                await this.submitSettleDebt();
            };
        }

        const settleModalEl = document.getElementById('settleDebtModal');
        if (settleModalEl) {
            settleModalEl.addEventListener('hidden.bs.modal', () => {
                if (this.returnToHistoryCustomerId) {
                    const returnId = this.returnToHistoryCustomerId;
                    this.returnToHistoryCustomerId = null;
                    setTimeout(() => {
                        this.viewHistory(returnId, true);
                    }, 50);
                } else {
                    this.cleanupModalBackdrops();
                }
            });
        }

        const salePaymentsModalEl = document.getElementById('salePaymentsModal');
        if (salePaymentsModalEl) {
            salePaymentsModalEl.addEventListener('show.bs.modal', () => {
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops[backdrops.length - 1].style.zIndex = '1060';
                    }
                }, 10);
            });
            salePaymentsModalEl.addEventListener('hidden.bs.modal', () => {
                const historyModalEl = document.getElementById('historyModal');
                if (historyModalEl && historyModalEl.classList.contains('show')) {
                    document.body.classList.add('modal-open');
                } else {
                    this.cleanupModalBackdrops();
                }
            });
        }

        const historyModalEl = document.getElementById('historyModal');
        if (historyModalEl) {
            historyModalEl.addEventListener('hidden.bs.modal', () => {
                if (!this.returnToHistoryCustomerId) {
                    this.cleanupModalBackdrops();
                }
            });
        }
    },

    initDataTable() {
        this.table = $('#customersTable').DataTable({
            destroy: true,
            ajax: 'api/customers.php?action=list',
            columns: [
                { data: 'name', className: 'fw-semibold' },
                { data: 'phone', render: (data) => data || `<span class="text-muted">${App.t('customers.js.na') || 'N/A'}</span>` },
                { data: 'email', render: (data) => data || `<span class="text-muted">${App.t('customers.js.na') || 'N/A'}</span>` },
                { 
                    data: 'wilaya', 
                    render: (data) => {
                        const w = this.wilayas.find(x => x.id === data);
                        return w ? w.name : `<span class="text-muted">${App.t('customers.js.na') || 'N/A'}</span>`;
                    }
                },
                {
                    data: 'total_purchases',
                    type: 'num',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            return `<span class="fw-bold text-teal">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: 'balance',
                    type: 'num',
                    render: (data, type, row) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            const color = val > 0 ? 'danger' : 'success';
                            return `<span class="fw-bold text-${color}">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: 'loyalty_points',
                    render: (data, type) => {
                        if (type === 'display') {
                            return `<span class="badge bg-info-subtle text-info px-3">${data} pts</span>`;
                        }
                        return data;
                    }
                },
                {
                    data: 'last_visit',
                    render: (data, type) => {
                        if (type === 'display') {
                            return data ? new Date(data).toLocaleDateString() : `<span class="text-muted">${App.t('customers.js.never') || 'Never'}</span>`;
                        }
                        return data;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.editCustomer(${data.id})"><i class="fas fa-edit me-2 text-primary"></i>${App.t('customers.js.action.edit') || 'Edit Profile'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.viewHistory(${data.id})"><i class="fas fa-history me-2 text-teal"></i>${App.t('customers.js.action.history') || 'Purchase History'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.openSettleDebt(${data.id})"><i class="fas fa-money-bill-wave me-2 text-success"></i>${App.t('customers.js.action.settle') || 'Settle Debt'}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="customersModule.deleteCustomer(${data.id})"><i class="fas fa-trash me-2"></i>${App.t('customers.js.action.delete') || 'Delete'}</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: App.getDataTableLanguage(),
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    async saveCustomer() {
        const form = document.getElementById('customerForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/customers.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('customerModal')).hide();
                this.cleanupModalBackdrops();
                this.table.ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', App.t('customers.js.save_fail') || 'Failed to save customer');
        }
    },

    editCustomer(id) {
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        document.getElementById('customer-id').value = customer.id;
        document.querySelector('[name="name"]').value = customer.name;
        document.querySelector('[name="phone"]').value = customer.phone;
        document.querySelector('[name="email"]').value = customer.email;
        document.querySelector('[name="wilaya"]').value = customer.wilaya || '';
        document.querySelector('[name="balance"]').value = customer.balance;
        document.querySelector('[name="loyalty_points"]').value = customer.loyalty_points;

        document.getElementById('customerModalLabel').textContent = App.t('customers.js.edit_title') || 'Edit Customer Profile';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('customerModal')).show();
    },

    async deleteCustomer(id) {
        const confirm = await Swal.fire({
            title: App.t('customers.js.delete_title') || 'Delete Customer?',
            text: App.t('customers.js.delete_text') || "This will remove the customer and their history!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('customers.js.btn_yes') || 'Yes, delete!',
            cancelButtonText: App.t('btn_cancel') || 'Cancel'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`customers.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            }
        }
    },

    currentHistoryCustomerId: null,
    returnToHistoryCustomerId: null,

    async viewHistory(id, openModal = true) {
        this.currentHistoryCustomerId = id;
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        document.getElementById('historyCustomerName').textContent = customer.name;
        const historyBody = document.getElementById('historyBody');
        historyBody.innerHTML = `<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-teal"></div> ${App.t('customers.js.loading') || 'Loading...'}</td></tr>`;

        const historyModalEl = document.getElementById('historyModal');
        const modal = bootstrap.Modal.getOrCreateInstance(historyModalEl);
        if (openModal && !historyModalEl.classList.contains('show')) {
            modal.show();
        }

        try {
            const result = await App.api(`sales.php?action=history&customer_id=${id}`);
            if (result && result.data) {
                if (result.data.length === 0) {
                    historyBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">${App.t('customers.js.no_history') || 'No purchase history found'}</td></tr>`;
                } else {
                    historyBody.innerHTML = result.data.map(sale => {
                        const isCancelled = sale.status === 'Cancelled';
                        const rowStyle = isCancelled ? 'style="text-decoration: line-through; opacity: 0.6;"' : '';
                        const totalVal = parseFloat(sale.total) || 0;
                        const paidVal = parseFloat(sale.paid_amount) || 0;
                        const remainingVal = Math.max(0, totalVal - paidVal);
                        const refCode = sale.invoice_number || ('#' + sale.id);

                        let pStatus = sale.payment_status;
                        if (!pStatus) {
                            pStatus = (remainingVal <= 0.001) ? 'Paid' : (paidVal > 0 ? 'Partial' : 'Unpaid');
                        }

                        let statusBadgeHtml = '';
                        if (isCancelled) {
                            statusBadgeHtml = `<span class="badge bg-secondary-subtle text-secondary">${App.t('customers.js.cancelled') || 'Cancelled'}</span>`;
                        } else if (pStatus === 'Paid') {
                            let settledNote = '';
                            if (sale.paid_at) {
                                const d = new Date(sale.paid_at);
                                const dFormatted = isNaN(d) ? sale.paid_at : d.toLocaleDateString();
                                settledNote = `<div class="text-success small mt-1" style="font-size: 0.72rem;"><i class="fas fa-calendar-check me-1"></i>${App.t('customers.status.settled_on') || 'Settled:'} ${dFormatted}</div>`;
                            }
                            statusBadgeHtml = `<span class="badge bg-success-subtle text-success px-2 py-1"><i class="fas fa-check-circle me-1"></i>${App.t('customers.status.paid') || 'Paid'}</span>${settledNote}`;
                        } else if (pStatus === 'Partial') {
                            statusBadgeHtml = `<span class="badge bg-warning-subtle text-warning-emphasis px-2 py-1"><i class="fas fa-clock me-1"></i>${App.t('customers.status.partial') || 'Partial'}</span>`;
                        } else {
                            statusBadgeHtml = `<span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="fas fa-exclamation-circle me-1"></i>${App.t('customers.status.unpaid') || 'Unpaid'}</span>`;
                        }

                        const remainingHtml = (remainingVal > 0 && !isCancelled)
                            ? `<span class="text-danger fw-bold">${App.formatCurrency(remainingVal)}</span>`
                            : `<span class="text-muted small">0.00</span>`;

                        const settleBtnHtml = (!isCancelled && remainingVal > 0)
                            ? `<button type="button" class="btn btn-sm btn-outline-teal px-2 py-1 ms-1" onclick="customersModule.openSettleDebt(${id}, ${sale.id}, ${remainingVal}, '${refCode}')" title="${App.t('customers.js.settle_this_voucher') || 'Settle this voucher'}">
                                 <i class="fas fa-hand-holding-usd me-1"></i>${App.t('customers.js.action.settle') || 'Settle'}
                               </button>`
                            : '';

                        const safeRef = refCode.replace(/'/g, "\\'");
                        return `
                            <tr ${rowStyle}>
                                <td>${new Date(sale.date).toLocaleDateString()}</td>
                                <td class="fw-semibold text-teal">${refCode}</td>
                                <td class="fw-bold">${App.formatCurrency(totalVal)}</td>
                                <td class="text-success fw-medium">${App.formatCurrency(paidVal)}</td>
                                <td>${remainingHtml}</td>
                                <td>${statusBadgeHtml}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" onclick="customersModule.viewSalePayments(${sale.id}, '${safeRef}', ${totalVal}, ${paidVal}, ${remainingVal})" title="${App.t('customers.js.view_payments') || 'Payment timeline'}">
                                        <i class="fas fa-history me-1"></i>${App.t('customers.js.payments_btn') || 'Payments'}
                                    </button>
                                    ${settleBtnHtml}
                                </td>
                            </tr>
                        `;
                    }).join('');
                }
            }
        } catch (error) {
            historyBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${App.t('customers.js.history_fail') || 'Failed to load history'}</td></tr>`;
        }
    },

    async viewSalePayments(saleId, invoiceNumber, total, paid, remaining) {
        document.getElementById('salePaymentsInvoiceNumber').textContent = invoiceNumber;
        document.getElementById('salePaymentsTotal').textContent = App.formatCurrency(total);
        document.getElementById('salePaymentsPaid').textContent = App.formatCurrency(paid);
        document.getElementById('salePaymentsRemaining').textContent = App.formatCurrency(remaining);

        const body = document.getElementById('salePaymentsBody');
        body.innerHTML = `<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm text-teal"></div> ${App.t('customers.js.loading') || 'Loading...'}</td></tr>`;

        const modalEl = document.getElementById('salePaymentsModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        try {
            const result = await App.api(`sales.php?action=sale_payments&sale_id=${saleId}`);
            if (result && result.data) {
                if (result.data.length === 0) {
                    body.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">${App.t('customers.js.no_payments_logged') || 'No individual payment records found'}</td></tr>`;
                } else {
                    body.innerHTML = result.data.map(p => {
                        const d = new Date(p.created_at);
                        const dateStr = isNaN(d) ? p.created_at : d.toLocaleString();
                        const noteText = p.notes ? `<div class="text-muted small">${p.notes}</div>` : '';
                        const methodKey = p.payment_method ? p.payment_method.toLowerCase().split(' ')[0] : 'cash';
                        const methodLabel = App.t(`customers.method.${methodKey}`) || p.payment_method;

                        return `
                            <tr>
                                <td>
                                    <div class="fw-medium">${dateStr}</div>
                                    ${noteText}
                                </td>
                                <td class="fw-bold text-success">${App.formatCurrency(p.amount)}</td>
                                <td><span class="badge bg-light text-dark border">${methodLabel}</span></td>
                                <td class="small text-muted">${p.user_name || 'System'}</td>
                            </tr>
                        `;
                    }).join('');
                }
            }
        } catch (error) {
            body.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">${App.t('customers.js.payments_fail') || 'Failed to load payments'}</td></tr>`;
        }
    },

    async openSettleDebt(id, saleId = null, saleRemaining = null, saleRef = '') {
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        // Check if history modal is currently shown
        const historyModalEl = document.getElementById('historyModal');
        const isHistoryShown = historyModalEl && historyModalEl.classList.contains('show');
        if (isHistoryShown) {
            this.returnToHistoryCustomerId = id;
            bootstrap.Modal.getOrCreateInstance(historyModalEl).hide();
        } else {
            this.returnToHistoryCustomerId = null;
        }

        document.getElementById('debt-customer-id').value = customer.id;
        document.getElementById('debt-customer-name').value = customer.name;
        document.getElementById('debt-current-balance').textContent = App.formatCurrency(customer.balance || 0);

        const saleIdInput = document.getElementById('debt-sale-id');
        if (saleIdInput) saleIdInput.value = saleId || '';

        const saleAlert = document.getElementById('debt-sale-alert');
        const saleAlertText = document.getElementById('debt-sale-alert-text');
        const amountInput = document.getElementById('debt-payment-amount');

        if (saleId && saleRemaining !== null) {
            if (saleAlert && saleAlertText) {
                saleAlertText.textContent = `${App.t('customers.js.settling_specific_voucher') || 'Settling voucher:'} ${saleRef} (${App.t('customers.modaltable.remaining') || 'Remaining'}: ${App.formatCurrency(saleRemaining)})`;
                saleAlert.classList.remove('d-none');
            }
            const defaultAmt = Math.min(saleRemaining, customer.balance || saleRemaining);
            amountInput.value = defaultAmt.toFixed(2);
            amountInput.max = defaultAmt;
            amountInput.dataset.max = defaultAmt;
        } else {
            if (saleAlert) saleAlert.classList.add('d-none');
            amountInput.value = '';
            amountInput.max = customer.balance || 0;
            amountInput.dataset.max = customer.balance || 0;
        }

        // Load accounts for treasury integration
        const accountSelect = document.getElementById('debt-account-id');
        if (accountSelect) {
            const result = await App.api('vault.php?action=list_accounts');
            if (result && result.data) {
                accountSelect.innerHTML = `<option value="">${App.t('customers.js.no_treasury') || '-- No Treasury Update --'}</option>` +
                    result.data.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');
            }
        }

        const settleModalEl = document.getElementById('settleDebtModal');
        const showSettle = () => {
            bootstrap.Modal.getOrCreateInstance(settleModalEl).show();
        };

        if (isHistoryShown) {
            historyModalEl.addEventListener('hidden.bs.modal', showSettle, { once: true });
        } else {
            showSettle();
        }
    },

    async submitSettleDebt() {
        const amountInput = document.getElementById('debt-payment-amount');
        const amount = parseFloat(amountInput.value) || 0;
        const maxAmount = parseFloat(amountInput.dataset.max) || 0;

        if (amount <= 0) {
            App.toast('error', App.t('customers.js.invalid_amount') || 'Please enter a valid payment amount.');
            amountInput.classList.add('is-invalid');
            return;
        }

        if (amount > maxAmount + 0.001) {
            App.toast('error', (App.t('customers.js.error_exceed') || `Payment cannot exceed the allowed amount of `) + App.formatCurrency(maxAmount));
            amountInput.classList.add('is-invalid');
            return;
        }

        amountInput.classList.remove('is-invalid');

        const form = document.getElementById('settleDebtForm');
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch('api/customers.php?action=add_payment', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);

                const settleModalEl = document.getElementById('settleDebtModal');
                const settleModal = bootstrap.Modal.getOrCreateInstance(settleModalEl);
                settleModal.hide();

                this.table.ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', (App.t('customers.js.error_settle_fail') || 'Failed to settle debt: ') + error.message);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    },

    cleanupModalBackdrops() {
        setTimeout(() => {
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0) {
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }
        }, 350);
    }
};

// Initialize
if (document.getElementById('customersTable')) {
    customersModule.init();
}

window.customersModule = customersModule;
