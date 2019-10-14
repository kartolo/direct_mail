.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Upgrading to version 3.0.0
--------------------------

Following changes are made to the new version 3.0.0

#. Swiftmailer: starting version 3.0.0 direct\_mail using the new
   swiftmailer class instead of the old t3lib\_htmlmail class.

#. SMTP support is dropped. Please configure your SMTP in Install Tool.
   The SMTP data, which are set in install tool, will be used
   automatically by Swiftmailer.

#. The old cronjob (dmailerd.phpcron) is removed. Please set up a
   scheduler task to automatically sending the newsletter

#. Changed database structure. Please do the following step
   
   #. Upon upgrading, you'll see the folowing message
      
      |img-18|
   
   #. On clicking the link, you'll see the updater wizard. After clicking
      the “do it now” button, the changes are written in the database.
      
      |img-19|
