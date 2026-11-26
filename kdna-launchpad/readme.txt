=== KDNA Launchpad ===
Contributors: krulldna
Tags: launchpad, giveaway, judging, applications, elementor
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.16
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Runs KDNA Launchpad, KDNA's quarterly free website program, on top of KDNA Forms: rounds, eligibility checks, judging, the winner decision and a full history.

== Description ==

KDNA Launchpad does not collect applications itself. KDNA Forms does that. KDNA Launchpad sits on top of KDNA Forms and handles everything after an application is submitted:

* Rounds: opens and closes the application form on set dates, and files each application under the right round
* Eligibility: flags applications that don't qualify (ABN checksum, staff limit, existing clients, duplicates, past winners)
* Judging: read each application and score it against the published criteria, with an autosaving scorecard
* Decision: rank applications, choose the winner and send the winner and unsuccessful emails in the background
* History: every round, score and note kept on record, with CSV export
* Claude: reads every application against your studio profile and suggests the most interesting briefs, with a round overview
* Contact: email any applicant from the dashboard, with templates and Claude-written drafts
* Offers: a discount on the first brief for unsuccessful applicants, one per business, tracked by hand

= Claude =

Add your Claude API key and studio profile in Launchpad > Settings > Claude. When a round closes, Claude reads every application in the background and writes a round overview on the Claude's picks tab. It only suggests: you score and choose the winner. Names, email addresses, phone numbers, addresses and ABNs are never sent to Claude. A developer can set the key in wp-config.php instead: `define( 'KDNA_LAUNCHPAD_CLAUDE_API_KEY', 'sk-ant-...' );`

= Offers =

When you choose a winner, qualifying unsuccessful applicants get a personal code. Put `{offer_code}`, `{offer_percent}` and `{offer_expiry}` in the unsuccessful email, inside `[offer]...[/offer]` so the wording only shows to people who get a code. Record the outcome on the Offers tab with Accept offer. Each business only ever gets one offer, matched by email, ABN or business name.

Requires KDNA Forms 3.7.1 or newer. If KDNA Forms is not active, KDNA Launchpad shows a notice and does nothing else.

= Showing the application form =

* Elementor: add the Launchpad Application widget from the KDNA Tools category.
* Anywhere else: use the shortcode `[kdna_launchpad]`.

Shortcode options: `countdown="yes|no"`, `countdown_label="Applications close in"`, `closed_heading="Applications closed"`, `title="no|yes"`, `description="no|yes"`.

While a round is open, the countdown and the KDNA Forms form are shown. Between rounds, the closed message from Settings is shown instead. If the site uses page caching, exclude the application page from the cache (or keep the cache short) so visitors always see the current state. Late submissions are always refused by the server either way.

= Actions for add-ons =

* `kdna_launchpad_application_captured( $application_id, $round_id, $entry )`: a new application was captured and checked.
* `kdna_launchpad_round_opened( $round_id )`: a round opened.
* `kdna_launchpad_round_closed( $round_id )`: a round closed.
* `kdna_launchpad_round_decided( $round_id, $application_id )`: a winner was chosen.

Test rounds never fire these actions.

== Installation ==

1. Install and activate KDNA Forms.
2. Upload the kdna-launchpad zip from Plugins > Add New > Upload Plugin, then activate it.
3. Go to Launchpad > Settings, choose the Launchpad application form and match its fields.
4. Set up the applicant confirmation email as a Notification on the form in KDNA Forms.
5. Go to Launchpad > Rounds and create the first round.

== Changelog ==

= 1.3.16 =
* Fixed: sites with saved email templates still asked winners for content "within 4 weeks". Updating now changes that sentence to 2 weeks in saved templates, leaving the rest of your wording as it is.

= 1.3.15 =
* The default "Hosting and next steps" email template now asks for content within 2 weeks of the kick-off meeting, to match the terms.

= 1.3.14 =
* Fixed: iPhones and iPads no longer zoom in when you tap a field on the Launchpad form. The form's fields use 16px text on those devices only, so the desktop design is unchanged.

= 1.3.13 =
* New: visual editors (bold, italic, lists and links) in the dashboard email window and in the "Emailing applicants" templates in Settings. Existing templates and Claude drafts are turned into paragraphs automatically.

= 1.3.12 =
* Email headings are now centred by default, with a Centre or Left choice in Email design.

= 1.3.11 =
* Fixed: the "Preview the winner email" button now shows the email heading. Sent emails already included it.

= 1.3.10 =
* New: body text letter spacing in Email design, alongside the heading letter spacing.

= 1.3.9 =
* New: heading letter spacing in Email design, in pixels (for example -0.5 to tighten). Works in Outlook too.

= 1.3.8 =
* New: a Heading for the winner and unsuccessful emails, shown at 24px in medium weight above the text. Edit it in Settings > Emails and messages; merge tags work, and leaving it blank means no heading.
* Headings typed into the application form's KDNA Forms emails (h1 or h2) get the same style, and newly created forms include one in the confirmation email.

= 1.3.7 =
* The email footer now accepts simple HTML, such as links and bold text. Links match the footer colour, and pressing Enter still starts a new line.

= 1.3.6 =
* The email logo is now centred and 100px wide by default (both can be changed in Email design), and always uses the full-size image so it stays sharp.

= 1.3.5 =
* New: email design. Every Launchpad email, and the application form's confirmation and "new application" emails, now use a designed layout with your logo, a Google Font with fallbacks (Arial for Gmail and Outlook), your colours and a footer. Set it up in Settings > Emails and messages > Email design, with a preview button.

= 1.3.4 =
* New: show round details anywhere in a layout. Use the "Launchpad round info" dynamic tag in any Elementor Heading or Text Editor widget, or the [launchpad_info] shortcode, for example [launchpad_info show="round"]. Choose from the round name, time left (a live countdown), closing date, next round name and next opening date, with optional text to show when there is nothing to show.

= 1.3.3 =
* New: tags in the countdown wording. Use {round} for the round name, {close_date} for the closing date and {time_left} to place the live countdown anywhere, for example "{round} applications close in {time_left}".
* New: {next_round} in the closed message, for the name of the next round.

= 1.3.2 =
* The terms tick box on the built-in form now links to your privacy policy too (the page set in Settings > Privacy), with a short note on how application details are used, including AI. The tick box now reads "I have read and agree to the Launchpad terms and conditions and privacy policy".

= 1.3.1 =
* Removed the "Do you have your logo and brand colours?" question from the built-in form. The required logo upload already confirms it.

= 1.3.0 =
* New: "Create the Launchpad form" in Launchpad > Settings builds the complete application form in KDNA Forms with one click: the agreed questions, word limits, the terms tick box, a "new application" email to you and the applicant's confirmation email. Every field is matched automatically.
* New: official ABN Lookup. The form opens with the ABN; as it's typed, the business name fills itself in ("Looking up your business name…" while it checks), registered trading names are offered for "Trading as", and ABNs that fail the checksum, aren't registered or are cancelled can't be submitted. Needs a free ABN Lookup key (GUID) from the Australian Business Register; without one, ABNs are still checked with the official checksum.
* New: "Trading as" field. Launchpad shows the trading name everywhere when one is given, and checks both names for existing clients, duplicates and offers. The registered name and state from ABN Lookup are kept with each application.
* New: live word counts and word limits on the longer answers.
* New: "Form template" in the Launchpad Application widget. Style the form in an Elementor template with the KDNA Forms widget, then choose it here. The widget warns you if the template doesn't contain the Launchpad form.
* New: {launchpad_business_name} merge tag for KDNA Forms emails (the trading name if given, otherwise the business name).
* New: "Edit questions in KDNA Forms" and "Edit the confirmation email" links in Settings.

= 1.2.0 =
* Changed: everything is now KDNA Launchpad, including the plugin folder (kdna-launchpad), database tables, settings, shortcode ([kdna_launchpad]) and Elementor widget. This is a fresh install, not an update of earlier test builds.

= 1.1.3 =
* Changed: the plugin is now called KDNA Launchpad, and its dashboard menu is called Launchpad. The Elementor widget is now called Launchpad Application. Nothing else changes: the folder, settings, data and automatic updates stay as they were.

= 1.1.2 =
* Changed: the program is now called KDNA Launchpad in the default emails, email templates, Claude's instructions and the privacy policy wording.
* Changed: new rounds are named after the quarter they open in, for example "Q1 2027", so emails read "KDNA Launchpad (Q1 2027)".

= 1.1.1 =
* New: Claude flags anything an applicant wants that Launchpad doesn't include (for example an online shop), or anything they don't have ready (for example no logo or photos), as "Outside Launchpad".
* New: "Hosting and next steps" email template for winners, recommending VentraIP.
* Changed: offers now default to 28 days, with wording asking applicants to engage KDNA and approve their quote by the expiry date. Saved settings still on the old 90-day default move to 28 days.
* Changed: rounds now default to quarterly. The first round is suggested for the next calendar quarter, open for two months, with the winner announced by the 15th of the month after. Saved settings still on the old two-month gap move to three months.
* Changed: "Create next round" keeps month-end closing dates on the last day of the month (28 February becomes 31 May), and never spills into the following month.
* Changed: default winner email now covers next steps, VentraIP and extras at 25% off. Round names in emails now sit in brackets.
* Fixed: Claude's instructions no longer describe the program as "every two months".

= 1.1.0 =
* New: Claude's picks. Claude reads each application against your studio profile, rates how interesting the brief is, lists strengths, concerns and call questions, and writes a round overview with its top picks. Runs automatically when a round closes, or on demand.
* New: "Claude's take" and "Ask Claude now" on the judging screen, and an Interest column on the Applications list.
* New: email any applicant from the dashboard, with saved templates, "Draft with Claude", Reply-To set to your address, and a record of every message.
* New: offers for unsuccessful applicants (25% off the first brief, valid for 90 days by default), with an Offers tab to accept, decline or undo, automatic expiry, and one offer per business.
* New: {offer_code}, {offer_percent}, {offer_expiry} and {offer_cap} merge tags and [offer]...[/offer] sections in the unsuccessful email.
* Changed: new rounds are named from their dates, for example "4 April to 4 May 2026".
* Changed: all outcome emails now set Reply-To to your address.

= 1.0.0 =
* First release.
* Rounds with automatic opening and closing, Create next round and overlap prevention.
* Captures KDNA Forms submissions into the open round and refuses submissions between rounds.
* Eligibility checks: official ABN checksum, staff limit, existing clients, duplicates and past winners, with Mark as eligible.
* Applications list with filters and sorting, and a judging screen with an autosaving scorecard.
* Editable criteria with a tie-break, Choose winner panel with confirmation, round lock and Reopen judging.
* Winner and unsuccessful emails with merge tags, sent in batches through WP Cron, with status, resend and test emails.
* Launchpad Application Elementor widget (KDNA Tools) with full style controls and an editor preview state, plus the [kdna_launchpad] shortcode.
* CSV export for every round, and actions for future add-ons.
* WordPress personal data export and erase, automatic data retention, and the kdna_launchpad_judge capability.
* Test tool with 6 sample applications covering every eligibility flag.
* Automatic updates via KDNA Updates.
