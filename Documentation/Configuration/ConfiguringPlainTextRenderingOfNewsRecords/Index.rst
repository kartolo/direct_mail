.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Configuring plain text rendering of News records
------------------------------------------------

If you insert News records in your newsletter page, using the “Insert
records” content element type, you may configure the plain text
rendering of these News rendering. Simply add static template “Direct
Mail News Plain Text” in your TS template. Note that order is
important: “Direct Mail News Plain Text” should come after or below
“Direct Mail Plain Text”. You may then tailor the template to your
specific needs.

The “Direct Mail Plain Text” static template is as follows:

Setup
"""""

.. code-block:: typoscript

   plugin.tx_directmail_pi1 {
     shortcut.0.conf.tt_news =< plugin.tt_news
     shortcut.0.conf.tt_news.code = PLAINTEXT
     shortcut.0.conf.tt_news.defaultCode = PLAINTEXT
     shortcut.0.conf.tt_news.displayCurrentRecord = 1
     shortcut.0.conf.tt_news.plainTextConf < plugin.tx_directmail_pi1
     shortcut.0.tables = tt_content,tt_news

     tt_news_author.defaultType = 3
     tt_news_author.date = D-m-Y
     tt_news_author.prefix = |###TT_NEWS_AUTHOR_PREFIX### |
     tt_news_author.datePrefix = |###TT_NEWS_AUTHOR_DATE_PREFIX### |
     tt_news_author.emailPrefix = | ###TT_NEWS_AUTHOR_EMAIL_PREFIX### |
     tt_news_author.1.preLineLen = 76
     tt_news_author.1.postLineLen = 76
     tt_news_author.1.preBlanks=1
     tt_news_author.1.stdWrap.case = upper

     tt_news_author.2 < .tt_news_author.1
     tt_news_author.2.preLineChar=*
     tt_news_author.2.postLineChar=*

     tt_news_author.3.preBlanks=1
     tt_news_author.3.stdWrap.case = upper

     tt_news_author.4 < .tt_news_author.1
     tt_news_author.4.preLineChar = =
     tt_news_author.4.postLineChar = =
     tt_news_author.4.preLineBlanks= 1
     tt_news_author.4.postLineBlanks= 1

     tt_news_short < .bodytext
     tt_news_short.header = |###TT_NEWS_SHORT_HEADER### |

     tt_news_bodytext < .bodytext
     tt_news_bodytext.header = |###TT_NEWS_BODYTEXT_HEADER### |
   }
