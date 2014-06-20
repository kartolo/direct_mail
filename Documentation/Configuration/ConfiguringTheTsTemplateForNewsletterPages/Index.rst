

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


Configuring the TS template for newsletter pages
------------------------------------------------

Newsletter pages are just normal pages. Their rendering is configured
by the TS template. However, take the following into consideration:

- The TS template should not contain frames.

- If you insert forms in the newsletter page, you should use the GET
  method. POST method may not transfer data to the page.


