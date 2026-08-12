=== REii Commerce ===
Contributors: whoisleon
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.5.40
License: Proprietary

Customer-facing WooCommerce project intake, production workflow, private delivery library, and GitHub-powered updates for Tech by Leon.

== Installation ==

Install on-model-commerce.zip once through Plugins > Add New Plugin > Upload Plugin. The permanent installation directory is on-model-commerce-github so it cannot collide with the site's orphaned legacy directory. Future versions appear in the standard WordPress plugin updater.

== Changelog ==

= 0.5.40 =
* Keeps the REii wordmark and period anchored while revealing the full tagline without clipping.

= 0.5.39 =
* Keeps the Stripe checkout in its overlay by teaching the legacy redirect fallback to recognize the current payment modal.

= 0.5.38 =
* Supplies the hidden billing country required by Stripe while keeping the digital-service checkout address-free.
* Prevents the animated REii tagline from clipping descenders.

= 0.5.37 =
* Hides the Stripe preparation indicator after load and prevents hidden WooCommerce address fields from blocking digital-service orders.

= 0.5.36 =
* Guarantees the address-free embedded Stripe checkout even when a migrated site preloads an older commerce class.

= 0.5.35 =
* Keeps Stripe payment inside a polished REii popup and removes the address wall for the digital service checkout.

= 0.5.34 =
* Moves the checkout fallback into the reliably executed portal asset for compatibility with dynamically injected page content.

= 0.5.33 =
* Adds a legacy-safe submission fallback so successful REii intakes always continue to secure checkout.

= 0.5.32 =
* Enables the Contact Form 7 checkout bridge on the live Style by REii page and preserves the form's native submit lifecycle.

= 0.5.31 =
* Prevents Contact Form 7 reset cleanup from erasing email and product fields while a customer is typing.

= 0.5.30 =
* Animates the REii wordmark into “REIMAGINE INFLUENCE” on hover and keyboard focus, with responsive and reduced-motion behavior.

= 0.5.29 =
* Rebrands the customer journey around REii, the Reimagined Influencer, with explicit AI influencer and UGC language across ordering, email, checkout, and delivery.

= 0.5.28 =
* Packages the Style by REii landing-page CSS and JavaScript so the public WordPress page can load the rebrand from the installed plugin.

= 0.5.27 =
* Registers the Order Studio REST namespace outside the class guard so legacy duplicate plugin files cannot hide orders.

= 0.5.26 =
* Initializes immediately so WordPress dependency loading cannot register the plugin after the plugins_loaded event has already fired.

= 0.5.25 =
* Gives the GitHub build a permanent unique class name so a legacy plugin copy cannot suppress the WooCommerce order API.

= 0.5.24 =
* Uses WordPress's authorized install-and-overwrite path for Personal plans that allow plugin replacement but block the normal upgrade controller.

= 0.5.23 =
* Production verification release for the nonce-safe GitHub updater.

= 0.5.22 =
* Preserves raw query separators in the WordPress upgrader redirect so its nonce validates correctly.

= 0.5.21 =
* Final end-to-end verification release for the dedicated GitHub updater.

= 0.5.20 =
* Keeps WordPress's upgrader eligible when the host blocks GitHub version metadata, while the downloaded ZIP retains the real release version.

= 0.5.19 =
* Confirms direct latest-release updates without manual ZIP replacement.

= 0.5.18 =
* Updates directly from GitHub's stable latest-release ZIP when WordPress.com blocks the optional version preflight request.

= 0.5.17 =
* Verifies the dedicated Update from GitHub workflow end to end on WordPress.com hosting.

= 0.5.16 =
* Loads the secure GitHub updater before the legacy class guard so orphaned plugin copies cannot suppress the button.

= 0.5.15 =
* Shows the secure GitHub updater to WordPress.com administrators who can install plugins even when the host withholds the separate update capability.

= 0.5.14 =
* Uses the broad WordPress plugin-row action filter so the GitHub update button also appears on WordPress.com hosting.

= 0.5.13 =
* Adds a nonce-protected Update from GitHub action to the installed plugin row.
* Sends verified GitHub releases through the standard WordPress plugin upgrader.

= 0.5.12 =
* Confirms the GitHub-to-WordPress automatic update delivery path.

= 0.5.11 =
* Forces a fresh GitHub release lookup during WordPress plugin update checks.
* Prevents a previously cached current version from hiding a newly published release.

= 0.5.10 =
* Refines the installed-plugin description for the private client delivery workflow.

= 0.5.9 =
* Falls back to the repository readme when the shared-host GitHub API request is unavailable or rate limited.
* Reduces negative update-cache time so failed checks recover quickly.

= 0.5.8 =
* Clarifies the installed-plugin description with the new shoppable UGC video branding.

= 0.5.7 =
* Uses WordPress core's native Update URI hostname filter for GitHub releases.
* Clears the plugin's GitHub response cache when an administrator clicks Check again.
* Keeps the existing update-transient integration as a compatibility fallback.

= 0.5.6 =
* Rebrands the customer offer as the Style by REii Shoppable Video Feature.
* Sets the launch feature to $20 and supports priced feature add-ons through checkout.
* Adds a storefront-focused homepage offer, included-benefits strip, and customization menu.
* Adds matching add-on offers to the private shoppable-video delivery library.

= 0.5.5 =
* Uses the complete 0.5.4 production and delivery workflow as its baseline.
* Adds update discovery and release downloads through GitHub Releases.
* Standardizes the permanent plugin directory as on-model-commerce-github.
