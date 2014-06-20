

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


Types of recipient lists
------------------------

There are five types of Recipient lists:

#. **From pages** : a list dynamically built from records found on pages
   of the website; you select the pages with the  **Starting Point**
   field; you may include records from subtrees using the  **Include page
   subtree** checkbox; you must specify the types of records to select:
   Address, Website User, Website User Group and/or From custom-defined
   Table; if Website User Group is checked and a Website User Group is
   found, all Website Users members of the group will be included in the
   list; you may also restrict the selection of records to those with
   specific categories assignments;

#. **Plain list** : a list of email addresses separated by space, comma
   or line break; if you change the  **List format** , you may rather
   enter, one per line, a name and address, separated by a comma; sending
   Direct mail to such list will send only plain text email messages;
   moreover, only non-categorized content will be sent;

#. **Static list** : a list of individually selected records; the type of
   record may be Address, Website User, and/or Website User Group; if a
   Website User Group is selected, all Website Users members of the group
   will be included in the list;

#. **Special query** : a list dynamically built using a SQL query; after
   creating the list, you may specify the query against one of the
   tables: Address, Website User or Custom-defined Table; to do so, you
   must select the  **Recipient list** function of the Direct Mail module
   and click on the title of the list;Note:- the custom-defined table
   must be configured in TYPO3 TCA-Array- if you're using a custom
   defined table, you table must have “name” and “email” fields.- TYPO3
   SQL-Query Generator, which is used, currently doesn't support a nm-
   query from a mm-table.

#. **From other recipient lists** : a list dynamically built from other
   recipient lists individually selected from the Recipient list table.

For more information on the individual fields found on the
creation/editing form of recipient lists, please use the contextual
help attached to each such field.


