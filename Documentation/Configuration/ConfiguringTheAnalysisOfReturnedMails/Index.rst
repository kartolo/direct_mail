.. include:: Images.txt

.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)


Configuring the analysis of returned mails
------------------------------------------

The analysis of the return mails can now be set in the scheduler job.
It needs the `PHP-IMAP <http://php.net/manual/en/book.imap.php>`_
extension.

#. Create a mailbox (IMAP or POP) for the returned mails, for example:
   “bounce@domain.tld”.

#. Use the Module Configuration function of the Direct mail module to
   configure this same address in the 'Return Path' field in Page TS
   Config:

   |img-17|

#. Now create a task **Analyze bounce mail** in the scheduler module.

#. There you can set following parameter:

   ======================================    ====================================
   Parameter                                 Description
   ======================================    ====================================
   Server URL/IP                             The URL or IP of the mail server
   Port number                               Port of the mail server
   Username                                  Bounce mail address
   Password                                  Password of the bounce mail account
   Type of mailserver                        IMAP or POP
   Number of bounce mail to be processed     How many mail to be fetch in a cycle
   ======================================    ====================================

#. If you have more than one bounce account, you have to create a new scheduler
   task for every bounce mail account.


