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

## Vérification en deux étapes

- Via **Profil**, activez une deuxième étape à la connexion : une **application d'authentification** (Microsoft Authenticator, Google Authenticator, Authy, 1Password, …) avec laquelle vous scannez un code QR, et/ou un **code par e-mail**.
- Conservez les **codes de récupération** en lieu sûr : chaque code fonctionne une fois si vous n'avez pas votre téléphone.
- Ensuite, la plateforme demande le code après votre mot de passe, aussi dans l'application mobile et sur le portail client.
- Plus d'accès à vos codes ? Un administrateur avec le droit **Sécurité → Réinitialiser la vérification en 2 étapes** la désactive (Administration → Utilisateurs ou Utilisateurs clients). Ce droit doit être coché séparément, même pour les administrateurs.

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

## Rôle RH (pas de feuille de prestations)

Le rôle **HR** ne voit par défaut que son propre compte, les **prestations par mois** et les **calculs RH**. Un rôle avec **Pas de feuille de prestations** (comme RH) n'a pas de feuille de prestations et ne figure pas dans le **planning**, les **congés** ou les **maladies** ; même sans équipe, ils ne sont pas invités. Tous les autres modules s'attribuent via les droits du rôle.

Le rôle **Administratie** fonctionne de la même manière : pas de feuille de prestations, de planning ni de congés, et pas d'équipe nécessaire. Il voit par défaut les **prestations par mois** et gère **tous les congés et absences de tous** (droit *Congés et absences → Gérer* : encoder les absences, approuver les demandes de congé, congé parental), même sans équipe ; cochez dans le rôle ce dont l'administration a encore besoin (p. ex. Financier).
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

## Affichage d'une équipe

Partout où vous choisissez ou voyez une équipe (utilisateurs, projets, planning, véhicules …), l'équipe s'affiche comme **nom de l'équipe - prénom membre 1 / prénom membre 2 / …**, p. ex. *Équipe Gand - An / Jan*. Le prénom vient du registre du personnel, sinon du premier mot du nom ; uniquement les membres actifs.
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

## Badges, clés électroniques et tags de sécurité

- **Badges & clés électroniques** : par collaborateur une ligne avec le **numéro de badge** (7 chiffres) et/ou la **clé électronique** (4 chiffres), si le collaborateur a l'**autorisation CLAD** et quelle **clearance**. Un numéro appartient à un seul collaborateur.
- **Tags de sécurité** : par collaborateur le **tag** (2 chiffres), le **nom dans le système de sécurité**, l'accès au **dépôt** et au **bureau**, et si le collaborateur a reçu les **clés des portes**.
- Un tag peut aussi être **non attribué** (en stock) ou remis à un **utilisateur avec un login qui ne figure pas dans la liste du personnel** (p. ex. nettoyage ou externes). Avec l'interrupteur **Perdu** (dans le formulaire ou directement dans la liste), vous signalez un tag perdu ; la date est conservée. Les onglets *Non attribués* et *Perdus* montrent ces tags, aussi dans le PDF.
- Les collaborateurs qui ne sont plus en service restent dans les deux listes comme **Désactivé** (onglet *Désactivé*).
- Ajouter et modifier avec le droit **Badges, clés électroniques & tags de sécurité** ; supprimer uniquement avec le droit *Supprimer*. Les deux listes s'exportent en PDF (droit *Générer des PDF*).

## Confidentialité

Les données sensibles comme le numéro de registre national sont conservées **chiffrées** et visibles uniquement avec les droits adéquats.

## Préavis et congé parental

- Avec **Enregistrer le préavis**, vous indiquez le début et la fin du préavis ; le collaborateur est alors *En préavis jusqu'au …* et peut prendre un jour de congé de sollicitation par semaine. Le lendemain de la date de fin, il est **automatiquement mis hors service** (login désactivé). **Retirer le préavis** l'annule.
- Dans l'onglet **Congé parental**, vous activez le congé parental du collaborateur, avec l'enfant, la forme, la période et l'attestation.
MD,
        ],

        'onboarding' => [
            'title' => 'Onboarding',
            'body' => <<<'MD'
## À quoi ça sert ?

Les nouveaux utilisateurs lisent d'abord les **documents d'onboarding** (p. ex. règlement de travail, règles de sécurité) et signent qu'ils les ont lus, avant d'utiliser la plateforme.

## Documents

- Dans **Documents d'onboarding**, téléversez un ou plusieurs fichiers à la fois avec une **langue** (néerlandais, français ou anglais). Chaque fichier devient un document séparé ; titre, ordre et langue se modifient ensuite.
- Un collaborateur ne voit que les documents dans **sa propre langue** : sa **langue maternelle** du registre du personnel, sinon la langue dans laquelle il utilise la plateforme. S'il n'y a pas (encore) de documents dans cette langue, il reçoit ceux de la langue suivante, sinon les néerlandais.

## Pour le collaborateur

- Un **nouvel utilisateur** arrive après la connexion d'abord sur la page **Onboarding** : toute autre page y mène tant que tout n'est pas signé.
- Par document : **Ouvrir** (le document s'ouvre dans un nouvel onglet), et aussitôt la plateforme demande de **signer** (nom et signature). La signature est conservée avec la date et l'heure.
- Les utilisateurs déjà présents à l'arrivée du module sont considérés comme intégrés. Les **administrateurs** et les logins qui **ne figurent pas dans la liste du personnel** (p. ex. nettoyage, externes) n'ont pas d'onboarding non plus : il est clôturé aussitôt. Si une telle personne est ajoutée plus tard à la liste du personnel sans avoir rien signé, la plateforme demande quand même l'onboarding.

## Suivi

Dans **Onboarding : signatures**, vous voyez qui a ouvert et signé quel document, avec la signature ; la liste s'exporte en PDF. Les droits **Voir les signatures** et *Générer des PDF* de l'onboarding sont nécessaires ; gérer les documents demande **Gérer**.
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
- **Intérimaires** : sur la page de l'employé (Personnel → employé), l'onglet **Récupération et jours fériés (intérim)** adapte librement leurs jours IR et jours fériés ; un intérimaire n'a pas toujours droit à chaque jour férié. **Retirer des jours de récupération ou fériés** (l'intérimaire travaille ce jour-là, il compte comme un jour ouvrable normal) ou **attribuer des jours de récupération** propres sur n'importe quel jour ouvrable ; **Annuler** rend le jour de récupération ou férié ou supprime le jour propre. La fiche de prestations, le planning, le décompte des congés, l'app et Outlook suivent.

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
- Seule la maladie demande un certificat : pour les **heures supplémentaires en congé**, le **congé familial** et le **congé sans solde**, il n'y a pas de téléversement (« Pas nécessaire »).
- L'**absence justifiée (GA)** est ajoutée uniquement par un administrateur, avec un **document** (photo ou PDF) comme preuve. À la création, vous choisissez une ou plusieurs **périodes distinctes** : chaque période devient une absence séparée avec le même document. La feuille de prestations affiche le code **GA**, sans heures. Le collaborateur peut seulement télécharger le document.

## Conseil

Les collègues ne voient dans le calendrier que « absent », jamais la raison d'une maladie.

## Autres types de congé

- **Congé mobilité (MV)** : 1 jour par 27 000 km enregistrés dans les prestations de **l'année précédente** ; à planifier librement via une demande de congé, valable jusqu'au 31 décembre. Pas d'attestation. Compte comme jour presté.
- **Congé d'ancienneté (AN)** : 1 jour par an à partir de **25 ans de service** (depuis la date d'entrée ; pas pour les intérimaires) ; à planifier librement. Compte comme jour presté.
- **Congé de sollicitation (SV)** : pendant un **préavis**, une **journée complète par semaine**, jusqu'à la date de fin. Compte comme jour presté (payé).
- **Chômage économique (EW)** : encodé uniquement par les administrateurs ; compte comme non presté (payé par l'ONEM), mais les **heures supplémentaires restent**. Pas d'attestation.
- **Petit chômage (KV)** : uniquement par les administrateurs, avec le **motif** de l'arrêté royal du 28/08/1963 (p. ex. mariage 2 jours, décès d'un parent 3 jours) et une **attestation**. Compte comme jour presté complet.
- **Accident du travail (WO)** : uniquement par les administrateurs, lié à la **déclaration d'accident**. Payé si le **certificat est dans la plateforme dans les 48 heures** ; sinon, les jours comptent comme **congé sans solde**. Rien ne peut y être planifié.
- **Congé parental (OV)** : les administrateurs l'activent par collaborateur (chez le collaborateur → Congé parental : enfant de moins de 12 ans, forme complète, 1/2, 1/5 ou 1/10 avec leur durée maximale, et **l'attestation**). Ils encodent ensuite les jours OV dans cette période. Compte comme non presté (ONEM), les heures supplémentaires restent.
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

## Employés (statut bediende)

Les collaborateurs avec le statut **employé** (registre du personnel) n'encodent ni heures ni kilomètres : chaque jour ouvrable compte **8 heures**. Une demi-journée de congé laisse 4 heures prestées ; congé, maladie, formation et jours fériés comptent comme pour tous, le congé familial et sans solde comptent 0. L'imputation, les remarques et l'envoi restent les mêmes. Dans l'application mobile, ils n'ont pas d'onglet Prestations.

Lorsque le statut d'un collaborateur est **Incapacité de travail** (registre du personnel), chaque jour ouvrable figure comme **Z (maladie)** sur la feuille de prestations, y compris les semaines à venir. En le définissant, vous choisissez le premier jour (aujourd'hui par défaut) ; en remettant le statut (p. ex. ouvrier), vous indiquez le **dernier jour de maladie** (hier par défaut). Chaque période est conservée et affichée chez le collaborateur sous *Incapacité de travail*.

## Travail de nuit et codes

- **Travail de nuit (N)** : les heures entre **20:00 et 06:00**. Dans l'aperçu mensuel, les heures prestées sont alors suivies d'un **N** (p. ex. *7,5N*), et le total des heures de nuit figure dans les codes. Commencer à 05:00 et continuer la journée n'est pas du travail de nuit. Une équipe de nuit peut passer minuit (p. ex. 22:00 → 06:00 : arrivée à partir de 18:00, départ jusqu'à 08:00).
- Nouveaux codes dans l'aperçu : **MV**, **AN**, **SV**, **KV** et **WO** payé comptent comme jour presté ; **EW** et **OV** comptent comme non presté sans déduire d'heures supplémentaires ; un **WO** sans certificat après 48 heures compte comme non payé.
MD,
        ],

        'hr-calculations' => [
            'title' => 'Calculs RH : avances',
            'body' => <<<'MD'
## À quoi ça sert ?

Calculer l'**avance mensuelle** des ouvriers et employés.

## Où ?

**Personnel → Calculs RH : avances**. Uniquement pour les rôles ayant le droit **Calculs RH → Voir** ; les administrateurs ne l'ont pas automatiquement, il faut le cocher.

## Le calcul

Avance = **valeur de départ × jours travaillés ÷ jours ouvrables** de la période (week-ends non comptés).

- Uniquement les travailleurs du **registre du personnel**, en service et **pas en intérim**.
- Valeur de départ : **620 €** pour les ouvriers, **1000 €** pour les employés (et cadres).
- Période : par défaut du **1er au 15** du mois inclus.
- **Comptent comme travaillés** : prestations normales, maladie, jours fériés, jours de récupération (IR), récupération d'heures supplémentaires et formations.
- **Ne comptent pas** : congé sans solde, congé familial et congé approuvé. S'il n'y a qu'**un seul jour de congé** dans la période, il compte comme travaillé.

## Tout est modifiable

- Choisissez le **mois** et adaptez la **période** (du – jusqu'au).
- Adaptez la **valeur de départ** par statut, ou par travailleur dans la liste.
- Avec le droit **Calculs RH → Gérer**, enregistrez les valeurs de départ et le dernier jour de la période comme **défaut** pour les mois suivants.
- Exportez l'aperçu en **PDF**.

Le congé sans solde s'enregistre dans **Personnel → Absences** (type *Congé sans solde*).
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

Les jours de **maladie ou de congé familial** sont ignorés et signalés ; les autres absences restent affichées, mais vous pouvez encore y planifier des projets (voir ci-dessous).

## Notifications

Chaque changement de votre planning vous est notifié dans la plateforme et sur votre téléphone (application mobile), et au choix par e-mail.

## Agenda Outlook

Si la liaison avec Microsoft 365 est activée (**Paramètres → Planning → Agenda Outlook**), chaque élément du planning est aussi ajouté à l'**agenda Outlook** de l'employé, en **journée entière** (sans heures), avec le projet, l'adresse et les remarques. Si l'élément est modifié, déplacé vers un collègue ou supprimé, c'est aussi le cas dans Outlook.

Les **absences** (maladie, heures supplémentaires en congé, congé familial, congé sans solde, absence justifiée), les **jours fériés**, les **jours de repos compensatoire** et les **formations** vont aussi dans Outlook, en journées entières « absent ». Une absence figure dans l'agenda de l'employé (maladie, congé familial et sans solde et absence justifiée en *privé*), un jour férié ou de repos compensatoire chez chaque employé actif, une formation chez ses participants (avec le lieu et un rappel la veille). Les modifications et suppressions suivent dans Outlook. Après l'activation de la liaison, `php artisan planning:sync-outlook` ajoute tout à partir d'aujourd'hui dans les agendas.

Pour un **projet 604**, le planning et Outlook indiquent les batteries retirées et placées au lieu de la description du projet, p. ex. *2 x 500Ah OPzS -> 2 x 490Ah OPzV* (du dossier batteries ; tant qu'il n'y a pas de dossier, uniquement les nouvelles batteries de la commande BAE). Si le dossier est rempli ou modifié plus tard, les rendez-vous planifiés dans Outlook sont mis à jour.

## Droits

- **Planifier tout le monde** (projets et magasin), **planifier d'autres éléments**, **voir le planning de chacun** et **planifier des formations** sont des droits distincts.
- Les chefs de projet planifient leurs propres projets.
- Celui qui a demandé un **GPW** planifie, après approbation, une équipe dans la période approuvée (voir « Demandes de GPW »).

## Congés et chevauchements

Vous pouvez aussi planifier des projets un jour de **congé, repos compensatoire, heures supplémentaires en congé, formation** ou autre absence : elle reste affichée. Seuls la **maladie** (aussi l'incapacité de travail) et le **congé familial** empêchent de planifier. Les projets d'une même personne peuvent **se chevaucher**.
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

## Cartes carburant

Dans **Parc automobile → Cartes carburant**, vous ajoutez les cartes : le **numéro de carte** (18 chiffres), le **code PIN** (4 chiffres), la **date d'expiration** (mois et année, p. ex. 08/2028 ; en rouge une fois expirée) et le **véhicule**. Le code PIN n'est jamais dans la liste : avec **Afficher le PIN**, seuls les **membres de l'équipe du véhicule** et ceux qui ont le droit **Cartes carburant → Afficher le code PIN** (à cocher séparément, même pour les administrateurs) voient le code ; chaque affichage est consigné. Les membres de l'équipe voient les cartes de leurs véhicules même sans le droit *Consulter*.
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

## Fournisseurs et stock par site

- Un article peut avoir **plusieurs fournisseurs**, chacun avec son propre **numéro d'article** et son **prix**. Marquez-en un comme **préféré** : son prix est le prix du marché de l'article.
- Pour un fournisseur **avec une connexion** (Rexel, Cebeo), le prix est récupéré ; pour tout autre fournisseur, **saisissez le prix vous-même**.
- La liste de stock affiche une colonne avec la quantité **par site** (les sites de vos équipes, ou tous pour les administrateurs), à côté du total. La recherche trouve aussi les numéros d'article des fournisseurs.
- Avec le droit **Prix (prix spéciaux des grossistes) → Exporter la liste avec les prix (Excel)**, **Exporter avec les prix** télécharge la liste affichée (avec votre recherche et vos filtres) : le stock par site et au total, le prix du marché et par fournisseur le numéro d'article, le prix et la date du prix.

## Étiquettes QR

Imprimez une **étiquette QR** par article ou plusieurs à la fois. En scannant l'étiquette, on arrive directement sur l'article.

## Prix et images

- **« Récupérer le prix chez le grossiste »** met à jour le prix chez **chaque fournisseur avec une connexion**, avec le numéro d'article de ce fournisseur (ou sinon la référence du fabricant).
- **Rexel** est connecté : avec le numéro client, l'utilisateur et le mot de passe du grossiste Rexel, la plateforme récupère votre **prix net** (par unité, sans contributions comme Recupel) et, si l'article n'en a pas encore, la **photo**. Indiquez comme référence le **code article Rexel** ou le **numéro e** ; la liaison Rexel ne connaît pas les codes EAN. Les prix sont mis à jour chaque matin à 05:30.
- **Cebeo** est aussi connecté (service web XML) : avec le numéro client, l'utilisateur et le mot de passe du grossiste Cebeo, la plateforme récupère votre **prix net** par unité. Indiquez comme référence le **numéro d'article Cebeo** ; sinon, la plateforme cherche sur la **référence fabricant**. Cebeo ne fournit pas de photos.
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

Les projets reçoivent leur code COW du site (p. ex. `604-0001-02GAM`). Les bornes, routeurs, interventions et demandes de GPW utilisent les mêmes sites et personnes de contact.
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

- Le numéro suit la catégorie : catégorie, numéro d'ordre et code COW, p. ex. `604-0001-02GAM` ; numéroté par an comme `605-260001-02GAM` ; particulier sans code COW, p. ex. `606-0001`.
- Laissez le numéro vide pour le **numéro libre suivant**. Sur une catégorie, l'administrateur peut fixer le **numéro suivant** ; sur un projet existant, le numéro peut être corrigé.

## Statut

Offre → En attente du PO → À planifier → Planifié → À exécuter → En cours → Exécuté → À facturer → Facturé → Clôturé. Changez le statut avec **« Changer le statut »**. Un projet **clôturé** est archivé avec un ZIP de tous les fichiers.

## Sur un projet

- **Numéros PO** du client (10 chiffres, plusieurs possibles).
- Onglets **documents, photos, plans, schémas, fichiers supplémentaires, pièces, offres et factures**. Les fichiers ont des versions.
- Les **chefs de projet** et les **équipes affectées** déterminent qui voit le projet.
- Pour 610–650, une **barre d'avancement** montre les étapes (demande, visite, offre, PO, … jusqu'à la remise) ; cochez les étapes ou elles se cochent automatiquement. Quand **PO reçu** est coché, **« Ajouter un numéro PO »** ajoute un numéro PO à tout moment (en cochant l'étape sans numéro PO, cette fenêtre s'ouvre directement).
- **Copier** démarre un nouveau projet à partir de celui-ci.
- Pour 608, choisissez l'**emplacement** : un code COW ou un **client tiers** avec sa propre adresse d'installation.
- Depuis un projet, demandez un **GPW** (voir « Demandes de GPW »).

## Jours de travail estimés

À la création (ou modification) d'un projet, vous indiquez les **jours de travail estimés**. Qui a le droit **Jours de travail des projets** voit dans la liste des projets et sur le projet **estimé/réel**, p. ex. *10/12* : le nombre réel correspond aux jours (jusqu'à aujourd'hui) où le projet figure dans le **planning**, quel que soit le nombre de personnes ce jour-là. **Vert** tant que le réel est égal ou inférieur à l'estimation, **rouge** dès que l'estimation est dépassée. Le filtre *Jours de travail dépassés* ne montre que ces projets.

Ce droit ne fait partie d'**aucun rôle** : un administrateur le coche dans les **droits propres d'un utilisateur** (Administration → Utilisateurs). Les administrateurs aussi ne le voient que s'il est coché pour eux.

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

## Aperçu et ajout manuel

Dans **Projets → Demandes GPW** figurent toutes les demandes GPW des projets que vous voyez, avec la **date de début**, la **date de fin** et le statut : *En attente d'approbation GPW*, *À planifier - GPW approuvé* ou *En attente - renvoyer la demande GPW* (et *Planifié*). Le même statut apparaît aussi dans la **liste des projets** (colonne Demandes GPW, la demande la plus récente) et dans l'onglet GPW du projet.

Un GPW a été demandé par **e-mail personnel** ? Utilisez **Ajouter un GPW manuellement** : choisissez le projet, la période, à qui il a été demandé et le statut (en attente, approuvé ou refusé). Aucun e-mail n'est envoyé ; approuvé met le projet sur *À planifier*, comme une approbation par le client. Si la réponse arrive plus tard, enregistrez-la avec **Enregistrer la décision**. Il faut le droit **GPW** des Projets. Le même bouton **Ajouter un GPW manuellement** se trouve aussi sur le **projet** lui-même (et dans l'onglet Demandes GPW) ; en haut du projet, un petit aperçu des demandes GPW indique le début, la fin et le statut.
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

Dans le portail client, le client voit par livraison si elle est **livrée** avec la **date de livraison effective** (le jour de la coche), quand l'**installation est planifiée** (la date d'exécution du dossier batteries, ou sinon le premier jour au planning) et une coche **exécuté** avec la date à laquelle le projet est passé à *exécuté*.

Avec **Documents**, ajoutez par livraison trois fichiers : la **commande** au fournisseur, la **confirmation de commande** du fournisseur et le **bon de livraison** à l'arrivée. Ils figurent aussi sur le projet (onglet *Livraisons de batteries*). Ils sont internes : jamais visibles dans le portail client, et uniquement avec les droits **Voir et télécharger les documents de livraison** et **Téléverser les documents de livraison**.

## Commande BAE

Chaque nouveau projet 604 reçoit d'emblée sa **commande BAE** (l'ancien *calc.xlsx*). Ouvrez-la via **Batteries → Commandes BAE** ou avec le bouton **Commande BAE** sur le projet (les projets plus anciens la reçoivent alors aussi).

1. Choisissez le **type de batterie** et le **montage** : les lignes de commande (connecteurs intercell, step, tier et row), le rack avec son prix et le prix BAE par élément sont remplis à partir des **types de batteries**.
2. Indiquez le **nombre de batteries**, les éléments par batterie et la capacité, ou reprenez-les avec **Reprendre du dossier batteries**.
3. Vérifiez l'**aperçu** en bas : il calcule comme la feuille Excel *Order* (batteries × éléments × prix par élément, plus les racks).
4. **Exporter en PDF** enregistre et ouvre la commande, prête à être envoyée à BAE. Avec **Joindre aux documents de livraison**, le PDF est ajouté à la livraison comme *Commande au fournisseur*.

5. **Envoyer au fournisseur** envoie la commande en PDF au **fournisseur** choisi, avec un message supplémentaire facultatif. Cela se fait uniquement avec ce bouton, jamais automatiquement, et seulement quand la commande est complète. La liste indique quand et à qui elle a été envoyée.

Les fournisseurs se gèrent dans **Batteries → Fournisseurs** (droit **Gérer les fournisseurs de batteries**) : l'adresse e-mail pour les commandes, des adresses en copie, la langue du mail et le fournisseur par défaut. Chaque commande part aussi en copie aux adresses de **Paramètres → Batteries → Commande BAE → Commandes en copie à**. Le texte du mail se modifie par langue dans **Modèles d'e-mail → Commande BAE (au fournisseur)**.

Les valeurs par défaut (contrat, adresse de livraison, personne de contact, facturation) se trouvent dans **Paramètres → Batteries → Commande BAE**. Les prix BAE et les connecteurs se modifient dans les **types de batteries**. Uniquement avec le droit **Commandes BAE : voir, remplir et exporter** (et *générer des PDF* pour l'export).

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


## Déplacement de batteries

Un projet 605 peut aussi être un **déplacement** : des batteries qui passent d'un code COW à un autre. Sur le projet, choisissez **Dossier Enersys** ou **Déplacement de batteries** ; dès que l'un existe, l'autre bouton disparaît. Vous les trouvez aussi dans **Batteries → Déplacements de batteries**.

- **Input** : le code COW de l'enlèvement et celui du placement (par défaut celui du projet), les locaux, le type de batteries, la capacité, le nombre et les éléments. Indiquez si les **anciennes batteries sur le nouveau code COW** sont enlevées et si le **code COW de l'enlèvement est mis hors service**.
- Ensuite, cela fonctionne comme un dossier 604 : **note de livraison** (avec le transport vertical à l'enlèvement et au placement), **rapport de mesure**, **rapport d'inspection visuelle**, **plan**, **schéma unifilaire**, photos, les **signatures** et la **clôture**.
- Il y a deux **notes d'envoi** : celle du déplacement (du premier vers le second code COW) et, seulement si des anciennes batteries partent, celle des déchets.
- Si **hors service** est coché, le système passe à la clôture le type de bâtiment du code COW de l'enlèvement à **Hors service**, avec une ligne dans le journal du site.
- Dans l'application mobile, ils figurent sous **Déplacements de batteries (605)**, avec les mêmes parties que sur la plateforme. Un projet 605 clôturé ne crée jamais de projet 609.

## Suivi des livraisons

Le client commande lui-même les batteries Enersys. Dans **Batteries → Livraisons Enersys**, suivez par projet 605 le **numéro de PO** et la **date de livraison**, et cochez **livré** à l'arrivée. Le projet passe alors à **À planifier** (sauf s'il est déjà plus loin, p. ex. planifié). Le numéro de PO est repris du projet. Les livraisons figurent aussi sur le projet (onglet *Suivi des livraisons*).

## Droits

Le même type de droits que pour les dossiers batteries, à régler séparément sous **Batteries Enersys**.
MD,
        ],

        'battery_rooms' => [
            'title' => 'Locaux batteries (609)',
            'body' => <<<'MD'
## À quoi ça sert ?

Après un remplacement de batteries (604) en **Flandre ou à Bruxelles**, le local batteries est mis en ordre. La catégorie **609** sert à cela, avec par projet un dossier comme l'Excel *Leveringsnota* : la note de livraison, les infos supplémentaires du client, la checklist et les photos, le calcul de ventilation et le rapport de contrôle BTV.

## Comment naît un 609 ?

- Quand un **projet 604 est clôturé** sur un code COW de l'area **1-VLA, 2-VLA ou 3-BRU**, la plateforme crée automatiquement un projet 609 sur le même code COW, avec la même équipe et le même chef de projet. Le numéro de local vient du dossier batteries du 604.
- Le **numéro de PO** n'est pas repris : le projet est en **Attente du PO**. Dès que Proximus donne le PO, ajoutez-le sur le projet avec **Ajouter un numéro de PO** ; la note de livraison l'affiche alors comme *Réf. client*.
- Avec **Nouveau** dans **Locaux batteries**, créez un local batteries à la main : choisissez le code COW (ou le projet 604 qu'il suit) et le numéro de local. Le projet 609 est créé immédiatement (le droit de créer des projets est aussi nécessaire).

## Où ?

**Locaux batteries → Locaux batteries**, ou le bouton **Local batteries** sur le projet 609.

## Fonctionnement

- **Note de livraison** : numéro de local, date, les quantités par poste (éclairage, ventilation, tapis ESD, pictogrammes, coffret fusibles, échelle à câbles, éclairage de secours, sol époxy, sonde de température, grille d'aspiration), remarques et la **signature** : comme pour le 604, le dossier est d'abord enregistré, puis la note de livraison est verrouillée (seul un administrateur peut retirer la signature). Le PDF est en portrait sur le papier à lettres, comme la note de livraison du 604.
- **Infos supplémentaires** : un champ libre pour ce que le client communique en plus.
- **Checklist** : partout la même liste ; seuls le code COW et le numéro de local changent. Par point, cochez **OK** (vide = NOK) et indiquez l'**action à entreprendre** comme le client la communique. Le PDF est en paysage, sans papier à lettres.
- **Photos & documents** : plusieurs photos, le calcul de ventilation et le rapport de contrôle BTV (PDF).

Les documents sont imprimés dans la langue de la région (Flandre en néerlandais, Bruxelles selon Paramètres → Batteries), sauf si vous choisissez une langue. À la clôture du projet 609, les documents et fichiers sont repris dans l'archive.

## Droits

**Locaux batteries (609)** : voir, créer, modifier, supprimer, générer des PDF et télécharger des PDF (pour le rapport BTV). Le module s'active ou se désactive dans **Paramètres → Modules**.
MD,
        ],

        'board_deliveries' => [
            'title' => 'Suivi des tableaux électriques (610–650)',
            'body' => <<<'MD'
## À quoi ça sert ?

Le suivi des **tableaux électriques** des projets 610, 620, 630, 640 et 650, avec une liste par **fournisseur** : ELP, P&V panels, CE+T et Automation LP (Halle). C'est un module séparé, à activer ou désactiver dans **Paramètres → Modules**.

## Où ?

- **Tableaux → Suivi des tableaux** : un onglet par fournisseur.
- **Tableaux → Fournisseurs de tableaux** : ajouter des fournisseurs, choisir l'ordre et le type de liste.
- Sur le projet : onglet *Suivi des livraisons*.

## Fonctionnement

- **ELP et P&V panels** (liste simple) : projet, numéro de PO, date de livraison et une coche **livré** (le jour de la coche devient la date de livraison effective).
- **CE+T et Automation LP** (liste avec configuration) : numéro de PO, projet, **livraison prévue**, **livraison effective**, **date de configuration** et une coche **installé & configuré**. Les dates se remplissent directement dans la liste.

Une nouvelle ligne se crée avec **Nouveau** : choisissez le fournisseur et un projet 610–650 ; le numéro de PO est repris du projet. Le statut du projet ne change jamais automatiquement ici.

## Droits

**Suivi des tableaux électriques** : voir, créer, modifier, supprimer et **gérer les fournisseurs**.
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

        'asbuilt' => [
            'title' => 'As-builts',
            'body' => <<<'MD'
## À quoi ça sert ?

Par projet, un **as-built** avec tous les fichiers par catégorie, le contrôle par **Normec BTV** et au final un ZIP pour le client.

## Où ?

- **Projets → As-builts** : l'aperçu par statut (en cours, planifié pour Normec BTV, terminé).
- **Projets → Fichiers standard as-built** : le registre des fichiers standard.
- Sur un projet : le bouton **As-built**.
- La page d'un as-built affiche en haut les **données du projet** (catégorie, site avec adresse, description, numéros PO, chefs de projet, équipes …) et en dessous les fichiers par catégorie.

## Comment naît un as-built ?

Dès qu'un projet passe au statut **Exécuté**, l'as-built est créé automatiquement avec le statut **En cours**. Pas pour les catégories exclues (par défaut 600, 604, 605, 606, 607 et 608.1 ; réglable dans Paramètres → Général). Les **fichiers standard** du code COW du projet, les influences externes et les fichiers standard généraux sont ajoutés directement.

Le bouton **Créer l'as-built** sur le projet (droit *As-built → Gérer*) crée l'as-built plus tôt, avec les mêmes fichiers standard et le même déroulement. Quand le projet passe ensuite à *Exécuté*, aucun deuxième n'est créé. Chaque as-built indique s'il a été créé automatiquement ou manuellement (et par qui).

## Catégories

Plans, schémas électriques, voies d'évacuation, influences externes, documents techniques, dimensionnement des câbles, rapports BTV, calculs ICC et Lmax. Tout format de fichier est accepté.

- Les **voies d'évacuation** appartiennent à un **code COW** et sont réutilisées dans chaque as-built de ce code.
- Les **influences externes** valent pour **tous les projets** : elles n'appartiennent à aucun code COW et rejoignent chaque as-built.
- Les **documents techniques** forment un **registre** : choisissez-en par as-built et téléversez-en de nouveaux (ils rejoignent aussi le registre).

## Déroulement

1. Ajoutez des fichiers par catégorie (le bouton de téléversement de chaque catégorie ou **Ajouter des fichiers**).
2. **Planifier le contrôle Normec BTV** : choisissez la date. Le statut devient *Planifié pour Normec BTV*.
3. Après le contrôle : **Téléverser le rapport BTV**. L'as-built se clôture automatiquement en un ZIP nommé *« 610-012-02GAM - ASBUILT »* avec un dossier par catégorie (0 - BTV control, 1 - cable dimensions, … 8 - evacuation routes).
4. Le client reçoit un **e-mail** et télécharge le ZIP dans le Global overview for Client.

Avec le droit **Rouvrir**, vous rouvrez un as-built terminé.

## Le client

Dans le portail client (droit **As-builts**), le client suit le statut, télécharge le ZIP et fournit des fichiers : voies d'évacuation, plans, influences externes, ICC et Lmax, ou tout en un ZIP dans la **zone générale**. Les chefs de projet en sont avertis.

## Droits

- **As-built → Voir** : l'aperçu de tous les as-builts.
- **As-built → Gérer** : ajouter et supprimer des fichiers, planifier le contrôle, téléverser le rapport BTV.
- **As-built → Gérer les fichiers standard** et **Rouvrir**.
- **As-built → Supprimer** : supprimer définitivement un as-built (avec ses propres fichiers et le ZIP), uniquement du **côté administrateur**. C'est un *droit supplémentaire* : même les administrateurs ne l'ont que s'il est coché.
- Qui est sur le projet (chef de projet ou équipe affectée) peut toujours ouvrir et télécharger l'as-built de ce projet.
MD,
        ],

        'cable-calculator' => [
            'title' => 'Calcul de câbles',
            'body' => <<<'MD'
## À quoi ça sert ?

Calculer une **section de câble indicative**, comme le calculateur de câbles de Schneider : selon l'**IEC 60364-5-52**, la plateforme cherche la plus petite section qui respecte le courant admissible, la chute de tension et (en option) le courant de court-circuit.

## Saisie

- **Circuit** : AC triphasé, AC monophasé ou **DC** (p. ex. 48 V), la tension, la charge en **courant** ou en **puissance** (avec cos φ), la **longueur** et éventuellement la **protection** (vide : la valeur normalisée suivante au-dessus du courant d'emploi).
- **Câble et pose** : cuivre ou aluminium (à partir de 16 mm²), PVC ou PR, le nombre de **câbles en parallèle**, le **mode de pose** (A1 à E), la température ambiante ou du sol et le nombre de **circuits groupés**.
- **Limites** : la **chute de tension** admise (3 % par défaut) et en option le **courant de court-circuit** avec le temps de coupure.

## Résultat

À côté du formulaire, vous voyez directement le **câble proposé** (p. ex. *3 × 6 mm² Cu XLPE*), ce qui détermine la section (courant, chute de tension ou court-circuit), Ib, In, les facteurs k1 et k2, Iz et la chute de tension, et les sections autour du choix. Enregistrez le calcul avec un nom et éventuellement un **projet** ; **Copier** crée une variante.

## PDF

Avec **PDF** (droit *Générer des PDF*), vous imprimez le calcul sur le **papier à en-tête de la note de livraison 604**, avec toutes les données et le tableau des sections.

## Droits

Le droit **Calcul de câbles** : consulter, créer, modifier, supprimer et générer des PDF. Le module s'active ou se désactive dans Paramètres → Modules.

Le calcul est une **indication** et ne remplace pas une étude complète (sélectivité, impédance de boucle, harmoniques …).
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
2. Pour les **déchets de batteries**, complétez par **attestation de destruction** une ligne avec la **région**, le code COW, le numéro PO et le **poids**. Un enregistrement peut avoir plusieurs lignes (plusieurs attestations, codes COW et numéros PO) ; le poids total est la somme des lignes. Par ligne, indiquez aussi une **référence de projet** (un projet de la plateforme, ou libre). Par ligne, téléversez le **document PDF** de cette attestation ; c'est aussi possible plus tard dans la liste de batteries par région avec **Téléverser le PDF**.
3. **Déchets → Listes de batteries par région** montre par région une ligne par attestation, avec son propre poids, même si plusieurs lignes ont la même référence. **Exporter par année** crée d'abord la liste (paysage), suivie du PDF de chaque ligne (portrait), dans le même ordre.
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

## La conversation

- Vos soumissions et les réponses se trouvent dans **« Mes soumissions »** (bouton sur la page de la boîte à idées). Cliquez sur une soumission pour ouvrir la conversation.
- Les administrateurs répondent dans **Sécurité → Boîte à idées** avec **« Répondre »** ; vous répondez de la même manière. Une réponse contient du texte et éventuellement des **photos**.
- À chaque nouvelle réponse, l'autre partie reçoit une **notification dans la plateforme** et un **e-mail** dans sa langue (modèle *Boîte à idées : nouvelle réponse*).
- Seuls vous et les administrateurs voyez la conversation et les photos.

## Suivi

Les administrateurs fixent le **statut** (nouveau, en cours, terminé) avec **Assurer le suivi** et exportent les soumissions en **PDF**. La première réponse d'un administrateur met une nouvelle soumission *en cours*.
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
- Télécharger les pièces jointes PDF nécessite le droit **Base de connaissances → Télécharger les fichiers PDF**.

## Sous-catégories

Une catégorie peut figurer sous une **catégorie principale** (un seul niveau) : choisissez la *Catégorie principale* dans la catégorie. L'aperçu montre les catégories principales avec leurs sous-catégories ; une catégorie principale masquée masque aussi ses sous-catégories. Le **manuel de l'application ERP** a ainsi une sous-catégorie par module. L'article d'un module n'est visible que si vous avez accès à ce module (p. ex. *Parc automobile* uniquement avec les droits du parc) ; les administrateurs et ceux qui gèrent la base de connaissances voient tout.
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
- Les bornes et routeurs sans code COW (p. ex. chez un client tiers) n'apparaissent jamais dans le portail.
- Dans **Demandes de GPW**, il approuve ou refuse les demandes.

Les mails (bienvenue, nouveau mot de passe) se modifient dans **Modèles d'e-mail**.

## Supprimer le compte

Un client peut supprimer son propre compte via son **profil → Supprimer mon compte**. Il reçoit d'abord l'avertissement qu'il n'aura ensuite plus accès aux informations et téléchargements, coche qu'il le comprend et confirme avec son mot de passe. Ce qu'il a fourni reste avec les projets. Les administrateurs reçoivent une notification.
MD,
        ],

        'mobile-app' => [
            'title' => 'Application mobile PI ERP',
            'body' => <<<'MD'
## À quoi ça sert ?

L'application **PI ERP** apporte l'essentiel de la plateforme sur votre téléphone, aussi en déplacement.

## Installer et se connecter

- Sur un téléphone Android, la plateforme propose de télécharger l'application ; le téléchargement se trouve aussi dans votre **menu utilisateur**.
- Si l'administrateur a désactivé le téléchargement (**Paramètres → Application mobile → Désactiver le téléchargement**), le menu et la proposition ouvrent **Google Play** ou, sur un iPhone, l'**App Store**, si ces liens sont remplis.
- Connectez-vous avec votre **adresse e-mail et mot de passe** de la plateforme. L'application utilise la langue choisie dans la plateforme.

## Que pouvez-vous faire dans l'application ?

- **Planning** : votre planning par jour, semaine ou mois (et celui des collègues que vous pouvez voir). Une notification à chaque changement.
- **Prestations** : remplir votre fiche de prestations ; elle est enregistrée directement dans la plateforme.
- **Congés** : demander un congé ou des **heures supplémentaires en congé**, **signaler une maladie** et ajouter un certificat (photo, fichier ou photo prise sur le moment).
- **Contrôles** : compléter le **contrôle annuel** du matériel de votre équipe (seulement quand une liste est ouverte).
- **Plus** : vos données, lire et signer les **toolbox meetings**, **signaler un incident**, la **boîte à idées** et compléter et signer les **dossiers batteries** (604/605), avec le **plan** que vous dessinez comme sur la plateforme (604), et les **locaux batteries** (609) : note de livraison avec signature, infos supplémentaires, checklist et photos (le calcul de ventilation et le rapport BTV sont ajoutés sur la plateforme).
- **Projets** : les projets 600 et 610–650 que votre équipe exécute : référence, description, adresse, code COW, personne de contact (appeler ou envoyer un e-mail) et chefs de projet, si le **GPW** est approuvé et pour quelle période (et les jours planifiés), les documents, photos, **schémas électriques** et fichiers extra à ouvrir (pas d'offres, factures ou plans), et la **liste de pièces** : ajoutez les articles utilisés, de la liste de stock ou décrits vous-même. Vous ne pouvez retirer que les lignes que vous avez ajoutées ; sur la plateforme, la liste de pièces montre qui les a ajoutées.
- **Interventions** : **clôturer** une intervention comme sur la plateforme (catégories, code COW et local, temps presté, collègues, matériel, positions fusible/disjoncteur, tableaux modifiés et remarques), sans signature. Un administrateur la revoit ensuite ; une intervention renvoyée se clôture à nouveau.
- **Messages** : les messages envoyés depuis la plateforme, dans votre langue.

## Notifications

Autorisez les notifications : vous êtes averti d'un changement de planning, d'un nouveau toolbox meeting, d'un contrôle annuel ou d'un GPW approuvé.

Les administrateurs avec le droit **Messages push de l'app → Envoyer** (à cocher séparément, même pour les administrateurs) envoient via **Administration → Messages push de l'app** un message à tout le monde, à des équipes ou à des personnes choisies. Le message est rédigé en néerlandais, français et anglais ; chaque collaborateur reçoit uniquement la version dans la langue de son profil, en notification et sous **Plus → Messages**. La liste indique combien de destinataires l'ont lu.

## Confidentialité

La déclaration de confidentialité de l'application se trouve sur **/privacy** et s'ouvre aussi depuis l'application.
MD,
        ],
    ],
];
