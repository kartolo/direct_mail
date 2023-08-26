<?php
return [
    'directmail_configuration_update' => [
        'path' => '/directmail/configuration',
        'methods' => ['POST'],
        'target' => \DirectMailTeam\DirectMail\Module\ConfigurationController::class . '::updateConfigAction',
    ],
];
