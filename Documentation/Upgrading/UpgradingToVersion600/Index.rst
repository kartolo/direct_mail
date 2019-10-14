.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Upgrading to version 6.0.0
--------------------------

Following changes are made to the new version 6.0.0

Breaking Changes
""""""""""""""""

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

#. CLI: direct_mail_cli CommandLineController was removed

   This class invoked mailer engine via CLI for mass sending of newsletters.

   This class reqiured a BE-user with the name of `_cli_direct_mail`
   which is no longer necessary and may deleted.

   Instantiating or requiring the PHP class direct_mail_cli,
   will result in PHP fatal error.

   Mass sending of newsletters via CLI by invoking mailer engine
   is still possible. Requires you to change CLI command from

.. code-block:: shell

   /ABS/PATH/TO/SITE/typo3/cli_dispatch.phpsh direct_mail masssend

   to

.. code-block:: shell

   /ABS/PATH/TO/BINARY/typo3 direct_mail:invokemailerengine

   Optional arguments like `masssend` are no longer supported,
   since this was the solely required argument in direct_mail_cli class.

   For help, execute following command to show current available options:

.. code-block:: shell

   /ABS/PATH/TO/BINARY/ direct_mail:invokemailerengine --help
