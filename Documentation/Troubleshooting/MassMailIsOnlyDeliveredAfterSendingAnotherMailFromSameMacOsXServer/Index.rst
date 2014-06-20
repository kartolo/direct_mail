

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


Mass mail is only delivered after sending another mail from same MAC OS X server
--------------------------------------------------------------------------------

**Problem description:**

Invoking a massmail results in getting an admin-mail which says that
the procedure has started.

Hours later I didn't got any newsletter, nor an admin-mail which says
that sending has stopped.

After invoking a form-mail from another domain on the same server, I
instantly get all those messages, i.e. the admin mail and the
newsletter. They all got the same timestamp as the first received
admin mail.

I send a newsletter to a small group of users in tt\_address. There
was only one (1) user in this list.

**Discussion:**

Asking Google brought the hint that there's some authorization problem
with sendmail. chmod'ing and chown'ing is supposed to help.

Reference: http://www.entropy.ch/software/macosx/php/welcome\_de.html
(german)

**Solution:**

I had to change the group of the "postfix"-directory as described in
the reference, i.e.

sudo chmod g-w /

and

sudo chgrp smmsp /var/spool/postfix

**Source:**

http://bugs.typo3.org/view.php?id=2570


