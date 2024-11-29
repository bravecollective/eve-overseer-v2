class FleetException(Exception):

    def __init__(self, readable_message, fleet_id, commander_id, attempted_action, additional_data = None):

        self.readable_message = readable_message
        self.fleet_id = fleet_id
        self.commander_id = commander_id
        self.attempted_action = attempted_action
        self.additional_data = additional_data

        super().__init__(msg=readable_message)