Usage/configuring multi language
--------------------------------

When the page has a translation you have the possibility to select a specific language/translation of the page when creating a new Direct Mail. It is also possible to target receiver groups based on a language restriction.
Creating a Direct Mail will provide the option in the BE module to use a translation of the page instead of the original only if a page is translated.

In the last step of creating a Direct Mail, receiver groups are shown that match the language selection. When only 1 group is present, the select is not shown but this group is automatically selected.

Configuring a language for recipients
"""""""""""""""""""""""""""""""""""""

In a recipient list an option is available to select a language for that list with as default <All languages>. This option is used at the last step of creating a Direct Mail to determine the receivers.

Language mapping
""""""""""""""""

Mapping language params to the sys_language_uid can be done by tsConfig and with an automatic fallback to `&L=sys_language_uid`.

Example tsConfig::

mod.web_modules.dmail {
    langParams.0 =
    langParams.1 = &L=1
}





