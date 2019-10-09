

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


Upgrading to version 6.0.0
--------------------------

Following changes are made to the new version 6.0.0

#. Base url: For TYPO3 v9 compatibility the usage of sys_domain
   records was removed.

   See TYPO3 Deprecation: #85892 -
   Various methods regarding sys_domain-resolving

   Now pages for direct_mail requires a valid site configuration,
   which should contain a base url.
   This base url will also be used in all internal links contained
   in mail content.

   For migration, check possible defined sys_domain records, and
   separate such direct_mail folders into different sites with
   corresponding site-configurations.
