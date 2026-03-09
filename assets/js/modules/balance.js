/**
 * Balance Module - Handles financial summaries and capital calculation
 */

const balanceModule = {
    init() {
        console.log('Balance Module Initialized');
        this.fetchBalanceData();
        this.bindEvents();
    },

    bindEvents() {
        $('#refreshBalance').on('click', () => {
            this.fetchBalanceData();
        });
    },

    async fetchBalanceData() {
        const result = await App.api('balance.php');
        if (result && result.data) {
            this.renderBalance(result.data);
        }
    },

    renderBalance(data) {
        // Summary Cards
        $('#netCapital').html(App.formatCurrency(data.net_capital));
        $('#totalAssets').html(App.formatCurrency(data.total_assets));
        $('#totalLiabilities').html(App.formatCurrency(data.total_liabilities));

        // Assets Breakdown Table
        $('#vaultBalanceBreakdown').html(App.formatCurrency(data.vault_balance));
        $('#inventoryValueBreakdown').html(App.formatCurrency(data.inventory_value));
        $('#receivableBreakdown').html(App.formatCurrency(data.accounts_receivable));
        $('#equipmentValueBreakdown').html(App.formatCurrency(data.equipment_value));
        $('#totalAssetsBreakdown').html(App.formatCurrency(data.total_assets));

        // Liabilities Breakdown
        $('#payableValueBreakdown').html(App.formatCurrency(data.accounts_payable));

        // Insights
        $('#netCapitalInsight').text(App.formatCurrency(data.net_capital));

        // Progress bar logic: Liabilities relative to Assets
        const liabilitiesPercent = data.total_assets > 0 ? (data.total_liabilities / data.total_assets) * 100 : 0;
        // The bar shows Assets minus Liabilities (Net Capital side)
        const assetsPercent = 100 - liabilitiesPercent;
        $('#capitalProgress').css('width', assetsPercent + '%');
    }
};

// Initialize
if (document.getElementById('refreshBalance')) {
    balanceModule.init();
}

window.balanceModule = balanceModule;
