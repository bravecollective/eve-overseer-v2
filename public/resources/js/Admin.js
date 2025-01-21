jQuery(document).ready(function () {
    
    var csrfToken = $("meta[name='csrftoken']").attr("content");
    
    $.ajaxSetup({
        beforeSend: function (request) {
            request.setRequestHeader("CSRF-Token", csrfToken);
        }
    });
    
    $("#search-button").click(function () {
        
        getSearchResults();
        
    });

    $("#entity-access-search-button").click(function () {
        
        getSearchResults("entity-access-");
        
    });
    
    $(document).on("click", ".acl-add-button", function () {
        
        addGroup(
            $(this).attr("data-group-type"), 
            $(this).attr("data-group-id"), 
            $(this).attr("data-group-name")
        );
        
    });

    $(document).on("click", ".entity-add-button", function () {
        
        addEntity(
            $(this).attr("data-group-type"), 
            $(this).attr("data-group-id"), 
            $(this).attr("data-group-name")
        );
        
    });
    
    $(document).on("click", ".acl-delete-button", function () {
        
        removeGroup(
            $(this).attr("data-type"), 
            $(this).attr("data-group")
        );
        
    });

    $(document).on("click", ".entity-delete-button", function () {
        
        removeEntity(
            $(this).attr("data-type"), 
            $(this).attr("data-id")
        );
        
    });
    
    $(document).on("click", ".acl-switch", function () {
        
        updateGroup(
            $(this).attr("data-type"), 
            $(this).attr("data-group"),
            $(this).attr("data-role"),
            $(this).attr("id")
        );
        
    });

    $(document).on("click", ".entity-acl-switch", function () {
        
        updateEntity(
            $(this).attr("data-entity-type"), 
            $(this).attr("data-entity-id"), 
            $(this).attr("data-group-type"), 
            $(this).attr("data-group-id"), 
            $(this).attr("id")
        );
        
    });

    $("#new-fleet-type-button").click(function () {
        
        createFleetType(
            $("#new-fleet-type").val()
        );
        
    });

    $(".delete-fleet-type-button").click(function () {
        
        deleteFleetType(
            $(this).attr("data-fleet-type"), 
            $(this).attr("id")
        );
        
    });


    $(document).on("click", ".fleet-acl-switch", function () {
        
        updateFleetTypeAccess(
            $(this).attr("data-fleet-type"), 
            $(this).attr("data-group-type"),
            $(this).attr("data-group"),
            $(this).attr("data-access-type"),
            $(this).attr("id")
        );
        
    });
    
});

function removeEmptySections(prefix = "") {
    
    for (eachType of ["character", "corporation", "alliance"]) {
        
        if ($("#" + prefix + eachType + "-group-list").length && !$("#" + prefix + eachType + "-group-list").children().length) {
            
            $("#" + prefix + eachType + "-group-header").remove();
            $("#" + prefix + eachType + "-group-list").remove();
            
        }
        
    }
    
}

function addSectionIfMissing(upperCaseType, prefix = "", sectionSuffix = "") {
    
    type = upperCaseType.toLowerCase()
    precedingSection = false
    
    for (eachType of ["character", "corporation", "alliance"]) {
        
        if ($("#" + prefix + eachType + "-group-list").length) {
            
            precedingSection = eachType;
            
        }
        else if (eachType === type) {
            
            if (!precedingSection) {
                
                $("#" + prefix + "groups-column").prepend(
                    $("<div/>")
                        .attr("id", prefix + type + "-group-list")
                )
                .prepend(
                    $("<h3/>")
                        .addClass("text-light")
                        .attr("id", prefix + type + "-group-header")
                        .text(upperCaseType + sectionSuffix + " Groups")
                );
                
            }
            else {
                
                $("#" + prefix + precedingSection + "-group-list").after(
                    $("<h3/>")
                        .addClass("text-light")
                        .attr("id", prefix + type + "-group-header")
                        .text(upperCaseType + sectionSuffix + " Groups")
                )
                $("#" + prefix + type + "-group-header").after(
                    $("<div/>")
                        .attr("id", prefix + type + "-group-list")
                );
                
            }
            
        }
        
    }
    
}

function generateImageLink(type, id) {
    
    imageType = type.toLowerCase() + "s";
    imageSource = "https://images.evetech.net/" + imageType + "/" + id + "/";
    
    if (imageType === "characters") {
        
        imageSource += "portrait";
        
    }
    else {
        
        imageSource += "logo";
        
    }
    
    imageSource += "?size=128";
    
    return imageSource;
    
}

function renderSearchResult(type, id, name, for_permissions) {

    button_class = (for_permissions) ? "acl-add-button" : "entity-add-button";
    button_name = (for_permissions) ? "Add to ACL" : "Add Entity";
    results_id = (for_permissions) ? "#group-search-results" : "#entity-access-group-search-results";
    
    $(results_id).append(
        $("<div/>")
            .addClass("card text-white bg-dark mt-3")
            .append(
                $("<div/>")
                    .addClass("row g-0")
                    .append(
                        $("<div/>")
                            .addClass("col-3")
                            .append(
                                $("<img>")
                                    .addClass("img-fluid rounded-start")
                                    .attr("src", generateImageLink(type, id))
                            )
                    )
                    .append(
                        $("<div/>")
                            .addClass("col-9")
                            .append(
                                $("<div/>")
                                    .addClass("card-header")
                                    .text(name)
                            )
                            .append(
                                $("<div/>")
                                    .addClass("card-body d-grid")
                                    .append(
                                        $("<button/>")
                                            .addClass("btn btn-success " + button_class)
                                            .attr("type", "button")
                                            .attr("data-group-type", type)
                                            .attr("data-group-id", eachResult)
                                            .attr("data-group-name", name)
                                            .text(button_name)
                                    )
                            )
                    )
            )
    );
    
}

function getSearchResults(prefix = "") {
    
    $("#" + prefix + "search-button").attr("hidden", true);
    $("#" + prefix + "search-spinner").removeAttr("hidden");
    $("#" + prefix + "group-search-results").empty();
    
    dataObject = {
        "Action": "Search", 
        "Type": $("#" + prefix + "type-selection").val(), 
        "Term": $("#" + prefix + "name-selection").val(), 
        "Strict": $("#" + prefix + "strict-selection").is(":checked")
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            for (eachResult in result["Entities"]) {
                
                renderSearchResult(result["Type"], eachResult, result["Entities"][eachResult], !prefix);
                
            }
            
            $("#" + prefix + "search-spinner").attr("hidden", true);
            $("#" + prefix + "search-button").removeAttr("hidden");
            
        },
        error: function(result) {
            
            $("#" + prefix + "group-search-results").append(
                $("<div/>")
                    .addClass("alert alert-warning")
                    .text("No Search Results Found")
            );
            
            $("#" + prefix + "search-spinner").attr("hidden", true);
            $("#" + prefix + "search-button").removeAttr("hidden");
            
        }
    });
    
}

function addGroup(type, id, name) {
    
    $("button[data-group-id='" + id + "'][data-group-type='" + type + "']").prop("disabled", true);
    
    dataObject = {
        "Action": "Add_Group", 
        "Type": type, 
        "ID": id, 
        "Name": name
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "html",
        success: function(result) {
            
            addSectionIfMissing(type);
            $("#" + type.toLowerCase() + "-group-list").append(result);
            
            $("button[data-group-id='" + id + "'][data-group-type='" + type + "']").text("Added Successfully");
            location.reload();
            
        },
        error: function(result) {
            
            $("button[data-group-id='" + id + "'][data-group-type='" + type + "']")
            .removeClass("btn-success")
            .addClass("btn-danger")
            .text("Failed To Add");
            
        }
    });
    
}

function addEntity(type, id, name) {
    
    $("button[data-group-id='" + id + "'][data-group-type='" + type + "']").prop("disabled", true);
    
    dataObject = {
        "Action": "Add_Entity", 
        "Type": type, 
        "ID": id, 
        "Name": name
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "html",
        success: function(result) {
            
            addSectionIfMissing(type, "entity-access-", " Entity");
            $("#entity-access-" + type.toLowerCase() + "-group-list").append(result);
            
            $("button[data-group-id='" + id + "'][data-group-type='" + type + "']").text("Added Successfully");
            
        },
        error: function(result) {
            
            $("button[data-group-id='" + id + "'][data-group-type='" + type + "']")
            .removeClass("btn-success")
            .addClass("btn-danger")
            .text("Failed To Add");
            
        }
    });
    
}

function removeGroup(type, id) {
    
    $("input[data-group='" + id + "'][data-type='" + type + "']").prop("disabled", true);
    $("button[data-group='" + id + "'][data-type='" + type + "']").prop("disabled", true);
    
    dataObject = {
        "Action": "Remove_Group", 
        "Type": type, 
        "ID": id
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $(".access-card[data-group='" + id + "'][data-type='" + type + "']").remove();
            removeEmptySections();
            location.reload();
            
        },
        error: function(result) {
            
            $("input[data-group='" + id + "'][data-type='" + type + "']").prop("disabled", false);
            $("button[data-group='" + id + "'][data-type='" + type + "']").prop("disabled", false);
            
        }
    });
    
}

function removeEntity(type, id) {
    
    $(".entity-acl-switch[data-id='" + id + "'][data-type='" + type + "']").prop("disabled", true);
    $(".entity-delete-button[data-id='" + id + "'][data-type='" + type + "']").prop("disabled", true);
    
    dataObject = {
        "Action": "Remove_Entity", 
        "Type": type, 
        "ID": id
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $(".entity-access-card[data-id='" + id + "'][data-type='" + type + "']").remove();
            removeEmptySections("entity-access-");
            
        },
        error: function(result) {
            
            $(".entity-acl-switch[data-id='" + id + "'][data-type='" + type + "']").prop("disabled", false);
            $(".entity-delete-button[data-id='" + id + "'][data-type='" + type + "']").prop("disabled", false);
            
        }
    });
    
}

function updateGroup(type, id, role, switch_id) {
    
    $("#" + switch_id).prop("disabled", true);
    
    dataObject = {
        "Action": "Update_Group", 
        "Type": type, 
        "ID": id, 
        "Role": role, 
        "Change": ($("#" + switch_id).is(":checked") ? "Added" : "Removed")
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#" + switch_id).prop("disabled", false);
            
        },
        error: function(result) {
            
            
            
        }
    });
    
}

function updateEntity(type, id, group_type, group_id, switch_id) {
    
    $("#" + switch_id).prop("disabled", true);
    
    dataObject = {
        "Action": "Update_Entity", 
        "Type": type, 
        "ID": id, 
        "Group_Type": group_type, 
        "Group_ID": group_id, 
        "Change": ($("#" + switch_id).is(":checked") ? "Added" : "Removed")
    };
    
    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#" + switch_id).prop("disabled", false);
            
        },
        error: function(result) {
            
            
            
        }
    });
    
}

function createFleetType(fleet_type_name) {

    $("#new-fleet-type-button").prop("disabled", true);

    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: {"Action": "Create_Fleet_Type", "Fleet_Type_Name": fleet_type_name},
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            window.location.reload();
            return false;
            
        },
        error: function(result) {
            
            
            
        }
    });

}

function deleteFleetType(fleet_type_id, button_id) {

    $("#" + button_id).prop("disabled", true);

    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: {"Action": "Delete_Fleet_Type", "Fleet_Type_ID": fleet_type_id},
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            window.location.reload();
            return false;
            
        },
        error: function(result) {
            
            
            
        }
    });

}

function updateFleetTypeAccess(fleet_type_id, role_type, role_id, access_type, switch_id) {

    $("#" + switch_id).prop("disabled", true);
    
    dataObject = {
        "Action": "Update_Fleet_Type_Access", 
        "Fleet_Type_ID": fleet_type_id, 
        "Role_Type": role_type, 
        "Role_ID": role_id, 
        "Access_Type": access_type,
        "Change": ($("#" + switch_id).is(":checked") ? "Add" : "Remove")
    };

    $.ajax({
        url: "/admin/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#" + switch_id).prop("disabled", false);
            
        },
        error: function(result) {
            
            
            
        }
    });

}
