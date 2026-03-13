/**
 * POS Module
 */

const posModule = {
    allProducts: [],
    allCustomers: [],
    selectedCustomer: null,
    cart: [],
    taxRate: 0, // F1.5: Default to 0, will be loaded from settings

    async init() {
        this.loadSettings();
        this.loadCategories();

        const session = await App.api('cash_register.php?action=get_status');
        if (!session || session.status !== 'open') {
            await Swal.fire({
                title: App.t('pos.msg.register_closed_title') || 'Register Closed',
                text: App.t('pos.msg.register_closed_desc') || 'You must open a cash session before you can perform sales.',
                icon: 'warning',
                confirmButtonText: App.t('pos.msg.go_to_cash') || 'Go to Cash Management',
                allowOutsideClick: false
            });
            window.location.hash = '#cash_register';
            return;
        }

        // Handle search query from URL if any
        const params = new URLSearchParams(window.location.hash.split('?')[1]);
        const searchQuery = params.get('search');

        this.loadProducts(searchQuery);
        this.loadCustomers();
        this.bindEvents();
        console.log('POS Module Loaded');
    },

    loadSettings() {
        if (App.state.settings.vat_rate) {
            this.taxRate = parseFloat(App.state.settings.vat_rate) / 100;
            const vatDisplay = document.getElementById('vat-rate-display');
            if (vatDisplay) vatDisplay.textContent = App.state.settings.vat_rate;
        }
    },

    bindEvents() {
        // Search filter
        document.getElementById('pos-search').oninput = (e) => this.filterProducts(e.target.value);

        // Category filter
        document.getElementById('pos-category-filter').onchange = (e) => this.filterProducts(document.getElementById('pos-search').value, e.target.value);

        // Cart controls
        document.getElementById('btn-clear-cart').onclick = () => this.clearCart();
        document.getElementById('cart-discount').oninput = () => this.calculateTotals();
        document.getElementById('cart-points-redeem').oninput = () => this.calculateTotals();
        document.getElementById('cart-paid-amount').oninput = () => this.calculateTotals();

        document.getElementById('btn-checkout').onclick = () => this.processCheckout();

        // Hold Sale
        const btnHold = document.getElementById('btn-hold-sale');
        if (btnHold) btnHold.onclick = () => this.holdSale();

        const btnRecent = document.getElementById('btn-recent-sales');
        if (btnRecent) btnRecent.onclick = () => this.renderHeldSales();

        // Customer search
        document.getElementById('pos-customer-search').oninput = (e) => this.searchCustomers(e.target.value);

        // Quick add customer form
        document.getElementById('pos-quick-customer-form').onsubmit = (e) => {
            e.preventDefault();
            this.saveQuickCustomer(new FormData(e.target));
        };

        // Invoice type change handler
        const invoiceTypeRadios = document.querySelectorAll('input[name="invoice-type"]');
        invoiceTypeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => this.handleInvoiceTypeChange(e.target.value));
        });
    },

    handleInvoiceTypeChange(type) {
        if (type === 'BL') {
            document.getElementById('pay-credit').checked = true;
            App.toast('info', 'Delivery Note selected. Tax set to 0 and payment set to Credit.');
        } else {
            document.getElementById('pay-cash').checked = true;
        }
        this.calculateTotals();
    },

    async saveQuickCustomer(formData) {
        try {
            const response = await fetch('api/customers.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success && result.id) {
                App.toast('success', 'Customer added and selected');

                // Refresh local customers list
                await this.loadCustomers();

                // Select the new customer
                this.selectCustomer(result.id);

                // Close modals
                bootstrap.Modal.getInstance(document.getElementById('posQuickCustomerModal')).hide();
                bootstrap.Modal.getInstance(document.getElementById('posCustomerModal')).hide();

                // Reset form
                document.getElementById('pos-quick-customer-form').reset();
            } else {
                App.toast('error', result.error || 'Failed to add customer');
            }
        } catch (error) {
            App.toast('error', 'Network error while adding customer');
        }
    },

    async loadProducts(searchQuery = null) {
        const data = await App.api('sales.php?action=list_products');
        if (data) {
            this.allProducts = data.products;
            if (searchQuery) {
                this.filterProducts(searchQuery);
            } else {
                this.renderProducts(this.allProducts);
            }
            this.loadCategories();
            this.renderCart();
            this.renderHeldSales(); // Initial load if any
        }
    },

    async loadCustomers() {
        const result = await App.api('customers.php?action=list');
        if (result && result.data) {
            this.allCustomers = result.data;
        }
    },

    holdSale() {
        if (this.cart.length === 0) {
            App.toast('error', 'Cart is empty');
            return;
        }

        const heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
        const totals = this.calculateTotals();

        const saleToHold = {
            id: Date.now(),
            timestamp: new Date().toISOString(),
            customer: this.selectedCustomer,
            cart: [...this.cart],
            discount: parseFloat(document.getElementById('cart-discount').value) || 0,
            points_redeemed: parseInt(document.getElementById('cart-points-redeem').value) || 0,
            invoice_type: document.querySelector('input[name="invoice-type"]:checked')?.value || 'BV',
            total: totals.total
        };

        heldSales.push(saleToHold);
        localStorage.setItem('pos_held_sales', JSON.stringify(heldSales));

        this.clearCart();
        this.selectCustomer(null);
        document.getElementById('cart-discount').value = 0;
        document.getElementById('cart-points-redeem').value = 0;

        App.toast('success', 'Sale held successfully');
        this.renderHeldSales();
    },

    renderHeldSales() {
        const heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
        const container = document.getElementById('pos-held-sales-list');

        if (!container) return;

        if (heldSales.length === 0) {
            container.innerHTML = `<div class="text-center py-4 text-muted small">${App.t('pos.modal.held.empty')}</div>`;
            return;
        }

        container.innerHTML = heldSales.map((sale, index) => `
            <div class="held-sale-item p-3 mb-3 bg-light rounded-4 border">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">${sale.customer ? sale.customer.name : 'Walking Customer'}</h6>
                        <small class="text-muted">${new Date(sale.timestamp).toLocaleString()}</small>
                    </div>
                    <span class="badge bg-teal-soft text-teal rounded-pill">${App.formatCurrency(sale.total)}</span>
                </div>
                <div class="small mb-3 text-muted">
                    ${sale.cart.length} items: ${sale.cart.map(i => i.name).join(', ')}
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-teal btn-sm rounded-pill px-3" onclick="posModule.resumeSale(${sale.id})">
                        <i class="fas fa-play me-1 small"></i> Resume
                    </button>
                    <button class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="posModule.deleteHeldSale(${sale.id})">
                        <i class="fas fa-trash-alt me-1 small"></i> Delete
                    </button>
                </div>
            </div>
        `).reverse().join('');
    },

    async resumeSale(heldId) {
        let heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
        const saleIndex = heldSales.findIndex(s => s.id === heldId);

        if (saleIndex === -1) return;

        const sale = heldSales[saleIndex];

        // Ask for confirmation if current cart is not empty
        if (this.cart.length > 0) {
            const confirmed = await App.confirm(
                App.t('pos.msg.resume_title') || 'Resume Sale?',
                App.t('pos.msg.resume_desc') || 'This will replace your current cart. Continue?'
            );
            if (!confirmed) return;
        }

        this.cart = sale.cart;
        this.selectedCustomer = sale.customer;

        // Update UI
        if (this.selectedCustomer) {
            document.getElementById('pos-selected-customer-name').textContent = this.selectedCustomer.name;
            document.getElementById('pos-selected-customer-phone').textContent = this.selectedCustomer.phone || '';
            document.getElementById('pos-selected-customer-points-container').classList.remove('d-none');
            document.getElementById('pos-selected-customer-points').textContent = this.selectedCustomer.loyalty_points || 0;
            document.getElementById('cart-points-row').classList.remove('d-none');

            const ptVal = App.state.settings.loyalty_point_value || 1;
            document.getElementById('points-value-hint').textContent = `(1 pt = ${App.formatCurrency(ptVal)})`;
        } else {
            this.selectCustomer(null);
        }

        document.getElementById('cart-discount').value = sale.discount || 0;
        document.getElementById('cart-points-redeem').value = sale.points_redeemed || 0;

        // Restore invoice type if saved
        if (sale.invoice_type) {
            const invoiceRadio = document.querySelector(`input[name="invoice-type"][value="${sale.invoice_type}"]`);
            if (invoiceRadio) invoiceRadio.checked = true;
        }

        // Remove from held sales
        heldSales.splice(saleIndex, 1);
        localStorage.setItem('pos_held_sales', JSON.stringify(heldSales));

        this.renderCart();
        this.calculateTotals();
        this.renderHeldSales();

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('posHeldSalesModal'));
        if (modal) modal.hide();

        App.toast('success', 'Sale resumed');
    },

    async deleteHeldSale(heldId) {
        const confirmed = await App.confirm(
            App.t('pos.msg.delete_held_title') || 'Delete Held Sale?',
            App.t('pos.msg.delete_held_desc') || 'Are you sure you want to delete this held sale?',
            'warning'
        );
        if (!confirmed) return;

        let heldSales = JSON.parse(localStorage.getItem('pos_held_sales') || '[]');
        heldSales = heldSales.filter(s => s.id !== heldId);
        localStorage.setItem('pos_held_sales', JSON.stringify(heldSales));

        this.renderHeldSales();
        App.toast('info', 'Held sale deleted');
    },

    searchCustomers(query) {
        const resultsContainer = document.getElementById('pos-customer-results');
        if (!query || query.length < 2) {
            resultsContainer.innerHTML = '<div class="text-center py-4 text-muted small">Type at least 2 characters to search...</div>';
            return;
        }

        const filtered = this.allCustomers.filter(c =>
            c.name.toLowerCase().includes(query.toLowerCase()) ||
            (c.phone && c.phone.includes(query))
        );

        if (filtered.length === 0) {
            resultsContainer.innerHTML = '<div class="text-center py-4 text-muted small">No customers found</div>';
            return;
        }

        resultsContainer.innerHTML = filtered.map(c => `
            <div class="customer-result-item d-flex align-items-center p-3 mb-2 rounded-4 border pointer hover-bg-light" onclick="posModule.selectCustomer(${c.id})">
                <div class="avatar me-3 bg-teal-soft text-teal rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">${c.name}</h6>
                    <small class="text-muted">${c.phone || 'No phone'}</small>
                </div>
            </div>
        `).join('');
    },

    selectCustomer(customerId) {
        if (customerId === null) {
            this.selectedCustomer = null;
            document.getElementById('pos-selected-customer-name').textContent = App.t('pos.walking_customer') || 'Walking Customer';
            document.getElementById('pos-selected-customer-phone').textContent = '';
            document.getElementById('pos-selected-customer-points-container').classList.add('d-none');
            document.getElementById('cart-points-row').classList.add('d-none');
            document.getElementById('cart-points-redeem').value = 0;
            // Also reset paid amount for walking customer to avoid accidental debt
            document.getElementById('cart-paid-amount').value = '';
        } else {
            const customer = this.allCustomers.find(c => c.id == customerId);
            if (customer) {
                this.selectedCustomer = customer;
                document.getElementById('pos-selected-customer-name').textContent = customer.name;
                document.getElementById('pos-selected-customer-phone').textContent = customer.phone || '';
                document.getElementById('pos-selected-customer-points-container').classList.remove('d-none');
                document.getElementById('pos-selected-customer-points').textContent = customer.loyalty_points || 0;
                document.getElementById('cart-points-row').classList.remove('d-none');

                const ptVal = App.state.settings.loyalty_point_value || 1;
                document.getElementById('points-value-hint').textContent = `(1 pt = ${App.formatCurrency(ptVal)})`;
            }
        }

        this.calculateTotals();

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('posCustomerModal'));
        if (modal) modal.hide();

        // Clear search
        document.getElementById('pos-customer-search').value = '';
        document.getElementById('pos-customer-results').innerHTML = '<div class="text-center py-4 text-muted small">Type to search customers...</div>';
    },

    async loadCategories() {
        const result = await App.api('catalog.php?action=list&type=categories');
        const filter = document.getElementById('pos-category-filter');
        if (result && result.data && filter) {
            filter.innerHTML = `<option value="">${App.t('pos.all_categories') || 'All Categories'}</option>` +
                result.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        }
    },

    renderProducts(products) {
        const grid = document.getElementById('pos-product-grid');
        if (products.length === 0) {
            grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">No products found</div>';
            return;
        }

        grid.innerHTML = products.map(p => {
            let badges = '';

            if (p.stock_qty <= 0) {
                badges += `<span class="badge bg-danger px-2">${App.t('pos.badge.out_of_stock') || 'Out of Stock'}</span>`;
            } else if (p.stock_qty <= 5) {
                badges += `<span class="badge bg-warning text-dark px-2">${App.t('pos.badge.low_stock') || 'Low Stock'}</span>`;
            }

            if (p.expiry_date) {
                const expDate = new Date(p.expiry_date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const nextMonth = new Date(today);
                nextMonth.setMonth(nextMonth.getMonth() + 1);

                if (expDate < today) {
                    badges += `<span class="badge bg-danger px-2">${App.t('pos.badge.expired') || 'Expired'}</span>`;
                } else if (expDate <= nextMonth) {
                    badges += `<span class="badge bg-warning text-dark px-2">${App.t('pos.badge.expiring_soon') || 'Expiring Soon'}</span>`;
                }
            }

            return `
            <div class="col-md-4 col-xl-3">
                <div class="card product-card border-0 shadow-sm h-100 rounded-4 pointer" onclick="posModule.addToCart(${p.id})">
                    <div class="p-3 position-relative">
                        <img src="assets/img/products/${p.image || 'default.jpg'}" class="card-img-top rounded-4" style="height: 120px; object-fit: contain;" onerror="this.src='assets/img/img_holder.png'">
                        <div class="position-absolute top-0 end-0 m-3 d-flex flex-column gap-1 align-items-end">
                            ${badges}
                        </div>
                    </div>
                    <div class="card-body pt-0 text-center">
                        <h6 class="fw-bold mb-1 text-truncate">${p.name}</h6>
                        <span class="text-teal fw-bold">${App.formatCurrency(p.selling_price)}</span>
                    </div>
                </div>
            </div>
            `;
        }).join('');
    },

    filterProducts(query, category) {
        const filtered = this.allProducts.filter(p => {
            const matchesSearch = p.name.toLowerCase().includes(query.toLowerCase());
            const matchesCategory = category ? p.category_id == category : true;
            return matchesSearch && matchesCategory;
        });
        this.renderProducts(filtered);
    },

    async addToCart(productId) {
        const product = this.allProducts.find(p => p.id === productId);
        if (!product) return;

        const cartItem = this.cart.find(item => item.id === productId);
        const newQty = cartItem ? cartItem.qty + 1 : 1;

        if (newQty > product.stock_qty) {
            App.toast('warning', 'Exceeds available stock');
            return;
        }

        // Check Batch FIFO for BL Warning
        let remainingToCheck = newQty;
        let hitsBL = false;

        try {
            const batches = typeof product.batches === 'string' ? JSON.parse(product.batches) : (product.batches || []);
            for (let batch of batches) {
                if (remainingToCheck <= 0) break;
                if (batch.type === 'BL') {
                    hitsBL = true;
                    break;
                }
                remainingToCheck -= batch.qty;
            }
        } catch (e) {
            console.error('Error parsing batches', e);
        }

        if (hitsBL && (!cartItem || cartItem.qty < newQty)) {
            // Only warn if they haven't already acknowledged BL for this item
            // Or if we need a strategy where we warn again, we can just warn.
            // Let's warn if it's the first time they hit a BL unit for this product.
            let alreadyHitBL = false;
            if (cartItem) {
                let oldRemaining = cartItem.qty;
                try {
                    const batches = typeof product.batches === 'string' ? JSON.parse(product.batches) : (product.batches || []);
                    for (let batch of batches) {
                        if (oldRemaining <= 0) break;
                        if (batch.type === 'BL') {
                            alreadyHitBL = true;
                            break;
                        }
                        oldRemaining -= batch.qty;
                    }
                } catch (e) { }
            }

            if (!alreadyHitBL) {
                const confirm = await Swal.fire({
                    title: 'Attention!',
                    text: `The requested quantity for "${product.name}" requires using stock from a "Delivery Note" (BL). Do you really want to add it to the cart?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, add to cart',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                });

                if (!confirm.isConfirmed) {
                    return;
                }
            }
        }

        if (cartItem) {
            cartItem.qty = newQty;
        } else {
            this.cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.selling_price),
                qty: 1,
                image: product.image
            });
        }

        this.renderCart();
        this.calculateTotals();
    },

    async updateQty(productId, delta) {
        const item = this.cart.find(i => i.id === productId);
        const product = this.allProducts.find(p => p.id === productId);

        if (item) {
            const newQty = item.qty + delta;

            if (newQty <= 0) {
                this.cart = this.cart.filter(i => i.id !== productId);
            } else if (newQty > product.stock_qty) {
                App.toast('warning', 'Maximum available stock reached');
            } else {
                if (delta > 0) {
                    // Check Batch FIFO for BL Warning
                    let remainingToCheck = newQty;
                    let hitsBL = false;

                    try {
                        const batches = typeof product.batches === 'string' ? JSON.parse(product.batches) : (product.batches || []);
                        for (let batch of batches) {
                            if (remainingToCheck <= 0) break;
                            if (batch.type === 'BL') {
                                hitsBL = true;
                                break;
                            }
                            remainingToCheck -= batch.qty;
                        }
                    } catch (e) { }

                    if (hitsBL) {
                        let alreadyHitBL = false;
                        let oldRemaining = item.qty;
                        try {
                            const batches = typeof product.batches === 'string' ? JSON.parse(product.batches) : (product.batches || []);
                            for (let batch of batches) {
                                if (oldRemaining <= 0) break;
                                if (batch.type === 'BL') {
                                    alreadyHitBL = true;
                                    break;
                                }
                                oldRemaining -= batch.qty;
                            }
                        } catch (e) { }

                        if (!alreadyHitBL) {
                            const confirm = await Swal.fire({
                                title: 'Attention!',
                                text: `This additional quantity requires using stock from a "Delivery Note" (BL). Do you want to continue?`,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#ffc107',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Yes, add',
                                cancelButtonText: 'Cancel',
                                reverseButtons: true
                            });

                            if (!confirm.isConfirmed) {
                                return;
                            }
                        }
                    }
                }
                item.qty = newQty;
            }
        }
        this.renderCart();
        this.calculateTotals();
    },

    removeFromCart(productId) {
        this.cart = this.cart.filter(i => i.id !== productId);
        this.renderCart();
        this.calculateTotals();
    },

    clearCart() {
        this.cart = [];
        document.getElementById('cart-paid-amount').value = '';
        this.renderCart();
        this.calculateTotals();
    },

    renderCart() {
        const cartList = document.getElementById('pos-cart-items');
        if (this.cart.length === 0) {
            cartList.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-basket-shopping mb-3 opacity-25" style="font-size: calc(1.575rem + 3.9vw);"></i>
                    <p>${App.t('pos.cart_empty') || 'Cart is empty'}</p>
                </div>
            `;
            return;
        }

        cartList.innerHTML = this.cart.map(item => `
            <div class="cart-item d-flex align-items-center mb-3 p-2 bg-light rounded-4">
                <img src="assets/img/products/${item.image || 'default.jpg'}" class="rounded-3 me-3" width="50" height="50" onerror="this.src='assets/img/img_holder.png'">
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold small text-truncate" style="max-width: 140px;">${item.name}</h6>
                    <span class="text-teal fw-bold">${App.formatCurrency(item.price)}</span>
                </div>
                <div class="d-flex align-items-center me-3">
                    <button class="btn btn-sm btn-white border-0 shadow-none p-1" onclick="posModule.updateQty(${item.id}, -1)">
                        <i class="fas fa-circle-minus text-muted"></i>
                    </button>
                    <span class="mx-2 fw-bold">${item.qty}</span>
                    <button class="btn btn-sm btn-white border-0 shadow-none p-1" onclick="posModule.updateQty(${item.id}, 1)">
                        <i class="fas fa-circle-plus text-teal"></i>
                    </button>
                </div>
                <button class="btn btn-sm text-danger" onclick="posModule.removeFromCart(${item.id})">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        `).join('');
    },

    calculateTotals() {
        const subtotal = this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        // Manual Discount
        const discountPercent = parseFloat(document.getElementById('cart-discount').value) || 0;
        let discountAmount = subtotal * (discountPercent / 100);

        // Points Discount
        let pointsRedeemed = 0;
        let pointsDiscount = 0;
        if (this.selectedCustomer) {
            const maxPoints = parseInt(this.selectedCustomer.loyalty_points || 0);
            let inputPoints = parseInt(document.getElementById('cart-points-redeem').value) || 0;

            if (inputPoints > maxPoints) {
                inputPoints = maxPoints;
                document.getElementById('cart-points-redeem').value = maxPoints;
            }
            if (inputPoints < 0) {
                inputPoints = 0;
                document.getElementById('cart-points-redeem').value = 0;
            }

            const ptVal = parseFloat(App.state.settings.loyalty_point_value || 1);
            pointsDiscount = inputPoints * ptVal;

            // Ensure points discount doesn't exceed subtotal after manual discount
            if (pointsDiscount > (subtotal - discountAmount)) {
                pointsDiscount = subtotal - discountAmount;
                inputPoints = Math.floor(pointsDiscount / ptVal);
                document.getElementById('cart-points-redeem').value = inputPoints;
            }

            pointsRedeemed = inputPoints;
            discountAmount += pointsDiscount;
        }

        const invoiceType = document.querySelector('input[name="invoice-type"]:checked')?.value || 'BV';
        const currentTaxRate = invoiceType === 'BL' ? 0 : this.taxRate;

        const taxableAmount = Math.max(0, subtotal - discountAmount);
        const tax = taxableAmount * currentTaxRate;
        const total = taxableAmount + tax;

        // Calculate potential points earned
        const earningRate = parseFloat(App.state.settings.loyalty_earning_rate || 100);
        const pointsEarned = earningRate > 0 ? Math.floor(total / earningRate) : 0;

        document.getElementById('cart-subtotal').textContent = App.formatCurrency(subtotal);
        document.getElementById('cart-tax').textContent = App.formatCurrency(tax);

        // Update vat label visually
        const vatRateDisplay = document.getElementById('vat-rate-display');
        if (vatRateDisplay) {
            vatRateDisplay.textContent = invoiceType === 'BL' ? 0 : (this.taxRate * 100);
        }

        document.getElementById('cart-total').textContent = App.formatCurrency(total);

        // Handle Paid Amount / Change / Debt
        const paidInput = document.getElementById('cart-paid-amount');
        const paidVal = parseFloat(paidInput.value);
        const changeDebtLabel = document.getElementById('change-debt-label');
        const changeDebtDisplay = document.getElementById('cart-change-debt');

        if (isNaN(paidVal)) {
            changeDebtDisplay.textContent = App.formatCurrency(0);
            changeDebtLabel.textContent = App.t('pos.change_due') || 'Change';
            changeDebtDisplay.className = 'fw-bold mb-0 text-navy';
            paidInput.placeholder = total.toFixed(2);
        } else {
            const diff = paidVal - total;
            changeDebtDisplay.textContent = App.formatCurrency(Math.abs(diff));
            if (diff >= 0) {
                changeDebtLabel.textContent = App.t('pos.change_due') || 'Change';
                changeDebtDisplay.className = 'fw-bold mb-0 text-success';
            } else {
                changeDebtLabel.textContent = App.t('pos.debt') || 'Debt';
                changeDebtDisplay.className = 'fw-bold mb-0 text-danger';
                if (!this.selectedCustomer) {
                    App.toast('warning', App.t('pos.msg.debt_not_allowed') || 'Debt is only allowed for registered customers.');
                }
            }
        }

        return { subtotal, discountAmount, tax, total, pointsRedeemed, pointsEarned, paidAmount: isNaN(paidVal) ? total : paidVal };
    },

    async processCheckout() {
        if (this.cart.length === 0) {
            App.toast('error', App.t('pos.cart_empty') || 'Cart is empty');
            return;
        }

        // F4.7: Prevent double-submit
        const btn = document.getElementById('btn-checkout');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${App.t('pos.msg.processing') || 'Processing...'}`;

        try {
            const totals = this.calculateTotals();
            const paymentMethod = document.querySelector('input[name="payment-method"]:checked').value;
            const invoiceType = document.querySelector('input[name="invoice-type"]:checked')?.value || 'BV';

            const saleData = {
                customer_id: this.selectedCustomer ? this.selectedCustomer.id : null,
                subtotal: totals.subtotal,
                discount_amount: totals.discountAmount,
                tax: totals.tax,
                total: totals.total,
                paid_amount: totals.paidAmount,
                points_redeemed: totals.pointsRedeemed,
                points_earned: totals.pointsEarned,
                payment_method: paymentMethod,
                invoice_type: invoiceType,
                items: this.cart
            };

            const result = await App.api('sales.php?action=process_sale', 'POST', saleData);
            if (result && result.success) {
                App.toast('success', 'Sale completed!');
                document.getElementById('cart-points-redeem').value = 0;
                this.clearCart();
                await this.loadProducts(); // Refresh stock in local state
                if (this.selectedCustomer) {
                    await this.loadCustomers(); // Refresh loyalty points
                    this.selectCustomer(this.selectedCustomer.id);
                }
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Complete Sale';
        }
    }
};

// Initialize
if (document.getElementById('pos-product-grid')) {
    posModule.init();
}

window.posModule = posModule;
