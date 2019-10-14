.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Direct mails are not sent
-------------------------

Here is a checklist to work through:

#. Direct Mail Boundaries should appear in the source of both the HTML
   and Plain text versions of the page that is used to build the direct
   mail; otherwise, personalized mails will be empty and will not be
   sent.

#. If the direct mail is HTML only, make sure that the recipient
   addresses or recipient FE users have HTML enabled; no mails will be
   sent to recipients that do not have HTML enabled.

#. Make sure there is content for the recipients. Try to include at least
   one content element that will be sent to all recipients, even if they
   subscribe to no category. Personalized mails that are empty will not
   be sent.

#. Sending a simple test mail will test that sending mail does work.

#. Sending a test mail to an individual test address or test recipient
   list configured with the module configuration function will ensure
   that personalization of messages is working.
