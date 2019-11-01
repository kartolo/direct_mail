.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Automatically invoke the mailer engine.
---------------------------------------


Configuring direct\_mail on Scheduler
"""""""""""""""""""""""""""""""""""""

This is the most recommended way to automatically sending the
newsletter. In Scheduler module there will be 2 direct\_mail job:

#. Mailing queue

#. Create Mail from draft

The first job sends the mail out. The second one creates a mailing
object based on the configuration saved in the “Direct Mail” module,
with checked “Save these settings as draft” checkbox.

