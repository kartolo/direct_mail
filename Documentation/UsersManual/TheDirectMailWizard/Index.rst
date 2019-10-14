.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


The Direct Mail Wizard
----------------------

The Direct Mail Wizard is located in the first module (Direct Mail).
After choosing a Direct Mail SysFolder, the first step is shown. Four
possible sources of a Direct Mail is shown: internal page (TYPO3
page), external page, quickmail and from existing Direct Mail.

|img-2|

#. **Internal Page**

   All page in the selected SysFolder will be listed. There will be 3
   icons, edit icon and two preview icon. With the edit icon you can edit
   the page contents. The first preview icon shows the HTML-version and
   the second one shows the Plaintext-version of the direct mail. Beside
   that you can create a new page by clicking the last link.

   By clicking the page title or the envelope icon, you choose a page as
   a direct mail and the second step will be shown.

   |img-3|

#. **External Page**

   To create a new direct mail based on an external page, you should
   insert the HTML and Plaintext URL of the external page and the subject
   of the direct mail. To continue to the second step click the “create
   mail” button

   |img-4|

#. **Quickmail**

   Defaultvalues for the sender name and email are the values, which you
   entered in the configuration module. Youcan only send plaintext
   version. In the second step you can put attachments like a normal
   direct mail.

   |img-5|

#. **Direct Mail**

   All existing and not yet sent direct mail are listed in this option.
   You can choose an existing direct mail to continue the sending process
   by clicking the subject of the direct mail. To delete an already
   created newsletter, just click the trash icon.

   |img-6|

|img-7|

In the second step, the detail information of the direct mail is shown
and you can manually change this, e.g. insert attachments, by clicking
the edit button. In this step the content of of the direct mail is
automatically fetched and an information on the fetching is also shown
(successful, warning or error). If there is an error, you should leave
the wizard , correct this error (e.g. by clearing FE-cache) and
continue the sending process by choosing from the list of existing
Direct Mail (fourth Option in the first step).

The third step is the categorizing of the content. This step will be
shown if the direct mail's source is a TYPO3 step. If the direct
mail's source is other than TYPO3 page, then it will be skipped to
step four. After choosing the categories, click the “update category
settings” button to save the assignment of the categories.

To continue the sending process, click the next button. The fourth
step will be shown.

|img-8|

The fourth step is sending test mails. If UIDs of tt\_address or
direct mail groups in the configuration module are given, they will be
shown and click the name will send email to them and this step will be
shown again. If there is no UID given in the configuration module,
there will be only a text field, where you can give an email address.

After finish testing the direct mail, click on the next button will
show the fifth and final step.

|img-9|

The last step is mass sending the direct mail. After choosing a
recipient lists from a drop down lists, you can choose time, when the
mailer engine start sending it. The button next to the input field
will show a pop up calendar. You can choose date and time. By clicking
the “send to all subscribers in the recipient list” button, the direct
mail is released to sending.

The number in the brackets shows the amount of the recipients in the
list.

If you check the “Send this as test newsletter” box, the subject of
the email will be prepended with the text, which you can define in the
configuration module (in the “additional module options” section).

The checkbox “Save these settings as draft” is useful only if you
activated the according scheduler job (see “invoke mailer engine”
section).

|img-10|
