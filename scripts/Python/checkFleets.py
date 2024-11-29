import inspect
import os
import configparser
import argparse
import base64

from pathlib import Path

from Fleet_Checker import Controller

argument_parser = argparse.ArgumentParser()
argument_parser.add_argument(
    "-s", 
    "--service", 
    help="run the script as a service", 
    action="store_true"
)
argument_parser.add_argument(
    "-t", 
    "--time", 
    help="time in seconds between service runs", 
    type=int,
    default=15
)
arguments = argument_parser.parse_args()

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
    authType = config["Eve Authentication"]["AuthType"]
    clientID = config["Eve Authentication"]["ClientID"]
    clientSecret = config["Eve Authentication"]["ClientSecret"]
    coreInfo = config["NeuCore Authentication"]

else:

    try:

        databaseInfo = {}
        databaseInfo["DatabaseServer"] = os.environ["ENV_OVERSEER_DATABASE_SERVER"]
        databaseInfo["DatabasePort"] = os.environ["ENV_OVERSEER_DATABASE_PORT"]
        databaseInfo["DatabaseUsername"] = os.environ["ENV_OVERSEER_DATABASE_USERNAME"]
        databaseInfo["DatabasePassword"] = os.environ["ENV_OVERSEER_DATABASE_PASSWORD"]
        databaseInfo["DatabaseName"] = os.environ["ENV_OVERSEER_DATABASE_NAME"]

        authType = os.environ["ENV_OVERSEER_EVE_AUTH_TYPE"]
        clientID = os.environ["ENV_OVERSEER_EVE_CLIENT_ID"]
        clientSecret = os.environ["ENV_OVERSEER_EVE_CLIENT_SECRET"]
        coreInfo = {"AppID": None, "AppSecret": None, "AppURL": None}

        if authType == "Neucore":

            coreInfo["AppID"] = os.environ["ENV_OVERSEER_NEUCORE_APP_ID"]
            coreInfo["AppSecret"] = os.environ["ENV_OVERSEER_NEUCORE_APP_SECRET"]
            coreInfo["AppURL"] = os.environ["ENV_OVERSEER_NEUCORE_APP_URL"]

    except:

        raise Warning("No Configuration File or Required Environment Variables Found!")

neucoreAuthHeader = None

if authType == "Neucore":

    neucoreRawHeader = str(coreInfo["AppID"]) + ":" + coreInfo["AppSecret"]
    neucoreAuthHeader = "Bearer " + base64.urlsafe_b64encode(neucoreRawHeader.encode("utf-8")).decode()

fleet_checker = Controller(
    databaseInfo,
    clientID,
    clientSecret,
    authType,
    coreInfo["AppURL"],
    neucoreAuthHeader
)
fleet_checker.run(
    arguments.service,
    arguments.time
)
