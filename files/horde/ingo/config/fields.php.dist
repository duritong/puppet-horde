<?php
/**
 * $Horde: ingo/config/fields.php.dist,v 1.5 2003/08/19 17:08:41 slusarz Exp $
 *
 * This file defines the set of default match items to display when creating
 * a new filter rule.
 * These fields will only appear if the driver can handle it.
 * Users will have to manually insert the name of the header on the rule
 * creation screen if it does not appear in this list.
 *
 * Format of $ingo_fields array:
 *   'LABEL' => array(
 *     MANDATORY:
 *     'label' => (string)  The gettext label for the entry.
 *     'type'  => (integer) The type of test. Either:
 *                          INGO_STORAGE_TYPE_HEADER  --  Header test
 *                          INGO_STORAGE_TYPE_SIZE    --  Message size test
 *                          INGO_STORAGE_TYPE_BODY    --  Body test
 *     OPTIONAL:
 *     'tests' => (array)   Force these tests to be used only.
 *                          If not set, will use the fields generally
 *                          available to the driver.
 *   )
 */
$ingo_fields = array(
  'To' => array(
    'label' => _("To"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Subject' => array(
    'label' => _("Subject"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Sender' => array(
    'label' => _("Sender"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'From' => array(
    'label' => _("From"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Cc' => array(
    'label' => _("Cc"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Bcc' => array(
    'label' => _("Bcc"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Resent-from' => array(
    'label' => _("Resent-from"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Resent-to' => array(
    'label' => _("Resent To"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'List-Id' => array(
    'label' => _("List-ID"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Received' => array(
    'label' => _("Received"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'X-Spam-Level' => array(
    'label' => _("X-Spam-Level"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'X-Spam-Score' => array(
    'label' => _("X-Spam-Score"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'X-Spam-Status' => array(
    'label' => _("X-Spam-Status"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'X-Priority' => array(
    'label' => _("X-Priority"),
    'type' => INGO_STORAGE_TYPE_HEADER
  ),
  'Size' => array(
    'label' => _("Size"),
    'type' => INGO_STORAGE_TYPE_SIZE,
    'tests' => array('greater than', 'less than')
  ),
  'Body' => array(
    'label' => _("Body"),
    'type' => INGO_STORAGE_TYPE_BODY
  )
);
