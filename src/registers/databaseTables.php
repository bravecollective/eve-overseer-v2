<?php

    declare(strict_types = 1);
    
    /*
        Define tables to add to the database here.
        
        The $siteDatabase->register method accepts the following arguments:
        
            A single $tableName string. 
            A variable amount of $tableColumns arrays.
            
        Each $tableColumns array can have the following keys:
        
            [REQUIRED] "Name" - The name of the column.
            [REQUIRED] "Type" - The SQL type of the column. 
            [OPTIONAL] "Special" - Any special modifiers for the column. 
            
        EXAMPLE:
        
            $siteDatabase->register(
                "table_name",
                ["Name" => "special_column", "Type" => "BIGINT", "Special" => "primary key AUTO_INCREMENT"],
                ["Name" => "column_two", "Type" => "TEXT"]
            );
            
    */

    $siteDatabase->register(
        "entitytypes",
        ["Name" => "type", "Type" => "VARCHAR(32)"],
        ["Name" => "id", "Type" => "BIGINT"],
        ["Name" => "name", "Type" => "TEXT"],
        ["Name" => "", "Type" => "", "Special" => "CONSTRAINT entity_type_pk PRIMARY KEY (type, id)"]
    );

    $siteDatabase->register(
        "entitytypeaccess",
        ["Name" => "entitytype", "Type" => "VARCHAR(32)"],
        ["Name" => "entityid", "Type" => "BIGINT"],
        ["Name" => "roletype", "Type" => "VARCHAR(32)"],
        ["Name" => "roleid", "Type" => "BIGINT"],
        ["Name" => "", "Type" => "", "Special" => "CONSTRAINT entity_type_access_pk PRIMARY KEY (entitytype, entityid, roletype, roleid)"],
        ["Name" => "", "Type" => "", "Special" => "FOREIGN KEY (roletype, roleid) REFERENCES access(type, id) ON DELETE CASCADE"]
    );

    $siteDatabase->register(
        "fleettypes",
        ["Name" => "id", "Type" => "BIGINT", "Special" => "primary key AUTO_INCREMENT"],
        ["Name" => "name", "Type" => "TEXT"]
    );

    $siteDatabase->register(
        "fleettypeaccess",
        ["Name" => "typeid", "Type" => "BIGINT"],
        ["Name" => "roletype", "Type" => "VARCHAR(32)"],
        ["Name" => "roleid", "Type" => "BIGINT"],
        ["Name" => "accesstype", "Type" => "VARCHAR(32)"],
        ["Name" => "", "Type" => "", "Special" => "CONSTRAINT fleet_type_pk PRIMARY KEY (typeid, roletype, roleid, accesstype)"]
    );

    $siteDatabase->register(
        "useraccounts",
        ["Name" => "accountid", "Type" => "BIGINT"],
        ["Name" => "accounttype", "Type" => "ENUM('Neucore', 'Character')"],
        ["Name" => "accountname", "Type" => "TEXT"],
        ["Name" => "", "Type" => "", "Special" => "CONSTRAINT user_account_pk PRIMARY KEY (accounttype, accountid)"]
    );

    $siteDatabase->register(
        "userlinks",
        ["Name" => "characterid", "Type" => "BIGINT", "Special" => "primary key"],
        ["Name" => "accounttype", "Type" => "ENUM('Neucore', 'Character')"],
        ["Name" => "accountid", "Type" => "BIGINT"]
    );

    $siteDatabase->register(
        "corptrackers",
        ["Name" => "corporationid", "Type" => "BIGINT", "Special" => "primary key"],
        ["Name" => "corporationname", "Type" => "TEXT"],
        ["Name" => "allianceid", "Type" => "BIGINT"],
        ["Name" => "alliancename", "Type" => "TEXT"],
        ["Name" => "characterid", "Type" => "BIGINT"],
        ["Name" => "recheck", "Type" => "BIGINT"]
    );

    $siteDatabase->register(
        "corpmembers",
        ["Name" => "characterid", "Type" => "BIGINT", "Special" => "primary key"],
        ["Name" => "charactername", "Type" => "TEXT"],
        ["Name" => "corporationid", "Type" => "BIGINT"]
    );

    $siteDatabase->register(
        "fleets",
        ["Name" => "id", "Type" => "BIGINT", "Special" => "primary key"],
        ["Name" => "name", "Type" => "TEXT"],
        ["Name" => "type", "Type" => "BIGINT"],
        ["Name" => "commanderid", "Type" => "BIGINT"],
        ["Name" => "starttime", "Type" => "BIGINT"],
        ["Name" => "endtime", "Type" => "BIGINT", "Special" => "DEFAULT NULL"],
        ["Name" => "status", "Type" => "TEXT"],
        ["Name" => "sharekey", "Type" => "VARCHAR(32)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (type)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (commanderid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (sharekey)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (starttime)"]
    );

    $siteDatabase->register(
        "sharesubscriptions",
        ["Name" => "sharekey", "Type" => "VARCHAR(32)"],
        ["Name" => "characterid", "Type" => "BIGINT"],
        ["Name" => "", "Type" => "", "Special" => "CONSTRAINT subscription_pk PRIMARY KEY (sharekey, characterid)"]
    );

    $siteDatabase->register(
        "fleetsnapshots",
        ["Name" => "fleetid", "Type" => "BIGINT"],
        ["Name" => "timestamp", "Type" => "BIGINT"],
        ["Name" => "", "Type" => "", "Special" => "CONSTRAINT snapshot_pk PRIMARY KEY (fleetid, timestamp)"]
    );

    $siteDatabase->register(
        "fleetmembers",
        ["Name" => "fleetid", "Type" => "BIGINT"],
        ["Name" => "characterid", "Type" => "BIGINT"],
        ["Name" => "corporationid", "Type" => "BIGINT"],
        ["Name" => "allianceid", "Type" => "BIGINT"],
        ["Name" => "role", "Type" => "TEXT"],
        ["Name" => "wingid", "Type" => "BIGINT"],
        ["Name" => "squadid", "Type" => "BIGINT"],
        ["Name" => "starttime", "Type" => "BIGINT"],
        ["Name" => "endtime", "Type" => "BIGINT", "Special" => "DEFAULT NULL"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (fleetid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (characterid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (corporationid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (allianceid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (starttime)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (endtime)"]
    );

    $siteDatabase->register(
        "fleetlocations",
        ["Name" => "fleetid", "Type" => "BIGINT"],
        ["Name" => "characterid", "Type" => "BIGINT"],
        ["Name" => "systemid", "Type" => "BIGINT"],
        ["Name" => "starttime", "Type" => "BIGINT"],
        ["Name" => "endtime", "Type" => "BIGINT", "Special" => "DEFAULT NULL"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (fleetid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (characterid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (starttime)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (endtime)"]
    );

    $siteDatabase->register(
        "fleetships",
        ["Name" => "fleetid", "Type" => "BIGINT"],
        ["Name" => "characterid", "Type" => "BIGINT"],
        ["Name" => "shipid", "Type" => "BIGINT"],
        ["Name" => "starttime", "Type" => "BIGINT"],
        ["Name" => "endtime", "Type" => "BIGINT", "Special" => "DEFAULT NULL"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (fleetid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (characterid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (shipid)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (starttime)"],
        ["Name" => "", "Type" => "", "Special" => "INDEX (endtime)"]
    );

    $siteDatabase->register(
        "evetypes",
        ["Name" => "id", "Type" => "BIGINT"],
        ["Name" => "groupid", "Type" => "BIGINT"],
        ["Name" => "", "Type" => "", "Special" => "PRIMARY KEY (id)"]
    );

    $siteDatabase->register(
        "evegroups",
        ["Name" => "id", "Type" => "BIGINT"],
        ["Name" => "name", "Type" => "TEXT"],
        ["Name" => "categoryid", "Type" => "BIGINT"],
        ["Name" => "", "Type" => "", "Special" => "PRIMARY KEY (id)"]
    );

    $siteDatabase->register(
        "evecategories",
        ["Name" => "id", "Type" => "BIGINT"],
        ["Name" => "name", "Type" => "TEXT"],
        ["Name" => "", "Type" => "", "Special" => "PRIMARY KEY (id)"]
    );


    $siteDatabase->register(
        "evesystems",
        ["Name" => "id", "Type" => "BIGINT"],
        ["Name" => "regionid", "Type" => "BIGINT"],
        ["Name" => "", "Type" => "", "Special" => "PRIMARY KEY (id)"]
    );

?>