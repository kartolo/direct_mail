.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Automatically invoke the mailer engine.
---------------------------------------

There are some way to automatically invoking the direct mail mailer
engine.

#. Scheduler (recommended)

#. The CLI script

It's :underline:`recommended` to use the scheduler option to
automatically invoking the mailer engine.


Configuring direct\_mail on Scheduler
"""""""""""""""""""""""""""""""""""""

This is the most recommended way to automatically sending the
newsletter. In Scheduler module there will be 2 direct\_mail job:

#. Mailing queue

#. Create Mail from draft

The first job sends the mail out. The second one creates a mailing
object based on the configuration saved in the “Direct Mail” module,
with checked “Save these settings as draft” checkbox.


Configuring the CLI script
""""""""""""""""""""""""""

Send newsletters directly via CLI by invoking mailer engine is done by:

.. code-block:: shell

   /ABS/PATH/TO/BINARY/typo3 direct_mail:invokemailerengine

For help and further information or options, execute following command:

.. code-block:: shell

   /ABS/PATH/TO/BINARY/ direct_mail:invokemailerengine --help
