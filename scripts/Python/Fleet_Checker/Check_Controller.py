import time
import schedule
import threading
from datetime import datetime, timezone
import mysql.connector as DatabaseConnector
from mysql.connector import Error as SQLError

import traceback

from ESI import AuthHandler
from Fleets import Fleet, FleetException

class Controller:

    def __init__(self, database_info, client_id, client_secret, auth_type, core_url = None, core_auth_header = None):

        self.database_info = database_info
        self.auth_type = auth_type
        self.core_url = core_url
        self.core_auth_header = core_auth_header

        self.client_id = client_id
        self.client_secret = client_secret

        self.thread = None

    def run(self, service, snapshot_period):

        if service:

            schedule.every(snapshot_period).seconds.do(self.runThread)

            while True:
                schedule.run_pending()
                time.sleep(1)

        else:

            self.conductChecks()

    def runThread(self):

        #Bad things can happen if we let checks run concurrently
        if self.thread is None or not self.thread.is_alive():
            self.thread = threading.Thread(target=self.conductChecks)
            self.thread.start()

    def getTimestamp(self):

        currentTime = datetime.now(timezone.utc)
        return currentTime.strftime("%d %B, %Y - %H:%M:%S EVE")

    def makeLogEntry(self, sqlDatabase, logType, logStatement):

        loggingCursor = sqlDatabase.cursor(buffered=True)

        logInsert = "INSERT INTO logs (timestamp, type, actor, details) VALUES (%s, %s, %s, %s)"
        loggingCursor.execute(logInsert, (int(time.time()), logType, "[Fleet Check Controller]", logStatement))
        sqlDatabase.commit()

        loggingCursor.close()
    
    def conductChecks(self):

        print("\n[{Time}] Starting Checks...".format(Time=self.getTimestamp()))

        sqlDatabase = DatabaseConnector.connect(
            user=self.database_info["DatabaseUsername"],
            password=self.database_info["DatabasePassword"],
            host=self.database_info["DatabaseServer"],
            port=int(self.database_info["DatabasePort"]),
            database=self.database_info["DatabaseName"]
        )

        try:

            esi_auth = AuthHandler(sqlDatabase, self.client_id, self.client_secret, "FC")

            fleet_query_cursor = sqlDatabase.cursor(buffered=True)

            fleet_query = "SELECT id, commanderid, status FROM fleets WHERE status IN ('Active', 'Starting', 'Closing')"
            fleet_query_cursor.execute(fleet_query)

            for (fleet_id, commander_id, status) in fleet_query_cursor:

                print("\n[{Time}] Starting check of {id}...".format(Time=self.getTimestamp(), id=fleet_id))

                Fleet(
                    sqlDatabase, 
                    fleet_id, 
                    status, 
                    commander_id,
                    esi_auth.getAccessToken(commander_id, retries=1),
                    self.auth_type, 
                    self.core_url,
                    self.core_auth_header
                )

            fleet_query_cursor.close()

            print("[{Time}] Done!\n".format(Time=self.getTimestamp()))

        except FleetException as error:

            log_string = "{message} \n\nFleet ID: {fleet} \nCommander ID: {commander} \nAttempted Action: {action} \nAdditional Context: {context}".format(
                message=error.readable_message, 
                fleet=error.fleet_id,
                commander=error.commander_id,
                action=error.attempted_action,
                context=error.additional_data
            )
            self.makeLogEntry(sqlDatabase, "Checker Fleet Error", log_string)
            print("[{time}] Fleet Error! \nMessage: {message}".format(time=self.getTimestamp(), message=error.readable_message))

        except SQLError as error:

            log_string = str(error)
            self.makeLogEntry(sqlDatabase, "Checker SQL Error", log_string)
            print("[{time}] SQL Error! \nMessage: {message}".format(time=self.getTimestamp(), message=str(error)))

        except Exception as error:

            log_string = str(traceback.format_exc())
            self.makeLogEntry(sqlDatabase, "Checker Unknown Error", log_string)
            print("[{time}] Unknown Error! \n{message}".format(time=self.getTimestamp(), message=str(traceback.format_exc())))


        sqlDatabase.close()
