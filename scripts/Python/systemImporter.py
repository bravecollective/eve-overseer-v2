import inspect
import os
import configparser
import json
import time
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

else:

    try:

        databaseInfo = {}
        databaseInfo["DatabaseServer"] = os.environ["ENV_OVERSEER_DATABASE_SERVER"]
        databaseInfo["DatabasePort"] = os.environ["ENV_OVERSEER_DATABASE_PORT"]
        databaseInfo["DatabaseUsername"] = os.environ["ENV_OVERSEER_DATABASE_USERNAME"]
        databaseInfo["DatabasePassword"] = os.environ["ENV_OVERSEER_DATABASE_PASSWORD"]
        databaseInfo["DatabaseName"] = os.environ["ENV_OVERSEER_DATABASE_NAME"]

    except:

        raise Warning("No Configuration File or Required Environment Variables Found!")

def getTimeMark():

        currentTime = datetime.now(timezone.utc)
        return currentTime.strftime("%d %B, %Y - %H:%M:%S EVE")

def makeLogEntry(passedDatabase, logType, logStatement):

    loggingCursor = passedDatabase.cursor(buffered=True)

    logInsert = "INSERT INTO logs (timestamp, type, actor, details) VALUES (%s, %s, %s, %s)"
    loggingCursor.execute(logInsert, (int(time.time()), logType, "[System Importer]", logStatement))
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

    with open(dataFile("/scripts/Python/Static") + "/geographicInformationV3.json") as systemsFile:
        systemsData = json.load(systemsFile)

    systemsTuple = [(id, data["region_id"]) for id, data in systemsData.items()]

    deletionCursor = sq1Database.cursor(buffered=True)
    updateCursor = sq1Database.cursor(buffered=True)

    print("[{Time}] Deleting Systems...".format(Time=getTimeMark()))
    deletionCursor.execute("DELETE FROM evesystems")

    print("[{Time}] Inserting Systems...".format(Time=getTimeMark()))
    systemsUpdate = "INSERT INTO evesystems (id, regionid) VALUES (%s, %s)"
    updateCursor.executemany(systemsUpdate, systemsTuple)

    print("[{Time}] Committing Transaction...\n".format(Time=getTimeMark()))
    sq1Database.commit()

    deletionCursor.close()
    updateCursor.close()

except:

    traceback.print_exc()

    error = traceback.format_exc()

    makeLogEntry(sq1Database, "Unknown System Importer Error", error)

sq1Database.close()

print("\n[{Time}] Concluded!".format(Time=getTimeMark()))