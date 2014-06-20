

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


Summary of the Direct Mail process
----------------------------------

The Direct Mail process let you create newsletter pages which can then
be emailed to people on a recipient list. The following are the main
steps of the Direct mail process:

#. Creating a newsletter: a newsletter is basically a regular TYPO3 page
   which resides in the Direct Mail folder; you can view the page in a
   browser and it may also be rendered in plain text.

#. Categorizing the content elements: you may assign categories to the
   content elements of the newsletter; the newsletter emailed to each
   subscriber will be tailored to the categories the subscriber has
   subscribed to.

#. Creating a direct mail: a direct mail is a record that contains a
   compiled version of either a newsletter page or alternatively the
   content of an external url. In addition, the direct mail contains
   information like the mail subject, any attachments, priority settings,
   reply addresses , etc. For each direct mail, a log is kept of who has
   received the direct mail and if they responded to it.

#. Building a recipient list: there is a number of ways by which
   recipient lists may be built. Recipient lists may be built beforehand
   and be reused as needed.

#. Sending a test: it is good practice to send a test mail to make sure
   that the email messages that will be sent to the recipient list look
   as expected when received in a client email application.

#. Invoking the mailer engine: schedule the actual sending of the direct
   mail to a specific recipient list.

#. Viewing the status of mass mailings: monitor the sending of the direct
   mails, view response statistics, and take action on returned mails.

At any point, you may, if needed, use the module context sensitive
help. Backend forms for editing direct mails and recipient lists also
provide context sensitive help on each field.


