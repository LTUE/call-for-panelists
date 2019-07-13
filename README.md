## Goals

* Register and login system, so they can resume
* Multiple pages
* Qualifications

## Implementation Notes

We're using a router script so there's a single entry point, to reduce boilerplate. We're using surrogate keys in the database to support renaming.

## Local Testing

Make sure you have database access from your home IP. Create an environment.conf file with the `DB*` constants. Run with `php -S localhost:8000 -c php.ini`.

To import, run something like `tail -n +2 LTUE\ 2020\ Call.csv | php -c php.ini import.php`.

## TODO

* CSRF on all forms
* Pull in real data from the spreadsheets
* Social logins
* Email - to confirm accounts, and
* Password reset
* Picture upload
* The books/presentation requests
* Input hours available, limiting shown panels to those
* Test with bulk data

### Wishlist

* Backoffice
    * Room assignments and scheduling in system
    * panel entry in system
    * panelist notes, also per panel for reporting afterwards

### Open issues

* Site copy is sometimes somewhat insulting
* Per-panel input notes?
* Why only three books/panel ideas/etc?
* Why so limited availability input?
* No search interface for tags
    * Show how many a tag hits, allow piling on tags
    * A view for "panels I've expressed interested in"
* Production data has too few tags to effectively slice-n-dice
