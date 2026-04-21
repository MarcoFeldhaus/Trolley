=== WooBillomat ===
Contributors: Billomat
Tags: woocommerce, billomat, invoice, invoicing
Requires at least: 4.8
Tested up to: 6.0.2

Connect WooCommerce to Billomat and generate clients, articles and invoices automatically.

== Description ==
Connect WooCommerce to your exiting Billomat account with ease and save time and money.
Clients and articles will be automatically created and updated and invoices are generated when a WooCommerce order completes.

== Installation ==
1. Move plugin files to the directory `/wp-content/plugins/woocommerce-billomat` or install it via WordPress plugin management.
2. Activate WooCommerce Billomat from your Plugins page.
3. Visit WoCommerce -> Settings and click on the Billomat tab to enter your billomatID and API key (you must activate the API access for a user under “Settings > employees“ in Billomat).
4. Set the other options to your requirements.

Warning: please insert net prices, otherwise rounding errors can occur.

== Billomat webhooks ==

This plugin uses Billomat webhooks to synchronize data from Billomat to WooCommerce.
Please register the following 3 webhooks in Billomat under “Settings > Webhooks” with the secret key shown under the Billomat tab in WoCommerce -> Settings.

Event: Customer / change
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_update_customer&secret_key=YOUR_SECRET_KEY

Event: Customer / delete
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_delete_customer&secret_key=YOUR_SECRET_KEY

Event: Articles / change
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_update_product&secret_key=YOUR_SECRET_KEY

Event: Articles / delete
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_delete_product&secret_key=YOUR_SECRET_KEY

Event: Delivery note / Change of status
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_add_delivery_note&secret_key=YOUR_SECRET_KEY

Event: Delivery note / delete
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_delete_delivery_note&secret_key=YOUR_SECRET_KEY

Event: Invoice / Change of status (since 1.1.0 - 2017-11-02)
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_change_invoice_status&secret_key=YOUR_SECRET_KEY

Event: Invoice / delete (since 1.1.0 - 2017-11-02)
URL: https://yourdomain.com/wp-admin/admin-post.php?action=wcb_delete_invoice&secret_key=YOUR_SECRET_KEY

== Changelog ==

= 2.4.8 - 2022-09-26 =
* Dev - Add `User-Agent` header to Billomat API requests.

= 2.4.7 - 2022-09-02 =
* Dev - Change invoice correction to new API endpoint.

= 2.4.6 - 2022-07-27 =
* Fix - Fix condition for checking correction invoice creation.

= 2.4.5 - 2022-03-15 =
* Fix - Swap join() parameters since glue right is deprecated in PHP 7.4.

= 2.4.4 - 2021-06-01 =
* Fix - Separate shipping calculation for carts with 0 total value. Fixes double-creation of shipping billing position under certain conditions (introduced by 2.4.3)
* Fix - Change selectors for order actions to correctly display invoice / delivery note icons

= 2.4.3 - 2021-05-19 =
* Fix - Fix missing shipping tax rate if tax total is 0

= 2.4.2 - 2021-04-12 =
* Fix - Remove JavaScript confirm when deleting references, which prevented the form from being submitted correctly

= 2.4.1 - 2021-03-09 =
* Fix - Ignore cancelled orders on invoice update webhook

= 2.4.0 - 2020-11-30 =
* Fix - Add discount tax if Billomat tax setting is gross
* Dev - Guzzlehttp upgrade (version 6.3 to 7.2)
* Dev - Add header to Billomat API calls (WooBillomat [version])

= 2.3.8 - 2020-02-11 =
* Fix - Add shipping items for `shipping` tax status: consider order items for products with tax_status shipping while creating shipping items

= 2.3.7 - 2019-07-18 =
* Fix - Find tax rate if tax has total (fixed no passed tax rate if default WC tax class is used)

= 2.3.6 - 2019-07-12 =
* Fix - Removed addition of discount tax
* Fix - Check invoice item tax class instead of tax total (fixed empty tax name / rate for items with 0% tax)

= 2.3.5 - 2019-05-06 =
Added missing $payment_method variable to map_data().

= 2.3.4 - 2019-04-17 =
* Fix - Moved template_id parameter from invoice 'complete' to 'create'. Fixes a bug where no default invoice template is set in Billomat and WCB order status setting is 'draft' (results in unset template_id).

= 2.3.3 - 2019-03-27 =
* Fix - Changed shipping item calculation values to avoid rounding errors.
* Fix - Removed user_email update on customer update webhook (only update billing email, not WP user email).

= 2.3.2 - 2019-01-29 =
* Dev - Added default option to payment gateway based settings.
* Tweak - Pass order discount as invoice `reduction` parameter instead of own invoice item (fixes tax problems).
* Tweak - Show the order ID as context for order related error messages.
* Fix - Added fallback shipping item if new tax rate based calculation fails (if sum of tax items is 0.00).
* Fix - Find valid WC_Tax in product updater (fixes passing inactive tax classes).
* Fix - Check if get_shipping_total() is greater than 0.00 before creating shipping invoice item.

= 2.3.1 - 2019-01-09 =
* Fix - Added separate shipping invoice items based on order items tax rates.
* Fix - Fixed typo in communication error message.

= 2.3.0 - 2018-11-21 =
* Dev - Added option to create an invoice correction for cancelled orders.
* Dev - Separated options for creating/delivering invoices: added new option to choose the order status for sending/attaching invoices.
* Dev - Replaced invoice payment checkbox with a dropdown to choose the new order status.
* Fix - Avoid adding a shipping invoice item if the shipping total is 0,00.

= 2.2.1 - 2018-11-03 =
* Fix - Discount gross/net calculation - add get_discount_tax() to discount invoice item if Billomat tax setting is gross and WooCommerce is net.
* Fix - Removed action for deleting Billomat user due to a bug with falsely called user_delete hook.
* Fix - Added static keyword to invoice account actions to get rid of strict PHP warning.

= 2.2.0 - 2018-08-16 =
* Dev - Added reset function for articles, clients and invoices to WooBillomat settings to delete all references to Billomat entities.
* Dev - Added Billomat ID fields to articles, variations, users and orders to allow admins to manually reset references to Billomat entities.
* Tweak - Improved error handling: display admin errors when something couldn´t be synced/created due to deleted Billomat entities.
* Tweak - Write to error_log (aka wp-content/debug.log) in case of an Billomat API exception (https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG_LOG).
* Dev - Display rating notice after 10, 30, 50 created invoices.. Give us 5 stars! :-)
* Fix - Use subtotal_tax instead of total_tax to find invoice item tax rate. This fixes a bug where free items (total 0,00) are passed without tax rate.

= 2.1.2 - 2018-06-25 =
* Fix - Avoid generating empty WooCommerce order when a Billomat invoice is created manually.

= 2.1.1 - 2018-06-05 =
* Fix - Fixed wrong connection failure message when adding Billomat API credentials.

= 2.1.0 - 2018-05-29 =
* Dev - Added WooCommerce -> Billomat payment mapping in order to complete invoice payments with the Billomat payment method.
* Dev - Optimized error message: added last Billomat API error to the WooCommerce Billomat backend tab.
* Fix - Fixed wrong tax calculation on invoice items in case a coupon is used (use total_tax instead of subtotal_tax).
* Fix - Added check of email type to email_attachments to avoid triggering invoice attachment for emails which are not an order confirmation.

= 2.0.0 - 2018-03-16 =
* Dev - Added setting "Invoice status" to define the Billomat invoice status per payment gateway.
* Dev - Added setting "Update orders" to update WooCommerce orders automatically based on Billomat invoice status change webhooks.
* Dev - Added setting "Invoice templates" to set an invoice template per payment gateway.
* Dev - Added setting "Sync article numbers" to synchronize articles numbers between WooCommerce<->Billomat.
* Dev - Added option "Disable description sync" to ignore the article descriprion in export and import.
* Dev - Added setting "Order summary" to add an order actions invoice button / Order detail invoice link in customer fronted.
* Dev - Added several export/import WordPress filters to modify the data passed from WooCommerce<->Billomat.
* Fix - Consider article description source in import webhook handler. This avoids overwriting the default WooCommerce description instead of the short description.
* Fix - Consider tax for coupons.
* Fix - Added net<->gross conversion of sales price in import.
* Fix - Consider WooCommerce sale price on import update. If a WooCommerce sale_price is set, update it instead of regular_price.
* Fix - Include empty tax_class in WC_Tax::find_rates() in build_invoice_item().
* Fix - Only add tax_name/tax_rate if order item has tax.
* Fix - Add order shipping tax if Billomat tax setting is set to 'GROSS'.
* Dev - Added admin errors for reaching Billomat quotas. Show which quotas are reached instead of a generic error message.
* Fix - Fixed rounding errors in net <-> gross calculation (too less decimals).
* Fix - Added fallback for tax country for invoice items.

= 1.1.0 - 2017-11-02 =
* Dev - Added setting "Article description source" to define which WooCommerce product field (description or short description) is used as Billomat article description. Defaults to short description.
* Dev - Added setting to disable invoice creation per payment gateway (or completely as until now).
* Dev - "Draft" added to invoice status selection. Draft-invoices can be completed at a later point in time via the order metabox. IMPORTANT: a new webhook has to be installed in Billomat (Event: Invoice / Change of status - see "Billomat webhooks").
* Fix - Implemented webhook handler for removing Billomat reference (`billomat_id` postmeta) from an order when an invoice is deleted in Billomat. IMPORTANT: a new webhook has to be installed in Billomat (Event: Invoice / delete - see "Billomat webhooks").

= 1.1.1 - 2017-11-29 =
* Fix - Consider Billomat tax setting (net/gross) when creating invoice items - add tax price to total if Billomat tax is set to gross.
* Fix - Wrap free texts single-resource response in array as Billomat API returns an one-dimensional array.
* Fix - Moved autoload include to WCB_Client due to an autoload error in certain environments.
* Tweak - Don´t cache Billomat tax setting (net/gross) - request directly via API when needed. This avoids problems when Billomat settings change.
* Tweak - Added error handling to all WCB_Client API call methods. An admin error notice will be added in case there are any Guzzle exceptions.
* Fix - Empty return replaced with `return $attachments` in `email_attachments` callback.
