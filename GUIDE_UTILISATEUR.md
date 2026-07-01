# Guide d'Utilisation - DentalPOS (Stock & Caisse Premium)

Bienvenue dans le guide d'utilisation de **DentalPOS**, votre système de gestion de stock et de point de vente spécialement conçu pour les dépôts dentaires et cliniques. 

Ce guide a été rédigé pour être accessible à tous, y compris aux personnes n'ayant pas de compétences techniques particulières. Toutes les notions complexes y sont expliquées avec des exemples simples du quotidien.

---

## Sommaire
1. [Rôles et Accès (Qui fait quoi ?)](#1-rôles-et-accès-qui-fait-quoi-)
2. [Routine Quotidienne : Caisse et Ventes](#2-routine-quotidienne--caisse-et-ventes)
   - [Étape 1 : Ouverture de la Caisse](#étape-1--ouverture-de-la-caisse)
   - [Étape 2 : Enregistrer des Ventes (POS)](#étape-2--enregistrer-des-ventes-pos)
   - [Étape 3 : Fermeture et Clôture de Caisse](#étape-3--fermeture-et-clôture-de-caisse)
3. [Gestion du Stock et des Approvisionnements](#3-gestion-du-stock-et-des-approvisionnements)
   - [Le Catalogue (Catégories et Marques)](#le-catalogue-catégories-et-marques)
   - [Ajouter un Produit](#ajouter-un-produit)
   - [Comprendre les Bons de Commande (PO)](#comprendre-les-bons-de-commande-po)
   - [Le Concept Complexe : Différence entre BA (Bon d'Achat) et BL (Bon de Livraison)](#le-concept-complexe--différence-entre-ba-bon-dachat-et-bl-bon-de-livraison)
   - [Ajuster le Stock Manuellement](#ajuster-le-stock-manuellement)
4. [Gestion des Clients, Fournisseurs et Dettes](#4-gestion-des-clients-fournisseurs-et-dettes)
   - [Gestion des Clients & Fidélité](#gestion-des-clients--fidélité)
   - [Régler une Dette Client](#régler-une-dette-client)
   - [Gestion des Fournisseurs & Paiements](#gestion-des-fournisseurs--paiements)
5. [Trésorerie et Bilan Financier](#5-trésorerie-et-bilan-financier)
   - [La Trésorerie (Comptes de Banque et Coffre-fort)](#la-trésorerie-comptes-de-banque-et-coffre-fort)
   - [Le Bilan Financier (Comprendre sa richesse nette)](#le-bilan-financier-comprendre-sa-richesse-nette)
   - [Gestion de la Paie (Salaires)](#gestion-de-la-paie-salaires)
6. [Administration et Outils Avancés](#6-administration-et-outils-avancés)
   - [Les Devis (Factures Proforma)](#les-devis-factures-proforma)
   - [Historique des Ventes et Retours de Marchandise](#historique-des-ventes-et-retours-de-marchandise)
   - [Statistiques, Rapports et Paramètres](#statistiques-rapports-et-paramètres)
7. [Astuces pour Travailler plus Vite (Raccourcis et Bonnes Pratiques)](#7-astuces-pour-travailler-plus-vite-raccourcis-et-bonnes-pratiques)

---

## 1. Rôles et Accès (Qui fait quoi ?)

Pour des raisons de sécurité, chaque employé possède son propre compte avec des droits limités selon sa fonction. Le système gère 3 rôles principaux :

*   **Administrateur (Admin)** : Il a accès à **tout**. Il gère la trésorerie générale, voit le bilan financier, configure les salaires des employés, ajoute de nouveaux utilisateurs et modifie les paramètres du système.
*   **Caissier (Cashier)** : Son rôle est concentré sur le contact client. Il peut ouvrir la caisse, enregistrer des ventes, créer des devis, gérer la liste des clients et recevoir les paiements des dettes des clients. Il ne peut pas voir le coffre-fort général, les bénéfices globaux, ni modifier les stocks de base.
*   **Gestionnaire de Stock (Stock Manager)** : Son rôle est d'assurer la logistique. Il passe les commandes aux fournisseurs (Bons de Commande), gère l'inventaire physique, ajoute de nouveaux produits et suit les équipements de l'établissement. Il n'a pas accès à la caisse de vente ni aux données financières sensibles.

---

## 2. Routine Quotidienne : Caisse et Ventes

C'est la procédure la plus importante pour les caissiers. Elle garantit qu'aucun centime ne se perd entre le matin et le soir.

### Étape 1 : Ouverture de la Caisse
Avant de pouvoir faire la moindre vente, vous devez déclarer que la caisse est ouverte.
1. Allez dans le menu **Ventes** > **Caisse Enregistreuse**.
2. Cliquez sur **Ouvrir la Caisse**.
3. Saisissez le **Solde initial d'ouverture** (le montant des pièces et billets déjà présents dans le tiroir-caisse pour rendre la monnaie).
4. Sélectionnez le **Compte source** (par exemple, "Coffre-fort" ou "Caisse Principale") d'où provient cet argent physique.
5. Cliquez sur **Ouvrir la session**.

> [!NOTE]
> **Exemple simple :** C'est le matin, vous commencez votre journée. Vous comptez le fond de caisse laissé la veille : il y a exactement 15 000 DZD en petites coupures. Vous tapez "15000", vous validez. Le système sait maintenant que le tiroir-caisse contient cette somme au départ.

---

### Étape 2 : Enregistrer des Ventes (POS)
Une fois la caisse ouverte, dirigez-vous vers le **Point de Vente** (ou *POS*).

1. **Sélectionner les produits** : Vous pouvez cliquer sur les images des produits, utiliser les filtres par catégorie, ou simplement saisir le nom ou scanner le code-barres dans la barre de recherche.
2. **Choisir le Client** : 
   * Par défaut, la vente se fait pour un **"Client de passage"** (anonyme).
   * Si c'est un client régulier, cliquez sur **Changer** pour le rechercher par son nom ou son numéro de téléphone. Si c'est un nouveau client, vous pouvez l'ajouter rapidement avec le bouton **Nouveau**.
3. **Appliquer une remise ou utiliser des points** :
   * Vous pouvez appliquer une réduction en pourcentage (`%`) sur le total.
   * Si le client est enregistré et possède des points de fidélité, vous pouvez saisir le nombre de points à échanger pour déduire une somme de sa facture.
4. **Choisir le Type de Document** :
   * **Bon de Vente (BV)** : Un ticket de vente direct classique.
   * **Bon de Livraison (BL)** : Un document de livraison professionnelle (souvent utilisé pour les professionnels de santé qui règlent plus tard).
5. **Enregistrer le paiement** :
   * Saisissez le montant réellement donné par le client dans **Montant payé**.
   * Si le client paie la totalité, le système affiche la monnaie à lui rendre dans **Monnaie**.
   * Si le client paie moins que le total (crédit) : le reste sera automatiquement ajouté à sa fiche client en tant que **Dette**. *(Attention : la dette n'est autorisée que pour les clients enregistrés, pas pour les clients de passage).*
6. **Valider** : Cliquez sur **Compléter la vente**. Le système vous demandera alors si vous souhaitez imprimer le reçu.

---

### Étape 3 : Fermeture et Clôture de Caisse
À la fin de votre journée de travail, vous devez fermer la session de caisse pour vérifier les comptes.
1. Allez dans **Caisse Enregistreuse**.
2. Cliquez sur le bouton rouge **Fermer la session**.
3. Le système affiche le montant **Attendu** (Calculé par l'ordinateur : *Fond de caisse du matin + toutes les ventes de la journée payées en espèces*).
4. Comptez physiquement l'argent (billets et pièces) présent dans votre tiroir-caisse et saisissez ce chiffre dans **Montant réel compté**.
5. Sélectionnez le **Compte de destination** (par exemple, "Banque" ou "Coffre-fort général") où vous allez déposer cet argent de la journée.
6. Saisissez le **Montant à transférer** vers ce compte (souvent la totalité ou une grande partie pour ne laisser que le fond de caisse pour le lendemain).
7. Cliquez sur **Confirmer la clôture**.

> [!IMPORTANT]
> **Qu'est-ce que l'écart (Discrepancy) ?**
> Si l'ordinateur calcule que vous devez avoir 52 300 DZD en caisse, mais qu'après avoir compté vos billets vous ne trouvez que 52 200 DZD, il y a un **écart de -100 DZD**. Le système l'enregistre automatiquement dans l'historique des sessions. Cela permet de repérer des erreurs récurrentes de rendu de monnaie.
> 
> *Exemple :* Si vous avez donné par erreur un billet de 200 DZD au lieu de 100 DZD à un client, cet écart apparaîtra ici sous forme de perte.

---

## 3. Gestion du Stock et des Approvisionnements

Le stock est le cœur de votre dépôt dentaire. Une bonne gestion évite les ruptures de stock et les produits périmés.

### Le Catalogue (Catégories et Marques)
Avant d'ajouter des produits, vous devez structurer votre stock. Allez dans **Stock & Opérations** > **Catalogue**.
*   **Catégories** : Permettent de regrouper les articles (ex: *Consommables*, *Instruments*, *Produits d'obturation*, *Prothèse*).
*   **Marques** : Permettent de filtrer par fabricant (ex: *3M*, *Dentsply*, *Coltene*).

### Ajouter un Produit
Dans **Stock & Opérations** > **Gestion de Stock**, cliquez sur **Ajouter un produit**.
*   Renseignez le nom, la catégorie, la marque et le code-barres.
*   Configurez le **Stock minimum** : C'est le seuil d'alerte. Si le stock descend sous cette limite, le système affichera un message d'alerte rouge sur le tableau de bord pour vous inviter à recommander.
*   Saisissez le **Prix d'achat** et le **Prix de vente**.

---

### Comprendre les Bons de Commande (PO)
Lorsque vous devez acheter des produits chez un fournisseur, vous créez un **Bon de Commande (Purchase Order ou PO)**.
1. Allez dans **Stock & Opérations** > **Bons de Commande** > **Nouveau Bon de Commande**.
2. Sélectionnez le fournisseur et ajoutez les articles avec leurs quantités et prix d'achat convenus.
3. Enregistrez le document. L'état du bon de commande est alors **En attente** (Pending).
4. Lorsque le fournisseur vous livre la marchandise physique : ouvrez ce bon de commande et changez le statut en **Reçu** (Received).
5. **Le système mettra alors automatiquement à jour vos quantités en stock** sans que vous ayez à modifier chaque produit manuellement !

---

### Le Concept Complexe : Différence entre BA (Bon d'Achat) et BL (Bon de Livraison)

Dans le domaine dentaire et le commerce en général, la traçabilité des lots d'achat est essentielle. Le système suit chaque lot de produit sous deux statuts différents lors de leur entrée en stock :

1.  **BA (Bon d'Achat / Facturé)** : Produits achetés de manière officielle, accompagnés d'une facture fiscale avec TVA.
2.  **BL (Bon de Livraison / Non-facturé)** : Produits livrés avec un simple bon de livraison, souvent dans le cadre d'un échange direct, d'un dépôt-vente, ou de transactions non facturées de manière fiscale immédiate.

#### Pourquoi le système sépare-t-il ces deux stocks ?
Parce que fiscalement et commercialement, vous ne devez pas mélanger ce que vous vendez officiellement avec facture (qui doit provenir d'achats facturés BA) et ce que vous vendez de manière simplifiée (provenant de BL).

#### Comment cela fonctionne-t-il concrètement lors de la vente ?
Le système utilise la méthode du **FIFO (Premier entré, premier sorti)**. C'est-à-dire qu'il vend toujours en priorité les articles reçus en premier.

> [!WARNING]
> **L'alerte BL au Point de Vente (POS) :**
> Si un client veut vous acheter 5 unités d'un produit, et que l'ordinateur remarque que pour atteindre ces 5 unités, il doit piocher dans un lot qui a été acheté sous forme de **BL** (et non de BA officiel), il affiche une alerte :
> 
> *« La quantité demandée nécessite l'utilisation de stock provenant d'un Bon de Livraison (BL)... »*
> 
> **Exemple concret pour comprendre :**
> Vous avez en stock 10 seringues de composite dentaire :
> *   Les 4 premières seringues ont été achetées avec une facture officielle (**BA**).
> *   Les 6 seringues suivantes ont été achetées via un bon de livraison simple (**BL**).
> 
> Si un dentiste vient acheter **6 seringues** :
> 1. Le système prend d'abord les 4 seringues officielles (**BA**).
> 2. Il lui manque 2 seringues. Il doit donc aller les chercher dans le lot **BL**.
> 3. L'ordinateur affiche alors l'alerte jaune.
> 
> **Que devez-vous faire en tant que caissier ?**
> *   **Option A** : Si le client exige une facture officielle complète pour son achat, vous ne devez pas lui vendre des produits issus du stock BL. Vous devez annuler ou ajuster sa commande pour ne pas dépasser la quantité disponible en BA (ici 4 seringues).
> *   **Option B** : Si le client accepte d'avoir un simple **Bon de Livraison (BL)** pour sa commande, vous validez l'alerte, et dans la section **Type de Document** du POS (à droite), vous cochez **Bon de Livraison (BL)** au lieu de Bon de Vente (BV).

---

### Ajuster le Stock Manuellement
Parfois, des flacons se cassent, des produits périment ou vous constatez une erreur d'inventaire. Vous devez alors corriger le stock sans faire de vente ou de commande :
1. Allez dans **Gestion de Stock**.
2. Sur la ligne du produit concerné, cliquez sur **Ajuster le stock** (ou *Adjust Stock*).
3. Choisissez l'action : **Ajouter (+)** ou **Soustraire (-)**.
4. Saisissez la quantité et le **motif** (ex: "Produit périmé jeté", "Erreur inventaire").
5. Validez. La modification est enregistrée dans l'historique du produit avec le nom de l'utilisateur qui a fait l'action.

---

## 4. Gestion des Clients, Fournisseurs et Dettes

### Gestion des Clients & Fidélité
Le système permet de mémoriser les coordonnées de vos clients (dentistes, cliniques, laboratoires). 
*   Chaque achat accumule des **Points de fidélité** (Loyalty Points) selon les règles de votre commerce. Ces points peuvent être convertis en réduction lors d'une future vente au POS.
*   Il conserve aussi l'historique complet de leurs achats.

### Régler une Dette Client (Créances)
Lorsqu'un client a acheté à crédit (dette), son solde devient négatif (ex: -25 000 DZD). Pour enregistrer son remboursement :
1. Allez dans **Contacts** > **Clients**.
2. Recherchez le client. Son solde de dette apparaît dans la colonne **Dette en attente** (Outstanding Balance).
3. Cliquez sur l'icône **Régler la dette** (Settle Debt).
4. Saisissez le **Montant du paiement** apporté par le client.
5. Sélectionnez le mode de paiement (Espèces, Chèque, Virement) et le **Compte de trésorerie** où vous déposez cet argent (ex: Caisse principale ou compte CCP).
6. Cliquez sur **Enregistrer le paiement**. La dette du client diminue immédiatement.

---

### Gestion des Fournisseurs & Paiements
C'est le même principe que pour les clients, mais dans le sens inverse (l'argent que vous devez à vos fournisseurs suite à vos commandes de stock en attente de paiement).
*   Dans **Contacts** > **Fournisseurs**, vous pouvez voir votre **Dette actuelle** envers chaque fournisseur.
*   Cliquez sur **Régler la dette** (Settle Debt) pour déclarer que vous avez envoyé un chèque ou effectué un virement à ce fournisseur. Cela réduira la dette dans votre bilan financier.

---

## 5. Trésorerie et Bilan Financier

Cette section concerne principalement les propriétaires de l'établissement et les administrateurs.

### La Trésorerie (Comptes de Banque et Coffre-fort)
Dans **Finances** > **Trésorerie** (Treasury), vous gérez vos différents "portefeuilles" virtuels. Vous pouvez créer autant de comptes que nécessaire, par exemple :
*   *Coffre-fort Physique* (pour stocker les grosses coupures).
*   *Caisse Boutique* (la caisse d'encaissement direct).
*   *Compte Bancaire BDL / BEA / CCP*.

Vous pouvez effectuer des **Transferts Internes** entre ces comptes (par exemple, retirer 100 000 DZD du compte bancaire pour les mettre dans le coffre-fort physique).

---

### Le Bilan Financier (Comprendre sa richesse nette)
Le **Bilan Financier** (Financial Balance) résume la structure financière et la santé de votre commerce. Il se base sur une règle mathématique simple :

$$\text{Capital Net (Votre richesse réelle)} = \text{Actif (Ce que vous possédez)} - \text{Passif (Ce que vous devez)}$$

#### 1. L'Actif (Ce que vous possédez)
*   **Disponibilités de Trésorerie** : Tout l'argent disponible sur vos comptes bancaires et dans vos caisses.
*   **Valeur du Stock** : Le coût total d'achat de toutes les marchandises qui dorment sur vos étagères.
*   **Créances Clients** : L'argent que les clients vous doivent (les dettes qu'ils n'ont pas encore remboursées).
*   **Immobilisations (Équipements)** : La valeur d'achat de vos ordinateurs, fauteuils dentaires de démonstration, compresseurs, etc.

#### 2. Le Passif (Ce que vous devez)
*   **Dettes Fournisseurs** : Tout l'argent que vous devez encore payer à vos fournisseurs pour les marchandises qu'ils vous ont livrées.

#### Exemple simple :
Imaginons la situation suivante dans votre dépôt dentaire :
*   Vous avez **100 000 DZD** sur votre compte CCP (Trésorerie).
*   Vos étagères contiennent pour **500 000 DZD** de produits dentaires (Stock).
*   Trois cliniques vous doivent au total **150 000 DZD** (Créances Clients).
*   Vos ordinateurs et vitrines de présentation valent **200 000 DZD** (Équipements).
*   **Total de votre Actif** = $100\ 000 + 500\ 000 + 150\ 000 + 200\ 000 = \mathbf{950\ 000\text{ DZD}}$.

Cependant, vous devez encore payer **250 000 DZD** à votre fournisseur de résines (Passif - Dette Fournisseur).

*   **Votre Capital Net réel** est de : $950\ 000 - 250\ 000 = \mathbf{700\ 000\text{ DZD}}$.
C'est le chiffre clé qui montre la véritable valeur financière de votre entreprise.

---

### Gestion de la Paie (Salaires)
Dans **Finances** > **Paie / Salaires** (Payroll) :
1. Créez d'abord vos employés dans l'onglet **Membres du personnel** en renseignant leur salaire de base mensuel.
2. Pour payer un employé, allez dans l'onglet **Historique des paiements** et cliquez sur **Nouveau paiement**.
3. Sélectionnez l'employé, la période (Mois/Année), le compte de trésorerie qui va payer (ex : Banque ou Caisse) et validez.
4. L'argent est automatiquement déduit du compte de trésorerie sélectionné et enregistré comme charge de personnel dans vos rapports.

---

## 6. Administration et Outils Avancés

### Les Devis (Factures Proforma)
Un dentiste vous demande un prix pour équiper son nouveau cabinet mais n'est pas encore prêt à acheter.
1. Allez dans **Ventes** > **Devis** > **Nouveau Devis** (ou directement depuis le POS en préparant le panier et en cliquant sur le raccourci Devis).
2. Ajoutez les articles et le client potentiel.
3. Imprimez ou exportez le devis en PDF pour le lui remettre.
4. Si le client revient deux semaines plus tard pour acheter : allez dans le Point de Vente (POS), cliquez sur **Charger Devis** (icône facture en bas à droite), sélectionnez son devis. **Le panier se remplit automatiquement avec les articles du devis !** Vous n'avez plus qu'à encaisser la vente.

### Historique des Ventes et Retours de Marchandise
Le module **Historique des Ventes** permet de consulter toutes les factures émises. C'est également ici que vous gérez les litiges :
*   **Annuler une vente** : Annule la transaction. L'argent est retiré virtuellement de vos comptes, et les produits retournent automatiquement dans le stock. Le solde du client est aussi recalculé.
*   **Retourner des articles (Retour partiel)** : Si un client a acheté 10 boîtes d'aiguilles mais souhaite en rendre 2 car elles ne correspondent pas à ses besoins. Vous pouvez sélectionner la vente, cliquer sur **Retourner des articles**, spécifier la quantité retournée (2) et valider. Le stock de ce produit augmentera de 2 et le compte du client sera crédité du montant équivalent.

---

## 7. Astuces pour Travailler plus Vite (Raccourcis et Bonnes Pratiques)

Pour utiliser DentalPOS de la manière la plus efficace possible, appliquez ces conseils au quotidien :

1.  **Utilisez la recherche globale du haut** : Dans la barre supérieure de l'écran, vous avez un champ de recherche permanent. Vous pouvez y saisir un nom de produit ou scanner un code-barres depuis n'importe quel écran du logiciel pour voir instantanément sa fiche de stock et son prix.
2.  **Mode Sombre** : Si vous travaillez tard le soir ou sous un éclairage tamisé, cliquez sur l'icône de lune en haut à droite. Cela réduira la fatigue oculaire.
3.  **Mode Plein Écran** : Sur les petits écrans d'ordinateur de caisse, cliquez sur l'icône d'agrandissement en haut à droite pour masquer les barres du navigateur internet et maximiser l'espace de vente.
4.  **Mise en attente des ventes (Hold Sale)** : Si vous êtes en train d'enregistrer les articles d'un client au POS et que ce dernier réalise qu'il a oublié son portefeuille dans sa voiture, ne supprimez pas son panier ! Cliquez sur le bouton **Pause** (Mettre en attente) sous le panier. Vous pouvez alors servir le client suivant. Quand le premier client revient, cliquez sur le bouton **Récentes** (icône ticket) pour récupérer son panier en un clic.
5.  **Gardez toujours l'œil sur les alertes de stock** : Prenez l'habitude de vérifier les notifications (la cloche en haut à droite) et les alertes du Tableau de bord chaque matin. Le système y liste les produits qui approchent de leur date de péremption ou dont les quantités sont trop faibles.
