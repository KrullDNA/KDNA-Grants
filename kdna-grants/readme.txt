=== KDNA Grants ===
Contributors: krulldna
Tags: grants, giveaway, judging, applications, elementor
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Runs the KDNA Website Grant on top of KDNA Forms: rounds, eligibility checks, judging, the winner decision and a full history.

== Description ==

KDNA Grants does not collect applications itself. KDNA Forms does that. KDNA Grants sits on top of KDNA Forms and handles everything after an application is submitted:

* Rounds: opens and closes the application form on set dates, and files each application under the right round
* Eligibility: flags applications that don't qualify (ABN checksum, staff limit, existing clients, duplicates, past winners)
* Judging: read each application and score it against the published criteria, with an autosaving scorecard
* Decision: rank applications, choose the winner and send the winner and unsuccessful emails in the background
* History: every round, score and note kept on record, with CSV export

Requires KDNA Forms 3.7.1 or newer. If KDNA Forms is not active, KDNA Grants shows a notice and does nothing else.

= Showing the application form =

* Elementor: add the Grant Application widget from the KDNA Tools category.
* Anywhere else: use the shortcode `[kdna_grant_application]`.

Shortcode options: `countdown="yes|no"`, `countdown_label="Applications close in"`, `closed_heading="Applications closed"`, `title="no|yes"`, `description="no|yes"`.

While a round is open, the countdown and the KDNA Forms form are shown. Between rounds, the closed message from Settings is shown instead. If the site uses page caching, exclude the application page from the cache (or keep the cache short) so visitors always see the current state. Late submissions are always refused by the server either way.

= Actions for add-ons =

* `kdna_grants_application_captured( $application_id, $round_id, $entry )`: a new application was captured and checked.
* `kdna_grants_round_opened( $round_id )`: a round opened.
* `kdna_grants_round_closed( $round_id )`: a round closed.
* `kdna_grants_round_decided( $round_id, $application_id )`: a winner was chosen.

Test rounds never fire these actions.

== Installation ==

1. Install and activate KDNA Forms.
2. Upload kdna-grants-1.0.0.zip from Plugins > Add New > Upload Plugin, then activate it.
3. Go to KDNA Grants > Settings, choose the grant application form and match its fields.
4. Set up the applicant confirmation email as a Notification on the form in KDNA Forms.
5. Go to KDNA Grants > Rounds and create the first round.

== Changelog ==

= 1.0.0 =
* First release.
* Rounds with automatic opening and closing, Create next round and overlap prevention.
* Captures KDNA Forms submissions into the open round and refuses submissions between rounds.
* Eligibility checks: official ABN checksum, staff limit, existing clients, duplicates and past winners, with Mark as eligible.
* Applications list with filters and sorting, and a judging screen with an autosaving scorecard.
* Editable criteria with a tie-break, Choose winner panel with confirmation, round lock and Reopen judging.
* Winner and unsuccessful emails with merge tags, sent in batches through WP Cron, with status, resend and test emails.
* Grant Application Elementor widget (KDNA Tools) with full style controls and an editor preview state, plus the [kdna_grant_application] shortcode.
* CSV export for every round, and actions for future add-ons.
* WordPress personal data export and erase, automatic data retention, and the kdna_grants_judge capability.
* Test tool with 6 sample applications covering every eligibility flag.
* Automatic updates via KDNA Updates.
