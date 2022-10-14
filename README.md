# ProSonata Backup

This tool creates a backup of all important data on your ProSonata workspace.

## Included data

- addresses
- contacts
- customers
- externalcosts
- projects
- projecttasks
- projecttimecategories
- projecttimes
- users
- workingtimes

## Security & Privacy

To keep the data safe, this tool can be used on any machine with an active internet connection and PHP installed. The machine running the tool needs to be able to communicate to the ProSonata API, but doesn't need to be reachable from the internet.

The created archives (data dumps) are stored on the machine running the tool and it's up to you to store them somewhere secure.

## Rate Limit

Depending on the ProSonata subscription, there may be different limits:

|Package|Allowed requests per 15 min.|
|-|-|
|ProSonata 1|50|
|ProSonata 3|100|
|ProSonata 5|200|
|ProSonata 10|300|
|ProSonata 20|500|

## Requirements

The device running the tool must be able to connect to the ProSonata API (internet connection required) and needs to have PHP installed.

It has been tested with PHP 8.0, it may work with newer versions as well. It's recommended to not use versions older than 7.4.

## Setup

1. Clone this repository or download the source code via GitHub.
1. Copy `config.example.php` to `config.php` and configure your workspace:
  1. Specify the ProSonata URL (e.g. https://yourcompany.prosonata.software)
  1. Create a new custom integration with your ProSonata Admin Account and add the appID and apiKey to the config
1. Create a folder called `archives`. If you want to use a different folder, customize it in `config.php`

## Create a Backup

To create a backup, simply run the script:

```bash
php backup.php
```

If the last backup isn't complete (e.g. because of the rate limit), it'll be continued. If the last backup is complete, a new one will be created.

It's recommended to run the script every 10 to 15 minutes. To prevent issus with multiple processes at the same time, a running process will stop after 10 minutes.

## Backup format

Each backup is one SQLite database, organized in tables and rows. To view the data, use a GUI for SQLite. A web-based GUI is currently not available, but might be added in the future.

---

&copy; 2022, Krauss Kommunikation GmbH
