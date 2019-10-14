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

#. Using scheduled task with Gabriel (Ext: Gabriel)

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

Since TYPO3 4.x there is a CLI mode for TYPO3. The direct\_mail CLI
script uses the new CLI-API, which is available since TYPO3 4.1.x.

Before writing a cron task in your crontab, a BE-user with the name of
“\_cli\_direct\_mail” has to be created. This user must have no
administrator right. After creating the BE-user, you can write the
following line in the crontab:

.. code-block:: shell

   */5 * * * * /ABS/PATH/TO/SITE/typo3/cli_dispatch.phpsh direct_mail masssend

This will call the CLI-script with two parameters: the extension's key
(direct\_mail) and a task (masssend).


Configuring direct\_mail on Gabriel
"""""""""""""""""""""""""""""""""""

This is also deprecated, since Gabriel extension is renamed Scheduler
and since 4.5.x a system extension.

Please refer to the documentation of Gabriel. The direct\_mail task
will be shown in the list of task automatically.

