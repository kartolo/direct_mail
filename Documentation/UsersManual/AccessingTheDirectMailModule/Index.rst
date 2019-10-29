.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Accessing the Direct Mail module
--------------------------------

Upon the installation of the Direct Mail extension there will be a new
section, called “Direct Mail” in the backend menu. In this section you
will find 5 modules:

#. **Direct Mail** : with this module you can send direct mail based on a
   new newsletter (internal page), an external page, a quick mail or an
   existing direct mail. The sending process is a four or five steps
   wizard.

#. **Recipients Lists** :this function lets you create a new recipient
   list, import Addresses records from csv data to create a new recipient
   list, link to a recipient list editing form, or select an existing
   recipient list; selecting an existing recipient list leads you to a
   screen that lets you view the number of recipients of each type in the
   list, with the options to list the recipients or download a csv file.
   The import process is a wizard. You can upload a CSV file and freely
   mapping the fields

#. **Statistics:** it shows a list of sent direct mails. If you choose a
   direct mail, the detailed statistics will be shown. You can also list,
   disable or download the list of recipients, whose mails are returned.

#. **Mailer Engine** : shows the queue of the mailer engine and the
   status of the cron job (if it's set). You can also manually invoke the
   engine by clicking the link. This link can be hidden per UserTS or
   PageTS.

#. **Configuration** : in this module you can configure the direct mail.
   The configuration is saved in the Direct Mail SysFolder. It means that
   every SysFolder has a different configuration. In this module there a
   second function menu, the **Categories Conversion** . This function is
   only used if you're upgrading to Direct Mail version 2.0.0. With the
   introduction of version 2.0.0 Direct Mail saves the Categories in a
   table.

|img-1|
