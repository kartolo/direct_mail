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


Importing a csv list of email addresses
---------------------------------------

The  **Recipient Lists** module gives you the option to import a csv
(comma-separates list) of address records and create a recipient list
containing the imported records.

To make it easier to import csv records, there is a wizard, which
guide you through the process.

In the first step you can choose to upload a csv file or paste the
records into a text field. You can use comma (;), semicolon (;), or
colon (:) as field delimiter. This can be configured in the second
step.

|img-11|

By clicking the next button, the csv file or csv records are uploaded
and the second step is shown. In this step you can specify the detail
information of the csv data, such as field delimiter, field
encapsulation, and field name in the first line. You can also specify
the SysFolder, where the records should be imported to, the uniqueness
of the records, rename or update the records if a similar record is
found, or to empty the SysFolder before importing.

|img-12|

**ATTENTION!** If you set the field “remove all Addresses in the
storage folder before importing”, all records in this SysFolder
**WILL be physically deleted** .

After specifying the configuration you can start mapping the fields.
There are 3 columns in the mapping step. The description column shows
the first row of the csv records (if you set in the configuration that
the first row is the field names) or shows only field\_xx (where xx is
continuous number).

The mapping column shows only the list of field, which are part of
tt\_address table. You must at least map the field “Name” and “Email”.

The value column shows the first up to three rows from the csv
records. They should help you to map the field.

|img-13| |img-14|

There are some new feature in the importer. You can now set HTML flag
or categories to all records you are importing. In the select box,
which contains the field names of tt\_adress, there's a new entry
called “categories”. This entry can be mapped to a comma-separated
list of direct mail category IDs. This value will overwrite whatever
categories you selected in the “Additional options” section.

After mapping the fields you are ready to start the import process. To
start the import click the import button. After the importing, a list
of new imported, invalid email, updated and doublet records will be
shown.

|img-15|


