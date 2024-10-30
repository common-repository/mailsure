=== Mailsure ===
Plugin URI: https://mailsure.app
Donate link: https://mailsure.app/donate
Contributors: CoryTrevor
Tags: email authentication, test email, email, dmarc, dkim
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.1
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
Test email sending, SPF, DKIM & DMARC

== Description ==

### Test email sending, SPF, DKIM & DMARC
 
Mailsure provides a simple one-click email authentication test to check if WordPress is able to send properly authenticated emails.

Also included:

* Send a test email to any address
* Mail server IP blacklist check via [MXToolbox](https://mxtoolbox.com/). View their privacy policy [here](https://mxtoolbox.com/privacypolicy.aspx).

Plugin settings are in Tools -> Mailsure

== Installation ==

= Automatic Installation =

1. Go to Plugins -> Add New
1. Search for "Mailsure"
1. Click on the Install button under the Mailsure plugin
1. Once plugin has been installed, click the Activate button

= Manual Installation =

1. Click the Download button to download the plugin zip file
1. Login to the WordPress dashboard and go to Plugins -> Add New Plugin
1. Select 'Upload Plugin' then 'Choose file' and select the plugin zip file
1. Click the 'Install Now' button
1. Click the 'Activate Plugin' button
 
== Frequently Asked Questions ==
 
= Will it work if I use an SMTP plugin? =

Yes, the plugin uses the wp_mail function to send the test email which should work with any SMTP plugin.
 
== Screenshots ==
1. Mailsure authentication test
2. Mailsure test email and blacklist check
 
== Changelog ==
= 1.0 =
* Plugin released.
