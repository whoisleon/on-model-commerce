=== Style by REii Commerce ===
Contributors: whoisleon
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.5.17
License: Proprietary

Customer-facing WooCommerce project intake, production workflow, private delivery library, and GitHub-powered updates for Tech by Leon.

== Installation ==

Install on-model-commerce.zip once through Plugins > Add New Plugin > Upload Plugin. The permanent installation directory is on-model-commerce-github so it cannot collide with the site's orphaned legacy directory. Future versions appear in the standard WordPress plugin updater.

== Changelog ==

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
