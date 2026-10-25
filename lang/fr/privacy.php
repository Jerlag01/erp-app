<?php

// Privacy policy of the mobile app (public page /privacy). :app = the app name, :company = the company.

return [
    'title' => 'Politique de confidentialité :app',
    'updated' => 'Dernière mise à jour le :date',
    'platform' => 'La déclaration de confidentialité de toute la plateforme ERP :',
    'language' => 'Langue',
    'intro' => 'Cette politique de confidentialité explique quelles données personnelles l\'application mobile :app et la plateforme ERP de :company traitent, pourquoi, combien de temps et quels sont vos droits. L\'application est destinée exclusivement aux collaborateurs de :company ; un compte est créé par l\'employeur.',
    'sections' => [
        'controller' => [
            'title' => '1. Responsable du traitement',
            'body' => 'Le responsable du traitement de vos données est :',
            'contact' => 'Des questions sur la vie privée ou vos droits ? Contactez-nous via :email.',
            'contact_hr' => 'Des questions sur la vie privée ou vos droits ? Contactez l\'administration ou le service du personnel.',
        ],
        'data' => [
            'title' => '2. Quelles données nous traitons',
            'items' => [
                'Données de compte : nom, adresse e-mail, numéro de téléphone, fonction, équipe et langue.',
                'Données du registre du personnel consultées dans l\'application, comme l\'adresse, les contacts en cas d\'urgence, les tailles de vêtements et le permis de conduire.',
                'Prestations et planning : heures, kilomètres, projets et chantiers, votre planning et celui des collègues que vous pouvez voir.',
                'Congés et absences : demandes de congé, heures supplémentaires en congé et déclarations de maladie, avec le certificat médical que vous téléversez vous-même (une donnée de santé).',
                'Documents de travail : le contrôle annuel du matériel, les dossiers batteries avec mesures et photos des travaux réalisés, et les toolbox meetings.',
                'Les signatures que vous apposez dans l\'application, avec votre nom et l\'heure.',
                'Vos signalements : incidents (avec photos et le lieu que vous choisissez) et envois dans la boîte à idées et plaintes.',
                'Données techniques : un nom et identifiant d\'appareil, l\'heure et l\'adresse IP de la dernière utilisation, et le jeton de notification.',
            ],
            'not' => 'L\'application n\'utilise pas la localisation GPS, ne lit pas les contacts et n\'utilise pas le microphone. Il n\'y a pas de publicité et aucune donnée n\'est vendue ni utilisée pour du profilage.',
        ],
        'purposes' => [
            'title' => '3. Pourquoi et sur quelle base',
            'items' => [
                'L\'exécution de votre contrat de travail : planning, prestations, congés, documents de travail et communication.',
                'Les obligations légales : législation sociale et fiscale (prestations, absences, certificats médicaux) et loi sur le bien-être au travail (signalements d\'incidents, toolbox meetings, contrôle du matériel).',
                'L\'intérêt légitime de :company : une application sûre et fiable, la sécurité des comptes et le suivi des idées et plaintes.',
            ],
            'health' => 'Un certificat médical est traité uniquement pour respecter les obligations du droit du travail et de la sécurité sociale et n\'est visible que par vous-même et l\'administration.',
        ],
        'permissions' => [
            'title' => '4. Autorisations sur votre appareil',
            'items' => [
                'Appareil photo : pour photographier un certificat, des travaux réalisés ou un incident — uniquement lorsque vous appuyez vous-même sur le bouton.',
                'Photos et fichiers : pour choisir une photo ou un PDF existant à téléverser.',
                'Notifications : pour vous avertir des changements de planning, des nouveaux toolbox meetings, des contrôles et des décisions sur les congés.',
            ],
            'revoke' => 'Vous pouvez toujours retirer ces autorisations dans les réglages de votre appareil ; l\'application continue de fonctionner, sans cette fonction.',
        ],
        'sharing' => [
            'title' => '5. Avec qui nous partageons les données',
            'body' => 'Vos données restent au sein de :company et ne sont consultées que par les personnes qui en ont besoin pour leur travail (par exemple votre responsable ou l\'administration). Pour l\'envoi des notifications, nous utilisons Expo (650 Industries, Inc.) et Google Firebase Cloud Messaging ; ils reçoivent uniquement le jeton de notification de votre appareil et le texte de la notification. L\'application est distribuée via Google Play ou via la plateforme elle-même. Lorsque des données sont ainsi transférées hors de l\'Union européenne, cela se fait avec des garanties appropriées, comme les clauses contractuelles types de la Commission européenne.',
        ],
        'security' => [
            'title' => '6. Conservation et sécurité',
            'items' => [
                'Les données sont conservées sur le serveur ERP de :company et toujours transmises de manière chiffrée (HTTPS).',
                'Sur votre appareil, l\'application ne conserve qu\'une clé de connexion, dans le stockage sécurisé de l\'appareil ; le serveur n\'en garde qu\'une version illisible (hachée).',
                'Les données sensibles comme le numéro de registre national, le numéro de compte bancaire et le numéro de permis de conduire sont stockées de manière chiffrée.',
            ],
        ],
        'retention' => [
            'title' => '7. Combien de temps nous conservons les données',
            'body' => 'Nous conservons les données aussi longtemps que nécessaire pour votre emploi, puis aussi longtemps que la loi l\'exige (par exemple les délais de conservation du droit social et fiscal). Une clé de connexion de l\'application est valable un an et est révoquée dès que vous vous déconnectez ou que l\'accès est arrêté ; le jeton de notification n\'est alors plus utilisé et disparaît lorsque l\'application est supprimée.',
        ],
        'rights' => [
            'title' => '8. Vos droits',
            'body' => 'Vous avez le droit d\'accès, de rectification, d\'effacement et de limitation de vos données, le droit d\'opposition et le droit à la portabilité, dans les limites de la loi. Adressez votre demande aux coordonnées ci-dessus. Si vous n\'êtes pas satisfait de la manière dont nous traitons vos données, vous pouvez introduire une plainte auprès de l\'Autorité de protection des données, rue de la Presse 35, 1000 Bruxelles (www.autoriteprotectiondonnees.be).',
            'delete' => 'Votre compte dans l\'application est géré par votre employeur : si vous souhaitez que votre compte et vos données soient supprimés, demandez-le à l\'administration.',
        ],
        'changes' => [
            'title' => '9. Modifications',
            'body' => 'Nous pouvons adapter cette politique de confidentialité, par exemple lorsque l\'application reçoit de nouvelles fonctions. La date en haut indique la dernière modification.',
        ],
    ],
];
