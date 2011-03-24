<?php
$extensionPath = t3lib_extMgm::extPath('direct_mail');
return array(
	'dmailer' => $extensionPath . 'res/scripts/class.dmailer.php',
	'tx_directmail_dmail' => $extensionPath . 'mod2/class.tx_directmail_dmail.php',
	'tx_directmail_static' => $extensionPath . 'res/scripts/class.tx_directmail_static.php',
	'tx_directmail_scheduler' => $extensionPath . 'class.tx_directmail_scheduler.php',
	'tx_directmail_scheduler_mailfromdraft' => $extensionPath . 'Classes/Scheduler/MailFromDraft.php',
	'tx_directmail_scheduler_mailfromdraft_additionalfields' => $extensionPath . 'Classes/Scheduler/MailFromDraft_AdditionalFields.php',
);
?>