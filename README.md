# Eve Overseer V2

Eve Overseer is a fleet activity and participation tracking application for Eve Online, usable with Brave Neucore. 

**Current Version: Ensign – 2 – 0**

## Requirements

This application requires the following:

* Apache ≥ 2.4
  * The `DocumentRoot` config option to set `/public`
  * The `FallbackResource` config option set to `/index.php`
* PHP ≥ 8.1
  * The `curl` Built-In Extension
  * The `pdo_mysql` Built-In Extension
  * The `openssl` Built-In Extension
* Python ≥ 3.11
  * [requests](https://pypi.org/project/requests/)
  * [schedule](https://pypi.org/project/schedule/)
  * [mysql-connector-python](https://pypi.org/project/mysql-connector-python/)
* An SQL Server
  * If you are using MySQL, the Authentication Method **MUST** be the Legacy Version. PDO does not support the use of `caching_sha2_password` Authentication.
* A Registered Eve Online Application with the `esi-search.search_structures.v1`, `esi-corporations.read_corporation_membership.v1`, `esi-fleets.read_fleet.v1`, and `esi-fleets.write_fleet.v1` scopes.
  * This can be setup via the [Eve Online Developers Site](https://developers.eveonline.com/).
* [When Using The Neucore Authentication Method] A Neucore Application
  * The application needs the `app-chars` and `app-groups` roles added, along with any groups that you want to be able to set access roles for.
