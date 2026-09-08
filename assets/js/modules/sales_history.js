/**
 * Sales History Module
 */

const sales_historyModule = {
    table: null,

    init() {
        this.initDataTable();
        this.bindEvents();
        console.log('Sales History Module Loaded');
    },

    bindEvents() {
        document.getElementById('btn-export-pdf').onclick = () => this.exportPDF();
        document.getElementById('btn-export-excel').onclick = () => this.exportExcel();

        document.getElementById('btn-print-receipt').onclick = () => {
            window.print();
        };

        document.getElementById('btn-confirm-return').onclick = () => {
            this.confirmReturn();
        };
    },

    initDataTable() {
        this.table = $('#salesHistoryTable').DataTable({
            destroy: true,
            ajax: 'api/sales.php?action=history',
            columns: [
                {
                    data: 'id',
                    type: 'num',
                    render: (data, type, row) => {
                        if (type === 'display') {
                            const returnBadge = row.has_returns > 0 ? `<span class="badge bg-danger-subtle text-danger ms-2 small" title="Contains Returned Items"><i class="fas fa-undo me-1"></i>Returned</span>` : '';
                            const cancelledBadge = row.status === 'Cancelled' ? `<span class="badge bg-danger-subtle text-danger ms-2 small" title="Cancelled Sale"><i class="fas fa-ban me-1"></i>Cancelled</span>` : '';
                            const textStyle = row.status === 'Cancelled' ? 'style="text-decoration: line-through; opacity: 0.6;"' : '';
                            return `<span class="fw-bold" ${textStyle}>${row.invoice_number || `#INV-${data}`}</span>${returnBadge}${cancelledBadge}`;
                        }
                        return data;
                    }
                },
                {
                    data: 'date',
                    render: (data, type) => {
                        if (type === 'display') {
                            return new Date(data).toLocaleString();
                        }
                        return data;
                    }
                },
                {
                    data: 'customer_name',
                    render: (data) => data || `<span class="text-muted">${App.t('sh.text.walk_in') || 'Walk-in Customer'}</span>`
                },
                { data: 'user_name' },
                {
                    data: 'payment_method',
                    render: (data) => {
                        const colors = {
                            'Cash': 'bg-success-subtle text-success',
                            'Card': 'bg-primary-subtle text-primary',
                            'Insurance': 'bg-info-subtle text-info'
                        };
                        return `<span class="badge ${colors[data] || 'bg-secondary'} px-3 rounded-pill">${data}</span>`;
                    }
                },
                {
                    data: 'invoice_type',
                    render: (data) => {
                        const style = data === 'BV' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning';
                        return `<span class="badge ${style} px-3 rounded-pill">${data || 'BV'}</span>`;
                    }
                },
                {
                    data: 'profit',
                    type: 'num',
                    render: (data, type, row) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            const textStyle = row.status === 'Cancelled' ? 'style="text-decoration: line-through; opacity: 0.6;"' : '';
                            const colorClass = val >= 0 ? 'text-success' : 'text-danger';
                            const sign = val >= 0 ? '+' : '';
                            return `<span class="fw-bold ${colorClass}" ${textStyle}>${sign}${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => {
                        const isAdmin = App.state.user?.role === 'Admin';
                        const isCancelled = data.status === 'Cancelled';
                        
                        let cancelItem = '';
                        if (isAdmin && !isCancelled) {
                            cancelItem = `
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="sales_historyModule.cancelSale(${data.id})">
                                    <i class="fas fa-ban me-2 text-danger"></i>${App.t('sh.action.cancel_sale') || 'Cancel Sale'}
                                </a></li>
                            `;
                        }
                        
                        return `
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="sales_historyModule.viewDetails(${data.id})"><i class="fas fa-eye me-2 text-info"></i>${App.t('sh.action.view_details') || 'View Details'}</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="sales_historyModule.directPrint(${data.id})"><i class="fas fa-print me-2 text-teal"></i>${App.t('sh.action.print_receipt') || 'Print Receipt'}</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="sales_historyModule.exportInvoicePDF(${data.id})"><i class="fas fa-file-pdf me-2 text-danger"></i>${App.t('sh.action.export_pdf') || 'Export Invoice (PDF)'}</a></li>
                                    ${!isCancelled ? `
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="sales_historyModule.openReturnModal(${data.id})"><i class="fas fa-undo me-2 text-warning"></i>${App.t('sh.action.return_items') || 'Return Items'}</a></li>
                                    ` : ''}
                                    ${cancelItem}
                                </ul>
                            </div>
                        `;
                    }
                }
            ],
            language: App.getDataTableLanguage(),
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    exportPDF() {
        const data = this.table.rows({ search: 'applied' }).data().toArray();
        if (data.length === 0) {
            App.toast('warning', App.t('reports.js.no_data_export') || 'No data to export');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4');
        
        doc.setFontSize(18);
        doc.text(App.t('sh.page_title') || 'Sales History', 40, 40);

        const columns = [
            App.t('sh.table.invoice'),
            App.t('sh.table.datetime'),
            App.t('sh.table.customer'),
            App.t('sh.table.cashier'),
            App.t('sh.table.payment'),
            App.t('sh.table.doc'),
            App.t('sh.table.profit')
        ];

        const rows = data.map(r => [
            r.invoice_number || `#INV-${r.id}`,
            new Date(r.date).toLocaleString(),
            r.customer_name || App.t('sh.text.walk_in'),
            r.user_name,
            r.payment_method,
            r.invoice_type || 'BV',
            `${parseFloat(r.profit).toFixed(2)} ${App.state.settings.currency}`
        ]);

        doc.autoTable({
            head: [columns],
            body: rows,
            startY: 60,
            theme: 'striped',
            headStyles: { fillColor: [0, 191, 166] },
            styles: { fontSize: 9 }
        });

        doc.save(`Sales_History_${new Date().toISOString().split('T')[0]}.pdf`);
    },

    exportExcel() {
        const data = this.table.rows({ search: 'applied' }).data().toArray();
        if (data.length === 0) {
            App.toast('warning', App.t('reports.js.no_data_export') || 'No data to export');
            return;
        }

        const rows = data.map(r => ({
            [App.t('sh.table.invoice')]: r.invoice_number || `#INV-${r.id}`,
            [App.t('sh.table.datetime')]: new Date(r.date).toLocaleString(),
            [App.t('sh.table.customer')]: r.customer_name || App.t('sh.text.walk_in'),
            [App.t('sh.table.cashier')]: r.user_name,
            [App.t('sh.table.payment')]: r.payment_method,
            [App.t('sh.table.doc')]: r.invoice_type || 'BV',
            [App.t('sh.table.profit')]: `${parseFloat(r.profit).toFixed(2)} ${App.state.settings.currency}`
        }));

        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sales History");
        XLSX.writeFile(wb, `Sales_History_${new Date().toISOString().split('T')[0]}.xlsx`);
    },

    async directPrint(id) {
        const result = await App.api(`sales.php?action=sale_details&id=${id}`);
        if (result) this.printReceipt(result);
    },

    async viewDetails(id) {
        const result = await App.api(`sales.php?action=sale_details&id=${id}`);
        if (!result) return;

        const { sale, items } = result;

        // Populate Modal Header
        document.getElementById('sale-details-subtitle').textContent = `${sale.invoice_number || `Invoice #INV-${sale.id}`} | ${new Date(sale.date).toLocaleString()}`;

        // Populate Customer Info
        document.getElementById('sale-details-customer-info').innerHTML = `
            <div class="fw-bold fs-5 text-teal mb-1">${sale.customer_name || (App.t('sh.text.walk_in') || 'Walk-in Customer')}</div>
            <div class="small mb-1"><i class="fas fa-phone me-2 text-muted"></i>${sale.customer_phone || (App.t('sh.text.no_phone') || 'No phone provided')}</div>
            <div class="small"><i class="fas fa-user-tag me-2 text-muted"></i>${App.t('sh.text.payment') || 'Payment'}: <strong>${sale.payment_method}</strong></div>
        `;

        // Populate Sale Metadata
        document.getElementById('sale-details-meta-info').innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <span>${App.t('sh.text.cashier') || 'Cashier'}:</span>
                <span class="fw-bold">${sale.user_name}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>${App.t('sh.text.status') || 'Status'}:</span>
                <span class="badge ${sale.status === 'Cancelled' ? 'bg-danger' : 'bg-success'} px-2">${sale.status === 'Cancelled' ? (App.t('sh.status.cancelled') || 'Cancelled') : (App.t('sh.text.completed') || 'Completed')}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>${App.t('sh.text.doc_type') || 'Doc Type'}:</span>
                <span class="badge ${sale.invoice_type === 'BL' ? 'bg-warning text-dark' : 'bg-primary'} px-2">${sale.invoice_type || 'BV'}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>${App.t('sh.text.date') || 'Date'}:</span>
                <span class="fw-bold text-nowrap">${new Date(sale.date).toLocaleDateString()}</span>
            </div>
        `;

        // Populate Item Table
        const tbody = document.querySelector('#sale-details-items-table tbody');
        tbody.innerHTML = items.map(item => `
            <tr ${item.returned_qty > 0 ? 'class="table-warning"' : ''}>
                <td>
                    <div class="fw-medium">${item.product_name}</div>
                    <small class="text-muted small">${item.barcode || ''}</small>
                    ${item.returned_qty > 0 ? `<div class="mt-1"><span class="badge bg-danger-subtle text-danger"><i class="fas fa-undo me-1"></i>Returned: ${item.returned_qty}</span></div>` : ''}
                </td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">${App.formatCurrency(item.unit_price)}</td>
                <td class="text-end fw-bold">${App.formatCurrency(item.total)}</td>
            </tr>
        `).join('');

        // Populate Totals
        document.getElementById('sale-details-subtotal').textContent = App.formatCurrency(sale.subtotal);
        document.getElementById('sale-details-discount').textContent = `-${App.formatCurrency(sale.discount)}`;
        document.getElementById('sale-details-tax').textContent = App.formatCurrency(sale.tax);
        document.getElementById('sale-details-total').textContent = App.formatCurrency(sale.total);

        // Update VAT Percentage label
        const vatDisplays = document.querySelectorAll('.vat-rate-display');
        const vatRate = App.state.settings.vat_rate || 15;
        vatDisplays.forEach(el => el.textContent = vatRate);

        // Show Modal
        new bootstrap.Modal(document.getElementById('saleDetailsModal')).show();

        // Bind Print Receipt button in modal to this specific sale
        document.getElementById('btn-print-receipt').onclick = () => {
            this.printReceipt(result);
        };

        // Bind Export Invoice PDF button in modal
        const modalExportPdfBtn = document.getElementById('btn-export-invoice-pdf');
        if (modalExportPdfBtn) {
            modalExportPdfBtn.onclick = () => {
                this.exportInvoicePDF(result);
            };
        }
    },

    printReceipt(data) {
        const printWindow = window.open('views/receipt-template.html', '_blank', 'width=900,height=800');

        // Wait for window to load then send data
        printWindow.onload = function () {
            printWindow.postMessage({
                type: 'POPULATE_RECEIPT',
                payload: { ...data, settings: App.state.settings }
            }, window.location.origin);
        };
    },

    async exportInvoicePDF(saleOrId) {
        let iframe = null;
        try {
            App.toast('info', App.t('sh.msg.generating_pdf') || 'Génération du PDF de la facture...');

            // 1. Fetch sale data if only an ID was provided
            let data;
            if (typeof saleOrId === 'object' && saleOrId.sale) {
                data = saleOrId;
            } else {
                data = await App.api(`sales.php?action=sale_details&id=${saleOrId}`);
            }

            if (!data || !data.sale) {
                App.toast('error', App.t('sh.msg.error_loading_sale') || 'Impossible de charger les données de la vente.');
                return;
            }

            // 2. Build filename
            const { sale } = data;
            const isBL = sale.invoice_type === 'BL';
            const invoiceNum = sale.invoice_number ||
                (isBL ? `BL-${String(sale.id).padStart(4, '0')}` : `FAC-${String(sale.id).padStart(4, '0')}`);
            const sanitizedNum = String(invoiceNum).replace(/[^a-zA-Z0-9-_]/g, '_');
            const fileName = `${isBL ? 'BL' : 'Facture'}_${sanitizedNum}.pdf`;

            // 3. Create a hidden iframe (never blocked by browsers, unlike popups)
            iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:900px;height:1200px;border:none;visibility:hidden;';
            iframe.src = 'views/receipt-template.html';
            document.body.appendChild(iframe);

            // 4. Set up the response listener BEFORE the iframe loads (avoids race condition)
            const imageResult = await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    window.removeEventListener('message', handler);
                    reject(new Error('Timeout: la template n\'a pas répondu (15s).'));
                }, 15000);

                function handler(event) {
                    // Accept only messages from our iframe
                    if (!iframe || event.source !== iframe.contentWindow) return;

                    if (event.data && event.data.type === 'PDF_CANVAS_READY') {
                        clearTimeout(timeout);
                        window.removeEventListener('message', handler);
                        resolve({ imageData: event.data.imageData, w: event.data.w, h: event.data.h });
                    } else if (event.data && event.data.type === 'PDF_ERROR') {
                        clearTimeout(timeout);
                        window.removeEventListener('message', handler);
                        reject(new Error('Erreur de capture: ' + event.data.message));
                    }
                }
                window.addEventListener('message', handler);

                // Send data once iframe is loaded
                iframe.onload = () => {
                    iframe.contentWindow.postMessage(
                        {
                            type: 'POPULATE_RECEIPT',
                            pdfMode: true,
                            payload: { ...data, settings: App.state.settings }
                        },
                        window.location.origin
                    );
                };
            });

            // 5. Build the PDF from the image sent by the iframe's html2canvas
            const { jsPDF } = window.jspdf;
            const pdfWidth  = 210; // A4 width in mm
            const pdfHeight = Math.round((imageResult.h / imageResult.w) * pdfWidth);

            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: [pdfWidth, pdfHeight]
            });

            doc.addImage(imageResult.imageData, 'JPEG', 0, 0, pdfWidth, pdfHeight, undefined, 'FAST');

            // 6. Save PDF
            doc.save(fileName);
            App.toast('success', (App.t('sh.msg.pdf_exported') || 'Facture exportée avec succès en PDF') + ` — ${fileName}`);

        } catch (err) {
            console.error('Error in exportInvoicePDF:', err);
            App.toast('error', 'Erreur lors de la création du PDF: ' + (err.message || err));
        } finally {
            // Always clean up the iframe
            if (iframe && iframe.parentNode) {
                iframe.parentNode.removeChild(iframe);
            }
        }
    },

    numberToWordsFR(num) {
        if (num === 0 || isNaN(num)) return 'Zéro';

        const units = ['', 'Un', 'Deux', 'Trois', 'Quatre', 'Cinq', 'Six', 'Sept', 'Huit', 'Neuf'];
        const teens = ['Dix', 'Onze', 'Douze', 'Treize', 'Quatorze', 'Quinze', 'Seize', 'Dix-Sept', 'Dix-Huit', 'Dix-Neuf'];
        const tens = ['', 'Dix', 'Vingt', 'Trente', 'Quarante', 'Cinquante', 'Soixante', 'Soixante-Dix', 'Quatre-Vingt', 'Quatre-Vingt-Dix'];

        function convertGroup(n) {
            let words = '';
            const h = Math.floor(n / 100);
            const t = Math.floor((n % 100) / 10);
            const u = n % 10;

            if (h > 0) {
                if (h === 1) {
                    words += 'Cent ';
                } else {
                    words += units[h] + ' Cent ';
                }
            }

            if (t > 0) {
                if (t === 7) {
                    if (u === 1) {
                        words += 'Soixante et Onze';
                    } else {
                        words += 'Soixante-' + teens[u];
                    }
                } else if (t === 9) {
                    words += 'Quatre-Vingt-' + teens[u];
                } else if (t === 8) {
                    if (u === 0) {
                        words += 'Quatre-Vingts';
                    } else {
                        words += 'Quatre-Vingt-' + units[u];
                    }
                } else if (t === 1) {
                    words += teens[u];
                } else {
                    words += tens[t];
                    if (u > 0) {
                        words += (u === 1) ? ' et Un' : '-' + units[u];
                    }
                }
            } else if (u > 0) {
                words += units[u];
            }

            return words.trim();
        }

        let words = '';
        const mainPart = Math.floor(Math.abs(num));
        const centsPart = Math.round((Math.abs(num) - mainPart) * 100);

        const billions = Math.floor(mainPart / 1000000000);
        const millions = Math.floor((mainPart % 1000000000) / 1000000);
        const thousands = Math.floor((mainPart % 1000000) / 1000);
        const remainder = mainPart % 1000;

        if (billions > 0) {
            words += convertGroup(billions) + ' Milliard' + (billions > 1 ? 's' : '') + ' ';
        }
        if (millions > 0) {
            words += convertGroup(millions) + ' Million' + (millions > 1 ? 's' : '') + ' ';
        }
        if (thousands > 0) {
            if (thousands === 1) {
                words += 'Mille ';
            } else {
                words += convertGroup(thousands) + ' Mille ';
            }
        }
        if (remainder > 0) {
            words += convertGroup(remainder) + ' ';
        }

        words = words.trim();
        if (!words) words = 'Zéro';

        if (centsPart > 0) {
            words += ' Dinars et ' + convertGroup(centsPart) + ' Centimes';
        } else {
            words += ' Dinars';
        }

        return words.replace(/\s+/g, ' ').trim();
    },

    async openReturnModal(id) {
        const result = await App.api(`sales.php?action=sale_details&id=${id}`);
        if (!result) return;

        const { sale, items } = result;
        document.getElementById('return-sale-id').value = sale.id;
        document.getElementById('sale-return-subtitle').textContent = `${sale.invoice_number || `Invoice #INV-${sale.id}`} | ${sale.customer_name || (App.t('sh.text.walk_in') || 'Walk-in')}`;
        document.getElementById('return-reason').value = '';

        const tbody = document.querySelector('#sale-return-items-table tbody');
        tbody.innerHTML = items.map(item => `
            <tr>
                <td>
                    <div class="fw-medium">${item.product_name}</div>
                    <small class="text-muted small">${item.barcode || ''}</small>
                </td>
                <td class="text-center">${item.qty}</td>
                <td class="text-center">${item.returned_qty || 0}</td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm return-qty-input text-center" 
                           data-product-id="${item.product_id}" 
                           data-price="${item.unit_price}"
                           max="${item.qty - (item.returned_qty || 0)}" 
                           min="0" value="0" 
                           onchange="sales_historyModule.updateReturnTotal()">
                </td>
                <td class="text-end fw-bold return-item-total">0.00 ${App.state.settings.currency || '$'}</td>
            </tr>
        `).join('');

        this.updateReturnTotal();
        new bootstrap.Modal(document.getElementById('saleReturnModal')).show();
    },

    updateReturnTotal() {
        let total = 0;
        document.querySelectorAll('.return-qty-input').forEach(input => {
            const qty = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price);
            const itemTotal = qty * price;
            total += itemTotal;
            input.closest('tr').querySelector('.return-item-total').textContent = App.formatCurrency(itemTotal);
        });
        document.getElementById('sale-return-total').textContent = App.formatCurrency(total);
    },

    async confirmReturn() {
        const saleId = document.getElementById('return-sale-id').value;
        const reason = document.getElementById('return-reason').value;
        const items = [];

        document.querySelectorAll('.return-qty-input').forEach(input => {
            const qty = parseInt(input.value) || 0;
            if (qty > 0) {
                items.push({
                    product_id: input.dataset.productId,
                    qty: qty
                });
            }
        });

        if (items.length === 0) {
            App.toast('warning', App.t('sh.msg.select_return_item') || 'Please select at least one item to return');
            return;
        }

        const confirmed = await App.confirm(
            App.t('sh.confirm.return_title') || 'Process Return?',
            App.t('sh.confirm.return_desc') || 'Are you sure you want to process this return? Customer balance will be adjusted.'
        );
        if (!confirmed) return;

        const result = await App.api('sales.php?action=process_return', 'POST', {
            sale_id: saleId,
            reason: reason,
            items: items
        });

        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('saleReturnModal')).hide();
            this.table.ajax.reload(null, false);
        }
    },

    async cancelSale(id) {
        const confirmed = await App.confirm(
            App.t('sh.confirm.cancel_title') || 'Cancel Sale?',
            App.t('sh.confirm.cancel_desc') || 'Are you sure you want to cancel this sale? Stock levels and customer balance will be reversed.'
        );
        if (!confirmed) return;

        const result = await App.api('sales.php?action=cancel_sale', 'POST', {
            sale_id: id
        });

        if (result && result.success) {
            App.toast('success', result.success);
            this.table.ajax.reload(null, false);
        }
    }
};

// Initialize
if (document.getElementById('salesHistoryTable')) {
    sales_historyModule.init();
}

window.sales_historyModule = sales_historyModule;
