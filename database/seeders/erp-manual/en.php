<?php

/*
 * ERP app manual, English. Same keys as nl.php (see ErpManualSeeder).
 */

return [
    'category' => [
        'name' => 'ERP app manual',
        'description' => 'How the platform and each module work: from signing in to invoicing, for employees and administrators.',
    ],

    'articles' => [
        'getting-started' => [
            'title' => 'Getting started: signing in, menu and language',
            'body' => <<<'MD'
## What is the platform for?

The ERP platform brings everything of the company together: projects, planning, timesheets, leave, equipment, stock, safety, finance and more. Each part is a **module**; the administrator decides which modules are switched on.

## Signing in

- You get a **welcome mail** with a link to choose your own password. The link works for 7 days and only once.
- After that you always sign in with your **e-mail address and password**.
- Forgot your password? Click **"Forgot password?"** on the sign-in page: you get a mail with a new link.

## Two sides: employees and administration

- **Employee side** (the address without /admin): your planning, prestation sheet, leave, toolbox meetings, reporting an incident, the idea box, the knowledge base and the modules you have permissions for.
- **Administration side** (/admin): for administrators only. It also holds the settings, roles, users, e-mail templates and the client portal.
- Administrators switch between both through the **user menu** at the top right.

## The screen

- **Left menu**: by group (Personnel, Projects, Planning, Safety, …). You only see what you may use.
- **Top bar**: search, the **idea box**, possibly an **announcement** from management, the **bell** with your notifications and your **user menu**.
- **Dashboard**: tiles and lists that matter to you (controls that expire, low stock, open tasks, …).

## Language and profile

- Choose **Dutch, French or English** with the language switch next to your user menu. Your choice also applies to the mails you receive and to the mobile app.
- In the user menu → **Profile** you change your name, e-mail address or password.

## Two-step verification

- In **Profile** you turn on a second step when signing in: an **authenticator app** (Microsoft Authenticator, Google Authenticator, Authy, 1Password, …) with which you scan a QR code, and/or a **code by e-mail**.
- Keep the **recovery codes** somewhere safe: each code works once when you do not have your phone at hand.
- From then on the platform asks for the code after your password, also in the mobile app and on the client portal.
- Lost access to your codes? An administrator with the permission **Security → Reset two-step verification** turns it off (Admin → Users or Client users). That permission must be ticked separately, also for administrators.

## Notifications

The bell at the top right shows the platform's notifications: a change in your planning, an approved leave request, a new toolbox meeting, … Click a notification to go straight to the right page.

## On your phone

On your phone, preferably use the **PI ERP mobile app** (see the article "Mobile app"). On an Android phone the platform offers to download the app after you sign in.
MD,
        ],

        'roles-users' => [
            'title' => 'Users, roles, permissions and teams',
            'body' => <<<'MD'
## What is it for?

What someone sees and may do depends on their **role** and their **team**.

## Where?

- **Administration → Organisation → Users**: the accounts.
- **Administration → System → Roles**: the roles and their permissions.
- **Organisation → Teams**: the teams and their members.

## Roles and permissions

- A role is a set of **permissions per area**, e.g. *Projects: view, create, edit* or *Leave: approve*.
- An **administrator role** automatically has all permissions (except a few confidential ones, such as the passwords of 4G routers, which you tick explicitly).
- On a user you can still **adjust the permissions of their role for that person only**.
- Permissions of a module that is switched off have no effect.

## Teams

- Employees are divided into **teams** with a team leader.
- An employee **without a team** is a *guest*: they have no permissions, whatever their role.
- Teams also decide what you see: you see a project if you are its project leader or if your team is assigned to it; you also see the planning of your team mates.

## Creating an account

- Through **Personnel → Employee register** (adding an employee also creates their login) or through **Organisation → Users**.
- Leave the password empty and keep **"Send welcome mail"** switched on: the employee chooses their own password.
- **"Send welcome mail"** in the list sends the link again.

## Tips

- Give permissions through roles rather than per person: that stays clear.
- Someone left the company? Set their account to **inactive**; their data is kept.
MD,
        ],

        'settings' => [
            'title' => 'Settings, modules and e-mail templates',
            'body' => <<<'MD'
## Where?

**Administration → System → Settings** and **Administration → System → E-mail templates**. Administrators only.

## Settings

The settings are spread over tabs:

- **General**: name of the platform, company name, reminders (how many days in advance you are warned of due dates).
- **Modules**: switch each module on or off. A module that is off disappears from the menu for everyone; its data is kept.
- **Email**: the mail server the platform sends with. **"Send test email"** checks the settings.
- **Appearance**: logo, favicon and colour.
- **Mobile app**: the APK, the suggestion on phones and the privacy policy.
- And per module where needed: **Finance**, **Prestations**, **Batteries**, **Planning**, …

## E-mail templates

- Every e-mail the platform sends (welcome, invoice, reminder, planning, prestation sheet, GPW request, …) has a **template per language** (NL, FR, EN).
- Adjust the subject and text; the **placeholders** in braces, e.g. `{name}` or `{project}`, are filled in when the mail is sent. The list is shown with each template.
- **Preview** shows the mail as it will be sent; **Standard text** puts back the original text.
- The mail always goes in the **recipient's language**.

## Tips

- Only switch a module on when its master data is ready (e.g. categories, locations).
- After changing the logo or colour, the page reloads by itself.
MD,
        ],

        'announcements' => [
            'title' => 'Announcements',
            'body' => <<<'MD'
## What is it for?

A short message for **all employees**, in the top bar left of the idea box. For example: "The office is closed on Friday".

## Where?

**Organisation → Announcement** (on the employee side and the administration side).

## How it works

1. Switch on **"Show announcement"**.
2. Choose a **colour**: blue (info), green, orange (attention) or red (urgent).
3. Enter the message, at most 200 characters, in **Dutch** (required) and optionally in **French** and **English**. Without a translation, French and English speaking employees see the Dutch text.
4. Click **Save**.

Those who may edit the announcement see a **pencil** in the bar to edit it straight away. At the bottom of the page you see who last changed it.

## Permissions

Administrators, and employees with the permission **Announcements → Edit**. The **Announcements** module must be switched on.
MD,
        ],

        'locations' => [
            'title' => 'Locations',
            'body' => <<<'MD'
## What is it for?

The company's **premises** (e.g. head office, warehouse) with their address. Teams, vehicles, equipment and stock are linked to a location.

## Where?

**Organisation → Locations**.

## How it works

- Create a location with a name and address.
- Its detail page shows the **teams, vehicles, equipment and stock** of that location.
- Warehouse stock is kept **per location**; without a location there is no stock.

## Permissions

Viewing, creating, editing and deleting locations are separate permissions.
MD,
        ],

        'teams' => [
            'title' => 'Teams',
            'body' => <<<'MD'
## What is it for?

Dividing employees into **teams** with a **team leader**, linked to a location.

## Where?

**Organisation → Teams**.

## How it works

- Create a team, choose a colour, a team leader and a location, and add the members.
- Teams are used throughout the platform:
  - **Projects**: assigning a team to a project gives its members access to that project.
  - **Planning**: you see your team mates' planning; a GPW period is planned per team.
  - **Equipment**: equipment and vehicles per team, and the yearly control per team.
  - **Stock**: the stock of your team's location.

## Important

An employee without a team is a **guest** and has no permissions. So always add new employees to a team.
MD,
        ],

        'employees' => [
            'title' => 'Employee register and my details',
            'body' => <<<'MD'
## What is it for?

All personnel data in one place: personal details, employment, emergency contacts, clothing sizes, driving licence, certificates and the medical check-up.

## Where?

- **Personnel → Employee register**: for the administration.
- **Personnel → My details**: every employee sees their own details here.
- **Personnel → Training & medical check-ups**: who has which training and when something expires.

## Adding an employee

1. Click **New** in the employee register.
2. Fill in the tabs: personal details, employment, emergency contacts, clothing sizes, driving licence.
3. Under **Login account** the login is created and the **welcome mail** goes out with a link to choose a password.

## Driving licence

Choose one or more **categories** (B, BE, C, …), the number and expiry date, and upload a copy (photo or PDF of front and back).

## Certificates and medical check-up

- Certificates (e.g. VCA, BA4/BA5) with **expiry date** and PDF. The dashboard warns in time before they expire.
- The **yearly medical check-up** is kept per employee.
- Employees can submit a certificate themselves; the administration approves it under **Submitted certificates**.

## Changing your details as an employee

An employee requests a change (e.g. a new address) through **My details**. The administration confirms it under **Changed details**.

## Badges, electronic keys and security tags

- **Badges & electronic keys**: per employee one line with the **badge number** (7 digits) and/or the **electronic key** (4 digits), whether the employee has **CLAD permission** and which **clearance**. A number belongs to one employee.
- **Security tags**: per employee the **tag** (2 digits), the **name in the security system**, access to the **warehouse** and the **office**, and whether the employee received **door keys**.
- Employees who left stay in both lists as **Deactivated** (tab *Deactivated*).
- Adding and changing with the permission **Badges, electronic keys & security tags**; deleting only with the permission *Delete*. Both lists are exported as PDF (permission *Generate PDF*).

## Privacy

Sensitive data such as the national register number is stored **encrypted** and only visible with the right permissions.
MD,
        ],

        'onboarding' => [
            'title' => 'Onboarding',
            'body' => <<<'MD'
## What for?

New users first read the **onboarding documents** (e.g. work rules, safety rules) and sign that they read them, before they use the platform.

## Documents

- Under **Onboarding documents** you upload one or more files at once with a **language** (Dutch, French or English). Each file becomes a separate document; title, order and language can be changed afterwards.
- An employee only sees the documents in **their own language** (that of their profile).

## For the employee

- A **new user** first lands on the **Onboarding** page after signing in: every other page leads there until everything is signed.
- Per document: **Open** and then **Sign** (name and signature). The signature is kept with the date and time.
- Users who were there when the module came count as onboarded.

## Follow-up

Under **Onboarding: signatures** you see who opened and signed which document, with the signature; the list is exported as PDF. This needs the onboarding permissions **View signatures** and *Generate PDF*; managing documents needs **Manage**.
MD,
        ],

        'leave' => [
            'title' => 'Leave, overtime and absences',
            'body' => <<<'MD'
## What is it for?

Requesting and approving leave, taking overtime as paid leave, reporting sickness with a certificate, and the public holidays and IR days.

## Where?

- **Personnel → My vacation**: your requests and your balance.
- **Personnel → My absences**: your sickness and other absences.
- **Personnel → Vacation requests**: approving or refusing (with the permission).
- **Personnel → Vacation calendar**: who is away when.
- **Personnel → Absences**, **Public holidays** and **IR days**: for the administration.

## Requesting leave

1. Go to **My vacation** and click **Request vacation**.
2. Choose the **type**: ordinary leave or **overtime as leave**.
3. Choose the period (possibly half a day). You see straight away how many days it counts and your balance afterwards; weekends and public holidays don't count.
4. Send the request. Those who may approve it get a notification.

A request that hasn't been handled yet can be **withdrawn**.

## Overtime as leave

With the **Timesheets** module your overtime is tracked. The request shows how many hours you can still take. These requests are also approved by someone with the permission.

## Approving

Under **Vacation requests** you approve a request or refuse it (with a reason). The employee gets a notification. Approved days are **blocked in the planning** and filled in automatically in the **prestation sheet**.

## Reporting sickness

- Through **My absences → Report sickness** (or in the mobile app). The administration is warned straight away.
- Add the **certificate** as a photo or PDF, now or later.
- Under **Absences** the administration sees which sickness periods are still **without a certificate**.
- Only sickness needs a certificate: **overtime as leave**, **family leave** and **unpaid leave** have no upload ("Not needed").
- **Justified absence (GA)** is added by administrators only, with a **document** (photo or PDF) as proof. When creating it you pick one or more **separate periods**: each period becomes its own absence with the same document. The prestation sheet shows the code **GA**, without hours. The employee can only download the document.

## Tip

Colleagues only see "absent" in the calendar, never the reason for a sickness.
MD,
        ],

        'timesheets' => [
            'title' => 'Timesheets (prestation sheet)',
            'body' => <<<'MD'
## What is it for?

The daily **prestation sheet**: hours, kilometres and projects per day, with a weekly PDF by e-mail.

## Where?

- **Personnel → Prestation sheet**: your own sheet (also in the mobile app).
- **Personnel → Prestations overview** and **Prestations per month**: for those who may see all sheets.

## Filling in a day

1. Open the day in your prestation sheet.
2. Per line: **start at the warehouse**, **work on the COW site**, possibly **extra sites**, and **back at the warehouse / home**, with times and odometer readings.
3. Choose the project under **Imputation** and fill in the **description** (COW code or site).
4. **"Fill in from planning"** puts in the projects of your planning for that day.
5. The **lunch break** is deducted from the hours worked.

Only the start, the site and the end of the day count for the hours; extra sites in between don't.

## Filled in automatically

Leave, sickness, trainings and public holidays are put in the sheet **automatically**.

## The week

- **Week settings** hold the hours to work and the kilometres and overtime **carried over** from the previous week.
- The **week PDF** is mailed automatically every week to the administration and to the employee. With the permission you can also send a week manually.

## Overtime

What you work beyond the expected hours goes to your **overtime balance**, which you can take as leave (see "Leave, overtime and absences").

## Salaried employees (bediende)

Employees with the statute **bediende** (employee register) fill in no hours and no kilometres: every working day counts **8 hours**. Half a day of leave leaves 4 worked hours; leave, sickness, training and holidays count as for everyone, family and unpaid leave count 0. The imputation, remarks and sending stay the same. In the mobile app they have no Prestations tab.
MD,
        ],

        'hr-calculations' => [
            'title' => 'HR calculations: advances',
            'body' => <<<'MD'
## What is it for?

Calculating the **monthly advance** of workers and servants.

## Where?

**Personnel → HR calculations: advances**. Only for roles with the permission **HR calculations → View**; administrators don't get it automatically, it has to be ticked.

## The calculation

Advance = **start value × days worked ÷ working days** of the period (weekends not counted).

- Only employees of the **employee register**, in service and **not on interim**.
- Start value: **€620** for workers, **€1000** for servants (and managers).
- Period: by default the **1st up to and including the 15th** of the month.
- **Counted as worked**: normal prestations, sickness, public holidays, IR days, overtime recuperation and trainings.
- **Not counted**: unpaid leave, family leave and approved vacation. If there is only **one vacation day** in the period, it does count as worked.

## Everything can be changed

- Choose the **month** and adjust the **period** (from – until).
- Adjust the **start value** per statute, or per employee in the list.
- With the permission **HR calculations → Manage** you save the start values and the last day of the period as **default** for the next months.
- Export the overview to **PDF**.

Unpaid leave is registered under **Personnel → Absences** (type *Unpaid leave*).
MD,
        ],

        'planning' => [
            'title' => 'Planning and trainings',
            'body' => <<<'MD'
## What is it for?

A planning calendar per employee: who works when on which project, in the warehouse or on something else.

## Where?

- **Planning → Planning**: the calendar (week or month, as a calendar or a list).
- **Planning → Trainings**: planning trainings.

## What do you see?

- Always **your own planning**, that of your **team mates** and of colleagues who share theirs with you.
- Project leaders also see the planning on their projects.
- Planners and administrators see everyone.

## Planning someone in

1. Click **Plan** (or a day next to an employee).
2. Choose one or more **employees**, the date and optionally **until** (one item per day; weekends only if you tick that).
3. Choose a **time slot** or your own hours.
4. Choose the **kind**: a **project** (open projects only: to be planned, planned, to be executed or in progress), **warehouse work** (description and tasks) or **something else**.
5. Optionally tick **"Also notify by e-mail"**.

Days with **leave, an absence or a training** are blocked; overlapping time slots are skipped and reported.

## Notifications

Every change in your planning is notified in the platform and on your phone (mobile app), and optionally by e-mail.

## Outlook calendar

When the Microsoft 365 link is on (**Settings → Planning → Outlook calendar**), every planning item is also put in the employee's **Outlook calendar**, as a **full day** (without hours), with the project, address and remarks. When the item is changed, moved to a colleague or removed, that happens in Outlook too.

**Absences** (sickness, overtime as leave, family leave, unpaid leave, justified absence), **public holidays**, **IR days** and **trainings** go to Outlook too, as whole days "out of office". An absence is in the employee's calendar (sickness, family and unpaid leave and justified absence as *private*), a holiday or IR day in every active employee's, a training in its participants' (with the place and a reminder the evening before). Changes and removals follow in Outlook. After switching the link on, `php artisan planning:sync-outlook` puts everything from today in the calendars.

For a **604 project**, the planning and Outlook show the batteries that go out and come in instead of the project description, e.g. *2 x 500Ah OPzS -> 2 x 490Ah OPzV* (from the battery dossier; while there is no dossier, only the new batteries of the BAE order). When the dossier is filled in or changed later, the planned Outlook events are updated.

## Permissions

- **Plan everyone** (projects and warehouse), **plan other items**, **see everyone's planning** and **plan trainings** are separate permissions.
- Project leaders plan their own projects.
- Whoever requested a **GPW** plans a team within the approved period once it is approved (see "GPW requests").
MD,
        ],

        'fleet' => [
            'title' => 'Fleet',
            'body' => <<<'MD'
## What is it for?

An overview of all **vehicles** with their details and inspections.

## Where?

**Fleet → Vehicles**.

## How it works

- Register per vehicle the **number plate, brand, type, VIN, year, fuel, mileage** and the **inspection dates**, optionally with a photo.
- Link the vehicle to a **location**, a **team** and a **driver**.
- The **dashboard** warns in advance when an inspection is due (the number of days is set under Settings → General).

## Permissions

Viewing, creating, editing and deleting are separate permissions.
MD,
        ],

        'assets' => [
            'title' => 'Equipment and the yearly control',
            'body' => <<<'MD'
## What is it for?

Keeping track of electrical work equipment and other equipment, with inspections, a log of damage and repairs, and a yearly control per team.

## Where?

- **Equipment → Equipment**: all pieces of equipment.
- **Equipment → Damage & repairs**: the log.
- **Equipment → Yearly control**: the control lists per team.
- **Equipment → New equipment to confirm**: equipment that teams added themselves.
- **Equipment → Categories**.

## Equipment

Each piece has a category, a serial number, a status, a place (team, vehicle, person or location) and an **inspection date**. The dashboard shows inspections that are due soon. Damage and repairs are noted in the **log**.

## The yearly control

1. An administrator **publishes** the yearly control for one or more teams. Each team gets a list of its equipment (including the equipment on its vehicles) and a notification.
2. The team fills in the **result** per piece, with a remark, on the computer or in the **mobile app** (Checks tab).
3. Equipment the team has that isn't on the list is added with **"Add equipment"**. An administrator confirms it under *New equipment to confirm*.
4. **Add new equipment** completes an open list; **Close** closes the list (the results are kept).

## Permissions

Viewing, creating, editing, deleting, and publishing or closing the yearly control are separate permissions.
MD,
        ],

        'orders' => [
            'title' => 'Order references',
            'body' => <<<'MD'
## What is it for?

Internal **order references** for purchases, such as `466/1026/JL`, so that each number is only used once.

## Where?

**Purchasing → Order references**.

## How it works

- Click **"+1 New order reference"** to take the next free number. It is put in your name straight away (with your initials).
- Fill in what it is for: **supplier**, **project** and description.
- In the list you look up which number was used by whom and for what.
MD,
        ],

        'stock' => [
            'title' => 'Warehouse and stock',
            'body' => <<<'MD'
## What is it for?

The **items** in the warehouse with their stock **per location**, QR labels, manufacturers, distributors, categories and units.

## Where?

**Stock → Items**, with the master data: **Stock categories**, **Units**, **Manufacturers**, **Distributors** and their **Contact persons**.

## Keeping stock

On an item, choose the location and then:

- **Add**: stock comes in.
- **Take**: you take something from the warehouse (optionally with a note, e.g. the site).
- **Count**: enter what is really there; the difference is logged. That is how you do a **stocktake**.

Every change is in the **history**. Items below the minimum stock appear under **Low stock** on the dashboard.

## QR labels

Print a **QR label** per item or several at once. Scanning the label opens the item.

## Prices and pictures

- With a distributor that has a price link, **"Update price from distributor"** fetches the current price (a distributor or manufacturer reference is needed).
- Add a product photo with **"Image from web address"**.
- The distributors' special prices are only visible with the permission **Prices (special distributor prices) → View**.
MD,
        ],

        'it' => [
            'title' => 'IT management',
            'body' => <<<'MD'
## What is it for?

All IT assets, as in Snipe-IT: **hardware, licenses, accessories, consumables and components**, with check-out and check-in, audits and maintenance.

## Where?

- **IT → Hardware, Licenses, Accessories, Consumables, Components, Maintenance**.
- Master data: **Models, IT categories, IT manufacturers, IT suppliers, Status labels**.
- **IT → My IT**: every employee sees what is checked out to them.

## How it works

- **Check out** to a user, a location or another device, optionally with an *expected back* date. **Check in** when it comes back.
- **Licenses** have seats that you assign; you see how many are free and which licenses expire soon.
- **Components** are installed in a device; **consumables** are handed out.
- An **audit** confirms a device was seen and plans the next one.
- Under **Maintenance** you note repairs and upgrades.

## Permissions

Per kind (hardware, licenses, other items) there are permissions to view, create, edit, delete, check out and audit. License product keys are only visible with the permission **View product keys**.
MD,
        ],

        'work-sites' => [
            'title' => 'Work sites (COW codes)',
            'body' => <<<'MD'
## What is it for?

The client's work sites, each with a **COW code** (e.g. `02GAM`), address, building type, area, contact person, photos and remarks.

## Where?

**Work sites → Work sites**, **Areas** and **Contact persons**.

## Work sites

- Search by COW code, street or municipality; open the address in **Waze** or **Google Maps**.
- Change the **building type** with **"Change building type"**: each change is kept with date and reason in the **log**.
- Add **photos** and **remarks** (remarks with the right permission).

## Areas and contact persons

- An **area** groups work sites and has **one or more contact persons**.
- A work site can have its own contact person. If it has none, the contact persons of its area apply.
- A contact person has a **language** (NL, FR or EN): mails such as a GPW request go in that language.

## Where is it used?

Projects get their COW code from the work site (e.g. `604-0001-02GAM`). Charging stations, routers, interventions and GPW requests use the same work sites and contact persons.
MD,
        ],

        'projects' => [
            'title' => 'Projects',
            'body' => <<<'MD'
## What is it for?

All projects per **category** (600, 604, 605, 606, 608, 610, …) with their PO numbers, files, photos, plans, schematics, parts list, offers and invoices.

## Where?

**Projects → Projects**; the categories under **Projects → Project categories** (administration).

## Project number

- The number follows the category: category, sequence number and COW code, e.g. `604-0001-02GAM`; numbered per year like `605-260001-02GAM`; private without COW code, e.g. `606-0001`.
- Leave the number empty for the **next free number**. On a category the administrator can set the **next number**; on an existing project the number can be corrected.

## Status

Offer → Waiting for PO → To be planned → Planned → To be executed → In progress → Executed → Invoicing to do → Invoiced → Closed. Change the status with **"Change status"**. A **closed** project is archived with a ZIP of all its files.

## On a project

- The client's **PO numbers** (10 digits, several possible).
- Tabs for **documents, photos, plans, schematics, extra files, parts, offers and invoices**. Files have versions.
- **Project leaders** and **assigned teams** decide who sees the project.
- For 610–650 a **progress bar** shows the steps (request, site visit, offer, PO, … up to handed over); tick steps yourself or they are ticked automatically. Once **PO received** is ticked, **"Add PO number"** adds a PO number at any moment (ticking the step without a PO number opens that window straight away).
- **Copy** starts a new project from this one.
- For 608 you choose the **location**: a COW code or a **third-party client** with its own installation address.
- From a project you request a **GPW** (see "GPW requests").

## Permissions

Separate permissions for viewing, creating, editing, deleting, files, parts, PDFs, copying and requesting a GPW. Offers and invoices are only visible with the project finance permissions or as project leader.
MD,
        ],

        'gpw' => [
            'title' => 'GPW requests',
            'body' => <<<'MD'
## What is it for?

Asking the client for permission to work on a site during a given **period**, and planning a team in that period once it is approved.

## Requesting a GPW

1. Open a project with the status **To be executed**.
2. Click **"Request GPW"**. You see who the request goes to.
3. Choose the **period** (from – until) and optionally add **remarks** (access, keys, outages).
4. Click **Send request**.

The request is e-mailed to the **contact person(s) of the COW site** (or of its area), each in **their own language**, with you in copy. If the site has no contact person with an e-mail address, fill one in on the work site or area first.

## The client approves

- Through the **link in the mail**: the client sees the request and clicks **Approve** or **Refuse** (with a reason).
- Or in the **Global overview for Client** (the client portal), **GPW requests** tab.

Once approved, the project goes to **To be planned** and you get a notification in the platform, on your phone and by e-mail.

## Planning

In the project's **GPW requests** tab, click **Plan** on the approved request: choose a **team**, the days (only within the approved period) and the hours. All team members are put in the planning; the project goes to **Planned**, and the client sees that with the period in their portal.

## Permissions and templates

- Requesting: the permission **Projects → Request GPW**.
- The mails (request, approved, refused) are edited under **E-mail templates**.
MD,
        ],

        'interventions' => [
            'title' => 'Interventions (600)',
            'body' => <<<'MD'
## What is it for?

Interventions on a COW code in category **600**, with a **WO number**, intervention categories, a closing report and a review by the administrator.

## Where?

- **Interventions → Interventions**: the list.
- **Interventions → Reviews** and **Intervention categories**: on the administration side.

## Creating an intervention

1. Create a project in category **600** (or click *New intervention*). It starts as **To be executed** straight away.
2. Fill in the **WO number** (unique), the COW site, the **intervention categories** and the subcategory with phase and tags.
3. Administrators and those with the permission can enter a **PO number** when creating it, and at any later moment with **"Add PO number"**.

## Closing

The team closes the intervention with a **closing report**: **material** used and freed, the **fuse/breaker positions**, boards, hours and co-workers. The intervention then goes to review.

## Review

The administrator **approves** with the PO number (a PO number of the project is proposed) or **sends it back** with a reason. Once approved, the project goes to **Invoicing to do** and the **PDF** is made.
MD,
        ],

        'batteries' => [
            'title' => 'Battery dossiers (604)',
            'body' => <<<'MD'
## What is it for?

The complete dossier for replacing batteries on **604 projects**: input, delivery note, measurement report, visual inspection report, floor plan, single-line diagram, shipping note and invoice request, all as PDF.

## Where?

- **Batteries → Battery dossiers**, or through the **Battery dossier** button on a 604 project.
- **Batteries → Battery deliveries**: the deliveries per project.
- **Batteries → Battery models** and **Frötek cables**: the master data.

## How it works

1. Open the dossier from the project; the project details and PO number are filled in.
2. Fill in the tabs: **input** (battery type, quantities, cables), the **measurements** and the **visual inspection**.
3. Have the dossier **signed** (on the computer or in the mobile app, with a finger).
4. Generate the **PDFs** of the various documents.

## Deliveries

Under **Battery deliveries** you follow up per project the order confirmation, type, quantity and planned delivery date, and tick **delivered**.

In the client portal the client sees per delivery whether it is **delivered** with the **actual delivery date** (the day of the tick), when the **installation is planned** (the execution date of the battery dossier, or else the first day in the planning) and an **executed** check mark with the date the project went to *executed*.

With **Documents** you add three files per delivery: the **order** to the supplier, the supplier's **order confirmation** and the **delivery note** on arrival. They are also on the project (tab *Battery deliveries*). They are internal: never visible in the client portal, and only with the permissions **See and download delivery documents** and **Upload delivery documents**.

## BAE order

Every new 604 project gets its **BAE order** straight away (the former *calc.xlsx*). Open it via **Batteries → BAE orders** or with the **BAE order** button on the project (older projects then get one too).

1. Choose the **battery type** and the **mounting**: the order lines (intercell, step, tier and row connectors), the rack with its price and the BAE price per cell are filled in from the **battery types**.
2. Enter the **number of batteries**, the cells per battery and the capacity, or take them over with **Take over from battery dossier**.
3. Check the **overview** at the bottom: it calculates like the Excel sheet *Order* (batteries × cells × price per cell, plus the racks).
4. **Export to PDF** saves and opens the order, ready to send to BAE. With **Save with delivery documents** the PDF is added to the delivery as *Order to the supplier*.

5. **Send to supplier** mails the order as PDF to the chosen **supplier**, with an optional extra message. This only happens with this button, never automatically, and only once the order is complete. The list shows when and to whom it was sent.

Suppliers are managed under **Batteries → Suppliers** (permission **Manage battery suppliers**): the e-mail address for orders, extra addresses in copy, the language of the mail and which supplier is the default. Every order also goes in copy to the addresses under **Settings → Batteries → BAE order → Orders in copy to**. The text of the mail is edited per language under **E-mail templates → BAE order (to supplier)**.

The defaults (contract, delivery address, contact person, billing) are in **Settings → Batteries → BAE order**. The BAE prices and connectors are changed under **battery types**. Only with the permission **BAE orders: see, fill in and export** (and *generate PDF* for the export).

## Permissions

Viewing, creating, editing, deleting, the **input** tab, **prices & invoice request**, PDFs and changing finished dossiers are separate permissions.
MD,
        ],

        'enersys' => [
            'title' => 'Enersys batteries (605)',
            'body' => <<<'MD'
## What is it for?

Dossiers for **Enersys batteries** on **605 projects**: input, delivery note, measurement report, visual inspection, transport document and order, with signatures and PDF.

## Where?

**Batteries → Enersys batteries**, or through the button on a 605 project.

## How it works

Like the battery dossiers (604): open the dossier from the project, fill in the input, measurements and inspection, have it signed (also in the mobile app) and make the PDFs.


## Battery move

A 605 project can also be a **move**: batteries that go from one COW code to another. On the project you choose **Enersys dossier** or **Battery move**; as soon as one exists, the other button disappears. They are also under **Batteries → Battery moves**.

- **Input**: the COW code of the pickup and that of the placement (by default the project's), the rooms, the battery type, capacity, number and cells. Tick whether the **old batteries at the new COW code** are removed and whether the **pickup COW code is decommissioned**.
- Then it works like a 604 dossier: **delivery note** (with the vertical transport at the pickup and the placement), **measurement report**, **visual inspection report**, **floor plan**, **single-line diagram**, photos, the **signatures** and **finishing**.
- There are two **shipping notes**: the move (from the first to the second COW code) and, only when old batteries go, the waste.
- When **decommissioned** is ticked, finishing sets the building type of the pickup COW code to **Decommissioned**, with a line in the site's log.
- In the mobile app they are under **Battery moves (605)**, with the same parts as on the platform. A closed 605 project never makes a 609 project.

## Delivery follow-up

The client orders the Enersys batteries itself. Under **Batteries → Enersys deliveries** you follow up per 605 project the **PO number** and the **delivery date**, and tick **delivered** on arrival. The project then goes to **To be planned** (unless it is already further, e.g. planned). The PO number is taken from the project. The deliveries are also on the project (tab *Delivery follow-up*).

## Permissions

The same kind of permissions as for battery dossiers, set separately under **Enersys batteries**.
MD,
        ],

        'battery_rooms' => [
            'title' => 'Battery rooms (609)',
            'body' => <<<'MD'
## What for?

After a battery replacement (604) in **Flanders or Brussels** the battery room is brought up to standard. Category **609** is for that, with per project a dossier like the Excel *Leveringsnota*: the delivery note, extra info from the client, the checklist and photos, the ventilation calculation and the BTV inspection report.

## How does a 609 start?

- When a **604 project is closed** on a COW code in the area **1-VLA, 2-VLA or 3-BRU**, the platform automatically makes a 609 project on the same COW code, with the same team and project leader. The room number comes from the 604's battery dossier.
- The **PO number** is not taken over: the project is **Waiting for PO**. As soon as Proximus gives the PO, add it on the project with **Add PO number**; the delivery note then shows it as *Client ref.*
- With **New** under **Battery rooms** you make a battery room by hand: choose the COW code (or the 604 project it follows) and the room number. The 609 project is made at once (this also needs the permission to create projects).

## Where?

**Battery rooms → Battery rooms**, or the **Battery room** button on the 609 project.

## How it works

- **Delivery note**: room number, date, the quantities per item (lighting, ventilation, ESD mats, pictograms, fuse box, cable ladder, emergency light, epoxy floor, temperature sensor, air intake grille), remarks and the **signature**: as with the 604 the dossier is saved first and the delivery note is then locked (only an administrator can remove the signature again). The PDF is portrait on the letterhead, like the 604 delivery note.
- **Extra info**: a free field for what the client adds.
- **Checklist**: the same list everywhere; only the COW code and room number differ. Per point you tick **OK** (empty = NOK) and fill in the **action to take** as the client supplies it. The PDF is landscape, without letterhead.
- **Photos & documents**: several photos, the ventilation calculation and the BTV inspection report (PDF).

The documents are printed in the language of the region (Flanders Dutch, Brussels as set under Settings → Batteries), unless you choose a language. When the 609 project is closed, the documents and files go into its archive.

## Permissions

**Battery rooms (609)**: view, create, edit, delete, generate PDF and download PDF (for the BTV report). The module is switched on or off under **Settings → Modules**.
MD,
        ],

        'board_deliveries' => [
            'title' => 'Electrical boards follow-up (610–650)',
            'body' => <<<'MD'
## What for?

The follow-up of the **electrical boards** of the 610, 620, 630, 640 and 650 projects, with a list per **supplier**: ELP, P&V panels, CE+T and Automation LP (Halle). It is a separate module you switch on or off under **Settings → Modules**.

## Where?

- **Boards → Boards follow-up**: one tab per supplier.
- **Boards → Board suppliers**: add suppliers, choose the order and the kind of list.
- On the project: tab *Delivery follow-up*.

## How it works

- **ELP and P&V panels** (simple list): project, PO number, delivery date and a **delivered** check box (the day of the tick becomes the actual delivery date).
- **CE+T and Automation LP** (list with configuration): PO number, project, **expected delivery**, **actual delivery**, **configuration date** and an **installed & configured** check box. The dates are filled in directly in the list.

A new line is made with **New**: choose the supplier and a 610–650 project; the PO number is taken from the project. The project status never changes by itself here.

## Permissions

**Electrical boards follow-up**: view, create, edit, delete and **manage suppliers**.
MD,
        ],

        'charging' => [
            'title' => 'Charging stations and 4G routers',
            'body' => <<<'MD'
## What is it for?

The details of the **charging stations** (608 projects) and of the **4G routers** per COW site, with passwords and VPN files that are only visible with the right permission.

## Where?

**Charging stations → Charging stations** and **Charging stations → 4G routers**. A 608 project also has a tab with its charging stations.

## Adding a charging station

1. Choose the **location**: **COW code** (a site from the list) or **Other (third-party client)** with the client's details and the full **installation address**.
2. Optionally choose the **project**: its location is then taken over.
3. Fill in the details: ACE number, type (standalone or cluster), system, brand, connectivity, licence, SIM, status.
4. Password and recovery code are entered with the permission **See confidential data**.

## Several stations at once

With **"Add several charging stations"** you enter the location and status once and each station on its own **row**. The copy icon duplicates a row.

## Export

Export the list to **Excel** or **PDF**, with or without confidential data (only with the permission).

## 4G routers

Per COW site the type, serial number, SIM numbers, login and the **VPN file**. Login and VPN file are only visible with the permission **See confidential data** for routers.
MD,
        ],

        'asbuilt' => [
            'title' => 'As-builts',
            'body' => <<<'MD'
## What is it for?

Per project an **as-built** with all files per category, the inspection by **Normec BTV** and at the end one ZIP for the client.

## Where?

- **Projects → As-builts**: the overview per status (in progress, planned for Normec BTV, finished).
- **Projects → As-built standard files**: the register of standard files.
- On a project: the **As-built** button.

## How is an as-built made?

As soon as a project gets the status **Executed**, the as-built is made automatically with the status **In progress**. Not for the excluded categories (by default 600, 604, 605, 606, 607 and 608.1; set under Settings → General). The **standard files** of the project's COW code, the external influences and the general standard files are added straight away.

The **Create as-built** button on the project (permission *As-built → Manage*) makes the as-built earlier, with the same standard files and workflow. When the project then becomes *Executed*, no second one is made. Each as-built shows whether it was made automatically or by hand (and by whom).

## Categories

Plans, electrical schematics, evacuation routes, external influences, technical documents, cable dimensions, BTV reports, ICC and Lmax calculations. Any file format is allowed.

- **Evacuation routes** belong to a **COW code** and are reused in every as-built of that COW code.
- **External influences** apply to **all projects**: they belong to no COW code and go into every as-built.
- **Technical documents** are a **register**: pick from it per as-built and upload new ones (they go into the register too).

## How it works

1. Add files per category (the upload button of each category, or **Add files**).
2. **Plan Normec BTV inspection**: choose the date. The status becomes *Planned for Normec BTV*.
3. After the inspection: **Upload BTV report**. The as-built closes automatically into a ZIP named *"610-012-02GAM - ASBUILT"* with a folder per category (0 - BTV control, 1 - cable dimensions, … 8 - evacuation routes).
4. The client gets an **e-mail** and downloads the ZIP in the Global overview for Client.

With the **Reopen** permission you reopen a finished as-built.

## The client

In the client portal (permission **As-builts**) the client follows the status, downloads the ZIP and hands in files: evacuation routes, plans, external influences, ICC and Lmax, or everything in one ZIP in the **general zone**. The project leaders are notified.

## Permissions

- **As-built → View**: the overview of all as-builts.
- **As-built → Manage**: add and remove files, plan the inspection, upload the BTV report.
- **As-built → Manage standard files** and **Reopen**.
- **As-built → Delete**: remove an as-built for good (with its own files and ZIP), only on the **admin side**. This is an *extra permission*: even administrators only have it when it is ticked.
- Whoever is on the project (project leader or assigned team) can always open and download that project's as-built.
MD,
        ],

        'cable-stripping' => [
            'title' => 'Cable stripping',
            'body' => <<<'MD'
## What is it for?

Following up **cable stripping**: per line the date, the project, who worked on it, the hours and the weighings.

## Where?

**Waste → Cable stripping**.

## How it works

- Add a **line** per batch with the date, the **imputation** (project), the workers, the **start and end time**, the bin number and the **weighings**: before, the empty bin and after stripping.
- The **hours**, the **stripped cable** and the **waste** are calculated automatically.
- Make a **PDF overview** of a period.
MD,
        ],

        'waste' => [
            'title' => 'Waste registry',
            'body' => <<<'MD'
## What is it for?

The legal **register of disposed waste** per category and EURAL waste code, with the waste processors, battery lists per region and totals.

## Where?

**Waste → Waste registry**, **Totals per category**, **Waste categories** and **Waste processors**.

## How it works

1. Register every disposal: date, **kind of waste** (category with EURAL code; an asterisk means hazardous waste), **weight**, the **waste processor** and its reference (e.g. consignment note number).
2. For **battery waste** fill in a line per **destruction certificate** with the **region**, the COW code, the PO number and the **weight**. One entry can have several lines (several certificates, COW codes and PO numbers); the total weight is the sum of the lines. Per line you also fill in a **project reference** (a project of the platform, or typed in freely). Per line you upload the **PDF document** of that certificate; this can also be done later in the battery list per region with **Upload PDF**.
3. **Waste → Battery lists per region** shows per region a row per line, with its own weight, also when several lines share a reference. **Export per year** first makes the list (landscape), followed by the PDF of each line (portrait), in the same order.
3. **Totals per category** shows the totals per period.
4. Export each list to **PDF**, e.g. for an inspection or the yearly declaration.

## Master data

Categories can have subcategories; inactive categories and processors can no longer be chosen for new registrations.
MD,
        ],

        'finance' => [
            'title' => 'Finance: invoices, credit notes and incoming invoices',
            'body' => <<<'MD'
## What is it for?

Making and sending invoices and credit notes (also through **Peppol**), payment reminders, validating incoming invoices, managing clients and statistics.

## Where?

**Finance → Invoices, Credit notes, Incoming invoices, Clients, Standard invoice lines, Finance overview, Statistics**. The company details and Peppol are set under **Settings → Finance**.

## Invoices

1. Create an invoice for a **client**, optionally linked to a **project** and its **PO numbers**.
2. Add lines (or choose a **standard invoice line**); VAT and totals are calculated.
3. **Send** makes the PDF with structured reference and mails it (template *Invoice*), or sends it through Peppol if the client has a Peppol ID.
4. For overdue invoices you send up to **three payment reminders**; switch them on under **Settings → Finance** and they go out automatically every day.

A **credit note** is made from an invoice or separately.

## Incoming invoices

Incoming invoices (also received through Peppol) are **validated**: approved or rejected, with a signature.

## Overview and statistics

- **Finance overview**: per month the income, incoming invoices and the result; click a month to see the documents.
- **Statistics**: per period, compared with the previous year.

## Permissions

Employees only see this with the finance permissions (per kind: invoices, credit notes, incoming invoices, clients).
MD,
        ],

        'incidents' => [
            'title' => 'Reporting and following up incidents',
            'body' => <<<'MD'
## What is it for?

Reporting **accidents, near misses, dangerous situations and dangerous acts** straight away, with photos, so they are followed up.

## Reporting

1. Go to **Safety → Report an incident** (or the More tab in the mobile app).
2. Choose the **kind** and **where** it happened (warehouse location or work site, with the exact place).
3. Indicate the **consequences** (other victims, material damage) and describe what happened.
4. Add up to **20 photos** and send. Date and time are added automatically.

The administrators are warned straight away. Your own reports are under **My reports**.

## Following up

Under **Safety → Incidents** administrators note the **follow-up** (measures taken, who was warned) and export a **PDF**, also per employee or per period.
MD,
        ],

        'suggestions' => [
            'title' => 'Idea box',
            'body' => <<<'MD'
## What is it for?

Submitting **ideas, complaints and other remarks** to management.

## Submitting

1. Click **Idea box** in the top bar, or use the mobile app.
2. Choose **idea, complaint or other**, write your message and optionally add photos.
3. Switch on **"May be shared within the company"** if your message may be shared with colleagues.

## The conversation

- Your submissions and the answers are under **"My submissions"** (button on the idea box page). Click a submission to open the conversation.
- Administrators reply under **Safety → Idea box** with **"Reply"**; you answer the same way. A reply holds text and optionally **photos**.
- With every new reply the other side gets a **notification in the platform** and an **e-mail** in their own language (template *Idea box: new reply*).
- Only you and the administrators see the conversation and the photos.

## Following up

Administrators set the **status** (new, in progress, done) with **Follow up** and export the submissions to **PDF**. An administrator's first reply puts a new submission *in progress*.
MD,
        ],

        'toolbox' => [
            'title' => 'Toolbox meetings',
            'body' => <<<'MD'
## What is it for?

**Safety instructions** with text and attachments that every employee reads in full and signs.

## Where?

- **Safety → Toolbox Meetings**: reading, signing and (with the permission) creating.
- **Safety → Toolbox meeting follow-up**: who signed which toolbox.

## Creating a toolbox meeting

1. Click **New** and fill in the title, the month, who gave the instructions and the subject in **Dutch and French**.
2. Write the text with the editor: headings, lists and **pictures** placed freely in the text.
3. Add **attachments** (PDF, PowerPoint, Word, pictures).
4. **Publish**: all employees see the toolbox and get a notification (also on their phone).

## Reading and signing

- Open the toolbox and **read the whole text** (scroll to the bottom) and/or **open all attachments**.
- Only then can you **sign** with your signature. Your name and signature are kept.
- This also works in the **mobile app**.

## Follow-up and PDF

- **Toolbox meeting follow-up** shows per month and per toolbox who signed (✓).
- The **PDF** is the attendance list with title, company, month, instructors, subject (NL/FR) and the names and signatures.
MD,
        ],

        'knowledge-base' => [
            'title' => 'Knowledge base',
            'body' => <<<'MD'
## What is it for?

Internal **articles and downloads** per category, in Dutch, French and English. This manual is in the knowledge base too.

## Reading

Go to **Knowledge base → Knowledge base**. Choose a category or search for a word. You read the articles in **your language**; attachments are downloaded at the bottom of the article.

## Writing

With the knowledge base permissions:

- **Knowledge base → Categories**: categories with a name and description per language; hidden categories are only seen by writers.
- **Knowledge base → Articles**: title and text **per language**, as formatted text (with pictures) or in Markdown, with downloadable files. An article can stay a draft until it is ready.
- Downloading PDF attachments needs the permission **Knowledge base → Download PDF files**.
MD,
        ],

        'client-portal' => [
            'title' => 'Client portal (Global overview for Client)',
            'body' => <<<'MD'
## What is it for?

A separate portal for the client (e.g. Proximus managers) with a **read-only overview** of their projects, GPW requests, battery deliveries, charging stations and 4G routers. **Prices are never shown.**

## Where?

- Clients sign in at **/client** with their own account, separate from the ERP users.
- Administrators manage the accounts under **Administration → System → Client portal**.

## Creating a client user

1. Fill in name, e-mail, company and **language**.
2. Optionally link a **contact person** (search by name or COW code): the user sees the projects at that contact person's COW sites. Also add separate **assigned projects**.
3. Tick what they may see: projects (with the **categories**), **GPW requests**, battery deliveries, charging stations, 4G routers.
4. Keep **"Send welcome mail"** switched on: the user gets a mail in their language and chooses their own password.

## What does the client see?

- By default only **their own projects**; projects of other clients only with **"🔒 All projects"**.
- Passwords, recovery codes, router logins and VPN files only with the 🔒 permissions.
- Charging stations and routers without a COW code (e.g. at a third-party client) never appear in the portal.
- Under **GPW requests** they approve or refuse requests.

The mails (welcome, new password) are edited under **E-mail templates**.

## Deleting the account

A client can delete their own account through their **profile → Delete my account**. They first get the warning that they will no longer have access to the information and downloads, tick that they understand and confirm with their password. What they handed in stays with the projects. The administrators get a notification.
MD,
        ],

        'mobile-app' => [
            'title' => 'PI ERP mobile app',
            'body' => <<<'MD'
## What is it for?

The **PI ERP** app brings the essentials of the platform to your phone, also on the road.

## Installing and signing in

- On an Android phone the platform offers to download the app; the download is also in your **user menu**.
- Sign in with your platform **e-mail address and password**. The app uses the language you chose in the platform.

## What can you do in the app?

- **Planning**: your planning per day, week or month (and that of colleagues you may see). You get a notification for every change.
- **Timesheet**: fill in your prestation sheet; it is saved straight away in the platform.
- **Leave**: request leave or **overtime as leave**, **report sickness** and add a certificate (photo, file or a picture taken on the spot).
- **Checks**: fill in the **yearly control** of your team's equipment (only while a list is open).
- **More**: your details, read and sign **toolbox meetings**, **report an incident**, the **idea box** and fill in and sign the **battery dossiers** (604/605), with the **floor plan** you draw as on the platform (604), and the **battery rooms** (609): delivery note with signature, extra info, checklist and photos (the ventilation calculation and BTV report are added on the platform).
- **Interventions**: **close** an intervention as on the platform (categories, COW code and room, time worked, colleagues, material, fuse/breaker positions, modified boards and remarks), without a signature. An administrator then reviews it; an intervention sent back is closed again.
- **Messages**: the messages sent from the platform, in your language.

## Notifications

Allow notifications: you are told about a change in your planning, a new toolbox meeting, a yearly control or an approved GPW.

Administrators with the permission **App push messages → Send** (ticked separately, also for administrators) send a message to everyone, to teams or to chosen people through **Admin → App push messages**. The message is written in Dutch, French and English; every employee only gets the version in the language of their profile, as a notification and under **More → Messages**. The list shows how many recipients read it.

## Privacy

The app's privacy policy is at **/privacy** and can also be opened from the app.
MD,
        ],
    ],
];
