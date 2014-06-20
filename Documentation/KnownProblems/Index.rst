

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


Known Problems
==============

Please see/report problems in the direct\_mail project page on `TYPO3
Forge <http://forge.typo3.org/projects/extension-direct_mail/issues>`_

You may get support in the use of this extension by subscribing to
`news://news.netfielders.de/typo3.projects.direct-mail
<news://news.netfielders.de/typo3.projects.direct-mail>`_ .

#. SMTP / PEAR
   
   There is a bug in PEAR class in sending email to user with special
   characters in the name field. Example: it won't send email to a user,
   whose name is test-directmail.
   
   More of this bug please see `PEAR Bugtracker
   <http://pear.php.net/bugs/bug.php?id=11238>`_


