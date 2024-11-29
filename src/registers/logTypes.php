<?php

    declare(strict_types = 1);

    /*
        Define tables to add to the database here.
        
        The $siteLogger->register method accepts the following arguments:
        
            [string] safeName: An HTML Class Safe Name for the option.
            [string] fullName: The full name of the option.
            [string ...] containedTypes: A variable number of log types that will be filtered by the option.
            
        EXAMPLE:
        
            $siteLogger->register(
                "page-control", 
                "Page Control", 
                "Access Granted", 
                "Access Denied", 
                "Page Not Found"
            );
            
    */

    $siteLogger->register(
        "user-input-exceptions", 
        "User Input Exceptions", 
        "User Input Not Found", 
        "Missing Hardcoded Input", 
        "Bad Hardcoded Input",
        "Missing User Input",
        "Bad User Input"
    );

    $siteLogger->register(
        "fleet-tracking", 
        "Fleet Tracking", 
        "Started Tracking", 
        "Restarted Tracking", 
        "Fleet Tracker Changed",
        "Stopped Tracking"
    );

    $siteLogger->register(
        "share-subscriptions", 
        "Share Subscriptions", 
        "Subscribed to Fleet"
    );

    $siteLogger->register(
        "checker_errors", 
        "Checker Errors", 
        "Checker Fleet Error", 
        "Checker SQL Error", 
        "Checker Unknown Error"
    );

?>