

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


Personalizing direct mail content
---------------------------------

You may personalize the direct mails that will be sent to each
recipient by inserting the following markers in content elements of
the newsletter page. The markers will be substituted with the
corresponding value found in each recipient record, whenever
available. This is the list of predefined markers:

- ###USER\_uid### (the unique id of the recipient)

- ###USER\_name### (full name)

- ###USER\_firstname### (first name calculated)

- ###USER\_NAME### and ###USER\_FIRSTNAME### will insert uppercase
  versions of the equivalents

- ###USER\_title###

- ###USER\_email###

- ###USER\_phone###

- ###USER\_www###

- ###USER\_address###

- ###USER\_company###

- ###USER\_city###

- ###USER\_zip###

- ###USER\_country###

- ###USER\_fax###

- ###SYS\_TABLE\_NAME###

- ###SYS\_MAIL\_ID###

- ###SYS\_AUTHCODE###

Additional fields from the recipient table (e.g. defined by an
extension) can be configured in the Extension Manager. You have to
switch to "Information" view of EXT:direct\_mail and set a comma-
separated list of DB fields in "Additional DB fields of the
recipie..." (addRecipFields) to make more markers available. These can
be used according to the pattern above like ###USER\_<some field>###.

Personalization only works for recipient lists of type "From pages"
(and "From other recipient lists", if these contain "From pages"
lists).


