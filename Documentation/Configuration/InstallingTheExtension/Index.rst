.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Installing the extension
------------------------

Import and install the extension using the Extension Manager.

You may be required to install extension Address list (tt\_address)
which is a prerequisite of extension Direct Mail.

Some updates to the database structure may be needed. Press the “Make
updates” button to make these updates.

When the extension is installed, a “Direct Mail” Section and five new
modules will appear above the Help section of the backend menu,
provided that the user is granted access using the User Admin tool.

The Extension Manager installation dialog allows to set the following
extension configuration variables:

- **Number of messages per cycle:** number of messages sent per cycle of
  the mailing engine cron task; default value is 50;

- **Language of the cron task:** the TYPO3 language code of the language
  to be used by the mailing engine cron task when progress messages are
  sent to the administrator; default value is “en”;

- **Additional DB fields of the recipient:** additional fields that may
  be substituted in the direct mail messages;

- **Administrator Email:** Email will be sent to this address, if there
  is an error in the cron task. Especially to notify administrator of
  the site if the table can't be written (Direct Mail creates a lot of
  log records while sending the email).

- **Interval of the cronjob:** Time interval of the cron task you set in
  minute. This is used to control if the cron task is still running or
  if there is an error while sending the email.

- **Enable notification email:** Allow Direct Mail to send notification
  emails about start and end of mailing job.

- **Enable plain text rendering of News** : if set, a script will be
  enabled to render News (tt\_news records) in plain text for inclusion
  as plain text content of email messages.


- **Use HTTP to fetch**: always use http to fetch the newsletter regardless https in BE

If you are upgrading to version 2.0+ from an earlier version, an
additional entry may be presented in the function drop-down menu of
the extension manager: UPDATE! This option provides a function to
convert some tables of your database. You should read the section
below about upgrading to version 2.0+.
