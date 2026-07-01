/**
 * DentalPOS Interactive User Guide Module
 */

const guideModule = {
    init() {
        console.log('Initializing Guide Module...');
        
        // Correct title since Guide is not in the sidebar navigation
        const titleEl = document.getElementById('currentViewTitle');
        if (titleEl) {
            titleEl.textContent = App.t('guide.title') || "Guide d'Utilisation";
        }
        
        this.bindEvents();
        this.resetSearch();
    },

    bindEvents() {
        const self = this;

        // Role Card Selection Switcher
        $('.role-btn-card').off('click').on('click', function() {
            $('.role-btn-card').removeClass('active');
            $(this).addClass('active');

            const role = $(this).data('role');
            $('.role-detail-content').addClass('d-none');
            $(`#role-${role}`).removeClass('d-none').hide().fadeIn(300);
        });

        // Search Input Filter
        $('#guide-search').off('keyup').on('keyup', function() {
            const query = $(this).val().toLowerCase().trim();
            self.filterContent(query);
        });

        // Clear Search Action
        $('#btn-clear-guide-search').off('click').on('click', function() {
            $('#guide-search').val('');
            self.resetSearch();
        });

        // Smooth Scroll TOC Link Behavior
        $('.toc-link').off('click').on('click', function(e) {
            e.preventDefault();
            $('.toc-link').removeClass('active');
            $(this).addClass('active');

            const target = $(this).attr('href');
            const targetEl = $(target);
            if (targetEl.length) {
                // Scroll in main container context
                $('html, body').animate({
                    scrollTop: targetEl.offset().top - 100
                }, 400);
            }
        });
    },

    filterContent(query) {
        if (!query) {
            this.resetSearch();
            return;
        }

        $('#btn-clear-guide-search').show();

        $('.guide-section').each(function() {
            const sectionText = $(this).text().toLowerCase();
            if (sectionText.indexOf(query) !== -1) {
                $(this).fadeIn(200);
            } else {
                $(this).fadeOut(200);
            }
        });

        // Handle case where zero matching sections are found
        setTimeout(() => {
            const visibleCount = $('.guide-section:visible').length;
            if (visibleCount === 0) {
                if (!$('#guide-no-results').length) {
                    $('#guide-content-area').append(`
                        <div id="guide-no-results" class="text-center py-5 text-muted card border-0 shadow-sm rounded-4 mt-3 animate__animated animate__fadeIn">
                            <i class="fas fa-search-minus fa-3x text-teal mb-3"></i>
                            <h5>Aucun résultat trouvé</h5>
                            <p class="small mb-0">Essayez avec d'autres mots-clés comme "caisse", "dette", "stock" ou "BL".</p>
                        </div>
                    `);
                }
            } else {
                $('#guide-no-results').remove();
            }
        }, 250);
    },

    resetSearch() {
        $('#btn-clear-guide-search').hide();
        $('.guide-section').fadeIn(200);
        $('#guide-no-results').remove();
    }
};

// Auto-run initialization when loaded
guideModule.init();
window.guideModule = guideModule;
