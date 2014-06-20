.. include:: Images.txt

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


Configuring the analysis of returned mails
------------------------------------------

There are probably many ways to configure analysis of returned mails.
We propose an approach based on open Source program fetchmail. For
more information about fetchmail, see `The fetchmail home page
<http://catb.org/~esr/fetchmail/>`_ .

#. Create a mailbox (popbox) for the returned mails, for example:
   “bounce@pophost.org”. This mailbox should be located on the same
   machine as the TYPO3 installation.

#. Use the Module Configuration function of the Direct mail module to
   configure this same address in the 'Return Path' field in Page TS
   Config:

   |img-17|

#. fetchmail can read a mailbox and then do something with these mails.
   We are going to use fetchmail to “pipe” the returned mails to the
   “returnmail.phpsh” script. fetchmail uses a configuration file that
   should be outside the web accessible folder. For examble, it may be
   positionned on the root: /root/.fetchmailrc. ls -l of this file may
   look like this:

   ::

      -rwx--x---  1 root root 208 Jun 20 12:50 /root/.fetchmailrc

#. Insert the following line in file .fetchmailrc, substituting variables
   my.pophost.org with the name of your mailserver, and username-of-
   popbox and password-of-popbox with the name and password of your
   bounce mailbox:

   ::

      poll my.pophost.org timeout 40 username "username-of-popbox" password "password-of-popbox" flush mda "/path/to/your/TYPO3/installation/typo3conf/ext/direct_mail/res/scripts/returnmail.phpsh"

#. Note that the absolute path to the script must be specified. If the
   extension is installed as a global extension, substitute typo3conf
   with typo3 in the above path. Make sure that script returnmail.phpsh
   has sufficient permissions to be run by the server. Note also that
   returnmail.phpsh is a shell script and requires the availability of a
   PHP binary, "/usr/bin/php”. Depending on your server configuration,
   you may have to edit the first line of the script to refer to the
   location of the PHP binary.

#. If you have configured multiple Direct Mail folders each with its own
   return mailbox, you will need a similar line for each mailbox.

#. Use the command “crontab -e” (as root), or cPanel tool, to add the
   following cron task. You need only one, even if you have configured
   multiple folders of Direct Mail. This example setting will run the
   cron task every 10 minutes:

   ::

      */10 * * * * fetchmail> /dev/null

#. Use the Direct Mail module to send a newsletter to a bouncing address.
   Use the Statistics function of the module to verify that the bounced
   mail is accounted for in the displayed statistics.


