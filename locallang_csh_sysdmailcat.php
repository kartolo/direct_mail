<?php
/**
 * Default  TCA_DESCR for "sys_dmail_category"
 *
 * @package TYPO3
 * @subpackage tx_directmail
 * @version $Id$
 */

$LOCAL_LANG = Array (
	'default' => Array (
		'.description' => 'Direct mail category',
		'.details' => 'Direct mail categories are used to classify content elements by subject so that direct mails may be personalized.
Direct mail categories may be assigned to content elements in pages that are used to build direct mails.
Subscribers may also subscribe to direct mail categories of interest to them.
When sending direct mails, the Direct mail module uses this information to personalize the email message sent to each subscriber.',
		'_.seeAlso' => 'sys_dmail, sys_dmail_group',
	),
	'fr' => Array (
		'.description' => 'Rubrique de contenu',
		'.details' => 'Les rubriques de contenu sont utilisées pour classifier les éléments de contenu par sujet de manière à ce que les bulletins puissent être personnalisés.
Des rubriques de contenu peuvent être attribuées aux éléments de contenu des pages utilisées pour construire les bulletins.
Les abonnés peuvent aussi s\'abonner à des rubriques de contenu selon leurs domaines d\'intérêt.
Lors d\'un envoi, le module d\'Envoi ciblé utilise ces informations pour personnaliser le message électronique envoyé à chaque abonné.',
	),

);
?>