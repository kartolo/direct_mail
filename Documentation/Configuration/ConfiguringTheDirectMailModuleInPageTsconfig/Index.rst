.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Configuring the Direct Mail module in Page TSConfig
---------------------------------------------------

The Direct Mail configuration properties are set in the Page TSConfig
of the Direct Mail folder under key mod.web\_modules.dmail.

Note that all these properties may conveniently be set using the
Direct Mail module function “Module configuration”.

The following properties set default values for corresponding
properties of direct mails. These properties of direct mails determine
the headers inserted in the direct mail messages.

.. ### BEGIN~OF~TABLE ###


.. container:: table-row

   Property
         from\_email

   Data type
         string

   Description
         Default value for the 'From' or sender email address of direct mails.
         (Required)

         Note: This email address appears as the originating address or sender
         address in the direct mails received by the recipients.


.. container:: table-row

   Property
         from\_name

   Data type
         string

   Description
         Default value for 'From' or sender name of direct mails. (Required)

         Note: This name appears as the name of the author or sender in the
         direct mails received by the recipients.


.. container:: table-row

   Property
         replyto\_email

   Data type
         string

   Description
         Default value for 'Reply To' email address.

         Note: This is the email address to which replies to direct mails are
         sent. If not specified, the 'From' email is used.


.. container:: table-row

   Property
         replyto\_name

   Data type
         string

   Description
         Default value for 'Reply To' name.

         Note: This is the name of the 'Reply To' email address. If not
         specified, the 'From' name is used.


.. container:: table-row

   Property
         return\_path

   Data type
         string

   Description
         Default return path email address.

         Note: This is the address to which non-deliverable mails will be
         returned to.

         Note: If you put in the marker ###XID###, it'll be substituted with
         the unique id of the mail recipient.

         Note: The return path email address cannot be set by the Direct Mail
         module if PHP is running with safe\_mode enabled.


.. container:: table-row

   Property
         organisation

   Data type
         string

   Description
         Name of the organization sending the mail.


.. container:: table-row

   Property
         priority

   Data type
         int+

   Description
         Default priority of direct mails.

         Possible values are:

         1 - High

         3 - Normal

         5 – Low

         Default: 3


.. ###### END~OF~TABLE ######

The following properties set default values for corresponding
properties of direct mails. These properties of direct mails determine
the format of the content of direct mail messages.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         sendOptions

   Data type
         int+

   Description
         Default value for the format of email content.

         If in doubt, set it to 3 (Plain and HTML). The recipients are normally
         able to select their preferences anyway.

         Possible values are:

         1 - Plain text only

         2 - HTML only

         3 - Plain and HTML

         Default: 3


.. _pageTsconfig_includeMedia:

.. container:: table-row

   Property
         includeMedia

   Data type
         boolean

   Description
         Default value for this direct mail option: if set, images and other
         media are incorporated into the HTML mail content.

         Note: When this option is set on a direct mail, images and other media
         are encoded and incorporated into the messages. Sent messages will be
         heavier to transport.

         Note: To prevent embedding of a specific image add ``do_not_embed="1"`` to
         the image tag. This can be useful for adding third party tracking.

         When the option is not set, images and media are included in HTML
         content by absolute reference (href) to their location on the site
         where they reside.

         Default: 0


.. container:: table-row

   Property
         flowedFormat

   Data type
         boolean

   Description
         Default value for this direct mail option: if set, text will flow
         normally in the plain text content of email messages.

         Note: If the option is set, plain text mail content will still be
         broken in fixed length lines, as is standard for plain text email
         content, but so-called flowed format will be used. This will allow
         client agents that support this format to display the text as normally
         flowing text. The option is ignored if 'quoted-printable' is used.

         Note: this setting will produce email headers with 'format=flowed'.
         See `http://www.ietf.org/rfc/rfc3676.txt
         <http://www.ietf.org/rfc/rfc3676.txt>`_ for more information.

         Note: In order for plain text content to be correctly rendered for
         effective use of this option, the flowedFormat property should also be
         set in the TS template of the plain text rendering plugin.


.. ###### END~OF~TABLE ######

The following properties set default values for corresponding
properties of direct mails. These properties of direct mails specify
parameters used to fetch the content of the direct mails.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         HTMLParams

   Data type
         string

   Description
         Default value for additional URL parameters used to fetch the HTML
         content from a TYPO3 page.

         Note: The specified parameters will be added to the URL used to fetch
         the HTML content of the direct mail from a TYPO3 page. If in doubt,
         leave it blank.


.. container:: table-row

   Property
         plainParams

   Data type
         string

   Description
         Default value for additional URL parameters used to fetch the plain
         text content from a TYPO3 page.

         Note: The specified parameters will be added to the URL used to fetch
         the plain text content of the direct mail from a TYPO3 page.

         Note: If in doubt, set it either to '&type=99' or, when TemplaVoila is
         used, to '&print=1'.

         Default: &type=99


.. ###### END~OF~TABLE ######

The following properties specify the content transfer encodings and
character sets to use when sending mails.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         quick\_mail\_encoding

   Data type
         string

   Description
         Content transfer encoding to use when sending quick mails.

         Possible values:

         quoted-printable

         base64

         8bit

         Default: quoted-printable


.. container:: table-row

   Property
         direct\_mail\_encoding

   Data type
         string

   Description
         Default value for the content transfer encoding of direct mails.

         Possible values:

         quoted-printable

         base64

         8bit

         Default: quoted-printable


.. container:: table-row

   Property
         quick\_mail\_charset

   Data type
         string

   Description
         Character set to use when sending quick mails.

         Default: iso-8859-1


.. container:: table-row

   Property
         direct\_mail\_charset

   Data type
         string

   Description
         Default character set for direct mails built from external pages.

         Note: This is the character set used in direct mails when they are
         built from external pages and character set cannot be auto-detected.

         Note: Direct mails based on internal TYPO3 pages will be sent with the
         character set in which they are rendered as determined by their TS
         template.

         Default: iso-8859-1


.. ###### END~OF~TABLE ######

The following properties specify how links in mail content are
processed.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         use\_rdct

   Data type
         boolean

   Description
         If set, links longer than 76 characters found in plain text content
         will be redirected: long URL's will be substituted with
         ?RDCT=[md5hash] parameters. This needs the extension `rdct <https://packagist.org/packages/friendsoftypo3/rdct>`_
         to be installed

         Note: This configuration determines how Quick Mails are handled and
         further sets the default value for Direct Mails.

         Default: 0


.. container:: table-row

   Property
         long\_link\_mode

   Data type
         boolean

   Description
         If set and if use\_rdct is set, all links in plain text content will
         be redirected, not only links longer than 76 characters.

         Default: 0


.. container:: table-row

   Property
         enable\_jump\_url

   Data type
         boolean

   Description
         If set, the use of jump URL's will be enabled so that click statistics
         can be produced.

         Default: 0


.. container:: table-row

   Property
         authcode\_fieldList

   Data type
         list

   Description
         Default list of fields to be used in the computation of the
         authentication code included in unsubscribe links and in jump URL's in
         direct mails.

         Default: uid

.. _pageTsconfig_jumpurl_tracking_privacy:

.. container:: table-row

   Property
         jumpurl\_tracking\_privacy

   Data type
         Boolean

   Description
         If set no “&rid” parameter will get added to jumpurls. This inhibits
         matching of clicked links to fe\_user or tt\_address records which
         increases privacy.

         Default: 0


.. ###### END~OF~TABLE ######

The following properties specify parameters for the operations of
various functions of the Direct Mail module.

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         http\_username

   Data type
         string

   Description
         The username used to fetch the mail content, if mail content is
         protected by HTTP authentication.

         Note: The username is NOT sent in the mail!

         Note: If you do not specify a username and password and a newsletter
         page happens to be protected, an error will occur and no mail content
         will be fetched.


.. container:: table-row

   Property
         http\_password

   Data type
         string

   Description
         The password used to fetch the mail content, if mail content is
         protected by a HTTP authentication.

         Note: The password is NOT sent in the mail!

         Note: If you do not specify a username and password and a newsletter
         page happens to be protected, an error will occur and no mail content
         will be fetched.


.. container:: table-row

   Property
         simulate\_usergroup

   Data type
         integer

   Description
         If mail content is protected by Frontend user authentication, enter
         a user group that has access to the page.

         Note: If you do not specify a usergroup uid and the page has frontend
         user restrictions, an error will occur and no mail content will be
         fetched.


.. container:: table-row

   Property
         userTable

   Data type
         string

   Description
         Custom-defined table that may be used to send direct mails in addition
         to fe\_users and tt\_address tables.

         Note: The following columns must be defined in the custom-defined
         table: uid, name, title, email, phone, ww, address, company, city,
         zip, country, fax, module\_sys\_dmail\_category,
         module\_sys\_dmail\_html


.. container:: table-row

   Property
         test\_tt\_address\_uids

   Data type
         list of UIDs

   Description
         List of UID numbers of test recipients.

         Before sending mails, you should test the mail content by sending test
         mails to one or more test recipients. The available recipients for
         testing are determined by this list of UID numbers. So first, find out
         the UID numbers of the recipients you wish to use for testing, then
         enter them here in a comma-separated list.


.. ### BEGIN~OF~TABLE ###

Following settings are for the statistics module

.. ### BEGIN~OF~TABLE ###

.. container:: table-row

   Property
         showContentTitle

   Data type
         bool

   Description
         if set to 1, then only content title, in which the link can be found, will be shown in the click statistics.


.. container:: table-row

   Property
         prependContentTitle

   Data type
         bool

   Description
         if set to 1, then content title and the linked words will be shown

.. container:: table-row

   Property
         maxLabelLength

   Data type
         int

   Description
         maximum length of the clicked statistics label


.. ### BEGIN~OF~TABLE ###
