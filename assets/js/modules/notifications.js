const notificationsModule = {
    table: null,

    async init() {
        await this.loadData();
    },

    async loadData() {
        const response = await App.api('notifications.php?action=history');
        if (response && response.data) {
            this.renderTable(response.data);
        }
    },

    renderTable(data) {
        if (this.table) {
            this.table.destroy();
        }

        const tbody = document.querySelector('#notificationsTable tbody');
        if (!tbody) return;

        tbody.innerHTML = data.map(n => `
            <tr class="${n.is_read == 0 ? 'table-active' : ''}">
                <td>
                    <div class="icon-circle bg-${n.type}-subtle text-${n.type} mx-auto" style="width: 35px; height: 35px; font-size: 14px;">
                        <i class="fas ${App.getNotificationIcon(n.type)}"></i>
                    </div>
                </td>
                <td class="fw-semibold">${n.title}</td>
                <td class="text-muted small">${n.message}</td>
                <td>
                    ${n.is_read == 0
                ? '<span class="badge bg-danger-subtle text-danger border border-danger p-2"><i class="fas fa-envelope me-1"></i>Unread</span>'
                : '<span class="badge bg-success-subtle text-success border border-success p-2"><i class="fas fa-envelope-open me-1"></i>Read</span>'}
                </td>
                <td class="small text-muted" data-sort="${n.created_at}">${App.formatDate(n.created_at)} <span class="d-block" style="font-size: 0.75rem;">${n.created_at.split(' ')[1]}</span></td>
                <td class="text-end">
                    ${n.link ? `<a href="${n.link}" class="btn btn-sm btn-light-primary me-2 tooltip-btn" title="Go to Linked Module" onclick="notificationsModule.markAsReadAndGo(${n.id}, '${n.link}')"><i class="fas fa-external-link-alt"></i></a>` : ''}
                    ${n.is_read == 0 ? `<button class="btn btn-sm btn-outline-secondary tooltip-btn" title="Mark as Read" onclick="notificationsModule.markAsRead(${n.id})"><i class="fas fa-check"></i></button>` : ''}
                </td>
            </tr>
        `).join('');

        // Initialize DataTable
        this.table = $('#notificationsTable').DataTable({
            order: [[4, 'desc']], // Sort by date descending
            pageLength: 25,
            dom: '<"row mb-3"<"col-md-6"B><"col-md-6 filter-row"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            buttons: [
                { extend: 'excel', className: 'btn btn-outline-secondary btn-sm', text: '<i class="fas fa-file-excel me-1"></i> Excel' },
                { extend: 'pdf', className: 'btn btn-outline-secondary btn-sm', text: '<i class="fas fa-file-pdf me-1"></i> PDF' },
                { extend: 'print', className: 'btn btn-outline-secondary btn-sm', text: '<i class="fas fa-print me-1"></i> Print' }
            ],
            language: {
                search: "",
                searchPlaceholder: "Search notifications..."
            },
            drawCallback: function () {
                $('.dataTables_filter input').addClass('form-control form-control-sm');
                // Re-initialize tooltips after redraw
                const tooltips = document.querySelectorAll('.tooltip-btn');
                tooltips.forEach(t => new bootstrap.Tooltip(t));
            }
        });
    },

    async markAsRead(id) {
        document.querySelectorAll('.tooltip').forEach(t => t.remove()); // Hide stuck tooltips
        const response = await App.api(`notifications.php?action=mark_read&id=${id}`);
        if (response && response.success) {
            App.loadNotifications(); // Update the header bell
            await this.loadData();    // Reload the table
        }
    },

    async markAsReadAndGo(id, link) {
        document.querySelectorAll('.tooltip').forEach(t => t.remove()); // Hide stuck tooltips
        await App.api(`notifications.php?action=mark_read&id=${id}`);
        App.loadNotifications(); // Update the header bell
        window.location.hash = link; // Navigate
    },

    async markAllAsRead() {
        const confirmed = await App.confirm('Mark All Read?', 'Are you sure you want to mark all notifications as read?', 'question');
        if (confirmed) {
            const response = await App.api('notifications.php?action=mark_all_read');
            if (response && response.success) {
                App.toast('success', 'All notifications marked as read');
                App.loadNotifications(); // Update the header bell
                await this.loadData();    // Reload the table
            }
        }
    }
};

// Initialize if loaded directly
if (App.state.currentRoute === 'notifications') {
    notificationsModule.init();
}
