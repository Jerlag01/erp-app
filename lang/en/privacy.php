<?php

// Privacy policy of the mobile app (public page /privacy). :app = the app name, :company = the company.

return [
    'title' => ':app privacy policy',
    'updated' => 'Last updated on :date',
    'platform' => 'The privacy statement of the whole ERP platform:',
    'language' => 'Language',
    'intro' => 'This privacy policy explains which personal data the :app mobile app and the ERP platform of :company process, why, for how long and what your rights are. The app is intended only for employees of :company; accounts are created by the employer.',
    'sections' => [
        'controller' => [
            'title' => '1. Data controller',
            'body' => 'The controller of your data is:',
            'contact' => 'Questions about privacy or your rights? Contact us at :email.',
            'contact_hr' => 'Questions about privacy or your rights? Contact the administration or HR department.',
        ],
        'data' => [
            'title' => '2. Which data we process',
            'items' => [
                'Account data: name, e-mail address, phone number, job title, team and language.',
                'Data from the employee register that you view in the app, such as your address, emergency contacts, clothing sizes and driver license.',
                'Prestations and planning: hours, kilometres, projects and sites, your planning and that of colleagues you may see.',
                'Leave and absences: leave requests, overtime taken as leave and sickness reports, with the medical certificate you upload yourself (health data).',
                'Work documents: the yearly equipment inspection, battery dossiers with measurements and photos of completed works, and toolbox meetings.',
                'Signatures you place in the app, with your name and the time.',
                'Reports you make: incidents (with photos and the place you choose) and entries in the idea and complaint box.',
                'Technical data: a device name and id, the time and IP address of the last use, and the push token for notifications.',
            ],
            'not' => 'The app does not use GPS location, does not read contacts and does not use the microphone. There is no advertising and no data is sold or used for profiling.',
        ],
        'purposes' => [
            'title' => '3. Why, and on which legal basis',
            'items' => [
                'Performing your employment contract: planning, prestations, leave, work documents and communication.',
                'Legal obligations: social and tax law (prestations, absences, medical certificates) and the law on well-being at work (incident reports, toolbox meetings, equipment inspections).',
                'The legitimate interest of :company: a safe and reliable app, account security and following up ideas and complaints.',
            ],
            'health' => 'A medical certificate is processed only to meet the obligations of employment and social security law and is visible only to you and the administration.',
        ],
        'permissions' => [
            'title' => '4. Permissions on your device',
            'items' => [
                'Camera: to photograph a certificate, completed works or an incident — only when you press the button yourself.',
                'Photos and files: to choose an existing photo or PDF to upload.',
                'Notifications: to tell you about changes in your planning, new toolbox meetings, inspections and leave decisions.',
            ],
            'revoke' => 'You can withdraw these permissions at any time in your device settings; the app keeps working, without that function.',
        ],
        'sharing' => [
            'title' => '5. Who we share data with',
            'body' => 'Your data stay within :company and are seen only by those who need them for their work (for example your manager or the administration). To deliver push notifications we use Expo (650 Industries, Inc.) and Google Firebase Cloud Messaging; they receive only your device\'s push token and the text of the notification. The app is distributed through Google Play or through the platform itself. Where data are transferred outside the European Union in doing so, this happens with appropriate safeguards, such as the standard contractual clauses of the European Commission.',
        ],
        'security' => [
            'title' => '6. Storage and security',
            'items' => [
                'The data are stored on the ERP server of :company and always sent encrypted (HTTPS).',
                'On your device the app only keeps a sign-in key, in the device\'s secure storage; the server only keeps an unreadable (hashed) version of it.',
                'Sensitive data such as the national number, bank account number and driver license number are stored encrypted.',
            ],
        ],
        'retention' => [
            'title' => '7. How long we keep data',
            'body' => 'We keep data as long as needed for your employment and afterwards as long as the law requires (for example the retention periods of social and tax law). An app sign-in key is valid for one year and is revoked as soon as you sign out or access is stopped; the push token is then no longer used and disappears when the app is removed.',
        ],
        'rights' => [
            'title' => '8. Your rights',
            'body' => 'You have the right of access, rectification, erasure and restriction of your data, the right to object and the right to data portability, within the limits of the law. Send your request to the contact details above. If you are not satisfied with how we handle your data, you can lodge a complaint with the Belgian Data Protection Authority, Rue de la Presse 35, 1000 Brussels (www.dataprotectionauthority.be).',
            'delete' => 'Your app account is managed by your employer: if you want your account and data deleted, ask the administration.',
        ],
        'changes' => [
            'title' => '9. Changes',
            'body' => 'We may update this privacy policy, for example when the app gets new features. The date at the top shows when it was last changed.',
        ],
    ],
];
