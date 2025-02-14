import time
import requests
import json

import ESI
from Fleets import FleetException

class Fleet:

    def __init__(self, database_connection, id, status, commander_id, access_token, auth_type, core_url = None, core_auth_header = None):
        
        self.database_connection = database_connection
        self.esi_handler = ESI.Handler(database_connection, access_token)

        #We're using milliseconds to avoid collisions
        self.current_timestamp = int(time.time() * 1000)

        self.id = id
        self.status = status
        self.commander_id = commander_id

        self.auth_type = auth_type
        self.core_url = core_url
        self.core_auth_header = core_auth_header

        self.existing_members = {}
        self.existing_locations = {}
        self.existing_ships = {}

        self.new_members = {}
        self.new_locations = {}
        self.new_ships = {}

        self.stop_processing = False

        self.register_snapshot()
        self.check_for_status()
        self.pull_new()
        self.pull_existing()
        self.queue_updates()

    def makeLogEntry(self, logType, logStatement):

        loggingCursor = self.database_connection.cursor(buffered=True)

        logInsert = "INSERT INTO logs (timestamp, type, actor, details) VALUES (%s, %s, %s, %s)"
        loggingCursor.execute(logInsert, (int(time.time()), logType, "[Fleet Check Controller]", logStatement))
        self.database_connection.commit()

        loggingCursor.close()

    def register_snapshot(self):

        insert_cursor = self.database_connection.cursor(buffered=True)

        register_statement = "REPLACE INTO fleetsnapshots (fleetid, timestamp) VALUES (%s, %s)"
        insert_cursor.execute(register_statement, (self.id, self.current_timestamp))

        self.database_connection.commit()
        insert_cursor.close()


    def check_for_status(self):

        if self.status == "Closing":

            self.close_fleet()

        elif self.status == "Starting":

            start_cursor = self.database_connection.cursor(buffered=True)

            start_statement = "UPDATE fleets SET status=%s, starttime=%s WHERE id=%s"
            start_cursor.execute(start_statement, ("Active", self.current_timestamp, self.id))

            self.database_connection.commit()
            start_cursor.close()


    def pull_new(self):

        if not self.stop_processing:

            fleet_call = self.esi_handler.call("/characters/{character_id}/fleet/", character_id=self.commander_id, retries=1)
            
            if fleet_call["Success"]:

                if fleet_call["Data"]["fleet_id"] != self.id or fleet_call["Data"]["fleet_boss_id"] != self.commander_id:

                    self.close_fleet()
                    return

            elif fleet_call["Status Code"] in [401, 403, 404]:

                self.close_fleet()
                return
            
            members_call = self.esi_handler.call("/fleets/{fleet_id}/members/", fleet_id=self.id, retries=1)

            if members_call["Success"]:

                for each_member in members_call["Data"]:

                    self.new_members[each_member["character_id"]] = {
                        "Role": each_member["role"],
                        "Wing ID": each_member["wing_id"],
                        "Squad ID": each_member["squad_id"]
                    }

                    self.new_locations[each_member["character_id"]] = {
                        "System ID": each_member["solar_system_id"]
                    }

                    self.new_ships[each_member["character_id"]] = {
                        "Ship ID": each_member["ship_type_id"]
                    }

            elif members_call["Status Code"] in [401, 403, 404]:

                self.close_fleet()
                return
            
            else:

                raise FleetException(
                    "Failed to pull new fleet members!", 
                    self.id, 
                    self.commander_id, 
                    "/fleets/{fleet_id}/members/", 
                    str(members_call["Data"])
                )
        

    def pull_existing(self):

        if not self.stop_processing:

            query_cursor = self.database_connection.cursor(buffered=True)

            members_statement = "SELECT characterid, role, wingid, squadid FROM fleetmembers WHERE fleetid = %s AND endtime IS NULL"
            query_cursor.execute(members_statement, (self.id,))

            for (character_id, role, wing_id, squad_id) in query_cursor:

                self.existing_members[character_id] = {
                    "Role": role,
                    "Wing ID": wing_id,
                    "Squad ID": squad_id
                }

            locations_statement = "SELECT characterid, systemid FROM fleetlocations WHERE fleetid = %s AND endtime IS NULL"
            query_cursor.execute(locations_statement, (self.id,))

            for (character_id, system_id) in query_cursor:

                self.existing_locations[character_id] = {
                    "System ID": system_id
                }

            ships_statement = "SELECT characterid, shipid FROM fleetships WHERE fleetid = %s AND endtime IS NULL"
            query_cursor.execute(ships_statement, (self.id,))

            for (character_id, ship_id) in query_cursor:

                self.existing_ships[character_id] = {
                    "Ship ID": ship_id
                }

            query_cursor.close()


    def queue_updates(self):

        if not self.stop_processing:

            #Check for Member Differences
            if self.new_members != self.existing_members:

                member_entries_to_close = []
                new_member_entries = {}
                core_entries = []

                for each_member, each_member_data in self.new_members.items():

                    if each_member not in self.existing_members:

                        core_entries.append(each_member)
                        new_member_entries[each_member] = each_member_data

                    elif each_member_data != self.existing_members[each_member]:

                        member_entries_to_close.append(each_member)
                        new_member_entries[each_member] = each_member_data

                for each_member in self.existing_members:

                    if each_member not in self.new_members:

                        member_entries_to_close.append(each_member)

                self.update_core_links(core_entries)
                self.update_members(new_member_entries, member_entries_to_close)

            #Check for Location Differences
            if self.new_locations != self.existing_locations:

                location_entries_to_close = []
                new_location_entries = []

                for each_member, each_location_data in self.new_locations.items():

                    if each_member not in self.existing_locations:

                        new_location_entries.append(
                            (
                                self.id,                          #fleetid
                                each_member,                      #characterid
                                each_location_data["System ID"],  #systemid
                                self.current_timestamp            #starttime
                            )
                        )

                    elif each_location_data != self.existing_locations[each_member]:

                        location_entries_to_close.append(each_member)
                        new_location_entries.append(
                            (
                                self.id,                          #fleetid
                                each_member,                      #characterid
                                each_location_data["System ID"],  #systemid
                                self.current_timestamp            #starttime
                            )
                        )

                for each_member in self.existing_locations:

                    if each_member not in self.new_locations:

                        location_entries_to_close.append(each_member)

                self.update_locations(new_location_entries, location_entries_to_close)

            #Check for Ship Differences
            if self.new_ships != self.existing_ships:

                ship_entries_to_close = []
                new_ship_entries = []

                for each_member, each_ship_data in self.new_ships.items():

                    if each_member not in self.existing_ships:

                        new_ship_entries.append(
                            (
                                self.id,                    #fleetid
                                each_member,                #characterid
                                each_ship_data["Ship ID"],  #shipid
                                self.current_timestamp      #starttime
                            )
                        )

                    elif each_ship_data != self.existing_ships[each_member]:

                        ship_entries_to_close.append(each_member)
                        new_ship_entries.append(
                            (
                                self.id,                    #fleetid
                                each_member,                #characterid
                                each_ship_data["Ship ID"],  #shipid
                                self.current_timestamp      #starttime
                            )
                        )

                for each_member in self.existing_ships:

                    if each_member not in self.new_ships:

                        ship_entries_to_close.append(each_member)

                self.update_ships(new_ship_entries, ship_entries_to_close)


    def update_members(self, new_preformatted_entries, closing_entries):

        update_cursor = self.database_connection.cursor(buffered=True)

        #Members have left fleet, or moved fleet positions.
        if closing_entries:

            entries_to_close = (self.current_timestamp, self.id) + tuple(closing_entries)

            placeholders = ", ".join(["%s" for x in range(len(closing_entries))])
            close_statement = """
                UPDATE fleetmembers 
                    SET endtime = %s 
                WHERE 
                    fleetid = %s
                    AND characterid IN ({placeholders})
                    AND endtime IS NULL
            """.format(placeholders = placeholders)
            update_cursor.execute(close_statement, entries_to_close)

        #New members have joined fleet, or moved fleet positions.
        if new_preformatted_entries:

            affiliations_call = self.esi_handler.call(
                "/characters/affiliation/", 
                characters=[x for x in new_preformatted_entries], 
                retries=1
            )

            if affiliations_call["Success"]:

                linked_affiliations = {}

                for characters in affiliations_call["Data"]:

                    linked_affiliations[characters["character_id"]] = {
                        "Alliance ID": None, 
                        "Corporation ID": characters["corporation_id"]
                    }

                    if "alliance_id" in characters:
                        linked_affiliations[characters["character_id"]]["Alliance ID"] = characters["alliance_id"]

                #Building insert data
                new_entries = []

                for each_member, each_member_data in new_preformatted_entries.items():

                    new_entries.append(
                        (
                            self.id,                                             #fleetid
                            each_member,                                         #characterid
                            linked_affiliations[each_member]["Corporation ID"],  #corporationid
                            linked_affiliations[each_member]["Alliance ID"],     #allianceid
                            each_member_data["Role"],                            #role
                            each_member_data["Wing ID"],                         #wingid
                            each_member_data["Squad ID"],                        #squadid
                            self.current_timestamp                               #starttime
                        )
                    )

                insert_statement = """
                    INSERT INTO fleetmembers 
                        (fleetid, characterid, corporationid, allianceid, role, wingid, squadid, starttime)
                    VALUES
                        (%s, %s, %s, %s, %s, %s, %s, %s)
                """
                update_cursor.executemany(insert_statement, new_entries)

            else:

                update_cursor.close()

                raise FleetException(
                    "Failed to get affiliations of new fleet members!", 
                    self.id, 
                    self.commander_id, 
                    "/characters/affiliation/", 
                    str(affiliations_call["Data"])
                )
            
        self.database_connection.commit()
        update_cursor.close()


    def update_core_links(self, entries):

        known_accounts = []
        linked_characters = []
        core_accounts = []
        core_links = []
        
        if self.core_auth_header is not None and self.core_url is not None and entries:

            core_headers = {"Authorization" : self.core_auth_header, "accept": "application/json", "Content-Type": "application/json"}

            core_request = requests.post(
                self.core_url + "api/app/v1/players", 
                data=json.dumps(entries), 
                headers=core_headers
            )

            if core_request.status_code == requests.codes.ok:

                request_data = json.loads(core_request.text)

                for each_character in request_data:

                    if each_character["id"] not in known_accounts:

                        core_accounts.append((each_character["id"], "Neucore", each_character["name"]))
                        known_accounts.append(each_character["id"])

                    core_links.append((each_character["characterId"], "Neucore", each_character["id"]))
                    linked_characters.append(each_character["characterId"])

                replace_cursor = self.database_connection.cursor(buffered=True)

                accounts_replace_statement = "REPLACE INTO useraccounts (accountid, accounttype, accountname) VALUES (%s, %s, %s)"
                replace_cursor.executemany(accounts_replace_statement, core_accounts)

                links_replace_statement = "REPLACE INTO userlinks (characterid, accounttype, accountid) VALUES (%s, %s, %s)"
                replace_cursor.executemany(links_replace_statement, core_links)

                self.database_connection.commit()
                replace_cursor.close()

            else:

                raise FleetException(
                    "Failed to get core accounts of new fleet members!", 
                    self.id, 
                    self.commander_id, 
                    "/app/v1/players", 
                    core_request.text
                )
            
        non_core_characters = list(set(entries) - set(linked_characters))
        non_core_accounts = []
        non_core_links = []

        if non_core_characters:
            
            names_call = self.esi_handler.call(
                "/universe/names/", 
                ids=non_core_characters, 
                retries=1
            )

            if names_call["Success"]:

                non_core_names = {x["id"]: x["name"] for x in names_call["Data"] if x["category"] == "character"}

                for each_character, each_name in non_core_names.items():

                    non_core_accounts.append((each_character, "Character", each_name))
                    non_core_links.append((each_character, "Character", each_character))
                    linked_characters.append(each_character)

                replace_cursor = self.database_connection.cursor(buffered=True)

                accounts_replace_statement = "REPLACE INTO useraccounts (accountid, accounttype, accountname) VALUES (%s, %s, %s)"
                replace_cursor.executemany(accounts_replace_statement, non_core_accounts)

                links_replace_statement = "REPLACE INTO userlinks (characterid, accounttype, accountid) VALUES (%s, %s, %s)"
                replace_cursor.executemany(links_replace_statement, non_core_links)

                self.database_connection.commit()
                replace_cursor.close()

            else:

                raise FleetException(
                    "Failed to get names of new non-core fleet members!", 
                    self.id, 
                    self.commander_id, 
                    "/universe/names/", 
                    str(names_call["Data"])
                )
            
        if entries:

            cleanup_cursor = self.database_connection.cursor(buffered=True)

            cleanup_statement = """
                DELETE FROM useraccounts
                WHERE (useraccounts.accountid, useraccounts.accounttype) NOT IN
                    (SELECT DISTINCT userlinks.accountid, userlinks.accounttype FROM userlinks)
            """
            cleanup_cursor.execute(cleanup_statement)

            cleanup_cursor.close()
            
        non_tracked_characters = list(set(entries) - set(linked_characters))
        if non_tracked_characters:

            raise FleetException(
                "Failed to get core account or name for some fleet members!", 
                self.id, 
                self.commander_id, 
                "/app/v1/players AND /universe/names/", 
                str(non_tracked_characters)
            )


    def update_locations(self, new_entries, closing_entries):

        update_cursor = self.database_connection.cursor(buffered=True)

        #Members have left fleet, or moved locations.
        if closing_entries:

            entries_to_close = (self.current_timestamp, self.id) + tuple(closing_entries)

            placeholders = ", ".join(["%s" for x in range(len(closing_entries))])
            close_statement = """
                UPDATE fleetlocations 
                    SET endtime = %s 
                WHERE 
                    fleetid = %s
                    AND characterid IN ({placeholders})
                    AND endtime IS NULL
            """.format(placeholders = placeholders)
            update_cursor.execute(close_statement, entries_to_close)

        #New members have joined fleet, or moved locations.
        if new_entries:

            insert_statement = """
                INSERT INTO fleetlocations 
                    (fleetid, characterid, systemid, starttime)
                VALUES
                    (%s, %s, %s, %s)
            """
            update_cursor.executemany(insert_statement, new_entries)

        self.database_connection.commit()
        update_cursor.close()


    def update_ships(self, new_entries, closing_entries):

        update_cursor = self.database_connection.cursor(buffered=True)

        #Members have left fleet, or changed ships.
        if closing_entries:

            entries_to_close = (self.current_timestamp, self.id) + tuple(closing_entries)

            placeholders = ", ".join(["%s" for x in range(len(closing_entries))])
            close_statement = """
                UPDATE fleetships 
                    SET endtime = %s 
                WHERE 
                    fleetid = %s
                    AND characterid IN ({placeholders})
                    AND endtime IS NULL
            """.format(placeholders = placeholders)
            update_cursor.execute(close_statement, entries_to_close)

        #New members have joined fleet, or changed ships.
        if new_entries:

            insert_statement = """
                INSERT INTO fleetships 
                    (fleetid, characterid, shipid, starttime)
                VALUES
                    (%s, %s, %s, %s)
            """
            update_cursor.executemany(insert_statement, new_entries)
        
        self.database_connection.commit()
        update_cursor.close()
        

    def close_fleet(self):

        closing_cursor = self.database_connection.cursor(buffered=True)

        close_members_statement = """
            UPDATE fleetmembers 
                SET endtime = %s 
            WHERE 
                fleetid = %s
                AND endtime IS NULL
        """
        closing_cursor.execute(close_members_statement, (self.current_timestamp, self.id))

        close_locations_statement = """
            UPDATE fleetlocations 
                SET endtime = %s 
            WHERE 
                fleetid = %s
                AND endtime IS NULL
        """
        closing_cursor.execute(close_locations_statement, (self.current_timestamp, self.id))

        close_ships_statement = """
            UPDATE fleetships 
                SET endtime = %s 
            WHERE 
                fleetid = %s
                AND endtime IS NULL
        """
        closing_cursor.execute(close_ships_statement, (self.current_timestamp, self.id))

        close_fleet_statement = """
            UPDATE fleets 
                SET endtime = %s, status = %s 
            WHERE 
                id = %s
        """
        closing_cursor.execute(close_fleet_statement, (self.current_timestamp, "Closed", self.id))

        self.database_connection.commit()
        closing_cursor.close()

        self.makeLogEntry(
            "Stopped Tracking", 
            "Fleet with ID {id} has automatically stopped tracking.".format(id=self.id)
        )

        self.stop_processing = True
