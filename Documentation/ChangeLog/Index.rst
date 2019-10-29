.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


Change Log
==========

For a complete change log, please refer to the ChangeLog file.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Version
         Version:
   
   Changes
         Changes:


.. container:: table-row

   Version
         2.0.0
   
   Changes
         Initial version of this document.


.. container:: table-row

   Version
         2.0.1
   
   Changes
         Fix Option includeMedia was not used when fetching direct mail. All
         mails were sent using absolute links.


.. container:: table-row

   Version
         2.1.0
   
   Changes
         Fix getMimeType method until it is fixed in the core. See bugtracker
         issue 3172.
         
         Systematic review of the use of enableFields and deleteClause methods
         in mod\_web\_dmail. Use BEfunc instead of pageSelect method.
         
         Fix regression of extension constraints in ext\_emconf.php.


.. container:: table-row

   Version
         2.1.1
   
   Changes
         Correct sql error when sending mails to a recipient list of type
         'Plain list'.
         
         Rewrite method getMimeType so that it uses the API form t3lib\_div.


.. container:: table-row

   Version
         2.1.2
   
   Changes
         Fix bugtracker issue 3298: Fatal error: Call to a member function on a
         non-object in statistics function.
         
         Fix bugtracker issue 3301: tca.php has short php open tag.
         
         Remove most field exclusions in tca.php.
         
         Issue error messages when content cannot be fetched.
         
         Issue warning message when fetched content contains no direct mail
         boundaries.
         
         Include all content in messages when there are no boundaries in the
         content.


.. container:: table-row

   Version
         2.1.4
   
   Changes
         Fix bugtracker issue 3444: missing value-check in tt\_news plain-text
         rendering script.
         
         Fix bugtracker issue 3317: too short require\_once statements in
         mod/class.mod\_web\_dmail.php.
         
         Remove references to $HTTP\_\*\_VARS in dmailerd.phpcron and
         returnmail.phpsh.
         
         Fix issue 3489: setting tt\_news to "strict" will cause an error in
         direct mail.
         
         Update to the structure of the manual.


.. container:: table-row

   Version
         2.1.5
   
   Changes
         Delete file pi1/locallang.php
         
         Add Spanish labels in pi1/locallang.xml
         
         Replace br tag by linefeed (instead of space) in plain text rendering
         plugin.
         
         Correction: missing character set conversion of mail subject when mail
         character set is different from the backend charset.
         
         Correction: mail subject would be encoded multiple times. Thanks to
         `David Bocher
         <mailto:david_bocher@yahoo.fr?subject=EXT:%20Direct%20Mail>`_ .
         
         Chapter "Personalizing direct mail content" was augmented. Yhanks to
         `Thorsten Kahler
         <mailto:thorsten.kahler@dkd.de?subject=EXT:%20Direct%20Mail>`_ .
         
         Correction: display of some statistics was erroneous. Thanks to `David
         Bocher <mailto:david_bocher@yahoo.fr?subject=EXT:%20Direct%20Mail>`_ .
         
         Two new constants in the “Direct Mail Plain text” static template:
         doubleLF and removeSplitChar.
         
         Partial fix for bugtracker issue 3207: Recipient list from Website
         User Group and users with multiple user groups. Will work at least
         with MySQL.


.. container:: table-row

   Version
         2.5.0
   
   Changes
         Wizard based sending.
         
         Wizard based importing of csv records.
         
         Main functions (Direct Mail, Recipient Lists, Statistics, Mailer
         Engine, Configuration) in separate modules.


.. ###### END~OF~TABLE ######

For later versions se `Github
<https://github.com/kartolo/direct_mail>`_