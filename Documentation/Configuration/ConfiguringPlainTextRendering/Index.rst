.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Configuring plain text rendering
--------------------------------

It is good practice to always include a plain text version in email
messages. Some email clients are not able to present the html content
of email messages, but will present the plain text version if
available. Users of many email clients may also have the option to set
their preference as to the format that they wish to see.

In order to configure the rendering of your newsletter pages in plain
text, include static template “Direct Mail Plain Text” on the TS
Template applicable to the pages. This static template contains a pre-
defined template for plain text content rendering and makes it
accessible as page type 99. You may thereafter configure the template
to your needs in the template.

Note: In versions previous to version 2.0, plain text rendering was
achieved by including static template "plugin.alt.plaintext (99)".
This static template may still be used in version 2.0+.

See “Direct Mail Plain Text” static template in :file:`Configuration/TypoScript/plaintext/setup.txt`
