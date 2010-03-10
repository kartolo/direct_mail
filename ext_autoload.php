<?php
$extensionPath = t3lib_extMgm::extPath('direct_mail');
return array(
	'dmailer' => $extensionPath . 'res/scripts/class.dmailer.php',
	'tx_directmail_scheduler' => $extensionPath . 'class.tx_directmail_scheduler.php',
);
?>