<?php
/**
 * $Horde: imp/config/mime_drivers.php.dist,v 1.35.10.3 2006/07/19 17:12:59 jan Exp $
 *
 * Decide which output drivers you want to activate for the IMP application.
 * Settings in this file override settings in horde/config/mime_drivers.php.
 *
 * The available drivers are:
 * --------------------------
 * alternative    multipart/alternative parts
 * appledouble    multipart/appledouble parts
 * enriched       Enriched text messages
 * html           HTML messages
 * images         Attached images inline
 * itip           iCalendar Transport-Independent Interoperability Protocol
 * multipart      All other multipart/* messages
 * notification   Notification messages
 * partial        message/partial parts
 * pgp            PGP signed/encrypted messages
 * pkcs7          S/MIME signed/encrypted messages
 * plain          URL syntax highlighting for text/plain parts 
 * related        multipart/related parts
 * rfc822         Digested messages
 * status         Mail delivery status messages
 * tnef           MS-TNEF attachments
 * zip            ZIP attachments
 */
$mime_drivers_map['imp']['registered'] = array(
    'alternative', 'appledouble', 'enriched', 'html', 'images', 'itip',
    'multipart', 'notification', 'partial', 'pgp', 'pkcs7', 'plain',
    'related', 'rfc822', 'status', 'tnef', 'zip');

/**
 * If you want to specifically override any MIME type to be handled by
 * a specific driver, then enter it here.  Normally, this is safe to
 * leave, but it's useful when multiple drivers handle the same MIME
 * type, and you want to specify exactly which one should handle it.
 */
$mime_drivers_map['imp']['overrides'] = array();

/**
 * Driver specific settings. See horde/config/mime_drivers.php for
 * the format.
 */

/**
 * Text driver settings
 */
$mime_drivers['imp']['plain']['inline'] = true;
$mime_drivers['imp']['plain']['handles'] = array(
    'text/plain', 'text/rfc822-headers', 'application/pgp');
/* If you want to scan ALL incoming messages for UUencoded data, set
   the following to true. */
$mime_drivers['imp']['plain']['uuencode'] = false;

/**
 * HTML driver settings
 */
$mime_drivers['imp']['html']['inline'] = false;
$mime_drivers['imp']['html']['handles'] = array(
    'text/html');
$mime_drivers['imp']['html']['icons'] = array(
    'default' => 'html.png');
/* If you don't want to display the link to open the HTML content in a
 * separate window, set the following to false. */
$mime_drivers['imp']['html']['external'] = true;

/**
 * Image driver settings
 */
$mime_drivers['imp']['images']['inline'] = true;
$mime_drivers['imp']['images']['handles'] = array(
    'image/*');
$mime_drivers['imp']['images']['icons'] = array(
    'default' => 'image.png');

/**
 * Enriched text driver settings
 */
$mime_drivers['imp']['enriched']['inline'] = true;
$mime_drivers['imp']['enriched']['handles'] = array(
    'text/enriched');
$mime_drivers['imp']['enriched']['icons'] = array(
    'default' => 'text.png');

/**
 * PGP settings
 */
$mime_drivers['imp']['pgp']['inline'] = true;
$mime_drivers['imp']['pgp']['icons'] = array(
    'default' => 'encryption.png' );
$mime_drivers['imp']['pgp']['handles'] = array(
    'application/pgp-encrypted', 'application/pgp-keys', 'application/pgp-signature');

/**
 * PKCS7 settings (S/MIME)
 */
$mime_drivers['imp']['pkcs7']['inline'] = true;
$mime_drivers['imp']['pkcs7']['icons'] = array(
    'default' => 'encryption.png' );
$mime_drivers['imp']['pkcs7']['handles'] = array(
    'application/x-pkcs7-signature', 'application/x-pkcs7-mime',
    'application/pkcs7-signature', 'application/pkcs7-mime');

/**
 * Digest message (message/rfc822) settings
 */
$mime_drivers['imp']['rfc822']['inline'] = false;
$mime_drivers['imp']['rfc822']['handles'] = array(
    'message/rfc822');
$mime_drivers['imp']['rfc822']['icons'] = array(
    'default' => 'mail.png');

/**
 * Zip File Attachments settings
 */
$mime_drivers['imp']['zip']['inline'] = false;
$mime_drivers['imp']['zip']['handles'] = array(
    'application/zip',
    'application/x-compressed',
    'application/x-zip-compressed');
$mime_drivers['imp']['zip']['icons'] = array(
    'default' => 'compressed.png');

/**
 * Delivery Status messages settings
 */
$mime_drivers['imp']['status']['inline'] = true;
$mime_drivers['imp']['status']['handles'] = array(
    'message/delivery-status');

/**
 * Disposition Notification message settings
 */
$mime_drivers['imp']['notification']['inline'] = true;
$mime_drivers['imp']['notification']['handles'] = array(
    'message/disposition-notification');

/**
 * multipart/appledouble settings
 */
$mime_drivers['imp']['appledouble']['inline'] = true;
$mime_drivers['imp']['appledouble']['handles'] = array(
    'multipart/appledouble');

/**
 * iCalendar Transport-Independent Interoperability Protocol
 */
$mime_drivers['imp']['itip']['inline'] = true;
$mime_drivers['imp']['itip']['handles'] = array(
    'text/calendar',
    'text/x-vcalendar',
    'text/x-icalendar',
    'x-extension/vcs',
    'x-extension/ics');
$mime_drivers['imp']['itip']['icons'] = array(
    'default' => 'itip.png');

/**
 * multipart/alternative settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['alternative']['inline'] = true;
$mime_drivers['imp']['alternative']['handles'] = array(
    'multipart/alternative');
/* If you don't want to display the link to show alternative message parts,
 * set the following to false. */
$mime_drivers['imp']['alternative']['show'] = true;

/**
 * multipart/related settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['related']['inline'] = true;
$mime_drivers['imp']['related']['handles'] = array(
    'multipart/related');
$mime_drivers['imp']['related']['icons'] = array(
    'default' => 'html.png');

/**
 * message/partial settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['partial']['inline'] = true;
$mime_drivers['imp']['partial']['handles'] = array(
    'message/partial');

/**
 * All other multipart/* messages
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['multipart']['inline'] = true;
$mime_drivers['imp']['multipart']['handles'] = array(
    'multipart/*');

/**
 * MS-TNEF Attachment (application/ms-tnef) settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['tnef']['inline'] = false;
$mime_drivers['imp']['tnef']['handles'] = array(
    'application/ms-tnef');
$mime_drivers['imp']['tnef']['icons'] = array(
    'default' => 'binary.png');
