<?php
/**
 * Copyright 2005-2007 Karsten Fourmont <karsten@horde.org>
 *
 * $Horde: framework/SyncML/SyncML/Constants.php,v 1.1.2.5 2007/01/02 13:54:41 jan Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <karsten@horde.org>
 * @package SyncML
 */
define('ALERT_DISPLAY', 100);

// Not implemented.
define('ALERT_TWO_WAY', 200);
define('ALERT_SLOW_SYNC', 201);
define('ALERT_ONE_WAY_FROM_CLIENT', 202);
define('ALERT_REFRESH_FROM_CLIENT', 203);
define('ALERT_ONE_WAY_FROM_SERVER', 204);
define('ALERT_REFRESH_FROM_SERVER', 205);

// Not implemented.
define('ALERT_TWO_WAY_BY_SERVER', 206);
define('ALERT_ONE_WAY_FROM_CLIENT_BY_SERVER', 207);
define('ALERT_REFRESH_FROM_CLIENT_BY_SERVER', 208);
define('ALERT_ONE_WAY_FROM_SERVER_BY_SERVER', 209);
define('ALERT_REFRESH_FROM_SERVER_BY_SERVER', 210);

define('ALERT_RESULT_ALERT', 221);
define('ALERT_NEXT_MESSAGE', 222);
define('ALERT_NO_END_OF_DATA', 223);

define('MIME_SYNCML_XML', 'application/vnd.syncml+xml');
define('MIME_SYNCML_WBXML', 'application/vnd.syncml+wbxml');

define('MIME_SYNCML_DEVICE_INFO_XML', 'application/vnd.syncml-devinf+xml');
define('MIME_SYNCML_DEVICE_INFO_WBXML', 'application/vnd.syncml-devinf+wbxml');

define('MIME_TEXT_PLAIN', 'text/plain');
define('MIME_VCARD_V21', 'text/x-vcard');
define('MIME_VCARD_V30', 'text/vcard');

define('MIME_VCALENDAR', 'text/x-vcalendar');
define('MIME_ICALENDAR', 'text/calendar');
define('MIME_XML_ICALENDAR', 'application/vnd.syncml-xcal');

define('MIME_MESSAGE', 'text/message');

define('MIME_SYNCML_XML_EMAIL', 'application/vnd.syncml-xmsg');
define('MIME_SYNCML_XML_BOOKMARK', 'application/vnd.syncml-xbookmark');
define('MIME_SYNCML_RELATIONAL_OBJECT', 'application/vnd.syncml-xrelational');

define('RESPONSE_IN_PROGRESS', 101);

define('RESPONSE_OK', 200);
define('RESPONSE_ITEM_ADDED', 201);
define('RESPONSE_ACCEPTED_FOR_PROCESSING', 202);
define('RESPONSE_NONAUTHORIATATIVE_RESPONSE', 203);
define('RESPONSE_NO_CONTENT', 204);
define('RESPONSE_RESET_CONTENT', 205);
define('RESPONSE_PARTIAL_CONTENT', 206);
define('RESPONSE_CONFLICT_RESOLVED_WITH_MERGE', 207);
define('RESPONSE_CONFLICT_RESOLVED_WITH_CLIENT_WINNING', 208);
define('RESPONSE_CONFILCT_RESOLVED_WITH_DUPLICATE', 209);
define('RESPONSE_DELETE_WITHOUT_ARCHIVE', 210);
define('RESPONSE_ITEM_NO_DELETED', 211);
define('RESPONSE_AUTHENTICATION_ACCEPTED', 212);
define('RESPONSE_CHUNKED_ITEM_ACCEPTED_AND_BUFFERED', 213);
define('RESPONSE_OPERATION_CANCELLED', 214);
define('RESPONSE_NO_EXECUTED', 215);
define('RESPONSE_ATOMIC_ROLL_BACK_OK', 216);

define('RESPONSE_MULTIPLE_CHOICES', 300);
// Need to change names.
// define('RESPONSE_MULTIPLE_CHOICES', 301);
// define('RESPONSE_MULTIPLE_CHOICES', 302);
// define('RESPONSE_MULTIPLE_CHOICES', 303);
// define('RESPONSE_MULTIPLE_CHOICES', 304);
define('RESPONSE_USE_PROXY', 305);

define('RESPONSE_BAD_REQUEST', 400);
define('RESPONSE_INVALID_CREDENTIALS', 401);
// Need to change names.
// define('RESPONSE_INVALID_CREDENTIALS', 402);
// define('RESPONSE_INVALID_CREDENTIALS', 403);
define('RESPONSE_NOT_FOUND', 404);
// Need to change names.
// define('RESPONSE_INVALID_CREDENTIALS', 405);
// define('RESPONSE_INVALID_CREDENTIALS', 406);
define('RESPONSE_CREDENTIALS_MISSING', 407);
// define('RESPONSE_INVALID_CREDENTIALS', 408);
// define('RESPONSE_INVALID_CREDENTIALS', 409);
// define('RESPONSE_INVALID_CREDENTIALS', 410);
// define('RESPONSE_INVALID_CREDENTIALS', 411);
// define('RESPONSE_INVALID_CREDENTIALS', 412);
// define('RESPONSE_INVALID_CREDENTIALS', 413);
// define('RESPONSE_INVALID_CREDENTIALS', 414);
// define('RESPONSE_INVALID_CREDENTIALS', 415);
define('RESPONSE_REQUEST_SIZE_TOO_BIG', 416);
// Need to change names.
// define('RESPONSE_INVALID_CREDENTIALS', 417);
// define('RESPONSE_INVALID_CREDENTIALS', 418);
// define('RESPONSE_INVALID_CREDENTIALS', 419);
// define('RESPONSE_INVALID_CREDENTIALS', 420);
// define('RESPONSE_INVALID_CREDENTIALS', 421);
// define('RESPONSE_INVALID_CREDENTIALS', 422);
// define('RESPONSE_INVALID_CREDENTIALS', 423);
define('RESPONSE_SIZE_MISMATCH', 424);

define('RESPONSE_COMMAND_FAILED', 500);
// Need to change names.
// define('RESPONSE_COMMAND_FAILED', 501);
// define('RESPONSE_COMMAND_FAILED', 502);
// define('RESPONSE_COMMAND_FAILED', 503);
// define('RESPONSE_COMMAND_FAILED', 504);
// define('RESPONSE_COMMAND_FAILED', 505);
// define('RESPONSE_COMMAND_FAILED', 506);
// define('RESPONSE_COMMAND_FAILED', 507);
// define('RESPONSE_COMMAND_FAILED', 508);
// define('RESPONSE_COMMAND_FAILED', 509);
// define('RESPONSE_COMMAND_FAILED', 510);
// define('RESPONSE_COMMAND_FAILED', 511);
// define('RESPONSE_COMMAND_FAILED', 512);
// define('RESPONSE_COMMAND_FAILED', 513);
// define('RESPONSE_COMMAND_FAILED', 514);
// define('RESPONSE_COMMAND_FAILED', 515);
// define('RESPONSE_ATOMIC_ROLL_BACK_FAILED', 516);

define('NAME_SPACE_URI_SYNCML_1_1', 'syncml:syncml1.1');
define('NAME_SPACE_URI_SYNCML', 'syncml:syncml');
define('NAME_SPACE_URI_METINF', 'syncml:metinf');
define('NAME_SPACE_URI_METINF_1_1', 'syncml:metinf');
define('NAME_SPACE_URI_DEVINF', 'syncml:devinf');
define('NAME_SPACE_URI_DEVINF_1_1', 'syncml:devinf');

/**
 * If set to true, logs all incoming and outgound icalendar/vcard data
 * to /tmp/sync/log.txt. The web server user (like wwwrun) must have
 * write access to this file.
 */
define('DEBUGLOG_ICALENDARDATA', true);

/**
 * If set to true, a copy of any DevInf received from clients
 * is saved to /tmp/sync/devinf.txt.
 * The web server user (like wwwrun) must have
 * write access to this file.
 */

define('DEBUGLOG_DEVINF', true);
