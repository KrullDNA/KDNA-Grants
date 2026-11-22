# KDNA Grants

## Plugin Project Brief

PLUGIN NAME: KDNA Grants
SLUG: kdna-grants
CLIENT: Krull Design & Advertising (KDNA)
SITE: krulldna.com
DEPENDS ON: KDNA Forms
VERSION AT DELIVERY: 1.0.0

## 1. Purpose

KDNA Grants runs the KDNA Website Grant, a free website build given to 1 small business every 2 months. It does not collect applications itself. KDNA Forms does that. KDNA Grants sits on top of KDNA Forms and handles everything after an application is submitted:

- **Rounds.** Opens and closes the application form on set dates, and files each application under the right round
- **Eligibility.** Flags applications that don't qualify, so Nick can set them aside quickly
- **Judging.** A clean admin screen to read each application and score it against the published criteria
- **Decision.** Ranks applications by score, lets Nick choose the winner, and sends the winner and unsuccessful emails
- **History.** Keeps every round, score and note on record

The plugin should stay lean. It does a small number of jobs well, and is written so it could run other judged giveaways in future, not just the Website Grant.

## 2. How It Links to KDNA Forms

This is the most important part of the build, and it must be based on KDNA Forms' actual code, not assumptions.

**Setting it up (Nick)**

- Drop the KDNA Forms plugin zip into a folder called `reference` in the repository root
- Drop the KDNA Updates Client Kit into the repository as usual

**How the link works**

1. Nick builds the application form in KDNA Forms, as written in the KDNA Website Grant application form document
2. In KDNA Grants > Settings, Nick picks that form from a dropdown, then matches these fields from it: first name, last name, email, business name, ABN, number of staff, and logo upload. All other answers are still shown in full on the judging screen; these are only the ones KDNA Grants needs to work with
3. When someone submits the form, KDNA Forms announces the new entry. KDNA Grants listens for that, adds the application to the open round, and runs the eligibility checks
4. KDNA Grants stores a link to the KDNA Forms entry (its entry ID), not a full copy, so there is one source of truth for the answers. It keeps only its own data: round, eligibility flags, scores, notes and outcome

**What Code must check first**

Before writing any KDNA Grants code, Code unzips the KDNA Forms zip into a temporary folder outside the repository and reports back to Nick, in plain English:

- How KDNA Forms announces a new submission (the action hook name and what data it passes)
- Whether there is a way to stop a submission before it is saved (a validation hook), so the form can be closed between rounds
- How entries and uploaded files are stored, and how to read an entry's answers by its ID
- The shortcode or function used to display a form
- How to get a list of forms for the settings dropdown

If any of these are missing, Code stops and proposes the smallest possible addition to KDNA Forms. That addition is a separate change to KDNA Forms, with its own minor version bump and its own zip, and is never edited inside the reference folder.

**Rules for the reference folder**

- Read-only. Never edit, move or delete anything inside it
- Never unzip it inside the KDNA Grants plugin folder
- Nothing from it goes into the KDNA Grants zip

**If KDNA Forms is not active,** KDNA Grants shows an admin notice explaining that it needs KDNA Forms, and does nothing else. It must never cause an error.

## 3. Rounds

A round is one application period. Each round has:

| Setting | Notes |
|---|---|
| Round name | For example "Round 1, February 2027". Filled in automatically, editable |
| Opens | Date and time |
| Closes | Date and time |
| Winner announced by | Date, used in the email copy |
| Status | Scheduled, Open, Judging, Decided. Set automatically from the dates, except Decided, which is set when a winner is chosen |

**Automatic opening and closing**

- The round opens and closes at the set times. Don't rely only on WP Cron, which only runs when someone visits the site. Also check the dates every time the form is displayed or submitted
- Only one round can be open at a time. The admin stops Nick creating overlapping dates
- A "Create next round" button fills in the next dates automatically, 2 months after the last one, with the gap between rounds set in Settings

**Outside an open round**

- The form is hidden and replaced by the closed message (see section 7)
- If someone submits anyway, for example from a page left open past the closing time, the submission is refused with a polite message: "Sorry, applications for this round have just closed. The next round opens on [date]."

## 4. Eligibility Checks

Checks run automatically on every new application. They **flag** applications, they never delete or reject them, because Nick always has the final say.

| Check | Flag when |
|---|---|
| ABN | The ABN fails the official ABN checksum (the 11-digit format check the Australian Business Register uses) |
| Staff | The staff answer is outside the limit set in Settings (default fewer than 20) |
| Existing client | The email domain or business name matches the existing client list in Settings |
| Duplicate | Same ABN or same email has already applied in this round |
| Past winner | The ABN or email belongs to a previous winner |

Each flag shows as a small label on the application with a short reason. Nick can click "Mark as eligible" to clear a flag, which is recorded with the date.

Settings include a simple text box for the existing client list: one email domain or business name per line.

## 5. Judging

### Applications screen

A list of every application in the selected round, with a round picker at the top. Columns: business name, industry, staff, applied date, eligibility, scored (yes or no), total score, shortlisted. Sortable by total score. Filters: eligible only, flagged only, unscored only, shortlisted only.

### Single application screen

Everything on one page:

- **Left side:** every answer from the KDNA Forms entry, in the order of the form, with the logo shown as an image and their current website and example websites as clickable links
- **Right side:** the scorecard
  - 1 to 5 points for each criterion, using simple buttons, not a dropdown
  - A notes box
  - A running total out of 20
  - A "Shortlist" toggle
  - Previous and Next buttons to move through the round without going back to the list

Scores and notes save automatically as Nick works, with a small "Saved" message.

### Criteria

The default criteria are Fit, Impact, Readiness and Story, each scored 1 to 5, matching the published criteria on the application page. In Settings, Nick can rename, reorder or add criteria, and choose the tie-break criterion (default Readiness). Changing criteria only affects rounds that haven't started judging, so past scores always stay as they were.

## 6. Choosing the Winner

When every eligible application in the round has been scored, a "Choose winner" panel appears at the top of the applications screen:

- It shows the top 3 by total score, with the tie-break applied, and each one's score breakdown
- Nick clicks "Choose as winner" on one of them
- A confirmation box shows exactly what will happen: who gets the winner email, how many unsuccessful emails will be sent, and that the round will be marked Decided. Nick confirms or cancels

If Nick chooses someone other than the top-scoring application, the confirmation box asks for a short reason, which is saved with the round. This keeps a clear record that the decision was made on merit against the published criteria.

Once decided:

- The winner gets the winner email
- Every other applicant gets the unsuccessful email
- The round is locked. Scores can be viewed but not changed, unless Nick clicks "Reopen judging", which asks for confirmation and is recorded

## 7. Emails and Messages

Three emails, all editable in Settings with a rich text editor, a subject line, and merge tags: `{first_name}`, `{business_name}`, `{round_name}`, `{close_date}`, `{announce_date}`, `{next_open_date}`.

- **Confirmation** (sent on submission): KDNA Forms may already send this. If so, KDNA Grants does not send a second one. Code confirms which plugin sends it during Stage 1
- **Winner**
- **Unsuccessful**

The default copy for all three is in the KDNA Website Grant application form document. Load it as the default text.

**Sending rules**

- Emails are sent in small batches with WP Cron (for example 20 at a time), so choosing a winner never times out, however many people applied
- Each applicant's email status is shown on the applications screen: Queued, Sent or Failed. Failed emails can be resent with one click
- A "Send test email" button for each template, which sends to the admin email with sample data
- Uses wp_mail, so it works with whatever email sending plugin the site already uses

**Closed message** (shown in place of the form between rounds), editable in Settings. Default: "Applications for this round have closed. The next round opens on {next_open_date}." If no next round has been created yet: "Applications are closed for now. Follow KDNA for news of the next round."

## 8. Front End

### Elementor widget: Grant Application

One widget, registered in the KDNA Tools category, that shows the right thing for the round:

- **Round open:** an optional countdown ("Applications close in 12 days"), then the KDNA Forms form, displayed using KDNA Forms' own shortcode or function
- **Round closed:** the closed message, with the next opening date if there is one

The widget has no style controls for the form itself, because KDNA Forms already styles that. It does carry full, responsive style controls for everything it adds: the container (background, border, radius, shadow, padding), the countdown (typography, colours, background, padding, radius), and the closed message (heading and body typography and colours, background, border, radius, padding).

**Preview state control (editor only):** Open with countdown, Open without countdown, Closed with next date, Closed with no next date. Has no effect on the live site.

Built for Elementor's Atomic architecture: `has_widget_inner_wrapper()` returns false when optimised markup is active, a single wrapper div, and no CSS relying on `.elementor-widget-container`.

### Shortcode

`[kdna_grant_application]` does the same as the widget, with default styling, for use outside Elementor.

## 9. History and Export

- **Rounds screen:** every round with its dates, status, number of applications and winner
- **CSV export** for any round: business details, eligibility flags, every score, total, notes, shortlisted, outcome and email status. For Nick's records, and for building case studies and follow-up lists
- **Actions for future add-ons:** fire WordPress actions when an application is captured, when a round closes and when a winner is chosen (for example `kdna_grants_round_decided`), so features like updating Campaign Monitor with the outcome can be added later without changing this plugin

## 10. Privacy and Security

- Joins the WordPress personal data export and erase tools, so a request to delete someone's data removes their KDNA Grants record. Deleting the KDNA Forms entry itself stays with KDNA Forms
- Data retention setting (default 24 months after a round is decided), with old records removed automatically
- Only administrators can see or score applications. Use a custom capability (`kdna_grants_judge`) so access could later be given to a guest judge without making them an administrator
- Nonces on every action, all input sanitised, all output escaped, `$wpdb->prepare()` for every query
- Logos and uploaded files are shown through KDNA Forms' own secure file handling, never by exposing a direct file path
- Scripts and styles only load on KDNA Grants admin screens and on pages containing the widget or shortcode

## 11. Data Storage

Two custom database tables:

- **Rounds:** ID, name, open date, close date, announce date, status, winner application ID, decision note, created and updated dates
- **Applications:** ID, round ID, KDNA Forms entry ID, mapped fields needed for checks and listings (name, email, business name, ABN, staff), eligibility flags, scores (stored as JSON so criteria can change), total, notes, shortlisted, outcome, email status, created and updated dates

Settings stored as a single option.

## 12. File Structure

```
kdna-grants/
├── kdna-grants.php                   (main plugin file, headers, includes, hooks)
├── uninstall.php                     (asks before deleting data, setting controlled)
├── readme.txt                        (with == Changelog ==)
├── includes/
│   ├── class-kdna-grants-install.php      (tables and default settings)
│   ├── class-kdna-grants-forms-bridge.php (the only file that talks to KDNA Forms)
│   ├── class-kdna-grants-rounds.php       (rounds, opening and closing)
│   ├── class-kdna-grants-applications.php (capturing and loading applications)
│   ├── class-kdna-grants-eligibility.php  (eligibility checks, ABN checksum)
│   ├── class-kdna-grants-judging.php      (scores, ranking, tie-break, decision)
│   ├── class-kdna-grants-emails.php       (templates, merge tags, batch sending)
│   └── class-kdna-grants-privacy.php      (data export and erase, retention)
├── admin/
│   ├── class-kdna-grants-admin.php
│   ├── views/      (applications, single application, rounds, settings)
│   └── assets/     (admin.css, admin.js)
├── elementor/
│   └── class-kdna-grants-widget.php
├── assets/
│   ├── js/kdna-grants.js            (countdown)
│   └── css/kdna-grants.css
└── vendor/
    └── kdna-update-client/          (KDNA Updates client, copied unchanged from the Client Kit)
```

All contact with KDNA Forms goes through the one bridge file. If KDNA Forms changes in future, only that file needs updating.

The main plugin file carries the header `Update URI: https://updates.krulldna.com` and, after the ABSPATH check:

```
require_once __DIR__ . '/vendor/kdna-update-client/load.php';
kdna_update_client_register( __FILE__, 'kdna-grants' );
```

## 13. Build Stages

Each stage produces files for review. Only the final stage packages the plugin as a single zip, `kdna-grants-1.0.0.zip`, in the repository root.

Before starting: the KDNA Forms zip is in the `reference` folder and the KDNA Updates Client Kit is in the repository. Code must stop and ask if either is missing.

### Stage 1: KDNA Forms review and foundation

Delivers a plain-English report on how KDNA Forms works, the plugin skeleton, the update client, the database tables, the KDNA Forms dependency check and the Settings screen with form selection and field mapping.

Prompt: "Read the KDNA Grants brief in full. First, unzip the KDNA Forms plugin from the reference folder into a temporary folder outside the repository, and report back to me in plain English on every point listed under 'What Code must check first' in section 2, including which plugin sends the confirmation email. If anything is missing, stop and propose the smallest addition to KDNA Forms before going further. Never edit the reference folder. Once I've approved the report, build the kdna-grants skeleton with the file structure in section 12, the KDNA Updates client (stop and ask me if it's not in the repository), the Update URI header, the two tables from section 11, the KDNA Forms dependency check, and the Settings screen with the form dropdown and field mapping. Version 1.0.0. Do not package yet."

### Stage 2: Rounds, capture and eligibility

Delivers the Rounds screen, automatic opening and closing, capturing new applications from KDNA Forms, refusing submissions outside an open round, and the eligibility checks.

Prompt: "Build Stage 2 of the KDNA Grants brief: the rounds from section 3, including automatic opening and closing that doesn't rely only on WP Cron, the 'Create next round' button and overlap prevention; capturing each new KDNA Forms submission into the open round through the bridge file; refusing submissions outside an open round; and the eligibility checks and flags from section 4, including the official ABN checksum. Include a test tool in the admin that creates 6 sample applications in a test round, covering each eligibility flag, so I can check the logic without real applicants, and a button to delete the test round. Do not package yet."

### Stage 3: Judging, decision and emails

Delivers the applications list, single application screen with autosaving scorecard, criteria settings, ranking with tie-break, the Choose winner panel, and batched emails with test sending.

Prompt: "Build Stage 3 of the KDNA Grants brief: the applications screen, single application screen and scorecard from section 5, the criteria settings, the Choose winner panel and confirmation from section 6, including the reason box when the top score isn't chosen and the round lock, and the emails from section 7 with merge tags, batch sending through WP Cron, email status, resend and test emails. Load the default email copy from the KDNA Website Grant application form document. Keep the admin clean and simple for a non-developer. Do not package yet."

### Stage 4: Front end, history, privacy and packaging

Delivers the Elementor widget, shortcode, history and CSV export, privacy tools, final checks and the single installable zip.

Prompt: "Build Stage 4 of the KDNA Grants brief: the Elementor widget and shortcode from section 8, following the Atomic rules and with the preview state control; the history, CSV export and future add-on actions from section 9; and the privacy, security and uninstall behaviour from section 10. Then check the whole plugin against the coding standards and the testing checklist in section 14, make sure the version reads 1.0.0 in the header, the constant and the filename, add the readme.txt changelog entry, and package kdna-grants-1.0.0.zip in the repository root, containing the plugin folder and the KDNA Updates client, and nothing from the reference folder."

## 14. Testing Checklist

- [ ] Upload kdna-grants-1.0.0.zip, activate, and confirm no errors and version 1.0.0 on the Plugins screen
- [ ] Deactivate KDNA Forms and confirm KDNA Grants shows a notice and causes no errors, then reactivate it
- [ ] Choose the grant form in Settings and map the fields
- [ ] Create a round that opens in 5 minutes. Confirm the page shows the closed message, then the form once the round opens
- [ ] Submit 3 test applications, including 1 with an invalid ABN and 1 duplicate email, and confirm they appear in the round with the right flags
- [ ] Let the round close. Confirm the form is replaced by the closed message, and that a submission from a page left open is refused politely
- [ ] Use the Stage 2 test tool and confirm each eligibility flag appears with the right reason
- [ ] Score every application, check autosave, Previous and Next, and the running total
- [ ] Create a tie and confirm the tie-break criterion decides it
- [ ] Choose a winner who isn't the top score and confirm the reason box appears and the reason is saved
- [ ] Confirm the winner and unsuccessful emails arrive, merge tags are filled in, and email status shows Sent
- [ ] Send a test of each email template
- [ ] Confirm the round is locked after the decision, and that Reopen judging asks for confirmation
- [ ] Export the round to CSV and check every column
- [ ] Style every widget state in the Elementor editor using the preview state control
- [ ] Test the shortcode on a page without Elementor
- [ ] Run a WordPress personal data erase for a test email and confirm the KDNA Grants record is removed
- [ ] Open the zip and confirm it contains only the kdna-grants folder, with no reference files
- [ ] Browser console shows no errors; no PHP warnings or notices with debugging on

## 15. Decisions to Confirm Before Building

These are working assumptions. Change any of them before handing the brief to Code.

1. **Who gets the unsuccessful email.** Assumed: everyone except the winner, including flagged applications. The alternative is not emailing flagged applications at all.
2. **Emails send straight away** when the winner is chosen. The alternative is scheduling them for the announcement date.
3. **Winner must be fully scored first.** The Choose winner panel only appears once every eligible application has a score. This keeps the decision consistent and on record.
4. **Countdown on by default** in the widget while a round is open.
5. **Shortlist calls** are tracked with the Shortlist toggle only. Booking the calls stays outside the plugin.
6. **Campaign Monitor outcome tags** (winner, unsuccessful) are left for a later version. The add-on actions in section 9 make this easy to add.
