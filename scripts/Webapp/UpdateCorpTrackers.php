<?php

    require __DIR__ . "/../../config/config.php";
    require __DIR__ . "/../../src/core/Autoloader/Autoloader.php";
    
    
    $siteDatabase = new \Ridley\Core\Database\DatabaseHandler($configVariables["Database Server"], $configVariables["Database Username"], $configVariables["Database Password"], $configVariables["Database Name"]);
    require __DIR__ . "/../../src/registers/databaseTables.php";
    $masterDatabaseConnection = $siteDatabase->databaseConnenction;
    
    
    $siteLogger = new \Ridley\Core\Logging\LogHandler($masterDatabaseConnection, $configVariables);
    require __DIR__ . "/../../src/registers/logTypes.php";
    
    
    $errorHandler = new \Ridley\Core\Errors\ErrorHandler($siteLogger);
    require __DIR__ . "/../../src/registers/errorHandlingMethods.php";
    
    $userAuthorization = new \Ridley\Core\Authorization\Base\AuthBase($siteLogger, $masterDatabaseConnection, $configVariables);

    $dependencyManager = new \Ridley\Core\Dependencies\DependencyManager();
    $dependencyManager->register("Database", $masterDatabaseConnection);
    $dependencyManager->register("Logging", $siteLogger);
    $dependencyManager->register("Authorization Control", $userAuthorization);

    $corpTrackingHandler = new \Ridley\Objects\CorporationTracking\Handler($dependencyManager);
    $corpTrackingHandler->updateAllCorps(true);

?>