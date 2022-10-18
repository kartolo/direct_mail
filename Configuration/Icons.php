<?php
//https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ApiOverview/Icon/Index.html
return [
    // icon identifier
    'directmail-attachment' => [
        // icon provider class
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        // the source bitmap file
        'source' => 'EXT:direct_mail/Resources/Public/Icons/attach.png'
    ],
    'directmail-dmail' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/dmail.png'
    ],
    'directmail-dmail-list' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/dmail_list.png'
    ],
    'directmail-folder' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/ext_icon_dmail_folder.png'
    ],
    'apps-pagetree-folder-contains-dmail' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/ext_icon_dmail_folder.png'
    ],
    'directmail-category' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/icon_tx_directmail_category.png'
    ],
    'directmail-mail' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/mail.png'
    ],
    'directmail-mailgroup' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/mailgroup.png'
    ],
    'directmail-page-modules-dmail' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/modules_dmail.png'
    ],
    'directmail-page-modules-dmail-inactive' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/modules_dmail__h.png'
    ],
    'directmail-dmail-new' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/newmail.png'
    ],
    'directmail-dmail-preview-html' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/preview_html.png'
    ],
    'directmail-dmail-preview-text' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
        'source' => 'EXT:direct_mail/Resources/Public/Icons/preview_txt.png'
    ],
/**
    'mysvgicon' => [
        // icon provider class
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        // the source SVG for the SvgIconProvider
        'source' => 'EXT:my_extension/Resources/Public/Icons/mysvg.svg',
    ],
    'myfontawesomeicon' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\FontawesomeIconProvider::class,
        // the fontawesome icon name
        'name' => 'spinner',
        // all icon providers provide the possibility to register an icon that spins
        'spinning' => true,
    ],
*/
];
