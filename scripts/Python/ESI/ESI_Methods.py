from ESI import ESI_Base

class Methods(ESI_Base.Base):

    esiURL = "https://esi.evetech.net/"
    
    def characters(self, arguments):
    
        return self.makeRequest(
            endpoint = "/characters/{character_id}/", 
            url = (self.esiURL + "latest/characters/" + str(arguments["character_id"]) + "/?datasource=tranquility"), 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )
        
    def character_locations(self, arguments):
    
        return self.makeRequest(
            endpoint = "/characters/{character_id}/location/", 
            url = (self.esiURL + "latest/characters/" + str(arguments["character_id"]) + "/location/?datasource=tranquility"), 
            accessToken = self.accessToken, 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def character_affiliations(self, arguments):
    
        return self.makeRequest(
            endpoint = "/characters/affiliation/",
            url = (self.esiURL + "latest/characters/affiliation/?datasource=tranquility"), 
            method = "POST", 
            payload = arguments["characters"], 
            cacheTime = 3600, 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )
        
    def universe_names(self, arguments):
    
        return self.makeRequest(
            endpoint = "/universe/names/",
            url = (self.esiURL + "latest/universe/names/?datasource=tranquility"), 
            method = "POST", 
            payload = arguments["ids"], 
            cacheTime = 3600, 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def universe_categories_list(self, arguments):
    
        return self.makeRequest(
            endpoint = "/universe/categories/", 
            url = (self.esiURL + "latest/universe/categories/?datasource=tranquility"), 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def universe_categories(self, arguments):
    
        return self.makeRequest(
            endpoint = "/universe/categories/{category_id}/", 
            url = (self.esiURL + "latest/universe/categories/" + str(arguments["category_id"]) + "/?datasource=tranquility"), 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def universe_groups_list(self, arguments):
    
        return self.makeRequest(
            endpoint = "/universe/groups/", 
            url = (self.esiURL + "latest/universe/groups/?datasource=tranquility&page=" + str(arguments["page"])), 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def universe_groups(self, arguments):
    
        return self.makeRequest(
            endpoint = "/universe/groups/{group_id}/", 
            url = (self.esiURL + "latest/universe/groups/" + str(arguments["group_id"]) + "/?datasource=tranquility"), 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )
    
    def character_fleet(self, arguments):

        return self.makeRequest(
            endpoint = "/characters/{character_id}/fleet/", 
            #Note we need to use v2 here to get the commander ID
            url = (self.esiURL + "v2/characters/" + str(arguments["character_id"]) + "/fleet/?datasource=tranquility"), 
            accessToken = self.accessToken, 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def fleet_members(self, arguments):

        return self.makeRequest(
            endpoint = "/fleets/{fleet_id}/members/", 
            url = (self.esiURL + "latest/fleets/" + str(arguments["fleet_id"]) + "/members/?datasource=tranquility"), 
            accessToken = self.accessToken, 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )

    def fleet_wings(self, arguments):

        return self.makeRequest(
            endpoint = "/fleets/{fleet_id}/wings/", 
            url = (self.esiURL + "latest/fleets/" + str(arguments["fleet_id"]) + "/wings/?datasource=tranquility"), 
            accessToken = self.accessToken, 
            retries = (arguments["retries"] if "retries" in arguments else 0)
        )