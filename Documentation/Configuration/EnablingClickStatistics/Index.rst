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


Enabling click statistics
-------------------------

To enable the click statistics of the Direct Mail module, enable the
following checkbox in the "Module configuration” function:

|img-16|

This will register all links in the email messages and count all
clicks on them.

Note that form url's are not directed through the jumpurl feature but
rather directly to the target page.

A nice trick is to place a little clear-gif image in your HTML
template and put the parameter dmailerping=”1” in the tag. This will
force the capture function to set the url of this image absolute
through the jumpurl registration. This means in other words that when
this mail is opened it will be registered. This is an additional
feature to the regular feature which registers all links clicked in
the mail.

Example:

::

   <img src="typo3conf/ext/direct_mail/Resources/Public/Icons/dmailerping.gif" width="1" height="1" dmailerping="1" />

Note that the result of the jumpurl setting on the above HTML line is
that the src attribute will be replaced by one that refers to the
address of a script. Such attribute in HTML content of email messages
may be disabled by SPAM filtering software.

If the :ref:`jumpurl_tracking_privacy <jumpurl_tracking_privacy>` is enabled in the configuration, then you might
want to use ``&no_jumpurl=1`` for link, which should not be replaced by
a jump URL.


Adding third party tracking images
----------------------------------

When :ref:`includeMedia <pageTsconfig_includeMedia>` is enabled all added images are embedded.
Put attribute ``do_not_embed="1"`` to the images to prevent it from being embedded.
