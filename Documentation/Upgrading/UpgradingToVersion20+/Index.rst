

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


Upgrading to version 2.0+
-------------------------

The two main considerations when upgrading to version 2.0+ from a
previous version are:

- the new static templates: see sections “Configuring plain text
  rendering” and “Configuring the use of categories”;

- the conversion of categories: in previous versions, categories were
  defined in the Page TSConfig of the Direct Mail folder. Version 2.0
  introduces a new Direct Mail Category database table.

When upgrading to version 2.0+, it is necessary to convert pre-
existing categories in Page TSConfig and create corresponding Direct
Mail Categories in the Direct Mail folders. If any categories were
assigned to Content elements, Addresses , FE Users or Recipient Lists,
these assignments also need to be converted to refer to the newly
created Direct Mail Categories.

Conversion of categories is NOT revertible. Therefore, it would be
prudent to take a backup of the TYPO3 database before this conversion
is performed.

When upgrading to version 2.0, an additional option is presented in
the function drop-down menu of the Extension Manager: UPDATE! The
additional option is presented if a Direct Mail folder already exists
and until at least one Direct Mail Category has been created.

If you select the UPDATE! option, pre-existing categories defined in
Page TSConfig will be converted into new Direct Mail Categories. The
Direct Mail categories will be created in each of the Direct Mail
folders for which category definitions may be found in Page TSConfig.

Conversion of categories assignments in Content elements, Addresses,
FE Users and Recipient Lists will also be attempted. If the use of
categories was not yet configured, this part of the conversion process
will fail, but may re-attempted a later time, using the “Categories
conversion” function of the Direct Mail module. The next step is thus
to configure the use of categories: see the section of this document
on this subject.

Note that the conversion of categories assignments is only simulated,
until you effectively confirm the conversion. Once confirmed, the
records are updated and the conversion cannot be undone without
restoring the table with some database backup.

When the use of categories is configured, you may re-attempt the
conversion of categories assignments using the “Categories conversion”
option of the function menu of the Direct Mail module. The function
will report statistics on the conversion being performed, as well as
conversion already done. It will also list the records that could not
be converted: this exception would normally be due to the fact that
the use of categories is not configured for this type of record in the
page subtree in which it is found.

Although it may not be undone, categories conversion may be re-
attempted as many times as required without causing any harm.


