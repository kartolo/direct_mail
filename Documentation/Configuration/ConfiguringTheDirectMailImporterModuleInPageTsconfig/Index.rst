

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


Configuring the Direct Mail importer Module in Page TSConfig
------------------------------------------------------------

The Direct Mail configuration properties are set in the Page TSConfig
of the Direct Mail folder under key mod.web\_modules.dmail.importer.

The following properties set default values for corresponding
properties of the direct mail's importer. These properties of direct
mail's importer determine the configurations.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         storage

   Data type
         integer

   Description
         PID of the target SysFolder, in which the recipients will be imported.


.. container:: table-row

   Property
         remove\_existing

   Data type
         boolean

   Description
         Remove all Addresses in the storage folder before importing [0,1]


.. container:: table-row

   Property
         first\_fieldname

   Data type
         boolean

   Description
         First row of import file has fieldnames [0,1]


.. container:: table-row

   Property
         delimiter

   Data type
         string

   Description
         Field delimiter (data fields are separated by...) [comma, semicolon,
         colon, tab]


.. container:: table-row

   Property
         encapsulation

   Data type
         string

   Description
         Field encapsulation character (data fields are encapsed with...)
         [doubleQoute, singleQoute]


.. container:: table-row

   Property
         valid\_email

   Data type
         boolean

   Description
         Only update/import valid emails from csv data. [0,1]


.. container:: table-row

   Property
         remove\_dublette

   Data type
         boolean

   Description
         Filter email dublettes from csv data. If a dublette is found, only the
         first entry is imported. [0,1]


.. container:: table-row

   Property
         update\_unique

   Data type
         boolean

   Description
         Update existing user, instead renaming the new user. [0,1]


.. container:: table-row

   Property
         record\_unique

   Data type
         string

   Description
         Specify the field which determines the uniqueness of imported users.
         [email, name]


.. container:: table-row

   Property
         inputDisable

   Data type
         boolean

   Description
         Disable all of above input field, so that no user can change it. [0,1]


.. container:: table-row

   Property
         resultOrder

   Data type
         string

   Description
         Set the order of import result. Keywords separated with comma. [new,
         update, invalid\_email, double]


.. ###### END~OF~TABLE ######


