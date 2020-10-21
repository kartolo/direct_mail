.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Configuring the use of categories
---------------------------------

If you intend to make use of Direct Mail categories, you should do the
following.

First, add static template “Direct Mail Content Boundaries” to the TS
template of the Direct Mail folder. This will ensure that content
boundaries are inserted on the page when it is rendered whenever
categories assignments are found on the content elements of the page.
The static template ensures that content boundaries will be inserted
in both HTML and plain text content. Insertion of boundaries is
specified by setting TS setup property:

.. code-block:: typoscript

   config.insertDmailerBoundaries = 1

This setting is already included in static template “Direct Mail
Content Boundaries” .

Secondly, categories that may be assigned to content elements, to
address records, to FE users or to recipient lists are determined by
Page TSConfig. Therefore, you should configure the following
properties in the Page TsConfig of the Direct Mail folder and,
perhaps, of any page whose subtree may contain records of the
corresponding types and which may be used in Direct Mail operations:

.. code-block:: typoscript

   TCEFORM.tt_content.module_sys_dmail_category.PAGE_TSCONFIG_IDLIST = pid_list
   TCEFORM.tt_address.module_sys_dmail_category.PAGE_TSCONFIG_IDLIST = pid_list
   TCEFORM.fe_users.module_sys_dmail_category.PAGE_TSCONFIG_IDLIST = pid_list
   TCEFORM.sys_dmail_group.select_categories.PAGE_TSCONFIG_IDLIST = pid_list

where pid\_list is the list of page id's on which categories may be
found that may be assigned to records of the given type.

Finally, when the use of categories is thus configured on a page and
its subtree, you may also want the categories assignment field to be
displayed in backend forms. This is achieved by setting the following
properties in Page TSConfig of the same pages:

.. code-block:: typoscript

   TCEFORM.tt_content.module_sys_dmail_category.disabled = 0
   TCEFORM.tt_address.module_sys_dmail_category.disabled = 0
   TCEFORM.fe_users.module_sys_dmail_category.disabled = 0
   TCEFORM.sys_dmail_group.select_categories.disabled = 0

The “Direct Mail Content Boundaries” static template can be found in :file:`Configuration/TypoScript/boundaries/setup.txt`

