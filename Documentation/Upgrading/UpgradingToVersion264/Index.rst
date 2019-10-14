.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Upgrading to version 2.6.4
--------------------------

A new feature (see issue at `Forge
<http://forge.typo3.org/issues/show/3896>`_ , thanks to Sonja Scholz
for providing the patch), which needs an update of the fe\_users table
is integrated in this version. A new column called activate newsletter
(module\_sys\_dmail\_newsletter) has been added to the fe\_users
table. If you're sending newsletter to fe\_users, this field needs to
be marked.

|img-20|

This feature enables the fe\_user to unsubscribe the
newsletter without having to delete its own account
