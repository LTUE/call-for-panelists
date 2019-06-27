## Goals

* Login system, so they can resume
* Multiple pages
* Qualifications

### Specific questions

* May we share your contact info with other panelists?
    * contact info
* Name
* Email (for account)
* Panel interests
    * Need to filter by tag, show decription

## Implementation Notes

We're using a router script so there's a single entry point, to reduce boilerplate. We're using surrogate keys in the database to support renaming.

## Local Testing

Make sure you have database access from your home IP. Create an environment.conf file with the `DB*` constants. Run with `php -S localhost:8000 -c php.ini`.

## TODO

* CSRF on all forms
* Pull in real data from the spreadsheets
