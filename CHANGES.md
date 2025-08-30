# Changelog

Changes for each version along with any requirements to update from the previous version will be listed below.

## Patch Version Ensign – 2 – 1 Update

### Player Participation

* Fleet List now adheres to Recency Boundaries.
* Fixed a bug that caused the Fleet List to only display one line per fleet.

### ESI

* Changed versioning scheme to the new X-Compatibility-Date.
* Fixed deprecated implicitly nullable argument in Ridley\Objects\ESI\Base.

### User Input Exceptions
* Fixed deprecated implicitly nullable argument in Ridley\Core\Exceptions\UserInputException.

### UPDATE INSTRUCTIONS (From Version Ensign – 0 – *)

1. Pause operation of the Fleet Checker.
2. Sync up files with the repository.
3. Restart operation of the Fleet Checker.

## Minor Version Ensign – 2 – 0 Update

### Player Participation

* Added an account details modal, displaying known characters and a list of recent fleets. 

### Fleet Stats

* Added a dedicated role for deleting fleets.

### Optimization

* Moved Corporation Tracking operations to a dedicated class.
* Moved some logic determining Participation data access to a dedicated class.
* Moved some logic determining Fleet Type access to a dedicated class.

### Bug Fixes

* Fixed an issue where the alliance selector in Player / Alliance Population would fail to populate.
* Corporation Trackers are no longer removed after Names Call Failures.

### UPDATE INSTRUCTIONS (From Version Ensign – 0 – *)

1. Pause operation of the Fleet Checker.
2. Sync up files with the repository.
3. Restart operation of the Fleet Checker.

## Minor Version Ensign – 1 – 0 Update

### Fleet Stats

* Added a member details modal with:
    * A timeline of Position in Fleet, Ships Used, and Systems Visited
    * An Event Log of the aforementioned

### Bug Fixes

* Fixed an asynchronous issue with tracking multiple shared fleets.
* Fixed an issue where the tracking page would fail to track a fleet with the status "Starting". 

### UPDATE INSTRUCTIONS (From Version Ensign – 0 – *)

1. Pause operation of the Fleet Checker.
2. Sync up files with the repository.
3. Restart operation of the Fleet Checker.

## Patch Version Ensign – 0 – 1 Update

### Tracking

* The Share Fleet checkbox is now checked by default.
* Better feedback is given when a fleet can't be tracked.
* Better feedback is given when a fleet has ceased tracking.

### Bug Fixes

* The Alliance Participation page now shows correct member counts.
* Fixed Login Links on the Player and Alliance Participation Pages.
* Made changes to the tracking interface to prevent API call spam.
* Fleets now must have a name to be tracked.

### UPDATE INSTRUCTIONS (From Version Ensign – 0 – *)

1. Pause operation of the Fleet Checker.
2. Sync up files with the repository.
3. Restart operation of the Fleet Checker.