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

In order to configure the rendering f your newsletter pages in plain
text, include static template “Direct Mail Plain Text” on the TS
Template applicable to the pages. This static template contains a pre-
defined template for plain text content rendering and makes it
accessible as page type 99. You may thereafter configure the template
to your needs in the template.

Note: In versions previous to version 2.0, plain text rendering was
achieved by including static template "plugin.alt.plaintext (99)".
This static template may still be used in version 2.0+.

The “Direct Mail Plain Text” static template is as follows:

Constants
"""""""""

.. code-block:: typoscript

   plugin.tx_directmail_pi1 {
       # cat=plugin.tx_directmail_pi1//; type=string; label= Site url: Enter the url of the site here.
     siteUrl = http://www.example.test/
       # cat=plugin.tx_directmail_pi1/enable/; type=boolean; label= Use flowed format: The same option should be set on the direct mail.
     FlowedFormat = 0
         # cat=plugin.tx_directmail_pi1/enable/; type=boolean; label= Double line feeds: Line feeds found in bodytext will be doubled in the plain text version.
     doubleLF = 0
         # cat=plugin.tx_directmail_pi1//; type=string; label= Split char to remove from graphical headers: Headers built as GIFBUILDER objects may contain split characters. If specified here, they will be removed from headers of type 5 in the plain text version.
     removeSplitChar =
   }


Setup
"""""

.. code-block:: typoscript

   plugin.tx_directmail_pi1 = USER
   plugin.tx_directmail_pi1.userFunc = tx_directmail_pi1->main
   plugin.tx_directmail_pi1 {

     siteUrl = {$plugin.tx_directmail_pi1.siteUrl}
     flowedFormat = {$plugin.tx_directmail_pi1.flowedFormat}

     header.defaultType = 1
     header.date = D-m-Y
     header.datePrefix = |###HEADER_DATE_PREFIX### |
     header.linkPrefix = | ###HEADER_LINK_PREFIX### |
     header.1.preLineLen = 76
     header.1.postLineLen = 76
     header.1.preBlanks=1
     header.1.stdWrap.case = upper

     header.2 < .header.1
     header.2.preLineChar=*
     header.2.postLineChar=*

     header.3.preBlanks=2
     header.3.postBlanks=1
     header.3.stdWrap.case = upper

     header.4 < .header.1
     header.4.preLineChar= =
     header.4.postLineChar= =
     header.4.preLineBlanks= 1
     header.4.postLineBlanks= 1

     header.5.removeSplitChar = {$plugin.tx_directmail_pi1.removeSplitChar}
     header.5.preBlanks=1
     header.5.autonumber=1
     header.5.prefix = |: >> |

     defaultOutput (
   |
   [###UNRENDERED_CONTENT### ###CType### ]
   |
     )

     uploads.header = |###UPLOADS_HEADER###|

     images.header = |###IMAGES_HEADER###|
     images.linkPrefix = | ###IMAGE_LINK_PREFIX### |
     images.captionHeader = |###CAPTION_HEADER###|

     bulletlist.0.bullet = |*  |
     bulletlist.1.bullet = |#  |
     bulletlist.2.bullet = | - |
     bulletlist.3.bullet = |>  |
     bulletlist.3.secondRow = |.  |
     bulletlist.3.blanks = 1

     menu =< tt_content.menu.20
     shortcut =< tt_content.shortcut.20
     shortcut.0.conf.tt_content =< plugin.tx_directmail_pi1
     shortcut.0.tables = tt_content

     bodytext.doubleLF = {$plugin.tx_directmail_pi1.doubleLF}
     bodytext.stdWrap.parseFunc.tags {
       link =< lib.parseFunc_RTE.tags.link
       typolist = USER
       typolist.userFunc = tx_directmail_pi1->typolist
       typolist.siteUrl = {$plugin.tx_directmail_pi1.siteUrl}
       typolist.bulletlist =< plugin.tx_directmail_pi1.bulletlist
       typohead = USER
       typohead.userFunc = tx_directmail_pi1->typohead
       typohead.siteUrl = {$plugin.tx_directmail_pi1.siteUrl}
       typohead.header =< plugin.tx_directmail_pi1.header
       typocode = USER
       typocode.userFunc = tx_directmail_pi1->typocode
       typocode.siteUrl = {$plugin.tx_directmail_pi1.siteUrl}
     }
   }

   includeLibs.tx_directmail_pi1 = EXT:direct_mail/pi1/class.tx_directmail_pi1.php

   tx_directmail_pi1 >
   tx_directmail_pi1 = PAGE
   tx_directmail_pi1.typeNum=99

   tx_directmail_pi1.config {
           disableAllHeaderCode = 1
           additionalHeaders = Content-type:text/plain
   }
   tx_directmail_pi1.10 = TEMPLATE
   tx_directmail_pi1.10 {
           template = FILE
           template.file = EXT:direct_mail/Resources/Private/Plaintext/tx_directmail_pi1_plaintext.tmpl
           marks.CONTENT < styles.content.get
           marks.CONTENT.renderObj = < plugin.tx_directmail_pi1
           marks.DATE = TEXT
           marks.DATE.data = date:U
           marks.DATE.strftime = %e. %B %Y
   }
