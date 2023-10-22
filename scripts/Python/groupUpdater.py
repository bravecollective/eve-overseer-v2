import ESI

import inspect
import os
import configparser
import time
import json
import traceback

from datetime import datetime, timezone
from pathlib import Path

import mysql.connector as DatabaseConnector

#If you've moved your config.ini file, set this variable to the path of the folder containing it (no trailing slash).
CONFIG_PATH_OVERRIDE = None

def dataFile(extraFolder):

    filename = inspect.getframeinfo(inspect.currentframe()).filename
    path = os.path.join(os.path.dirname(os.path.abspath(filename)), "../..")

    dataLocation = str(path) + extraFolder

    return(dataLocation)

configPath = (CONFIG_PATH_OVERRIDE) if (CONFIG_PATH_OVERRIDE is not None) else (dataFile("/config"))

if Path(configPath + "/config.ini").is_file():

    config = configparser.ConfigParser()
    config.read(dataFile("/config") + "/config.ini")

    databaseInfo = config["Database"]
    EveAuthInfo = config["Eve Authentication"]

else:

    try:

        databaseInfo = {}
        databaseInfo["DatabaseServer"] = os.environ["ENV_OVERSEER_DATABASE_SERVER"]
        databaseInfo["DatabasePort"] = os.environ["ENV_OVERSEER_DATABASE_PORT"]
        databaseInfo["DatabaseUsername"] = os.environ["ENV_OVERSEER_DATABASE_USERNAME"]
        databaseInfo["DatabasePassword"] = os.environ["ENV_OVERSEER_DATABASE_PASSWORD"]
        databaseInfo["DatabaseName"] = os.environ["ENV_OVERSEER_DATABASE_NAME"]

        EveAuthInfo = {}
        EveAuthInfo["ClientID"] = os.environ["ENV_OVERSEER_EVE_CLIENT_ID"]
        EveAuthInfo["ClientSecret"] = os.environ["ENV_OVERSEER_EVE_CLIENT_SECRET"]
        EveAuthInfo["ClientScopes"] = os.environ["ENV_OVERSEER_EVE_CLIENT_SCOPES"] if "ENV_OVERSEER_EVE_CLIENT_SCOPES" in os.environ else "esi-search.search_structures.v1"
        EveAuthInfo["DefaultScopes"] = os.environ["ENV_OVERSEER_EVE_DEFAULT_SCOPES"] if "ENV_OVERSEER_EVE_DEFAULT_SCOPES" in os.environ else "esi-search.search_structures.v1"
        EveAuthInfo["ClientRedirect"] = os.environ["ENV_OVERSEER_EVE_CLIENT_REDIRECT"]
        EveAuthInfo["AuthType"] = os.environ["ENV_OVERSEER_EVE_AUTH_TYPE"] if "ENV_OVERSEER_EVE_AUTH_TYPE" in os.environ else "Neucore"
        EveAuthInfo["SuperAdmins"] = os.environ["ENV_OVERSEER_EVE_SUPER_ADMINS"]

    except:

        raise Warning("No Configuration File or Required Environment Variables Found!")

def getTimeMark():

        currentTime = datetime.now(timezone.utc)
        return currentTime.strftime("%d %B, %Y - %H:%M:%S EVE")

def makeLogEntry(passedDatabase, logType, logStatement):

    loggingCursor = passedDatabase.cursor(buffered=True)

    logInsert = "INSERT INTO logs (timestamp, type, actor, details) VALUES (%s, %s, %s, %s)"
    loggingCursor.execute(logInsert, (int(time.time()), logType, "[Group Updater]", logStatement))
    passedDatabase.commit()

    loggingCursor.close()


print("[{Time}] Starting Update...\n".format(Time=getTimeMark()))

sq1Database = DatabaseConnector.connect(
    user=databaseInfo["DatabaseUsername"],
    password=databaseInfo["DatabasePassword"],
    host=databaseInfo["DatabaseServer"],
    port=int(databaseInfo["DatabasePort"]),
    database=databaseInfo["DatabaseName"]
)

try:

    ESIHandler = ESI.Handler(
        sq1Database
    )

    types = []
    groups = []
    categories = []
    knownGroups = []

    print("[{Time}] Getting Categories...\n".format(Time=getTimeMark()))

    categoriesCall = ESIHandler.call("/universe/categories/", retries=1)

    if categoriesCall["Success"]:

        knownCategories = categoriesCall["Data"]

    else:

        raise Exception("Failed to get list of categories.")

    for eachCategory in knownCategories:
        
        categoryCall = ESIHandler.call("/universe/categories/{category_id}/", category_id=eachCategory, retries=1)

        if categoryCall["Success"]:

            categories.append({
                "ID": categoryCall["Data"]["category_id"], 
                "Name": categoryCall["Data"]["name"]
            })

        else:

            raise Exception("Failed to get data on category {}.".format(eachCategory))



    print("[{Time}] Getting Groups...\n".format(Time=getTimeMark()))

    for eachPage in range(1,3):

        groupsCall = ESIHandler.call("/universe/groups/", page=eachPage, retries=1)

        if groupsCall["Success"]:

            knownGroups = knownGroups + groupsCall["Data"]

        else:

            raise Exception("Failed to get list of groups.")

    for eachGroup in knownGroups:
        
        groupCall = ESIHandler.call("/universe/groups/{group_id}/", group_id=eachGroup, retries=1)

        if groupCall["Success"]:

            groups.append({
                "ID": groupCall["Data"]["group_id"], 
                "Name": groupCall["Data"]["name"],
                "Category ID": groupCall["Data"]["category_id"]
            })

            for eachType in groupCall["Data"]["types"]:
                types.append({
                    "ID": eachType,
                    "Group ID": groupCall["Data"]["group_id"]
                })

        else:

            raise Exception("Failed to get data on group {}.".format(eachGroup))

    print("[{Time}] Updating Database...".format(Time=getTimeMark()))

    categoryTuples = [(x["ID"], x["Name"]) for x in categories]
    groupsTuple = [(x["ID"], x["Name"], x["Category ID"]) for x in groups]
    typesTuple = [(x["ID"], x["Group ID"]) for x in types]

    deletionCursor = sq1Database.cursor(buffered=True)
    updateCursor = sq1Database.cursor(buffered=True)

    print("[{Time}] Deleting Categories...".format(Time=getTimeMark()))
    deletionCursor.execute("DELETE FROM evecategories")

    print("[{Time}] Inserting Categories...".format(Time=getTimeMark()))
    categoryUpdate = "INSERT INTO evecategories (id, name) VALUES (%s, %s)"
    updateCursor.executemany(categoryUpdate, categoryTuples)

    print("[{Time}] Deleting Groups...".format(Time=getTimeMark()))
    deletionCursor.execute("DELETE FROM evegroups")

    print("[{Time}] Inserting Groups...".format(Time=getTimeMark()))
    groupUpdate = "INSERT INTO evegroups (id, name, categoryid) VALUES (%s, %s, %s)"
    updateCursor.executemany(groupUpdate, groupsTuple)

    print("[{Time}] Deleting Types...".format(Time=getTimeMark()))
    deletionCursor.execute("DELETE FROM evetypes")

    print("[{Time}] Inserting Types...".format(Time=getTimeMark()))
    typeUpdate = "INSERT INTO evetypes (id, groupid) VALUES (%s, %s)"
    updateCursor.executemany(typeUpdate, typesTuple)

    print("[{Time}] Committing Transaction...\n".format(Time=getTimeMark()))
    sq1Database.commit()

    deletionCursor.close()
    updateCursor.close()

except:

    traceback.print_exc()

    error = traceback.format_exc()

    makeLogEntry(sq1Database, "Unknown Group Updater Error", error)

sq1Database.close()

print("\n[{Time}] Concluded!".format(Time=getTimeMark()))