.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Configuring the Direct Mail Wizard in User TSConfig
---------------------------------------------------

The following properties may be used in the User TSConfig (BE user or
BE usergroups) to disable some options in the Direct Mail wizard (send
wizard).

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         hideTabs

   Data type
         string

   Description
         Hide the options of the direct mail source in the first step. To hide
         more than one options, you can separate the values with comma.

         Available value:

         - int: hide the internal page option

         - ext: hide the external page option

         - quick: hide the quickmail option

         - dmail: hide the direct mail option


.. container:: table-row

   Property
         hideSteps

   Data type
         string

   Description
         Only to hide the third step (categorizing content). Available value=
         cat


.. container:: table-row

   Property
         defaultTab

   Data type
         string

   Description
         one of the keywords from hideTabs. If set, the chosen tab will be open
         by default. Default value is “dmail”


.. ###### END~OF~TABLE ######
