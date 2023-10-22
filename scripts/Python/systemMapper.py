import requests
import time
import json
import traceback

import concurrent.futures as taskmaster

newSystems = []
newConstellations = []
newRegions = []
newStargates = []
systems = {}
constellations = {}
regions = {}
stargates = {}

print("[{timestamp}] - Searching for System IDs...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z")))

while True:
    esiCall = requests.get("https://esi.evetech.net/latest/universe/systems/?datasource=tranquility")
    foundList = esiCall.json()

    if ("error" in foundList and foundList["error"] == "Undefined 404 response. Original message: Requested page does not exist!") or foundList == []:
        break

    for eachID in foundList:
        newSystems.append(int(eachID))

    break

def getSystemInformation(systemID):

        #Threads fail silently, so this at least generates tracebacks when there's a problem.
        try:

            while True:

                systemRequest = requests.get("https://esi.evetech.net/latest/universe/systems/" + str(systemID) + "/?datasource=tranquility&language=en")

                if systemRequest.status_code == requests.codes.ok:

                    systemData = systemRequest.json()

                    break

                else:

                    if "x-esi-error-limit-remain" in systemRequest.headers and "x-esi-error-limit-reset" in systemRequest.headers:

                        try:

                            errorData = systemRequest.json()
                            print(str(systemRequest.status_code) + " Error while getting system information with ID " + str(systemID) + ". \nError Text: " + str(errorData["error"]) + "\nErrors Remaining: " + str(systemRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(systemRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        except:

                            print(str(systemRequest.status_code) + " Error while getting system information with ID " + str(systemID) + ".\nErrors Remaining: " + str(systemRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(systemRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        if int(systemRequest.headers["x-esi-error-limit-remain"]) <= 80:

                            print("WARNING! Error remaining has gone below safe threshold! Waiting it out...\n\n")
                            time.sleep(int(systemRequest.headers["x-esi-error-limit-reset"]) + 2)

                    else:

                        print(str(systemRequest.status_code) + " Error while getting system information with ID " + str(systemID) + ". Waiting a few seconds...\n\n")

                        time.sleep(5)


            if "stargates" in systemData:
                for eachGate in systemData["stargates"]:
                    newStargates.append(int(eachGate))

            systems[int(systemID)] = systemData
            newConstellations.append(int(systemData["constellation_id"]))

        except:
            traceback.print_exc()

def getConstellationInformation(constellationID):

        #Threads fail silently, so this at least generates tracebacks when there's a problem.
        try:

            while True:

                constellationRequest = requests.get("https://esi.evetech.net/latest/universe/constellations/" + str(constellationID) + "/?datasource=tranquility&language=en")

                if constellationRequest.status_code == requests.codes.ok:

                    constellationData = constellationRequest.json()

                    break

                else:

                    if "x-esi-error-limit-remain" in constellationRequest.headers and "x-esi-error-limit-reset" in constellationRequest.headers:

                        try:

                            errorData = constellationRequest.json()
                            print(str(constellationRequest.status_code) + " Error while getting constellation information with ID " + str(constellationID) + ". \nError Text: " + str(errorData["error"]) + "\nErrors Remaining: " + str(constellationRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(constellationRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        except:

                            print(str(constellationRequest.status_code) + " Error while getting constellation information with ID " + str(constellationID) + ".\nErrors Remaining: " + str(constellationRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(constellationRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        if int(constellationRequest.headers["x-esi-error-limit-remain"]) <= 80:

                            print("WARNING! Error remaining has gone below safe threshold! Waiting it out...\n\n")
                            time.sleep(int(constellationRequest.headers["x-esi-error-limit-reset"]) + 2)

                    else:

                        print(str(constellationRequest.status_code) + " Error while getting constellation information with ID " + str(constellationID) + ". Waiting a few seconds...\n\n")

                        time.sleep(5)

            constellations[int(constellationID)] = constellationData
            newRegions.append(int(constellationData["region_id"]))

        except:
            traceback.print_exc()

def getRegionInformation(regionID):

        #Threads fail silently, so this at least generates tracebacks when there's a problem.
        try:

            while True:

                regionRequest = requests.get("https://esi.evetech.net/latest/universe/regions/" + str(regionID) + "/?datasource=tranquility&language=en")

                if regionRequest.status_code == requests.codes.ok:

                    regionData = regionRequest.json()

                    break

                else:

                    if "x-esi-error-limit-remain" in regionRequest.headers and "x-esi-error-limit-reset" in regionRequest.headers:

                        try:

                            errorData = regionRequest.json()
                            print(str(regionRequest.status_code) + " Error while getting region information with ID " + str(regionID) + ". \nError Text: " + str(errorData["error"]) + "\nErrors Remaining: " + str(regionRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(regionRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        except:

                            print(str(regionRequest.status_code) + " Error while getting region information with ID " + str(regionID) + ".\nErrors Remaining: " + str(regionRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(regionRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        if int(regionRequest.headers["x-esi-error-limit-remain"]) <= 80:

                            print("WARNING! Error remaining has gone below safe threshold! Waiting it out...\n\n")
                            time.sleep(int(regionRequest.headers["x-esi-error-limit-reset"]) + 2)

                    else:

                        print(str(regionRequest.status_code) + " Error while getting region information with ID " + str(regionID) + ". Waiting a few seconds...\n\n")

                        time.sleep(5)

            regions[int(regionID)] = regionData

        except:
            traceback.print_exc()

def getStargateInformation(stargateID):

        #Threads fail silently, so this at least generates tracebacks when there's a problem.
        try:

            while True:

                stargateRequest = requests.get("https://esi.evetech.net/latest/universe/stargates/" + str(stargateID) + "/?datasource=tranquility&language=en")

                if stargateRequest.status_code == requests.codes.ok:

                    stargateData = stargateRequest.json()

                    break

                else:

                    if "x-esi-error-limit-remain" in stargateRequest.headers and "x-esi-error-limit-reset" in stargateRequest.headers:

                        try:

                            errorData = stargateRequest.json()
                            print(str(stargateRequest.status_code) + " Error while getting stargate information with ID " + str(stargateID) + ". \nError Text: " + str(errorData["error"]) + "\nErrors Remaining: " + str(stargateRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(stargateRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        except:

                            print(str(stargateRequest.status_code) + " Error while getting stargate information with ID " + str(stargateID) + ".\nErrors Remaining: " + str(stargateRequest.headers["x-esi-error-limit-remain"]) + "\nErrors Reset: " + str(stargateRequest.headers["x-esi-error-limit-reset"]) + " Seconds\n\n")

                        if int(stargateRequest.headers["x-esi-error-limit-remain"]) <= 80:

                            print("WARNING! Error remaining has gone below safe threshold! Waiting it out...\n\n")
                            time.sleep(int(stargateRequest.headers["x-esi-error-limit-reset"]) + 2)

                    else:

                        print(str(stargateRequest.status_code) + " Error while getting stargate information with ID " + str(stargateID) + ". Waiting a few seconds...\n\n")

                        time.sleep(5)

            stargates[int(stargateID)] = stargateData

        except:
            traceback.print_exc()

print("[{timestamp}] - Found {systems} systems! Processing...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z"), systems=len(newSystems)))

with taskmaster.ThreadPoolExecutor(max_workers=75) as workPool:
    results = workPool.map(getSystemInformation, newSystems)

newConstellations = list(set(newConstellations))

print("[{timestamp}] - Found {constellations} constellations! Processing...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z"), constellations=len(newConstellations)))

with taskmaster.ThreadPoolExecutor(max_workers=75) as workPool:
    results = workPool.map(getConstellationInformation, newConstellations)

newRegions = list(set(newRegions))

print("[{timestamp}] - Found {regions} regions! Processing...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z"), regions=len(newRegions)))

with taskmaster.ThreadPoolExecutor(max_workers=75) as workPool:
    results = workPool.map(getRegionInformation, newRegions)

newStargates = list(set(newStargates))

print("[{timestamp}] - Found {stargates} stargates! Processing...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z"), stargates=len(newStargates)))

with taskmaster.ThreadPoolExecutor(max_workers=75) as workPool:
    results = workPool.map(getStargateInformation, newStargates)

print("[{timestamp}] - Aggregating Data...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z")))

for eachSystem in systems:
    systems[eachSystem]["constellation"] = constellations[int(systems[eachSystem]["constellation_id"])]["name"]

    systems[eachSystem]["region_id"] = int(constellations[int(systems[eachSystem]["constellation_id"])]["region_id"])

    systems[eachSystem]["region"] = regions[int(constellations[int(systems[eachSystem]["constellation_id"])]["region_id"])]["name"]

    if "stargates" in systems[eachSystem]:
        systems[eachSystem]["connected_system_ids"] = []
        systems[eachSystem]["connected_systems"] = []

        for eachStargate in systems[eachSystem]["stargates"]:
            systems[eachSystem]["connected_system_ids"].append(int(stargates[int(eachStargate)]["destination"]["system_id"]))

            systems[eachSystem]["connected_systems"].append(systems[int(stargates[int(eachStargate)]["destination"]["system_id"])]["name"])

print("[{timestamp}] - Exporting Data...".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z")))

with open("Static/geographicInformationV3.json", "w") as outputFile:
    json.dump(systems, outputFile, indent=1)

print("[{timestamp}] - Done!".format(timestamp=time.strftime("%B %d, %Y - %H:%M:%S %z")))
