<?php

// GDPR / privacy statement of the ERP platform (public page /gdpr). :company = the company, :site = the platform's name.

return [
    'title' => 'Déclaration de confidentialité (RGPD) :site',
    'updated' => 'Dernière mise à jour le :date',
    'language' => 'Langue',
    'menu' => 'Confidentialité (RGPD)',
    'intro' => 'Cette déclaration explique quelles données à caractère personnel :company traite dans sa plateforme ERP :site, pourquoi, combien de temps, avec qui elles sont partagées et quels sont vos droits selon le Règlement général sur la protection des données (RGPD). Elle s\'applique à toute personne dont les données se trouvent dans la plateforme : collaborateurs, utilisateurs du portail client, personnes de contact des clients, des sites et des fournisseurs, et clients particuliers.',
    'app_policy' => 'Pour l\'application mobile, la politique de confidentialité de l\'application s\'applique en outre :',
    'sections' => [
        'controller' => [
            'title' => '1. Responsable du traitement et contact',
            'body' => 'Le responsable du traitement est :',
            'contact' => 'Pour toute question sur la confidentialité et pour exercer vos droits, adressez-vous à :',
        ],
        'subjects' => [
            'title' => '2. De qui nous traitons des données',
            'items' => [
                'Les collaborateurs (aussi les intérimaires et stagiaires) qui ont un compte ou figurent dans le registre du personnel.',
                'Les utilisateurs du portail client, créés à la demande de nos clients.',
                'Les personnes de contact des clients, des sites (sites COW) et des fournisseurs.',
                'Les clients particuliers pour lesquels nous réalisons un projet.',
            ],
        ],
        'data' => [
            'title' => '3. Quelles données nous traitons',
            'items' => [
                'Identité et contact : nom, adresse e-mail, numéro de téléphone, adresse, fonction, équipe, langue et société.',
                'Registre du personnel : date de naissance, numéro de registre national, numéro de compte bancaire, permis de conduire, personnes à contacter en cas d\'urgence, tailles de vêtements, contrat et catégorie, certificats et formations.',
                'Travail : planning, prestations (heures, kilomètres, projets et chantiers), congés et absences, l\'utilisation des véhicules, du matériel et des moyens informatiques.',
                'Santé : le certificat médical téléversé lors d\'une absence et, si nécessaire, des informations dans une déclaration d\'incident.',
                'Documents de travail : fichiers de projet, photos des travaux réalisés, dossiers batteries, as-builts, toolbox meetings et signatures avec nom et date.',
                'Signalements et communication : incidents, idées et plaintes avec leurs réponses, demandes de GPW et les e-mails envoyés par la plateforme.',
                'Portail client : compte, droits, connexions et les décisions prises par l\'utilisateur (p. ex. l\'approbation d\'un GPW).',
                'Facturation : données des clients, factures, paiements et factures entrantes.',
                'Données techniques : connexions, date et adresse IP de la dernière utilisation, la langue et un cookie de session.',
            ],
            'not' => 'Nous n\'utilisons pas de données pour de la publicité ou du profilage et ne vendons aucune donnée.',
        ],
        'purposes' => [
            'title' => '4. Pourquoi et sur quelle base légale',
            'items' => [
                'Exécution du contrat de travail : planning, prestations, congés, administration salariale et du personnel, moyens de travail et communication.',
                'Obligations légales : législation sociale et fiscale, loi sur le bien-être (incidents, formations, contrôles, toolbox meetings), législation sur les déchets et législation comptable.',
                'Exécution des contrats avec les clients et fournisseurs : projets, livraisons, suivi, portail client et facturation.',
                'Intérêt légitime : une plateforme sûre et fonctionnelle, la sécurité des comptes, le suivi de la qualité et le traitement des idées et plaintes.',
            ],
            'health' => 'Nous traitons les données de santé (certificats médicaux) uniquement pour respecter le droit du travail et de la sécurité sociale. Elles ne sont visibles que par le collaborateur concerné et l\'administration.',
        ],
        'sharing' => [
            'title' => '5. Avec qui nous partageons des données',
            'body' => 'Au sein de :company, seules les personnes qui en ont besoin pour leur travail voient les données (droits par rôle et par équipe). Les utilisateurs du portail client ne voient que les projets auxquels ils ont accès, jamais des données du personnel ni des prix. Si nécessaire, nous partageons des données avec :',
            'items' => [
                'nos clients (p. ex. Proximus), dans la mesure nécessaire à l\'exécution de leurs projets, comme les noms sur les documents de travail et les signatures ;',
                'les prestataires qui travaillent pour nous (sous-traitants) : l\'hébergement et le trafic e-mail de la plateforme, Microsoft 365 lorsque le planning est synchronisé avec Outlook, Expo et Google Firebase pour les notifications de l\'application, et un prestataire Peppol pour les factures électroniques ;',
                'les autorités et organismes lorsque la loi l\'exige (p. ex. secrétariat social, organismes de contrôle, collecteurs de déchets).',
            ],
            'transfers' => 'Lorsque des données quittent ainsi l\'Espace économique européen, cela se fait uniquement avec des garanties appropriées, comme les clauses contractuelles types de la Commission européenne.',
        ],
        'security' => [
            'title' => '6. Sécurité',
            'items' => [
                'Accès uniquement avec un compte personnel et un mot de passe ; droits par rôle, équipe et module.',
                'Tout le trafic est chiffré (HTTPS) ; les données sensibles comme le numéro de registre national, le numéro de compte bancaire, le numéro de permis de conduire et les mots de passe d\'installations sont stockées chiffrées.',
                'Les fichiers (certificats, documents, photos) ne sont pas publics : seuls ceux qui y ont droit peuvent les ouvrir via la plateforme.',
            ],
        ],
        'retention' => [
            'title' => '7. Combien de temps nous conservons les données',
            'body' => 'Nous conservons les données aussi longtemps que nécessaire pour la finalité de leur collecte, puis aussi longtemps que la loi l\'exige, par exemple les délais de conservation du droit social (données du personnel et salariales), du droit fiscal et comptable (factures) et de la loi sur le bien-être. Nous conservons les dossiers de projet tant qu\'ils sont nécessaires pour la garantie et le suivi. L\'utilisateur du portail client peut supprimer lui-même son compte ; les comptes des collaborateurs sont clôturés à la fin de l\'occupation.',
        ],
        'cookies' => [
            'title' => '8. Cookies',
            'body' => 'La plateforme utilise uniquement des cookies fonctionnels : un cookie de session pour rester connecté et la protection contre les formulaires falsifiés, et votre choix de langue. Il n\'y a pas de cookies de tiers, pas de statistiques et pas de publicité.',
        ],
        'rights' => [
            'title' => '9. Vos droits',
            'body' => 'Vous avez le droit d\'accès, de rectification, d\'effacement et de limitation de vos données, le droit d\'opposition et le droit à la portabilité, dans les limites de la loi. Envoyez votre demande aux adresses de contact ci-dessus ; nous répondons dans un délai d\'un mois. Si vous n\'êtes pas satisfait de la manière dont nous traitons vos données, vous pouvez introduire une plainte auprès de l\'Autorité de protection des données, rue de la Presse 35, 1000 Bruxelles (www.autoriteprotectiondonnees.be).',
        ],
        'changes' => [
            'title' => '10. Modifications',
            'body' => 'Nous adaptons cette déclaration lorsque la plateforme ou la législation change. La date en haut indique la dernière modification.',
        ],
    ],
];
