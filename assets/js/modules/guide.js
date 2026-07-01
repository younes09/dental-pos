/**
 * DentalPOS Interactive User Guide Module
 */

const guideModule = {
    // POS Hotspots Explanations Database
    posSpots: {
        search: {
            title: "1. Recherche & Catégories",
            desc: "Cette zone vous permet de chercher un produit en scannant son code-barres ou en tapant son nom. Les filtres par catégorie permettent de classer les produits à l'écran."
        },
        grid: {
            title: "2. Grille des Produits",
            desc: "Affiche les fiches de stock des produits sous forme de cartes. Si un produit est périmé, en stock faible ou en rupture, un badge rouge ou orange est affiché sur sa photo."
        },
        customer: {
            title: "3. Choix du Client & Fidélité",
            desc: "Permet de lier la vente à un client pour suivre ses points de fidélité et autoriser la vente à crédit. Par défaut, c'est le 'Client de passage' (crédit non autorisé)."
        },
        totals: {
            title: "4. Calculs des Totaux & Crédit",
            desc: "Affiche le sous-total, la taxe (TVA) et le total à payer. Si le montant saisi dans 'Montant Payé' est inférieur au total, le reste s'enregistre en tant que dette (crédit) client."
        },
        docpay: {
            title: "5. Type de Document & Paiement",
            desc: "Choisissez le type de reçu : </strong>Bon de Vente (BV)<strong> (ticket ordinaire) ou </strong>Bon de Livraison (BL)<strong> (professionnel), et le mode de paiement (Espèces ou Carte bancaire)."
        },
        shortcuts: {
            title: "6. Raccourcis Utiles",
            desc: "Boutons rapides pour mettre en attente (Pause) une vente en cours, retrouver les paniers suspendus (Récentes), ou charger un devis client proforma."
        }
    },

    // Scenario Simulator Database
    scenarios: {
        debt: [
            {
                title: "Étape 1 : Choisir le client",
                desc: "Au Point de Vente (POS), cliquez sur le bouton </strong>Changer<strong> à côté du client. Recherchez le nom du dentiste (ex: Dr. Benali) et sélectionnez-le. N'utilisez pas 'Client de passage', car les crédits anonymes sont bloqués."
            },
            {
                title: "Étape 2 : Ajouter les produits",
                desc: "Ajoutez les produits souhaités au panier en scannant leurs codes-barres ou en cliquant sur leurs images."
            },
            {
                title: "Étape 3 : Saisir le montant perçu",
                desc: "Dans le champ </strong>Montant Payé<strong>, tapez la somme réelle apportée par le client (ex: 20 000 DZD pour un achat total de 35 000 DZD)."
            },
            {
                title: "Étape 4 : Valider la vente",
                desc: "Cliquez sur le bouton vert </strong>COMPLÉTER LA VENTE<strong>. Le système enregistre 20 000 DZD dans votre caisse et inscrit la différence (15 000 DZD) comme une dette à payer dans la fiche du Dr. Benali."
            }
        ],
        bl_alert: [
            {
                title: "Étape 1 : Alerte Stock BL",
                desc: "Vous ajoutez un produit au panier. L'ordinateur affiche une alerte jaune vous prévenant que la quantité dépasse le stock facturé (BA) et va piocher dans le stock de livraison simple (BL)."
            },
            {
                title: "Étape 2 : Valider l'avertissement",
                desc: "Si vous êtes d'accord pour vendre ce produit issu du lot BL, cliquez sur le bouton </strong>Oui, ajouter au panier<strong>."
            },
            {
                title: "Étape 3 : Changer le type de document",
                desc: "Pour rester cohérent avec votre comptabilité et votre stock, cochez l'option </strong>Bon de Livraison (BL)<strong> sous le panier au lieu de Bon de Vente (BV) officiel."
            },
            {
                title: "Étape 4 : Encaisser",
                desc: "Procédez à la validation finale de la vente. Le document généré sera un Bon de Livraison. Les stocks BL seront mis à jour."
            }
        ],
        broken: [
            {
                title: "Étape 1 : Ouvrir le produit",
                desc: "Rendez-vous dans </strong>Inventaire<strong> > </strong>Gestion de Stock<strong> et recherchez l'article qui a été endommagé (ex: Flacon de composite fêlé)."
            },
            {
                title: "Étape 2 : Cliquer sur Ajuster",
                desc: "Sur la ligne du produit, cliquez sur le bouton </strong>Ajuster le stock<strong> (Adjust Stock)."
            },
            {
                title: "Étape 3 : Saisir la perte",
                desc: "Sélectionnez l'action </strong>Soustraire (-)<em></em>. Saisissez la quantité perdue (ex: 1)."
            },
            {
                title: "Étape 4 : Renseigner le motif",
                desc: "Dans le champ motif, tapez la raison (ex: 'Flacon fêlé lors du rangement'). Cliquez sur Enregistrer. Votre stock est à jour et la perte est tracée historiquement."
            }
        ]
    },

    // Current State for Simulator
    currentScenario: "debt",
    currentStep: 0,

    init() {
        console.log('Initializing Guide Module...');
        
        // Correct title since Guide is not in the sidebar navigation
        const titleEl = document.getElementById('currentViewTitle');
        if (titleEl) {
            titleEl.textContent = App.t('guide.title') || "Guide d'Utilisation";
        }
        
        this.bindEvents();
        this.resetSearch();
        this.loadScenario("debt");
        
        // Trigger initial scroll check
        this.handleScrollSpy();
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

        // Search Input Filter (Main Content)
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
            
            const target = $(this).attr('href');
            const targetEl = $(target);
            if (targetEl.length) {
                // Temporarily disable scrollspy during manual scroll animation to prevent flickering active states
                $(window).off('scroll.guide');
                
                $('.toc-link').removeClass('active');
                $(this).addClass('active');

                $('html, body').animate({
                    scrollTop: targetEl.offset().top - 90
                }, 450, function() {
                    // Re-bind scrollspy after smooth scroll completes
                    $(window).off('scroll.guide').on('scroll.guide', function() {
                        self.handleScrollSpy();
                    });
                });
            }
        });

        // POS Hotspots hover/click action
        $('.pos-hotspot').off('click').on('click', function() {
            $('.pos-hotspot').removeClass('active');
            $(this).addClass('active');
            
            const spot = $(this).data('spot');
            const data = self.posSpots[spot];
            
            if (data) {
                $('#pos-explain-title').text(data.title);
                $('#pos-explain-desc').html(data.desc);
            }
        });

        // Simulator Scenario Selection change
        $('#sim-scenario-selector').off('change').on('change', function() {
            const val = $(this).val();
            self.loadScenario(val);
        });

        // Simulator Button Navigation
        $('#sim-btn-next').off('click').on('click', function() {
            const steps = self.scenarios[self.currentScenario];
            if (self.currentStep < steps.length - 1) {
                self.currentStep++;
                self.updateScenarioStep();
            }
        });

        $('#sim-btn-prev').off('click').on('click', function() {
            if (self.currentStep > 0) {
                self.currentStep--;
                self.updateScenarioStep();
            }
        });

        // Glossary live search
        $('#glossary-search').off('keyup').on('keyup', function() {
            const query = $(this).val().toLowerCase().trim();
            $('.glossary-item').each(function() {
                const text = $(this).text().toLowerCase();
                if (text.indexOf(query) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Quiz Submission
        $('#quiz-btn-submit').off('click').on('click', function() {
            self.gradeQuiz();
        });

        // Window Scroll Spy Scroll Listener
        $(window).off('scroll.guide').on('scroll.guide', function() {
            self.handleScrollSpy();
        });
    },

    // ScrollSpy Core logic
    handleScrollSpy() {
        const scrollPos = $(window).scrollTop();
        const navbarHeight = 110; // offset of top bar and spacing
        let activeId = null;

        $('.guide-section').each(function() {
            if ($(this).is(':visible')) {
                const top = $(this).offset().top - navbarHeight;
                const bottom = top + $(this).outerHeight();

                if (scrollPos >= top && scrollPos < bottom) {
                    activeId = $(this).attr('id');
                }
            }
        });

        // Fallback: If at the absolute top, activate the first visible section
        if (scrollPos < 100) {
            activeId = $('.guide-section:visible').first().attr('id');
        }

        // Fallback: If scrolled to the bottom of the page, activate the last visible section
        if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
            activeId = $('.guide-section:visible').last().attr('id');
        }

        if (activeId) {
            $('.toc-link').removeClass('active');
            $(`.toc-link[href="#${activeId}"]`).addClass('active');
        }
    },

    // Simulator Helpers
    loadScenario(scenarioName) {
        this.currentScenario = scenarioName;
        this.currentStep = 0;
        
        let title = "Scénario A : Vente à crédit";
        if (scenarioName === "bl_alert") title = "Scénario B : Alerte Stock BL";
        if (scenarioName === "broken") title = "Scénario C : Produit Cassé";
        
        $('#sim-scenario-title').text(title);
        this.updateScenarioStep();
    },

    updateScenarioStep() {
        const steps = this.scenarios[this.currentScenario];
        const stepData = steps[this.currentStep];
        
        // Update Step indicators
        const indicators = $('#sec-simulation .sim-step-indicator');
        indicators.removeClass('active completed');
        
        indicators.each((idx, el) => {
            if (idx < this.currentStep) {
                $(el).addClass('completed');
            } else if (idx === this.currentStep) {
                $(el).addClass('active');
            }
        });
        
        // Update Step content text
        if (stepData) {
            $('#sim-step-content').html(`
                <h6 class="fw-bold text-teal">${stepData.title}</h6>
                <p class="small text-muted mb-0">${stepData.desc}</p>
            `).hide().fadeIn(250);
        }
        
        // Enable/Disable buttons
        $('#sim-btn-prev').prop('disabled', this.currentStep === 0);
        if (this.currentStep === steps.length - 1) {
            $('#sim-btn-next').html('Terminer <i class="fas fa-check ms-1"></i>');
        } else {
            $('#sim-btn-next').html('Suivant <i class="fas fa-chevron-right ms-1"></i>');
        }
    },

    // Quiz Grading Logic
    gradeQuiz() {
        let score = 0;
        const total = 3;
        
        // Q1 Check
        const q1Val = $('input[name="q1"]:checked').val();
        const q1Correct = q1Val === "no";
        if (q1Correct) score++;
        
        // Q2 Check
        const q2Val = $('input[name="q2"]:checked').val();
        const q2Correct = q2Val === "select_bl";
        if (q2Correct) score++;

        // Q3 Check
        const q3Val = $('input[name="q3"]:checked').val();
        const q3Correct = q3Val === "auto";
        if (q3Correct) score++;

        // Show results
        if (!q1Val || !q2Val || !q3Val) {
            Swal.fire({
                title: "Attention",
                text: "Veuillez répondre à toutes les questions avant de soumettre.",
                icon: "warning",
                confirmButtonColor: "#0d9488"
            });
            return;
        }

        // Highlight answers
        $('.quiz-question').each(function() {
            const qid = $(this).data('qid');
            let isCorrect = false;
            let explanation = "";
            
            if (qid === 1) {
                isCorrect = q1Correct;
                explanation = "Explication : Seuls les clients enregistrés disposent d'un dossier historique pour le suivi des créances.";
            } else if (qid === 2) {
                isCorrect = q2Correct;
                explanation = "Explication : L'alerte BL prévient que vous allez puiser dans un stock acheté sans facture officielle. Il faut donc ajuster votre type de document en conséquence.";
            } else if (qid === 3) {
                isCorrect = q3Correct;
                explanation = "Explication : La réception d'un Bon de Commande (PO) incrémente automatiquement les quantités de stock correspondantes.";
            }

            $(this).find('.quiz-feedback').remove();
            if (isCorrect) {
                $(this).append(`<div class="quiz-feedback alert alert-success py-2 px-3 small mt-2"><i class="fas fa-check-circle me-2"></i>Correct ! ${explanation}</div>`);
            } else {
                $(this).append(`<div class="quiz-feedback alert alert-danger py-2 px-3 small mt-2"><i class="fas fa-times-circle me-2"></i>Incorrect. ${explanation}</div>`);
            }
        });

        $('#quiz-result-score').text(`Votre score : ${score} / ${total}`);
        
        let message = "Excellent travail ! Vous maîtrisez les concepts fondamentaux de DentalPOS.";
        let icon = "success";
        if (score < total) {
            message = "Pas mal ! Relisez les sections concernées pour parfaire vos connaissances.";
            icon = "info";
        }
        
        Swal.fire({
            title: `Score : ${score}/${total}`,
            text: message,
            icon: icon,
            confirmButtonColor: "#0d9488"
        });
    },

    // Main Content Search Filter
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

        // Show empty message if nothing matches
        setTimeout(() => {
            const visibleCount = $('.guide-section:visible').length;
            if (visibleCount === 0) {
                if (!$('#guide-no-results').length) {
                    $('#guide-content-area').append(`
                        <div id="guide-no-results" class="text-center py-5 text-muted card border-0 shadow-sm rounded-4 mt-3 animate__animated animate__fadeIn">
                            <i class="fas fa-search-minus fa-3x text-teal mb-3"></i>
                            <h5>Aucun résultat trouvé</h5>
                            <p class="small mb-0">Essayez avec d'autres mots-clés comme "caisse", "dette", "stock", "BL" ou "bilan".</p>
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

// Initialize module
guideModule.init();
window.guideModule = guideModule;
