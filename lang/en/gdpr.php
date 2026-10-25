<?php

// GDPR / privacy statement of the ERP platform (public page /gdpr). :company = the company, :site = the platform's name.

return [
    'title' => 'Privacy statement (GDPR) :site',
    'updated' => 'Last updated on :date',
    'language' => 'Language',
    'menu' => 'Privacy (GDPR)',
    'intro' => 'This statement explains which personal data :company processes in its ERP platform :site, why, for how long, with whom it is shared and which rights you have under the General Data Protection Regulation (GDPR). It applies to everyone whose data is in the platform: employees, users of the client portal, contact persons of clients, work sites and suppliers, and private clients.',
    'app_policy' => 'For the mobile app, the privacy policy of the app also applies:',
    'sections' => [
        'controller' => [
            'title' => '1. Controller and contact',
            'body' => 'The controller is:',
            'contact' => 'For all questions about privacy and to exercise your rights, contact:',
        ],
        'subjects' => [
            'title' => '2. Whose data we process',
            'items' => [
                'Employees (also temporary workers and interns) who have an account or are in the employee register.',
                'Users of the client portal, created at the request of our clients.',
                'Contact persons of clients, work sites (COW sites) and suppliers.',
                'Private clients for whom we carry out a project.',
            ],
        ],
        'data' => [
            'title' => '3. Which data we process',
            'items' => [
                'Identity and contact: name, e-mail address, phone number, address, job title, team, language and company.',
                'Employee register: date of birth, national register number, bank account number, driving licence, emergency contacts, clothing sizes, contract and category, certificates and trainings.',
                'Work: planning, timesheets (hours, kilometres, projects and sites), leave and absences, the use of vehicles, equipment and IT resources.',
                'Health: the medical certificate uploaded with an absence and, where needed, information in an incident report.',
                'Work documents: project files, photos of completed works, battery dossiers, as-builts, toolbox meetings and signatures with name and time.',
                'Reports and communication: incidents, ideas and complaints with their replies, GPW requests and the e-mails the platform sends.',
                'Client portal: account, permissions, sign-ins and the decisions the user takes (e.g. approving a GPW).',
                'Invoicing: client details, invoices, payments and incoming invoices.',
                'Technical data: sign-ins, time and IP address of the last use, the language and a session cookie.',
            ],
            'not' => 'We do not use data for advertising or profiling and do not sell any data.',
        ],
        'purposes' => [
            'title' => '4. Why and on which legal basis',
            'items' => [
                'Performance of the employment contract: planning, timesheets, leave, payroll and HR administration, work equipment and communication.',
                'Legal obligations: social and tax law, the well-being at work act (incidents, trainings, inspections, toolbox meetings), waste legislation and accounting law.',
                'Performance of contracts with clients and suppliers: projects, deliveries, follow-up, the client portal and invoicing.',
                'Legitimate interest: a safe and well-working platform, account security, quality follow-up and handling ideas and complaints.',
            ],
            'health' => 'We process health data (medical certificates) only to comply with employment and social security law. It is only visible to the employee concerned and the administration.',
        ],
        'sharing' => [
            'title' => '5. With whom we share data',
            'body' => 'Within :company, only those who need the data for their work see it (permissions per role and team). Users of the client portal only see the projects they were given access to, never employee data or prices. Where needed, we share data with:',
            'items' => [
                'our clients (e.g. Proximus), as far as needed to carry out their projects, such as names on work documents and signatures;',
                'service providers working for us (processors): hosting and e-mail of the platform, Microsoft 365 when the planning is synchronised with Outlook, Expo and Google Firebase for push notifications of the app, and a Peppol provider for electronic invoices;',
                'authorities and bodies when the law requires it (e.g. social secretariat, inspection bodies, waste processors).',
            ],
            'transfers' => 'When data ends up outside the European Economic Area in doing so, this only happens with appropriate safeguards, such as the standard contractual clauses of the European Commission.',
        ],
        'security' => [
            'title' => '6. Security',
            'items' => [
                'Access only with a personal account and password; permissions per role, team and module.',
                'All traffic is encrypted (HTTPS); sensitive data such as the national register number, bank account number, driving licence number and passwords of installations are stored encrypted.',
                'Files (certificates, documents, photos) are not public: only those entitled to them can open them through the platform.',
            ],
        ],
        'retention' => [
            'title' => '7. How long we keep data',
            'body' => 'We keep data as long as needed for the purpose it was collected for, and then as long as the law requires, for example the retention periods of social law (employee and payroll data), tax and accounting law (invoices) and the well-being at work act. We keep project files as long as they are needed for warranty and follow-up. A client portal user can delete their own account; employee accounts are closed at the end of employment.',
        ],
        'cookies' => [
            'title' => '8. Cookies',
            'body' => 'The platform only uses functional cookies: a session cookie to stay signed in and the protection against forged forms, and your language choice. There are no third-party cookies, no statistics and no advertising.',
        ],
        'rights' => [
            'title' => '9. Your rights',
            'body' => 'You have the right of access, rectification, erasure and restriction of your data, the right to object and the right to data portability, within the limits of the law. Send your request to the contact addresses above; we reply within one month. If you are not satisfied with how we handle your data, you can lodge a complaint with the Data Protection Authority, Drukpersstraat 35, 1000 Brussels (www.dataprotectionauthority.be).',
        ],
        'changes' => [
            'title' => '10. Changes',
            'body' => 'We adapt this statement when the platform or the law changes. The date at the top shows when it was last changed.',
        ],
    ],
];
