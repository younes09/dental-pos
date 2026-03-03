/**
 * POS Module
 */

const posModule = {
    allProducts: [],
    cart: [],
    taxRate: 0.15, // 15% VAT

    init() {
        this.loadCategories();

        // Handle search query from URL if any
        const params = new URLSearchParams(window.location.hash.split('?')[1]);
        const searchQuery = params.get('search');

        this.loadProducts(searchQuery);
        this.bindEvents();
        console.log('POS Module Loaded');
    },

    bindEvents() {
        // Search filter
        document.getElementById('pos-search').oninput = (e) => this.filterProducts(e.target.value);

        // Category filter
        document.getElementById('pos-category-filter').onchange = (e) => this.filterProducts(document.getElementById('pos-search').value, e.target.value);

        // Cart controls
        document.getElementById('btn-clear-cart').onclick = () => this.clearCart();
        document.getElementById('cart-discount').oninput = () => this.calculateTotals();

        document.getElementById('btn-checkout').onclick = () => this.processCheckout();
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
        }
    },

    loadCategories() {
        // Just extract from products for now or fetch from meta
        const categories = [...new Set(this.allProducts.map(p => p.category_id))];
        // For a better UX, should fetch category names
    },

    renderProducts(products) {
        const grid = document.getElementById('pos-product-grid');
        if (products.length === 0) {
            grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">No products found</div>';
            return;
        }

        grid.innerHTML = products.map(p => `
            <div class="col-md-4 col-xl-3">
                <div class="card product-card border-0 shadow-sm h-100 rounded-4 pointer" onclick="posModule.addToCart(${p.id})">
                    <div class="p-3 position-relative">
                        <img src="assets/img/products/${p.image || 'default.jpg'}" class="card-img-top rounded-4" style="height: 120px; object-fit: contain;" onerror="this.src='https://ui-avatars.com/api/?name=P&background=random'">
                        ${p.stock_qty <= 5 ? '<span class="badge bg-danger position-absolute top-0 end-0 m-3 px-2">Low Stock</span>' : ''}
                    </div>
                    <div class="card-body pt-0 text-center">
                        <h6 class="fw-bold mb-1 text-truncate">${p.name}</h6>
                        <span class="text-teal fw-bold">$${parseFloat(p.selling_price).toFixed(2)}</span>
                    </div>
                </div>
            </div>
        `).join('');
    },

    filterProducts(query, category) {
        const filtered = this.allProducts.filter(p => {
            const matchesSearch = p.name.toLowerCase().includes(query.toLowerCase());
            const matchesCategory = category ? p.category_id == category : true;
            return matchesSearch && matchesCategory;
        });
        this.renderProducts(filtered);
    },

    addToCart(productId) {
        const product = this.allProducts.find(p => p.id === productId);
        if (!product) return;

        const cartItem = this.cart.find(item => item.id === productId);
        if (cartItem) {
            if (cartItem.qty < product.stock_qty) {
                cartItem.qty++;
            } else {
                App.toast('warning', 'Exceeds available stock');
                return;
            }
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

    updateQty(productId, delta) {
        const item = this.cart.find(i => i.id === productId);
        const product = this.allProducts.find(p => p.id === productId);

        if (item) {
            item.qty += delta;
            if (item.qty <= 0) {
                this.cart = this.cart.filter(i => i.id !== productId);
            } else if (item.qty > product.stock_qty) {
                item.qty = product.stock_qty;
                App.toast('warning', 'Maximum available stock reached');
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
        this.renderCart();
        this.calculateTotals();
    },

    renderCart() {
        const cartList = document.getElementById('pos-cart-items');
        if (this.cart.length === 0) {
            cartList.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-shopping-basket display-1 mb-3 opacity-25"></i>
                    <p>Cart is empty</p>
                </div>
            `;
            return;
        }

        cartList.innerHTML = this.cart.map(item => `
            <div class="cart-item d-flex align-items-center mb-3 p-2 bg-light rounded-4">
                <img src="assets/img/products/${item.image || 'default.jpg'}" class="rounded-3 me-3" width="50" height="50" onerror="this.src='https://ui-avatars.com/api/?name=P&background=random'">
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold small text-truncate" style="max-width: 140px;">${item.name}</h6>
                    <span class="text-teal fw-bold">$${item.price.toFixed(2)}</span>
                </div>
                <div class="d-flex align-items-center me-3">
                    <button class="btn btn-sm btn-white border-0 shadow-none p-1" onclick="posModule.updateQty(${item.id}, -1)">
                        <i class="fas fa-minus-circle text-muted"></i>
                    </button>
                    <span class="mx-2 fw-bold">${item.qty}</span>
                    <button class="btn btn-sm btn-white border-0 shadow-none p-1" onclick="posModule.updateQty(${item.id}, 1)">
                        <i class="fas fa-plus-circle text-teal"></i>
                    </button>
                </div>
                <button class="btn btn-sm text-danger" onclick="posModule.removeFromCart(${item.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    },

    calculateTotals() {
        const subtotal = this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const discountPercent = parseFloat(document.getElementById('cart-discount').value) || 0;
        const discountAmount = subtotal * (discountPercent / 100);
        const taxableAmount = subtotal - discountAmount;
        const tax = taxableAmount * this.taxRate;
        const total = taxableAmount + tax;

        document.getElementById('cart-subtotal').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('cart-tax').textContent = `$${tax.toFixed(2)}`;
        document.getElementById('cart-total').textContent = `$${total.toFixed(2)}`;

        return { subtotal, discountAmount, tax, total };
    },

    async processCheckout() {
        if (this.cart.length === 0) {
            App.toast('error', 'Cart is empty');
            return;
        }

        const totals = this.calculateTotals();
        const paymentMethod = document.querySelector('input[name="payment-method"]:checked').value;

        const saleData = {
            subtotal: totals.subtotal,
            discount_amount: totals.discountAmount,
            tax: totals.tax,
            total: totals.total,
            payment_method: paymentMethod,
            items: this.cart
        };

        const result = await App.api('sales.php?action=process_sale', 'POST', saleData);
        if (result && result.success) {
            App.toast('success', 'Sale completed!');
            this.clearCart();
            await this.loadProducts(); // Refresh stock in local state
        }
    }
};

// Initialize
if (document.getElementById('pos-product-grid')) {
    posModule.init();
}

window.posModule = posModule;
