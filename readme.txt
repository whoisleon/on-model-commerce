=== REii Commerce ===
Contributors: whoisleon
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.5.81
License: Proprietary

Customer-facing direct Stripe project intake, production workflow, private delivery library, and GitHub-powered updates for Tech by Leon.

== Installation ==

Install on-model-commerce.zip once through Plugins > Add New Plugin > Upload Plugin. The permanent installation directory is on-model-commerce-github so it cannot collide with the site's orphaned legacy directory. Future versions appear in the standard WordPress plugin updater.

== Changelog ==

= 0.5.81 =
* Fix create another video modal re-opening from order confirmation screen.

= 0.5.80 =
* Automatically grey out and explain Storefront option when no Amazon ASIN is provided.

= 0.5.79 =
* Never send admin notifications or customer invoice emails for unpaid pending orders.

= 0.5.78 =
* Position Amazon Storefront feature description on its own line below the heading.

= 0.5.77 =
* Add 1-second confetti celebration on order confirmation and autofill previous details when creating another video.

= 0.5.76 =
* Position cancel notification and alerts at top of intake form.

= 0.5.75 =
* Auto-save customer emails and product form details so information is remembered when returning from Stripe payment.

= 0.5.74 =
* Fix Storefront add-on checkbox sync to direct Stripe checkout sessions and intake orders.

= 0.5.73 =
* Perfectly center checkbox checkmark with crisp SVG background icon.

= 0.5.72 =
* Update Amazon Storefront add-on wording to REii's Amazon Storefront and embed official Storefront link.

= 0.5.71 =
* Add custom high-contrast checkmark styling to rights confirmation and storefront add-on checkboxes.

= 0.5.70 =
* Add optional $10 Amazon Storefront posting add-on and intake feature checkbox.

= 0.5.69 =
* Simplify delivery library reorder section to a single "Create another video" button.

= 0.5.68 =
* Remove the order-summary rail and restore the designed purple intake step badges.

= 0.5.67 =
* Match the product-intake modal to the editorial REii payment-confirmation design across desktop and mobile.

= 0.5.66 =
* Turn the confirmation action into a working invitation to start another REii video order.

= 0.5.65 =
* Replace internal portal language on the confirmation screen with customer-focused receipt and delivery messaging.

= 0.5.64 =
* Show the verified Stripe Checkout email address on the payment confirmation screen.

= 0.5.63 =
* Redesign the Stripe payment confirmation as a compact Uncode-inspired editorial card with clear receipt, creation, and private-delivery steps.

= 0.5.62 =
* Replace the broken WooCommerce email product placeholder with a branded REii video icon.

= 0.5.61 =
* Publish REii category post permalinks and canonical metadata on reii.techbyleon.com.
* Permanently redirect legacy Tech by Leon REii post and category URLs while leaving REST, Admin, uploads, WooCommerce, and Stripe webhooks on the primary WordPress host.

= 0.5.60 =
* Include the direct Stripe backend in the WordPress release package.

= 0.5.59 =
* Replace the customer-facing WooCommerce checkout with Stripe-hosted Checkout Sessions.
* Redirect back to a branded REii confirmation without creating a WordPress customer account.
* Verify Stripe payment webhooks before releasing paid orders into the existing production workflow.

= 0.5.58 =
* Force REii checkout to remain guest-only and prevent WooCommerce from creating customer accounts or sending account setup emails.

= 0.5.57 =
* Load embedded WooCommerce checkout from the visible REii domain so same-origin iframe protection does not block payment.

= 0.5.56 =
* Keep native checkout requests on the visible REii domain so browser security does not block the payment handoff.

= 0.5.55 =
* Replace the returning-customer login prompt with the current guest email and a confirmation-delivery notice.
* Show the installed REii Commerce version in a subtle footer on the REii page.

= 0.5.54 =
* Replaced the Contact Form 7 intake handoff with a native WooCommerce session endpoint.
* Seeds the checkout email on the server before Stripe payment validation runs.

= 0.5.53 =
* Keep completed-order delivery emails, private content-library links, and admin delivery controls registered when WordPress preloads the commerce class from a legacy plugin directory.
* Reduce the customer email flow to the paid-order receipt and completed-delivery link by disabling the pre-checkout intake autoresponder and optional on-hold stage email.
* Replace Contact Form 7's misleading sent-message confirmation with a clear reminder that payment is still required to place the order.
* Enforce a 16px minimum font size throughout the product form, payment handoff, embedded checkout, and payment confirmation.

= 0.5.52 =
* Polish the fashion product callouts with larger imagery, tighter typography, accessible hover/focus motion, and scroll-drawn pointer lines.

= 0.5.51 =
* Add a branded submit-to-payment handoff, an UNCODE-inspired secure checkout, and a rounded REii order-confirmation experience.

= 0.5.50 =
* Keep the corrected upload handler authoritative when a legacy plugin copy initializes later in the WordPress request.

= 0.5.49 =
* Correctly recognize Contact Form 7 radio values so file-upload orders validate their uploaded files instead of requesting an Amazon ASIN.

= 0.5.48 =
* Replace any stale REii cart item with the complete current intake so repeat orders keep the newly entered email and Amazon reference.
* Reapply the current cart line immediately before Checkout Blocks processes payment and retain order-level intake metadata as a dashboard fallback.

= 0.5.47 =
* Prevent checkout from opening unless the current order includes an Amazon link/ASIN or at least one uploaded product file.

= 0.5.46 =
* Keep the email submitted in the current REii intake authoritative when WooCommerce has an older billing email saved.

= 0.5.45 =
* Replaces awkward “REii feature” wording with natural video, order, and project language throughout checkout and confirmation.
* Renames the purchasable item to “REii AI-Generated UGC Video,” including previously placed orders when displayed.

= 0.5.44 =
* Replaces the stale $50 legacy service record with the REii AI Influencer UGC Feature at the intended $10 launch price.
* Enforces direct-purchase pricing at checkout so legacy plugin copies cannot restore the old price.

= 0.5.43 =
* Makes the embedded payment screen a reliable one-product direct purchase, automatically loading the $10 REii video instead of ever showing an empty cart.
* Captures the REii intake through a legacy-safe fallback when an older plugin class is preloaded by the host.

= 0.5.42 =
* Gives the animated REiMAGiNE letter inserts additional width for smoother final spacing.

= 0.5.41 =
* Transforms the REii wordmark directly into REiMAGiNE with anchored lettering, refined tracking, and a precisely aligned period.

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
* Sets the launch video to $10 and supports priced video add-ons through checkout.
* Adds a storefront-focused homepage offer, included-benefits strip, and customization menu.
* Adds matching add-on offers to the private shoppable-video delivery library.

= 0.5.5 =
* Uses the complete 0.5.4 production and delivery workflow as its baseline.
* Adds update discovery and release downloads through GitHub Releases.
* Standardizes the permanent plugin directory as on-model-commerce-github.
