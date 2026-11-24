=== KDNA Launchpad ===
Contributors: krulldna
Tags: launchpad, giveaway, judging, applications, elementor
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
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
