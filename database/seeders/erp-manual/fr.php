<?php

/*
 * Manuel de l'application ERP, français. Mêmes clés que nl.php (voir ErpManualSeeder).
 */

return [
    'category' => [
        'name' => 'Manuel de l\'application ERP',
        'description' => 'Comment fonctionnent la plateforme et chaque module : de la connexion aux factures, pour les employés et les gestionnaires.',
    ],

    'articles' => [
        'getting-started' => [
            'title' => 'Premiers pas : connexion, menu et langue',
            'body' => <<<'MD'
## À quoi sert la plateforme ?

La plateforme ERP rassemble tout ce qui concerne l'entreprise : projets, planning, prestations, congés, matériel, magasin, sécurité, finances et plus encore. Chaque partie est un **module** ; l'administrateur décide quels modules sont activés.

## Se connecter

- Vous recevez un **mail de bienvenue** avec un lien pour choisir vous-même votre mot de passe. Le lien fonctionne 7 jours et une seule fois.
- Ensuite, vous vous connectez toujours avec votre **adresse e-mail et votre mot de passe**.
- Mot de passe oublié ? Cliquez sur **« Mot de passe oublié ? »** sur la page de connexion : vous recevez un mail avec un nouveau lien.

## Deux côtés : employés et gestion

- **Côté employés** (l'adresse sans /admin) : votre planning, votre fiche de prestations, vos congés, les toolbox meetings, signaler un incident, la boîte à idées, la base de connaissances et les modules pour lesquels vous avez des droits.
- **Côté gestion** (/admin) : uniquement pour les administrateurs. On y trouve aussi les paramètres, les rôles, les utilisateurs, les modèles d'e-mail et le portail client.
- Les administrateurs passent de l'un à l'autre via le **menu utilisateur** en haut à droite.

## L'écran

- **Menu de gauche** : par groupe (Personnel, Projets, Planning, Sécurité, …). Vous ne voyez que ce que vous pouvez utiliser.
- **Barre du haut** : recherche, la **boîte à idées**, éventuellement une **annonce** de la direction, la **cloche** avec vos notifications et votre **menu utilisateur**.
- **Tableau de bord** : tuiles et listes utiles pour vous (contrôles qui expirent, stock bas, tâches ouvertes, …).

## Langue et profil

- Choisissez **néerlandais, français ou anglais** avec le choix de langue à côté du menu utilisateur. Ce choix vaut aussi pour les mails que vous recevez et pour l'application mobile.
- Via le menu utilisateur → **Profil**, vous modifiez votre nom, votre adresse e-mail ou votre mot de passe.

## Notifications

La cloche en haut à droite affiche les notifications : un changement dans votre planning, une demande de congé approuvée, un nouveau toolbox meeting, … Cliquez sur une notification pour ouvrir directement la bonne page.

## Sur votre téléphone

Sur votre téléphone, utilisez de préférence l'**application mobile PI ERP** (voir l'article « Application mobile »). Sur un téléphone Android, la plateforme propose de télécharger l'application après la connexion.
MD,
        ],

        'roles-users' => [
            'title' => 'Utilisateurs, rôles, droits et équipes',
            'body' => <<<'MD'
## À quoi ça sert ?

Ce que quelqu'un voit et peut faire dépend de son **rôle** et de son **équipe**.

## Où ?

- **Gestion → Organisation → Utilisateurs** : les comptes.
- **Gestion → Système → Rôles** : les rôles et leurs droits.
- **Organisation → Équipes** : les équipes et leurs membres.

## Rôles et droits

- Un rôle est un ensemble de **droits par partie**, p. ex. *Projets : voir, créer, modifier* ou *Congés : approuver*.
- Un **rôle administrateur** a automatiquement tous les droits (sauf quelques droits confidentiels, comme les mots de passe des routeurs 4G, qu'il faut cocher explicitement).
- Sur un utilisateur, vous pouvez encore **adapter les droits de son rôle pour lui seul**.
- Les droits d'un module désactivé n'ont aucun effet.

## Équipes

- Les employés sont répartis en **équipes** avec un chef d'équipe.
- Un employé **sans équipe** est un *invité* : il n'a aucun droit, quel que soit son rôle.
- Les équipes déterminent aussi ce que vous voyez : vous voyez un projet si vous en êtes le chef de projet ou si votre équipe y est affectée ; vous voyez aussi le planning de vos collègues d'équipe.

## Créer un compte

- Via **Personnel → Registre du personnel** (ajouter un employé crée aussi son accès) ou via **Organisation → Utilisateurs**.
- Laissez le mot de passe vide et gardez **« Envoyer le mail de bienvenue »** activé : l'employé choisit lui-même son mot de passe.
- **« Envoyer le mail de bienvenue »** dans la liste renvoie le lien.

## Conseils

- Donnez les droits de préférence via les rôles, pas par personne : cela reste clair.
- Quelqu'un quitte l'entreprise ? Mettez son compte sur **inactif** ; ses données sont conservées.
MD,
        ],

        'settings' => [
            'title' => 'Paramètres, modules et modèles d\'e-mail',
            'body' => <<<'MD'
## Où ?

**Gestion → Système → Paramètres** et **Gestion → Système → Modèles d'e-mail**. Uniquement pour les administrateurs.

## Paramètres

Les paramètres sont répartis en onglets :

- **Général** : nom de la plateforme, nom de l'entreprise, rappels (combien de jours à l'avance vous êtes averti des échéances).
- **Modules** : activez ou désactivez chaque module. Un module désactivé disparaît du menu pour tout le monde ; les données sont conservées.
- **E-mail** : le serveur de messagerie de la plateforme. **« Envoyer un e-mail de test »** vérifie la configuration.
- **Apparence** : logo, favicon et couleur.
- **Application mobile** : l'APK, la suggestion sur les téléphones et la déclaration de confidentialité.
- Et par module si nécessaire : **Finances**, **Prestations**, **Batteries**, **Planning**, …

## Modèles d'e-mail

- Chaque e-mail envoyé par la plateforme (bienvenue, facture, rappel, planning, fiche de prestations, demande de GPW, …) a un **modèle par langue** (NL, FR, EN).
- Adaptez l'objet et le texte ; les **variables** entre accolades, p. ex. `{name}` ou `{project}`, sont remplies à l'envoi. La liste figure sur chaque modèle.
- **Aperçu** montre le mail tel qu'il sera envoyé ; **Texte standard** remet le texte d'origine.
- Le mail est toujours envoyé dans la **langue du destinataire**.

## Conseils

- N'activez un module que lorsque ses données de base sont prêtes (p. ex. catégories, sites).
- Après une modification du logo ou de la couleur, la page se recharge d'elle-même.
MD,
        ],

        'announcements' => [
            'title' => 'Annonces',
            'body' => <<<'MD'
## À quoi ça sert ?

Un court message pour **tous les employés**, dans la barre du haut à gauche de la boîte à idées. Par exemple : « Le bureau est fermé vendredi ».

## Où ?

**Organisation → Annonce** (côté employés et côté gestion).

## Comment ça marche

1. Activez **« Afficher l'annonce »**.
2. Choisissez une **couleur** : bleu (info), vert, orange (attention) ou rouge (urgent).
3. Saisissez le message, 200 caractères maximum, en **néerlandais** (obligatoire) et éventuellement en **français** et **anglais**. Sans traduction, les employés francophones et anglophones voient le texte néerlandais.
4. Cliquez sur **Enregistrer**.

Ceux qui peuvent modifier l'annonce voient un **crayon** dans la barre pour la modifier directement. En bas de la page figure qui l'a modifiée en dernier.

## Droits

Les administrateurs et les employés ayant le droit **Annonces → Modifier**. Le module **Annonces** doit être activé.
MD,
        ],

        'locations' => [
            'title' => 'Sites de l\'entreprise',
            'body' => <<<'MD'
## À quoi ça sert ?

Les **implantations** de l'entreprise (p. ex. siège, magasin) avec leur adresse. Les équipes, véhicules, matériel et stock sont liés à un site.

## Où ?

**Organisation → Sites**.

## Comment ça marche

- Créez un site avec un nom et une adresse.
- La page de détail montre les **équipes, véhicules, matériel et stock** de ce site.
- Le stock du magasin est suivi **par site** ; sans site, il n'y a donc pas de stock.

## Droits

Voir, créer, modifier et supprimer des sites sont des droits distincts.
MD,
        ],

        'teams' => [
            'title' => 'Équipes',
            'body' => <<<'MD'
## À quoi ça sert ?

Répartir les employés en **équipes** avec un **chef d'équipe**, liées à un site.

## Où ?

**Organisation → Équipes**.

## Comment ça marche

- Créez une équipe, choisissez une couleur, un chef d'équipe et un site, puis ajoutez les membres.
- Les équipes sont utilisées dans toute la plateforme :
  - **Projets** : affecter une équipe à un projet donne accès à ce projet à ses membres.
  - **Planning** : vous voyez le planning de vos collègues d'équipe ; une période GPW est planifiée par équipe.
  - **Matériel** : matériel et véhicules par équipe, et le contrôle annuel par équipe.
  - **Magasin** : le stock du site de votre équipe.

## Important

Un employé sans équipe est un **invité** et n'a aucun droit. Ajoutez donc toujours les nouveaux employés à une équipe.
MD,
        ],

        'employees' => [
            'title' => 'Registre du personnel et mes données',
            'body' => <<<'MD'
## À quoi ça sert ?

Toutes les données du personnel au même endroit : données personnelles, emploi, contacts d'urgence, tailles de vêtements, permis de conduire, certificats et examen médical.

## Où ?

- **Personnel → Registre du personnel** : pour l'administration.
- **Personnel → Mes données** : chaque employé y voit ses propres données.
- **Personnel → Formations & examen médical** : qui a quelle formation et quand quelque chose expire.

## Ajouter un employé

1. Cliquez sur **Nouveau** dans le registre du personnel.
2. Complétez les onglets : données personnelles, emploi, contacts d'urgence, tailles de vêtements, permis de conduire.
3. Dans **Compte de connexion**, l'accès est créé et le **mail de bienvenue** part avec un lien pour choisir le mot de passe.

## Permis de conduire

Choisissez une ou plusieurs **catégories** (B, BE, C, …), le numéro et la date d'expiration, et ajoutez une copie (photo ou PDF recto verso).

## Certificats et examen médical

- Certificats (p. ex. VCA, BA4/BA5) avec **date d'expiration** et PDF. Le tableau de bord avertit à temps avant l'expiration.
- L'**examen médical annuel** est suivi par employé.
- Les employés peuvent soumettre eux-mêmes un certificat ; l'administration l'approuve dans **Certificats soumis**.

## Modifier ses données en tant qu'employé

Un employé demande une modification (p. ex. nouvelle adresse) via **Mes données**. L'administration la confirme dans **Données modifiées**.

## Confidentialité

Les données sensibles comme le numéro de registre national sont conservées **chiffrées** et visibles uniquement avec les droits adéquats.
MD,
        ],

        'leave' => [
            'title' => 'Congés, heures supplémentaires et absences',
            'body' => <<<'MD'
## À quoi ça sert ?

Demander et approuver des congés, prendre des heures supplémentaires en congé payé, signaler une maladie avec un certificat, et les jours fériés et jours de récupération.

## Où ?

- **Personnel → Mes congés** : vos demandes et votre solde.
- **Personnel → Mes absences** : vos maladies et autres absences.
- **Personnel → Demandes de congé** : approuver ou refuser (avec le droit).
- **Personnel → Calendrier des congés** : qui est absent quand.
- **Personnel → Absences**, **Jours fériés** et **Jours de récupération (IR)** : pour l'administration.

## Demander un congé

1. Allez dans **Mes congés** et cliquez sur **Demander un congé**.
2. Choisissez le **type** : congé ordinaire ou **heures supplémentaires en congé**.
3. Choisissez la période (éventuellement une demi-journée). Vous voyez directement combien de jours elle compte et votre solde ensuite ; les week-ends et jours fériés ne comptent pas.
4. Envoyez la demande. Les personnes qui peuvent l'approuver reçoivent une notification.

Une demande pas encore traitée peut être **retirée**.

## Heures supplémentaires en congé

Avec le module **Prestations**, vos heures supplémentaires sont suivies. La demande indique combien d'heures vous pouvez encore prendre. Ces demandes sont aussi approuvées par une personne ayant le droit.

## Approuver

Dans **Demandes de congé**, vous approuvez ou refusez une demande (avec une raison). L'employé reçoit une notification. Les jours approuvés sont **bloqués dans le planning** et remplis automatiquement dans la **fiche de prestations**.

## Signaler une maladie

- Via **Mes absences → Déclarer une maladie** (ou dans l'application mobile). L'administration est avertie immédiatement.
- Ajoutez le **certificat** en photo ou PDF, tout de suite ou plus tard.
- Dans **Absences**, l'administration voit les maladies encore **sans certificat**.

## Conseil

Les collègues ne voient dans le calendrier que « absent », jamais la raison d'une maladie.
MD,
        ],

        'timesheets' => [
            'title' => 'Prestations (fiche de prestations)',
            'body' => <<<'MD'
## À quoi ça sert ?

La **fiche de prestations** quotidienne : heures, kilomètres et projets par jour, avec un PDF hebdomadaire par e-mail.

## Où ?

- **Personnel → Fiche de prestations** : votre fiche (aussi dans l'application mobile).
- **Personnel → Aperçu des prestations** et **Prestations par mois** : pour ceux qui peuvent voir toutes les fiches.

## Remplir une journée

1. Ouvrez le jour dans votre fiche.
2. Par ligne : **départ du magasin**, **travail sur le site COW**, éventuellement des **sites supplémentaires**, et **retour au magasin / à la maison**, avec les heures et les relevés kilométriques.
3. Choisissez le projet dans **Imputation** et complétez la **description** (code COW ou chantier).
4. **« Remplir depuis le planning »** reprend les projets de votre planning de ce jour.
5. La **pause de midi** est déduite des heures prestées.

Seuls le départ, le site et la fin de journée comptent pour les heures ; les sites supplémentaires intermédiaires non.

## Rempli automatiquement

Les congés, maladies, formations et jours fériés sont **automatiquement** repris dans la fiche.

## La semaine

- Dans **Paramètres de la semaine** figurent les heures à prester et les kilomètres et heures supplémentaires **reportés** de la semaine précédente.
- Le **PDF de la semaine** est envoyé automatiquement chaque semaine à l'administration et à l'employé lui-même. Avec le droit, vous pouvez aussi envoyer une semaine manuellement.

## Heures supplémentaires

Ce que vous prestez au-delà des heures prévues s'ajoute à votre **solde d'heures supplémentaires**, que vous pouvez prendre en congé (voir « Congés, heures supplémentaires et absences »).
MD,
        ],

        'planning' => [
            'title' => 'Planning et formations',
            'body' => <<<'MD'
## À quoi ça sert ?

Un calendrier de planning par employé : qui travaille quand sur quel projet, au magasin ou à autre chose.

## Où ?

- **Planning → Planning** : le calendrier (semaine ou mois, en calendrier ou en liste).
- **Planning → Formations** : planifier des formations.

## Que voyez-vous ?

- Toujours **votre planning**, celui de vos **collègues d'équipe** et des collègues qui partagent leur planning avec vous.
- Les chefs de projet voient aussi le planning sur leurs projets.
- Les planificateurs et administrateurs voient tout le monde.

## Planifier

1. Cliquez sur **Planifier** (ou sur un jour chez un employé).
2. Choisissez un ou plusieurs **employés**, la date et éventuellement **jusqu'au** (un élément par jour ; les week-ends seulement si vous les cochez).
3. Choisissez un **créneau** ou des heures libres.
4. Choisissez le **type** : un **projet** (uniquement les projets ouverts : à planifier, planifié, à exécuter ou en cours), du **travail au magasin** (description et tâches) ou **autre chose**.
5. Cochez éventuellement **« Notifier aussi par e-mail »**.

Les jours de **congé, d'absence ou de formation** sont bloqués ; les créneaux qui se chevauchent sont ignorés et signalés.

## Notifications

Chaque changement de votre planning vous est notifié dans la plateforme et sur votre téléphone (application mobile), et au choix par e-mail.

## Droits

- **Planifier tout le monde** (projets et magasin), **planifier d'autres éléments**, **voir le planning de chacun** et **planifier des formations** sont des droits distincts.
- Les chefs de projet planifient leurs propres projets.
- Celui qui a demandé un **GPW** planifie, après approbation, une équipe dans la période approuvée (voir « Demandes de GPW »).
MD,
        ],

        'fleet' => [
            'title' => 'Flotte',
            'body' => <<<'MD'
## À quoi ça sert ?

Un aperçu de tous les **véhicules** avec leurs données et contrôles techniques.

## Où ?

**Flotte → Véhicules**.

## Comment ça marche

- Enregistrez par véhicule la **plaque, la marque, le type, le numéro de châssis, l'année, le carburant, le kilométrage** et les **dates de contrôle**, éventuellement avec une photo.
- Liez le véhicule à un **site**, une **équipe** et un **conducteur**.
- Le **tableau de bord** avertit à l'avance quand un contrôle arrive à échéance (le nombre de jours se règle dans Paramètres → Général).

## Droits

Voir, créer, modifier et supprimer sont des droits distincts.
MD,
        ],

        'assets' => [
            'title' => 'Matériel et contrôle annuel',
            'body' => <<<'MD'
## À quoi ça sert ?

Suivre les équipements électriques et autre matériel, avec les contrôles, un journal des dégâts et réparations, et un contrôle annuel par équipe.

## Où ?

- **Matériel → Matériel** : tout le matériel.
- **Matériel → Dégâts & réparations** : le journal.
- **Matériel → Contrôle annuel** : les listes de contrôle par équipe.
- **Matériel → Nouveau matériel à confirmer** : le matériel ajouté par les équipes.
- **Matériel → Catégories**.

## Matériel

Chaque pièce a une catégorie, un numéro de série, un statut, un emplacement (équipe, véhicule, personne ou site) et une **date de contrôle**. Le tableau de bord montre les contrôles qui expirent bientôt. Les dégâts et réparations sont notés dans le **journal**.

## Le contrôle annuel

1. Un administrateur **publie** le contrôle annuel pour une ou plusieurs équipes. Chaque équipe reçoit la liste de son matériel (y compris celui de ses véhicules) et une notification.
2. L'équipe complète le **résultat** par pièce, avec une remarque, sur l'ordinateur ou dans l'**application mobile** (onglet Contrôles).
3. Le matériel que l'équipe possède mais qui n'est pas sur la liste s'ajoute avec **« Ajouter du matériel »**. Un administrateur le confirme dans *Nouveau matériel à confirmer*.
4. **Ajouter le nouveau matériel** complète une liste ouverte ; **Fermer** ferme la liste (les résultats sont conservés).

## Droits

Voir, créer, modifier, supprimer, et publier ou fermer le contrôle annuel sont des droits distincts.
MD,
        ],

        'orders' => [
            'title' => 'Numéros de commande',
            'body' => <<<'MD'
## À quoi ça sert ?

Des **numéros de commande** internes pour les achats, comme `466/1026/JL`, pour que chaque numéro ne soit utilisé qu'une fois.

## Où ?

**Achats → Numéros de commande**.

## Comment ça marche

- Cliquez sur **« +1 Nouveau numéro de commande »** pour prendre le numéro libre suivant. Il est directement à votre nom (avec vos initiales).
- Indiquez à quoi il sert : **fournisseur**, **projet** et description.
- La liste permet de retrouver quel numéro a été utilisé par qui et pour quoi.
MD,
        ],

        'stock' => [
            'title' => 'Magasin et stock',
            'body' => <<<'MD'
## À quoi ça sert ?

Les **articles** du magasin avec leur stock **par site**, des étiquettes QR, les fabricants, grossistes, catégories et unités.

## Où ?

**Magasin → Articles**, avec les données de base : **Catégories d'articles**, **Unités**, **Fabricants**, **Grossistes** et leurs **Personnes de contact**.

## Suivre le stock

Sur un article, choisissez le site, puis :

- **Ajouter** : du stock arrive.
- **Prendre** : vous prenez quelque chose au magasin (éventuellement avec une note, p. ex. le chantier).
- **Compter** : indiquez ce qui est réellement présent ; la différence est enregistrée. C'est ainsi que vous faites un **inventaire**.

Chaque modification figure dans l'**historique**. Les articles sous le stock minimum apparaissent dans **Stock bas** sur le tableau de bord.

## Étiquettes QR

Imprimez une **étiquette QR** par article ou plusieurs à la fois. En scannant l'étiquette, on arrive directement sur l'article.

## Prix et images

- Chez un grossiste avec liaison de prix, **« Récupérer le prix chez le grossiste »** met à jour le prix (une référence du grossiste ou du fabricant est nécessaire).
- Ajoutez une photo du produit avec **« Image depuis une adresse web »**.
- Les prix spéciaux des grossistes ne sont visibles qu'avec le droit **Prix (prix spéciaux des grossistes) → Voir**.
MD,
        ],

        'it' => [
            'title' => 'Gestion IT',
            'body' => <<<'MD'
## À quoi ça sert ?

Tous les moyens IT, comme dans Snipe-IT : **hardware, licences, accessoires, consommables et composants**, avec prêts et retours, audits et maintenance.

## Où ?

- **IT → Hardware, Licences, Accessoires, Consommables, Composants, Maintenance**.
- Données de base : **Modèles, Catégories IT, Fabricants IT, Fournisseurs IT, Statuts**.
- **IT → Mon IT** : chaque employé voit ce qui lui est prêté.

## Comment ça marche

- **Prêter** à un utilisateur, un site ou un autre appareil, éventuellement avec une date de *retour prévu*. **Reprendre** quand il revient.
- Les **licences** ont des places (seats) à attribuer ; vous voyez combien sont libres et quelles licences expirent bientôt.
- Les **composants** s'installent dans un appareil ; les **consommables** se distribuent.
- Un **audit** confirme qu'un appareil a été vu et planifie le suivant.
- Dans **Maintenance**, notez réparations et mises à niveau.

## Droits

Par type (hardware, licences, autres éléments), il existe des droits pour voir, créer, modifier, supprimer, prêter et auditer. Les clés de produit des licences ne sont visibles qu'avec le droit **Voir les clés de produit**.
MD,
        ],

        'work-sites' => [
            'title' => 'Sites (codes COW)',
            'body' => <<<'MD'
## À quoi ça sert ?

Les sites du client, chacun avec un **code COW** (p. ex. `02GAM`), une adresse, un type de bâtiment, une zone, une personne de contact, des photos et des remarques.

## Où ?

**Sites → Sites**, **Zones** et **Personnes de contact**.

## Sites

- Recherchez par code COW, rue ou commune ; ouvrez l'adresse dans **Waze** ou **Google Maps**.
- Le **type de bâtiment** se modifie avec **« Changer le type de bâtiment »** : chaque changement est enregistré avec date et raison dans le **journal**.
- Ajoutez des **photos** et des **remarques** (remarques avec le droit adéquat).

## Zones et personnes de contact

- Une **zone** regroupe des sites et a **une ou plusieurs personnes de contact**.
- Un site peut avoir sa propre personne de contact. Sinon, ce sont les personnes de contact de sa zone qui s'appliquent.
- Une personne de contact a une **langue** (NL, FR ou EN) : les mails comme une demande de GPW sont envoyés dans cette langue.

## Où est-ce utilisé ?

Les projets reçoivent leur code COW du site (p. ex. `604-001-02GAM`). Les bornes, routeurs, interventions et demandes de GPW utilisent les mêmes sites et personnes de contact.
MD,
        ],

        'projects' => [
            'title' => 'Projets',
            'body' => <<<'MD'
## À quoi ça sert ?

Tous les projets par **catégorie** (600, 604, 605, 606, 608, 610, …) avec leurs numéros PO, fichiers, photos, plans, schémas, liste de pièces, offres et factures.

## Où ?

**Projets → Projets** ; les catégories dans **Projets → Catégories de projets** (gestion).

## Numéro de projet

- Le numéro suit la catégorie : catégorie, numéro d'ordre et code COW, p. ex. `604-001-02GAM` ; numéroté par an comme `605-26001-02GAM` ; particulier sans code COW, p. ex. `606-001`.
- Laissez le numéro vide pour le **numéro libre suivant**. Sur une catégorie, l'administrateur peut fixer le **numéro suivant** ; sur un projet existant, le numéro peut être corrigé.

## Statut

Offre → En attente du PO → À planifier → Planifié → À exécuter → En cours → Exécuté → À facturer → Facturé → Clôturé. Changez le statut avec **« Changer le statut »**. Un projet **clôturé** est archivé avec un ZIP de tous les fichiers.

## Sur un projet

- **Numéros PO** du client (10 chiffres, plusieurs possibles).
- Onglets **documents, photos, plans, schémas, fichiers supplémentaires, pièces, offres et factures**. Les fichiers ont des versions.
- Les **chefs de projet** et les **équipes affectées** déterminent qui voit le projet.
- Pour 610–650, une **barre d'avancement** montre les étapes (demande, visite, offre, PO, … jusqu'à la remise) ; cochez les étapes ou elles se cochent automatiquement.
- **Copier** démarre un nouveau projet à partir de celui-ci.
- Pour 608, choisissez l'**emplacement** : un code COW ou un **client tiers** avec sa propre adresse d'installation.
- Depuis un projet, demandez un **GPW** (voir « Demandes de GPW »).

## Droits

Droits distincts pour voir, créer, modifier, supprimer, fichiers, pièces, PDF, copier et demander un GPW. Les offres et factures ne sont visibles qu'avec les droits financiers du projet ou en tant que chef de projet.
MD,
        ],

        'gpw' => [
            'title' => 'Demandes de GPW',
            'body' => <<<'MD'
## À quoi ça sert ?

Demander au client l'autorisation de travailler sur un site pendant une **période** donnée, puis planifier une équipe dans cette période après approbation.

## Demander un GPW

1. Ouvrez un projet avec le statut **À exécuter**.
2. Cliquez sur **« Demander un GPW »**. Vous voyez à qui la demande sera envoyée.
3. Choisissez la **période** (du – jusqu'au) et ajoutez éventuellement des **remarques** (accès, clés, coupures).
4. Cliquez sur **Envoyer la demande**.

La demande est envoyée par e-mail à la ou aux **personnes de contact du site COW** (ou de la zone), chacune **dans sa langue**, avec vous en copie. Si le site n'a pas de personne de contact avec adresse e-mail, complétez-la d'abord sur le site ou la zone.

## Le client approuve

- Via le **lien dans le mail** : le client voit la demande et clique sur **Approuver** ou **Refuser** (avec une raison).
- Ou dans le **Global overview for Client** (le portail client), onglet **Demandes de GPW**.

Après approbation, le projet passe à **À planifier** et vous recevez une notification dans la plateforme, sur votre téléphone et par e-mail.

## Planifier

Dans l'onglet **Demandes de GPW** du projet, cliquez sur **Planifier** pour la demande approuvée : choisissez une **équipe**, les jours (uniquement dans la période approuvée) et les heures. Tous les membres de l'équipe sont planifiés ; le projet passe à **Planifié**, et le client le voit avec la période dans son portail.

## Droits et modèles

- Demander : le droit **Projets → Demander des GPW**.
- Les mails (demande, approuvé, refusé) se modifient dans **Modèles d'e-mail**.
MD,
        ],

        'interventions' => [
            'title' => 'Interventions (600)',
            'body' => <<<'MD'
## À quoi ça sert ?

Les interventions sur un code COW en catégorie **600**, avec un **numéro WO**, des catégories d'intervention, un rapport de clôture et une revue par l'administrateur.

## Où ?

- **Interventions → Interventions** : la liste.
- **Interventions → Revues** et **Catégories d'intervention** : côté gestion.

## Créer une intervention

1. Créez un projet en catégorie **600** (ou cliquez sur *Nouvelle intervention*). Il démarre directement en **À exécuter**.
2. Complétez le **numéro WO** (unique), le site COW, les **catégories d'intervention** et la sous-catégorie avec phase et tags.
3. Les administrateurs et les personnes ayant le droit peuvent saisir un **numéro PO** dès la création, et à tout moment ensuite avec **« Ajouter un numéro PO »**.

## Clôturer

L'équipe clôture l'intervention avec un **rapport de clôture** : **matériel** utilisé et libéré, **positions fusible/disjoncteur**, tableaux, heures et collaborateurs. L'intervention passe alors en revue.

## Revue

L'administrateur **approuve** avec le numéro PO (un numéro PO du projet est proposé) ou **renvoie** avec une raison. Après approbation, le projet passe à **À facturer** et le **PDF** est créé.
MD,
        ],

        'batteries' => [
            'title' => 'Dossiers batteries (604)',
            'body' => <<<'MD'
## À quoi ça sert ?

Le dossier complet pour le remplacement de batteries sur les **projets 604** : input, bon de livraison, rapport de mesure, rapport d'inspection visuelle, plan, schéma unifilaire, bon d'envoi et demande de facturation, le tout en PDF.

## Où ?

- **Batteries → Dossiers batteries**, ou via le bouton **Dossier batteries** sur un projet 604.
- **Batteries → Livraisons de batteries** : les livraisons par projet.
- **Batteries → Modèles de batteries** et **Câbles Frötek** : les données de base.

## Comment ça marche

1. Ouvrez le dossier depuis le projet ; les données du projet et le numéro PO sont repris.
2. Complétez les onglets : **input** (type de batterie, quantités, câbles), les **mesures** et l'**inspection visuelle**.
3. Faites **signer** le dossier (sur l'ordinateur ou dans l'application mobile, au doigt).
4. Générez les **PDF** des différents documents.

## Livraisons

Dans **Livraisons de batteries**, suivez par projet la confirmation de commande, le type, la quantité et la date de livraison prévue, et cochez **livré**.

## Droits

Voir, créer, modifier, supprimer, l'onglet **input**, **prix & demande de facturation**, les PDF et la modification de dossiers terminés sont des droits distincts.
MD,
        ],

        'enersys' => [
            'title' => 'Batteries Enersys (605)',
            'body' => <<<'MD'
## À quoi ça sert ?

Les dossiers des **batteries Enersys** sur les **projets 605** : input, bon de livraison, rapport de mesure, inspection visuelle, document de transport et commande, avec signatures et PDF.

## Où ?

**Batteries → Batteries Enersys**, ou via le bouton sur un projet 605.

## Comment ça marche

Comme les dossiers batteries (604) : ouvrez le dossier depuis le projet, complétez l'input, les mesures et l'inspection, faites-le signer (aussi dans l'application mobile) et générez les PDF.

## Droits

Le même type de droits que pour les dossiers batteries, à régler séparément sous **Batteries Enersys**.
MD,
        ],

        'charging' => [
            'title' => 'Bornes de recharge et routeurs 4G',
            'body' => <<<'MD'
## À quoi ça sert ?

Les données des **bornes de recharge** (projets 608) et des **routeurs 4G** par site COW, avec des mots de passe et fichiers VPN visibles uniquement avec le droit adéquat.

## Où ?

**Bornes de recharge → Bornes de recharge** et **Bornes de recharge → Routeurs 4G**. Un projet 608 a aussi un onglet avec ses bornes.

## Ajouter une borne

1. Choisissez l'**emplacement** : **code COW** (un site de la liste) ou **Autre (client tiers)** avec les données du client et l'**adresse d'installation** complète.
2. Choisissez éventuellement le **projet** : l'emplacement est alors repris.
3. Complétez les données : numéro ACE, type (standalone ou cluster), système, marque, connectivité, licence, SIM, statut.
4. Le mot de passe et le code de récupération se saisissent avec le droit **Voir les données confidentielles**.

## Plusieurs bornes à la fois

**« Ajouter plusieurs bornes »** : l'emplacement et le statut se saisissent une seule fois, les bornes chacune sur sa **ligne**. L'icône de copie duplique une ligne.

## Export

Exportez la liste en **Excel** ou **PDF**, avec ou sans données confidentielles (uniquement avec le droit).

## Routeurs 4G

Par site COW : type, numéro de série, numéros SIM, identifiants et **fichier VPN**. Identifiants et fichier VPN ne sont visibles qu'avec le droit **Voir les données confidentielles** des routeurs.
MD,
        ],

        'cable-stripping' => [
            'title' => 'Dénudage de câbles',
            'body' => <<<'MD'
## À quoi ça sert ?

Le suivi du **dénudage de câbles** : par ligne la date, le projet, qui y a travaillé, les heures et les pesées.

## Où ?

**Déchets → Dénudage de câbles**.

## Comment ça marche

- Ajoutez par lot une **ligne** avec la date, l'**imputation** (projet), les collaborateurs, l'**heure de début et de fin**, le numéro de bac et les **pesées** : avant, bac vide et après le dénudage.
- Les **heures**, le **câble dénudé** et les **déchets** sont calculés automatiquement.
- Créez un **aperçu PDF** d'une période.
MD,
        ],

        'waste' => [
            'title' => 'Registre des déchets',
            'body' => <<<'MD'
## À quoi ça sert ?

Le **registre légal des déchets évacués** par catégorie et code déchet EURAL, avec les collecteurs, les listes de batteries par région et les totaux.

## Où ?

**Déchets → Registre des déchets**, **Totaux par catégorie**, **Catégories de déchets** et **Collecteurs de déchets**.

## Comment ça marche

1. Enregistrez chaque évacuation : date, **type de déchet** (catégorie avec code EURAL ; un astérisque signifie déchet dangereux), **poids**, le **collecteur** et sa référence (p. ex. numéro de lettre de voiture).
2. Pour les **déchets de batteries**, complétez aussi la **région**, l'**attestation de destruction**, le code COW et le numéro PO.
3. **Totaux par catégorie** montre les totaux par période.
4. Exportez chaque liste en **PDF**, p. ex. pour un contrôle ou la déclaration annuelle.

## Données de base

Les catégories peuvent avoir des sous-catégories ; les catégories et collecteurs inactifs ne peuvent plus être choisis pour de nouveaux enregistrements.
MD,
        ],

        'finance' => [
            'title' => 'Finances : factures, notes de crédit et factures entrantes',
            'body' => <<<'MD'
## À quoi ça sert ?

Établir et envoyer des factures et notes de crédit (aussi via **Peppol**), les rappels de paiement, valider les factures entrantes, gérer les clients et consulter les statistiques.

## Où ?

**Finances → Factures, Notes de crédit, Factures entrantes, Clients, Lignes de facture standard, Aperçu financier, Statistiques**. Les données de l'entreprise et Peppol se règlent dans **Paramètres → Finances**.

## Factures

1. Créez une facture pour un **client**, éventuellement liée à un **projet** et à ses **numéros PO**.
2. Ajoutez des lignes (ou choisissez une **ligne de facture standard**) ; TVA et totaux sont calculés.
3. **Envoyer** crée le PDF avec communication structurée et l'envoie par mail (modèle *Facture*), ou via Peppol si le client a un identifiant Peppol.
4. Pour les factures échues, envoyez jusqu'à **trois rappels de paiement** ; activés dans **Paramètres → Finances**, ils partent automatiquement chaque jour.

Une **note de crédit** se crée depuis une facture ou séparément.

## Factures entrantes

Les factures entrantes (aussi reçues via Peppol) sont **validées** : approuvées ou refusées, avec une signature.

## Aperçu et statistiques

- **Aperçu financier** : par mois les revenus, factures entrantes et le résultat ; cliquez sur un mois pour voir les documents.
- **Statistiques** : par période, avec comparaison avec l'année précédente.

## Droits

Les employés ne voient cette partie qu'avec les droits financiers (par type : factures, notes de crédit, factures entrantes, clients).
MD,
        ],

        'incidents' => [
            'title' => 'Signaler et suivre les incidents',
            'body' => <<<'MD'
## À quoi ça sert ?

Signaler immédiatement les **accidents, presque-accidents, situations dangereuses et actes dangereux**, avec photos, pour qu'ils soient suivis.

## Signaler

1. Allez dans **Sécurité → Signaler un incident** (ou l'onglet Plus dans l'application mobile).
2. Choisissez le **type** et **où** cela s'est passé (site du magasin ou site client, avec l'endroit exact).
3. Indiquez les **conséquences** (autres victimes, dégâts matériels) et décrivez ce qui s'est passé.
4. Ajoutez jusqu'à **20 photos** et envoyez. La date et l'heure sont ajoutées automatiquement.

Les administrateurs sont avertis immédiatement. Vos propres signalements se trouvent dans **Mes signalements**.

## Suivi

Dans **Sécurité → Incidents**, les administrateurs notent le **suivi** (mesures prises, personnes averties) et exportent un **PDF**, aussi par employé ou par période.
MD,
        ],

        'suggestions' => [
            'title' => 'Boîte à idées',
            'body' => <<<'MD'
## À quoi ça sert ?

Soumettre des **idées, plaintes et autres remarques** à la direction.

## Soumettre

1. Cliquez sur **Boîte à idées** dans la barre du haut, ou utilisez l'application mobile.
2. Choisissez **idée, plainte ou autre**, écrivez votre message et ajoutez éventuellement des photos.
3. Activez **« Peut être partagé au sein de l'entreprise »** si votre message peut être partagé avec les collègues.

## Suivi

Les administrateurs consultent les messages dans **Sécurité → Boîte à idées**, par type, donnent une **réponse** et les exportent en **PDF**.
MD,
        ],

        'toolbox' => [
            'title' => 'Toolbox meetings',
            'body' => <<<'MD'
## À quoi ça sert ?

Des **instructions de sécurité** avec texte et pièces jointes que chaque employé lit entièrement et signe.

## Où ?

- **Sécurité → Toolbox Meetings** : lire, signer et (avec le droit) créer.
- **Sécurité → Suivi des toolbox meetings** : qui a signé quel toolbox.

## Créer un toolbox meeting

1. Cliquez sur **Nouveau** et complétez le titre, le mois, qui a donné les instructions et le sujet en **néerlandais et français**.
2. Rédigez le texte avec l'éditeur : titres, listes et **images** placées librement dans le texte.
3. Ajoutez des **pièces jointes** (PDF, PowerPoint, Word, images).
4. **Publiez** : tous les employés voient le toolbox et reçoivent une notification (aussi sur leur téléphone).

## Lire et signer

- Ouvrez le toolbox et **lisez tout le texte** (faites défiler jusqu'en bas) et/ou **ouvrez toutes les pièces jointes**.
- Ensuite seulement, vous pouvez **signer**. Votre nom et votre signature sont conservés.
- C'est aussi possible dans l'**application mobile**.

## Suivi et PDF

- **Suivi des toolbox meetings** montre par mois et par toolbox qui a signé (✓).
- Le **PDF** est la liste de présence avec titre, entreprise, mois, instructeurs, sujet (NL/FR) et les noms et signatures.
MD,
        ],

        'knowledge-base' => [
            'title' => 'Base de connaissances',
            'body' => <<<'MD'
## À quoi ça sert ?

Des **articles et téléchargements** internes par catégorie, en néerlandais, français et anglais. Ce manuel se trouve aussi dans la base de connaissances.

## Lire

Allez dans **Base de connaissances → Base de connaissances**. Choisissez une catégorie ou recherchez un mot. Vous lisez les articles dans **votre langue** ; les pièces jointes se téléchargent en bas de l'article.

## Écrire

Avec les droits de la base de connaissances :

- **Base de connaissances → Catégories** : catégories avec nom et description par langue ; les catégories masquées ne sont visibles que par les rédacteurs.
- **Base de connaissances → Articles** : titre et texte **par langue**, en texte mis en forme (avec images) ou en Markdown, avec des fichiers à télécharger. Un article peut rester en brouillon jusqu'à ce qu'il soit prêt.
- Les articles se téléchargent aussi en **PDF**.
MD,
        ],

        'client-portal' => [
            'title' => 'Portail client (Global overview for Client)',
            'body' => <<<'MD'
## À quoi ça sert ?

Un portail séparé pour le client (p. ex. les managers de Proximus) avec un **aperçu en lecture seule** de leurs projets, demandes de GPW, livraisons de batteries, bornes et routeurs 4G. **Les prix ne sont jamais affichés.**

## Où ?

- Les clients se connectent sur **/client** avec leur propre compte, séparé des utilisateurs de l'ERP.
- Les administrateurs gèrent les comptes dans **Gestion → Système → Portail client**.

## Créer un utilisateur client

1. Complétez nom, e-mail, entreprise et **langue**.
2. Liez éventuellement une **personne de contact** (recherche par nom ou code COW) : l'utilisateur voit les projets sur les sites COW de cette personne. Ajoutez aussi des **projets attribués** séparément.
3. Cochez ce qu'il peut voir : projets (avec les **catégories**), **demandes de GPW**, livraisons de batteries, bornes, routeurs 4G.
4. Gardez **« Envoyer le mail de bienvenue »** activé : l'utilisateur reçoit un mail dans sa langue et choisit lui-même son mot de passe.

## Que voit le client ?

- Par défaut uniquement **ses propres projets** ; les projets d'autres clients seulement avec **« 🔒 Tous les projets »**.
- Mots de passe, codes de récupération, identifiants de routeur et fichiers VPN uniquement avec les droits 🔒.
- Dans **Demandes de GPW**, il approuve ou refuse les demandes.

Les mails (bienvenue, nouveau mot de passe) se modifient dans **Modèles d'e-mail**.
MD,
        ],

        'mobile-app' => [
            'title' => 'Application mobile PI ERP',
            'body' => <<<'MD'
## À quoi ça sert ?

L'application **PI ERP** apporte l'essentiel de la plateforme sur votre téléphone, aussi en déplacement.

## Installer et se connecter

- Sur un téléphone Android, la plateforme propose de télécharger l'application ; le téléchargement se trouve aussi dans votre **menu utilisateur**.
- Connectez-vous avec votre **adresse e-mail et mot de passe** de la plateforme. L'application utilise la langue choisie dans la plateforme.

## Que pouvez-vous faire dans l'application ?

- **Planning** : votre planning par jour, semaine ou mois (et celui des collègues que vous pouvez voir). Une notification à chaque changement.
- **Prestations** : remplir votre fiche de prestations ; elle est enregistrée directement dans la plateforme.
- **Congés** : demander un congé ou des **heures supplémentaires en congé**, **signaler une maladie** et ajouter un certificat (photo, fichier ou photo prise sur le moment).
- **Contrôles** : compléter le **contrôle annuel** du matériel de votre équipe (seulement quand une liste est ouverte).
- **Plus** : vos données, lire et signer les **toolbox meetings**, **signaler un incident**, la **boîte à idées** et compléter et signer les **dossiers batteries** (604/605).

## Notifications

Autorisez les notifications : vous êtes averti d'un changement de planning, d'un nouveau toolbox meeting, d'un contrôle annuel ou d'un GPW approuvé.

## Confidentialité

La déclaration de confidentialité de l'application se trouve sur **/privacy** et s'ouvre aussi depuis l'application.
MD,
        ],
    ],
];
