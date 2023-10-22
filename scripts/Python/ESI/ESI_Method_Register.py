from ESI import ESI_Methods

class MethodRegister(ESI_Methods.Methods):

    def initalizeMethodList(self):
    
        self.methodList = {}
        
        self.register(
            endpoint = "/characters/{character_id}/", 
            method = "characters",
            requiredArguments = ["character_id"]
        )
        
        self.register(
            endpoint = "/characters/{character_id}/location/", 
            method = "character_locations",
            requiredArguments = ["character_id"]
        )
        
        self.register(
            endpoint = "/characters/affiliation/", 
            method = "character_affiliations",
            requiredArguments = ["characters"]
        )
        
        self.register(
            endpoint = "/universe/names/", 
            method = "universe_names",
            requiredArguments = ["ids"]
        )

        self.register(
            endpoint = "/universe/categories/", 
            method = "universe_categories_list",
            requiredArguments = []
        )

        self.register(
            endpoint = "/universe/categories/{category_id}/", 
            method = "universe_categories",
            requiredArguments = ["category_id"]
        )

        self.register(
            endpoint = "/universe/groups/", 
            method = "universe_groups_list",
            requiredArguments = ["page"]
        )

        self.register(
            endpoint = "/universe/groups/{group_id}/", 
            method = "universe_groups",
            requiredArguments = ["group_id"]
        )
    
    def register(self, endpoint, method, requiredArguments):
    
        self.methodList[endpoint] = {"Name": method, "Required Arguments": requiredArguments}
    